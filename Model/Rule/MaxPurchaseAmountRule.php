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
use Orangecat\Company\Model\ResourceModel\CompanyCustomer\CollectionFactory as CompanyCustomerCollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Approval rule: blocks checkout if the cart grand total exceeds
 * the Company Buyer's configured maximum single-purchase amount.
 *
 * If max_purchase_amount is NULL or 0 for the buyer, no limit is enforced
 * and this rule always returns false.
 */
class MaxPurchaseAmountRule implements ApprovalRuleInterface
{
    private const RULE_NAME = 'max_purchase_amount';

    /**
     * @param CompanyCustomerCollectionFactory $companyCustomerCollectionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CompanyCustomerCollectionFactory $companyCustomerCollectionFactory,
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
     * Triggers when: quote grand total > buyer's max_purchase_amount (and limit is set).
     */
    public function needsApproval(CartInterface $quote, int $customerId): bool
    {
        $limit = $this->getBuyerPurchaseLimit($customerId);

        // NULL or 0 means "no limit configured" → do not block.
        if ($limit === null || $limit <= 0.0) {
            return false;
        }

        $grandTotal = (float) $quote->getGrandTotal();

        $needsApproval = $grandTotal > $limit;

        if ($needsApproval) {
            $this->logger->info(sprintf(
                '[PurchaseOrder][%s] Customer %d needs approval. Cart total %.4f exceeds limit %.4f.',
                self::RULE_NAME,
                $customerId,
                $grandTotal,
                $limit
            ));
        }

        return $needsApproval;
    }

    /**
     * Load the max_purchase_amount configured for this buyer in mycompany_customer.
     *
     * Returns null if no link record exists (customer not in a company).
     *
     * @param int $customerId
     * @return float|null
     */
    private function getBuyerPurchaseLimit(int $customerId): ?float
    {
        $collection = $this->companyCustomerCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->setPageSize(1);

        /** @var \Orangecat\Company\Model\CompanyCustomer $link */
        $link = $collection->getFirstItem();

        if (!$link->getId()) {
            return null;
        }

        $raw = $link->getData('max_purchase_amount');

        return $raw !== null ? (float) $raw : null;
    }
}
