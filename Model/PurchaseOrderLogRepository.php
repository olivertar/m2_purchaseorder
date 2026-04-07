<?php
/**
 * This file is part of the Orangecat PurchaseOrder package.
 *
 * (c) Oliverio Gombert <olivertar@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Orangecat\PurchaseOrder\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogInterface;
use Orangecat\PurchaseOrder\Model\PurchaseOrderLogFactory as PurchaseOrderLogInterfaceFactory;
use Magento\Framework\Api\SearchResultsFactory as PurchaseOrderLogSearchResultsInterfaceFactory;
use Orangecat\PurchaseOrder\Api\PurchaseOrderLogRepositoryInterface;
use Orangecat\PurchaseOrder\Model\ResourceModel\PurchaseOrderLog as PurchaseOrderLogResource;
use Orangecat\PurchaseOrder\Model\ResourceModel\PurchaseOrderLog\CollectionFactory as PurchaseOrderLogCollectionFactory;

class PurchaseOrderLogRepository implements PurchaseOrderLogRepositoryInterface
{
    /**
     * @var PurchaseOrderLogResource
     */
    protected $resource;

    /**
     * @var PurchaseOrderLogInterfaceFactory
     */
    protected $purchaseOrderLogFactory;

    /**
     * @var PurchaseOrderLogCollectionFactory
     */
    protected $purchaseOrderLogCollectionFactory;

    /**
     * @var PurchaseOrderLogSearchResultsInterfaceFactory
     */
    protected $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    protected $collectionProcessor;

    /**
     * @param PurchaseOrderLogResource $resource
     * @param PurchaseOrderLogInterfaceFactory $purchaseOrderLogFactory
     * @param PurchaseOrderLogCollectionFactory $purchaseOrderLogCollectionFactory
     * @param PurchaseOrderLogSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        PurchaseOrderLogResource $resource,
        PurchaseOrderLogInterfaceFactory $purchaseOrderLogFactory,
        PurchaseOrderLogCollectionFactory $purchaseOrderLogCollectionFactory,
        PurchaseOrderLogSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->purchaseOrderLogFactory = $purchaseOrderLogFactory;
        $this->purchaseOrderLogCollectionFactory = $purchaseOrderLogCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritdoc
     */
    public function save(PurchaseOrderLogInterface $purchaseOrderLog)
    {
        try {
            $this->resource->save($purchaseOrderLog);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the purchase order log: %1', $exception->getMessage()),
                $exception
            );
        }
        return $purchaseOrderLog;
    }

    /**
     * @inheritdoc
     */
    public function getById($id)
    {
        $purchaseOrderLog = $this->purchaseOrderLogFactory->create();
        $this->resource->load($purchaseOrderLog, $id);
        if (!$purchaseOrderLog->getId()) {
            throw new NoSuchEntityException(__('Purchase Order Log with id "%1" does not exist.', $id));
        }
        return $purchaseOrderLog;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->purchaseOrderLogCollectionFactory->create();
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
    public function log(int $poId, string $action, ?int $actorId = null, ?string $comment = null)
    {
        $logEntry = $this->purchaseOrderLogFactory->create();
        $logEntry->setPurchaseOrderId($poId);
        $logEntry->setAction($action);
        if ($actorId !== null) {
            $logEntry->setActorId($actorId);
        }
        if ($comment !== null) {
            $logEntry->setComment($comment);
        }
        return $this->save($logEntry);
    }
}
