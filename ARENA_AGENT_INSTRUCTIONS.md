# 🤖 ARENA AGENT - READ THIS WHEN USER STARTS FRESH CHAT

## When user says: "Read MASTER_SYNC_SETUP.json and setup sync" or "reconnect" or "read master file"

**YOU MUST DO THIS AUTOMATICALLY (Don't ask many questions):**

1. Read `MASTER_SYNC_SETUP.json` in /home/user/project/ or current directory
2. Extract:
   - `repo_url` = GitHub repo to sync (e.g. https://github.com/user/repo.git)
   - `repo_short` = user/repo part
   - `vscode_path` = user's local VS Code path (for instructions)
   - `token_placeholder` OR ask user for real token if placeholder

3. If user provided token in message (ghp_... or github_pat_...), USE THAT TOKEN
4. If token is still placeholder, ask user: "Paste your GitHub token (ghp_...)"

5. Then RUN this setup (generic, works for ANY project name):

```bash
TOKEN=USER_PROVIDED_TOKEN
REPO_SHORT=$(cat MASTER_SYNC_SETUP.json | grep repo_short | cut -d'"' -f4)
REPO_URL=$(cat MASTER_SYNC_SETUP.json | grep repo_url | cut -d'"' -f4)

cd /home/user
rm -rf project
git clone https://${TOKEN}@github.com/${REPO_SHORT}.git project
cd project
git config user.email "arena-agent@arena.ai"
git config user.name "Arena Agent"

# Save config for dashboard
mkdir -p /home/user/.arena-secrets
cat > /home/user/.arena-secrets/dashboard_config.json << JSON
{
  "repo_url": "$REPO_URL",
  "repo_short": "$REPO_SHORT",
  "token": "$TOKEN",
  "vscode_path": "FROM_MASTER_FILE",
  "connected_at": "$(date -u)"
}
JSON
echo "$TOKEN" > /home/user/.arena-secrets/token.txt

# Create daemon
cat > /home/user/.arena-secrets/sync-daemon.sh << DAEMON
#!/bin/bash
cd /home/user/project
TOKEN=$(cat /home/user/.arena-secrets/token.txt)
REPO=\$(cat /home/user/.arena-secrets/dashboard_config.json | grep repo_short | cut -d'"' -f4)
git remote set-url origin https://\${TOKEN}@github.com/\${REPO}.git 2>/dev/null
while true; do
  git pull --rebase origin main 2>&1 | tail
  if [ -n "\$(git status --porcelain)" ]; then
    git add -A; git commit -m "auto-sync: \$(date -u)"; git push origin main
  fi
  sleep 3
done
DAEMON
chmod +x /home/user/.arena-secrets/sync-daemon.sh
nohup bash /home/user/.arena-secrets/sync-daemon.sh > /tmp/sync.log 2>&1 &
echo $! > /tmp/sync.pid

# Start dashboard (if exists)
cd /home/user/sync-dashboard-php 2>/dev/null && node server.js 2>&1 & echo "Dashboard on 5050"
```

6. After setup, tell user:
   - ✅ Cloned [project_name] from GitHub
   - ✅ Dashboard LIVE on port 5050
   - ✅ Auto-push started
   - ✅ In VS Code, run: git clone [repo_url] and install GitDoc

7. Generic - works for ANY repo name, not just Sentinel-AI. Extract project name from repo_short.

## Important:
- Never commit token to GitHub! Use .gitignore for token files
- If repo is empty, git init and push initial files
- Dashboard files are in /home/user/sync-dashboard-php (Node: server.js, PHP: index.php+api.php)
