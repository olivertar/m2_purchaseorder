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

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Orangecat\PurchaseOrder\Exception\PurchaseOrderCreatedException;
use Psr\Log\LoggerInterface;

/**
 * Catches PurchaseOrderCreatedException thrown by PlaceOrderPlugin
 * and converts it into a success response for the checkout frontend.
 *
 * The frontend checkout JS expects savePaymentInformationAndPlaceOrder()
 * to return an integer order ID on success. When a PO is created instead,
 * we:
 *   1. Store PO data in the checkout session (used by the success page).
 *   2. Return 0 (signals "no Magento order" to our custom success page).
 *
 * The custom success page (Phase 8) reads `last_purchase_order_id` from
 * the checkout session and displays the PO confirmation instead of an order.
 *
 * Registered on: Magento\Checkout\Model\PaymentInformationManagement
 * and            Magento\Checkout\Model\GuestPaymentInformationManagement
 */
class PaymentInformationPlugin
{
    /**
     * @param CheckoutSession $checkoutSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Around plugin on PaymentInformationManagement::savePaymentInformationAndPlaceOrder().
     *
     * @param mixed $subject
     * @param callable $proceed
     * @param mixed $args
     * @return int|string 0 when a PO is created, otherwise the real Magento order ID.
     * @throws \Magento\Framework\Exception\LocalizedException On real errors.
     */
    public function aroundSavePaymentInformationAndPlaceOrder(
        $subject,
        callable $proceed,
        ...$args
    ) {
        $result = $proceed(...$args);

        // If the checkout completes as a normal order (returns an order ID != 0),
        // clear any stale PO session data so the success page behaves normally.
        if ($result !== 0 && $result !== '0') {
            $this->checkoutSession->unsLastPurchaseOrderId();
            $this->checkoutSession->unsLastPurchaseOrderIncrementId();
        }

        return $result;
    }

    // storePurchaseOrderInSession is no longer needed here (moved to PlaceOrderPlugin)
}
