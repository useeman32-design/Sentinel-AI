#!/usr/bin/env node
// Node.js version of PHP Sync Dashboard - for Arena preview (since PHP not installed)
// Serves same UI as index.php but with Node backend
// Also provides downloadable PHP files for XAMPP

const http = require('http');
const fs = require('fs');
const path = require('path');
const { exec } = require('child_process');
const url = require('url');

const PORT = 5050;
const PROJECT_DIR = '/home/user/project';
const SECRETS_DIR = '/home/user/.arena-secrets';
const CONFIG_FILE = path.join(SECRETS_DIR, 'dashboard_config.json');
const PID_FILE = '/tmp/sync.pid';
const LOG_FILE = '/tmp/sync.log';
const DASHBOARD_DIR = __dirname;

function loadConfig(){
  try{
    if(fs.existsSync(CONFIG_FILE)){
      return JSON.parse(fs.readFileSync(CONFIG_FILE,'utf8'));
    }
  }catch(e){}
  return {};
}

function saveConfig(cfg){
  fs.mkdirSync(SECRETS_DIR, {recursive:true});
  fs.writeFileSync(CONFIG_FILE, JSON.stringify(cfg,null,2));
}

function runCmd(cmd, cwd=PROJECT_DIR){
  return new Promise((resolve)=>{
    exec(cmd, {cwd, timeout:10000}, (err, stdout, stderr)=>{
      resolve({code: err ? err.code || 1 : 0, out: stdout, err: stderr, combined: stdout + stderr});
    });
  });
}

async function getRemote(){
  const {combined} = await runCmd('git remote get-url origin 2>&1 || echo ""');
  const raw = combined.trim().split('\n')[0] || '';
  let masked = raw;
  if(raw.includes('@') && raw.includes('github.com')){
    const parts = raw.split('@');
    masked = 'https://***TOKEN***@' + parts[parts.length-1];
  }
  return {masked, raw};
}

async function isDaemonRunning(){
  if(fs.existsSync(PID_FILE)){
    try{
      const pid = parseInt(fs.readFileSync(PID_FILE,'utf8').trim());
      process.kill(pid,0);
      return true;
    }catch(e){
      return false;
    }
  }
  return false;
}

async function getFiles(){
  try{
    const files = fs.readdirSync(PROJECT_DIR).filter(f=>{
      const full = path.join(PROJECT_DIR,f);
      if(fs.statSync(full).isDirectory()) return false;
      if(f.startsWith('.')) return false;
      if(['config.json','sync.log','sync.pid','api.php'].includes(f)) return false;
      return true;
    }).map(f=>{
      const stat = fs.statSync(path.join(PROJECT_DIR,f));
      return {
        name: f,
        size: stat.size,
        modified: new Date(stat.mtime).toLocaleTimeString() + ' ' + new Date(stat.mtime).toLocaleDateString()
      };
    });
    return files;
  }catch(e){return [];}
}

async function getLastPush(){
  const {combined} = await runCmd("git log -1 --format='%cd %s' --date=format:'%H:%M:%S' 2>&1");
  return combined.trim() || 'No commits';
}

function getLogTail(lines=40){
  if(!fs.existsSync(LOG_FILE)) return 'Daemon not started';
  try{
    const content = fs.readFileSync(LOG_FILE,'utf8');
    return content.split('\n').slice(-lines).join('\n');
  }catch(e){return 'No log';}
}

const MIME = {
  '.html':'text/html','.php':'text/html','.js':'application/javascript','.css':'text/css',
  '.json':'application/json','.md':'text/markdown','.txt':'text/plain'
};

