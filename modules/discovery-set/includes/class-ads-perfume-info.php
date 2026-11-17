<?php
/**
 * Everything the shop's product page tells about a perfume, for the set page, the builder and
 * its «جزئیات» window:
 *   - English name (meta `english_title`), brand (`product_brand`), short description, rating;
 *   - specs = the product's visible, non-variation attributes (طبع، فصل، حال و هوا، پخش بو، ماندگاری،
 *     موقعیت استفاده…) with their shop labels (the volume attribute is left out);
 *   - olfactory family and top / heart / base notes from the JetEngine relations the product
 *     template uses (found by their objects and names; override with `ads_perfume_relations`).
 *
 * @package AromistDiscoverySet
 */

defined( 'ABSPATH' ) || exit;

/**
 * Perfume info.
 */
final class ADS_Perfume_Info {

	/**
	 * Request cache: id => info.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Relation ids: family, top, heart, base (0 = not found).
	 *
	 * @var array|null
	 */
	private static $relations = null;

	/**
	 * Related post ids per perfume, filled by prime(): id => [ family => [], top => [], … ].
	 *
	 * @var array
	 */
	private static $related = array();

	/**
	 * Clear request caches.
	 */
	public static function reset_cache() {
		self::$cache     = array();
		self::$related   = array();
		self::$relations = null;
	}

	/*
	|--------------------------------------------------------------------------
	| JetEngine relations
	|--------------------------------------------------------------------------
	*/

	/**
	 * Relation ids of the olfactory family and the three note levels.
	 *
	 * @return array [ family, top, heart, base ] => relation id (0 = none).
	 */
	public static function relations() {
		if ( null !== self::$relations ) {
			return self::$relations;
		}
		$out = array(
			'family' => 0,
			'top'    => 0,
			'heart'  => 0,
			'base'   => 0,
		);

		foreach ( self::jet_relations() as $id => $relation ) {
			$parent = (string) $relation->get_args( 'parent_object' );
			$child  = (string) $relation->get_args( 'child_object' );
			$name   = method_exists( $relation, 'get_relation_name' ) ? (string) $relation->get_relation_name() : '';

			if ( 'posts::product' === $child && false !== strpos( $parent, 'olfactory' ) && ! $out['family'] ) {
				$out['family'] = (int) $id;
			} elseif ( 'posts::product' === $parent && false !== strpos( $child, 'note' ) ) {
				if ( false !== mb_strpos( $name, 'اولیه' ) || false !== stripos( $name, 'top' ) ) {
					$out['top'] = $out['top'] ? $out['top'] : (int) $id;
				} elseif ( false !== mb_strpos( $name, 'میانی' ) || false !== stripos( $name, 'heart' ) || false !== stripos( $name, 'middle' ) ) {
					$out['heart'] = $out['heart'] ? $out['heart'] : (int) $id;
				} elseif ( false !== mb_strpos( $name, 'پایانی' ) || false !== stripos( $name, 'base' ) ) {
					$out['base'] = $out['base'] ? $out['base'] : (int) $id;
				}
			}
		}

		/**
		 * JetEngine relation ids used for a perfume's olfactory family and notes.
		 *
		 * @param array $out [ family, top, heart, base ] => relation id.
		 */
		self::$relations = array_map( 'absint', (array) apply_filters( 'ads_perfume_relations', $out ) );
		return self::$relations;
	}

	/**
	 * Active JetEngine relations (empty when JetEngine is not active).
	 *
	 * @return array id => relation object
	 */
	private static function jet_relations() {
		if ( ! function_exists( 'jet_engine' ) ) {
			return array();
		}
		$engine = jet_engine();
		if ( ! $engine || empty( $engine->relations ) || ! method_exists( $engine->relations, 'get_active_relations' ) ) {
			return array();
		}
		$out = array();
		foreach ( (array) $engine->relations->get_active_relations() as $id => $relation ) {
			if ( is_object( $relation ) && method_exists( $relation, 'get_args' ) && method_exists( $relation, 'get_children' ) ) {
				$out[ $id ] = $relation;
			}
		}
		return $out;
	}

