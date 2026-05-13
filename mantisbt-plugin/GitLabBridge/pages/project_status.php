<?php
/**
 * GitLabBridge — Project status check proxy
 * GET: plugin.php?page=GitLabBridge/project_status&bug_id=<id>
 * ตรวจว่า mantis project ของ bug นี้ถูก map ใน git-bridge config หรือไม่
 */
access_ensure_bug_level( VIEWER, gpc_get_int( 'bug_id' ) );

$bug_id     = gpc_get_int( 'bug_id', 0 );
$bridge_url = plugin_config_get( 'bridge_url' );
$api_token  = plugin_config_get( 'api_token' );

header( 'Content-Type: application/json' );

if ( $bug_id <= 0 ) {
    http_response_code( 400 );
    echo json_encode( ['error' => 'missing bug_id'] );
    exit;
}

if ( empty( $bridge_url ) || empty( $api_token ) ) {
    http_response_code( 503 );
    echo json_encode( ['configured' => false, 'error' => 'plugin not configured (bridge_url / api_token missing)'] );
    exit;
}

$bug        = bug_get( $bug_id );
$project_id = (int) $bug->project_id;

$url = rtrim( $bridge_url, '/' ) . '/project-status?mantis_project_id=' . $project_id;

$ctx = stream_context_create([
    'http' => [
        'method'        => 'GET',
        'header'        => 'X-Api-Token: ' . $api_token,
        'timeout'       => 5,
        'ignore_errors' => true,
    ],
]);

$result = @file_get_contents( $url, false, $ctx );

if ( $result === false ) {
    http_response_code( 502 );
    echo json_encode( ['configured' => false, 'error' => 'bridge service unreachable'] );
    exit;
}

$status_line = $http_response_header[0] ?? '';
if ( preg_match( '/HTTP\/\d\.\d\s+(\d+)/', $status_line, $m ) ) {
    http_response_code( (int) $m[1] );
}

echo $result;
