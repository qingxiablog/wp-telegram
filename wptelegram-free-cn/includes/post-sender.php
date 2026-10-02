<?php
/**
 * Post to Telegram Sender Logic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wptg_free_cn_processed_posts;
$wptg_free_cn_processed_posts = array();

/**
 * 处理 wp_insert_post 动作
 */
function wptg_free_cn_p2tg_on_wp_insert_post( $post_id, $post ) {
	wptg_free_cn_p2tg_send_post( $post, 'wp_insert_post' );
}
add_action( 'wp_insert_post', 'wptg_free_cn_p2tg_on_wp_insert_post', 20, 2 );

/**
 * 处理延迟发布动作
 */
function wptg_free_cn_p2tg_on_delayed_post( $post_id ) {
	$post = get_post( $post_id );
	if ( $post ) {
		wptg_free_cn_p2tg_send_post( $post, 'delayed_post', true );
	}
}
add_action( 'wptg_free_cn_p2tg_delayed_post', 'wptg_free_cn_p2tg_on_delayed_post' );

/**
 * 处理 REST API 插入后动作
 */
function wptg_free_cn_p2tg_on_rest_after_insert( $post ) {
	wptg_free_cn_p2tg_send_post( $post, 'rest_after_insert' );
}

/**
 * 核心发送逻辑
 *
 * @param WP_Post $post    文章对象
 * @param string  $trigger 触发来源
 * @param bool    $force   是否强制发送
 */
function wptg_free_cn_p2tg_send_post( $post, $trigger = 'non_wp', $force = false ) {
	if ( empty( $post ) || ! is_a( $post, 'WP_Post' ) ) {
		return;
	}

	$original_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;

	// 针对 delayed_post 触发器设置全局 $post，以便模板等能正常工作
	if ( 'delayed_post' === $trigger ) {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['post'] = $post;
		setup_postdata( $post );
	}

	try {
		wptg_free_cn_p2tg_send_the_post( $post, $trigger, $force );
	} finally {
		if ( 'delayed_post' === $trigger ) {
			if ( null !== $original_post ) {
				$GLOBALS['post'] = $original_post;
				setup_postdata( $original_post );
			} else {
				unset( $GLOBALS['post'] );
			}
		}
	}
}
add_action( 'wptg_free_cn_p2tg_send_post', 'wptg_free_cn_p2tg_send_post', 10, 3 );

/**
 * 执行发送流程
 */
