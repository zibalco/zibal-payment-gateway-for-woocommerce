<?php
/*
Plugin Name: Gateway Zibal for WooCommerce
Description: درگاه پرداخت زیبال برای فروشگاه‌ساز ووکامرس.
Version: 2.1.0
Requires at least: 4.7
Requires PHP: 5.6
WC requires at least: 3.0
WC tested up to: 10.8
Author: Zibal
Author URI: https://zibal.ir
Text Domain: zibal-woocommerce
Domain Path: /languages
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
*/

if (!defined('ABSPATH'))
    exit;

define('WOO_GAPIRDIRZIBAL', plugin_dir_path(__FILE__));
define('WOO_GAPIRDUZIBAL', plugin_dir_url(__FILE__));
define('WOO_ZIBAL_VERSION', '2.1.0');

function Woocommerce_Add_zibal_Gateway($methods)
{
    $methods[] = 'WC_Gateway_Zibal';
    return $methods;
}

function zibal_woocommerce_load_textdomain()
{
    load_plugin_textdomain(
        'zibal-woocommerce',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}

function load_zibal_woo_gateway()
{
    add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_zibal_Gateway');
    require_once(WOO_GAPIRDIRZIBAL . 'class-wc-gateway-zibal.php');
}
add_action('plugins_loaded', 'zibal_woocommerce_load_textdomain', 0);
add_action('plugins_loaded', 'load_zibal_woo_gateway', 0);

function declare_zibal_cart_checkout_blocks_compatibility()
{

    $features_util = 'Automattic\\WooCommerce\\Utilities\\FeaturesUtil';

    if (class_exists($features_util)) {
        call_user_func(array($features_util, 'declare_compatibility'), 'cart_checkout_blocks', __FILE__, true);
        call_user_func(array($features_util, 'declare_compatibility'), 'custom_order_tables', __FILE__, true);
    }
}

add_action('before_woocommerce_init', 'declare_zibal_cart_checkout_blocks_compatibility');
add_action('woocommerce_blocks_loaded', 'zibal_register_order_approval_payment_method_type');

function zibal_register_order_approval_payment_method_type()
{

    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }

    require_once plugin_dir_path(__FILE__) . 'class-block.php';

    add_action('woocommerce_blocks_payment_method_type_registration', 'zibal_register_blocks_payment_method');
}

function zibal_register_blocks_payment_method($payment_method_registry)
{
    $payment_method_registry->register(new Zibal_Gateway_Blocks());
}
