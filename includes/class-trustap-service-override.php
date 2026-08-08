<?php
use Trustap\PaymentGateway\Helper\Template;

use Trustap\PaymentGateway\Helper\Validator;

use Automattic\WooCommerce\Utilities\OrderUtil;

use Trustap\PaymentGateway\Enumerators\Uri as UriEnumerator;



class Service_Override
{

    private $wc_payment_gateway;

    private $helper;

    public $controller;
    public $namespace;

    public $trustap_api_url;
    public $trustap_api;

    public $seller_id;


    public $username;

    public $password;



    public function __construct($gateway, $controller)
    {
        // Initialize properties from AbstractController's constructor
        $this->controller = $controller;
        $this->helper = new WCFM_Trustap_Helper();
        $this->trustap_api = new WCFM_Trustap_API();

        $this->namespace = 'trustap_payment_gateway';

        $this->trustap_api_url = UriEnumerator::API_URL();

        $this->wc_payment_gateway = $gateway; // Assuming this is the main gateway class

        $this->register_custom_order_statuses();
        add_filter('wc_order_statuses', array($this, 'filter_wc_order_statuses'), 20);

        remove_all_actions('woocommerce_api_trustap_webhook');

        // Add your child webhook handler

        //add_action('woocommerce_api_trustap_webhook_raju', array($this, 'child_trustap_webhook'));

        add_action('woocommerce_api_trustap_webhook_raju', array($this, 'child_trustap_custom_webhook'));

        // add_action('add_meta_boxes', [$this, 't4e_add_confirm_handover_meta_box'], 110000, 2);
        add_action('before_wcfm_orders_details', [$this, 't4e_before_wcfm_orders_details']);
        add_action('add_meta_boxes', [$this, 'remove_parent_trustap_meta_boxes'], 999);
        // do_action('before_wcfm_orders_details', $order_id);
    }

    public function remove_parent_trustap_meta_boxes()
    {
        remove_meta_box('trustap-shipping-meta-box', 'shop_order', 'side');
        remove_meta_box('trustap-confirm-handover-meta-box', 'shop_order', 'side');
        remove_meta_box('trustap-accept-complaint-meta-box', 'shop_order', 'side');
        
        // Also for HPOS
        remove_meta_box('trustap-shipping-meta-box', 'woocommerce_page_wc-orders', 'side');
        remove_meta_box('trustap-confirm-handover-meta-box', 'woocommerce_page_wc-orders', 'side');
        remove_meta_box('trustap-accept-complaint-meta-box', 'woocommerce_page_wc-orders', 'side');
    }

    public function register_custom_order_statuses()
    {
        $order_statuses = [
            'wc-complained-buyer' => [
                'label'                     => 'Complained by Buyer',
                'public'                    => true,
                'show_in_admin_status_list' => true,
                'show_in_admin_all_list'    => true,
                'exclude_from_search'       => false,
                'label_count'               => _n_noop('Complained by Buyer <span class="count">(%s)</span>', 'Complained by Buyer <span class="count">(%s)</span>', 't4e-pg-trustap'),
            ],
            'wc-complaint-accepted' => [
                'label'                     => 'Complaint Accepted',
                'public'                    => true,
                'show_in_admin_status_list' => true,
                'show_in_admin_all_list'    => true,
                'exclude_from_search'       => false,
                'label_count'               => _n_noop('Complaint Accepted <span class="count">(%s)</span>', 'Complaint Accepted <span class="count">(%s)</span>', 't4e-pg-trustap'),
            ],
            'wc-refunded-buyer' => [
                'label'                     => 'Refunded to Buyer',
                'public'                    => true,
                'show_in_admin_status_list' => true,
                'show_in_admin_all_list'    => true,
                'exclude_from_search'       => false,
                'label_count'               => _n_noop('Refunded to Buyer <span class="count">(%s)</span>', 'Refunded to Buyer <span class="count">(%s)</span>', 't4e-pg-trustap'),
            ],
        ];

        foreach ($order_statuses as $key => $status) {
            register_post_status($key, $status);
        }
    }

