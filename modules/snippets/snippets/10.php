<?php
// اسنیپت #10: افزونه لیست محصولات ووکامرس
defined( 'ABSPATH' ) || exit;

/**
 * Noghte Group
 * WooCommerce Modern Products Admin v2.0
 *
 * noghte-group.ir
 */


/* =========================================================
 * Helpers
 * ======================================================= */

function ngp2_is_products_screen() {

	if (
		! is_admin() ||
		! function_exists( 'get_current_screen' )
	) {
		return false;
	}

	$screen = get_current_screen();

	return $screen && 'edit-product' === $screen->id;
}


function ngp2_req( $key, $default = '' ) {

	return isset( $_GET[ $key ] )
		? wc_clean( wp_unslash( $_GET[ $key ] ) )
		: $default;
}


/* =========================================================
 * AJAX PRODUCT SEARCH
 * ======================================================= */

add_action( 'wp_ajax_ngp2_product_search', function () {

	check_ajax_referer(
		'ngp2_search',
		'nonce'
	);


	if ( ! current_user_can( 'edit_products' ) ) {

		wp_send_json_error(
			array(
				'message' => 'دسترسی کافی ندارید.'
			),
			403
		);
	}


	$term = isset( $_POST['q'] )
		? wc_clean( wp_unslash( $_POST['q'] ) )
		: '';


	$len = function_exists( 'mb_strlen' )
		? mb_strlen( $term )
		: strlen( $term );


	if (
		$len < 2 ||
		! class_exists( 'WC_Data_Store' )
	) {

		wp_send_json_success(
			array()
		);
	}


	try {

		$ids = array();


		/*
		 * Exact ID search
		 */
		if ( ctype_digit( $term ) ) {

			$exact = wc_get_product(
				absint( $term )
			);


			if ( $exact ) {

				$ids[] =
					$exact->get_id();

			}

		}


		/*
		 * WooCommerce native search
		 */
		$store =
			WC_Data_Store::load(
				'product'
			);


		$ids = array_merge(

			$ids,

			(array) $store->search_products(
				$term,
				'',
				true,
				true,
				15
			)

		);


		$ids = array_values(
			array_unique(
				array_map(
					'absint',
					$ids
				)
			)
		);


		$items = array();

		$seen = array();


		foreach ( $ids as $id ) {

			$product =
				wc_get_product(
					$id
				);


			if ( ! $product ) {
				continue;
			}


			/*
			 * Variation => parent
			 */
			if (
				$product->is_type(
					'variation'
				) &&
				$product->get_parent_id()
			) {

				$product =
					wc_get_product(
						$product->get_parent_id()
					);

			}


			if (
				! $product ||
				isset(
					$seen[
						$product->get_id()
					]
				)
			) {
				continue;
			}


			if (
				in_array(
					get_post_status(
						$product->get_id()
					),
					array(
						'trash',
						'auto-draft'
					),
					true
				)
			) {
				continue;
			}


			$seen[
				$product->get_id()
			] = true;


			$status =
				$product->get_stock_status();


			$labels = array(

				'instock' =>
					'موجود',

				'outofstock' =>
					'ناموجود',

				'onbackorder' =>
					'پیش‌سفارش',

			);


			$image_id =
				$product->get_image_id();


			$image =
				$image_id

				? wp_get_attachment_image_url(
					$image_id,
					'thumbnail'
				)

				: wc_placeholder_img_src(
					'thumbnail'
				);


			$types =
				wc_get_product_types();


			$items[] = array(

				'id' =>
					$product->get_id(),

				'name' =>
					$product->get_name(),

				'sku' =>
					$product->get_sku(),

				'image' =>
					esc_url_raw( $image ),

				'price' =>
					wp_kses_post(
						$product->get_price_html()
					),

				'stock_status' =>
					$status,

				'stock_label' =>
					isset(
						$labels[ $status ]
					)
						? $labels[ $status ]
						: $status,

				'stock_qty' =>
					$product->managing_stock()

						? $product->get_stock_quantity()

						: null,

				'type_label' =>
					isset(
						$types[
							$product->get_type()
						]
					)

						? $types[
							$product->get_type()
						]

						: $product->get_type(),

				'edit_url' =>
					get_edit_post_link(
						$product->get_id(),
						'raw'
					),

			);


			if (
				count( $items ) >= 12
			) {
				break;
			}

		}


		wp_send_json_success(
			$items
		);


	} catch ( Throwable $e ) {

		wp_send_json_error(
			array(
				'message' =>
					'خطا در جستجوی محصولات.'
			),
			500
		);

	}

} );


/* =========================================================
 * CUSTOM UI
 * ======================================================= */

