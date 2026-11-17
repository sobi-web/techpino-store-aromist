<?php
// اسنیپت #42: صفحه سبد خرید مشابه یانی
defined( 'ABSPATH' ) || exit;


/**
 * Aromist – سبد خرید اختصاصی ووکامرس
 * شورت‌کد: [aromist_cart]
 *
 * این اسنیپت را در افزونه Code Snippets (نوع PHP، اجرا در همه جا) اضافه کنید
 * و محتوای برگه «سبد خرید» را با [aromist_cart] جایگزین کنید.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * ۱) صفحه‌ای که شورت‌کد دارد را «صفحه سبد خرید» حساب کن
 *    (تا اسکریپت cart ووکامرس برای آپدیت ایجکس لود شود)
 * ---------------------------------------------------------------------- */
function aromist_cart_page_has_shortcode() {
	if ( ! is_singular() ) {
		return false;
	}
	$post = get_post();
	return $post instanceof WP_Post && has_shortcode( $post->post_content, 'aromist_cart' );
}

add_filter( 'woocommerce_is_cart', function ( $is_cart ) {
	return $is_cart || aromist_cart_page_has_shortcode();
} );

// هزینه ارسال در سبد خرید محاسبه نشود (به صفحه تسویه حساب منتقل شده است)
add_filter( 'woocommerce_cart_ready_to_calc_shipping', function ( $show ) {
	if ( defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
		return $show;
	}
	return defined( 'WOOCOMMERCE_CART' ) ? false : $show;
}, 99 );

// کد تخفیف فقط در سبد خرید؛ فرم کد تخفیف از صفحه تسویه حساب حذف شود
add_action( 'wp', function () {
	remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
} );

/* -------------------------------------------------------------------------
 * ۲) استایل‌ها
 * ---------------------------------------------------------------------- */
