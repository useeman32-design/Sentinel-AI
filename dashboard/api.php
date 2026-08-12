<?php
// API for PHP + JS Sync Dashboard
// Works on XAMPP

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit; }

$configFile = __DIR__ . '/config.json';
$logFile = __DIR__ . '/sync.log';
$pidFile = __DIR__ . '/sync.pid';
$projectDir = '/home/user/project'; // In Arena
if (strpos(__DIR__, 'xampp') !== false || strpos(__DIR__, 'htdocs') !== false) {
    // If on XAMPP Windows, project dir is this folder itself or subfolder
    $projectDir = __DIR__;
}

function loadConfig() {
    global $configFile;
    if (file_exists($configFile)) {
        return json_decode(file_get_contents($configFile), true) ?? [];
    }
    return [];
}

function saveConfig($cfg) {
    global $configFile;
    file_put_contents($configFile, json_encode($cfg, JSON_PRETTY_PRINT));
}

function runCmd($cmd, $cwd = null) {
    global $projectDir;
    $cwd = $cwd ?? $projectDir;
    $out = [];
    $code = 0;
    // Use 2>&1 to capture all
    exec("cd " . escapeshellarg($cwd) . " && $cmd 2>&1", $out, $code);
    return [$code, implode("\n", $out)];
}

function getRemote() {
    list($code, $out) = runCmd("git remote get-url origin 2>&1");
    $raw = trim($out);
    $masked = $raw;
    if (strpos($raw, '@') !== false && strpos($raw, 'github.com') !== false) {
        $parts = explode('@', $raw);
        $masked = 'https://***TOKEN***@' . end($parts);
    }
    return [$masked, $raw];
}

function isDaemonRunning() {
    global $pidFile;
    if (file_exists($pidFile)) {
        $pid = intval(file_get_contents($pidFile));
        if ($pid > 0) {
            // On Linux check, on Windows just assume file exists means running
            if (function_exists('posix_kill')) {
                return posix_kill($pid, 0);
            }
            return true;
        }
    }
    // check via log recent
    return false;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'status';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($action === 'status') {
    list($remoteMasked, $remoteRaw) = getRemote();
    $files = [];
    $projDir = $projectDir;
    if (is_dir($projDir)) {
        foreach (glob($projDir . '/*') as $file) {
            if (is_file($file)) {
                $name = basename($file);
                if ($name[0] === '.') continue;
                if (in_array($name, ['config.json', 'sync.log', 'sync.pid', 'api.php'])) continue;
                $files[] = [
                    'name' => $name,
                    'size' => filesize($file),
                    'modified' => date('H:i:s d/m', filemtime($file))
                ];
            }
        }
    }
    usort($files, fn($a,$b)=> strcmp($b['modified'], $a['modified']));

    list($code, $lastPush) = runCmd("git log -1 --format='%cd %s' --date=format:'%H:%M:%S' 2>&1");
    $log = file_exists($logFile) ? implode("", array_slice(file($logFile), -40)) : "No log yet - daemon not started";

    $cfg = loadConfig();
    echo json_encode([
        'remote' => $remoteRaw,
        'remote_masked' => $remoteMasked,
        'daemon_running' => isDaemonRunning(),
        'last_push' => trim($lastPush) ?: 'No commits',
        'files' => $files,
        'log' => $log,
        'vscode_path' => $cfg['vscode_path'] ?? 'C:\\xampp\\htdocs\\Sentinel-AI',
        'repo_url' => $cfg['repo_url'] ?? $remoteRaw
    ]);
    exit;
}

if ($action === 'connect') {
    $repoUrl = $input['repo_url'] ?? $_POST['repo_url'] ?? '';
    $token = $input['token'] ?? $_POST['token'] ?? '';
    $vscodePath = $input['vscode_path'] ?? $_POST['vscode_path'] ?? 'C:\\xampp\\htdocs\\Sentinel-AI';

    if (!$repoUrl || !$token) {
        echo json_encode(['success'=>false, 'message'=>'Repo URL and token required']);
        exit;
    }

    $repoShort = str_replace(['https://github.com/', '.git'], '', $repoUrl);
    
    $cfg = ['repo_url'=>$repoUrl, 'repo_short'=>$repoShort, 'token'=>$token, 'vscode_path'=>$vscodePath, 'connected_at'=>date('c')];
    saveConfig($cfg);
    file_put_contents(__DIR__ . '/token.txt', $token);

    // Set remote and create daemon script
    $projectDirEsc = escapeshellarg($projectDir);
    $tokenEsc = escapeshellarg($token);
    
    // For XAMPP Windows, use simple batch, for Linux use bash
    $daemonScript = __DIR__ . '/sync-daemon.sh';
    $scriptContent = "#!/bin/bash\ncd $projectDirEsc\nTOKEN=\$(cat " . escapeshellarg(__DIR__ . '/token.txt') . " 2>/dev/null)\n".
                     "git remote set-url origin https://\${TOKEN}@github.com/{$repoShort}.git 2>/dev/null || git remote add origin https://\${TOKEN}@github.com/{$repoShort}.git\n".
                     "git config user.email 'arena-agent@arena.ai'\n".
                     "git config user.name 'Arena Agent'\n".
                     "echo \"[$(date)] Sync started for $repoShort\" > " . escapeshellarg($logFile) . "\n".
                     "while true; do git pull --rebase origin main 2>&1 | tail -n 2 >> " . escapeshellarg($logFile) . "; if [ -n \"\$(git status --porcelain)\" ]; then echo \"[\$(date +%H:%M:%S)] Pushing...\" >> " . escapeshellarg($logFile) . "; git add -A; git commit -m \"auto-sync: \$(date -u +%Y-%m-%dT%H:%M:%SZ)\" >> " . escapeshellarg($logFile) . " 2>&1; git push origin main >> " . escapeshellarg($logFile) . " 2>&1; fi; sleep 3; done\n";
    
    file_put_contents($daemonScript, $scriptContent);
    chmod($daemonScript, 0755);

    // Start daemon in background (Linux)
    exec("nohup bash $daemonScript > $logFile 2>&1 & echo $! > $pidFile &");

    echo json_encode(['success'=>true, 'message'=>"Connected to $repoShort! Auto-sync started. Git remote set. Your VS Code path: $vscodePath"]);
    exit;
}

if ($action === 'disconnect') {
    exec("pkill -f sync-daemon; rm -f $pidFile; echo 'Stopped at ' . date('H:i:s') > $logFile");
    echo json_encode(['success'=>true, 'message'=>'Sync stopped']);
    exit;
}

if ($action === 'push') {
    $cfg = loadConfig();
    $token = $cfg['token'] ?? '';
    if ($token) {
        $repoShort = $cfg['repo_short'] ?? 'useeman32-design/Sentinel-AI';
        runCmd("git remote set-url origin https://{$token}@github.com/{$repoShort}.git 2>&1");
    }
    list($code, $out) = runCmd("git add -A; git commit -m 'manual push from dashboard' 2>&1; git push origin main 2>&1 | tail -n 10");
    echo json_encode(['success'=>true, 'message'=>$out]);
    exit;
}

if ($action === 'pull') {
    list($code, $out) = runCmd("git pull --rebase origin main 2>&1 | tail -n 10");
    echo json_encode(['success'=>true, 'message'=>$out]);
    exit;
}

echo json_encode(['success'=>false, 'message'=>'Unknown action']);
