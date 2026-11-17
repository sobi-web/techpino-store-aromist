<?php
// اسنیپت #51: Techpino Product Gallery
defined( 'ABSPATH' ) || exit;


/**
 * Techpino Product Gallery
 *
 * Include from functions.php:
 *   require_once get_stylesheet_directory() . '/inc/techpino-product-gallery.php';
 *
 * Shortcode:
 *   [techpino_gallery]
 *   [techpino_gallery id="123" class="my-class"]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'techpino_pg_boot' );

/**
 * Boot when WooCommerce is available.
 */
function techpino_pg_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	add_shortcode( 'techpino_gallery', 'techpino_pg_shortcode' );
}

/**
 * Collect ordered attachment IDs for a product gallery.
 *
 * @param WC_Product $product Product.
 * @return array<int,int>
 */
function techpino_pg_get_image_ids( $product ) {
	$ids    = array();
	$main   = (int) $product->get_image_id();
	$unique = array();

	if ( $main > 0 ) {
		$ids[]           = $main;
		$unique[ $main ] = true;
	}

	foreach ( $product->get_gallery_image_ids() as $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id > 0 && ! isset( $unique[ $attachment_id ] ) ) {
			$ids[]                    = $attachment_id;
			$unique[ $attachment_id ] = true;
		}
	}

	return $ids;
}

/**
 * Image data for one gallery slide.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $alt           Alt text fallback.
 * @return array<string,string>|null
 */
function techpino_pg_get_slide_data( $attachment_id, $alt = '' ) {
	$attachment_id = (int) $attachment_id;
	if ( $attachment_id <= 0 ) {
		return null;
	}

	$full  = wp_get_attachment_image_src( $attachment_id, 'full' );
	$main  = wp_get_attachment_image_src( $attachment_id, 'woocommerce_single' );
	$thumb = wp_get_attachment_image_src( $attachment_id, 'woocommerce_gallery_thumbnail' );

	if ( ! $main || empty( $main[0] ) ) {
		$main = $full;
	}
	if ( ! $thumb || empty( $thumb[0] ) ) {
		$thumb = $main;
	}
	if ( ! $main || empty( $main[0] ) ) {
		return null;
	}

	$image_alt = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
	if ( '' === $image_alt ) {
		$image_alt = $alt;
	}

	return array(
		'src'    => (string) $main[0],
		'width'  => isset( $main[1] ) ? (string) $main[1] : '',
		'height' => isset( $main[2] ) ? (string) $main[2] : '',
		'full'   => ( $full && ! empty( $full[0] ) ) ? (string) $full[0] : (string) $main[0],
		'full_w' => ( $full && ! empty( $full[1] ) ) ? (string) $full[1] : '',
		'full_h' => ( $full && ! empty( $full[2] ) ) ? (string) $full[2] : '',
		'thumb'  => (string) $thumb[0],
		'alt'    => $image_alt,
		'srcset' => (string) wp_get_attachment_image_srcset( $attachment_id, 'woocommerce_single' ),
		'sizes'  => (string) wp_get_attachment_image_sizes( $attachment_id, 'woocommerce_single' ),
	);
}

/**
 * Register inline CSS/JS (safe to call once).
 */
function techpino_pg_enqueue_now() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done   = true;
	$handle = 'techpino-product-gallery';

	wp_register_style( $handle, false, array(), '3.1.0' );
	wp_enqueue_style( $handle );
	wp_add_inline_style( $handle, techpino_pg_get_css() );

	$deps = array( 'jquery' );
	if ( wp_script_is( 'wc-add-to-cart-variation', 'registered' ) ) {
		$deps[] = 'wc-add-to-cart-variation';
	}

	wp_register_script( $handle, false, $deps, '3.1.0', true );
	wp_enqueue_script( $handle );

	wp_add_inline_script(
		$handle,
		'window.techpinoPg=' . wp_json_encode(
			array(
				'mobileMq' => '(max-width: 768px)',
			)
		) . ';',
		'before'
	);
	wp_add_inline_script( $handle, techpino_pg_get_js() );
}

/**
 * Arrow icon markup.
 *
 * @param string $dir 'left' or 'right'.
 * @param int    $size Icon size.
 * @return string
 */
