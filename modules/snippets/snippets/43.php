<?php
// اسنیپت #43: سفارش با قابلیت انتخاب آدرس
defined( 'ABSPATH' ) || exit;

/**
 * Aromist – صفحه تسویه حساب اختصاصی (نسخه‌ی سازگار با پنل کاربری تکپینو)
 *
 * تغییرات نسبت به نسخه‌ی قبلی:
 *  ۱) دو فیلتری که آدرس ارسال را خاموش می‌کردند حذف شدند.
 *  ۲) بخش «ارسال به آدرس دیگر» و فیلدهای آدرس ارسال (آدرس ۲) بعد از اطلاعات شما رندر می‌شود.
 *  ۳) استایل فیلدهای آدرس ارسال اضافه شد.
 *  ۴) با انتخاب «آدرس ۲» کل بخش «اطلاعات شما» پنهان می‌شود و فقط اطلاعات آدرس ۲ می‌ماند.
 *  ۵) نوار پیشرفت زنده «... تومان مانده تا ارسال رایگان» (ارسال رایگان از ۵ میلیون تومان).
 *  ۶) ریسپانسیو: فیلدهای آدرس ۲ در موبایل تمام‌عرض، فاصله‌ها و جستجوی استان/شهر در موبایل.
 *  ۷) طراحی تازه‌ی روش‌های پرداخت (ردیف با رادیو، لوگو، عنوان و توضیح) و دکمه ثبت سفارش.
 *  ۸) همه‌ی درگاه‌های فعال دیده می‌شوند (کیف پول دیگر بقیه را حذف نمی‌کند).
 *  ۹) نوار ارسال رایگان با کامیون متحرک، درصد و هدیه‌ی انتهای مسیر.
 */
/**
 * Aromist – صفحه تسویه حساب اختصاصی ووکامرس
 * شورت‌کد: [aromist_checkout]
 *
 * این اسنیپت را در افزونه Code Snippets (نوع PHP، اجرا در همه جا) یا functions.php قالب اضافه کنید.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// سقف ارسال رایگان به تومان (برای ریال / هزار تومان خودکار تبدیل می‌شود)
if ( ! defined( 'AROMIST_FREE_SHIPPING_TOMAN' ) ) {
	define( 'AROMIST_FREE_SHIPPING_TOMAN', 5000000 );
}

/* -------------------------------------------------------------------------
 * ۱) صفحه‌ای که شورت‌کد دارد را «صفحه تسویه حساب» حساب کن
 *    (تا اسکریپت‌های checkout ووکامرس لود شوند)
 * ---------------------------------------------------------------------- */
function aromist_checkout_page_has_shortcode() {
	if ( ! is_singular() ) {
		return false;
	}
	$post = get_post();
	return $post instanceof WP_Post && has_shortcode( $post->post_content, 'aromist_checkout' );
}

add_filter( 'woocommerce_is_checkout', function ( $is_checkout ) {
	return $is_checkout || aromist_checkout_page_has_shortcode();
} );

/* -------------------------------------------------------------------------
 * ۲) استایل‌ها
 * ---------------------------------------------------------------------- */
function aromist_checkout_css() {
	return <<<'CSS'
.aromist-checkout{
	--ac-primary:#009879;
	--ac-gold:#D7B46A;
	--ac-danger:#FF0408;
	--ac-white:#ffffff;
	--ac-black:#221E1F;
	--ac-nature:#F8FAFC;
	--ac-nature2:#F1F5F9;
	--ac-gray:#707070;
	--ac-line:#DADADA;
	direction:rtl;
	text-align:right;
	color:var(--ac-gray);
	font-family:inherit;
	box-sizing:border-box;
}
.aromist-checkout *,.aromist-checkout *::before,.aromist-checkout *::after{box-sizing:border-box}

/* --- گرید دو ستونه (col-lg-7 / col-lg-5) --- */
.aromist-checkout .ac-row{display:grid;grid-template-columns:minmax(0,1fr);gap:0 30px;align-items:start}
@media (min-width:992px){
	.aromist-checkout .ac-row{grid-template-columns:minmax(0,7fr) minmax(0,5fr)}
}

/* --- باکس‌های اطلاعات و توضیحات --- */
.aromist-checkout form.checkout .checkout_billing_box,
.aromist-checkout form.checkout .checkout_shipping_box,
.aromist-checkout form.checkout .checkout_terms_box{
	display:flex;flex-direction:column;gap:24px clamp(12px,2vw,24px);align-items:center;
	border-radius:12px;padding:clamp(16px,2vw,24px);background:var(--ac-nature2);margin-bottom:20px;
}
.aromist-checkout wc-order-attribution-inputs{display:none}
.aromist-checkout .woocommerce-billing-fields,
.aromist-checkout .woocommerce-shipping-fields,
.aromist-checkout .woocommerce-additional-fields,
.aromist-checkout .woocommerce-account-fields{width:100%}
.aromist-checkout .woocommerce-shipping-fields:empty,
.aromist-checkout .checkout_billing_box > .woocommerce-shipping-fields:not(:has(*)){display:none}

.aromist-checkout .woocommerce-billing-fields > h3,
.aromist-checkout .woocommerce-additional-fields > h3,
.aromist-checkout .checkout_terms_box > h3,
.aromist-checkout #ship-to-different-address{
	width:100%;position:relative;font-weight:500;font-size:clamp(14px,2vw,18px);
	color:#313233;border-bottom:1px solid var(--ac-line);padding:0 0 8px;margin:0 0 20px;text-align:center;line-height:1.6;
}
.aromist-checkout #ship-to-different-address label{justify-content:center}

.aromist-checkout .woocommerce-billing-fields__field-wrapper,
.aromist-checkout .woocommerce-shipping-fields__field-wrapper,
.aromist-checkout .woocommerce-additional-fields__field-wrapper,
.aromist-checkout .woocommerce-account-fields .create-account{
	display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px 0;
}

/* --- ردیف‌های فرم ---
 * همه فیلدها تمام‌عرض و زیر هم؛
 * فقط «نام + نام خانوادگی»، «استان + شهر» و «موبایل + ایمیل» دو ستونه کنار هم
 */
.aromist-checkout form.checkout .form-row{
	width:100% !important;clear:both;position:relative;z-index:0;float:none;margin:0;padding:0;order:10;
}
.aromist-checkout form.checkout #billing_first_name_field,
.aromist-checkout form.checkout #billing_last_name_field,
.aromist-checkout form.checkout #billing_state_field,
.aromist-checkout form.checkout #billing_city_field,
.aromist-checkout form.checkout #shipping_first_name_field,
.aromist-checkout form.checkout #shipping_last_name_field,
.aromist-checkout form.checkout #shipping_state_field,
.aromist-checkout form.checkout #shipping_city_field,
.aromist-checkout form.checkout #billing_phone_field,
.aromist-checkout form.checkout #billing_email_field,
.aromist-checkout form.checkout #shipping_phone_field,
.aromist-checkout form.checkout #shipping_postcode_field{width:48% !important}
.aromist-checkout form.checkout #shipping_phone_field{order:11}
.aromist-checkout form.checkout #shipping_postcode_field{order:12}
.aromist-checkout .shipping_address{margin-top:4px}
.aromist-checkout .woocommerce-shipping-fields #ship-to-different-address{margin-top:8px}
.aromist-checkout form.checkout #billing_first_name_field,
.aromist-checkout form.checkout #shipping_first_name_field{order:1}
.aromist-checkout form.checkout #billing_last_name_field,
.aromist-checkout form.checkout #shipping_last_name_field{order:2}
.aromist-checkout form.checkout #billing_state_field,
.aromist-checkout form.checkout #shipping_state_field{order:3}
.aromist-checkout form.checkout #billing_city_field,
.aromist-checkout form.checkout #shipping_city_field{order:4}
.aromist-checkout form.checkout #billing_phone_field{order:11}
.aromist-checkout form.checkout #billing_email_field{order:12}
.aromist-checkout form.checkout #billing_country_field,
.aromist-checkout form.checkout #shipping_country_field{display:none}

.aromist-checkout form.checkout .form-row label{
	display:inline-flex !important;align-items:center;gap:2px;font-size:15px;line-height:1;
	padding:0 1rem 0 0;margin:0 0 10px;color:#666 !important;font-weight:400;
}
.aromist-checkout form.checkout .form-row label.screen-reader-text{display:none !important}
.aromist-checkout .required{margin-left:5px;text-decoration:none;color:red;border:0}
.aromist-checkout .optional{font-size:12px;color:var(--ac-gray);margin-right:4px}
.aromist-checkout .woocommerce-input-wrapper{display:block;width:100%}

/* --- اینپوت‌ها --- */
.aromist-checkout form.checkout .form-row input.input-text,
.aromist-checkout form.checkout .form-row textarea,
.aromist-checkout form.checkout .form-row select:not(.select2-hidden-accessible){
	display:block;width:100%;background:var(--ac-white);border:1px solid var(--ac-nature2);
	border-radius:16px;transition:.4s;text-align:right;font-family:inherit;font-size:16px !important;
	padding:16px 22px !important;margin:0;color:var(--ac-black);box-shadow:none;outline:0;line-height:1.5;
}
@media (min-width:992px){
	.aromist-checkout form.checkout .form-row input.input-text,
	.aromist-checkout form.checkout .form-row select:not(.select2-hidden-accessible){height:64px}
}
.aromist-checkout form.checkout .form-row textarea{min-height:191px;resize:vertical}
.aromist-checkout form.checkout .form-row input.input-text::placeholder,
.aromist-checkout form.checkout .form-row textarea::placeholder{color:var(--ac-gray);font-size:16px}
.aromist-checkout form.checkout .form-row input.input-text:focus,
.aromist-checkout form.checkout .form-row textarea:focus{border-color:var(--ac-primary)}
.aromist-checkout form.checkout .form-row.woocommerce-invalid input.input-text,
.aromist-checkout form.checkout .form-row.woocommerce-invalid .select2-selection{border-color:var(--ac-danger) !important}

