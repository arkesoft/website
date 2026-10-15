<?php
declare(strict_types=1);

/** Small, single-recipient text-only SMTP transport; TLS is mandatory. */
final class ArkesoftSmtp {
    private $stream=null;
    private float $deadline;
    private function timeout(): void {
        $remaining=$this->deadline-microtime(true);
        if($remaining<=0)throw new RuntimeException('SMTP işlem zaman aşımı.');
        $seconds=min(5.0,$remaining);stream_set_timeout($this->stream,(int)$seconds,(int)(($seconds-(int)$seconds)*1000000));
    }
    private function write(string $bytes): void {
        $offset=0;$length=strlen($bytes);
        while($offset<$length){$this->timeout();$written=@fwrite($this->stream,substr($bytes,$offset));if(!$written)throw new RuntimeException('SMTP bağlantısına yazılamadı.');$offset+=$written;}
    }
    private function response(array $expected,string $stage): string {
        $reply='';$code=null;
        for($lines=0;$lines<100;$lines++){
            $this->timeout();
            $line=@fgets($this->stream,1024);
            if($line===false)throw new RuntimeException('SMTP yanıtı alınamadı: '.$stage.'.');
            if(!preg_match('/^(\d{3})([ -])([^\r\n]*)\r?\n$/D',$line,$m)||($code!==null&&$code!==(int)$m[1]))throw new RuntimeException('SMTP yanıt biçimi geçersiz: '.$stage.'.');
            $code=(int)$m[1];$reply.=$line;
            if($m[2]===' '){if(!in_array($code,$expected,true))throw new RuntimeException('SMTP '.$stage.' başarısız (kod '.$code.').');return $reply;}
        }
        throw new RuntimeException('SMTP yanıtı çok uzun.');
    }
    private function command(string $command,array $codes,string $stage): string {$this->write($command."\r\n");return $this->response($codes,$stage);}
    public function send(array $config,string $to,string $subject,string $body,?string $replyTo,string $messageId): bool {
        if($issues=mail_configuration_issues($config))throw new RuntimeException(implode(' ',$issues));
        $from=$config['mail_from'];
        foreach([$from,$to,...($replyTo?[$replyTo]:[])] as $address)if(!filter_var($address,FILTER_VALIDATE_EMAIL)||preg_match('/[^\x21-\x7E]|[<>]/',$address))throw new RuntimeException('SMTP e-posta adresi geçersiz.');
        if(!preg_match('/^<[a-zA-Z0-9._@-]+>$/D',$messageId))throw new RuntimeException('SMTP ileti kimliği geçersiz.');
        if(strlen($subject)>300||strlen($config['mail_from_name']??'')>150||strlen($body)>200000)throw new RuntimeException('SMTP ileti boyutu sınırı aşıldı.');
        $host=$config['smtp_host'];$port=(int)$config['smtp_port'];$mode=$config['smtp_encryption'];
        $ssl=['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false,'peer_name'=>$host,'SNI_enabled'=>true,'crypto_method'=>STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT|STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT];
        if(!empty($config['smtp_ca_file']))$ssl['cafile']=$config['smtp_ca_file'];
        $context=stream_context_create(['ssl'=>$ssl]);$this->deadline=microtime(true)+20;
        $this->stream=@stream_socket_client(($mode==='ssl'?'tls':'tcp').'://'.$host.':'.$port,$errorCode,$errorMessage,10,STREAM_CLIENT_CONNECT,$context);
        if(!$this->stream)throw new RuntimeException('SMTP sunucusuna güvenli bağlantı kurulamadı.');
        $this->timeout();
        try {
            $this->response([220],'karşılama');$capabilities=$this->command('EHLO arkesoft.local',[250],'EHLO');
            if($mode==='tls'){
                if(!preg_match('/^250[ -]STARTTLS(?:\s|$)/mi',$capabilities))throw new RuntimeException('SMTP sunucusu STARTTLS sunmuyor.');
                $this->command('STARTTLS',[220],'STARTTLS');
                $this->timeout();
                if(@stream_socket_enable_crypto($this->stream,true,$ssl['crypto_method'])!==true)throw new RuntimeException('SMTP TLS doğrulaması başarısız.');
                $capabilities=$this->command('EHLO arkesoft.local',[250],'TLS sonrası EHLO');
            }
            // Credentials are never sent before a verified encrypted connection.
            if(preg_match('/^250[ -]AUTH(?:=|\s)[^\r\n]*\bLOGIN\b/mi',$capabilities)){
                $this->command('AUTH LOGIN',[334],'kimlik doğrulama');
                $this->command(base64_encode($config['smtp_user']),[334],'kullanıcı doğrulama');
                $this->command(base64_encode($config['smtp_pass']),[235],'parola doğrulama');
            }elseif(preg_match('/^250[ -]AUTH(?:=|\s)[^\r\n]*\bPLAIN\b/mi',$capabilities))$this->command('AUTH PLAIN '.base64_encode("\0".$config['smtp_user']."\0".$config['smtp_pass']),[235],'kimlik doğrulama');
            else throw new RuntimeException('SMTP sunucusu desteklenen kimlik doğrulama yöntemi sunmuyor.');
            $this->command('MAIL FROM:<'.$from.'>',[250],'gönderici');$this->command('RCPT TO:<'.$to.'>',[250,251],'alıcı');$this->command('DATA',[354],'ileti başlangıcı');
            $mime=static fn(string $value):string=>mb_encode_mimeheader($value,'UTF-8','B',"\r\n");
            $name=$config['mail_from_name']??'';
            $displayName=preg_match('/^[\x20-\x7E]*$/D',$name)?'"'.addcslashes($name,'\\"').'"':$mime($name);
            $headers=['Date: '.gmdate('D, d M Y H:i:s').' +0000','From: '.($name!==''?$displayName.' ':'').'<'.$from.'>','To: <'.$to.'>','Subject: '.$mime(str_replace(["\r","\n"],' ',$subject)),'Message-ID: '.$messageId,'MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','Content-Transfer-Encoding: base64'];
            if($replyTo)$headers[]='Reply-To: <'.$replyTo.'>';
            $normalized=str_replace(["\r\n","\r"],"\n",$body);$normalized=str_replace("\n","\r\n",$normalized);
            $message=implode("\r\n",$headers)."\r\n\r\n".chunk_split(base64_encode($normalized),76,"\r\n");
            // Base64 text contains no dot-prefixed lines, so SMTP DATA terminators cannot be injected.
            $this->write($message.".\r\n");$this->response([250],'ileti kabulü');
            // Acceptance is final even when QUIT/connection teardown fails afterwards.
            @fwrite($this->stream,"QUIT\r\n");return true;
        }finally{if(is_resource($this->stream))fclose($this->stream);$this->stream=null;}
    }
}
