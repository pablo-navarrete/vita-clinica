// Local synthetic fixture; no patients, database writes or third-party requests.
const {spawn}=require('node:child_process');
const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..'),build=path.join(root,'public','build');
const manifest=JSON.parse(fs.readFileSync(path.join(build,'manifest.json'),'utf8'));
const phone=manifest['resources/js/vita-phone.js'],app=manifest['resources/js/app.js'];
const html=`<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
${phone.css.map(file=>`<link rel="stylesheet" href="/build/${file}">`).join('')}
<style>*{box-sizing:border-box}body{font:16px system-ui;margin:0;padding:24px;background:#eff6ff}main{max-width:440px;margin:auto;padding:24px;background:white;border-radius:18px}input{width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:8px}label{display:block;margin:16px 0 6px}button{padding:10px}</style>
<script type="module" src="/build/${phone.file}"></script><script type="module" src="/build/${app.file}"></script></head>
<body><main><h1>Teléfonos de VITA</h1><form id="fixture" x-data="{phone:'+5491123456789'}"><label for="phone">Teléfono</label><input id="phone" name="phone" value="+56987654321"><label for="alpine-phone">Profesional</label><input id="alpine-phone" data-vita-phone x-model="phone"><label for="plain">Nombre</label><input id="plain" name="name"><button type="submit">Guardar</button></form><a id="contact" href="tel:+14165550123">+1 416 555 0123</a></main></body></html>`;
const server=http.createServer((req,res)=>{
    if(req.url==='/'){res.setHeader('Content-Type','text/html; charset=utf-8');return res.end(html);}
    const pathname=new URL(req.url,'http://localhost').pathname;
    if(!/^\/build\/assets\/[A-Za-z0-9@_.-]+$/.test(pathname)){res.writeHead(404);return res.end();}
    const file=path.join(build,'assets',path.basename(pathname));
    if(!fs.existsSync(file)){res.writeHead(404);return res.end();}
    res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':'image/webp');res.end(fs.readFileSync(file));
});
let chrome,id=0,buffer='';const pending=new Map();
function send(method,params={},sessionId){return new Promise((resolve,reject)=>{const seq=++id;pending.set(seq,{resolve,reject});chrome.stdio[3].write(JSON.stringify({id:seq,method,params,...(sessionId?{sessionId}:{})})+'\0');});}
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
const timeout=setTimeout(()=>{chrome?.kill();server.close();process.exit(1)},55000);
(async()=>{
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
    const output=path.join(root,'storage','app','phone-audit');fs.mkdirSync(output,{recursive:true});
    chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-pipe','--user-data-dir='+path.join(output,'browser-profile')],{stdio:['ignore','ignore','ignore','pipe','pipe'],windowsHide:true});
    chrome.stdio[4].on('data',data=>{buffer+=data.toString();let end;while((end=buffer.indexOf('\0'))>=0){const part=buffer.slice(0,end);buffer=buffer.slice(end+1);if(!part)continue;const msg=JSON.parse(part),p=pending.get(msg.id);if(p){pending.delete(msg.id);msg.error?p.reject(Error(JSON.stringify(msg.error))):p.resolve(msg.result);}}});
    try{
        const {targetId}=await send('Target.createTarget',{url:'about:blank'});
        const {sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
        const evaluate=async expression=>{
            const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true},sessionId);
            if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;
        };
        const report=[];
        for(const width of [390,1440]){
            await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false},sessionId);
            await send('Page.navigate',{url:`http://127.0.0.1:${server.address().port}/`},sessionId);
            for(let attempt=0;attempt<30;attempt++){
                if(await evaluate(`!!window.VitaPhone && !!document.querySelector('#phone')?.closest('.iti') && !!window.Alpine`))break;
                await delay(100);
            }
            await delay(150);
            assert.equal(await evaluate(`!!document.querySelector('#phone').closest('.iti').querySelector('.iti__cl')`),true,'Initial Chile flag');
            assert.equal(await evaluate(`!!document.querySelector('#alpine-phone').closest('.iti').querySelector('.iti__ar')`),true,'Alpine Argentina flag');
            assert.equal(await evaluate(`!!document.querySelector('#contact .iti__ca')`),true,'Public phone link has country flag');
            await evaluate(`document.querySelector('#phone').focus();document.querySelector('#phone').select()`);
            await send('Input.insertText',{text:'+5491123456789'},sessionId);await delay(70);
            assert.equal(await evaluate(`!!document.querySelector('#phone').closest('.iti').querySelector('.iti__ar')`),true,'Typing international code changes flag: '+await evaluate(`JSON.stringify({value:document.querySelector('#phone').value,html:document.querySelector('#phone').closest('.iti').outerHTML})`));
            for(const [number,country]of [['+51987654321','pe'],['+14165550123','ca'],['+12025550123','us'],['+34612345678','es']]){
                await evaluate(`document.querySelector('#phone').value=${JSON.stringify(number)}`);await delay(70);
                assert.equal(await evaluate(`!!document.querySelector('#phone').closest('.iti').querySelector('.iti__${country}')`),true,`${country} flag after programmatic edit`);
                assert.equal(await evaluate(`window.VitaPhone.getNumber(document.querySelector('#phone'))`),number);
            }
            await evaluate(`document.querySelector('#phone').value='+999123456789'`);await delay(50);
            assert.equal(await evaluate(`window.VitaPhone.validate(document.querySelector('#phone'))`),false);
            assert.equal(await evaluate(`document.querySelector('#phone').getAttribute('aria-invalid')`),'true');
            await evaluate(`document.querySelector('#phone').value='+56987654321'`);await delay(50);
            assert.equal(await evaluate(`window.VitaPhone.validate(document.querySelector('#phone'))`),true);
            await evaluate(`Alpine.$data(document.querySelector('#fixture')).phone='+573001234567'`);await delay(90);
            assert.equal(await evaluate(`!!document.querySelector('#alpine-phone').closest('.iti').querySelector('.iti__co')`),true,'Alpine edit updates flag');
            await evaluate(`document.querySelector('#alpine-phone').closest('.iti').querySelector('button').click()`);await delay(60);
            await evaluate(`document.querySelector('[data-iso2="pe"]').click()`);await delay(90);
            assert.equal(await evaluate(`typeof Alpine.$data(document.querySelector('#fixture')).phone`),'string','Country selection must keep Alpine phone as a string');
            assert.equal(await evaluate(`document.querySelector('#alpine-phone').value.includes('[object Object]')`),false,'Country selection must not render an object');
            for (const [country,national,expected] of [['cl','987654321','+56987654321'],['us','2025550123','+12025550123']]) {
                await evaluate(`document.querySelector('#phone').value=''`);await delay(50);
                await evaluate(`document.querySelector('#phone').closest('.iti').querySelector('button').click()`);await delay(40);
                await evaluate(`document.querySelector('[data-iso2="${country}"]').click();document.querySelector('#phone').focus();document.querySelector('#phone').select()`);await delay(40);
                await send('Input.insertText',{text:national},sessionId);await delay(70);
                assert.equal(await evaluate(`window.VitaPhone.getNumber(document.querySelector('#phone'))`),expected,`${country}: national number gets the selected prefix`);
                assert.equal(await evaluate(`new FormData(document.querySelector('#fixture')).get('phone')`),expected,'Form submits the complete international number');
                assert.equal(await evaluate(`document.querySelector('#phone').closest('.iti').querySelector('input[type=tel]').value.startsWith('+')`),false,'Visible input does not require a country prefix');
                assert.equal(await evaluate(`document.querySelector('#phone-national').value`),national,'Visible input contains only the national digits');
                await send('Input.insertText',{text:'abc +'},sessionId);await delay(50);
                assert.equal(await evaluate(`document.querySelector('#phone-national').value`),national,'Letters and plus sign cannot be typed');
                if(country==='cl'){
                    await evaluate(`(()=>{const clipboard=new DataTransfer();clipboard.setData('text/plain','+56 9 8765 4321');document.querySelector('#phone-national').dispatchEvent(new ClipboardEvent('paste',{clipboardData:clipboard,bubbles:true,cancelable:true}));})()`);await delay(70);
                    assert.equal(await evaluate(`document.querySelector('#phone-national').value`),'987654321','Pasted international prefix is removed');
                    assert.equal(await evaluate(`document.querySelector('#phone').value`),'+56987654321','Pasted prefix is not duplicated in stored value');
                    assert.equal(await evaluate(`document.querySelector('#phone').closest('.iti').nextElementSibling.textContent.includes('Lo quitamos')`),true,'Prefix removal is explained');
                    await evaluate(`document.querySelector('#phone').value=''`);await delay(50);
                    await evaluate(`document.querySelector('#phone').focus();document.querySelector('#phone').select()`);
                    await send('Input.insertText',{text:'56987654321'},sessionId);await delay(70);
                    assert.equal(await evaluate(`document.querySelector('#phone-national').value`),'987654321','Duplicated prefix without plus is removed when unambiguous');
                    assert.equal(await evaluate(`window.VitaPhone.validate(document.querySelector('#phone'))`),true,'Normalized national number is valid');
                }
            }
            await evaluate(`document.querySelector('#alpine-phone').focus();document.querySelector('#alpine-phone').select()`);
            await send('Input.insertText',{text:'987654321'},sessionId);await delay(70);
            assert.equal(await evaluate(`Alpine.$data(document.querySelector('#fixture')).phone`),'+51987654321','Alpine JSON forms receive selected Peru prefix');
            await evaluate(`document.querySelector('#phone').closest('.iti').querySelector('button').click()`);await delay(120);
            const bounds=await evaluate(`(()=>{const el=[...document.querySelectorAll('.iti__country-selector')].find(el=>el.getBoundingClientRect().height>0);if(!el)return null;const r=el.getBoundingClientRect();return {left:r.left,right:r.right,width:r.width,top:r.top,height:r.height,viewport:innerWidth}})()`);
            assert(bounds && bounds.left>=0 && bounds.right<=width+1,'Country dropdown stays within viewport: '+JSON.stringify(bounds));
            assert.equal(await evaluate(`document.documentElement.scrollWidth<=innerWidth`),true,'No horizontal overflow');
            const shot=await send('Page.captureScreenshot',{format:'png'},sessionId);fs.writeFileSync(path.join(output,`phone-${width}.png`),Buffer.from(shot.data,'base64'));
            await evaluate(`document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));const field=document.createElement('input');field.id='dynamic';field.name='reply_whatsapp';field.value='+5511912345678';document.querySelector('main').append(field)`);await delay(90);
            assert.equal(await evaluate(`!!document.querySelector('#dynamic').closest('.iti').querySelector('.iti__br')`),true,'Dynamic input is enhanced');
            report.push({width,passed:true,dropdown:bounds});
        }
        console.log(JSON.stringify(report,null,2));
    }finally{clearTimeout(timeout);chrome.kill();server.close();}
})().catch(error=>{console.error(error);process.exitCode=1;clearTimeout(timeout);chrome?.kill();server.close();});
