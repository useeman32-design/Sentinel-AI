#!/bin/bash
# MASTER - Works for ANY project name - No re-download needed
# Usage: bash SETUP_ANY_PROJECT.sh <repo_url> <token> [vscode_path]

REPO_URL=${1:-""}
TOKEN=${2:-""}
VSCODE_PATH=${3:-"C:\\xampp\\htdocs\\MyProject"}

if [ -z "$REPO_URL" ] || [ -z "$TOKEN" ]; then
  echo "=== Arena Generic Setup - ANY Project Name ==="
  echo "Usage: bash SETUP_ANY_PROJECT.sh <repo_url> <token> [vscode_path]"
  echo "Example: bash SETUP_ANY_PROJECT.sh https://github.com/user/MyApp.git ghp_xxxx"
  # Try reading from MASTER_SYNC_SETUP.json if exists
  if [ -f "MASTER_SYNC_SETUP.json" ]; then
    echo ""
    echo "Found MASTER_SYNC_SETUP.json, reading..."
    REPO_URL=$(grep repo_url MASTER_SYNC_SETUP.json | cut -d'"' -f4)
    VSCODE_PATH=$(grep vscode_path MASTER_SYNC_SETUP.json | cut -d'"' -f4 | sed 's/\\\\/\\/g')
    echo "Repo: $REPO_URL"
    echo "VSCode Path: $VSCODE_PATH"
    echo "Now run: bash SETUP_ANY_PROJECT.sh $REPO_URL YOUR_TOKEN \"$VSCODE_PATH\""
  fi
  exit 1
fi

REPO_SHORT=$(echo $REPO_URL | sed 's|https://github.com/||' | sed 's|\.git||')
PROJECT_NAME=$(echo $REPO_SHORT | cut -d'/' -f2)

echo "=== Setting up ANY project: $PROJECT_NAME ==="

# If we are not in /home/user/project, clone there
if [ ! -d "/home/user/project/.git" ]; then
  cd /home/user
  rm -rf project
  git clone https://${TOKEN}@github.com/${REPO_SHORT}.git project
fi

cd /home/user/project
git config user.email "arena-agent@arena.ai"
git config user.name "Arena Agent"
git remote set-url origin https://${TOKEN}@github.com/${REPO_SHORT}.git

# Save config
mkdir -p /home/user/.arena-secrets
cat > /home/user/.arena-secrets/dashboard_config.json << JSON
{
  "repo_url": "$REPO_URL",
  "repo_short": "$REPO_SHORT",
  "token": "$TOKEN",
  "vscode_path": "$VSCODE_PATH",
  "project_name": "$PROJECT_NAME"
}
JSON
echo "$TOKEN" > /home/user/.arena-secrets/token.txt

# Restore dashboard files from this repo's dashboard folder if exists
if [ -d "dashboard" ]; then
  mkdir -p /home/user/sync-dashboard-php
  cp dashboard/index.php /home/user/sync-dashboard-php/ 2>/dev/null
  cp dashboard/api.php /home/user/sync-dashboard-php/ 2>/dev/null
  cp dashboard/server.js /home/user/sync-dashboard-php/ 2>/dev/null
  echo "Dashboard files restored from repo/dashboard/"
fi

# Create daemon
cat > /home/user/.arena-secrets/sync-daemon.sh << DAEMON
#!/bin/bash
cd /home/user/project
TOKEN=\$(cat /home/user/.arena-secrets/token.txt)
REPO=\$(cat /home/user/.arena-secrets/dashboard_config.json | grep repo_short | cut -d'"' -f4)
git remote set-url origin https://\${TOKEN}@github.com/\${REPO}.git 2>/dev/null
while true; do
  git pull --rebase origin main 2>&1 | tail -n 2
  if [ -n "\$(git status --porcelain)" ]; then
    git add -A; git commit -m "auto-sync: \$(date -u)"; git push origin main 2>&1 | tail -n 2
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
  node server.js > /tmp/dashboard.log 2>&1 &
  echo "Dashboard on 5050"
fi

echo "✅ $PROJECT_NAME ready! Files: $(ls /home/user/project | wc -l)"
