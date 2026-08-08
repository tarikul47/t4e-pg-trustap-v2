<?php

use Trustap\PaymentGateway\Helper\Template;
use Trustap\PaymentGateway\Controller\AbstractController;
use Trustap\PaymentGateway\Enumerators\Uri as UriEnumerator;

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://onlytarikul.com
 * @since      1.0.0
 *
 * @package    T4e_Pg_Trustap
 * @subpackage T4e_Pg_Trustap/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    T4e_Pg_Trustap
 * @subpackage T4e_Pg_Trustap/admin
 * @author     Tarikul Islam <tarikul47@gmail.com>
 */
class T4e_Pg_Trustap_Admin extends T4e_Pg_Trustap_Core
{

		/**
		 * The ID of this plugin.
		 *
		 * @since    1.0.0
		 * @access   private
		 * @var      string    $plugin_name    The ID of this plugin.
		 */

		/**
		 * The version of this plugin.
		 *
		 * @since    1.0.0
		 * @access   private
		 * @var      string    $version    The current version of this plugin.
		 */

		/**
		 * Initialize the class and set its properties.
		 *
		 * @since    1.0.0
		 * @param      string    $plugin_name       The name of this plugin.
		 * @param      string    $version           The version of this plugin.
		 */


		public function __construct($plugin_name, $version, $trustap_api)
		{

			parent::__construct($plugin_name, $version, $trustap_api);

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
			if (!$order->has_status('processing')) {
				return;
			}

			add_meta_box(
				't4e-trustap-confirm-handover-meta-box',
				'Trustap Handover & Claim',
				[$this, 't4e_confirm_handover_meta_box'],
				'woocommerce_page_wc-orders',
				'side',
				'high'
			);
		}

		public function t4e_add_accept_complaint_meta_box($post_type, $post)
		{
			$order = wc_get_order($post->ID);

			if (!$order || $order->get_payment_method() !== 'trustap') {
				return;
			}

			if (!$order->has_status('complained-buyer')) {
				return;
			}

			add_meta_box(
				't4e-trustap-accept-complaint-meta-box',
				'Trustap Accept Complaint',
				[$this, 't4e_accept_complaint_meta_box'],
				'woocommerce_page_wc-orders',
				'side',
				'high'
			);
		}

		public function t4e_accept_complaint_meta_box()
		{
			$args = [
				'accept_complaint_url' => get_rest_url(null, 't4e-pg-trustap/v1/accept-complaint'),
				'nonce' => wp_create_nonce('wp_rest'),
			];
			extract($args);
			include(plugin_dir_path(__FILE__) . 'partials/t4e-accept-complaint.php');
		}

		public function t4e_confirm_handover_meta_box()
		{
			$args = [
				'icon' => TRUSTAP_IMAGE_URL . "handshake-simple-solid.svg",
				'confirm_handover_url' => UriEnumerator::CONFIRM_HANDOVER_URL(),
				'claim_transaction_url' => get_rest_url(null, 't4e-pg-trustap/v1/claim-transaction'),
				'nonce' => wp_create_nonce('wp_rest')
			];
			// Make $icon, $confirm_handover_url, $claim_transaction_url, $nonce available in partial
			extract($args);
			include(plugin_dir_path(__FILE__) . 'partials/t4e-confirm-handover.php');
		}

		public function wcfmmp_custom_pg($payment_methods)
		{
			$payment_methods[WCFMTrustap_GATEWAY] = __(WCFMTrustap_GATEWAY_LABEL, 'wcfm-pg-trustap');
			return $payment_methods;
		}

		public function override_trustap_gateway($gateways)
		{
			$trustap_gateway_key = array_search('Trustap\PaymentGateway\Gateway', $gateways);

			if ($trustap_gateway_key !== false) {
				// Unset the original gateway using the key we found.
				unset($gateways[$trustap_gateway_key]);
				// Add our overridden gateway class with the correct key.
				$gateways['trustap'] = 'Override_Gateway_Trustap';
			}
			return $gateways;
		}

