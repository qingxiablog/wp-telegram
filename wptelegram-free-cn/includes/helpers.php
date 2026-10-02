<?php
/**
 * 辅助函数 (Helper Functions)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 获取插件的所有选项
 */
function wptg_free_cn_options() {
    $defaults = function_exists('wptg_free_cn_get_default_values') ? wptg_free_cn_get_default_values() : [];
    $options  = get_option( 'wptg_free_cn_options', [] );
    return wp_parse_args( $options, $defaults );
}

/**
 * 获取嵌套选项的值 (支持点分法)
 */
function wptg_free_cn_get_option( $path, $default = '' ) {
    $options = wptg_free_cn_options();
    $keys = explode( '.', $path );
    
    $value = $options;
    foreach ( $keys as $key ) {
        if ( is_array( $value ) && isset( $value[ $key ] ) ) {
            $value = $value[ $key ];
        } else {
            return $default;
        }
    }
    return $value;
}

/**
 * 更新插件选项
 */
function wptg_free_cn_update_options( $data ) {
    $current = get_option( 'wptg_free_cn_options', [] );
    if ( ! is_array( $current ) ) {
        $current = [];
    }
    // 递归合并数组
    $updated = array_replace_recursive( $current, $data );
    update_option( 'wptg_free_cn_options', $updated );
}

/**
 * 获取 Nonce 名称
 */
function wptg_free_cn_nonce_name( $action = '', $post_id = 0 ) {
    if ( ! empty( $action ) && ! empty( $post_id ) ) {
        return 'wptg_free_cn_' . $action . '_' . $post_id;
    }
    return 'wptg_free_cn_nonce';
}

/**
 * 验证解析模式 (Parse Mode)
 */
function wptg_free_cn_valid_parse_mode( $mode ) {
    if ( empty( $mode ) || strtolower( $mode ) === 'none' ) {
        return '';
    }
    return 'HTML';
}

/**
 * 净化消息模板，保留 {macros}、允许的 HTML 标签和换行符
 */
function wptg_free_cn_sanitize_message_template( $template ) {
    $allowed_html = [
        'b'      => [],
        'strong' => [],
        'i'      => [],
        'em'     => [],
        'u'      => [],
        'ins'    => [],
        's'      => [],
        'strike' => [],
        'del'    => [],
        'a'      => [ 'href' => [] ],
        'code'   => [ 'class' => [] ],
        'pre'    => [],
    ];
    
    // 暂存大括号宏以免被 wp_kses 清除
    $template = preg_replace_callback('/\{[a-zA-Z0-9_]+\}/', function($matches) {
        return '%%WPTGMACRO%%' . base64_encode($matches[0]) . '%%WPTGMACRO%%';
    }, $template);

    $template = wp_kses( $template, $allowed_html );

    // 恢复大括号宏
    $template = preg_replace_callback('/%%WPTGMACRO%%(.*?)%%WPTGMACRO%%/', function($matches) {
        return base64_decode($matches[1]);
    }, $template);

    return $template;
}

/**
 * 获取 Telegram 文本最大长度
 */
function wptg_free_cn_get_max_text_length( $for = 'text', $padding = 20 ) {
    $max = ( $for === 'caption' ) ? 1024 : 4096;
    return $max - $padding;
}

/**
 * 检查是否应通过 URL 发送文件
 */
function wptg_free_cn_send_files_by_url() {
    return (bool) wptg_free_cn_get_option( 'advanced.send_files_by_url', true );
}

/**
 * 递归过滤输入数据
 */
function wptg_free_cn_sanitize( $input, $typefy = false ) {
    if ( is_array( $input ) ) {
        foreach ( $input as $key => $value ) {
            $input[ $key ] = wptg_free_cn_sanitize( $value, $typefy );
        }
    } else {
        $input = sanitize_text_field( $input );
        if ( $typefy ) {
            if ( is_numeric( $input ) ) {
                $input = ( strpos( $input, '.' ) !== false ) ? (float) $input : (int) $input;
            } elseif ( in_array( strtolower( (string) $input ), [ 'true', 'false' ], true ) ) {
                $input = filter_var( $input, FILTER_VALIDATE_BOOLEAN );
            }
        }
    }
    return $input;
}

/**
 * 准备内容，将 HTML 转换为 Telegram 兼容的格式
 */
