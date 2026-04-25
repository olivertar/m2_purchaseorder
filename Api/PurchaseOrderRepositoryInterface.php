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
 * Interface PurchaseOrderRepositoryInterface
 */
interface PurchaseOrderRepositoryInterface
{
    /**
     * Save purchase order.
     *
     * @param \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface $purchaseOrder
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(\Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface $purchaseOrder);

    /**
     * Get purchase order by ID.
     *
     * @param int $id
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($id);

    /**
     * Get purchase order list.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderSearchResultsInterface
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);

    /**
     * Delete purchase order.
     *
     * @param \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface $purchaseOrder
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(\Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface $purchaseOrder);

    /**
     * Delete purchase order by ID.
     *
     * @param int $id
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($id);
}
