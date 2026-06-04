# Security Audit — Orangecat_PurchaseOrder

**Date:** 2026-06-04
**Auditor:** Claude Code (automated static analysis)
**Scope:** `app/code/Orangecat/PurchaseOrder` — all PHP, templates, schema, and configuration files
**Magento version:** 2.4.8-p5

---

## Executive Summary

No critical vulnerabilities found. The module correctly implements CSRF protection, SQL injection prevention, XSS escaping, session authentication, and a comprehensive audit trail. Two findings require attention: one medium-severity defense-in-depth gap and three low-severity issues.

---

## Findings

### MEDIUM

#### M-01 — Approve/Reject controllers missing explicit company boundary check

**Files:**
- `Controller/Order/Manage/Approve.php:58`
- `Controller/Order/Manage/Reject.php:58`

**Detail:**
Both controllers validate that the actor has an Admin or Manager role ID, but do not verify that the actor belongs to the same company as the target purchase order before invoking the service layer.

```php
// Approve.php:56-58 (same pattern in Reject.php)
$roleId = (int)$this->companyManagement->getRoleIdByCustomerId($customerId);
if ($roleId !== RoleInterface::ADMIN_ROLE_ID && $roleId !== RoleInterface::MANAGER_ROLE_ID) {
    // redirects — but a Manager from Company B reaches the service for Company A's PO
}
```

The service layer does enforce the company boundary via `assertActorIsApprover()` (`Model/PurchaseOrderManagement.php:576-581`), which throws a `LocalizedException` and causes the controller to display a generic error. This means the actual security boundary holds, but the defense-in-depth principle is violated: the controller grants access to the service before the authorization check occurs.

**Recommendation:**
Load the PO at the controller level and compare company IDs before calling the service:

```php
$poId   = (int)$this->getRequest()->getParam('id');
$po     = $this->purchaseOrderRepository->getById($poId);
$userCo = (int)$this->companyManagement->getCompanyIdByCustomerId($customerId);
if ($userCo !== (int)$po->getCompanyId()) {
    $this->messageManager->addErrorMessage(__('Access denied.'));
    return $this->_redirect('customer/account');
}
```

**Risk:** Medium (boundary enforced at service layer; no exploit path exists today, but layered defenses required by Magento security policy).

---

### LOW

#### L-01 — Block loads purchase order by ID without authorization check

**File:** `Block/Order/View.php:71-79`

```php
public function getPurchaseOrder()
{
    $id = $this->getRequest()->getParam('id');
    try {
        return $this->purchaseOrderRepository->getById((int)$id);
    } catch (\Exception $e) {
        return null;
    }
}
```

The block fetches the PO directly from the request parameter with no ownership or company verification. Access is currently gated by the controller (`Controller/Order/View.php:51-85`), which validates that the current customer is either the PO creator or an Admin/Manager of the same company. The block is never rendered without passing the controller gate.

However, if the block is referenced from another layout handle in the future (e.g., a widget or third-party module), it would expose PO data to unauthorized users.

**Recommendation:**
Add a guard in `getPurchaseOrder()` that validates the current customer can access the loaded PO, or add a dedicated `isAllowed(PurchaseOrderInterface $po): bool` method to the block and call it before rendering in the template.

**Risk:** Low (currently safe due to controller gate; fragile to future layout changes).

---

#### L-02 — Role names are hard-coded strings with no cross-module contract

**File:** `Model/Config.php:41,46`

```php
public const APPROVER_ROLE_NAMES = ['Company Admin', 'Company Manager'];
public const BUYER_ROLE_NAME     = 'Company Buyer';
```

Authorization depends on exact string equality between these constants and the role names defined in `Orangecat_Company`. If the Company module's role names change (e.g., via translation, refactor, or data migration), the PO authorization silently breaks — approvals fail with no warning, and buyers stop generating POs.

**Recommendation:**
- Define role name constants in `Orangecat_Company` (e.g., `Orangecat\Company\Model\Config::ROLE_NAME_ADMIN`) and import them here.
- Add an integration test that asserts `Config::APPROVER_ROLE_NAMES` values exist in the `company_roles` table after `setup:upgrade`.