const server = http.createServer(async (req,res)=>{
  const parsed = url.parse(req.url, true);
  const pathname = parsed.pathname;
  const query = parsed.query;

  // CORS
  res.setHeader('Access-Control-Allow-Origin','*');
  res.setHeader('Access-Control-Allow-Methods','GET,POST,OPTIONS');
  res.setHeader('Access-Control-Allow-Headers','Content-Type');

  if(req.method==='OPTIONS'){ res.writeHead(200); res.end(); return; }

  // API routes for Node version (same as PHP api.php)
  if(pathname.startsWith('/api/')){
    const action = pathname.replace('/api/','');
    let body = '';
    if(req.method==='POST'){
      body = await new Promise(r=>{
        let d=''; req.on('data',c=>d+=c); req.on('end',()=>r(d));
      });
    }
    let input = {};
    try{ input = JSON.parse(body||'{}'); }catch(e){}

    if(action==='status'){
      const remote = await getRemote();
      const running = await isDaemonRunning();
      const files = await getFiles();
      const lastPush = await getLastPush();
      const log = getLogTail();
      const cfg = loadConfig();
      res.writeHead(200,{'Content-Type':'application/json'});
      res.end(JSON.stringify({
        remote: remote.raw,
        remote_masked: remote.masked,
        daemon_running: running,
        last_push: lastPush,
        files,
        log,
        vscode_path: cfg.vscode_path || 'C:\\xampp\\htdocs\\Sentinel-AI',
        repo_url: cfg.repo_url || remote.raw
      }));
      return;
    }

    if(action==='connect'){
      const repoUrl = input.repo_url || '';
      const token = input.token || '';
      const vscodePath = input.vscode_path || 'C:\\xampp\\htdocs\\Sentinel-AI';
      if(!repoUrl || !token){
        res.writeHead(400,{'Content-Type':'application/json'});
        res.end(JSON.stringify({success:false,message:'Repo URL and token required'}));
        return;
      }
      const repoShort = repoUrl.replace('https://github.com/','').replace('.git','').trim();
      const cfg = {repo_url:repoUrl, repo_short:repoShort, token, vscode_path:vscodePath, connected_at:new Date().toISOString()};
      saveConfig(cfg);
      fs.writeFileSync(path.join(SECRETS_DIR,'token.txt'), token);

      // Set remote
      await runCmd(`git remote remove origin 2>/dev/null; git remote add origin https://${token}@github.com/${repoShort}.git 2>&1 || git remote set-url origin https://${token}@github.com/${repoShort}.git`);
      await runCmd(`git config user.email 'arena-agent@arena.ai'; git config user.name 'Arena Agent'`);

      // Create daemon script
      const daemonScript = `#!/bin/bash
cd ${PROJECT_DIR}
TOKEN=$(cat ${SECRETS_DIR}/token.txt 2>/dev/null)
git remote set-url origin https://\${TOKEN}@github.com/${repoShort}.git 2>/dev/null || true
git config user.email 'arena-agent@arena.ai' 2>/dev/null
git config user.name 'Arena Agent' 2>/dev/null
echo "[$(date)] Two-way sync ACTIVE for ${repoShort}" > ${LOG_FILE}
while true; do
  git pull --rebase origin main 2>&1 | tail -n 2 >> ${LOG_FILE} || true
  if [ -n "$(git status --porcelain 2>&1)" ]; then
    echo "[$(date +%H:%M:%S)] Pushing..." >> ${LOG_FILE}
    git add -A
    git commit -m "auto-sync: $(date -u +%Y-%m-%dT%H:%M:%SZ)" >> ${LOG_FILE} 2>&1 || true
    git push origin main >> ${LOG_FILE} 2>&1 || true
  fi
  sleep 3
done
`;
      fs.writeFileSync(path.join(SECRETS_DIR,'sync-daemon.sh'), daemonScript);
      require('child_process').execSync(`chmod +x ${SECRETS_DIR}/sync-daemon.sh`);

      // Kill old and start new
      try{ require('child_process').execSync('pkill -f sync-daemon || true'); }catch(e){}
      const { spawn } = require('child_process');
      const child = spawn('bash', [path.join(SECRETS_DIR,'sync-daemon.sh')], {detached:true, stdio:'ignore'});
      child.unref();
      if(child.pid) fs.writeFileSync(PID_FILE, child.pid.toString());

      res.writeHead(200,{'Content-Type':'application/json'});
      res.end(JSON.stringify({success:true, message:`Connected to ${repoShort}! Auto-sync started. VS Code path: ${vscodePath}`}));
      return;
    }

    if(action==='disconnect'){
      try{ require('child_process').execSync('pkill -f sync-daemon; rm -f /tmp/sync.pid; echo "Stopped" > /tmp/sync.log'); }catch(e){}
      res.writeHead(200,{'Content-Type':'application/json'});
      res.end(JSON.stringify({success:true,message:'Sync stopped'}));
      return;
    }

    if(action==='push'){
      const cfg = loadConfig();
      if(cfg.token){
        await runCmd(`git remote set-url origin https://${cfg.token}@github.com/${cfg.repo_short}.git`);
      }
      const {combined} = await runCmd(`git add -A; git commit -m 'manual push from dashboard' 2>&1 || true; git push origin main 2>&1 | tail -n 10`);
      res.writeHead(200,{'Content-Type':'application/json'});
      res.end(JSON.stringify({success:true,message:combined.slice(-500)}));
      return;
    }

    if(action==='pull'){
      const {combined} = await runCmd(`git pull --rebase origin main 2>&1 | tail -n 10`);
      res.writeHead(200,{'Content-Type':'application/json'});
      res.end(JSON.stringify({success:true,message:combined.slice(-500)}));
      return;
    }
  }

  // Serve static files - index.php as HTML
  let filePath = pathname === '/' ? path.join(DASHBOARD_DIR,'index.php') : path.join(DASHBOARD_DIR, pathname);
  // If requesting index.php, serve it as HTML (strip PHP tags for Node)
  if(!fs.existsSync(filePath)){
    // Try index.html fallback
    filePath = path.join(DASHBOARD_DIR,'index.php');
  }

  try{
    let content = fs.readFileSync(filePath, 'utf8');
    // If file is PHP, strip <?php ... ?> tags for display (keep HTML)
    if(filePath.endsWith('.php')){
      content = content.replace(/<\?php[\s\S]*?\?>/g, '');
      content = content.replace(/<\?=[\s\S]*?\?>/g, '');
      // Inject current config values
      const cfg = loadConfig();
      content = content.replace(/<\?php echo htmlspecialchars\(\$currentRepo\); \?>/g, cfg.repo_url || 'https://github.com/useeman32-design/Sentinel-AI.git');
      content = content.replace(/<\?php echo htmlspecialchars\(\$currentPath\); \?>/g, cfg.vscode_path || 'C:\\xampp\\htdocs\\Sentinel-AI');
    }
    const ext = path.extname(filePath);
    const mime = MIME[ext] || 'text/html';
    res.writeHead(200, {'Content-Type': mime});
    res.end(content);
  }catch(e){
    res.writeHead(404, {'Content-Type':'text/plain'});
    res.end('Not found: ' + pathname);
  }
});

server.listen(PORT, '0.0.0.0', ()=>{
  console.log(`PHP+JS Sync Dashboard (Node version) running at http://0.0.0.0:${PORT}`);
  console.log(`Project dir: ${PROJECT_DIR}`);
});
