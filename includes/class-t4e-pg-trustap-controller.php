<?php
namespace Trustap\PaymentGateway\Controller;

/**
 * Custom controller for the T4e plugin to handle specific API needs
 * like requests that don't require a Trustap-User header.
 */
class T4e_Pg_Trustap_Controller extends AbstractController
{
    /**
     * Performs a POST request without the Trustap-User header.
     * Useful for endpoints like creating guest users.
     *
     * @param string $endpoint API endpoint
     * @param array $data Request body
     * @return \WP_Http_Response
     * @throws \Exception
     */
    public function post_request_no_user($endpoint, $data)
    {
        $url = $this->trustap_api_url . $endpoint;
        // Trim possible whitespace from stored API key
        $clean_key = trim($this->api_key);
        // Log request details (masking the key for security)
        amaturlog('Trustap POST (guest) request: ' . wp_json_encode([
            'url' => $url,
            'headers' => [
                'Content-Type' => 'application/json',
                'Trustap-User' => '',
                'Authorization' => 'Basic ' . base64_encode($clean_key . ':' . ''),
            ],
            'body' => $data,
        ]), 'debug', 'Trustap_Core');
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                // Empty Trustap-User header required by guest endpoint
                'Trustap-User' => '',
                'Authorization' => 'Basic ' .
                    base64_encode($clean_key . ':' . ''),
            ),
            'body' => json_encode($data)
        );
        $result = wp_remote_post($url, $args);
        if (is_wp_error($result)) {
            throw new \Exception(
                __('API request failed. Please try again.', 't4e-pg-trustap'),
                'error'
            );
        }
        return $result;
    }

    /**
     * Performs a GET request that requires the Trustap-User header.
     *
     * @param string $endpoint API endpoint
     * @param string $user_id The Full User ID of the Trustap user
     * @param array $params Optional query parameters
     * @return \WP_Http_Response
     * @throws \Exception
     */
    public function get_request_with_user($endpoint, $user_id, $params = [])
    {
        $url = add_query_arg($params, $this->trustap_api_url . $endpoint);
        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' .
                    base64_encode($this->api_key . ':' . ''),
                'Trustap-User' => $user_id,
            ),
        );
        $result = wp_remote_get($url, $args);
        if (is_wp_error($result)) {
            throw new \Exception(
                __('API request failed. Please try again.', 't4e-pg-trustap'),
                'error'
            );
        }
        return $result;
    }

}
