// Synthetic browser fixture. No application accounts or database access.
const {spawn}=require('node:child_process');
const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..');
const chart=`<div style="position:relative;height:240px"><canvas data-attention-chart='{"labels":["Ene","Feb"],"values":[2,1]}'></canvas></div>`;
const booking=`<form action="/mi-salud/agendar"><label for="booking-specialty">Especialidad</label><select id="booking-specialty" name="especialidad" data-search-select data-search-placeholder="Buscar especialidad" data-options-label="Especialidades" data-empty-message="Sin coincidencias"><option value="">Todas</option><option value="1">Pediatr\u00eda</option><option value="2">Cardiolog\u00eda</option></select><button>Buscar</button><button type="button" data-portal-location data-url="/mi-salud/agendar/ubicacion">Ubicacion</button></form><p id="portal-booking-location-status"></p>`;
const page=(url)=>`<!doctype html><html><head><title>Mi Salud</title><link rel="stylesheet" href="/css/clinic-field-focus.css"><meta name="csrf-token" content="fixture"><script src="/js/mi-salud.js" defer></script><script src="/js/vendor/chart.umd.min.js" defer></script><script src="/js/mi-salud-charts.js" defer></script><script src="/js/mi-salud-navigation.js" defer></script></head><body class="vita-patient-portal">
<aside id="portal-sidebar" data-preference-key="fixture"><button data-portal-menu-toggle></button><nav><a href="/mi-salud/panel"><i class="vita-menu-icon"></i>Panel</a><a href="/mi-salud/perfil"><i class="vita-menu-icon"></i>Perfil</a><a href="/mi-salud/mis-atenciones"><i class="vita-menu-icon"></i>Atenciones</a><a href="/mi-salud/agendar"><i class="vita-menu-icon"></i>Agendar</a></nav></aside><button data-portal-menu-close hidden></button><button class="portal-mobile-menu"></button><div id="viti">Viti</div>
<div id="portal-content-loading" hidden></div><main id="contenido" tabindex="-1"><h1>${url.pathname}</h1>${url.pathname.endsWith('/panel')?`<form action="/mi-salud/panel" data-attention-filters><select name="year"><option>2026</option><option>2025</option></select><select name="month"><option value="">Todos</option><option value="1">Enero</option></select></form><div data-attention-results><p id="period">${url.search}</p>${chart}</div>`:url.pathname.endsWith('/perfil')?`<form id="profile-form" method="POST" action="/mi-salud/perfil"><select id="country" name="identification_country" data-country-select><option value="CL">Chile</option><option value="PE">Perú</option></select><input id="fixture-checkbox" type="checkbox"><input id="fixture-radio" type="radio"><input id="password" type="password"><button type="button" class="portal-password-toggle" aria-controls="password"><span data-password-eye-open></span><span data-password-eye-closed hidden></span></button><button type="submit">Guardar</button></form>`:url.pathname.endsWith('/agendar')?booking:'Historial'}</main></body></html>`;
let requests=[];
const server=http.createServer((req,res)=>{
    const url=new URL(req.url,'http://localhost');
    if(url.pathname==='/css/clinic-field-focus.css') {
        res.setHeader('Content-Type','text/css');return res.end(fs.readFileSync(path.join(root,'public/css/clinic-field-focus.css')));
    }
    if(['/js/mi-salud.js','/js/mi-salud-navigation.js','/js/vendor/chart.umd.min.js','/js/mi-salud-charts.js'].includes(url.pathname)){
        res.setHeader('Content-Type','text/javascript');return res.end(fs.readFileSync(path.join(root,'public',url.pathname)));
    }
    requests.push({path:url.pathname,search:url.search,method:req.method});
    res.setHeader('Content-Type','text/html; charset=utf-8');
    if(req.headers['x-mi-salud-summary']==='1') {
        res.setHeader('X-Mi-Salud-Summary','1');
        return setTimeout(()=>res.end(`<div data-attention-results><p id="period">${url.search}</p>${chart}</div>`),180);
    }
    res.end(page(url));
});
let chrome,id=0,buffer='';const pending=new Map();
function send(method,params={},sessionId){return new Promise((resolve,reject)=>{const seq=++id;pending.set(seq,{resolve,reject});chrome.stdio[3].write(JSON.stringify({id:seq,method,params,...(sessionId?{sessionId}:{})})+'\0');});}
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
const timeout=setTimeout(()=>{chrome?.kill();server.close();process.exit(1)},55000);
(async()=>{
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
    const output=path.join(root,'storage','app','mi-salud-navigation-audit');fs.mkdirSync(output,{recursive:true});
    chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-pipe','--user-data-dir='+path.join(output,'browser-profile')],{stdio:['ignore','ignore','ignore','pipe','pipe'],windowsHide:true});
    chrome.stdio[4].on('data',data=>{buffer+=data.toString();let end;while((end=buffer.indexOf('\0'))>=0){const part=buffer.slice(0,end);buffer=buffer.slice(end+1);if(!part)continue;const msg=JSON.parse(part),p=pending.get(msg.id);if(p){pending.delete(msg.id);msg.error?p.reject(Error(JSON.stringify(msg.error))):p.resolve(msg.result);}}});
    try {
        const {targetId}=await send('Target.createTarget',{url:'about:blank'});
        const {sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
        const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true},sessionId);if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
        const wait=async expression=>{for(let i=0;i<100;i++){if(await evaluate(expression))return;await delay(50);}throw Error('Timed out: '+expression);};
        await send('Page.navigate',{url:`http://127.0.0.1:${server.address().port}/mi-salud/panel`},sessionId);
        await wait('!!window.PortalCharts && Object.keys(Chart.instances).length===1');
        assert.equal(await evaluate('Chart.version'),'4.5.1','Same local Chart.js version');
        await evaluate("window.originalViti=document.getElementById('viti');window.pageMarker='persistent';document.querySelector('a[href$=perfil]').click()");
        await wait("!!document.querySelector('.portal-country-trigger')");
        assert.equal(await evaluate("document.getElementById('viti')===window.originalViti && window.pageMarker==='persistent'"),true,'Shell remains mounted');
        assert.equal(await evaluate('Object.keys(Chart.instances).length'),0,'Charts disposed when leaving panel');
        await evaluate("document.querySelector('#fixture-checkbox').focus()");
        assert.deepEqual(await evaluate("{const field=document.querySelector('#fixture-checkbox');const style=getComputedStyle(field);[style.appearance,style.borderRadius,style.boxShadow.includes('3px')]}"),['none','6px',true],'Checkbox focus follows rounded custom control');
        await evaluate("document.querySelector('#fixture-checkbox').click();document.querySelector('#fixture-radio').click()");
        assert.equal(await evaluate("document.querySelector('#fixture-checkbox').checked && document.querySelector('#fixture-radio').checked"),true,'Rounded controls retain native selection');
        assert.equal(await evaluate("getComputedStyle(document.querySelector('#fixture-radio')).borderRadius"),'50%','Radio focus remains circular');
        await evaluate("document.querySelector('.portal-password-toggle').click()");
        assert.equal(await evaluate("document.querySelector('#password').type"),'text','Profile controls reinitialize');
        await evaluate("document.querySelector('#profile-form').requestSubmit()");
        await wait("!document.querySelector('#contenido').hasAttribute('aria-busy')");
        assert.equal(requests.filter(r=>r.method==='POST').length,1,'Profile submits once');
        await evaluate("document.querySelector('a[href$=panel]').click()");
        await wait("!!document.querySelector('[data-attention-filters]')");
        await evaluate("window.originalFilters=document.querySelector('[data-attention-filters]');let year=document.querySelector('[name=year]');year.value='2025';year.dispatchEvent(new Event('change',{bubbles:true}))");
        assert.equal(await evaluate("document.getElementById('portal-content-loading').hidden"),false,'Preloader appears during fetch');
        await wait("document.querySelector('#period')?.textContent.includes('year=2025')");
        assert.equal(await evaluate("document.querySelector('[data-attention-filters]')===window.originalFilters"),true,'Filters remain mounted');
        assert.equal(await evaluate("document.getElementById('portal-content-loading').hidden"),true,'Preloader clears after response');
        assert.equal(await evaluate('Object.keys(Chart.instances).length'),1,'Chart remounts after filters without leaked instances');
        assert.deepEqual(await evaluate("Chart.getChart(document.querySelector('canvas')).data.datasets[0].data"),[2,1]);
        // Retained filter controls keep their selections without recreating the dropdowns.
        await evaluate("{let year=document.querySelector('[name=year]');year.value='2025';let month=document.querySelector('[name=month]');month.value='1';month.dispatchEvent(new Event('change',{bubbles:true}));}");
        await wait("document.querySelector('#period')?.textContent.includes('month=1')");
        await evaluate("document.querySelector('a[href$=mis-atenciones]').click()");
        await wait("document.querySelector('h1')?.textContent.endsWith('mis-atenciones')");
        assert.equal(await evaluate("document.querySelector('a[aria-current=page]').pathname"),'/mi-salud/mis-atenciones');
        await evaluate("document.querySelector('a[href$=agendar]').click()");
        await wait("document.querySelector('h1')?.textContent.endsWith('agendar')");
        assert.equal(await evaluate("document.getElementById('viti')===window.originalViti"),true,'Booking directory retains shell');
        await wait("!!document.querySelector('#booking-specialty-search')");
        await evaluate("document.querySelector('#booking-specialty').click()");
        assert.equal(await evaluate("document.querySelector('#booking-specialty-results').children.length"),3,'Opening specialty selector immediately shows options');
        await evaluate("{let input=document.querySelector('#booking-specialty-search');input.value='pediatria';input.dispatchEvent(new Event('input',{bubbles:true}));}");
        assert.equal(await evaluate("document.querySelector('#booking-specialty-results').textContent"),'Pediatr\u00eda','Specialty search ignores accents');
        await evaluate("document.querySelector('#booking-specialty-results button').click()");
        assert.equal(await evaluate("document.querySelector('[name=especialidad]').value"),'1','Selection retains actual specialty ID');
        assert.equal(requests.filter(r=>r.path.endsWith('/ubicacion')).length,0,'No location requested before consent');
        await evaluate("Object.defineProperty(navigator,'geolocation',{configurable:true,value:{getCurrentPosition(success){success({coords:{latitude:-32.880123,longitude:-71.250123}})}}});document.querySelector('[data-portal-location]').click()");
        await wait("location.search.includes('cercanas=1') && !document.querySelector('#contenido').hasAttribute('aria-busy')");
        assert.equal(requests.filter(r=>r.path.endsWith('/ubicacion') && r.method==='POST').length,1,'Location saved through portal endpoint');
        assert.equal(await evaluate("location.search.includes('especialidad=1') && document.getElementById('viti')===window.originalViti"),true,'Nearby filtering preserves specialty and shell');
        await evaluate("navigator.geolocation.getCurrentPosition=(success,error)=>error({code:1});document.querySelector('[data-portal-location]').click()");
        assert.equal(await evaluate("document.getElementById('portal-booking-location-status').textContent.includes('No autorizaste')"),true,'Denied consent shows feedback');


        await evaluate('history.back()');
        await wait("!location.search.includes('cercanas=1') && !document.querySelector('#contenido').hasAttribute('aria-busy')");
        await evaluate('history.back()');
        await wait("document.querySelector('h1')?.textContent.endsWith('mis-atenciones')");
        await evaluate('history.back()');
        await wait("!!document.querySelector('[data-attention-filters]')");
        assert.equal(await evaluate("document.getElementById('viti')===window.originalViti"),true,'Back retains shell');
        console.log('OK: local Chart.js rendering and cleanup, content navigation, persistent shell, profile initialization and save, automatic year/month filters, active menu and browser history.');
    } finally {clearTimeout(timeout);chrome?.kill();server.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
