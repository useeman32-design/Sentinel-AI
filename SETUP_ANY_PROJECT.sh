#!/bin/bash
# MASTER FILE - Works for ANY project name
# Usage in fresh Arena chat:
#   bash SETUP_ANY_PROJECT.sh https://github.com/username/ANY-REPO.git YOUR_TOKEN [VSCODE_PATH]

REPO_URL=${1:-""}
TOKEN=${2:-""}
VSCODE_PATH=${3:-"C:\\xampp\\htdocs\\MyProject"}

if [ -z "$REPO_URL" ] || [ -z "$TOKEN" ]; then
  echo "=== Arena Generic Project Setup ==="
  echo "Usage: bash SETUP_ANY_PROJECT.sh <repo_url> <github_token> [vscode_path]"
  echo ""
  echo "Examples:"
  echo "  bash SETUP_ANY_PROJECT.sh https://github.com/user/Sentinel-AI.git ghp_xxxx"
  echo "  bash SETUP_ANY_PROJECT.sh https://github.com/user/MyNewApp.git ghp_xxxx 'C:\\xampp\\htdocs\\MyNewApp'"
  echo ""
  echo "Or edit MASTER_SYNC_SETUP.json and run:"
  echo "  bash SETUP_ANY_PROJECT.sh \$(cat MASTER_SYNC_SETUP.json | grep repo_url | cut -d'\"' -f4) \$(cat MASTER_SYNC_SETUP.json | grep token_placeholder | cut -d'\"' -f4)"
  exit 1
fi

REPO_SHORT=$(echo $REPO_URL | sed 's|https://github.com/||' | sed 's|\.git||')
PROJECT_NAME=$(echo $REPO_SHORT | cut -d'/' -f2)

echo "=== Setting up ANY project: $PROJECT_NAME ==="
echo "Repo: $REPO_SHORT"
echo "VS Code Path: $VSCODE_PATH"

cd /home/user
rm -rf project
git clone https://${TOKEN}@github.com/${REPO_SHORT}.git project
cd project
git config user.email "arena-agent@arena.ai"
git config user.name "Arena Agent"
git remote set-url origin https://${TOKEN}@github.com/${REPO_SHORT}.git

# Update MASTER_SYNC_SETUP.json for future
cat > MASTER_SYNC_SETUP.json << JSON
{
  "project_name": "$PROJECT_NAME",
  "repo_url": "$REPO_URL",
  "repo_short": "$REPO_SHORT",
  "vscode_path": "$VSCODE_PATH",
  "token_placeholder": "CONFIGURED",
  "last_setup": "$(date -u)"
}
JSON

mkdir -p /home/user/.arena-secrets
cat > /home/user/.arena-secrets/dashboard_config.json << JSON
{
  "repo_url": "$REPO_URL",
  "repo_short": "$REPO_SHORT",
  "token": "$TOKEN",
  "vscode_path": "$VSCODE_PATH",
  "project_name": "$PROJECT_NAME",
  "connected_at": "$(date -u)"
}
JSON
echo "$TOKEN" > /home/user/.arena-secrets/token.txt

# Create daemon
cat > /home/user/.arena-secrets/sync-daemon.sh << DAEMON
#!/bin/bash
cd /home/user/project
TOKEN=\$(cat /home/user/.arena-secrets/token.txt)
REPO=\$(cat /home/user/.arena-secrets/dashboard_config.json | grep repo_short | cut -d'"' -f4)
git remote set-url origin https://\${TOKEN}@github.com/\${REPO}.git 2>/dev/null
git config user.email "arena-agent@arena.ai" 2>/dev/null
git config user.name "Arena Agent" 2>/dev/null
echo "[\$(date)] Sync ACTIVE for \${REPO}"
while true; do
  git pull --rebase origin main 2>&1 | tail -n 2
  if [ -n "\$(git status --porcelain)" ]; then
    echo "[\$(date +%H:%M:%S)] Pushing..."
    git add -A
    git commit -m "auto-sync: \$(date -u +%Y-%m-%dT%H:%M:%SZ)" 2>&1 | tail -n 1
    git push origin main 2>&1 | tail -n 2
  fi
  sleep 3
done
DAEMON
chmod +x /home/user/.arena-secrets/sync-daemon.sh
pkill -f sync-daemon 2>/dev/null; true
nohup bash /home/user/.arena-secrets/sync-daemon.sh > /tmp/sync.log 2>&1 &
echo $! > /tmp/sync.pid

# Try start dashboard if exists
if [ -d "/home/user/sync-dashboard-php" ]; then
  cd /home/user/sync-dashboard-php
  node server.js > /tmp/dashboard.log 2>&1 &
  echo "Dashboard started on 5050"
fi

echo ""
echo "✅ Setup complete for ANY project: $PROJECT_NAME"
echo "📁 Location: /home/user/project"
echo "🔗 Repo: https://github.com/$REPO_SHORT"
echo "💻 VS Code Path: $VSCODE_PATH"
echo "📊 Dashboard: Port 5050 (Live Preview)"
echo "📝 Files: $(ls /home/user/project | wc -l) files"
echo ""
echo "In your local VS Code, run:"
echo "  git clone $REPO_URL"
echo "Then install GitDoc extension for auto-pull"
