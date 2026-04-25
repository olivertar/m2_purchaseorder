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

namespace Orangecat\PurchaseOrder\Model\Rule;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Interface for Purchase Order approval rules.
 *
 * Each rule evaluates whether a given cart/customer combination
 * requires approval (i.e., should be converted to a PO instead of
 * being placed as a direct Magento order).
 *
 * To add a new rule, implement this interface and register it in di.xml
 * under the ApprovalRuleChain's "rules" argument. No existing code needs
 * to be modified.
 */
interface ApprovalRuleInterface
{
    /**
     * Determine whether this quote requires PO approval.
     *
     * Return true  → this rule blocks direct checkout; a PO must be created.
     * Return false → this rule has no objection; other rules may still block.
     *
     * @param CartInterface $quote
     * @param int $customerId
     * @return bool
     */
    public function needsApproval(CartInterface $quote, int $customerId): bool;

    /**
     * Return a short, human-readable identifier for this rule.
     *
     * Used in audit logs and error messages so administrators can tell
     * which rule triggered a PO conversion.
     *
     * Example return values: "max_purchase_amount", "max_period_amount"
     *
     * @return string
     */
    public function getRuleName(): string;
}
