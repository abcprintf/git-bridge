# git-bridge

Go microservice — MantisBT → GitLab / GitHub  
รองรับหลายโครงการ: แต่ละ MantisBT project map ไปยัง Git repo คนละตัวได้

---

## Supported Providers

| Provider | Auth | Branch API |
|----------|------|-----------|
| GitLab (self-hosted / cloud) | `PRIVATE-TOKEN` | `POST /api/v4/projects/:id/repository/branches` |
| GitHub (cloud) | `Bearer token` | `POST /repos/:owner/:repo/git/refs` |
| GitHub Enterprise Server | `Bearer token` | `POST /api/v3/repos/:owner/:repo/git/refs` |

---

## Architecture

```
MantisBT
  ├── Webhook (auto) ──→ POST /mantis-webhook ──┐
  └── Button (manual) ─→ POST /create-branch  ──┤
                                                 ↓
                                          git-bridge (Go)
                                          lookup projects.json
                                           ├── project 1 → GitLab 123
                                           ├── project 2 → GitLab 456
                                           └── project 3 → GitHub org/repo
                                                 ↓
                                        branch: issue/{id}-{slug}
```

---

## Project Mapping (projects.json)

แต่ละ entry map `mantis_project_id` → Git repo:

```json
[
  {
    "mantis_project_id": 1,
    "provider": "gitlab",
    "gitlab_url": "https://gitlab.example.com",
    "gitlab_token": "glpat-xxx",
    "gitlab_project_id": "123",
    "base_branch": "main"
  },
  {
    "mantis_project_id": 2,
    "provider": "github",
    "github_token": "github_pat_xxx",
    "github_owner": "abcprintf",
    "github_repo": "project-b",
    "base_branch": "develop"
  }
]
```

> ⚠️ `projects.json` มี token — อยู่ใน `.gitignore` ห้าม commit

ดู `projects.example.json` สำหรับ template ทุก provider

---

## Setup

### 1. Config

```bash
cp .env.example .env
cp projects.example.json projects.json
# แก้ค่าใน .env และ projects.json
```

### 2. Token Permissions

| Provider | Token Type | Required Scope |
|----------|-----------|----------------|
| GitLab | Project Access Token | `write_repository` |
| GitHub | Fine-grained PAT | `Contents: Read and write` |
| GitHub | Classic PAT | `repo` |

### 3. Deploy

```bash
docker compose up -d
curl https://bridge.example.com/health
# {"status":"ok","projects":2}
```

---

## MantisBT Integration

### Webhook Plugin (Auto)

```
Admin → Plugins → Webhook → Configure
URL:    https://bridge.example.com/mantis-webhook
Events: issue_updated, issue_assigned
Secret: <ค่าเดียวกับ WEBHOOK_SECRET>
```

### GitLabBridge PHP Plugin (Button)

```bash
cp -r mantisbt-plugin/GitLabBridge /path/to/mantisbt/plugins/
```
```
Admin → Plugins → Install "GitLab Bridge"
→ Configure → Bridge URL + API Token
```

---

## Branch Naming

```
issue/42-fix-login-error
issue/100-auth-session-timeout
issue/7          ← ถ้าไม่มี summary
```

---

## Security

- Webhook: HMAC-SHA256 (`X-Hub-Signature-256`)
- Button API: constant-time token compare (`X-Api-Token`)
- PHP plugin ส่ง request server-side — token ไม่โผล่ใน browser
- `projects.json` อยู่นอก git, mount ผ่าน docker volume

---

## Project Structure

```
git-bridge/
├── cmd/main.go
├── internal/
│   ├── config/
│   │   ├── config.go        ← env config
│   │   └── projects.go      ← load projects.json
│   ├── handler/handler.go   ← webhook + button handlers
│   ├── middleware/middleware.go
│   └── provider/
│       ├── provider.go      ← interface
│       ├── factory.go       ← build provider from config
│       ├── gitlab/client.go
│       └── github/client.go
├── mantisbt-plugin/GitLabBridge/
│   ├── GitLabBridgePlugin.php
│   ├── lang/
│   │   ├── strings_english.txt
│   │   └── strings_thai.txt
│   └── pages/
│       ├── config_page.php
│       └── create_branch_proxy.php
├── Dockerfile
├── docker-compose.yml
├── go.mod
├── .env.example
├── .gitignore
└── projects.example.json    ← template (safe to commit)
```
