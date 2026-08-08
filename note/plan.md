# Development Plan: Trustap Vendor Payment Integration (Enhanced)

## Overview
This plan implements the end-to-end Trustap flow, focusing on the transition from Guest Users to Full Accounts (Claiming), order cancellation, and improved architectural structure, leveraging the parent `trustap-payment-gateway` plugin.

## 🏗️ Architectural Improvements
The child plugin currently duplicates some API logic and has hardcoded URLs.
- **Leverage Parent SDK**: Instead of building a new API client, we will strictly use `Trustap\PaymentGateway\Controller\AbstractController` and `Trustap\PaymentGateway\Enumerators\Uri`.
- **Fix Guest User Creation**: Move the hardcoded API calls in `create_trustap_guest_user_on_registration` to use an extended `AbstractController` to ensure the correct `Uri::API_URL()` is used.
- **User Management Layer**: Create a `Trustap_User_Manager` in the child plugin to handle the mapping between WP User IDs $\rightarrow$ Guest IDs $\rightarrow$ Full IDs.
- **Order Completion Logic**: 
    - **Vendors**: Strictly prohibited from marking orders as completed.
    - **Buyers**: Primary actors responsible for marking orders as completed upon satisfaction.
    - **Admins**: Authorized to mark orders as completed as a fallback (e.g., when the vendor is unavailable or unreachable).

---

## 🚀 Implementation Roadmap

### Phase 1: Foundation & Critical Fixes
- [x] **Task 1.1: Remove Hardcoded URLs**
    - Replace hardcoded `api-sandbox.trustap.com` in `Admin::create_trustap_guest_user_on_registration` with calls to the `AbstractController`.

- [x] **Task 1.2: Standardize API Interaction**
    - Ensure all new features (Claims, Cancellations) extend `AbstractController` to benefit from centralized header and environment management.

- [x] **Task 1.3: User Management Setup**
    - Create `includes/class-trustap-user-manager.php` to centralize retrieval of Guest and Full IDs.

- [x] **Task 1.4: Fix Autoloading/Dependency Issue**
    - Move plugin requirements into `run_t4e_pg_trustap()` to ensure parent plugin `trustap-payment-gateway` autoloader is registered before child classes are loaded.

### Phase 2: Vendor Connection (OAuth Flow)
- [x] **Task 2.1: Vendor Dashboard UI**
    - Add a "Trustap Connection" section to the WCFM Vendor Dashboard.
    - Display status: `Connected` (with Full ID) or `Not Connected` (Guest only).
    - Add "Connect Full Account" button.

- [x] **Task 2.2: OAuth Linkage**
    - Trigger `WCFM_Trustap_API::get_auth_url()`.
    - On callback, save `trustap_{env}_user_id` to user meta.

### Phase 3: The Claim Functionality (Technical Flow)
- [x] **Task 3.1: Claim API Implementation**
    - Create a method that calls `POST /api/v1/p2p/transactions/{id}/claim_for_seller`.
    - **Technical Requirement**: Use the Vendor's **Full User ID** in the `Trustap-User` header.

- [x] **Task 3.2: Automated Claim Trigger (Post-Complaint Period)**
    - Logic: `confirm_handover()` $\rightarrow$ Transaction enters Complaint Period $\rightarrow$ **Verify Complaint Period is Completed** $\rightarrow$ Check for Full User ID $\rightarrow$ Call `claim_transaction()`.
    - Integration: Use Trustap API to check if the transaction status has moved to a "claimable" state (e.g., `completed` or `Funds Released`).

- [x] **Task 3.3: Manual Claim Trigger (Admin/Vendor)**
    - Add "Claim Funds" button in Order Meta Box.
    - **Constraint**: Button is disabled/hidden until the complaint period is officially over.
    - Add tooltips explaining: "Funds can be claimed after the complaint period expires."

- [x] **Task 3.4: Claim Verification**
    - Verify `seller_is_guest` becomes `false` in the Trustap response after claim.

- [x] **Task 3.5: Complaint Period Monitoring**
    - Implement a check (via API or Webhook) to detect when the complaint period ends, triggering a notification to the vendor that they can now claim their funds.

### Phase 4: Order Cancellation Feature (Skipped for now - to be discussed later)
- [ ] **Task 4.1: Cancellation Logic**
- [ ] **Task 4.2: Admin Cancellation UI**
- [ ] **Task 4.3: WooCommerce Sync**

### Phase 5: Extended Features (API-based)
- [x] **Task 5.1: Vendor Balance Check**
    - Endpoint: `GET /api/v1/me/balances`.
    - Display current Trustap balance in the Vendor Dashboard.

- [x] **Task 5.2: Payout Status Tracking**
    - Endpoint: `GET /api/v1/me/profile/payout_status`.
    - Notify vendor if they are not yet eligible to receive payouts.

### Phase 6: Testing & Validation
- [ ] **Task 6.1: Guest $\rightarrow$ Full Claim Cycle**
    - Test: Register Vendor $\rightarrow$ Connect OAuth $\rightarrow$ Create Transaction $\rightarrow$ Handover $\rightarrow$ Claim $\rightarrow$ Verify Balance.

- [ ] **Task 6.2: Cancellation Cycle**
    - Test: Create Transaction $\rightarrow$ Trigger Cancellation $\rightarrow$ Verify Buyer Deposit Return.

---

## 👥 Feature Matrix

| Feature | Vendor Capability | Admin Capability | Technical Implementation |
| :--- | :--- | :--- | :--- |
| **Account Link** | Connect Full Account via OAuth | View Vendor Connection Status | OAuth2 $\rightarrow$ User Meta |
| **Handover** | Confirm Handover (Guest) | Force Confirm Handover | `confirm_handover` endpoint |
| **Claiming** | Receive funds to Full Account | Trigger Claim on behalf of Vendor | `claim_for_seller` endpoint |
| **Cancellation** | Request Cancellation | Execute Cancellation | `cancel_with_description` endpoint |
| **Finances** | View Trustap Balances | Monitor Transaction Status | `me/balances` endpoint |

## 🛠️ Technical Guardrails for Claims
To ensure a vendor can technically extract their transaction:
1. **The OAuth Lock**: The `claim_for_seller` API will return `401` if the user has not completed the OAuth flow. The system must check for the existence of a Full User ID *before* calling the API.
2. **The Header**: Unlike guest calls, the Claim call **must** use the Full User ID in the `Trustap-User` header, not the API Key.
3. **The State**: Claiming is only possible *after* the Handover is confirmed. The system must verify the transaction status is `completed` (or equivalent) before attempting a claim.
