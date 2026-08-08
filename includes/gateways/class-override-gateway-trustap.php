<?php

if (!defined('ABSPATH')) {
    exit;
}
use Trustap\PaymentGateway\Controller\AbstractController;
use Trustap\PaymentGateway\Gateway as Trustap_Gateway;
use Trustap\PaymentGateway\Helper\Validator;
use Trustap\PaymentGateway\Enumerators\Uri as UriEnumerator;

if (class_exists('Trustap\PaymentGateway\Gateway')) {
    class Override_Gateway_Trustap extends Trustap_Gateway
    {
        protected $logger;

        private $helper;
        public $controller;

        public function __construct()
        {
            parent::__construct();
            $this->helper = new WCFM_Trustap_Helper();
            $this->controller = new AbstractController('trustap/v1');
            $this->my_custom_service = new Service_Override($this, $this->controller);

            // WooCommerce logger
            $this->logger = wc_get_logger();
        }

        private function log($message)
        {
            $this->logger->info($message, array('source' => 'trustap'));
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            try {
                if (!$this->validate_vendor_consistency($order->get_items())) {
                    wc_add_notice(__('You can only purchase from one vendor at a time using Trustap. Please split your cart.', 'wcfm-pg-trustap'), 'error');
                    return;
                }

                $seller_id = $this->helper->get_trustap_seller_id($order->get_items());
                if (is_wp_error($seller_id)) {
                    wc_add_notice($seller_id->get_error_message(), 'error');
                    return;
                }

                if (!$this->validate_cart_for_trustap($order)) {
                    return; // Notices are added within the function
                }

                $trustap_model = $this->get_trustap_model();
                $charge_details = $this->get_trustap_charge_details($order, $trustap_model);
                $buyer_id = $this->helper->get_trustap_buyer_id();

                $transaction = $this->create_trustap_transaction($order, $seller_id, $buyer_id, $charge_details, $trustap_model);

                $redirect_url = $this->prepare_redirect_url($order, $transaction, $trustap_model);

                return [
                    'result' => 'success',
                    'redirect' => $redirect_url,
                ];
            } catch (Exception $e) {
                $this->log('Process payment error raju1: ' . $e->getMessage());
                wc_add_notice($e->getMessage(), 'error');
                return;
            }
        }

        private function validate_vendor_consistency(array $items)
        {
            $first_item = reset($items);
            $product_id = $first_item->get_product_id();
            $vendor_id = wcfm_get_vendor_id_by_post($product_id);

            foreach ($items as $item) {
                if (wcfm_get_vendor_id_by_post($item->get_product_id()) !== $vendor_id) {
                    return false;
                }
            }
            return true;
        }

        private function validate_cart_for_trustap(WC_Order $order)
        {
            if ($order->get_total() * 100 < 100) {
                wc_add_notice(__("In order to use Trustap Payment Gateway, total price of the order has to be larger than 1.00 " . get_woocommerce_currency(), 'trustap-payment-gateway'), 'error');
                return false;
            }

            $payment_methods = [];
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($product->is_virtual() || $product->is_downloadable()) {
                    $payment_methods['f2f'] = true;
                } else {
                    $payment_methods['online'] = true;
                }
            }

            if (isset($payment_methods['online']) && isset($payment_methods['f2f'])) {
                wc_add_notice(__("We currently don't support different types of products in cart. Please select only digital products and continue or choose only products which will get delivered to you by post.", 'trustap-payment-gateway'), 'error');
                return false;
            }

            return true;
        }

        private function get_trustap_model()
        {
            return !empty(WC()->shipping->packages) ? '' : 'p2p/';
        }

        private function get_trustap_charge_details(WC_Order $order, $trustap_model)
        {
            $data = [
                'price' => ($order->get_total()) * 100,
                'currency' => strtolower(get_woocommerce_currency()),
            ];

            $response = $this->controller->get_request($trustap_model . 'charge', $data);
            
            return json_decode($response['body'], true);
        }

        private function create_trustap_transaction(WC_Order $order, $seller_id, $buyer_id, $charge_details, $trustap_model)
        {
            $product_names = [];
            foreach ($order->get_items() as $item) {
                $product_names[] = $item->get_name();
            }

            $data = [
                'buyer_id' => Validator::sanitize_string($buyer_id),
                'creator_role' => 'seller',
                'description' => 'Order ID ' . $order->get_id() . ': ' . implode(', ', $product_names),
                'currency' => Validator::sanitize_string($charge_details['currency']),
                'charge_calculator_version' => Validator::sanitize_integer($charge_details['charge_calculator_version']),
                'charge_seller' => Validator::sanitize_integer($charge_details['charge_seller']),
                'seller_id' => $seller_id,
            ];

            if ('p2p/' === $trustap_model) {
                $data['deposit_price'] = Validator::sanitize_integer($charge_details['price']);
                $data['deposit_charge'] = Validator::sanitize_integer($charge_details['charge']);
                $data['skip_remainder'] = true;
            } else {
                $data['price'] = Validator::sanitize_integer($charge_details['price']);
                $data['charge'] = Validator::sanitize_integer($charge_details['charge']);
            }

            $endpoint = $trustap_model . 'me/transactions/' . 'create_with_guest_user';
            $response = $this->controller->post_request($endpoint, $seller_id, $data);
            $response_code = wp_remote_retrieve_response_code($response);
            $transaction = json_decode(wp_remote_retrieve_body($response), true);

            if ($response_code !== 201 && $response_code !== 200 || empty($transaction) || !isset($transaction['id'])) {
                $error_message = isset($transaction['message']) ? $transaction['message'] : __('Failed to create Trustap transaction.', 'wcfm-pg-trustap');
                throw new Exception($error_message);
            }

            $order->update_meta_data('trustap_transaction_ID', Validator::sanitize_integer($transaction['id']));
            $order->update_meta_data('model', $trustap_model);
            $order->save();

            return $transaction;
        }

        private function prepare_redirect_url(WC_Order $order, $transaction, $trustap_model)
        {
            $action_url = \Trustap\PaymentGateway\Enumerators\Uri::ACTION_PAGE_URL();
            $action_token = md5(uniqid(mt_rand(), true));
            $_SESSION['token'] = $action_token;

            $billing_details = $this->get_billing_details($order->get_id());
            $state_parts = [
                'token' => $action_token,
                'order_id' => $order->get_id(),
                'name' => $billing_details['name'],
                'line1' => $billing_details['line1'],
                'city' => $billing_details['city'],
                'state' => $billing_details['state'],
                'postcode' => $billing_details['postcode'],
                'country' => $billing_details['country'],
            ];

            if ('p2p/' === $trustap_model) {
                $state_parts['tx_type'] = 'p2p';
                $state = http_build_query($state_parts, '', ':');
                return $action_url . 'f2f/transactions/' . Validator::sanitize_integer($transaction['id']) . '/pay_deposit?redirect_uri=' . get_home_url() . '/wc-api/trustap_webhook_raju&state=' . base64_encode($state);
            } else {
                $this->service->send_shipping_details($order->get_id());
                $state_parts['tx_type'] = 'online';
                $state = http_build_query($state_parts, '', ':');
                return $action_url . 'online/transactions/' . Validator::sanitize_integer($transaction['id']) . '/guest_pay?redirect_uri=' . get_home_url() . '/wc-api/trustap_webhook_raju&state=' . base64_encode($state);
            }
        }

    }
}