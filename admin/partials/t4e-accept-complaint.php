<?php
defined('ABSPATH') || exit;
?>
<div class="t4e-trustap-accept-complaint">
    <p>
        <?php echo esc_html__("If you wish to accept the complaint, click the button below.", "t4e-pg-trustap"); ?>
    </p>
    <button class="button button-primary" type="button" id="t4e-accept-complaint-button">
        <?php echo esc_html__("Refund Buyer", "t4e-pg-trustap"); ?>
    </button>
</div>
<style>
.t4e-trustap-accept-complaint p {
    margin-bottom: 10px;
}
</style>
