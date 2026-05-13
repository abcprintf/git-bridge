<?php
/**
 * GitLabBridge — Admin Config Page
 * MantisBT Admin → Manage → Plugins → GitLab Bridge → Configure
 */
access_ensure_global_level( ADMINISTRATOR );

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
    $bridge_url = gpc_get_string( 'bridge_url', '' );
    $api_token  = gpc_get_string( 'api_token', '' );

    if ( !empty( $bridge_url ) && !filter_var( $bridge_url, FILTER_VALIDATE_URL ) ) {
        $error = 'Invalid URL format';
    } else {
        plugin_config_set( 'bridge_url', rtrim( $bridge_url, '/' ) );
        plugin_config_set( 'api_token',  $api_token );
        $saved = true;
    }
}

$current_url   = plugin_config_get( 'bridge_url' );
$current_token = plugin_config_get( 'api_token' );

html_page_top( plugin_lang_get( 'config_title' ) );
?>

<div class="col-md-12 col-xs-12">
<div class="widget-box widget-color-blue2">
<div class="widget-header">
    <h4 class="widget-title"><?php echo plugin_lang_get( 'config_title' ) ?></h4>
</div>
<div class="widget-body">
<div class="widget-main no-padding">

<?php if ( isset( $saved ) ): ?>
    <div class="alert alert-success"><?php echo plugin_lang_get( 'saved' ) ?></div>
<?php endif ?>
<?php if ( isset( $error ) ): ?>
    <div class="alert alert-danger"><?php echo string_html_specialchars( $error ) ?></div>
<?php endif ?>

<form method="post" action="">
<?php echo form_security_field( 'plugin_GitLabBridge_config' ) ?>

<table class="table table-bordered table-condensed">
    <tr>
        <th class="category" style="width:220px"><?php echo plugin_lang_get( 'bridge_url' ) ?></th>
        <td>
            <input type="url" name="bridge_url" class="form-control"
                   value="<?php echo string_attribute( $current_url ) ?>"
                   placeholder="https://bridge.igenco.dev" style="max-width:400px">
            <small class="text-muted">URL ของ git-bridge service (ไม่มี trailing slash)</small>
        </td>
    </tr>
    <tr>
        <th class="category"><?php echo plugin_lang_get( 'api_token' ) ?></th>
        <td>
            <input type="password" name="api_token" class="form-control"
                   value="<?php echo string_attribute( $current_token ) ?>"
                   placeholder="your-api-token" style="max-width:400px"
                   autocomplete="new-password">
            <small class="text-muted">ต้องตรงกับ <code>API_TOKEN</code> ใน .env ของ bridge service</small>
        </td>
    </tr>
</table>

<div class="widget-toolbox padding-8">
    <button type="submit" class="btn btn-primary"><?php echo plugin_lang_get( 'save' ) ?></button>
</div>

</form>
</div>
</div>
</div>
</div>

<?php html_page_bottom() ?>
