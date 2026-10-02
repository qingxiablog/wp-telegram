<?php
/**
 * 默认值 (Default Values)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 获取插件的默认设置值
 *
 * @return array
 */
function wptg_free_cn_get_default_values() {
    return [
        'bot_token' => '',
        'bot_username' => '',
        'p2tg' => [
            'active' => false,
            'channels' => [],
            'send_when' => ['new'],
            'post_types' => ['post'],
            'rules' => [],
            'message_template' => "{post_title}\n\n{post_excerpt}\n\n{full_url}",
            'excerpt_source' => 'post_content',
            'excerpt_length' => 55,
            'excerpt_preserve_eol' => true,
            'send_featured_image' => true,
            'image_position' => 'before',
            'single_message' => true,
            'cats_as_tags' => false,
            'parse_mode' => 'none',
            'link_preview_disabled' => false,
            'link_preview_url' => '',
            'link_preview_above_text' => false,
            'inline_url_button' => false,
            'inline_button_text' => '🔗 查看文章',
            'inline_button_url' => '{full_url}',
            'plugin_posts' => false,
            'post_edit_switch' => true,
            'delay' => ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? 0 : 0.5,
            'disable_notification' => false,
            'protect_content' => false,
        ],
        'notify' => [
            'active' => false,
            'watch_emails' => get_option('admin_email'),
            'chat_ids' => [],
            'user_notifications' => false,
            'message_template' => "🔔‌<b>{email_subject}</b>🔔\n\n{email_message}",
            'parse_mode' => 'HTML',
        ],
        'proxy' => [
            'active' => false,
            'proxy_method' => 'cf_worker',
            'proxy_type' => 'CURLPROXY_HTTP',
        ],
        'advanced' => [
            'send_files_by_url' => true,
            'enable_logs' => [],
            'clean_uninstall' => true,
        ],
    ];
}
