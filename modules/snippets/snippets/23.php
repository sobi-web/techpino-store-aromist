<?php
// اسنیپت #23: شماره موبایل و فاکتور در uPanel
defined( 'ABSPATH' ) || exit;


add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax()) {
        return;
    }
    if (strpos($_SERVER['REQUEST_URI'], 'panel') === false) {
        return;
    }

    $current_mobile = '';
    if (is_user_logged_in()) {
        $current_mobile = get_user_meta(get_current_user_id(), 'mobile_otino', true);
    }

    // Build {order_id: invoice_url} for the current user's own orders.
    // The invoice link needs the WooCommerce order_key (the "wc=" part),
    // which the plugin doesn't send to the browser, so we fetch it here,
    // server-side, with full WooCommerce access, and hand only the
    // finished links to the page.
    $invoice_links = array();
    if (is_user_logged_in() && function_exists('wc_get_orders')) {
        $customer_orders = wc_get_orders(array(
            'customer_id' => get_current_user_id(),
            'limit'       => -1,
            'return'      => 'objects',
        ));
        foreach ($customer_orders as $order) {
            $invoice_links[(string) $order->get_id()] = add_query_arg(
                array(
                    'action'   => 'fci-show',
                    'type'     => 'invoice',
                    'order-id' => $order->get_id(),
                    'wc'       => $order->get_order_key(),
                ),
                home_url('/')
            );
        }
    }

    ob_start(function ($html) use ($current_mobile, $invoice_links) {
        // Only touch the actual uPanel app shell, leave every other page untouched.
        if (strpos($html, 'ng-app="hd-upanel"') === false) {
            return $html;
        }

        // Nowdoc: everything below is 100% literal text to PHP, no escaping
        // of quotes/backslashes/$ needed at all, so a save-pipeline that
        // strips backslashes (the earlier cause of the syntax error) can't
        // break it anymore.
        $js = <<<'HD_JS'
(function () {
    var HD_CUSTOM_MOBILE = __HD_CUSTOM_MOBILE__;
    var HD_INVOICE_LINKS = __HD_INVOICE_LINKS__;
    var HD_AJAX_URL = __HD_AJAX_URL__;
    var HD_NONCE = __HD_NONCE__;

    window.hdSaveCustomMobile = function () {
        var val = document.getElementById("hd_custom_mobile").value;
        jQuery.post(HD_AJAX_URL, {
            action: "hd_theme_save_mobile",
            nonce: HD_NONCE,
            mobile: val
        }).done(function (r) {
            alert(r && r.success ? "شماره موبایل ذخیره شد"
                : (r && r.data === "mobile_taken" ? "این شماره برای حساب دیگری ثبت شده است" : "شماره موبایل معتبر نیست"));
        }).fail(function () {
            alert("ذخیره نشد؛ صفحه را تازه کنید و دوباره امتحان کنید");
        });
    };

    // AngularJS $http interceptor: rewrites the plugin page templates
    // (orders.html / settings.html) right after they are fetched over HTTP,
    // before Angular compiles them, so our markup becomes a normal part
    // of the app (Angular directives like {{order.ID}} still work in it).
    angular.module("hd-upanel")
        .config(["$httpProvider", function ($httpProvider) {
            $httpProvider.interceptors.push(function () {
                return {
                    response: function (response) {
                        var url = response.config && response.config.url;
                        if (!url) {
                            return response;
                        }

                        // Idempotency guard: AngularJS calls this interceptor again
                        // every time the route is revisited, even when the template
                        // is served from $templateCache instead of the network. If we
                        // always re-append, the markup piles up on every visit. So
                        // every injection below first checks a unique marker and
                        // skips if it is already there.

                        if (url.indexOf("settings.html") > -1 && response.data.indexOf("hd_custom_mobile") === -1) {
                            // Anchored on a single, whole line (the submit button tag)
                            // on purpose: this file's line endings are CRLF, and a
                            // multi-line anchor built with plain \n never matches CRLF
                            // content, so the injection silently never happens.
                            var anchor = `<button type="submit" ng-click="edit_user()" value="Upload">`;
                            var field = `<div class="upanel-material-textfield">
      <input type="text" id="hd_custom_mobile" placeholder=" " value="` + HD_CUSTOM_MOBILE + `">
      <label>شماره موبایل</label>
    </div>
    <button type="button" onclick="hdSaveCustomMobile()">ذخیره شماره موبایل</button>
    ` + anchor;
                            response.data = response.data.split(anchor).join(field);
                        }

                        if (url.indexOf("orders.html") > -1 && response.data.indexOf("hd-invoice-btn") === -1) {
                            // "order" is in scope here because this markup is spliced
                            // straight into the ng-repeat row, {{}} still works normally.
                            var btnAnchor = `<button ng-click="data.single_order = order" type="button">مشاهده</button>`;
                            var btnNew = btnAnchor + `<a class="hd-invoice-btn" ng-if="hd_invoice_links[order.ID]" target="_blank" href="{{hd_invoice_links[order.ID]}}">فاکتور</a>`;
                            response.data = response.data.split(btnAnchor).join(btnNew);
                        }

                        return response;
                    }
                };
            });
        }])
        .run(["$rootScope", function ($rootScope) {
            // Exposed on $rootScope so every child scope (including each
            // ng-repeat row's scope) can read it via prototypal inheritance.
            $rootScope.hd_invoice_links = HD_INVOICE_LINKS;
        }]);
})();
HD_JS;

        $js = str_replace(
            array('__HD_CUSTOM_MOBILE__', '__HD_INVOICE_LINKS__', '__HD_AJAX_URL__', '__HD_NONCE__'),
            array(
                wp_json_encode((string) $current_mobile),
                wp_json_encode($invoice_links, JSON_FORCE_OBJECT),
                wp_json_encode(admin_url('admin-ajax.php')),
                wp_json_encode(wp_create_nonce('hd_theme_save_mobile')),
            ),
            $js
        );

        $inject = '<script>' . $js . '</script>';

        return str_replace('</body>', $inject . '</body>', $html);
    });
}, 0);