add_action(
	'all_admin_notices',
	function () {

		if (
			! ngp2_is_products_screen() ||
			! function_exists(
				'wc_get_product_types'
			)
		) {
			return;
		}


		$current_cat =
			ngp2_req(
				'product_cat'
			);


		$current_type =
			ngp2_req(
				'product_type'
			);


		$current_stock =
			ngp2_req(
				'stock_status'
			);


		$current_state =
			ngp2_req(
				'post_status'
			);


		$orderby =
			ngp2_req(
				'orderby'
			);


		$order =
			strtoupper(
				ngp2_req(
					'order',
					'DESC'
				)
			);


		$current_sort =
			$orderby
				? $orderby . ':' . $order
				: 'date:DESC';


		/*
		 * Categories
		 */
		$categories =
			get_terms(
				array(

					'taxonomy' =>
						'product_cat',

					'hide_empty' =>
						false,

					'orderby' =>
						'name',

					'order' =>
						'ASC',

				)
			);


		if (
			is_wp_error(
				$categories
			)
		) {

			$categories =
				array();

		}


		$types =
			wc_get_product_types();


		$stocks =
			wc_get_product_stock_status_options();

		?>

		<div
			id="ngp2-manager"
			class="ngp2-manager"
			dir="rtl"
		>


			<!-- ===========================
			     HEADER
			=========================== -->

			<section class="ngp2-hero">


				<div class="ngp2-title-wrap">

					<span class="ngp2-title-icon">

						<span
							class="dashicons dashicons-products"
						></span>

					</span>


					<div>

						<h1>
							مدیریت محصولات
						</h1>

						<p>
							جستجو، فیلتر و مدیریت سریع محصولات فروشگاه
						</p>

					</div>

				</div>


				<a

					class="ngp2-add"

					href="<?php
					echo esc_url(
						admin_url(
							'post-new.php?post_type=product'
						)
					);
					?>"

				>

					<span
						class="dashicons dashicons-plus-alt2"
					></span>

					افزودن محصول

				</a>


			</section>



			<!-- ===========================
			     SEARCH
			=========================== -->

			<section class="ngp2-search-wrap">


				<div class="ngp2-search">


					<span
						class="dashicons dashicons-search ngp2-search-icon"
					></span>


					<input

						id="ngp2-search-input"

						type="search"

						autocomplete="off"

						placeholder="جستجو با نام محصول، SKU یا شناسه محصول..."

					>


					<span

						id="ngp2-search-spinner"

						class="ngp2-spinner"

						hidden

					></span>


					<button

						id="ngp2-search-clear"

						type="button"

						class="ngp2-clear"

						aria-label="پاک کردن"

						hidden

					>

						<span
							class="dashicons dashicons-no-alt"
						></span>

					</button>


				</div>


				<div

					id="ngp2-search-results"

					class="ngp2-results"

					hidden

				></div>


			</section>



			<!-- ===========================
			     FILTERS
			=========================== -->

			<section class="ngp2-filter-card">


				<div class="ngp2-filter-head">


					<div>

						<span
							class="dashicons dashicons-filter"
						></span>

						<strong>
							فیلتر محصولات
						</strong>

					</div>


					<a href="<?php

						echo esc_url(
							admin_url(
								'edit.php?post_type=product'
							)
						);

					?>">

						پاک کردن همه

					</a>


				</div>


				<div class="ngp2-filter-grid">


					<!-- Category -->

					<label>

						<span>
							دسته‌بندی
						</span>


						<select id="ngp2-cat">

							<option value="">
								همه دسته‌بندی‌ها
							</option>


							<?php foreach (
								$categories
								as $cat
							) : ?>

								<option

									value="<?php
									echo esc_attr(
										$cat->slug
									);
									?>"

									<?php
									selected(
										$current_cat,
										$cat->slug
									);
									?>

								>

									<?php
									echo esc_html(
										$cat->name
									);
									?>

								</option>

							<?php endforeach; ?>

						</select>

					</label>



					<!-- Product type -->

					<label>

						<span>
							نوع محصول
						</span>


						<select id="ngp2-type">

							<option value="">
								همه نوع‌ها
							</option>


							<?php foreach (
								$types
								as $value => $label
							) : ?>

								<option

									value="<?php
									echo esc_attr(
										$value
									);
									?>"

									<?php
									selected(
										$current_type,
										$value
									);
									?>

								>

									<?php
									echo esc_html(
										$label
									);
									?>

								</option>

							<?php endforeach; ?>

						</select>

					</label>



					<!-- Stock -->

					<label>

						<span>
							وضعیت موجودی
						</span>


						<select id="ngp2-stock">

							<option value="">
								همه وضعیت‌ها
							</option>


							<?php foreach (
								$stocks
								as $value => $label
							) : ?>

								<option

									value="<?php
									echo esc_attr(
										$value
									);
									?>"

									<?php
									selected(
										$current_stock,
										$value
									);
									?>

								>

									<?php
									echo esc_html(
										$label
									);
									?>

								</option>

							<?php endforeach; ?>

						</select>

					</label>



					<!-- Status -->

					<label>

						<span>
							وضعیت انتشار
						</span>


						<select id="ngp2-status">


							<option value="">
								همه وضعیت‌ها
							</option>


							<option
								value="publish"
								<?php
								selected(
									$current_state,
									'publish'
								);
								?>
							>
								منتشرشده
							</option>


							<option
								value="draft"
								<?php
								selected(
									$current_state,
									'draft'
								);
								?>
							>
								پیش‌نویس
							</option>


							<option
								value="pending"
								<?php
								selected(
									$current_state,
									'pending'
								);
								?>
							>
								در انتظار بررسی
							</option>


							<option
								value="private"
								<?php
								selected(
									$current_state,
									'private'
								);
								?>
							>
								خصوصی
							</option>


						</select>

					</label>



					<!-- Sort -->

					<label>

						<span>
							مرتب‌سازی
						</span>


						<select id="ngp2-sort">


							<option
								value="date:DESC"
								<?php
								selected(
									$current_sort,
									'date:DESC'
								);
								?>
							>
								جدیدترین
							</option>


							<option
								value="date:ASC"
								<?php
								selected(
									$current_sort,
									'date:ASC'
								);
								?>
							>
								قدیمی‌ترین
							</option>


							<option
								value="title:ASC"
								<?php
								selected(
									$current_sort,
									'title:ASC'
								);
								?>
							>
								نام: الف تا ی
							</option>


							<option
								value="title:DESC"
								<?php
								selected(
									$current_sort,
									'title:DESC'
								);
								?>
							>
								نام: ی تا الف
							</option>


							<option
								value="price:ASC"
								<?php
								selected(
									$current_sort,
									'price:ASC'
								);
								?>
							>
								قیمت: کم به زیاد
							</option>


							<option
								value="price:DESC"
								<?php
								selected(
									$current_sort,
									'price:DESC'
								);
								?>
							>
								قیمت: زیاد به کم
							</option>


						</select>

					</label>



					<div class="ngp2-filter-actions">


						<button
							id="ngp2-apply"
							type="button"
						>

							<span
								class="dashicons dashicons-filter"
							></span>

							اعمال فیلتر

						</button>


						<a href="<?php

							echo esc_url(
								admin_url(
									'edit.php?post_type=product'
								)
							);

						?>">

							ریست

						</a>


					</div>


				</div>


			</section>


		</div>

		<?php

	}
);


/* =========================================================
 * CSS
 * ======================================================= */

add_action(
	'admin_head',
	function () {

		if ( ! ngp2_is_products_screen() ) {
			return;
		}

		?>

<style>

/* =========================================================
   VARIABLES
========================================================= */

.post-type-product {

	--ngp2-bg: #f5f7fb;

	--ngp2-card: #ffffff;

	--ngp2-text: #191b22;

	--ngp2-muted: #7a8190;

	--ngp2-line: #e7eaf0;

	--ngp2-primary: #6257ff;

	--ngp2-danger: #d92d20;

	--ngp2-dangerbg: #fff1f1;

	--ngp2-success: #067647;

	--ngp2-successbg: #ecfdf3;

	--ngp2-warn: #b54708;

	--ngp2-warnbg: #fffaeb;

}



/* =========================================================
   PAGE WIDTH
========================================================= */

.post-type-product #wpcontent,
.post-type-product #wpbody-content {

	background: var(--ngp2-bg);

}


/*
 * فاصله واقعی از دو طرف صفحه
 */
.post-type-product
#wpbody-content > .wrap {

	box-sizing: border-box;

	width: 100%;

	max-width: 1680px;

	margin:
		0 auto !important;

	padding:
		0 26px 48px !important;

}


/*
 * Custom manager
 */
#ngp2-manager {

	box-sizing: border-box;

	width: 100%;

	max-width: 1680px;

	margin:
		22px auto 18px;

	padding:
		0 26px;

	font-family: inherit;

}



/* =========================================================
   REMOVE DEFAULT TITLE
========================================================= */

