const {spawn,spawnSync}=require('node:child_process');
const {randomBytes}=require('node:crypto');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),net=require('node:net');
const http=require('node:http');
const assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..'),php=process.env.PHP_BINARY||'D:/xampp1/php/php.exe';
const database='seniorlink_test_'+randomBytes(6).toString('hex');
const sessions=fs.mkdtempSync(path.join(os.tmpdir(),'seniorlink-id-notice-'));
let server,browser,page,passed=0,errors='';
const check=(ok,label)=>{assert.ok(ok,label);passed++;console.log('PASS '+label);};
function run(args,env={}){const r=spawnSync(php,args,{cwd:root,encoding:'utf8',windowsHide:true,env:{...process.env,...env}});if(r.status!==0)throw Error(r.stdout+r.stderr);return r.stdout;}
function sql(statement){return run(['-r',"$n=$argv[1];if(!preg_match('/^seniorlink_test_[a-f0-9]{12}$/D',$n))exit(1);$c=new PDO('mysql:host=localhost;dbname='.$n.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$c->exec($argv[2]);",database,statement]);}
(async()=>{
try{
 run(['tests/workflow_fixture.php','setup',database]);
 const env={DATABASE_URL:'mysql://root@localhost/'+database};
 run(['scripts/migrate_digital_id_notifications.php'],env);
 run(['scripts/migrate_digital_id_notifications.php'],env);
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const base='http://127.0.0.1:'+port;
 server=spawn(php,['-d','session.save_path='+sessions,'-S','127.0.0.1:'+port,'-t',root],{cwd:root,windowsHide:true,env:{...process.env,...env},stdio:['ignore','ignore','pipe']});
 server.stderr.on('data',c=>errors+=c);
 async function request(url,cookie='',body){
    const payload=body?new URLSearchParams(body).toString():'';
    return new Promise((resolve,reject)=>{
        const req=http.request(base+url,{method:body?'POST':'GET',headers:{Connection:'close',...(cookie?{Cookie:cookie}:{}),...(body?{Origin:base,'Content-Type':'application/x-www-form-urlencoded','Content-Length':Buffer.byteLength(payload)}:{})}},r=>{
            let text='';r.on('data',c=>text+=c);r.on('end',()=>{let data;try{data=JSON.parse(text);}catch{data=text;}resolve({status:r.statusCode,data,headers:{getSetCookie:()=>r.headers['set-cookie']||[]}});});
        });req.on('error',reject);req.end(payload);
    });
 }
 for(let i=0;i<50;i++){try{await request('/index.php');break;}catch{await new Promise(r=>setTimeout(r,100));}}
 async function login(username,role){const r=await request('/index.php','',{username,password:'TestOnly!2026',login_role:role,barangay:'Bagong Ilog'});check(r.status===302,'Login '+username);return r.headers.getSetCookie().map(x=>x.split(';')[0]).join('; ');}
 const admin=await login('test-admin','department_admin'),staff=await login('test-staff','barangay_staff');
 check((await request('/api/tracker_notifications.php')).status===400,'Missing tracking reference rejected');
 check((await request('/api/tracker_notifications.php?token[]=PRX-BENE')).status===400,'Array tracking input rejected');
 let r=await request('/api/tracker_notifications.php?token=PRX-BENE');
 check(r.data.notification?.title==='Your digital ID is ready','Existing approved IDs receive backfilled alert');
 check(!JSON.stringify(r.data).includes('OSCA-TEST')&&!JSON.stringify(r.data).includes('Benefits Portal Senior'),'Alert does not disclose ID credentials or name');
 check((await request('/api/tracker_notifications.php?token=PRX-NONE')).data.notification===null,'Unknown token receives no notice');
 sql("UPDATE applications SET proxy_token='PRX-CORR' WHERE id_number='CORRECT'");
 check((await request('/api/tracker_notifications.php?token=PRX-CORR')).data.notification===null,'Unapproved application has no notice');
 if(process.argv.includes('--browser')){
    const {chromium}=require('C:/Users/PLPASIG/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
    browser=await chromium.launch({channel:'chrome',headless:true});
    page=await browser.newPage({viewport:{width:1280,height:900}});
    await page.route('**/*',route=>route.request().url().startsWith(base)?route.continue():route.abort());
    await page.goto(base+'/pages/benefit_tracker.php?token=PRX-CORR&service=senior');
    check(await page.locator('[data-digital-id-notification]').isHidden(),'Browser hides notice before ID is ready');
 }
 r=await request('/api/update_workflow_status.php',admin,{applicationId:'CORRECT',action:'next'});
 check(r.data.success,'Senior application verification works');
 check((await request('/api/tracker_notifications.php?token=PRX-CORR')).data.notification===null,'Verification without ID waits for issuance');
 r=await request('/api/set_official_senior_id.php',admin,{applicationId:'CORRECT',generate:'1'});
 check(r.data.success,'Official ID generation succeeds');
 let first=(await request('/api/tracker_notifications.php?token=PRX-CORR')).data.notification;
 check(first?.created_at,'Issuance immediately creates dated tracker notification');
 if(page){
    await page.waitForSelector('[data-digital-id-notification]:not([hidden])',{timeout:25000});
    check((await page.locator('[data-notice-title]').textContent())==='Your digital ID is ready','Open tracker announces readiness automatically without reloading');
    await page.locator('[data-digital-id-notification] a').click();
    await page.waitForSelector('#digital-id');
    check(await page.locator('#digital-id').isVisible(),'View digital ID opens the credential on the tracker');
    await page.setViewportSize({width:390,height:844});
    check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Notice and tracker fit a mobile screen');
    const out=path.join(root,'tmp','digital-id-notification-qa');fs.mkdirSync(out,{recursive:true});
    await page.screenshot({path:path.join(out,'tracker-mobile.png'),fullPage:true});
 }
 await request('/api/set_official_senior_id.php',admin,{applicationId:'CORRECT',seniorIdNo:r.data.seniorIdNo});
 check(JSON.stringify((await request('/api/tracker_notifications.php?token=PRX-CORR')).data.notification)===JSON.stringify(first),'Saving same ID retains the original notification');
 check((await request('/api/set_official_senior_id.php',staff,{applicationId:'CORRECT',generate:'1'})).status===403,'SHDO cannot issue ID to trigger an alert');
 sql("UPDATE applications SET senior_id_no='99901',proxy_token='PRX-OTHR' WHERE id_number='OTHERQUEUE'");
 r=await request('/api/update_workflow_status.php',admin,{applicationId:'OTHERQUEUE',action:'next'});
 check(r.data.success&&(await request('/api/tracker_notifications.php?token=PRX-OTHR')).data.notification,'Existing official ID followed by verification triggers alert');
 r=await request('/pages/benefit_tracker.php?token=PRX-CORR&service=senior');
 check(r.status===200&&r.data.includes('Your digital ID is ready')&&r.data.includes('Notification history')&&r.data.includes('id="digital-id"'),'Tracker renders notice, history, and digital ID target');
 check(r.data.includes('digital-id-notifications.js'),'Tracker loads automatic update script');
 sql("UPDATE applications SET is_archived=1 WHERE id_number='CORRECT'");
 check((await request('/api/tracker_notifications.php?token=PRX-CORR')).data.notification===null,'Archived record cannot advertise an available ID');
 if(page){
    await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));
    await page.waitForSelector('[data-digital-id-notification][hidden]',{state:'attached'});
    check(await page.locator('[data-digital-id-notification]').isHidden(),'Live notice disappears when the record becomes archived');
    check(await page.locator('#digital-id').isHidden(),'Archived credential is hidden on the open tracker');
 }
 check(!/PHP (Fatal error|Warning|Parse error)/.test(errors),'Requests finish without PHP errors');
 console.log(passed+' HTTP checks passed.');
}finally{
 if(browser)await browser.close();
 if(server){server.kill();await new Promise(resolve=>server.exitCode!==null?resolve():server.once('exit',resolve));}
 run(['tests/workflow_fixture.php','cleanup',database]);
 fs.rmSync(sessions,{recursive:true,force:true});
}
})().catch(e=>{console.error(e);process.exitCode=1;});
