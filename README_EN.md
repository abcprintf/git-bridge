# git-bridge

**English** | [ภาษาไทย](README.md)

Go microservice — MantisBT → GitLab / GitHub  
Automatically creates branches from MantisBT issues. Supports multiple projects and multiple Git providers.

---

## Features

- **Manual (Button)** — "Create Branch" button on the MantisBT issue view. Select branch type, edit the name, and copy the checkout command directly.
- **Auto (Webhook)** — Creates a branch automatically when an issue changes status (e.g. `assigned`, `in progress`).
- **Multi-project** — Each MantisBT project maps to a separate Git repository. Mix GitLab and GitHub in the same deployment.
- **CSP-safe** — PHP plugin passes MantisBT's Content Security Policy without `unsafe-inline`.
- **Token never exposed** — The PHP plugin acts as a server-side proxy only. Tokens are never sent to the browser.

---

## Supported Providers

| Provider | Auth |
|----------|------|
| GitLab Self-hosted / Cloud | Project Access Token (`api` scope) |
| GitHub Cloud | Fine-grained PAT or Classic PAT |
| GitHub Enterprise Server | Fine-grained PAT or Classic PAT |

---

## Architecture

```
MantisBT Issue
  ├── Button (manual) ───→ plugin.php (PHP proxy) ──→ POST /create-branch ──┐
  └── Webhook (auto)  ───→                            POST /mantis-webhook ──┤
                                                                              ↓
                                                                     git-bridge (Go)
                                                                     lookup projects.json
                                                                      ├── project 1 → GitLab
                                                                      ├── project 2 → GitLab
                                                                      └── project 3 → GitHub
                                                                              ↓
                                                                     branch: issue/{id}-{slug}
```

---

## Quick Start

### 1. Clone and configure

```bash
git clone https://github.com/abcprintf/git-bridge.git
cd git-bridge
cp .env.example .env
cp projects.example.json projects.json
```

### 2. Edit `.env`

```bash
nano .env
```

### 3. Edit `projects.json`