.post-type-product
.wrap > h1.wp-heading-inline,

.post-type-product
.wrap > .page-title-action {

	display: none !important;

}



/* =========================================================
   CARDS
========================================================= */

.ngp2-hero,
.ngp2-search-wrap,
.ngp2-filter-card {

	background: #fff;

	border:
		1px solid
		var(--ngp2-line);

	box-shadow:
		0 8px 30px
		rgba(
			16,
			24,
			40,
			.035
		);

}



/* =========================================================
   HERO
========================================================= */

.ngp2-hero {

	display: flex;

	align-items: center;

	justify-content:
		space-between;

	gap: 20px;

	padding:
		20px 22px;

	border-radius: 20px;

	margin-bottom: 14px;

}


.ngp2-title-wrap {

	display: flex;

	align-items: center;

	gap: 13px;

}


.ngp2-title-icon {

	width: 46px;

	height: 46px;

	display: grid;

	place-items: center;

	background: #f0efff;

	color:
		var(--ngp2-primary);

	border-radius: 14px;

}


.ngp2-title-icon
.dashicons {

	font-size: 24px;

	width: 24px;

	height: 24px;

}


.ngp2-title-wrap h1 {

	margin: 0 !important;

	padding: 0 !important;

	font-size:
		20px !important;

	line-height:
		1.5 !important;

	font-weight:
		800 !important;

	color:
		var(--ngp2-text);

}


.ngp2-title-wrap p {

	margin:
		3px 0 0;

	color:
		var(--ngp2-muted);

	font-size: 12px;

}



/* ADD PRODUCT */

.ngp2-add {

	height: 42px;

	display: inline-flex;

	align-items: center;

	gap: 6px;

	padding:
		0 16px;

	border-radius: 12px;

	background:
		var(--ngp2-primary);

	color:
		#fff !important;

	text-decoration: none;

	font-weight: 700;

	box-shadow:
		0 7px 18px
		rgba(
			98,
			87,
			255,
			.18
		);

	transition:
		.2s ease;

}


.ngp2-add:hover {

	background: #5046ec;

	color: #fff;

	transform:
		translateY(-1px);

}



/* =========================================================
   SEARCH
========================================================= */

.ngp2-search-wrap {

	position: relative;

	border-radius: 18px;

	margin-bottom: 14px;

	overflow: visible;

}


.ngp2-search {

	height: 58px;

	display: flex;

	align-items: center;

	padding:
		0 17px;

	gap: 10px;

}


.ngp2-search-icon {

	color: #8b92a4;

	font-size: 22px;

	width: 22px;

	height: 22px;

}


.ngp2-search input {

	flex: 1;

	min-width: 0;

	height:
		54px !important;

	border:
		0 !important;

	outline:
		0 !important;

	box-shadow:
		none !important;

	background:
		transparent !important;

	font-size:
		14px !important;

	color:
		var(--ngp2-text) !important;

	padding:
		0 !important;

}


.ngp2-search input::placeholder {

	color: #a2a8b5;

}


.ngp2-search-wrap:focus-within {

	border-color: #c7c3ff;

	box-shadow:

		0 0 0 4px
		rgba(
			98,
			87,
			255,
			.07
		),

		0 8px 30px
		rgba(
			16,
			24,
			40,
			.04
		);

}



/* CLEAR */

.ngp2-clear {

	width: 34px;

	height: 34px;

	border: 0;

	border-radius: 9px;

	background: #f4f5f8;

	color: #737b8b;

	cursor: pointer;

}



/* SPINNER */

.ngp2-spinner {

	width: 17px;

	height: 17px;

	border:
		2px solid #e0e3e8;

	border-top-color:
		var(--ngp2-primary);

	border-radius: 50%;

	animation:
		ngp2spin
		.7s
		linear
		infinite;

}


@keyframes ngp2spin {

	to {

		transform:
			rotate(360deg);

	}

}



/* =========================================================
   SEARCH RESULTS
========================================================= */

.ngp2-results {

	position: absolute;

	top:
		calc(
			100% + 8px
		);

	right: 0;

	left: 0;

	z-index: 99999;

	background: #fff;

	border:
		1px solid
		var(--ngp2-line);

	border-radius: 18px;

	box-shadow:
		0 22px 70px
		rgba(
			16,
			24,
			40,
			.16
		);

	overflow: hidden;

	max-height: 520px;

	overflow-y: auto;

}


.ngp2-result {

	display: grid;

	grid-template-columns:
		48px
		minmax(0, 1fr)
		auto
		auto;

	align-items: center;

	gap: 12px;

	padding:
		11px 13px;

	border-bottom:
		1px solid #f0f1f4;

	text-decoration:
		none !important;

	color:
		inherit !important;

	transition:
		.15s ease;

}


.ngp2-result:last-child {

	border-bottom: 0;

}


.ngp2-result:hover {

	background: #fafaff;

}


.ngp2-result img {

	width: 48px;

	height: 48px;

	object-fit: cover;

	border-radius: 11px;

	border:
		1px solid #eceef2;

}


.ngp2-result-name {

	display: block;

	font-weight: 750;

	color: #20232c;

	white-space: nowrap;

	overflow: hidden;

	text-overflow: ellipsis;

}


.ngp2-result-meta {

	display: flex;

	gap: 7px;

	flex-wrap: wrap;

	margin-top: 5px;

	color: #858c9c;

	font-size: 11px;

}


.ngp2-result-price {

	font-weight: 750;

	white-space: nowrap;

	color: #313541;

}



/* BADGES */

.ngp2-badge {

	display: inline-flex;

	align-items: center;

	padding:
		4px 8px;

	border-radius: 999px;

	font-size: 10px;

	font-weight: 750;

	white-space: nowrap;

}


.ngp2-instock {

	background:
		var(--ngp2-successbg);

	color:
		var(--ngp2-success);

}


.ngp2-outofstock {

	background:
		var(--ngp2-dangerbg);

	color:
		var(--ngp2-danger);

}


.ngp2-onbackorder {

	background:
		var(--ngp2-warnbg);

	color:
		var(--ngp2-warn);

}


.ngp2-empty {

	padding: 26px;

	text-align: center;

	color: #858c9b;

}



/* =========================================================
   FILTERS
========================================================= */

.ngp2-filter-card {

	padding:
		17px 18px 18px;

	border-radius: 18px;

	margin-bottom: 14px;

}


.ngp2-filter-head {

	display: flex;

	align-items: center;

	justify-content:
		space-between;

	margin-bottom: 13px;

}


.ngp2-filter-head > div {

	display: flex;

	align-items: center;

	gap: 7px;

	color: #343844;

}


.ngp2-filter-head
.dashicons {

	color:
		var(--ngp2-primary);

}


.ngp2-filter-head a {

	font-size: 11px;

	color: #777f90;

	text-decoration: none;

}



