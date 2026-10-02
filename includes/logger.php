<?php
/**
 * Logger Module
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wptg_free_cn_log_write( $type, $text ) {
    $dir = WP_CONTENT_DIR . '/wptg-logs';
    if ( ! file_exists( $dir ) ) {
        wp_mkdir_p( $dir );
        file_put_contents( $dir . '/.htaccess', 'Deny from all' );
        file_put_contents( $dir . '/index.php', '<?php // Silence is golden.' );
    }

    $hash = wp_hash( 'log' );
    $file = $dir . "/wptg-free-cn-{$type}-{$hash}.log";
    
    // Redact bot tokens
    $bot_token = wptg_free_cn_get_option( 'bot_token' );
    if ( ! empty( $bot_token ) ) {
        $text = str_replace( $bot_token, '**********', $text );
    }
    
    $timestamp = current_time( 'mysql' );
    $log_entry = "[{$timestamp}] {$text}\n";
    
    if ( file_exists( $file ) && filesize( $file ) > 1048576 ) { // 1MB
        $lines = file( $file );
        $lines = array_slice( $lines, -500 ); // Keep last 500 lines
        file_put_contents( $file, implode( '', $lines ) );
    }
    
    error_log( $log_entry, 3, $file );
}

function wptg_free_cn_log_get_url( $type ) {
    $hash = wp_hash( 'log' );
    return add_query_arg( array(
        'action' => 'wptg_free_cn_view_log',
        'type'   => $type,
        'hash'   => $hash,
    ), admin_url( 'admin-ajax.php' ) );
}

add_action( 'wp_ajax_wptg_free_cn_view_log', 'wptg_free_cn_log_view' );
function wptg_free_cn_log_view() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( '未授权访问：您没有查看日志的权限。', '无权访问', array( 'response' => 403 ) );
    }
    
    $type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';
    $hash = isset( $_GET['hash'] ) ? sanitize_text_field( $_GET['hash'] ) : '';
    
    if ( $hash !== wp_hash( 'log' ) ) {
        wp_die( '安全校验失败：无效的安全哈希参数。', '校验失败', array( 'response' => 403 ) );
    }
    
    $file = WP_CONTENT_DIR . "/wptg-logs/wptg-free-cn-{$type}-{$hash}.log";
    if ( file_exists( $file ) ) {
        header( 'Content-Type: text/plain' );
        readfile( $file );
        exit;
    } else {
        wp_die( '未找到指定的日志文件。', '文件不存在', array( 'response' => 404 ) );
    }
}

function wptg_free_cn_log_hookup( $active_logs = array() ) {
    if ( in_array( 'bot_api', $active_logs ) ) {
        add_action( 'wptelegram_bot_api_debug', function( $message ) {
            wptg_free_cn_log_write( 'bot_api', print_r( $message, true ) );
        } );
    }
    
    if ( in_array( 'p2tg', $active_logs ) ) {
        $p2tg_hooks = array(
            'wptg_free_cn_p2tg_instant_post',
            'wptg_free_cn_p2tg_delayed_post',
        );
        foreach ( $p2tg_hooks as $hook ) {
            add_action( $hook, function( $arg ) use ( $hook ) {
                wptg_free_cn_log_write( 'p2tg', "Action fired: {$hook} with arg " . print_r( $arg, true ) );
            }, 999 );
        }
    }
}