		public function create_trustap_guest_user_on_registration($user_id)
		{
			if (Trustap_User_Manager::get_guest_id($user_id)) {
				return;
			}

			WCFMTrustap_Logger::log('Attempting to create Trustap guest user for user ID: ' . $user_id);
			$user_data = get_userdata($user_id);
			$user_roles = (array) $user_data->roles;

			// Proceed only for customers and vendors
			if (in_array('customer', $user_roles, true) || in_array('wcfm_vendor', $user_roles, true)) {
				WCFMTrustap_Logger::log('User is a customer or vendor. Proceeding.');
				$email = $user_data->user_email;
				$first_name = $user_data->first_name ?: $user_data->user_login;
				$last_name = $user_data->last_name ?: $user_data->user_login;

				// Get country from WooCommerce billing info if available
				$country = get_user_meta($user_id, 'billing_country', true) ?: 'IE';

				$body = array(
					'email' => $email,
					'first_name' => $first_name,
					'last_name' => $last_name,
					'country_code' => $country,
					'tos_acceptance' => array(
						'unix_timestamp' => time(),
						'ip' => $_SERVER['REMOTE_ADDR']
					)
				);

				try {
					$response = $this->controller->post_request_no_user('guest_users', $body);
					$decoded_response = json_decode(wp_remote_retrieve_body($response), true);

					if (isset($decoded_response['id'])) {
						Trustap_User_Manager::save_guest_id($user_id, $decoded_response['id']);
						WCFMTrustap_Logger::log('Trustap guest user ID saved for user ' . $user_id . ': ' . $decoded_response['id']);
					} else {
						WCFMTrustap_Logger::log('Trustap guest user ID not found in response.');
					}
				} catch (\Exception $e) {
					WCFMTrustap_Logger::log('Error during guest user creation: ' . $e->getMessage());
				}
			} else {
				WCFMTrustap_Logger::log('User is not a customer or vendor. Skipping guest user creation.');
			}
		}

		/**
		 * Register the stylesheets for the admin area.
		 *
		 * @since    1.0.0
		 */
		public function enqueue_styles()
		{

			/**
			 * This function is provided for demonstration purposes only.
			 *
			 * An instance of this class should be passed to the run() function
			 * defined in T4e_Pg_Trustap_Loader as all of the hooks are defined
			 * in that particular class.
			 *
			 * The T4e_Pg_Trustap_Loader will then create the relationship
			 * between the defined hooks and the functions defined in this
			 * class.
			 */

			wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/t4e-pg-trustap-admin.css', array(), $this->version, 'all');

		}

		/**
		 * Register the JavaScript for the admin area.
		 *
		 * @since    1.0.0
		 * @access   public
		 */
		public function enqueue_scripts()
		{
			wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/t4e-pg-trustap-admin.js', array('jquery'), $this->version, true);

			$order_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
			if (!$order_id && isset($_GET['post'])) {
				$order_id = absint($_GET['post']);
			}
			$payment_method = '';
			$funds_released = false;
			$complaint_accepted = false;

			if ($order_id) {
				$order = wc_get_order($order_id);
				if ($order) {
					$payment_method = $order->get_payment_method();
					$transaction_details = $order->get_meta('_trustap_transaction_details');

					$terminal_handover_statuses = ['completed', 'buyer_handover_confirmed', 'seller_handover_confirmed', 'Funds Released'];
					if (isset($transaction_details['status']) && in_array($transaction_details['status'], $terminal_handover_statuses)) {
						$funds_released = true;
					}

					$terminal_refund_statuses = ['complaint_accepted', 'refunded', 'deposit_refunded'];
					if (isset($transaction_details['status']) && in_array($transaction_details['status'], $terminal_refund_statuses)) {
						$complaint_accepted = true;
					}
				}
			}

			$localized_data = array(
				'confirm_handover_url' => get_rest_url(null, 't4e-pg-trustap/v1/confirm-handover'),
				'claim_transaction_url' => get_rest_url(null, 't4e-pg-trustap/v1/claim-transaction'),
				'accept_complaint_url' => get_rest_url(null, 't4e-pg-trustap/v1/accept-complaint'),
				'nonce' => wp_create_nonce('wp_rest'),
				'payment_method' => $payment_method,
				'funds_released' => $funds_released,
				'complaint_accepted' => $complaint_accepted,
			);
			wp_localize_script($this->plugin_name, 't4e_pg_trustap_admin_data', $localized_data);
		}
}
