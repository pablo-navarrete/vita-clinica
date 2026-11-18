// Checks saved synthetic HTTP responses in a real local Chrome, without external requests.
const {spawn}=require('node:child_process');
const fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'..'),out=path.join(root,'storage/app/directory-audit/final');
for(const page of ['initial','specialty','detail']) {
    let html=fs.readFileSync(path.join(out,page+'.html'),'utf8');
    html=html.replace(/<link\b[^>]*>/g,'');
    html=html.replace('</head>','<style>'+fs.readFileSync(path.join(root,'public/css/customer-directory.css'),'utf8')+'</style></head>');
    html=html.replace(/<script src="[^"]*\/js\/([^"/]+)"><\/script>/g,(_,file)=>'<script>'+fs.readFileSync(path.join(root,'public/js',file),'utf8')+'</script>');
    fs.writeFileSync(path.join(out,'browser-'+page+'.html'),html);
}
const chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',[
    '--headless','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-pipe',
    '--user-data-dir='+path.join(root,'storage/app/directory-audit/browser-profile')
],{stdio:['ignore','ignore','ignore','pipe','pipe'],windowsHide:true});
let id=0,buffer='',pending=new Map();
chrome.stdio[4].on('data',data=>{buffer+=data.toString();let end;
    while((end=buffer.indexOf('\0'))>=0){const part=buffer.slice(0,end);buffer=buffer.slice(end+1);if(!part)continue;
        const msg=JSON.parse(part),p=pending.get(msg.id);if(p){pending.delete(msg.id);msg.error?p.reject(Error(JSON.stringify(msg.error))):p.resolve(msg.result);}}
});
function send(method,params={},sessionId){return new Promise((resolve,reject)=>{const seq=++id;pending.set(seq,{resolve,reject});chrome.stdio[3].write(JSON.stringify({id:seq,method,params,...(sessionId?{sessionId}:{})})+'\0');});}
const timeout=setTimeout(()=>{chrome.kill();process.exit(1)},55000);
(async()=>{const report=[];
    try {
        const {targetId}=await send('Target.createTarget',{url:'about:blank'});
        const {sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
        const evaluate=async expression=>(await send('Runtime.evaluate',{expression,returnByValue:true},sessionId)).result.value;
        for(const width of [390,768,1024,1440])for(const page of ['initial','specialty','detail']){
            await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false},sessionId);
            await send('Page.navigate',{url:'file:///'+path.join(out,'browser-'+page+'.html').replaceAll('\\','/')},sessionId);
            await new Promise(r=>setTimeout(r,250));
            const metrics=JSON.parse(await evaluate(`JSON.stringify({viewport:innerWidth,documentWidth:document.documentElement.scrollWidth,nestedLinks:document.querySelectorAll('a a').length,h1:document.querySelectorAll('h1').length,unlabelledInputs:[...document.querySelectorAll('input:not([type=hidden]):not([hidden]),select:not([hidden])')].filter(el=>!el.labels?.length&&!el.getAttribute('aria-label')).length,landmark:!!document.querySelector('main'),skipLink:!!document.querySelector('.directory-skip-link')})`));
            if(metrics.documentWidth>width||metrics.nestedLinks||metrics.h1!==1||metrics.unlabelledInputs||!metrics.skipLink)throw Error('Layout/labels failed: '+JSON.stringify(metrics));
            const accessibility=await send('Accessibility.getFullAXTree',{},sessionId);
            metrics.accessibilityNodes=accessibility.nodes.length;
            if(page==='initial'){
                await evaluate(`document.getElementById('directory-specialty-search').focus()`);
                await send('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowUp',code:'ArrowUp',windowsVirtualKeyCode:38},sessionId);
                await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13},sessionId);
                metrics.keyboardSelected=await evaluate(`document.getElementById('directory-specialty').value`);
                if(!metrics.keyboardSelected)throw Error('Keyboard selection failed');
                await evaluate(`document.getElementById('directory-specialty-search').value='NO-RESULT-SYNTHETIC';document.getElementById('directory-specialty-search').dispatchEvent(new Event('input'))`);
                metrics.emptySearchAnnouncement=await evaluate(`document.querySelector('.directory-specialty-status').textContent`);
                await evaluate(`document.getElementById('directory-specialty-search').blur()`);
            }
            const image=await send('Page.captureScreenshot',{format:'png'},sessionId);
            fs.writeFileSync(path.join(out,page+'-'+width+'.png'),Buffer.from(image.data,'base64'));
            report.push({page,width,...metrics});console.log(page,width,JSON.stringify(metrics));
        }
        fs.writeFileSync(path.join(out,'browser-results.json'),JSON.stringify(report,null,2));
    }catch(error){console.error(error);process.exitCode=1;}
    finally{clearTimeout(timeout);await send('Browser.close').catch(()=>{});}
})();
