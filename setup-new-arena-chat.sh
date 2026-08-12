#!/bin/bash
# Quick setup for fresh Arena chat - reconnects Sentinel-AI sync
# Usage: In NEW Arena chat, paste: bash setup-new-arena-chat.sh YOUR_GITHUB_TOKEN

TOKEN=${1:-""}
REPO="useeman32-design/Sentinel-AI"

if [ -z "$TOKEN" ]; then
  echo "Usage: bash setup-new-arena-chat.sh YOUR_GITHUB_TOKEN"
  echo "Get token from https://github.com/settings/tokens/new (check repo scope)"
  exit 1
fi

echo "=== Setting up Sentinel-AI in fresh Arena chat ==="
cd /home/user
rm -rf project
git clone https://${TOKEN}@github.com/${REPO}.git project
cd project
git config user.email "arena-agent@arena.ai"
git config user.name "Arena Agent"
git remote set-url origin https://${TOKEN}@github.com/${REPO}.git

# Save config for dashboard
mkdir -p /home/user/.arena-secrets
cat > /home/user/.arena-secrets/dashboard_config.json << JSON
{
  "repo_url": "https://github.com/${REPO}.git",
  "repo_short": "${REPO}",
  "token": "${TOKEN}",
  "vscode_path": "C:\\\\xampp\\\\htdocs\\\\Sentinel-AI\\\\Sentinel-AI",
  "connected_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
}
JSON
echo "$TOKEN" > /home/user/.arena-secrets/token.txt

# Start auto-sync daemon
cat > /home/user/.arena-secrets/sync-daemon.sh << DAEMON
#!/bin/bash
cd /home/user/project
TOKEN=\$(cat /home/user/.arena-secrets/token.txt)
git remote set-url origin https://\${TOKEN}@github.com/${REPO}.git 2>/dev/null
while true; do
  git pull --rebase origin main 2>&1 | tail -n 2
  if [ -n "\$(git status --porcelain)" ]; then
    git add -A
    git commit -m "auto-sync: \$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    git push origin main 2>&1 | tail -n 2
  fi
  sleep 3
done
DAEMON
chmod +x /home/user/.arena-secrets/sync-daemon.sh
nohup bash /home/user/.arena-secrets/sync-daemon.sh > /tmp/sync.log 2>&1 &
echo $! > /tmp/sync.pid

echo "✅ Setup complete!"
echo "Project: $REPO"
echo "Location: /home/user/project"
echo "Files: $(ls | wc -l) files restored from GitHub"
echo "Daemon PID: $(cat /tmp/sync.pid)"
echo "Run: cd /home/user/project && ls"
