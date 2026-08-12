# MASTER FILE - Works for ANY Project Name

## What is this?

This is a generic sync setup that works for ANY project, not just Sentinel-AI.

When you start a FRESH Arena chat, you don't need to explain everything again. Just say:

**"Read MASTER_SYNC_SETUP.json and setup sync. My token is ghp_xxxx"**

And the new Arena agent will automatically:
1. Read the config
2. Clone your repo (whatever name you set)
3. Set remote with token
4. Start dashboard on port 5050
5. Start auto-push daemon

## How to use for ANY project:

### 1. Edit MASTER_SYNC_SETUP.json (one time per project):

```json
{
  "project_name": "MyNewApp",
  "repo_url": "https://github.com/username/MyNewApp.git",
  "repo_short": "username/MyNewApp",
  "vscode_path": "C:\\xampp\\htdocs\\MyNewApp",
  "token_placeholder": "PASTE_TOKEN_HERE"
}
```

Change:
- `project_name`: Any name you want (e.g. "Blog", "Ecommerce", "AI-Chatbot")
- `repo_url`: Your GitHub repo URL for that project
- `vscode_path`: Where you cloned it in VS Code

### 2. In fresh Arena chat, just say:

"Read MASTER_SYNC_SETUP.json, my token is ghp_xxxx, setup sync"

That's it! Agent will understand because ARENA_AGENT_INSTRUCTIONS.md tells it what to do.

### 3. Or use the script directly:

```bash
bash SETUP_ANY_PROJECT.sh https://github.com/username/MyNewApp.git ghp_xxxx "C:\xampp\htdocs\MyNewApp"
```

Works for any repo name!

## Files in this Master Kit:

- `MASTER_SYNC_SETUP.json` - Config you edit for any project
- `ARENA_AGENT_INSTRUCTIONS.md` - Instructions for AI agent in new chat
- `SETUP_ANY_PROJECT.sh` - Generic setup script for any project
- `index.php` / `api.php` - PHP+JS Dashboard for XAMPP
- `server.js` - Node version for Arena preview
- `RECONNECT_GUIDE.md` - What happens when switching chats

## Download:

This entire folder is in `Arena-Sync-Dashboard-PHP-JS.zip` - download and extract to C:\xampp\htdocs\arena-sync\

Then open http://localhost/arena-sync/
