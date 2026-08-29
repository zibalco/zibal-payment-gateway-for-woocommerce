<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Zibal_Gateway_Blocks extends AbstractPaymentMethodType
{

    private $gateway;
    protected $name = 'WC_Zibal';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_zibal_gateway_settings', []);
        $this->gateway = new WC_Zibal();
    }

    public function is_active()
    {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles()
    {

        wp_register_script(
            'zibal_gateway-blocks-integration',
            plugin_dir_url(__FILE__) . '/assets/js/zibal-checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('zibal_gateway-blocks-integration');

        }
        return ['zibal_gateway-blocks-integration'];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
            'icon' => plugin_dir_url(__FILE__) . '/assets/images/logo.svg'
        ];
    }

}
?>