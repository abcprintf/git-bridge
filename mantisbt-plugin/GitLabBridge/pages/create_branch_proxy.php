<?php
/**
 * GitLabBridge — Server-side proxy
 * รับ branch_name จาก modal (user อาจแก้ชื่อเอง)
 */
access_ensure_bug_level( VIEWER, gpc_get_int( 'bug_id' ) );

$bug_id      = gpc_get_int( 'bug_id', 0 );
$branch_name = gpc_get_string( 'branch_name', '' ); // ถ้าส่งมา ใช้เลย, ถ้าไม่มี bridge จะ generate เอง

if ( $bug_id <= 0 ) {
    http_response_code( 400 );
    echo json_encode( ['error' => 'missing bug_id'] );
    exit;
}

$bug        = bug_get( $bug_id );
$bridge_url = plugin_config_get( 'bridge_url' );
$api_token  = plugin_config_get( 'api_token' );

header( 'Content-Type: application/json' );

if ( empty( $bridge_url ) || empty( $api_token ) ) {
    http_response_code( 503 );
    echo json_encode( ['error' => 'plugin not configured'] );
    exit;
}

// Validate branch_name ถ้ามีการส่งมา
if ( $branch_name !== '' && !preg_match( '/^[\w\-\/\.]+$/', $branch_name ) ) {
    http_response_code( 400 );
    echo json_encode( ['error' => 'invalid branch name'] );
    exit;
}

$payload = json_encode( array_filter([
    'issue_id'    => (int) $bug_id,
    'project_id'  => (int) $bug->project_id,
    'summary'     => $bug->summary,
    'branch_name' => $branch_name ?: null, // null = ให้ bridge generate เอง
]) );

$ctx = stream_context_create([
    'http' => [
        'method'        => 'POST',
        'header'        => implode( "\r\n", [
            'Content-Type: application/json',
            'X-Api-Token: ' . $api_token,
        ]),
        'content'       => $payload,
        'timeout'       => 10,
        'ignore_errors' => true,
    ],
]);

$result = @file_get_contents( rtrim( $bridge_url, '/' ) . '/create-branch', false, $ctx );

if ( $result === false ) {
    http_response_code( 502 );
    echo json_encode( ['error' => 'bridge service unreachable'] );
    exit;
}

$status_line = $http_response_header[0] ?? '';
if ( preg_match( '/HTTP\/\d\.\d\s+(\d+)/', $status_line, $m ) ) {
    http_response_code( (int) $m[1] );
}

echo $result;