/* GRID */

.ngp2-filter-grid {

	display: grid;

	grid-template-columns:

		1.15fr
		.9fr
		.9fr
		.9fr
		1fr
		auto;

	gap: 10px;

	align-items: end;

}


.ngp2-filter-grid
label > span {

	display: block;

	margin:
		0 2px 6px;

	font-size: 11px;

	font-weight: 650;

	color: #747b8b;

}


.ngp2-filter-grid select {

	width: 100%;

	height:
		42px !important;

	min-height:
		42px !important;

	border:
		1px solid #e2e5eb !important;

	border-radius:
		11px !important;

	background:
		#fafbfc !important;

	box-shadow:
		none !important;

	padding:
		0 11px !important;

	color:
		#343844 !important;

	font-size:
		12px !important;

}



/* FILTER BUTTONS */

.ngp2-filter-actions {

	display: flex;

	gap: 7px;

}


.ngp2-filter-actions
button,

.ngp2-filter-actions
a {

	height: 42px;

	display: inline-flex;

	align-items: center;

	justify-content: center;

	gap: 5px;

	border-radius: 11px;

	text-decoration: none;

	font-size: 12px;

	font-weight: 700;

	white-space: nowrap;

}


.ngp2-filter-actions
button {

	border: 0;

	background: #191b22;

	color: #fff;

	padding:
		0 15px;

	cursor: pointer;

}


.ngp2-filter-actions
a {

	background: #f2f4f7;

	color: #667085;

	padding:
		0 12px;

}



/* =========================================================
   HIDE ORIGINAL FILTERS
========================================================= */

.post-type-product
#posts-filter > p.search-box {

	display: none !important;

}


.post-type-product
#posts-filter
.tablenav.top
.actions:not(.bulkactions) {

	display: none !important;

}



/* =========================================================
   STATUS NAVIGATION
========================================================= */

.post-type-product
.subsubsub {

	float: none !important;

	display: flex;

	gap: 7px;

	flex-wrap: wrap;

	margin:
		8px 0 12px !important;

}


.post-type-product
.subsubsub li {

	font-size:
		0 !important;

	margin:
		0 !important;

}


.post-type-product
.subsubsub a {

	display: inline-flex;

	padding:
		7px 10px;

	border:
		1px solid
		var(--ngp2-line);

	border-radius: 9px;

	background: #fff;

	color: #697080;

	text-decoration: none;

	font-size:
		11px !important;

}


.post-type-product
.subsubsub a.current {

	background: #efefff;

	border-color: #d8d5ff;

	color:
		var(--ngp2-primary);

	font-weight: 750;

}



/* =========================================================
   BULK ACTIONS
========================================================= */

.post-type-product
.tablenav {

	height: auto;

	margin:
		10px 0;

}


.post-type-product
.bulkactions {

	display: flex;

	gap: 6px;

	align-items: center;

}


.post-type-product
.bulkactions select,

.post-type-product
.bulkactions .button {

	min-height: 38px;

	border-radius: 10px;

	border-color:
		var(--ngp2-line);

	background: #fff;

}



/* =========================================================
   TABLE
========================================================= */

.post-type-product
.wp-list-table {

	border:
		0 !important;

	background:
		transparent !important;

	box-shadow:
		none !important;

	border-collapse:
		separate !important;

	border-spacing:
		0 9px !important;

	min-width: 980px;

}



/* HEADER */

.post-type-product
.wp-list-table
thead th,

.post-type-product
.wp-list-table
thead td {

	background:
		transparent !important;

	border:
		0 !important;

	color:
		#8b92a1 !important;

	font-size:
		11px !important;

	font-weight:
		650 !important;

	padding-top:
		8px !important;

	padding-bottom:
		8px !important;

}


.post-type-product
.wp-list-table
tfoot {

	display:
		none !important;

}



/* =========================================================
   PRODUCT ROWS
========================================================= */

.post-type-product
.wp-list-table
tbody tr {

	box-shadow:
		0 4px 18px
		rgba(
			16,
			24,
			40,
			.035
		);

	transition:
		.18s ease;

}


.post-type-product
.wp-list-table
tbody tr:hover {

	transform:
		translateY(-1px);

	box-shadow:
		0 9px 28px
		rgba(
			16,
			24,
			40,
			.07
		);

}


.post-type-product
.wp-list-table
tbody td,

.post-type-product
.wp-list-table
tbody th {

	background:
		#fff !important;

	border-top:
		1px solid
		#eceef2 !important;

	border-bottom:
		1px solid
		#eceef2 !important;

	padding-top:
		14px !important;

	padding-bottom:
		14px !important;

	vertical-align:
		middle !important;

}


.post-type-product
.wp-list-table
tbody tr > *:first-child {

	border-right:
		1px solid
		#eceef2 !important;

	border-radius:
		0 14px 14px 0;

}


.post-type-product
.wp-list-table
tbody tr > *:last-child {

	border-left:
		1px solid
		#eceef2 !important;

	border-radius:
		14px 0 0 14px;

}



/* =========================================================
   IMAGE
========================================================= */

.post-type-product
.column-thumb {

	width:
		68px !important;

}


.post-type-product
.column-thumb img {

	width:
		52px !important;

	height:
		52px !important;

	object-fit: cover;

	border-radius: 11px;

	border:
		1px solid #eceef2;

	margin:
		0 !important;

}



/* =========================================================
   NAME
========================================================= */

.post-type-product
.column-name {

	min-width: 240px;

}


.post-type-product
.column-name strong a,

.post-type-product
.column-name .row-title {

	font-size:
		13px !important;

	font-weight:
		780 !important;

	line-height: 1.7;

	color:
		#252934 !important;

}


.post-type-product
.column-name strong a:hover {

	color:
		var(--ngp2-primary) !important;

}


.post-type-product
.row-actions {

	margin-top: 6px;

}


.post-type-product
.row-actions a {

	font-size: 10px;

	text-decoration: none;

}



/* =========================================================
   PRICE
========================================================= */

.post-type-product
.column-price {

	width:
		155px !important;

	min-width:
		155px !important;

	white-space:
		nowrap !important;

	font-weight: 750;

	color: #303541;

	line-height: 1.8;

}


.post-type-product
.column-price
.woocommerce-Price-amount,

.post-type-product
.column-price
.woocommerce-Price-amount bdi {

	white-space:
		nowrap !important;

}


.post-type-product
.column-price del {

	display: block;

	color: #98a0af;

	font-size: 10px;

	font-weight: 500;

	line-height: 1.2;

}


.post-type-product
.column-price ins {

	display: block;

	text-decoration: none;

	color: #20232b;

}


.post-type-product
.column-price
.woocommerce-Price-currencySymbol {

	font-size: .86em;

	margin-right: 3px;

}



/* =========================================================
   SKU
========================================================= */

