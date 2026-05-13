# GitHub Releases — Draft Copy

## v1.2.2 — 2026-05-13



[View Release](https://github.com/abcprintf/git-bridge/releases/tag/v1.2.2)

---

วาง markdown ด้านล่างใน GitHub → Releases → "Create a new release"
แยกแต่ละ version ด้วย `---`

---

## v1.2.0 — CSP-Safe UI & Project Status Check

> **Tag:** `v1.2.0`  
> **Title:** `v1.2.0 — CSP-Safe UI & Project Status Check`  
> **Set as latest release:** ✅

---

### What's New

**🛡️ CSP-Safe JavaScript**

Moved all JavaScript to an external file (`files/glb-modal.js`) — fully compatible with MantisBT's `Content-Security-Policy: script-src 'nonce-... 'self'` without requiring `unsafe-inline`. Uses event delegation and `data-*` attributes throughout.

**🔍 Project Status Check**

The "Create Branch" widget is now hidden by default. On page load, the plugin fetches `/project-status` and only shows the button if the MantisBT project is mapped in `projects.json`. No more error messages for unconfigured projects.

New API endpoint:
```
GET /project-status?mantis_project_id=<id>
```
```json
{ "configured": true, "project_id": 21, "provider": "gitlab" }
```

**🎨 Widget-Box UI**

Rebuilt UI to match MantisBT's native widget style (`widget-color-blue2`) — same look and feel as the Time Tracking plugin. Supports collapse/expand.

**🌿 Branch Type Selector**

Choose branch prefix in the modal with live preview:

| Type | Example |
|------|---------|
| `issue/` | `issue/42-fix-login` |
| `feature/` | `feature/42-new-dashboard` |
| `bugfix/` | `bugfix/42-fix-login` |
| `hotfix/` | `hotfix/42-critical-fix` |

**📋 Checkout Command**

After branch creation, the modal displays the ready-to-run command and a Copy button:
```
git fetch origin && git checkout bugfix/42-fix-login
```

Plus one-click shortcuts to open in VS Code, GitHub Desktop, or browser.

---

### Changes

- Widget hidden by default — shown only after project config is confirmed
- JavaScript fully extracted to external file (CSP `'self'` compliant)
- `onclick=""` replaced with event delegation throughout
- PHP variables passed via `data-*` attributes (no inline script)
- Div-based widget structure (fixes browser foster-parent bug with `<tr>` in MantisBT tables)

---

### Files Changed

```
mantisbt-plugin/GitLabBridge/
├── GitLabBridge.php           ← UI rewrite, CSP fix, widget-box structure
├── files/glb-modal.js         ← NEW: external JS (modal, fetch, event delegation)
└── pages/project_status.php  ← NEW: PHP proxy for /project-status

internal/handler/handler.go   ← added ProjectStatus handler
cmd/main.go                    ← added GET /project-status route
```

---

### Upgrade Notes

1. Copy updated `GitLabBridge.php` and new `files/glb-modal.js` + `pages/project_status.php` to your MantisBT plugins directory
2. **Deactivate → Activate** the plugin in MantisBT Admin → Manage Plugins (required to register new pages)
3. Rebuild and redeploy Go service:
   ```bash
   docker compose build git-bridge
   docker compose up -d
   curl https://bridge.example.com/health
   ```

**No breaking changes** — `projects.json` and `.env` format unchanged.

---

---

## v1.1.0 — GitHub Support & Multi-Project

> **Tag:** `v1.1.0`  
> **Title:** `v1.1.0 — GitHub Support & Multi-Project`

---

### What's New

**🐙 GitHub Support**

Added GitHub Cloud and GitHub Enterprise Server (GHES) as providers alongside GitLab.

```json
{
  "mantis_project_id": 3,
  "provider": "github",
  "github_token": "github_pat_...",
  "github_owner": "your-org",
  "github_repo": "repo-name",
  "base_branch": "main"
}
```

For GHES, set `github_api_url`:
```json
"github_api_url": "https://github.example.com/api/v3"
```

**🗂️ Multi-Project**

Each MantisBT project can now map to a different Git repository — mix GitLab and GitHub in the same `projects.json`:

```json
[
  { "mantis_project_id": 1, "provider": "gitlab", ... },
  { "mantis_project_id": 2, "provider": "gitlab", ... },
  { "mantis_project_id": 3, "provider": "github", ... }
]
```

**🌿 Per-Project Base Branch**

Set `base_branch` independently per project (`main`, `develop`, or any branch).

---

### Breaking Changes

**`projects.json` format changed** — from map to array:

```json
// ❌ Old (v1.0.0)
{ "21": { "provider": "gitlab", ... } }

// ✅ New
[ { "mantis_project_id": 21, "provider": "gitlab", ... } ]
```

---

---

## v1.0.0 — Initial Release

> **Tag:** `v1.0.0`  
> **Title:** `v1.0.0 — Initial Release`

---

### Overview

**git-bridge** is a lightweight Go microservice that connects MantisBT to GitLab (and GitHub), automatically creating branches from issues.

**Two modes:**
- **Manual** — "Create Branch" button in MantisBT issue view
- **Auto** — Branch created automatically when issue status changes (configurable)

**Highlights:**
- ✅ GitLab Self-hosted & Cloud (Project Access Token, `api` scope)
- ✅ HMAC-SHA256 webhook validation (`X-Hub-Signature-256`)
- ✅ API token auth with constant-time compare (timing-attack safe)
- ✅ Server-side proxy — tokens never exposed to browser
- ✅ Docker Compose deployment
- ✅ `/health` endpoint

**Quick start:**
```bash
git clone https://github.com/abcprintf/git-bridge.git
cd git-bridge
cp .env.example .env && cp projects.example.json projects.json
# edit .env and projects.json
docker compose up -d
curl https://bridge.example.com/health
# {"status":"ok","projects":1}
```

See [README](README.md) for full configuration guide.