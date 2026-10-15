import {fixture} from './harness.mjs';
import {readFile,writeFile} from 'node:fs/promises';
import {execFileSync,spawn} from 'node:child_process';
import assert from 'node:assert/strict';

const f=await fixture(8138);let checks=0,lockProcess;
const ok=(condition,label)=>{assert.ok(condition,label);checks++;console.log('PASS '+label);};
const php=code=>execFileSync('php',['-d','extension=pdo_sqlite','-r',`require 'app/bootstrap.php';${code}`],{cwd:f.root,env:{...process.env,ARKESOFT_CONFIG:f.config},encoding:'utf8'});
const original=await readFile(f.config,'utf8');
const configure=async(mode='test')=>writeFile(f.config,original.replace('return [',`return ['mail_from'=>'sender@example.test','mail_transport'=>'${mode}',`));
const queue=()=>JSON.parse(php(`echo json_encode(query('SELECT * FROM notification_queue ORDER BY id')->fetchAll());`));
const unlockTime=()=>php(`$file=growth_mail_lock_path();if(is_file($file))file_put_contents($file,'0');`);
let submitted=0;
async function submit(){
 // Fixture-only rate reset lets independent delivery scenarios use the same local visitor.
 php(`query('DELETE FROM rate_limits');`);
 const form=await f.visitor.request('/iletisim');
 const token=name=>form.body.match(new RegExp('name="'+name+'" value="([a-f0-9]+)"'))?.[1];
 const values={csrf:token('csrf'),submission_id:token('submission_id'),name:'Automatic '+(++submitted),email:'visitor@example.test',short_form:'1',consent:'1',website:'',message:''};
 const response=await f.visitor.request('/iletisim',values);assert.equal(response.status,303);return values;
}
try{
 ok(php(`echo settings()['mail_enabled'];`)==='auto','New installations default to automatic mode');
 await submit();ok(queue()[0].status==='pending'&&queue()[0].attempts===0,'Missing sender keeps the saved lead pending without consuming retries');
 await configure();await f.visitor.request('/blog');
 ok(queue()[0].status==='accepted'&&queue()[0].attempts===1,'Completing configuration activates pending delivery on the next visit');
 const values=await submit();let rows=queue();
 ok(rows[1].status==='accepted'&&rows[1].attempts===1,'New lead dispatches immediately despite the background throttle');
 await f.visitor.request('/iletisim',values);await f.visitor.request('/');
 ok(queue().length===2&&queue().every(r=>r.attempts===1),'Duplicate form and page visits never resend accepted notifications');
 php(`setting_write('mail_enabled','0');`);await submit();
 ok(queue()[2].status==='pending'&&queue()[2].attempts===0,'Explicit pause stops automatic delivery even with complete credentials');
 const paused=JSON.parse(php(`echo json_encode(growth_mail_worker());`));
 ok(paused.disabled&&queue()[2].attempts===0,'CLI worker respects the same pause');
 const identity=await f.owner.request('/yonetim?tab=identity');
 const settings=JSON.parse(php(`echo json_encode(settings());`));
 await f.owner.request('/yonetim',{...settings,csrf:identity.body.match(/name="csrf" value="([a-f0-9]+)"/)[1],action:'identity_save',mail_enabled:'auto'});
 ok(php(`echo settings()['mail_enabled'];`)==='auto','Admin mode selection persists automatic mode');
 unlockTime();await f.visitor.request('/');ok(queue()[2].status==='accepted','Resuming automatic mode drains the pending queue');
 await configure('test-fail');await submit();rows=queue();
 ok(rows[3].status==='pending'&&rows[3].attempts===1&&rows[3].available_at>Math.floor(Date.now()/1000),'Transport failure retains the lead and schedules retry');
 unlockTime();await f.visitor.request('/blog');ok(queue()[3].attempts===1,'Visits do not bypass retry backoff');
 await configure();php(`query("UPDATE notification_queue SET available_at=0 WHERE status='pending'");`);unlockTime();
 await f.visitor.request('/blog');ok(queue()[3].status==='accepted'&&queue()[3].attempts===2,'Due retry succeeds without manual worker or cron');
 php(`setting_write('mail_enabled','0');`);await submit();
 php(`setting_write('mail_enabled','auto');query("UPDATE notification_queue SET status='sending',locked_at=?,attempts=1 WHERE status='pending'",[time()-901]);`);unlockTime();
 await f.visitor.request('/');ok(queue()[4].status==='accepted'&&queue()[4].attempts===2,'Interrupted stale worker lease is recovered automatically');
 php(`setting_write('mail_enabled','0');`);await submit();
 php(`setting_write('mail_enabled','auto');query("UPDATE notification_queue SET attempts=4 WHERE status='pending'");`);await configure('test-fail');unlockTime();
 await f.visitor.request('/');ok(queue()[5].status==='failed'&&queue()[5].attempts===5,'Fifth failure stops automatic retries');
 await configure();unlockTime();await f.visitor.request('/');
 ok(queue()[5].status==='failed'&&queue()[5].attempts===5,'Failed notification waits for an explicit retry');
 const notifications=await f.owner.request('/yonetim?tab=notifications');
 await f.owner.request('/yonetim',{csrf:notifications.body.match(/name="csrf" value="([a-f0-9]+)"/)[1],action:'notification_retry',id:queue()[5].id});
 ok(queue()[5].status==='accepted'&&queue()[5].attempts===1,'Admin retry uses the automatic dispatcher');
 php(`setting_write('mail_enabled','0');`);await submit();await submit();php(`setting_write('mail_enabled','auto');`);unlockTime();
 await f.visitor.request('/blog');rows=queue();
 ok(rows[6].status==='accepted'&&rows[7].status==='pending','A normal visit handles at most one queued notification');
 await f.visitor.request('/blog');ok(queue()[7].status==='pending'&&queue()[7].attempts===0,'Repeated page traffic observes the one-minute throttle');
 unlockTime();
 lockProcess=spawn('php',['-d','extension=pdo_sqlite','-r',`require 'app/bootstrap.php';$lock=fopen(growth_mail_lock_path(),'c+');flock($lock,LOCK_EX);echo "ready\\n";fflush(STDOUT);fgets(STDIN);flock($lock,LOCK_UN);fclose($lock);`],{cwd:f.root,env:{...process.env,ARKESOFT_CONFIG:f.config},stdio:['pipe','pipe','pipe']});
 await new Promise((resolve,reject)=>{lockProcess.stdout.once('data',resolve);lockProcess.once('error',reject);lockProcess.once('exit',code=>{if(code)reject(Error('Lock fixture failed'));});});
 await f.visitor.request('/');ok(queue()[7].attempts===0,'A concurrent dispatcher lock leaves the queued message untouched');
 const stopped=new Promise(resolve=>lockProcess.once('close',resolve));lockProcess.stdin.end('\n');await stopped;lockProcess=null;
 await f.visitor.request('/');ok(queue()[7].status==='accepted','Pending delivery resumes after lock release');
 const panel=await f.owner.request('/yonetim?tab=identity');ok(panel.body.includes('Otomatik gönderim hazır')&&panel.body.includes('Zamanlanmış görev zorunlu değildir'),'Panel reports ready-to-use automatic delivery');
 console.log(checks+' automatic-mail checks passed. No real mail sent.');
}finally{lockProcess?.kill();f.stop();}
