<?php
/**
 * Post to Telegram Template Parser
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 获取支持的宏键
 *
 * @return array
 */
function wptg_free_cn_p2tg_get_macro_keys() {
	return array(
		'post_title', 'post_excerpt', 'post_author', 'post_content',
		'post_date', 'post_date_gmt', 'post_id', 'post_modified_date',
		'post_modified_date_gmt', 'post_modified_time', 'post_modified_time_gmt',
		'post_slug', 'post_time', 'post_time_gmt', 'post_type', 'post_type_label',
		'short_url', 'full_url', 'featured_image_url',
		// 别名
		'id', 'ID', 'title', 'slug', 'post_name', 'author', 'excerpt', 'content'
	);
}

/**
 * 解析模板
 *
 * @param string  $template 模板字符串
 * @param WP_Post $post     WordPress文章对象
 * @param array   $options  配置选项
 * @return string
 */
function wptg_free_cn_p2tg_parse_template( $template, $post, $options = array() ) {
	if ( empty( $template ) ) {
		return '';
	}

	// 1. 标准化模板
	$template = wptg_free_cn_p2tg_normalize_template( $template, $post );

	// 2. bypass wpautop for the_content如果包含在模板中
	// (在获取字段时处理)

	// 找出所有宏
	preg_match_all( '/\{([a-zA-Z0-9_:-]+)\}/', $template, $matches );
	$macros_found = ! empty( $matches[1] ) ? array_unique( $matches[1] ) : array();

	$macro_values = array();

	// 3. 解析宏的值
	foreach ( $macros_found as $macro ) {
		// 跳过 encode 和特殊宏，它们在后面处理
		if ( strpos( $macro, 'encode:' ) === 0 || $macro === 'remove_line' ) {
			continue;
		}

		$macro_values[ $macro ] = wptg_free_cn_p2tg_get_field( $macro, $post, $options );
	}

	// 4. 处理条件逻辑 [if {macro}][consequence][alternative]
	$template = wptg_free_cn_p2tg_process_template_logic( $template, $macro_values );

	// 5. 替换宏
	foreach ( $macro_values as $macro => $value ) {
		$template = str_replace( '{' . $macro . '}', $value, $template );
	}

	// 6. 处理 encode URL 编码
	$template = preg_replace_callback( '/\{encode:(.*?)\}/is', function ( $matches ) {
		return rawurlencode( $matches[1] );
	}, $template );

	// 7. 处理 {remove_line} 和连续空行
	$template = preg_replace( '/(?:\A|[\n\r]).*?\{remove_line\}.*/u', '', $template );
	
	// 移除3个以上的连续换行符
	$template = preg_replace( "/\n{3,}/", "\n\n", $template );

	return trim( $template );
}

/**
 * 标准化模板 (向后兼容)
 *
 * @param string  $template 模板字符串
 * @param WP_Post $post     文章对象
 * @return string
 */
function wptg_free_cn_p2tg_normalize_template( $template, $post ) {
	$is_woo = 'product' === $post->post_type;

	$replacements = array(
		'{tags}'       => $is_woo ? '{terms:product_tag}' : '{terms:post_tag}',
		'{categories}' => $is_woo ? '{terms:product_cat}' : '{terms:category}',
	);

	return strtr( $template, $replacements );
}

/**
 * 处理模板逻辑 [if {macro}][consequence][alternative]
 *
 * @param string $template     模板
 * @param array  $macro_values 宏对应的值
 * @return string
 */
function wptg_free_cn_p2tg_process_template_logic( $template, $macro_values ) {
	// 匹配 [if {macro}][consequence] 或 [if {macro}][consequence][alternative]
	$pattern = '/\[if\s+\{([a-zA-Z0-9_:-]+)\}\]\[(.*?)\](?:\[(.*?)\])?/s';

	return preg_replace_callback( $pattern, function ( $matches ) use ( $macro_values ) {
		$macro       = $matches[1];
		$consequence = $matches[2];
		$alternative = isset( $matches[3] ) ? $matches[3] : '';

		$condition = isset( $macro_values[ $macro ] ) && trim( $macro_values[ $macro ] ) !== '';

		return $condition ? $consequence : $alternative;
	}, $template );
}

