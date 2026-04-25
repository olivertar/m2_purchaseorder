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

namespace Orangecat\PurchaseOrder\Exception;

use Magento\Framework\Exception\LocalizedException;
use Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface;

/**
 * Thrown when a purchase order is successfully created during checkout interception.
 *
 * This is NOT an error condition — it signals to the calling stack that the cart
 * was intentionally converted to a PO instead of a Magento order.
 *
 * Callers (e.g. PaymentInformationManagement plugins) should catch this class
 * specifically to distinguish PO creation from real checkout failures.
 */
class PurchaseOrderCreatedException extends LocalizedException
{
    /**
     * Constructor.
     *
     * @param PurchaseOrderInterface $purchaseOrder
     * @param \Magento\Framework\Phrase|null $phrase
     */
    public function __construct(
        private PurchaseOrderInterface $purchaseOrder,
        ?\Magento\Framework\Phrase $phrase = null
    ) {

        parent::__construct(
            $phrase ?? __(
                'Your order has been submitted as Purchase Order %1 and is pending approval.',
                $purchaseOrder->getIncrementId()
            )
        );
    }

    /**
     * Get purchase order.
     *
     * @return PurchaseOrderInterface
     */
    public function getPurchaseOrder(): PurchaseOrderInterface
    {
        return $this->purchaseOrder;
    }
}
