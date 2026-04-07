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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reads PurchaseOrder module configuration from Magento system config.
 *
 * Configuration values are defined in Orangecat_Company's system.xml
 * under the mycompany/purchase_orders group (already created).
 */
class Config
{
    /**
     * Path to the "Purchase Order validity in days" global config value.
     * Defined in Orangecat_Company's system.xml / adminhtml/system.xml.
     */
    private const XML_PATH_PO_VALIDITY_DAYS = 'mycompany/purchase_orders/po_validity_time';

    /**
     * Path to the "Enable Purchase Orders" global config value.
     */
    private const XML_PATH_PO_ENABLED = 'mycompany/purchase_orders/enabled';

    /**
     * Role names that are allowed to approve or reject Purchase Orders.
     * Stored here so callers don't need to hard-code strings.
     */
    public const APPROVER_ROLE_NAMES = ['Company Admin', 'Company Manager'];

    /**
     * The role name that triggers PO-approval rules during checkout.
     */
    public const BUYER_ROLE_NAME = 'Company Buyer';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Check if Purchase Order functionality is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PO_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Return the number of days a PO is valid after creation.
     *
     * Returns 0 when not configured, which the management class interprets
     * as "no expiry" (PO never expires automatically).
     *
     * @return int
     */
    public function getPurchaseOrderValidityDays(): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_PO_VALIDITY_DAYS,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Return role names that are permitted to approve/reject POs.
     *
     * @return string[]
     */
    public function getApproverRoleNames(): array
    {
        return self::APPROVER_ROLE_NAMES;
    }

    /**
     * Return the role name whose members are subject to PO approval rules.
     *
     * @return string
     */
    public function getBuyerRoleName(): string
    {
        return self::BUYER_ROLE_NAME;
    }
}
