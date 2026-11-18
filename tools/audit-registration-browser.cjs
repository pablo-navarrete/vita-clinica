// Replay the synthetic Laravel verification response on loopback; never contacts VITA or real patients.
const fs=require('node:fs'), path=require('node:path'), http=require('node:http'), {spawn}=require('node:child_process');
const root=path.resolve(__dirname,'..'), directory=path.join(root,'storage/framework/testing/registration-browser');
const html=fs.readFileSync(path.join(directory,'page.html'),'utf8'), headers=JSON.parse(fs.readFileSync(path.join(directory,'headers.json'),'utf8'));
const token='abc123'.repeat(10)+'abcd'; // Synthetic bearer, not associated with any database record.
let posts=0, correctBody=false, leaked=false, external=false;
const server=http.createServer((req,res)=>{
    if(req.url.includes(token)||(req.headers.referer||'').includes(token))leaked=true;
    if(req.method==='POST'){
        posts++; let body=''; req.on('data',chunk=>body+=chunk); req.on('end',()=>{
            correctBody=new URLSearchParams(body).get('registration_token')===token;
            res.writeHead(303,{Location:'/finished'}); res.end();
        });
    }else if(req.url==='/finished'){res.end('<!doctype html><title>Processed</title><p>Processed</p>');}
    else if(req.url==='/favicon.ico'){res.writeHead(204);res.end();}
    else{res.writeHead(200,{'Content-Type':'text/html; charset=utf-8',...headers});res.end(html);}
});
let chrome,id=0,buffer='',pending=new Map();
function send(method,params={},sessionId){return new Promise((resolve,reject)=>{const seq=++id;pending.set(seq,{resolve,reject});chrome.stdio[3].write(JSON.stringify({id:seq,method,params,...(sessionId?{sessionId}:{})})+'\0');});}
const timeout=setTimeout(()=>{chrome?.kill();server.close();process.exitCode=1;},30000);
(async()=>{
    try{
        await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
        const origin='http://127.0.0.1:'+server.address().port;
        chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless','--disable-gpu','--disable-extensions','--disable-background-networking','--no-first-run','--no-default-browser-check','--remote-debugging-pipe','--user-data-dir='+path.join(directory,'profile')],{stdio:['ignore','ignore','ignore','pipe','pipe'],windowsHide:true});
        chrome.stdio[4].on('data',data=>{buffer+=data.toString();let end;while((end=buffer.indexOf('\0'))>=0){const part=buffer.slice(0,end);buffer=buffer.slice(end+1);if(!part)continue;const msg=JSON.parse(part),p=pending.get(msg.id);if(p){pending.delete(msg.id);msg.error?p.reject(Error('Browser command failed')):p.resolve(msg.result);}if(msg.method==='Network.requestWillBeSent'){const req=msg.params.request;if(req.url.includes(token)||Object.values(req.headers||{}).some(v=>String(v).includes(token)))leaked=true;if(!req.url.startsWith(origin))external=true;}}});
        const {targetId}=await send('Target.createTarget',{url:'about:blank'});
        const {sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
        await send('Network.enable',{},sessionId);
        const evaluate=async expression=>(await send('Runtime.evaluate',{expression,returnByValue:true},sessionId)).result.value;
        await send('Page.navigate',{url:origin+'/agendar/verificar/synthetic-operation#'+token},sessionId);
        let ready=false;const deadline=Date.now()+8000;
        while(!ready&&Date.now()<deadline){ready=await evaluate("document.getElementById('registration-token')?.value.length===64 && !location.hash");if(!ready)await new Promise(r=>setTimeout(r,30));}
        if(!ready||posts)throw Error('Fragment handling or GET side effect failed');
        await evaluate("document.querySelector('button').click()");
        while(posts<1&&Date.now()<deadline)await new Promise(r=>setTimeout(r,30));
        const ok=ready&&posts===1&&correctBody&&!leaked&&!external;
        const report={fragment_cleared:ready,no_verification_on_get:true,single_form_post:posts===1,token_only_in_post_body:correctBody,no_token_in_request_urls_or_headers:!leaked,no_external_resources_or_analytics:!external,passed:ok};
        fs.writeFileSync(path.join(directory,'report.json'),JSON.stringify(report,null,2));
        process.stdout.write(JSON.stringify(report,null,2)+'\n');if(!ok)process.exitCode=1;
    }catch(error){process.stderr.write('Synthetic browser verification failed: '+error.message+'\n');process.exitCode=1;}
    finally{clearTimeout(timeout);chrome?.kill();server.closeAllConnections();server.close();}
})();
