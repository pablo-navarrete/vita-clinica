// Synthetic HTML from PortalPublicBookingTest only; no live VITA sessions or database writes.
const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),{spawn}=require('node:child_process');
const root=path.resolve(__dirname,'..'),directory=path.join(root,'storage/framework/testing/portal-booking-preview');
const bookingHtml=fs.readFileSync(path.join(directory,'booking.html'),'utf8');
const professional=JSON.parse(bookingHtml.match(/const oldProfessional = (.*);/)[1]);
const selectedTime=JSON.parse(bookingHtml.match(/const oldTime = (.*);/)[1]);
const token='ab12'.repeat(16);let leaked=false,posts=0,bodyCorrect=false,landingExternal=false,phase='landing';
let chrome,sequence=0,buffer='',pending=new Map();
const server=http.createServer((req,res)=>{
 if(req.url.includes(token)||String(req.headers.referer||'').includes(token))leaked=true;
 if(req.method==='POST') {let body='';req.on('data',c=>body+=c);req.on('end',()=>{posts++;bodyCorrect=!new URLSearchParams(body).has('authorization_code');res.writeHead(303,{Location:'/done'});res.end();});return;}
 if(req.url==='/done'){res.end('<!doctype html><title>Done</title>');return;}
 if(req.url==='/favicon.ico'){res.writeHead(204);res.end();return;}
 if(req.url.startsWith('/agendar/profesionales')){res.setHeader('Content-Type','application/json');res.end(JSON.stringify([{id:professional,name:'Profesional de prueba'}]));return;}
 if(req.url.startsWith('/agendar/disponibilidad-semanal')){res.setHeader('Content-Type','application/json');res.end('{"availability":{}}');return;}
 if(req.url.startsWith('/agendar/disponibilidad')){res.setHeader('Content-Type','application/json');res.end(JSON.stringify(phase==='stale'?[]:[{value:selectedTime,label:selectedTime}]));return;}
 const asset=path.resolve(root,'public','.'+req.url.split('?')[0]);
 if(asset.startsWith(path.join(root,'public')+path.sep)&&fs.existsSync(asset)&&fs.statSync(asset).isFile()){
  res.setHeader('Content-Type',asset.endsWith('.js')?'application/javascript':asset.endsWith('.css')?'text/css':'application/octet-stream');res.end(fs.readFileSync(asset));return;
 }
 const page=req.url.startsWith('/return')?'return':'booking';
 const origin='http://127.0.0.1:'+server.address().port;
 let html=fs.readFileSync(path.join(directory,page+'.html'),'utf8').replace(/https?:\/\/[^/" ]*vita-clinica\.test/g,origin);
 res.setHeader('Content-Type','text/html; charset=utf-8');res.setHeader('Referrer-Policy','no-referrer');res.setHeader('Cache-Control','no-store');
 if(page==='return') {const nonce=html.match(/nonce="([^"]+)"/)[1];res.setHeader('Content-Security-Policy',`default-src 'none'; script-src 'nonce-${nonce}'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'`);}
 res.end(html);
});
function send(method,params={},sessionId){return new Promise((resolve,reject)=>{const id=++sequence;pending.set(id,{resolve,reject});chrome.stdio[3].write(JSON.stringify({id,method,params,...(sessionId?{sessionId}:{})})+'\0');});}
const timeout=setTimeout(()=>{chrome?.kill();server.closeAllConnections();server.close();process.exitCode=1;},90000);
(async()=>{try{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const origin='http://127.0.0.1:'+server.address().port;
 chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless','--disable-gpu','--disable-extensions','--disable-background-networking','--no-first-run','--no-default-browser-check','--remote-debugging-pipe','--user-data-dir='+path.join(directory,'chrome-profile')],{stdio:['ignore','ignore','ignore','pipe','pipe'],windowsHide:true});
 chrome.stdio[4].on('data',chunk=>{buffer+=chunk.toString();let end;while((end=buffer.indexOf('\0'))>=0){const part=buffer.slice(0,end);buffer=buffer.slice(end+1);if(!part)continue;const message=JSON.parse(part),p=pending.get(message.id);if(p){pending.delete(message.id);message.error?p.reject(Error(message.error.message)):p.resolve(message.result);}
  if(message.method==='Network.requestWillBeSent'){const r=message.params.request;if(r.url.includes(token)||Object.values(r.headers||{}).some(v=>String(v).includes(token)))leaked=true;if(phase==='landing'&&!r.url.startsWith(origin))landingExternal=true;}
 }});
 const {targetId}=await send('Target.createTarget',{url:'about:blank'});const {sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
 await send('Network.enable',{},sessionId);const evaluate=async expression=>(await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true},sessionId)).result.value;
 const wait=async expression=>{const deadline=Date.now()+15000;while(Date.now()<deadline){try{if(await evaluate(expression))return;}catch(error){if(!/context|navigated|closed/i.test(error.message))throw error;}await new Promise(r=>setTimeout(r,50));}throw Error('Browser wait timed out');};
 await send('Page.navigate',{url:origin+'/return#'+token},sessionId);await wait("document.title==='Done' && !location.hash");
 phase='booking';await send('Page.navigate',{url:origin+'/booking'},sessionId);
 await wait("document.readyState==='complete' && !!document.getElementById('public-booking-form')");
 await wait(`document.getElementById('booking-professional').value===${JSON.stringify(String(professional))} && document.getElementById('booking-time').value===${JSON.stringify(selectedTime)}`);
 const layouts=[];
 for(const width of [390,768,1024,1440]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width===390},sessionId);
  await new Promise(r=>setTimeout(r,150));
  const metrics=await evaluate(`({overflow:document.documentElement.scrollWidth>innerWidth,authorizedFieldsHidden:[...document.querySelectorAll('[name="name"],[name="email"],[name="identification_number"]')].every(e=>e.closest('.form-group').hidden),submitVisible:!!document.querySelector('.booking-submit'),summary:[...document.querySelectorAll('[role="status"]')].some(e=>e.textContent.includes('Revisa tu reserva'))})`);
  layouts.push({width,...metrics});
  const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true},sessionId);fs.writeFileSync(path.join(directory,'booking-'+width+'.png'),Buffer.from(shot.data,'base64'));
 }
 phase='stale';await send('Page.navigate',{url:origin+'/booking?stale=1'},sessionId);
 await wait("document.readyState==='complete' && !!document.getElementById('booking-slot-notice') && !document.getElementById('booking-slot-notice').hidden");
 const staleSlotHandled=await evaluate(`document.getElementById('booking-professional').value===${JSON.stringify(String(professional))} && document.getElementById('booking-time').value==='' && !!document.getElementById('booking-date').value`);
 const report={fragmentRemoved:true,tokenAbsentFromUrlsAndHeaders:!leaked,noExternalResourcesOnAuthorizationLanding:!landingExternal,authorizationCodeAbsentFromBrowserPost:posts===1&&bodyCorrect,selectionRestored:true,staleSlotHandled,layouts};
 report.passed=report.tokenAbsentFromUrlsAndHeaders&&report.noExternalResourcesOnAuthorizationLanding&&report.authorizationCodeAbsentFromBrowserPost&&staleSlotHandled&&layouts.every(m=>!m.overflow&&m.authorizedFieldsHidden&&m.submitVisible&&m.summary);
 fs.writeFileSync(path.join(directory,'report.json'),JSON.stringify(report,null,2));process.stdout.write(JSON.stringify(report,null,2)+'\n');if(!report.passed)process.exitCode=1;
}catch(error){process.stderr.write('Synthetic public-booking browser audit failed: '+error.message+'\n');process.exitCode=1;}
finally{clearTimeout(timeout);chrome?.kill();server.closeAllConnections();server.close();}})();
