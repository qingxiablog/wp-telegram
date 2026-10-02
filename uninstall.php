<?php
/**
 * 插件卸载文件 (Uninstall File)
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$options = get_option( 'wptg_free_cn_options', [] );
$clean_uninstall = isset( $options['advanced']['clean_uninstall'] ) ? $options['advanced']['clean_uninstall'] : true;

if ( $clean_uninstall ) {
    // 仅删除当前插件的选项数据
    delete_option( 'wptg_free_cn_options' );
    delete_option( 'wptg_free_cn_schema_version' );
    delete_option( 'wptg_free_cn_migration_backup' );
    delete_option( 'wptg_free_cn_migrated_tasks' );

    // 删除文章元数据
    delete_post_meta_by_key( '_wptg_free_cn_p2tg_options' );
    delete_post_meta_by_key( '_wptg_free_cn_p2tg_send2tg' );
    delete_post_meta_by_key( '_wptg_free_cn_p2tg_sent2tg' );
    
    // 清除日志目录和文件
    $log_dir = WP_CONTENT_DIR . '/wptg-logs';
    if ( is_dir( $log_dir ) ) {
        $files = array_diff( scandir( $log_dir ), array( '.', '..' ) );
        foreach ( $files as $file ) {
            @unlink( "$log_dir/$file" );
        }
        @rmdir( $log_dir );
    }
}
// 注意：不删除旧版 wptelegram 插件的选项，也不删除 wptelegram_user_id 元数据。
