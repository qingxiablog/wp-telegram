<?php
/**
 * Plugin Name: Telegram 中文免费版
 * Version: 1.0.0
 * Requires at least: 6.6
 * Requires PHP: 8.0
 * Author: WPTelegramFreeCN
 * License: GPL-2.0+
 * Text Domain: wptelegram-free-cn
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. 定义常量 (Define Constants)
define( 'WPTG_FREE_CN_VER', '1.0.0' );
define( 'WPTG_FREE_CN_MAIN_FILE', __FILE__ );
define( 'WPTG_FREE_CN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPTG_FREE_CN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WPTG_FREE_CN_URL', untrailingslashit( plugins_url( '', __FILE__ ) ) );

if ( ! defined( 'WPTG_FREE_CN_USER_ID_META_KEY' ) ) {
    define( 'WPTG_FREE_CN_USER_ID_META_KEY', 'wptelegram_user_id' );
}

// 2. 引入 Composer 自动加载器 (Require Composer Autoloader)
if ( file_exists( WPTG_FREE_CN_DIR . '/vendor/autoload.php' ) ) {
    require_once WPTG_FREE_CN_DIR . '/vendor/autoload.php';
}

// 3. 环境要求与冲突检查 (Environment & Conflict Checks)
function wptg_free_cn_check_environment() {
    $min_php = '8.0';
    $min_wp  = '6.6';
    $errors  = [];

    if ( version_compare( PHP_VERSION, $min_php, '<' ) ) {
        $errors[] = sprintf( '需要 PHP %s 或更高版本 (当前为 %s)。', $min_php, PHP_VERSION );
    }
    if ( version_compare( get_bloginfo( 'version' ), $min_wp, '<' ) ) {
        $errors[] = sprintf( '需要 WordPress %s 或更高版本 (当前为 %s)。', $min_wp, get_bloginfo( 'version' ) );
    }

    if ( ! empty( $errors ) ) {
        add_action( 'admin_notices', function () use ( $errors ) {
            echo '<div class="notice notice-error"><p><strong>Telegram 中文免费版：</strong><br>' . implode( '<br>', $errors ) . '</p></div>';
        } );
        return false;
    }
    return true;
}

function wptg_free_cn_check_conflict() {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
    $is_active = is_plugin_active( 'wptelegram/wptelegram.php' ) || class_exists( 'WPTelegram\Plugin' ) || defined( 'WPTELEGRAM_VER' );
    if ( $is_active ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Telegram 中文免费版：</strong> 检测到原版 WPTelegram 插件正在运行。请先停用原版插件，以免发生冲突。</p></div>';
        } );
        return true;
    }
    return false;
}

// 4. 激活与停用钩子 (Activation & Deactivation Hooks)
register_activation_hook( __FILE__, 'wptg_free_cn_activate' );
register_deactivation_hook( __FILE__, 'wptg_free_cn_deactivate' );

function wptg_free_cn_activate() {
    // 激活时执行的逻辑
}

function wptg_free_cn_deactivate() {
    // 遍历 WP-Cron 数组，确保清除所有带文章 ID 参数的延迟发布任务
    if ( function_exists( '_get_cron_array' ) ) {
        $cron_array = _get_cron_array();
        if ( ! empty( $cron_array ) && is_array( $cron_array ) ) {
            foreach ( $cron_array as $timestamp => $cron_hooks ) {
                if ( isset( $cron_hooks['wptg_free_cn_p2tg_delayed_post'] ) ) {
                    foreach ( $cron_hooks['wptg_free_cn_p2tg_delayed_post'] as $hash => $task ) {
                        wp_unschedule_event( $timestamp, 'wptg_free_cn_p2tg_delayed_post', $task['args'] );
                    }
                }
            }
        }
    }
    wp_clear_scheduled_hook( 'wptg_free_cn_p2tg_delayed_post' );

    // 清理 Action Scheduler 中的任务
    if ( function_exists( 'as_unschedule_all_actions' ) ) {
        as_unschedule_all_actions( 'wptg_free_cn_p2tg_delayed_post' );
    }
}

// 5. 初始化插件 (Bootstrap)
add_action( 'plugins_loaded', 'wptg_free_cn_init', 20 );

function wptg_free_cn_init() {
    if ( ! wptg_free_cn_check_environment() ) {
        return;
    }

    $has_conflict = wptg_free_cn_check_conflict();

    // 加载辅助函数与默认值配置
    require_once WPTG_FREE_CN_DIR . '/includes/default-values.php';
    require_once WPTG_FREE_CN_DIR . '/includes/helpers.php';

    // 加载迁移兼容函数（始终加载以支持旧 meta 回读）
    require_once WPTG_FREE_CN_DIR . '/includes/migration.php';

    // 加载日志模块
    require_once WPTG_FREE_CN_DIR . '/includes/logger.php';

    $options = wptg_free_cn_options();

    // 启动日志（根据 advanced.enable_logs 配置）
    $active_logs = isset( $options['advanced']['enable_logs'] ) ? (array) $options['advanced']['enable_logs'] : [];
    if ( ! empty( $active_logs ) ) {
        wptg_free_cn_log_hookup( $active_logs );
    }

    // 后台页面（始终加载）
    if ( is_admin() ) {
        require_once WPTG_FREE_CN_DIR . '/includes/admin.php';
        require_once WPTG_FREE_CN_DIR . '/includes/settings.php';
        add_action( 'admin_menu', 'wptg_free_cn_admin_menu' );
        add_action( 'admin_init', 'wptg_free_cn_register_settings' );
        add_action( 'admin_enqueue_scripts', 'wptg_free_cn_admin_enqueue' );
        add_action( 'admin_notices', 'wptg_free_cn_admin_notices' );

        // AJAX 处理
        add_action( 'wp_ajax_wptg_free_cn_test_connection', 'wptg_free_cn_handle_test_connection' );
        add_action( 'wp_ajax_wptg_free_cn_test_send', 'wptg_free_cn_handle_test_send' );
        add_action( 'wp_ajax_wptg_free_cn_import_config', 'wptg_free_cn_migration_ajax_import' );
    }

    // 若旧版插件冲突，仅保留后台设置/迁移入口，不启动前台与自动推送业务钩子
    if ( $has_conflict ) {
        return;
    }

    // 代理模块（始终加载以挂接 Bot API 请求前后的钩子）
    require_once WPTG_FREE_CN_DIR . '/includes/proxy.php';
    if ( ! empty( $options['proxy']['active'] ) ) {
        add_action( 'wptelegram_bot_api_remote_request_init', 'wptg_free_cn_proxy_configure' );
        add_action( 'wptelegram_bot_api_remote_request_finish', 'wptg_free_cn_proxy_remove' );
    }

    // 文章推送模块 (p2tg)
    if ( ! empty( $options['p2tg']['active'] ) ) {
        require_once WPTG_FREE_CN_DIR . '/includes/template.php';
        require_once WPTG_FREE_CN_DIR . '/includes/rules.php';
        require_once WPTG_FREE_CN_DIR . '/includes/post-sender.php';
        require_once WPTG_FREE_CN_DIR . '/includes/editor.php';

        // 文章保存钩子
        add_action( 'wp_insert_post', 'wptg_free_cn_p2tg_on_wp_insert_post', 20, 2 );

        // 延迟发送钩子
        add_action( 'wptg_free_cn_p2tg_delayed_post', 'wptg_free_cn_p2tg_on_delayed_post', 10, 1 );

        // 公共发送接口
        add_action( 'wptg_free_cn_p2tg_send_post', 'wptg_free_cn_p2tg_send_post', 10, 3 );

        // REST API 钩子 (无论是否 is_admin() 均需挂接)
        add_action( 'rest_api_init', 'wptg_free_cn_p2tg_hook_into_rest_pre_insert' );

        // 编辑器集成
        if ( is_admin() ) {
            add_action( 'post_submitbox_misc_actions', 'wptg_free_cn_p2tg_add_post_edit_switch' );
            add_action( 'edit_form_top', 'wptg_free_cn_p2tg_post_edit_form_hidden_input' );
            add_action( 'block_editor_meta_box_hidden_fields', 'wptg_free_cn_p2tg_block_editor_hidden_fields' );
            add_action( 'add_meta_boxes', 'wptg_free_cn_p2tg_add_override_metabox' );
            add_action( 'enqueue_block_editor_assets', 'wptg_free_cn_p2tg_enqueue_editor_assets' );

            // 即时发送
            $instant_action = 'wptg_free_cn_instant_post';
            add_action( "admin_action_{$instant_action}", 'wptg_free_cn_p2tg_handle_instant_post' );
            add_filter( 'post_row_actions', 'wptg_free_cn_p2tg_add_instant_post_action', 10, 2 );
            add_filter( 'page_row_actions', 'wptg_free_cn_p2tg_add_instant_post_action', 10, 2 );
        }
    }

    // 邮件通知模块 (notify)
    if ( ! empty( $options['notify']['active'] ) ) {
        require_once WPTG_FREE_CN_DIR . '/includes/notifications.php';
        add_filter( 'wp_mail', 'wptg_free_cn_notify_handle_wp_mail', 5, 1 );
    }

    // 标记插件已加载
    define( 'WPTG_FREE_CN_LOADED', true );
}