.post-type-product
.column-sku {

	min-width: 110px;

	color: #72798a;

	font-size: 11px;

}



/* =========================================================
   STOCK
========================================================= */

.post-type-product
.column-is_in_stock {

	min-width: 115px;

}


.post-type-product
mark.instock,

.post-type-product
mark.outofstock,

.post-type-product
mark.onbackorder {

	display: inline-flex;

	padding:
		5px 8px;

	border-radius: 999px;

	font-size: 10px;

	font-weight: 750;

	white-space: nowrap;

}


.post-type-product
mark.instock {

	background:
		var(--ngp2-successbg) !important;

	color:
		var(--ngp2-success) !important;

}


.post-type-product
mark.outofstock {

	background:
		var(--ngp2-dangerbg) !important;

	color:
		var(--ngp2-danger) !important;

}


.post-type-product
mark.onbackorder {

	background:
		var(--ngp2-warnbg) !important;

	color:
		var(--ngp2-warn) !important;

}



/* =========================================================
   OUT OF STOCK ROW
========================================================= */

.post-type-product
.wp-list-table
tbody
tr.ngp2-oos td,

.post-type-product
.wp-list-table
tbody
tr.ngp2-oos th {

	background:
		#fffafa !important;

	border-color:
		#f6dddd !important;

}


.post-type-product
.wp-list-table
tbody
tr.ngp2-oos
> *:first-child {

	border-right:
		3px solid
		#ea5b55 !important;

}


.post-type-product
tr.ngp2-oos
.column-thumb img {

	filter:
		grayscale(.75);

	opacity:
		.68;

}


.ngp2-oos-tag {

	display: inline-flex;

	margin-right: 7px;

	padding:
		3px 6px;

	border-radius: 7px;

	background:
		var(--ngp2-dangerbg);

	color:
		var(--ngp2-danger);

	font-size: 9px;

	font-weight: 750;

	vertical-align: middle;

}



/* =========================================================
   CATEGORY
========================================================= */

.post-type-product
.column-product_cat a {

	display: inline-flex;

	margin:
		2px 1px;

	padding:
		4px 7px;

	border-radius: 7px;

	background: #f5f6f8;

	color: #616978;

	text-decoration: none;

	font-size: 10px;

}



/* =========================================================
   PAGINATION
========================================================= */

.post-type-product
.tablenav-pages .button,

.post-type-product
.tablenav-pages
.tablenav-pages-navspan,

.post-type-product
.tablenav-pages
input.current-page {

	border-radius: 8px;

}



/* =========================================================
   RESPONSIVE
========================================================= */

@media
(max-width: 1350px) {

	.ngp2-filter-grid {

		grid-template-columns:
			repeat(
				3,
				minmax(
					0,
					1fr
				)
			);

	}

}



@media
(max-width: 900px) {

	#ngp2-manager,

	.post-type-product
	#wpbody-content > .wrap {

		padding-left:
			14px !important;

		padding-right:
			14px !important;

	}


	.ngp2-filter-grid {

		grid-template-columns:
			1fr 1fr;

	}


	.ngp2-hero {

		align-items: stretch;

	}


	.ngp2-result {

		grid-template-columns:
			44px
			minmax(
				0,
				1fr
			)
			auto;

	}


	.ngp2-result-price {

		display: none;

	}

}



@media
(max-width: 600px) {

	#ngp2-manager,

	.post-type-product
	#wpbody-content > .wrap {

		padding-left:
			10px !important;

		padding-right:
			10px !important;

	}


	.ngp2-hero {

		flex-direction:
			column;

	}


	.ngp2-filter-grid {

		grid-template-columns:
			1fr;

	}


	.ngp2-filter-actions
	button {

		flex: 1;

	}

}
	
	/* =========================================================
   NOGHTE GROUP - ALIGNMENT + MOBILE PRODUCT CARDS
   Add at the END of current CSS
========================================================= */


/* =========================================================
   1. FIX DESKTOP ALIGNMENT
   سکشن‌های بالا دقیقاً هم‌تراز با محتوای پایین
========================================================= */

#ngp2-manager {
	width: 100% !important;
	max-width: none !important;

	margin:
		22px 0 18px !important;

	padding:
		0 !important;

	box-sizing:
		border-box;
}


/*
 * فقط Wrap اصلی فاصله صفحه را کنترل کند
 */
.post-type-product
#wpbody-content > .wrap {

	width:
		100% !important;

	max-width:
		1680px !important;

	box-sizing:
		border-box;

	margin:
		0 auto !important;

	padding:
		0 26px 48px !important;

}



/* =========================================================
   2. TABLE DESKTOP FIX
========================================================= */

.post-type-product
.wp-list-table {

	width:
		100% !important;

}


/*
 * جلوگیری از کش آمدن بیش از حد بعضی ستون‌ها
 */
.post-type-product
.column-name {

	width:
		auto !important;

	min-width:
		230px;

}


.post-type-product
.column-price {

	width:
		145px !important;

	min-width:
		145px !important;

}


.post-type-product
.column-is_in_stock {

	width:
		120px !important;

	min-width:
		120px !important;

}



/* =========================================================
   TABLET
========================================================= */

@media (max-width: 1100px) {

	.post-type-product
	#wpbody-content > .wrap {

		padding-left:
			18px !important;

		padding-right:
			18px !important;

	}


	#ngp2-manager {

		padding:
			0 !important;

	}


	.ngp2-filter-grid {

		grid-template-columns:
			repeat(
				2,
				minmax(
					0,
					1fr
				)
			) !important;

	}


	.ngp2-filter-actions {

		grid-column:
			1 / -1;

	}


	.ngp2-filter-actions button {

		min-width:
			140px;

	}

}