/* --- Select2 (استان / شهر) --- */
.aromist-checkout .select2.select2-container{width:100% !important}
.aromist-checkout .select2-container--default .select2-selection--single{
	display:flex !important;align-items:center;background:#fff;min-height:60px;height:60px;padding:15px 9px;
	border:1px solid #e1e1e1 !important;border-radius:1rem !important;position:relative;
}
.aromist-checkout .select2-container--default .select2-selection--single .select2-selection__rendered{
	display:flex !important;align-items:center;width:100%;color:var(--ac-black);line-height:1.5;padding:0 8px 0 3rem;font-size:15px;
	overflow:hidden;white-space:nowrap;text-overflow:ellipsis;
}
.aromist-checkout .select2-container--default .select2-selection--single .select2-selection__placeholder{color:var(--ac-gray)}
.aromist-checkout .select2-container--default .select2-selection--single .select2-selection__arrow{
	position:absolute;top:50%;left:0;right:auto;width:3rem;height:3rem;transform:translateY(-50%);
	display:flex;align-items:center;justify-content:center;cursor:pointer;
}
.aromist-checkout .select2-container--default .select2-selection--single .select2-selection__arrow b{display:none}
.aromist-checkout .select2-container--default .select2-selection--single .select2-selection__arrow::before{
	content:"";width:8px;height:8px;border-left:1.5px solid var(--ac-gray);border-bottom:1.5px solid var(--ac-gray);
	transform:rotate(-45deg) translate(2px,-2px);transition:.4s;
}
.aromist-checkout .select2-container--open .select2-selection__arrow::before{transform:rotate(135deg) translate(-2px,2px)}
.select2-dropdown{border:1px solid rgba(102,102,102,.1) !important;border-radius:12px !important;overflow:hidden}
.select2-container--default .select2-results__option--highlighted[aria-selected],
.select2-container--default .select2-results__option--highlighted[data-selected]{background:#009879 !important;color:#fff !important}
.select2-search--dropdown .select2-search__field{border-radius:8px;border:1px solid #e1e1e1 !important;padding:6px 10px !important}

/* --- چک‌باکس‌ها (قوانین / ارسال به آدرس دیگر / ایجاد حساب) --- */
.aromist-checkout .woocommerce-form__label-for-checkbox{display:flex !important;align-items:center;cursor:pointer;padding:0 !important;margin:0 !important;font-size:14px !important;line-height:1.9 !important}
.aromist-checkout .woocommerce-form__label-for-checkbox input[type=checkbox]{position:absolute;opacity:0;width:1px;height:1px;margin:0;pointer-events:none}
.aromist-checkout .woocommerce-form__label-for-checkbox span{position:relative;display:flex;align-items:center;gap:10px;cursor:pointer}
.aromist-checkout .woocommerce-form__label-for-checkbox span::before{
	content:"";flex:0 0 16px;height:16px;width:16px;display:inline-block;margin:auto 0;
	border:1px solid var(--ac-primary);border-radius:0;background:#fff;
}
.aromist-checkout .woocommerce-form__label-for-checkbox span::after{
	position:absolute;border-bottom:2px solid var(--ac-primary);border-right:2px solid var(--ac-primary);content:"";
	width:6px;height:12px;right:5px;top:calc(50% - 8px);opacity:0;transform:rotate(40deg);transition:opacity .5s;
}
.aromist-checkout .woocommerce-form__label-for-checkbox input[type=checkbox]:checked + span::after{opacity:1}
.aromist-checkout .woocommerce-form__label-for-checkbox input[type=checkbox]:focus-visible + span::before{outline:2px solid var(--ac-primary);outline-offset:2px}

/* --- ستون خلاصه سفارش --- */
.aromist-checkout #order_review_heading{display:none}
.aromist-checkout #order_review{position:sticky;top:20px;z-index:1;width:auto;float:none;padding:0}
.aromist-checkout #order_review .checkout-review-order-wrap{margin-bottom:24px}
.aromist-checkout #order_review .cart_item-review{
	display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;align-items:center;
	border-radius:12px;padding:clamp(10px,2vw,16px);background:var(--ac-nature);margin-bottom:20px;
}
.aromist-checkout #order_review .cart_item-review .product-name{flex:0 0 70%;max-width:70%}
.aromist-checkout #order_review .cart_item-review .product-total{flex:0 0 20%;font-size:14px;text-align:left;margin-right:auto}
.aromist-checkout #order_review .cart_item-review .product-total .woocommerce-Price-currencySymbol{font-size:12px}
.aromist-checkout #order_review .cart_item-review_top{display:flex;align-items:center;gap:10px}
.aromist-checkout #order_review .cart_item-review-image{background:#fff;border-radius:8px;border:1px solid var(--ac-line);flex:0 0 25%;max-width:25%;line-height:0}
.aromist-checkout #order_review .cart_item-review-image img{border-radius:8px;margin:0 !important;width:100%;height:auto;display:block}
.aromist-checkout #order_review .product-meta-checkout{line-height:1.8;margin:0;font-size:clamp(14px,2vw,16px);font-weight:500;color:var(--ac-black);min-width:0}
.aromist-checkout #order_review .product-meta-checkout .product-quantity{color:var(--ac-gray);font-weight:400;font-size:14px}
.aromist-checkout #order_review .product-meta-checkout .variation{display:none}

/* کارت خلاصه پرداخت (سبز) */
.aromist-checkout #order_review .review-order-form-box{
	display:flex;flex-direction:column;gap:12px;border-radius:16px;padding:clamp(16px,2vw,24px);
	background:var(--ac-primary);color:#fff;font-size:14px;line-height:1.8;
}
.aromist-checkout #order_review .ac-totals-head{
	color:#fff;font-weight:500;font-size:17px;line-height:1.6;padding-bottom:12px;border-bottom:1px solid rgba(255,255,255,.25);
}
.aromist-checkout #order_review .cart-discount,
.aromist-checkout #order_review .fee,
.aromist-checkout #order_review .tax-rate,
.aromist-checkout #order_review .tax-total{
	display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;
	padding:12px 16px;border-radius:12px;margin:0;background:rgba(255,255,255,.12);color:#fff;font-size:14px;
}
.aromist-checkout #order_review .review-order-form-box .cart-discount *,
.aromist-checkout #order_review .review-order-form-box .fee *,
.aromist-checkout #order_review .review-order-form-box .tax-rate *,
.aromist-checkout #order_review .review-order-form-box .tax-total *{color:#fff}
.aromist-checkout #order_review .cart-discount a{color:#FFE2E2 !important;font-size:12px;margin-right:6px;text-decoration:underline}
.aromist-checkout #order_review .order-total{
	background:#fff;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;
	padding:16px 18px;border-radius:12px;margin:4px 0 0;color:var(--ac-black);
}
.aromist-checkout #order_review .order-total *{color:var(--ac-black)}
.aromist-checkout #order_review .order-total > div:first-child{font-size:15px;font-weight:500}
.aromist-checkout #order_review .order-total > div:last-child,
.aromist-checkout #order_review .order-total > div:last-child .amount{font-size:19px;font-weight:600;color:var(--ac-primary)}
.aromist-checkout #order_review .order-total strong{font-weight:inherit}
.aromist-checkout #order_review .order-total small{font-size:11px;margin-right:4px;font-weight:400}

