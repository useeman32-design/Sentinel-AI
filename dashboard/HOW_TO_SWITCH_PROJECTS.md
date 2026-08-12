# How to Switch to Another Project + Find VS Code Folder Path

## 1. How to know your VS Code folder path:

Your VS Code folder path is the folder where you ran `git clone`.

### Method A - Title Bar (Easiest):
- Look at the VERY TOP of VS Code window (title bar)
- It shows something like: `Sentinel-AI — C:\Users\YourName\Desktop\Sentinel-AI`
- The part after "—" is your path: `C:\Users\YourName\Desktop\Sentinel-AI`

### Method B - Copy Path:
- In VS Code Explorer, right-click ANY file (e.g. README.md)
- Click "Copy Path"
- Paste into Notepad, remove the file name, keep folder part
- Example: `C:\xampp\htdocs\Sentinel-AI\README.md` -> `C:\xampp\htdocs\Sentinel-AI`

### Method C - Terminal:
- In VS Code, open terminal (Ctrl + `)
- Type: `cd` (Windows) or `pwd` (Mac/Linux) and press Enter
- It prints your current folder path

### Your Current Path:
For you, it's likely:
`C:\Users\User\Downloads\Softwares\Compressed\xampp\htdocs\Sentinel-AI`
But you cloned into `Sentinel-AI` subfolder, so correct path is:
`C:\Users\User\Downloads\Softwares\Compressed\xampp\htdocs\Sentinel-AI\Sentinel-AI`

Check with `git remote -v` - if it shows URL, you're in correct folder.

---

## 2. How to Switch to Another Project:

### Option A - Using the Dashboard UI (Recommended):
1. Open dashboard: http://localhost/arena-sync/ (XAMPP) or Arena preview
2. In "Saved Projects" click "+ New Project"
3. Enter NEW repo URL: `https://github.com/username/new-project.git`
4. Enter NEW VS Code path: `C:\xampp\htdocs\new-project`
5. Enter new token (if different repo)
6. Click "Connect & Start Auto-Sync"
7. Dashboard now syncs new project
8. In VS Code, clone new repo: `git clone https://github.com/username/new-project.git`

### Option B - Manual:
1. Create new empty repo at github.com/new
2. Copy its URL
3. In C:\xampp\htdocs\, create new folder `new-project`
4. Open terminal in that folder, run: `git clone <new-repo-url> .` (clone into current folder)
5. Update dashboard config.json with new repo_url and vscode_path

### Option C - For Completely Different Project (Not Sentinel-AI):
- Just change Repo URL in dashboard to new repo
- The files in /home/user/project (Arena) will now push to new repo
- Your old repo keeps old files

---

## 3. How the Auto-Sync Works (PHP + JS):

```
Arena Agent writes file in /home/user/project
    ↓ (PHP api.php calls git push every 3 sec)
GitHub Repo (e.g. Sentinel-AI)
    ↓ (GitDoc extension in VS Code auto-pulls every 3 sec)
Your VS Code folder (e.g. C:\xampp\htdocs\Sentinel-AI)
```

Your edits:
```
VS Code save file
    ↓ (GitDoc auto-commits & auto-pushes to GitHub)
GitHub
    ↓ (Arena dashboard auto-pulls every 3 sec)
Arena
```

---

## 4. Download & Setup for XAMPP:

1. Download `Arena-Sync-Dashboard-PHP-JS.zip`
2. Extract to `C:\xampp\htdocs\arena-sync\`
3. Folder structure:
   ```
   C:\xampp\htdocs\arena-sync\
   ├── index.php (main dashboard)
   ├── api.php (backend)
   ├── config.json (will be created)
   └── README.md
   ```
4. Start XAMPP Apache
5. Open browser: http://localhost/arena-sync/
6. You will see same UI as Arena preview

Note: For XAMPP, PHP can run git commands if git is in PATH. If push fails, add git to PATH or use Git Bash.

---

## 5. FAQ:

**Q: Do I need token for pulling?**
A: No. Pull from public repo needs no token. Push needs token.

**Q: Where is my VS Code folder path?**
A: Run `git remote -v` inside your project folder. If it shows GitHub URL, you're in correct path. Copy that path from Explorer.

**Q: Can I have multiple projects?**
A: Yes. Dashboard saves projects in localStorage (browser) and config.json. Click project chips to switch.

**Q: How to change VS Code path only?**
A: In dashboard, just edit VS Code Folder Path input and click Connect again.