function techpino_pg_arrow_svg( $dir, $size = 16 ) {
	$path = 'left' === $dir ? 'M12.5 15L7.5 10L12.5 5' : 'M7.5 5L12.5 10L7.5 15';
	return '<svg width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="' . $path . '" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

/**
 * Shortcode output.
 *
 * @param array<string,string>|string $atts Attributes.
 * @return string
 */
function techpino_pg_shortcode( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'id'    => '',
			'class' => '',
		),
		$atts,
		'techpino_gallery'
	);

	$product_id = '' !== $atts['id'] ? absint( $atts['id'] ) : (int) get_the_ID();
	if ( $product_id <= 0 ) {
		return '';
	}

	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return '';
	}

	$image_ids = techpino_pg_get_image_ids( $product );
	if ( empty( $image_ids ) ) {
		return '';
	}

	$slides = array();
	foreach ( $image_ids as $attachment_id ) {
		$slide = techpino_pg_get_slide_data( $attachment_id, $product->get_name() );
		if ( $slide ) {
			$slides[] = $slide;
		}
	}

	if ( empty( $slides ) ) {
		return '';
	}

	techpino_pg_enqueue_now();

	$uid         = 'techpino-pg-' . wp_unique_id();
	$total       = count( $slides );
	$multi       = $total > 1;
	$extra       = sanitize_html_class( $atts['class'] );
	$root_class  = trim( 'techpino-pg' . ( $extra ? ' ' . $extra : '' ) . ( $multi ? ' techpino-pg--multi' : '' ) );
	$is_variable = $product->is_type( 'variable' );

	ob_start();
	?>
	<div
		id="<?php echo esc_attr( $uid ); ?>"
		class="<?php echo esc_attr( $root_class ); ?>"
		data-techpino-pg="1"
		<?php echo $is_variable ? 'data-product-id="' . esc_attr( (string) $product_id ) . '"' : ''; ?>
	>
		<div class="techpino-pg__stage">
			<div class="techpino-pg__viewport">
				<div class="techpino-pg__track" role="list">
					<?php foreach ( $slides as $i => $slide ) : ?>
						<div
							class="techpino-pg__slide<?php echo 0 === $i ? ' is-active' : ''; ?>"
							role="listitem"
							data-index="<?php echo esc_attr( (string) $i ); ?>"
							data-thumb="<?php echo esc_url( $slide['thumb'] ); ?>"
						>
							<div class="techpino-pg__frame">
								<img
									class="techpino-pg__img"
									src="<?php echo esc_url( $slide['src'] ); ?>"
									<?php if ( $slide['srcset'] ) : ?>
										srcset="<?php echo esc_attr( $slide['srcset'] ); ?>"
									<?php endif; ?>
									<?php if ( $slide['sizes'] ) : ?>
										sizes="<?php echo esc_attr( $slide['sizes'] ); ?>"
									<?php endif; ?>
									width="<?php echo esc_attr( $slide['width'] ); ?>"
									height="<?php echo esc_attr( $slide['height'] ); ?>"
									alt="<?php echo esc_attr( $slide['alt'] ); ?>"
									loading="eager"
									decoding="async"
									<?php echo 0 === $i ? 'fetchpriority="high"' : ''; ?>
									data-full="<?php echo esc_url( $slide['full'] ); ?>"
									data-thumb="<?php echo esc_url( $slide['thumb'] ); ?>"
								/>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<?php if ( $multi ) : ?>
				<span class="techpino-pg__counter" aria-hidden="true">
					<b class="techpino-pg__counter-current">1</b><i>/</i><span class="techpino-pg__counter-total"><?php echo esc_html( (string) $total ); ?></span>
				</span>

				<button type="button" class="techpino-pg__arrow techpino-pg__arrow--left techpino-pg__nav--prev" aria-label="<?php esc_attr_e( 'تصویر قبلی', 'techpino' ); ?>">
					<?php echo techpino_pg_arrow_svg( 'left', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</button>
				<button type="button" class="techpino-pg__arrow techpino-pg__arrow--right techpino-pg__nav--next" aria-label="<?php esc_attr_e( 'تصویر بعدی', 'techpino' ); ?>">
					<?php echo techpino_pg_arrow_svg( 'right', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</button>

				<div class="techpino-pg__dots" role="tablist" aria-label="<?php esc_attr_e( 'صفحه‌بندی گالری', 'techpino' ); ?>">
					<?php foreach ( $slides as $i => $slide ) : ?>
						<button
							type="button"
							class="techpino-pg__dot<?php echo 0 === $i ? ' is-active' : ''; ?>"
							role="tab"
							aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"
							aria-label="<?php echo esc_attr( sprintf( __( 'اسلاید %1$d از %2$d', 'techpino' ), $i + 1, $total ) ); ?>"
							data-index="<?php echo esc_attr( (string) $i ); ?>"
						><span class="techpino-pg__dot-mark" aria-hidden="true"></span></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $multi ) : ?>
			<div class="techpino-pg__thumbs" aria-label="<?php esc_attr_e( 'تصاویر محصول', 'techpino' ); ?>">
				<button type="button" class="techpino-pg__nav techpino-pg__nav--prev" aria-label="<?php esc_attr_e( 'تصویر قبلی', 'techpino' ); ?>">
					<?php echo techpino_pg_arrow_svg( 'left', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</button>
				<div class="techpino-pg__thumbs-viewport">
					<span class="techpino-pg__thumbs-fade techpino-pg__thumbs-fade--start" aria-hidden="true"></span>
					<div class="techpino-pg__thumbs-track" role="tablist">
						<?php foreach ( $slides as $i => $slide ) : ?>
							<button
								type="button"
								class="techpino-pg__thumb<?php echo 0 === $i ? ' is-active' : ''; ?>"
								role="tab"
								aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"
								aria-label="<?php echo esc_attr( sprintf( __( 'اسلاید %1$d از %2$d', 'techpino' ), $i + 1, $total ) ); ?>"
								data-index="<?php echo esc_attr( (string) $i ); ?>"
							>
								<img src="<?php echo esc_url( $slide['thumb'] ); ?>" alt="" loading="lazy" decoding="async" />
							</button>
						<?php endforeach; ?>
					</div>
					<span class="techpino-pg__thumbs-fade techpino-pg__thumbs-fade--end" aria-hidden="true"></span>
				</div>
				<button type="button" class="techpino-pg__nav techpino-pg__nav--next" aria-label="<?php esc_attr_e( 'تصویر بعدی', 'techpino' ); ?>">
					<?php echo techpino_pg_arrow_svg( 'right', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</button>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Gallery styles.
 *
 * Theme it with CSS variables on any parent:
 *   --techpino-primary, --techpino-radius, --techpino-border, --techpino-stage-bg, --techpino-pg-fade,
 *   --techpino-cream, --techpino-cream-line, --techpino-cream-hover
 *
 * @return string
 */
function techpino_pg_get_css() {
	return <<<'CSS'
.techpino-pg{--tp-primary:var(--techpino-primary,#0f766e);--tp-cream:var(--techpino-cream,#FFF4E0);--tp-cream-line:var(--techpino-cream-line,#e6c498);--tp-cream-hover:var(--techpino-cream-hover,#FBE7C2);--tp-radius:var(--techpino-radius,18px);--tp-border:var(--techpino-border,#edf0f4);--tp-ink:#0f172a;--tp-fade:var(--techpino-pg-fade,#fff);--tp-ease:cubic-bezier(.22,.8,.24,1);position:relative;width:100%;max-width:100%;margin:0;padding:0;box-sizing:border-box}
.techpino-pg *,.techpino-pg *::before,.techpino-pg *::after{box-sizing:border-box}
.techpino-pg button.techpino-pg__dot,.techpino-pg button.techpino-pg__thumb,.techpino-pg button.techpino-pg__nav,.techpino-pg button.techpino-pg__arrow{appearance:none;-webkit-appearance:none;margin:0;padding:0!important;border:0;background:transparent!important;box-shadow:none!important;outline:none;font:inherit;line-height:1;letter-spacing:normal;text-transform:none;text-decoration:none;color:inherit;cursor:pointer;min-width:0;max-width:none;width:auto;height:auto;-webkit-tap-highlight-color:transparent}

/* Stage */
.techpino-pg__stage{position:relative;border-radius:var(--tp-radius);background:var(--techpino-stage-bg,transparent);isolation:isolate}
.techpino-pg__stage::after{content:'';position:absolute;inset:0;border-radius:inherit;border:1px solid var(--tp-border);pointer-events:none;z-index:4}
.techpino-pg__viewport{position:relative;overflow:hidden;border-radius:inherit;aspect-ratio:1/1;width:100%;touch-action:pan-y pinch-zoom}
.techpino-pg--multi .techpino-pg__viewport{cursor:grab}
.techpino-pg.is-dragging .techpino-pg__viewport{cursor:grabbing}
.techpino-pg__track{display:flex;flex-wrap:nowrap;height:100%;margin:0;padding:0;list-style:none;will-change:transform;transition:transform .45s var(--tp-ease);direction:ltr}
.techpino-pg__slide{flex:0 0 100%;width:100%;min-width:100%;height:100%;margin:0;padding:0}
.techpino-pg__frame{display:flex;align-items:center;justify-content:center;width:100%;height:100%;padding:6%;pointer-events:none}
.techpino-pg__img{display:block;width:100%;height:100%;max-width:100%;max-height:100%;object-fit:contain;user-select:none;-webkit-user-drag:none;pointer-events:none;transform:scale(.94);opacity:.55;transition:transform .55s var(--tp-ease),opacity .45s ease}
.techpino-pg__slide.is-active .techpino-pg__img{transform:scale(1);opacity:1}
.techpino-pg.is-dragging .techpino-pg__img{opacity:1}

/* Counter */
.techpino-pg__counter{position:absolute;top:14px;inset-inline-start:14px;z-index:5;display:inline-flex;align-items:baseline;gap:3px;padding:5px 11px;border-radius:999px;background:rgba(255,255,255,.72);-webkit-backdrop-filter:blur(10px) saturate(1.4);backdrop-filter:blur(10px) saturate(1.4);border:1px solid rgba(255,255,255,.8);box-shadow:0 2px 10px rgba(15,23,42,.08);color:var(--tp-ink);font-size:12px;line-height:1;direction:ltr;font-variant-numeric:tabular-nums;pointer-events:none}
.techpino-pg__counter b{font-weight:700}
.techpino-pg__counter i{font-style:normal;opacity:.35;margin:0 1px}
.techpino-pg__counter span{opacity:.6}

/* Overlay arrows (desktop) */
.techpino-pg button.techpino-pg__arrow{position:absolute;top:50%;z-index:5;display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:999px!important;background:var(--tp-cream)!important;border:1px solid var(--tp-cream-line)!important;box-shadow:0 6px 18px -8px rgba(120,84,30,.35)!important;color:var(--tp-ink);opacity:0;transform:translateY(-50%) scale(.9);transition:opacity .25s ease,transform .3s var(--tp-ease),background-color .2s ease,color .2s ease}
.techpino-pg button.techpino-pg__arrow svg{display:block;pointer-events:none}
.techpino-pg button.techpino-pg__arrow--left{left:14px}
.techpino-pg button.techpino-pg__arrow--right{right:14px}
.techpino-pg__stage:hover button.techpino-pg__arrow,.techpino-pg button.techpino-pg__arrow:focus-visible{opacity:1;transform:translateY(-50%) scale(1)}
.techpino-pg button.techpino-pg__arrow:hover{background:var(--tp-cream-hover)!important}
.techpino-pg button.techpino-pg__arrow:focus-visible{outline:2px solid var(--tp-primary);outline-offset:2px}

/* Thumbnails */
.techpino-pg__thumbs{display:flex;align-items:center;gap:10px;margin-top:14px;direction:ltr}
.techpino-pg__thumbs-viewport{position:relative;flex:1 1 auto;min-width:0;overflow:hidden}
.techpino-pg__thumbs-track{display:flex;flex-wrap:nowrap;gap:10px;min-width:0;padding:4px 2px 6px;overflow-x:auto;direction:rtl;scroll-behavior:smooth;-webkit-overflow-scrolling:touch;scrollbar-width:none;-ms-overflow-style:none}
.techpino-pg__thumbs-track::-webkit-scrollbar{display:none}
.techpino-pg__thumbs-fade{position:absolute;top:0;bottom:0;width:40px;z-index:2;pointer-events:none;transition:opacity .25s ease}
.techpino-pg__thumbs-fade--start{left:0;background:linear-gradient(to right,var(--tp-fade),transparent)}
.techpino-pg__thumbs-fade--end{right:0;background:linear-gradient(to left,var(--tp-fade),transparent)}
.techpino-pg__thumbs--at-start .techpino-pg__thumbs-fade--start,.techpino-pg__thumbs--at-end .techpino-pg__thumbs-fade--end{opacity:0}
.techpino-pg button.techpino-pg__thumb{position:relative;flex:0 0 68px;width:68px;height:68px;min-width:68px;border:1px solid var(--tp-border)!important;border-radius:14px!important;background:transparent!important;overflow:hidden;touch-action:manipulation;opacity:.6;transition:opacity .25s ease,transform .3s var(--tp-ease),border-color .25s ease,box-shadow .25s ease}
.techpino-pg button.techpino-pg__thumb img{display:block;width:100%;height:100%;object-fit:contain;padding:6px;pointer-events:none}
.techpino-pg button.techpino-pg__thumb:hover{opacity:1;transform:translateY(-2px)}
.techpino-pg button.techpino-pg__thumb.is-active{opacity:1;border-color:var(--tp-primary)!important;box-shadow:0 0 0 3px color-mix(in srgb,var(--tp-primary) 16%,transparent),0 6px 14px -8px rgba(15,23,42,.35)!important}
.techpino-pg button.techpino-pg__thumb:focus-visible{outline:2px solid var(--tp-primary);outline-offset:2px}
.techpino-pg button.techpino-pg__nav{flex:0 0 32px;display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid var(--tp-cream-line)!important;border-radius:999px!important;background:var(--tp-cream)!important;color:var(--tp-ink);transition:opacity .2s ease,border-color .2s ease,background-color .2s ease,color .2s ease}
.techpino-pg button.techpino-pg__nav svg{display:block;pointer-events:none}
.techpino-pg button.techpino-pg__nav:hover{background:var(--tp-cream-hover)!important}
.techpino-pg button.techpino-pg__nav:focus-visible{outline:2px solid var(--tp-primary);outline-offset:2px}

/* Dots (mobile) */
.techpino-pg__dots{display:none}
.techpino-pg button.techpino-pg__dot{display:inline-flex;align-items:center;justify-content:center;width:auto;height:16px;padding:0 2px!important;touch-action:manipulation;flex:0 0 auto}
.techpino-pg__dot-mark{display:block;width:6px;height:6px;border-radius:999px;background:rgba(15,23,42,.25);transition:width .35s var(--tp-ease),background-color .25s ease}
.techpino-pg button.techpino-pg__dot.is-active .techpino-pg__dot-mark{width:20px;background:var(--tp-primary)}
.techpino-pg button.techpino-pg__dot:focus-visible{outline:2px solid var(--tp-primary);outline-offset:2px;border-radius:999px}

@media (max-width:768px){
.techpino-pg{--tp-radius:var(--techpino-radius,16px)}
.techpino-pg__thumbs,.techpino-pg button.techpino-pg__arrow{display:none!important}
.techpino-pg__counter{top:12px;inset-inline-start:12px;font-size:11px;padding:4px 9px}
.techpino-pg__dots{position:absolute;left:50%;bottom:12px;z-index:5;transform:translateX(-50%);display:flex;align-items:center;gap:2px;padding:3px 8px;border-radius:999px;background:rgba(255,255,255,.72);-webkit-backdrop-filter:blur(10px) saturate(1.4);backdrop-filter:blur(10px) saturate(1.4);border:1px solid rgba(255,255,255,.8);box-shadow:0 2px 10px rgba(15,23,42,.08);direction:ltr}
.techpino-pg__img{transform:none;opacity:1}
}
@media (hover:none){
.techpino-pg button.techpino-pg__arrow{display:none!important}
}
@media (prefers-reduced-motion:reduce){
.techpino-pg__track,.techpino-pg__img,.techpino-pg__dot-mark,.techpino-pg button.techpino-pg__thumb,.techpino-pg button.techpino-pg__arrow{transition:none}
.techpino-pg__thumbs-track{scroll-behavior:auto}
}
CSS;
}

/**
 * Gallery script.
 *
 * @return string
 */
function techpino_pg_get_js() {
	return <<<'JS'
(function($){
	'use strict';

	var config=window.techpinoPg||{mobileMq:'(max-width: 768px)'};
	var MOBILE_MQ=window.matchMedia(config.mobileMq);
	var numLocale=(document.documentElement.lang||'').toLowerCase().indexOf('fa')===0?'fa-IR':undefined;

	function isRtl(){
		return document.documentElement.getAttribute('dir')==='rtl';
	}

	function prefersReducedMotion(){
		return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function fmt(n){
		try{return Number(n).toLocaleString(numLocale);}catch(e){return String(n);}
	}

	function applyTrackTransform(track,x,smooth){
		track.style.transition=(smooth&&!prefersReducedMotion())?'':'none';
		track.style.transform='translate3d('+(-x)+'px,0,0)';
		if(!smooth){
			void track.offsetWidth;
			track.style.transition='';
		}
	}

	function storeSlideData(slide){
		var img=slide.querySelector('.techpino-pg__img');
		if(!img){return null;}
		return{
			src:img.getAttribute('src')||'',
			srcset:img.getAttribute('srcset')||'',
			sizes:img.getAttribute('sizes')||'',
			full:img.getAttribute('data-full')||'',
			thumb:img.getAttribute('data-thumb')||slide.getAttribute('data-thumb')||'',
			alt:img.getAttribute('alt')||''
		};
	}

	function applySlideData(slide,data){
		if(!data){return;}
		var img=slide.querySelector('.techpino-pg__img');
		if(img){
			if(data.src){img.setAttribute('src',data.src);}
			if(data.srcset){img.setAttribute('srcset',data.srcset);}else{img.removeAttribute('srcset');}
			if(data.sizes){img.setAttribute('sizes',data.sizes);}else{img.removeAttribute('sizes');}
			if(data.full){img.setAttribute('data-full',data.full);}
			if(data.thumb){img.setAttribute('data-thumb',data.thumb);}
			if(data.alt){img.setAttribute('alt',data.alt);}
		}
		if(data.thumb){slide.setAttribute('data-thumb',data.thumb);}
	}

	function initGallery(root){
		if(!root||root.getAttribute('data-techpino-pg-init')==='1'){return;}

		var track=root.querySelector('.techpino-pg__track');
		var slides=Array.prototype.slice.call(root.querySelectorAll('.techpino-pg__slide'));
		if(!track||!slides.length){return;}

		root.setAttribute('data-techpino-pg-init','1');
		root.setAttribute('tabindex','0');

		var thumbButtons=Array.prototype.slice.call(root.querySelectorAll('.techpino-pg__thumb'));
		var dotButtons=Array.prototype.slice.call(root.querySelectorAll('.techpino-pg__dot'));
		var navPrevs=Array.prototype.slice.call(root.querySelectorAll('.techpino-pg__nav--prev'));
		var navNexts=Array.prototype.slice.call(root.querySelectorAll('.techpino-pg__nav--next'));
		var thumbsBar=root.querySelector('.techpino-pg__thumbs-track');
		var thumbsWrap=root.querySelector('.techpino-pg__thumbs');
		var viewport=root.querySelector('.techpino-pg__viewport');
		var counterCurrent=root.querySelector('.techpino-pg__counter-current');
		var counterTotal=root.querySelector('.techpino-pg__counter-total');
		var n=slides.length;
		var multi=n>1;
		var index=0;
		var pos=0;
		var swiped=false;
		var dragStart=null;
		var dragActive=false;
		var settleTimer=null;
		var firstOriginal=storeSlideData(slides[0]);
		var cloneFirst=null;
		var cloneLast=null;
		var allSlides=slides.slice();

		/* Infinite loop: a clone of the last slide before the first, and of the first after the last. */
		if(multi){
			cloneLast=slides[n-1].cloneNode(true);
			cloneFirst=slides[0].cloneNode(true);
			[cloneLast,cloneFirst].forEach(function(c){
				c.classList.add('is-clone');
				c.setAttribute('aria-hidden','true');
				c.removeAttribute('role');
				var ci=c.querySelector('img');
				if(ci){ci.removeAttribute('fetchpriority');}
			});
			track.insertBefore(cloneLast,slides[0]);
			track.appendChild(cloneFirst);
			allSlides=[cloneLast].concat(slides,[cloneFirst]);
			pos=1;
		}

		if(counterTotal){counterTotal.textContent=fmt(n);}

		function getViewportWidth(){
			return viewport?viewport.clientWidth:0;
		}

		function syncSlideWidths(){
			var w=getViewportWidth();
			if(!w){return;}
			allSlides.forEach(function(slide){
				slide.style.width=w+'px';
				slide.style.flex='0 0 '+w+'px';
			});
		}

		function offsetFor(p){
			var slide=allSlides[p];
			return slide?slide.offsetLeft:p*getViewportWidth();
		}

		function realFromPos(p){
			if(!multi){return 0;}
			return ((p-1)%n+n)%n;
		}

		/* If we are sitting on a clone, jump (no animation) to the matching real slide. */
		function normalize(){
			if(settleTimer){clearTimeout(settleTimer);settleTimer=null;}
			if(!multi){return;}
			if(pos===0||pos===n+1){
				pos=realFromPos(pos)+1;
				applyTrackTransform(track,offsetFor(pos),false);
			}
		}

		function slideDir(){return isRtl()?-1:1;}
		function dotIndexFromSlide(s){return isRtl()?n-1-s:s;}
		function slideIndexFromDot(d){return isRtl()?n-1-d:d;}

		function updateUi(){
			allSlides.forEach(function(slide,i){
				slide.classList.toggle('is-active',(multi?realFromPos(i):i)===index);
			});
			thumbButtons.forEach(function(btn,i){
				btn.classList.toggle('is-active',i===index);
				btn.setAttribute('aria-selected',i===index?'true':'false');
			});
			var activeDot=dotIndexFromSlide(index);
			dotButtons.forEach(function(btn,i){
				btn.classList.toggle('is-active',i===activeDot);
				btn.setAttribute('aria-selected',i===activeDot?'true':'false');
			});
			if(thumbsWrap){
				thumbsWrap.classList.toggle('techpino-pg__thumbs--at-start',isRtl()?index>=n-1:index<=0);
				thumbsWrap.classList.toggle('techpino-pg__thumbs--at-end',isRtl()?index<=0:index>=n-1);
			}
			if(counterCurrent){counterCurrent.textContent=fmt(index+1);}
		}

		function scrollThumbIntoView(){
			var btn=thumbButtons[index];
			if(!btn||!thumbsBar){return;}
			var barRect=thumbsBar.getBoundingClientRect();
			var btnRect=btn.getBoundingClientRect();
			thumbsBar.scrollLeft+=(btnRect.left+btnRect.width/2)-(barRect.left+barRect.width/2);
		}

		function goToPos(p,smooth){
			if(!multi){
				index=0;
				track.style.transform='';
				track.style.transition='';
				updateUi();
				return;
			}
			if(settleTimer){clearTimeout(settleTimer);settleTimer=null;}
			pos=Math.max(0,Math.min(n+1,p));
			index=realFromPos(pos);
			syncSlideWidths();
			var animate=smooth&&!prefersReducedMotion();
			applyTrackTransform(track,offsetFor(pos),animate);
			updateUi();
			scrollThumbIntoView();
			if(pos===0||pos===n+1){
				if(animate){
					settleTimer=setTimeout(normalize,600);
				}else{
					normalize();
				}
			}
		}

		function goTo(i,smooth){
			goToPos(i+1,smooth);
		}

		/* delta is in track (physical, LTR) positions: +1 = content moves left. */
		function step(delta){
			normalize();
			goToPos(pos+delta,true);
		}

		function stepPrev(){step(-slideDir());}
		function stepNext(){step(slideDir());}

		track.addEventListener('transitionend',function(e){
			if(e.target===track&&e.propertyName==='transform'){normalize();}
		});

		function beginDrag(x,y){
			if(!multi){return;}
			normalize();
			dragStart={x:x,y:y};
			dragActive=true;
			swiped=false;
			track.style.transition='none';
		}

		function moveDrag(x,y){
			if(!dragActive||!dragStart||!multi){return;}
			var dx=x-dragStart.x;
			var dy=y-dragStart.y;
			if(Math.abs(dx)>8&&Math.abs(dx)>Math.abs(dy)){
				if(!swiped){root.classList.add('is-dragging');}
				swiped=true;
				track.style.transform='translate3d('+(-offsetFor(pos)+dx)+'px,0,0)';
			}
		}

		function endDrag(x){
			if(!dragActive||!dragStart){return;}
			var dx=x-dragStart.x;
			dragActive=false;
			dragStart=null;
			root.classList.remove('is-dragging');
			var threshold=Math.min(60,Math.max(36,getViewportWidth()*.12));
			if(swiped&&Math.abs(dx)>=threshold){
				goToPos(pos+(dx<0?1:-1),true);
			}else{
				goToPos(pos,true);
			}
		}

		thumbButtons.forEach(function(btn){
			btn.addEventListener('click',function(){
				goTo(parseInt(btn.getAttribute('data-index')||'0',10),true);
			});
		});

		dotButtons.forEach(function(btn){
			btn.addEventListener('click',function(){
				goTo(slideIndexFromDot(parseInt(btn.getAttribute('data-index')||'0',10)),true);
			});
		});

		navPrevs.forEach(function(b){b.addEventListener('click',stepPrev);});
		navNexts.forEach(function(b){b.addEventListener('click',stepNext);});

		root.addEventListener('keydown',function(e){
			if(!multi){return;}
			if(e.key==='ArrowLeft'){e.preventDefault();stepPrev();}
			if(e.key==='ArrowRight'){e.preventDefault();stepNext();}
		});

		if(viewport){
			var onResize=function(){
				syncSlideWidths();
				applyTrackTransform(track,offsetFor(pos),false);
			};
			if(window.ResizeObserver){
				new ResizeObserver(onResize).observe(viewport);
			}else{
				window.addEventListener('resize',onResize);
			}

			viewport.addEventListener('touchstart',function(e){
				if(!multi){return;}
				var t=e.changedTouches[0];
				beginDrag(t.clientX,t.clientY);
			},{passive:true});

			viewport.addEventListener('touchmove',function(e){
				if(!multi){return;}
				var t=e.changedTouches[0];
				moveDrag(t.clientX,t.clientY);
			},{passive:true});

			viewport.addEventListener('touchend',function(e){
				if(!multi){return;}
				endDrag(e.changedTouches[0].clientX);
			},{passive:true});

			viewport.addEventListener('touchcancel',function(){
				dragActive=false;
				dragStart=null;
				root.classList.remove('is-dragging');
				goToPos(pos,true);
			},{passive:true});

			viewport.addEventListener('mousedown',function(e){
				if(!multi||e.button!==0){return;}
				e.preventDefault();
				beginDrag(e.clientX,e.clientY);
				function onMouseMove(ev){
					if(dragActive){moveDrag(ev.clientX,ev.clientY);}
				}
				function onMouseUp(ev){
					document.removeEventListener('mousemove',onMouseMove);
					document.removeEventListener('mouseup',onMouseUp);
					if(dragActive){endDrag(ev.clientX);}
				}
				document.addEventListener('mousemove',onMouseMove);
				document.addEventListener('mouseup',onMouseUp);
			});
		}

		allSlides.forEach(function(slide){
			slide.addEventListener('click',function(e){
				if(swiped){
					e.preventDefault();
					e.stopImmediatePropagation();
					swiped=false;
				}
			},true);
		});

		var productId=root.getAttribute('data-product-id');
		var form=null;
		if(productId){
			form=document.querySelector('form.variations_form[data-product_id="'+productId+'"]');
		}
		if(!form){
			form=document.querySelector('form.variations_form');
		}

		function setFirstSlide(data){
			applySlideData(slides[0],data);
			if(cloneFirst){applySlideData(cloneFirst,data);}
			if(thumbButtons[0]&&data&&data.thumb){
				var timg=thumbButtons[0].querySelector('img');
				if(timg){timg.setAttribute('src',data.thumb);}
			}
		}

		if(form){
			$(form).on('found_variation',function(evt,variation){
				if(!variation||!variation.image){return;}
				setFirstSlide({
					src:variation.image.src||'',
					srcset:variation.image.srcset||'',
					sizes:variation.image.sizes||'',
					full:variation.image.full_src||variation.image.src||'',
					thumb:variation.image.gallery_thumbnail_src||variation.image.thumb_src||variation.image.src||'',
					alt:variation.image.alt||''
				});
				goTo(0,false);
			});
			$(form).on('reset_data',function(){
				setFirstSlide(firstOriginal);
				goTo(0,false);
			});
		}

		if(MOBILE_MQ.addEventListener){
			MOBILE_MQ.addEventListener('change',function(){goToPos(pos,false);});
		}

		syncSlideWidths();
		goTo(0,false);
	}

	function boot(){
		document.querySelectorAll('.techpino-pg[data-techpino-pg]').forEach(initGallery);
	}

	$(boot);
	$(document).on('elementor/popup/show',boot);
})(jQuery);
JS;
}
