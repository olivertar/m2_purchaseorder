<?php

/**
 * This file is part of the Orangecat PurchaseOrder package.
 *
 * (c) Oliverio Gombert <olivertar@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Orangecat\PurchaseOrder\Model;

use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Orangecat\Company\Api\CompanyManagementInterface;
use Orangecat\Company\Api\RoleRepositoryInterface;
use Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Orangecat\PurchaseOrder\Model\PurchaseOrderFactory as PurchaseOrderInterfaceFactory;
use Orangecat\PurchaseOrder\Api\PurchaseOrderLogRepositoryInterface;
use Orangecat\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Central service for all Purchase Order lifecycle operations.
 *
 * Responsibilities:
 *  - createFromQuote()        Create a PO from an active quote, freezing prices.
 *  - approvePurchaseOrder()   Validate + convert an approved PO to a Magento Order.
 *  - rejectPurchaseOrder()    Mark a PO as rejected (admin/manager action).
 *  - cancelPurchaseOrder()    Mark a PO as canceled (buyer-initiated).
 *  - checkStockForSnapshot()  Public stock check used before approval.
 *  - isPurchaseOrderExpired() Expiry check (also used by UI blocks).
 */
class PurchaseOrderManagement
{
    /**
     * @param PurchaseOrderRepositoryInterface    $purchaseOrderRepository
     * @param PurchaseOrderLogRepositoryInterface $purchaseOrderLogRepository
     * @param PurchaseOrderInterfaceFactory       $purchaseOrderFactory
     * @param CompanyManagementInterface          $companyManagement
     * @param RoleRepositoryInterface             $roleRepository
     * @param CartRepositoryInterface             $cartRepository
     * @param CartManagementInterface             $cartManagement
     * @param StoreManagerInterface               $storeManager
     * @param StockRegistryInterface              $stockRegistry
     * @param OrderRepositoryInterface            $orderRepository
     * @param Config                              $config
     * @param LoggerInterface                     $logger
     * @param Json                                $json
     */
    public function __construct(
        private readonly PurchaseOrderRepositoryInterface    $purchaseOrderRepository,
        private readonly PurchaseOrderLogRepositoryInterface $purchaseOrderLogRepository,
        private readonly PurchaseOrderInterfaceFactory       $purchaseOrderFactory,
        private readonly CompanyManagementInterface          $companyManagement,
        private readonly RoleRepositoryInterface             $roleRepository,
        private readonly CartRepositoryInterface             $cartRepository,
        private readonly CartManagementInterface             $cartManagement,
        private readonly StoreManagerInterface               $storeManager,
        private readonly StockRegistryInterface              $stockRegistry,
        private readonly OrderRepositoryInterface            $orderRepository,
        private readonly Config                              $config,
        private readonly LoggerInterface                     $logger,
        private readonly Json                                $json,
        private readonly CheckoutState                       $checkoutState
    ) {
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Create a Purchase Order from an active shopping cart.
     *
     * The source quote is deactivated after snapshot is taken so the buyer
     * cannot accidentally re-submit the same cart.
     *
     * @param CartInterface $quote          The active cart being checked out.
     * @param int           $customerId     The buyer creating the PO.
     * @param string        $triggeredByRule Human-readable rule name for audit log.
     * @return PurchaseOrderInterface
     * @throws LocalizedException
     */
    public function createFromQuote(
        CartInterface $quote,
        int $customerId,
        string $triggeredByRule = ''
    ): PurchaseOrderInterface {
        $companyId = $this->companyManagement->getCompanyIdByCustomerId($customerId);

        if (!$companyId) {
            throw new LocalizedException(__(
                'Cannot create a purchase order: customer %1 is not associated with a company.',
                $customerId
            ));
        }

        // Freeze prices and cart state into a JSON snapshot BEFORE deactivating the quote.
        $snapshot = $this->buildSnapshot($quote);

        // Calculate optional expiry date.
        $expiresAt = null;
        $validityDays = $this->config->getPurchaseOrderValidityDays();
        if ($validityDays > 0) {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$validityDays} days"));
        }

        /** @var PurchaseOrderInterface $purchaseOrder */
        $purchaseOrder = $this->purchaseOrderFactory->create();
        $purchaseOrder->setQuoteId((int) $quote->getId());
        $purchaseOrder->setCompanyId((int) $companyId);
        $purchaseOrder->setCreatorId($customerId);
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING_APPROVAL);
        $purchaseOrder->setGrandTotal((float) $quote->getGrandTotal());
        $purchaseOrder->setSnapshot($snapshot);
        $purchaseOrder->setExpiresAt($expiresAt);

