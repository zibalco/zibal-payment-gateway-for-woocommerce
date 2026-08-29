<?php

if (!defined('ABSPATH')) {
    exit;
}

$zibal_abstract_payment_method_class = 'Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType';

if (!class_exists('Zibal_Abstract_Payment_Method_Type', false)) {
    class_alias($zibal_abstract_payment_method_class, 'Zibal_Abstract_Payment_Method_Type');
}

final class Zibal_Gateway_Blocks extends Zibal_Abstract_Payment_Method_Type
{

    private $gateway;
    protected $name = 'WC_Gateway_Zibal';

    public function initialize()
    {
        $this->gateway = new WC_Gateway_Zibal();
        $this->settings = $this->gateway->settings;
    }

    public function is_active()
    {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles()
    {

        wp_register_script(
            'zibal_gateway-blocks-integration',
            plugin_dir_url(__FILE__) . 'assets/js/zibal-checkout.js',
            array(
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ),
            defined('WOO_ZIBAL_VERSION') ? WOO_ZIBAL_VERSION : '2.1.0',
            true
        );
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(
                'zibal_gateway-blocks-integration',
                'zibal-woocommerce',
                WOO_GAPIRDIRZIBAL . 'languages'
            );

        }
        return array('zibal_gateway-blocks-integration');
    }

    public function get_payment_method_data()
    {
        $supports = isset($this->gateway->supports) && is_array($this->gateway->supports)
            ? array_values($this->gateway->supports)
            : array();

        return array(
            'title' => wp_strip_all_tags((string) $this->gateway->title),
            'description' => wp_strip_all_tags((string) $this->gateway->description),
            'icon' => plugin_dir_url(__FILE__) . 'assets/images/logo.png',
            'supports' => $supports,
        );
    }

}
