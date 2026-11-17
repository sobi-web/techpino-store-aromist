<?php
// اسنیپت #53: کوئری المنتور برای محصولات دارای نت در صفحه تکی نت
defined( 'ABSPATH' ) || exit;


/**
 * Aromist – محصولات مرتبط با نت جاری در Elementor Loop Carousel
 *
 * Query ID المنتور: aromist_note_products
 * رابطه‌های JetEngine: 4 (نت اولیه)، 5 (نت میانی)، 6 (نت پایانی)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'elementor/query/aromist_note_products', function ( $query ) {
	if ( ! function_exists( 'jet_engine' ) ) {
		$query->set( 'post__in', array( 0 ) );
		return;
	}

	// get_queried_object_id() works on the frontend; Elementor preview can instead
	// expose its preview post through the global $post.
	$note_id = absint( get_queried_object_id() );
	$note_post = get_post( $note_id );
	if ( ! $note_post ) {
		global $post;
		$note_post = ( $post instanceof WP_Post ) ? $post : null;
		$note_id   = $note_post ? absint( $note_post->ID ) : 0;
	}

	if ( ! $note_id ) {
		$query->set( 'post_type', 'product' );
		$query->set( 'post__in', array( 0 ) );
		return;
	}

	// Use JetEngine's relation API so both default and relation-specific tables work.
	$all_relations = jet_engine()->relations->get_active_relations();
	$product_ids   = array();

	foreach ( array( 4, 5, 6 ) as $relation_id ) {
		if ( empty( $all_relations[ $relation_id ] ) ) {
			continue;
		}

		$relation = $all_relations[ $relation_id ];
		$product_ids = array_merge( $product_ids, (array) $relation->get_related_items_for_object( $note_post ) );
	}

	$product_ids = array_filter(
		array_values( array_unique( array_map( 'absint', $product_ids ) ) ),
		function ( $product_id ) {
			return 'product' === get_post_type( $product_id );
		}
	);

	$query->set( 'post_type', 'product' );
	$query->set( 'post__in', $product_ids ? $product_ids : array( 0 ) );
	$query->set( 'orderby', 'post__in' );
}, 10, 1 );
