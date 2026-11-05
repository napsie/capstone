// End-to-end classification checks. All writes use a disposable synthetic database.
const fs=require('node:fs');
const path=require('node:path');
const os=require('node:os');
const net=require('node:net');
const http=require('node:http');
const {spawn,spawnSync}=require('node:child_process');
const assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..');
const php=process.env.PHP_BINARY||'D:/xampp1/php/php.exe';
let database,server,browser;
const sessionDir=fs.mkdtempSync(path.join(os.tmpdir(),'senior-classification-http-'));
const out=path.join(root,'tmp','classification-qa');
let passed=0;
const check=(ok,label)=>{assert.ok(ok,label);passed++;console.log('PASS '+label);};
(async()=>{
 try {
    const fixture=spawnSync(php,[path.join(__dirname,'senior_classifications.integration.php'),'--keep'],{cwd:root,encoding:'utf8',windowsHide:true});
    if(fixture.status!==0)throw Error(fixture.stdout+fixture.stderr);
    database=JSON.parse(fixture.stdout.trim().split(/\r?\n/).at(-1)).database;
    fs.mkdirSync(out,{recursive:true});
    fs.writeFileSync(path.join(out,'test-database.txt'),database);
    const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
    const base='http://127.0.0.1:'+port;
    server=spawn(php,['-d','session.save_path='+sessionDir,'-S','127.0.0.1:'+port,'-t',root],{cwd:root,env:{...process.env,DATABASE_URL:'mysql://root@localhost/'+database},windowsHide:true,stdio:['ignore','ignore','pipe']});
    let errors='';server.stderr.on('data',data=>{errors+=data;});
    function request(url,cookie='',body){
        const payload=body?new URLSearchParams(body).toString():'';
        return new Promise((resolve,reject)=>{
            const req=http.request(base+url,{method:body?'POST':'GET',headers:{Connection:'close',...(cookie?{Cookie:cookie}:{}),...(body?{Origin:base,'Content-Type':'application/x-www-form-urlencoded','Content-Length':Buffer.byteLength(payload)}:{})}},r=>{
                const chunks=[];r.on('data',c=>chunks.push(c));r.on('error',reject);
                r.on('end',()=>resolve({status:r.statusCode,headers:{get:name=>r.headers[name.toLowerCase()],getSetCookie:()=>r.headers['set-cookie']||[]},bytes:Buffer.concat(chunks)}));
            });
            req.on('error',reject);req.setTimeout(10000,()=>req.destroy(Error('Request timed out')));req.end(payload);
        });
    }
    for(let i=0;i<50;i++){if(server.exitCode!==null)throw Error(errors);try{await request('/index.php');break;}catch{await new Promise(r=>setTimeout(r,100));}}
    async function login(username,role,barangay=''){
        const r=await request('/index.php','',{login_role:role,username,password:'TestOnly!2026',barangay});
        check(r.status===302,'Sign in '+username);
        return r.headers.getSetCookie().map(c=>c.split(';')[0]).join('; ');
    }
    check((await request('/pages/senior_classifications.php')).status===403,'Anonymous page access denied');
    check((await request('/api/export_senior_classifications.php?format=pdf')).status===403,'Anonymous export denied');
    const admin=await login('test-admin','department_admin');
    const staff=await login('test-staff','barangay_staff','Bagong Ilog');
    const unassigned=await request('/index.php','',{login_role:'barangay_staff',username:'test-unassigned',password:'TestOnly!2026',barangay:''});
    check(unassigned.status!==302,'Unassigned staff cannot sign in');
    const adminPage=await request('/pages/senior_classifications.php?profile=VALID',admin);
    const html=adminPage.bytes.toString();
    check(adminPage.status===200 && html.includes('Save classification'),'Administrator can open the editor');
    const csrf=html.match(/name="csrf_token" value="([^"]+)"/)[1];
    const version=html.match(/name="version" value="(\d+)"/)[1];
    const form={profile_id:'VALID',version,activity_status:'Inactive',residence_status:'Within Pasig',effective_date:new Date().toLocaleDateString('en-CA',{timeZone:'Asia/Manila'}),reason:'Reviewed through the classification screen.'};
    check((await request('/pages/senior_classifications.php',admin,form)).status===403,'Missing CSRF denied');
    check((await request('/pages/senior_classifications.php',staff,{...form,csrf_token:csrf})).status===403,'SHDO POST denied');
    check((await request('/pages/senior_classifications.php?profile=OTHER',staff)).status===404,'Cross-barangay profile URL denied');
    check((await request('/pages/senior_classifications.php?activity=invalid',admin)).status===400,'Malformed filters return a clear error');
    check((await request('/pages/senior_classifications.php?activity[]=Active',admin)).status===400,'Array filter input rejected without warnings');
    const invalid=await request('/pages/senior_classifications.php',admin,{...form,csrf_token:csrf,reason:'',residence_status:'Outside Pasig'});
    check(invalid.status===422 && invalid.bytes.toString().includes('<option selected>Outside Pasig</option>'),'Invalid update retains entered residence');
    check((await request('/pages/senior_classifications.php',admin,{...form,csrf_token:csrf})).status===302,'Valid update redirects after saving');
    check((await request('/pages/senior_classifications.php',admin,{...form,csrf_token:csrf})).status===409,'Repeat stale POST rejected');
    const staffPage=await request('/pages/senior_classifications.php?barangay=Ugong&profile=VALID',staff);
    check(staffPage.status===200 && !staffPage.bytes.includes('Save classification') && !staffPage.bytes.includes('OSCA-TEST-OTHER'),'Staff filters stay scoped and editor remains read-only');
    const pdf=await request('/api/export_senior_classifications.php?format=pdf&barangay=Ugong',staff);
    check(pdf.status===200 && pdf.headers.get('content-type').includes('application/pdf') && pdf.bytes.subarray(0,5).toString()==='%PDF-','Scoped PDF download works');
    check(!pdf.bytes.includes('OSCA-TEST-OTHER'),'Scoped PDF excludes other barangay');
    const xlsx=await request('/api/export_senior_classifications.php?format=xlsx&activity=Active',admin);
    check(xlsx.status===200 && xlsx.bytes.subarray(0,2).toString()==='PK','Filtered Excel download works');
    fs.writeFileSync(path.join(out,'scoped-http.pdf'),pdf.bytes);fs.writeFileSync(path.join(out,'filtered-http.xlsx'),xlsx.bytes);
    const playwright=require(process.env.SENIORLINK_PLAYWRIGHT||'C:/Users/PLPASIG/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
    browser=await playwright.chromium.launch({channel:'chrome',headless:true});
    const context=await browser.newContext({viewport:{width:1440,height:1100}});
    await context.addCookies(admin.split('; ').filter(v=>v.includes('=')).map(v=>({name:v.slice(0,v.indexOf('=')),value:v.slice(v.indexOf('=')+1),url:base})));
    const page=await context.newPage();const pageErrors=[];page.on('pageerror',e=>pageErrors.push(e.message));
    await page.goto(base+'/pages/senior_classifications.php');
    await page.screenshot({path:path.join(out,'classification-redesign-desktop.png'),fullPage:true});
    check(await page.locator('.classification-summary-link').count()===4,'All four summary cards are navigable');
    const overallCards=await page.locator('.classification-totals .stat-value').allTextContents();
    const searchBox=page.locator('#classification-filters [name="search"]');
    const beforeTyping=page.url();
    await searchBox.pressSequentially('Same Name',{delay:120});
    await page.waitForTimeout(800);
    check(page.url()===beforeTyping && await searchBox.inputValue()==='Same Name','Typing and pausing keeps search text without reloading');
    await Promise.all([page.waitForURL(/search=Same/),searchBox.press('Enter')]);
    check(await page.locator('.classification-table tbody tr').count()===2,'Enter applies the complete search');
    await page.locator('#classification-filters [name="search"]').fill('NoMatchingSenior');
    await Promise.all([page.waitForURL(/search=NoMatchingSenior/),page.locator('#classification-filters').getByRole('button',{name:'Search',exact:true}).click()]);
    check((await page.locator('.classification-table tbody').innerText()).includes('No senior profiles'),'Search button applies text');
    await page.goto(base+'/pages/senior_classifications.php');

    await page.goto(base+'/pages/senior_classifications.php?search=NoMatchingSenior&barangay=Ugong&activity=Inactive&residence=Unclassified');
    check(JSON.stringify(await page.locator('.classification-totals .stat-value').allTextContents())===JSON.stringify(overallCards),'Card totals stay unchanged across search and all filters');
    check((await page.locator('.classification-table tbody').innerText()).includes('No senior profiles'),'Table still follows filters independently of cards');
    await page.goto(base+'/pages/senior_classifications.php');

    await page.goto(base+'/pages/senior_classifications.php?residence=Unclassified');
    check(new URL(page.url()).searchParams.get('residence')==='Unclassified','Residence filter remains supported');
    await page.getByRole('link',{name:'Show inactive seniors',exact:true}).click();
    check(new URL(page.url()).searchParams.get('residence')==='all','Inactive card clears residence filter');
    check(await page.locator('#classification-filters [name="activity"]').inputValue()==='Inactive','Inactive card applies activity filter');
    check(await page.locator('.classification-table tbody .classification-badge[data-activity="Inactive"]').count()===1,'Inactive record remains available');
    await page.getByRole('link',{name:'Show all registered seniors',exact:true}).click();
    check(await page.locator('#classification-filters [name="activity"]').inputValue()==='all','Registered seniors resets category filters');

    await page.getByRole('button',{name:'Generate Report',exact:true}).click();
    const exportDialog=page.locator('#classification-export');
    check(await exportDialog.isVisible(),'Export Report opens filter dialog');
    check(!(await page.locator('#classification-export-barangay').isVisible()),'All barangays hides individual selection');
    await exportDialog.locator('[name="format"]').selectOption('pdf');
    let downloadEvent=page.waitForEvent('download');
    await exportDialog.getByRole('button',{name:'Generate Report',exact:true}).click();
    const pdfDownload=await downloadEvent;
    check(pdfDownload.suggestedFilename().endsWith('.pdf'),'Report dialog downloads PDF');
    await exportDialog.locator('[name="coverage"][value="one"]').check();
    await exportDialog.locator('[name="barangay"]').selectOption('Bagong Ilog');
    await exportDialog.locator('[name="format"]').selectOption('xlsx');
    await exportDialog.locator('[name="activity"]').selectOption('Active');
    downloadEvent=page.waitForEvent('download');
    await exportDialog.getByRole('button',{name:'Generate Report',exact:true}).click();
    check((await downloadEvent).suggestedFilename().endsWith('.xlsx'),'Report dialog downloads filtered Excel');
    await page.setViewportSize({width:390,height:844});
    check(await exportDialog.evaluate(e=>{const r=e.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.height<=innerHeight}),'Export dialog fits mobile viewport');
    await page.screenshot({path:path.join(out,'export-mobile.png'),fullPage:false});
    await page.setViewportSize({width:1440,height:1100});
    await page.screenshot({path:path.join(out,'export-desktop.png'),fullPage:false});
    await page.getByRole('button',{name:'Close generate report',exact:true}).click();
    check(!(await exportDialog.isVisible()),'Export dialog closes and returns to report');
    await page.setViewportSize({width:390,height:844});
    await page.waitForFunction(()=>document.querySelector('.classification-page').getBoundingClientRect().right<=innerWidth+1 && document.querySelector('.sidebar-menu').getBoundingClientRect().right<=0);
    await page.screenshot({path:path.join(out,'classification-redesign-mobile.png'),fullPage:true});
    await page.setViewportSize({width:1440,height:1100});
    await page.goto(base+'/pages/senior_classifications.php?profile=VALID');
    check(await page.getByRole('dialog').isVisible(),'Selected senior opens in a modal');
    await page.getByRole('button',{name:'Close classification',exact:true}).click();
    check(await page.getByRole('heading',{name:'Senior Classification Report',exact:true}).isVisible(),'Closing modal returns to the report');
    await page.waitForFunction(()=>!new URL(location.href).searchParams.has('profile'));
    const listUrl=page.url();
    await page.locator('[data-classification-manage][href*="profile=VALID"]').click();
    await page.getByRole('dialog').waitFor();
    check(page.url()===listUrl,'Manage opens modal without navigating away from list');
    await page.locator('select[name="activity_status"]').selectOption('Inactive');
    await page.getByLabel('Reason for this classification').fill('Updated after a staff review in the browser.');
    await Promise.all([page.waitForURL(/profile=VALID/),page.getByRole('button',{name:'Save classification',exact:true}).click()]);
    await page.getByRole('status').waitFor();
    check((await page.getByRole('status').innerText()).includes('saved'),'Browser form saves and displays confirmation');
    await page.screenshot({path:path.join(out,'desktop.png'),fullPage:true});
    await page.setViewportSize({width:390,height:844});
    await page.screenshot({path:path.join(out,'mobile.png'),fullPage:true});
    check(await page.getByRole('button',{name:'Close classification',exact:true}).isVisible(),'Mobile modal close button stays visible');
    await page.keyboard.press('Escape');
    check(!(await page.getByRole('dialog').isVisible()),'Escape dismisses the modal');
    check(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth),'Mobile page has no horizontal overflow outside its table');
    check(pageErrors.length===0,'No browser script errors: '+pageErrors.join('; '));
    check(!/PHP (Fatal error|Warning|Parse error)/.test(errors),'No PHP warnings during requests');
    console.log(passed+' HTTP and browser checks passed.');
 }finally{
    if(browser)await browser.close();
    if(server && server.exitCode===null){const stopped=new Promise(r=>server.once('exit',r));server.kill();await stopped;}
    if(database && /^seniorlink_test_[a-f0-9]{12}$/.test(database)){
        const clean=spawnSync(php,[path.join(__dirname,'workflow_fixture.php'),'cleanup',database],{cwd:root,encoding:'utf8',windowsHide:true});
        if(clean.status!==0)throw Error(clean.stderr||'Unable to remove synthetic database');
    }
    fs.rmSync(sessionDir,{recursive:true,force:true});
 }
})().catch(e=>{console.error(e);process.exitCode=1;});
