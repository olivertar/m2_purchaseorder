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

namespace Orangecat\PurchaseOrder\Api;

/**
 * Interface PurchaseOrderLogRepositoryInterface
 */
interface PurchaseOrderLogRepositoryInterface
{
    /**
     * Save purchase order log.
     *
     * @param \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogInterface $purchaseOrderLog
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(\Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogInterface $purchaseOrderLog);

    /**
     * Get purchase order log by ID.
     *
     * @param int $id
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($id);

    /**
     * Get purchase order log list.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogSearchResultsInterface
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);

    /**
     * Create a new log entry.
     *
     * @param int $poId
     * @param string $action
     * @param int|null $actorId
     * @param string|null $comment
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function log(int $poId, string $action, ?int $actorId = null, ?string $comment = null);
}
