<?php
/**
 * WooCommerce → تخفیف‌ها: what is on sale right now, and the settings.
 *
 * @package AromistDeals
 */

defined( 'ABSPATH' ) || exit;

final class ARD_Admin {

	const SLUG = 'ard-deals';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 60 );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ARD_FILE ), array( $this, 'links' ) );
	}

	public function menu(): void {
		add_submenu_page( 'techpino-settings', 'تخفیف‌های ویژه', 'تخفیف‌های ویژه', 'manage_woocommerce', self::SLUG, array( $this, 'page' ) );
	}

	/**
	 * @param array $links Plugin row links.
	 */
	public function links( $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">تنظیمات</a>' );
		return $links;
	}

	public function register(): void {
		register_setting(
			'ard_settings_group',
			ARD_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => ARD_Settings::defaults(),
			)
		);
	}

	/**
	 * @param mixed $in Posted values.
	 */
	public function sanitize( $in ): array {
		$in  = is_array( $in ) ? $in : array();
		$d   = ARD_Settings::defaults();
		$out = array();
		foreach ( array( 'badge', 'cart_badge', 'card_mark', 'card_select', 'pdp_timer', 'pdp_mark', 'pdp_select' ) as $key ) {
			$out[ $key ] = ! empty( $in[ $key ] ) && 'no' !== $in[ $key ] ? 'yes' : 'no';
		}
		// WordPress sanitizes twice when the option is first added: accept already-clean values too.
		foreach ( array( 'campaign_start', 'campaign_end' ) as $key ) {
			$raw         = $in[ $key ] ?? '';
			$out[ $key ] = is_int( $raw ) ? $raw : ARD_Settings::parse_date( (string) $raw );
		}
		if ( $out['campaign_end'] && $out['campaign_start'] >= $out['campaign_end'] ) {
			$out['campaign_start'] = 0;
		}
		$out['period_days'] = max( 1, min( 90, (int) ( $in['period_days'] ?? $d['period_days'] ) ) );
		$out['progress']    = 'elapsed' === ( $in['progress'] ?? '' ) ? 'elapsed' : 'remaining';
		$out['digits']      = in_array( $in['digits'] ?? '', array( 'all', 'deal', 'latin' ), true ) ? $in['digits'] : $d['digits'];
		foreach ( array( 'badge_bg', 'badge_fg' ) as $key ) {
			$color       = sanitize_hex_color( (string) ( $in[ $key ] ?? '' ) );
			$out[ $key ] = $color ? $color : $d[ $key ];
		}
		return $out;
	}

	private function check( string $key, string $label, string $help = '' ): void {
		$s = ARD_Settings::all();
		printf(
			'<label class="ard-row"><input type="checkbox" name="%1$s[%2$s]" value="yes" %3$s> <span><b>%4$s</b>%5$s</span></label>',
			esc_attr( ARD_Settings::OPTION ),
			esc_attr( $key ),
			checked( 'yes', $s[ $key ], false ),
			esc_html( $label ),
			$help ? '<small>' . esc_html( $help ) . '</small>' : ''
		);
	}

	public function page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s     = ARD_Settings::all();
		$deals = ARD_Deals::query(
			array(
				'orderby' => 'ending',
				'limit'   => 200,
			)
		);
		$name  = esc_attr( ARD_Settings::OPTION );
		?>
		<div class="wrap ard-admin" dir="rtl">
			<h1>تخفیف‌های ویژه</h1>
			<style>
				.ard-admin .card{max-width:980px;padding:8px 20px 16px}
				.ard-admin .ard-row{display:flex;gap:8px;align-items:flex-start;margin:10px 0}
				.ard-admin .ard-row small{display:block;color:#666;margin-top:2px}
				.ard-admin table.widefat td,.ard-admin table.widefat th{vertical-align:middle}
				.ard-admin .ard-pct{display:inline-block;min-width:34px;padding:2px 6px;border-radius:6px;background:<?php echo esc_attr( $s['badge_bg'] ); ?>;color:<?php echo esc_attr( $s['badge_fg'] ); ?>;text-align:center;font-weight:600}
				.ard-admin .ard-warn{color:#996800}
				.ard-admin .ard-muted{color:#888}
				.ard-admin input.ard-date{width:190px;direction:ltr;text-align:right}
			</style>

			<div class="card">
				<h2>الان چه محصولاتی تخفیف دارند؟</h2>
				<?php if ( ! $deals['items'] ) : ?>
					<p>هیچ محصولی الان تخفیفِ فعال و موجود ندارد. تخفیف را در ویرایش محصول، برای هر حجم («قیمت فروش ویژه») بگذارید؛ برای تایمر، «زمان‌بندی» آن را هم پر کنید.</p>
				<?php else : ?>
					<p>
						<?php
						echo esc_html( ARD_Settings::fa_digits( (string) $deals['total'] ) . ' محصول تخفیف‌دار. ' );
						if ( $deals['total'] < 4 ) {
							echo '<span class="ard-warn">' . esc_html( 'ویجت «تخفیف‌های ویژه» با حداقل پیش‌فرض ۴ محصول، فعلاً در سایت نمایش داده نمی‌شود.' ) . '</span>';
						}
						?>
					</p>
					<table class="widefat striped">
						<thead><tr><th>محصول</th><th>حجم</th><th>قیمت</th><th>تخفیف</th><th>پایان تخفیف</th></tr></thead>
						<tbody>
						<?php
						foreach ( $deals['items'] as $item ) :
							foreach ( $item['sizes'] as $size ) :
								if ( ! $size['sale'] ) {
									continue;
								}
								$own = wc_get_product( $size['vid'] );
								$end = $own && $own->get_date_on_sale_to() ? $own->get_date_on_sale_to()->getTimestamp() : 0;
								?>
								<tr>
									<td><a href="<?php echo esc_url( (string) get_edit_post_link( $item['id'] ) ); ?>"><?php echo esc_html( $item['name'] ); ?></a></td>
									<td><?php echo esc_html( $size['label'] ? $size['label'] : '—' ); ?><?php echo $size['in'] ? '' : ' <span class="ard-muted">(ناموجود)</span>'; ?></td>
									<td><del class="ard-muted"><?php echo wp_kses_post( wc_price( $size['regular'] ) ); ?></del> <?php echo wp_kses_post( wc_price( $size['price'] ) ); ?></td>
									<td><span class="ard-pct"><?php echo esc_html( ARD_Settings::fa_digits( (string) $size['pct'] ) . '٪' ); ?></span></td>
									<td>
										<?php
										if ( $end ) {
											echo esc_html( ARD_Settings::format_date( $end ) );
										} elseif ( $size['to'] ) {
											echo esc_html( ARD_Settings::format_date( $size['to'] ) ) . ' <span class="ard-muted">(پایان کمپین)</span>';
										} else {
											echo '<span class="ard-warn">بدون تاریخ پایان — تایمر ندارد</span>';
										}
										?>
									</td>
								</tr>
								<?php
							endforeach;
						endforeach;
						?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'ard_settings_group' ); ?>

				<div class="card">
					<h2>برچسب تخفیف در همه‌ی سایت</h2>
					<?php
					$this->check( 'badge', 'کنار قیمتِ محصولات تخفیف‌دار، کادر طلایی درصد تخفیف و قیمت قبلی (خط‌خورده) نمایش داده شود', 'در باکس محصولات، کاروسل‌ها، آرشیو و جستجو؛ صفحه‌ی محصول همان طرح فعلی خودش را دارد.' );
					$this->check( 'cart_badge', 'در سبد خرید، صفحه‌ی پرداخت و مینی‌سبد هم درصد تخفیف و قیمت قبلی نمایش داده شود', 'کنار جمع هر ردیف: کادر درصد و قیمت قبلی خط‌خورده؛ کنار قیمت واحد: قیمت قبلی خط‌خورده.' );
					$this->check( 'card_mark', 'در باکس محصولات، روی دایره‌ی حجم‌های تخفیف‌دار علامت «٪» بیاید' );
					$this->check( 'card_select', 'در باکس محصولات، حجمِ تخفیف‌دار از اول انتخاب شده باشد', 'وگرنه مثل قبل ارزان‌ترین حجم انتخاب می‌شود.' );
					?>
					<p><b>اعداد قیمت‌ها</b><br>
						<label><input type="radio" name="<?php echo $name; // phpcs:ignore ?>[digits]" value="all" <?php checked( 'all', $s['digits'] ); ?>> فارسی در همه‌ی قیمت‌های سایت (۲,۳۸۰,۰۰۰)</label><br>
						<label><input type="radio" name="<?php echo $name; // phpcs:ignore ?>[digits]" value="deal" <?php checked( 'deal', $s['digits'] ); ?>> فارسی فقط در قیمت‌های تخفیف‌دار و تایمرها</label><br>
						<label><input type="radio" name="<?php echo $name; // phpcs:ignore ?>[digits]" value="latin" <?php checked( 'latin', $s['digits'] ); ?>> انگلیسی (2,380,000) مثل قبل</label>
					</p>
					<p>
						<label>رنگ کادر تخفیف <input type="text" name="<?php echo $name; // phpcs:ignore ?>[badge_bg]" value="<?php echo esc_attr( $s['badge_bg'] ); ?>" size="8" dir="ltr"></label>
						&nbsp; <label>رنگ متن آن <input type="text" name="<?php echo $name; // phpcs:ignore ?>[badge_fg]" value="<?php echo esc_attr( $s['badge_fg'] ); ?>" size="8" dir="ltr"></label>
					</p>
				</div>

				<div class="card">
					<h2>صفحه‌ی محصول</h2>
					<?php
					$this->check( 'pdp_timer', 'زیر قیمت، شمارش معکوسِ پایان تخفیفِ حجم انتخاب‌شده نمایش داده شود', 'فقط برای تخفیف‌هایی که تاریخ پایان دارند (یا پایان کمپین پایین).' );
					$this->check( 'pdp_mark', 'روی دکمه‌ی حجم‌های تخفیف‌دار، درصد تخفیف نوشته شود' );
					$this->check( 'pdp_select', 'صفحه‌ی محصول با حجمِ تخفیف‌دار باز شود', 'مگر اینکه در ویرایش محصول «مقدار پیش‌فرض» گذاشته باشید.' );
					?>
				</div>

				<div class="card">
					<h2>تایمر و نوار زمان</h2>
					<p>تایمر هر حجم از «زمان‌بندی» تخفیفِ همان حجم در ویرایش محصول خوانده می‌شود. برای تخفیف‌هایی که تاریخ پایان ندارند، می‌توانید یک کمپین تعریف کنید:</p>
					<p>
						<label>شروع کمپین <input class="ard-date" type="text" name="<?php echo $name; // phpcs:ignore ?>[campaign_start]" value="<?php echo esc_attr( ARD_Settings::format_date( (int) $s['campaign_start'] ) ); ?>" placeholder="۱۴۰۵/۰۸/۰۱ ۰۰:۰۰"></label>
						&nbsp; <label>پایان کمپین <input class="ard-date" type="text" name="<?php echo $name; // phpcs:ignore ?>[campaign_end]" value="<?php echo esc_attr( ARD_Settings::format_date( (int) $s['campaign_end'] ) ); ?>" placeholder="۱۴۰۵/۰۸/۱۰ ۲۳:۵۹"></label>
					</p>
					<p class="description">تاریخ شمسی با ساعت (مثل ۱۴۰۵/۰۸/۱۰ ۲۳:۵۹). خالی = تخفیف‌های بدون تاریخ، تایمر ندارند. بعد از پایان کمپین، تایمر خودبه‌خود حذف می‌شود (خود تخفیف را باید در محصول بردارید).</p>
					<p>
						<label>اگر تخفیف تاریخ شروع ندارد، نوار زمان
						<input type="number" min="1" max="90" name="<?php echo $name; // phpcs:ignore ?>[period_days]" value="<?php echo esc_attr( (string) $s['period_days'] ); ?>" style="width:70px"> روز آخر را نشان دهد</label>
					</p>
					<p>
						<label><input type="radio" name="<?php echo $name; // phpcs:ignore ?>[progress]" value="remaining" <?php checked( 'remaining', $s['progress'] ); ?>> نوار با نزدیک شدن به پایان، خالی شود</label><br>
						<label><input type="radio" name="<?php echo $name; // phpcs:ignore ?>[progress]" value="elapsed" <?php checked( 'elapsed', $s['progress'] ); ?>> نوار با گذشت زمان، پر شود</label>
					</p>
				</div>

				<?php submit_button( 'ذخیره' ); ?>
			</form>

			<div class="card">
				<h2>نمایش در صفحه‌ها</h2>
				<p>در المنتور ویجت <b>«تخفیف‌های ویژه (تکپینو)»</b> را هر جا خواستید بگذارید. تا وقتی حداقل تعداد محصول تخفیف‌دار (پیش‌فرض ۴) نباشد، در سایت دیده نمی‌شود و می‌تواند کانتینرش را هم پنهان کند.</p>
				<p>بیرون از المنتور: <code>[aromist_deals]</code> — مثلاً <code>[aromist_deals layout="grid" skin="ref" min="1"]</code> برای صفحه‌ی «همه‌ی تخفیف‌ها».</p>
			</div>
		</div>
		<?php
	}
}
