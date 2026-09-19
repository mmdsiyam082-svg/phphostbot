<?php
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PHP Host Pro</title>
<style>
*{box-sizing:border-box}body{margin:0;padding:24px;background:#050816;color:#fff;font-family:Arial,sans-serif}main{max-width:1000px;margin:auto}.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}.brand{display:flex;gap:12px;align-items:center}.logo{width:55px;height:55px;border-radius:16px;display:grid;place-items:center;background:linear-gradient(135deg,#2563eb,#7c3aed);font-weight:900}.sub{color:#94a3b8;font-size:13px}.status{color:#86efac;background:#052e16;border:1px solid #166534;padding:9px 12px;border-radius:999px;font-size:12px}.card{background:#0d1426;border:1px solid #1e293b;border-radius:20px;padding:22px;margin-bottom:18px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}label{display:block;color:#cbd5e1;font-size:13px;margin-bottom:7px}input{width:100%;padding:14px;border-radius:12px;border:1px solid #26334a;background:#070c18;color:#fff;outline:0}input:focus{border-color:#3b82f6}.full{grid-column:1/-1}.actions{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap}button{border:0;border-radius:12px;padding:12px 16px;font-weight:700;cursor:pointer}.primary{background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff}.secondary{background:#172033;color:#dbeafe}.danger{background:#35151a;color:#fca5a5}.msg{display:none;margin-top:14px;padding:12px;border-radius:12px}.show{display:block}.ok{background:#052e16;color:#86efac}.err{background:#3b1015;color:#fca5a5}.item{display:flex;justify-content:space-between;gap:12px;align-items:center;background:#080f1e;border:1px solid #1e293b;padding:15px;border-radius:14px;margin-top:10px}.url{color:#64748b;font-size:12px;margin-top:5px;word-break:break-all}.item-actions{display:flex;gap:7px;flex-wrap:wrap}.empty{text-align:center;color:#64748b;padding:25px}@media(max-width:650px){.grid{grid-template-columns:1fr}.full{grid-column:auto}.top{align-items:flex-start}.status{display:none}.item{flex-direction:column;align-items:flex-start;width:100%}}
</style>
</head>
<body>
<main>
<div class="top"><div class="brand"><div class="logo">PHP</div><div><h1 style="margin:0;font-size:23px">PHP Host Pro</h1><div class="sub">Real PHP Project Hosting</div></div></div><div class="status">SERVER ONLINE</div></div>
<section class="card">
<h2>Host New PHP Project</h2>
<div class="grid">
<div><label>Project Name</label><input id="project" maxlength="40" placeholder="mywebsite"></div>
<div><label>Hosting API Key</label><input id="key" type="password" placeholder="Enter API key"></div>
<div class="full"><label>PHP Project ZIP</label><input id="zip" type="file" accept=".zip,application/zip"></div>
</div>
<div class="actions"><button class="primary" id="upload">Upload & Host</button><button class="secondary" id="refresh">Refresh Projects</button></div>
<div id="msg" class="msg"></div>
</section>
<section class="card"><h2>Hosted Projects</h2><div id="list"><div class="empty">Enter API key to load projects.</div></div></section>
</main>
<script>
const $=id=>document.getElementById(id), key=$('key'), list=$('list'), msg=$('msg');
key.value=localStorage.getItem('php_host_api_key')||'';
key.onchange=()=>localStorage.setItem('php_host_api_key',key.value.trim());
function say(t,ok=true){msg.textContent=t;msg.className='msg show '+(ok?'ok':'err')}
function esc(s){return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}
function bytes(n){if(!n)return'0 B';let u=['B','KB','MB','GB'],i=Math.floor(Math.log(n)/Math.log(1024));return (n/Math.pow(1024,i)).toFixed(1)+' '+u[i]}
async function load(){
 const k=key.value.trim(); if(!k){list.innerHTML='<div class="empty">Enter API key to load projects.</div>';return}
 list.innerHTML='<div class="empty">Loading...</div>';
 try{let r=await fetch('api.php?action=projects',{headers:{'X-API-Key':k}}),d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Unable to load projects');
 if(!d.projects.length){list.innerHTML='<div class="empty">No hosted projects yet.</div>';return}
 list.innerHTML=d.projects.map(p=>`<div class="item"><div><b>${esc(p.name)}</b><div class="url">${esc(p.url)} · ${bytes(p.size)}</div></div><div class="item-actions"><button class="secondary" onclick="openProject('${encodeURIComponent(p.name)}')">Open</button><button class="danger" onclick="delProject('${encodeURIComponent(p.name)}')">Delete</button></div></div>`).join('');
 }catch(e){list.innerHTML='<div class="empty">'+esc(e.message)+'</div>'}
}
$('upload').onclick=async()=>{
 const p=$('project').value.trim(),k=key.value.trim(),z=$('zip').files[0];
 if(!/^[A-Za-z0-9_-]{1,40}$/.test(p))return say('Invalid project name.',false);
 if(!k)return say('Enter Hosting API Key.',false);
 if(!z||!z.name.toLowerCase().endsWith('.zip'))return say('Select a ZIP file.',false);
 if(z.size>50*1024*1024)return say('Maximum ZIP size is 50MB.',false);
 const b=$('upload');b.disabled=true;b.textContent='Uploading...';
 try{let f=new FormData();f.append('project',p);f.append('project_file',z);let r=await fetch('api.php?action=upload',{method:'POST',headers:{'X-API-Key':k},body:f}),d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Upload failed');localStorage.setItem('php_host_api_key',k);say('Hosted: '+d.full_url,true);$('project').value='';$('zip').value='';load()}catch(e){say(e.message,false)}finally{b.disabled=false;b.textContent='Upload & Host'}
}
function openProject(n){window.open('sites/'+n+'/','_blank')}
async function delProject(n){const k=key.value.trim();if(!k)return say('Enter API key.',false);if(!confirm('Delete this project?'))return;try{let r=await fetch('api.php?action=delete&project='+n,{method:'DELETE',headers:{'X-API-Key':k}}),d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Delete failed');say('Project deleted.');load()}catch(e){say(e.message,false)}}
$('refresh').onclick=load;load();
</script>
</body>
</html>
