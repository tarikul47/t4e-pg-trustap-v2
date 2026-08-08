<?php

if (!defined('ABSPATH')) {
    exit;
}

class WCFMmp_Gateway_Trustap extends WCFMmp_Abstract_Gateway
{

    public $id;
    public $message = array();
    public $gateway_title;
    public $payment_gateway;
    public $withdrawal_id;
    public $vendor_id;
    public $withdraw_amount = 0;
    public $withdraw_charges = 0;
    public $currency;
    public $transaction_mode;
    public $test_mode = false;
    public $client_id;
    public $client_secret;
    public $mp;

    public function __construct()
    {
        // echo "hghgggg";
        $this->id = WCFMTrustap_GATEWAY;
        $this->gateway_title = __(WCFMTrustap_GATEWAY_LABEL, 'wcfm-pg-mangopay');
        $this->payment_gateway = $this->id;

    }

    public function gateway_logo()
    {
        global $WCFMpgmp;
        return $WCFMpgmp->plugin_url . 'assets/images/' . $this->id . '.png';
    }

    public function validate_request() {
		return true;
	}

	public function process_payment( $withdrawal_id, $vendor_id, $withdraw_amount, $withdraw_charges, $transaction_mode = 'auto' ) {
		return array();
	}

}