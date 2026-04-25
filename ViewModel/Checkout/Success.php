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

namespace Orangecat\PurchaseOrder\ViewModel\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * View model for the checkout success page to handle Purchase Order data.
 */
class Success implements ArgumentInterface
{
    /**
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    /**
     * Get the last purchase order ID from session.
     *
     * @return int|null
     */
    public function getPurchaseOrderId(): ?int
    {
        $id = $this->checkoutSession->getLastPurchaseOrderId();
        return $id ? (int)$id : null;
    }

    /**
     * Get the last purchase order increment ID from session.
     *
     * @return string|null
     */
    public function getPurchaseOrderIncrementId(): ?string
    {
        return $this->checkoutSession->getLastPurchaseOrderIncrementId();
    }
}
