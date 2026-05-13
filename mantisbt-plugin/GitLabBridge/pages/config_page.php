<?php
/**
 * GitLabBridge — Config Page (display form)
 * MantisBT 2.26+ pattern: layout_page_header / layout_page_begin / layout_page_end
 */
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

$t_bridge_url = plugin_config_get( 'bridge_url', '' );
$t_api_token  = plugin_config_get( 'api_token',  '' );

layout_page_header( plugin_lang_get( 'config_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="widget-box widget-color-blue2">
<div class="widget-header">
    <h4 class="widget-title"><?php echo plugin_lang_get( 'config_title' ) ?></h4>
</div>
<div class="widget-body">
<div class="widget-main no-padding">

<form method="post" action="<?php echo plugin_page( 'config' ) ?>">
<?php echo form_security_field( 'config' ) ?>

<table class="table table-bordered table-condensed table-striped">
    <tr>
        <td class="category" width="30%"><?php echo plugin_lang_get( 'bridge_url' ) ?></td>
        <td>
            <input type="text" name="bridge_url" class="input-xxlarge"
                   value="<?php echo string_attribute( $t_bridge_url ) ?>"
                   placeholder="https://bridge.domain">
            <br><small>URL ของ git-bridge service (ไม่มี trailing slash)</small>
        </td>
    </tr>
    <tr>
        <td class="category"><?php echo plugin_lang_get( 'api_token' ) ?></td>
        <td>
            <input type="password" name="api_token" class="input-xxlarge"
                   value="<?php echo string_attribute( $t_api_token ) ?>"
                   placeholder="your-api-token"
                   autocomplete="new-password">
            <br><small>ต้องตรงกับ <code>API_TOKEN</code> ใน .env ของ bridge service</small>
        </td>
    </tr>
</table>

<div class="widget-toolbox padding-8 clearfix">
    <input type="submit" class="btn btn-primary btn-sm btn-white btn-round"
           value="<?php echo plugin_lang_get( 'save' ) ?>">
</div>

</form>
</div>
</div>
</div>
</div>

<?php layout_page_end() ?>
