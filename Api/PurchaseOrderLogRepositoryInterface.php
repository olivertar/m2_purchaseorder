<?php
/**
 * This file is part of the Orangecat PurchaseOrder package.
 *
 * (c) Oliverio Gombert <olivertar@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Orangecat\PurchaseOrder\Api;

interface PurchaseOrderLogRepositoryInterface
{
    /**
     * @param \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogInterface $purchaseOrderLog
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(\Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogInterface $purchaseOrderLog);

    /**
     * @param int $id
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($id);

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogSearchResultsInterface
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);

    /**
     * @param int $poId
     * @param string $action
     * @param int|null $actorId
     * @param string|null $comment
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function log(int $poId, string $action, ?int $actorId = null, ?string $comment = null);
}
