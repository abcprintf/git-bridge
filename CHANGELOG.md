# Changelog

All notable changes to **git-bridge** will be documented in this file.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.2.0] — 2025-05-13

### Added
- **`GET /project-status`** — new API endpoint ตรวจว่า MantisBT project ถูก map ใน `projects.json` หรือไม่ (returns `{"configured": true/false, "provider": "gitlab|github"}`)
- **Auto-hide widget** — PHP plugin ซ่อน widget ไว้ก่อน แล้ว fetch `/project-status` ตอน page load หากไม่มี config → ไม่แสดง button เลย (ไม่ spam error ใส่ user)
- **Widget-box UI** — ปรับ UI เป็น MantisBT widget-box style (`widget-color-blue2`) เหมือน Time Tracking plugin รองรับ collapse/expand
- **Branch type selector** — เลือก `issue/` `feature/` `bugfix/` `hotfix/` ใน modal พร้อม live preview
- **Checkout command + Copy button** — แสดง `git fetch origin && git checkout <branch>` หลัง branch ถูกสร้าง
- **IDE shortcuts** — ปุ่ม Open in VS Code, GitHub Desktop, และ Open Branch in browser
- **`pages/project_status.php`** — PHP server-side proxy สำหรับ `/project-status` endpoint

### Changed
- **CSP-safe JS** — ย้าย JavaScript ทั้งหมดออกจาก inline script ไปไว้ใน `files/glb-modal.js` (external file) รองรับ MantisBT `Content-Security-Policy: script-src 'nonce-...' 'self'` โดยไม่ต้อง `unsafe-inline`
- **Event delegation** — ใช้ `document.addEventListener` แทน `onclick=""` attribute ทุกจุด
- **`data-*` attributes** — ส่ง PHP variables ไปยัง JS ผ่าน `data-*` บน DOM elements แทน inline script

### Fixed
- Widget render ภายนอก bug details table แล้ว (ใช้ `div` แทน `<tr>`) — ป้องกัน browser foster-parent `<tr>` เข้า table ผิดตัว
- Token ไม่โผล่ใน browser ผ่าน server-side proxy ทุก request

---

## [1.1.0] — 2025-04-20

### Added
- **GitHub Cloud** support — Fine-grained PAT หรือ Classic PAT (`repo` scope)
- **GitHub Enterprise Server (GHES)** support — ระบุ `github_api_url` ได้
- **Multi-project** — `projects.json` รองรับหลาย MantisBT project → หลาย Git repo พร้อมกัน (GitLab + GitHub ผสมกันได้)
- **`base_branch`** per-project config — แต่ละ project กำหนด base branch ได้อิสระ

### Changed
- `projects.json` format เปลี่ยนจาก map `{"id": {...}}` เป็น array `[{...}]`
- Factory pattern (`internal/factory/factory.go`) สำหรับ build provider จาก config
- Provider interface (`internal/provider/provider.go`) abstraction สำหรับ GitLab + GitHub

---

## [1.0.0] — 2025-03-15

### Added
- **Go microservice** — HTTP service รับ request จาก MantisBT
- **GitLab Self-hosted / Cloud** — สร้าง branch ผ่าน GitLab REST API (`POST /repository/branches`)
- **Manual branch creation** — ปุ่ม "Create Branch" ใน MantisBT issue view
- **Auto webhook** — สร้าง branch อัตโนมัติเมื่อ issue เปลี่ยน status (configurable via `TRIGGER_STATUSES`)
- **HMAC-SHA256 webhook validation** — `X-Hub-Signature-256` header
- **API Token auth** — `X-Api-Token` header พร้อม constant-time compare
- **MantisBT Plugin** (`GitLabBridge`) — PHP plugin สำหรับ button + server-side proxy
- **Docker Compose** deployment
- **`GET /health`** endpoint

[1.2.0]: https://github.com/abcprintf/git-bridge/releases/tag/v1.2.0
[1.1.0]: https://github.com/abcprintf/git-bridge/releases/tag/v1.1.0
[1.0.0]: https://github.com/abcprintf/git-bridge/releases/tag/v1.0.0
