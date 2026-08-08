<?php

/**
 * Fired during plugin activation
 *
 * @link       https://onlytarikul.com
 * @since      1.0.0
 *
 * @package    T4e_Pg_Trustap
 * @subpackage T4e_Pg_Trustap/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    T4e_Pg_Trustap
 * @subpackage T4e_Pg_Trustap/includes
 * @author     Tarikul Islam <tarikul47@gmail.com>
 */
class T4e_Pg_Trustap_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		if (!wp_next_scheduled('t4e_pg_trustap_automated_claim_cron')) {
			wp_schedule_event(time(), 'hourly', 't4e_pg_trustap_automated_claim_cron');
		}
	}

}