/* نحوه ارسال (کاملاً از روش‌های ارسال فعال ووکامرس) */
.aromist-checkout #order_review .aromist-shipping-table{width:100%;border:0;border-collapse:separate;border-spacing:0;margin:0;background:none}
.aromist-checkout #order_review .aromist-shipping-table tbody{display:block}
.aromist-checkout #order_review .woocommerce-shipping-totals.shipping{display:flex;flex-direction:column;gap:10px;background:none;padding:0}
.aromist-checkout #order_review .woocommerce-shipping-totals th,
.aromist-checkout #order_review .woocommerce-shipping-totals td{display:block;width:100%;padding:0;border:0;background:none;text-align:right;color:#fff;font-weight:400}
.aromist-checkout #order_review .woocommerce-shipping-totals th{font-weight:500;font-size:15px;line-height:1.6;color:#fff;display:flex;align-items:center;gap:8px}
.aromist-checkout #order_review .woocommerce-shipping-totals th::before{content:"";width:4px;height:16px;border-radius:4px;background:var(--ac-gold);flex:0 0 4px}
.aromist-checkout #order_review #shipping_method{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px}
.aromist-checkout #order_review #shipping_method li{
	position:relative;margin:0;padding:14px 16px;display:flex;flex-wrap:wrap;align-items:center;gap:4px 0;font-size:14px;
	background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.35);border-radius:14px;transition:.3s;cursor:pointer;
}
.aromist-checkout #order_review #shipping_method li::before{display:none}
.aromist-checkout #order_review #shipping_method li:hover{background:rgba(255,255,255,.18)}
.aromist-checkout #order_review #shipping_method input.shipping_method{position:absolute;opacity:0;width:1px;height:1px;margin:0;pointer-events:none}
.aromist-checkout #order_review #shipping_method label{
	flex:1 1 100%;min-width:0;display:flex;align-items:center;gap:10px;margin:0;padding:0;cursor:pointer;color:#fff;font-size:14px;line-height:1.6;
}
.aromist-checkout #order_review #shipping_method label::before{
	content:"";flex:0 0 20px;width:20px;height:20px;border-radius:50%;border:2px solid rgba(255,255,255,.85);background:transparent;transition:.3s;
}
.aromist-checkout #order_review #shipping_method label *{color:inherit}
.aromist-checkout #order_review #shipping_method .ac-ship-name{font-weight:500;min-width:0}
.aromist-checkout #order_review #shipping_method .ac-ship-cost{font-weight:600;white-space:nowrap;margin-right:auto}
.aromist-checkout #order_review #shipping_method .ac-ship-free{
	font-size:12px;font-weight:600;padding:2px 10px;border-radius:99px;background:var(--ac-gold);color:var(--ac-black) !important;
}
.aromist-checkout #order_review #shipping_method .ac-ship-eta{
	flex:0 0 100%;font-size:12px;color:rgba(255,255,255,.85);padding-right:30px;line-height:1.7;
}
/* روش انتخاب‌شده */
.aromist-checkout #order_review #shipping_method li:has(input:checked),
.aromist-checkout #order_review #shipping_method li:has(input[type=hidden]){background:#fff;border-color:#fff;box-shadow:0 6px 18px rgba(0,0,0,.08)}
.aromist-checkout #order_review #shipping_method li:has(input:checked) label,
.aromist-checkout #order_review #shipping_method li:has(input[type=hidden]) label{color:var(--ac-black)}
.aromist-checkout #order_review #shipping_method li:has(input:checked) .ac-ship-cost,
.aromist-checkout #order_review #shipping_method li:has(input[type=hidden]) .ac-ship-cost{color:var(--ac-primary)}
.aromist-checkout #order_review #shipping_method li:has(input:checked) label::before,
.aromist-checkout #order_review #shipping_method li:has(input[type=hidden]) label::before{border-color:var(--ac-primary);background:var(--ac-primary);box-shadow:inset 0 0 0 4px #fff}
.aromist-checkout #order_review #shipping_method li:has(input:checked) .ac-ship-eta,
.aromist-checkout #order_review #shipping_method li:has(input[type=hidden]) .ac-ship-eta{color:var(--ac-gray)}
.aromist-checkout #order_review #shipping_method input.shipping_method:focus-visible + label::before{outline:2px solid #fff;outline-offset:2px}
.aromist-checkout #order_review .woocommerce-shipping-destination,
.aromist-checkout #order_review .woocommerce-shipping-calculator{font-size:12px;margin:0;color:rgba(255,255,255,.85)}
.aromist-checkout #order_review .woocommerce-shipping-totals td > p:not(.woocommerce-shipping-destination){
	font-size:13px;color:#fff;background:rgba(255,255,255,.1);border:1px dashed rgba(255,255,255,.5);border-radius:12px;padding:10px 14px;margin:0;
}

/* --- روش‌های پرداخت: همه درگاه‌های فعال ووکامرس، هر کدام یک ردیف (رادیو، لوگو، عنوان، توضیح) --- */
.aromist-checkout .screen-reader-text{position:absolute !important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.aromist-checkout #order_review #payment{
	display:flex;flex-direction:column;border-radius:16px;padding:clamp(16px,2vw,22px);
	background:#fff;border:1px solid #E7ECEF;box-shadow:0 12px 32px -20px rgba(34,30,31,.28);margin-block:24px;
}
.aromist-checkout #order_review #payment ul.payment_methods{
	margin:0;padding:0;list-style:none;border:0;display:flex;flex-direction:column;gap:10px;
}
.aromist-checkout #order_review #payment ul.payment_methods::before{
	content:"روش پرداخت";display:block;font-weight:600;font-size:15px;color:var(--ac-black);line-height:1.6;
	margin:0 0 6px;padding:0 10px 0 0;border-right:4px solid var(--ac-gold);border-radius:2px;
}
.aromist-checkout #order_review #payment ul.payment_methods::after{display:none}
.aromist-checkout #order_review #payment ul.payment_methods li{
	position:relative;margin:0;padding:0;list-style:none;user-select:none;overflow:hidden;
	background:var(--ac-nature);border:1.5px solid #E7ECEF;border-radius:14px;
	transition:border-color .25s,background .25s,box-shadow .25s;
}
.aromist-checkout #order_review #payment ul.payment_methods li::before{display:none}
.aromist-checkout #order_review #payment ul.payment_methods li:hover{border-color:#B3E0D7}
.aromist-checkout #order_review #payment ul.payment_methods li:has(input:checked),
.aromist-checkout #order_review #payment ul.payment_methods li.wc_payment_method:only-child{
	border-color:var(--ac-primary);background:#F0FAF7;box-shadow:0 10px 24px -16px rgba(0,152,121,.75);
}
.aromist-checkout #order_review #payment ul.payment_methods li input[type=radio]{position:absolute;opacity:0;width:1px;height:1px;margin:0;pointer-events:none}
.aromist-checkout #order_review #payment ul.payment_methods li label{
	cursor:pointer;display:flex !important;align-items:center;gap:12px;min-height:76px;
	margin:0 !important;padding:14px 16px !important;font-size:15px !important;line-height:1.6;
	color:var(--ac-black) !important;font-weight:500;text-align:right;
}
/* ترتیب: رادیو ← لوگو ← عنوان */
.aromist-checkout #order_review #payment ul.payment_methods li label::before{
	content:"";order:0;flex:0 0 20px;width:20px;height:20px;border-radius:50%;
	border:2px solid #C9D2D6;background:#fff;transition:.25s;
}
.aromist-checkout #order_review #payment ul.payment_methods li label > *{order:1}
.aromist-checkout #order_review #payment ul.payment_methods li label > .ac-pay-title{order:2;flex:1 1 auto;min-width:0;overflow-wrap:anywhere}
.aromist-checkout #order_review #payment ul.payment_methods li input[type=radio]:checked + label::before,
.aromist-checkout #order_review #payment ul.payment_methods li.wc_payment_method:only-child label::before{
	border-color:var(--ac-primary);background:var(--ac-primary);box-shadow:inset 0 0 0 4px #fff;
}
.aromist-checkout #order_review #payment ul.payment_methods li input[type=radio]:focus-visible + label::before{outline:2px solid var(--ac-primary);outline-offset:2px}
.aromist-checkout #order_review #payment ul.payment_methods li label img{
	flex:0 0 48px;width:48px;height:48px;max-width:48px;max-height:48px;object-fit:contain;
	padding:6px;margin:0 !important;display:block;float:none;
	background:#fff;border:1px solid #E7ECEF;border-radius:12px;
}
/* توضیح درگاه فقط زیر روش انتخاب‌شده (باز و بسته شدنش با اسکریپت خود ووکامرس) */
.aromist-checkout #order_review #payment ul.payment_methods li .payment_box{
	margin:-4px 0 0 !important;padding:0 108px 14px 16px !important;
	background:none !important;border:0;font-size:13px;line-height:1.9;color:var(--ac-gray);
}
.aromist-checkout #order_review #payment ul.payment_methods li .payment_box::before{display:none !important}
.aromist-checkout #order_review #payment ul.payment_methods li .payment_box p{margin:0}
.aromist-checkout #order_review #payment ul.payment_methods li:not(:has(input:checked)):not(:only-child) .payment_box{display:none !important}
.aromist-checkout #order_review #payment ul.payment_methods li.woocommerce-notice{padding:14px 16px;font-size:13px;line-height:1.9;color:var(--ac-black)}

.aromist-checkout #order_review #payment .place-order{padding:0;margin:0;width:100%}
.aromist-checkout #order_review #payment .place-order::after{
	content:"پرداخت امن از طریق درگاه‌های معتبر";display:block;margin-top:10px;
	text-align:center;font-size:12px;line-height:1.8;color:var(--ac-gray);
}
.aromist-checkout #order_review #payment .woocommerce-terms-and-conditions-wrapper{
	background:#fff;padding:6px 12px;border-radius:6px;color:#777;
}
.aromist-checkout #order_review #payment .woocommerce-terms-and-conditions-wrapper p{font-size:12px;line-height:26px;margin:0}
.aromist-checkout #order_review #payment .woocommerce-terms-and-conditions-wrapper .form-row{width:100%}
.aromist-checkout #order_review #payment .woocommerce-privacy-policy-text:empty{display:none}
.aromist-checkout #order_review #payment .woocommerce-privacy-policy-text p{margin-bottom:6px}
.aromist-checkout #order_review #payment a{color:var(--ac-primary)}
.aromist-checkout #order_review #payment .woocommerce-terms-and-conditions{background:var(--ac-nature);border-radius:6px;padding:8px 12px;margin-bottom:8px;font-size:12px;line-height:2}