function aromist_cart_css() {
	return <<<'CSS'
.aromist-cart{
	--ac-primary:#009879;
	--ac-primary-light:#B3E0D7;
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
.aromist-cart *,.aromist-cart *::before,.aromist-cart *::after{box-sizing:border-box}
.aromist-cart .screen-reader-text{
	position:absolute !important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;
}

/* --- گرید دو ستونه (col-lg-8 / col-lg-4) --- */
.aromist-cart .ac-cart-row{display:grid;grid-template-columns:minmax(0,1fr);gap:0 30px;align-items:start}
@media (min-width:992px){
	.aromist-cart .ac-cart-row{grid-template-columns:minmax(0,8fr) minmax(0,4fr)}
}
@media (max-width:992px){
	.aromist-cart form.woocommerce-cart-form{margin-bottom:28px}
}

/* --- کارت هر محصول --- */
.aromist-cart .woocommerce-cart-form__cart-item{
	display:flex;flex-wrap:wrap;gap:24px clamp(12px,2vw,24px);align-items:center;
	border-radius:12px;padding:clamp(16px,2vw,24px);background:var(--ac-nature2);
	margin-bottom:20px;font-size:clamp(12px,2vw,14px);
}
.aromist-cart .woocommerce-cart-form__cart-item:last-of-type{margin-bottom:0}
@media (min-width:768px){
	.aromist-cart .woocommerce-cart-form__cart-item{flex-wrap:nowrap}
}

/* تصویر */
.aromist-cart .woocommerce-cart-form__cart-item .cart-img{flex:0 0 20%;max-width:20%}
@media (min-width:768px){
	.aromist-cart .woocommerce-cart-form__cart-item .cart-img{flex:0 0 12%;max-width:12%}
}
.aromist-cart .woocommerce-cart-form__cart-item .product-thumbnail-w{
	display:flex;align-items:center;justify-content:center;background:#f7f7f7;border-radius:11px;border:1px solid var(--ac-line);overflow:hidden;
}
.aromist-cart .woocommerce-cart-form__cart-item .product-thumbnail-w img{margin:0;width:100%;height:auto;display:block;border-radius:11px}

/* نام و کنترل‌ها */
.aromist-cart .woocommerce-cart-form__cart-item .cart-item-info{flex:1 1 auto;min-width:0}
@media (max-width:576px){
	.aromist-cart .woocommerce-cart-form__cart-item .cart-item-info{flex:0 0 68%;max-width:68%}
}
.aromist-cart .woocommerce-cart-form__cart-item .cart-item-info > a,
.aromist-cart .woocommerce-cart-form__cart-item .cart-item-info .ac-item-name{
	display:block;color:var(--ac-black);font-weight:500;font-size:clamp(14px,2vw,16px);line-height:1.8;text-decoration:none;transition:.3s;
}
.aromist-cart .woocommerce-cart-form__cart-item .cart-item-info > a:hover{color:var(--ac-primary)}
.aromist-cart .woocommerce-cart-form__cart-item .variation{display:none}
.aromist-cart .woocommerce-cart-form__cart-item .backorder_notification{font-size:12px;color:var(--ac-gold);margin:4px 0 0}

.aromist-cart .prd-cart-rc{
	display:flex !important;flex-direction:row !important;direction:rtl;align-items:center;gap:10px;margin-top:10px;
}
@media (max-width:768px){
	.aromist-cart .prd-cart-rc{margin-top:15px}
}

/* تعداد: [+] عدد [−]  →  + سمت راست، − سمت چپ */
.aromist-cart .quantity.hidden{display:none !important}
.aromist-cart .prd-cart-rc .quantity{
	order:1;width:89px;height:48px;border-radius:8px;display:flex !important;flex-direction:row !important;direction:ltr;
	justify-content:center;align-items:center;background:#fff;padding:8px;margin:0;float:none;
}
.aromist-cart .prd-cart-rc .quantity .ac-minus{order:1}
.aromist-cart .prd-cart-rc .quantity input.qty{order:2}
.aromist-cart .prd-cart-rc .quantity .ac-plus{order:3}
.aromist-cart .prd-cart-rc .quantity .ac-qty-btn{
	font-size:12px;width:24px;height:24px;border-radius:8px;color:var(--ac-black);display:flex;align-items:center;justify-content:center;
	background:#fff;padding:0;margin:0;border:0;cursor:pointer;flex:0 0 24px;transition:.3s;box-shadow:none;
}
.aromist-cart .prd-cart-rc .quantity .ac-qty-btn:hover{color:var(--ac-primary)}
.aromist-cart .prd-cart-rc .quantity .ac-qty-btn:disabled{opacity:.35;cursor:default;color:var(--ac-black)}
.aromist-cart .prd-cart-rc .quantity .ac-qty-btn svg{width:12px;height:12px;display:block}
.aromist-cart .prd-cart-rc .quantity input.qty{
	pointer-events:none;cursor:default;-webkit-appearance:none;-moz-appearance:textfield;appearance:none;font-size:20px;line-height:37px;font-family:inherit;color:var(--ac-black);
	border:none !important;text-align:center !important;width:36px !important;height:auto !important;
	padding:4px 0 0 !important;margin:0 !important;background:transparent !important;box-shadow:none !important;outline:0;
}
.aromist-cart .prd-cart-rc .quantity input.qty::-webkit-outer-spin-button,
.aromist-cart .prd-cart-rc .quantity input.qty::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}

/* حذف: دکمه جدا، سمت چپ باکس تعداد */
.aromist-cart .prd-cart-rc .product-remove{order:2;display:flex;margin:0}
.aromist-cart .woocommerce-cart-form .prd-cart-rc .product-remove a.remove{
	display:flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:8px;
	color:var(--ac-danger) !important;background:#fff !important;font-size:13px;line-height:1;
	text-decoration:none;border:0;transition:.3s;margin:0;padding:0;
}
.aromist-cart .woocommerce-cart-form .prd-cart-rc .product-remove a.remove:hover{color:#fff !important;background:var(--ac-danger) !important}
.aromist-cart .woocommerce-cart-form .prd-cart-rc .product-remove a.remove svg{width:20px;height:20px;display:block}

/* باکس قیمت */
.aromist-cart .woocommerce-cart-form-price-box{
	background:var(--ac-nature);border-radius:8px;padding:clamp(12px,2vw,18px);align-self:center;margin-right:auto;flex:0 0 30%;
}
@media (max-width:1200px){
	.aromist-cart .woocommerce-cart-form-price-box{font-size:12px;margin-top:15px}
}
@media (min-width:1200px){
	.aromist-cart .woocommerce-cart-form-price-box{flex:0 0 40%}
}
@media (min-width:1400px){
	.aromist-cart .woocommerce-cart-form-price-box{flex:0 0 30%}
}
@media (max-width:576px){
	.aromist-cart .woocommerce-cart-form-price-box{margin:0;width:100%;flex:0 0 100% !important}
}
.aromist-cart .woocommerce-cart-form-price-box .product-price{
	display:flex;justify-content:center;flex-direction:column;align-items:flex-start;color:#252525;position:relative;
	float:none;width:fit-content;padding-right:46px;margin-right:auto;text-decoration:none !important;min-height:46px;
}
.aromist-cart .woocommerce-cart-form-price-box .product-price del{
	color:var(--ac-gray);line-height:14px;font-size:14px !important;text-decoration:line-through;
}
.aromist-cart .woocommerce-cart-form-price-box .product-price del *{color:var(--ac-gray)}
.aromist-cart .woocommerce-cart-form-price-box .product-price del .woocommerce-Price-currencySymbol{display:none}
.aromist-cart .woocommerce-cart-form-price-box .product-price ins{
	line-height:32px;text-decoration:none;font-size:clamp(14px,2vw,16px) !important;background:none;color:var(--ac-black);font-weight:400;
}
.aromist-cart .woocommerce-cart-form-price-box .product-price ins .woocommerce-Price-currencySymbol{font-size:14px}
.aromist-cart .woocommerce-cart-form-price-box .product-price .on-sale{
	position:absolute;top:50%;transform:translateY(-50%);right:0;font-size:16px;display:flex;align-items:center;justify-content:center;
	color:var(--ac-black);background:var(--ac-gold);width:36px;height:46px;border-radius:8px;
}
.aromist-cart .woocommerce-cart-form-price-box .product-price .on-sale .percentage{font-size:12px}
.aromist-cart .woocommerce-cart-form-price-box .product-price > .woocommerce-Price-amount{
	display:flex;width:100%;align-items:center;justify-content:space-between;flex-direction:row-reverse;gap:6px;
}
.aromist-cart .woocommerce-cart-form-price-box .product-price > ins::before{content:"قیمت ";color:var(--ac-gray);font-size:12px}
.aromist-cart .woocommerce-cart-form-price-box .product-price > .woocommerce-Price-amount::after{content:"قیمت";color:var(--ac-gray);font-size:12px}
.aromist-cart .woocommerce-cart-form-price-box .product-price > .amount,
.aromist-cart .woocommerce-cart-form-price-box .product-subtotal > .amount{font-size:clamp(14px,2vw,16px) !important}
.aromist-cart .woocommerce-cart-form-price-box .product-price > span:not(.on-sale),
.aromist-cart .woocommerce-cart-form-price-box .product-subtotal > span:not(.on-sale){color:var(--ac-black)}
.aromist-cart .woocommerce-cart-form-price-box .product-subtotal{
	display:flex;align-items:center;justify-content:space-between;gap:8px;border-top:1px solid var(--ac-line);padding-top:12px;margin-top:12px;
}

/* دکمه مخفی بروزرسانی سبد */
.aromist-cart button[name="update_cart"]{display:none !important}

/* --- کد تخفیف (ستون چپ، بالای جمع سبد) --- */
.aromist-cart .ac-coupon-box{
	margin:0 0 20px;padding:clamp(16px,2vw,24px);border-radius:16px;background:var(--ac-nature2);
	display:flex;flex-direction:column;gap:14px;
}
.aromist-cart .ac-coupon-box .ac-coupon-label{display:flex;align-items:center;gap:8px;color:var(--ac-black);font-weight:500;font-size:17px;line-height:1.6}
.aromist-cart .ac-coupon-box .ac-coupon-label svg{width:22px;height:22px;color:var(--ac-primary);flex:0 0 22px}
.aromist-cart .ac-coupon-box .coupon{display:flex;align-items:center;gap:10px;width:100%}
.aromist-cart .ac-coupon-box #coupon_code{
	flex:1 1 auto;min-width:0;width:auto;height:50px;border-radius:12px;background:#fff;padding:8px 14px !important;
	border:1px solid #e1e1e1;font-family:inherit;font-size:16px;color:var(--ac-black);margin:0;box-shadow:none;outline:0;transition:.3s;
}
.aromist-cart .ac-coupon-box #coupon_code:focus{border-color:var(--ac-primary)}
.aromist-cart .ac-coupon-box .coupon button.button{
	flex:0 0 auto;min-width:96px;height:50px;font-size:16px;border-radius:12px;display:flex;align-items:center;justify-content:center;gap:5px;
	background:var(--ac-primary);transition:.4s;color:#fff;border:0;font-family:inherit;font-weight:500;padding:0 18px;margin:0;cursor:pointer;white-space:nowrap;
}
.aromist-cart .ac-coupon-box .coupon button.button:hover{background:var(--ac-gold);color:var(--ac-black)}
.aromist-cart .ac-coupon-box .coupon button.button:disabled{opacity:.6;cursor:wait}
.aromist-cart .ac-coupon-box .ac-coupon-notice:empty{display:none}
.aromist-cart .ac-coupon-box .ac-coupon-notice .woocommerce-error,
.aromist-cart .ac-coupon-box .ac-coupon-notice .woocommerce-message,
.aromist-cart .ac-coupon-box .ac-coupon-notice .woocommerce-info{margin:0;font-size:13px;padding:10px 12px !important;border-radius:8px}
.aromist-cart .ac-coupon-box .ac-coupon-notice .woocommerce-message,
.aromist-cart .ac-coupon-box .ac-coupon-notice .woocommerce-info{background:#fff}

/* --- ستون جمع سبد (کارت سبز) --- */
.aromist-cart .cart-collaterals{width:100%;float:none}
.aromist-cart .cart-collaterals .cart_totals{width:100%;float:none;margin:0}
.aromist-cart .cart-collaterals h2{display:none}
.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box{
	font-size:15px;line-height:1.7;border-radius:16px;padding:clamp(16px,2vw,24px);display:flex;flex-direction:column;gap:12px;
	background:var(--ac-primary);color:#fff;
}
.aromist-cart .cart-collaterals .ac-totals-head{
	color:#fff;font-weight:500;font-size:17px;line-height:1.6;padding-bottom:12px;border-bottom:1px solid rgba(255,255,255,.25);
}
.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box .cart-discount,
.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box .fee,
.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box .tax-rate,
.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box .tax-total{
	display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;
	background:rgba(255,255,255,.12);border-radius:12px;padding:12px 16px;font-size:14px;color:#fff;
}
.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box .cart-discount *,
.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box .fee *,
.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box .tax-rate *,
.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box .tax-total *{color:#fff}
.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box a.woocommerce-remove-coupon{color:#FFE2E2 !important;font-size:12px;text-decoration:underline;margin-right:6px}
.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box .order-total{
	display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;
	background:#fff;color:var(--ac-black);border-radius:12px;padding:16px 18px;margin:4px 0 0;
}
.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box .order-total *{color:var(--ac-black)}
.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box .order-total .title{font-size:15px;font-weight:500}
.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box .order-total .value,
.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box .order-total .value .amount{font-size:19px;font-weight:600;color:var(--ac-primary)}
.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box .order-total strong{font-weight:inherit}
.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box .order-total small{font-size:11px;margin-right:4px;font-weight:400}
.aromist-cart .cart-collaterals .ac-ship-note{
	display:flex;align-items:center;gap:8px;font-size:13px;line-height:1.8;color:#fff;
	background:rgba(255,255,255,.1);border:1px dashed rgba(255,255,255,.5);border-radius:12px;padding:10px 14px;
}
.aromist-cart .cart-collaterals .ac-ship-note svg{width:20px;height:20px;flex:0 0 20px;color:#fff}

/* دکمه اقدام به پرداخت */
.aromist-cart .cart_totals .wc-proceed-to-checkout{padding:0;margin:0}
.aromist-cart .cart_totals .wc-proceed-to-checkout a.checkout-button{
	height:54px;display:flex;align-items:center;justify-content:center;background:var(--ac-primary-light);color:var(--ac-black) !important;
	border-radius:100px;width:100%;transition:.4s;border:0;font-family:inherit;font-weight:500;font-size:17px;
	margin:4px 0 0;padding:0 16px;text-decoration:none;line-height:1;box-shadow:none;
}
.aromist-cart .cart_totals .wc-proceed-to-checkout a.checkout-button:hover{background:var(--ac-gold);color:#fff !important}

/* --- موبایل --- */
@media (max-width:576px){
	.aromist-cart .woocommerce-cart-form__cart-item{gap:14px 12px;padding:14px 12px;margin-bottom:14px}
	.aromist-cart .woocommerce-cart-form__cart-item .cart-img{flex:0 0 72px;max-width:72px}
	.aromist-cart .woocommerce-cart-form__cart-item .cart-item-info{flex:1 1 0;max-width:none}
	.aromist-cart .prd-cart-rc .quantity{height:44px}
	.aromist-cart .woocommerce-cart-form .prd-cart-rc .product-remove a.remove{width:44px;height:44px}
	.aromist-cart .woocommerce-cart-form-price-box{padding:12px}
	.aromist-cart .woocommerce-cart-form-price-box .product-price{margin-right:0;width:100%}
	.aromist-cart .ac-coupon-box,
	.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box{padding:16px 12px;border-radius:14px}
	.aromist-cart .ac-coupon-box .coupon button.button{min-width:84px;padding:0 14px}
	.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box .order-total .value,
	.aromist-cart .cart-collaterals .woocommerce-cart-form-left-box .order-total .value .amount{font-size:17px}
}

/* --- پیام‌ها --- */
.aromist-cart .woocommerce-error{
	background:var(--ac-danger);border-radius:8px;color:#fff;text-align:center;font-size:13px;display:block;
	position:relative;width:100%;margin:12px auto;z-index:1;padding:12px !important;border:0;list-style:none;
}
.aromist-cart .woocommerce-error::before{display:none}
.aromist-cart .woocommerce-error *{color:#fff}
.aromist-cart .woocommerce-error li{margin:5px 0;padding:0}
.aromist-cart .woocommerce-info,
.aromist-cart .woocommerce-message{
	background:var(--ac-nature2);border:0;border-radius:12px;color:var(--ac-black);padding:14px 18px !important;margin:0 0 16px;font-size:14px;list-style:none;
}
.aromist-cart .woocommerce-message{border-right:4px solid var(--ac-primary)}
.aromist-cart .woocommerce-info::before,.aromist-cart .woocommerce-message::before{display:none}
.aromist-cart .woocommerce-info a,.aromist-cart .woocommerce-message a{color:var(--ac-primary)}
.aromist-cart .woocommerce-message .button{float:left;background:none;color:var(--ac-primary);padding:0;border:0}

/* --- سبد خرید خالی --- */
.aromist-cart .aromist-cart-empty{
	border-radius:32px;padding:clamp(32px,5vw,56px) clamp(16px,2vw,24px);background:var(--ac-nature2);
	text-align:center;display:flex;flex-direction:column;align-items:center;max-width:720px;margin:0 auto;
}
.aromist-cart .aromist-cart-empty .ac-empty-icon{
	width:clamp(110px,14vw,150px);height:clamp(110px,14vw,150px);border-radius:50%;background:#fff;border:1px solid var(--ac-line);
	display:flex;align-items:center;justify-content:center;margin-bottom:clamp(24px,4vw,36px);position:relative;
}
.aromist-cart .aromist-cart-empty .ac-empty-icon svg{width:46%;height:46%;color:var(--ac-primary)}
.aromist-cart .aromist-cart-empty .ac-empty-icon::after{
	content:"0";position:absolute;top:10%;left:10%;width:30px;height:30px;border-radius:50%;background:var(--ac-gold);color:var(--ac-black);
	font-size:14px;font-weight:500;display:flex;align-items:center;justify-content:center;
}
.aromist-cart .aromist-cart-empty .cart-empty{
	color:var(--ac-black);font-size:clamp(17px,2vw,20px);font-weight:500;margin:0 0 10px;padding:0;background:none;border:0;
}
.aromist-cart .aromist-cart-empty .cart-empty::before{display:none}
.aromist-cart .aromist-cart-empty .ac-empty-text{font-size:14px;line-height:2;color:var(--ac-gray);margin:0 0 clamp(20px,3vw,28px)}
.aromist-cart .aromist-cart-empty .return-to-shop{margin:0;text-align:center !important}
.aromist-cart .aromist-cart-empty .return-to-shop a.button{
	height:50px;display:inline-flex;align-items:center;justify-content:center;gap:8px;min-width:220px;padding:0 28px;
	background:var(--ac-primary);color:#fff !important;border-radius:100px;border:0;font-family:inherit;font-weight:500;font-size:15px;
	text-decoration:none;transition:.4s;box-shadow:none;
}
.aromist-cart .aromist-cart-empty .return-to-shop a.button:hover{background:var(--ac-gold);color:#fff !important}
.aromist-cart .aromist-cart-empty .return-to-shop a.button svg{width:18px;height:18px}

/* --- حالت بارگذاری ایجکس --- */
.aromist-cart .blockUI.blockOverlay{border-radius:12px}
CSS;
}

// چاپ CSS در هد صفحه‌ای که شورت‌کد دارد
add_action( 'wp_head', function () {
	if ( aromist_cart_page_has_shortcode() ) {
		$GLOBALS['aromist_cart_css_printed'] = true;
		echo '<style id="aromist-cart-css">' . aromist_cart_css() . '</style>'; // phpcs:ignore
	}
}, 99 );

/* -------------------------------------------------------------------------
 * ۳) جاوااسکریپت: دکمه‌های + / − و آپدیت خودکار سبد
 * ---------------------------------------------------------------------- */
add_action( 'wp_footer', function () {
	if ( empty( $GLOBALS['aromist_cart_rendered'] ) ) {
		return;
	}
	?>
	<script id="aromist-cart-js">
	(function ($) {
		if (!$) { return; }
		var timer;

		// + و −
		$(document.body).on('click', '.aromist-cart .quantity .ac-qty-btn', function (e) {
			e.preventDefault();
			var $qty = $(this).closest('.quantity').find('input.qty');
			if (!$qty.length || $qty.prop('disabled')) { return; }

			var val  = parseFloat($qty.val()) || 0,
				step = parseFloat($qty.attr('step')) || 1,
				min  = parseFloat($qty.attr('min')),
				max  = parseFloat($qty.attr('max'));

			if (isNaN(min) || min < 1) { min = 1; }

			if ($(this).hasClass('ac-plus')) {
				val = val + step;
				if (!isNaN(max) && max > 0 && val > max) { val = max; }
			} else {
				val = val - step;
				if (val < min) { val = min; }
			}

			val = parseFloat(val.toFixed(4));
			if (val === parseFloat($qty.val())) { return; }
			$qty.val(val).trigger('change');
		});

		// غیرفعال کردن − در حداقل و + در حداکثر
		function syncQtyButtons() {
			$('.aromist-cart .quantity').each(function () {
				var $q = $(this).find('input.qty'), v = parseFloat($q.val()) || 0,
					min = parseFloat($q.attr('min')), max = parseFloat($q.attr('max'));
				if (isNaN(min) || min < 1) { min = 1; }
				$(this).find('.ac-minus').prop('disabled', v <= min);
				$(this).find('.ac-plus').prop('disabled', !isNaN(max) && max > 0 && v >= max);
			});
		}
		$(syncQtyButtons);
		$(document.body).on('updated_wc_div updated_cart_totals change', syncQtyButtons);

		// اعمال کد تخفیف از باکس ستون چپ (ایجکس)
		$(document.body).on('submit', '.aromist-cart form.ac-coupon-form', function (e) {
			if (typeof wc_cart_params === 'undefined') { return; } // بدون ایجکس: ارسال معمولی فرم
			e.preventDefault();
			e.stopPropagation();
			var $form = $(this), $field = $form.find('input[name="coupon_code"]'),
				$btn = $form.find('button[name="apply_coupon"]'), $notice = $form.find('.ac-coupon-notice'),
				code = $.trim($field.val());
			if (!code) { $field.trigger('focus'); return; }
			$btn.prop('disabled', true);
			$notice.empty();
			$.ajax({
				type: 'POST',
				url: wc_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'apply_coupon'),
				data: { security: wc_cart_params.apply_coupon_nonce, coupon_code: code },
				dataType: 'html'
			}).done(function (response) {
				$notice.html(response);
				if (!$(response).filter('.woocommerce-error').length && !$(response).find('.woocommerce-error').length) {
					$field.val('');
				}
				$(document.body).trigger('applied_coupon', [code]);
				$(document.body).trigger('wc_update_cart', [true]);
			}).always(function () {
				$btn.prop('disabled', false);
			});
		});

		// آپدیت خودکار سبد بعد از تغییر تعداد
		$(document.body).on('change', '.aromist-cart .woocommerce-cart-form input.qty', function () {
			clearTimeout(timer);
			timer = setTimeout(function () {
				var $btn = $('.aromist-cart .woocommerce-cart-form [name="update_cart"]');
				$btn.prop('disabled', false).attr('aria-disabled', 'false');
				$('.aromist-cart .woocommerce-cart-form button[type=submit]').removeAttr('clicked');
				$btn.attr('clicked', 'true').trigger('click');
			}, 700);
		});

		// مشخص کردن دکمه کلیک‌شده (برای تشخیص «اعمال کد تخفیف» یا «بروزرسانی سبد» در cart.js ووکامرس)
		$(document.body).on('click', '.aromist-cart .woocommerce-cart-form button[type=submit]', function () {
			$('button[type=submit]', $(this).closest('form')).removeAttr('clicked');
			$(this).attr('clicked', 'true');
		});
	})(window.jQuery);
	</script>
	<?php
}, 99 );

/* -------------------------------------------------------------------------
 * ۴) کمکی‌ها
 * ---------------------------------------------------------------------- */

// قیمت نمایشی با توجه به تنظیم مالیات سبد خرید
function aromist_cart_display_price( $product, $price ) {
	if ( '' === $price || null === $price ) {
		return 0;
	}
	$args = array( 'price' => $price, 'qty' => 1 );
	return WC()->cart->display_prices_including_tax()
		? wc_get_price_including_tax( $product, $args )
		: wc_get_price_excluding_tax( $product, $args );
}

// آیکن‌ها
function aromist_cart_icon( $name ) {
	$icons = array(
		'plus'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
		'minus'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/></svg>',
		'trash'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4.5A1.5 1.5 0 0 1 9.5 3h5A1.5 1.5 0 0 1 16 4.5V6"/><path d="M5.5 6l1 13.2A2 2 0 0 0 8.5 21h7a2 2 0 0 0 2-1.8L18.5 6"/><path d="M10 10.5v6M14 10.5v6"/></svg>',
		'coupon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9V6.5A1.5 1.5 0 0 1 4.5 5h15A1.5 1.5 0 0 1 21 6.5V9a3 3 0 0 0 0 6v2.5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5V15a3 3 0 0 0 0-6z"/><path d="M9.5 14.5l5-5"/><circle cx="9.5" cy="9.5" r=".6" fill="currentColor"/><circle cx="14.5" cy="14.5" r=".6" fill="currentColor"/></svg>',
		'bag'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 7.5h14l-1.2 11.3A2.5 2.5 0 0 1 15.3 21H8.7a2.5 2.5 0 0 1-2.5-2.2z"/><path d="M8.5 10V6.5a3.5 3.5 0 0 1 7 0V10"/></svg>',
		'truck'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 6.5h11v9h-11z"/><path d="M13.5 9.5h4l3 3v3h-7z"/><circle cx="6.5" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/></svg>',
		'arrow'  =>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>',
	);
	return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
}

/* -------------------------------------------------------------------------
 * ۵) ستون جمع سبد (هم در بار اول، هم در آپدیت ایجکس)
 * ---------------------------------------------------------------------- */
function aromist_cart_totals_html() {
	$cart = WC()->cart;
	ob_start();
	?>
	<div class="cart_totals <?php echo ( WC()->customer && WC()->customer->has_calculated_shipping() ) ? 'calculated_shipping' : ''; ?>">
		<?php do_action( 'woocommerce_before_cart_totals' ); ?>

		<div class="shop_table shop_table_responsive">
			<div class="woocommerce-cart-form-left-box woocommerce-cart-form-totals">

				<div class="ac-totals-head">خلاصه سفارش</div>

				<?php foreach ( $cart->get_coupons() as $code => $coupon ) : ?>
					<div class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
						<div class="title"><?php wc_cart_totals_coupon_label( $coupon ); ?></div>
						<div class="value" data-title="<?php echo esc_attr( wc_cart_totals_coupon_label( $coupon, false ) ); ?>"><?php wc_cart_totals_coupon_html( $coupon ); ?></div>
					</div>
				<?php endforeach; ?>

				<?php foreach ( $cart->get_fees() as $fee ) : ?>
					<div class="fee">
						<div class="title"><?php echo esc_html( $fee->name ); ?></div>
						<div class="value"><?php wc_cart_totals_fee_html( $fee ); ?></div>
					</div>
				<?php endforeach; ?>

				<?php if ( wc_tax_enabled() && ! $cart->display_prices_including_tax() ) : ?>
					<?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
						<?php foreach ( $cart->get_tax_totals() as $code => $tax ) : ?>
							<div class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
								<div class="title"><?php echo esc_html( $tax->label ); ?></div>
								<div class="value"><?php echo wp_kses_post( $tax->formatted_amount ); ?></div>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<div class="tax-total">
							<div class="title"><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></div>
							<div class="value"><?php wc_cart_totals_taxes_total_html(); ?></div>
						</div>
					<?php endif; ?>
				<?php endif; ?>

				<?php do_action( 'woocommerce_cart_totals_before_order_total' ); ?>

				<div class="order-total">
					<div class="title">جمع مبلغ پرداختی</div>
					<div class="value" data-title="جمع مبلغ پرداختی"><?php wc_cart_totals_order_total_html(); ?></div>
				</div>

				<?php do_action( 'woocommerce_cart_totals_after_order_total' ); ?>

				<?php if ( $cart->needs_shipping() ) : ?>
					<div class="ac-ship-note"><?php echo aromist_cart_icon( 'truck' ); // phpcs:ignore ?><span>هزینه ارسال در مرحله بعد و پس از ثبت آدرس محاسبه می‌شود.</span></div>
				<?php endif; ?>

				<div class="wc-proceed-to-checkout">
					<?php do_action( 'woocommerce_proceed_to_checkout' ); ?>
				</div>
			</div>
		</div>

		<?php do_action( 'woocommerce_after_cart_totals' ); ?>
	</div>
	<?php
	return ob_get_clean();
}

// وقتی روش ارسال عوض می‌شود، ووکامرس جمع سبد را با ایجکس می‌گیرد → همین طراحی را برگردان
add_action( 'wc_ajax_get_cart_totals', function () {
	wc_maybe_define_constant( 'WOOCOMMERCE_CART', true );
	WC()->cart->calculate_totals();
	echo aromist_cart_totals_html(); // phpcs:ignore
	wp_die();
}, 1 );

/* -------------------------------------------------------------------------
 * ۶) سبد خرید خالی
 * ---------------------------------------------------------------------- */
function aromist_cart_empty_html() {
	ob_start();

	// اعلان‌ها و هوک‌های افزونه‌ها (بدون پیام پیش‌فرض ووکامرس)
	$priority = has_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message' );
	if ( false !== $priority ) {
		remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', $priority );
	}
	do_action( 'woocommerce_cart_is_empty' );
	if ( false !== $priority ) {
		add_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', $priority );
	}

	$shop_url = apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) );
	?>
	<div class="wc-empty-cart-message">
		<div class="aromist-cart-empty">
			<div class="ac-empty-icon"><?php echo aromist_cart_icon( 'bag' ); // phpcs:ignore ?></div>
			<p class="cart-empty" role="status"><?php echo esc_html( apply_filters( 'wc_empty_cart_message', 'سبد خرید شما در حال حاضر خالی است.' ) ); ?></p>
			<p class="ac-empty-text">هنوز محصولی به سبد خرید اضافه نکرده‌اید. برای مشاهده محصولات به فروشگاه سر بزنید.</p>
			<?php if ( wc_get_page_id( 'shop' ) > 0 ) : ?>
				<p class="return-to-shop">
					<a class="button wc-backward" href="<?php echo esc_url( $shop_url ); ?>">
						<?php echo esc_html( apply_filters( 'woocommerce_return_to_shop_text', 'بازگشت به فروشگاه' ) ); ?>
						<?php echo aromist_cart_icon( 'arrow' ); // phpcs:ignore ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * ۷) شورت‌کد [aromist_cart]
 * ---------------------------------------------------------------------- */
add_shortcode( 'aromist_cart', 'aromist_cart_shortcode' );

function aromist_cart_shortcode() {
	if ( ! function_exists( 'WC' ) || is_admin() || ! WC()->cart ) {
		return '';
	}

	wc_maybe_define_constant( 'WOOCOMMERCE_CART', true );
	wp_enqueue_script( 'wc-cart' );
	$GLOBALS['aromist_cart_rendered'] = true;

	// محاسبه‌گر هزینه ارسال (مثل شورت‌کد پیش‌فرض ووکامرس)
	$calc_nonce = isset( $_REQUEST['woocommerce-shipping-calculator-nonce'] ) ? wc_clean( wp_unslash( $_REQUEST['woocommerce-shipping-calculator-nonce'] ) ) : ''; // phpcs:ignore
	if ( ! empty( $_POST['calc_shipping'] ) && wp_verify_nonce( $calc_nonce, 'woocommerce-shipping-calculator' ) ) { // phpcs:ignore
		WC_Shortcode_Cart::calculate_shipping();
		WC()->cart->calculate_totals();
	}

	do_action( 'woocommerce_check_cart_items' );
	WC()->cart->calculate_totals();

	ob_start();

	if ( empty( $GLOBALS['aromist_cart_css_printed'] ) ) {
		$GLOBALS['aromist_cart_css_printed'] = true;
		echo '<style id="aromist-cart-css">' . aromist_cart_css() . '</style>'; // phpcs:ignore
	}

	echo '<div class="aromist-cart woocommerce">';

	if ( WC()->cart->is_empty() ) {
		echo aromist_cart_empty_html(); // phpcs:ignore
		echo '</div>';
		return ob_get_clean();
	}

	do_action( 'woocommerce_before_cart' );
	?>
	<div class="ac-cart-row">
		<div class="ac-cart-main">
			<form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
				<?php do_action( 'woocommerce_before_cart_table' ); ?>

				<div class="shop_table shop_table_responsive cart woocommerce-cart-form__contents">
					<?php do_action( 'woocommerce_before_cart_contents' ); ?>

					<?php
					foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
						$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
						$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

						if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 || ! apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
							continue;
						}

						$product_name      = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
						$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
						$thumbnail         = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'woocommerce_thumbnail' ), $cart_item, $cart_item_key );

						// --- قیمت (با تخفیف / بدون تخفیف) ---
						$regular_raw = (float) $_product->get_regular_price();
						$current_raw = (float) $_product->get_price();
						$on_sale     = $_product->is_on_sale() && $regular_raw > 0 && $current_raw < $regular_raw;

						if ( $on_sale ) {
							$percent    = (int) round( ( ( $regular_raw - $current_raw ) / $regular_raw ) * 100 );
							$price_html = wc_format_sale_price(
								aromist_cart_display_price( $_product, $regular_raw ),
								aromist_cart_display_price( $_product, $current_raw )
							);
							$price_html .= '<span class="on-sale"><span>' . esc_html( $percent ) . '<span class="percentage">%</span></span></span>';
						} else {
							$price_html = WC()->cart->get_product_price( $_product );
						}
						$price_html = apply_filters( 'woocommerce_cart_item_price', $price_html, $cart_item, $cart_item_key );

						// --- تعداد ---
						if ( $_product->is_sold_individually() ) {
							$quantity_html = sprintf( '<input type="hidden" name="cart[%s][qty]" value="1" />', esc_attr( $cart_item_key ) );
						} else {
							$max_qty  = $_product->get_max_purchase_quantity();
							$step     = apply_filters( 'woocommerce_quantity_input_step', 1, $_product );
							$input_id = uniqid( 'quantity_' );

							$quantity_html  = '<div class="quantity">';
							$quantity_html .= '<button type="button" class="ac-qty-btn ac-plus" aria-label="افزایش تعداد">' . aromist_cart_icon( 'plus' ) . '</button>';
							$quantity_html .= sprintf(
								'<label class="screen-reader-text" for="%1$s">تعداد %2$s</label><input type="number" id="%1$s" class="input-text qty text" step="%3$s" min="1" %4$s name="cart[%5$s][qty]" value="%6$s" title="تعداد" size="4" readonly tabindex="-1" autocomplete="off" />',
								esc_attr( $input_id ),
								esc_attr( wp_strip_all_tags( $product_name ) ),
								esc_attr( $step ),
								( $max_qty > 0 ? 'max="' . esc_attr( $max_qty ) . '"' : '' ),
								esc_attr( $cart_item_key ),
								esc_attr( $cart_item['quantity'] )
							);
							$quantity_html .= '<button type="button" class="ac-qty-btn ac-minus" aria-label="کاهش تعداد">' . aromist_cart_icon( 'minus' ) . '</button>';
							$quantity_html .= '</div>';
						}
						$quantity_html = apply_filters( 'woocommerce_cart_item_quantity', $quantity_html, $cart_item_key, $cart_item );

						// --- حذف (سطل آشغال) ---
						$remove_html = apply_filters(
							'woocommerce_cart_item_remove_link',
							sprintf(
								'<a href="%s" class="remove" aria-label="%s" title="حذف" data-product_id="%s" data-product_sku="%s">%s</a>',
								esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
								esc_attr( sprintf( 'حذف %s از سبد خرید', wp_strip_all_tags( $product_name ) ) ),
								esc_attr( $product_id ),
								esc_attr( $_product->get_sku() ),
								aromist_cart_icon( 'trash' )
							),
							$cart_item_key
						);
						?>
						<div class="woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
							<div class="cart-img">
								<?php
								if ( $product_permalink ) {
									printf( '<a class="product-thumbnail-w" href="%s">%s</a>', esc_url( $product_permalink ), $thumbnail ); // phpcs:ignore
								} else {
									printf( '<span class="product-thumbnail-w">%s</span>', $thumbnail ); // phpcs:ignore
								}
								?>
							</div>

							<div class="cart-item-info">
								<?php
								if ( $product_permalink ) {
									echo wp_kses_post( sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $product_name ) );
								} else {
									echo '<span class="ac-item-name">' . wp_kses_post( $product_name ) . '</span>';
								}

								do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );

								echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore

								if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
									echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">در انتظار موجودی</p>', $product_id ) );
								}
								?>
								<div class="prd-cart-rc">
									<?php echo $quantity_html; // phpcs:ignore ?>
									<span class="product-remove"><?php echo $remove_html; // phpcs:ignore ?></span>
								</div>
							</div>

							<div class="woocommerce-cart-form-price-box">
								<div class="product-price price<?php echo $on_sale ? ' price-on-sale' : ''; ?>" data-title="قیمت">
									<?php echo $price_html; // phpcs:ignore ?>
								</div>

								<div class="product-subtotal" data-title="جمع جزء">
									<span>قیمت کل</span>
									<?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>

					<?php do_action( 'woocommerce_cart_contents' ); ?>

					<button type="submit" class="button" name="update_cart" value="بروزرسانی سبد" disabled>بروزرسانی سبد</button>

					<?php do_action( 'woocommerce_cart_actions' ); ?>

					<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>

					<?php do_action( 'woocommerce_after_cart_contents' ); ?>
				</div>

				<?php do_action( 'woocommerce_after_cart_table' ); ?>
			</form>
		</div>

		<div class="ac-cart-side">
			<?php if ( wc_coupons_enabled() ) : ?>
				<form class="ac-coupon-box ac-coupon-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
					<div class="ac-coupon-label"><?php echo aromist_cart_icon( 'coupon' ); // phpcs:ignore ?>کد تخفیف دارید؟</div>
					<div class="coupon">
						<label for="coupon_code" class="screen-reader-text">کد تخفیف</label>
						<input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="کد تخفیف را وارد کنید" autocomplete="off" />
						<button type="submit" class="button" name="apply_coupon" value="اعمال">اعمال</button>
						<?php do_action( 'woocommerce_cart_coupon' ); ?>
					</div>
					<div class="ac-coupon-notice" aria-live="polite"></div>
					<input type="hidden" name="woocommerce-cart-nonce" value="<?php echo esc_attr( wp_create_nonce( 'woocommerce-cart' ) ); ?>" />
				</form>
			<?php endif; ?>

			<div class="cart-collaterals">
				<?php echo aromist_cart_totals_html(); // phpcs:ignore ?>
			</div>
		</div>
	</div>
	<?php
	do_action( 'woocommerce_after_cart' );

	echo '</div>';

	return ob_get_clean();
}
