# GitHub Auto-Sync is ACTIVE -Waiting for your repo URL

The daemon is running in background in /home/user/project. It will auto-push every 3 seconds.

## What you need to do RIGHT NOW:

### 1. Create empty repo (if you haven't)
- Go to https://github.com/new
- Name: `arena-sync` (or any name)
- **Don't** initialize with README (create empty)
- Click Create
- Copy URL: https://github.com/YOURNAME/arena-sync.git

### 2. Create a Personal Access Token (for me to push)
- Go to https://github.com/settings/tokens/new
- Note: `arena-agent-sync`
- Expiration: 7 days (or whatever)
- Select scopes: check `repo` (full control)
- Click Generate token
- COPY the token (starts with ghp_...) - you won't see it again!

### 3. Paste in this chat:
Send me BOTH in one message:
```
REPO_URL: https://github.com/YOURNAME/arena-sync.git
TOKEN: ghp_xxxxxxxxxxxx
```

I will:
- Set remote to https://TOKEN@github.com/YOURNAME/arena-sync.git
- Push initial files
- Start auto-sync loop

The token is safe - it's only stored in this sandbox /home/user/.git/config and you can delete it from GitHub after we're done. For more security, use a fine-grained token limited to just that repo: https://github.com/settings/tokens?type=beta -> New fine-grained token -> select only your arena-sync repo -> Repository access -> Contents: Read and Write.

### 4. In your LOCAL VS Code (for two-way sync):

After I push first time:

```bash
git clone https://github.com/YOURNAME/arena-sync.git
cd arena-sync
code .
```

Inside VS Code:
- Install extension: **GitDoc** by Victor Bogomets (auto-commit + auto-push + auto-pull)
- Enable it: Settings -> search GitDoc -> check Auto Pull, Auto Push after delay 3000ms

OR if you don't want extension, run in terminal inside that folder:
```bash
# Auto-pull every 3 seconds (keeps you in sync with Arena)
while true; do git pull --rebase; sleep 3; done
```

And for pushing your local changes to Arena, also run:
```bash
# Auto-push your edits back to Arena (in another terminal)
while true; do 
  if [ -n "$(git status --porcelain)" ]; then
    git add -A; git commit -m "local sync"; git push
  fi
  sleep 3
done
```

With GitDoc extension, both are automatic - no terminals needed.

### Testing Sync:

Once you paste URL+TOKEN, I will create a file `SYNC_TEST_$(date).txt` - you should see it appear in your local VS Code within 5-10 seconds after `git pull` or GitDoc auto-pull.

## Important:
- Keep this Arena chat open while working - daemon runs here
- All files you want synced MUST be in /home/user/project
