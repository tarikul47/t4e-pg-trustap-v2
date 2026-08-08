<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://onlytarikul.com
 * @since      1.0.0
 *
 * @package    T4e_Pg_Trustap
 * @subpackage T4e_Pg_Trustap/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    T4e_Pg_Trustap
 * @subpackage T4e_Pg_Trustap/includes
 * @author     Tarikul Islam <tarikul47@gmail.com>
 */
class T4e_Pg_Trustap
{

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      T4e_Pg_Trustap_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * The OAuth handler for the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      T4e_Pg_Trustap_OAuth_Handler    $oauth_handler    Handles OAuth2 flow.
	 */
	protected $oauth_handler;

	/**
	 * The single instance of the Trustap API handler.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WCFM_Trustap_API    $trustap_api    Handles all API communication.
	 */
	protected $trustap_api;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{
		if (defined('T4E_PG_TRUSTAP_VERSION')) {
			$this->version = T4E_PG_TRUSTAP_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 't4e-pg-trustap';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - T4e_Pg_Trustap_Loader. Orchestrates the hooks of the plugin.
	 * - T4e_Pg_Trustap_i18n. Defines internationalization functionality.
	 * - T4e_Pg_Trustap_Admin. Defines all hooks for the admin area.
	 * - T4e_Pg_Trustap_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies()
	{

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-t4e-pg-trustap-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-t4e-pg-trustap-i18n.php';


		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-trustap-service-override.php';

		// Trustap gateway override for process payment 
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/gateways/class-override-gateway-trustap.php';



		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-t4e-pg-trustap-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-t4e-pg-trustap-public.php';

		$this->trustap_api = new WCFM_Trustap_API();
		$this->oauth_handler = new T4e_Pg_Trustap_OAuth_Handler($this->trustap_api);
		$this->loader = new T4e_Pg_Trustap_Loader();
		$this->loader->add_action('wcfm_init', $this, 'load_wcfm_gateway', 10);
	}

	public function load_wcfm_gateway()
	{
		if (class_exists('WCFMmp_Abstract_Gateway')) {
			require_once plugin_dir_path(dirname(__FILE__)) . 'includes/gateways/class-wcfm-gateway-trustap.php';
		}
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the T4e_Pg_Trustap_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale()
	{

		$plugin_i18n = new T4e_Pg_Trustap_i18n();

		$this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks()
	{

		$plugin_admin = new T4e_Pg_Trustap_Admin($this->get_plugin_name(), $this->get_version(), $this->trustap_api);

		// Register your custom payment gateway with WCFM Marketplace
		$this->loader->add_filter('wcfm_marketplace_withdrwal_payment_methods', $plugin_admin, 'wcfmmp_custom_pg');
		$this->loader->add_filter('woocommerce_payment_gateways', $plugin_admin, 'override_trustap_gateway', 999);

		$this->loader->add_action('add_meta_boxes', $plugin_admin, 't4e_add_confirm_handover_meta_box', 10, 2);
		$this->loader->add_action('add_meta_boxes', $plugin_admin, 't4e_add_accept_complaint_meta_box', 10, 2);

		$this->loader->add_action('woocommerce_order_status_completed', $plugin_admin, 't4e_sync_handover_on_status_change', 10, 1);
		$this->loader->add_action('woocommerce_order_status_complaint-accepted', $plugin_admin, 't4e_sync_complaint_acceptance_on_status_change', 10, 1);

		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks()
	{

		$plugin_public = new T4e_Pg_Trustap_Public($this->get_plugin_name(), $this->get_version(), $this->oauth_handler, trustap_api: $this->trustap_api);


		$this->loader->add_filter('wcfm_marketplace_settings_fields_billing', $plugin_public, 'wcfmmp_custom_pg_vendor_setting', 50, 2);

		$this->loader->add_action('wcfm_main_contentainer_before', $plugin_public, 't4e_wcfm_main_contentainer_before');

		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');

		$this->loader->add_action('wcfm_order_totals_after_total', $plugin_public, 'display_trustap_transaction_details', 20, 1);
		//$this->loader->add_action('before_wcfm_orders_details', $plugin_public, 'sync_trustap_order_status', 10, 1);
		//	$this->loader->add_action('woocommerce_payment_complete', $plugin_public, 'save_trustap_transaction_details_on_payment_complete', 10, 1);
	}
	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run()
	{
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name()
	{
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    T4e_Pg_Trustap_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader()
	{
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version()
	{
		return $this->version;
	}

}
