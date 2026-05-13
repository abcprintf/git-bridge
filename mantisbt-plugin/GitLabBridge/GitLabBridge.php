<?php
/**
 * GitLabBridge — MantisBT Plugin (Jira-style modal)
 * - Modal dialog พร้อม branch name preview และแก้ไขได้
 * - เลือก branch type: feature / bugfix / hotfix
 * - หลัง create: แสดง checkout command + copy button
 * - Request ผ่าน server-side proxy — token ไม่โผล่ใน browser
 */
class GitLabBridgePlugin extends MantisPlugin {

    function register() {
        $this->name        = 'GitLab Bridge';
        $this->description = 'Create GitLab/GitHub branch from MantisBT issue (Jira-style)';
        $this->version     = '1.2.0';
        $this->requires    = ['MantisCore' => '2.0.0'];
        $this->author      = 'IGENCO';
    }

    function config() {
        return [
            'bridge_url' => '',
            'api_token'  => '',
        ];
    }

    function hooks() {
        return [
            'EVENT_DISPLAY_BUG_DETAILS' => 'render_create_branch_row',
        ];
    }

    function render_create_branch_row( $p_event, $p_bug_id ) {
        $bridge_url = plugin_config_get( 'bridge_url' );
        $api_token  = plugin_config_get( 'api_token' );
        if ( empty( $bridge_url ) || empty( $api_token ) ) return;

        $bug        = bug_get( $p_bug_id );
        $bug_id     = (int) $p_bug_id;
        $summary    = string_attribute( $bug->summary );
        $proxy_url  = plugin_page( 'create_branch_proxy', true ) . '&bug_id=' . $bug_id;

        // Pre-compute default branch name (slugify จาก summary)
        $slug         = $this->slugify( $summary );
        $default_name = $slug ? "issue/{$bug_id}-{$slug}" : "issue/{$bug_id}";

        ?>
        <tr>
            <td class="category"><?php echo plugin_lang_get( 'title' ) ?></td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="glbOpenModal(<?php echo $bug_id ?>)">
                    🔀 <?php echo plugin_lang_get( 'create_branch' ) ?>
                </button>
            </td>
        </tr>

        <!-- Modal -->
        <div id="glb-modal-<?php echo $bug_id ?>"
             style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
            <div style="background:#fff;border-radius:8px;padding:28px;width:520px;max-width:95vw;box-shadow:0 8px 32px rgba(0,0,0,.2)">

                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
                    <h3 style="margin:0;font-size:16px;font-weight:600">🔀 Create Git Branch</h3>
                    <button onclick="glbCloseModal(<?php echo $bug_id ?>)"
                            style="background:none;border:none;font-size:20px;cursor:pointer;color:#666;line-height:1">×</button>
                </div>

                <div style="margin-bottom:14px">
                    <label style="display:block;font-size:12px;font-weight:600;color:#666;margin-bottom:4px">ISSUE</label>
                    <div style="font-size:13px;color:#333">#<?php echo $bug_id ?> — <?php echo htmlspecialchars( $summary, ENT_QUOTES ) ?></div>
                </div>

                <div style="margin-bottom:14px">
                    <label style="display:block;font-size:12px;font-weight:600;color:#666;margin-bottom:4px">BRANCH TYPE</label>
                    <div style="display:flex;gap:8px">
                        <?php foreach ( ['issue','feature','bugfix','hotfix'] as $type ): ?>
                        <label style="cursor:pointer">
                            <input type="radio" name="glb-type-<?php echo $bug_id ?>"
                                   value="<?php echo $type ?>"
                                   <?php echo $type === 'issue' ? 'checked' : '' ?>
                                   onchange="glbUpdatePreview(<?php echo $bug_id ?>)">
                            <code style="font-size:12px;padding:3px 8px;border-radius:4px;background:#f0f0f0"><?php echo $type ?>/</code>
                        </label>
                        <?php endforeach ?>
                    </div>
                </div>

                <div style="margin-bottom:20px">
                    <label style="display:block;font-size:12px;font-weight:600;color:#666;margin-bottom:4px">BRANCH NAME</label>
                    <input id="glb-branchname-<?php echo $bug_id ?>"
                           type="text"
                           value="<?php echo htmlspecialchars( $default_name, ENT_QUOTES ) ?>"
                           style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;font-family:monospace;font-size:13px;box-sizing:border-box"
                           oninput="glbOnNameInput(<?php echo $bug_id ?>)">
                    <div id="glb-name-warn-<?php echo $bug_id ?>"
                         style="display:none;color:#e65;font-size:11px;margin-top:4px">
                        ชื่อ branch ควรใช้แค่ตัวอักษร ตัวเลข และ - / .
                    </div>
                </div>

                <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px">
                    <button onclick="glbCloseModal(<?php echo $bug_id ?>)"
                            class="btn btn-default btn-sm">ยกเลิก</button>
                    <button id="glb-confirm-<?php echo $bug_id ?>"
                            onclick="glbDoCreate(<?php echo $bug_id ?>)"
                            class="btn btn-primary btn-sm">สร้าง Branch</button>
                </div>

                <!-- Result section (hidden until after create) -->
                <div id="glb-result-<?php echo $bug_id ?>" style="display:none;margin-top:20px;padding-top:16px;border-top:1px solid #eee">
                    <div id="glb-result-msg-<?php echo $bug_id ?>" style="margin-bottom:12px;font-size:13px"></div>

                    <div id="glb-checkout-<?php echo $bug_id ?>" style="display:none">
                        <!-- Checkout command -->
                        <label style="display:block;font-size:12px;font-weight:600;color:#666;margin-bottom:4px">CHECKOUT COMMAND</label>
                        <div style="display:flex;gap:6px;margin-bottom:14px">
                            <code id="glb-cmd-<?php echo $bug_id ?>"
                                  style="flex:1;background:#f5f5f5;padding:8px 10px;border-radius:4px;font-size:12px;display:block;word-break:break-all"></code>
                            <button onclick="glbCopy(<?php echo $bug_id ?>)"
                                    id="glb-copy-<?php echo $bug_id ?>"
                                    class="btn btn-default btn-xs"
                                    style="white-space:nowrap;align-self:flex-start">📋 Copy</button>
                        </div>

                        <!-- Open in IDE buttons -->
                        <label style="display:block;font-size:12px;font-weight:600;color:#666;margin-bottom:8px">OPEN IN</label>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <a id="glb-vscode-<?php echo $bug_id ?>"
                               href="#"
                               onclick="glbOpenVSCode(<?php echo $bug_id ?>);return false;"
                               class="btn btn-default btn-sm"
                               style="display:flex;align-items:center;gap:6px;text-decoration:none">
                                <svg width="14" height="14" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M74.5 5.5L37.5 40.5L16.5 24.5L5.5 29.5V70.5L16.5 75.5L37.5 59.5L74.5 94.5L94.5 84.5V15.5L74.5 5.5Z" fill="#007ACC"/>
                                </svg>
                                VS Code
                            </a>
                            <a id="glb-ghdesktop-<?php echo $bug_id ?>"
                               href="#"
                               onclick="glbOpenGHDesktop(<?php echo $bug_id ?>);return false;"
                               class="btn btn-default btn-sm"
                               style="display:flex;align-items:center;gap:6px;text-decoration:none">
                                <svg width="14" height="14" viewBox="0 0 16 16" fill="#6e40c9" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/>
                                </svg>
                                GitHub Desktop
                            </a>
                            <a id="glb-webbranch-<?php echo $bug_id ?>"
                               href="#"
                               target="_blank"
                               class="btn btn-default btn-sm"
                               style="display:flex;align-items:center;gap:6px;text-decoration:none">
                                🌐 Open Branch
                            </a>
                        </div>
                        <p id="glb-ide-note-<?php echo $bug_id ?>"
                           style="display:none;margin-top:8px;font-size:11px;color:#888;margin-bottom:0"></p>
                    </div>
                </div>

            </div>
        </div>

        <script>
        (function() {
            var PROXY_URL    = <?php echo json_encode( $proxy_url ) ?>;
            var BUG_ID       = <?php echo $bug_id ?>;
            var DEFAULT_SLUG = <?php echo json_encode( $slug ) ?>;
            var manualEdit   = false;

            window.glbOpenModal = function(id) {
                if (id !== BUG_ID) return;
                manualEdit = false;
                document.getElementById('glb-modal-' + id).style.display = 'flex';
                document.getElementById('glb-result-' + id).style.display = 'none';
                document.getElementById('glb-confirm-' + id).disabled = false;
                document.getElementById('glb-confirm-' + id).textContent = 'สร้าง Branch';
                glbUpdatePreview(id);
            };

            window.glbCloseModal = function(id) {
                if (id !== BUG_ID) return;
                document.getElementById('glb-modal-' + id).style.display = 'none';
            };

            window.glbUpdatePreview = function(id) {
                if (id !== BUG_ID || manualEdit) return;
                var radios = document.querySelectorAll('input[name="glb-type-' + id + '"]');
                var type   = 'issue';
                radios.forEach(function(r) { if (r.checked) type = r.value; });
                var name = DEFAULT_SLUG ? type + '/' + BUG_ID + '-' + DEFAULT_SLUG : type + '/' + BUG_ID;
                document.getElementById('glb-branchname-' + id).value = name;
            };

            window.glbOnNameInput = function(id) {
                if (id !== BUG_ID) return;
                manualEdit = true;
                var val  = document.getElementById('glb-branchname-' + id).value;
                var warn = document.getElementById('glb-name-warn-' + id);
                warn.style.display = /[^\w\-\/\.]/.test(val) ? 'block' : 'none';
            };

            window.glbDoCreate = function(id) {
                if (id !== BUG_ID) return;
                var branchName = document.getElementById('glb-branchname-' + id).value.trim();
                if (!branchName) return;

                var btn = document.getElementById('glb-confirm-' + id);
                btn.disabled    = true;
                btn.textContent = '⏳ Creating...';

                fetch(PROXY_URL + '&branch_name=' + encodeURIComponent(branchName), { method: 'POST' })
                .then(function(res) { return res.json().then(function(d) { return { ok: res.ok, data: d }; }); })
                .then(function(r) {
                    var resultEl   = document.getElementById('glb-result-' + id);
                    var msgEl      = document.getElementById('glb-result-msg-' + id);
                    var checkoutEl = document.getElementById('glb-checkout-' + id);
                    var cmdEl      = document.getElementById('glb-cmd-' + id);
                    resultEl.style.display = 'block';

                    if (r.ok) {
                        var created = r.data.status === 'created';
                        var icon    = created ? '✅' : '⚠️';
                        var label   = created ? 'Branch สร้างเรียบร้อย' : 'Branch นี้มีอยู่แล้ว';
                        msgEl.innerHTML = icon + ' <strong>' + label + '</strong><br>'
                            + '<code style="font-size:12px">' + r.data.branch_name + '</code>';

                        checkoutEl.style.display = 'block';
                        cmdEl.textContent = 'git fetch origin && git checkout ' + r.data.branch_name;
                        btn.textContent   = created ? '✅ Done' : '✅ Already exists';

                        // เซ็ต IDE deep links
                        glbSetIDELinks(id, r.data.branch_name, r.data.repo_url, r.data.web_url, r.data.provider);
                    } else {
                        msgEl.innerHTML = '❌ <strong>Error:</strong> ' + (r.data.error || 'unknown');
                        btn.disabled    = false;
                        btn.textContent = 'สร้าง Branch';
                    }
                })
                .catch(function(err) {
                    document.getElementById('glb-result-' + id).style.display = 'block';
                    document.getElementById('glb-result-msg-' + id).innerHTML = '❌ Network error: ' + err.message;
                    btn.disabled    = false;
                    btn.textContent = 'สร้าง Branch';
                });
            };

            window.glbCopy = function(id) {
                if (id !== BUG_ID) return;
                var cmd = document.getElementById('glb-cmd-' + id).textContent;
                navigator.clipboard.writeText(cmd).then(function() {
                    var btn = document.getElementById('glb-copy-' + id);
                    btn.textContent = '✅ Copied!';
                    setTimeout(function() { btn.textContent = '📋 Copy'; }, 2000);
                });
            };

            // เซ็ต deep link ให้ปุ่ม IDE หลัง branch สร้างสำเร็จ
            window.glbSetIDELinks = function(id, branchName, repoUrl, webUrl, provider) {
                if (id !== BUG_ID) return;

                // ─── VS Code ───────────────────────────────────────────
                // vscode://vscode.git/fetch เปิด VS Code source control panel
                // แล้วแสดง note ให้ checkout ด้วย command ข้างบน
                var vsBtn = document.getElementById('glb-vscode-' + id);
                var note  = document.getElementById('glb-ide-note-' + id);
                vsBtn.href = 'vscode://vscode.git/fetch';
                vsBtn.removeAttribute('onclick');
                vsBtn.onclick = function() {
                    note.style.display = 'block';
                    note.textContent   = '💡 VS Code เปิดแล้ว — รัน "Git: Checkout to..." หรือใช้ command ด้านบนใน terminal';
                    return true; // เปิด deep link ต่อ
                };

                // ─── GitHub Desktop ────────────────────────────────────
                // x-github-client://openRepo?repoUrl=<https_url>
                // เปิด GitHub Desktop และไปที่ repo นั้น
                var ghBtn = document.getElementById('glb-ghdesktop-' + id);
                if (repoUrl) {
                    ghBtn.href = 'x-github-client://openRepo?repoUrl=' + encodeURIComponent(repoUrl);
                    ghBtn.removeAttribute('onclick');
                    ghBtn.onclick = function() {
                        note.style.display = 'block';
                        note.textContent   = '💡 GitHub Desktop เปิดแล้ว — เลือก branch "' + branchName + '" ในเมนู Branch';
                        return true;
                    };
                } else {
                    ghBtn.style.display = 'none';
                }

                // ─── Open Branch in browser ────────────────────────────
                var webBtn = document.getElementById('glb-webbranch-' + id);
                if (webUrl) {
                    webBtn.href = webUrl;
                } else {
                    webBtn.style.display = 'none';
                }
            };

            window.glbOpenVSCode    = function() {}; // placeholder
            window.glbOpenGHDesktop = function() {}; // placeholder

            // Close modal เมื่อ click outside
            document.getElementById('glb-modal-' + BUG_ID).addEventListener('click', function(e) {
                if (e.target === this) glbCloseModal(BUG_ID);
            });
        })();
        </script>
        <?php
    }

    // Slugify summary สำหรับ pre-fill branch name
    private function slugify( $s ) {
        $s = strtolower( $s );
        $s = preg_replace( '/[^a-z0-9]+/', '-', $s );
        $s = trim( $s, '-' );
        if ( strlen( $s ) > 50 ) {
            $s = substr( $s, 0, 50 );
            $s = rtrim( $s, '-' );
        }
        return $s;
    }

    function config_page() {
        return 'config';
    }
}
