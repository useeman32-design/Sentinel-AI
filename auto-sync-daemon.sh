#!/bin/bash
# Auto-sync daemon - watches /home/user/project and pushes to GitHub
set -e
cd /home/user/project

echo "=== Arena Auto-Sync Daemon ==="
echo "Watching: $(pwd)"
echo "Remote: $(git remote get-url origin 2>&1 || echo 'NOT SET YET - waiting for you to provide repo URL')"

git config --global user.email "arena-agent@arena.ai" 2>/dev/null || true
git config --global user.name "Arena Agent" 2>/dev/null || true
git config --global init.defaultBranch main 2>/dev/null || true

# Try to set default branch to main
git branch -M main 2>/dev/null || true

while true; do
  # Check if origin exists
  if ! git remote | grep -q origin; then
    echo "[$(date)] Waiting for origin to be set... (paste repo URL in chat)"
    sleep 5
    continue
  fi

  if [ -n "$(git status --porcelain)" ]; then
    echo "[$(date)] Changes detected, pushing..."
    git add -A
    # Only commit if there is something to commit
    if ! git diff --cached --quiet; then
      git commit -m "auto-sync: $(date -u +%Y-%m-%dT%H:%M:%SZ)" || true
      git push origin main 2>&1 | tail -n 5 || echo "Push failed - might need auth"
    fi
  fi
  sleep 3
done
