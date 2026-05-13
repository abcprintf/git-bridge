# git-bridge

Go microservice — MantisBT → GitLab / GitHub  
Auto-create branch เมื่อ issue ถูก assign หรือกดปุ่มใน MantisBT

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
                                          provider interface
                                           ├── GitLab client
                                           └── GitHub client
                                                 ↓
                                        branch: issue/{id}-{slug}
```

---

## Setup

### 1. Config

```bash
cp .env.example .env
```

**GitLab:**
```env
GIT_PROVIDER=gitlab
GITLAB_URL=https://gitlab.igenco.dev
GITLAB_TOKEN=glpat-xxx      # Project Access Token, scope: write_repository
GITLAB_PROJECT_ID=123
```

**GitHub:**
```env
GIT_PROVIDER=github
GITHUB_TOKEN=github_pat_xxx  # Fine-grained token, Permission: Contents → Read and write
GITHUB_OWNER=igenco
GITHUB_REPO=my-repo
# GITHUB_API_URL=https://github.igenco.dev/api/v3  # GHES เท่านั้น
```

### 2. Deploy

```bash
docker compose up -d
curl https://bridge.igenco.dev/health
# {"status":"ok","provider":"gitlab"}
```

---

## MantisBT Integration

### Webhook Plugin (Auto)

```
Admin → Plugins → Webhook → Configure
URL:    https://bridge.igenco.dev/mantis-webhook
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

## Token Permissions

| Provider | Token Type | Required Scope |
|----------|-----------|----------------|
| GitLab | Project Access Token | `write_repository` |
| GitHub | Fine-grained PAT | `Contents: Read and write` |
| GitHub | Classic PAT | `repo` |

---

## Security

- Webhook: HMAC-SHA256 validation (`X-Hub-Signature-256`)
- Button API: constant-time token comparison (`X-Api-Token`)
- ควร deploy ใน internal network + Nginx/Traefik reverse proxy พร้อม TLS
- ไม่ควร expose port 8080 ตรงๆ

---

## Project Structure

```
git-bridge/
├── cmd/main.go
├── internal/
│   ├── config/config.go
│   ├── handler/handler.go
│   ├── middleware/middleware.go
│   └── provider/
│       ├── provider.go          ← interface
│       ├── gitlab/client.go
│       └── github/client.go
├── mantisbt-plugin/GitLabBridge/
│   ├── GitLabBridgePlugin.php
│   ├── lang/strings_english.txt
│   └── pages/config_page.php
├── Dockerfile
├── docker-compose.yml
├── go.mod
└── .env.example
```
