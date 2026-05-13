/**
 * GitLabBridge — Modal logic (external file, CSP 'self' compliant)
 * ไม่มี inline script — ทุก data มาจาก data-* attributes บน DOM elements
 */
(function () {
    'use strict';

    // ─── Helpers ──────────────────────────────────────────────────────────────

    function $(id) { return document.getElementById(id); }

    // ─── Check project config on page load ────────────────────────────────────
    // ซ่อน widget ไว้ก่อน แล้วแสดงเฉพาะเมื่อ project มี config ใน bridge

    function initWidgets() {
        var widgets = document.querySelectorAll('[id^="glb-widget-"]');
        widgets.forEach(function (widget) {
            var checkUrl = widget.dataset.checkUrl;
            if (!checkUrl) return;

            fetch(checkUrl)
                .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, data: d }; }); })
                .then(function (r) {
                    if (r.ok && r.data.configured) {
                        widget.style.display = 'block'; // แสดง widget
                    }
                    // ถ้าไม่ configured → ซ่อนต่อ (ไม่ต้อง render อะไร)
                })
                .catch(function () {
                    // bridge unreachable → ซ่อนต่อ (ไม่ spam error ใส่ user)
                });
        });
    }

    function openModal(bugId, proxyUrl, slug) {
        var modal = $('glb-modal-' + bugId);
        if (!modal) return;

        // reset state
        modal._proxyUrl   = proxyUrl;
        modal._slug       = slug;
        modal._manualEdit = false;

        $('glb-result-'  + bugId).style.display = 'none';
        $('glb-checkout-'+ bugId).style.display = 'none';

        var confirm = $('glb-confirm-' + bugId);
        var form    = $('glb-form-'    + bugId);
        var status  = $('glb-status-'  + bugId);

        // form แสดงเลย (check ผ่านแล้วตั้งแต่ page load)
        if (status) status.style.display = 'none';
        if (form)   form.style.display   = 'block';
        confirm.disabled    = false;
        confirm.textContent = 'สร้าง Branch';
        confirm.style.display = '';

        updatePreview(bugId, slug);
        modal.style.display = 'flex';
    }

    function closeModal(bugId) {
        var modal = $('glb-modal-' + bugId);
        if (modal) modal.style.display = 'none';
    }

    function updatePreview(bugId, slug) {
        var modal = $('glb-modal-' + bugId);
        if (modal && modal._manualEdit) return;

        var radios = document.querySelectorAll('input[name="glb-type-' + bugId + '"]');
        var type = 'issue';
        radios.forEach(function (r) { if (r.checked) type = r.value; });

        var el = $('glb-branchname-' + bugId);
        if (el) {
            el.value = slug
                ? type + '/' + bugId + '-' + slug
                : type + '/' + bugId;
        }
    }

    function doCreate(bugId) {
        var modal     = $('glb-modal-' + bugId);
        var proxyUrl  = modal ? modal._proxyUrl : '';
        var branchEl  = $('glb-branchname-' + bugId);
        var confirm   = $('glb-confirm-'    + bugId);
        var result    = $('glb-result-'     + bugId);
        var msg       = $('glb-result-msg-' + bugId);
        var checkout  = $('glb-checkout-'   + bugId);
        var cmd       = $('glb-cmd-'        + bugId);

        if (!branchEl || !proxyUrl) return;
        var branchName = branchEl.value.trim();
        if (!branchName) return;

        confirm.disabled = true;
        confirm.textContent = '⏳ Creating...';

        fetch(proxyUrl + '&branch_name=' + encodeURIComponent(branchName), { method: 'POST' })
            .then(function (res) {
                return res.json().then(function (d) { return { ok: res.ok, data: d }; });
            })
            .then(function (r) {
                result.style.display = 'block';
                if (r.ok) {
                    var created = r.data.status === 'created';
                    msg.innerHTML = (created ? '✅' : '⚠️')
                        + ' <strong>' + (created ? 'Branch สร้างเรียบร้อย' : 'Branch นี้มีอยู่แล้ว') + '</strong><br>'
                        + '<code style="font-size:12px">' + r.data.branch_name + '</code>';
                    checkout.style.display = 'block';
                    cmd.textContent = 'git fetch origin && git checkout ' + r.data.branch_name;
                    confirm.textContent = created ? '✅ Done' : '✅ Already exists';
                    setIDELinks(bugId, r.data.branch_name, r.data.repo_url, r.data.web_url);
                } else {
                    msg.innerHTML = '❌ <strong>Error:</strong> ' + (r.data.error || 'unknown');
                    confirm.disabled = false;
                    confirm.textContent = 'สร้าง Branch';
                }
            })
            .catch(function (err) {
                result.style.display = 'block';
                msg.innerHTML = '❌ Network error: ' + err.message;
                confirm.disabled = false;
                confirm.textContent = 'สร้าง Branch';
            });
    }

    function setIDELinks(bugId, branchName, repoUrl, webUrl) {
        var vscode = $('glb-vscode-'    + bugId);
        var ghd    = $('glb-ghdesktop-' + bugId);
        var web    = $('glb-webbranch-' + bugId);
        var note   = $('glb-ide-note-'  + bugId);

        if (vscode) {
            vscode.href = 'vscode://vscode.git/fetch';
            vscode.addEventListener('click', function () {
                note.style.display = 'block';
                note.textContent = '💡 VS Code เปิดแล้ว — รัน "Git: Checkout to..." หรือใช้ command ด้านบนใน terminal';
            });
        }

        if (ghd) {
            if (repoUrl) {
                ghd.href = 'x-github-client://openRepo?repoUrl=' + encodeURIComponent(repoUrl);
                ghd.addEventListener('click', function () {
                    note.style.display = 'block';
                    note.textContent = '💡 GitHub Desktop เปิดแล้ว — เลือก branch "' + branchName + '" ในเมนู Branch';
                });
            } else {
                ghd.style.display = 'none';
            }
        }

        if (web) {
            if (webUrl) { web.href = webUrl; }
            else { web.style.display = 'none'; }
        }
    }

    // ─── Wire up events via event delegation ─────────────────────────────────
    // ใช้ delegation เพื่อรองรับ dynamic content และ CSP compliant

    document.addEventListener('click', function (e) {
        var t = e.target;

        // ปุ่ม Open modal
        var openBtn = t.closest('.glb-open-btn');
        if (openBtn) {
            var bugId    = openBtn.dataset.bugId;
            var proxyUrl = openBtn.dataset.proxyUrl;
            var slug     = openBtn.dataset.slug;
            openModal(bugId, proxyUrl, slug);
            return;
        }

        // ปุ่ม Close / Cancel
        if (t.closest('.glb-close-btn') || t.closest('.glb-cancel-btn')) {
            var btn = t.closest('.glb-close-btn') || t.closest('.glb-cancel-btn');
            closeModal(btn.dataset.bugId);
            return;
        }

        // คลิก backdrop ปิด modal
        if (t.classList && t.classList.contains('glb-modal-backdrop')) {
            closeModal(t.dataset.bugId);
            return;
        }

        // ปุ่ม Confirm สร้าง branch
        var confirmBtn = t.closest('[id^="glb-confirm-"]');
        if (confirmBtn) {
            var id = confirmBtn.id.replace('glb-confirm-', '');
            doCreate(id);
            return;
        }

        // ปุ่ม Copy command
        var copyBtn = t.closest('[id^="glb-copy-"]');
        if (copyBtn) {
            var cid = copyBtn.id.replace('glb-copy-', '');
            var cmdEl = $('glb-cmd-' + cid);
            if (cmdEl) {
                navigator.clipboard.writeText(cmdEl.textContent).then(function () {
                    copyBtn.textContent = '✅ Copied!';
                    setTimeout(function () { copyBtn.textContent = '📋 Copy'; }, 2000);
                });
            }
            return;
        }
    });

    // Branch name input validation
    document.addEventListener('input', function (e) {
        var el = e.target;
        if (el.id && el.id.indexOf('glb-branchname-') === 0) {
            var bugId = el.id.replace('glb-branchname-', '');
            var modal = $('glb-modal-' + bugId);
            if (modal) modal._manualEdit = true;
            var warn = $('glb-name-warn-' + bugId);
            if (warn) warn.style.display = /[^\w\-\/\.]/.test(el.value) ? 'block' : 'none';
        }
    });

    // Branch type radio — update preview
    document.addEventListener('change', function (e) {
        var el = e.target;
        if (el.classList && el.classList.contains('glb-type-radio')) {
            var bugId = el.dataset.bugId;
            var modal = $('glb-modal-' + bugId);
            var slug  = modal ? modal._slug : '';
            updatePreview(bugId, slug);
        }
    });

    // Backdrop click — ปิด modal เมื่อคลิกพื้นที่นอก dialog
    document.addEventListener('click', function (e) {
        var modals = document.querySelectorAll('[id^="glb-modal-"]');
        modals.forEach(function (modal) {
            if (e.target === modal) {
                closeModal(modal.dataset.bugId);
            }
        });
    });

    // ─── Init: check project config on page load ───────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWidgets);
    } else {
        initWidgets(); // DOM already ready
    }

})();
