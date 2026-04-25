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

namespace Orangecat\PurchaseOrder\Block\Order;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Orangecat\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Orangecat\PurchaseOrder\Model\PurchaseOrderManagement;
use Orangecat\PurchaseOrder\Model\ResourceModel\PurchaseOrderLog\CollectionFactory as LogCollectionFactory;

/**
 * Block for viewing a single purchase order.
 */
class View extends Template
{
    /**
     * @param Context $context
     * @param PurchaseOrderRepositoryInterface $purchaseOrderRepository
     * @param LogCollectionFactory $logCollectionFactory
     * @param Json $json
     * @param PurchaseOrderManagement $purchaseOrderManagement
     * @param Session $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param array $data
     */
    public function __construct(
        Context $context,
        protected PurchaseOrderRepositoryInterface $purchaseOrderRepository,
        protected LogCollectionFactory $logCollectionFactory,
        protected Json $json,
        protected PurchaseOrderManagement $purchaseOrderManagement,
        protected Session $customerSession,
        protected CustomerRepositoryInterface $customerRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get the purchase order from registry or request
     *
     * @return PurchaseOrderInterface|null
     */
    public function getPurchaseOrder()
    {
        $id = $this->getRequest()->getParam('id');
        try {
            return $this->purchaseOrderRepository->getById((int)$id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get snapshot data items
     *
     * @return array
     */
    public function getSnapshotItems()
    {
        $po = $this->getPurchaseOrder();
        if (!$po || !$po->getSnapshot()) {
            return [];
        }
        $data = $this->json->unserialize($po->getSnapshot());
        return $data['items'] ?? [];
    }

    /**
     * Get snapshot totals
     *
     * @return array
     */
    public function getSnapshotTotals()
    {
        $po = $this->getPurchaseOrder();
        if (!$po || !$po->getSnapshot()) {
            return [];
        }
        return $this->json->unserialize($po->getSnapshot());
    }

    /**
     * Get activity log
     *
     * @return \Orangecat\PurchaseOrder\Model\ResourceModel\PurchaseOrderLog\Collection
     */
    public function getActivityLog()
    {
        $po = $this->getPurchaseOrder();
        $collection = $this->logCollectionFactory->create();
        if ($po) {
            $collection->addFieldToFilter('purchase_order_id', $po->getId());
        }
        $collection->setOrder('created_at', 'DESC');
        return $collection;
    }

    /**
     * Get status label
     *
     * @param string $status
     * @return \Magento\Framework\Phrase
     */
    public function getStatusLabel($status)
    {
        $labels = [
            PurchaseOrderInterface::STATUS_PENDING_APPROVAL => __('Pending Approval'),
            PurchaseOrderInterface::STATUS_APPROVED => __('Approved'),
            PurchaseOrderInterface::STATUS_REJECTED => __('Rejected'),
            PurchaseOrderInterface::STATUS_ORDER_PLACED => __('Order Placed'),
            PurchaseOrderInterface::STATUS_CANCELED => __('Canceled'),
            PurchaseOrderInterface::STATUS_EXPIRED => __('Expired')
        ];
        return $labels[$status] ?? __($status);
    }

    /**
     * Is PO cancelable by current customer
     *
     * @return bool
     */
    public function canCancel()
    {
        $po = $this->getPurchaseOrder();
        if (!$po || $po->getStatus() !== PurchaseOrderInterface::STATUS_PENDING_APPROVAL) {
            return false;
        }

        return (int)$po->getCreatorId() === (int)$this->customerSession->getCustomerId();
    }

    /**
     * Get cancel URL
     *
     * @return string
     */
    public function getCancelUrl()
    {
        $po = $this->getPurchaseOrder();
        return $po ? $this->getUrl('purchaseorder/order/cancel', ['id' => $po->getId()]) : '';
    }

    /**
     * Get creator name
     *
     * @param PurchaseOrderInterface $po
     * @return string
     */
    public function getCreatorName(PurchaseOrderInterface $po): string
    {
        $creatorId = $po->getCreatorId();
        if (!$creatorId) {
            return (string)__('N/A');
        }

        try {
            $customer = $this->customerRepository->getById((int)$creatorId);
            return $customer->getFirstname() . ' ' . $customer->getLastname();
        } catch (\Exception $e) {
            return (string)__('Unknown');
        }
    }
}
