const fs=require('node:fs');
function luminance(hex){return hex.match(/\w\w/g).map(v=>parseInt(v,16)/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4).reduce((sum,v,i)=>sum+v*[.2126,.7152,.0722][i],0);}
const pairs=[['hero before','64748b','eff6ff'],['hero corrected','52677d','eff6ff'],['body text','64748b','ffffff'],['button','ffffff','1d4ed8'],['specialty tag','1e40af','eff6ff'],['focus outline','1d4ed8','eff6ff']];
const results=pairs.map(([label,fg,bg])=>{const a=luminance(fg),b=luminance(bg);return {label,foreground:'#'+fg,background:'#'+bg,ratio:+((Math.max(a,b)+.05)/(Math.min(a,b)+.05)).toFixed(2)};});
console.log(JSON.stringify(results,null,2));
fs.writeFileSync('storage/app/directory-audit/final/contrast.json',JSON.stringify(results,null,2));
