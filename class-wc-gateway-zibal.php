<?php

if (!defined('ABSPATH'))
  exit;

if (class_exists('WC_Payment_Gateway') && !class_exists('WC_Gateway_Zibal')) {

  class WC_Gateway_Zibal extends WC_Payment_Gateway
  {

    const TRACK_ID_META_KEY = '_zibal_track_id';
    const REQUESTED_AMOUNT_META_KEY = '_zibal_requested_amount';
    const PAYMENT_STATE_META_KEY = '_zibal_payment_state';
    const VERIFY_LOCK_PREFIX = 'zibal_verify_lock_';

    private $pin;
    private $sandbox;
    private $success_massage;
    private $failed_massage;
    private $author;

    public function __construct()
    {

      $this->author = 'zibal.ir';

      $this->id = 'WC_Gateway_Zibal';
      $this->method_title = __('زیبال', 'woocommerce');
      $this->method_description = __('تنظیمات درگاه پرداخت زیبال برای افزونه فروشگاه ساز ووکامرس', 'woocommerce');
      $this->icon = apply_filters('woo_zibal_logo', WOO_GAPIRDUZIBAL . '/assets/images/logo.png');
      $this->has_fields = false;

      $this->init_form_fields();
      $this->init_settings();

      $this->title = $this->settings['title'];
      $this->description = $this->settings['description'];

      $this->pin = $this->settings['pin'];
      $this->sandbox = $this->settings['sandbox'];

      $this->success_massage = $this->settings['success_massage'];
      $this->failed_massage = $this->settings['failed_massage'];

      if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
      } else {
        add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
      }
      add_action('woocommerce_receipt_' . $this->id, array($this, 'Send_to_zibal_Gateway'));
      add_action('woocommerce_api_' . strtolower($this->id), array($this, 'Return_from_zibal_Gateway'));
      if (is_admin() && $this->settings['sandbox'] === 'yes') {
        add_action('admin_bar_menu', array($this, 'add_sandbox_notice_to_admin_bar'), 100);
      }
      add_action('admin_notices', array($this, 'admin_notice_missing_pin'));
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
            'title' => __('تنظیمات پایه ای', 'woocommerce'),
            'type' => 'title',
            'description' => '',
          ),
          'enabled' => array(
            'title' => __('فعالسازی/غیرفعالسازی', 'woocommerce'),
            'type' => 'checkbox',
            'label' => __('فعالسازی درگاه زیبال', 'woocommerce'),
            'description' => __('برای فعالسازی درگاه پرداخت زیبال باید چک باکس را تیک بزنید', 'woocommerce'),
            'default' => 'yes',
            'desc_tip' => true,
          ),
          'title' => array(
            'title' => __('عنوان درگاه', 'woocommerce'),
            'type' => 'text',
            'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woocommerce'),
            'default' => __('زیبال', 'woocommerce'),
            'desc_tip' => true,
          ),
          'description' => array(
            'title' => __('توضیحات درگاه', 'woocommerce'),
            'type' => 'text',
            'desc_tip' => true,
            'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce'),
            'default' => __('پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق درگاه زیبال', 'woocommerce')
          ),
          'account_confing' => array(
            'title' => __('تنظیمات حساب زیبال', 'woocommerce'),
            'type' => 'title',
            'description' => '',
          ),
          'pin' => array(
            'title' => __('مرچنت کد', 'woocommerce'),
            'type' => 'text',
            'description' => __('مرچنت کد درگاه زیبال', 'woocommerce'),
            'default' => '',
            'desc_tip' => true
          ),
          'sandbox' => array(
            'title' => __('فعالسازی حالت آزمایشی', 'woocommerce'),
            'type' => 'checkbox',
            'label' => __('فعالسازی حالت آزمایشی زیبال', 'woocommerce'),
            'description' => __('برای فعال سازی حالت آزمایشی زیبال چک باکس را تیک بزنید .', 'woocommerce'),
            'default' => 'no',
            'desc_tip' => true,
          ),
          'payment_confing' => array(
            'title' => __('تنظیمات عملیات پرداخت', 'woocommerce'),
            'type' => 'title',
            'description' => '',
          ),
          'success_massage' => array(
            'title' => __('پیام پرداخت موفق', 'woocommerce'),
            'type' => 'textarea',
            'description' => __('متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (کد تراکنش زیبال) استفاده نمایید .', 'woocommerce'),
            'default' => __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'woocommerce'),
          ),
          'failed_massage' => array(
            'title' => __('پیام پرداخت ناموفق', 'woocommerce'),
            'type' => 'textarea',
            'description' => __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت زیبال ارسال میگردد .', 'woocommerce'),
            'default' => __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'woocommerce'),
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

    private function api_request_with_fallback($endpoint, $data, &$used_base_url = '', &$raw_response = '')
    {
      $api_bases = array('https://gateway.zibal.ir', 'https://gateway.zibal.io');
      $errors = array();

      foreach ($api_bases as $api_base) {
        $response = wp_remote_post(
          $api_base . '/v1/' . ltrim($endpoint, '/'),
          array(
            'body' => wp_json_encode($data),
            'headers' => array(
              'Content-Type' => 'application/json',
              'User-Agent' => apply_filters('WC_Gateway_Zibal_User_Agent', 'Zibal-WooCommerce-Plugin/2.0'),
            ),
            'timeout' => 'https://gateway.zibal.ir' === $api_base ? 20 : 10,
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

        if ($status_code >= 500 || empty($body) || !is_object($result)) {
          $errors[] = sprintf('%s [HTTP %d]: %s', $api_base, $status_code, $body ? $body : 'Empty response');
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
      wp_redirect($url);
      exit;
    }

    private function acquire_verification_lock($order_id)
    {
      $lock_key = self::VERIFY_LOCK_PREFIX . absint($order_id);
      $created_at = (int) get_option($lock_key, 0);

      if ($created_at && (time() - $created_at) > 60) {
        delete_option($lock_key);
      }

      return add_option($lock_key, time(), '', 'no');
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
      wp_redirect(wc_get_checkout_url());
      exit;
    }

    public function Send_to_zibal_Gateway($order_id)
    {
      ob_start();
      $Message = '';
      $Fault = '';
      $Customer_Message = '';
      $raw_response = '';
      global $woocommerce;
      $woocommerce->session->order_id_zibal = $order_id;
      $order = wc_get_order($order_id);
      if (!$order) {
        wc_add_notice(__('اطلاعات سفارش معتبر نیست؛ لطفاً دوباره تلاش کنید.', 'woocommerce'), 'error');
        return false;
      }
      $currency = $order->get_currency();
      $currency = apply_filters('WC_Gateway_Zibal_Currency', $currency, $order_id);
      $action = $this->author;
      do_action('WC_Gateway_Payment_Actions', $action);
      $form = '<form action="" method="POST" class="zibal-checkout-form" id="zibal-checkout-form">
						<input type="submit" name="zibal_submit" class="button alt" id="zibal-payment-button" value="' . __('پرداخت', 'woocommerce') . '"/>
						<a class="button cancel" href="' . wc_get_checkout_url() . '">' . __('بازگشت', 'woocommerce') . '</a>
					 </form><br/>';
      $form = apply_filters('WC_Gateway_Zibal_Form', $form, $order_id, $woocommerce);

      do_action('WC_Gateway_Zibal_Gateway_Before_Form', $order_id, $woocommerce);
      echo $form;
      do_action('WC_Gateway_Zibal_Gateway_After_Form', $order_id, $woocommerce);

      $action = $this->author;
      do_action('WC_Gateway_Payment_Actions', $action);
      $Amount = intval($order->get_total());
      $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
      if (
        strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
      )
        $Amount = $Amount * 10;
      else if (strtolower($currency) == strtolower('IRHT'))
        $Amount = $Amount * 10000;
      else if (strtolower($currency) == strtolower('IRHR'))
        $Amount = $Amount * 1000;
      else if (strtolower($currency) == strtolower('IRR'))
        $Amount = $Amount;

      $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency);
      $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency);
      $Amount = apply_filters('woocommerce_order_amount_total_Zibal_gateway', $Amount, $currency);
      $products = array();
      $order_items = $order->get_items();
      foreach ($order_items as $product) {
        $products[] = $product['name'] . ' (' . $product['qty'] . ') ';
      }
      $products = implode(' - ', $products);
      $Description = 'خریدار : ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . ' | محصولات : ' . $products;
      $Tell = intval($order->get_billing_phone());
      $Email = $order->get_billing_email();

      $Description = apply_filters('WC_Gateway_Zibal_Description', $Description, $order_id);
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

      $gateway_base_url = '';
      $result = $this->api_request_with_fallback(
        'request',
        [
          'merchant' => $apiID,
          'amount' => $Amount,
          'callbackUrl' => $CallbackURL,
          'orderId' => $order_id,
          'mobile' => $Tell,
          'email' => $Email,
          'description' => $Description
        ],
        $gateway_base_url,
        $raw_response
      );

      if (is_wp_error($result)) {
        $Message = $result->get_error_message();
        $Fault = $result->get_error_code();
        $Customer_Message = __('خطا در اتصال به زیبال، لطفاً به مدیر سایت اطلاع دهید.', 'woocommerce');
      } else {
        if (isset($result->result, $result->trackId) && "100" == $result->result) {
          $order->update_meta_data(self::TRACK_ID_META_KEY, (string) $result->trackId);
          $order->update_meta_data(self::REQUESTED_AMOUNT_META_KEY, (int) $Amount);
          $order->update_meta_data(self::PAYMENT_STATE_META_KEY, 'requested');
          $order->save();
          $this->redirect_to_gateway($gateway_base_url, $result->trackId);
        } elseif (isset($result->result) && "100" == $result->result) {
          $Fault = 'missing_track_id';
          $Message = 'زیبال نتیجه موفق برگرداند، اما trackId در پاسخ موجود نیست.';
          $Customer_Message = __('ایجاد پرداخت ناموفق بود، لطفاً دوباره تلاش کنید.', 'woocommerce');
          $raw_response = '';
        } else {
          $Fault = isset($result->result) ? $result->result : 'invalid_response';
          $Message = isset($result->message) ? (string) $result->message : 'تراکنش ناموفق بود- کد خطا : ' . $Fault;
          $Customer_Message = __('ایجاد پرداخت ناموفق بود، لطفاً دوباره تلاش کنید.', 'woocommerce');
        }
      }

      if (!empty($Message) && $Message) {

        $Note = sprintf(__('خطا در هنگام ارسال به زیبال: %s', 'woocommerce'), esc_html($Message));
        if ($raw_response) {
          $Note .= '<br/>' . sprintf(__('پاسخ زیبال: %s', 'woocommerce'), esc_html($raw_response));
        }
        $Note = apply_filters('WC_Gateway_Zibal_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault);
        $order->add_order_note($Note);

        $Notice = $Customer_Message;
        $Notice = apply_filters('WC_Gateway_Zibal_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $Fault);
        if ($Notice)
          wc_add_notice($Notice, 'error');

        do_action('WC_Gateway_Zibal_Send_to_Gateway_Failed', $order_id, $Fault);
      }
    }

    public function Return_from_zibal_Gateway()
    {
      $Status = 'failed';
      $Fault = '';
      $Message = '';
      $Customer_Message = '';
      $Transaction_ID = '';
      $verify_raw_response = '';
      $verify_base_url = '';
      $success = isset($_GET['success']) ? sanitize_text_field($_GET['success']) : '';
      $trackId = isset($_GET['trackId']) ? sanitize_text_field($_GET['trackId']) : '';
      $callback_order_key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
      $tracking_number = isset($_POST['tracking_number']) ? sanitize_text_field($_POST['tracking_number']) : '';
      $card_number = isset($_POST['cardnumber']) ? sanitize_text_field($_POST['cardnumber']) : '';

      global $woocommerce;
      $action = $this->author;
      do_action('WC_Gateway_Payment_Actions', $action);

      if (isset($_GET['wc_order']))
        $order_id = absint($_GET['wc_order']);
      else
        $order_id = absint($woocommerce->session->order_id_zibal);
      unset($woocommerce->session->order_id_zibal);

      if ($order_id) {

        $order = wc_get_order($order_id);

        if (!$order) {
          $this->reject_callback(false, '', __('اطلاعات سفارش معتبر نیست؛ لطفاً دوباره تلاش کنید.', 'woocommerce'));
        }

        if ('WC_Gateway_Zibal' !== $order->get_payment_method()) {
          $this->reject_callback(
            $order,
            __('Callback زیبال رد شد: روش پرداخت سفارش متعلق به این درگاه نیست.', 'woocommerce'),
            __('اطلاعات پرداخت معتبر نیست؛ لطفاً به مدیر سایت اطلاع دهید.', 'woocommerce')
          );
        }

        $order_key = (string) $order->get_order_key();
        if (!$callback_order_key || !$order_key || !hash_equals($order_key, $callback_order_key)) {
          $this->reject_callback(
            $order,
            __('Callback زیبال رد شد: کلید سفارش معتبر نیست.', 'woocommerce'),
            __('اطلاعات پرداخت معتبر نیست؛ لطفاً به مدیر سایت اطلاع دهید.', 'woocommerce')
          );
        }

        $saved_track_id = (string) $order->get_meta(self::TRACK_ID_META_KEY, true);
        if (!$saved_track_id || !$trackId || !hash_equals($saved_track_id, (string) $trackId)) {
          $this->reject_callback(
            $order,
            sprintf(
              __('Callback زیبال رد شد: trackId دریافتی (%1$s) با trackId سفارش (%2$s) مطابقت ندارد.', 'woocommerce'),
              esc_html($trackId),
              esc_html($saved_track_id)
            ),
            __('اطلاعات پرداخت معتبر نیست؛ لطفاً به مدیر سایت اطلاع دهید.', 'woocommerce')
          );
        }

        if ($order->is_paid()) {
          $paid_transaction_id = (string) $order->get_transaction_id();
          if (!$paid_transaction_id || !hash_equals($paid_transaction_id, $saved_track_id)) {
            $this->reject_callback(
              $order,
              __('Callback تکراری زیبال رد شد: شناسه تراکنش پرداخت‌شده با trackId سفارش مطابقت ندارد.', 'woocommerce'),
              __('اطلاعات پرداخت معتبر نیست؛ لطفاً به مدیر سایت اطلاع دهید.', 'woocommerce')
            );
          }

          $Notice = wpautop(wptexturize($this->success_massage));
          $Notice = str_replace('{transaction_id}', $saved_track_id, $Notice);
          wc_add_notice($Notice, 'success');
          wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
          exit;
        }

        $stored_amount = (int) $order->get_meta(self::REQUESTED_AMOUNT_META_KEY, true);
        if ($stored_amount <= 0) {
          $order->update_meta_data(self::PAYMENT_STATE_META_KEY, 'review_required');
          $order->update_status('on-hold', __('مبلغ درخواست‌شده زیبال روی سفارش موجود نیست و پرداخت نیازمند بررسی مدیر است.', 'woocommerce'));
          $order->save();
          wc_add_notice(__('وضعیت پرداخت شما نیازمند بررسی است؛ لطفاً با مدیر سایت تماس بگیرید.', 'woocommerce'), 'notice');
          wp_redirect($this->get_return_url($order));
          exit;
        }
        $currency = $order->get_currency();
        $currency = apply_filters('WC_Gateway_Zibal_Currency', $currency, $order_id);

        $Amount = intval($order->get_total());
        $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
        if (
          strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
        )
          $Amount = $Amount * 10;
        else if (strtolower($currency) == strtolower('IRHT'))
          $Amount = $Amount * 10000;
        else if (strtolower($currency) == strtolower('IRHR'))
          $Amount = $Amount * 1000;
        else if (strtolower($currency) == strtolower('IRR'))
          $Amount = $Amount;

        $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency);

        if ((int) $Amount !== $stored_amount) {
          $this->reject_callback(
            $order,
            sprintf(
              __('Callback زیبال رد شد: مبلغ فعلی سفارش (%1$d) با مبلغ زمان ایجاد تراکنش (%2$d) مطابقت ندارد.', 'woocommerce'),
              (int) $Amount,
              $stored_amount
            ),
            __('مبلغ پرداخت با سفارش مطابقت ندارد؛ لطفاً به مدیر سایت اطلاع دهید.', 'woocommerce')
          );
        }

        $Sandbox = $this->sandbox;
        $apiID = $Sandbox == "yes" ? 'zibal' : $this->pin;

        if (!$order->is_paid()) {
          if ($success == '1') {

            if (!$this->acquire_verification_lock($order_id)) {
              wc_add_notice(__('پرداخت شما در حال بررسی است؛ لطفاً چند لحظه صبر کنید.', 'woocommerce'), 'notice');
              wp_redirect($this->get_return_url($order));
              exit;
            }

            $result = $this->api_request_with_fallback(
              'verify',
              [
                'merchant' => $apiID,
                'trackId' => $trackId
              ],
              $verify_base_url,
              $verify_raw_response
            );

            if (is_wp_error($result)) {
              $Status = 'failed';
              $Fault = $result->get_error_code();
              $Message = $result->get_error_message();
              $Customer_Message = __('خطا در اتصال به زیبال، لطفاً به مدیر سایت اطلاع دهید.', 'woocommerce');
            } elseif (isset($result->result, $result->amount) && $result->result == "100" && $result->amount == $Amount) {
              $Status = 'completed';
              $Transaction_ID = $trackId;
              $verify_cardnum = $card_number;
              $verify_tracking = $tracking_number;
              $Fault = '';
              $Message = '';
            } elseif (isset($result->result) && $result->result == "100") {
              $Status = 'failed';
              $Fault = 'amount_mismatch';
              $Message = sprintf(
                'مغایرت مبلغ پرداخت: مبلغ اعلامی زیبال %1$s و مبلغ مورد انتظار سفارش %2$s است.',
                isset($result->amount) ? (string) $result->amount : 'نامشخص',
                (string) $Amount
              );
              $Customer_Message = __('مبلغ پرداخت با سفارش مطابقت ندارد؛ لطفاً به مدیر سایت اطلاع دهید.', 'woocommerce');
              $verify_raw_response = '';
            } elseif (isset($result->result) && $result->result == "201") {

              $Message = isset($result->message) ? (string) $result->message : 'این تراکنش قبلا تایید شده است';
              $order->update_meta_data(self::PAYMENT_STATE_META_KEY, 'review_required');
              $order->update_status(
                'on-hold',
                sprintf(
                  __('زیبال نتیجه 201 برگرداند، اما سفارش در ووکامرس پرداخت‌شده نیست. trackId: %s. پاسخ: %s', 'woocommerce'),
                  esc_html($saved_track_id),
                  esc_html($verify_raw_response)
                )
              );
              $order->save();
              $this->release_verification_lock($order_id);
              wc_add_notice(__('پرداخت شما توسط زیبال قبلاً تأیید شده و اکنون نیازمند بررسی مدیر سایت است.', 'woocommerce'), 'notice');
              wp_redirect($this->get_return_url($order));
              exit;
            } else {
              $Status = 'failed';
              $Fault = isset($result->result) ? $result->result : 'invalid_response';
              $Message = isset($result->message) ? (string) $result->message : 'تراکنش ناموفق بود- کد خطا : ' . $Fault;
              $Customer_Message = __('پرداخت ناموفق بود، لطفاً دوباره تلاش کنید.', 'woocommerce');
            }
          } else {
            $Status = 'failed';
            $Fault = 'payment_cancelled';
            $Message = 'پرداخت توسط کاربر لغو شد.';
            $Customer_Message = __('پرداخت توسط شما لغو شد.', 'woocommerce');
          }

          if ($Status == 'completed' && isset($Transaction_ID) && $Transaction_ID != 0) {
            $action = $this->author;
            do_action('WC_Gateway_Payment_Actions', $action);
            $order->update_meta_data('_card_number', $verify_cardnum);
            $order->update_meta_data('_tracking_number', $verify_tracking);
            $order->update_meta_data(self::PAYMENT_STATE_META_KEY, 'verified');

            $order->payment_complete($Transaction_ID);
            $order->save();
            $woocommerce->cart->empty_cart();

            $Note = sprintf(__('کد رهگیری : %s', 'woocommerce'), $Transaction_ID);
            $Note .= sprintf(__('<br/>شماره کارت پرداخت کننده : %s', 'woocommerce'), $verify_cardnum);
            $Note .= sprintf(__('<br/>شماره تراکنش : %s', 'woocommerce'), $verify_tracking);
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
            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
            exit;
          } else {

            $action = $this->author;
            do_action('WC_Gateway_Payment_Actions', $action);
            $tr_id = ($Transaction_ID && $Transaction_ID != 0) ? ('<br/>کد تراکنش : ' . $Transaction_ID) : '';

            $Note = sprintf(__('خطا در هنگام بازگشت از زیبال : %s %s', 'woocommerce'), esc_html($Message), $tr_id);
            if ($verify_raw_response) {
              $Note .= '<br/>' . sprintf(__('پاسخ زیبال: %s', 'woocommerce'), esc_html($verify_raw_response));
            }

            $Note = apply_filters('WC_Gateway_Zibal_Return_from_Gateway_Failed_Note', $Note, $order_id, $Transaction_ID, $Fault);
            if ($Note)
              $order->add_order_note($Note);

            $Notice = $Customer_Message;
            $Notice = apply_filters('WC_Gateway_Zibal_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $Transaction_ID, $Fault);
            if ($Notice)
              wc_add_notice($Notice, 'error');

            do_action('WC_Gateway_Zibal_Return_from_Gateway_Failed', $order_id, $Transaction_ID, $Fault);

            if ($success == '1') {
              $this->release_verification_lock($order_id);
            }
            wp_redirect(wc_get_checkout_url());
            exit;
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
          wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
          exit;
        }
      } else {

        $Fault = __('شماره سفارش وجود ندارد .', 'woocommerce');
        $Notice = __('اطلاعات سفارش معتبر نیست؛ لطفاً دوباره تلاش کنید.', 'woocommerce');
        $Notice = apply_filters('WC_Gateway_Zibal_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault);
        if ($Notice)
          wc_add_notice($Notice, 'error');

        do_action('WC_Gateway_Zibal_Return_from_Gateway_No_Order_ID', $order_id, $Transaction_ID, $Fault);

        wp_redirect(wc_get_checkout_url());
        exit;
      }
    }

    public function add_sandbox_notice_to_admin_bar($wp_admin_bar)
    {
      if (!current_user_can('manage_options')) {
        return;
      }
      $message = sprintf(
        __('درگاه زیبال در حالت پرداخت آزمایشی فعال است. پرداخت‌های واقعی انجام نخواهند شد. برای غیرفعال کردن این حالت، به تنظیمات درگاه <a href="%s">اینجا</a> مراجعه کنید.'),
        admin_url('admin.php?page=wc-settings&tab=checkout&section=WC_Gateway_Zibal')
      );
      echo '<div class="notice notice-error is-dismissible">';
      echo '<p>' . $message . '</p>';
      echo '</div>';
    }

    public function admin_notice_missing_pin()
    {
      $pin = $this->settings['pin'];
      if (empty($pin) && 'yes' === $this->settings['enabled']) {
        $message = sprintf(
          __('مرچنت کد درگاه زیبال خالی است. برای تکمیل مورد مربوطه به تنظیمات درگاه <a href="%s">اینجا</a> مراجعه کنید.', ),
          admin_url('admin.php?page=wc-settings&tab=checkout&section=WC_Gateway_Zibal')
        );
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p>' . $message . '</p>';
        echo '</div>';
      }
    }

  }
}
