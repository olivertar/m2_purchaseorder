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

namespace Orangecat\PurchaseOrder\Plugin\Checkout;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Orangecat\Company\Api\CompanyManagementInterface;
use Orangecat\Company\Api\RoleRepositoryInterface;
use Orangecat\PurchaseOrder\Exception\PurchaseOrderCreatedException;
use Orangecat\PurchaseOrder\Model\ApprovalRuleChain;
use Orangecat\PurchaseOrder\Model\Config;
use Orangecat\PurchaseOrder\Model\PurchaseOrderManagement;
use Psr\Log\LoggerInterface;

/**
 * Intercepts QuoteManagement::placeOrder() to apply PO approval rules.
 *
 * Decision tree:
 *   1. Guest checkout (no customer ID)       → always proceed normally.
 *   2. Customer not in a company             → proceed normally.
 *   3. Customer role ≠ "Company Buyer"       → proceed normally.
 *   4. ApprovalRuleChain says no approval    → proceed normally.
 *   5. ApprovalRuleChain says approval needed→ create PO, throw PurchaseOrderCreatedException.
 *
 * When a PO is created (case 5), the exception carries the PO model.
 * The PaymentInformationPlugin (see below) catches it and stores PO data
 * in the checkout session before allowing the checkout JS to redirect to
 * the PO confirmation page.
 *
 * Registered on: Magento\Quote\Model\QuoteManagement::placeOrder()
 */
class PlaceOrderPlugin
{
    /**
     * @param CartRepositoryInterface $cartRepository
     * @param CompanyManagementInterface $companyManagement
     * @param RoleRepositoryInterface $roleRepository
     * @param ApprovalRuleChain $approvalRuleChain
     * @param PurchaseOrderManagement $purchaseOrderManagement
     * @param Config $config
     * @param LoggerInterface $logger
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Orangecat\PurchaseOrder\Model\CheckoutState $checkoutState
     */
    public function __construct(
        private readonly CartRepositoryInterface    $cartRepository,
        private readonly CompanyManagementInterface $companyManagement,
        private readonly RoleRepositoryInterface    $roleRepository,
        private readonly ApprovalRuleChain          $approvalRuleChain,
        private readonly PurchaseOrderManagement    $purchaseOrderManagement,
        private readonly Config                     $config,
        private readonly LoggerInterface            $logger,
        private readonly \Magento\Checkout\Model\Session $checkoutSession,
        private readonly \Orangecat\PurchaseOrder\Model\CheckoutState $checkoutState
    ) {
    }

    /**
     * Around plugin on QuoteManagement::placeOrder().
     *
     * @param mixed $subject
     * @param callable $proceed
     * @param mixed $args
     * @return mixed  Order ID.
     * @throws PurchaseOrderCreatedException When a PO is successfully created.
     * @throws LocalizedException            On unexpected errors.
     */
    public function aroundPlaceOrder(
        $subject,
        callable $proceed,
        ...$args
    ) {
        $cartId = $args[0] ?? null;
        if (!$cartId) {
            return $proceed(...$args);
        }
        try {
            $quote = $this->cartRepository->get((int) $cartId);
        } catch (\Exception $e) {
            // Cannot load quote — let the original method handle the error.
            return $proceed(...$args);
        }

        // --- Gate -1: bypass if the module is disabled. ---
        if (!$this->config->isEnabled()) {
            return $proceed(...$args);
        }

        // --- Gate 0: bypass if this is an approval process. ---
        if ($this->checkoutState->isApprovalMode()) {
            return $proceed(...$args);
        }

        $customerId = (int) $quote->getCustomerId();

        // --- Gate 1: guests always proceed normally. ---
        if (!$customerId) {
            return $proceed(...$args);
        }

        // --- Gate 2: customer must belong to a company. ---
        if (!$this->companyManagement->getCompanyIdByCustomerId($customerId)) {
            return $proceed(...$args);
        }

        // --- Gate 3: customer must have the "Company Buyer" role. ---
        if (!$this->isCompanyBuyer($customerId)) {
            return $proceed(...$args);
        }

        // --- Gate 4: apply all approval rules. ---
        if (!$this->approvalRuleChain->needsApproval($quote, $customerId)) {
            // No rule requires approval → proceed with normal checkout.
            return $proceed(...$args);
        }

        // --- Gate 5: all gates passed → create the Purchase Order. ---
        $this->logger->info(sprintf(
            '[PurchaseOrder][Plugin] Creating PO for customer %d, quote %d.',
            $customerId,
            $cartId
        ));

        $triggeredRule = $this->approvalRuleChain->getRuleName();
        $purchaseOrder = $this->purchaseOrderManagement->createFromQuote(
            $quote,
            $customerId,
            $triggeredRule
        );

        // Set checkout session variables directly so Magento's SuccessValidator passes.
        $this->checkoutSession
            ->setLastQuoteId((int) $purchaseOrder->getQuoteId())
            ->setLastSuccessQuoteId((int) $purchaseOrder->getQuoteId())
            ->setLastOrderId('po_' . $purchaseOrder->getId())
            ->setLastRealOrderId($purchaseOrder->getIncrementId())
            ->setLastPurchaseOrderId((int) $purchaseOrder->getId())
            ->setLastPurchaseOrderIncrementId($purchaseOrder->getIncrementId());

        // Return a dummy order ID to gracefully bypass Magento's exception logging
        return 0;
    }

    /**
     * Determine whether the given customer holds the "Company Buyer" role.
     *
     * @param int $customerId
     * @return bool
     */
    private function isCompanyBuyer(int $customerId): bool
    {
        $roleId = $this->companyManagement->getRoleIdByCustomerId($customerId);
        if (!$roleId) {
            return false;
        }

        try {
            $role = $this->roleRepository->get((int) $roleId);
            return $role->getRoleName() === $this->config->getBuyerRoleName();
        } catch (\Exception $e) {
            $this->logger->warning(sprintf(
                '[PurchaseOrder][Plugin] Could not load role %d for customer %d: %s',
                $roleId,
                $customerId,
                $e->getMessage()
            ));
            return false;
        }
    }
}
