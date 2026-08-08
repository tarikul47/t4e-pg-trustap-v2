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

    /*
    public function post_request_no_user($endpoint, $data)
    {
        $url = $this->trustap_api_url . $endpoint;
        // ১. যদি $this->api_key ফাঁকা থাকে, তবে ডাটাবেস সেটিংস থেকে তুলে আনুন
        $api_key = $this->api_key;

        if (empty($api_key)) {
            // আপনার ট্রাস্ট্যাপ অপশন কি (option_name) অনুযায়ী নিচের 't4e_pg_trustap_settings' বা 'api_key' পরিবর্তন করে নিতে পারেন
            $settings = get_option('t4e_pg_trustap_settings', array());

            $api_key = isset($settings['api_key']) ? $settings['api_key'] : get_option('trustap_api_key', '');
        }

        // Trim possible whitespace from stored API key
        $clean_key = trim($api_key);

        // API Key একেবারেই ফাঁকা কি না চেক করার জন্য সেফটি গার্ড
        if (empty($clean_key)) {
            amaturlog('Trustap Error: API Key is missing or empty in post_request_no_user', 'error', 'Trustap_Core');
            throw new \Exception(__('Trustap API key is missing. Please check gateway settings.', 't4e-pg-trustap'));
        }

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
*/

    public function post_request_no_user($endpoint, $data)
    {
        $url = $this->trustap_api_url . $endpoint;

        // --- DEBUG LOG START ---
        $class_api_key = $this->api_key;
        $db_settings = get_option('t4e_pg_trustap_settings', array());

        amaturlog('Trustap DEBUG - Class API Key: ' . print_r($class_api_key, true), 'debug', 'Trustap_Core');
        amaturlog('Trustap DEBUG - DB Option Settings: ' . print_r($db_settings, true), 'debug', 'Trustap_Core');
        // --- DEBUG LOG END ---

        // ১. যদি $this->api_key ফাঁকা থাকে, তবে ডাটাবেস সেটিংস থেকে নেওয়া
        $api_key = $class_api_key;
        if (empty($api_key)) {
            if (is_array($db_settings) && isset($db_settings['api_key'])) {
                $api_key = $db_settings['api_key'];
            } else {
                // বিকল্প যদি অপশন কি অন্য নামে থাকে
                $api_key = get_option('trustap_api_key', '');
            }
        }

        // Trim whitespace
        $clean_key = trim($api_key);

        amaturlog('Trustap DEBUG - Final Cleaned Key Used: ' . print_r($clean_key, true), 'debug', 'Trustap_Core');

        // সেফটি চেক: কী একদম ফাঁকা থাকলে এক্সেপশন থ্রো করবে
        if (empty($clean_key)) {
            amaturlog('Trustap Error: API Key is missing or empty in post_request_no_user', 'error', 'Trustap_Core');
            throw new \Exception(__('Trustap API key is missing. Please check gateway settings.', 't4e-pg-trustap'));
        }

        // Log request details (masking key slightly for log)
        amaturlog('Trustap POST (guest) request: ' . wp_json_encode([
            'url' => $url,
            'headers' => [
                'Content-Type' => 'application/json',
                'Trustap-User' => '',
                'Authorization' => 'Basic ' . base64_encode($clean_key . ':'),
            ],
            'body' => $data,
        ]), 'debug', 'Trustap_Core');

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Trustap-User' => '',
                'Authorization' => 'Basic ' . base64_encode($clean_key . ':'),
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