// 2) New, independent AJAX endpoint. Saves into "mobile_otino", the
// usermeta key the site's own mobile-login system actually reads
// (format: 9xxxxxxxxx, no leading zero, no country code).
add_action('wp_ajax_hd_theme_save_mobile', 'hd_theme_save_mobile');
function hd_theme_save_mobile()
{
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in');
    }
    // Without this, any other website could change a logged-in visitor's login mobile number (CSRF).
    check_ajax_referer('hd_theme_save_mobile', 'nonce');

    $mobile = isset($_POST['mobile']) ? sanitize_text_field(wp_unslash($_POST['mobile'])) : '';

    // Normalize: keep digits only, strip a leading country code or zero.
    $mobile = preg_replace('/\D/', '', $mobile);
    if (strpos($mobile, '0098') === 0) {
        $mobile = substr($mobile, 4);
    } elseif (strpos($mobile, '98') === 0 && strlen($mobile) === 12) {
        $mobile = substr($mobile, 2);
    } elseif (strpos($mobile, '0') === 0) {
        $mobile = substr($mobile, 1);
    }

    if (!preg_match('/^9\d{9}$/', $mobile)) {
        wp_send_json_error('invalid_mobile');
    }

    // One number = one account: refuse a number that another user already logs in with.
    $taken = get_users(array(
        'meta_key'   => 'mobile_otino',
        'meta_value' => $mobile,
        'exclude'    => array(get_current_user_id()),
        'number'     => 1,
        'fields'     => 'ids',
    ));
    if ($taken) {
        wp_send_json_error('mobile_taken');
    }

    update_user_meta(get_current_user_id(), 'mobile_otino', $mobile);
    wp_send_json_success();
}