    public function filter_wc_order_statuses($order_statuses)
    {
        // Remove parent Trustap custom statuses from the list
        unset($order_statuses['wc-shipped']);
        unset($order_statuses['wc-handoverpending']);
        unset($order_statuses['wc-handoverconfirmed']);
        unset($order_statuses['wc-complainedbybuyer']);
        unset($order_statuses['wc-complaintaccepted']);
        unset($order_statuses['wc-refundedtobuyer']);

        // Add our new custom statuses
        $order_statuses['wc-complained-buyer'] = __('Complained by Buyer', 't4e-pg-trustap');
        $order_statuses['wc-complaint-accepted'] = __('Complaint Accepted', 't4e-pg-trustap');
        $order_statuses['wc-refunded-buyer'] = __('Refunded to Buyer', 't4e-pg-trustap');

        return $order_statuses;
    }

    public function t4e_before_wcfm_orders_details($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== 'trustap') {
            return;
        }

        // Get the currently stored transaction details
        $transaction_details = $order->get_meta('_trustap_transaction_details');

        // Define terminal statuses
        $terminal_statuses = ['completed', 'cancelled', 'refunded', "Funds Released"];

        // If the transaction is already in a terminal state, do nothing.
        if (isset($transaction_details['status']) && in_array($transaction_details['status'], $terminal_statuses)) {
            return;
        }

