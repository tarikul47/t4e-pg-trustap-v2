<?php
class WCFM_Trustap_API
{
    private $settings;
    private $test_mode;
    public $environment;
    public $api_key;
    public $client_id;
    private $client_secret;

    public function __construct()
    {
        // Load plugin settings
        $this->settings = get_option('woocommerce_trustap_settings', array());
        $this->test_mode = (isset($this->settings['testmode']) && $this->settings['testmode'] === 'yes');
        $this->environment = $this->test_mode ? 'test' : 'live';

        $logger = wc_get_logger();
        $logger->info('Trustap environment: ' . $this->environment, ['source' => 'trustap']);

        // Attempt to load API key for determined environment
        $this->api_key = get_option("trustap_{$this->environment}_api_key");
        // Fallback: if not set, try the opposite environment (covers mis‑configuration)
        if (empty($this->api_key)) {
            $fallback_env = $this->environment === 'test' ? 'live' : 'test';
            $logger->info('Primary API key empty, trying fallback env: ' . $fallback_env, ['source' => 'trustap']);
            $this->api_key = get_option("trustap_{$fallback_env}_api_key");
            if (!empty($this->api_key)) {
                $this->environment = $fallback_env; // switch to the environment that actually has a key
                $logger->info('Using fallback environment: ' . $this->environment, ['source' => 'trustap']);
            }
        }
        $logger->info('Loaded api_key length: ' . (strlen($this->api_key) ?: 0), ['source' => 'trustap']);
        $this->client_id = get_option("trustap_{$this->environment}_client_id");
        $this->client_secret = get_option("trustap_{$this->environment}_client_secret");
        $this->provider_url = get_option("trustap_{$this->environment}_provider_url");
    }

    private function get_sso_url()
    {
        $realm = $this->test_mode ? 'trustap-stage' : 'trustap';
        return sprintf('https://sso.trustap.com/auth/realms/%s/protocol/openid-connect', $realm);
    }

    public function get_auth_url()
    {
        $redirect_uri = urlencode(admin_url('admin-ajax.php?action=wcfm_trustap_oauth_callback'));

        $user_id = get_current_user_id();
        $state_data = json_encode(['user_id' => $user_id, 'random' => bin2hex(random_bytes(8))]);
        $state = base64_encode($state_data);

        set_transient('trustap_oauth_state_' . $state, $state, 15 * 60);

       // $scope = urlencode('openid p2p_tx:offline_create_join p2p_tx:offline_accept_deposit p2p_tx:offline_cancel p2p_tx:offline_confirm_handover p2p_tx:offline_claim');
        
        $scope = urlencode('openid p2p_tx:offline_create_join p2p_tx:offline_accept_deposit p2p_tx:offline_cancel p2p_tx:offline_claim');

        $auth_url = sprintf(
            '%s/auth?client_id=%s&redirect_uri=%s&response_type=code&scope=%s&state=%s',
            $this->get_sso_url(),
            $this->client_id,
            $redirect_uri,
            $scope,
            $state
        );

        return $auth_url;
    }

    public function handle_oauth_callback($code, $state)
    {
        $logger = wc_get_logger();
        $context = array('source' => 't4e-pg-trustap');

        $saved_state = get_transient('trustap_oauth_state_' . $state);

        if (!$saved_state || $state !== $saved_state) {
            $logger->error('State verification failed. Saved State: ' . $saved_state . ' | Incoming State: ' . $state, $context);
            wp_die('State verification failed. Please try again.');
        }

        $state_data = json_decode(base64_decode($state), true);
        $user_id = $state_data['user_id'];

        delete_transient('trustap_oauth_state_' . $state);

        $token_url = $this->get_sso_url() . '/token';

        $response = wp_remote_post($token_url, array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => admin_url('admin-ajax.php?action=wcfm_trustap_oauth_callback'),
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
            ),
        ));

        if (is_wp_error($response)) {
            $logger->error('Token request failed: ' . $response->get_error_message(), $context);
            wp_die('Token request failed.');
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['id_token']) && isset($data['access_token'])) {
            $id_token_parts = explode('.', $data['id_token']);
            $id_token_payload = json_decode(base64_decode($id_token_parts[1]), true);

            if (isset($id_token_payload['sub'])) {
                // update_user_meta($user_id, 'trustap_user_id', $id_token_payload['sub']);
                // update_user_meta($user_id, 'trustap_access_token', $data['access_token']);
                // update_user_meta($user_id, 'trustap_refresh_token', $data['refresh_token']);

                update_user_meta($user_id, "trustap_{$this->environment}_user_id", $id_token_payload['sub']);
                return true;
            }
        }

        return false;
    }
}