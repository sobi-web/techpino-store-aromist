<?php
/**
 * منطق مشترک: خواندن دکمه‌ها، پیدا کردن کاروسل هدف و اعمال فیلتر روی کوئری Loop.
 *
 * کاروسل هدف یا با «شناسهٔ کاروسل» (CSS ID یا شناسهٔ المنتور، هر جای سند) تعیین می‌شود یا، اگر خالی بود،
 * اولین Loop داخل همان کانتینر. ویجت فیلتر «تنها منبع حقیقت» ترم فصل است:
 * هم رندر اول صفحه (دکمهٔ پیش‌فرض) و هم درخواست‌های AJAX از همین‌جا فیلتر می‌شوند.
 *
 * @package AromistSeasonFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ASF_Core {

	const WIDGET = 'aromist_season_filter';

	/**
	 * فقط هنگام رندر درخواست AJAX پر است: array( 'target' => id کاروسل, 'selection' => array ).
	 *
	 * @var array|null
	 */
	private static $ajax_ctx = null;

	/** @var array doc id => درخت خام المان‌ها */
	private static $trees = array();

	/** @var array doc id => نگاشت Loop هدف به فیلتر */
	private static $maps = array();

	public static function init() {
		add_filter( 'elementor/query/query_args', array( __CLASS__, 'filter_query_args' ), 20, 2 );
	}

	public static function set_ajax_context( $ctx ) {
		self::$ajax_ctx = $ctx;
	}

	/**
	 * مقدار کنترل «taxonomy:term_id» را به آرایه تبدیل می‌کند.
	 *
	 * @param mixed $value مقدار ذخیره‌شده.
	 * @return array|null array( taxonomy, term_id ).
	 */
	public static function parse_term( $value ) {
		if ( ! is_string( $value ) || false === strpos( $value, ':' ) ) {
			return null;
		}
		list( $taxonomy, $term_id ) = explode( ':', $value, 2 );
		$term_id                    = (int) $term_id;
		if ( ! $term_id || ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}
		return array( $taxonomy, $term_id );
	}

	/**
	 * دکمه‌های معتبر ویجت، با حفظ اندیس ردیف در ریپیتر.
	 *
	 * @param array $settings تنظیمات ویجت فیلتر.
	 * @return array index => array( label, taxonomy, term ).
	 */
	public static function pills( array $settings ) {
		$out = array();
		if ( empty( $settings['pills'] ) || ! is_array( $settings['pills'] ) ) {
			return $out;
		}
		foreach ( array_values( $settings['pills'] ) as $index => $row ) {
			$label  = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
			$parsed = self::parse_term( isset( $row['term'] ) ? $row['term'] : '' );
			if ( '' === $label || ! $parsed ) {
				continue;
			}
			$out[ $index ] = array(
				'label'    => $label,
				'taxonomy' => $parsed[0],
				'term'     => $parsed[1],
			);
		}
		return $out;
	}

	/**
	 * اندیس دکمهٔ پیش‌فرض (شمارهٔ ردیف ریپیتر، از ۱) یا اولین دکمهٔ معتبر.
	 *
	 * @param array $settings تنظیمات ویجت فیلتر.
	 * @return int|null
	 */
	public static function default_index( array $settings ) {
		$pills = self::pills( $settings );
		if ( ! $pills ) {
			return null;
		}
		$want = isset( $settings['default'] ) ? (int) $settings['default'] - 1 : 0;
		if ( isset( $pills[ $want ] ) ) {
			return $want;
		}
		$keys = array_keys( $pills );
		return $keys[0];
	}

	/**
	 * فیلتر انتخاب‌شده برای یک دکمه.
	 *
	 * @param array $settings تنظیمات ویجت فیلتر.
	 * @param int   $index    اندیس ردیف ریپیتر.
	 * @return array|null array( taxonomy, term, taxonomies ) که taxonomies همهٔ تاکسونومی‌های دکمه‌هاست.
	 */
	public static function selection( array $settings, $index ) {
		$pills = self::pills( $settings );
		if ( ! isset( $pills[ $index ] ) ) {
			return null;
		}
		$taxonomies = array();
		foreach ( $pills as $pill ) {
			$taxonomies[ $pill['taxonomy'] ] = true;
		}
		return array(
			'taxonomy'   => $pills[ $index ]['taxonomy'],
			'term'       => $pills[ $index ]['term'],
			'taxonomies' => array_keys( $taxonomies ),
		);
	}

	/**
	 * شناسهٔ سندی که همین حالا رندر می‌شود.
	 *
	 * @return int
	 */
	public static function current_doc_id() {
		$document = \Elementor\Plugin::$instance->documents->get_current();
		return $document ? (int) $document->get_main_id() : (int) get_the_ID();
	}

	/**
	 * درخت خام المان‌های یک سند؛ فقط اگر ویجت فیلتر در آن باشد (بررسی ارزان روی متای خام).
	 *
	 * @param int $doc_id شناسهٔ سند.
	 * @return array
	 */
	private static function tree( $doc_id ) {
		if ( ! isset( self::$trees[ $doc_id ] ) ) {
			$tree = array();
			$raw  = get_post_meta( $doc_id, '_elementor_data', true );
			if ( is_string( $raw ) && false !== strpos( $raw, self::WIDGET ) ) {
				$document = \Elementor\Plugin::$instance->documents->get( $doc_id );
				$tree     = $document ? (array) $document->get_elements_data() : array();
			}
			self::$trees[ $doc_id ] = $tree;
		}
		return self::$trees[ $doc_id ];
	}

	/**
	 * شناسهٔ المنتوریِ ویجت Loop هدف یک ویجت فیلتر ('' اگر پیدا نشد).
	 *
	 * @param int    $doc_id    شناسهٔ سند.
	 * @param string $filter_id شناسهٔ ویجت فیلتر.
	 * @return string
	 */
	public static function target_for_filter( $doc_id, $filter_id ) {
		$tree  = self::tree( $doc_id );
		$found = self::find_with_parent( $tree, $filter_id );
		if ( ! $found ) {
			return '';
		}
		$target = self::resolve_target( $tree, $found[0], $found[1] );
		return $target ? (string) $target['id'] : '';
	}

	/**
	 * نگاشت «شناسهٔ Loop هدف => المان خام فیلتر» برای یک سند.
	 *
	 * @param int $doc_id شناسهٔ سند.
	 * @return array
	 */
	private static function filters_by_target( $doc_id ) {
		if ( ! isset( self::$maps[ $doc_id ] ) ) {
			$map  = array();
			$tree = self::tree( $doc_id );
			self::collect( $tree, $tree, null, $map );
			self::$maps[ $doc_id ] = $map;
		}
		return self::$maps[ $doc_id ];
	}

	private static function collect( array $tree, array $elements, $parent, array &$map ) {
		foreach ( $elements as $element ) {
			if ( isset( $element['widgetType'] ) && self::WIDGET === $element['widgetType'] ) {
				$target = self::resolve_target( $tree, $element, $parent );
				if ( $target ) {
					$map[ (string) $target['id'] ] = $element;
				}
			}
			if ( ! empty( $element['elements'] ) ) {
				self::collect( $tree, $element['elements'], $element, $map );
			}
		}
	}

	/**
	 * تنظیمات ویجت فیلتر با مقادیر پیش‌فرض (از روی داده‌ی خام).
	 *
	 * @param array $node المان خام فیلتر.
	 * @return array
	 */
	public static function settings_of( array $node ) {
		$instance = \Elementor\Plugin::$instance->elements_manager->create_element_instance( $node );
		return $instance ? (array) $instance->get_settings_for_display() : array();
	}

	/**
	 * پیدا کردن المان در داده‌ی خام سند المنتور همراه والدش.
	 *
	 * @param array      $elements آرایهٔ المان‌ها.
	 * @param string     $id       شناسه.
	 * @param array|null $parent   والد.
	 * @return array|null array( element, parent ).
	 */
	public static function find_with_parent( array $elements, $id, $parent = null ) {
		foreach ( $elements as $element ) {
			if ( isset( $element['id'] ) && (string) $element['id'] === (string) $id ) {
				return array( $element, $parent );
			}
			if ( ! empty( $element['elements'] ) ) {
				$found = self::find_with_parent( $element['elements'], $id, $element );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * اولین ویجت Loop در فرزندان یک کانتینر (داده‌ی خام).
	 *
	 * @param array $parent المان والد.
	 * @return array|null
	 */
	public static function first_loop_child( array $parent ) {
		if ( empty( $parent['elements'] ) ) {
			return null;
		}
		foreach ( $parent['elements'] as $child ) {
			if ( isset( $child['elType'], $child['widgetType'] ) && 'widget' === $child['elType'] && 0 === strpos( (string) $child['widgetType'], 'loop-' ) ) {
				return $child;
			}
		}
		return null;
	}

	/**
	 * مقدار فیلد «شناسهٔ کاروسل»: بدون فاصله و بدون # اول.
	 *
	 * @param mixed $value مقدار خام.
	 * @return string
	 */
	public static function clean_ref( $value ) {
		return ltrim( trim( (string) $value ), '#' );
	}

	/**
	 * ویجت Loop را با CSS ID (تب Advanced) یا شناسهٔ داخلی المنتور در کل سند پیدا می‌کند.
	 *
	 * @param array  $elements آرایهٔ المان‌ها.
	 * @param string $ref      CSS ID یا شناسهٔ المنتور.
	 * @return array|null
	 */
	public static function find_loop_by_ref( array $elements, $ref ) {
		foreach ( $elements as $element ) {
			if ( isset( $element['elType'], $element['widgetType'] ) && 'widget' === $element['elType'] && 0 === strpos( (string) $element['widgetType'], 'loop-' ) ) {
				$css_id = isset( $element['settings']['_element_id'] ) ? self::clean_ref( $element['settings']['_element_id'] ) : '';
				if ( ( '' !== $css_id && $css_id === $ref ) || ( isset( $element['id'] ) && (string) $element['id'] === $ref ) ) {
					return $element;
				}
			}
			if ( ! empty( $element['elements'] ) ) {
				$found = self::find_loop_by_ref( $element['elements'], $ref );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * ویجت Loop هدفِ یک ویجت فیلتر: اگر «شناسهٔ کاروسل» پر باشد همان (هر جای سند)، وگرنه اولین Loop هم‌کانتینر.
	 *
	 * @param array      $tree        درخت خام سند.
	 * @param array      $filter_node المان خام ویجت فیلتر.
	 * @param array|null $parent      المان خام والد فیلتر.
	 * @return array|null
	 */
	public static function resolve_target( array $tree, array $filter_node, $parent = null ) {
		$ref = isset( $filter_node['settings']['target_id'] ) ? self::clean_ref( $filter_node['settings']['target_id'] ) : '';
		if ( '' !== $ref ) {
			return self::find_loop_by_ref( $tree, $ref );
		}
		return is_array( $parent ) ? self::first_loop_child( $parent ) : null;
	}

	/**
	 * @param array  $args   آرگومان‌های WP_Query ساخته‌شده توسط المنتور.
	 * @param object $widget ویجت Loop.
	 * @return array
	 */
	public static function filter_query_args( $args, $widget ) {
		if ( ! is_array( $args ) || ! is_object( $widget ) || ! method_exists( $widget, 'get_id' ) ) {
			return $args;
		}

		$selection = null;
		if ( null !== self::$ajax_ctx ) {
			if ( (string) $widget->get_id() === (string) self::$ajax_ctx['target'] ) {
				$selection = self::$ajax_ctx['selection'];
			}
		} else {
			$doc_id = self::current_doc_id();
			$map    = $doc_id ? self::filters_by_target( $doc_id ) : array();
			$id     = (string) $widget->get_id();
			if ( isset( $map[ $id ] ) ) {
				$settings = self::settings_of( $map[ $id ] );
				$index    = self::default_index( $settings );
				if ( null !== $index ) {
					$selection = self::selection( $settings, $index );
				}
			}
		}

		if ( ! $selection ) {
			return $args;
		}

		$tax_query = ( isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) ? $args['tax_query'] : array();
		foreach ( $tax_query as $key => $clause ) {
			// ترم فصلِ تنظیم‌شده روی خود کاروسل نباید با ترم دکمه تداخل کند.
			if ( is_array( $clause ) && isset( $clause['taxonomy'] ) && in_array( $clause['taxonomy'], $selection['taxonomies'], true ) ) {
				unset( $tax_query[ $key ] );
			}
		}
		$tax_query[]       = array(
			'taxonomy' => $selection['taxonomy'],
			'field'    => 'term_id',
			'terms'    => array( (int) $selection['term'] ),
		);
		$args['tax_query'] = $tax_query;

		return $args;
	}
}
