<div class="trustap-action-card" style="background: #f0f9ff; border: 1px solid #007cba; padding: 15px; border-radius: 4px; margin-bottom: 10px;">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
        <img src="<?php echo wp_kses_post($icon) ?>" alt="Confirm Handover" style="width: 20px; height: 20px;">
        <h3 style="margin: 0; color: #007cba; font-size: 1em;"><?php echo esc_html__("Trustap Handover & Claim", "trustap-payment-gateway"); ?></h3>
    </div>
    <p style="font-size: 0.9em; color: #444; margin-bottom: 12px;">
        <?php echo esc_html__(
            "Confirm the handover to start the complaint period, then claim the funds once the period is completed.",
            "trustap-payment-gateway"
        )
            ?>
    </p>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button id="t4e-confirm-handover-button-admin" class="button-primary" type="button" style="background: #46b450; border-color: #46b450; box-shadow: none; text-shadow: none;">
            <?php echo esc_html__("Confirm Handover", "trustap-payment-gateway") ?>
        </button>
        <button id="t4e-claim-funds-button-admin" class="button-primary" type="button" style="background: #007cba; border-color: #007cba; box-shadow: none; text-shadow: none;">
            <?php echo esc_html__("Claim Funds", "trustap-payment-gateway") ?>
        </button>
    </div>
    <div id="t4e-handover-spinner" style="display: none; margin-top: 10px;">
        <div class="t4e-spinner"></div>
    </div>
</div>