function wptg_free_cn_p2tg_send_the_post( $post, $trigger, $force ) {
	global $wptg_free_cn_processed_posts;

	$bot_token = wptg_free_cn_get_option( 'bot_token' );
	if ( empty( $bot_token ) ) {
		return;
	}

	$post_id = $post->ID;

	// 防止同一次请求中重复发送
	if ( in_array( $post_id, $wptg_free_cn_processed_posts, true ) && ! $force ) {
		return;
	}

	// 检查请求类型和来源
	$is_rest_request = defined( 'REST_REQUEST' ) && REST_REQUEST;
	$is_gb_metabox   = isset( $_POST['_wptg_free_cn_p2tg_is_gb_metabox'] ); // phpcs:ignore
	$from_web        = isset( $_POST['_wptg_free_cn_p2tg_from_web'] ); // phpcs:ignore

	// 处理 REST API 预插入阶段，延迟到 after_insert
	if ( $is_rest_request && did_action( "wptg_free_cn_rest_pre_insert_{$post->post_type}" ) ) {
		if ( current_filter() !== "rest_after_insert_{$post->post_type}" && 'rest_after_insert' !== $trigger ) {
			add_action( "rest_after_insert_{$post->post_type}", 'wptg_free_cn_p2tg_on_rest_after_insert', 20, 1 );
			return;
		}
	}

	// 初始化选项合并（默认 + 全局设置）
	$options = array(
		'send2tg'                  => '',
		'override_switch'          => false,
		'channels'                 => wptg_free_cn_get_option( 'p2tg.channels', array() ),
		'message_template'         => wptg_free_cn_get_option( 'p2tg.message_template', '' ),
		'send_featured_image'      => wptg_free_cn_get_option( 'p2tg.send_featured_image', false ),
		'image_position'           => wptg_free_cn_get_option( 'p2tg.image_position', 'before' ),
		'single_message'           => wptg_free_cn_get_option( 'p2tg.single_message', false ),
		'disable_notification'     => wptg_free_cn_get_option( 'p2tg.disable_notification', false ),
		'protect_content'          => wptg_free_cn_get_option( 'p2tg.protect_content', false ),
		'link_preview_disabled'    => wptg_free_cn_get_option( 'p2tg.link_preview_disabled', false ),
		'link_preview_url'         => wptg_free_cn_get_option( 'p2tg.link_preview_url', '' ),
		'link_preview_above_text'  => wptg_free_cn_get_option( 'p2tg.link_preview_above_text', false ),
		'inline_url_button'        => wptg_free_cn_get_option( 'p2tg.inline_url_button', false ),
		'inline_button_text'       => wptg_free_cn_get_option( 'p2tg.inline_button_text', '🔗 查看文章' ),
		'inline_button_url'        => wptg_free_cn_get_option( 'p2tg.inline_button_url', '{full_url}' ),
		'plugin_posts'             => wptg_free_cn_get_option( 'p2tg.plugin_posts', false ),
		'delay'                    => wptg_free_cn_get_option( 'p2tg.delay', 0 ),
		'send_when'                => wptg_free_cn_get_option( 'p2tg.send_when', array( 'new' ) ),
		'post_types'               => wptg_free_cn_get_option( 'p2tg.post_types', array( 'post' ) ),
		'rules'                    => wptg_free_cn_get_option( 'p2tg.rules', array() ),
		'parse_mode'               => wptg_free_cn_get_option( 'p2tg.parse_mode', 'none' ),
	);

	// 读取表单/Meta覆盖数据
	$form_data = array();
	$meta_data = wptg_free_cn_p2tg_get_saved_options( $post_id );
	if ( ! is_array( $meta_data ) ) {
		$meta_data = array();
	}
	$saved_send2tg = wptg_free_cn_p2tg_get_send2tg( $post_id );

	if ( $from_web || $is_gb_metabox ) {
		// 从 POST 获取
		if ( isset( $_POST['_wptg_free_cn_p2tg_send2tg'] ) ) {
			$form_data['send2tg'] = sanitize_text_field( wp_unslash( $_POST['_wptg_free_cn_p2tg_send2tg'] ) );
		} elseif ( $from_web ) {
			// Web 经典编辑器中，如果表单提交且未勾选发送，无论是否开启覆盖，均视为不发送 ('no')
			$form_data['send2tg'] = 'no';
		}
		if ( isset( $_POST['_wptg_free_cn_p2tg_override_switch'] ) ) {
			$form_data['override_switch'] = true;
			
			// 单篇覆盖配置中，checkbox 未勾选时必须明确为 false，不能继承全局的 true！
			$form_data['send_featured_image']  = ! empty( $_POST['_wptg_free_cn_p2tg_send_featured_image'] );
			$form_data['disable_notification'] = ! empty( $_POST['_wptg_free_cn_p2tg_disable_notification'] );

			if ( isset( $_POST['_wptg_free_cn_p2tg_delay'] ) ) {
				$form_data['delay'] = wp_unslash( $_POST['_wptg_free_cn_p2tg_delay'] );
			}

			// 消息模板：若单篇覆盖留空，依据中文帮助“留空使用全局”，回退至全局模板（不覆盖）
			if ( isset( $_POST['_wptg_free_cn_p2tg_message_template'] ) ) {
				$tmpl = trim( (string) wp_unslash( $_POST['_wptg_free_cn_p2tg_message_template'] ) );
				if ( '' !== $tmpl ) {
					$form_data['message_template'] = $tmpl;
				}
			}

			if ( isset( $_POST['_wptg_free_cn_p2tg_channels'] ) ) {
				$raw_channels = wp_unslash( $_POST['_wptg_free_cn_p2tg_channels'] );
				if ( is_array( $raw_channels ) ) {
					$form_data['channels'] = array_map( 'sanitize_text_field', $raw_channels );
				} else {
					$lines = explode( "\n", (string) $raw_channels );
					$channels = array();
					foreach ( $lines as $line ) {
						$line = trim( $line );
						if ( '' !== $line ) {
							$channels[] = sanitize_text_field( $line );
						}
					}
					$form_data['channels'] = array_values( array_unique( $channels ) );
				}
			}

			if ( isset( $_POST['_wptg_free_cn_p2tg_files'] ) ) {
				$raw_files = wp_unslash( $_POST['_wptg_free_cn_p2tg_files'] );
				if ( is_array( $raw_files ) ) {
					$form_data['files'] = array_map( 'sanitize_text_field', $raw_files );
				} else {
					$lines = explode( "\n", (string) $raw_files );
					$files = array();
					foreach ( $lines as $line ) {
						$line = trim( $line );
						if ( '' !== $line ) {
							$files[] = sanitize_text_field( $line );
						}
					}
					$form_data['files'] = array_values( array_unique( $files ) );
				}
			}
		}
	} elseif ( $is_rest_request ) {
		// 尝试从 JSON body 获取
		$raw_input = file_get_contents( 'php://input' );
		$json_data = json_decode( $raw_input, true );
		if ( is_array( $json_data ) ) {
			$p2tg_payload = ( isset( $json_data['_wptg_free_cn_p2tg_'] ) && is_array( $json_data['_wptg_free_cn_p2tg_'] ) ) ? $json_data['_wptg_free_cn_p2tg_'] : array();
			$meta_payload = ( isset( $json_data['meta'] ) && is_array( $json_data['meta'] ) ) ? $json_data['meta'] : array();

			// 检查 send2tg
			if ( isset( $p2tg_payload['send2tg'] ) ) {
				$val = $p2tg_payload['send2tg'];
				$form_data['send2tg'] = ( false === $val || 'no' === $val || 0 === $val || '0' === $val ) ? 'no' : 'yes';
			} elseif ( isset( $meta_payload['_wptg_free_cn_p2tg_send2tg'] ) ) {
				$val = $meta_payload['_wptg_free_cn_p2tg_send2tg'];
				$form_data['send2tg'] = ( false === $val || 'no' === $val || 0 === $val || '0' === $val ) ? 'no' : 'yes';
			} elseif ( isset( $json_data['_wptg_free_cn_p2tg_send2tg'] ) ) {
				$val = $json_data['_wptg_free_cn_p2tg_send2tg'];
				$form_data['send2tg'] = ( false === $val || 'no' === $val || 0 === $val || '0' === $val ) ? 'no' : 'yes';
			}

			// 检查 override_switch
			$has_override = ! empty( $p2tg_payload['override_switch'] ) || ! empty( $json_data['_wptg_free_cn_p2tg_override_switch'] );
			if ( $has_override ) {
				$form_data['override_switch'] = true;
				$src = ! empty( $p2tg_payload['override_switch'] ) ? $p2tg_payload : $json_data;

				if ( isset( $src['send_featured_image'] ) || isset( $src['_wptg_free_cn_p2tg_send_featured_image'] ) ) {
					$img_flag = isset( $src['send_featured_image'] ) ? $src['send_featured_image'] : $src['_wptg_free_cn_p2tg_send_featured_image'];
					$form_data['send_featured_image'] = ! empty( $img_flag );
				}
				if ( isset( $src['disable_notification'] ) || isset( $src['_wptg_free_cn_p2tg_disable_notification'] ) ) {
					$silent_flag = isset( $src['disable_notification'] ) ? $src['disable_notification'] : $src['_wptg_free_cn_p2tg_disable_notification'];
					$form_data['disable_notification'] = ! empty( $silent_flag );
				}
				if ( isset( $src['message_template'] ) || isset( $src['_wptg_free_cn_p2tg_message_template'] ) ) {
					$tmpl = trim( (string) ( isset( $src['message_template'] ) ? $src['message_template'] : $src['_wptg_free_cn_p2tg_message_template'] ) );
					if ( '' !== $tmpl ) {
						$form_data['message_template'] = $tmpl;
					}
				}
				if ( isset( $src['delay'] ) || isset( $src['_wptg_free_cn_p2tg_delay'] ) ) {
					$form_data['delay'] = isset( $src['delay'] ) ? $src['delay'] : $src['_wptg_free_cn_p2tg_delay'];
				}
				if ( isset( $src['channels'] ) || isset( $src['_wptg_free_cn_p2tg_channels'] ) ) {
					$raw_channels = isset( $src['channels'] ) ? $src['channels'] : $src['_wptg_free_cn_p2tg_channels'];
					if ( is_array( $raw_channels ) ) {
						$form_data['channels'] = array_map( 'sanitize_text_field', $raw_channels );
					} else {
						$lines = explode( "\n", (string) $raw_channels );
						$channels = array();
						foreach ( $lines as $line ) {
							$line = trim( $line );
							if ( '' !== $line ) {
								$channels[] = sanitize_text_field( $line );
							}
						}
						$form_data['channels'] = array_values( array_unique( $channels ) );
					}
				}
			}
		}
	}

	// 如果没有表单数据（如定时发布或纯后台API触发），使用保存的 meta_data
	if ( empty( $form_data ) && ! empty( $meta_data ) ) {
		$form_data = $meta_data;
	}
	if ( ! isset( $form_data['send2tg'] ) && '' !== $saved_send2tg && false !== $saved_send2tg ) {
		$form_data['send2tg'] = $saved_send2tg;
	}

	// 合并数据
	foreach ( $form_data as $key => $value ) {
		$options[ $key ] = $value;
	}

	// 安全与有效性检查 ---------------------------------------
	
	// 2. classic metabox from gutenberg
	if ( $is_gb_metabox ) {
		return;
	}

	// 3. 导入时
	if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING && ! apply_filters( 'wptg_free_cn_p2tg_allow_importing', false ) ) {
		return;
	}

	// 4/5. 批量/快速编辑
	if ( ( isset( $_GET['bulk_edit'] ) || isset( $_POST['bulk_edit'] ) ) && ! apply_filters( 'wptg_free_cn_p2tg_allow_bulk_edit', false ) ) {
		return;
	}
	$is_quick_edit = ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_REQUEST['action'] ) && 'inline-save' === $_REQUEST['action'] );
	if ( ( $is_quick_edit || apply_filters( 'wptg_free_cn_p2tg_is_quick_edit', false ) ) && ! apply_filters( 'wptg_free_cn_p2tg_allow_quick_edit', false ) ) {
		return;
	}

	// 6. Web 表单 Nonce 验证
	if ( $from_web && ! check_ajax_referer( 'wptg_free_cn_p2tg_metabox_' . $post_id, '_wptg_free_cn_p2tg_nonce', false ) ) {
		return;
	}

	// 7. 自动保存
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// 8. 过滤修订版本
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	// 状态划分：live 文章 (publish, private) 和 non_live 文章 (future, draft, pending)
	$live_statuses     = array( 'publish', 'private' );
	$non_live_statuses = array( 'future', 'draft', 'pending' );
	$is_live           = in_array( $post->post_status, $live_statuses, true );
	$is_non_live       = in_array( $post->post_status, $non_live_statuses, true );

	// 如果来自 Web 提交或有明确覆盖参数，且文章属于有效状态，持久化覆盖配置到 meta
	if ( ( $from_web || $is_gb_metabox || ! empty( $options['override_switch'] ) || ! empty( $options['send2tg'] ) ) && ( $is_live || $is_non_live ) ) {
		$save_data = array();
		if ( ! empty( $options['send2tg'] ) ) {
			$save_data['send2tg'] = $options['send2tg'];
			update_post_meta( $post_id, '_wptg_free_cn_p2tg_send2tg', $options['send2tg'] );
		}
		if ( ! empty( $options['override_switch'] ) ) {
			$save_data['override_switch']      = true;
			$save_data['channels']             = $options['channels'];
			$save_data['message_template']     = $options['message_template'];
			$save_data['send_featured_image']  = $options['send_featured_image'];
			$save_data['disable_notification'] = $options['disable_notification'];
			$save_data['delay']                = $options['delay'];
			if ( ! empty( $options['files'] ) ) {
				$save_data['files'] = $options['files'];
			}
		}
		if ( ! empty( $save_data ) ) {
			update_post_meta( $post_id, '_wptg_free_cn_p2tg_options', $save_data );
		}
	}

	// 1. send2tg 为 no 则跳过并清理延迟任务
	if ( 'no' === $options['send2tg'] ) {
		wptg_free_cn_p2tg_clear_scheduled_hook( $post_id );
		return;
	}

	// 9. 状态检查：未发布草稿、待审或定时文章绝不直接发送，即使 force 也不发送草稿
	if ( ! $is_live ) {
		return;
	}

	// 10. 权限检查
	$plugin_posts = ! empty( $options['plugin_posts'] );
	$bypass_permission = ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && WP_CLI ) || in_array( $trigger, array( 'delayed_post', 'non_wp' ), true ) || $plugin_posts;
	if ( ! $bypass_permission && ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// 检查文章类型（即使是手动强制发送 instant/force，也必须遵守允许的内容类型规则）
	$allowed_post_types = isset( $options['post_types'] ) ? (array) $options['post_types'] : array( 'post' );
	if ( ! in_array( $post->post_type, $allowed_post_types, true ) ) {
		return;
	}

	// 11. 主过滤器拦截
	if ( ! apply_filters( 'wptg_free_cn_p2tg_filter_post', true, $post, $options ) && ! $force ) {
		return;
	}

	// 如果没有配置频道，视同 send2tg='no'
	if ( empty( $options['channels'] ) ) {
		wptg_free_cn_p2tg_clear_scheduled_hook( $post_id );
		return;
	}

	$is_bypassing_rules = in_array( $trigger, array( 'non_wp', 'instant' ), true ) || $force;
	$bypass_date_rules  = ( 'yes' === $options['send2tg'] ) || $is_bypassing_rules;
	$rules_apply = true;

	// 规则评估 ---------------------------------------
	if ( ! $is_bypassing_rules ) {
		if ( ! $bypass_date_rules ) {
			// 判断是否是新文章
			$sent2tg = wptg_free_cn_p2tg_get_sent2tg( $post_id );
			$post_publish_time = get_post_time( 'U', true, $post_id, false );
			$is_more_than_a_day_old = $post_publish_time ? ( ( time() - $post_publish_time ) / DAY_IN_SECONDS ) > 1 : false;
			$is_new  = empty( $sent2tg ) && ! $is_more_than_a_day_old;
			
			$send_when = isset( $options['send_when'] ) ? (array) $options['send_when'] : array( 'new' );
			if ( $is_new && ! in_array( 'new', $send_when, true ) ) {
				$rules_apply = false;
			}
			if ( ! $is_new && ! in_array( 'existing', $send_when, true ) ) {
				$rules_apply = false;
			}
		}

		// 检查自定义规则
		if ( $rules_apply && ! wptg_free_cn_p2tg_rules_apply( $options['rules'], $post ) ) {
			$rules_apply = false;
		}
	}

	// 延迟发送处理 ---------------------------------------
	$delay_seconds      = intval( round( floatval( $options['delay'] ) * 60 ) );
	$is_bypassing_delay = in_array( $trigger, array( 'delayed_post', 'instant' ), true );

	if ( ! $is_bypassing_delay && $delay_seconds > 0 ) {
		// 安排延迟任务
		if ( $rules_apply || apply_filters( 'wptg_free_cn_p2tg_defer_rules_check', false ) ) {
			wptg_free_cn_p2tg_schedule_event( $post_id, time() + $delay_seconds );
		}
		return;
	}

	// 最终发送 ---------------------------------------
	if ( $rules_apply ) {
		wptg_free_cn_p2tg_do_send( $post, $options, $bot_token );
		$wptg_free_cn_processed_posts[] = $post_id;
	}
}

