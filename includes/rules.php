<?php
/**
 * Post to Telegram Rules Evaluation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 评估规则以确定是否应用
 *
 * @param array   $rules 规则配置组
 * @param WP_Post $post  WordPress 文章对象
 * @return bool
 */
function wptg_free_cn_p2tg_rules_apply( $rules, $post ) {
	if ( empty( $rules ) || ! is_array( $rules ) ) {
		return true;
	}

	$post_rules_data = array(
		'post'        => array( (string) $post->ID ),
		'post_author' => array( (string) $post->post_author ),
	);

	// 获取支持的文章格式
	if ( current_theme_supports( 'post-formats' ) ) {
		$post_format = get_post_format( $post->ID );
		$post_rules_data['post_format'] = array( $post_format ? $post_format : 'standard' );
	}

	// 初始化分类法数据
	$taxonomies = get_object_taxonomies( $post->post_type );
	foreach ( $taxonomies as $taxonomy ) {
		$terms = get_the_terms( $post->ID, $taxonomy );
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			$term_ids = wp_list_pluck( $terms, 'term_id' );
			
			// 对于层级分类法，可以选择包含子项
			$include_children = apply_filters( 'wptg_free_cn_p2tg_rules_include_children', is_taxonomy_hierarchical( $taxonomy ), $taxonomy, $post );
			
			if ( $include_children ) {
				$ancestors = array();
				foreach ( $term_ids as $term_id ) {
					$ancestors = array_merge( $ancestors, get_ancestors( $term_id, $taxonomy, 'taxonomy' ) );
				}
				$term_ids = array_unique( array_merge( $term_ids, $ancestors ) );
			}
			
			$term_ids = array_map( 'strval', $term_ids );
			
			if ( 'category' === $taxonomy || 'post_tag' === $taxonomy ) {
				$post_rules_data[ $taxonomy ] = $term_ids;
			}
			$post_rules_data[ 'tax:' . $taxonomy ] = $term_ids;
		}
	}

	// 允许通过过滤器修改文章规则数据
	$post_rules_data = apply_filters( 'wptg_free_cn_p2tg_post_rules_data', $post_rules_data, $post );

	// 组之间是 OR 关系
	foreach ( $rules as $group ) {
		if ( empty( $group ) || ! is_array( $group ) ) {
			continue;
		}

		$group_match = true;

		// 组内规则是 AND 关系
		foreach ( $group as $rule ) {
			$param    = $rule['param'];
			$operator = $rule['operator'];
			
			// 兼容处理 values (数组或对象列表) 与 value (单个或数组)
			$raw_values = array();
			if ( isset( $rule['values'] ) ) {
				$raw_values = (array) $rule['values'];
			} elseif ( isset( $rule['value'] ) ) {
				$raw_values = (array) $rule['value'];
			}

			// 如果数组元素为对象/关联数组（如 ['value' => '...', 'label' => '...']），提取其实际值
			if ( ! empty( $raw_values ) && is_array( reset( $raw_values ) ) ) {
				$values = wp_list_pluck( $raw_values, 'value' );
			} else {
				$values = $raw_values;
			}
			$values = array_map( 'strval', (array) $values );

			$post_values = isset( $post_rules_data[ $param ] ) ? (array) $post_rules_data[ $param ] : array();

			$intersect = array_intersect( $values, $post_values );

			if ( 'in' === $operator ) {
				if ( empty( $intersect ) ) {
					$group_match = false;
					break;
				}
			} elseif ( 'not_in' === $operator ) {
				if ( ! empty( $intersect ) ) {
					$group_match = false;
					break;
				}
			} else {
				// 自定义操作符支持
				$match = apply_filters( 'wptg_free_cn_p2tg_rule_operator_match', null, $rule, $post_rules_data, $post );
				if ( $match !== null ) {
					if ( ! $match ) {
						$group_match = false;
						break;
					}
				} else {
					$group_match = false;
					break;
				}
			}
		}

		if ( $group_match ) {
			return true;
		}
	}

	return false;
}

/**
 * 获取可用的规则类型
 *
 * @return array
 */
function wptg_free_cn_p2tg_get_rule_types() {
	$types = array(
		'post'        => __( 'Post ID', 'wptelegram-free-cn' ),
		'category'    => __( 'Category', 'wptelegram-free-cn' ),
		'post_tag'    => __( 'Tag', 'wptelegram-free-cn' ),
		'post_author' => __( 'Post Author', 'wptelegram-free-cn' ),
	);

	if ( current_theme_supports( 'post-formats' ) ) {
		$types['post_format'] = __( 'Post Format', 'wptelegram-free-cn' );
	}

	$taxonomies = get_taxonomies( array(
		'public'   => true,
		'_builtin' => false,
	), 'objects' );

	foreach ( $taxonomies as $taxonomy ) {
		$types[ 'tax:' . $taxonomy->name ] = $taxonomy->label;
	}

	return apply_filters( 'wptg_free_cn_p2tg_rule_types', $types );
}
