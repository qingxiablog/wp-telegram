<?php
/**
 * Migration Module
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wptg_free_cn_migration_import( $force = false ) {
    $schema_version = get_option( 'wptg_free_cn_schema_version', 0 );
    if ( $schema_version >= 1 && ! $force ) {
        return false;
    }

    $old_data_json = get_option( 'wptelegram' );
    if ( empty( $old_data_json ) ) {
        return false;
    }

    $old_data = json_decode( $old_data_json, true );
    if ( empty( $old_data ) || ( empty( $old_data['bot_token'] ) && empty( $old_data['p2tg'] ) ) ) {
        return false;
    }

    $new_options = array();
    
    if ( isset( $old_data['bot_token'] ) ) {
        $new_options['bot_token'] = $old_data['bot_token'];
    }
    if ( isset( $old_data['bot_username'] ) ) {
        $new_options['bot_username'] = $old_data['bot_username'];
    }
    
    $sections = array( 'p2tg', 'notify', 'proxy', 'advanced' );
    foreach ( $sections as $section ) {
        if ( isset( $old_data[ $section ] ) && is_array( $old_data[ $section ] ) ) {
            $new_options[ $section ] = $old_data[ $section ];
        }
    }

    $backup_saved = update_option( 'wptg_free_cn_migration_backup', $old_data );
    if ( false === $backup_saved && get_option( 'wptg_free_cn_migration_backup' ) !== $old_data ) {
        return false;
    }

    $existing = get_option( 'wptg_free_cn_options', array() );
    $target_options = $new_options;
    if ( $schema_version >= 1 && ! empty( $existing ) && is_array( $existing ) ) {
        $defaults       = wptg_free_cn_get_default_values();
        $merged         = wptg_free_cn_array_merge_recursive_distinct( $defaults, $new_options );
        $target_options = wptg_free_cn_array_merge_recursive_distinct( $merged, $existing );
    }

    $opt_saved = update_option( 'wptg_free_cn_options', $target_options );
    if ( false === $opt_saved && get_option( 'wptg_free_cn_options' ) !== $target_options ) {
        return false;
    }
    
    $tasks_ok = wptg_free_cn_migration_takeover_tasks();
    if ( ! $tasks_ok ) {
        return false;
    }

    $schema_saved = update_option( 'wptg_free_cn_schema_version', 1 );
    if ( false === $schema_saved && 1 !== get_option( 'wptg_free_cn_schema_version', 0 ) ) {
        return false;
    }
    return true;
}

/**
 * 递归合并配置，将顺序/索引数组视为整体原子替换，避免数组下标合并导致旧项残留
 */
function wptg_free_cn_array_merge_recursive_distinct( array $base, array $replacement ) {
    $merged = $base;
    foreach ( $replacement as $key => $value ) {
        if ( is_array( $value ) && isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ) {
            $is_assoc_val  = ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) );
            $is_assoc_base = ( array_keys( $merged[ $key ] ) !== range( 0, count( $merged[ $key ] ) - 1 ) );
            if ( $is_assoc_val && $is_assoc_base ) {
                $merged[ $key ] = wptg_free_cn_array_merge_recursive_distinct( $merged[ $key ], $value );
            } else {
                // 索引列表整体替换
                $merged[ $key ] = $value;
            }
        } else {
            $merged[ $key ] = $value;
        }
    }
    return $merged;
}

/**
 * AJAX 导入旧版配置处理函数
 */
function wptg_free_cn_migration_ajax_import() {
    check_ajax_referer( 'wptg_free_cn_nonce', '_wpnonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( '无权执行此操作。' );
    }

    $result = wptg_free_cn_migration_import( true );
    if ( $result ) {
        $results = get_transient( 'wptg_free_cn_migration_results' );
        $cron_count = isset( $results['cron'] ) ? $results['cron'] : 0;
        $as_count   = isset( $results['as'] ) ? $results['as'] : 0;
        wp_send_json_success( sprintf( '旧版配置与待发任务导入成功！（接管定时任务：WP-Cron %d 个，Action Scheduler %d 个）', $cron_count, $as_count ) );
    } else {
        wp_send_json_error( '未找到有效的旧版 WP Telegram (4.2.15) 配置数据，或接管定时任务时发生失败。' );
    }
}