/**
 * 实际构造消息并调用 API
 */
function wptg_free_cn_p2tg_do_send( $post, $options, $bot_token ) {
	// 加载 API 类
	if ( ! class_exists( 'WPTelegram\BotAPI\API' ) ) {
		return;
	}

	$api = new WPTelegram\BotAPI\API( $bot_token );
	
	// API HTTP 客户端配置
	add_filter( 'http_request_args', 'wptg_free_cn_p2tg_http_request_args', 10, 2 );

	// 解析模板
	$template   = isset( $options['message_template'] ) ? $options['message_template'] : '';
	$message    = wptg_free_cn_p2tg_parse_template( $template, $post, $options );
	$parse_mode = wptg_free_cn_valid_parse_mode( isset( $options['parse_mode'] ) ? $options['parse_mode'] : 'none' );

	// 准备发图
	$has_image = ! empty( $options['send_featured_image'] ) && has_post_thumbnail( $post->ID );
	$image_url = '';
	if ( $has_image ) {
		$image_url = get_the_post_thumbnail_url( $post->ID, 'full' );
	}

	// 若既无文字也无图片也无附件，则不发送
	if ( empty( $message ) && ! ( $has_image && $image_url ) && empty( $options['files'] ) ) {
		return;
	}

	// 针对长文本截断，保证不超过 Telegram 限制 (4096 字符) 并保持 HTML 标签平衡
	$max_text_len = 4096;
	if ( mb_strlen( $message ) > $max_text_len ) {
		$message = wptg_free_cn_safe_truncate( $message, $max_text_len, $parse_mode );
	}

	// 针对 Caption 截断，保证不超过 Telegram 限制 (1024 字符) 并保持 HTML 标签平衡
	$max_caption_len = 1024;
	$caption = $message;
	if ( mb_strlen( $caption ) > $max_caption_len ) {
		$caption = wptg_free_cn_safe_truncate( $caption, $max_caption_len, $parse_mode );
	}

	$disable_notification  = ! empty( $options['disable_notification'] );
	$protect_content       = ! empty( $options['protect_content'] );
	$link_preview_disabled = ! empty( $options['link_preview_disabled'] ) || ! empty( $options['disable_web_page_preview'] );

	$link_preview_options = array(
		'is_disabled' => (bool) $link_preview_disabled,
	);
	if ( ! empty( $options['link_preview_url'] ) ) {
		$link_preview_options['url'] = esc_url( wptg_free_cn_p2tg_parse_template( $options['link_preview_url'], $post, $options ) );
	}
	if ( ! empty( $options['link_preview_above_text'] ) ) {
		$link_preview_options['show_above_text'] = true;
	}

	// 处理单个消息/组合逻辑
	$responses_to_send = array();
	$keyboard = wptg_free_cn_p2tg_build_keyboard( $options, $post );

	if ( $has_image && $image_url ) {
		$image_position = isset( $options['image_position'] ) ? $options['image_position'] : 'before';
		$single_message = ! empty( $options['single_message'] );
		$thumb_id       = function_exists( 'get_post_thumbnail_id' ) ? get_post_thumbnail_id( $post->ID ) : null;

		if ( empty( $message ) ) {
			// 仅发图片
			$responses_to_send[] = array(
				'method'        => 'sendPhoto',
				'attachment_id' => $thumb_id,
				'params'        => array(
					'photo'                => $image_url,
					'caption'              => '',
					'disable_notification' => $disable_notification,
					'protect_content'      => $protect_content,
					'reply_markup'         => $keyboard,
				),
			);
		} elseif ( $single_message && 'before' === $image_position ) {
			// 作为图文消息合并发送 (Caption)
			$responses_to_send[] = array(
				'method'        => 'sendPhoto',
				'attachment_id' => $thumb_id,
				'params'        => array(
					'photo'                => $image_url,
					'caption'              => $caption,
					'parse_mode'           => $parse_mode,
					'disable_notification' => $disable_notification,
					'protect_content'      => $protect_content,
					'reply_markup'         => $keyboard,
				),
			);
		} elseif ( $single_message && 'after' === $image_position && 'HTML' === $parse_mode ) {
			// 使用隐藏链接方式
			$hidden_link = '<a href="' . esc_url( $image_url ) . '">&#8203;</a>';
			$after_preview_options = array_merge( $link_preview_options, array(
				'is_disabled'     => false,
				'url'             => $image_url,
				'show_above_text' => false,
			) );
			$responses_to_send[] = array(
				'method' => 'sendMessage',
				'params' => array(
					'text'                 => $message . $hidden_link,
					'parse_mode'           => $parse_mode,
					'disable_notification' => $disable_notification,
					'protect_content'      => $protect_content,
					'link_preview_options' => $after_preview_options,
					'reply_markup'         => $keyboard,
				),
			);
		} else {
			// 分开发送或无法使用隐藏链接的纯文本后置图片
			$msg_item = array(
				'method' => 'sendMessage',
				'params' => array(
					'text'                 => $message,
					'parse_mode'           => $parse_mode,
					'disable_notification' => $disable_notification,
					'protect_content'      => $protect_content,
					'link_preview_options' => $link_preview_options,
					'reply_markup'         => $keyboard,
				),
			);
			$photo_item = array(
				'method'        => 'sendPhoto',
				'attachment_id' => $thumb_id,
				'params'        => array(
					'photo'                => $image_url,
					'disable_notification' => $disable_notification,
					'protect_content'      => $protect_content,
				),
			);

			if ( 'after' === $image_position ) {
				$responses_to_send[] = $msg_item;
				$responses_to_send[] = $photo_item;
			} else {
				$responses_to_send[] = $photo_item;
				$responses_to_send[] = $msg_item;
			}
		}
	} elseif ( ! empty( $message ) ) {
		// 纯文本消息
		$responses_to_send[] = array(
			'method' => 'sendMessage',
			'params' => array(
				'text'                 => $message,
				'parse_mode'           => $parse_mode,
				'disable_notification' => $disable_notification,
				'protect_content'      => $protect_content,
				'link_preview_options' => $link_preview_options,
				'reply_markup'         => $keyboard,
			),
		);
	}

	// 附加文件/媒体处理
	if ( ! empty( $options['files'] ) && is_array( $options['files'] ) ) {
		foreach ( $options['files'] as $key => $file_entry ) {
			$att_id     = null;
			$target_url = '';
			if ( is_numeric( $key ) && is_string( $file_entry ) && filter_var( $file_entry, FILTER_VALIDATE_URL ) ) {
				// 兼容旧版 [ attachment_id => url ] 结构
				$att_id     = intval( $key );
				$target_url = $file_entry;
			} elseif ( is_numeric( $file_entry ) ) {
				// 新版或纯 ID 列表 ['20']
				$att_id     = intval( $file_entry );
				$target_url = wp_get_attachment_url( $att_id );
			} elseif ( is_string( $file_entry ) ) {
				$target_url = trim( $file_entry );
			}
			if ( empty( $target_url ) ) {
				continue;
			}
			$ext = strtolower( pathinfo( parse_url( $target_url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
			if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
				$responses_to_send[] = array(
					'method'        => 'sendPhoto',
					'attachment_id' => $att_id,
					'params'        => array(
						'photo'                => $target_url,
						'disable_notification' => $disable_notification,
						'protect_content'      => $protect_content,
					),
				);
			} elseif ( in_array( $ext, array( 'mp3', 'm4a', 'ogg', 'wav' ), true ) ) {
				$responses_to_send[] = array(
					'method'        => 'sendAudio',
					'attachment_id' => $att_id,
					'params'        => array(
						'audio'                => $target_url,
						'disable_notification' => $disable_notification,
						'protect_content'      => $protect_content,
					),
				);
			} elseif ( in_array( $ext, array( 'mp4', 'mov' ), true ) ) {
				$responses_to_send[] = array(
					'method'        => 'sendVideo',
					'attachment_id' => $att_id,
					'params'        => array(
						'video'                => $target_url,
						'disable_notification' => $disable_notification,
						'protect_content'      => $protect_content,
					),
				);
			} else {
				$responses_to_send[] = array(
					'method'        => 'sendDocument',
					'attachment_id' => $att_id,
					'params'        => array(
						'document'             => $target_url,
						'disable_notification' => $disable_notification,
						'protect_content'      => $protect_content,
					),
				);
			}
		}
	}

	// 允许修改响应数组
	$responses_to_send = apply_filters( 'wptg_free_cn_p2tg_responses', $responses_to_send, $post, $options );

	// 本地文件上传处理标识
	$send_by_url = wptg_free_cn_send_files_by_url();

	$success   = false;
	$has_error = false;

	// 分发到各个频道
	foreach ( (array) $options['channels'] as $channel ) {
		// 去除备注 (e.g. @channel|My Channel)
		$parts = explode( '|', $channel );
		$channel_id = trim( $parts[0] );
		
		if ( empty( $channel_id ) ) {
			continue;
		}

		// 支持 chat_id:thread_id 格式
		$thread_id = null;
		if ( strpos( $channel_id, ':' ) !== false && strpos( $channel_id, '@' ) !== 0 ) {
			$id_parts = explode( ':', $channel_id );
			$channel_id = $id_parts[0];
			$thread_id = $id_parts[1];
		}

		$reply_to_message_id = null;

		foreach ( $responses_to_send as $resp ) {
			$method = $resp['method'];
			$params = $resp['params'];
			
			$params['chat_id'] = $channel_id;
			if ( $thread_id ) {
				$params['message_thread_id'] = $thread_id;
			}
			if ( $reply_to_message_id ) {
				$params['reply_parameters'] = array( 'message_id' => $reply_to_message_id );
			}

			// 处理本地文件上传
			if ( ! $send_by_url ) {
				$media_key = null;
				if ( isset( $params['photo'] ) ) {
					$media_key = 'photo';
				} elseif ( isset( $params['audio'] ) ) {
					$media_key = 'audio';
				} elseif ( isset( $params['video'] ) ) {
					$media_key = 'video';
				} elseif ( isset( $params['document'] ) ) {
					$media_key = 'document';
				}

				if ( $media_key ) {
					$att_id = isset( $resp['attachment_id'] ) ? $resp['attachment_id'] : null;
					if ( $att_id && function_exists( 'get_attached_file' ) ) {
						$file_path = get_attached_file( $att_id );
						if ( $file_path && file_exists( $file_path ) ) {
							$params[ $media_key ] = new \CURLFile( $file_path );
						}
					}
				}
			}

			try {
				$result = $api->$method( $params );
				if ( $api->is_success( $result ) ) {
					$success = true;
					if ( is_object( $result ) && method_exists( $result, 'get_decoded_body' ) ) {
						$decoded = $result->get_decoded_body();
						if ( isset( $decoded['result']['message_id'] ) ) {
							$reply_to_message_id = $decoded['result']['message_id'];
						}
					}
				} else {
					$has_error = true;
					$error_msg = is_wp_error( $result ) ? $result->get_error_message() : ( is_object( $result ) && method_exists( $result, 'get_response_message' ) ? $result->get_response_message() : '发送失败' );
					set_transient( 'wptg_free_cn_p2tg_error_' . $post->ID, $error_msg, HOUR_IN_SECONDS );
				}
			} catch ( \Throwable $e ) {
				$has_error = true;
				set_transient( 'wptg_free_cn_p2tg_error_' . $post->ID, $e->getMessage(), HOUR_IN_SECONDS );
			}
		}
	}

	remove_filter( 'http_request_args', 'wptg_free_cn_p2tg_http_request_args', 10 );

	if ( $success ) {
		update_post_meta( $post->ID, '_wptg_free_cn_p2tg_sent2tg', current_time( 'mysql' ) );
		// 消费并清理单篇一次性覆盖配置与开关
		$is_gb_metabox = isset( $_POST['_wptg_free_cn_p2tg_is_gb_metabox'] );
		if ( ! $is_gb_metabox ) {
			delete_post_meta( $post->ID, '_wptg_free_cn_p2tg_options' );
			delete_post_meta( $post->ID, '_wptg_free_cn_p2tg_send2tg' );
			delete_post_meta( $post->ID, '_wptg_p2tg_options' );
			delete_post_meta( $post->ID, '_wptg_p2tg_send2tg' );
		}
		if ( ! $has_error ) {
			delete_transient( 'wptg_free_cn_p2tg_error_' . $post->ID );
		}
	}
}

/**
 * 构造 Inline Keyboard
 */
function wptg_free_cn_p2tg_build_keyboard( $button_config, $post ) {
	if ( empty( $button_config ) || ! is_array( $button_config ) ) {
		return null;
	}

	// 检查是否开启内联按钮
	if ( isset( $button_config['inline_url_button'] ) && ! $button_config['inline_url_button'] ) {
		return null;
	}

	$btn_text = '';
	$btn_url  = '';

	if ( ! empty( $button_config['inline_button_text'] ) && ! empty( $button_config['inline_button_url'] ) ) {
		$btn_text = $button_config['inline_button_text'];
		$btn_url  = $button_config['inline_button_url'];
	} elseif ( ! empty( $button_config['text'] ) && ! empty( $button_config['url'] ) ) {
		$btn_text = $button_config['text'];
		$btn_url  = $button_config['url'];
	}

	if ( empty( $btn_text ) || empty( $btn_url ) ) {
		return null;
	}

	$text = wptg_free_cn_p2tg_parse_template( $btn_text, $post );
	$url  = wptg_free_cn_p2tg_parse_template( $btn_url, $post );

	if ( empty( $text ) || empty( $url ) ) {
		return null;
	}

	return array(
		'inline_keyboard' => array(
			array(
				array(
					'text' => $text,
					'url'  => $url,
				),
			),
		),
	);
}

/**
 * 配置 HTTP 请求参数 (增加超时等)
 */
function wptg_free_cn_p2tg_http_request_args( $args, $url ) {
	if ( strpos( $url, 'api.telegram.org' ) !== false ) {
		$args['timeout'] = 30;
		// 如果有代理配置，可以在这里处理
	}
	return $args;
}

/**
 * 清理定时任务
 */
function wptg_free_cn_p2tg_clear_scheduled_hook( $post_id ) {
	$args_str = array( (string) $post_id );
	$args_int = array( (int) $post_id );
	
	// 无论是否存在 Action Scheduler，始终清理 WP-Cron（支持字符串和整数参数）
	wp_clear_scheduled_hook( 'wptg_free_cn_p2tg_delayed_post', $args_str );
	wp_clear_scheduled_hook( 'wptg_free_cn_p2tg_delayed_post', $args_int );

	// Action Scheduler 任务可能属于迁移时保留的非空 group，逐条读取实际 group。
	if ( function_exists( 'as_get_scheduled_actions' ) && function_exists( 'as_unschedule_action' ) && class_exists( 'ActionScheduler_Store' ) ) {
		foreach ( array( $args_str, $args_int ) as $target_args ) {
			$guard = 0;
			do {
				$actions = as_get_scheduled_actions( array(
					'hook'     => 'wptg_free_cn_p2tg_delayed_post',
					'args'     => $target_args,
					'status'   => ActionScheduler_Store::STATUS_PENDING,
					'per_page' => 100,
					'offset'   => 0,
				) );
				$removed = false;
				foreach ( (array) $actions as $action ) {
					$group = method_exists( $action, 'get_group' ) ? $action->get_group() : '';
					if ( as_unschedule_action( 'wptg_free_cn_p2tg_delayed_post', $target_args, $group ) ) {
						$removed = true;
					}
				}
				$guard++;
			} while ( $removed && $guard < 100 );
		}
	} elseif ( function_exists( 'as_unschedule_action' ) ) {
		while ( as_unschedule_action( 'wptg_free_cn_p2tg_delayed_post', $args_str ) ) {}
		while ( as_unschedule_action( 'wptg_free_cn_p2tg_delayed_post', $args_int ) ) {}
	}
}

/**
 * 安排定时任务
 */
function wptg_free_cn_p2tg_schedule_event( $post_id, $timestamp ) {
	$args = array( (string) $post_id );
	
	wptg_free_cn_p2tg_clear_scheduled_hook( $post_id );
	
	if ( function_exists( 'as_schedule_single_action' ) ) {
		$res = as_schedule_single_action( $timestamp, 'wptg_free_cn_p2tg_delayed_post', $args );
		if ( empty( $res ) || is_wp_error( $res ) ) {
			set_transient( 'wptg_free_cn_p2tg_error_' . $post_id, '安排 Action Scheduler 延迟任务失败', HOUR_IN_SECONDS );
			return false;
		}
	} else {
		$res = wp_schedule_single_event( $timestamp, 'wptg_free_cn_p2tg_delayed_post', $args );
		if ( false === $res || is_wp_error( $res ) ) {
			set_transient( 'wptg_free_cn_p2tg_error_' . $post_id, '安排 WP-Cron 延迟任务失败', HOUR_IN_SECONDS );
			return false;
		}
	}
	return true;
}

/**
 * cURL 上传本地文件钩子
 */
add_action( 'http_api_curl', 'wptg_free_cn_p2tg_http_api_curl', 10, 3 );
function wptg_free_cn_p2tg_http_api_curl( $handle, $r, $url ) {
	if ( strpos( (string) $url, 'api.telegram.org' ) === false ) {
		return;
	}
	if ( ! wptg_free_cn_send_files_by_url() && ! empty( $r['body'] ) && is_array( $r['body'] ) ) {
		$types = array( 'animation', 'photo', 'audio', 'video', 'document' );
		foreach ( $types as $type ) {
			if ( empty( $r['body'][ $type ] ) ) {
				continue;
			}
			if ( $r['body'][ $type ] instanceof \CURLFile ) {
				curl_setopt( $handle, CURLOPT_POSTFIELDS, $r['body'] );
				break;
			}
			if ( is_string( $r['body'][ $type ] ) && file_exists( $r['body'][ $type ] ) ) {
				$r['body'][ $type ] = curl_file_create( $r['body'][ $type ] );
				curl_setopt( $handle, CURLOPT_POSTFIELDS, $r['body'] );
				break;
			}
		}
	}
}
