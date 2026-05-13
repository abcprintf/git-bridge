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
        $this->page        = 'config_page';
    }

    function config() {
        return [
            'bridge_url' => '',
            'api_token'  => '',
        ];
    }

    function hooks() {
        return [
            'EVENT_VIEW_BUG_EXTRA' => 'render_create_branch_row',
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
                <button id="glb-open-<?php echo $bug_id ?>" class="btn btn-sm btn-primary">
                    🔀 <?php echo plugin_lang_get( 'create_branch' ) ?>
                </button>
            </td>
        </tr>

        <!-- Modal — ไม่มี inline event handlers (CSP compliant) -->
        <div id="glb-modal-<?php echo $bug_id ?>"
             style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
            <div style="background:#fff;border-radius:8px;padding:28px;width:520px;max-width:95vw;box-shadow:0 8px 32px rgba(0,0,0,.2)">

                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
                    <h3 style="margin:0;font-size:16px;font-weight:600">🔀 Create Git Branch</h3>
                    <button id="glb-close-<?php echo $bug_id ?>"
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
                                   <?php echo $type === 'issue' ? 'checked' : '' ?>>
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
                           style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;font-family:monospace;font-size:13px;box-sizing:border-box">
                    <div id="glb-name-warn-<?php echo $bug_id ?>"
                         style="display:none;color:#e65;font-size:11px;margin-top:4px">
                        ชื่อ branch ควรใช้แค่ตัวอักษร ตัวเลข และ - / .
                    </div>
                </div>

                <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px">
                    <button id="glb-cancel-<?php echo $bug_id ?>" class="btn btn-default btn-sm">ยกเลิก</button>
                    <button id="glb-confirm-<?php echo $bug_id ?>" class="btn btn-primary btn-sm">สร้าง Branch</button>
                </div>

                <!-- Result section -->
                <div id="glb-result-<?php echo $bug_id ?>" style="display:none;margin-top:20px;padding-top:16px;border-top:1px solid #eee">
                    <div id="glb-result-msg-<?php echo $bug_id ?>" style="margin-bottom:12px;font-size:13px"></div>

                    <div id="glb-checkout-<?php echo $bug_id ?>" style="display:none">
                        <label style="display:block;font-size:12px;font-weight:600;color:#666;margin-bottom:4px">CHECKOUT COMMAND</label>
                        <div style="display:flex;gap:6px;margin-bottom:14px">
                            <code id="glb-cmd-<?php echo $bug_id ?>"
                                  style="flex:1;background:#f5f5f5;padding:8px 10px;border-radius:4px;font-size:12px;display:block;word-break:break-all"></code>
                            <button id="glb-copy-<?php echo $bug_id ?>"
                                    class="btn btn-default btn-xs"
                                    style="white-space:nowrap;align-self:flex-start">📋 Copy</button>
                        </div>

                        <label style="display:block;font-size:12px;font-weight:600;color:#666;margin-bottom:8px">OPEN IN</label>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <a id="glb-vscode-<?php echo $bug_id ?>" href="#"
                               class="btn btn-default btn-sm"
                               style="display:flex;align-items:center;gap:6px;text-decoration:none">
                                <svg width="14" height="14" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M74.5 5.5L37.5 40.5L16.5 24.5L5.5 29.5V70.5L16.5 75.5L37.5 59.5L74.5 94.5L94.5 84.5V15.5L74.5 5.5Z" fill="#007ACC"/>
                                </svg>
                                VS Code
                            </a>
                            <a id="glb-ghdesktop-<?php echo $bug_id ?>" href="#"
                               class="btn btn-default btn-sm"
                               style="display:flex;align-items:center;gap:6px;text-decoration:none">
                                <svg width="14" height="14" viewBox="0 0 16 16" fill="#6e40c9" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/>
                                </svg>
                                GitHub Desktop
                            </a>
                            <a id="glb-webbranch-<?php echo $bug_id ?>" href="#" target="_blank"
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

            var elModal    = document.getElementById('glb-modal-'      + BUG_ID);
            var elName     = document.getElementById('glb-branchname-' + BUG_ID);
            var elWarn     = document.getElementById('glb-name-warn-'  + BUG_ID);
            var elConfirm  = document.getElementById('glb-confirm-'    + BUG_ID);
            var elResult   = document.getElementById('glb-result-'     + BUG_ID);
            var elMsg      = document.getElementById('glb-result-msg-' + BUG_ID);
            var elCheckout = document.getElementById('glb-checkout-'   + BUG_ID);
            var elCmd      = document.getElementById('glb-cmd-'        + BUG_ID);
            var elCopy     = document.getElementById('glb-copy-'       + BUG_ID);
            var elVSCode   = document.getElementById('glb-vscode-'     + BUG_ID);
            var elGHD      = document.getElementById('glb-ghdesktop-'  + BUG_ID);
            var elWeb      = document.getElementById('glb-webbranch-'  + BUG_ID);
            var elNote     = document.getElementById('glb-ide-note-'   + BUG_ID);

            function openModal() {
                manualEdit = false;
                elModal.style.display = 'flex';
                elResult.style.display = 'none';
                elConfirm.disabled = false;
                elConfirm.textContent = 'สร้าง Branch';
                updatePreview();
            }

            function closeModal() {
                elModal.style.display = 'none';
            }

            function updatePreview() {
                if (manualEdit) return;
                var radios = document.querySelectorAll('input[name="glb-type-' + BUG_ID + '"]');
                var type = 'issue';
                radios.forEach(function(r) { if (r.checked) type = r.value; });
                elName.value = DEFAULT_SLUG
                    ? type + '/' + BUG_ID + '-' + DEFAULT_SLUG
                    : type + '/' + BUG_ID;
            }

            function doCreate() {
                var branchName = elName.value.trim();
                if (!branchName) return;
                elConfirm.disabled = true;
                elConfirm.textContent = '⏳ Creating...';

                fetch(PROXY_URL + '&branch_name=' + encodeURIComponent(branchName), { method: 'POST' })
                .then(function(res) { return res.json().then(function(d) { return { ok: res.ok, data: d }; }); })
                .then(function(r) {
                    elResult.style.display = 'block';
                    if (r.ok) {
                        var created = r.data.status === 'created';
                        elMsg.innerHTML = (created ? '✅' : '⚠️')
                            + ' <strong>' + (created ? 'Branch สร้างเรียบร้อย' : 'Branch นี้มีอยู่แล้ว') + '</strong><br>'
                            + '<code style="font-size:12px">' + r.data.branch_name + '</code>';
                        elCheckout.style.display = 'block';
                        elCmd.textContent = 'git fetch origin && git checkout ' + r.data.branch_name;
                        elConfirm.textContent = created ? '✅ Done' : '✅ Already exists';
                        setIDELinks(r.data.branch_name, r.data.repo_url, r.data.web_url);
                    } else {
                        elMsg.innerHTML = '❌ <strong>Error:</strong> ' + (r.data.error || 'unknown');
                        elConfirm.disabled = false;
                        elConfirm.textContent = 'สร้าง Branch';
                    }
                })
                .catch(function(err) {
                    elResult.style.display = 'block';
                    elMsg.innerHTML = '❌ Network error: ' + err.message;
                    elConfirm.disabled = false;
                    elConfirm.textContent = 'สร้าง Branch';
                });
            }

            function setIDELinks(branchName, repoUrl, webUrl) {
                elVSCode.href = 'vscode://vscode.git/fetch';
                elVSCode.addEventListener('click', function() {
                    elNote.style.display = 'block';
                    elNote.textContent = '💡 VS Code เปิดแล้ว — รัน "Git: Checkout to..." หรือใช้ command ด้านบนใน terminal';
                });

                if (repoUrl) {
                    elGHD.href = 'x-github-client://openRepo?repoUrl=' + encodeURIComponent(repoUrl);
                    elGHD.addEventListener('click', function() {
                        elNote.style.display = 'block';
                        elNote.textContent = '💡 GitHub Desktop เปิดแล้ว — เลือก branch "' + branchName + '" ในเมนู Branch';
                    });
                } else {
                    elGHD.style.display = 'none';
                }

                if (webUrl) {
                    elWeb.href = webUrl;
                } else {
                    elWeb.style.display = 'none';
                }
            }

            // ─── Event listeners (CSP-safe, ไม่มี inline handlers) ───
            document.getElementById('glb-open-'   + BUG_ID).addEventListener('click', openModal);
            document.getElementById('glb-close-'  + BUG_ID).addEventListener('click', closeModal);
            document.getElementById('glb-cancel-' + BUG_ID).addEventListener('click', closeModal);
            elConfirm.addEventListener('click', doCreate);

            elName.addEventListener('input', function() {
                manualEdit = true;
                elWarn.style.display = /[^\w\-\/\.]/.test(elName.value) ? 'block' : 'none';
            });

            document.querySelectorAll('input[name="glb-type-' + BUG_ID + '"]').forEach(function(r) {
                r.addEventListener('change', updatePreview);
            });

            elCopy.addEventListener('click', function() {
                navigator.clipboard.writeText(elCmd.textContent).then(function() {
                    elCopy.textContent = '✅ Copied!';
                    setTimeout(function() { elCopy.textContent = '📋 Copy'; }, 2000);
                });
            });

            elModal.addEventListener('click', function(e) {
                if (e.target === elModal) closeModal();
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

    # config_page() ไม่จำเป็น — ใช้ $this->page = 'config_page' ใน register() แทน
}
