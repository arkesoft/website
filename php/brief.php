<?php
// Brief / teklif formu gönderimi (Apache/PHP hosting, ör. Hostinger).
// .htaccess, /api/brief isteğini bu dosyaya yönlendirir.
// Şifreler bu dosyada DEĞİL, .env dosyasında durur (bkz. .env.example).
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $body): void {
  http_response_code($status);
  echo json_encode($body);
  exit;
}

function clean($value, int $max = 4000, bool $multiline = false): string {
  $text = is_scalar($value) ? (string) $value : '';
  $text = str_replace(["\r\n", "\r"], "\n", $text);
  $text = preg_replace($multiline ? '/[\x00-\x09\x0B-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', ' ', $text) ?? '';
  return mb_substr(trim($text), 0, $max, 'UTF-8');
}

function valid_email(string $value): bool {
  return (bool) preg_match('/^[^\s@<>,;"]+@[^\s@<>,;"]+\.[^\s@<>,;"]{2,}$/i', $value);
}

// Ayarlar .env dosyasından okunur. Önce public_html'in bir üst klasörü (önerilen,
// internetten erişilemez), sonra site kökü (.htaccess erişimi engeller), en son
// sunucu ortam değişkenleri. Örnek için .env.example dosyasına bakın.
function load_env(string $file): array {
  $values = [];
  if (!is_file($file) || !is_readable($file)) return $values;
  foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    $line = trim(preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? '');
    if ($line === '' || $line[0] === '#') continue;
    if (str_starts_with($line, 'export ')) $line = ltrim(substr($line, 7));
    $equals = strpos($line, '=');
    if ($equals === false) continue;
    $name = trim(substr($line, 0, $equals));
    $value = trim(substr($line, $equals + 1));
    if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $name)) continue;
    $quote = $value[0] ?? '';
    if (($quote === '"' || $quote === "'") && strlen($value) >= 2 && substr($value, -1) === $quote) {
      $value = substr($value, 1, -1);
      if ($quote === '"') $value = strtr($value, ['\\"' => '"', '\\\\' => '\\']);
    } else {
      $value = trim(preg_replace('/\s+#.*$/', '', $value) ?? '');
    }
    $values[$name] = $value;
  }
  return $values;
}
$config = load_env(dirname(__DIR__, 2) . '/.env') + load_env(dirname(__DIR__) . '/.env');
$setting = function (string $name, string $default = '') use ($config): string {
  if (isset($config[$name]) && $config[$name] !== '') return (string) $config[$name];
  $env = getenv($name);
  return ($env === false || $env === '') ? $default : (string) $env;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') respond(405, ['error' => 'method-not-allowed']);

// Yalnızca sitenin kendi sayfalarından gelen istekler.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
  $allowed = $setting('PUBLIC_ORIGIN');
  if ($allowed === '') {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $allowed = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
  }
  // Virgülle birden fazla adres yazılabilir: https://arkesoft.com,https://www.arkesoft.com
  $allowedList = array_map(fn($item) => rtrim(trim($item), '/'), explode(',', $allowed));
  if (!in_array(rtrim($origin, '/'), $allowedList, true)) respond(403, ['error' => 'origin-not-allowed']);
}

// IP başına saatte en fazla 5 gönderim.
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$limitFile = sys_get_temp_dir() . '/arkesoft-brief-' . hash('sha256', $ip) . '.json';
$now = time();
$attempts = [];
if (is_file($limitFile)) {
  $attempts = json_decode((string) @file_get_contents($limitFile), true) ?: [];
  $attempts = array_values(array_filter($attempts, fn($time) => is_int($time) && $now - $time < 3600));
}
if (count($attempts) >= 5) respond(429, ['error' => 'rate-limit']);

$to = $setting('BRIEF_TO', 'arkesoft.info@gmail.com');
$from = $setting('BRIEF_FROM');           // Örn. info@arkesoft.com (alan adındaki gerçek bir e-posta hesabı)
$fromName = $setting('BRIEF_FROM_NAME', 'Arkesoft');
$smtpHost = $setting('SMTP_HOST');         // Hostinger: smtp.hostinger.com
$smtpPort = (int) $setting('SMTP_PORT', '465');
$smtpUser = $setting('SMTP_USER', $from);
$smtpPass = $setting('SMTP_PASS');
if (!valid_email($to) || !valid_email($from)) respond(503, ['error' => 'not-configured']);

$payload = json_decode((string) file_get_contents('php://input', false, null, 0, 200000), true);
$items = is_array($payload) ? ($payload['items'] ?? null) : null;
if (!is_array($items) || !$items || count($items) > 20) respond(400, ['error' => 'bad-request']);

