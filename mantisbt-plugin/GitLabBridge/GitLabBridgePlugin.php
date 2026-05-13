<?php
/**
 * GitLabBridge — MantisBT Plugin
 * เพิ่มปุ่ม "Create Branch" บน issue detail page
 * Request ผ่าน server-side proxy — token ไม่โผล่ใน browser
 */
class GitLabBridgePlugin extends MantisPlugin {

    function register() {
        $this->name        = 'GitLab Bridge';
        $this->description = 'Create GitLab/GitHub branch directly from MantisBT issue';
        $this->version     = '1.1.0';
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

        if ( empty( $bridge_url ) || empty( $api_token ) ) {
            return;
        }

        $bug_id    = (int) $p_bug_id;
        // ชี้ไป proxy page ของ plugin เอง — ไม่มี token ใน browser
        $proxy_url = plugin_page( 'create_branch_proxy', true ) . '&bug_id=' . $bug_id;
        ?>
        <tr>
            <td class="category"><?php echo plugin_lang_get( 'title' ) ?></td>
            <td>
                <button
                    id="glb-btn-<?php echo $bug_id ?>"
                    class="btn btn-sm btn-primary"
                    onclick="glbCreateBranch(<?php echo $bug_id ?>)"
                    style="margin-right:8px">
                    🔀 <?php echo plugin_lang_get( 'create_branch' ) ?>
                </button>
                <span id="glb-result-<?php echo $bug_id ?>" style="font-family:monospace;font-size:12px"></span>
            </td>
        </tr>

        <script>
        (function() {
            var PROXY_URL = <?php echo json_encode( $proxy_url ) ?>; // ไม่มี token
            var ISSUE_ID  = <?php echo $bug_id ?>;

            window.glbCreateBranch = function(issueId) {
                if (issueId !== ISSUE_ID) return;

                var btn    = document.getElementById('glb-btn-' + issueId);
                var result = document.getElementById('glb-result-' + issueId);

                btn.disabled    = true;
                btn.textContent = '⏳ Creating...';
                result.innerHTML = '';

                fetch(PROXY_URL, { method: 'POST' })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.status === 'created' || data.status === 'already_exists') {
                        var label = data.status === 'created' ? '✅ Created: ' : '⚠️ Already exists: ';
                        var link  = data.web_url
                            ? ' <a href="' + data.web_url + '" target="_blank">↗</a>'
                            : '';
                        result.innerHTML = label + '<code>' + data.branch_name + '</code>' + link;
                        btn.textContent  = data.status === 'created' ? '✅ Done' : '🔀 Create Branch';
                        btn.disabled     = data.status === 'created';
                    } else {
                        result.innerHTML = '❌ ' + (data.error || 'unknown error');
                        btn.disabled    = false;
                        btn.textContent = '🔀 Create Branch';
                    }
                })
                .catch(function(err) {
                    result.innerHTML = '❌ Network error: ' + err.message;
                    btn.disabled    = false;
                    btn.textContent = '🔀 Create Branch';
                });
            };
        })();
        </script>
        <?php
    }

    function config_page() {
        return 'config_page.php';
    }
}
