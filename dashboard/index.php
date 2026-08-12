<?php
// Sentinel-AI Sync Dashboard - PHP + JS Version
// Works on XAMPP: C:\xampp\htdocs\arena-sync\

$configFile = __DIR__ . '/config.json';
$logFile = __DIR__ . '/sync.log';
$projectDir = isset($_GET['project']) ? $_GET['project'] : '/home/user/project';

// Load config if exists
$config = [];
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true) ?? [];
}
$currentRepo = $config['repo_url'] ?? 'https://github.com/useeman32-design/Sentinel-AI.git';
$currentPath = $config['vscode_path'] ?? 'C:\\xampp\\htdocs\\Sentinel-AI';
$projects = $config['projects'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Arena Sync Dashboard - PHP + JS</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif}
body{background:#0a0a0f;color:#e4e4e7;min-height:100vh;padding:16px}
.container{max-width:1300px;margin:0 auto}
.header{background:#18181b;border:1px solid #27272a;border-radius:12px;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px}
.header h1{font-size:20px;font-weight:700;display:flex;align-items:center;gap:10px}
.badge{padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700}
.badge-live{background:#14532d;color:#86efac;border:1px solid #166534;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.6}}
.grid{display:grid;grid-template-columns:1.1fr 0.9fr;gap:16px;margin-bottom:16px}
@media(max-width:1000px){.grid{grid-template-columns:1fr}}
.card{background:#18181b;border:1px solid #27272a;border-radius:12px;padding:18px}
.card h2{font-size:14px;font-weight:700;margin-bottom:14px;color:#fafafa;display:flex;align-items:center;gap:8px;letter-spacing:.5px;text-transform:uppercase}
label{font-size:11px;color:#a1a1aa;display:block;margin-bottom:5px;font-weight:600;letter-spacing:.3px;text-transform:uppercase}
input,select{width:100%;background:#09090b;border:1px solid #27272a;color:#fafafa;padding:10px 12px;border-radius:8px;margin-bottom:12px;font-size:13px}
input:focus,select:focus{outline:none;border-color:#6366f1;background:#0f0f12}
.btn{padding:10px 14px;border-radius:8px;border:0;font-weight:600;cursor:pointer;font-size:13px;transition:.15s;display:inline-flex;align-items:center;gap:6px;justify-content:center}
.btn-primary{background:#6366f1;color:white;width:100%}
.btn-primary:hover{background:#4f46e5;transform:translateY(-1px)}
.btn-secondary{background:#27272a;color:#e4e4e7;border:1px solid #3f3f46}
.btn-secondary:hover{background:#3f3f46}
.btn-sm{padding:6px 10px;font-size:12px}
.status{display:flex;align-items:center;gap:10px;padding:10px 12px;background:#09090b;border:1px solid #27272a;border-radius:8px;margin-bottom:8px;font-size:12px}
.dot{width:8px;height:8px;border-radius:50%}
.dot.green{background:#22c55e;box-shadow:0 0 6px #22c55e}
.dot.red{background:#ef4444}
.dot.yellow{background:#eab308}
.files{max-height:280px;overflow:auto}
.file{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #1f1f23;font-size:12px}
.file:last-child{border:0}
.log{background:#050507;border:1px solid #27272a;border-radius:8px;padding:12px;font-family:Consolas,monospace;font-size:11px;max-height:220px;overflow:auto;white-space:pre-wrap;color:#71717a;line-height:1.5}
.path-box{background:#09090b;border:1px dashed #3f3f46;padding:10px;border-radius:8px;font-family:monospace;font-size:12px;color:#a1a1aa;margin-bottom:12px;word-break:break-all}
.instructions{font-size:12px;line-height:1.6;color:#a1a1aa}
.instructions ol{padding-left:16px;margin-top:8px}
.instructions li{margin-bottom:5px}
code{background:#27272a;padding:2px 6px;border-radius:4px;color:#e4e4e7;font-size:11px}
.project-switch{display:flex;gap:8px;margin-bottom:12px}
.project-chip{background:#27272a;border:1px solid #3f3f46;padding:6px 10px;border-radius:20px;font-size:11px;cursor:pointer;transition:.2s}
.project-chip.active{background:#6366f1;border-color:#6366f1;color:white}
.project-chip:hover{border-color:#6366f1}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>⚡ Arena Sync Dashboard <span style="font-weight:400;color:#71717a;font-size:13px">PHP + JS</span></h1>
    <div style="display:flex;gap:10px;align-items:center">
      <span class="badge badge-live">● LIVE AUTO-SYNC</span>
      <span id="daemonBadge" class="badge" style="background:#27272a;color:#a1a1aa">Checking...</span>
    </div>
  </div>

  <div class="grid">
    <!-- LEFT: Connection + Project Switch -->
    <div class="card">
      <h2>🔗 Connection & Project Switcher</h2>
      
      <label>📂 Saved Projects (Click to Switch)</label>
      <div class="project-switch" id="projectChips">
        <div class="project-chip active" onclick="switchProject('https://github.com/useeman32-design/Sentinel-AI.git')">Sentinel-AI</div>
        <div class="project-chip" onclick="showNewProject()">+ New Project</div>
      </div>

      <div id="newProjectForm">
        <label>GitHub Repo URL (for current project)</label>
        <input id="repoUrl" type="text" value="<?php echo htmlspecialchars($currentRepo); ?>" placeholder="https://github.com/username/repo.git">
        
        <label>GitHub Token (ghp_... - needed to push from Arena)</label>
        <input id="token" type="password" placeholder="ghp_xxxx or github_pat_xxxx" value="">

        <label>💻 Your VS Code Folder Path (Where you cloned)</label>
        <input id="vscodePath" type="text" value="<?php echo htmlspecialchars($currentPath); ?>" placeholder="C:\xampp\htdocs\Sentinel-AI">
        <div class="path-box" id="pathHelp">
          <strong>How to find your VS Code folder path:</strong><br>
          1. In VS Code, look at top title bar → it shows full path like <code>C:\Users\...\Sentinel-AI</code><br>
          2. OR: Right-click any file in Explorer → Copy Path → paste here and remove file name<br>
          3. OR: In VS Code terminal, run <code>pwd</code> (Mac/Linux) or <code>cd</code> (Windows)<br>
          4. Your path is where you ran <code>git clone</code> — e.g. <code>C:\xampp\htdocs\Sentinel-AI</code>
        </div>

        <div style="display:flex;gap:8px;margin-top:10px">
          <button class="btn btn-primary" style="flex:1" onclick="connect()">🔌 Connect & Start Auto-Sync</button>
          <button class="btn btn-secondary" onclick="disconnect()">⏹ Stop</button>
        </div>
        <div id="connectStatus" style="margin-top:10px;font-size:12px"></div>

        <div style="margin-top:14px;display:flex;gap:8px">
          <button class="btn btn-secondary btn-sm" style="flex:1" onclick="pushNow()">⬆ Push Now (Arena → GitHub)</button>
          <button class="btn btn-secondary btn-sm" style="flex:1" onclick="pullNow()">⬇ Pull Now (GitHub → Arena)</button>
        </div>
      </div>
    </div>

    <!-- RIGHT: Status -->
    <div class="card">
      <h2>📡 Live Sync Status</h2>
      <div class="status"><div class="dot green" id="arenaDot"></div><div><strong>Arena → GitHub:</strong> <span id="arenaStatus">Checking...</span></div><span class="badge" id="arenaBadge" style="margin-left:auto;background:#27272a">-</span></div>
      <div class="status"><div class="dot green"></div><div><strong>GitHub → VS Code:</strong> GitDoc Auto-Pull</div><span class="badge" style="margin-left:auto;background:#14532d;color:#86efac">READY</span></div>
      <div class="status"><div class="dot yellow"></div><div><strong>Last Push:</strong> <span id="lastPush">-</span></div></div>
      <div class="status"><div class="dot" id="remoteDot"></div><div><strong>Remote:</strong> <span id="remoteUrl" style="font-size:10px">-</span></div></div>
      <div class="status"><div class="dot green"></div><div><strong>Your VS Code Path:</strong> <span id="currentVscodePath" style="font-family:monospace;font-size:11px"><?php echo htmlspecialchars($currentPath); ?></span></div></div>

      <div style="margin-top:14px;background:#09090b;border-radius:8px;padding:12px">
        <div style="font-size:11px;font-weight:700;color:#a1a1aa;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px">Flow</div>
        <div style="font-size:11px;line-height:1.6;color:#71717a">
          Arena (<code>/home/user/project</code>) <br>
          &nbsp;&nbsp;⬇ auto-push every 3s<br>
          GitHub (<code id="flowRepo">Sentinel-AI</code>) <br>
          &nbsp;&nbsp;⬇ GitDoc auto-pull every 3s<br>
          VS Code (<span id="flowPath" style="font-size:10px">C:\xampp\htdocs\Sentinel-AI</span>)
        </div>
      </div>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <h2>📁 Project Files (<span id="fileCount">0</span>) — Synced from Arena</h2>
      <div class="files" id="filesList">Loading...</div>
    </div>
    <div class="card">
      <h2>📜 Live Log</h2>
      <div class="log" id="logBox">Loading...</div>
    </div>
  </div>

  <div class="card">
    <h2>🔄 How to Change to Another Project</h2>
    <div class="instructions" style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
      <div>
        <strong>To switch project:</strong>
        <ol>
          <li>Create new empty repo at <code>github.com/new</code> — e.g. <code>My-New-Project</code></li>
          <li>In this dashboard, paste new Repo URL in top field: <code>https://github.com/username/My-New-Project.git</code></li>
          <li>Update VS Code Path to new folder: <code>C:\xampp\htdocs\My-New-Project</code></li>
          <li>Click <strong>Connect & Start Auto-Sync</strong></li>
          <li>Dashboard will now push new project files to new repo</li>
          <li>In your VS Code, <code>git clone</code> the new repo URL to new folder</li>
        </ol>
      </div>
      <div>
        <strong>How to know VS Code folder path:</strong>
        <ol>
          <li><strong>Title Bar:</strong> Top of VS Code window shows full path</li>
          <li><strong>Copy Path:</strong> Right-click file in Explorer → Copy Path → remove file name</li>
          <li><strong>Terminal:</strong> In VS Code terminal, type <code>cd</code> (Windows) or <code>pwd</code> (Mac) → shows current folder path</li>
          <li><strong>Your current path is:</strong><br><code id="showVscodePathHelp"><?php echo htmlspecialchars($currentPath); ?></code><br>This is where you ran <code>git clone</code></li>
          <li>For XAMPP, it's usually <code>C:\xampp\htdocs\YourProject</code></li>
        </ol>
      </div>
    </div>
  </div>
</div>

<script>
// JS Logic - Works with both PHP api.php and Node.js server.js
const API_BASE = window.location.hostname.includes('arena') || window.location.port == '5050' ? '' : ''; // same origin

async function apiCall(action, data=null){
  const url = API_BASE + (action.includes('.php') ? action : '/api/' + action);
  // Try PHP api.php first, fallback to Node /api/
  let endpoint = action.startsWith('api/') ? action : 'api.php?action=' + action;
  if(window.location.port == '5050') endpoint = '/api/' + action; // Node server
  
  try{
    const res = await fetch(endpoint, {
      method: data ? 'POST' : 'GET',
      headers: data ? {'Content-Type':'application/json'} : {},
      body: data ? JSON.stringify(data) : null
    });
    return await res.json();
  }catch(e){
    // fallback to PHP
    try{
      const res2 = await fetch('api.php?action=' + action, {
        method: data ? 'POST' : 'GET',
        headers: data ? {'Content-Type':'application/json'} : {},
        body: data ? JSON.stringify(data) : null
      });
      return await res2.json();
    }catch(e2){ return {success:false, message:e2.message} }
  }
}

async function fetchStatus(){
  try{
    let data;
    if(window.location.port == '5050'){
      const res = await fetch('/api/status');
      data = await res.json();
    } else {
      const res = await fetch('api.php?action=status');
      data = await res.json();
    }
    
    document.getElementById('remoteUrl').textContent = data.remote_masked || data.remote || 'Not set';
    document.getElementById('lastPush').textContent = data.last_push || '-';
    document.getElementById('fileCount').textContent = (data.files||[]).length;
    document.getElementById('arenaStatus').textContent = data.daemon_running ? 'Auto-pushing every 3s' : 'Stopped';
    document.getElementById('arenaDot').className = 'dot ' + (data.daemon_running ? 'green' : 'red');
    document.getElementById('daemonBadge').textContent = data.daemon_running ? '● LIVE' : '● STOPPED';
    document.getElementById('daemonBadge').style.background = data.daemon_running ? '#14532d' : '#7f1d1d';
    document.getElementById('daemonBadge').style.color = data.daemon_running ? '#86efac' : '#fca5a5';
    document.getElementById('remoteDot').className = 'dot ' + (data.remote ? 'green' : 'red');
    
    let filesHtml = '';
    (data.files||[]).forEach(f=>{
      filesHtml += `<div class="file"><span>📄 ${f.name}</span><span style="color:#52525b;font-size:10px">${f.size}b • ${f.modified}</span></div>`;
    });
    document.getElementById('filesList').innerHTML = filesHtml || 'No files yet';
    document.getElementById('logBox').textContent = data.log || 'No log';
    
    if(data.vscode_path){
      document.getElementById('currentVscodePath').textContent = data.vscode_path;
      document.getElementById('flowPath').textContent = data.vscode_path;
      document.getElementById('showVscodePathHelp').textContent = data.vscode_path;
    }
    if(data.remote){
      let repoName = data.remote.split('/').pop().replace('.git','');
      document.getElementById('flowRepo').textContent = repoName;
    }
  }catch(e){ console.log('status error', e) }
}

async function connect(){
  const repoUrl = document.getElementById('repoUrl').value.trim();
  const token = document.getElementById('token').value.trim();
  const vscodePath = document.getElementById('vscodePath').value.trim();
  if(!repoUrl || !token){ alert('Enter Repo URL and Token'); return; }
  document.getElementById('connectStatus').innerHTML = '<span style="color:#eab308">⏳ Connecting and starting auto-sync...</span>';
  
  let endpoint = window.location.port == '5050' ? '/api/connect' : 'api.php?action=connect';
  const res = await fetch(endpoint, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({repo_url:repoUrl, token, vscode_path:vscodePath})});
  const data = await res.json();
  document.getElementById('connectStatus').innerHTML = `<span style="color:${data.success?'#22c55e':'#ef4444'}">${data.message}</span>`;
  if(data.success){
    document.getElementById('currentVscodePath').textContent = vscodePath;
    // save to localStorage for project switcher
    let projects = JSON.parse(localStorage.getItem('arena_projects')||'[]');
    if(!projects.find(p=>p.url===repoUrl)){
      projects.push({url:repoUrl, path:vscodePath, name:repoUrl.split('/').pop().replace('.git','')});
      localStorage.setItem('arena_projects', JSON.stringify(projects));
      renderProjectChips();
    }
  }
  fetchStatus();
}

async function disconnect(){
  let endpoint = window.location.port == '5050' ? '/api/disconnect' : 'api.php?action=disconnect';
  const res = await fetch(endpoint, {method:'POST'});
  const data = await res.json();
  document.getElementById('connectStatus').innerHTML = `<span style="color:#a1a1aa">${data.message}</span>`;
  fetchStatus();
}

async function pushNow(){
  let endpoint = window.location.port == '5050' ? '/api/push' : 'api.php?action=push';
  const res = await fetch(endpoint, {method:'POST'});
  const data = await res.json();
  alert(data.message.slice(0,300));
  fetchStatus();
}

async function pullNow(){
  let endpoint = window.location.port == '5050' ? '/api/pull' : 'api.php?action=pull';
  const res = await fetch(endpoint, {method:'POST'});
  const data = await res.json();
  alert(data.message.slice(0,300));
  fetchStatus();
}

function switchProject(url){
  document.getElementById('repoUrl').value = url;
  document.querySelectorAll('.project-chip').forEach(c=>c.classList.remove('active'));
  event.target.classList.add('active');
  // update flow
  document.getElementById('flowRepo').textContent = url.split('/').pop().replace('.git','');
}

function showNewProject(){
  document.getElementById('repoUrl').value = '';
  document.getElementById('repoUrl').placeholder = 'https://github.com/username/new-project.git';
  document.getElementById('repoUrl').focus();
  document.querySelectorAll('.project-chip').forEach(c=>c.classList.remove('active'));
}

function renderProjectChips(){
  const projects = JSON.parse(localStorage.getItem('arena_projects')||'[]');
  if(projects.length===0) return;
  let html = projects.map(p=>`<div class="project-chip" onclick="switchProject('${p.url}');document.getElementById('vscodePath').value='${p.path}'">${p.name}</div>`).join('') + '<div class="project-chip" onclick="showNewProject()">+ New</div>';
  document.getElementById('projectChips').innerHTML = html;
}

renderProjectChips();
fetchStatus();
setInterval(fetchStatus, 3000);

// Update VS Code path live
document.getElementById('vscodePath').addEventListener('input', (e)=>{
  document.getElementById('currentVscodePath').textContent = e.target.value;
  document.getElementById('flowPath').textContent = e.target.value;
  document.getElementById('showVscodePathHelp').textContent = e.target.value;
});
</script>
</body>
</html>