        // First save to obtain the DB-assigned entity_id (auto-increment; no race condition).
        $purchaseOrder = $this->purchaseOrderRepository->save($purchaseOrder);

        // Build the human-readable increment_id from the guaranteed-unique entity_id.
        $purchaseOrder->setIncrementId($this->generateIncrementId((int) $purchaseOrder->getId()));
        $purchaseOrder = $this->purchaseOrderRepository->save($purchaseOrder);

        // Deactivate the quote so the buyer's cart appears empty and cannot be
        // re-submitted through the normal checkout flow.
        $quote->setIsActive(false);
        $this->cartRepository->save($quote);

        // Audit log entry.
        $this->purchaseOrderLogRepository->log(
            (int) $purchaseOrder->getId(),
            'created',
            $customerId,
            $triggeredByRule ? sprintf('Triggered by rule: %s', $triggeredByRule) : null
        );

        $this->logger->info(sprintf(
            '[PurchaseOrder] Created %s — customer %d, company %d, total %.4f, expires %s.',
            $purchaseOrder->getIncrementId(),
            $customerId,
            $companyId,
            $quote->getGrandTotal(),
            $expiresAt ?? 'never'
        ));

        return $purchaseOrder;
    }

    /**
     * Approve a Purchase Order and convert it into a Magento Sales Order.
     *
     * Validation sequence (each step throws on failure; no partial state is written):
     *   1. PO must be in pending_approval status.
     *   2. Actor must be an Admin or Manager of the PO's company.
     *   3. PO must not be expired (expired POs are auto-updated to STATUS_EXPIRED).
     *   4. All snapshot items must have sufficient stock.
     *
     * On success the PO transitions to STATUS_ORDER_PLACED.
     * On placeOrder failure the PO stays in STATUS_PENDING_APPROVAL (retriable).
     *
     * @param int $poId
     * @param int $actorId  Admin or Manager performing the approval.
     * @return OrderInterface
     * @throws LocalizedException
     */
    public function approvePurchaseOrder(int $poId, int $actorId): OrderInterface
    {
        $purchaseOrder = $this->purchaseOrderRepository->getById($poId);

        // --- Guard 1: status ---
        if ($purchaseOrder->getStatus() !== PurchaseOrderInterface::STATUS_PENDING_APPROVAL) {
            throw new LocalizedException(__(
                'Purchase order %1 cannot be approved: current status is "%2".',
                $purchaseOrder->getIncrementId(),
                $purchaseOrder->getStatus()
            ));
        }

        // --- Guard 2: actor permission ---
        $this->assertActorIsApprover($actorId, (int) $purchaseOrder->getCompanyId());

        // --- Guard 3: expiry ---
        if ($this->isPurchaseOrderExpired($purchaseOrder)) {
            $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_EXPIRED);
            $this->purchaseOrderRepository->save($purchaseOrder);
            $this->purchaseOrderLogRepository->log(
                $poId,
                'expired',
                $actorId,
                'PO found expired during approval attempt; status updated.'
            );
            throw new LocalizedException(__(
                'Purchase order %1 has expired and cannot be approved.',
                $purchaseOrder->getIncrementId()
            ));
        }

        // --- Guard 4: stock ---
        $snapshotData = $this->json->unserialize($purchaseOrder->getSnapshot());
        $this->checkStockForSnapshot($snapshotData['items'] ?? []);

        // --- Place the order using frozen prices ---
        try {
            $order = $this->placeOrderFromPurchaseOrder($purchaseOrder, $snapshotData);
        } catch (LocalizedException $e) {
            // Keep PO in pending_approval so the admin can retry after resolving the issue.
            $this->purchaseOrderLogRepository->log(
                $poId,
                'place_order_failed',
                $actorId,
                $e->getMessage()
            );
            $this->logger->critical(sprintf(
                '[PurchaseOrder] Failed to place order for %s: %s',
                $purchaseOrder->getIncrementId(),
                $e->getMessage()
            ));
            throw $e;
        }

        // --- Update PO record ---
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_ORDER_PLACED);
        $purchaseOrder->setOrderId((int) $order->getId());
        $purchaseOrder->setOrderIncrementId($order->getIncrementId());
        $this->purchaseOrderRepository->save($purchaseOrder);

        $this->purchaseOrderLogRepository->log(
            $poId,
            'approved',
            $actorId,
            sprintf('Magento order %s created.', $order->getIncrementId())
        );

        $this->logger->info(sprintf(
            '[PurchaseOrder] Approved %s → Order %s (actor: customer %d).',
            $purchaseOrder->getIncrementId(),
            $order->getIncrementId(),
            $actorId
        ));

        return $order;
    }

    /**
     * Reject a Purchase Order (admin / manager action).
     *
     * The PO is kept in the system for audit purposes; it is not deleted.
     *
     * @param int    $poId
     * @param int    $actorId
     * @param string $comment  Optional rejection reason shown to the buyer.
     * @throws LocalizedException
     */
    public function rejectPurchaseOrder(int $poId, int $actorId, string $comment = ''): void
    {
        $purchaseOrder = $this->purchaseOrderRepository->getById($poId);

        if ($purchaseOrder->getStatus() !== PurchaseOrderInterface::STATUS_PENDING_APPROVAL) {
            throw new LocalizedException(__(
                'Purchase order %1 cannot be rejected: current status is "%2".',
                $purchaseOrder->getIncrementId(),
                $purchaseOrder->getStatus()
            ));
        }

        $this->assertActorIsApprover($actorId, (int) $purchaseOrder->getCompanyId());

        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_REJECTED);
        $this->purchaseOrderRepository->save($purchaseOrder);

        $this->purchaseOrderLogRepository->log($poId, 'rejected', $actorId, $comment ?: null);

        $this->logger->info(sprintf(
            '[PurchaseOrder] Rejected %s by actor %d. Reason: %s',
            $purchaseOrder->getIncrementId(),
            $actorId,
            $comment ?: 'none'
        ));
    }

    /**
     * Cancel a Purchase Order (buyer-initiated cancellation).
     *
     * Only the original creator of the PO may cancel it.
     * Admins and managers should use rejectPurchaseOrder() instead.
     *
     * @param int $poId
     * @param int $actorId  Must equal the PO's creator_id.
     * @throws LocalizedException
     */
    public function cancelPurchaseOrder(int $poId, int $actorId): void
    {
        $purchaseOrder = $this->purchaseOrderRepository->getById($poId);

        if ($purchaseOrder->getStatus() !== PurchaseOrderInterface::STATUS_PENDING_APPROVAL) {
            throw new LocalizedException(__(
                'Purchase order %1 cannot be canceled: current status is "%2".',
                $purchaseOrder->getIncrementId(),
                $purchaseOrder->getStatus()
            ));
        }

        if ((int) $purchaseOrder->getCreatorId() !== $actorId) {
            throw new LocalizedException(__(
                'You do not have permission to cancel purchase order %1.',
                $purchaseOrder->getIncrementId()
            ));
        }

        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_CANCELED);
        $this->purchaseOrderRepository->save($purchaseOrder);
        $this->purchaseOrderLogRepository->log($poId, 'canceled', $actorId);

        $this->logger->info(sprintf(
            '[PurchaseOrder] Canceled %s by creator %d.',
            $purchaseOrder->getIncrementId(),
            $actorId
        ));
    }

    /**
     * Check whether a Purchase Order has passed its expiry date.
     *
     * A PO without an expiry date (expires_at = null) never expires.
     *
     * @param PurchaseOrderInterface $purchaseOrder
     * @return bool
     */
    public function isPurchaseOrderExpired(PurchaseOrderInterface $purchaseOrder): bool
    {
        $expiresAt = $purchaseOrder->getExpiresAt();
        if (!$expiresAt) {
            return false;
        }
        return strtotime($expiresAt) < time();
    }

    /**
     * Verify that all items in the snapshot have sufficient stock.
     *
     * This is public so UI blocks can use it to show a warning badge on
     * a PO before an admin even attempts approval.
     *
     * Products with "manage_stock = false" are always considered available
     * (e.g. virtual products, downloadable, etc.).
     *
     * @param array $snapshotItems  Decoded from PO snapshot['items'].
     * @throws LocalizedException   When any item has insufficient stock.
     */
    public function checkStockForSnapshot(array $snapshotItems): void
    {
        foreach ($snapshotItems as $item) {
            $productId   = (int)   ($item['product_id'] ?? 0);
            $sku         = (string)($item['sku']        ?? '');
            $name        = (string)($item['name']       ?? $sku);
            $requiredQty = (float) ($item['qty']        ?? 1);

            if (!$productId) {
                continue;
            }

            $stockItem = $this->stockRegistry->getStockItem($productId);

            // Skip products where Magento is not configured to track stock.
            if (!$stockItem->getManageStock()) {
                continue;
            }

            if (!$stockItem->getIsInStock()) {
                throw new LocalizedException(__(
                    'Product "%1" (SKU: %2) is out of stock and cannot be ordered.',
                    $name,
                    $sku
                ));
            }

            if ($stockItem->getQty() < $requiredQty) {
                $this->logger->warning(sprintf(
                    '[PurchaseOrder] Stock check failed for SKU %s (ID %d). Available: %f, Required: %f',
                    $sku,
                    $productId,
                    $stockItem->getQty(),
                    $requiredQty
                ));
                throw new LocalizedException(__(
                    'Insufficient stock for "%1" (SKU: %2). Available: %3, Required: %4.',
                    $name,
                    $sku,
                    $stockItem->getQty(),
                    $requiredQty
                ));
            }
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Serialize the current state of a quote into a JSON snapshot.
     *
     * The snapshot captures per-unit prices so they can be restored exactly
     * when the PO is approved, regardless of later catalog price changes.
     *
     * Note: getAllVisibleItems() is used (not getAllItems()) to avoid
     * duplicating child items of configurable/bundle products. For
     * configurable products, getSku() already returns the variant SKU.
     *
     * @param CartInterface $quote
     * @return string JSON-encoded snapshot.
     */
    private function buildSnapshot(CartInterface $quote): string
    {
        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $itemOptions = [];
            foreach (($item->getOptions() ?? []) as $option) {
                $itemOptions[$option->getCode()] = $option->getValue();
            }

            $actualProductId = (int) $item->getProductId();
            if ($item->getHasChildren()) {
                foreach ($item->getChildren() as $child) {
                    $actualProductId = (int) $child->getProductId();
                    break;
                }
            }

            $items[] = [
                'item_id'         => (int)   $item->getId(),
                'product_id'      => $actualProductId,
                'sku'             => $item->getSku(),
                'name'            => $item->getName(),
                'qty'             => (float) $item->getQty(),
                // 'price' is the per-unit catalog price before any custom override.
                // This is the value restored at approval time to freeze prices.
                'price'           => (float) $item->getPrice(),
                'custom_price'    => $item->getCustomPrice() !== null
                    ? (float) $item->getCustomPrice()
                    : null,
                'row_total'       => (float) $item->getRowTotal(),
                'discount_amount' => (float) $item->getDiscountAmount(),
                'options'         => $itemOptions,
            ];
        }

        $shippingAddress = $quote->getShippingAddress();

        $data = [
            'items'           => $items,
            'currency_code'   => $quote->getQuoteCurrencyCode(),
            'coupon_code'     => $quote->getCouponCode(),
            'subtotal'        => (float) $quote->getSubtotal(),
            'shipping_amount' => $shippingAddress ? (float) $shippingAddress->getShippingAmount() : 0.0,
            'shipping_method' => $shippingAddress ? $shippingAddress->getShippingMethod() : null,
            'tax_amount'      => $shippingAddress ? (float) $shippingAddress->getTaxAmount() : 0.0,
            'grand_total'     => (float) $quote->getGrandTotal(),
            'payment_method'  => $quote->getPayment() ? $quote->getPayment()->getMethod() : null,
            'captured_at'     => date('Y-m-d H:i:s'),
        ];

        return $this->json->serialize($data);
    }

    /**
     * Reactivate the PO's source quote with frozen prices, then place the order.
     *
     * Price restoration: for each quote item, we set custom_price +
     * original_custom_price from the snapshot.  Setting isSuperMode on the
     * product bypasses Magento's per-product price model validation, which
     * would otherwise overwrite the custom price during collectTotals().
     *
     * CartManagement::placeOrder() automatically deactivates the quote on
     * success, so no manual cleanup is required in the happy path.
     *
     * @param PurchaseOrderInterface $purchaseOrder
     * @param array                  $snapshotData
     * @return OrderInterface
     * @throws LocalizedException
     */
    private function placeOrderFromPurchaseOrder(
        PurchaseOrderInterface $purchaseOrder,
        array $snapshotData
    ): OrderInterface {
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->cartRepository->get((int) $purchaseOrder->getQuoteId());

        // Restore store context so price models and tax rules use the correct store.
        $this->storeManager->setCurrentStore($quote->getStore()->getId());

        // Reactivate so CartManagement can process it.
        $quote->setIsActive(true);

        // Mark global state as "Approval Mode" so plugins (PlaceOrderPlugin) skip recursion.
        $this->checkoutState->setIsApprovalMode(true);

        try {
            // Restore per-unit prices captured at PO creation time.
            $this->restorePricesFromSnapshot($quote, $snapshotData['items'] ?? []);

            // Recalculate totals with the restored prices.
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
            $this->cartRepository->save($quote);

            // placeOrder() converts the quote to an order and deactivates the quote.
            $orderId = $this->cartManagement->placeOrder((int) $quote->getId());

            return $this->orderRepository->get($orderId);
        } finally {
            // Always reset the flag to avoid side effects on other processes.
            $this->checkoutState->setIsApprovalMode(false);
        }
    }

    /**
     * Set frozen (custom) prices on quote items from the snapshot.
     *
     * If the snapshot recorded a custom_price (buyer had a special price at
     * creation time), that custom price is preserved. Otherwise the standard
     * unit price from the snapshot is used.
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param array                      $snapshotItems
     */
    private function restorePricesFromSnapshot(
        \Magento\Quote\Model\Quote $quote,
        array $snapshotItems
    ): void {
        // Index by SKU for O(1) lookup per quote item.
        $snapshotBySku = array_column($snapshotItems, null, 'sku');

        foreach ($quote->getAllVisibleItems() as $item) {
            $sku = $item->getSku();
            if (!isset($snapshotBySku[$sku])) {
                continue;
            }

            $snap = $snapshotBySku[$sku];
            // Prefer custom_price if it was set at PO creation, fall back to price.
            $frozenPrice = (float) ($snap['custom_price'] ?? $snap['price']);

            $item->setCustomPrice($frozenPrice);
            $item->setOriginalCustomPrice($frozenPrice);
            // isSuperMode prevents the product's price model from overwriting
            // the custom price during collectTotals().
            $item->getProduct()->setIsSuperMode(true);
        }
    }

    /**
     * Assert that the given customer is an Admin or Manager of the given company.
     *
     * @param int $actorId
     * @param int $companyId
     * @throws LocalizedException
     */
    private function assertActorIsApprover(int $actorId, int $companyId): void
    {
        // Company match: actor must belong to the same company as the PO.
        $actorCompanyId = $this->companyManagement->getCompanyIdByCustomerId($actorId);
        if ((int) $actorCompanyId !== $companyId) {
            throw new LocalizedException(__(
                'You do not have permission to manage purchase orders for this company.'
            ));
        }

        // Role match: actor must have an approver role.
        $roleId = $this->companyManagement->getRoleIdByCustomerId($actorId);
        if (!$roleId) {
            throw new LocalizedException(__('Your account does not have a company role assigned.'));
        }

        try {
            $role = $this->roleRepository->get((int) $roleId);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('Your company role could not be determined.'));
        }

        if (!in_array($role->getRoleName(), $this->config->getApproverRoleNames(), true)) {
            throw new LocalizedException(__(
                'Your role "%1" does not have permission to approve or reject purchase orders.',
                $role->getRoleName()
            ));
        }
    }

    /**
     * Generate a human-readable increment ID from the database entity_id.
     *
     * Format: PO-{YEAR}-{ENTITY_ID zero-padded to 6 digits}
     * Example: PO-2026-000042
     *
     * Using entity_id as the base guarantees global uniqueness without any
     * counter table or risk of duplicate IDs under concurrent load.
     *
     * @param int $entityId
     * @return string
     */
    private function generateIncrementId(int $entityId): string
    {
        return sprintf('PO-%s-%06d', date('Y'), $entityId);
    }
}
