(function ($) {
  "use strict";

  $(document).ready(function () {
    const data = t4e_pg_trustap_public_data;

    // 1. Manual Handover Button
    const $handoverBtn = $("#t4e-confirm-handover-button");
    if (data.funds_released) {
      $handoverBtn.prop("disabled", true).text("Funds Released").css("background", "#999").css("border-color", "#999");
    }

    $handoverBtn.on("click", function (e) {
      e.preventDefault();
      if (data.funds_released) return;

      if (!confirm("Changing status to 'Completed' will automatically release the funds to the seller on Trustap. Do you want to continue?")) {
        return;
      }

      const button = $(this);
      const messageDiv = $("#t4e-handover-message");
      const orderId = button.data("order-id");

      button.prop("disabled", true).text("Confirming...");
      messageDiv.empty();

      fetch(data.confirm_handover_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": data.nonce,
        },
        credentials: "include",
        body: JSON.stringify({ orderId: orderId }),
      })
        .then(async (response) => {
          let resData = await response.json();
          if (response.ok) {
            messageDiv
              .css("color", "green")
              .text(
                resData.message ||
                  "Handover confirmed successfully! Syncing status...",
              );
            
            // Sync the dropdown status to 'completed'
            const $wcfmStatusSelect = $("#wcfm_order_status");
            if ($wcfmStatusSelect.length) {
              $wcfmStatusSelect.val('completed');
              if ($wcfmStatusSelect.hasClass("select2-hidden-accessible")) {
                $wcfmStatusSelect.trigger("change.select2");
              }
            }

            setTimeout(() => window.location.reload(), 2000);
          } else {
            messageDiv
              .css("color", "red")
              .text(
                "Error: " + (resData.message || "Handover confirmation failed!"),
              );
            button.prop("disabled", false).text("Confirm & Release Funds");
          }
        })
        .catch((error) => {
          messageDiv.css("color", "red").text("Error: " + error.message);
          button.prop("disabled", false).text("Confirm & Release Funds");
        });
    });

    // 2. Intercept Status Dropdown Changes in WCFM Order Details
    if (data.payment_method === "trustap") {
      const $wcfmStatusSelect = $("#wcfm_order_status");

      if ($wcfmStatusSelect.length) {
        $wcfmStatusSelect.on("change", function () {
          const newStatus = $(this).val();
          let message = "";

          if (newStatus === "completed" || newStatus === "wc-completed") {
            if (!data.funds_released) {
              message =
                "Changing status to 'Completed' will automatically release the funds to the seller on Trustap. Do you want to continue?";
            }
          } else if (
            newStatus === "complaint-accepted" ||
            newStatus === "wc-complaint-accepted"
          ) {
            if (!data.complaint_accepted) {
              message =
                "Changing status to 'Complaint Accepted' will automatically trigger a refund to the buyer on Trustap. Do you want to continue?";
            }
          }

          if (message && !confirm(message)) {
            $(this).val($(this).data("prev-val"));
            // If Select2 is used, we need to trigger an update
            if ($(this).hasClass("select2-hidden-accessible")) {
              $(this).trigger("change.select2");
            }
            return false;
          }

          $(this).data("prev-val", newStatus);
        });

        // Initialize prev-val
        $wcfmStatusSelect.data("prev-val", $wcfmStatusSelect.val());

        // Secondary check on Update button click (safety net)
        $("#wcfm_modify_order_status").on("click", function (e) {
          const newStatus = $wcfmStatusSelect.val();
          let message = "";

          if (newStatus === "completed" || newStatus === "wc-completed") {
            if (!data.funds_released) {
              message =
                "Changing status to 'Completed' will automatically release the funds to the seller on Trustap. Do you want to continue?";
            }
          } else if (
            newStatus === "complaint-accepted" ||
            newStatus === "wc-complaint-accepted"
          ) {
            if (!data.complaint_accepted) {
              message =
                "Changing status to 'Complaint Accepted' will automatically trigger a refund to the buyer on Trustap. Do you want to continue?";
            }
          }

          if (message && !confirm(message)) {
            e.stopImmediatePropagation();
            return false;
          }
        });
      }
    }
  });
})(jQuery);
