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

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Orangecat\PurchaseOrder\Model\PurchaseOrderFactory as PurchaseOrderInterfaceFactory;
use Magento\Framework\Api\SearchResultsFactory as PurchaseOrderSearchResultsInterfaceFactory;
use Orangecat\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Orangecat\PurchaseOrder\Model\ResourceModel\PurchaseOrder as PurchaseOrderResource;
use Orangecat\PurchaseOrder\Model\ResourceModel\PurchaseOrder\CollectionFactory as PurchaseOrderCollectionFactory;

class PurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    /**
     * @param PurchaseOrderResource $resource
     * @param PurchaseOrderInterfaceFactory $purchaseOrderFactory
     * @param PurchaseOrderCollectionFactory $purchaseOrderCollectionFactory
     * @param PurchaseOrderSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        protected PurchaseOrderResource $resource,
        protected PurchaseOrderInterfaceFactory $purchaseOrderFactory,
        protected PurchaseOrderCollectionFactory $purchaseOrderCollectionFactory,
        protected PurchaseOrderSearchResultsInterfaceFactory $searchResultsFactory,
        protected CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritdoc
     */
    public function save(PurchaseOrderInterface $purchaseOrder)
    {
        try {
            $this->resource->save($purchaseOrder);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the purchase order: %1', $exception->getMessage()),
                $exception
            );
        }
        return $purchaseOrder;
    }

    /**
     * @inheritdoc
     */
    public function getById($id)
    {
        $purchaseOrder = $this->purchaseOrderFactory->create();
        $this->resource->load($purchaseOrder, $id);
        if (!$purchaseOrder->getId()) {
            throw new NoSuchEntityException(__('Purchase Order with id "%1" does not exist.', $id));
        }
        return $purchaseOrder;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->purchaseOrderCollectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritdoc
     */
    public function delete(PurchaseOrderInterface $purchaseOrder)
    {
        try {
            $this->resource->delete($purchaseOrder);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the purchase order: %1', $exception->getMessage())
            );
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function deleteById($id)
    {
        return $this->delete($this->getById($id));
    }
}