**Risk:** Low (unlikely to change in practice; high impact if it does change, silent failure mode).

---

#### L-03 — PO snapshot has no cryptographic integrity check

**File:** `Model/PurchaseOrderManagement.php:427-478`

The price and item snapshot is stored as plain JSON text (`mediumtext` column). During approval, prices are restored from this snapshot (`lines 541-563`). There is no hash or signature to detect tampering at the database level.

In a standard single-server deployment this is acceptable, but in environments with direct DB access (DBA accounts, DB replication users, compromised DB credentials), snapshot prices could be silently altered before approval, resulting in orders placed at fraudulent prices.

**Recommendation (optional):**
Store a SHA-256 HMAC of the snapshot JSON at creation time and verify it during `approvePurchaseOrder()`:

```php
// Creation
$snapshot = $this->buildSnapshot($quote);
$hash     = hash_hmac('sha256', $snapshot, $this->deploymentConfig->get('crypt/key'));
$purchaseOrder->setSnapshot($snapshot);
$purchaseOrder->setSnapshotHash($hash);

// Approval
$computed = hash_hmac('sha256', $purchaseOrder->getSnapshot(), $this->deploymentConfig->get('crypt/key'));
if (!hash_equals($computed, $purchaseOrder->getSnapshotHash())) {
    throw new LocalizedException(__('Purchase order data integrity check failed.'));
}
```

**Risk:** Low (requires DB-level access to exploit; acceptable without the fix for typical deployments).

---

#### L-04 — Reject comment stored without server-side sanitization

**File:** `Controller/Order/Manage/Reject.php:71`

```php
$comment = (string)$this->getRequest()->getParam('comment', '');
```

The comment is cast to string and persisted as-is. Output escaping in the template (`$escaper->escapeHtml()`) prevents XSS at display time. However, if the comment is ever used in an email notification, PDF, or admin grid without escaping, stored XSS becomes possible.

**Recommendation:**
Apply `strip_tags()` before persisting, or enforce `$escaper->escapeHtml()` at the point of storage rather than relying on all future consumers to escape correctly. Document the escaping contract clearly in the template.

**Risk:** Low (XSS mitigated at template layer; risk increases if the field is reused in new contexts).

---

## Passed Controls

| Control | Location | Result |
|---|---|---|
| CSRF / Form key validation | `Controller/Order/Cancel.php:51`, `Approve.php:65`, `Reject.php:65` | PASS |
| Session authentication on all controllers | All 5 controllers, `isLoggedIn()` check | PASS |
| SQL injection — parameterized ORM queries | All ResourceModel and Collection queries | PASS |
| XSS — output escaping in templates | All `.phtml` files use `$escaper->escapeHtml()`, `escapeHtmlAttr()`, `escapeJs()` | PASS |
| Mass assignment — no `setData($_POST)` | All controllers and management class | PASS |
| State machine guards — status pre-condition | `PurchaseOrderManagement.php:185-191` | PASS |
| Cross-company approval prevention | `assertActorIsApprover()` lines 576-581 | PASS |
| Creator-only cancellation | `cancelPurchaseOrder()` lines 317-322 | PASS |
| Audit trail on all state transitions | `PurchaseOrderLogRepository` called on create/approve/reject/cancel/expire | PASS |
| No REST/GraphQL endpoints exposed | No `webapi.xml` present | PASS |
| `CheckoutState` recursion guard (`finally` block) | `PurchaseOrderManagement.php:525-527` | PASS |
| Approval rules safe-default (empty = allow checkout) | `ApprovalRuleChain.php:68-85` | PASS |

---

## Remediation Status

| ID | Severity | Status | Notes |
|---|---|---|---|
| M-01 | Medium | **FIXED** | Company check added to `Approve.php` and `Reject.php` controllers |
| L-01 | Low | **FIXED** | Auth guard added to `Block/Order/View::getPurchaseOrder()` |
| L-02 | Low | **DOCUMENTED** | Cross-module contract warning added to `Model/Config.php`; integration test recommended |
| L-03 | Low | **FIXED** | `snapshot_hash` column added; HMAC-SHA256 computed on create, verified on approve |
| L-04 | Low | **FIXED** | `strip_tags()` applied to reject comment in `Reject.php` |
