<?php
// اسنیپت #32: فقط محصولات تخفیف دار
defined( 'ABSPATH' ) || exit;

/**
 * آرومیست: فیلتر «فقط محصولات تخفیف‌دار» برای JetSmartFilters
 */
add_filter( 'jet-smart-filters/query/final-query', function ( $query ) {
	if ( empty( $query['meta_query'] ) || ! is_array( $query['meta_query'] ) || ! function_exists( 'wc_get_product_ids_on_sale' ) ) {
		return $query;
	}

	$on_sale = false;
	$query['meta_query'] = aromist_strip_meta_key( $query['meta_query'], 'aromist_on_sale', $on_sale );

	if ( $on_sale ) {
		$ids = wc_get_product_ids_on_sale();

		if ( ! empty( $query['post__in'] ) ) {
			$ids = array_intersect( (array) $query['post__in'], $ids );
		}

		$query['post__in'] = $ids ? array_values( $ids ) : array( 0 );
	}

	return $query;
} );

function aromist_strip_meta_key( $meta_query, $key, &$found ) {
	foreach ( $meta_query as $i => $clause ) {
		if ( ! is_array( $clause ) ) {
			continue;
		}
		if ( isset( $clause['key'] ) ) {
			if ( $clause['key'] === $key ) {
				$found = true;
				unset( $meta_query[ $i ] );
			}
		} else {
			$meta_query[ $i ] = aromist_strip_meta_key( $clause, $key, $found );
		}
	}
	return $meta_query;
}