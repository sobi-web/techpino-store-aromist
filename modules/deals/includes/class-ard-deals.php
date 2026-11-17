<?php
/**
 * Which products are deals, and everything a deal card needs about them.
 *
 * A «deal» is a published, visible product with at least one size that is on sale, in stock and
 * purchasable right now. Its card shows the best size (highest %, then the bigger saving).
 *
 * @package AromistDeals
 */

defined( 'ABSPATH' ) || exit;

final class ARD_Deals {

	const CANDIDATES = 'ard_candidates';

	/** Product types never treated as deals (discovery sets have their own pricing and pages). */
	public static function excluded_types(): array {
		return (array) apply_filters( 'ard_excluded_types', array( 'discovery_set', 'grouped', 'external' ) );
	}

	public static function percent( float $regular, float $price ): int {
		if ( $regular <= 0 || $price >= $regular ) {
			return 0;
		}
		return (int) round( ( $regular - $price ) / $regular * 100 );
	}

	/**
	 * Parent ids of every published product with a sale price set (on a variation or itself).
	 * Read from the sale-price meta, not WooCommerce's on-sale lookup: that one only changes when the
	 * product is saved or the daily sales cron runs, so a scheduled sale would appear late.
	 * Dates and stock are checked later, per product.
	 */
	public static function candidate_ids(): array {
		$cached = get_transient( self::CANDIDATES );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col(
			"SELECT DISTINCT IF( p.post_type = 'product_variation', p.post_parent, p.ID )
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_sale_price' AND m.meta_value <> ''
			WHERE p.post_type IN ( 'product', 'product_variation' ) AND p.post_status = 'publish'"
		);
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		set_transient( self::CANDIDATES, $ids, 6 * HOUR_IN_SECONDS );
		return $ids;
	}

	public static function forget(): void {
		delete_transient( self::CANDIDATES );
	}

	/**
	 * Start / end of a sale (timestamps, 0 = unknown). Falls back to the campaign end from the
	 * settings, and for the progress bar's start to «end − N days».
	 *
	 * @param WC_Product $product Variation or simple product.
	 */
	public static function window( $product ): array {
		$to   = $product->get_date_on_sale_to() ? $product->get_date_on_sale_to()->getTimestamp() : 0;
		$from = $product->get_date_on_sale_from() ? $product->get_date_on_sale_from()->getTimestamp() : 0;
		if ( ! $to ) {
			list( $c_from, $c_to ) = ARD_Settings::campaign();
			if ( $c_to ) {
				$to   = $c_to;
				$from = $from ? $from : $c_from;
			}
		}
		if ( $to && ( ! $from || $from >= $to ) ) {
			$from = $to - max( 1, (int) ARD_Settings::get( 'period_days' ) ) * DAY_IN_SECONDS;
		}
		return array( $from, $to );
	}

	/** Name of the size attribute (pa_volume when present, else the first variation attribute). */
	public static function size_attribute( WC_Product $product ): string {
		$attrs = $product->get_variation_attributes();
		if ( isset( $attrs['pa_volume'] ) ) {
			return 'pa_volume';
		}
		$first = array_key_first( $attrs );
		return $first ? (string) $first : '';
	}