/* --- دکمه ثبت سفارش --- */
.aromist-checkout #order_review #payment #place_order{
	height:54px;display:flex;align-items:center;justify-content:center;gap:10px;
	background:linear-gradient(135deg,#00AD8A,#00876B);color:var(--ac-white);
	border-radius:100px;width:100%;transition:.35s;border:0;font-weight:600;font-family:inherit;font-size:16px;
	margin:20px 0 0;padding:0 16px;float:none;cursor:pointer;text-shadow:none;line-height:1;
	box-shadow:0 14px 26px -16px rgba(0,152,121,.95);
}
.aromist-checkout #order_review #payment #place_order::before{
	content:"";flex:0 0 18px;width:18px;height:18px;background:currentColor;
	-webkit-mask:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='4' y='10' width='16' height='11' rx='2.5'/><path d='M8 10V7a4 4 0 0 1 8 0v3'/></svg>") center/contain no-repeat;
	mask:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='4' y='10' width='16' height='11' rx='2.5'/><path d='M8 10V7a4 4 0 0 1 8 0v3'/></svg>") center/contain no-repeat;
}
.aromist-checkout #order_review #payment #place_order:hover{background:linear-gradient(135deg,#DDBE7A,#C9A251);color:#fff;box-shadow:0 14px 26px -16px rgba(201,162,81,.95)}
.aromist-checkout #order_review #payment #place_order:active{transform:scale(.99)}