function wptg_free_cn_prepare_content( $content, $options = [] ) {
    if ( ! is_array( $options ) ) {
        if ( is_bool( $options ) ) {
            $options = [ 'preserve_eol' => ! $options ];
        } else {
            $options = [];
        }
    }

    $defaults = [
        'elipsis'         => '…',
        'format_to'       => 'text',
        'id'              => 'default',
        'limit'           => 0,
        'limit_by'        => 'words',
        'text_hyperlinks' => 'strip',
        'preserve_eol'    => true,
    ];
    $options = wp_parse_args( $options, $defaults );

    if ( class_exists( '\WPSocio\TelegramFormatText\HtmlConverter' ) ) {
        try {
            $converter = new \WPSocio\TelegramFormatText\HtmlConverter( $options );
            $content = $converter->convert( $content );
        } catch ( \Throwable $e ) {
            if ( function_exists( 'error_log' ) ) {
                error_log( 'Telegram HtmlConverter Error: ' . $e->getMessage() );
            }
            $content = wp_strip_all_tags( $content );
        }
    } else {
        $content = wp_strip_all_tags( $content );
    }
    return $content;
}

/**
 * 智能裁剪摘要
 */
function wptg_free_cn_smart_trim_excerpt( $content, $options = [] ) {
    if ( is_numeric( $options ) ) {
        $options = [ 'length' => (int) $options ];
    }
    $length       = isset( $options['length'] ) ? (int) $options['length'] : 55;
    $preserve_eol = isset( $options['preserve_eol'] ) ? (bool) $options['preserve_eol'] : true;
    $limit_by     = isset( $options['limit_by'] ) ? $options['limit_by'] : 'words';

    // 处理自定义的 <excerpt> 标签
    if ( preg_match( '/<excerpt>(.*?)<\/excerpt>/is', $content, $matches ) ) {
        return trim( $matches[1] );
    }

    $content = strip_shortcodes( $content );
    $content = wp_strip_all_tags( $content );

    if ( ! $preserve_eol ) {
        $content = str_replace( [ "\r\n", "\r", "\n" ], ' ', $content );
    }

    if ( 'chars' === $limit_by || $length > 300 ) {
        // Character count trimming (for captions and Chinese text)
        if ( mb_strlen( $content ) > $length ) {
            $content = mb_substr( $content, 0, $length );
        }
        return trim( $content );
    }

    $words = preg_split( "/[\n\r\t ]+/", $content, $length + 1, PREG_SPLIT_NO_EMPTY );
    if ( count( $words ) > $length ) {
        array_pop( $words );
        $content = implode( ' ', $words ) . '...';
    } elseif ( count( $words ) <= 1 && mb_strlen( $content ) > $length ) {
        // 针对无空格语言（如中文）
        $content = mb_substr( $content, 0, $length ) . '...';
    }

    return trim( $content );
}

/**
 * 拆分长内容以适应 Telegram 限制
 */
function wptg_free_cn_split_content( $content, $parse_mode = '' ) {
    $limit = wptg_free_cn_get_max_text_length();
    if ( mb_strlen( $content ) <= $limit ) {
        return [ $content ];
    }
    
    $chunks = [];
    while ( mb_strlen( $content ) > 0 ) {
        if ( mb_strlen( $content ) <= $limit ) {
            $chunks[] = $content;
            break;
        }
        
        $chunk = mb_substr( $content, 0, $limit );
        // 尝试在最后一个换行符处分割
        $last_newline = mb_strrpos( $chunk, "\n" );
        if ( $last_newline !== false && $last_newline > ( $limit / 2 ) ) {
            $chunk = mb_substr( $content, 0, $last_newline );
            $content = mb_substr( $content, $last_newline + 1 );
        } else {
            $content = mb_substr( $content, $limit );
        }
        $chunks[] = $chunk;
    }
    
    return $chunks;
}

/**
 * 获取 Telegram 允许的图片限制
 */
function wptg_free_cn_get_image_limits() {
    return [
        'max_filesize' => 10 * 1024 * 1024, // 10MB
        'max_dimensions' => 10000,
        'max_w2h_ratio' => 20,
    ];
}

/**
 * 根据限制获取附件
 */
function wptg_free_cn_get_attachment_by_limits( $id, $limits, $return = 'url' ) {
    $metadata = wp_get_attachment_metadata( $id );
    if ( ! $metadata ) {
        return $return === 'url' ? wp_get_attachment_url( $id ) : wp_get_attachment_image_src( $id, 'full' );
    }

    $file = get_attached_file( $id );
    if ( file_exists( $file ) && filesize( $file ) > $limits['max_filesize'] ) {
        // 若超出限制，尝试返回 large 尺寸或在实际业务中做进一步处理
        $src = wp_get_attachment_image_src( $id, 'large' );
        if ( $src ) {
            return $return === 'url' ? $src[0] : $src;
        }
    }

    return $return === 'url' ? wp_get_attachment_url( $id ) : wp_get_attachment_image_src( $id, 'full' );
}