$replyTo = '';
$name = '';
$rows = [];
foreach ($items as $pair) {
  $label = clean(is_array($pair) ? ($pair[0] ?? '') : '', 120);
  $value = clean(is_array($pair) ? ($pair[1] ?? '') : '', 4000, true);
  $lower = mb_strtolower($label, 'UTF-8');
  if ($replyTo === '' && (str_contains($lower, 'e-posta') || str_contains($lower, 'email') || str_contains($lower, 'e-mail'))) $replyTo = $value;
  if ($name === '' && preg_match('/isim|name|ad soyad|marka|brand/iu', $lower)) $name = $value;
  $rows[] = [$label, $value];
}
if (!valid_email($replyTo)) respond(400, ['error' => 'invalid-email']);

$title = clean($payload['title'] ?? '', 200) ?: 'ARKESOFT — FORM';
$page = clean($payload['page'] ?? '', 300);
$subject = $name !== '' ? $title . ' · ' . mb_substr($name, 0, 80, 'UTF-8') : $title;
$sentAt = new DateTimeImmutable('now', new DateTimeZone('Europe/Istanbul'));
$siteHost = preg_replace('/[^a-z0-9.\-:]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?: substr(strrchr($from, '@'), 1);

$text = $title . "\n" . str_repeat('=', 40) . "\n\n"
  . implode("\n\n", array_map(fn($row) => $row[0] . "\n" . $row[1], $rows))
  . "\n\n" . str_repeat('-', 40) . "\nSayfa: https://" . $siteHost . $page
  . "\nTarih: " . $sentAt->format('d.m.Y H:i') . "\n"
  . "Yanıtla'ya basarak doğrudan müşteriye cevap verebilirsiniz.\n";
$html = email_html($title, $rows, $name, $replyTo, $siteHost, $page, $sentAt);

$attempts[] = $now;
@file_put_contents($limitFile, json_encode($attempts), LOCK_EX);

$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
$encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
$domain = substr(strrchr($from, '@'), 1);
$boundary = 'arkesoft-' . bin2hex(random_bytes(12));
$headers = [
  'Date: ' . date('r'),
  'From: ' . $encodedFromName . ' <' . $from . '>',
  'Reply-To: ' . $replyTo,
  'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
  'MIME-Version: 1.0',
  'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
];
$body = "This is a multi-part message in MIME format.\r\n\r\n"
  . '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
  . chunk_split(base64_encode($text), 76, "\r\n") . "\r\n"
  . '--' . $boundary . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
  . chunk_split(base64_encode($html), 76, "\r\n") . "\r\n"
  . '--' . $boundary . "--\r\n";

function h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// E-posta istemcileri için tablo tabanlı, satır içi stilli HTML şablon.
// Renkler sitenin paletinden: lacivert #0c1017, mavi-gri #6f8faf, kâğıt #ece9e1.
function email_html(string $title, array $rows, string $name, string $replyTo, string $host, string $page, DateTimeImmutable $sentAt): string {
  $sans = "font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;";
  $serif = "font-family:Georgia,'Times New Roman',serif;font-style:italic;";
  $replySubject = rawurlencode('Re: ' . $title);
  $pageUrl = 'https://' . $host . $page;
  $rowsHtml = '';
  foreach ($rows as $index => [$label, $value]) {
    $isLong = mb_strlen($value, 'UTF-8') > 90 || str_contains($value, "\n");
    $display = $value === '' ? '<span style="color:#9aa3ad;">—</span>' : nl2br(h($value), false);
    $border = $index === 0 ? '' : 'border-top:1px solid #e6e2d8;';
    if (str_contains(mb_strtolower($label, 'UTF-8'), 'posta') || str_contains(mb_strtolower($label, 'UTF-8'), 'mail')) {
      $display = '<a href="mailto:' . h($value) . '" style="color:#3d5f82;text-decoration:none;">' . h($value) . '</a>';
    }
    if ($isLong) {
      $rowsHtml .= '<tr><td colspan="2" style="' . $border . 'padding:18px 0 6px;' . $sans . 'font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#6f8faf;font-weight:bold;">' . h($label) . '</td></tr>'
        . '<tr><td colspan="2" style="padding:0 0 18px;"><div style="background:#f6f4ef;border-left:3px solid #6f8faf;padding:16px 18px;' . $sans . 'font-size:15px;line-height:1.65;color:#1b2230;">' . $display . '</div></td></tr>';
    } else {
      $rowsHtml .= '<tr>'
        . '<td valign="top" width="38%" style="' . $border . 'padding:15px 12px 15px 0;' . $sans . 'font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#6f8faf;font-weight:bold;">' . h($label) . '</td>'
        . '<td valign="top" style="' . $border . 'padding:13px 0;' . $sans . 'font-size:15px;line-height:1.5;color:#1b2230;">' . $display . '</td>'
        . '</tr>';
    }
  }
  $who = $name !== '' ? h($name) : h($replyTo);
  return '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>' . h($title) . '</title></head>'
    . '<body style="margin:0;padding:0;background:#ece9e1;">'
    . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . $who . ' — ' . h($replyTo) . '</div>'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#ece9e1;"><tr><td align="center" style="padding:32px 14px;">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;">'
    // Üst bant
    . '<tr><td style="background:#0c1017;padding:26px 32px;">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
    . '<td style="' . $sans . 'font-size:17px;letter-spacing:6px;color:#f5f3ee;font-weight:bold;">ARKESOFT</td>'
    . '<td align="right" style="' . $sans . 'font-size:9px;letter-spacing:2px;color:#6f8faf;text-transform:uppercase;">Yeni form talebi</td>'
    . '</tr></table></td></tr>'
    . '<tr><td style="background:#6f8faf;height:3px;line-height:3px;font-size:0;">&nbsp;</td></tr>'
    // Başlık ve kişi
    . '<tr><td style="background:#ffffff;padding:34px 32px 8px;">'
    . '<div style="' . $sans . 'font-size:10px;letter-spacing:2.5px;text-transform:uppercase;color:#6f8faf;font-weight:bold;">' . h($title) . '</div>'
    . '<div style="' . $serif . 'font-size:32px;line-height:1.2;color:#0c1017;margin:12px 0 6px;">' . $who . '</div>'
    . '<div style="' . $sans . 'font-size:13px;color:#6b7480;">' . h($sentAt->format('d.m.Y · H:i')) . ' &nbsp;·&nbsp; ' . h($host . $page) . '</div>'
    . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 8px;"><tr>'
    . '<td style="background:#0c1017;"><a href="mailto:' . h($replyTo) . '?subject=' . $replySubject . '" style="display:inline-block;padding:14px 26px;' . $sans . 'font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#f5f3ee;text-decoration:none;font-weight:bold;">Müşteriye yanıtla &nbsp;&#8599;</a></td>'
    . '</tr></table>'
    . '</td></tr>'
    // Alanlar
    . '<tr><td style="background:#ffffff;padding:16px 32px 30px;">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #0c1017;">' . $rowsHtml . '</table>'
    . '</td></tr>'
    // Alt bilgi
    . '<tr><td style="padding:22px 32px;' . $sans . 'font-size:12px;line-height:1.6;color:#6b7480;">'
    . 'Bu mesaj <a href="' . h($pageUrl) . '" style="color:#3d5f82;text-decoration:none;">' . h($host) . '</a> formundan otomatik gönderildi. '
    . 'E-postadaki <strong style="color:#1b2230;">Yanıtla</strong> düğmesi doğrudan müşteriye cevap verir.'
    . '</td></tr>'
    . '</table></td></tr></table></body></html>';
}

function smtp_send(string $host, int $port, string $user, string $pass, string $from, string $to, string $message): bool {
  $socket = @stream_socket_client(($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15);
  if (!$socket) return false;
  stream_set_timeout($socket, 15);
  $read = function () use ($socket): string {
    $response = '';
    while (($line = fgets($socket, 1024)) !== false) {
      $response .= $line;
      if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    return $response;
  };
  $send = function (string $command, array $expect) use ($socket, $read): bool {
    if ($command !== '') fwrite($socket, $command . "\r\n");
    return in_array((int) substr($read(), 0, 3), $expect, true);
  };
  $ehlo = 'EHLO ' . (gethostname() ?: 'localhost');
  $ok = $send('', [220]) && $send($ehlo, [250]);
  if ($ok && $port === 587) {
    $ok = $send('STARTTLS', [220])
      && stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)
      && $send($ehlo, [250]);
  }
  $ok = $ok
    && $send('AUTH LOGIN', [334])
    && $send(base64_encode($user), [334])
    && $send(base64_encode($pass), [235])
    && $send('MAIL FROM:<' . $from . '>', [250])
    && $send('RCPT TO:<' . $to . '>', [250, 251])
    && $send('DATA', [354])
    && $send(preg_replace('/^\./m', '..', $message) . "\r\n.", [250]);
  fwrite($socket, "QUIT\r\n");
  fclose($socket);
  return $ok;
}

if ($smtpHost !== '' && $smtpPass !== '') {
  $message = implode("\r\n", array_merge($headers, ['To: <' . $to . '>', 'Subject: ' . $encodedSubject])) . "\r\n\r\n" . $body;
  $sent = smtp_send($smtpHost, $smtpPort, $smtpUser, $smtpPass, $from, $to, $message);
} else {
  $sent = mail($to, $encodedSubject, $body, implode("\r\n", $headers), '-f' . $from);
}

if (!$sent) respond(502, ['error' => 'delivery-failed']);
respond(200, ['ok' => true]);