/**
 * 提取文章字段的值
 *
 * @param string  $field   字段名/宏名
 * @param WP_Post $post    文章对象
 * @param array   $options 配置选项
 * @return string
 */
function wptg_free_cn_p2tg_get_field( $field, $post, $options = array() ) {
	$value = '';

	// 处理别名
	$aliases = array(
		'id'        => 'post_id',
		'ID'        => 'post_id',
		'title'     => 'post_title',
		'slug'      => 'post_slug',
		'post_name' => 'post_slug',
		'author'    => 'post_author',
		'excerpt'   => 'post_excerpt',
		'content'   => 'post_content',
	);

	if ( isset( $aliases[ $field ] ) ) {
		$field = $aliases[ $field ];
	}

	// 解析 terms: taxonomy
	if ( strpos( $field, 'terms:' ) === 0 ) {
		$taxonomy = substr( $field, 6 );
		return wptg_free_cn_p2tg_get_terms( $taxonomy, $post, $options );
	}

	// 解析 cf: custom_field
	if ( strpos( $field, 'cf:' ) === 0 ) {
		$parts     = explode( ':', $field );
		$meta_key  = $parts[1];
		$modifier  = isset( $parts[2] ) ? $parts[2] : '';
		
		$meta_value = get_post_meta( $post->ID, $meta_key, true );
		
		if ( '__debug__' === $modifier ) {
			return print_r( $meta_value, true );
		}
		
		if ( 'json' === $modifier ) {
			return wp_json_encode( $meta_value );
		}
		
		if ( is_array( $meta_value ) ) {
			return implode( ', ', $meta_value );
		}
		
		return (string) $meta_value;
	}

	switch ( $field ) {
		case 'post_id':
			$value = $post->ID;
			break;

		case 'post_title':
			$value = get_the_title( $post );
			$value = wptg_free_cn_prepare_content( $value );
			break;

		case 'post_excerpt':
			$excerpt_source   = wptg_free_cn_get_option( 'p2tg.excerpt_source', 'post_excerpt' );
			$excerpt_length   = wptg_free_cn_get_option( 'p2tg.excerpt_length', 55 );
			$preserve_eol     = wptg_free_cn_get_option( 'p2tg.excerpt_preserve_eol', false );
			
			if ( 'before_more' === $excerpt_source ) {
				if ( function_exists( 'get_extended' ) ) {
					$parts = get_extended( $post->post_content );
					$raw_excerpt = $parts['main'];
				} elseif ( preg_match( '/<!--more(.*?)?-->/', $post->post_content, $m ) ) {
					$parts = explode( $m[0], $post->post_content, 2 );
					$raw_excerpt = $parts[0];
				} else {
					$raw_excerpt = $post->post_content;
				}
			} elseif ( 'post_content' === $excerpt_source ) {
				$raw_excerpt = $post->post_content;
			} else {
				$raw_excerpt = $post->post_excerpt;
				if ( empty( $raw_excerpt ) ) {
					$raw_excerpt = $post->post_content;
				}
			}
			
			// 剥离短代码
			$raw_excerpt = strip_shortcodes( $raw_excerpt );
			
			// 应用过滤器
			$raw_excerpt = apply_filters( 'the_excerpt', $raw_excerpt );
			$raw_excerpt = str_replace( ']]>', ']]&gt;', $raw_excerpt );
			
			// 准备内容 (移除 HTML 等)
			$parse_mode   = wptg_free_cn_valid_parse_mode( wptg_free_cn_get_option( 'p2tg.parse_mode', 'none' ) );
			$format_to    = ( 'HTML' === $parse_mode ) ? 'HTML' : 'text';
			$raw_excerpt  = wptg_free_cn_prepare_content( $raw_excerpt, array(
				'format_to'    => $format_to,
				'preserve_eol' => (bool) $preserve_eol,
				'limit'        => 0,
			) );
			
			// 智能截断
			$value = wptg_free_cn_smart_trim_excerpt( $raw_excerpt, array( 'length' => $excerpt_length, 'preserve_eol' => $preserve_eol ) );
			break;

		case 'post_content':
			$content = $post->post_content;
			
			// 转换换行
			if ( ! has_filter( 'the_content', 'wpautop' ) ) {
				$content = wpautop( $content );
			}
			
			// 剥离短代码并应用过滤器
			$content = strip_shortcodes( $content );
			$content = apply_filters( 'the_content', $content );
			$content = str_replace( ']]>', ']]&gt;', $content );
			
			$parse_mode = wptg_free_cn_valid_parse_mode( wptg_free_cn_get_option( 'p2tg.parse_mode', 'none' ) );
			$format_to  = ( 'HTML' === $parse_mode ) ? 'HTML' : 'text';
			$value      = wptg_free_cn_prepare_content( $content, array(
				'format_to'    => $format_to,
				'preserve_eol' => true,
				'limit'        => 0,
			) );
			break;

		case 'post_author':
			$author_id = $post->post_author;
			if ( $author_id ) {
				$value = get_the_author_meta( 'display_name', $author_id );
			}
			break;

		case 'post_date':
		case 'post_time':
		case 'post_date_gmt':
		case 'post_time_gmt':
		case 'post_modified_date':
		case 'post_modified_time':
		case 'post_modified_date_gmt':
		case 'post_modified_time_gmt':
			$time_string = '';
			$format = '';
			
			if ( strpos( $field, 'date' ) !== false ) {
				$format = get_option( 'date_format' );
			} else {
				$format = get_option( 'time_format' );
			}
			
			$is_gmt = strpos( $field, 'gmt' ) !== false;
			$is_modified = strpos( $field, 'modified' ) !== false;
			
			if ( $is_modified ) {
				$time_string = $is_gmt ? $post->post_modified_gmt : $post->post_modified;
			} else {
				$time_string = $is_gmt ? $post->post_date_gmt : $post->post_date;
			}
			
			$value = mysql2date( $format, $time_string );
			break;

		case 'post_slug':
			$value = $post->post_name;
			break;

		case 'post_type':
			$value = $post->post_type;
			break;

		case 'post_type_label':
			$post_type_obj = get_post_type_object( $post->post_type );
			if ( $post_type_obj ) {
				$value = $post_type_obj->labels->singular_name;
			}
			break;

		case 'short_url':
			$value = wp_get_shortlink( $post->ID, 'post', true );
			if ( empty( $value ) ) {
				$value = get_permalink( $post->ID );
			}
			break;

		case 'full_url':
			$value = get_permalink( $post->ID );
			break;

		case 'featured_image_url':
			if ( has_post_thumbnail( $post->ID ) ) {
				$value = get_the_post_thumbnail_url( $post->ID, 'full' );
			}
			break;
	}

	return apply_filters( 'wptg_free_cn_p2tg_get_field', (string) $value, $field, $post, $options );
}

/**
 * 获取文章分类法并格式化
 *
 * @param string  $taxonomy 分类法
 * @param WP_Post $post     文章对象
 * @param array   $options  配置选项
 * @return string
 */
function wptg_free_cn_p2tg_get_terms( $taxonomy, $post, $options ) {
	$terms = get_the_terms( $post->ID, $taxonomy );
	
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '';
	}

	$is_hierarchical = is_taxonomy_hierarchical( $taxonomy );
	$cats_as_tags    = wptg_free_cn_get_option( 'p2tg.cats_as_tags', false );
	
	$term_names = array();

	if ( $is_hierarchical && ! $cats_as_tags ) {
		// 层级显示: Term 1 | Term 2
		foreach ( $terms as $term ) {
			$term_names[] = $term->name;
		}
		return implode( ' | ', $term_names );
	} else {
		// 标签显示: #tag1 #tag2
		foreach ( $terms as $term ) {
			$sanitized = wptg_free_cn_sanitize_hashtag( $term->name );
			if ( ! empty( $sanitized ) ) {
				$term_names[] = '#' . $sanitized;
			}
		}
		return implode( ' ', $term_names );
	}
}
