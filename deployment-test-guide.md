# Deployment & Testing Guide: Trustap Integration

This guide provides practical steps to verify the implementation of each phase before uploading changes to the production site.

---

## Phase 1: Foundation & Critical Fixes

### Test 1.1 & 1.3: Guest User Creation
**Goal**: Verify that a new user (Customer or Vendor) is automatically assigned a Trustap Guest User ID upon registration.

#### Prerequisites
- Trustap API keys must be correctly configured in the WooCommerce Trustap settings.
- The site must be in the correct mode (Test/Live) matching the API keys used.

#### Practical Steps
1. **Create a Test User**: 
   - Go to **Users $\rightarrow$ Add New**.
   - Create a user with the role `customer` or `wcfm_vendor`.
   - Ensure the user has a valid email address.
2. **Check Logs**:
   - Go to **WooCommerce $\rightarrow$ Status $\rightarrow$ Logs**.
   - Look for logs from `t4e-pg-trustap` or `wcfm-trustap`.
   - You should see: `Attempting to create Trustap guest user...` followed by `Trustap guest user ID saved for user X: [ID]`.
3. **Verify User Meta**:
   - Use a user meta viewer plugin or run the following SQL query in phpMyAdmin:
     ```sql
     SELECT meta_value FROM wp_usermeta WHERE user_id = [YOUR_USER_ID] AND meta_key LIKE 'trustap_guest_%';
     ```
   - **Expected Result**: A valid Trustap Guest User ID should be returned.

#### Troubleshooting
- **No ID saved**: Check the logs for "API keys not set" or "API request failed".
- **Wrong environment**: Ensure the `testmode` setting in WooCommerce matches the keys you provided.

---

## Phase 2: Vendor Connection (OAuth Flow)

### Test 2.1 & 2.2: Vendor OAuth Connection
**Goal**: Verify that a vendor can successfully connect their full Trustap account and that the system records the Full User ID.

#### Prerequisites
- A test user with the `wcfm_vendor` role.
- A valid Trustap account (Full account) to connect.
- The vendor must be logged into the WordPress site.

#### Practical Steps
1. **Access Vendor Settings**:
   - Log in as the vendor.
   - Go to the **WCFM Vendor Dashboard $\rightarrow$ Settings $\rightarrow$ Payment**.
2. **Verify Disconnected State**:
   - You should see the "Trustap Account Connection" section.
   - It should display: "You are currently using a guest account. To receive payouts, you must link your full Trustap account."
   - The **"Connect Full Trustap Account"** button should be visible.
3. **Execute Connection**:
   - Click the **"Connect Full Trustap Account"** button.
   - You will be redirected to the Trustap SSO page.
   - Log in with the Trustap Full Account and authorize the application.
4. **Verify Redirect**:
   - After authorization, you should be redirected back to the WCFM Payment settings.
5. **Verify Connected State**:
   - The section should now display: **"Connected"** (with a success badge).
   - It should show a link to "View Profile" and a **"Disconnect Account"** button.
6. **Verify Database**:
   - Run the following SQL query:
     ```sql
     SELECT meta_value FROM wp_usermeta WHERE user_id = [YOUR_VENDOR_ID] AND meta_key LIKE 'trustap_%_user_id';
     ```
   - **Expected Result**: You should see the Full User ID stored in the meta (e.g., `trustap_test_user_id`).

#### Testing Disconnect
1. Click the **"Disconnect Account"** button.
2. The page should reload, and the status should return to "Not Connected".
3. Verify in the database that the `trustap_{env}_user_id` meta has been deleted.

---

## Phase 3: The Claim Functionality (Technical Flow)

### Test 3.1 & 3.3: Manual Claiming Funds
**Goal**: Verify that a vendor with a linked full account can successfully claim their funds after the complaint period has ended.

#### Prerequisites
- An order that has had its handover confirmed (status `completed` or `funds_released` in Trustap).
- The vendor for this order must have a linked full Trustap account.
- The complaint period must be over.

#### Practical Steps
1. **Access Order Admin**:
   - Go to **WooCommerce $\rightarrow$ Orders**.
   - Open the specific order that is ready for claiming.
2. **Verify Claim Button**:
   - Look at the "Trustap Handover & Claim" meta box in the side panel.
   - The **"Claim Funds"** button should be active.
3. **Execute Claim**:
   - Click the **"Claim Funds"** button.
   - Confirm the action in the browser alert.
4. **Verify Success**:
   - You should see an alert: "Funds claimed successfully!".
   - The page should reload, and the "Claim Funds" button should now be disabled and say "Funds Claimed".
5. **Verify Database/Trustap**:
   - Check the order meta `_trustap_transaction_details`. The `claim_status` should be `claimed`.
   - Check the Trustap Dashboard for the transaction; the seller should no longer be marked as a "Guest".

### Test 3.2 & 3.5: Automated Claims & Notifications
**Goal**: Verify that the system automatically claims funds for connected vendors and notifies disconnected vendors when funds are ready.

#### Practical Steps
1. **Setup Test Order**:
   - Create an order and ensure handover is confirmed.
   - Manually set the Trustap transaction status to `completed` in the Trustap portal.
2. **Run Cron Job**:
   - Use WP-CLI: `wp cron event run t4e_pg_trustap_automated_claim_cron`.
3. **Verify Outcomes**:
   - **Connected Vendor**: Check the order notes. You should see: "Trustap Auto-Claim: Transaction claimed successfully."
   - **Disconnected Vendor**: Check the order notes for "Vendor notified to connect full account for claim" and verify the vendor received the notification email.

---

## Phase 4: Order Cancellation
*(Feature skipped for now)*

---

## Phase 5: Extended Features (Vendor Dashboard)

### Test 5.1 & 5.2: Balance and Payout Status
**Goal**: Verify that vendors can see their Trustap balance and payout status in their dashboard.

#### Practical Steps
1. **Access Vendor Settings**:
   - Log in as a connected vendor.
   - Go to **WCFM Vendor Dashboard $\rightarrow$ Settings $\rightarrow$ Payment**.
2. **Verify Display**:
   - Under "Trustap Account Status", you should see:
     - **Current Balance**: [Amount] [Currency]
     - **Payout Status**: [Status Message from API]
3. **Verify Disconnected State**:
   - Log in as a disconnected vendor.
   - Verify the message: "Please connect your Trustap account to view your balance and payout status."
4. **Verify Caching**:
   - Refresh the page several times.
   - Check the Trustap API logs (or site logs). The request should only occur once every hour for balance and once every 12 hours for payout status.

---

## Final Validation Checklist
- [ ] Guest users are created on registration.
- [ ] Vendors can connect/disconnect full accounts via OAuth.
- [ ] Handover confirmation triggers status updates.
- [ ] Funds can be claimed manually and automatically.
- [ ] Disconnected vendors are notified when funds are ready.
- [ ] Balance and Payout status are visible in the vendor dashboard.
- [ ] All logs are clean and translation strings are implemented.
