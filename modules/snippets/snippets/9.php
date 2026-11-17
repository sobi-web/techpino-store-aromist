<?php
// اسنیپت #9: بازطراحی صفحه ویرایش محصول ووکامرس
defined( 'ABSPATH' ) || exit;

/**
 * Plugin Name: Noghte Modular Product Editor
 * Description: Modular, AJAX-first and performance-aware WooCommerce classic product editor.
 * Version: 1.3.0
 * Author: Noghte Group
 * Author URI: https://noghte-group.ir
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Noghte_Modular_Product_Editor {
	const VERSION   = '1.3.0';
	const USER_META = '_noghte_mpe_preferences_v1';
	const NONCE     = 'noghte_mpe_nonce';

	private static $instance = null;
	private $prefs = array();
	private $is_product_editor = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'current_screen', array( $this, 'bootstrap_screen' ) );
		add_action( 'add_meta_boxes', array( $this, 'prune_meta_boxes' ), PHP_INT_MAX, 2 );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'filter_product_data_tabs' ), 9999 );
		add_filter( 'woocommerce_admin_meta_boxes_variations_per_page', array( $this, 'optimize_variations_per_page' ), 9999 );
		add_filter( 'heartbeat_settings', array( $this, 'heartbeat_settings' ) );

		add_action( 'edit_form_top', array( $this, 'render_topbar' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
		add_action( 'admin_head', array( $this, 'render_css' ) );
		add_action( 'admin_footer', array( $this, 'render_drawers_and_js' ) );

		add_action( 'wp_ajax_noghte_mpe_save_pref', array( $this, 'ajax_save_preference' ) );
		add_action( 'wp_ajax_noghte_mpe_quick_save', array( $this, 'ajax_quick_save' ) );
		add_action( 'wp_ajax_noghte_mpe_search_terms', array( $this, 'ajax_search_terms' ) );
		add_action( 'wp_ajax_noghte_mpe_save_terms', array( $this, 'ajax_save_terms' ) );
	}

	private function defaults() {
		return array(
			'description'       => 1,
			'product_data'      => 1,
			'short_description' => 1,
			'image'             => 1,
			'gallery'           => 1,
			'categories'        => 1,
			'tags'              => 1,
			'brands'            => 1,
			'reviews'           => 1,
			'slug'              => 1,
			'custom_fields'     => 1,
			'revisions'         => 1,
			'seo'               => 1,
			'tab_general'       => 1,
			'tab_inventory'     => 1,
			'tab_shipping'      => 1,
			'tab_linked_product'=> 1,
			'tab_attribute'     => 1,
			'tab_variations'    => 1,
			'tab_advanced'      => 1,
			'performance_mode'  => 1,
		);
	}

	private function get_preferences() {
		if ( ! empty( $this->prefs ) ) {
			return $this->prefs;
		}

		$stored = get_user_meta( get_current_user_id(), self::USER_META, true );
		$stored = is_array( $stored ) ? $stored : array();
		$this->prefs = array_merge( $this->defaults(), $stored );

		return $this->prefs;
	}

	private function pref_enabled( $key ) {
		$prefs = $this->get_preferences();
		return ! empty( $prefs[ $key ] );
	}

	public function bootstrap_screen( $screen ) {
		if ( ! $screen || 'product' !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}

		$this->is_product_editor = true;
		$this->get_preferences();

		// This is a real performance optimization: when the main editor is disabled,
		// WordPress does not build TinyMCE/Quicktags for this request.
		if ( ! $this->pref_enabled( 'description' ) ) {
			remove_post_type_support( 'product', 'editor' );
		}
	}

	public function prune_meta_boxes( $post_type = '', $post = null ) {
		if ( ! $this->is_product_editor || 'product' !== $post_type ) {
			return;
		}

		$map = array(
			'product_data'      => array( array( 'woocommerce-product-data', 'normal' ) ),
			'short_description' => array( array( 'postexcerpt', 'normal' ) ),
			'image'             => array( array( 'postimagediv', 'side' ) ),
			'gallery'           => array( array( 'woocommerce-product-images', 'side' ) ),
			'categories'        => array( array( 'product_catdiv', 'side' ) ),
			'tags'              => array( array( 'tagsdiv-product_tag', 'side' ) ),
			'brands'            => array(
				array( 'product_branddiv', 'side' ),
				array( 'tagsdiv-product_brand', 'side' ),
				array( 'pwb-branddiv', 'side' ),
				array( 'yith_product_branddiv', 'side' ),
			),
			'reviews'           => array(
				array( 'commentsdiv', 'normal' ),
				array( 'commentstatusdiv', 'normal' ),
			),
			'slug'              => array( array( 'slugdiv', 'normal' ) ),
			'custom_fields'     => array( array( 'postcustom', 'normal' ) ),
			'revisions'         => array( array( 'revisionsdiv', 'normal' ) ),
		);

		foreach ( $map as $pref => $boxes ) {
			if ( $this->pref_enabled( $pref ) ) {
				continue;
			}
			foreach ( $boxes as $box ) {
				remove_meta_box( $box[0], 'product', $box[1] );
			}
		}

		// Popular SEO boxes. Only removed when the user explicitly disables the SEO module.
		if ( ! $this->pref_enabled( 'seo' ) ) {
			$seo_ids = array(
				'wpseo_meta',
				'rank_math_metabox',
				'aioseo-post-settings',
				'squirrly',
				'seopress_cpt',
			);
			foreach ( $seo_ids as $id ) {
				foreach ( array( 'normal', 'advanced', 'side' ) as $context ) {
					remove_meta_box( $id, 'product', $context );
				}
			}
		}
	}

	public function filter_product_data_tabs( $tabs ) {
		if ( ! $this->is_product_editor ) {
			return $tabs;
		}

		$keys = array( 'general', 'inventory', 'shipping', 'linked_product', 'attribute', 'variations', 'advanced' );
		foreach ( $keys as $key ) {
			if ( ! $this->pref_enabled( 'tab_' . $key ) && isset( $tabs[ $key ] ) ) {
				unset( $tabs[ $key ] );
			}
		}

		return $tabs;
	}

	public function optimize_variations_per_page( $per_page ) {
		if ( $this->is_product_editor && ! $this->pref_enabled( 'tab_variations' ) ) {
			return 1;
		}
		return $per_page;
	}

	public function heartbeat_settings( $settings ) {
		if ( $this->is_product_editor && $this->pref_enabled( 'performance_mode' ) ) {
			// Reduces background admin-ajax traffic without disabling post locking entirely.
			$settings['interval'] = 60;
		}
		return $settings;
	}

	private function get_current_product() {
		global $post;
		if ( ! $post || 'product' !== $post->post_type || ! function_exists( 'wc_get_product' ) ) {
			return false;
		}
		return wc_get_product( $post->ID );
	}

	public function admin_body_class( $classes ) {
		if ( $this->is_product_editor ) {
			$classes .= ' nmpe-modern-editor';
		}
		return $classes;
	}

	public function render_topbar( $post ) {
		if ( ! $this->is_product_editor || ! $post || 'product' !== $post->post_type ) {
			return;
		}

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : false;
		$type    = $product ? $product->get_type() : 'product';
		$status  = get_post_status( $post );
		$status_labels = array(
			'publish' => 'منتشر شده',
			'draft'   => 'پیش‌نویس',
			'pending' => 'در انتظار بررسی',
			'private' => 'خصوصی',
		);
		$status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status;
		$preview_url = function_exists( 'get_preview_post_link' ) ? get_preview_post_link( $post ) : '';
		if ( ! $preview_url && $product ) {
			$preview_url = get_permalink( $post->ID );
		}
		?>
		<div id="noghte-mpe-shell" dir="rtl">
			<div id="noghte-mpe-bar">
				<div class="noghte-mpe-brand">
					<span class="noghte-mpe-mark">ن</span>
					<div class="noghte-mpe-brand-copy">
						<span class="noghte-mpe-eyebrow">NOGHTE PRODUCT STUDIO</span>
						<strong>ویرایش حرفه‌ای محصول</strong>
						<small><?php echo esc_html( 'نوع محصول: ' . $type . ' · شناسه: #' . $post->ID ); ?></small>
					</div>
				</div>
				<div class="noghte-mpe-actions">
					<span class="noghte-mpe-status"><i></i><span><?php echo esc_html( $status_label ? $status_label : 'پیش‌نویس' ); ?></span></span>
					<?php if ( $preview_url ) : ?><a class="button noghte-mpe-btn noghte-mpe-preview" href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-visibility"></span> پیش‌نمایش</a><?php endif; ?>
					<button type="button" class="button noghte-mpe-btn" data-nmpe-open="modules"><span class="dashicons dashicons-screenoptions"></span> ماژول‌ها</button>
					<button type="button" class="button button-primary noghte-mpe-btn-primary" data-nmpe-open="quick"><span class="dashicons dashicons-update"></span> ذخیره سریع AJAX</button>
				</div>
			</div>
			<div class="noghte-mpe-jumpbar" aria-label="دسترسی سریع به بخش‌های محصول">
				<button type="button" data-nmpe-scroll="#titlediv"><span class="dashicons dashicons-edit"></span> عنوان</button>
				<button type="button" data-nmpe-scroll="#woocommerce-product-data"><span class="dashicons dashicons-admin-generic"></span> اطلاعات محصول</button>
				<button type="button" data-nmpe-scroll="#postexcerpt"><span class="dashicons dashicons-text-page"></span> توضیح کوتاه</button>
				<button type="button" data-nmpe-scroll="#postimagediv"><span class="dashicons dashicons-format-image"></span> تصویر</button>
				<button type="button" data-nmpe-scroll="#product_catdiv"><span class="dashicons dashicons-category"></span> دسته‌بندی</button>
				<button type="button" data-nmpe-scroll="#tagsdiv-product_tag"><span class="dashicons dashicons-tag"></span> برچسب</button>
				<button type="button" data-nmpe-scroll="#product_branddiv,#tagsdiv-product_brand,#pwb-branddiv,#yith_product_branddiv"><span class="dashicons dashicons-awards"></span> برند</button>
			</div>
		</div>
		<?php
	}

	public function render_css() {
		if ( ! $this->is_product_editor ) {
			return;
		}
		?>
		<style id="noghte-mpe-css">
			:root{
				--nmpe-navy:#0b1220;--nmpe-navy-2:#111d35;--nmpe-blue:#2563eb;--nmpe-blue-soft:#eff6ff;
				--nmpe-border:#e3e8f0;--nmpe-border-2:#edf1f6;--nmpe-bg:#f4f7fb;--nmpe-card:#fff;
				--nmpe-muted:#64748b;--nmpe-text:#0f172a;--nmpe-green:#16a34a;--nmpe-red:#dc2626;
				--nmpe-radius:18px;--nmpe-shadow:0 12px 35px rgba(15,23,42,.065);--nmpe-shadow-lg:0 24px 70px rgba(15,23,42,.13)
			}

			/* ---------- Global canvas ---------- */
			body.nmpe-modern-editor{background:var(--nmpe-bg)!important;color:var(--nmpe-text)}
			body.nmpe-modern-editor #wpcontent{background:var(--nmpe-bg)!important}
			body.nmpe-modern-editor #wpbody-content{padding-bottom:60px}
			body.nmpe-modern-editor .wrap{margin:18px 24px 0 18px}
			body.nmpe-modern-editor #poststuff{padding-top:0;max-width:1540px;margin:0 auto}
			body.nmpe-modern-editor #screen-meta-links{margin-top:3px}
			body.nmpe-modern-editor #poststuff h2{font-size:14px}
			body.nmpe-modern-editor .notice:not(.inline),body.nmpe-modern-editor div.updated:not(.inline),body.nmpe-modern-editor div.error:not(.inline){border-radius:13px;border:1px solid var(--nmpe-border);box-shadow:none;margin:12px 0}
			#poststuff #post-body.columns-2 #side-sortables {width: auto; }
			/* ---------- Product studio header ---------- */
			#noghte-mpe-shell{max-width:1540px;margin:0 auto 18px}
			#noghte-mpe-bar{position:sticky;top:32px;z-index:999;display:flex;align-items:center;justify-content:space-between;gap:18px;padding:15px 18px;background:rgba(255,255,255,.93);border:1px solid rgba(226,232,240,.9);border-radius:20px 20px 12px 12px;box-shadow:0 13px 42px rgba(15,23,42,.09);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px)}
			.noghte-mpe-brand{display:flex;align-items:center;gap:13px;min-width:0}.noghte-mpe-mark{width:46px;height:46px;display:grid;place-items:center;flex:0 0 46px;border-radius:15px;background:linear-gradient(145deg,#0b1220,#1e293b);color:#fff;font-weight:900;font-size:23px;box-shadow:0 8px 20px rgba(15,23,42,.22)}
			.noghte-mpe-brand-copy{min-width:0}.noghte-mpe-eyebrow{display:block;color:#94a3b8;font-size:9px;letter-spacing:1.5px;font-weight:800;margin-bottom:2px}.noghte-mpe-brand strong{display:block;color:var(--nmpe-text);font-size:15px;line-height:1.5}.noghte-mpe-brand small{display:block;color:var(--nmpe-muted);margin-top:2px;font-size:10.5px}
			.noghte-mpe-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap}.noghte-mpe-status{display:inline-flex;align-items:center;gap:7px;padding:8px 11px;border:1px solid #dcfce7;border-radius:999px;background:#f0fdf4;color:#166534;font-size:11px;font-weight:700}.noghte-mpe-status i{width:7px;height:7px;border-radius:50%;background:var(--nmpe-green);box-shadow:0 0 0 4px rgba(22,163,74,.1)}
			.noghte-mpe-btn,.noghte-mpe-btn-primary{display:inline-flex!important;align-items:center;justify-content:center;gap:7px;min-height:39px!important;border-radius:11px!important;padding:0 14px!important;font-weight:700!important;box-shadow:none!important}.noghte-mpe-btn .dashicons,.noghte-mpe-btn-primary .dashicons{font-size:16px;width:16px;height:16px;line-height:16px}.noghte-mpe-btn{border-color:#dbe2ea!important;color:#334155!important;background:#fff!important}.noghte-mpe-btn:hover{border-color:#94a3b8!important;color:#0f172a!important}.noghte-mpe-btn-primary{background:var(--nmpe-navy)!important;border-color:var(--nmpe-navy)!important;color:#fff!important}.noghte-mpe-btn-primary:hover{background:#172033!important;border-color:#172033!important}
			.noghte-mpe-jumpbar{display:flex;align-items:center;gap:6px;padding:8px 10px;margin-top:2px;background:#fff;border:1px solid var(--nmpe-border);border-radius:12px 12px 18px 18px;overflow:auto;box-shadow:0 8px 24px rgba(15,23,42,.035)}.noghte-mpe-jumpbar button{display:inline-flex;align-items:center;gap:6px;white-space:nowrap;border:0;background:transparent;color:#64748b;padding:8px 11px;border-radius:9px;cursor:pointer;font-size:11px;font-weight:700}.noghte-mpe-jumpbar button:hover{background:#f1f5f9;color:#0f172a}.noghte-mpe-jumpbar .dashicons{font-size:15px;width:15px;height:15px}

			/* ---------- Product title ---------- */
			body.nmpe-modern-editor #titlediv{background:#fff;border:1px solid var(--nmpe-border);border-radius:var(--nmpe-radius);padding:18px 20px 16px;margin:0 0 18px;box-shadow:var(--nmpe-shadow);position:relative;overflow:visible}
			body.nmpe-modern-editor #titlediv:before{content:"نام محصول";display:block;margin-bottom:9px;color:#334155;font-size:12px;font-weight:800}
			body.nmpe-modern-editor #titlediv #titlewrap{position:relative}
			body.nmpe-modern-editor #titlediv #title{height:54px!important;padding:8px 15px!important;border:1px solid #dfe5ec!important;border-radius:13px!important;background:#fbfdff!important;color:#0f172a!important;font-size:20px!important;font-weight:800!important;line-height:1.35!important;box-shadow:none!important;transition:.2s}
			body.nmpe-modern-editor #titlediv #title:focus{border-color:#94a3b8!important;background:#fff!important;box-shadow:0 0 0 4px rgba(37,99,235,.07)!important}
			body.nmpe-modern-editor #title-prompt-text{right:16px;left:auto;top:15px;color:#94a3b8;font-size:16px}
			body.nmpe-modern-editor #edit-slug-box{margin:11px 2px 0;padding:8px 10px;border-radius:9px;background:#f8fafc;color:#64748b;font-size:11px}

			/* ---------- Real two-column application layout ---------- */
			body.nmpe-modern-editor #poststuff #post-body.columns-2{display:grid!important;grid-template-columns:minmax(0,1fr) 320px;grid-template-areas:"content sidebar" "normal sidebar";column-gap:20px;row-gap:18px;align-items:start;margin:0!important}
			body.nmpe-modern-editor #post-body.columns-2 #post-body-content{grid-area:content;float:none!important;width:auto!important;min-width:0!important;margin:0!important;padding:0!important}
			body.nmpe-modern-editor #post-body.columns-2 #postbox-container-2{grid-area:normal;float:none!important;width:auto!important;min-width:0!important;margin:0!important}
			body.nmpe-modern-editor #post-body.columns-2 #postbox-container-1{grid-area:sidebar;float:none!important;width:auto!important;margin:0!important;position:relative;align-self:start}
			body.nmpe-modern-editor #postbox-container-1 .meta-box-sortables{position:sticky;top:112px}
			body.nmpe-modern-editor #poststuff .postbox-container{padding:0}
			body.nmpe-modern-editor #post-body-content:after{display:none!important}

			/* ---------- Universal cards ---------- */
			body.nmpe-modern-editor .postbox{border:1px solid var(--nmpe-border)!important;border-radius:var(--nmpe-radius)!important;background:#fff!important;box-shadow:var(--nmpe-shadow)!important;overflow:hidden;margin-bottom:18px!important}
			body.nmpe-modern-editor .postbox.closed{box-shadow:none!important}
			body.nmpe-modern-editor .postbox .postbox-header{min-height:50px;border-bottom:1px solid var(--nmpe-border-2)!important;background:#fff!important;padding:0 4px}
			body.nmpe-modern-editor .postbox .hndle{padding:14px 15px!important;font-size:13px!important;font-weight:800!important;color:#0f172a!important}
			body.nmpe-modern-editor .postbox .handle-actions{margin-left:5px}.postbox .handle-order-higher,.postbox .handle-order-lower{opacity:.5}
			body.nmpe-modern-editor .postbox .inside{margin:0!important;padding:16px!important}
			body.nmpe-modern-editor .postbox .toggle-indicator:before{color:#94a3b8}

			/* ---------- Editor card ---------- */
			body.nmpe-modern-editor #postdivrich{border:1px solid var(--nmpe-border);border-radius:var(--nmpe-radius);background:#fff;padding:15px;margin:0;box-shadow:var(--nmpe-shadow)}
			body.nmpe-modern-editor #postdivrich:before{content:"توضیحات کامل محصول";display:block;margin:1px 2px 12px;color:#0f172a;font-size:13px;font-weight:800}
			body.nmpe-modern-editor #wp-content-editor-tools{background:#fff!important}.nmpe-modern-editor .wp-editor-container{border:1px solid #e2e8f0!important;border-radius:12px!important;overflow:hidden}.nmpe-modern-editor .mce-top-part:before{box-shadow:none!important}.nmpe-modern-editor .mce-toolbar-grp{background:#f8fafc!important;border-bottom:1px solid #e9eef5!important}

			/* ---------- WooCommerce Product Data: full redesign ---------- */
			body.nmpe-modern-editor #woocommerce-product-data{border-radius:20px!important;overflow:hidden!important}
			body.nmpe-modern-editor #woocommerce-product-data .postbox-header{background:linear-gradient(180deg,#fff,#fbfdff)!important}
			body.nmpe-modern-editor #woocommerce-product-data .inside{padding:0!important;background:#fff}
			body.nmpe-modern-editor #woocommerce-product-data .panel-wrap{display:block!important;background:#fff}
			body.nmpe-modern-editor #woocommerce-product-data .panel-wrap>.panel,body.nmpe-modern-editor #woocommerce-product-data .panel-wrap>.woocommerce_options_panel{display:none!important}
			body.nmpe-modern-editor #woocommerce-product-data .panel-wrap>.nmpe-panel-active{display:block!important}
			body.nmpe-modern-editor #woocommerce-product-data .wc-tabs{float:none!important;width:100%!important;margin:0!important;padding:9px 10px!important;display:flex!important;align-items:center;gap:5px;overflow-x:auto;overflow-y:hidden;background:#f8fafc!important;border:0!important;border-bottom:1px solid var(--nmpe-border)!important;box-sizing:border-box}
			body.nmpe-modern-editor #woocommerce-product-data .wc-tabs:after{display:none!important}
			body.nmpe-modern-editor #woocommerce-product-data .wc-tabs li{float:none!important;width:auto!important;margin:0!important;padding:0!important;border:0!important;background:transparent!important;flex:0 0 auto}
			body.nmpe-modern-editor #woocommerce-product-data .wc-tabs li a{display:flex!important;align-items:center!important;gap:7px!important;min-height:37px;padding:0 12px!important;border-radius:10px!important;background:transparent!important;color:#64748b!important;font-size:11px!important;font-weight:700!important;white-space:nowrap;line-height:37px!important}
			body.nmpe-modern-editor #woocommerce-product-data .wc-tabs li a:before{margin:0!important;color:#94a3b8!important}
			body.nmpe-modern-editor #woocommerce-product-data .wc-tabs li:hover a{background:#eef2f7!important;color:#0f172a!important}
			body.nmpe-modern-editor #woocommerce-product-data .wc-tabs li.active a{background:#fff!important;color:#0f172a!important;box-shadow:0 3px 12px rgba(15,23,42,.07)!important}
			body.nmpe-modern-editor #woocommerce-product-data .wc-tabs li.active a:before{color:var(--nmpe-blue)!important}
			body.nmpe-modern-editor #woocommerce-product-data .woocommerce_options_panel,body.nmpe-modern-editor #woocommerce-product-data .panel{float:none!important;width:100%!important;margin:0!important;padding:20px!important;box-sizing:border-box;min-height:240px;background:#fff}
			body.nmpe-modern-editor #woocommerce-product-data .options_group{border-top:0!important;border-bottom:1px solid #eef2f7!important;padding:7px 0 14px!important;margin-bottom:10px}
			body.nmpe-modern-editor #woocommerce-product-data .options_group:last-child{border-bottom:0!important}
			body.nmpe-modern-editor #woocommerce-product-data .form-field{padding:7px 0!important;margin:0!important;align-items:center;gap:14px}
			body.nmpe-modern-editor #woocommerce-product-data .form-field>label{float:none!important;width:auto!important;margin:0!important;color:#475569;font-size:11.5px;font-weight:800;line-height:1.5}
			body.nmpe-modern-editor #woocommerce-product-data .form-field .woocommerce-help-tip{margin:0 5px 0 0!important}
			body.nmpe-modern-editor #woocommerce-product-data input[type=text],body.nmpe-modern-editor #woocommerce-product-data input[type=number],body.nmpe-modern-editor #woocommerce-product-data input[type=email],body.nmpe-modern-editor #woocommerce-product-data input[type=url],body.nmpe-modern-editor #woocommerce-product-data select,body.nmpe-modern-editor #woocommerce-product-data textarea{float:none!important;width:100%!important;max-width:none!important;min-height:40px;border:1px solid #dce3eb!important;border-radius:10px!important;background:#fbfdff!important;box-shadow:none!important;margin:0!important;padding:5px 10px!important}
			body.nmpe-modern-editor #woocommerce-product-data textarea{min-height:85px}.nmpe-modern-editor #woocommerce-product-data .select2-container{float:none!important;width:100%!important;max-width:none!important;margin:0!important}.nmpe-modern-editor #woocommerce-product-data .select2-selection{min-height:40px!important;border:1px solid #dce3eb!important;border-radius:10px!important;background:#fbfdff!important}.nmpe-modern-editor #woocommerce-product-data .select2-selection__rendered{line-height:38px!important}.nmpe-modern-editor #woocommerce-product-data .select2-selection__arrow{height:38px!important}
			body.nmpe-modern-editor #woocommerce-product-data input:focus,body.nmpe-modern-editor #woocommerce-product-data select:focus,body.nmpe-modern-editor #woocommerce-product-data textarea:focus{border-color:#94a3b8!important;background:#fff!important;box-shadow:0 0 0 3px rgba(37,99,235,.06)!important}
			body.nmpe-modern-editor #woocommerce-product-data .description{color:#94a3b8!important;font-size:10.5px!important;line-height:1.8!important;margin-top:4px!important}

			/* Attributes / Variations */
			body.nmpe-modern-editor #product_attributes .woocommerce_attribute,body.nmpe-modern-editor #variable_product_options .woocommerce_variation{border:1px solid #e5eaf1!important;border-radius:13px!important;background:#fff!important;margin:0 0 10px!important;overflow:hidden;box-shadow:0 4px 14px rgba(15,23,42,.035)}
			body.nmpe-modern-editor #product_attributes .woocommerce_attribute h3,body.nmpe-modern-editor #variable_product_options .woocommerce_variation h3{background:#f8fafc!important;border-bottom:1px solid #edf1f5!important;padding:12px!important;margin:0!important}
			body.nmpe-modern-editor #variable_product_options .toolbar{padding:12px 14px!important;background:#f8fafc!important;border-radius:11px!important;margin:0 0 12px!important}

			/* ---------- Sidebar / publish box ---------- */
			body.nmpe-modern-editor #submitdiv .inside{padding:0!important}.nmpe-modern-editor #submitdiv #submitpost{margin:0!important}.nmpe-modern-editor #submitdiv #minor-publishing{padding:13px 15px!important;background:#fbfdff}.nmpe-modern-editor #submitdiv #minor-publishing-actions{padding:0 0 12px!important}.nmpe-modern-editor #submitdiv #misc-publishing-actions{padding:10px 0 0!important}.nmpe-modern-editor #submitdiv .misc-pub-section{padding:8px 2px!important;border:0!important;color:#64748b}.nmpe-modern-editor #submitdiv #major-publishing-actions{display:flex;align-items:center;gap:8px;padding:14px 15px!important;background:#fff!important;border-top:1px solid #edf1f5!important}.nmpe-modern-editor #publishing-action{float:none!important;margin-right:auto!important}.nmpe-modern-editor #delete-action{float:none!important}.nmpe-modern-editor #publish{min-height:40px!important;border-radius:10px!important;padding:0 16px!important;background:var(--nmpe-navy)!important;border-color:var(--nmpe-navy)!important;font-weight:800!important;box-shadow:none!important}.nmpe-modern-editor #save-post{border-radius:9px!important}
			body.nmpe-modern-editor #postimagediv .inside{padding:14px!important}.nmpe-modern-editor #postimagediv #set-post-thumbnail{display:grid;place-items:center;min-height:180px;border:1px dashed #cbd5e1;border-radius:14px;background:#f8fafc;color:#475569;text-align:center;text-decoration:none!important;overflow:hidden}.nmpe-modern-editor #postimagediv #set-post-thumbnail img{display:block;width:100%;height:auto;max-height:270px;object-fit:cover;border-radius:11px}.nmpe-modern-editor #postimagediv .remove-post-thumbnail{display:block;margin-top:10px;text-align:center;color:#dc2626}
			body.nmpe-modern-editor #woocommerce-product-images .product_images{display:grid;grid-template-columns:repeat(3,1fr);gap:7px}.nmpe-modern-editor #woocommerce-product-images .product_images li.image{width:100%!important;margin:0!important;border-radius:10px!important;overflow:hidden}.nmpe-modern-editor #woocommerce-product-images .add_product_images{display:block;margin-top:10px!important}

			/* ---------- Taxonomy cards: categories / tags / brands ---------- */
			body.nmpe-modern-editor #product_catdiv .inside,
			body.nmpe-modern-editor #tagsdiv-product_tag .inside,
			body.nmpe-modern-editor #product_branddiv .inside,
			body.nmpe-modern-editor #tagsdiv-product_brand .inside,
			body.nmpe-modern-editor #pwb-branddiv .inside,
			body.nmpe-modern-editor #yith_product_branddiv .inside{padding:14px!important;background:linear-gradient(180deg,#fff 0,#fbfdff 100%)!important}
			body.nmpe-modern-editor #product_catdiv .category-tabs,
			body.nmpe-modern-editor #product_branddiv .category-tabs,
			body.nmpe-modern-editor #pwb-branddiv .category-tabs,
			body.nmpe-modern-editor #yith_product_branddiv .category-tabs{display:flex;gap:6px;margin:0 0 10px!important;padding:4px!important;border:1px solid #e7edf4;border-radius:11px;background:#f1f5f9}
			body.nmpe-modern-editor #product_catdiv .category-tabs li,
			body.nmpe-modern-editor #product_branddiv .category-tabs li,
			body.nmpe-modern-editor #pwb-branddiv .category-tabs li,
			body.nmpe-modern-editor #yith_product_branddiv .category-tabs li{margin:0!important;padding:0!important;flex:1;text-align:center}
			body.nmpe-modern-editor #product_catdiv .category-tabs a,
			body.nmpe-modern-editor #product_branddiv .category-tabs a,
			body.nmpe-modern-editor #pwb-branddiv .category-tabs a,
			body.nmpe-modern-editor #yith_product_branddiv .category-tabs a{display:block;padding:7px 9px;border-radius:8px;color:#64748b;text-decoration:none;font-size:10.5px;font-weight:800}
			body.nmpe-modern-editor #product_catdiv .category-tabs .tabs a,
			body.nmpe-modern-editor #product_branddiv .category-tabs .tabs a,
			body.nmpe-modern-editor #pwb-branddiv .category-tabs .tabs a,
			body.nmpe-modern-editor #yith_product_branddiv .category-tabs .tabs a{background:#fff;color:#0f172a;box-shadow:0 3px 10px rgba(15,23,42,.07)}
			body.nmpe-modern-editor #product_catdiv .categorydiv div.tabs-panel,
			body.nmpe-modern-editor #product_branddiv .categorydiv div.tabs-panel,
			body.nmpe-modern-editor #pwb-branddiv .categorydiv div.tabs-panel,
			body.nmpe-modern-editor #yith_product_branddiv .categorydiv div.tabs-panel{max-height:245px!important;margin:0!important;padding:8px!important;border:1px solid #e5ebf2!important;border-radius:12px!important;background:#fff!important;box-shadow:inset 0 1px 0 rgba(15,23,42,.02)}
			body.nmpe-modern-editor #product_catdiv .categorychecklist li,
			body.nmpe-modern-editor #product_branddiv .categorychecklist li,
			body.nmpe-modern-editor #pwb-branddiv .categorychecklist li,
			body.nmpe-modern-editor #yith_product_branddiv .categorychecklist li{margin:2px 0!important}
			body.nmpe-modern-editor #product_catdiv .categorychecklist label,
			body.nmpe-modern-editor #product_branddiv .categorychecklist label,
			body.nmpe-modern-editor #pwb-branddiv .categorychecklist label,
			body.nmpe-modern-editor #yith_product_branddiv .categorychecklist label{display:flex;align-items:center;gap:7px;padding:6px 7px;border-radius:8px;color:#475569;font-size:11px;transition:.15s}
			body.nmpe-modern-editor #product_catdiv .categorychecklist label:hover,
			body.nmpe-modern-editor #product_branddiv .categorychecklist label:hover,
			body.nmpe-modern-editor #pwb-branddiv .categorychecklist label:hover,
			body.nmpe-modern-editor #yith_product_branddiv .categorychecklist label:hover{background:#f1f5f9;color:#0f172a}
			body.nmpe-modern-editor #product_cat-add-toggle,
			body.nmpe-modern-editor #product_brand-add-toggle{display:inline-flex;align-items:center;margin-top:10px!important;padding:7px 10px;border:1px solid #dbe3ec;border-radius:9px;background:#fff;color:#334155;text-decoration:none;font-size:10.5px;font-weight:800}
			body.nmpe-modern-editor #product_cat-add-toggle:hover,
			body.nmpe-modern-editor #product_brand-add-toggle:hover{border-color:#b8c4d2;background:#f8fafc;color:#0f172a}
			body.nmpe-modern-editor #product_cat-adder .category-add,
			body.nmpe-modern-editor #product_brand-adder .category-add{margin-top:10px;padding:10px;border:1px solid #e5ebf2;border-radius:11px;background:#f8fafc}
			body.nmpe-modern-editor #product_cat-adder input[type=text],
			body.nmpe-modern-editor #product_brand-adder input[type=text]{width:100%;min-height:38px;margin:0 0 7px!important;background:#fff}

			body.nmpe-modern-editor #tagsdiv-product_tag .ajaxtag,
			body.nmpe-modern-editor #tagsdiv-product_brand .ajaxtag{margin:0!important}
			body.nmpe-modern-editor #tagsdiv-product_tag .ajaxtag p,
			body.nmpe-modern-editor #tagsdiv-product_brand .ajaxtag p{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:7px;margin:0!important}
			body.nmpe-modern-editor #tagsdiv-product_tag .newtag,
			body.nmpe-modern-editor #tagsdiv-product_brand .newtag{width:100%!important;min-height:40px!important;margin:0!important;padding:5px 10px!important;border:1px solid #dce3eb!important;border-radius:10px!important;background:#fff!important}
			body.nmpe-modern-editor #tagsdiv-product_tag .tagadd,
			body.nmpe-modern-editor #tagsdiv-product_brand .tagadd{min-height:40px!important;margin:0!important;padding:0 12px!important;border-radius:10px!important;font-weight:800!important}
			body.nmpe-modern-editor #tagsdiv-product_tag .howto,
			body.nmpe-modern-editor #tagsdiv-product_brand .howto{display:block;margin-top:8px!important;color:#94a3b8;font-size:10px;line-height:1.8}
			body.nmpe-modern-editor #tagsdiv-product_tag .tagchecklist,
			body.nmpe-modern-editor #tagsdiv-product_brand .tagchecklist{display:flex;flex-wrap:wrap;gap:6px;margin:10px 0 0!important}
			body.nmpe-modern-editor #tagsdiv-product_tag .tagchecklist span,
			body.nmpe-modern-editor #tagsdiv-product_brand .tagchecklist span{display:inline-flex;align-items:center;gap:4px;margin:0!important;padding:5px 8px;border:1px solid #dbeafe;border-radius:999px;background:#eff6ff;color:#1e3a8a;font-size:10.5px}
			body.nmpe-modern-editor #tagsdiv-product_tag .tagchecklist .ntdelbutton,
			body.nmpe-modern-editor #tagsdiv-product_brand .tagchecklist .ntdelbutton{position:static!important;margin:0!important;color:#64748b;text-decoration:none}
			body.nmpe-modern-editor #tagsdiv-product_tag .tagcloud-link,
			body.nmpe-modern-editor #tagsdiv-product_brand .tagcloud-link{display:inline-block;margin-top:12px;padding:7px 9px;border-radius:8px;background:#f1f5f9;color:#475569;text-decoration:none;font-size:10px;font-weight:700}


			/* ---------- AJAX taxonomy selector ---------- */
			body.nmpe-modern-editor .postbox.nmpe-tax-enhanced>.inside{padding:14px!important;background:#fff!important}
			body.nmpe-modern-editor .postbox.nmpe-tax-enhanced>.inside>*:not(.nmpe-tax-card){display:none!important}
			.nmpe-tax-card{position:relative;overflow:hidden;padding:14px;border:1px solid #e5eaf1;border-radius:14px;background:linear-gradient(145deg,#fbfdff 0%,#fff 58%,#f8fafc 100%)}
			.nmpe-tax-card:before{content:"";position:absolute;left:-26px;top:-34px;width:105px;height:105px;border-radius:50%;background:radial-gradient(circle,rgba(37,99,235,.10),rgba(37,99,235,0) 68%);pointer-events:none}
			.nmpe-tax-card-head{position:relative;display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px}.nmpe-tax-card-title{display:flex;align-items:center;gap:9px;min-width:0}.nmpe-tax-card-icon{width:36px;height:36px;display:grid;place-items:center;flex:0 0 36px;border-radius:11px;background:#0f172a;color:#fff}.nmpe-tax-card-icon .dashicons{font-size:17px;width:17px;height:17px;line-height:17px}.nmpe-tax-card-title b{display:block;color:#0f172a;font-size:12px}.nmpe-tax-card-title small{display:block;color:#94a3b8;font-size:9.5px;margin-top:2px}.nmpe-tax-manage{display:inline-flex!important;align-items:center;gap:5px!important;min-height:34px!important;padding:0 10px!important;border-radius:9px!important;font-size:10.5px!important;font-weight:800!important;white-space:nowrap}.nmpe-tax-manage .dashicons{font-size:14px;width:14px;height:14px;line-height:14px}
			.nmpe-tax-card-chips{display:flex;flex-wrap:wrap;gap:6px;min-height:31px}.nmpe-tax-chip{display:inline-flex;align-items:center;max-width:100%;padding:5px 8px;border:1px solid #dbe6f2;border-radius:999px;background:#fff;color:#334155;font-size:10px;font-weight:700;box-shadow:0 2px 7px rgba(15,23,42,.025)}.nmpe-tax-chip span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.nmpe-tax-empty{display:flex;align-items:center;gap:6px;color:#94a3b8;font-size:10px;padding:5px 1px}.nmpe-tax-empty:before{content:"";width:6px;height:6px;border-radius:50%;background:#cbd5e1}.nmpe-tax-card-more{display:inline-flex;align-items:center;padding:5px 8px;border-radius:999px;background:#f1f5f9;color:#64748b;font-size:9.5px;font-weight:800}
			.nmpe-tax-modal{position:fixed;inset:0;z-index:100050;display:grid;place-items:center;padding:26px;opacity:0;visibility:hidden;transition:.2s}.nmpe-tax-modal.is-open{opacity:1;visibility:visible}.nmpe-tax-modal-backdrop{position:absolute;inset:0;background:rgba(8,15,30,.62);backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px)}.nmpe-tax-dialog{position:relative;width:min(720px,96vw);max-height:min(780px,90vh);display:flex;flex-direction:column;overflow:hidden;border:1px solid rgba(255,255,255,.45);border-radius:22px;background:#fff;box-shadow:0 34px 95px rgba(15,23,42,.32);transform:translateY(12px) scale(.985);transition:.22s cubic-bezier(.2,.8,.2,1)}.nmpe-tax-modal.is-open .nmpe-tax-dialog{transform:none}
			.nmpe-tax-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:19px 21px;border-bottom:1px solid #e8edf3;background:linear-gradient(180deg,#fff,#fbfdff)}.nmpe-tax-head>div{min-width:0}.nmpe-tax-kicker{display:block;color:#2563eb;font-size:9px;font-weight:900;letter-spacing:.35px;margin-bottom:3px}.nmpe-tax-head strong{display:block;color:#0f172a;font-size:16px;line-height:1.5}.nmpe-tax-head small{display:block;color:#94a3b8;font-size:10px;margin-top:2px}.nmpe-tax-body{padding:17px 20px 20px;overflow:auto;min-height:360px}.nmpe-tax-search-wrap{position:relative;display:flex;align-items:center;margin-bottom:16px}.nmpe-tax-search-wrap>.dashicons{position:absolute;right:13px;z-index:2;color:#94a3b8;font-size:17px}.nmpe-tax-search-wrap input{width:100%!important;height:48px!important;padding:6px 42px 6px 62px!important;border:1px solid #dbe3ec!important;border-radius:13px!important;background:#f8fafc!important;box-shadow:none!important;font-size:12px!important}.nmpe-tax-search-wrap input:focus{background:#fff!important;border-color:#94a3b8!important;box-shadow:0 0 0 4px rgba(37,99,235,.07)!important}.nmpe-tax-search-wrap kbd{position:absolute;left:11px;padding:3px 6px;border:1px solid #dce3eb;border-bottom-width:2px;border-radius:6px;background:#fff;color:#94a3b8;font:8px/1.4 sans-serif}
			.nmpe-tax-selected-wrap{padding:12px;border:1px solid #e5eaf1;border-radius:13px;background:#fbfdff;margin-bottom:16px}.nmpe-tax-section-row{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:9px}.nmpe-tax-section-row b{color:#334155;font-size:10.5px}.nmpe-tax-section-row span{color:#94a3b8;font-size:9.5px}.nmpe-tax-selected{display:flex;flex-wrap:wrap;gap:6px;min-height:30px}.nmpe-tax-selected .nmpe-modal-chip{display:inline-flex;align-items:center;gap:5px;padding:5px 8px;border:1px solid #dbeafe;border-radius:999px;background:#eff6ff;color:#1e3a8a;font-size:10px;font-weight:800}.nmpe-modal-chip button{width:16px;height:16px;display:grid;place-items:center;padding:0;border:0;border-radius:50%;background:rgba(30,58,138,.09);color:#1e3a8a;cursor:pointer;font-size:12px;line-height:1}.nmpe-tax-results-title{margin:0 2px 8px}.nmpe-tax-results{display:grid;gap:7px;min-height:145px}.nmpe-tax-result{display:flex;align-items:center;gap:11px;padding:10px 11px;border:1px solid #e8edf3;border-radius:11px;background:#fff;cursor:pointer;transition:.14s}.nmpe-tax-result:hover{border-color:#cbd5e1;background:#fbfdff;transform:translateY(-1px)}.nmpe-tax-result.is-selected{border-color:#bfdbfe;background:#eff6ff}.nmpe-tax-check{width:20px;height:20px;display:grid;place-items:center;flex:0 0 20px;border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:transparent}.nmpe-tax-result.is-selected .nmpe-tax-check{border-color:#2563eb;background:#2563eb;color:#fff}.nmpe-tax-check .dashicons{font-size:13px;width:13px;height:13px;line-height:13px}.nmpe-tax-result-copy{min-width:0;flex:1}.nmpe-tax-result-copy b{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#1e293b;font-size:11.5px}.nmpe-tax-result-copy small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#94a3b8;font-size:9.5px;margin-top:2px}.nmpe-tax-result-badge{padding:4px 7px;border-radius:999px;background:#f1f5f9;color:#64748b;font-size:8.5px;font-weight:800}.nmpe-tax-loading,.nmpe-tax-no-results{display:grid;place-items:center;min-height:145px;border:1px dashed #dbe3ec;border-radius:12px;background:#fbfdff;color:#94a3b8;font-size:10.5px;text-align:center;padding:20px}.nmpe-tax-loading:before{content:"";width:23px;height:23px;margin-bottom:8px;border:2px solid #dbeafe;border-top-color:#2563eb;border-radius:50%;animation:nmpeSpin .7s linear infinite}@keyframes nmpeSpin{to{transform:rotate(360deg)}}
			.nmpe-tax-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 20px;border-top:1px solid #e8edf3;background:#fff}.nmpe-tax-foot>div{display:flex;gap:7px}.nmpe-tax-foot .button{min-height:38px!important;padding:0 13px!important;font-weight:800!important}.nmpe-tax-foot-note{display:flex;align-items:center;gap:7px;color:#64748b;font-size:9.5px}.nmpe-tax-foot-note i{width:7px;height:7px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 4px rgba(34,197,94,.10)}.nmpe-tax-modal.nmpe-is-saving #nmpe-tax-apply{opacity:.55;pointer-events:none}

			/* ---------- General wp-admin inputs/buttons ---------- */
			body.nmpe-modern-editor input[type=text],body.nmpe-modern-editor input[type=search],body.nmpe-modern-editor input[type=number],body.nmpe-modern-editor select,body.nmpe-modern-editor textarea{border-color:#dce3eb;border-radius:9px;box-shadow:none}
			body.nmpe-modern-editor .button{border-radius:9px;box-shadow:none}.nmpe-modern-editor .button-primary{background:var(--nmpe-navy);border-color:var(--nmpe-navy)}
			body.nmpe-modern-editor input[type=checkbox],body.nmpe-modern-editor input[type=radio]{border-color:#cbd5e1;box-shadow:none}

			/* ---------- Drawers ---------- */
			.nmpe-overlay{position:fixed;inset:0;z-index:100000;background:rgba(8,15,30,.52);opacity:0;visibility:hidden;transition:.22s;backdrop-filter:blur(3px)}.nmpe-overlay.is-open{opacity:1;visibility:visible}.nmpe-drawer{position:absolute;top:0;right:0;width:min(470px,94vw);height:100%;background:#fff;box-shadow:-22px 0 65px rgba(15,23,42,.25);transform:translateX(105%);transition:.28s cubic-bezier(.2,.8,.2,1);display:flex;flex-direction:column}.nmpe-overlay.is-open .nmpe-drawer{transform:translateX(0)}
			.nmpe-drawer-head{display:flex;align-items:center;justify-content:space-between;padding:21px 22px;border-bottom:1px solid var(--nmpe-border);background:#fbfdff}.nmpe-drawer-head strong{font-size:16px;color:var(--nmpe-text)}.nmpe-close{border:0;background:#eef2f7;width:36px;height:36px;border-radius:11px;cursor:pointer;font-size:20px;color:#475569}.nmpe-drawer-body{padding:18px 22px;overflow:auto;flex:1}.nmpe-drawer-foot{padding:14px 22px;border-top:1px solid var(--nmpe-border);background:#fff;display:flex;gap:8px}
			.nmpe-section-title{margin:8px 0 10px;color:#475569;font-size:11px;font-weight:900;letter-spacing:.2px}.nmpe-module{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:12px 2px;border-bottom:1px solid #f0f3f7}.nmpe-module:last-child{border-bottom:0}.nmpe-module b{display:block;color:#0f172a;font-size:12.5px}.nmpe-module small{display:block;color:var(--nmpe-muted);font-size:10.5px;margin-top:3px;line-height:1.65}
			.nmpe-switch{position:relative;width:45px;height:25px;flex:0 0 45px}.nmpe-switch input{display:none}.nmpe-switch span{position:absolute;inset:0;background:#cbd5e1;border-radius:999px;cursor:pointer;transition:.2s}.nmpe-switch span:before{content:"";position:absolute;width:19px;height:19px;right:3px;top:3px;background:#fff;border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,.14);transition:.2s}.nmpe-switch input:checked+span{background:var(--nmpe-navy)}.nmpe-switch input:checked+span:before{transform:translateX(-20px)}
			.nmpe-field{margin-bottom:14px}.nmpe-field label{display:block;margin-bottom:6px;font-size:11.5px;font-weight:800;color:#334155}.nmpe-field input,.nmpe-field select{width:100%;min-height:42px;border:1px solid #dbe2ea!important;border-radius:11px!important;box-shadow:none!important;background:#fbfdff}.nmpe-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.nmpe-note{padding:11px 12px;border:1px solid #dbeafe;background:#eff6ff;border-radius:11px;color:#1e3a8a;font-size:10.5px;line-height:1.9;margin-bottom:15px}
			.nmpe-toast{position:fixed;left:24px;bottom:24px;z-index:100001;background:#0f172a;color:#fff;padding:11px 14px;border-radius:11px;box-shadow:var(--nmpe-shadow-lg);transform:translateY(20px);opacity:0;pointer-events:none;transition:.2s}.nmpe-toast.show{transform:none;opacity:1}.nmpe-reload-hint{display:none;margin-top:12px;padding:10px 12px;border-radius:10px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:11px}.nmpe-reload-hint.show{display:block}
			body.nmpe-saving .noghte-mpe-btn-primary{opacity:.6;pointer-events:none}

			.nmpe-focus-card{animation:nmpeFocusFlash .85s ease}@keyframes nmpeFocusFlash{0%{box-shadow:0 0 0 0 rgba(37,99,235,.25),var(--nmpe-shadow)}45%{box-shadow:0 0 0 5px rgba(37,99,235,.10),var(--nmpe-shadow)}100%{box-shadow:var(--nmpe-shadow)}}

			/* ---------- Responsive ---------- */
			@media(max-width:1180px){body.nmpe-modern-editor #poststuff #post-body.columns-2{grid-template-columns:minmax(0,1fr) 285px}.noghte-mpe-brand small{display:none}}
			@media(max-width:960px){body.nmpe-modern-editor #poststuff #post-body.columns-2{display:block!important}body.nmpe-modern-editor #post-body.columns-2 #post-body-content,body.nmpe-modern-editor #post-body.columns-2 #postbox-container-1,body.nmpe-modern-editor #post-body.columns-2 #postbox-container-2{width:auto!important;margin:0!important}body.nmpe-modern-editor #postbox-container-1 .meta-box-sortables{position:static}.noghte-mpe-actions .noghte-mpe-status{display:none}}
			@media(max-width:782px){body.nmpe-modern-editor .wrap{margin:10px 12px 0 2px}#noghte-mpe-bar{top:46px;align-items:flex-start;flex-direction:column;padding:13px;border-radius:15px}.noghte-mpe-actions{width:100%;display:grid;grid-template-columns:1fr 1fr}.noghte-mpe-actions .button{width:100%;margin:0!important}.noghte-mpe-preview{display:none!important}.noghte-mpe-jumpbar{border-radius:11px;margin-top:4px}.nmpe-grid{grid-template-columns:1fr}body.nmpe-modern-editor #woocommerce-product-data .form-field{grid-template-columns:1fr;gap:6px}body.nmpe-modern-editor #woocommerce-product-data .woocommerce_options_panel,body.nmpe-modern-editor #woocommerce-product-data .panel{padding:14px!important}body.nmpe-modern-editor #titlediv{padding:14px}.noghte-mpe-eyebrow{display:none}}
			@media(max-width:600px){.nmpe-tax-modal{padding:9px}.nmpe-tax-dialog{width:100%;max-height:94vh;border-radius:17px}.nmpe-tax-head{padding:15px}.nmpe-tax-body{padding:13px;min-height:300px}.nmpe-tax-foot{padding:11px 13px;flex-direction:column;align-items:stretch}.nmpe-tax-foot-note{justify-content:center}.nmpe-tax-foot>div{display:grid;grid-template-columns:1fr 1fr}.nmpe-tax-card-head{align-items:flex-start;flex-direction:column}.nmpe-tax-manage{width:100%!important;justify-content:center}}
		</style>
		<?php
	}

	private function module_definitions() {
		return array(
			'description'       => array( 'توضیحات اصلی', 'ویرایشگر کامل توضیحات محصول' ),
			'product_data'      => array( 'اطلاعات محصول', 'قیمت، موجودی، حمل‌ونقل، ویژگی‌ها و...' ),
			'short_description' => array( 'توضیح کوتاه', 'باکس توضیح کوتاه ووکامرس' ),
			'image'             => array( 'تصویر شاخص', 'تصویر اصلی محصول' ),
			'gallery'           => array( 'گالری محصول', 'تصاویر گالری ووکامرس' ),
			'categories'        => array( 'دسته‌بندی‌ها', 'دسته‌های محصول' ),
			'tags'              => array( 'برچسب‌ها', 'برچسب‌های محصول' ),
			'brands'            => array( 'برندها', 'برند محصول؛ سازگار با WooCommerce Brands و چند افزونه رایج' ),
			'reviews'           => array( 'دیدگاه‌ها', 'مدیریت نظر و وضعیت دیدگاه' ),
			'slug'              => array( 'نامک', 'Slug محصول' ),
			'custom_fields'     => array( 'فیلدهای سفارشی', 'Custom Fields هسته وردپرس' ),
			'revisions'         => array( 'بازبینی‌ها', 'Revisions محصول' ),
			'seo'               => array( 'باکس SEO', 'Yoast / Rank Math / AIOSEO / SEOPress' ),
		);
	}

	private function tab_definitions() {
		return array(
			'tab_general'        => array( 'عمومی', 'قیمت و تنظیمات عمومی' ),
			'tab_inventory'      => array( 'انبار', 'SKU و موجودی' ),
			'tab_shipping'       => array( 'حمل‌ونقل', 'وزن، ابعاد و کلاس حمل' ),
			'tab_linked_product' => array( 'محصولات مرتبط', 'Upsell و Cross-sell' ),
			'tab_attribute'      => array( 'ویژگی‌ها', 'Attributes محصول' ),
			'tab_variations'     => array( 'متغیرها', 'Variations محصول' ),
			'tab_advanced'       => array( 'پیشرفته', 'یادداشت خرید، ترتیب و...' ),
		);
	}


	private function taxonomy_is_brand( $taxonomy ) {
		$name  = isset( $taxonomy->name ) ? strtolower( (string) $taxonomy->name ) : '';
		$label = isset( $taxonomy->label ) ? strtolower( wp_strip_all_tags( (string) $taxonomy->label ) ) : '';

		$known = array( 'product_brand', 'pwb-brand', 'yith_product_brand', 'yith_product_brands', 'berocket_brand', 'woo-brand' );
		if ( in_array( $name, $known, true ) ) {
			return true;
		}

		return false !== strpos( $name, 'brand' ) || false !== strpos( $label, 'brand' ) || false !== strpos( $label, 'برند' );
	}

	private function get_taxonomy_modules( $product_id = 0 ) {
		$objects = get_object_taxonomies( 'product', 'objects' );
		$modules = array();

		foreach ( $objects as $taxonomy ) {
			if ( empty( $taxonomy->name ) || 0 === strpos( $taxonomy->name, 'pa_' ) ) {
				continue;
			}

			$type = '';
			if ( 'product_cat' === $taxonomy->name ) {
				$type = 'category';
			} elseif ( 'product_tag' === $taxonomy->name ) {
				$type = 'tag';
			} elseif ( $this->taxonomy_is_brand( $taxonomy ) ) {
				$type = 'brand';
			}

			if ( ! $type ) {
				continue;
			}

			$default_id = $taxonomy->hierarchical ? $taxonomy->name . 'div' : 'tagsdiv-' . $taxonomy->name;
			$selectors  = array( '#' . $default_id );

			// Common brand plugins sometimes use a custom metabox id.
			if ( 'product_brand' === $taxonomy->name ) {
				$selectors[] = '#product_branddiv';
				$selectors[] = '#tagsdiv-product_brand';
				$selectors[] = '#yith_product_branddiv';
			}
			if ( 'pwb-brand' === $taxonomy->name ) {
				$selectors[] = '#pwb-branddiv';
			}

			$selected = array();
			if ( $product_id ) {
				$terms = wp_get_object_terms( $product_id, $taxonomy->name, array( 'orderby' => 'name', 'order' => 'ASC' ) );
				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$selected[] = array(
							'id'   => (int) $term->term_id,
							'name' => $term->name,
						);
					}
				}
			}

			$modules[] = array(
				'taxonomy'    => $taxonomy->name,
				'label'       => isset( $taxonomy->labels->name ) ? $taxonomy->labels->name : $taxonomy->label,
				'singular'    => isset( $taxonomy->labels->singular_name ) ? $taxonomy->labels->singular_name : $taxonomy->label,
				'type'        => $type,
				'hierarchical'=> (bool) $taxonomy->hierarchical,
				'selectors'   => array_values( array_unique( $selectors ) ),
				'selected'    => $selected,
			);
		}

		return $modules;
	}

	private function validate_product_taxonomy_request( $product_id, $taxonomy_name ) {
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) || ! current_user_can( 'edit_post', $product_id ) ) {
			return new WP_Error( 'nmpe_product_access', 'محصول یا دسترسی معتبر نیست.' );
		}

		$taxonomy = get_taxonomy( $taxonomy_name );
		if ( ! $taxonomy || ! in_array( 'product', (array) $taxonomy->object_type, true ) ) {
			return new WP_Error( 'nmpe_taxonomy', 'Taxonomy معتبر نیست.' );
		}

		if ( isset( $taxonomy->cap->assign_terms ) && ! current_user_can( $taxonomy->cap->assign_terms ) ) {
			return new WP_Error( 'nmpe_assign_terms', 'اجازه انتخاب موارد این بخش را ندارید.' );
		}

		return $taxonomy;
	}

	public function render_drawers_and_js() {
		if ( ! $this->is_product_editor ) {
			return;
		}

		global $post;
		$product = $this->get_current_product();
		$prefs   = $this->get_preferences();
		$is_variable = $product && $product->is_type( 'variable' );
		$is_price_parent_safe = $product && ! in_array( $product->get_type(), array( 'variable', 'grouped' ), true );
		$taxonomy_modules = $this->get_taxonomy_modules( $post ? absint( $post->ID ) : 0 );
		?>
		<div class="nmpe-overlay" id="nmpe-modules-overlay" dir="rtl">
			<div class="nmpe-drawer">
				<div class="nmpe-drawer-head"><strong>مدیریت ماژول‌ها</strong><button type="button" class="nmpe-close" data-nmpe-close>×</button></div>
				<div class="nmpe-drawer-body">
					<div class="nmpe-note">خاموش‌کردن ماژول‌ها همان لحظه آن‌ها را مخفی می‌کند؛ از بارگذاری بعدی، متاباکس‌های غیرفعال از سمت PHP هم Render نمی‌شوند تا واقعاً سبک‌تر شود.</div>
					<div class="nmpe-section-title">بخش‌های اصلی</div>
					<?php foreach ( $this->module_definitions() as $key => $def ) : ?>
						<div class="nmpe-module"><div><b><?php echo esc_html( $def[0] ); ?></b><small><?php echo esc_html( $def[1] ); ?></small></div><label class="nmpe-switch"><input type="checkbox" data-nmpe-pref="<?php echo esc_attr( $key ); ?>" <?php checked( ! empty( $prefs[ $key ] ) ); ?>><span></span></label></div>
					<?php endforeach; ?>

					<div class="nmpe-section-title" style="margin-top:22px">تب‌های اطلاعات محصول</div>
					<?php foreach ( $this->tab_definitions() as $key => $def ) : ?>
						<div class="nmpe-module"><div><b><?php echo esc_html( $def[0] ); ?></b><small><?php echo esc_html( $def[1] ); ?></small></div><label class="nmpe-switch"><input type="checkbox" data-nmpe-pref="<?php echo esc_attr( $key ); ?>" <?php checked( ! empty( $prefs[ $key ] ) ); ?>><span></span></label></div>
					<?php endforeach; ?>

					<div class="nmpe-section-title" style="margin-top:22px">کارایی</div>
					<div class="nmpe-module"><div><b>Performance Mode</b><small>کاهش Heartbeat ادمین به هر ۶۰ ثانیه در همین صفحه</small></div><label class="nmpe-switch"><input type="checkbox" data-nmpe-pref="performance_mode" <?php checked( ! empty( $prefs['performance_mode'] ) ); ?>><span></span></label></div>
					<div class="nmpe-reload-hint" id="nmpe-reload-hint">برای اعمال کامل بهینه‌سازی سمت PHP، یک بار صفحه را بازخوانی کن.</div>
				</div>
				<div class="nmpe-drawer-foot"><button type="button" class="button" data-nmpe-close>بستن</button><button type="button" class="button button-primary" id="nmpe-reload">اعمال و بازخوانی</button></div>
			</div>
		</div>

		<div class="nmpe-overlay" id="nmpe-quick-overlay" dir="rtl">
			<div class="nmpe-drawer">
				<div class="nmpe-drawer-head"><strong>ذخیره سریع محصول</strong><button type="button" class="nmpe-close" data-nmpe-close>×</button></div>
				<div class="nmpe-drawer-body">
					<?php if ( ! $product ) : ?>
						<div class="nmpe-note">برای محصول جدید، ابتدا یک بار از دکمه اصلی <ذخیره پیش‌نویس / انتشار> استفاده کن تا محصول ساخته شود.</div>
					<?php else : ?>
						<div class="nmpe-note">این پنل فقط فیلدهای داخل خودش را با AJAX ذخیره می‌کند؛ تغییرات ذخیره‌نشده در ادیتور اصلی را ارسال نمی‌کند.</div>
						<div class="nmpe-field"><label>نام محصول</label><input type="text" id="nmpe-name" value="<?php echo esc_attr( $product->get_name( 'edit' ) ); ?>"></div>
						<div class="nmpe-grid">
							<div class="nmpe-field"><label>SKU</label><input type="text" id="nmpe-sku" value="<?php echo esc_attr( $product->get_sku( 'edit' ) ); ?>"></div>
							<div class="nmpe-field"><label>وضعیت انتشار</label><select id="nmpe-status"><option value="draft" <?php selected( $product->get_status(), 'draft' ); ?>>پیش‌نویس</option><option value="publish" <?php selected( $product->get_status(), 'publish' ); ?>>منتشرشده</option><option value="private" <?php selected( $product->get_status(), 'private' ); ?>>خصوصی</option><option value="pending" <?php selected( $product->get_status(), 'pending' ); ?>>در انتظار</option></select></div>
						</div>
						<div class="nmpe-grid">
							<div class="nmpe-field"><label>قیمت عادی</label><input type="text" id="nmpe-regular-price" value="<?php echo esc_attr( $product->get_regular_price( 'edit' ) ); ?>" <?php disabled( ! $is_price_parent_safe ); ?>></div>
							<div class="nmpe-field"><label>قیمت فروش ویژه</label><input type="text" id="nmpe-sale-price" value="<?php echo esc_attr( $product->get_sale_price( 'edit' ) ); ?>" <?php disabled( ! $is_price_parent_safe ); ?>></div>
						</div>
						<?php if ( $is_variable ) : ?><div class="nmpe-note">برای محصول متغیر، قیمت باید روی خود Variationها مدیریت شود؛ به همین دلیل قیمت والد در ذخیره سریع قفل شده است.</div><?php endif; ?>
						<div class="nmpe-grid">
							<div class="nmpe-field"><label>مدیریت موجودی</label><select id="nmpe-manage-stock"><option value="0" <?php selected( $product->get_manage_stock(), false ); ?>>غیرفعال</option><option value="1" <?php selected( $product->get_manage_stock(), true ); ?>>فعال</option></select></div>
							<div class="nmpe-field"><label>تعداد موجودی</label><input type="number" step="1" id="nmpe-stock-qty" value="<?php echo esc_attr( is_null( $product->get_stock_quantity( 'edit' ) ) ? '' : $product->get_stock_quantity( 'edit' ) ); ?>"></div>
						</div>
						<div class="nmpe-field"><label>وضعیت موجودی</label><select id="nmpe-stock-status"><option value="instock" <?php selected( $product->get_stock_status(), 'instock' ); ?>>موجود</option><option value="outofstock" <?php selected( $product->get_stock_status(), 'outofstock' ); ?>>ناموجود</option><option value="onbackorder" <?php selected( $product->get_stock_status(), 'onbackorder' ); ?>>پیش‌خرید / Backorder</option></select></div>
					<?php endif; ?>
				</div>
				<div class="nmpe-drawer-foot"><button type="button" class="button" data-nmpe-close>انصراف</button><?php if ( $product ) : ?><button type="button" class="button button-primary" id="nmpe-quick-save">ذخیره با AJAX</button><?php endif; ?></div>
			</div>
		</div>

		<div class="nmpe-tax-modal" id="nmpe-tax-modal" dir="rtl" aria-hidden="true">
			<div class="nmpe-tax-modal-backdrop" data-nmpe-tax-close></div>
			<div class="nmpe-tax-dialog" role="dialog" aria-modal="true" aria-labelledby="nmpe-tax-title">
				<div class="nmpe-tax-head">
					<div><span class="nmpe-tax-kicker">انتخاب سریع محصول</span><strong id="nmpe-tax-title">مدیریت طبقه‌بندی</strong><small id="nmpe-tax-subtitle">جست‌وجو و انتخاب با AJAX</small></div>
					<button type="button" class="nmpe-close" data-nmpe-tax-close>×</button>
				</div>
				<div class="nmpe-tax-body">
					<div class="nmpe-tax-search-wrap"><span class="dashicons dashicons-search"></span><input type="search" id="nmpe-tax-search" autocomplete="off" placeholder="جست‌وجو..."><kbd>ESC</kbd></div>
					<div class="nmpe-tax-selected-wrap"><div class="nmpe-tax-section-row"><b>انتخاب‌شده‌ها</b><span id="nmpe-tax-selected-count">۰ مورد</span></div><div id="nmpe-tax-selected" class="nmpe-tax-selected"></div></div>
					<div class="nmpe-tax-section-row nmpe-tax-results-title"><b>نتایج</b><span id="nmpe-tax-result-status">برای مشاهده موارد جست‌وجو کنید</span></div>
					<div id="nmpe-tax-results" class="nmpe-tax-results"></div>
				</div>
				<div class="nmpe-tax-foot"><span class="nmpe-tax-foot-note"><i></i> تغییرات این بخش مستقیم روی محصول ذخیره می‌شود.</span><div><button type="button" class="button" data-nmpe-tax-close>انصراف</button><button type="button" class="button button-primary" id="nmpe-tax-apply">اعمال انتخاب‌ها</button></div></div>
			</div>
		</div>
		<div class="nmpe-toast" id="nmpe-toast"></div>
		<?php

		$config = array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( self::NONCE ),
			'productId'=> $post ? absint( $post->ID ) : 0,
			'prefs'    => $prefs,
			'taxonomies'=> $taxonomy_modules,
		);
		?>
		<script id="noghte-mpe-js">
		(function($){
			'use strict';

			var NMPE = <?php echo wp_json_encode( $config ); ?>;
			var toastTimer = null;
			var taxSearchTimer = null;
			var activeTaxonomy = null;
			var taxState = {};
			var taxDraft = {};
			var selectors = {
				description:'#postdivrich',
				product_data:'#woocommerce-product-data',
				short_description:'#postexcerpt',
				image:'#postimagediv',
				gallery:'#woocommerce-product-images',
				categories:'#product_catdiv',
				tags:'#tagsdiv-product_tag',
				brands:'#product_branddiv,#tagsdiv-product_brand,#pwb-branddiv,#yith_product_branddiv',
				reviews:'#commentsdiv,#commentstatusdiv',
				slug:'#slugdiv',
				custom_fields:'#postcustom',
				revisions:'#revisionsdiv',
				seo:'#wpseo_meta,#rank_math_metabox,#aioseo-post-settings,#squirrly,#seopress_cpt'
			};
			var tabSelectors = {
				tab_general:['.general_options','#general_product_data'],
				tab_inventory:['.inventory_options','#inventory_product_data'],
				tab_shipping:['.shipping_options','#shipping_product_data'],
				tab_linked_product:['.linked_product_options','#linked_product_data'],
				tab_attribute:['.attribute_options','#product_attributes'],
				tab_variations:['.variations_options','#variable_product_options'],
				tab_advanced:['.advanced_options','#advanced_product_data']
			};

			function isEnabled(value){
				return String(value) === '1' || value === true;
			}

			function toast(message, ok){
				var el = $('#nmpe-toast');
				if (typeof ok === 'undefined') { ok = true; }
				window.clearTimeout(toastTimer);
				el.text(message).css('background', ok ? '#0f172a' : '#991b1b').addClass('show');
				toastTimer = window.setTimeout(function(){ el.removeClass('show'); }, 2400);
			}

			function syncJumpbar(){
				$('[data-nmpe-scroll]').each(function(){
					var selector = $(this).attr('data-nmpe-scroll');
					var exists = false;
					try { exists = $(selector).filter(':visible').length > 0; } catch(err) { exists = false; }
					$(this).toggle(exists);
				});
			}

			function getWooPanelFromLink(link){
				var href = link && link.length ? link.attr('href') : '';
				if (!href || href.charAt(0) !== '#') { return $(); }
				try { return $(href).first(); } catch(err) { return $(); }
			}

			function activateWooProductTab(link, triggerEvent){
				var wrap = $('#woocommerce-product-data');
				if (!wrap.length || !link || !link.length || !link.closest('li').is(':visible')) { return; }
				var panel = getWooPanelFromLink(link);
				if (!panel.length) { return; }
				wrap.find('ul.wc-tabs li').removeClass('active');
				link.closest('li').addClass('active');
				wrap.find('.panel-wrap>.panel,.panel-wrap>.woocommerce_options_panel').removeClass('nmpe-panel-active').attr('aria-hidden','true');
				panel.addClass('nmpe-panel-active').attr('aria-hidden','false');
				if (triggerEvent) { $(document.body).trigger('woocommerce_product_data_panel_open', [link.attr('href')]); }
			}

			function syncWooProductTabs(){
				var wrap = $('#woocommerce-product-data');
				if (!wrap.length) { return; }
				var active = wrap.find('ul.wc-tabs li.active:visible a').first();
				if (!active.length) { active = wrap.find('ul.wc-tabs li:visible a').first(); }
				if (active.length) { activateWooProductTab(active, false); }
			}

			function applyPrefs(){
				$.each(selectors, function(key, selector){
					$(selector).toggle(isEnabled(NMPE.prefs[key]));
				});
				$.each(tabSelectors, function(key, pair){
					var enabled = isEnabled(NMPE.prefs[key]);
					$(pair[0]).toggle(enabled);
					if (!enabled) { $(pair[1]).removeClass('nmpe-panel-active').attr('aria-hidden','true'); }
				});
				syncWooProductTabs();
				syncJumpbar();
			}

			function openDrawer(which){
				var drawer = $('#nmpe-' + which + '-overlay');
				if (!drawer.length) { toast('پنل موردنظر پیدا نشد', false); return; }
				drawer.addClass('is-open').attr('aria-hidden','false');
				$('body').addClass('nmpe-drawer-open');
			}

			function closeDrawers(){
				$('.nmpe-overlay').removeClass('is-open').attr('aria-hidden','true');
				$('body').removeClass('nmpe-drawer-open');
			}

			function scrollToSelector(selector){
				var target;
				try { target = $(selector).filter(':visible').first(); } catch(err) { target = $(); }
				if (!target.length) { toast('این بخش در حال حاضر در صفحه فعال نیست', false); return; }
				var top = Math.max(0, target.offset().top - 145);
				$('html, body').stop(true).animate({scrollTop:top}, 280);
				target.addClass('nmpe-focus-card');
				window.setTimeout(function(){ target.removeClass('nmpe-focus-card'); }, 900);
			}

			function polishDynamicWooUI(){
				$('#woocommerce-product-data').find('input,select,textarea').each(function(){
					if (!$(this).attr('autocomplete')) { $(this).attr('autocomplete','off'); }
				});
			}

			function escapeHtml(value){
				return $('<div>').text(value == null ? '' : String(value)).html();
			}

			function iconForTaxType(type){
				if (type === 'category') { return 'dashicons-category'; }
				if (type === 'brand') { return 'dashicons-awards'; }
				return 'dashicons-tag';
			}

			function labelForTaxType(type){
				if (type === 'category') { return 'دسته‌بندی محصول'; }
				if (type === 'brand') { return 'برند محصول'; }
				return 'برچسب محصول';
			}

			function buildTaxState(){
				$.each(NMPE.taxonomies || [], function(_, module){
					var selected = {};
					$.each(module.selected || [], function(__, item){ selected[String(item.id)] = {id:parseInt(item.id,10), name:item.name}; });
					taxState[module.taxonomy] = selected;
				});
			}

			function findTaxBox(module){
				var found = $();
				$.each(module.selectors || [], function(_, selector){
					if (found.length) { return false; }
					try { found = $(selector).first(); } catch(err) { found = $(); }
				});
				return found;
			}

			function selectedArray(taxonomy, source){
				var items = [];
				var store = source || taxState;
				$.each(store[taxonomy] || {}, function(_, item){ items.push(item); });
				items.sort(function(a,b){ return String(a.name).localeCompare(String(b.name), 'fa'); });
				return items;
			}

			function renderTaxCard(module){
				var box = findTaxBox(module);
				if (!box.length) { return; }
				box.addClass('nmpe-tax-enhanced').attr('data-nmpe-taxonomy', module.taxonomy);
				var inside = box.children('.inside').first();
				if (!inside.length) { return; }
				var card = inside.children('.nmpe-tax-card');
				if (!card.length) {
					card = $('<div class="nmpe-tax-card"><div class="nmpe-tax-card-head"><div class="nmpe-tax-card-title"><span class="nmpe-tax-card-icon"><span class="dashicons '+iconForTaxType(module.type)+'"></span></span><div><b>'+escapeHtml(module.label)+'</b><small>'+escapeHtml(labelForTaxType(module.type))+' · جست‌وجوی AJAX</small></div></div><button type="button" class="button nmpe-tax-manage" data-nmpe-tax-open="'+escapeHtml(module.taxonomy)+'"><span class="dashicons dashicons-search"></span> مدیریت و جست‌وجو</button></div><div class="nmpe-tax-card-chips"></div></div>');
					inside.append(card);
				}
				var chips = card.find('.nmpe-tax-card-chips').empty();
				var items = selectedArray(module.taxonomy);
				if (!items.length) { chips.append('<span class="nmpe-tax-empty">هنوز موردی انتخاب نشده است</span>'); return; }
				$.each(items.slice(0,5), function(_, item){ chips.append('<span class="nmpe-tax-chip"><span>'+escapeHtml(item.name)+'</span></span>'); });
				if (items.length > 5) { chips.append('<span class="nmpe-tax-card-more">+'+(items.length-5)+' مورد دیگر</span>'); }
			}

			function enhanceTaxonomyCards(){
				$.each(NMPE.taxonomies || [], function(_, module){ renderTaxCard(module); });
				syncJumpbar();
			}

			function getTaxModule(taxonomy){
				var found = null;
				$.each(NMPE.taxonomies || [], function(_, module){ if (module.taxonomy === taxonomy) { found = module; return false; } });
				return found;
			}

			function renderModalSelected(){
				var wrap = $('#nmpe-tax-selected').empty();
				if (!activeTaxonomy) { return; }
				var items = selectedArray(activeTaxonomy.taxonomy, taxDraft);
				$('#nmpe-tax-selected-count').text(items.length + ' مورد');
				if (!items.length) { wrap.append('<span class="nmpe-tax-empty">هنوز انتخابی ندارید</span>'); return; }
				$.each(items, function(_, item){ wrap.append('<span class="nmpe-modal-chip" data-term-id="'+item.id+'"><span>'+escapeHtml(item.name)+'</span><button type="button" aria-label="حذف">×</button></span>'); });
			}

			function setResultSelectionStates(){
				$('#nmpe-tax-results .nmpe-tax-result').each(function(){
					var row = $(this), id = String(row.attr('data-term-id'));
					row.toggleClass('is-selected', !!(taxDraft[activeTaxonomy.taxonomy] || {})[id]);
				});
			}

			function loadTaxResults(query){
				if (!activeTaxonomy) { return; }
				var results = $('#nmpe-tax-results').html('<div class="nmpe-tax-loading">در حال دریافت موارد...</div>');
				$('#nmpe-tax-result-status').text('در حال جست‌وجو...');
				$.ajax({
					url:NMPE.ajaxUrl, type:'POST', dataType:'json',
					data:{action:'noghte_mpe_search_terms', nonce:NMPE.nonce, product_id:NMPE.productId, taxonomy:activeTaxonomy.taxonomy, search:query || ''}
				}).done(function(res){
					if (!res || !res.success) { results.html('<div class="nmpe-tax-no-results">دریافت موارد ناموفق بود.</div>'); $('#nmpe-tax-result-status').text('خطا'); return; }
					var terms = (res.data && res.data.terms) ? res.data.terms : [];
					results.empty();
					$('#nmpe-tax-result-status').text(terms.length + ' نتیجه');
					if (!terms.length) { results.html('<div class="nmpe-tax-no-results">موردی پیدا نشد.<br>عبارت دیگری جست‌وجو کنید.</div>'); return; }
					$.each(terms, function(_, term){
						var meta = term.parent_name ? ('زیرمجموعه '+term.parent_name) : labelForTaxType(activeTaxonomy.type);
						var row = $('<div class="nmpe-tax-result" data-term-id="'+term.id+'" data-term-name="'+escapeHtml(term.name)+'"><span class="nmpe-tax-check"><span class="dashicons dashicons-yes"></span></span><div class="nmpe-tax-result-copy"><b>'+escapeHtml(term.name)+'</b><small>'+escapeHtml(meta)+'</small></div><span class="nmpe-tax-result-badge">#'+term.id+'</span></div>');
						results.append(row);
					});
					setResultSelectionStates();
				}).fail(function(){ results.html('<div class="nmpe-tax-no-results">ارتباط با سرور برقرار نشد.</div>'); $('#nmpe-tax-result-status').text('خطای ارتباط'); });
			}

			function openTaxModal(taxonomy){
				activeTaxonomy = getTaxModule(taxonomy);
				if (!activeTaxonomy) { toast('این بخش Taxonomy معتبر ندارد', false); return; }
				taxDraft = {};
				taxDraft[activeTaxonomy.taxonomy] = {};
				$.each(taxState[activeTaxonomy.taxonomy] || {}, function(id, item){ taxDraft[activeTaxonomy.taxonomy][id] = {id:item.id, name:item.name}; });
				$('#nmpe-tax-title').text('مدیریت '+activeTaxonomy.label);
				$('#nmpe-tax-subtitle').text('جست‌وجوی AJAX و انتخاب چندتایی');
				$('#nmpe-tax-search').val('').attr('placeholder','جست‌وجو در '+activeTaxonomy.label+'...');
				renderModalSelected();
				$('#nmpe-tax-modal').addClass('is-open').attr('aria-hidden','false');
				$('body').addClass('nmpe-drawer-open');
				loadTaxResults('');
				window.setTimeout(function(){ $('#nmpe-tax-search').trigger('focus'); }, 120);
			}

			function closeTaxModal(){
				$('#nmpe-tax-modal').removeClass('is-open nmpe-is-saving').attr('aria-hidden','true');
				$('body').removeClass('nmpe-drawer-open');
				activeTaxonomy = null;
				taxDraft = {};
			}

			function syncNativeTaxonomyBox(module){
				var box = findTaxBox(module), items = selectedArray(module.taxonomy), ids = {};
				$.each(items, function(_, item){ ids[String(item.id)] = true; });
				if (!box.length) { return; }
				if (module.hierarchical) {
					box.find('input[type="checkbox"]').each(function(){ var input=$(this), val=String(input.val()); if (/^\d+$/.test(val)) { input.prop('checked', !!ids[val]); } });
				} else {
					var names = $.map(items, function(item){ return item.name; }).join(', ');
					box.find('textarea.the-tags').val(names);
				}
			}

			$(function(){
				$('body').addClass('nmpe-modern-editor');
				buildTaxState();
				applyPrefs();
				polishDynamicWooUI();
				enhanceTaxonomyCards();
				window.setTimeout(syncWooProductTabs, 80);
			});

			$(document).on('click.nmpe', '[data-nmpe-open]', function(e){
				e.preventDefault();
				openDrawer($(this).attr('data-nmpe-open'));
			});

			$(document).on('click.nmpe', '[data-nmpe-close]', function(e){
				e.preventDefault();
				closeDrawers();
			});

			$(document).on('click.nmpe', '.nmpe-overlay', function(e){
				if (e.target === this) { closeDrawers(); }
			});

			$(document).on('keydown.nmpe', function(e){
				if (e.key === 'Escape' || e.keyCode === 27) { closeDrawers(); closeTaxModal(); }
			});

			$(document).on('click.nmpe', '[data-nmpe-scroll]', function(e){
				e.preventDefault();
				scrollToSelector($(this).attr('data-nmpe-scroll'));
			});

			$(document).on('click.nmpe', '#woocommerce-product-data .wc-tabs a', function(){
				var link = $(this);
				window.setTimeout(function(){ activateWooProductTab(link, false); }, 0);
			});

			$(document.body).on('woocommerce_variations_loaded.nmpe woocommerce_variations_added.nmpe wc-enhanced-select-init.nmpe', function(){
				polishDynamicWooUI();
				syncWooProductTabs();
			});


			$(document).on('click.nmpe', '[data-nmpe-tax-open]', function(e){
				e.preventDefault();
				openTaxModal($(this).attr('data-nmpe-tax-open'));
			});

			$(document).on('click.nmpe', '[data-nmpe-tax-close]', function(e){
				e.preventDefault();
				closeTaxModal();
			});

			$(document).on('input.nmpe', '#nmpe-tax-search', function(){
				var query = $(this).val();
				window.clearTimeout(taxSearchTimer);
				taxSearchTimer = window.setTimeout(function(){ loadTaxResults(query); }, 280);
			});

			$(document).on('click.nmpe', '#nmpe-tax-results .nmpe-tax-result', function(){
				if (!activeTaxonomy) { return; }
				var row = $(this), id = String(row.attr('data-term-id')), name = row.attr('data-term-name') || '';
				taxDraft[activeTaxonomy.taxonomy] = taxDraft[activeTaxonomy.taxonomy] || {};
				if (taxDraft[activeTaxonomy.taxonomy][id]) { delete taxDraft[activeTaxonomy.taxonomy][id]; }
				else { taxDraft[activeTaxonomy.taxonomy][id] = {id:parseInt(id,10), name:name}; }
				renderModalSelected();
				setResultSelectionStates();
			});

			$(document).on('click.nmpe', '#nmpe-tax-selected .nmpe-modal-chip button', function(e){
				e.preventDefault(); e.stopPropagation();
				if (!activeTaxonomy) { return; }
				var id = String($(this).closest('.nmpe-modal-chip').attr('data-term-id'));
				delete taxDraft[activeTaxonomy.taxonomy][id];
				renderModalSelected();
				setResultSelectionStates();
			});

			$(document).on('click.nmpe', '#nmpe-tax-apply', function(e){
				e.preventDefault();
				if (!activeTaxonomy || !NMPE.productId) { toast('ابتدا محصول را ذخیره کنید', false); return; }
				var module = activeTaxonomy;
				var ids = $.map(selectedArray(module.taxonomy, taxDraft), function(item){ return item.id; });
				var modal = $('#nmpe-tax-modal').addClass('nmpe-is-saving');
				$.ajax({
					url:NMPE.ajaxUrl, type:'POST', dataType:'json',
					data:{action:'noghte_mpe_save_terms', nonce:NMPE.nonce, product_id:NMPE.productId, taxonomy:module.taxonomy, term_ids:ids}
				}).done(function(res){
					if (!res || !res.success) { toast((res && res.data && res.data.message) ? res.data.message : 'ذخیره انتخاب‌ها ناموفق بود', false); return; }
					var selected = {};
					$.each((res.data && res.data.terms) ? res.data.terms : [], function(_, item){ selected[String(item.id)]={id:parseInt(item.id,10),name:item.name}; });
					taxState[module.taxonomy] = selected;
					syncNativeTaxonomyBox(module);
					renderTaxCard(module);
					toast(module.label + ' ذخیره شد');
					closeTaxModal();
				}).fail(function(xhr){
					var msg='خطا در ارتباط با سرور'; if(xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message){msg=xhr.responseJSON.data.message;} toast(msg,false);
				}).always(function(){ modal.removeClass('nmpe-is-saving'); });
			});

			$(document).on('change.nmpe', '[data-nmpe-pref]', function(){
				var input = $(this);
				var key = input.attr('data-nmpe-pref');
				var value = input.is(':checked') ? 1 : 0;
				var previous = value ? 0 : 1;
				NMPE.prefs[key] = value;
				applyPrefs();
				input.prop('disabled', true);

				$.ajax({
					url: NMPE.ajaxUrl,
					type: 'POST',
					dataType: 'json',
					data: {action:'noghte_mpe_save_pref', nonce:NMPE.nonce, key:key, value:value}
				}).done(function(res){
					if (!res || !res.success) {
						input.prop('checked', !!previous);
						NMPE.prefs[key] = previous;
						applyPrefs();
						toast((res && res.data && res.data.message) ? res.data.message : 'ذخیره تنظیمات ناموفق بود', false);
						return;
					}
					$('#nmpe-reload-hint').addClass('show');
					toast('تنظیم ماژول ذخیره شد');
				}).fail(function(){
					input.prop('checked', !!previous);
					NMPE.prefs[key] = previous;
					applyPrefs();
					toast('خطا در ارتباط با سرور', false);
				}).always(function(){ input.prop('disabled', false); });
			});

			$(document).on('click.nmpe', '#nmpe-reload', function(e){
				e.preventDefault();
				window.location.reload();
			});

			$(document).on('click.nmpe', '#nmpe-quick-save', function(e){
				e.preventDefault();
				if (!NMPE.productId) { toast('ابتدا محصول را یک بار ذخیره کنید', false); return; }

				var button = $(this);
				var data = {
					action:'noghte_mpe_quick_save',
					nonce:NMPE.nonce,
					product_id:NMPE.productId,
					name:$('#nmpe-name').val(),
					sku:$('#nmpe-sku').val(),
					status:$('#nmpe-status').val(),
					regular_price:$('#nmpe-regular-price').val(),
					sale_price:$('#nmpe-sale-price').val(),
					manage_stock:$('#nmpe-manage-stock').val(),
					stock_quantity:$('#nmpe-stock-qty').val(),
					stock_status:$('#nmpe-stock-status').val()
				};

				$('body').addClass('nmpe-saving');
				button.prop('disabled', true).text('در حال ذخیره...');

				$.ajax({url:NMPE.ajaxUrl, type:'POST', dataType:'json', data:data}).done(function(res){
					if (!res || !res.success) {
						toast((res && res.data && res.data.message) ? res.data.message : 'ذخیره ناموفق بود', false);
						return;
					}
					$('#title').val(data.name);
					if ($('#_sku').length) { $('#_sku').val(data.sku); }
					if ($('#_regular_price').length && !$('#nmpe-regular-price').prop('disabled')) { $('#_regular_price').val(data.regular_price); }
					if ($('#_sale_price').length && !$('#nmpe-sale-price').prop('disabled')) { $('#_sale_price').val(data.sale_price); }
					if ($('#_manage_stock').length) { $('#_manage_stock').prop('checked', String(data.manage_stock) === '1').trigger('change'); }
					if ($('#_stock').length) { $('#_stock').val(data.stock_quantity); }
					if ($('#_stock_status').length) { $('#_stock_status').val(data.stock_status).trigger('change'); }
					if ($('#post_status').length) { $('#post_status').val(data.status); }
					if (res.data && res.data.status_label) { $('.noghte-mpe-status span').text(res.data.status_label); }
					toast('محصول با موفقیت ذخیره شد');
					closeDrawers();
				}).fail(function(xhr){
					var message = 'خطا در ارتباط با سرور';
					if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) { message = xhr.responseJSON.data.message; }
					toast(message, false);
				}).always(function(){
					$('body').removeClass('nmpe-saving');
					button.prop('disabled', false).text('ذخیره با AJAX');
				});
			});
		})(jQuery);
		</script>
		<?php
	}


	public function ajax_search_terms() {
		check_ajax_referer( self::NONCE, 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$taxonomy_name = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
		$taxonomy = $this->validate_product_taxonomy_request( $product_id, $taxonomy_name );
		if ( is_wp_error( $taxonomy ) ) {
			wp_send_json_error( array( 'message' => $taxonomy->get_error_message() ), 403 );
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$args = array(
			'taxonomy'   => $taxonomy_name,
			'hide_empty' => false,
			'number'     => 40,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);
		if ( '' !== $search ) {
			$args['search'] = $search;
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			wp_send_json_error( array( 'message' => $terms->get_error_message() ), 500 );
		}

		$data = array();
		$parent_cache = array();
		foreach ( $terms as $term ) {
			$parent_name = '';
			if ( ! empty( $term->parent ) ) {
				if ( ! isset( $parent_cache[ $term->parent ] ) ) {
					$parent = get_term( (int) $term->parent, $taxonomy_name );
					$parent_cache[ $term->parent ] = ( $parent && ! is_wp_error( $parent ) ) ? $parent->name : '';
				}
				$parent_name = $parent_cache[ $term->parent ];
			}
			$data[] = array(
				'id'          => (int) $term->term_id,
				'name'        => $term->name,
				'parent_name' => $parent_name,
			);
		}

		wp_send_json_success( array( 'terms' => $data ) );
	}

	public function ajax_save_terms() {
		check_ajax_referer( self::NONCE, 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$taxonomy_name = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
		$taxonomy = $this->validate_product_taxonomy_request( $product_id, $taxonomy_name );
		if ( is_wp_error( $taxonomy ) ) {
			wp_send_json_error( array( 'message' => $taxonomy->get_error_message() ), 403 );
		}

		$raw_ids = isset( $_POST['term_ids'] ) ? (array) wp_unslash( $_POST['term_ids'] ) : array();
		$term_ids = array_values( array_unique( array_filter( array_map( 'absint', $raw_ids ) ) ) );

		if ( $term_ids ) {
			$valid_ids = get_terms(
				array(
					'taxonomy'   => $taxonomy_name,
					'hide_empty' => false,
					'include'    => $term_ids,
					'fields'     => 'ids',
				)
			);
			if ( is_wp_error( $valid_ids ) ) {
				wp_send_json_error( array( 'message' => $valid_ids->get_error_message() ), 422 );
			}
			$term_ids = array_map( 'intval', $valid_ids );
		}

		$result = wp_set_object_terms( $product_id, $term_ids, $taxonomy_name, false );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		}

		clean_post_cache( $product_id );
		$terms = wp_get_object_terms( $product_id, $taxonomy_name, array( 'orderby' => 'name', 'order' => 'ASC' ) );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$data = array();
		foreach ( $terms as $term ) {
			$data[] = array( 'id' => (int) $term->term_id, 'name' => $term->name );
		}

		wp_send_json_success( array( 'terms' => $data ) );
	}

	public function ajax_save_preference() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( 'edit_products' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی کافی ندارید.' ), 403 );
		}

		$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
		$value = ! empty( $_POST['value'] ) ? 1 : 0;
		$allowed = array_keys( $this->defaults() );

		if ( ! in_array( $key, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => 'کلید تنظیمات معتبر نیست.' ), 400 );
		}

		$prefs = $this->get_preferences();
		$prefs[ $key ] = $value;
		update_user_meta( get_current_user_id(), self::USER_META, $prefs );
		$this->prefs = $prefs;

		wp_send_json_success( array( 'prefs' => $prefs ) );
	}

	public function ajax_quick_save() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! function_exists( 'wc_get_product' ) ) {
			wp_send_json_error( array( 'message' => 'ووکامرس فعال نیست.' ), 500 );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) || ! current_user_can( 'edit_post', $product_id ) ) {
			wp_send_json_error( array( 'message' => 'محصول یا دسترسی معتبر نیست.' ), 403 );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => 'محصول پیدا نشد.' ), 404 );
		}

		try {
			$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$sku  = isset( $_POST['sku'] ) ? wc_clean( wp_unslash( $_POST['sku'] ) ) : '';
			$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'draft';
			$allowed_statuses = array( 'draft', 'publish', 'private', 'pending' );
			if ( ! in_array( $status, $allowed_statuses, true ) ) {
				$status = 'draft';
			}

			if ( '' === $name ) {
				wp_send_json_error( array( 'message' => 'نام محصول نمی‌تواند خالی باشد.' ), 422 );
			}

			$product->set_name( $name );
			$product->set_status( $status );
			$product->set_sku( $sku );

			// Parent price is intentionally not changed for variable/grouped products.
			if ( ! in_array( $product->get_type(), array( 'variable', 'grouped' ), true ) ) {
				$regular = isset( $_POST['regular_price'] ) ? wc_format_decimal( wp_unslash( $_POST['regular_price'] ) ) : '';
				$sale    = isset( $_POST['sale_price'] ) ? wc_format_decimal( wp_unslash( $_POST['sale_price'] ) ) : '';
				$product->set_regular_price( $regular );
				$product->set_sale_price( $sale );
			}

			$manage_stock = ! empty( $_POST['manage_stock'] );
			$product->set_manage_stock( $manage_stock );
			if ( $manage_stock ) {
				$qty = isset( $_POST['stock_quantity'] ) && '' !== $_POST['stock_quantity'] ? wc_stock_amount( wp_unslash( $_POST['stock_quantity'] ) ) : null;
				$product->set_stock_quantity( $qty );
			}

			$stock_status = isset( $_POST['stock_status'] ) ? sanitize_key( wp_unslash( $_POST['stock_status'] ) ) : 'instock';
			if ( ! in_array( $stock_status, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
				$stock_status = 'instock';
			}
			$product->set_stock_status( $stock_status );

			$product->save();
			clean_post_cache( $product_id );

			$status_labels = array(
				'publish' => 'منتشر شده',
				'draft'   => 'پیش‌نویس',
				'pending' => 'در انتظار بررسی',
				'private' => 'خصوصی',
			);
			$current_status = $product->get_status();

			wp_send_json_success(
				array(
					'message'      => 'محصول ذخیره شد.',
					'id'           => $product_id,
					'status'       => $current_status,
					'status_label' => isset( $status_labels[ $current_status ] ) ? $status_labels[ $current_status ] : $current_status,
				)
			);
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), 422 );
		}
	}
}

function noghte_mpe_boot() {
	if ( class_exists( 'WooCommerce' ) || function_exists( 'WC' ) ) {
		Noghte_Modular_Product_Editor::instance();
	}
}

if ( did_action( 'plugins_loaded' ) ) {
	noghte_mpe_boot();
} else {
	add_action( 'plugins_loaded', 'noghte_mpe_boot', 20 );
}