/* --- پیام خطا / اطلاع‌رسانی --- */
.aromist-checkout .woocommerce-error,
.aromist-checkout .woocommerce-NoticeGroup .woocommerce-error{
	background:var(--ac-danger);border-radius:8px;color:#fff;text-align:center;font-size:13px;display:block;
	position:relative;width:100%;margin:12px auto;z-index:1;padding:12px !important;border:0;list-style:none;
}
.aromist-checkout .woocommerce-error::before{display:none}
.aromist-checkout .woocommerce-error *{color:#fff}
.aromist-checkout .woocommerce-error li{margin:5px 0;padding:0}
.aromist-checkout .woocommerce-error li a{color:#fff;text-decoration:underline}
.aromist-checkout .woocommerce-info,
.aromist-checkout .woocommerce-message{
	background:var(--ac-nature2);border:0;border-radius:12px;color:var(--ac-black);padding:14px 18px !important;margin:0 0 16px;font-size:14px;list-style:none;
}
.aromist-checkout .woocommerce-info::before,.aromist-checkout .woocommerce-message::before{display:none}
.aromist-checkout .woocommerce-info a,.aromist-checkout .woocommerce-message a{color:var(--ac-primary)}

/* --- قوانین ارسال سفارش (کادر جدا در ستون خلاصه سفارش) --- */
.aromist-checkout form.checkout .checkout_terms_box{margin:24px 0 0;gap:0}
.aromist-checkout .checkout_terms_box .woocommerce-terms-and-conditions-wrapper{
	width:100%;background:#fff;padding:14px 16px;border-radius:12px;color:#777;
}
.aromist-checkout .checkout_terms_box .woocommerce-terms-and-conditions-wrapper p{font-size:13px;line-height:2;margin:0}
.aromist-checkout .checkout_terms_box .woocommerce-privacy-policy-text:empty{display:none}
.aromist-checkout .checkout_terms_box .woocommerce-privacy-policy-text p{margin-bottom:10px}
.aromist-checkout .checkout_terms_box .woocommerce-terms-and-conditions{background:var(--ac-nature);border-radius:8px;padding:10px 14px;margin-bottom:10px;font-size:13px;line-height:2;max-height:260px;overflow:auto}
.aromist-checkout .checkout_terms_box .form-row{margin-top:6px !important}
.aromist-checkout .checkout_terms_box a{color:var(--ac-primary)}

/* --- آدرس ۲ فعال → کل «اطلاعات شما» بسته شود --- */
.aromist-checkout form.checkout.ac-addr2-active .woocommerce-billing-fields{display:none !important}
/* با کارت‌های «آدرس ۱ / آدرس ۲» پنل کاربری، باکس اطلاعات چیز دیگری ندارد → کل باکس پنهان */
.aromist-checkout form.checkout.ac-addr2-active.ac-has-picker .checkout_billing_box,
.aromist-checkout form.checkout:has(.tpup-picker:not([hidden]) input[name="tpup_ship_to"][value="shipping"]:checked) .checkout_billing_box{display:none !important}
.aromist-checkout .ac-addr1-note{
	display:none;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;width:100%;
	margin:0;padding:10px 14px;border-radius:12px;background:#fff;border:1px dashed var(--ac-line);font-size:13px;line-height:1.8;color:var(--ac-gray);
}
.aromist-checkout form.checkout.ac-addr2-active .ac-addr1-note{display:flex}
.aromist-checkout .ac-addr1-note button{
	background:none;border:0;padding:0;margin:0;color:var(--ac-primary);font-family:inherit;font-size:13px;font-weight:500;cursor:pointer;
}

/* --- کارت‌های «آدرس ۱ / آدرس ۲» (پنل کاربری تکپینو) --- */
.aromist-checkout .tpup-picker{margin:0 0 20px}
.aromist-checkout .tpup-picker__text span{overflow-wrap:anywhere}

/* --- نوار ارسال رایگان (کامیون روی نوار تا هدیه‌ی انتها حرکت می‌کند) --- */
.aromist-checkout .ac-freeship{
	position:relative;width:100%;margin:0 0 20px;padding:16px clamp(14px,2vw,22px) 14px;border-radius:16px;overflow:hidden;
	background:linear-gradient(135deg,#EAF7F3 0%,#F8FAFC 58%,#FBF5E7 100%);border:1px solid #DDEEE8;
	transition:background .5s,border-color .5s;
}
.aromist-checkout .ac-freeship::before{
	content:"";position:absolute;top:-60px;left:-40px;width:160px;height:160px;border-radius:50%;
	background:radial-gradient(circle,rgba(215,180,106,.22),transparent 70%);pointer-events:none;
}
.aromist-checkout .ac-freeship[hidden]{display:none}
.aromist-checkout .ac-freeship__head{position:relative;display:flex;align-items:center;justify-content:space-between;gap:8px 12px;margin:0 0 16px}
.aromist-checkout .ac-freeship__text{min-width:0;font-size:15px;line-height:1.8;font-weight:500;color:var(--ac-black)}
.aromist-checkout .ac-freeship__text strong,
.aromist-checkout .ac-freeship__text strong .amount{color:var(--ac-primary);font-weight:700}
.aromist-checkout .ac-freeship__badge{
	flex:0 0 auto;display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:99px;
	font-size:12px;font-weight:700;line-height:1;background:#fff;color:var(--ac-primary);box-shadow:0 0 0 1px #D2EAE2;
}
.aromist-checkout .ac-freeship__badge svg{width:13px;height:13px}
.aromist-checkout .ac-freeship__rail{position:relative;margin:0 16px}
.aromist-checkout .ac-freeship__track{
	height:10px;border-radius:99px;background:#fff;overflow:hidden;
	box-shadow:inset 0 1px 2px rgba(34,30,31,.08),0 0 0 1px #E1EAE7;
}
.aromist-checkout .ac-freeship__fill{
	position:relative;display:block;height:100%;width:0;border-radius:inherit;overflow:hidden;
	background:linear-gradient(90deg,#4FCBAA,var(--ac-primary));transition:width 1.1s cubic-bezier(.22,1,.36,1);
}
.aromist-checkout .ac-freeship:not(.is-done) .ac-freeship__fill::after{
	content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,.55),transparent);
	transform:translateX(100%);animation:ac-freeship-shine 2.2s ease-in-out infinite;
}
@keyframes ac-freeship-shine{to{transform:translateX(-100%)}}
.aromist-checkout .ac-freeship__rider{
	position:absolute;z-index:2;top:50%;right:0;width:32px;height:32px;border-radius:50%;
	display:flex;align-items:center;justify-content:center;background:#fff;color:var(--ac-primary);
	transform:translate(50%,-50%);transition:right 1.1s cubic-bezier(.22,1,.36,1),background .4s,color .4s;
	box-shadow:0 0 0 2px var(--ac-primary),0 8px 16px -8px rgba(0,152,121,.8);
}
.aromist-checkout .ac-freeship__rider svg{width:18px;height:18px;transform:scaleX(-1)}
.aromist-checkout .ac-freeship__goal{
	position:absolute;z-index:1;top:50%;left:0;width:26px;height:26px;border-radius:50%;
	display:flex;align-items:center;justify-content:center;background:#fff;color:#B8913F;
	transform:translate(-50%,-50%);box-shadow:0 0 0 1.5px var(--ac-gold);
}
.aromist-checkout .ac-freeship__goal svg{width:14px;height:14px}
.aromist-checkout .ac-freeship__hint{position:relative;margin:14px 0 0;font-size:12px;line-height:1.8;color:var(--ac-gray)}
.aromist-checkout .ac-freeship__hint .amount{color:var(--ac-black);font-weight:600}
/* رسید به ارسال رایگان */
.aromist-checkout .ac-freeship.is-done{background:linear-gradient(135deg,#DFF4EC 0%,#F3FBF7 55%,#FBF1DA 100%);border-color:#BFE3D6}
.aromist-checkout .ac-freeship.is-done .ac-freeship__text{color:var(--ac-primary);font-weight:700}
.aromist-checkout .ac-freeship.is-done .ac-freeship__badge{background:var(--ac-primary);color:#fff;box-shadow:none}
.aromist-checkout .ac-freeship.is-done .ac-freeship__fill{background:linear-gradient(90deg,var(--ac-gold),var(--ac-primary))}
.aromist-checkout .ac-freeship.is-done .ac-freeship__rider{background:var(--ac-gold);color:#fff;box-shadow:0 0 0 2px #fff,0 8px 18px -8px rgba(184,145,63,.9)}
.aromist-checkout .ac-freeship.is-done .ac-freeship__goal{display:none}
.aromist-checkout .ac-freeship.is-celebrate .ac-freeship__rider{animation:ac-freeship-pop .8s ease 1.1s}
@keyframes ac-freeship-pop{
	0%,100%{transform:translate(50%,-50%) scale(1)}
	40%{transform:translate(50%,-50%) scale(1.3)}
	70%{transform:translate(50%,-50%) scale(.92)}
}
@media (prefers-reduced-motion:reduce){
	.aromist-checkout .ac-freeship__fill,
	.aromist-checkout .ac-freeship__rider{transition:none}
	.aromist-checkout .ac-freeship__fill::after{animation:none;display:none}
	.aromist-checkout .ac-freeship.is-celebrate .ac-freeship__rider{animation:none}
}

/* --- کد تخفیف --- */
.aromist-checkout .checkout_coupon.woocommerce-form-coupon{
	padding:1rem;border-radius:12px;background:var(--ac-nature2);margin:0 auto 1rem;border:0;
	align-items:center;flex-wrap:wrap;gap:12px;
}
.aromist-checkout .checkout_coupon.woocommerce-form-coupon:not([style*="none"]){display:flex !important}
.aromist-checkout .checkout_coupon p{margin:0 !important;float:none !important;width:auto !important;padding:0 !important}
.aromist-checkout .checkout_coupon p:first-child{width:100% !important;font-size:13px}
.aromist-checkout .checkout_coupon .form-row-first{flex:1 1 200px;min-width:0}
.aromist-checkout .checkout_coupon #coupon_code{height:46px;border-radius:10px;background:#fff;padding:8px 12px !important;border:1px solid #e1e1e1;width:100%;font-family:inherit;font-size:16px}
.aromist-checkout .checkout_coupon .button{
	min-width:125px;height:46px;font-size:14px;border-radius:12px;display:flex;align-items:center;justify-content:center;
	background:var(--ac-primary);transition:.4s;color:#fff;border:0;font-family:inherit;padding:0 16px;cursor:pointer;
}
.aromist-checkout .checkout_coupon .button:hover{background:var(--ac-gold);color:var(--ac-black)}
.aromist-checkout .checkout_coupon .clear{display:none}

/* --- موبایل و تبلت --- */
@media (max-width:991px){
	.aromist-checkout #order_review{position:static;top:auto}
	.aromist-checkout .ac-col-side{margin-top:4px}
}
@media (max-width:576px){
	.aromist-checkout form.checkout .checkout_billing_box,
	.aromist-checkout form.checkout .checkout_shipping_box,
	.aromist-checkout form.checkout .checkout_terms_box,
	.aromist-checkout .tpup-picker{padding:16px 12px;border-radius:12px}
	.aromist-checkout form.checkout #billing_first_name_field,
	.aromist-checkout form.checkout #billing_last_name_field,
	.aromist-checkout form.checkout #billing_state_field,
	.aromist-checkout form.checkout #billing_city_field,
	.aromist-checkout form.checkout #shipping_first_name_field,
	.aromist-checkout form.checkout #shipping_last_name_field,
	.aromist-checkout form.checkout #shipping_state_field,
	.aromist-checkout form.checkout #shipping_city_field,
	.aromist-checkout form.checkout #billing_phone_field,
	.aromist-checkout form.checkout #billing_email_field,
	.aromist-checkout form.checkout #shipping_phone_field,
	.aromist-checkout form.checkout #shipping_postcode_field,
	.aromist-checkout form.checkout .tpup-picker__fields #shipping_phone_field,
	.aromist-checkout form.checkout .tpup-picker__fields #shipping_postcode_field{width:100% !important}
	.aromist-checkout form.checkout .form-row label{font-size:14px;padding-right:.5rem}
	.aromist-checkout form.checkout .form-row input.input-text,
	.aromist-checkout form.checkout .form-row select:not(.select2-hidden-accessible){height:54px;padding:12px 16px !important;border-radius:12px}
	.aromist-checkout .select2-container--default .select2-selection--single{min-height:54px;height:54px;border-radius:12px !important}
	.select2-search--dropdown .select2-search__field{font-size:16px !important}
	.aromist-checkout form.checkout .form-row textarea{min-height:130px}
	.aromist-checkout .tpup-picker__option{padding:12px}
	.aromist-checkout .ac-freeship{padding:14px 12px 12px;border-radius:14px}
	.aromist-checkout .ac-freeship__head{align-items:flex-start;margin-bottom:14px}
	.aromist-checkout .ac-freeship__text{font-size:13.5px;line-height:1.9}
	.aromist-checkout .ac-freeship__badge{font-size:11px;padding:5px 9px}
	.aromist-checkout .ac-freeship__rail{margin:0 14px}
	.aromist-checkout .ac-freeship__track{height:8px}
	.aromist-checkout .ac-freeship__rider{width:28px;height:28px}
	.aromist-checkout .ac-freeship__rider svg{width:16px;height:16px}
	.aromist-checkout .ac-freeship__goal{width:22px;height:22px}
	.aromist-checkout .ac-freeship__goal svg{width:12px;height:12px}
	.aromist-checkout .ac-freeship__hint{margin-top:12px;font-size:11px}
	.aromist-checkout .checkout_coupon.woocommerce-form-coupon{padding:12px}
	.aromist-checkout .checkout_coupon .form-row-last{flex:1 1 100%}
	.aromist-checkout .checkout_coupon .button{width:100%}
	.aromist-checkout #order_review .cart_item-review{padding:10px;margin-bottom:12px}
	.aromist-checkout #order_review .cart_item-review .product-name{flex:1 1 100%;max-width:100%}
	.aromist-checkout #order_review .cart_item-review .product-total{flex:1 1 100%;text-align:left}
	.aromist-checkout #order_review .cart_item-review-image{flex:0 0 64px;max-width:64px}
	.aromist-checkout #order_review .review-order-form-box{padding:16px 12px}
	.aromist-checkout #order_review #shipping_method li{padding:12px}
	.aromist-checkout #order_review #payment ul.payment_methods li label{min-height:64px;gap:10px;padding:12px !important;font-size:14px !important}
	.aromist-checkout #order_review #payment ul.payment_methods li label img{flex-basis:40px;width:40px;height:40px;max-width:40px;max-height:40px;padding:5px;border-radius:10px}
	.aromist-checkout #order_review #payment ul.payment_methods li .payment_box{padding:0 92px 12px 12px !important;font-size:12px}
	.aromist-checkout #order_review .order-total > div:last-child,
	.aromist-checkout #order_review .order-total > div:last-child .amount{font-size:17px}
	.aromist-checkout #order_review #payment{padding:16px 12px}
	.aromist-checkout #order_review #payment #place_order{height:54px;font-size:17px}
}

/* --- حالت بارگذاری ایجکس --- */
.aromist-checkout .blockUI.blockOverlay{border-radius:12px}
CSS;
}

// چاپ CSS در هد صفحه‌ای که شورت‌کد دارد
add_action( 'wp_head', function () {
	if ( aromist_checkout_page_has_shortcode() ) {
		$GLOBALS['aromist_checkout_css_printed'] = true;
		echo '<style id="aromist-checkout-css">' . aromist_checkout_css() . '</style>';
	}
}, 99 );

/* -------------------------------------------------------------------------
 * ۳) ستون خلاصه سفارش (محصولات + جمع‌ها) — هم در بار اول، هم در آپدیت ایجکس
 * ---------------------------------------------------------------------- */
function aromist_checkout_review_html() {
	$cart = WC()->cart;
	ob_start();
	?>
	<div class="shop_table woocommerce-checkout-review-order-table">
		<?php do_action( 'woocommerce_review_order_before_cart_contents' ); ?>

		<div class="checkout-review-order-wrap">
			<?php
			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) :
				$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

				if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 || ! apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
					continue;
				}
				?>
				<div class="cart_item-review <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
					<div class="product-name">
						<div class="cart_item-review_top">
							<div class="cart_item-review-image"><?php echo apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'woocommerce_thumbnail' ), $cart_item, $cart_item_key ); // phpcs:ignore ?></div>
							<div class="product-meta-checkout">
								<?php
								echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) );
								if ( $cart_item['quantity'] > 1 ) {
									echo apply_filters( 'woocommerce_checkout_cart_item_quantity', ' <strong class="product-quantity">&times;&nbsp;' . esc_html( $cart_item['quantity'] ) . '</strong>', $cart_item, $cart_item_key ); // phpcs:ignore
								}
								echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore
								?>
							</div>
						</div>
					</div>
					<div class="product-total">
						<?php echo apply_filters( 'woocommerce_cart_item_subtotal', $cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php do_action( 'woocommerce_review_order_after_cart_contents' ); ?>

		<div>
			<div class="woocommerce-cart-form-left-box review-order-form-box">
				<div class="ac-totals-head">خلاصه پرداخت</div>

				<?php foreach ( $cart->get_coupons() as $code => $coupon ) : ?>
					<div class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
						<div><?php wc_cart_totals_coupon_label( $coupon ); ?></div>
						<div><?php wc_cart_totals_coupon_html( $coupon ); ?></div>
					</div>
				<?php endforeach; ?>

				<?php if ( $cart->needs_shipping() && $cart->show_shipping() ) : ?>
					<?php do_action( 'woocommerce_review_order_before_shipping' ); ?>
					<table class="aromist-shipping-table"><tbody><?php wc_cart_totals_shipping_html(); ?></tbody></table>
					<?php do_action( 'woocommerce_review_order_after_shipping' ); ?>
				<?php endif; ?>

				<?php foreach ( $cart->get_fees() as $fee ) : ?>
					<div class="fee">
						<div><?php echo esc_html( $fee->name ); ?></div>
						<div><?php wc_cart_totals_fee_html( $fee ); ?></div>
					</div>
				<?php endforeach; ?>

				<?php if ( wc_tax_enabled() && ! $cart->display_prices_including_tax() ) : ?>
					<?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
						<?php foreach ( $cart->get_tax_totals() as $code => $tax ) : ?>
							<div class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
								<div><?php echo esc_html( $tax->label ); ?></div>
								<div><?php echo wp_kses_post( $tax->formatted_amount ); ?></div>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<div class="tax-total">
							<div><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></div>
							<div><?php wc_cart_totals_taxes_total_html(); ?></div>
						</div>
					<?php endif; ?>
				<?php endif; ?>

				<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

				<div class="order-total">
					<div>جمع مبلغ پرداختی</div>
					<div><?php wc_cart_totals_order_total_html(); ?></div>
				</div>

				<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

function aromist_checkout_echo_review() {
	echo aromist_checkout_review_html(); // phpcs:ignore
}

// وقتی ووکامرس با ایجکس جمع‌ها را آپدیت می‌کند، همین طراحی را برگردان
add_filter( 'woocommerce_update_order_review_fragments', function ( $fragments ) {
	if ( empty( $_POST['post_data'] ) ) { // phpcs:ignore
		return $fragments;
	}
	parse_str( wp_unslash( $_POST['post_data'] ), $posted ); // phpcs:ignore
	if ( empty( $posted['aromist_checkout'] ) ) {
		return $fragments;
	}
	$fragments['.woocommerce-checkout-review-order-table'] = aromist_checkout_review_html();
	$fragments['.ac-freeship']                             = aromist_free_shipping_bar_html();
	return $fragments;
}, 99 );

/* -------------------------------------------------------------------------
 * ۳-۱) ارسال، قوانین و آدرس
 * ---------------------------------------------------------------------- */

// آیا درخواست فعلی مربوط به همین صفحه تسویه حساب اختصاصی است؟ (رندر صفحه یا آپدیت ایجکس)
function aromist_checkout_is_ours() {
	if ( defined( 'WC_DOING_AJAX' ) || wp_doing_ajax() ) {
		if ( ! empty( $_POST['aromist_checkout'] ) ) { // phpcs:ignore
			return true;
		}
		return ! empty( $_POST['post_data'] ) && false !== strpos( wp_unslash( $_POST['post_data'] ), 'aromist_checkout=1' ); // phpcs:ignore
	}
	return ! empty( $GLOBALS['aromist_checkout_rendering'] ) || aromist_checkout_page_has_shortcode();
}

// فیلد «توضیح / زمان تحویل» در تنظیمات هر روش ارسال ووکامرس (حمل و نقل ← منطقه ← ویرایش روش)
add_action( 'woocommerce_init', function () {
	if ( ! function_exists( 'WC' ) || ! WC()->shipping() ) {
		return;
	}
	foreach ( array_keys( WC()->shipping()->get_shipping_method_class_names() ) as $method_id ) {
		add_filter( 'woocommerce_shipping_instance_form_fields_' . $method_id, function ( $fields ) {
			$fields['aromist_eta'] = array(
				'title'       => 'توضیح / زمان تحویل',
				'type'        => 'text',
				'description' => 'زیر این روش ارسال در صفحه تسویه حساب نمایش داده می‌شود. مثال: تحویل ۳ تا ۵ روز کاری',
				'default'     => '',
				'desc_tip'    => true,
			);
			return $fields;
		} );
	}
} );

function aromist_checkout_shipping_eta( $method ) {
	$eta = '';
	if ( is_callable( array( $method, 'get_instance_id' ) ) && $method->get_instance_id() ) {
		$settings = get_option( 'woocommerce_' . $method->get_method_id() . '_' . $method->get_instance_id() . '_settings', array() );
		$eta      = isset( $settings['aromist_eta'] ) ? trim( (string) $settings['aromist_eta'] ) : '';
	}
	return apply_filters( 'aromist_shipping_eta', $eta, $method );
}

add_action( 'woocommerce_after_shipping_rate', function ( $method ) {
	if ( ! aromist_checkout_is_ours() ) {
		return;
	}
	$eta = aromist_checkout_shipping_eta( $method );
	if ( $eta ) {
		echo '<span class="ac-ship-eta">' . esc_html( $eta ) . '</span>';
	}
} );

// «نام: هزینه» → نام و هزینه جدا برای چیدمان دو طرفه
add_filter( 'woocommerce_cart_shipping_method_full_label', function ( $label, $method ) {
	if ( ! aromist_checkout_is_ours() ) {
		return $label;
	}
	$name   = $method->get_label();
	$prefix = $name . ': ';
	if ( 0 === strpos( $label, $prefix ) ) {
		return '<span class="ac-ship-name">' . esc_html( $name ) . '</span><span class="ac-ship-cost">' . substr( $label, strlen( $prefix ) ) . '</span>';
	}
	if ( $label === $name ) {
		$meta = $method->get_meta_data();
		$free = ! empty( $meta['_aromist_free_shipping'] ) ? '<span class="ac-ship-cost ac-ship-free">رایگان</span>' : '';
		return '<span class="ac-ship-name">' . esc_html( $name ) . '</span>' . $free;
	}
	return $label;
}, 20, 2 );

// عنوان بخش حمل و نقل
add_filter( 'woocommerce_shipping_package_name', function ( $name ) {
	return aromist_checkout_is_ours() ? 'نحوه ارسال' : $name;
}, 99 );

// روش پرداخت: عنوان داخل span تا ردیف «رادیو، لوگو، عنوان» چیده شود (فقط هنگام نمایش فرم، نه در ثبت سفارش)
add_filter( 'woocommerce_gateway_title', function ( $title ) {
	if ( ! aromist_checkout_is_ours() || ( isset( $_GET['wc-ajax'] ) && 'checkout' === $_GET['wc-ajax'] ) || did_action( 'woocommerce_checkout_order_processed' ) ) { // phpcs:ignore
		return $title;
	}
	if ( ! doing_action( 'woocommerce_checkout_order_review' ) && ! doing_filter( 'woocommerce_update_order_review_fragments' ) && ! ( defined( 'WC_DOING_AJAX' ) && isset( $_GET['wc-ajax'] ) && 'update_order_review' === $_GET['wc-ajax'] ) ) { // phpcs:ignore
		return $title;
	}
	if ( '' === trim( (string) $title ) || false !== strpos( (string) $title, 'ac-pay-title' ) ) {
		return $title;
	}
	return '<span class="ac-pay-title">' . $title . '</span>';
}, 99 );

// همه درگاه‌های فعال ووکامرس دیده شوند: گزینه‌ی «غیرفعال کردن سایر روش‌های پرداخت» افزونه کیف پول
// (FS WooCommerce Wallet) وقتی موجودی کیف پول کافی باشد، بقیه‌ی درگاه‌ها (زیبال، اسنپ‌پی و ...) را حذف می‌کند.
add_action( 'wp_loaded', function () {
	global $wp_filter;
	$hook = 'woocommerce_available_payment_gateways';
	if ( ! apply_filters( 'aromist_checkout_show_all_gateways', true ) || empty( $wp_filter[ $hook ] ) ) {
		return;
	}
	foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$fn = $callback['function'];
			if ( is_array( $fn ) && is_object( $fn[0] ) && $fn[0] instanceof FS_WC_Wallet && 'disable_payment_methods' === $fn[1] ) {
				remove_filter( $hook, $fn, $priority );
			}
		}
	}
}, 99 );

