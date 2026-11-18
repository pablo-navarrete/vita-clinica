const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../../public/js/vita-patient-access.js'),'utf8');
const flush=()=>new Promise(resolve=>setImmediate(resolve));
function fixture(){
    const guests=[{hidden:false},{hidden:false}],panel={hidden:true},events={},calls=[];
    const group={dataset:{sessionUrl:'http://vita.test/mi-salud/estado-sesion'},querySelectorAll:()=>guests,querySelector:()=>panel};
    const state={authenticated:true,fail:false};
    const document={hidden:false,querySelectorAll:()=>[group],addEventListener:(name,handler)=>{events[name]=handler;}};
    const window={location:{href:'http://vita.test/',origin:'http://vita.test'},addEventListener:(name,handler)=>{events[name]=handler;}};
    vm.runInNewContext(source,{document,window,URL,fetch:async(url,options)=>{
        calls.push({url,options});if(state.fail)throw Error('Offline');return {ok:true,json:async()=>({authenticated:state.authenticated})};
    }});
    return {guests,panel,state,events,calls};
}
test('signed-in patient sees only the panel link and requests the isolated session with cookies',async()=>{
    const f=fixture();await flush();
    assert(f.guests.every(link=>link.hidden));assert.equal(f.panel.hidden,false);
    assert.equal(f.calls[0].url,'http://vita.test/mi-salud/estado-sesion');
    assert.equal(f.calls[0].options.credentials,'same-origin');assert.equal(f.calls[0].options.cache,'no-store');
});
test('returning after logout restores login and registration links',async()=>{
    const f=fixture();await flush();f.state.authenticated=false;await f.events.focus();
    assert(f.guests.every(link=>!link.hidden));assert.equal(f.panel.hidden,true);
});
test('back navigation rechecks the session and errors fall back to guest access',async()=>{
    const f=fixture();await flush();f.state.fail=true;f.events.pageshow({persisted:true});await flush();
    assert(f.guests.every(link=>!link.hidden));assert.equal(f.panel.hidden,true);
});
