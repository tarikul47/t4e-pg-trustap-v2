<?php
use Trustap\PaymentGateway\Controller\AbstractController;

class T4e_Pg_Trustap_Core
{

    protected $plugin_name;
    protected $version;
    protected $trustap_api;
    protected $helper;
    protected $controller;

    public function __construct($plugin_name, $version, $trustap_api)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->helper = new WCFM_Trustap_Helper();
        $this->trustap_api = $trustap_api;
        $this->controller = new \Trustap\PaymentGateway\Controller\T4e_Pg_Trustap_Controller('trustap/v1');
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('t4e_pg_trustap_automated_claim_cron', array($this, 't4e_automated_claim_check'));
        add_action('woocommerce_created_customer', array($this, 't4e_create_guest_account_on_registration'), 10, 1);
        add_action('wcfm_vendor_registration_completed', array($this, 't4e_create_guest_account_on_registration'), 10, 1);
    }

    /**
     * Get the current Trustap environment (test or live).
     *
     * @return string 'test' or 'live'
     */
    public static function get_environment()
    {
        $trustap_settings = get_option('woocommerce_trustap_settings', array());
        return (isset($trustap_settings['testmode']) && $trustap_settings['testmode'] === 'yes') ? 'test' : 'live';
    }

    public function register_routes()
    {
        register_rest_route('t4e-pg-trustap/v1', '/confirm-handover', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_confirm_handover_request'),
            'permission_callback' => '__return_true' // Adjust permissions as needed
        ));

        register_rest_route('t4e-pg-trustap/v1', '/accept-complaint', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_accept_complaint_request'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('t4e-pg-trustap/v1', '/claim-transaction', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_claim_transaction_request'),
            'permission_callback' => '__return_true'
        ));
    }

    public function handle_accept_complaint_request($request)
    {
        $order_id = $request->get_param('orderId');
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_Error(
                'invalid_order',
                'Order not found.',
                array('status' => 404)
            );
        }

        $result = $this->accept_complaint($order);

        if (is_wp_error($result)) {
            return $result;
        }

        $order->update_status('complaint-accepted');

        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => 'Complaint accepted successfully.'
            ),
            200
        );
    }

    public function accept_complaint($order)
    {
        $transaction_id = $order->get_meta('trustap_transaction_ID');
        $seller_trustap_id = $this->helper->get_trustap_seller_id($order->get_items());
        $model = $order->get_meta('model');
        $tx_type = (strpos($model, 'p2p') !== false) ? 'p2p/' : '';

        if (is_wp_error($seller_trustap_id)) {
            return $seller_trustap_id;
        }

        $raw_response = $this->controller->post_request(
            "{$tx_type}transactions/{$transaction_id}/accept_complaint",
            $seller_trustap_id,
            []
        );

        $response_status = $raw_response['response']['code'];
        $response_body = json_decode($raw_response['body'], true);

        if ($response_status != 200) {
            return new WP_Error(
                'accept_complaint_failed',
                $response_body['message'] ?? 'Failed to accept complaint.',
                array('status' => $response_status)
            );
        }

        // Update meta to prevent redundant sync calls
        $transaction_details = $order->get_meta('_trustap_transaction_details');
        if (!is_array($transaction_details)) {
            $transaction_details = [];
        }
        $transaction_details['status'] = 'complaint_accepted';
        $order->update_meta_data('_trustap_transaction_details', $transaction_details);
        $order->save();

        return true;
    }

    public function handle_confirm_handover_request($request)
    {
        $order_id = $request->get_param('orderId');
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_Error(
                'invalid_order',
                'Order not found.',
                array('status' => 404)
            );
        }

        $result = $this->confirm_handover($order);

        if (is_wp_error($result)) {
            return $result;
        }

        $order->update_status('completed');

        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => 'Handover confirmed successfully.'
            ),
            200
        );
    }

    public function confirm_handover($order)
    {
        $transaction_id = $order->get_meta('trustap_transaction_ID');
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

        $raw_response = $this->controller->post_request(
            "p2p/transactions/{$transaction_id}/confirm_handover",
            $seller_trustap_id,
            []
        );

        $response_status = $raw_response['response']['code'];
        $response_body = json_decode($raw_response['body'], true);

        if ($response_status != 200) {
            return new WP_Error(
                'handover_failed',
                $response_body['message'] ?? 'Handover confirmation failed.',
                array('status' => $response_status)
            );
        }

        // Update meta to prevent redundant sync calls
        $transaction_details = $order->get_meta('_trustap_transaction_details');
        if (!is_array($transaction_details)) {
            $transaction_details = [];
        }
        $transaction_details['status'] = 'completed';
        $order->update_meta_data('_trustap_transaction_details', $transaction_details);
        $order->save();

        return true;
    }

    public function handle_claim_transaction_request($request)
    {
        $order_id = $request->get_param('orderId');
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_Error('invalid_order', 'Order not found.', array('status' => 404));
        }

        $result = $this->claim_transaction($order);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(
            array('success' => true, 'message' => 'Transaction claimed successfully.'),
            200
        );
    }

    /**
     * Claims a transaction for the seller using their full Trustap account.
     */
    public function claim_transaction($order)
    {
        $transaction_id = $order->get_meta('trustap_transaction_ID');
        $vendor_id = $this->helper->get_vendor_id_from_order($order);
        $full_user_id = Trustap_User_Manager::get_full_id($vendor_id);

        if (!$transaction_id) {
            return new WP_Error('missing_tx_id', 'Trustap Transaction ID missing.');
        }

        if (!$full_user_id) {
            return new WP_Error('no_full_account', 'Vendor has no linked full Trustap account. Connection required to claim funds.');
        }

        // Check if claimable (complaint period over)
        $status_check = $this->get_transaction_status($transaction_id);
        if (is_wp_error($status_check)) {
            return $status_check;
        }

        if (!$this->is_claimable($status_check)) {
            return new WP_Error('not_claimable', 'Transaction is not yet claimable. The complaint period may still be active.');
        }

        $raw_response = $this->controller->post_request(
            "p2p/transactions/{$transaction_id}/claim_for_seller",
            $full_user_id,
            []
        );

        $response_status = $raw_response['response']['code'];
        $response_body = json_decode($raw_response['body'], true);

        if ($response_status != 200) {
            return new WP_Error(
                'claim_failed',
                $response_body['message'] ?? 'Failed to claim transaction.',
                array('status' => $response_status)
            );
        }

        // Verification: Ensure seller_is_guest is now false
        if (isset($response_body['seller_is_guest']) && $response_body['seller_is_guest'] === true) {
            return new WP_Error('verification_failed', 'Claim succeeded but seller is still marked as guest.');
        }

        $transaction_details = $order->get_meta('_trustap_transaction_details');
        if (!is_array($transaction_details)) {
            $transaction_details = [];
        }
        $transaction_details['claim_status'] = 'claimed';
        $transaction_details['status'] = 'funds_released';
        $order->update_meta_data('_trustap_transaction_details', $transaction_details);
        $order->save();

        return true;
    }

    public function get_transaction_status($transaction_id)
    {
        $raw_response = $this->controller->get_request("p2p/transactions/{$transaction_id}", []);
        $response_status = $raw_response['response']['code'];
        $response_body = json_decode($raw_response['body'], true);

        if ($response_status != 200) {
            return new WP_Error('status_check_failed', $response_body['message'] ?? 'Failed to fetch status.');
        }

        return $response_body['status'] ?? '';
    }

    private function is_claimable($status)
    {
        $claimable_statuses = ['completed', 'funds_released', 'buyer_handover_confirmed', 'seller_handover_confirmed'];
        return in_array($status, $claimable_statuses);
    }

    public function t4e_sync_handover_on_status_change($order_id)
    {
        static $syncing = [];
        if (isset($syncing[$order_id])) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== 'trustap') {
            return;
        }

        $syncing[$order_id] = true;

        $transaction_id = $order->get_meta('trustap_transaction_ID');
        if (empty($transaction_id)) {
            return;
        }

        $transaction_details = $order->get_meta('_trustap_transaction_details');
        $terminal_statuses = ['completed', 'buyer_handover_confirmed', 'seller_handover_confirmed', 'Funds Released'];
        if (isset($transaction_details['status']) && in_array($transaction_details['status'], $terminal_statuses)) {
            return;
        }

        $result = $this->confirm_handover($order);

        if (is_wp_error($result)) {
            $order->add_order_note(__('Trustap Handover Sync Error: ', 't4e-pg-trustap') . $result->get_error_message());
        } else {
            $order->add_order_note(__('Trustap Handover Sync: Handover confirmed successfully.', 't4e-pg-trustap'));
        }
    }

    public function t4e_sync_complaint_acceptance_on_status_change($order_id)
    {
        static $syncing = [];
        if (isset($syncing[$order_id])) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== 'trustap') {
            return;
        }

        $syncing[$order_id] = true;

        $transaction_id = $order->get_meta('trustap_transaction_ID');
        if (empty($transaction_id)) {
            return;
        }

        $transaction_details = $order->get_meta('_trustap_transaction_details');
        $terminal_statuses = ['complaint_accepted', 'refunded', 'deposit_refunded'];
        if (isset($transaction_details['status']) && in_array($transaction_details['status'], $terminal_statuses)) {
            return;
        }

        $result = $this->accept_complaint($order);

        if (is_wp_error($result)) {
            $order->add_order_note(__('Trustap Complaint Sync Error: ', 't4e-pg-trustap') . $result->get_error_message());
        } else {
            $order->add_order_note(__('Trustap Complaint Sync: Complaint accepted successfully.', 't4e-pg-trustap'));
        }
    }

    public function t4e_create_guest_account_on_registration($customer_id)
    {
        $result = $this->t4e_create_guest_account($customer_id);
        if (is_wp_error($result)) {
            amaturlog("Failed to create guest account on registration for User ID $customer_id: " . $result->get_error_message(), 'error', 'Trustap_Core');
        }
    }

    /**
     * Creates a Trustap guest account for a user.
     * This can be called during WP registration or WooCommerce customer creation.
     *
     * @param int $user_id The WordPress user ID.
     * @return string|WP_Error The Guest User ID or a WP_Error.
     */
    public function t4e_create_guest_account($user_id)
    {
        // Check if already exists
        $existing_id = Trustap_User_Manager::get_guest_id($user_id);
        if ($existing_id) {
            return $existing_id;
        }

        $user_data = get_userdata($user_id);
        if (!$user_data) {
            return new WP_Error('invalid_user', 'User data not found.');
        }

        $email = $user_data->user_email;
        $first_name = $user_data->first_name ?: $user_data->user_login;
        $last_name = $user_data->last_name ?: $user_data->user_login;
        $country = get_user_meta($user_id, 'billing_country', true) ?: 'IE';

        $body = array(
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'country_code' => $country,
            'tos_acceptance' => array(
                'unix_timestamp' => time(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            )
        );

        try {
            $response = $this->controller->post_request_no_user('guest_users', $body);
            $decoded_response = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($decoded_response['id'])) {
                $guest_id = $decoded_response['id'];
                Trustap_User_Manager::save_guest_id($user_id, $guest_id);
                return $guest_id;
            }
            return new WP_Error('api_failure', $decoded_response['message'] ?? 'Failed to create guest account.');
        } catch (\Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }

    /**
     * Automated check to claim transactions once the complaint period has expired.
     * This is intended to be called by a WP-Cron job.
     */
    public function t4e_automated_claim_check()

    {
        $args = array(
            'payment_method' => 'trustap',
            'status' => array('completed'), // Only check orders that were marked completed (handover confirmed)
            'limit' => -1,
        );
        $orders = wc_get_orders($args);

        if (empty($orders)) {
            return;
        }

        foreach ($orders as $order) {
            $transaction_details = $order->get_meta('_trustap_transaction_details');

            // Skip if already claimed
            if (isset($transaction_details['claim_status']) && $transaction_details['claim_status'] === 'claimed') {
                continue;
            }

            $transaction_id = $order->get_meta('trustap_transaction_ID');
            if (empty($transaction_id)) {
                continue;
            }

            // Check if claimable (complaint period over)
            $status_check = $this->get_transaction_status($transaction_id);
            if (is_wp_error($status_check)) {
                continue;
            }

            if (!$this->is_claimable($status_check)) {
                continue;
            }

            // It IS claimable. Now check for full account.
            $vendor_id = $this->helper->get_vendor_id_from_order($order);
            $full_user_id = Trustap_User_Manager::get_full_id($vendor_id);

            if (!$full_user_id) {
                // Notify vendor that funds are ready but they need to connect their account
                $this->notify_vendor_to_connect_account($vendor_id, $order);
                continue;
            }

            // Attempt to claim
            $result = $this->claim_transaction($order);

            if (is_wp_error($result)) {
                if ($result->get_error_code() === 'not_claimable') {
                    continue;
                }

                $order->add_order_note(__('Trustap Auto-Claim Error: ', 't4e-pg-trustap') . $result->get_error_message());
            } else {
                $order->add_order_note(__('Trustap Auto-Claim: Transaction claimed successfully.', 't4e-pg-trustap'));
            }
        }
    }

    /**
     * Notifies the vendor via email that their funds are ready to be claimed,
     * but they must first connect their full Trustap account.
     */
    private function notify_vendor_to_connect_account($vendor_id, $order)
    {
        $user = get_userdata($vendor_id);
        if (!$user) {
            return;
        }

        $email = $user->user_email;
        $subject = __('Funds Ready for Claim - Action Required', 't4e-pg-trustap');
        $message = sprintf(
            __('Hello %s, your funds for order #%d are now ready to be claimed. Please log in to your vendor dashboard and connect your full Trustap account to receive your payout.', 't4e-pg-trustap'),
            $user->display_name,
            $order->get_id()
        );

        wp_mail($email, $subject, $message);
        $order->add_order_note(__('Trustap Notification: Vendor notified to connect full account for claim.', 't4e-pg-trustap'));
    }

    /**
     * Static handler for WP-Cron event.
     */
    public static function t4e_cron_automated_claim_check()
    {
        if (!class_exists('WCFM_Trustap_API')) {
            return;
        }

        $core = new T4e_Pg_Trustap_Core(
            't4e-pg-trustap',
            defined('T4E_PG_TRUSTAP_VERSION') ? T4E_PG_TRUSTAP_VERSION : '1.0.0',
            new WCFM_Trustap_API()
        );

        $core->t4e_automated_claim_check();
    }

    /**
     * Fetches the current Trustap balance for a vendor.
     * Uses transients for caching.
     *
     * @param int $vendor_id The WordPress user ID of the vendor.
     * @return array|WP_Error The balance details or a WP_Error on failure.
     */
    public function get_vendor_balance($vendor_id)
    {
        $transient_key = 'trustap_v_bal_' . $vendor_id;
        $cached_balance = get_transient($transient_key);
        if ($cached_balance !== false) {
            return $cached_balance;
        }

        $full_user_id = Trustap_User_Manager::get_full_id($vendor_id);
        if (!$full_user_id) {
            return new WP_Error('no_full_account', 'No linked full Trustap account found.');
        }

        try {
            $raw_response = $this->controller->get_request_with_user('me/balances', $full_user_id);
            $response_status = $raw_response['response']['code'];
            $response_body = json_decode($raw_response['body'], true);

            if ($response_status != 200) {
                return new WP_Error('api_error', $response_body['message'] ?? 'Failed to fetch balance.');
            }

            set_transient($transient_key, $response_body, HOUR_IN_SECONDS);
            return $response_body;
        } catch (\Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }

    /**
     * Fetches the payout status for a vendor.
     * Uses transients for caching.
     *
     * @param int $vendor_id The WordPress user ID of the vendor.
     * @return array|WP_Error The payout status details or a WP_Error on failure.
     */
    public function get_vendor_payout_status($vendor_id)
    {
        $transient_key = 'trustap_v_ps_' . $vendor_id;
        $cached_status = get_transient($transient_key);
        if ($cached_status !== false) {
            return $cached_status;
        }

        $full_user_id = Trustap_User_Manager::get_full_id($vendor_id);
        if (!$full_user_id) {
            return new WP_Error('no_full_account', 'No linked full Trustap account found.');
        }

        try {
            $raw_response = $this->controller->get_request_with_user('me/profile/payout_status', $full_user_id);
            $response_status = $raw_response['response']['code'];
            $response_body = json_decode($raw_response['body'], true);

            if ($response_status != 200) {
                return new WP_Error('api_error', $response_body['message'] ?? 'Failed to fetch payout status.');
            }

            set_transient($transient_key, $response_body, 12 * HOUR_IN_SECONDS);
            return $response_body;
        } catch (\Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }



}
