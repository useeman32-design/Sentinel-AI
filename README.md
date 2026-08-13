# Arena Sync - Master Setup (Any Project Name)

**Clean master setup - no trial files. Works for ANY project.**

## Files in this repo (ONLY setup files):
- `MASTER_SYNC_SETUP.json` - EDIT for any project (repo URL, name, VS Code path, token placeholder)
- `SETUP_ANY_PROJECT.sh` - Generic setup - works for ANY project name
- `ARENA_AGENT_INSTRUCTIONS.md` - Tells new Arena agent what to do
- `dashboard/` - PHP+JS Dashboard for XAMPP and Arena
  - `index.php` - UI
  - `api.php` - Backend
  - `server.js` - Node version for Arena preview

## For Fresh Arena Chat:

### Option 1: Edit JSON then ask agent:
1. Edit `MASTER_SYNC_SETUP.json`:
```json
{
  "project_name": "MyNewApp",
  "repo_url": "https://github.com/username/MyNewApp.git",
  "repo_short": "username/MyNewApp",
  "vscode_path": "C:\\xampp\\htdocs\\MyNewApp",
  "token_placeholder": "ghp_xxxx"
}
```
2. In fresh chat, say:
> "Read MASTER_SYNC_SETUP.json, my token is ghp_xxxx, setup sync"

### Option 2: One-line for ANY project:
```bash
bash SETUP_ANY_PROJECT.sh https://github.com/username/Repo.git ghp_xxxx "C:\xampp\htdocs\Repo"
```

### Option 3: Interactive (asks for repo, token, path):
```bash
bash SETUP_ANY_PROJECT.sh
```

## How to switch project:
- Just edit repo_url and vscode_path in MASTER_SYNC_SETUP.json
- Run setup script with new repo + token
- Clone new repo in VS Code

## Download for XAMPP:
- Dashboard is in `dashboard/` folder
- Copy `dashboard/` to `C:\xampp\htdocs\arena-sync\`
- Open http://localhost/arena-sync/