	/**
	 * One size (variation, or the simple product itself).
	 *
	 * @param WC_Product $p    Variation or simple product.
	 * @param string     $attr Size attribute name, '' for simple products.
	 */
	public static function size_row( $p, string $attr ): array {
		$regular = (float) wc_get_price_to_display( $p, array( 'price' => $p->get_regular_price() ) );
		$price   = (float) wc_get_price_to_display( $p );
		$pct     = $p->is_on_sale() ? self::percent( $regular, $price ) : 0;
		$window  = $pct ? self::window( $p ) : array( 0, 0 );
		$label   = '';
		$attrs   = array();

		if ( $p->is_type( 'variation' ) ) {
			$attrs = wc_get_product_variation_attributes( $p->get_id() );
			$value = $attrs[ 'attribute_' . sanitize_title( $attr ) ] ?? '';
			if ( '' !== $value ) {
				$term  = taxonomy_exists( $attr ) ? get_term_by( 'slug', $value, $attr ) : false;
				$label = $term ? $term->name : rawurldecode( $value );
			}
		}
		preg_match( '/\d+(?:\.\d+)?/', ARD_Settings::en_digits( $label ), $m );

		$url = $p->is_type( 'variation' ) ? wc_get_product( $p->get_parent_id() )->get_permalink() : $p->get_permalink();
		if ( $attrs ) {
			// The product page opens on this size (WooCommerce reads attribute_* from the URL). The value
			// must be the plain «20-میل»: WooCommerce's wc_clean() strips %-octets, then sanitize_title()
			// turns the plain value back into the stored slug.
			$plain = array_map(
				function ( $v ) {
					return rawurlencode( rawurldecode( (string) $v ) );
				},
				array_filter( $attrs )
			);
			$url   = add_query_arg( $plain, $url );
		}

		return array(
			'vid'     => $p->get_id(),
			'label'   => $label,
			'num'     => isset( $m[0] ) ? (float) $m[0] : null,
			'price'   => $price,
			'regular' => $regular,
			'save'    => max( 0, $regular - $price ),
			'pct'     => $pct,
			'sale'    => $pct > 0,
			'in'      => $p->is_in_stock() && $p->is_purchasable(),
			'from'    => (int) $window[0],
			'to'      => (int) $window[1],
			'attrs'   => $attrs,
			'url'     => $url,
		);
	}

	/**
	 * Deal data for one product, or null when nothing of it is on sale and buyable now.
	 *
	 * @param WC_Product|int $product Product or id.
	 */
	public static function item( $product ): ?array {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( (int) $product );
		}
		if ( ! $product instanceof WC_Product || 'publish' !== $product->get_status() || $product->is_type( 'variation' ) ) {
			return null;
		}
		if ( in_array( $product->get_type(), self::excluded_types(), true ) || ! $product->is_visible() ) {
			return null;
		}

		$sizes = array();
		if ( $product->is_type( 'variable' ) ) {
			$attr = self::size_attribute( $product );
			foreach ( $product->get_children() as $vid ) {
				$v = wc_get_product( $vid );
				if ( ! $v || 'publish' !== $v->get_status() || '' === $v->get_price() ) {
					continue;
				}
				$sizes[] = self::size_row( $v, $attr );
			}
			usort(
				$sizes,
				function ( $a, $b ) {
					if ( null === $a['num'] || null === $b['num'] ) {
						return null === $a['num'] ? ( null === $b['num'] ? $a['price'] <=> $b['price'] : 1 ) : -1;
					}
					return $a['num'] <=> $b['num'];
				}
			);
		} elseif ( '' !== $product->get_price() ) {
			$sizes[] = self::size_row( $product, '' );
		}

		$deal = -1;
		$pcts = array();
		foreach ( $sizes as $i => $s ) {
			if ( ! $s['sale'] || ! $s['in'] ) {
				continue;
			}
			$pcts[] = $s['pct'];
			if ( $deal < 0 || $s['pct'] > $sizes[ $deal ]['pct'] || ( $s['pct'] === $sizes[ $deal ]['pct'] && $s['save'] > $sizes[ $deal ]['save'] ) ) {
				$deal = $i;
			}
		}
		if ( $deal < 0 ) {
			return null;
		}