function wptg_free_cn_migration_takeover_tasks() {
    $orig_migrated_tasks = get_option( 'wptg_free_cn_migrated_tasks', array() );
    $migrated_tasks = is_array( $orig_migrated_tasks ) ? $orig_migrated_tasks : array();
    $results = array( 'cron' => 0, 'as' => 0 );
    $has_failure = false;

    // Take over WP-Cron
    $cron_array = _get_cron_array();
    if ( ! empty( $cron_array ) && is_array( $cron_array ) ) {
        foreach ( $cron_array as $timestamp => $cron_hooks ) {
            if ( isset( $cron_hooks['wptelegram_p2tg_delayed_post'] ) ) {
                foreach ( $cron_hooks['wptelegram_p2tg_delayed_post'] as $hash => $task ) {
                    if ( in_array( 'cron_' . $hash, $migrated_tasks, true ) ) {
                        continue;
                    }
                    
                    $sched = wp_schedule_single_event( $timestamp, 'wptg_free_cn_p2tg_delayed_post', $task['args'] );
                    if ( false !== $sched && ! is_wp_error( $sched ) ) {
                        $unsched = wp_unschedule_event( $timestamp, 'wptelegram_p2tg_delayed_post', $task['args'] );
                        if ( false !== $unsched && ! is_wp_error( $unsched ) ) {
                            $migrated_tasks[] = 'cron_' . $hash;
                            $results['cron']++;
                        } else {
                            $has_failure = true;
                            // 接管未完成时撤回刚创建的新任务，避免重试重复排期。
                            wp_unschedule_event( $timestamp, 'wptg_free_cn_p2tg_delayed_post', $task['args'] );
                        }
                    } else {
                        $has_failure = true;
                    }
                }
            }
        }
    }

    // Take over Action Scheduler (with pagination and group support)
    if ( function_exists( 'as_get_scheduled_actions' ) && class_exists( 'ActionScheduler_Store' ) ) {
        $offset = 0;
        $per_page = 100;
        do {
            $actions = as_get_scheduled_actions( array(
                'hook'     => 'wptelegram_p2tg_delayed_post',
                'status'   => \ActionScheduler_Store::STATUS_PENDING,
                'per_page' => $per_page,
                'offset'   => $offset,
            ) );

            if ( empty( $actions ) ) {
                break;
            }

            $all_skipped = true;
            foreach ( $actions as $action_id => $action ) {
                if ( in_array( 'as_' . $action_id, $migrated_tasks, true ) ) {
                    continue;
                }
                $all_skipped = false;
                
                $schedule  = method_exists( $action, 'get_schedule' ) ? $action->get_schedule() : null;
                $args      = method_exists( $action, 'get_args' ) ? $action->get_args() : array();
                $group     = method_exists( $action, 'get_group' ) ? $action->get_group() : '';
                $timestamp = ( $schedule && method_exists( $schedule, 'get_date' ) && $schedule->get_date() ) ? $schedule->get_date()->getTimestamp() : time();
                
                $as_res = as_schedule_single_action( $timestamp, 'wptg_free_cn_p2tg_delayed_post', $args, $group );
                if ( ! empty( $as_res ) && ! is_wp_error( $as_res ) ) {
                    $unsched_res = as_unschedule_action( 'wptelegram_p2tg_delayed_post', $args, $group );
                    if ( ! empty( $unsched_res ) ) {
                        $migrated_tasks[] = 'as_' . $action_id;
                        $results['as']++;
                    } else {
                        $has_failure = true;
                        // 旧任务仍在队列时，撤回刚创建的新 Action。
                        as_unschedule_action( 'wptg_free_cn_p2tg_delayed_post', $args, $group );
                        $offset++;
                    }
                } else {
                    $has_failure = true;
                    $offset++;
                }
            }

            if ( $all_skipped ) {
                $offset += count( $actions );
            }
        } while ( count( $actions ) === $per_page );
    }

    if ( ! empty( $migrated_tasks ) && $migrated_tasks !== $orig_migrated_tasks ) {
        $saved_tasks = update_option( 'wptg_free_cn_migrated_tasks', $migrated_tasks );
        if ( false === $saved_tasks && get_option( 'wptg_free_cn_migrated_tasks' ) !== $migrated_tasks ) {
            $has_failure = true;
        }
    }
    set_transient( 'wptg_free_cn_migration_results', $results, HOUR_IN_SECONDS );

    return ! $has_failure;
}

