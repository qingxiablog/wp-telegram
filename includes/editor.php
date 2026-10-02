<?php
/**
 * Editor Module
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



function wptg_free_cn_p2tg_show_post_edit_switch() {
    $bot_token = wptg_free_cn_get_option( 'bot_token' );
    $p2tg_options = wptg_free_cn_get_option( 'p2tg', array() );
    $post_edit_switch = ! empty( $p2tg_options['post_edit_switch'] );
    return ! empty( $bot_token ) && $post_edit_switch;
}

function wptg_free_cn_p2tg_send2tg_default( $post = null ) {
    $p2tg_options = wptg_free_cn_get_option( 'p2tg', array() );
    $send_when = isset( $p2tg_options['send_when'] ) ? (array) $p2tg_options['send_when'] : array( 'new' );
    
    if ( ! $post || $post->post_status === 'auto-draft' ) {
        return in_array( 'new', $send_when, true );
    }
    
    $sent = wptg_free_cn_p2tg_get_sent2tg( $post->ID );
    if ( ! $sent ) {
        return in_array( 'new', $send_when, true );
    }
    
    return in_array( 'existing', $send_when, true );
}

function wptg_free_cn_p2tg_add_post_edit_switch( $post ) {
    if ( ! wptg_free_cn_p2tg_show_post_edit_switch() ) return;
    $checked = wptg_free_cn_p2tg_send2tg_default( $post ) ? 'checked="checked"' : '';
    echo '<div class="misc-pub-section misc-pub-wptg-p2tg">';
    echo '<input type="hidden" name="_wptg_free_cn_p2tg_send2tg" value="no">';
    echo '<label><input type="checkbox" name="_wptg_free_cn_p2tg_send2tg" value="yes" ' . $checked . '> 本次发送到 Telegram</label>';
    echo '</div>';
}

function wptg_free_cn_p2tg_post_edit_form_hidden_input( $post ) {
    echo '<input type="hidden" name="_wptg_free_cn_p2tg_from_web" value="1">';
    wp_nonce_field( 'wptg_free_cn_p2tg_metabox_' . ( $post ? $post->ID : 0 ), '_wptg_free_cn_p2tg_nonce' );
}

function wptg_free_cn_p2tg_block_editor_hidden_fields( $fields ) {
    $fields['_wptg_free_cn_p2tg_is_gb_metabox'] = '1';
    return $fields;
}

function wptg_free_cn_p2tg_add_override_metabox() {
    $post_types = get_post_types( array( 'public' => true ) );
    foreach ( $post_types as $post_type ) {
        add_meta_box(
            'wptg_free_cn_p2tg_override',
            'Telegram 独立推送配置（覆盖全局）',
            'wptg_free_cn_p2tg_render_override_metabox',
            $post_type,
            'advanced',
            'default'
        );
    }
}

function wptg_free_cn_p2tg_render_override_metabox( $post ) {
    $options = wptg_free_cn_p2tg_get_saved_options( $post->ID );
    if ( ! is_array( $options ) ) {
        $options = array();
    }
    $channels = isset( $options['channels'] ) ? ( is_array( $options['channels'] ) ? implode( "\n", $options['channels'] ) : $options['channels'] ) : '';
    $template = isset( $options['message_template'] ) ? $options['message_template'] : ( isset( $options['template'] ) ? $options['template'] : '' );
    $delay    = isset( $options['delay'] ) ? $options['delay'] : '';
    $files    = isset( $options['files'] ) ? ( is_array( $options['files'] ) ? implode( "\n", $options['files'] ) : $options['files'] ) : '';
    $override = ! empty( $options['override_switch'] );
    ?>
    <p>
        <label>
            <input type="checkbox" name="_wptg_free_cn_p2tg_override_switch" value="1" <?php checked( $override ); ?>>
            <strong>启用本篇独立配置（不使用全局设置）</strong>
        </label>
    </p>
    <p><label>接收目标（每行一个，如 @channel 或 聊天ID:话题ID | 备注）:<br/><textarea name="_wptg_free_cn_p2tg_channels" rows="3" style="width:100%"><?php echo esc_textarea( $channels ); ?></textarea></label></p>
    <p><label>消息模板（留空使用全局）:<br/><textarea name="_wptg_free_cn_p2tg_message_template" rows="4" style="width:100%"><?php echo esc_textarea( $template ); ?></textarea></label></p>
    <p><label>延迟发送分钟数 (0 为立即发送):<br/><input type="number" step="0.1" name="_wptg_free_cn_p2tg_delay" value="<?php echo esc_attr( $delay ); ?>" style="width:120px"></label></p>
    <p><label><input type="checkbox" name="_wptg_free_cn_p2tg_disable_notification" value="1" <?php checked( ! empty( $options['disable_notification'] ) ); ?>> 静默发送（不响铃）</label></p>
    <p><label><input type="checkbox" name="_wptg_free_cn_p2tg_send_featured_image" value="1" <?php checked( ! empty( $options['send_featured_image'] ) ); ?>> 发送特色图片</label></p>
    <p><label>附加文件/媒体 (每行一个媒体 URL 或附件 ID):<br/><textarea name="_wptg_free_cn_p2tg_files" rows="2" style="width:100%"><?php echo esc_textarea( $files ); ?></textarea></label></p>
    <?php
}

function wptg_free_cn_p2tg_add_instant_post_action( $actions, $post ) {
    if ( ! wptg_free_cn_p2tg_show_post_edit_switch() ) return $actions;
    $url = wp_nonce_url( admin_url( 'post.php?action=wptg_free_cn_instant_post&post=' . $post->ID ), 'wptg_free_cn_instant_post_' . $post->ID );
    $actions['wptg_instant_post'] = '<a href="' . esc_url( $url ) . '">发送到 Telegram</a>';
    return $actions;
}

function wptg_free_cn_p2tg_handle_instant_post() {
    if ( isset( $_GET['action'], $_GET['post'], $_GET['_wpnonce'] ) && $_GET['action'] === 'wptg_free_cn_instant_post' ) {
        $post_id = intval( $_GET['post'] );
        if ( wp_verify_nonce( $_GET['_wpnonce'], 'wptg_free_cn_instant_post_' . $post_id ) ) {
            $post = get_post( $post_id );
            if ( $post && current_user_can( 'edit_post', $post_id ) ) {
                wptg_free_cn_p2tg_send_post( $post, 'instant', true );
            }
            wp_redirect( admin_url( 'edit.php?post_type=' . get_post_type( $post_id ) . '&wptg_instant_sent=1' ) );
            exit;
        }
    }
}

function wptg_free_cn_p2tg_hook_into_rest_pre_insert() {
    $post_types = get_post_types( array( 'public' => true, 'show_in_rest' => true ) );
    foreach ( $post_types as $post_type ) {
        add_filter( "rest_pre_insert_{$post_type}", function( $prepared_post, $request ) use ( $post_type ) {
            do_action( "wptg_free_cn_rest_pre_insert_{$post_type}", $prepared_post, $request );
            return $prepared_post;
        }, 10, 2 );
    }
}

/**
 * 将编辑器使用的单篇配置注册到 REST meta，确保保存后重新打开可回读。
 */