	/**
	 * Load the family and notes of many perfumes at once (one query per relation + one for the
	 * titles), e.g. for the builder.
	 *
	 * @param int[] $ids Perfume ids.
	 */
	public static function prime( array $ids ) {
		$ids = array_values( array_diff( array_unique( array_map( 'absint', $ids ) ), array_keys( self::$related ), array( 0 ) ) );
		if ( ! $ids ) {
			return;
		}
		foreach ( $ids as $id ) {
			self::$related[ $id ] = array(
				'family' => array(),
				'top'    => array(),
				'heart'  => array(),
				'base'   => array(),
			);
		}

		$relations = self::relations();
		$objects   = self::jet_relations();
		$titles    = array();
		foreach ( $relations as $key => $rel_id ) {
			if ( ! $rel_id || ! isset( $objects[ $rel_id ] ) ) {
				continue;
			}
			try {
				$rows = 'family' === $key ? $objects[ $rel_id ]->get_parents( $ids, 'all' ) : $objects[ $rel_id ]->get_children( $ids, 'all' );
			} catch ( \Throwable $e ) {
				$rows = array();
			}
			foreach ( (array) $rows as $row ) {
				$row     = (array) $row;
				$perfume = (int) ( 'family' === $key ? ( $row['child_object_id'] ?? 0 ) : ( $row['parent_object_id'] ?? 0 ) );
				$other   = (int) ( 'family' === $key ? ( $row['parent_object_id'] ?? 0 ) : ( $row['child_object_id'] ?? 0 ) );
				if ( isset( self::$related[ $perfume ] ) && $other && ! in_array( $other, self::$related[ $perfume ][ $key ], true ) ) {
					self::$related[ $perfume ][ $key ][] = $other;
					$titles[ $other ]                    = $other;
				}
			}
		}

		// Warm the post cache of all related posts (titles, status) with one query.
		if ( $titles ) {
			_prime_post_caches( array_values( $titles ), false, false );
		}
	}

