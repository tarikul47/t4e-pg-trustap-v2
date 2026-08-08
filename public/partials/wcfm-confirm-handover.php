<?php

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="trustap-action-card" style="background: #f0f9ff; border: 1px solid #007cba; padding: 15px; border-radius: 4px; margin-top: 20px; margin-bottom: 20px;">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
        <span class="wcfmfa fa-check-circle" style="color: #007cba; font-size: 20px;"></span>
        <h3 style="margin: 0; color: #007cba; font-size: 1.1em;"><?php echo esc_html__("Trustap: Confirm Handover", "t4e-pg-trustap"); ?></h3>
    </div>
    <p style="margin-bottom: 15px; font-size: 0.95em; color: #444;">
        <?php
        echo esc_html__(
            "Once you have handed over the item to the buyer, click the button below to release the escrowed funds to the seller's account.",
            "t4e-pg-trustap"
        );
        ?>
    </p>
    <button type="button" id="t4e-confirm-handover-button" class="wcfm_submit_button" 
            style="background: #46b450; border-color: #46b450; color: #fff; padding: 8px 20px; font-weight: 600;" 
            data-order-id="<?php echo esc_attr($order->id); ?>">
        <?php echo esc_html__("Confirm & Release Funds", "t4e-pg-trustap"); ?>
    </button>
    <div id="t4e-handover-message" style="margin-top: 10px; font-weight: 500;"></div>
</div>