        // If the status is not terminal, then proceed with the update.
        $this->save_trustap_transaction_details_on_payment_complete($order_id);
    }

    public function t4e_add_confirm_handover_meta_box($post_type, $post)
    {

        // global $post;
        $order = wc_get_order($post->ID);

        $logger = wc_get_logger();
        $logger->info('t4e_add_confirm_handover_meta_box', ['source' => 'service-override']);

        if (!$order) {
            return;
        }
        if (strpos($order->get_meta('model'), "p2p/") === false) {
            return;
        }
        if ($order->get_payment_method() !== 'trustap') {
            return;
        }
        
        // Handover pending is now mapped to processing
        if (!$order->has_status('processing')) {
            return;
        }

        add_meta_box(
            't4e-trustap-confirm-handover-meta-box',
            'Trustap Handover',
            [$this, 't4e_confirm_handover_meta_box'],
            'page',
            'side',
            'high'
        );
    }


    public function t4e_confirm_handover_meta_box()
    {
        $template = new Template();
        $args = [
            'icon' => TRUSTAP_IMAGE_URL . "handshake-simple-solid.svg",
            'confirm_handover_url' => UriEnumerator::CONFIRM_HANDOVER_URL(),
            'nonce' => wp_create_nonce('wp_rest')
        ];
        echo $template->render('settings', 'ConfirmHandover', $args);
    }

    public function child_trustap_webhook()
    {

        $logger = wc_get_logger();

        // $logger->info('Child webhook triggered ✅', ['source' => 'trustap-child']);

        $state = isset($_GET['state']) ? $_GET['state'] : '';

        //  $logger->info('State parameter: ' . $state, ['source' => 'trustap-child']);

        // Here you can add custom service logic if needed

        wp_send_json_success(['message' => 'RajuHandled by child plugin----tt-']);

    }

    public function child_trustap_custom_webhook()
    {

        $logger = wc_get_logger();

        // $logger->info('Child webhook triggered ✅', ['source' => 'trustap-child']);



        if ($_SERVER['REQUEST_METHOD'] === 'GET') {

            //  $logger->info('Handling GET request for P2P webhook.', ['source' => 'trustap-child']);

            $this->p2p_webhook_get();

        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

            //  $logger->info('Handling POST request for P2P webhook.', ['source' => 'trustap-child']);

            $this->p2p_webhook_post();

        }

        die();

    }

    private function p2p_webhook_get()
    {

        try {

            if (!isset($_GET['tx_id'])) {
                $logger = wc_get_logger();
                $logger->error('Missing tx_id in GET request', ['source' => 'trustap-child']);
                exit();
            }

            $transaction_id = Validator::sanitize_string($_GET['tx_id']);

            $order = $this->get_order_by_transaction_id($transaction_id);

            if (!$order) {
                $logger = wc_get_logger();
                $logger->error("Order not found for transaction ID: {$transaction_id}", ['source' => 'trustap-child']);
                exit();
            }

            $transaction = $this->get_transaction('p2p', $transaction_id);

            // transaction save meta 
            $this->save_trustap_transaction_details_on_payment_complete($order->get_id());

            $this->synchronize_commission($order->get_id(), $transaction);

            if (!isset($transaction['deposit_paid']) || !$transaction['deposit_paid']) {

                $logger = wc_get_logger();

                $logger->error("Deposit not paid for transaction ID: {$transaction_id}", ['source' => 'trustap-child']);

                wc_add_notice(__('Please try again.', 'trustap-payment-gateway'), 'error');

                exit();

            }
            $order->payment_complete();
            $order->add_order_note(__('Paid and confirmed (P2P via GET)', 'trustap-payment-gateway'), true);

            if (function_exists('WC') && is_a(WC()->cart, 'WC_Cart')) {
                WC()->cart->empty_cart();
            }

            $this->accept_deposit($transaction_id, $order);
            $this->handle_handover_confirmation($transaction_id, $order);
            return wp_redirect($this->wc_payment_gateway->get_return_url($order));

        } catch (Exception $e) {

            $logger = wc_get_logger();

            $logger->error('p2p_webhook_get error: ' . $e->getMessage(), ['source' => 'trustap-child']);

            wc_add_notice(__('An error occurred. Please try again later.', 'trustap-payment-gateway'), 'error');

        }

    }

    private function p2p_webhook_post()
    {
        try {

            $request_body = file_get_contents('php://input');

            $body_webhook = json_decode($request_body);



            if (!$body_webhook || !isset($body_webhook->code) || strpos($body_webhook->code, 'p2p_tx') === false) {

                wc_add_notice(__('Invalid webhook data.', 'trustap-payment-gateway'), 'error');

                exit();

            }



            $logger = wc_get_logger();

            //   $logger->info("Received P2P POST webhook with code: {$body_webhook->code}", ['source' => 'trustap-child']);



            $transaction_id = isset($body_webhook->target_id) ? $body_webhook->target_id : null;

            $order = $transaction_id ? $this->get_order_by_transaction_id($transaction_id) : null;



            if ($body_webhook->code === "p2p_tx.deposit_paid") {

                if ($order && $this->is_deposit_paid($transaction_id)) {

                    $order->payment_complete();

                    $order->add_order_note(__('Paid and confirmed (P2P via POST)', 'trustap-payment-gateway'), true);

                    $this->accept_deposit($transaction_id, $order);

                    $this->handle_handover_confirmation($transaction_id, $order);

                }

            } elseif ($body_webhook->code === "p2p_tx.cancelled") {

                if ($order) {

                    $this->cancel_order($transaction_id);

                }

            } elseif (

                $body_webhook->code === "p2p_tx.buyer_handover_confirmed" ||

                $body_webhook->code === "p2p_tx.seller_handover_confirmed"

            ) {

                if ($order) {
                    // handover_confirmed = completed
                    $order->update_status('completed');

                }

            } elseif ($body_webhook->code === "p2p_tx.complained") {

                if ($order) {

                    $order->update_status('complained-buyer');

                    $order->add_order_note(__('Complaint raised by the buyer.', 'trustap-payment-gateway'));

                }

            } elseif ($body_webhook->code === "p2p_tx.deposit_refunded") {

                if ($order) {

                    $order->update_status('refunded-buyer');

                    $order->add_order_note(__('Funds were refunded to the buyer.', 'trustap-payment-gateway'));

                }

            }

            status_header(200);

        } catch (Exception $e) {

            $logger = wc_get_logger();

            $logger->error('p2p_webhook_post error: ' . $e->getMessage(), ['source' => 'trustap-child']);

            wc_add_notice(__('An error occurred. Please try again later.', 'trustap-payment-gateway'), 'error');

        }
    }

    private function is_deposit_paid($transaction_id)
    {

        $transaction = $this->get_transaction('p2p', $transaction_id);

        if (!$transaction || !isset($transaction['deposit_paid']) || !$transaction['deposit_paid']) {

            wc_add_notice(__('Please try again.', 'trustap-payment-gateway'), 'error');

            return false;

        }

        return true;
    }

    public function get_transaction($type, $transaction_id)
    {
        $prefix = ($type === 'p2p') ? 'p2p' : '';
        try {
            $response = $this->controller->get_request(
                "{$prefix}/transactions/{$transaction_id}",
                ''
            );

        } catch (Exception $error) {
            wc_add_notice($error);
            return false;
        }

        $body = json_decode($response['body'], true);
        return $body;
    }

    private function accept_deposit($transaction_id, $order)
    {
        $logger = wc_get_logger();
        try {
            $seller_trustap_id = $this->helper->get_trustap_seller_id($order->get_items());

            if (is_wp_error($seller_trustap_id)) {
                throw new Exception($seller_trustap_id->get_error_message());
            }

            if (empty($seller_trustap_id)) {
                throw new Exception('Seller Trustap ID not found for order #' . $order->get_id());
            }

            $result = $this->controller->post_request(
                "p2p/transactions/{$transaction_id}/accept_deposit",
                $seller_trustap_id,
                '',
            );

        } catch (Exception $exception) {
            $logger->error('accept_deposit error: ' . $exception->getMessage(), ['source' => 'trustap-child']);
            $order->add_order_note(__('Accept deposit manually.', 'trustap-payment-gateway'), false);
        }
    }

    private function handle_handover_confirmation($transaction_id, $order)
    {

        if (isset($this->wc_payment_gateway->confirm_handover) && $this->wc_payment_gateway->confirm_handover === 'manually') {
            // handover_pending = processing
            $order->update_status('processing');

        } else {

            $this->confirm_handover($transaction_id, $order);

        }

    }

    public function confirm_handover($transaction_id, $order)
    {
        $seller_trustap_id = $this->helper->get_trustap_seller_id($order->get_items());

        if (is_wp_error($seller_trustap_id)) {
            return $seller_trustap_id;
        }

        if (empty($seller_trustap_id)) {
            return new WP_Error(
                'no_seller_trustap_id',
                'Seller Trustap ID not found for order #' . $order->get_id(),
                array('status' => 400)
            );
        }

        try {

            $this->controller->post_request(
                "p2p/transactions/{$transaction_id}/confirm_handover",
                $seller_trustap_id,
                []
            );

        } catch (Exception $exception) {
            $order->add_order_note(
                __('Confirm handover manually.', 'trustap-payment-gateway'),
                false
            );
            return wp_redirect($this->wc_payment_gateway->get_return_url($order));

        }

    }
    private function cancel_order($transaction_id)
    {

        $order = $this->get_order_by_transaction_id($transaction_id);

        if ($order) {

            $order->update_status('cancelled');

        }

    }

    public static function get_order_by_transaction_id($transaction_id)
    {

        global $wpdb;

        $order_id = null;

        if (OrderUtil::custom_orders_table_usage_is_enabled()) {

            $order_id = $wpdb->get_var(

                $wpdb->prepare(

                    "

            SELECT order_id

            FROM {$wpdb->prefix}wc_orders_meta

            WHERE meta_key = 'trustap_transaction_ID'

            AND meta_value = %s

            ",

                    $transaction_id

                )

            );

        } else {

            $order_id = $wpdb->get_var(

                $wpdb->prepare(

                    "

                    SELECT DISTINCT ID FROM

                    $wpdb->posts as posts

                    LEFT JOIN $wpdb->postmeta as meta

                        ON posts.ID = meta.post_id

                        WHERE meta.meta_value = %s

                        AND meta.meta_key = %s

                ",

                    $transaction_id,

                    'trustap_transaction_ID'

                )

            );

        }

        if (empty($order_id)) {

            return false;

        }

        return wc_get_order($order_id);

    }

    public function save_trustap_transaction_details_on_payment_complete($order_id)
    {
        $logger = wc_get_logger();
        $context = ['source' => 't4e-pg-trustap-save-details'];
        $logger->info('Attempting to save Trustap transaction details for Order ID: ' . $order_id, $context);

        $order = wc_get_order($order_id);
        if (!$order) {
            //	$logger->error('Order not found for ID: ' . $order_id, $context);
            return;
        }

        $payment_method = $order->get_payment_method();
        //	$logger->info('Order ' . $order_id . ' payment method: ' . $payment_method, $context);

        if ($payment_method !== 'trustap') {
            //	$logger->info('Order ' . $order_id . ' is not a Trustap payment. Skipping.', $context);
            return;
        }

        $transaction_id = $order->get_meta('trustap_transaction_ID');
        if (empty($transaction_id)) {
            //	$logger->error('Order ' . $order_id . ' has no trustap_transaction_ID.', $context);
            return;
        }
        //	$logger->info('Order ' . $order_id . ' - trustap_transaction_ID: ' . $transaction_id, $context);

        $model = $order->get_meta('model');
        $type = (strpos($model, 'p2p') !== false) ? 'p2p' : '';
        //$logger->info('Order ' . $order_id . ' - Model: ' . $model . ' | Type: ' . $type, $context);

        $transaction_details = $this->get_transaction($type, $transaction_id);
        //	$logger->info('Order ' . $order_id . ' - API Response from get_transaction: ' . print_r($transaction_details, true), $context);

        if ($transaction_details && !is_wp_error($transaction_details)) {
            $order->update_meta_data('_trustap_transaction_details', $transaction_details);
            $order->save();
            //		$logger->info('Order ' . $order_id . ' - Trustap transaction details successfully saved to order meta.', $context);
        } else {
            //		$logger->error('Order ' . $order_id . ' - Failed to get valid Trustap transaction details from API.', $context);
        }

    }

    private function synchronize_commission($order_id, $transaction_details)
    {
        global $wpdb, $WCFMmp;

        amaturlog('Synchronizing commission for order ID: ' . $order_id, 'debug', source: basename(__FILE__) . ':' . __LINE__);

        if (!$WCFMmp) {
            amaturlog('WCFMmp not available.', 'error', source: basename(__FILE__) . ':' . __LINE__);
            return;
        }

        $commission_ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->prefix}wcfm_marketplace_orders WHERE order_id = %d", $order_id));

        if (empty($commission_ids)) {
            amaturlog('No commission IDs found for order ID: ' . $order_id, 'info', source: basename(__FILE__) . ':' . __LINE__);
            return;
        }
        amaturlog('Commission IDs found: ' . print_r($commission_ids, true), 'debug', source: basename(__FILE__) . ':' . __LINE__);

        if ($transaction_details && isset($transaction_details['deposit_pricing']['price']) && isset($transaction_details['deposit_pricing']['charge_seller'])) {
            $payout_amount = ($transaction_details['deposit_pricing']['price'] - $transaction_details['deposit_pricing']['charge_seller']) / 100;
            amaturlog('Calculated payout amount: ' . $payout_amount, 'debug', source: basename(__FILE__) . ':' . __LINE__);

            foreach ($commission_ids as $commission_id) {
                $result = $wpdb->update(
                    "{$wpdb->prefix}wcfm_marketplace_orders",
                    array(
                        'total_commission' => $payout_amount,
                        'commission_amount' => $payout_amount
                    ),
                    array('ID' => $commission_id),
                    array('%f', '%f'),
                    array('%d')
                );

                amaturlog('Commission update result for commission ID ' . $commission_id . ': ' . $result, 'debug', source: basename(__FILE__) . ':' . __LINE__);

                if (property_exists($WCFMmp, 'wcfmmp_commission')) {
                    if (isset($transaction_details['deposit_pricing']['charge'])) {
                        $WCFMmp->wcfmmp_commission->wcfmmp_update_commission_meta($commission_id, '_trustap_buyer_fee', $transaction_details['deposit_pricing']['charge'] / 100);
                    }
                    if (isset($transaction_details['deposit_pricing']['charge_seller'])) {
                        $WCFMmp->wcfmmp_commission->wcfmmp_update_commission_meta($commission_id, '_trustap_seller_fee', $transaction_details['deposit_pricing']['charge_seller'] / 100);
                    }
                    if (isset($transaction_details['deposit_pricing']['charge_international_payment'])) {
                        $WCFMmp->wcfmmp_commission->wcfmmp_update_commission_meta($commission_id, '_trustap_international_payment_fee', $transaction_details['deposit_pricing']['charge_international_payment'] / 100);
                    }
                    if (isset($transaction_details['deposit_pricing']['price'])) {
                        $WCFMmp->wcfmmp_commission->wcfmmp_update_commission_meta($commission_id, '_trustap_amount_paid', $transaction_details['deposit_pricing']['price'] / 100);
                    }
                }
                $log_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wcfm_marketplace_orders WHERE ID = %d", $commission_id), ARRAY_A);
                amaturlog($log_data, 'wcfm_marketplace_orders_updated_data', source: basename(__FILE__) . ':' . __LINE__);
            }
        } else {
            amaturlog('No payout amount found in transaction details for order ID: ' . $order_id, 'warning', source: basename(__FILE__) . ':' . __LINE__);
            amaturlog($transaction_details, 'debug', source: basename(__FILE__) . ':' . __LINE__);
        }
    }
}