/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 782px) {


	/* ==========================================
	   PAGE SPACING
	========================================== */

	.post-type-product
	#wpbody-content > .wrap {

		max-width:
			100% !important;

		margin:
			0 !important;

		padding:
			0 12px 35px !important;

	}


	#ngp2-manager {

		width:
			100% !important;

		margin:
			12px 0 16px !important;

		padding:
			0 !important;

	}



	/* ==========================================
	   HERO
	========================================== */

	.ngp2-hero {

		padding:
			16px !important;

		border-radius:
			16px !important;

		gap:
			14px;

	}


	.ngp2-title-icon {

		width:
			42px;

		height:
			42px;

		flex:
			0 0 42px;

	}


	.ngp2-title-wrap {

		align-items:
			flex-start;

	}


	.ngp2-title-wrap h1 {

		font-size:
			17px !important;

	}


	.ngp2-title-wrap p {

		font-size:
			11px;

		line-height:
			1.8;

	}


	.ngp2-add {

		height:
			40px;

		padding:
			0 13px;

		font-size:
			11px;

	}



	/* ==========================================
	   SEARCH
	========================================== */

	.ngp2-search-wrap {

		border-radius:
			15px;

	}


	.ngp2-search {

		height:
			54px;

		padding:
			0 14px;

	}


	.ngp2-search input {

		height:
			50px !important;

		font-size:
			12px !important;

	}


	.ngp2-search-icon {

		font-size:
			20px;

		width:
			20px;

		height:
			20px;

	}



	/* ==========================================
	   AJAX RESULTS
	========================================== */

	.ngp2-results {

		border-radius:
			15px;

		max-height:
			430px;

	}


	.ngp2-result {

		grid-template-columns:
			45px
			minmax(
				0,
				1fr
			)
			auto !important;

		gap:
			10px;

		padding:
			10px;

	}


	.ngp2-result img {

		width:
			45px;

		height:
			45px;

		border-radius:
			10px;

	}


	.ngp2-result-price {

		display:
			none !important;

	}


	.ngp2-result-name {

		font-size:
			12px;

	}


	.ngp2-result-meta {

		font-size:
			9px;

	}



	/* ==========================================
	   FILTERS
	========================================== */

	.ngp2-filter-card {

		padding:
			15px;

		border-radius:
			16px;

	}


	.ngp2-filter-grid {

		grid-template-columns:
			1fr !important;

		gap:
			12px;

	}


	.ngp2-filter-grid select {

		height:
			46px !important;

		min-height:
			46px !important;

		border-radius:
			12px !important;

		font-size:
			12px !important;

	}


	.ngp2-filter-actions {

		display:
			grid;

		grid-template-columns:
			1fr auto;

		grid-column:
			auto;

		width:
			100%;

	}


	.ngp2-filter-actions button {

		width:
			100%;

		min-width:
			0;

		height:
			44px;

	}


	.ngp2-filter-actions a {

		height:
			44px;

	}



	/* ==========================================
	   STATUS FILTER CHIPS
	========================================== */

	.post-type-product
	.subsubsub {

		display:
			flex !important;

		flex-wrap:
			nowrap !important;

		gap:
			7px;

		width:
			100%;

		overflow-x:
			auto;

		overflow-y:
			hidden;

		padding:
			2px 0 8px !important;

		margin:
			8px 0 8px !important;

		-webkit-overflow-scrolling:
			touch;

		scrollbar-width:
			none;

	}


	.post-type-product
	.subsubsub::-webkit-scrollbar {

		display:
			none;

	}


	.post-type-product
	.subsubsub li {

		flex:
			0 0 auto;

	}


	.post-type-product
	.subsubsub a {

		white-space:
			nowrap;

		padding:
			7px 10px;

	}



	/* ==========================================
	   TABLENAV
	========================================== */

	.post-type-product
	.tablenav {

		display:
			flex;

		align-items:
			center;

		justify-content:
			space-between;

		flex-wrap:
			wrap;

		gap:
			10px;

		margin:
			10px 0 14px;

	}


	.post-type-product
	.tablenav .alignleft,

	.post-type-product
	.tablenav .alignright {

		float:
			none !important;

	}


	.post-type-product
	.bulkactions {

		display:
			flex;

		width:
			100%;

		gap:
			7px;

	}


	.post-type-product
	.bulkactions select {

		flex:
			1;

		max-width:
			none !important;

		min-width:
			0;

	}


	.post-type-product
	.bulkactions .button {

		flex:
			0 0 auto;

	}



	/* =====================================================
	   MOBILE PRODUCTS
	   جدول به کارت تبدیل می‌شود
	===================================================== */


	.post-type-product
	.wp-list-table {

		display:
			block !important;

		width:
			100% !important;

		min-width:
			0 !important;

		border:
			0 !important;

		border-spacing:
			0 !important;

		background:
			transparent !important;

	}


	.post-type-product
	.wp-list-table thead,

	.post-type-product
	.wp-list-table tfoot {

		display:
			none !important;

	}


	.post-type-product
	.wp-list-table tbody,

	.post-type-product
	.wp-list-table #the-list {

		display:
			block !important;

		width:
			100% !important;

	}



	/* هر محصول = Card */

	.post-type-product
	.wp-list-table
	tbody tr {

		display:
			grid !important;

		grid-template-columns:
			58px
			minmax(
				0,
				1fr
			)
			32px;

		grid-template-areas:

			"thumb name check"
			"thumb sku check"
			"price price price"
			"stock stock stock"
			"category category category";

		column-gap:
			12px;

		row-gap:
			0;

		width:
			100% !important;

		box-sizing:
			border-box;

		margin:
			0 0 12px !important;

		padding:
			14px !important;

		background:
			#fff !important;

		border:
			1px solid #e8ebf0 !important;

		border-radius:
			16px !important;

		box-shadow:
			0 5px 20px
			rgba(
				16,
				24,
				40,
				.04
			) !important;

		transform:
			none !important;

	}



	/*
	 * در موبایل ابتدا همه ستون‌ها مخفی
	 */
	.post-type-product
	.wp-list-table
	tbody tr > * {

		display:
			none !important;

		width:
			auto !important;

		min-width:
			0 !important;

		max-width:
			none !important;

		height:
			auto !important;

		box-sizing:
			border-box;

		border:
			0 !important;

		background:
			transparent !important;

		padding:
			0 !important;

	}



	/* ==========================================
	   CHECKBOX
	========================================== */

	.post-type-product
	.wp-list-table
	tbody
	.check-column {

		display:
			flex !important;

		grid-area:
			check;

		align-items:
			flex-start;

		justify-content:
			flex-end;

	}


	.post-type-product
	.wp-list-table
	tbody
	.check-column input {

		margin:
			3px 0 0 !important;

	}



	/* ==========================================
	   IMAGE
	========================================== */

	.post-type-product
	.wp-list-table
	tbody
	.column-thumb {

		display:
			block !important;

		grid-area:
			thumb;

		width:
			58px !important;

	}


	.post-type-product
	.wp-list-table
	.column-thumb img {

		display:
			block;

		width:
			58px !important;

		height:
			58px !important;

		max-width:
			58px !important;

		margin:
			0 !important;

		border-radius:
			13px;

		object-fit:
			cover;

	}



	/* ==========================================
	   PRODUCT NAME
	========================================== */

	.post-type-product
	.wp-list-table
	tbody
	.column-name {

		display:
			block !important;

		grid-area:
			name;

		align-self:
			center;

		padding:
			0 !important;

	}


	.post-type-product
	.column-name strong {

		display:
			block;

	}


	.post-type-product
	.column-name strong a,

	.post-type-product
	.column-name .row-title {

		display:
			block;

		font-size:
			13px !important;

		line-height:
			1.8;

		font-weight:
			800 !important;

		color:
			#20232c !important;

	}


	.post-type-product
	.row-actions {

		display:
			flex !important;

		flex-wrap:
			wrap;

		gap:
			5px 9px;

		margin:
			5px 0 0 !important;

		position:
			static !important;

		visibility:
			visible !important;

	}


	.post-type-product
	.row-actions span {

		display:
			inline-flex;

	}


	.post-type-product
	.row-actions a {

		font-size:
			9px !important;

	}



	/* ==========================================
	   SKU
	========================================== */

	.post-type-product
	.wp-list-table
	tbody
	.column-sku {

		display:
			flex !important;

		grid-area:
			sku;

		align-items:
			center;

		gap:
			6px;

		margin-top:
			4px;

		color:
			#858c9a;

		font-size:
			10px;

	}


	.post-type-product
	.column-sku::before {

		content:
			"SKU";

		display:
			inline-flex;

		padding:
			2px 5px;

		border-radius:
			5px;

		background:
			#f3f4f7;

		color:
			#8a91a0;

		font-size:
			8px;

		font-weight:
			700;

	}



	/* ==========================================
	   PRICE
	========================================== */

	.post-type-product
	.wp-list-table
	tbody
	.column-price {

		display:
			flex !important;

		grid-area:
			price;

		align-items:
			center;

		justify-content:
			space-between;

		width:
			100% !important;

		min-width:
			0 !important;

		margin-top:
			14px;

		padding:
			11px 12px !important;

		background:
			#f8f9fb !important;

		border-radius:
			11px !important;

		font-size:
			13px;

		font-weight:
			800;

		line-height:
			1.6;

	}


	.post-type-product
	.column-price::before {

		content:
			"قیمت";

		color:
			#7b8291;

		font-size:
			10px;

		font-weight:
			650;

	}


	.post-type-product
	.column-price del,

	.post-type-product
	.column-price ins {

		display:
			inline-block;

	}



	/* ==========================================
	   STOCK
	========================================== */

	.post-type-product
	.wp-list-table
	tbody
	.column-is_in_stock {

		display:
			flex !important;

		grid-area:
			stock;

		align-items:
			center;

		justify-content:
			space-between;

		width:
			100% !important;

		min-width:
			0 !important;

		padding:
			10px 2px 0 !important;

	}


	.post-type-product
	.column-is_in_stock::before {

		content:
			"وضعیت موجودی";

		color:
			#7c8392;

		font-size:
			10px;

		font-weight:
			650;

	}



	/* ==========================================
	   CATEGORY
	========================================== */

	.post-type-product
	.wp-list-table
	tbody
	.column-product_cat {

		display:
			flex !important;

		grid-area:
			category;

		align-items:
			flex-start;

		justify-content:
			space-between;

		gap:
			10px;

		width:
			100% !important;

		min-width:
			0 !important;

		padding:
			9px 2px 0 !important;

		margin-top:
			7px;

		border-top:
			1px solid #f0f1f4 !important;

	}


	.post-type-product
	.column-product_cat::before {

		content:
			"دسته‌بندی";

		flex:
			0 0 auto;

		color:
			#7c8392;

		font-size:
			10px;

		font-weight:
			650;

	}



	/* ==========================================
	   OUT OF STOCK MOBILE CARD
	========================================== */

	.post-type-product
	.wp-list-table
	tbody
	tr.ngp2-oos {

		background:
			#fffafa !important;

		border-color:
			#f5d9d8 !important;

		border-right:
			3px solid #e85952 !important;

	}


	.post-type-product
	tr.ngp2-oos
	.column-price {

		background:
			#fff5f5 !important;

	}



	/* ==========================================
	   PRODUCT COUNT
	========================================== */

	.post-type-product
	.displaying-num {

		font-size:
			10px;

		color:
			#7d8492;

	}



	/* ==========================================
	   PAGINATION MOBILE
	========================================== */

	.post-type-product
	.tablenav-pages {

		float:
			none !important;

		display:
			flex;

		align-items:
			center;

		justify-content:
			center;

		width:
			100%;

		margin:
			4px 0 0 !important;

	}


	.post-type-product
	.tablenav-pages
	.pagination-links {

		display:
			flex;

		align-items:
			center;

		gap:
			4px;

	}

}



