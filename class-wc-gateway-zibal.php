<?php

if (!defined('ABSPATH'))
  exit;

if (class_exists('WC_Payment_Gateway') && !class_exists('WC_Gateway_Zibal')) {

  class WC_Gateway_Zibal extends WC_Payment_Gateway
  {

    const TRACK_ID_META_KEY = '_zibal_track_id';
    const REQUESTED_AMOUNT_META_KEY = '_zibal_requested_amount';
    const PAYMENT_STATE_META_KEY = '_zibal_payment_state';
    const TRANSACTIONS_META_KEY = '_zibal_transactions';
    const VERIFY_LOCK_PREFIX = 'zibal_verify_lock_';

    private static $admin_notices_registered = false;

    private $pin;
    private $sandbox;
    private $success_massage;
    private $failed_massage;
    private $author;

    public function __construct()
    {

      $this->author = 'zibal.ir';

      $this->id = 'WC_Gateway_Zibal';
      $this->method_title = __('زیبال', 'zibal-woocommerce');
      $this->method_description = __('تنظیمات درگاه پرداخت زیبال برای افزونه فروشگاه ساز ووکامرس', 'zibal-woocommerce');
      $this->icon = apply_filters('woo_zibal_logo', WOO_GAPIRDUZIBAL . 'assets/images/logo.png');
      $this->has_fields = false;

      $this->init_form_fields();
      $this->init_settings();

      // WC_Payment_Gateway only started hydrating this property itself in newer
      // WooCommerce versions. Keep it explicit for older releases.
      $this->enabled = $this->get_option('enabled', 'yes');
      $this->title = $this->get_option('title', __('زیبال', 'zibal-woocommerce'));
      $this->description = $this->get_option('description', __('پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق درگاه زیبال', 'zibal-woocommerce'));

      $this->pin = $this->get_option('pin', '');
      $this->sandbox = $this->get_option('sandbox', 'no');

      $this->success_massage = $this->get_option('success_massage', __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'zibal-woocommerce'));
      $this->failed_massage = $this->get_option('failed_massage', __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'zibal-woocommerce'));

      if (!defined('WOOCOMMERCE_VERSION') || version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
      } else {
        add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
      }
      add_action('woocommerce_receipt_' . $this->id, array($this, 'Send_to_zibal_Gateway'));
      add_action('woocommerce_api_' . strtolower($this->id), array($this, 'Return_from_zibal_Gateway'));
      if (is_admin() && !self::$admin_notices_registered) {
        if ($this->sandbox === 'yes') {
          add_action('admin_notices', array($this, 'add_sandbox_notice_to_admin_bar'));
        }
        add_action('admin_notices', array($this, 'admin_notice_missing_pin'));
        self::$admin_notices_registered = true;
      }
    }

    public function admin_options()
    {
      parent::admin_options();
    }

    public function init_form_fields()
    {
      $this->form_fields = apply_filters(
        'WC_Gateway_Zibal_Config',
        array(
          'base_confing' => array(
            'title' => __('تنظیمات پایه ای', 'zibal-woocommerce'),
            'type' => 'title',
            'description' => '',
          ),
          'enabled' => array(
            'title' => __('فعالسازی/غیرفعالسازی', 'zibal-woocommerce'),
            'type' => 'checkbox',
            'label' => __('فعالسازی درگاه زیبال', 'zibal-woocommerce'),
            'description' => __('برای فعالسازی درگاه پرداخت زیبال باید چک باکس را تیک بزنید', 'zibal-woocommerce'),
            'default' => 'yes',
            'desc_tip' => true,
          ),
          'title' => array(
            'title' => __('عنوان درگاه', 'zibal-woocommerce'),
            'type' => 'text',
            'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'zibal-woocommerce'),
            'default' => __('زیبال', 'zibal-woocommerce'),
            'desc_tip' => true,
          ),
          'description' => array(
            'title' => __('توضیحات درگاه', 'zibal-woocommerce'),
            'type' => 'text',
            'desc_tip' => true,
            'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'zibal-woocommerce'),
            'default' => __('پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق درگاه زیبال', 'zibal-woocommerce')
          ),
          'account_confing' => array(
            'title' => __('تنظیمات حساب زیبال', 'zibal-woocommerce'),
            'type' => 'title',
            'description' => '',
          ),
          'pin' => array(
            'title' => __('مرچنت کد', 'zibal-woocommerce'),
            'type' => 'text',
            'description' => __('مرچنت کد درگاه زیبال', 'zibal-woocommerce'),
            'default' => '',
            'desc_tip' => true
          ),
          'sandbox' => array(
            'title' => __('فعالسازی حالت آزمایشی', 'zibal-woocommerce'),
            'type' => 'checkbox',
            'label' => __('فعالسازی حالت آزمایشی زیبال', 'zibal-woocommerce'),
            'description' => __('برای فعال سازی حالت آزمایشی زیبال چک باکس را تیک بزنید .', 'zibal-woocommerce'),
            'default' => 'no',
            'desc_tip' => true,
          ),
          'payment_confing' => array(
            'title' => __('تنظیمات عملیات پرداخت', 'zibal-woocommerce'),
            'type' => 'title',
            'description' => '',
          ),
          'success_massage' => array(
            'title' => __('پیام پرداخت موفق', 'zibal-woocommerce'),
            'type' => 'textarea',
            'description' => __('متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (کد تراکنش زیبال) استفاده نمایید .', 'zibal-woocommerce'),
            'default' => __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'zibal-woocommerce'),
          ),
          'failed_massage' => array(
            'title' => __('پیام پرداخت ناموفق', 'zibal-woocommerce'),
            'type' => 'textarea',
            'description' => __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت زیبال ارسال میگردد .', 'zibal-woocommerce'),
            'default' => __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'zibal-woocommerce'),
          )
        )
      );
    }

    public function process_payment($order_id)
    {
      $order = wc_get_order($order_id);
      if (!$order) {
        return array('result' => 'failure');
      }
      return array(
        'result' => 'success',
        'redirect' => $order->get_checkout_payment_url(true)
      );
    }

    public function is_available()
    {
      if (!parent::is_available()) {
        return false;
      }

      if ('yes' !== $this->sandbox && '' === trim((string) $this->pin)) {
        return false;
      }

      $currency = apply_filters('WC_Gateway_Zibal_Currency', get_woocommerce_currency(), 0);
      return $this->is_supported_currency($currency);
    }

    private function is_supported_currency($currency)
    {
      $currency = strtolower(trim((string) $currency));
      $supported_currencies = array(
        'irr',
        'irt',
        'toman',
        'iran toman',
        'iranian toman',
        'iran-toman',
        'iranian-toman',
        'iran_toman',
        'iranian_toman',
        'تومان',
        'تومان ایران',
        'irht',
        'irhr',
      );

      return in_array($currency, $supported_currencies, true);
    }

    private function get_zibal_amount($order)
    {
      $order_id = $order->get_id();
      $currency = apply_filters('WC_Gateway_Zibal_Currency', $order->get_currency(), $order_id);
      $normalized_currency = strtolower(trim((string) $currency));

      if (!$this->is_supported_currency($normalized_currency)) {
        return new WP_Error('zibal_unsupported_currency', __('واحد پول این سفارش توسط درگاه زیبال پشتیبانی نمی‌شود.', 'zibal-woocommerce'));
      }

      $amount = (float) $order->get_total();
      $amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $amount, $currency);

      if (in_array($normalized_currency, array('irt', 'toman', 'iran toman', 'iranian toman', 'iran-toman', 'iranian-toman', 'iran_toman', 'iranian_toman', 'تومان', 'تومان ایران'), true)) {
        $amount = $amount * 10;
      } elseif ('irht' === $normalized_currency) {
        $amount = $amount * 10000;
      } elseif ('irhr' === $normalized_currency) {
        $amount = $amount * 1000;
      }

      $amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $amount, $currency);
      $amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $amount, $currency);
      $amount = apply_filters('woocommerce_order_amount_total_Zibal_gateway', $amount, $currency);
      $amount = (int) round((float) $amount);

      if ($amount <= 0) {
        return new WP_Error('zibal_invalid_amount', __('مبلغ سفارش برای پرداخت معتبر نیست.', 'zibal-woocommerce'));
      }

      return $amount;
    }

    private function normalize_mobile($phone)
    {
      $phone = strtr(
        (string) $phone,
        array(
          '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
          '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
          '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
          '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        )
      );
      $phone = preg_replace('/\D+/', '', (string) $phone);

      if (0 === strpos($phone, '0098')) {
        $phone = '0' . substr($phone, 4);
      } elseif (0 === strpos($phone, '98')) {
        $phone = '0' . substr($phone, 2);
      } elseif (10 === strlen($phone) && '9' === substr($phone, 0, 1)) {
        $phone = '0' . $phone;
      }

      return preg_match('/^09\d{9}$/', $phone) ? $phone : '';
    }

    private function amounts_equal($provider_amount, $expected_amount)
    {
      if (!is_numeric($provider_amount)) {
        return false;
      }

      return (float) $provider_amount === (float) (int) $expected_amount;
    }

    private function values_equal($known_value, $provided_value)
    {
      $known_value = (string) $known_value;
      $provided_value = (string) $provided_value;

      if (function_exists('hash_equals')) {
        return hash_equals($known_value, $provided_value);
      }

      $known_length = strlen($known_value);
      if ($known_length !== strlen($provided_value)) {
        return false;
      }

      $difference = 0;
      for ($index = 0; $index < $known_length; $index++) {
        $difference |= ord($known_value[$index]) ^ ord($provided_value[$index]);
      }

      return 0 === $difference;
    }

    private function compare_transaction_age($first, $second)
    {
      return (int) $first['created_at'] - (int) $second['created_at'];
    }

    private function encode_json($value, $options = 0)
    {
      if (function_exists('wp_json_encode')) {
        return wp_json_encode($value, $options);
      }

      if (version_compare(PHP_VERSION, '5.3.0', '>=')) {
        return json_encode($value, $options);
      }

      $encoded_value = json_encode($value);
      if ($options) {
        $encoded_value = str_replace(
          array('<', '>', '&', "'"),
          array('\\u003C', '\\u003E', '\\u0026', '\\u0027'),
          $encoded_value
        );
      }

      return $encoded_value;
    }

    private function unslash_request_value($value)
    {
      if (function_exists('wp_unslash')) {
        return wp_unslash($value);
      }

      return function_exists('stripslashes_deep') ? stripslashes_deep($value) : $value;
    }

    private function limit_plain_text($value, $maximum_length)
    {
      $value = wp_strip_all_tags((string) $value);
      $normalized_value = preg_replace('/\s+/u', ' ', $value);
      if (null !== $normalized_value) {
        $value = $normalized_value;
      }
      $value = trim($value);

      if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maximum_length, 'UTF-8');
      }

      $matches = array();
      if (1 === preg_match('/^.{0,' . (int) $maximum_length . '}/us', $value, $matches)) {
        return $matches[0];
      }

      return substr($value, 0, $maximum_length);
    }

    private function sanitize_reference($value, $maximum_length)
    {
      return $this->limit_plain_text(sanitize_text_field((string) $value), $maximum_length);
    }

    private function mask_card_number($value)
    {
      $value = $this->sanitize_reference($value, 32);
      $digits = preg_replace('/\D+/', '', $value);

      if (strlen($digits) >= 12) {
        return substr($digits, 0, 6) . str_repeat('*', strlen($digits) - 10) . substr($digits, -4);
      }

      return $value;
    }

    private function is_valid_track_id($track_id)
    {
      return 1 === preg_match('/^\d{1,64}$/', (string) $track_id);
    }

    private function order_requires_manual_review($order)
    {
      $status = method_exists($order, 'get_status') ? (string) $order->get_status() : '';
      return in_array($status, array('cancelled', 'refunded', 'trash'), true);
    }

    private function redirect_internal($url)
    {
      if (function_exists('wp_safe_redirect')) {
        wp_safe_redirect($url);
      } else {
        wp_redirect($url);
      }

      exit;
    }

    private function store_transaction($order, $track_id, $amount, $base_url)
    {
      $transactions = $order->get_meta(self::TRANSACTIONS_META_KEY, true);
      $transactions = is_array($transactions) ? $transactions : array();
      $week_in_seconds = defined('WEEK_IN_SECONDS') ? WEEK_IN_SECONDS : 604800;
      $minimum_time = time() - $week_in_seconds;

      foreach ($transactions as $saved_track_id => $transaction) {
        if (!is_array($transaction) || empty($transaction['created_at']) || (int) $transaction['created_at'] < $minimum_time) {
          unset($transactions[$saved_track_id]);
        }
      }

      $transactions[(string) $track_id] = array(
        'amount' => (int) $amount,
        'base_url' => (string) $base_url,
        'created_at' => time(),
        'state' => 'requested',
      );

      if (count($transactions) > 10) {
        uasort($transactions, array($this, 'compare_transaction_age'));
        $transactions = array_slice($transactions, -10, null, true);
      }

      $order->update_meta_data(self::TRANSACTIONS_META_KEY, $transactions);
      // Preserve the original keys for existing installations and integrations.
      $order->update_meta_data(self::TRACK_ID_META_KEY, (string) $track_id);
      $order->update_meta_data(self::REQUESTED_AMOUNT_META_KEY, (int) $amount);
      $order->update_meta_data(self::PAYMENT_STATE_META_KEY, 'requested');
      $order->save();
    }

    private function get_transaction($order, $track_id)
    {
      $transactions = $order->get_meta(self::TRANSACTIONS_META_KEY, true);
      if (is_array($transactions)) {
        foreach ($transactions as $saved_track_id => $transaction) {
          if ($this->values_equal($saved_track_id, $track_id) && is_array($transaction)) {
            return $transaction;
          }
        }
      }

      $legacy_track_id = (string) $order->get_meta(self::TRACK_ID_META_KEY, true);
      if ($legacy_track_id && $track_id && $this->values_equal($legacy_track_id, $track_id)) {
        return array(
          'amount' => (int) $order->get_meta(self::REQUESTED_AMOUNT_META_KEY, true),
          'base_url' => '',
          'created_at' => 0,
          'state' => (string) $order->get_meta(self::PAYMENT_STATE_META_KEY, true),
        );
      }

      return false;
    }

    private function update_transaction_state($order, $track_id, $state)
    {
      $transactions = $order->get_meta(self::TRANSACTIONS_META_KEY, true);
      if (!is_array($transactions)) {
        return;
      }

      foreach ($transactions as $saved_track_id => $transaction) {
        if ($this->values_equal($saved_track_id, $track_id) && is_array($transaction)) {
          $transactions[$saved_track_id]['state'] = (string) $state;
          $order->update_meta_data(self::TRANSACTIONS_META_KEY, $transactions);
          return;
        }
      }
    }

    private function api_request_with_fallback($endpoint, $data, &$used_base_url = '', &$raw_response = '', $preferred_base_url = '')
    {
      $api_bases = array('https://gateway.zibal.ir', 'https://gateway.zibal.io');
      $errors = array();
      $payload = $this->encode_json($data);

      if (false === $payload || null === $payload || '' === $payload) {
        return new WP_Error('zibal_json_error', __('اطلاعات درخواست پرداخت قابل پردازش نیست.', 'zibal-woocommerce'));
      }

      if (in_array($preferred_base_url, $api_bases, true)) {
        $api_bases = array_values(array_unique(array_merge(array($preferred_base_url), $api_bases)));
      }

      foreach ($api_bases as $api_base) {
        $response = wp_remote_post(
          $api_base . '/v1/' . ltrim($endpoint, '/'),
          array(
            'body' => $payload,
            'headers' => array(
              'Content-Type' => 'application/json',
              'User-Agent' => apply_filters('WC_Gateway_Zibal_User_Agent', 'Zibal-WooCommerce-Plugin/' . (defined('WOO_ZIBAL_VERSION') ? WOO_ZIBAL_VERSION : '2.1.0')),
            ),
            'timeout' => 'https://gateway.zibal.ir' === $api_base ? 12 : 8,
            'redirection' => 0,
            'sslverify' => false,
            'data_format' => 'body',
          )
        );

        if (is_wp_error($response)) {
          $errors[] = $api_base . ': ' . $response->get_error_message();
          continue;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body);

        if ($status_code < 200 || $status_code >= 300 || empty($body) || !is_object($result)) {
          $errors[] = sprintf('%s [HTTP %d]: invalid response', $api_base, $status_code);
          continue;
        }

        $used_base_url = $api_base;
        $raw_response = $body;
        return $result;
      }

      $error_details = !empty($errors) ? implode(' | ', $errors) : 'No response from Zibal endpoints';
      return new WP_Error('zibal_connection_error', $error_details);
    }

    private function redirect_to_gateway($base_url, $track_id)
    {
      $url = trailingslashit($base_url) . 'start/' . rawurlencode($track_id);

      if (!headers_sent()) {
        wp_redirect($url);
        exit;
      }

      // 15 combines JSON_HEX_TAG, JSON_HEX_AMP, JSON_HEX_APOS and JSON_HEX_QUOT
      // without referencing constants that may be unavailable on very old PHP.
      $encoded_url = $this->encode_json($url, 15);
      echo '<script>window.location.replace(' . $encoded_url . ');</script>';
      echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_attr($url) . '"></noscript>';
      echo '<p><a href="' . esc_url($url) . '">' . esc_html__('برای انتقال به درگاه زیبال اینجا کلیک کنید.', 'zibal-woocommerce') . '</a></p>';
      exit;
    }

    private function acquire_verification_lock($order_id)
    {
      global $wpdb;

      $lock_key = self::VERIFY_LOCK_PREFIX . absint($order_id);
      $created_at = (int) get_option($lock_key, 0);

      if ($created_at && (time() - $created_at) > 60) {
        delete_option($lock_key);
      }

      $inserted = $wpdb->query(
        $wpdb->prepare(
          "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
          $lock_key,
          (string) time(),
          'no'
        )
      );

      if (1 === (int) $inserted) {
        wp_cache_delete($lock_key, 'options');
        wp_cache_delete('notoptions', 'options');
        return true;
      }

      return false;
    }

    private function release_verification_lock($order_id)
    {
      delete_option(self::VERIFY_LOCK_PREFIX . absint($order_id));
    }

    private function reject_callback($order, $admin_message, $customer_message)
    {
      if ($order) {
        $order->add_order_note($admin_message);
      }

      wc_add_notice($customer_message, 'error');
      $this->redirect_internal(wc_get_checkout_url());
    }

    public function Send_to_zibal_Gateway($order_id)
    {
      $zibal_buffer_started = ob_start();
      $Message = '';
      $Fault = '';
      $Customer_Message = '';
      $raw_response = '';
      global $woocommerce;
      if (isset($woocommerce->session) && $woocommerce->session) {
        $woocommerce->session->order_id_zibal = $order_id;
      }
      $order = wc_get_order($order_id);
      if (!$order) {
        wc_add_notice(__('اطلاعات سفارش معتبر نیست؛ لطفاً دوباره تلاش کنید.', 'zibal-woocommerce'), 'error');
        if ($zibal_buffer_started) {
          ob_end_flush();
        }
        return false;
      }
      if ('yes' !== $this->sandbox && '' === trim((string) $this->pin)) {
        $message = __('مرچنت کد زیبال تنظیم نشده است؛ لطفاً با مدیر فروشگاه تماس بگیرید.', 'zibal-woocommerce');
        $order->add_order_note($message);
        wc_add_notice($message, 'error');
        if ($zibal_buffer_started) {
          ob_end_flush();
        }
        return false;
      }
      $action = $this->author;
      do_action('WC_Gateway_Payment_Actions', $action);
      $form = '<form action="" method="POST" class="zibal-checkout-form" id="zibal-checkout-form">
						<input type="submit" name="zibal_submit" class="button alt" id="zibal-payment-button" value="' . esc_attr__('پرداخت', 'zibal-woocommerce') . '"/>
						<a class="button cancel" href="' . esc_url(wc_get_checkout_url()) . '">' . esc_html__('بازگشت', 'zibal-woocommerce') . '</a>
					 </form><br/>';
      $form = apply_filters('WC_Gateway_Zibal_Form', $form, $order_id, $woocommerce);

      do_action('WC_Gateway_Zibal_Gateway_Before_Form', $order_id, $woocommerce);
      echo $form;
      do_action('WC_Gateway_Zibal_Gateway_After_Form', $order_id, $woocommerce);

      $action = $this->author;
      do_action('WC_Gateway_Payment_Actions', $action);
      $Amount = $this->get_zibal_amount($order);
      if (is_wp_error($Amount)) {
        $order->add_order_note(sprintf(__('ایجاد پرداخت زیبال متوقف شد: %s', 'zibal-woocommerce'), esc_html($Amount->get_error_message())));
        wc_add_notice($Amount->get_error_message(), 'error');
        if ($zibal_buffer_started) {
          ob_end_flush();
        }
        return false;
      }
      $products = array();
      $order_items = $order->get_items();
      foreach ($order_items as $product) {
        $product_name = '';
        $product_quantity = 0;
        if (is_object($product) && method_exists($product, 'get_name')) {
          $product_name = $product->get_name();
        } elseif (is_array($product) && isset($product['name'])) {
          $product_name = $product['name'];
        } elseif ($product instanceof ArrayAccess && isset($product['name'])) {
          $product_name = $product['name'];
        }
        if (is_object($product) && method_exists($product, 'get_quantity')) {
          $product_quantity = $product->get_quantity();
        } elseif (is_array($product) && isset($product['qty'])) {
          $product_quantity = $product['qty'];
        } elseif ($product instanceof ArrayAccess && isset($product['qty'])) {
          $product_quantity = $product['qty'];
        }
        $products[] = $product_name . ' (' . $product_quantity . ') ';
      }
      $products = implode(' - ', $products);
      $Description = 'خریدار : ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . ' | محصولات : ' . $products;
      $Tell = $this->normalize_mobile($order->get_billing_phone());
      $Email = $order->get_billing_email();

      $Description = apply_filters('WC_Gateway_Zibal_Description', $Description, $order_id);
      $Description = $this->limit_plain_text($Description, 500);
      do_action('WC_Gateway_Zibal_Gateway_Payment', $order_id, $Description);

      $CallbackURL = add_query_arg(
        array(
          'wc_order' => $order_id,
          'key' => $order->get_order_key(),
        ),
        WC()->api_request_url('WC_Gateway_Zibal')
      );

      $Sandbox = $this->sandbox;
      $apiID = $Sandbox == "yes" ? 'zibal' : $this->pin;

      $request_data = array(
        'merchant' => $apiID,
        'amount' => $Amount,
        'callbackUrl' => $CallbackURL,
        'orderId' => $order_id,
        'email' => $Email,
        'description' => $Description,
      );
      if ('' !== $Tell) {
        $request_data['mobile'] = $Tell;
      }

      $gateway_base_url = '';
      $result = $this->api_request_with_fallback(
        'request',
        $request_data,
        $gateway_base_url,
        $raw_response
      );

      if (is_wp_error($result)) {
        $Message = $result->get_error_message();
        $Fault = $result->get_error_code();
        $Customer_Message = __('خطا در اتصال به زیبال، لطفاً به مدیر سایت اطلاع دهید.', 'zibal-woocommerce');
      } else {
        if (isset($result->result, $result->trackId) && '100' === (string) $result->result && $this->is_valid_track_id($result->trackId)) {
          $this->store_transaction($order, (string) $result->trackId, (int) $Amount, $gateway_base_url);
          $this->redirect_to_gateway($gateway_base_url, $result->trackId);
        } elseif (isset($result->result) && '100' === (string) $result->result) {
          $Fault = 'missing_track_id';
          $Message = 'زیبال نتیجه موفق برگرداند، اما trackId در پاسخ موجود نیست.';
          $Customer_Message = __('ایجاد پرداخت ناموفق بود، لطفاً دوباره تلاش کنید.', 'zibal-woocommerce');
          $raw_response = '';
        } else {
          $Fault = isset($result->result) ? $result->result : 'invalid_response';
          $Message = isset($result->message) ? (string) $result->message : 'تراکنش ناموفق بود- کد خطا : ' . $Fault;
          $Customer_Message = __('ایجاد پرداخت ناموفق بود، لطفاً دوباره تلاش کنید.', 'zibal-woocommerce');
        }
      }

      if (!empty($Message) && $Message) {

        $Note = sprintf(__('خطا در هنگام ارسال به زیبال: %s', 'zibal-woocommerce'), esc_html($Message));
        $Note = apply_filters('WC_Gateway_Zibal_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault);
        $order->add_order_note($Note);

        $Notice = $Customer_Message;
        $Notice = apply_filters('WC_Gateway_Zibal_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $Fault);
        if ($Notice)
          wc_add_notice($Notice, 'error');

        do_action('WC_Gateway_Zibal_Send_to_Gateway_Failed', $order_id, $Fault);
      }

      if ($zibal_buffer_started) {
        ob_end_flush();
      }
    }

    public function Return_from_zibal_Gateway()
    {
      $Status = 'failed';
      $Fault = '';
      $Message = '';
      $Customer_Message = '';
      $Transaction_ID = '';
      $verify_cardnum = '';
      $verify_tracking = '';
      $verify_raw_response = '';
      $verify_base_url = '';
      $success = isset($_GET['success']) ? sanitize_text_field($this->unslash_request_value($_GET['success'])) : '';
      $success = in_array($success, array('0', '1'), true) ? $success : '';
      $trackId = isset($_GET['trackId']) ? $this->sanitize_reference($this->unslash_request_value($_GET['trackId']), 64) : '';
      $callback_order_key = isset($_GET['key']) ? $this->sanitize_reference($this->unslash_request_value($_GET['key']), 128) : '';

      global $woocommerce;
      $action = $this->author;
      do_action('WC_Gateway_Payment_Actions', $action);

      $order_id = 0;
      if (isset($_GET['wc_order'])) {
        $order_id = absint($this->unslash_request_value($_GET['wc_order']));
      } elseif (isset($woocommerce->session) && $woocommerce->session && isset($woocommerce->session->order_id_zibal)) {
        $order_id = absint($woocommerce->session->order_id_zibal);
      }

      if (isset($woocommerce->session) && $woocommerce->session) {
        unset($woocommerce->session->order_id_zibal);
      }

      if ($order_id) {

        $order = wc_get_order($order_id);

        if (!$order) {
          $this->reject_callback(false, '', __('اطلاعات سفارش معتبر نیست؛ لطفاً دوباره تلاش کنید.', 'zibal-woocommerce'));
        }

        if ('WC_Gateway_Zibal' !== $order->get_payment_method()) {
          $this->reject_callback(
            $order,
            __('Callback زیبال رد شد: روش پرداخت سفارش متعلق به این درگاه نیست.', 'zibal-woocommerce'),
            __('اطلاعات پرداخت معتبر نیست؛ لطفاً به مدیر سایت اطلاع دهید.', 'zibal-woocommerce')
          );
        }

        $order_key = (string) $order->get_order_key();
        if (!$callback_order_key || !$order_key || !$this->values_equal($order_key, $callback_order_key)) {
          $this->reject_callback(
            $order,
            __('Callback زیبال رد شد: کلید سفارش معتبر نیست.', 'zibal-woocommerce'),
            __('اطلاعات پرداخت معتبر نیست؛ لطفاً به مدیر سایت اطلاع دهید.', 'zibal-woocommerce')
          );
        }

        $transaction = $this->is_valid_track_id($trackId) ? $this->get_transaction($order, $trackId) : false;
        if (!$transaction) {
          $this->reject_callback(
            $order,
            sprintf(
              __('Callback زیبال رد شد: trackId دریافتی (%s) متعلق به این سفارش نیست.', 'zibal-woocommerce'),
              esc_html($trackId)
            ),
            __('اطلاعات پرداخت معتبر نیست؛ لطفاً به مدیر سایت اطلاع دهید.', 'zibal-woocommerce')
          );
        }

        if ($order->is_paid()) {
          $paid_transaction_id = (string) $order->get_transaction_id();
          if (!$paid_transaction_id || !$this->values_equal($paid_transaction_id, $trackId)) {
            $this->reject_callback(
              $order,
              __('Callback تکراری زیبال رد شد: شناسه تراکنش پرداخت‌شده با trackId سفارش مطابقت ندارد.', 'zibal-woocommerce'),
              __('اطلاعات پرداخت معتبر نیست؛ لطفاً به مدیر سایت اطلاع دهید.', 'zibal-woocommerce')
            );
          }

          $Notice = wpautop(wptexturize($this->success_massage));
          $Notice = str_replace('{transaction_id}', $trackId, $Notice);
          wc_add_notice($Notice, 'success');
          $this->redirect_internal(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
        }

        $stored_amount = isset($transaction['amount']) ? (int) $transaction['amount'] : 0;
        if ('1' === $success && $stored_amount <= 0) {
          $order->update_meta_data(self::PAYMENT_STATE_META_KEY, 'review_required');
          $order->update_status('on-hold', __('مبلغ درخواست‌شده زیبال روی سفارش موجود نیست و پرداخت نیازمند بررسی مدیر است.', 'zibal-woocommerce'));
          $order->save();
          wc_add_notice(__('وضعیت پرداخت شما نیازمند بررسی است؛ لطفاً با مدیر سایت تماس بگیرید.', 'zibal-woocommerce'), 'notice');
          $this->redirect_internal($this->get_return_url($order));
        }

        $current_amount = '1' === $success ? $this->get_zibal_amount($order) : $stored_amount;
        $current_amount_matches = '1' !== $success || (!is_wp_error($current_amount) && (int) $current_amount === $stored_amount);
        $Amount = $stored_amount;

        $Sandbox = $this->sandbox;
        $apiID = $Sandbox == "yes" ? 'zibal' : $this->pin;

        if (!$order->is_paid()) {
          if ($success == '1') {

            if (!$this->acquire_verification_lock($order_id)) {
              wc_add_notice(__('پرداخت شما در حال بررسی است؛ لطفاً چند لحظه صبر کنید.', 'zibal-woocommerce'), 'notice');
              $this->redirect_internal($this->get_return_url($order));
            }

            $result = $this->api_request_with_fallback(
              'verify',
              array(
                'merchant' => $apiID,
                'trackId' => $trackId
              ),
              $verify_base_url,
              $verify_raw_response,
              isset($transaction['base_url']) ? (string) $transaction['base_url'] : ''
            );

            if (is_wp_error($result)) {
              $Status = 'failed';
              $Fault = $result->get_error_code();
              $Message = $result->get_error_message();
              $Customer_Message = __('خطا در اتصال به زیبال، لطفاً به مدیر سایت اطلاع دهید.', 'zibal-woocommerce');
            } elseif (isset($result->result, $result->amount) && '100' === (string) $result->result && $this->amounts_equal($result->amount, $Amount)) {
              if (!$current_amount_matches) {
                $current_amount_text = is_wp_error($current_amount) ? $current_amount->get_error_message() : (string) $current_amount;
                $this->update_transaction_state($order, $trackId, 'review_required');
                $order->update_meta_data(self::PAYMENT_STATE_META_KEY, 'review_required');
                $order->update_status(
                  'on-hold',
                  sprintf(
                    __('پرداخت در زیبال تأیید شد، اما مبلغ فعلی سفارش (%1$s) با مبلغ پرداخت‌شده (%2$d) مطابقت ندارد. trackId: %3$s', 'zibal-woocommerce'),
                    esc_html($current_amount_text),
                    $stored_amount,
                    esc_html($trackId)
                  )
                );
                $order->save();
                $this->release_verification_lock($order_id);
                wc_add_notice(__('پرداخت شما انجام شده اما به‌دلیل تغییر مبلغ سفارش نیازمند بررسی مدیر است.', 'zibal-woocommerce'), 'notice');
                $this->redirect_internal($this->get_return_url($order));
              }

              if ($this->order_requires_manual_review($order)) {
                $this->update_transaction_state($order, $trackId, 'review_required');
                $order->update_meta_data(self::PAYMENT_STATE_META_KEY, 'review_required');
                $order->update_status(
                  'on-hold',
                  sprintf(
                    __('پرداخت در زیبال تأیید شد، اما وضعیت قبلی سفارش برای تکمیل خودکار مناسب نبود. trackId: %s', 'zibal-woocommerce'),
                    esc_html($trackId)
                  )
                );
                $order->save();
                $this->release_verification_lock($order_id);
                wc_add_notice(__('پرداخت انجام شده و سفارش برای بررسی مدیر ثبت شده است.', 'zibal-woocommerce'), 'notice');
                $this->redirect_internal($this->get_return_url($order));
              }

              $Status = 'completed';
              $Transaction_ID = $trackId;
              $verify_cardnum = isset($result->cardNumber) ? $this->mask_card_number($result->cardNumber) : '';
              $verify_tracking = isset($result->refNumber) ? $this->sanitize_reference($result->refNumber, 64) : '';
              $Fault = '';
              $Message = '';
            } elseif (isset($result->result) && $result->result == "100") {
              $Message = sprintf(
                'مغایرت مبلغ پرداخت: مبلغ اعلامی زیبال %1$s و مبلغ مورد انتظار سفارش %2$s است.',
                isset($result->amount) ? (string) $result->amount : 'نامشخص',
                (string) $Amount
              );
              $this->update_transaction_state($order, $trackId, 'review_required');
              $order->update_meta_data(self::PAYMENT_STATE_META_KEY, 'review_required');
              $order->update_status('on-hold', $Message);
              $order->save();
              $this->release_verification_lock($order_id);
              wc_add_notice(__('پرداخت انجام شده اما مبلغ اعلامی زیبال با سفارش مطابقت ندارد و نیازمند بررسی مدیر است.', 'zibal-woocommerce'), 'notice');
              $this->redirect_internal($this->get_return_url($order));
            } elseif (isset($result->result) && $result->result == "201") {

              $Message = isset($result->message) ? (string) $result->message : 'این تراکنش قبلا تایید شده است';
              $order->update_meta_data(self::PAYMENT_STATE_META_KEY, 'review_required');
              $order->update_status(
                'on-hold',
                sprintf(
                  __('زیبال نتیجه 201 برگرداند، اما سفارش در ووکامرس پرداخت‌شده نیست. trackId: %1$s. پیام: %2$s', 'zibal-woocommerce'),
                  esc_html($trackId),
                  esc_html($Message)
                )
              );
              $this->update_transaction_state($order, $trackId, 'review_required');
              $order->save();
              $this->release_verification_lock($order_id);
              wc_add_notice(__('پرداخت شما توسط زیبال قبلاً تأیید شده و اکنون نیازمند بررسی مدیر سایت است.', 'zibal-woocommerce'), 'notice');
              $this->redirect_internal($this->get_return_url($order));
            } else {
              $Status = 'failed';
              $Fault = isset($result->result) ? $result->result : 'invalid_response';
              $Message = isset($result->message) ? (string) $result->message : 'تراکنش ناموفق بود- کد خطا : ' . $Fault;
              $Customer_Message = __('پرداخت ناموفق بود، لطفاً دوباره تلاش کنید.', 'zibal-woocommerce');
            }
          } else {
            $Status = 'failed';
            $Fault = 'payment_cancelled';
            $Message = 'پرداخت توسط کاربر لغو شد.';
            $Customer_Message = __('پرداخت توسط شما لغو شد.', 'zibal-woocommerce');
          }

          if ($Status == 'completed' && isset($Transaction_ID) && $Transaction_ID != 0) {
            $action = $this->author;
            do_action('WC_Gateway_Payment_Actions', $action);
            $order->update_meta_data('_card_number', $verify_cardnum);
            $order->update_meta_data('_tracking_number', $verify_tracking);
            $order->update_meta_data(self::PAYMENT_STATE_META_KEY, 'verified');
            $this->update_transaction_state($order, $trackId, 'verified');

            $order->payment_complete($Transaction_ID);
            $order->save();
            if (isset($woocommerce->cart) && $woocommerce->cart) {
              $woocommerce->cart->empty_cart();
            }

            $Note = sprintf(__('کد رهگیری : %s', 'zibal-woocommerce'), esc_html($Transaction_ID));
            $Note .= sprintf(__('<br/>شماره کارت پرداخت کننده : %s', 'zibal-woocommerce'), esc_html($verify_cardnum));
            $Note .= sprintf(__('<br/>شماره تراکنش : %s', 'zibal-woocommerce'), esc_html($verify_tracking));
            $Note = apply_filters('WC_Gateway_Zibal_Return_from_Gateway_Success_Note', $Note, $order_id, $Transaction_ID, $verify_cardnum, $verify_tracking);
            if ($Note)
              $order->add_order_note($Note);


            $Notice = wpautop(wptexturize($this->success_massage));

            $Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

            $Notice = apply_filters('WC_Gateway_Zibal_Return_from_Gateway_Success_Notice', $Notice, $order_id, $Transaction_ID);
            if ($Notice)
              wc_add_notice($Notice, 'success');

            do_action('WC_Gateway_Zibal_Return_from_Gateway_Success', $order_id, $Transaction_ID);

            $this->release_verification_lock($order_id);
            $this->redirect_internal(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
          } else {

            $action = $this->author;
            do_action('WC_Gateway_Payment_Actions', $action);
            $tr_id = ($Transaction_ID && $Transaction_ID != 0) ? ('<br/>کد تراکنش : ' . esc_html($Transaction_ID)) : '';

            $this->update_transaction_state($order, $trackId, '1' === $success ? 'verification_failed' : 'cancelled');
            $order->update_meta_data(self::PAYMENT_STATE_META_KEY, '1' === $success ? 'verification_failed' : 'cancelled');
            $order->save();

            $Note = sprintf(__('خطا در هنگام بازگشت از زیبال : %s %s', 'zibal-woocommerce'), esc_html($Message), $tr_id);
            $Note = apply_filters('WC_Gateway_Zibal_Return_from_Gateway_Failed_Note', $Note, $order_id, $Transaction_ID, $Fault);
            if ($Note)
              $order->add_order_note($Note);

            $failure_reason = $Customer_Message ? $Customer_Message : __('پرداخت ناموفق بود، لطفاً دوباره تلاش کنید.', 'zibal-woocommerce');
            $Notice = wpautop(wptexturize($this->failed_massage));
            $Notice = str_replace('{fault}', esc_html(wp_strip_all_tags($failure_reason)), $Notice);
            $Notice = apply_filters('WC_Gateway_Zibal_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $Transaction_ID, $Fault);
            if ($Notice)
              wc_add_notice($Notice, 'error');

            do_action('WC_Gateway_Zibal_Return_from_Gateway_Failed', $order_id, $Transaction_ID, $Fault);

            if ($success == '1') {
              $this->release_verification_lock($order_id);
            }
            $this->redirect_internal(wc_get_checkout_url());
          }
        } else {

          $Transaction_ID = $order->get_transaction_id();

          $Notice = wpautop(wptexturize($this->success_massage));

          $Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

          $Notice = apply_filters('WC_Gateway_Zibal_Return_from_Gateway_ReSuccess_Notice', $Notice, $order_id, $Transaction_ID);
          if ($Notice)
            wc_add_notice($Notice, 'success');


          do_action('WC_Gateway_Zibal_Return_from_Gateway_ReSuccess', $order_id, $Transaction_ID);

          $this->release_verification_lock($order_id);
          $this->redirect_internal(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
        }
      } else {

        $Fault = __('شماره سفارش وجود ندارد .', 'zibal-woocommerce');
        $Notice = __('اطلاعات سفارش معتبر نیست؛ لطفاً دوباره تلاش کنید.', 'zibal-woocommerce');
        $Notice = apply_filters('WC_Gateway_Zibal_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault);
        if ($Notice)
          wc_add_notice($Notice, 'error');

        do_action('WC_Gateway_Zibal_Return_from_Gateway_No_Order_ID', $order_id, $Transaction_ID, $Fault);

        $this->redirect_internal(wc_get_checkout_url());
      }
    }

    public function add_sandbox_notice_to_admin_bar($wp_admin_bar = null)
    {
      if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
        return;
      }
      $settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=WC_Gateway_Zibal');
      echo '<div class="notice notice-error is-dismissible">';
      echo '<p>' . esc_html__('درگاه زیبال در حالت پرداخت آزمایشی فعال است. پرداخت‌های واقعی انجام نخواهند شد.', 'zibal-woocommerce') . ' ';
      echo '<a href="' . esc_url($settings_url) . '">' . esc_html__('مشاهده تنظیمات درگاه', 'zibal-woocommerce') . '</a></p>';
      echo '</div>';
    }

    public function admin_notice_missing_pin()
    {
      if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
        return;
      }

      if (empty($this->pin) && 'yes' === $this->enabled) {
        $settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=WC_Gateway_Zibal');
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p>' . esc_html__('مرچنت کد درگاه زیبال خالی است.', 'zibal-woocommerce') . ' ';
        echo '<a href="' . esc_url($settings_url) . '">' . esc_html__('تکمیل تنظیمات درگاه', 'zibal-woocommerce') . '</a></p>';
        echo '</div>';
      }
    }

  }
}
