#!/bin/bash
# MASTER SETUP - Works for ANY project name - Clean version
# Usage:
#   bash SETUP_ANY_PROJECT.sh <repo_url> <token> [vscode_path]
# Or interactive: bash SETUP_ANY_PROJECT.sh

REPO_URL=${1:-""}
TOKEN=${2:-""}
VSCODE_PATH=${3:-""}

# If no args, try to read from MASTER_SYNC_SETUP.json
if [ -z "$REPO_URL" ] && [ -f "MASTER_SYNC_SETUP.json" ]; then
  REPO_URL=$(grep -o '"repo_url"[[:space:]]*:[[:space:]]*"[^"]*"' MASTER_SYNC_SETUP.json | cut -d'"' -f4)
  if [[ "$REPO_URL" == *"USERNAME/REPO_NAME"* ]]; then
    REPO_URL=""
  fi
fi

# Interactive prompts if still empty
if [ -z "$REPO_URL" ]; then
  echo "=== Arena Master Setup - ANY Project ==="
  read -p "Enter GitHub Repo URL (https://github.com/user/repo.git): " REPO_URL
fi

if [ -z "$TOKEN" ]; then
  read -p "Enter GitHub Token (ghp_...): " TOKEN
  echo ""
fi

if [ -z "$VSCODE_PATH" ]; then
  # Try read from json
  if [ -f "MASTER_SYNC_SETUP.json" ]; then
    VSCODE_PATH=$(grep -o '"vscode_path"[[:space:]]*:[[:space:]]*"[^"]*"' MASTER_SYNC_SETUP.json | cut -d'"' -f4)
  fi
  if [[ "$VSCODE_PATH" == *"MyProject"* ]] || [ -z "$VSCODE_PATH" ]; then
    read -p "Enter VS Code Folder Path (e.g. C:\\xampp\\htdocs\\MyProject): " VSCODE_PATH
  fi
fi

if [ -z "$REPO_URL" ] || [ -z "$TOKEN" ]; then
  echo "Repo URL and Token required!"
  exit 1
fi

REPO_SHORT=$(echo $REPO_URL | sed 's|https://github.com/||' | sed 's|\.git||' | xargs)
PROJECT_NAME=$(echo $REPO_SHORT | cut -d'/' -f2)

echo ""
echo "=== Setting up: $PROJECT_NAME ==="
echo "Repo: $REPO_SHORT"
echo "VS Code Path: $VSCODE_PATH"
echo ""

cd /home/user
rm -rf project
git clone https://${TOKEN}@github.com/${REPO_SHORT}.git project 2>&1 | tail -n 2
cd project

git config user.email "arena-agent@arena.ai"
git config user.name "Arena Agent"
git remote set-url origin https://${TOKEN}@github.com/${REPO_SHORT}.git

# Update MASTER_SYNC_SETUP.json with actual values
cat > MASTER_SYNC_SETUP.json << JSON
{
  "project_name": "$PROJECT_NAME",
  "repo_url": "$REPO_URL",
  "repo_short": "$REPO_SHORT",
  "vscode_path": "$VSCODE_PATH",
  "token_placeholder": "CONFIGURED - Token saved in secrets, not here",
  "last_setup": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "instructions": "In fresh chat: Read MASTER_SYNC_SETUP.json and setup sync. Provide token: ghp_xxxx"
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
  "connected_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
}
JSON
echo "$TOKEN" > /home/user/.arena-secrets/token.txt

# Restore dashboard if exists in repo
if [ -d "dashboard" ]; then
  mkdir -p /home/user/sync-dashboard-php
  cp dashboard/* /home/user/sync-dashboard-php/ 2>/dev/null
  echo "Dashboard files restored"
fi

# Create daemon
cat > /home/user/.arena-secrets/sync-daemon.sh << DAEMON
#!/bin/bash
cd /home/user/project
TOKEN=\$(cat /home/user/.arena-secrets/token.txt 2>/dev/null)
REPO=\$(cat /home/user/.arena-secrets/dashboard_config.json 2>/dev/null | grep repo_short | cut -d'"' -f4)
[ -z "\$REPO" ] && REPO="$REPO_SHORT"
git remote set-url origin https://\${TOKEN}@github.com/\${REPO}.git 2>/dev/null || true
git config user.email "arena-agent@arena.ai" 2>/dev/null
git config user.name "Arena Agent" 2>/dev/null
echo "[\$(date)] Sync ACTIVE for \$REPO - Project: $PROJECT_NAME" > /tmp/sync.log
while true; do
  git pull --rebase origin main 2>&1 | tail -n 1 >> /tmp/sync.log || true
  if [ -n "\$(git status --porcelain 2>&1 | grep -v dashboard)" ]; then
    echo "[\$(date +%H:%M:%S)] Pushing..." >> /tmp/sync.log
    git add -A
    git commit -m "auto-sync: \$(date -u)" >> /tmp/sync.log 2>&1 || true
    git push origin main >> /tmp/sync.log 2>&1 || true
  fi
  sleep 3
done
DAEMON
chmod +x /home/user/.arena-secrets/sync-daemon.sh
pkill -f sync-daemon 2>/dev/null; true
nohup bash /home/user/.arena-secrets/sync-daemon.sh > /tmp/sync.log 2>&1 &
echo $! > /tmp/sync.pid

# Start dashboard
if [ -f "/home/user/sync-dashboard-php/server.js" ]; then
  cd /home/user/sync-dashboard-php
  pkill -f "node server.js" 2>/dev/null; true
  node server.js > /tmp/dashboard.log 2>&1 &
  echo "Dashboard started on port 5050"
fi

echo ""
echo "✅ Setup complete for: $PROJECT_NAME"
echo "📁 Arena: /home/user/project"
echo "🔗 GitHub: https://github.com/$REPO_SHORT"
echo "💻 VS Code: $VSCODE_PATH"
echo "📊 Dashboard: http://localhost:5050 (Live Preview)"
echo ""
echo "In your local VS Code:"
echo "  git clone $REPO_URL"
echo "  Install GitDoc extension -> Auto Pull 3000ms"
