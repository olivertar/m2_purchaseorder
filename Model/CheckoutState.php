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

/**
 * Singleton state object to manage checkout-wide flags during a single request.
 *
 * This is used to communicate between PurchaseOrderManagement and PlaceOrderPlugin
 * to avoid recursive PO creation during the approval process.
 */
class CheckoutState
{
    /**
     * @var bool
     */
    private bool $isApprovalMode = false;

    /**
     * Set whether the current request is in PO approval mode.
     *
     * @param bool $isApprovalMode
     * @return void
     */
    public function setIsApprovalMode(bool $isApprovalMode): void
    {
        $this->isApprovalMode = $isApprovalMode;
    }

    /**
     * Check if the current request is in PO approval mode.
     *
     * @return bool
     */
    public function isApprovalMode(): bool
    {
        return $this->isApprovalMode;
    }
}
