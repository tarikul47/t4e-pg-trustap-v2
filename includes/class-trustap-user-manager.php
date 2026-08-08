<?php
/**
 * Handles the mapping and retrieval of Trustap User IDs (Guest and Full).
 */
class Trustap_User_Manager
{
    /**
     * Get the Trustap Guest User ID for a given WordPress user.
     *
     * @param int $user_id WordPress User ID
     * @return string|false Trustap Guest ID or false if not found.
     */
    public static function get_guest_id($user_id)
    {
        $env = T4e_Pg_Trustap_Core::get_environment(); // Assuming this helper exists or is added to Core
        return get_user_meta($user_id, "trustap_guest_{$env}_user_id", true);
    }

    /**
     * Get the Trustap Full User ID for a given WordPress user.
     *
     * @param int $user_id WordPress User ID
     * @return string|false Trustap Full ID or false if not found.
     */
    public static function get_full_id($user_id)
    {
        $env = T4e_Pg_Trustap_Core::get_environment();
        return get_user_meta($user_id, "trustap_{$env}_user_id", true);
    }

    /**
     * Save the Trustap Guest User ID.
     *
     * @param int $user_id WordPress User ID
     * @param string $guest_id Trustap Guest ID
     */
    public static function save_guest_id($user_id, $guest_id)
    {
        $env = T4e_Pg_Trustap_Core::get_environment();
        update_user_meta($user_id, "trustap_guest_{$env}_user_id", $guest_id);
    }

    /**
     * Save the Trustap Full User ID.
     *
     * @param int $user_id WordPress User ID
     * @param string $full_id Trustap Full ID
     */
    public static function save_full_id($user_id, $full_id)
    {
        $env = T4e_Pg_Trustap_Core::get_environment();
        update_user_meta($user_id, "trustap_{$env}_user_id", $full_id);
    }
}
