<?php
/**
 * GitLabBridge — MantisBT Plugin
 * เพิ่มปุ่ม "Create Branch" บน issue detail page
 * เรียก git-bridge service ผ่าน HTTP
 */
class GitLabBridgePlugin extends MantisPlugin {

    function register() {
        $this->name        = 'GitLab Bridge';
        $this->description = 'Create GitLab/GitHub branch directly from MantisBT issue';
        $this->version     = '1.0.0';
        $this->requires    = ['MantisCore' => '2.0.0'];
        $this->author      = 'IGENCO';
    }

    function config() {
        return [
            'bridge_url' => '',   // https://bridge.igenco.dev
            'api_token'  => '',   // shared token ตรงกับ API_TOKEN ใน .env
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

        $bug     = bug_get( $p_bug_id );
        $summary = string_attribute( $bug->summary );
        $bug_id  = (int) $p_bug_id;

        $js_bridge_url = json_encode( rtrim( $bridge_url, '/' ) . '/create-branch' );
        $js_token      = json_encode( $api_token );
        $js_issue_id   = $bug_id;
        $js_summary    = json_encode( $summary );

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
            var BRIDGE_URL  = <?php echo $js_bridge_url ?>;
            var API_TOKEN   = <?php echo $js_token ?>;
            var ISSUE_ID    = <?php echo $js_issue_id ?>;
            var SUMMARY     = <?php echo $js_summary ?>;

            window.glbCreateBranch = function(issueId) {
                if (issueId !== ISSUE_ID) return;

                var btn    = document.getElementById('glb-btn-' + issueId);
                var result = document.getElementById('glb-result-' + issueId);

                btn.disabled    = true;
                btn.textContent = '⏳ Creating...';
                result.innerHTML = '';

                fetch(BRIDGE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Api-Token': API_TOKEN,
                    },
                    body: JSON.stringify({ issue_id: ISSUE_ID, summary: SUMMARY }),
                })
                .then(function(res) {
                    return res.json().then(function(data) {
                        return { ok: res.ok, status: res.status, data: data };
                    });
                })
                .then(function(r) {
                    if (r.ok) {
                        var label  = r.data.status === 'already_exists' ? '⚠️ Already exists: ' : '✅ Created: ';
                        var webUrl = r.data.web_url ? ' <a href="' + r.data.web_url + '" target="_blank">↗</a>' : '';
                        result.innerHTML = label + '<code>' + r.data.branch_name + '</code>' + webUrl;
                        btn.textContent  = r.data.status === 'already_exists' ? '🔀 Create Branch' : '✅ Done';
                        btn.disabled     = r.data.status !== 'already_exists';
                    } else {
                        result.innerHTML = '❌ Error: ' + (r.data.error || 'unknown');
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
