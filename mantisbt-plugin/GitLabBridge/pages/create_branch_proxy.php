<?php
/**
 * GitLabBridge — Server-side proxy
 * Browser เรียก endpoint นี้ → PHP ส่ง request ไป git-bridge พร้อม token
 * Token ไม่เคยโผล่ใน browser
 */

// MantisBT จัดการ auth ก่อน — ถ้าไม่ login จะ redirect อัตโนมัติ
$bug_id = gpc_get_int( 'bug_id', 0 );
if ( $bug_id <= 0 ) {
    http_response_code( 400 );
    echo json_encode( ['error' => 'missing bug_id'] );
    exit;
}

// ตรวจสิทธิ์: ต้องมีสิทธิ์อ่าน issue นั้นอย่างน้อย
access_ensure_bug_level( VIEWER, $bug_id );

$bug        = bug_get( $bug_id );
$bridge_url = plugin_config_get( 'bridge_url' );
$api_token  = plugin_config_get( 'api_token' );

header( 'Content-Type: application/json' );

if ( empty( $bridge_url ) || empty( $api_token ) ) {
    http_response_code( 503 );
    echo json_encode( ['error' => 'plugin not configured'] );
    exit;
}

$payload = json_encode([
    'issue_id'   => (int) $bug_id,
    'project_id' => (int) $bug->project_id,
    'summary'    => $bug->summary,
]);

// Server-side HTTP call — token อยู่ใน header ฝั่ง server เท่านั้น
$ctx = stream_context_create([
    'http' => [
        'method'        => 'POST',
        'header'        => implode( "\r\n", [
            'Content-Type: application/json',
            'X-Api-Token: ' . $api_token,
        ]),
        'content'       => $payload,
        'timeout'       => 10,
        'ignore_errors' => true, // ให้อ่าน response body แม้ status 4xx/5xx
    ],
]);

$result = @file_get_contents( rtrim( $bridge_url, '/' ) . '/create-branch', false, $ctx );

if ( $result === false ) {
    http_response_code( 502 );
    echo json_encode( ['error' => 'bridge service unreachable'] );
    exit;
}

// Forward HTTP status code จาก bridge service
$status_line = $http_response_header[0] ?? '';
if ( preg_match( '/HTTP\/\d\.\d\s+(\d+)/', $status_line, $m ) ) {
    http_response_code( (int) $m[1] );
}

echo $result;