// تخفیف روی هزینه ارسال اعمال نشود: کوپن‌ها هرگز ارسال رایگان نمی‌دهند
// (تخفیف‌های درصدی/مبلغی ووکامرس به‌طور پیش‌فرض فقط روی محصولات اعمال می‌شوند)
add_filter( 'woocommerce_coupon_get_free_shipping', function ( $free ) {
	return ( is_admin() && ! wp_doing_ajax() ) ? $free : false;
} );

// قوانین از داخل باکس پرداخت حذف و در کادر جدا در ستون اصلی نمایش داده می‌شود
add_filter( 'woocommerce_checkout_show_terms', function ( $show ) {
	if ( ! empty( $GLOBALS['aromist_terms_box'] ) ) {
		return $show;
	}
	return aromist_checkout_is_ours() ? false : $show;
}, 99 );

function aromist_checkout_terms_box_html() {
	$GLOBALS['aromist_terms_box'] = true;
	ob_start();
	wc_get_template( 'checkout/terms.php' );
	$html = trim( ob_get_clean() );
	unset( $GLOBALS['aromist_terms_box'] );
	if ( '' === $html ) {
		return '';
	}
	return '<div class="checkout_terms_box"><h3>قوانین ارسال سفارش</h3>' . $html . '</div>';
}