		return array(
			'id'         => $product->get_id(),
			'name'       => $product->get_name(),
			'english'    => trim( (string) get_post_meta( $product->get_id(), 'english_title', true ) ),
			'url'        => $product->get_permalink(),
			'image_id'   => (int) $product->get_image_id(),
			'type'       => $product->get_type(),
			'sizes'      => $sizes,
			'deal'       => $deal,
			'max_pct'    => max( $pcts ),
			'min_pct'    => min( $pcts ),
			'sale_count' => count( $pcts ),
			'from'       => $sizes[ $deal ]['from'],
			'to'         => $sizes[ $deal ]['to'],
		);
	}

	/**
	 * Deals for a widget / shortcode.
	 *
	 * @param array $args source (all|cats|archive|manual), cats, include, exclude, orderby (discount|ending|newest|random|manual), limit.
	 * @return array{items: array, total: int}
	 */
	public static function query( array $args ): array {
		$args = wp_parse_args(
			$args,
			array(
				'source'  => 'all',
				'cats'    => array(),
				'include' => array(),
				'exclude' => array(),
				'orderby' => 'discount',
				'limit'   => 12,
			)
		);

		static $memo = array();
		$key = md5( wp_json_encode( $args ) . '|' . ( 'archive' === $args['source'] ? get_queried_object_id() : '' ) );
		if ( isset( $memo[ $key ] ) ) {
			return $memo[ $key ];
		}

		$ids = self::candidate_ids();
		if ( 'manual' === $args['source'] ) {
			// Keep the order the admin picked.
			$ids = array_values( array_intersect( array_map( 'absint', (array) $args['include'] ), $ids ) );
		}
		$ids = array_values( array_diff( $ids, array_map( 'absint', (array) $args['exclude'] ) ) );
		if ( ! $ids ) {
			$memo[ $key ] = array( 'items' => array(), 'total' => 0 );
			return $memo[ $key ];
		}

		$visibility = wc_get_product_visibility_term_ids();
		$tax_query  = array();
		if ( ! empty( $visibility['exclude-from-catalog'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'term_taxonomy_id',
				'terms'    => array( (int) $visibility['exclude-from-catalog'] ),
				'operator' => 'NOT IN',
			);
		}
		if ( 'cats' === $args['source'] && $args['cats'] ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => array_map( 'absint', (array) $args['cats'] ),
			);
		}
		if ( 'archive' === $args['source'] && ( is_product_taxonomy() || is_tax() ) ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$tax_query[] = array(
					'taxonomy' => $term->taxonomy,
					'field'    => 'term_id',
					'terms'    => array( $term->term_id ),
				);
			}
		}

		$found = get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => 'publish',
				'post__in'         => $ids,
				'orderby'          => 'post__in',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'tax_query'        => $tax_query ? array_merge( array( 'relation' => 'AND' ), $tax_query ) : array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);

		$items = array();
		foreach ( $found as $id ) {
			$item = self::item( (int) $id );
			if ( $item ) {
				$items[] = $item;
			}
		}

		$soon = function ( $a, $b ) {
			// Sales with an end date first, the soonest first.
			return ( $a['to'] ? $a['to'] : PHP_INT_MAX ) <=> ( $b['to'] ? $b['to'] : PHP_INT_MAX );
		};
		switch ( $args['orderby'] ) {
			case 'ending':
				usort(
					$items,
					function ( $a, $b ) use ( $soon ) {
						return $soon( $a, $b ) ?: $b['max_pct'] <=> $a['max_pct'];
					}
				);
				break;
			case 'newest':
				usort(
					$items,
					function ( $a, $b ) {
						return ( $b['from'] <=> $a['from'] ) ?: $b['id'] <=> $a['id'];
					}
				);
				break;
			case 'random':
				shuffle( $items );
				break;
			case 'manual':
				break;
			default:
				usort(
					$items,
					function ( $a, $b ) use ( $soon ) {
						return ( $b['max_pct'] <=> $a['max_pct'] ) ?: $soon( $a, $b );
					}
				);
		}

		$memo[ $key ] = array(
			'items' => array_slice( $items, 0, max( 1, (int) $args['limit'] ) ),
			'total' => count( $items ),
		);
		return $memo[ $key ];
	}
}