/* =========================================================
   VERY SMALL MOBILES
========================================================= */

@media (max-width: 480px) {

	.post-type-product
	#wpbody-content > .wrap {

		padding-left:
			8px !important;

		padding-right:
			8px !important;

	}


	.ngp2-hero {

		flex-direction:
			column;

		align-items:
			stretch;

	}


	.ngp2-title-wrap {

		width:
			100%;

	}


	.ngp2-add {

		width:
			100%;

		box-sizing:
			border-box;

		justify-content:
			center;

	}


	.post-type-product
	.wp-list-table
	tbody tr {

		grid-template-columns:
			52px
			minmax(
				0,
				1fr
			)
			28px;

		column-gap:
			10px;

		padding:
			12px !important;

	}


	.post-type-product
	.wp-list-table
	.column-thumb,

	.post-type-product
	.wp-list-table
	.column-thumb img {

		width:
			52px !important;

		height:
			52px !important;

	}


	.post-type-product
	.column-name strong a,

	.post-type-product
	.column-name .row-title {

		font-size:
			12px !important;

	}


	.post-type-product
	.wp-list-table
	tbody
	.column-price {

		margin-top:
			12px;

		padding:
			10px !important;

		font-size:
			12px;

	}

}

</style>

		<?php

	}
);


/* =========================================================
 * JAVASCRIPT
 * ======================================================= */

