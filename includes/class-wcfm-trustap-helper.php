<?php

if (!defined('ABSPATH')) {
    exit;
}

use Trustap\PaymentGateway\Controller\AbstractController;

class WCFM_Trustap_Helper
{
    private $controller;

    public $trustap_api;

    public function __construct()
    {
        // Use the child controller to access post_request_no_user
        $this->controller = new \Trustap\PaymentGateway\Controller\T4e_Pg_Trustap_Controller('trustap/v1');
        $this->trustap_api = new WCFM_Trustap_API();
    }

    public function get_trustap_seller_id(array $items)
    {
        $first_item = reset($items);
        $product_id = $first_item->get_product_id();
        $vendor_id = wcfm_get_vendor_id_by_post($product_id);

        if ($vendor_id) {
            $seller_id = get_user_meta($vendor_id, "trustap_{$this->trustap_api->environment}_user_id", true);
            if (empty($seller_id)) {
                return new WP_Error('no_trustap_account', __('The vendor for this product does not have a Trustap account configured.', 'wcfm-pg-trustap'));
            }
            return $seller_id;
        } else {
            // Get the admin seller ID from the controller instantiated in the constructor.
            return $this->controller->seller_id;
        }
    }

    /**
     * Get the WordPress Vendor ID from an order.
     *
     * @param WC_Order $order
     * @return int|false
     */
    public function get_vendor_id_from_order($order)
    {
        $items = $order->get_items();
        if (empty($items)) {
            return false;
        }
        $first_item = reset($items);
        $product_id = $first_item->get_product_id();
        return wcfm_get_vendor_id_by_post($product_id);
    }

    public function get_trustap_buyer_id()
    {
        $buyer_id = get_current_user_id();
        $trustap_buyer_id = get_user_meta($buyer_id, "trustap_guest_{$this->trustap_api->environment}_user_id", true);

        if (empty($trustap_buyer_id) && isset($_SESSION['buyer_id'])) {
            $trustap_buyer_id = $_SESSION['buyer_id'];
        }
        return $trustap_buyer_id ? $trustap_buyer_id : '';
    }

    /**
     * Ensures that a Trustap Guest ID exists for the buyer.
     * If missing, it attempts to create one using the order's billing email.
     *
     * @param \WC_Order $order The current order.
     * @return string The Trustap Guest User ID.
     */
    public function ensure_trustap_buyer_id($order)
    {
        $buyer_id = get_current_user_id();
        $trustap_buyer_id = $this->get_trustap_buyer_id();

        if (!empty($trustap_buyer_id)) {
            return $trustap_buyer_id;
        }

        // If we are here, the user doesn't have a Guest ID. Let's create one.
        $email = $order->get_billing_email();
        if (empty($email)) {
            return '';
        }

        $user_data = get_userdata($buyer_id);
        $first_name = $user_data ? $user_data->first_name : $order->get_billing_first_name();
        $last_name = $user_data ? $user_data->last_name : $order->get_billing_last_name();
        $country = $order->get_billing_country() ?: 'IE';

        $body = array(
            'email' => $email,
            'first_name' => $first_name ?: 'Customer',
            'last_name' => $last_name ?: 'Customer',
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
                $new_id = $decoded_response['id'];
                // Save to user meta if user is logged in
                if ($buyer_id) {
                    update_user_meta($buyer_id, "trustap_guest_{$this->trustap_api->environment}_user_id", $new_id);
                }
                return $new_id;
            }
        } catch (\Exception $e) {
            // Log failure but return empty to let the gateway handle the error
            amaturlog("Failed to create on-the-fly guest account for " . $email . ": " . $e->getMessage(), 'error', 'Trustap_Helper');
        }

        return '';
    }

}