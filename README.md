# Orangecat_PurchaseOrder

Purchase order approval workflow for B2B buyers — intercepts checkout, holds carts as reviewable purchase orders, and converts approved ones into real Magento sales orders.

**Module:** `Orangecat_PurchaseOrder`
**Version:** 1.0.0
**License:** OSL-3.0
**Author:** Oliverio Gombert <olivertar@gmail.com>

---

## Table of Contents

1. [Overview](#overview)
2. [Theme Compatibility](#theme-compatibility)
3. [Requirements](#requirements)
4. [Installation](#installation)
5. [What Gets Installed](#what-gets-installed)
6. [Configuration](#configuration)
7. [Store Admin Guide](#store-admin-guide)
8. [Buyer Guide (Frontend)](#buyer-guide-frontend)
9. [Developer Guide](#developer-guide)
10. [REST API](#rest-api)
11. [Frontend Routes Reference](#frontend-routes-reference)
12. [DevOps & Integrator Notes](#devops--integrator-notes)

---

## Overview

`Orangecat_PurchaseOrder` adds a purchase order approval workflow to the Magento 2 B2B checkout. When a Company Buyer's cart meets configurable spending thresholds, the normal checkout is intercepted and the cart is held as a **Purchase Order** requiring review by a Company Admin or Manager before a real Magento sales order is created.

The module handles:

- Transparent checkout interception — no change to the buyer's checkout UI; the flow ends on the standard success page with PO confirmation instead of an order number
- Per-buyer approval rules: maximum single-purchase amount and maximum monthly spending limit
- Frozen price snapshots — prices captured at PO creation are restored exactly at approval time, regardless of later catalog changes
- PO lifecycle management: create → approve/reject/cancel/expire
- Full audit log of every PO action with actor and comment
- Email notifications to the company admin on creation, and to the buyer on approval and rejection
- Frontend dashboard for buyers (My Purchase Orders) and approvers (Company Purchase Orders)

### Position in the Orangecat B2B Dependency Chain

```
Orangecat_Core (via composer: orangecat/core)
  └── Orangecat_Company
        └── Orangecat_PurchaseOrder          ← this module
```

This module depends on `Orangecat_Company` for company membership, role resolution, and per-buyer limit values stored in the `mycompany_customer` table.

### Purchase Order State Machine

A PO can only move forward; there is no way to revert a terminal state.

```
[Checkout triggers rule]
        │
        ▼
  pending_approval  ──── approve (Admin/Manager) ──▶  order_placed  (Magento order created)
        │
        ├── reject  (Admin/Manager) ──▶  rejected
        ├── cancel  (Buyer/creator) ──▶  canceled
        └── expires_at < now        ──▶  expired      (detected at approval attempt)
```

| Status | Constant | Description |
|---|---|---|
| `pending_approval` | `STATUS_PENDING_APPROVAL` | Awaiting review. Counts against the buyer's period spend. |
| `approved` | `STATUS_APPROVED` | Internal transitional state (reserved for future async flows). |
| `rejected` | `STATUS_REJECTED` | Denied by Admin/Manager. Funds released from period calculation. |
| `order_placed` | `STATUS_ORDER_PLACED` | Converted to a Magento order. Terminal success state. |
| `canceled` | `STATUS_CANCELED` | Canceled by the original buyer. Terminal. |
| `expired` | `STATUS_EXPIRED` | Validity window passed. Set automatically when an expired PO is submitted for approval. Terminal. |

---

## Theme Compatibility

| Theme | Status | Notes |
|---|---|---|
| **Luma** | Supported | Standard `.phtml` templates. JS via RequireJS (`purchase-order-actions.js`). Layout handles use the `purchaseorder_*` prefix. |
| **Hyvä** | Supported | Dedicated `*_hyva.phtml` templates (no Alpine.js required — plain PHP/HTML). Custom CSS loaded from `web/css/hyva/module.css` via `hyva_default.xml`. Layout handles use the `hyva_purchaseorder_*` prefix. |
| **Breeze Evolution** | Supported | JS bundled via `breeze_default.xml`. Uses the same `*_hyva.phtml` templates as Hyvä for list and view pages. The `purchase-order-actions.js` component registers itself on the Breeze map automatically. |

---

## Requirements

| Dependency | Version / Notes |
|---|---|
| Magento 2 | 2.4.x |
| PHP | >= 8.1 |
| `orangecat/core` | composer dependency |
| `Orangecat_Company` | must be installed and enabled first |
| `Magento_Checkout` | |
| `Magento_Quote` | |
| `Magento_Sales` | |
| `Magento_Customer` | |
| `Magento_CatalogInventory` | stock checks at approval time |

---

## Installation

### Via Git Submodule (recommended for this project)

```bash
# From repo root
git submodule add git@github.com:olivertar/m2_purchase_order.git app/code/Orangecat/PurchaseOrder
git submodule update --init --recursive
```

### Enable the Module

Run inside the PHP container (`reward shell`):

```bash
bin/magento module:enable Orangecat_PurchaseOrder
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

---

## What Gets Installed

### Database Tables

#### `purchase_order`

| Column | Type | Notes |
|---|---|---|
| `entity_id` | `int unsigned` | Auto-increment PK |
| `increment_id` | `varchar(50)` | Human-readable ID, format `PO-{YEAR}-{000001}`. Null until assigned after first save. |
| `quote_id` | `int unsigned` | Source Magento quote. FK to `quote` (no DB constraint — quote may be cleaned up). |
| `company_id` | `int unsigned` | FK → `mycompany.entity_id`. CASCADE DELETE. |
| `creator_id` | `int unsigned` | FK → `customer_entity.entity_id`. SET NULL on customer delete. |
| `status` | `varchar(50)` | See state machine above. |
| `grand_total` | `decimal(12,4)` | Grand total captured at PO creation. |
| `snapshot` | `mediumtext` | JSON blob — full cart state frozen at creation (items, prices, shipping, payment method). |
| `order_id` | `int unsigned` | Magento order ID after approval. Null until order is placed. |
| `order_increment_id` | `varchar(50)` | Magento order increment ID (e.g. `000000042`). Null until order is placed. |
| `expires_at` | `timestamp` | Expiry datetime. Null = never expires. |
| `created_at` | `timestamp` | Auto-set to `CURRENT_TIMESTAMP`. |
| `updated_at` | `timestamp` | Auto-updated on every write. |

#### `purchase_order_log`

| Column | Type | Notes |
|---|---|---|
| `log_id` | `int unsigned` | Auto-increment PK |
| `purchase_order_id` | `int unsigned` | FK → `purchase_order.entity_id`. CASCADE DELETE. |
| `actor_id` | `int unsigned` | Customer who performed the action. Null if system-initiated. |
| `action` | `varchar(100)` | Action name: `created`, `approved`, `rejected`, `canceled`, `expired`, `place_order_failed`. |
| `comment` | `text` | Optional free-text reason (mandatory for `rejected`). |
| `created_at` | `timestamp` | Auto-set to `CURRENT_TIMESTAMP`. |

### EAV Attributes

None. This module does not install any EAV attributes.

### Data Patches

None. No default records, roles, CMS pages, or seed data are installed by this module.

---

## Configuration

**Path:** `Stores > Configuration > Orangecat > Company (B2B) > Purchase Orders`

The settings live under the `mycompany` section defined by `Orangecat_Company`. This module adds the `purchase_orders` group to that section.

### Purchase Orders

| Label | Config path | Default | Description |
|---|---|---|---|
| Enable Purchase Orders | `mycompany/purchase_orders/enabled` | No | Master switch. When disabled, the checkout plugin is bypassed and all buyers check out normally. |
| Purchase Order Validity (Days) | `mycompany/purchase_orders/po_validity_time` | 30 | Number of days from creation before a PO expires. Set to `0` to disable expiry. |

```
mycompany/purchase_orders/enabled
mycompany/purchase_orders/po_validity_time
```

### Per-Buyer Spending Limits

Approval rule thresholds are **not** stored in `core_config_data`. They are per-customer fields in the `mycompany_customer` table, managed by `Orangecat_Company`:

| Field | Column | Description |
|---|---|---|
| Max Purchase Amount | `mycompany_customer.max_purchase_amount` | Single-order ceiling. A cart grand total exceeding this value requires PO approval. Null or 0 = no limit. |
| Max Period Amount | `mycompany_customer.max_period_amount` | Monthly spending ceiling. When `(committed_this_month + cart_total) > limit`, approval is required. Null or 0 = no limit. |

Set these values in Admin > Customers > All Customers > (edit customer) > Company Assignment.

---

## Store Admin Guide

This module does not add an admin grid for purchase orders. PO management is handled entirely on the frontend by Company Admins and Managers (see [Buyer Guide](#buyer-guide-frontend)). Store administrators interact with this module via:

### Enabling the Feature

1. Go to **Stores > Configuration > Orangecat > Company (B2B) > Purchase Orders**.
2. Set **Enable Purchase Orders** to **Yes**.
3. Optionally set **Purchase Order Validity (Days)** (default: 30; 0 = no expiry).
4. Save configuration and flush cache.

### Setting Buyer Spending Limits

1. Go to **Customers > All Customers**.
2. Open the target buyer's customer record.
3. Open the **Company Assignment** tab.
4. Set **Max Purchase Amount** and/or **Max Period Amount**.
5. Save the customer.

> Both fields are defined and rendered by `Orangecat_Company`. If the tab is missing, ensure `Orangecat_Company` is enabled.

### Reviewing Purchase Orders

Store admins can query the `purchase_order` and `purchase_order_log` tables directly via a database shell or a custom report. No admin UI grid is provided in this version.

---

## Buyer Guide (Frontend)

### Checkout Behavior

When a Company Buyer proceeds to checkout and submits payment:

- If the cart total exceeds the buyer's **Max Purchase Amount**, or the projected monthly spend exceeds **Max Period Amount**, checkout is intercepted.
- No error is shown. The buyer lands on the standard success page (`/checkout/onepage/success`) with a message confirming the PO number instead of an order number.
- The buyer's cart is cleared. A PO is created in **Pending Approval** status.
- An email notification is sent to the Company Admin.

If neither threshold is exceeded, checkout completes normally as a Magento order.

### My Purchase Orders

**URL:** `/purchaseorder/order/index`
**Navigation:** Account Dashboard > **My Purchase Orders** (visible when the module is enabled)

Shows all POs created by the logged-in buyer, sorted newest first. Columns: PO number, date, status, total.

Each row links to the PO detail page.

### Purchase Order Detail

**URL:** `/purchaseorder/order/view?id={entity_id}`

Shows the full PO snapshot: line items, totals, shipping method, payment method, status, expiry date, and audit log.

**Available actions (buyer):**

| Action | Condition |
|---|---|
| Cancel | PO is in `pending_approval` status and the buyer is the original creator. |

### Company Purchase Orders (Admins and Managers only)

**URL:** `/purchaseorder/order_manage/index`
**Navigation:** Account Dashboard > **Company Purchase Orders** (visible to Company Admin and Manager roles only)

Shows all POs from all buyers in the company, sorted newest first.

**Available actions:**

| Action | Condition |
|---|---|
| Approve | PO is `pending_approval`. Requires confirmation. Creates Magento order immediately using frozen prices. |
| Reject | PO is `pending_approval`. Prompts for a mandatory rejection reason comment. |

After approval, a success message shows the generated Magento order number. After rejection, the buyer receives an email.

---

## Developer Guide

### Module Structure

```
Orangecat/PurchaseOrder/
├── Api/
│   ├── Data/
│   │   ├── PurchaseOrderInterface.php        Status constants + getters/setters
│   │   ├── PurchaseOrderLogInterface.php
│   │   ├── PurchaseOrderSearchResultsInterface.php
│   │   └── PurchaseOrderLogSearchResultsInterface.php
│   ├── PurchaseOrderRepositoryInterface.php  save/getById/getList/delete
│   └── PurchaseOrderLogRepositoryInterface.php
├── Block/
│   ├── Account/Navigation/Link.php           Conditionally renders "Company POs" nav link
│   └── Order/
│       ├── ListOrder.php                     Buyer's own PO list
│       ├── View.php                          PO detail page
│       └── Manage/ListOrder.php              Admin/Manager company PO list
├── Controller/
│   ├── Order/
│   │   ├── Index.php                         GET purchaseorder/order/index
│   │   ├── View.php                          GET purchaseorder/order/view
│   │   └── Cancel.php                        POST purchaseorder/order/cancel
│   └── Order/Manage/
│       ├── Index.php                         GET purchaseorder/order_manage/index
│       ├── Approve.php                       POST purchaseorder/order_manage/approve
│       └── Reject.php                        POST purchaseorder/order_manage/reject
├── Exception/
│   └── PurchaseOrderCreatedException.php     (unused externally — flow changed to return 0)
├── Model/
│   ├── ApprovalRuleChain.php                 Composite rule evaluator (short-circuit OR)
│   ├── CheckoutState.php                     Request-scoped flag to prevent recursive PO creation
│   ├── Config.php                            Typed config reader
│   ├── PurchaseOrder.php                     Entity model
│   ├── PurchaseOrderLog.php
│   ├── PurchaseOrderManagement.php           Central service: create/approve/reject/cancel/expire
│   ├── PurchaseOrderRepository.php
│   ├── PurchaseOrderLogRepository.php
│   ├── ResourceModel/
│   │   ├── PurchaseOrder.php + Collection.php
│   │   └── PurchaseOrderLog.php + Collection.php
│   └── Rule/
│       ├── ApprovalRuleInterface.php         needsApproval(CartInterface, int): bool
│       ├── MaxPurchaseAmountRule.php         Single-order ceiling rule
│       └── MaxPeriodAmountRule.php           Monthly period ceiling rule
├── Plugin/Checkout/
│   ├── PlaceOrderPlugin.php                  around QuoteManagement::placeOrder()
│   └── PaymentInformationPlugin.php          around PaymentInformationManagement::save*
├── ViewModel/Checkout/
│   └── Success.php                           Reads PO data from session for success page
└── view/frontend/
    ├── layout/                               Luma (purchaseorder_*), Hyvä (hyva_*), Breeze (breeze_default)
    ├── templates/                            Paired *.phtml + *_hyva.phtml for all pages
    ├── email/                                purchaseorder_created/approved/rejected.html
    └── web/
        ├── css/hyva/module.css               Hyvä-specific styles
        └── js/purchase-order-actions.js      Confirm/prompt dialogs for approve/reject/cancel
```

### Service Contracts

#### `PurchaseOrderRepositoryInterface`

```php
save(PurchaseOrderInterface $purchaseOrder): PurchaseOrderInterface
getById(int $id): PurchaseOrderInterface       // throws NoSuchEntityException
getList(SearchCriteriaInterface $criteria): PurchaseOrderSearchResultsInterface
delete(PurchaseOrderInterface $purchaseOrder): bool
deleteById(int $id): bool
```

#### `PurchaseOrderManagement`

Main service class — no interface (inject the concrete class).

```php
createFromQuote(CartInterface $quote, int $customerId, string $triggeredByRule = ''): PurchaseOrderInterface
approvePurchaseOrder(int $poId, int $actorId): OrderInterface
rejectPurchaseOrder(int $poId, int $actorId, string $comment = ''): void
cancelPurchaseOrder(int $poId, int $actorId): void
isPurchaseOrderExpired(PurchaseOrderInterface $purchaseOrder): bool
checkStockForSnapshot(array $snapshotItems): void   // throws LocalizedException on insufficient stock
```

`approvePurchaseOrder` runs four guards in sequence: status check → actor is Admin/Manager → not expired → stock available. If any guard fails it throws `LocalizedException`; the PO status is not mutated unless approval succeeds.

#### `ApprovalRuleInterface`

```php
needsApproval(CartInterface $quote, int $customerId): bool
getRuleName(): string
```

`ApprovalRuleChain` implements this interface (Composite pattern). Rules are short-circuit OR: the first rule returning `true` stops evaluation.

### Observers

This module does not register any observers.

### Plugins

| Class | Target | Hook | Purpose |
|---|---|---|---|
| `Plugin\Checkout\PlaceOrderPlugin` | `Magento\Quote\Model\QuoteManagement::placeOrder()` | `around` | Checks approval rules for Company Buyers. Creates PO and returns `0` when approval is needed; otherwise calls `$proceed`. |
| `Plugin\Checkout\PaymentInformationPlugin` | `Magento\Checkout\Api\PaymentInformationManagementInterface::savePaymentInformationAndPlaceOrder()` | `around` | Clears stale PO session data when a normal order completes (result ≠ 0). |
| `Plugin\Checkout\PaymentInformationPlugin` | `Magento\Checkout\Api\GuestPaymentInformationManagementInterface::savePaymentInformationAndPlaceOrder()` | `around` | Same as above for guest checkout path. |

`PlaceOrderPlugin` uses `CheckoutState::setIsApprovalMode(true)` to prevent recursive PO creation when `PurchaseOrderManagement` itself calls `CartManagement::placeOrder()` during approval.

### JS Components

#### Frontend

| File | Usage | Notes |
|---|---|---|
| `web/js/purchase-order-actions.js` | Luma + Breeze | RequireJS component initializer. Wires confirm/prompt/alert dialogs to `.action-approve`, `.action-reject`, `.action-cancel` buttons. Registers on the Breeze component map automatically via `$.breezemap`. |

No Alpine.js components are needed; Hyvä templates use plain PHP/HTML with standard form submissions.

### Email Templates

| Template ID | File | Trigger |
|---|---|---|
| `purchaseorder_email_created` | `purchaseorder_created.html` | PO created — sent to Company Admin when a buyer's checkout produces a PO |
| `purchaseorder_email_approved` | `purchaseorder_approved.html` | PO approved — sent to the buyer when a PO is approved and converted to an order |
| `purchaseorder_email_rejected` | `purchaseorder_rejected.html` | PO rejected — sent to the buyer with the rejection comment |

Templates are in `view/frontend/email/`. Customize via Admin > Marketing > Email Templates.

> Email sending is triggered inside `PurchaseOrderManagement` — the actual send calls are not yet wired in this version. The templates are registered and ready; connect them via the management service or an observer as needed.

### ACL Resources

This module does not define an `acl.xml`. No admin-panel ACL resources are registered. Frontend access control is enforced directly in controllers by checking the customer session and company role.

### Adding Custom Logic — Extension Points

- **New approval rule:** implement `Model\Rule\ApprovalRuleInterface` and add a `<item>` entry under the `Orangecat\PurchaseOrder\Model\ApprovalRuleChain` `rules` argument in your module's `di.xml`. No existing files need modification.
- **Custom PO data:** `PurchaseOrderInterface` extends `ExtensibleDataInterface` — add extension attributes via your module's `extension_attributes.xml`.
- **Post-approval hook:** observe the native `sales_order_place_after` event or add a plugin on `PurchaseOrderManagement::approvePurchaseOrder()` to react when a PO converts to an order.

---

## REST API

This module does not expose any REST API endpoints. No `webapi.xml` is defined.

To query purchase orders programmatically, use the `PurchaseOrderRepositoryInterface` service contract directly from PHP, or add a REST endpoint in a custom module by declaring it in your own `webapi.xml` against the existing repository.

---

## Frontend Routes Reference

| Route | Controller | Access |
|---|---|---|
| `GET /purchaseorder/order/index` | `Controller\Order\Index` | Any logged-in customer |
| `GET /purchaseorder/order/view?id={id}` | `Controller\Order\View` | PO creator, or company Admin/Manager of the same company |
| `POST /purchaseorder/order/cancel` | `Controller\Order\Cancel` | PO creator only, PO must be `pending_approval` |
| `GET /purchaseorder/order_manage/index` | `Controller\Order\Manage\Index` | Company Admin or Manager only |
| `POST /purchaseorder/order_manage/approve` | `Controller\Order\Manage\Approve` | Company Admin or Manager only |
| `POST /purchaseorder/order_manage/reject` | `Controller\Order\Manage\Reject` | Company Admin or Manager only |

All POST routes validate the Magento form key. Unauthorized access redirects to `customer/account` with an error message.

---

## DevOps & Integrator Notes

### Deployment Checklist

```bash
# Enable Orangecat_Company first if not already enabled
bin/magento module:enable Orangecat_Company

# Enable this module
bin/magento module:enable Orangecat_PurchaseOrder
bin/magento setup:upgrade          # creates purchase_order and purchase_order_log tables
bin/magento setup:di:compile       # compiles interceptors for PlaceOrderPlugin
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

After deployment, go to **Stores > Configuration > Orangecat > Company (B2B) > Purchase Orders** and enable the feature.

### Integration Token Scope

This module has no REST API and no ACL resources. No integration token configuration is required.

### Disabling Without Uninstalling

```bash
bin/magento module:disable Orangecat_PurchaseOrder
bin/magento setup:upgrade
bin/magento cache:flush
```

When disabled, `PlaceOrderPlugin` is bypassed (gate check on `Config::isEnabled()` returns false) and all buyers check out normally. The `purchase_order` and `purchase_order_log` tables are not removed.

### Data Integrity

- Deleting a company (`mycompany`) cascades to all its `purchase_order` rows, which cascades to `purchase_order_log`.
- Deleting a customer sets `creator_id` to NULL on their POs (SET NULL constraint). The PO record is preserved for audit purposes.
- Deleting a customer does not delete POs where that customer was the approver (`actor_id` in `purchase_order_log` has no FK constraint).
- The `snapshot` column stores a complete cart freeze. If products are deleted from the catalog after a PO is created, approval can still proceed using the frozen prices, but the stock check may fail if inventory is also removed.
- `increment_id` format is `PO-{YEAR}-{entity_id zero-padded to 6 digits}` (e.g. `PO-2026-000042`). The year in the ID reflects the year at creation time and does not change if the PO spans a year boundary before approval.
