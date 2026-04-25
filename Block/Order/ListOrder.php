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

use Magento\Customer\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Orangecat\PurchaseOrder\Model\ResourceModel\PurchaseOrder\CollectionFactory;

/**
 * Block for displaying the customer's own purchase orders.
 */
class ListOrder extends Template
{
    /**
     * @param Context $context
     * @param CollectionFactory $collectionFactory
     * @param Session $customerSession
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
     * @param array $data
     */
    public function __construct(
        Context $context,
        protected CollectionFactory $collectionFactory,
        protected Session $customerSession,
        protected \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Format price
     *
     * @param float $amount
     * @return string
     */
    public function formatPrice($amount)
    {
        return $this->priceCurrency->format($amount, false);
    }

    /**
     * Get Purchase Orders for the current customer
     *
     * @return \Orangecat\PurchaseOrder\Model\ResourceModel\PurchaseOrder\Collection
     */
    public function getPurchaseOrders()
    {
        $customerId = $this->customerSession->getCustomerId();
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(PurchaseOrderInterface::CREATOR_ID, $customerId);
        $collection->setOrder(PurchaseOrderInterface::CREATED_AT, 'DESC');
        return $collection;
    }

    /**
     * Get view URL for a purchase order
     *
     * @param PurchaseOrderInterface $purchaseOrder
     * @return string
     */
    public function getViewUrl(PurchaseOrderInterface $purchaseOrder): string
    {
        return $this->getUrl('purchaseorder/order/view', ['id' => $purchaseOrder->getId()]);
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
}
