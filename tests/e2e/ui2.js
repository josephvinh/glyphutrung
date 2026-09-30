const { chromium } = require('playwright');
const S=__dirname + '/out';
(async()=>{
 const b=await chromium.launch(); const p=await (await b.newContext({viewport:{width:390,height:844}})).newPage();
 const seen=[]; p.on('response',r=>{ if(r.url().includes('export.php')) seen.push(r.status()+' '+r.url().split('/api/')[1]); });
 await p.goto((process.env.E2E_BASE || 'http://127.0.0.1:8088') + '/?dangnhap=1',{waitUntil:'networkidle'});
 await p.fill('input[type=tel]','0901000001'); await p.fill('input[autocomplete=current-password]','tntt@2026');
 await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type=submit]')]);
 await p.waitForTimeout(1200);
 await p.evaluate(()=>window.Alpine.$data(document.querySelector('.app-shell')).openModule('attendance'));
 await p.waitForTimeout(1500);
 await p.click('button:has-text("Xuất Excel")');
 await p.waitForTimeout(1500);
 await p.screenshot({path:S+'/shots/export-dialog.png'});
 const btns=await p.$$eval('button',bs=>bs.filter(b=>b.offsetParent&&/xuất|tải/i.test(b.innerText)).map(b=>b.innerText.trim()));
 console.log('buttons:',btns);
 // click the confirm export button inside dialog if present
 const ok=await p.$('button:has-text("Tải về")');
 if(ok){ await ok.click(); await p.waitForTimeout(2000); }
 await p.screenshot({path:S+'/shots/export-result.png'});
 const toast=await p.$$eval('[class*=toast], [role=alert], [role=status]',e=>e.filter(x=>x.offsetParent).map(x=>x.innerText.trim()).slice(0,3));
 console.log('requests:',seen,'toasts:',toast);
 await b.close();
})();