function aromist_checkout_echo_terms() {
	echo aromist_checkout_terms_box_html(); // phpcs:ignore
}

/* -------------------------------------------------------------------------
 * ۳-۲) آدرس ۲: کل «اطلاعات شما» پنهان و غیر اجباری
 * ---------------------------------------------------------------------- */

// سفارش از همین فرم و به «آدرس ۲» ثبت می‌شود؟ (کارت پنل کاربری اولویت دارد، بعد چک‌باکس «ارسال به آدرس دیگر»)
function aromist_checkout_addr2_posted() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	if ( empty( $_POST['aromist_checkout'] ) ) {
		return false;
	}
	if ( isset( $_POST['tpup_ship_to'] ) ) {
		return 'shipping' === sanitize_key( wp_unslash( $_POST['tpup_ship_to'] ) );
	}
	return ! empty( $_POST['ship_to_different_address'] );
	// phpcs:enable
}

// فیلدهای «اطلاعات شما» پنهان‌اند، پس هیچ‌کدام نباید جلوی ثبت سفارش را بگیرد
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
	if ( ! aromist_checkout_addr2_posted() || empty( $fields['billing'] ) ) {
		return $fields;
	}
	foreach ( $fields['billing'] as $key => $field ) {
		$fields['billing'][ $key ]['required'] = false;
	}
	return $fields;
}, 10000 );

// فیلدهای خالیِ «اطلاعات شما» از آدرس ۲ پر می‌شوند تا سفارش نام، آدرس و تلفن داشته باشد
add_filter( 'woocommerce_checkout_posted_data', function ( $data ) {
	if ( ! aromist_checkout_addr2_posted() || empty( $data['ship_to_different_address'] ) ) {
		return $data;
	}
	foreach ( array( 'first_name', 'last_name', 'company', 'country', 'state', 'city', 'address_1', 'address_2', 'postcode', 'phone' ) as $key ) {
		if ( empty( $data[ 'billing_' . $key ] ) && ! empty( $data[ 'shipping_' . $key ] ) ) {
			$data[ 'billing_' . $key ] = $data[ 'shipping_' . $key ];
		}
	}
	if ( empty( $data['billing_email'] ) && is_user_logged_in() ) {
		$data['billing_email'] = wp_get_current_user()->user_email;
	}
	return $data;
}, 20 );

add_action( 'wp_footer', function () {
	if ( empty( $GLOBALS['aromist_checkout_rendered'] ) ) {
		return;
	}
	?>
	<script id="aromist-checkout-js">
	(function ($) {
		if (!$) { return; }
		var sel = 'input[name="ship_to_different_address"]';
		var lastPct = 0;

		function hasPicker() {
			var $picker = $('.aromist-checkout [data-tpup-picker]');
			return $picker.length > 0 && !$picker.prop('hidden');
		}
		function addr2On() {
			var $card = $('.aromist-checkout input[name="tpup_ship_to"]:checked');
			if (hasPicker() && $card.length) {
				return $card.val() === 'shipping';
			}
			var $cb = $('.aromist-checkout ' + sel);
			if ($cb.filter(':checkbox, :radio').length) {
				return $cb.filter(':checked').length > 0;
			}
			return $('.aromist-checkout .shipping_address').is(':visible');
		}
		function syncAddress() {
			$('.aromist-checkout form.checkout')
				.toggleClass('ac-has-picker', hasPicker())
				.toggleClass('ac-addr2-active', addr2On());
		}

		// نوار ارسال رایگان: نوار و کامیون بعد از هر آپدیت سفارش از مقدار قبلی تا مقدار جدید حرکت می‌کنند
		function animateBar() {
			$('.aromist-checkout .ac-freeship').each(function () {
				var bar = this;
				var fill = bar.querySelector('.ac-freeship__fill');
				var rider = bar.querySelector('.ac-freeship__rider');
				if (!fill) { return; }
				var pct = parseFloat(bar.getAttribute('data-pct')) || 0;
				var els = rider ? [fill, rider] : [fill];
				els.forEach(function (el) { el.style.transition = 'none'; });
				fill.style.width = lastPct + '%';
				if (rider) { rider.style.right = lastPct + '%'; }
				void fill.offsetWidth;
				els.forEach(function (el) { el.style.transition = ''; });
				window.requestAnimationFrame(function () {
					fill.style.width = pct + '%';
					if (rider) { rider.style.right = pct + '%'; }
				});
				if (pct >= 100 && lastPct < 100) {
					bar.classList.add('is-celebrate');
				}
				lastPct = pct;
			});
		}

		$(document.body).on('change', sel + ', input[name="tpup_ship_to"]', function () {
			setTimeout(syncAddress, 0);
		});
		// کارت‌های آدرس قالب/پنل کاربری: بعد از هر کلیک وضعیت را دوباره بررسی کن
		$(document.body).on('click', '.aromist-checkout form.checkout', function () {
			setTimeout(syncAddress, 50);
			setTimeout(syncAddress, 450);
		});
		$(document.body).on('click', '.aromist-checkout .ac-addr1-open', function (e) {
			e.preventDefault();
			$('.aromist-checkout ' + sel).prop('checked', false).trigger('change');
		});
		$(function () { syncAddress(); animateBar(); });
		$(window).on('load', syncAddress);
		$(document.body).on('init_checkout', syncAddress);
		$(document.body).on('updated_checkout', function () { syncAddress(); animateBar(); });
	})(window.jQuery);
	</script>
	<?php
}, 99 );

/* -------------------------------------------------------------------------
 * ۳-۳) ارسال رایگان از ۵ میلیون تومان + نوار پیشرفت
 * ---------------------------------------------------------------------- */

function aromist_free_shipping_threshold() {
	$factors  = array(
		'IRT'  => 1,
		'IRR'  => 10,
		'IRHT' => 0.001,
		'IRHR' => 0.01,
	);
	$currency = get_woocommerce_currency();
	$factor   = isset( $factors[ $currency ] ) ? $factors[ $currency ] : 1;
	return (float) apply_filters( 'aromist_free_shipping_threshold', AROMIST_FREE_SHIPPING_TOMAN * $factor );
}

// مبلغ محصولات بعد از کد تخفیف (بدون هزینه ارسال)
function aromist_free_shipping_amount( $items ) {
	$total = 0;
	foreach ( (array) $items as $item ) {
		$total += (float) ( isset( $item['line_total'] ) ? $item['line_total'] : 0 ) + (float) ( isset( $item['line_tax'] ) ? $item['line_tax'] : 0 );
	}
	return $total;
}

// به ۵ میلیون رسید → همه روش‌های ارسال رایگان (در کل سایت، هم سبد خرید و هم تسویه حساب)
add_filter( 'woocommerce_package_rates', function ( $rates, $package ) {
	$threshold = aromist_free_shipping_threshold();
	if ( $threshold <= 0 || empty( $package['contents'] ) || aromist_free_shipping_amount( $package['contents'] ) < $threshold ) {
		return $rates;
	}
	foreach ( $rates as $rate ) {
		if ( (float) $rate->get_cost() > 0 ) {
			$rate->add_meta_data( '_aromist_free_shipping', 1 );
		}
		$rate->set_cost( 0 );
		$rate->set_taxes( array_fill_keys( array_keys( (array) $rate->get_taxes() ), 0 ) );
	}
	return $rates;
}, 100, 2 );

function aromist_free_shipping_bar_html() {
	$threshold = aromist_free_shipping_threshold();
	if ( $threshold <= 0 || ! WC()->cart || ! WC()->cart->needs_shipping() ) {
		return '<div class="ac-freeship" hidden></div>';
	}

	$amount    = aromist_free_shipping_amount( WC()->cart->get_cart() );
	$remaining = max( 0, $threshold - $amount );
	$pct       = round( min( 100, $amount / $threshold * 100 ), 2 );
	$done      = $remaining <= 0;
	$fa_pct    = strtr( (string) floor( $pct ), array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );

	$truck = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 6.5h11v9.5h-11z"/><path d="M13.5 9.5h4l3 3.2v3.3h-7"/><circle cx="6.5" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/></svg>';
	$gift  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 11h16v9H4z"/><path d="M3 7h18v4H3z"/><path d="M12 7v13"/><path d="M12 7c-1.5-3-5-3-5-.5S12 7 12 7zm0 0c1.5-3 5-3 5-.5S12 7 12 7z"/></svg>';
	$check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';

	if ( $done ) {
		$text  = 'تبریک! ارسال سفارش شما رایگان شد';
		$badge = $check . 'ارسال رایگان';
		$hint  = 'هزینه‌ی ارسال از جمع سفارش حذف شد.';
	} else {
		$text  = 'فقط <strong>' . wc_price( $remaining ) . '</strong> تا ارسال رایگان';
		$badge = $fa_pct . '٪';
		$hint  = 'ارسال رایگان برای خریدهای بالای ' . wc_price( $threshold );
	}

	ob_start();
	?>
	<div class="ac-freeship<?php echo $done ? ' is-done' : ''; ?>" data-pct="<?php echo esc_attr( $pct ); ?>" aria-live="polite">
		<div class="ac-freeship__head">
			<div class="ac-freeship__text"><?php echo wp_kses_post( $text ); ?></div>
			<span class="ac-freeship__badge"><?php echo $badge; // phpcs:ignore ?></span>
		</div>
		<div class="ac-freeship__rail">
			<div class="ac-freeship__track" role="progressbar" aria-label="پیشرفت تا ارسال رایگان" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( floor( $pct ) ); ?>">
				<span class="ac-freeship__fill" style="width:<?php echo esc_attr( $pct ); ?>%"></span>
			</div>
			<span class="ac-freeship__rider" style="right:<?php echo esc_attr( $pct ); ?>%"><?php echo $truck; // phpcs:ignore ?></span>
			<span class="ac-freeship__goal"><?php echo $gift; // phpcs:ignore ?></span>
		</div>
		<div class="ac-freeship__hint"><?php echo wp_kses_post( $hint ); ?></div>
	</div>
	<?php
	return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * ۴) کمکی: رندر فیلدهای یک گروه
 * ---------------------------------------------------------------------- */