	/**
	 * Published related posts with their own pages (/notes/…, /olfactory-families/…).
	 *
	 * @param int[] $ids Post ids.
	 * @return array[] [ id, name, url ]
	 */
	private static function links( array $ids ) {
		$out = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post && 'publish' === $post->post_status && '' !== trim( $post->post_title ) ) {
				$out[] = array(
					'id'   => (int) $post->ID,
					'name' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
					'url'  => is_post_publicly_viewable( $post ) ? (string) get_permalink( $post ) : '',
				);
			}
		}
		return $out;
	}

	/**
	 * How many values of a spec are shown (more would break its box). Filter `ads_spec_max_values`.
	 *
	 * @return int
	 */
	public static function spec_limit() {
		return max( 1, (int) apply_filters( 'ads_spec_max_values', 2 ) );
	}

	/**
	 * How many notes of a level are shown before «نمایش بیشتر». Filter `ads_notes_shown`.
	 *
	 * @return int
	 */
	public static function notes_limit() {
		return max( 1, (int) apply_filters( 'ads_notes_shown', 5 ) );
	}

	/**
	 * Images of a perfume that can be shown: its main image, then its gallery.
	 *
	 * @param array $info ADS_Perfume_Info::get().
	 * @return int[]
	 */
	public static function image_ids( array $info ) {
		$ids = array();
		foreach ( array_merge( array( $info['image_id'] ), isset( $info['gallery'] ) ? (array) $info['gallery'] : array() ) as $id ) {
			$id = absint( $id );
			if ( $id && ! in_array( $id, $ids, true ) && wp_get_attachment_image_url( $id, 'woocommerce_single' ) ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/*
	|--------------------------------------------------------------------------
	| Info
	|--------------------------------------------------------------------------
	*/

	/**
	 * All info of a perfume.
	 *
	 * @param int $id Perfume (parent product) id.
	 * @return array Keys: id, name, english, brand, url, image_id, gallery, thumb, image, family
	 *               (names), families [ id, name, url ], notes [top, heart, base] (names),
	 *               note_links [top, heart, base] => [ id, name, url ], specs [ key, label, value,
	 *               values [ name, url ] ], excerpt, rating, reviews, cats.
	 */
	public static function get( $id ) {
		$id = absint( $id );
		if ( isset( self::$cache[ $id ] ) ) {
			return self::$cache[ $id ];
		}
		self::prime( array( $id ) );

		$product = wc_get_product( $id );
		$related = isset( self::$related[ $id ] ) ? self::$related[ $id ] : array();
		$family  = isset( $related['family'] ) ? self::links( $related['family'] ) : array();
		$notes   = array();
		foreach ( array( 'top', 'heart', 'base' ) as $level ) {
			$notes[ $level ] = isset( $related[ $level ] ) ? self::links( $related[ $level ] ) : array();
		}

		$english = trim( (string) get_post_meta( $id, 'english_title', true ) );
		if ( 'english title' === strtolower( $english ) ) {
			$english = ''; // The placeholder value of the field.
		}

		$brands = taxonomy_exists( 'product_brand' ) ? wp_get_post_terms( $id, 'product_brand', array( 'fields' => 'names' ) ) : array();
		$image  = $product ? (int) $product->get_image_id() : 0;

		$info = array(
			'id'         => $id,
			'name'       => $product ? html_entity_decode( $product->get_name(), ENT_QUOTES, 'UTF-8' ) : '#' . $id,
			'english'    => $english,
			'brand'      => is_array( $brands ) && $brands ? html_entity_decode( (string) $brands[0], ENT_QUOTES, 'UTF-8' ) : '',
			'url'        => $product && $product->is_visible() ? $product->get_permalink() : '',
			'image_id'   => $image,
			'gallery'    => $product ? array_values( array_filter( array_map( 'absint', $product->get_gallery_image_ids() ) ) ) : array(),
			'thumb'      => $image ? (string) wp_get_attachment_image_url( $image, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' ),
			'image'      => $image ? (string) wp_get_attachment_image_url( $image, 'woocommerce_single' ) : wc_placeholder_img_src( 'woocommerce_single' ),
			'family'     => ads_join( wp_list_pluck( $family, 'name' ) ),
			'families'   => $family,
			'notes'      => array_map(
				static function ( $links ) {
					return wp_list_pluck( $links, 'name' );
				},
				$notes
			),
			'note_links' => $notes,
			'specs'      => $product ? self::specs( $product ) : array(),
			'excerpt'    => $product ? self::excerpt( $product ) : '',
			'rating'     => $product ? (float) $product->get_average_rating() : 0.0,
			'reviews'    => $product ? (int) $product->get_review_count() : 0,
			'cats'       => $product ? array_map( 'intval', $product->get_category_ids() ) : array(),
		);

		/**
		 * Info of a perfume shown on the set page and in the builder.
		 *
		 * @param array $info Info.
		 * @param int   $id   Perfume id.
		 */
		self::$cache[ $id ] = (array) apply_filters( 'ads_perfume_info', $info, $id );
		return self::$cache[ $id ];
	}

	/**
	 * Visible attributes of a perfume (not the ones used for variations, not the volume).
	 *
	 * @param WC_Product $product Perfume.
	 * @return array[] [ key, label, value (all values joined), values [ name, url ] ] — url is the
	 *                 term's page when the attribute has archives (e.g. /atmosphere-and-characters/…).
	 */
	public static function specs( $product ) {
		$out = array();
		foreach ( $product->get_attributes() as $key => $attribute ) {
			if ( ! $attribute instanceof WC_Product_Attribute || ! $attribute->get_visible() || $attribute->get_variation() ) {
				continue;
			}
			$name = $attribute->get_name();
			if ( in_array( $name, (array) apply_filters( 'ads_hidden_spec_attributes', array( 'pa_volume' ) ), true ) ) {
				continue;
			}
			$values = array();
			if ( $attribute->is_taxonomy() ) {
				$viewable = is_taxonomy_viewable( $name );
				foreach ( wc_get_product_terms( $product->get_id(), $name, array( 'fields' => 'all' ) ) as $term ) {
					$link     = $viewable ? get_term_link( $term ) : '';
					$values[] = array(
						'name' => trim( html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) ),
						'url'  => is_string( $link ) ? $link : '',
					);
				}
			} else {
				foreach ( $attribute->get_options() as $option ) {
					$values[] = array(
						'name' => trim( html_entity_decode( (string) $option, ENT_QUOTES, 'UTF-8' ) ),
						'url'  => '',
					);
				}
			}
			$values = array_values(
				array_filter(
					$values,
					static function ( $value ) {
						return '' !== $value['name'];
					}
				)
			);
			if ( ! $values ) {
				continue;
			}
			$out[] = array(
				'key'    => (string) $key,
				'label'  => html_entity_decode( wc_attribute_label( $name, $product ), ENT_QUOTES, 'UTF-8' ),
				'value'  => ads_join( wp_list_pluck( $values, 'name' ) ),
				'values' => $values,
			);
		}
		return $out;
	}

	/**
	 * Short description as plain text (or the start of the description).
	 *
	 * @param WC_Product $product Perfume.
	 * @return string
	 */
	private static function excerpt( $product ) {
		$text = wp_strip_all_tags( strip_shortcodes( (string) $product->get_short_description() ) );
		if ( '' === trim( $text ) ) {
			$text = wp_strip_all_tags( strip_shortcodes( (string) $product->get_description() ) );
		}
		$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
		return wp_trim_words( $text, 45, '…' );
	}

	/**
	 * Labels of the note levels.
	 *
	 * @return array
	 */
	public static function note_labels() {
		return array(
			'top'   => __( 'نت‌های اولیه', 'aromist-discovery-set' ),
			'heart' => __( 'نت‌های میانی', 'aromist-discovery-set' ),
			'base'  => __( 'نت‌های پایانی', 'aromist-discovery-set' ),
		);
	}

	/**
	 * Info for the builder's «جزئیات» window (JSON-ready, only what the window shows).
	 *
	 * @param int $id Perfume id.
	 * @return array
	 */
	public static function for_js( $id ) {
		$info  = self::get( $id );
		$pairs = static function ( array $links ) {
			return array_map(
				static function ( $link ) {
					return array( $link['name'], $link['url'] );
				},
				$links
			);
		};

		// [ big, thumbnail ] of the main image and the gallery.
		$images = array();
		foreach ( self::image_ids( $info ) as $image_id ) {
			$images[] = array( (string) wp_get_attachment_image_url( $image_id, 'woocommerce_single' ), (string) wp_get_attachment_image_url( $image_id, 'woocommerce_gallery_thumbnail' ) );
		}
		if ( ! $images ) {
			$images[] = array( $info['image'], $info['thumb'] );
		}

		$limit = self::spec_limit();
		return array(
			'name'    => $info['name'],
			'english' => $info['english'],
			'brand'   => $info['brand'],
			'url'     => $info['url'],
			'images'  => $images,
			'family'  => $pairs( $info['families'] ),
			'notes'   => array_map( $pairs, $info['note_links'] ),
			'specs'   => array_map(
				static function ( $spec ) use ( $pairs, $limit ) {
					return array( $spec['label'], $pairs( array_slice( $spec['values'], 0, $limit ) ) );
				},
				$info['specs']
			),
			'excerpt' => $info['excerpt'],
		);
	}
}
