// Replay the synthetic Laravel verification response on loopback; never contacts VITA or real patients.
const fs=require('node:fs'), path=require('node:path'), http=require('node:http'), {spawn}=require('node:child_process');
const root=path.resolve(__dirname,'..'), directory=path.join(root,'storage/framework/testing/mi-salud-preview');
const headers={'Cache-Control':'no-store','Referrer-Policy':'no-referrer','Content-Security-Policy':"default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; frame-ancestors 'none'; form-action 'self'"};
const token='abc123'.repeat(10)+'abcd'; // Synthetic bearer, not associated with any database record.
let posts=0, correctBody=false, leaked=false, external=false;
const server=http.createServer((req,res)=>{
    if(req.url.includes(token)||(req.headers.referer||'').includes(token))leaked=true;
    if(req.method==='POST'){
        posts++; let body=''; req.on('data',chunk=>body+=chunk); req.on('end',()=>{
            correctBody=new URLSearchParams(body).get('security_token')===token;
            res.writeHead(303,{Location:'/finished'}); res.end();
        });
    }else if(req.url==='/finished'){res.end('<!doctype html><title>Processed</title><p>Processed</p>');}
    else if(req.url==='/favicon.ico'){res.writeHead(204);res.end();}
    else if((req.url.startsWith('/fonts/figtree/')||req.url.startsWith('/fonts/fontawesome/'))){const file=path.join(root,'public',req.url.split('?')[0]);if(fs.existsSync(file)){res.writeHead(200,{'Content-Type':'font/woff2'});res.end(fs.readFileSync(file));}else{res.writeHead(404);res.end();}}
    else if(req.url.startsWith('/storage/')){const file=path.join(root,'public',req.url.split('?')[0]);if(fs.existsSync(file)){res.writeHead(200,{'Content-Type':'image/png'});res.end(fs.readFileSync(file));}else{res.writeHead(404);res.end();}}
    else if(req.url.startsWith('/css/')||req.url.startsWith('/js/')){const file=path.join(root,'public',req.url.split('?')[0]);res.writeHead(200,{'Content-Type':req.url.split('?')[0].endsWith('.css')?'text/css':'application/javascript'});res.end(fs.readFileSync(file));}
    else{const page=req.url.includes('verify')?'verify-token':req.url.replace('/','').split('?')[0];const html=fs.readFileSync(path.join(directory,page+'.html'),'utf8').replace(/https?:\/\/[^/" ]+/g,'http://127.0.0.1:'+server.address().port);res.writeHead(200,{'Content-Type':'text/html; charset=utf-8',...headers});res.end(html);}
});
let chrome,id=0,buffer='',pending=new Map();
function send(method,params={},sessionId){return new Promise((resolve,reject)=>{const seq=++id;pending.set(seq,{resolve,reject});chrome.stdio[3].write(JSON.stringify({id:seq,method,params,...(sessionId?{sessionId}:{})})+'\0');});}
const timeout=setTimeout(()=>{chrome?.kill();server.close();process.exitCode=1;},30000);
(async()=>{
    try{
        await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
        const origin='http://127.0.0.1:'+server.address().port;
        chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless','--disable-gpu','--disable-extensions','--disable-background-networking','--no-first-run','--no-default-browser-check','--remote-debugging-pipe','--user-data-dir='+path.join(directory,'profile')],{stdio:['ignore','ignore','ignore','pipe','pipe'],windowsHide:true});
        chrome.stdio[4].on('data',data=>{buffer+=data.toString();let end;while((end=buffer.indexOf('\0'))>=0){const part=buffer.slice(0,end);buffer=buffer.slice(end+1);if(!part)continue;const msg=JSON.parse(part),p=pending.get(msg.id);if(p){pending.delete(msg.id);msg.error?p.reject(Error('Browser command failed')):p.resolve(msg.result);}if(msg.method==='Network.requestWillBeSent'){const req=msg.params.request;if(req.url.includes(token)||Object.values(req.headers||{}).some(v=>String(v).includes(token)))leaked=true;if(!req.url.startsWith(origin)&&!req.url.startsWith('data:image/'))external=true;}}});
        const {targetId}=await send('Target.createTarget',{url:'about:blank'});
        const {sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
        await send('Network.enable',{},sessionId);
        const evaluate=async expression=>(await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true},sessionId)).result.value;
        await send('Page.navigate',{url:origin+'/verify-token#'+token},sessionId);
        let ready=false;const deadline=Date.now()+8000;
        while(!ready&&Date.now()<deadline){ready=await evaluate("document.getElementById('security-token')?.value.length===64 && !location.hash");if(!ready)await new Promise(r=>setTimeout(r,30));}
        if(!ready||posts)throw Error('Fragment handling or GET side effect failed');
        await evaluate("document.querySelector('[data-security-form] button').click()");
        while(posts<1&&Date.now()<deadline)await new Promise(r=>setTimeout(r,30));
        const layouts=[];
        for(const page of ['register','login','panel','profile']) {
            await send('Page.navigate',{url:origin+'/'+page},sessionId);
            let loaded=false; const limit=Date.now()+5000;
            while(!loaded && Date.now()<limit){loaded=await evaluate("document.readyState==='complete' && document.querySelector('main')!==null");if(!loaded)await new Promise(r=>setTimeout(r,30));}
            const passwordControlsWork=await evaluate(`Array.from(document.querySelectorAll('.portal-password-toggle')).every(button=>{const input=document.getElementById(button.getAttribute('aria-controls'));button.click();const shown=input.type==='text'&&button.getAttribute('aria-pressed')==='true';button.click();return shown&&input.type==='password'&&button.getAttribute('aria-pressed')==='false'&&button.type==='button';})`);
            if(!passwordControlsWork)throw Error('Password visibility control failed');
            const countryPickerWorks=await evaluate(`(()=>{const picker=document.querySelector('.portal-country-select');if(!picker)return true;const select=picker.querySelector('select'),original=select.value,originalLabel=picker.querySelector('[data-country-trigger] span').textContent,trigger=picker.querySelector('[data-country-trigger]'),search=picker.querySelector('[data-country-search]');trigger.click();search.value='argentina';search.dispatchEvent(new Event('input',{bubbles:true}));const filtered=picker.querySelectorAll('[role=option]').length===1;search.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true,cancelable:true}));const selected=select.value==='AR'&&trigger.getAttribute('aria-expanded')==='false';trigger.click();search.value=originalLabel;search.dispatchEvent(new Event('input',{bubbles:true}));search.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true,cancelable:true}));trigger.click();search.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true,cancelable:true}));return filtered&&selected&&select.value===original&&trigger.getAttribute('aria-expanded')==='false';})()`);
            if(!countryPickerWorks)throw Error('Searchable country selector failed');
            for(const width of [390,768,1024,1440]) {
                await send('Emulation.setDeviceMetricsOverride',{width,height:960,deviceScaleFactor:1,mobile:width===390},sessionId);
                await evaluate('new Promise(resolve=>setTimeout(resolve,220))');
                const workspaceMenuWorks=await evaluate(`(()=>{if(!document.getElementById('portal-sidebar'))return true;const mobile=matchMedia('(max-width:767px)').matches;const toggle=document.querySelector(mobile?'.portal-mobile-menu':'.portal-sidebar-collapse');toggle.click();const opened=mobile?document.body.classList.contains('portal-workspace-menu-open')&&!document.getElementById('portal-sidebar').inert:document.body.classList.contains('portal-workspace-compact');if(mobile)document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));else toggle.click();const closed=mobile?!document.body.classList.contains('portal-workspace-menu-open'):!document.body.classList.contains('portal-workspace-compact');const account=document.querySelector('.portal-account-menu');account.querySelector('summary').click();const accountOpened=account.open;document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));return opened&&closed&&accountOpened&&!account.open;})()`);
                if(!workspaceMenuWorks)throw Error('Workspace navigation failed');
                const vitiWorks=await evaluate(`(()=>{const toggle=document.querySelector('[data-viti-nav-toggle]');if(!toggle)return true;const panel=document.querySelector('[data-assistant-panel]');if(!panel)return false;toggle.click();const opened=!panel.hidden;panel.querySelector('[data-close]').click();return opened&&panel.hidden;})()`);
                if(!vitiWorks)throw Error('Mi Salud Viti opening or closing failed');
                const compactPreviewWorks=await evaluate(`(()=>{const sidebar=document.getElementById('portal-sidebar');if(!sidebar||innerWidth<768)return true;const toggle=document.querySelector('.portal-sidebar-collapse'),link=sidebar.querySelector('nav a');toggle.click();link.dispatchEvent(new MouseEvent('mouseover',{bubbles:true}));const preview=document.querySelector('.portal-sidebar-preview');const shown=!preview.hidden&&preview.querySelector('a').href===link.href;const saved=localStorage.getItem(sidebar.dataset.preferenceKey)==='true';document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));const hidden=preview.hidden;toggle.click();return shown&&saved&&hidden&&localStorage.getItem(sidebar.dataset.preferenceKey)==='false';})()`);
                if(!compactPreviewWorks)throw Error('Compact menu preview or preference failed');
                const centeredIcons=await evaluate(`(async()=>{const sidebar=document.getElementById('portal-sidebar');if(!sidebar||innerWidth<768)return true;const toggle=sidebar.querySelector('.portal-sidebar-collapse');toggle.click();await new Promise(resolve=>setTimeout(resolve,220));const outer=sidebar.getBoundingClientRect();const centered=[...sidebar.querySelectorAll('nav a')].every(link=>{const box=link.getBoundingClientRect(),icon=link.querySelector('i').getBoundingClientRect();return Math.abs(icon.x+icon.width/2-(box.x+box.width/2))<1&&Math.abs(icon.y+icon.height/2-(box.y+box.height/2))<1&&Math.abs(icon.x+icon.width/2-(outer.x+outer.width/2))<1;});toggle.click();await new Promise(resolve=>setTimeout(resolve,220));return centered;})()`);
                if(!centeredIcons)throw Error('Compact sidebar icons are not centered');
                const metrics=await evaluate("({overflow:document.documentElement.scrollWidth>innerWidth,labels:[...document.querySelectorAll('input:not([type=hidden]),select:not([hidden])')].every(e=>!!document.querySelector('label[for=\"'+e.id+'\"]'))})");
                layouts.push({page,width,...metrics});
                if((page==='register'&&width===390)||(page==='panel'&&width===1440)||(page==='login'&&(width===390||width===1440))) {const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true},sessionId);fs.writeFileSync(path.join(directory,page+'-'+width+'.png'),Buffer.from(shot.data,'base64'));}
            }
        }
        const ok=ready&&posts===1&&correctBody&&!leaked&&!external&&layouts.every(x=>!x.overflow&&x.labels);
        const report={fragment_cleared:ready,single_form_post:posts===1,token_only_in_post_body:correctBody,no_token_in_request_urls_or_headers:!leaked,no_external_resources_or_analytics:!external,layouts,passed:ok};
        fs.writeFileSync(path.join(directory,'report.json'),JSON.stringify(report,null,2));
        process.stdout.write(JSON.stringify(report,null,2)+'\n');if(!ok)process.exitCode=1;
    }catch(error){process.stderr.write('Synthetic browser verification failed: '+error.message+'\n');process.exitCode=1;}
    finally{clearTimeout(timeout);chrome?.kill();server.closeAllConnections();server.close();}
})();
