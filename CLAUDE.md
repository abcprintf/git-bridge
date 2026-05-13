# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Build
go build ./cmd/main.go

# Run (requires env vars)
PORT=8080 WEBHOOK_SECRET=xxx API_TOKEN=xxx PROJECTS_FILE=./projects.json go run ./cmd/main.go

# Docker
docker compose up -d --build
docker compose logs -f

# Test single package
go test ./internal/handler/...
go test ./internal/provider/gitlab/...
```

## Required Environment Variables

| Variable | Description |
|----------|-------------|
| `WEBHOOK_SECRET` | HMAC-SHA256 secret สำหรับ MantisBT webhook |
| `API_TOKEN` | Bearer token สำหรับ PHP plugin (`X-Api-Token`) |
| `PROJECTS_FILE` | Path ของ `projects.json` (default `/etc/git-bridge/projects.json`) |
| `PORT` | HTTP port (default `8080`) |
| `TRIGGER_STATUSES` | Comma-separated statuses ที่ trigger auto branch (default `assigned,in progress`) |

## Architecture

Go microservice ที่เชื่อม MantisBT กับ Git hosting (GitLab/GitHub) โดยมี PHP plugin ฝั่ง MantisBT เป็น client

```
MantisBT UI
  └── PHP Plugin (mantisbt-plugin/GitLabBridge/)
        ├── pages/project_status.php   → proxy → GET /project-status
        ├── pages/create_branch_proxy.php → proxy → POST /create-branch
        └── files/glb-modal.js         → UI modal (CSP-safe, external file)

Go Service (cmd/main.go)
  ├── POST /mantis-webhook   → HMAC auth → auto branch on status change
  ├── POST /create-branch    → API token auth → manual branch from button
  ├── GET  /project-status   → API token auth → check if project is configured
  └── GET  /health
```

### Package Structure

- **`internal/config`** — โหลด env vars (`Config`) + parse `projects.json` (`ProjectConfig`)
- **`internal/provider`** — `Provider` interface: `CreateBranch(name, ref) → (Branch, alreadyExists, error)`
- **`internal/provider/gitlab`** — GitLab REST API (`POST /api/v4/projects/:id/repository/branches`)
- **`internal/provider/github`** — GitHub REST API (2-step: GET base SHA → POST refs)
- **`internal/factory`** — `BuildProviderMap()` แปลง `[]ProjectConfig` → `map[mantisProjectID]Provider`
- **`internal/handler`** — HTTP handlers + `buildBranchName()` slugify จาก issue summary
- **`internal/middleware`** — `ValidateHMAC` (webhook), `ValidateAPIToken` (button), `Logger`

### Key Design Decisions

- `projects.json` mount เข้า container ผ่าน volume — ไม่ commit ลง repo (มี token)
- PHP plugin ทำ server-side proxy ทุก request → token ไม่โผล่ใน browser
- `CreateBranch` return `alreadyExists bool` แทน error เพื่อแยก 409 case ออกจาก real error
- Branch name สร้างจาก `issue/<id>-<slugified-summary>` (ASCII only, max 50 chars) หรือ custom name จาก modal

### Adding a New Provider

1. สร้าง package ใน `internal/provider/<name>/client.go` implement `Provider` interface
2. เพิ่ม case ใน `internal/factory/factory.go`
3. เพิ่ม fields ที่ต้องการใน `internal/config/projects.go` (`ProjectConfig` struct + `validateProject`)

## MantisBT Plugin

ชื่อ plugin คือ `GitLabBridge` (ชื่อเดิมจาก v1.0) — ยังใช้ชื่อนี้อยู่แม้รองรับ GitHub แล้ว  
Install: วาง `mantisbt-plugin/GitLabBridge/` ไว้ใน `<mantisbt>/plugins/`

## Release Workflow

1. อัปเดต `CHANGELOG.md` เพิ่ม section `## [x.x.x] — YYYY-MM-DD`
2. `git tag vx.x.x && git push origin vx.x.x`
3. GitHub Actions (`.github/workflows/release.yml`) จัดการ: parse changelog → create release → update `docs/github-releases.md`
