(function ($) {
  "use strict";

  $(document).ready(function () {
    const data = t4e_pg_trustap_admin_data;

    // 1. Manual Handover Button
    const $handoverBtn = $("#t4e-confirm-handover-button-admin");
    if (data.funds_released) {
      $handoverBtn.prop("disabled", true).text("Funds Released").css("background", "#999").css("border-color", "#999");
    }

    $handoverBtn.on("click", function () {
      if (data.funds_released) return;

      const button = this;
      const spinner = document.getElementById("t4e-handover-spinner");

      const confirmed = confirm("Changing status to 'Completed' will automatically release the funds to the seller on Trustap. Do you want to continue?");
      if (!confirmed) return;

      button.style.display = "none";
      spinner.style.display = "block";

      const params = new Proxy(new URLSearchParams(window.location.search), {
        get: (searchParams, prop) => searchParams.get(prop) || searchParams.get('post'),
      });
      let orderId = params.id || params.post;

      fetch(data.confirm_handover_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": data.nonce,
        },
        credentials: "include",
        body: JSON.stringify({ orderId }),
      })
        .then(async (response) => {
          let resData = await response.json();
          if (response.ok) {
            const $statusSelect = $('select#order_status, select[name="order_status"]');
            if ($statusSelect.length) {
              $statusSelect.val('wc-completed');
            }
            alert(resData.message || "Handover confirmed successfully!");
            location.reload();
          } else {
            alert(resData.message || "Handover confirmation failed!");
          }
        })
        .catch((error) => {
          alert("Error: " + error.message);
        })
        .finally(() => {
          button.style.display = "block";
          spinner.style.display = "none";
        });
    });

    // 2. Manual Claim Funds Button
    const $claimBtn = $("#t4e-claim-funds-button-admin");
    if (data.funds_released) {
      $claimBtn.prop("disabled", true).text("Funds Claimed").css("background", "#999").css("border-color", "#999");
    }

    $claimBtn.on("click", function () {
      if (data.funds_released) return;

      const button = this;
      const spinner = document.getElementById("t4e-handover-spinner");

      const confirmed = confirm("This will attempt to claim the funds for the vendor's full Trustap account. This is only possible after the complaint period is over. Do you want to continue?");
      if (!confirmed) return;

      $(button).prop('disabled', true);
      spinner.style.display = "block";

      const params = new Proxy(new URLSearchParams(window.location.search), {
        get: (searchParams, prop) => searchParams.get(prop) || searchParams.get('post'),
      });
      let orderId = params.id || params.post;

      fetch(data.claim_transaction_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": data.nonce,
        },
        credentials: "include",
        body: JSON.stringify({ orderId }),
      })
        .then(async (response) => {
          let resData = await response.json();
          if (response.ok) {
            alert(resData.message || "Funds claimed successfully!");
            location.reload();
          } else {
            alert(resData.message || "Claim failed: " + (resData.code || "Unknown error"));
          }
        })
        .catch((error) => {
          alert("Error: " + error.message);
        })
        .finally(() => {
          $(button).prop('disabled', false);
          spinner.style.display = "none";
        });
    });

    // 3. Manual Accept Complaint Button
    const $complaintBtn = $("#t4e-accept-complaint-button");
    if (data.complaint_accepted) {
      $complaintBtn.prop("disabled", true).text("Complaint Accepted (Refunded)");
    }

    $complaintBtn.on("click", function () {
      if (data.complaint_accepted) return;

      const button = this;
      const confirmed = confirm("This action will trigger a refund to the buyer. Are you sure you want to proceed?");
      if (!confirmed) return;

      $(button).prop('disabled', true);

      const params = new Proxy(new URLSearchParams(window.location.search), {
        get: (searchParams, prop) => searchParams.get(prop) || searchParams.get('post'),
      });
      let orderId = params.id || params.post;

      fetch(data.accept_complaint_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": data.nonce,
        },
        credentials: "include",
        body: JSON.stringify({ orderId }),
      })
        .then(async (response) => {
          let resData = await response.json();
          if (response.ok) {
            const $statusSelect = $('select#order_status, select[name="order_status"]');
            if ($statusSelect.length) {
              $statusSelect.val('wc-complaint-accepted');
            }
            alert(resData.message || "Complaint accepted successfully!");
            location.reload();
          } else {
            alert(resData.message || "Failed to accept complaint!");
          }
        })
        .catch((error) => {
          alert("Error: " + error.message);
        })
        .finally(() => {
          $(button).prop('disabled', false);
        });
    });

    // 4. Intercept Status Dropdown Changes
    if (data.payment_method === 'trustap') {
      const $statusSelect = $('select#order_status, select[name="order_status"]');

      $statusSelect.on('change', function() {
        const newStatus = $(this).val();
        let message = '';

        if (newStatus === 'wc-completed' || newStatus === 'completed') {
          if (!data.funds_released) {
            message = "Changing status to 'Completed' will automatically release the funds to the seller on Trustap. Do you want to continue?";
          }
        } else if (newStatus === 'wc-complaint-accepted' || newStatus === 'complaint-accepted') {
          if (!data.complaint_accepted) {
            message = "Changing status to 'Complaint Accepted' will automatically trigger a refund to the buyer on Trustap. Do you want to continue?";
          }
        }

        if (message && !confirm(message)) {
          $(this).val($(this).data('prev-val'));
          return false;
        }

        $(this).data('prev-val', newStatus);
      });

      $statusSelect.data('prev-val', $statusSelect.val());
    }
  });

})(jQuery);
