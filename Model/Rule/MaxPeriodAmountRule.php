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

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Quote\Api\Data\CartInterface;
use Orangecat\Company\Model\ResourceModel\CompanyCustomer\CollectionFactory as CompanyCustomerCollectionFactory;
use Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Psr\Log\LoggerInterface;

/**
 * Approval rule: blocks checkout if adding this cart's total to the buyer's
 * already-committed spend for the current calendar month would exceed their
 * configured periodic spending limit (max_period_amount).
 *
 * "Committed spend" includes POs with statuses that represent real financial
 * exposure: pending_approval, approved, and order_placed.
 * Rejected, canceled, and expired POs are excluded because the funds
 * were never committed.
 *
 * If max_period_amount is NULL or 0 for the buyer, no limit is enforced
 * and this rule always returns false.
 */
class MaxPeriodAmountRule implements ApprovalRuleInterface
{
    private const RULE_NAME = 'max_period_amount';

    /**
     * Statuses that represent committed spend within the period.
     * A pending PO is counted because it represents an intent to spend
     * that has not yet been rejected; counting it prevents a buyer from
     * creating many simultaneous POs to circumvent the limit.
     */
    private const COMMITTED_STATUSES = [
        PurchaseOrderInterface::STATUS_PENDING_APPROVAL,
        PurchaseOrderInterface::STATUS_APPROVED,
        PurchaseOrderInterface::STATUS_ORDER_PLACED,
    ];

    /**
     * @param CompanyCustomerCollectionFactory $companyCustomerCollectionFactory
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CompanyCustomerCollectionFactory $companyCustomerCollectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getRuleName(): string
    {
        return self::RULE_NAME;
    }

    /**
     * @inheritdoc
     *
     * Triggers when: (period_spent_so_far + cart_grand_total) > max_period_amount.
     */
    public function needsApproval(CartInterface $quote, int $customerId): bool
    {
        $limit = $this->getBuyerPeriodLimit($customerId);

        // NULL or 0 means "no limit configured" → do not block.
        if ($limit === null || $limit <= 0.0) {
            return false;
        }

        $periodSpent  = $this->getPeriodSpent($customerId);
        $grandTotal   = (float) $quote->getGrandTotal();
        $projectedTotal = $periodSpent + $grandTotal;

        $needsApproval = $projectedTotal > $limit;

        if ($needsApproval) {
            $this->logger->info(sprintf(
                '[PurchaseOrder][%s] Customer %d needs approval. '
                . 'Period spent %.4f + cart %.4f = projected %.4f exceeds limit %.4f.',
                self::RULE_NAME,
                $customerId,
                $periodSpent,
                $grandTotal,
                $projectedTotal,
                $limit
            ));
        }

        return $needsApproval;
    }

    /**
     * Load the max_period_amount configured for this buyer.
     *
     * @param int $customerId
     * @return float|null
     */
    private function getBuyerPeriodLimit(int $customerId): ?float
    {
        $collection = $this->companyCustomerCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->setPageSize(1);

        /** @var \Orangecat\Company\Model\CompanyCustomer $link */
        $link = $collection->getFirstItem();

        if (!$link->getId()) {
            return null;
        }

        $raw = $link->getData('max_period_amount');

        return $raw !== null ? (float) $raw : null;
    }

    /**
     * Calculate the total committed spend for this buyer in the current
     * calendar month (from the 1st of the month at 00:00:00 UTC until now).
     *
     * Uses a raw SQL aggregate for performance — avoids loading a full
     * collection into memory when only a SUM is needed.
     *
     * @param int $customerId
     * @return float
     */
    private function getPeriodSpent(int $customerId): float
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName  = $connection->getTableName('purchase_order');

        // First day of the current calendar month in UTC.
        $periodStart = date('Y-m-01 00:00:00');

        $select = $connection->select()
            ->from($tableName, ['period_total' => new \Zend_Db_Expr('COALESCE(SUM(grand_total), 0)')])
            ->where('creator_id = ?', $customerId)
            ->where('status IN (?)', self::COMMITTED_STATUSES)
            ->where('created_at >= ?', $periodStart);

        return (float) $connection->fetchOne($select);
    }
}
