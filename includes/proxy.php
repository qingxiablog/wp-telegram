<?php
/**
 * Proxy Module
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wptelegram_bot_api_remote_request_init', 'wptg_free_cn_proxy_configure' );
add_action( 'wptelegram_bot_api_remote_request_finish', 'wptg_free_cn_proxy_remove' );

function wptg_free_cn_proxy_configure() {
    $proxy_options = wptg_free_cn_get_option( 'proxy', array() );
    if ( empty( $proxy_options['active'] ) ) {
        wptg_free_cn_proxy_remove();
        return;
    }
    if ( empty( $proxy_options['proxy_method'] ) ) {
        return;
    }
    wptg_free_cn_proxy_setup( $proxy_options );
}

function wptg_free_cn_proxy_setup( $options ) {
    $method = isset( $options['proxy_method'] ) ? $options['proxy_method'] : '';
    
    if ( $method === 'cf_worker' && ! empty( $options['cf_worker_url'] ) ) {
        add_filter( 'wptelegram_bot_api_base_url', 'wptg_free_cn_proxy_cf_worker_url' );
    } elseif ( $method === 'google_script' && ! empty( $options['google_script_url'] ) ) {
        add_filter( 'wptelegram_bot_api_remote_post_args', 'wptg_free_cn_proxy_gs_args', 30, 2 );
        add_filter( 'wptelegram_bot_api_request_url', 'wptg_free_cn_proxy_gs_url', 30, 1 );
    } elseif ( $method === 'php_proxy' ) {
        $host = ! empty( $options['proxy_host'] ) ? $options['proxy_host'] : ( ! empty( $options['host'] ) ? $options['host'] : '' );
        if ( ! empty( $host ) ) {
            add_action( 'http_api_curl', 'wptg_free_cn_proxy_php_curl', 11, 3 );
        }
    }
}

function wptg_free_cn_proxy_remove() {
    remove_filter( 'wptelegram_bot_api_base_url', 'wptg_free_cn_proxy_cf_worker_url' );
    remove_filter( 'wptelegram_bot_api_remote_post_args', 'wptg_free_cn_proxy_gs_args', 30 );
    remove_filter( 'wptelegram_bot_api_request_url', 'wptg_free_cn_proxy_gs_url', 30 );
    remove_action( 'http_api_curl', 'wptg_free_cn_proxy_php_curl', 11 );
}

function wptg_free_cn_proxy_cf_worker_url( $url ) {
    $options = wptg_free_cn_get_option( 'proxy', array() );
    $cf_url  = isset( $options['cf_worker_url'] ) ? $options['cf_worker_url'] : '';
    return untrailingslashit( $cf_url ) . '/bot';
}

function wptg_free_cn_proxy_gs_args( $args, $request ) {
    $bot_token = is_object( $request ) && method_exists( $request, 'get_bot_token' ) ? $request->get_bot_token() : '';
    $method    = is_object( $request ) && method_exists( $request, 'get_api_method' ) ? $request->get_api_method() : '';

    $args['body'] = array(
        'bot_token' => $bot_token,
        'method'    => $method,
        'args'      => isset( $args['body'] ) ? wp_json_encode( $args['body'] ) : '',
    );
    $args['method'] = 'GET';
    return $args;
}

function wptg_free_cn_proxy_gs_url( $url ) {
    $options = wptg_free_cn_get_option( 'proxy', array() );
    return isset( $options['google_script_url'] ) ? $options['google_script_url'] : $url;
}

function wptg_free_cn_proxy_php_curl( &$handle, $r, $url ) {
    $bot_api_url = 'https://api.telegram.org/bot';
    $user_link   = 'https://t.me/';
    $pattern     = '/^(?:' . preg_quote( $bot_api_url, '/' ) . '|' . preg_quote( $user_link, '/' ) . ')/i';

    $to_telegram   = (bool) preg_match( $pattern, $url );
    $by_wptelegram = ! empty( $r['headers']['wptelegram_bot'] );

    if ( ! $to_telegram || ! $by_wptelegram ) {
        return;
    }

    $options = wptg_free_cn_get_option( 'proxy', array() );
    $host = ! empty( $options['proxy_host'] ) ? $options['proxy_host'] : ( ! empty( $options['host'] ) ? $options['host'] : '' );
    if ( empty( $host ) ) {
        return;
    }

    $port = ! empty( $options['proxy_port'] ) ? $options['proxy_port'] : ( ! empty( $options['port'] ) ? $options['port'] : '' );
    $type = ! empty( $options['proxy_type'] ) ? $options['proxy_type'] : ( ! empty( $options['type'] ) ? $options['type'] : 'CURLPROXY_HTTP' );
    $user = ! empty( $options['proxy_username'] ) ? $options['proxy_username'] : ( ! empty( $options['username'] ) ? $options['username'] : '' );
    $pass = ! empty( $options['proxy_password'] ) ? $options['proxy_password'] : ( ! empty( $options['password'] ) ? $options['password'] : '' );

    curl_setopt( $handle, CURLOPT_PROXY, $host );

    if ( ! empty( $port ) ) {
        curl_setopt( $handle, CURLOPT_PROXYPORT, intval( $port ) );
    }

    if ( ! empty( $type ) && defined( $type ) ) {
        curl_setopt( $handle, CURLOPT_PROXYTYPE, constant( $type ) );
    }

    if ( ! empty( $user ) && ! empty( $pass ) ) {
        curl_setopt( $handle, CURLOPT_PROXYAUTH, CURLAUTH_ANY );
        curl_setopt( $handle, CURLOPT_PROXYUSERPWD, $user . ':' . $pass );
    }
}
