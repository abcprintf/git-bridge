<?php
/**
 * GitLabBridge — Config POST handler
 * รับ form จาก config_page.php แล้ว save + redirect กลับ
 */
form_security_validate( 'config' );

plugin_config_set( 'bridge_url', rtrim( gpc_get_string( 'bridge_url', '' ), '/' ) );
plugin_config_set( 'api_token',  gpc_get_string( 'api_token', '' ) );

form_security_purge( 'config' );

$t_redirect_url = plugin_page( 'config_page', true );
layout_page_header( null, $t_redirect_url );
layout_page_begin();
html_operation_successful( $t_redirect_url, plugin_lang_get( 'saved' ) );
layout_page_end();