function aromist_checkout_render_fields( $checkout, $group ) {
	foreach ( $checkout->get_checkout_fields( $group ) as $key => $field ) {
		woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
	}
}

// ترتیب فیلدها: نام، نام خانوادگی، استان، شهر، سپس بقیه (برای ترتیب درست کلید Tab)
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
	$order = array( 'first_name' => 1, 'last_name' => 2, 'state' => 3, 'city' => 4 );
	foreach ( array( 'billing', 'shipping' ) as $group ) {
		foreach ( $order as $key => $priority ) {
			if ( isset( $fields[ $group ][ $group . '_' . $key ] ) ) {
				$fields[ $group ][ $group . '_' . $key ]['priority'] = $priority;
			}
		}
	}
	return $fields;
}, 9999 );

// «ارسال به آدرس دیگر» فعال است: پنل کاربری تکپینو بالای فرم دو کارت «آدرس ۱ / آدرس ۲» نشان می‌دهد
// و آدرس ۲ (آدرس ارسال ووکامرس) با همین فیلدهای ارسال ثبت می‌شود.

/* -------------------------------------------------------------------------
 * ۵) شورت‌کد [aromist_checkout]
 * ---------------------------------------------------------------------- */
add_shortcode( 'aromist_checkout', 'aromist_checkout_shortcode' );

function aromist_checkout_shortcode() {
	if ( ! function_exists( 'WC' ) || is_admin() || ! WC()->cart ) {
		return '';
	}

	// صفحات پرداخت سفارش / تشکر از خرید → همان خروجی استاندارد ووکامرس
	if ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) {
		return do_shortcode( '[woocommerce_checkout]' );
	}

	wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
	wp_enqueue_script( 'wc-checkout' );
	$GLOBALS['aromist_checkout_rendered']  = true;
	$GLOBALS['aromist_checkout_rendering'] = true;

	ob_start();

	if ( empty( $GLOBALS['aromist_checkout_css_printed'] ) ) {
		$GLOBALS['aromist_checkout_css_printed'] = true;
		echo '<style id="aromist-checkout-css">' . aromist_checkout_css() . '</style>'; // phpcs:ignore
	}

	echo '<div class="aromist-checkout woocommerce">';

	WC()->cart->calculate_totals();

	if ( WC()->cart->is_empty() ) {
		wc_print_notices();
		echo '<p class="woocommerce-info">سبد خرید شما در حال حاضر خالی است.</p>';
		echo '<p><a class="button" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">بازگشت به فروشگاه</a></p>';
		echo '</div>';
		return ob_get_clean();
	}

	WC()->cart->check_cart_items();
	wc_print_notices();

	$checkout = WC()->checkout();

	do_action( 'woocommerce_before_checkout_form', $checkout );

	if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
		echo '<p class="woocommerce-info">' . esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', 'برای ثبت سفارش باید وارد حساب کاربری خود شوید.' ) ) . '</p>';
		echo '</div>';
		return ob_get_clean();
	}
	?>
	<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" novalidate="novalidate">
		<input type="hidden" name="aromist_checkout" value="1">

		<?php echo aromist_free_shipping_bar_html(); // phpcs:ignore ?>

		<div class="ac-row">
			<div class="ac-col-main">
				<?php if ( $checkout->get_checkout_fields() ) : ?>

					<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

					<div id="customer_details">
						<div class="checkout_billing_box">
							<div class="woocommerce-billing-fields">
								<h3>اطلاعات شما</h3>

								<?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>

								<div class="woocommerce-billing-fields__field-wrapper">
									<?php aromist_checkout_render_fields( $checkout, 'billing' ); ?>
								</div>

								<?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>
							</div>

							<?php if ( true === WC()->cart->needs_shipping_address() ) : ?>
								<div class="ac-addr1-note">
									<span>«اطلاعات شما» بسته شد؛ سفارش به «آدرس ۲» ارسال می‌شود.</span>
									<button type="button" class="ac-addr1-open">ارسال به آدرس ۱</button>
								</div>
							<?php endif; ?>

							<div class="woocommerce-shipping-fields"><?php if ( true === WC()->cart->needs_shipping_address() ) : ?>
									<h3 id="ship-to-different-address">
										<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
											<input id="ship-to-different-address-checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" <?php checked( apply_filters( 'woocommerce_ship_to_different_address_checked', 'shipping' === get_option( 'woocommerce_ship_to_destination' ) ? 1 : 0 ), 1 ); ?> type="checkbox" name="ship_to_different_address" value="1" />
											<span>ارسال به آدرس دیگر</span>
										</label>
									</h3>

									<div class="shipping_address">
										<?php do_action( 'woocommerce_before_checkout_shipping_form', $checkout ); ?>

										<div class="woocommerce-shipping-fields__field-wrapper">
											<?php aromist_checkout_render_fields( $checkout, 'shipping' ); ?>
										</div>

										<?php do_action( 'woocommerce_after_checkout_shipping_form', $checkout ); ?>
									</div>
								<?php endif; ?></div>

							<?php if ( ! is_user_logged_in() && $checkout->is_registration_enabled() ) : ?>
								<div class="woocommerce-account-fields">
									<?php if ( ! $checkout->is_registration_required() ) : ?>
										<p class="form-row form-row-wide create-account">
											<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
												<input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" id="createaccount" <?php checked( ( true === $checkout->get_value( 'createaccount' ) || ( true === apply_filters( 'woocommerce_create_account_default_checked', false ) ) ), true ); ?> type="checkbox" name="createaccount" value="1" />
												<span>ایجاد حساب کاربری؟</span>
											</label>
										</p>
									<?php endif; ?>

									<?php do_action( 'woocommerce_before_checkout_registration_form', $checkout ); ?>

									<?php if ( $checkout->get_checkout_fields( 'account' ) ) : ?>
										<div class="create-account">
											<?php aromist_checkout_render_fields( $checkout, 'account' ); ?>
										</div>
									<?php endif; ?>

									<?php do_action( 'woocommerce_after_checkout_registration_form', $checkout ); ?>
								</div>
							<?php endif; ?>
						</div>

						<div class="checkout_shipping_box">

							<div class="woocommerce-additional-fields">
								<?php do_action( 'woocommerce_before_order_notes', $checkout ); ?>

								<?php if ( apply_filters( 'woocommerce_enable_order_notes_field', 'yes' === get_option( 'woocommerce_enable_order_comments', 'yes' ) ) ) : ?>

									<h3>توضیحات تکمیلی</h3>

									<div class="woocommerce-additional-fields__field-wrapper">
										<?php aromist_checkout_render_fields( $checkout, 'order' ); ?>
									</div>

								<?php endif; ?>

								<?php do_action( 'woocommerce_after_order_notes', $checkout ); ?>
							</div>
						</div>
					</div>

					<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

				<?php endif; ?>
			</div>

			<div class="ac-col-side">
				<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
				<h3 id="order_review_heading">سفارش شما</h3>
				<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

				<div id="order_review" class="woocommerce-checkout-review-order">
					<?php
					// جدول پیش‌فرض ووکامرس را با طراحی اختصاصی جایگزین کن (فقط برای همین رندر)
					$had_default = has_action( 'woocommerce_checkout_order_review', 'woocommerce_order_review' );
					if ( false !== $had_default ) {
						remove_action( 'woocommerce_checkout_order_review', 'woocommerce_order_review', $had_default );
					}
					add_action( 'woocommerce_checkout_order_review', 'aromist_checkout_echo_review', 10 );
					add_action( 'woocommerce_checkout_order_review', 'aromist_checkout_echo_terms', 15 );

					do_action( 'woocommerce_checkout_order_review' );

					// روش‌های پرداخت به‌صورت داینامیک از درگاه‌های فعال ووکامرس خوانده می‌شوند.
					// اگر قالب/افزونه‌ای آن را از این هوک برداشته باشد، اینجا مستقیم نمایش بده.
					if ( false === has_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment' ) ) {
						woocommerce_checkout_payment();
					}

					remove_action( 'woocommerce_checkout_order_review', 'aromist_checkout_echo_review', 10 );
					remove_action( 'woocommerce_checkout_order_review', 'aromist_checkout_echo_terms', 15 );
					if ( false !== $had_default ) {
						add_action( 'woocommerce_checkout_order_review', 'woocommerce_order_review', $had_default );
					}
					?>
				</div>

				<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
			</div>
		</div>
	</form>
	<?php
	do_action( 'woocommerce_after_checkout_form', $checkout );

	echo '</div>';

	unset( $GLOBALS['aromist_checkout_rendering'] );

	return ob_get_clean();
}