function wptg_free_cn_p2tg_register_rest_meta() {
    if ( ! function_exists( 'register_post_meta' ) ) {
        return;
    }
    $post_types = get_post_types( array( 'public' => true, 'show_in_rest' => true ) );
    foreach ( $post_types as $post_type ) {
        $auth = function( $allowed, $meta_key, $post_id ) {
            return current_user_can( 'edit_post', $post_id );
        };
        register_post_meta( $post_type, '_wptg_free_cn_p2tg_send2tg', array(
            'single'        => true,
            'type'          => 'string',
            'show_in_rest'  => true,
            'auth_callback' => $auth,
        ) );
        register_post_meta( $post_type, '_wptg_free_cn_p2tg_options', array(
            'single'        => true,
            'type'          => 'object',
            'show_in_rest'  => true,
            'auth_callback' => $auth,
        ) );
    }
}
add_action( 'init', 'wptg_free_cn_p2tg_register_rest_meta', 20 );

function wptg_free_cn_p2tg_enqueue_editor_assets() {
    if ( ! wptg_free_cn_p2tg_show_post_edit_switch() ) return;
    wp_enqueue_script(
        'wptg-free-cn-editor',
        plugin_dir_url( dirname(__FILE__) ) . 'assets/editor.js',
        array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data' ),
        '1.0.0',
        true
    );
    wp_localize_script( 'wptg-free-cn-editor', 'wptgFreeCNEditor', array(
        'defaultSend2tg' => wptg_free_cn_p2tg_send2tg_default() ? 'yes' : 'no',
        'restBase'       => ( function_exists( 'get_current_screen' ) && get_current_screen() && ! empty( get_current_screen()->post_type ) && get_post_type_object( get_current_screen()->post_type ) ) ? get_post_type_object( get_current_screen()->post_type )->rest_base : 'posts',
    ) );
}