function wptg_free_cn_migration_page_render() {
    echo '<div class="wrap">';
    echo '<h1>WP Telegram 数据迁移</h1>';
    
    $results = get_transient( 'wptg_free_cn_migration_results' );
    if ( $results ) {
        echo '<div class="notice notice-success is-dismissible"><p>迁移成功！接管了 ' . intval( $results['cron'] ) . ' 个 WP-Cron 任务和 ' . intval( $results['as'] ) . ' 个 Action Scheduler 任务。</p></div>';
    }

    $old_data = get_option( 'wptelegram' );
    if ( ! empty( $old_data ) ) {
        echo '<form method="post" action="">';
        wp_nonce_field( 'wptg_free_cn_migrate' );
        echo '<p>检测到旧版 WP Telegram (4.2.15) 配置数据。</p>';
        echo '<input type="submit" name="wptg_free_cn_do_migration" class="button button-primary" value="导入旧数据">';
        echo '</form>';
        
        if ( isset( $_POST['wptg_free_cn_do_migration'] ) && check_admin_referer( 'wptg_free_cn_migrate' ) ) {
            wptg_free_cn_migration_import( true );
            echo '<script>window.location.reload();</script>';
        }
    } else {
        echo '<p>未检测到需要迁移的数据。</p>';
    }
    echo '</div>';
}

/**
 * 兼容读取文章已发送记录（优先新版，回退旧版）
 */
function wptg_free_cn_p2tg_get_sent2tg( $post_id ) {
    $val = get_post_meta( $post_id, '_wptg_free_cn_p2tg_sent2tg', true );
    if ( ! empty( $val ) ) {
        return $val;
    }
    return get_post_meta( $post_id, '_wptg_p2tg_sent2tg', true );
}

/**
 * 兼容读取文章本次发送开关（优先新版，回退旧版）
 */
function wptg_free_cn_p2tg_get_send2tg( $post_id ) {
    $val = get_post_meta( $post_id, '_wptg_free_cn_p2tg_send2tg', true );
    if ( '' !== $val && false !== $val ) {
        return $val;
    }
    return get_post_meta( $post_id, '_wptg_p2tg_send2tg', true );
}

/**
 * 兼容读取文章独立配置覆盖（优先新版，回退旧版）
 */
function wptg_free_cn_p2tg_get_saved_options( $post_id ) {
    $val = get_post_meta( $post_id, '_wptg_free_cn_p2tg_options', true );
    if ( ! empty( $val ) && is_array( $val ) ) {
        return $val;
    }
    $old_val = get_post_meta( $post_id, '_wptg_p2tg_options', true );
    if ( ! empty( $old_val ) ) {
        if ( is_string( $old_val ) ) {
            $decoded = json_decode( $old_val, true );
            if ( is_array( $decoded ) ) {
                return $decoded;
            }
        } elseif ( is_array( $old_val ) ) {
            return $old_val;
        }
    }
    return array();
}
