<?php
/*
  Plugin Name: Gateway zibal for Woocommerce
  Version: 1.9
  Description: این افزونه درگاه زیبال برای فروشگاه ساز ووکامرس میباشد.
  Author: zibal
  Author URI: https://zibal.ir
  Stable tag: 1.9
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
*/

if (!defined('ABSPATH'))
    exit;

define('WOO_GAPIRDIRZIBAL', plugin_dir_path(__FILE__));
define('WOO_GAPIRDUZIBAL', plugin_dir_url(__FILE__));

function load_zibal_woo_gateway()
{

    add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_zibal_Gateway');
    function Woocommerce_Add_zibal_Gateway($methods)
    {
        $methods[] = 'WC_Zibal';
        return $methods;
    }
    require_once(WOO_GAPIRDIRZIBAL . 'class-wc-gateway-zibal.php');

}
add_action('plugins_loaded', 'load_zibal_woo_gateway', 0);

function declare_zibal_cart_checkout_blocks_compatibility()
{

    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
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

    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            $payment_method_registry->register(new zibal_Gateway_Blocks);
        }
    );
}