/**
 * 猜测 Telegram 文件类型 (photo, video, document)
 */
function wptg_free_cn_guess_file_type( $id, $file = '' ) {
    if ( ! $file ) {
        $file = get_attached_file( $id );
    }
    $mime = get_post_mime_type( $id );
    if ( ! $mime && $file ) {
        $mime = wp_check_filetype( $file )['type'];
    }
    
    if ( strpos( $mime, 'image/' ) === 0 ) {
        // Telegram GIF 只能作为 document 发送(除非是特定的 animation)，按常规处理
        return ( $mime === 'image/gif' ) ? 'document' : 'photo';
    } elseif ( strpos( $mime, 'video/' ) === 0 ) {
        return 'video';
    } elseif ( strpos( $mime, 'audio/' ) === 0 ) {
        return 'audio';
    }
    
    return 'document';
}

/**
 * 净化 Hashtag
 */
function wptg_free_cn_sanitize_hashtag( $input ) {
    // 移除不允许在 hashtag 中出现的字符 (非字母、非数字、非下划线)
    $input = preg_replace( '/[^\p{L}\p{N}_]+/u', '_', $input );
    return trim( $input, '_' );
}

/**
 * 闭合未完成的 HTML 标签，保证标签配对平衡
 */
function wptg_free_cn_balance_html_tags( $html ) {
    preg_match_all( '#<(/?[a-zA-Z0-9\-_]+)(?:\s+[^>]*?)?(?:/?)>#', $html, $matches, PREG_OFFSET_CAPTURE );
    $open_tags = [];
    $void_tags = [ 'br', 'hr', 'img' ];

    foreach ( $matches[1] as $idx => $tag_info ) {
        $tag_name = strtolower( $tag_info[0] );
        $full_match = $matches[0][$idx][0];

        if ( str_ends_with( $full_match, '/>' ) || in_array( $tag_name, $void_tags, true ) ) {
            continue;
        }

        if ( str_starts_with( $tag_name, '/' ) ) {
            $tag_name = substr( $tag_name, 1 );
            for ( $i = count( $open_tags ) - 1; $i >= 0; $i-- ) {
                if ( $open_tags[$i] === $tag_name ) {
                    array_splice( $open_tags, $i, 1 );
                    break;
                }
            }
        } else {
            $open_tags[] = $tag_name;
        }
    }

    $closing = '';
    while ( ! empty( $open_tags ) ) {
        $tag = array_pop( $open_tags );
        $closing .= '</' . $tag . '>';
    }

    return $html . $closing;
}

/**
 * 清理截断文本末尾可能破损的 HTML 实体或未完整闭合的标签
 */
function wptg_free_cn_clean_truncated_html_boundary( $slice ) {
    // 避免截断在 HTML 实体内部 (&...;)
    $last_amp = mb_strrpos( $slice, '&' );
    if ( false !== $last_amp && false === mb_strpos( mb_substr( $slice, $last_amp ), ';' ) ) {
        $slice = mb_substr( $slice, 0, $last_amp );
    }

    // 避免截断在标签属性内部 (<...>)
    $last_lt = mb_strrpos( $slice, '<' );
    $last_gt = mb_strrpos( $slice, '>' );
    if ( false !== $last_lt && ( false === $last_gt || $last_gt < $last_lt ) ) {
        $slice = mb_substr( $slice, 0, $last_lt );
    }

    return $slice;
}

/**
 * 文本/HTML 安全截断（保护 HTML 标签平衡、实体完整性与 Emoji）
 */
function wptg_free_cn_safe_truncate( $text, $max_len, $parse_mode = 'none' ) {
    if ( mb_strlen( $text ) <= $max_len ) {
        return $text;
    }

    if ( 'HTML' !== $parse_mode ) {
        return mb_substr( $text, 0, $max_len );
    }

    // 预留闭合标签空间
    $closing_reserve = 50;
    $target_len = max( 1, $max_len - $closing_reserve );
    $slice = mb_substr( $text, 0, $target_len );
    $slice = wptg_free_cn_clean_truncated_html_boundary( $slice );

    $balanced = wptg_free_cn_balance_html_tags( $slice );

    while ( mb_strlen( $balanced ) > $max_len && mb_strlen( $slice ) > 0 ) {
        $slice = mb_substr( $slice, 0, max( 0, mb_strlen( $slice ) - 10 ) );
        $slice = wptg_free_cn_clean_truncated_html_boundary( $slice );
        $balanced = wptg_free_cn_balance_html_tags( $slice );
    }

    return $balanced;
}