add_action(
	'admin_footer',
	function () {

		if (
			! ngp2_is_products_screen()
		) {
			return;
		}


		$config = array(

			'ajaxUrl' =>
				admin_url(
					'admin-ajax.php'
				),

			'nonce' =>
				wp_create_nonce(
					'ngp2_search'
				),

			'productsUrl' =>
				admin_url(
					'edit.php?post_type=product'
				),

		);

		?>

<script>

(() => {

	'use strict';


	const cfg =
		<?php
		echo wp_json_encode(
			$config
		);
		?>;



	/* =====================================================
	   Move manager inside page wrap
	===================================================== */

	const manager =
		document.getElementById(
			'ngp2-manager'
		);


	const wrap =
		document.querySelector(
			'.post-type-product #wpbody-content > .wrap'
		);


	if (
		manager &&
		wrap &&
		manager.parentNode !== wrap
	) {

		wrap.insertBefore(
			manager,
			wrap.firstChild
		);

	}



	/* =====================================================
	   SEARCH
	===================================================== */

	const input =
		document.getElementById(
			'ngp2-search-input'
		);


	const box =
		document.getElementById(
			'ngp2-search-results'
		);


	const spinner =
		document.getElementById(
			'ngp2-search-spinner'
		);


	const clear =
		document.getElementById(
			'ngp2-search-clear'
		);


	let timer = null;

	let controller = null;



	function escapeHtml(value) {

		return String(
			value ?? ''
		).replace(

			/[&<>'"]/g,

			char => ({

				'&':
					'&',

				'<':
					'<',

				'>':
					'>',

				"'":
					''',

				'"':
					'"'

			}[char])

		);

	}



	function badgeClass(status) {

		if (
			status === 'instock'
		) {

			return 'ngp2-instock';

		}


		if (
			status === 'onbackorder'
		) {

			return 'ngp2-onbackorder';

		}


		return 'ngp2-outofstock';

	}



	function hideResults() {

		if ( ! box ) {
			return;
		}


		box.hidden = true;

		box.innerHTML = '';

	}



	function renderResults(items) {

		if ( ! box ) {
			return;
		}


		if (
			! items ||
			! items.length
		) {

			box.innerHTML =

				'<div class="ngp2-empty">' +

					'محصولی پیدا نشد.' +

				'</div>';


			box.hidden = false;

			return;

		}


		box.innerHTML =
			items.map(
				item => {


					const sku =
						item.sku

							? 'SKU: ' +
								escapeHtml(
									item.sku
								)

							: 'ID: ' +
								escapeHtml(
									item.id
								);


					const qty =

						item.stock_qty !== null &&
						item.stock_qty !== ''

							?

							'<span>' +

								'موجودی: ' +

								escapeHtml(
									item.stock_qty
								) +

							'</span>'

							:

							'';


					return (

						'<a class="ngp2-result" href="' +
						escapeHtml(
							item.edit_url
						) +
						'">' +


							'<img src="' +
							escapeHtml(
								item.image
							) +
							'" alt="">' +


							'<span>' +

								'<span class="ngp2-result-name">' +

									escapeHtml(
										item.name
									) +

								'</span>' +


								'<span class="ngp2-result-meta">' +

									'<span>' +
										sku +
									'</span>' +

									'<span>•</span>' +

									'<span>' +

										escapeHtml(
											item.type_label
										) +

									'</span>' +

									qty +

								'</span>' +

							'</span>' +


							'<span class="ngp2-result-price">' +

								(
									item.price ||
									'-'
								) +

							'</span>' +


							'<span class="ngp2-badge ' +

								badgeClass(
									item.stock_status
								) +

							'">' +

								escapeHtml(
									item.stock_label
								) +

							'</span>' +


						'</a>'

					);

				}

			).join('');


		box.hidden = false;

	}



	async function searchProducts(
		term
	) {

		if ( controller ) {

			controller.abort();

		}


		controller =
			new AbortController();


		if ( spinner ) {

			spinner.hidden =
				false;

		}


		const data =
			new FormData();


		data.append(
			'action',
			'ngp2_product_search'
		);


		data.append(
			'nonce',
			cfg.nonce
		);


		data.append(
			'q',
			term
		);


		try {


			const response =
				await fetch(

					cfg.ajaxUrl,

					{

						method:
							'POST',

						credentials:
							'same-origin',

						body:
							data,

						signal:
							controller.signal

					}

				);


			const json =
				await response.json();


			renderResults(

				json &&
				json.success

					? json.data
					: []

			);


		} catch (error) {


			if (
				error.name !==
				'AbortError'
			) {

				renderResults(
					[]
				);

			}


		} finally {


			if ( spinner ) {

				spinner.hidden =
					true;

			}

		}

	}



	if ( input ) {


		input.addEventListener(
			'input',
			() => {


				const term =
					input.value.trim();


				if ( clear ) {

					clear.hidden =
						! term;

				}


				clearTimeout(
					timer
				);


				if (
					term.length < 2
				) {

					hideResults();


					if (
						controller
					) {

						controller.abort();

					}


					return;

				}


				timer =
					setTimeout(

						() =>
							searchProducts(
								term
							),

						250

					);

			}
		);



		/*
		 * Enter => normal WC search
		 */

		input.addEventListener(
			'keydown',
			event => {


				if (
					event.key !==
						'Enter'
				) {
					return;
				}


				event.preventDefault();


				const term =
					input.value.trim();


				if ( term ) {

					window.location.href =

						cfg.productsUrl +

						'&s=' +

						encodeURIComponent(
							term
						);

				}

			}
		);



		input.addEventListener(
			'focus',
			() => {


				if (
					box &&
					box.innerHTML &&
					input.value.trim().length >= 2
				) {

					box.hidden =
						false;

				}

			}
		);

	}



	/* CLEAR */

	if ( clear ) {

		clear.addEventListener(
			'click',
			() => {


				input.value = '';

				clear.hidden = true;


				hideResults();


				input.focus();


				if (
					controller
				) {

					controller.abort();

				}

			}
		);

	}



	/* OUTSIDE CLICK */

	document.addEventListener(
		'click',
		event => {


			if (
				! event.target.closest(
					'.ngp2-search-wrap'
				)
			) {

				hideResults();

			}

		}
	);



	/* =====================================================
	   FILTERS
	===================================================== */

	const apply =
		document.getElementById(
			'ngp2-apply'
		);


	if ( apply ) {

		apply.addEventListener(
			'click',
			() => {


				const url =
					new URL(
						cfg.productsUrl,
						window.location.origin
					);


				function setParam(
					key,
					id
				) {


					const element =
						document.getElementById(
							id
						);


					if (
						element &&
						element.value
					) {

						url.searchParams.set(
							key,
							element.value
						);

					}

				}


				setParam(
					'product_cat',
					'ngp2-cat'
				);


				setParam(
					'product_type',
					'ngp2-type'
				);


				setParam(
					'stock_status',
					'ngp2-stock'
				);


				setParam(
					'post_status',
					'ngp2-status'
				);



				/* SORT */

				const sort =
					document.getElementById(
						'ngp2-sort'
					);


				if (
					sort &&
					sort.value
				) {


					const parts =
						sort.value.split(
							':'
						);


					url.searchParams.set(
						'orderby',
						parts[0]
					);


					url.searchParams.set(
						'order',
						parts[1] ||
						'DESC'
					);

				}


				window.location.href =
					url.toString();

			}
		);

	}



	/* =====================================================
	   OUT OF STOCK ROW DESIGN
	===================================================== */

	document
		.querySelectorAll(
			'#the-list tr'
		)
		.forEach(
			row => {


				if (
					! row.querySelector(
						'mark.outofstock'
					)
				) {

					return;

				}


				row.classList.add(
					'ngp2-oos'
				);


				const title =
					row.querySelector(
						'.column-name strong'
					);


				if (
					title &&
					! title.querySelector(
						'.ngp2-oos-tag'
					)
				) {

					title.insertAdjacentHTML(

						'beforeend',

						'<span class="ngp2-oos-tag">' +
							'ناموجود' +
						'</span>'

					);

				}

			}
		);


})();

</script>

		<?php

	}
);