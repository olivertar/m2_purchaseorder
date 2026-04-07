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

interface PurchaseOrderRepositoryInterface
{
    /**
     * @param \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface $purchaseOrder
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(\Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface $purchaseOrder);

    /**
     * @param int $id
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($id);

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderSearchResultsInterface
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);

    /**
     * @param \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface $purchaseOrder
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(\Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface $purchaseOrder);

    /**
     * @param int $id
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($id);
}