Add your MantisBT project → Git repo mappings. See [Projects Config](#projects-config) below.

### 4. Deploy

```bash
docker compose up -d
curl https://bridge.example.com/health
# {"status":"ok","projects":2}
```

---

## Go Service Configuration

### Environment Variables (`.env`)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `PORT` | No | `8080` | Port the service listens on |
| `WEBHOOK_SECRET` | **Yes** | — | HMAC secret for MantisBT webhook (`X-Hub-Signature-256`) |
| `API_TOKEN` | **Yes** | — | Token for the PHP plugin (`X-Api-Token`) |
| `PROJECTS_FILE` | No | `/etc/git-bridge/projects.json` | Path to projects.json |
| `TRIGGER_STATUSES` | No | `assigned,in progress` | Issue statuses that trigger auto branch creation |

Example `.env`:

```env
PORT=8080
WEBHOOK_SECRET=change-this-to-a-random-secret-32chars
API_TOKEN=change-this-to-another-random-token
PROJECTS_FILE=/etc/git-bridge/projects.json
TRIGGER_STATUSES=assigned,in progress
```

> Generate a random secret: `openssl rand -hex 32`

---

## Projects Config (`projects.json`)

This file maps MantisBT project IDs to Git repositories.  
It is listed in `.gitignore` — **do not commit** it, as it contains tokens.

### Finding Your MantisBT Project ID

Go to **Manage → Projects** in MantisBT — the ID appears in the URL, e.g. `manage_proj_edit_page.php?project_id=21`.

### Format

```json
[
  { ... project 1 ... },
  { ... project 2 ... }
]
```

> `gitlab_project_id` must be a **string** (quoted with `"`) — not a number.

---

### GitLab (Self-hosted / Cloud)

```json
{
  "mantis_project_id": 21,
  "provider": "gitlab",
  "gitlab_url": "https://gitlab.example.com",
  "gitlab_token": "glpat-xxxxxxxxxxxxxxxxxxxx",
  "gitlab_project_id": "123",
  "base_branch": "main"
}
```

**Finding `gitlab_project_id`:**  
GitLab project → **Settings → General** → Project ID  
Or: `https://gitlab.example.com/api/v4/projects?search=<repo-name>`

**Required token:**  
GitLab project → **Settings → Access Tokens** → create a token with Role **Developer** and Scope **`api`**

---

### GitHub Cloud

```json
{
  "mantis_project_id": 3,
  "provider": "github",
  "github_token": "github_pat_xxxxxxxxxxxxxxxxxxxx",
  "github_owner": "your-org-or-username",
  "github_repo": "repo-name",
  "base_branch": "main"
}
```

**Required token:**  
GitHub → **Settings → Developer settings → Fine-grained tokens**  
Permissions: **Contents → Read and write**

---

### GitHub Enterprise Server (GHES)

```json
{
  "mantis_project_id": 4,
  "provider": "github",
  "github_api_url": "https://github.example.com/api/v3",
  "github_token": "github_pat_xxxxxxxxxxxxxxxxxxxx",
  "github_owner": "your-org",
  "github_repo": "repo-name",
  "base_branch": "develop"
}
```

> If `github_api_url` is omitted, defaults to `https://api.github.com` (GitHub Cloud).

---

### Multi-provider Example

```json
[
  {
    "mantis_project_id": 1,
    "provider": "gitlab",
    "gitlab_url": "https://gitlab.example.com",
    "gitlab_token": "glpat-xxxxxxxxxxxxxxxxxxxx",
    "gitlab_project_id": "10",
    "base_branch": "main"
  },
  {
    "mantis_project_id": 2,
    "provider": "gitlab",
    "gitlab_url": "https://gitlab.example.com",
    "gitlab_token": "glpat-xxxxxxxxxxxxxxxxxxxx",
    "gitlab_project_id": "11",
    "base_branch": "develop"
  },
  {
    "mantis_project_id": 3,
    "provider": "github",
    "github_token": "github_pat_xxxxxxxxxxxxxxxxxxxx",
    "github_owner": "acme-corp",
    "github_repo": "backend-api",
    "base_branch": "main"
  }
]
```

After updating `projects.json`, restart the service:

```bash
docker compose restart git-bridge
docker logs git-bridge --tail 10
# [git-bridge] loaded 3 project mapping(s) from /etc/git-bridge/projects.json
# [git-bridge]   mantis project 1 → gitlab
# [git-bridge]   mantis project 2 → gitlab
# [git-bridge]   mantis project 3 → github
```

---

## Token Permissions

| Provider | Token Type | Required Permission |
|----------|-----------|---------------------|
| GitLab | Project Access Token | Role: **Developer**, Scope: **`api`** |
| GitLab | Personal Access Token | Scope: **`api`**, must have Developer role in project |
| GitHub Cloud | Fine-grained PAT | **Contents: Read and write** |
| GitHub Cloud | Classic PAT | **`repo`** |
| GitHub Enterprise | Fine-grained PAT | **Contents: Read and write** |

> ⚠️ GitLab: Guest/Reporter role returns 403 when creating branches — **Developer or above is required**.

---

## MantisBT Plugin Setup

### Installation

```bash
cp -r mantisbt-plugin/GitLabBridge /path/to/mantisbt/plugins/
```

MantisBT Admin → **Manage → Plugins → Install "GitLab Bridge"**

### Plugin Configuration

Admin → **Plugins → GitLab Bridge → Configure**

| Field | Value |
|-------|-------|
| Bridge URL | URL that the MantisBT **server** can reach git-bridge, e.g. `https://bridge.example.com` |
| API Token | Same value as `API_TOKEN` in `.env` |

> **Important**: Bridge URL must be reachable from the **MantisBT server** (not the browser).  
> If MantisBT and git-bridge are on different machines, use an internal hostname or IP.

### Webhook (Auto Branch)

MantisBT Admin → **Manage → Webhooks → Add Webhook**

```
URL:    https://bridge.example.com/mantis-webhook
Events: issue_updated, issue_assigned
Secret: <same value as WEBHOOK_SECRET in .env>
```

---

## API Reference

### `GET /health`

Check that the service is running. No authentication required.

```bash
curl https://bridge.example.com/health
# {"status":"ok","projects":3}
```

### `GET /project-status?mantis_project_id=<id>`

Check whether a MantisBT project is mapped in `projects.json`.  
Header: `X-Api-Token: <API_TOKEN>`

```bash
# Project has config
curl -H "X-Api-Token: xxx" "https://bridge.example.com/project-status?mantis_project_id=21"
# {"configured":true,"project_id":21,"provider":"gitlab"}

# Project has no config
# HTTP 404
# {"configured":false,"error":"mantis project_id=99 is not mapped to any git repo"}
```

### `POST /create-branch`

Create a branch (called from the PHP plugin).  
Header: `X-Api-Token: <API_TOKEN>`

```json
{
  "issue_id": 11308,
  "project_id": 21,
  "summary": "Fix login error on mobile",
  "branch_name": "bugfix/11308-fix-login-error"
}
```

Response `201 Created`:
```json
{
  "status": "created",
  "branch_name": "bugfix/11308-fix-login-error",
  "web_url": "https://gitlab.example.com/group/repo/-/tree/bugfix/11308-fix-login-error",
  "repo_url": "https://gitlab.example.com/group/repo",
  "provider": "gitlab"
}
```

Response `200 OK` (branch already exists):
```json
{
  "status": "already_exists",
  "branch_name": "bugfix/11308-fix-login-error",
  "provider": "gitlab"
}
```

### `POST /mantis-webhook`

Receive a MantisBT webhook event and create a branch automatically.  
Header: `X-Hub-Signature-256: sha256=<hmac>`

---

## Branch Naming

```
issue/42-fix-login-error-on-mobile
issue/100-auth-session-timeout
issue/7                             ← when summary is empty or slugifies to empty
```

Branch type (selectable in the modal):

| Type | Example |
|------|---------|
| `issue/` | `issue/42-fix-login` |
| `feature/` | `feature/42-new-dashboard` |
| `bugfix/` | `bugfix/42-fix-login` |
| `hotfix/` | `hotfix/42-critical-fix` |

---

## Security

- **Webhook**: HMAC-SHA256 (`X-Hub-Signature-256`) — requests with invalid signatures are rejected
- **Button API**: Constant-time token comparison (`X-Api-Token`) — prevents timing attacks
- **Token never in browser**: PHP plugin forwards requests server-side only
- **CSP compliant**: JavaScript is served as an external file with event delegation — no inline scripts or handlers
- **`projects.json`**: Kept outside the Git repository, mounted into the container via Docker volume

---

## Project Structure

```
git-bridge/
├── cmd/main.go                          ← entry point, routes
├── internal/
│   ├── config/
│   │   ├── config.go                   ← load env vars
│   │   └── projects.go                 ← load projects.json
│   ├── factory/
│   │   └── factory.go                  ← build provider from config
│   ├── handler/
│   │   └── handler.go                  ← webhook + button + project-status handlers
│   ├── middleware/
│   │   └── middleware.go               ← HMAC, API token, logger
│   └── provider/
│       ├── provider.go                 ← Provider interface
│       ├── gitlab/client.go            ← GitLab API client
│       └── github/client.go            ← GitHub API client
├── mantisbt-plugin/GitLabBridge/
│   ├── GitLabBridge.php                ← plugin main (register, hooks, render)
│   ├── lang/
│   │   ├── strings_english.txt
│   │   └── strings_thai.txt
│   ├── files/
│   │   └── glb-modal.js               ← modal JS (external file, CSP-safe)
│   └── pages/
│       ├── config_page.php            ← plugin settings form
│       ├── config.php                 ← settings POST handler
│       ├── create_branch_proxy.php   ← proxy: MantisBT → git-bridge
│       └── project_status.php        ← proxy: check project config
├── Dockerfile
├── docker-compose.yml
├── go.mod
├── .env.example                       ← template (safe to commit)
├── .gitignore
└── projects.example.json              ← template for all providers (safe to commit)
```

---

## Troubleshooting

### Bridge not responding (502)

```bash
docker ps | grep git-bridge          # check container is running
docker logs git-bridge --tail 30     # inspect errors
curl http://localhost:8011/health    # test from host
```

### `project_id=X is not mapped` (422)

```bash
cat /etc/git-bridge/projects.json    # verify mantis_project_id
docker compose restart git-bridge    # reload config
```

### GitLab 403 Forbidden

- Token scope must be **`api`**, not `read_api`
- Token owner must have **Developer** role or above in the project

### `cannot unmarshal number into... type string`

`gitlab_project_id` must be a string:
```json
"gitlab_project_id": "123"    ✅
"gitlab_project_id": 123      ❌
```

### Plugin page 404

Deactivate and re-activate the plugin in MantisBT Admin → Manage Plugins.

---

## License

MIT
