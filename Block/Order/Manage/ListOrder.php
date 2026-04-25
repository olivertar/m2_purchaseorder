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

namespace Orangecat\PurchaseOrder\Block\Order\Manage;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Orangecat\Company\Api\CompanyManagementInterface;
use Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Orangecat\PurchaseOrder\Model\ResourceModel\PurchaseOrder\CollectionFactory;

/**
 * Block for managing company purchase orders list.
 */
class ListOrder extends Template
{
    /**
     * @param Context $context
     * @param CollectionFactory $collectionFactory
     * @param Session $customerSession
     * @param CompanyManagementInterface $companyManagement
     * @param FormKey $formKey
     * @param CustomerRepositoryInterface $customerRepository
     * @param array $data
     */
    public function __construct(
        Context $context,
        protected CollectionFactory $collectionFactory,
        protected Session $customerSession,
        protected CompanyManagementInterface $companyManagement,
        protected FormKey $formKey,
        protected CustomerRepositoryInterface $customerRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get Purchase Orders for the current company
     *
     * @return \Orangecat\PurchaseOrder\Model\ResourceModel\PurchaseOrder\Collection
     */
    public function getPurchaseOrders()
    {
        $customerId = $this->customerSession->getCustomerId();
        $companyId = $this->companyManagement->getCompanyIdByCustomerId($customerId);

        $collection = $this->collectionFactory->create();
        if ($companyId) {
            $collection->addFieldToFilter(PurchaseOrderInterface::COMPANY_ID, $companyId);
        }
        $collection->setOrder(PurchaseOrderInterface::CREATED_AT, 'DESC');
        return $collection;
    }

    /**
     * Get view URL for admin/manager
     *
     * @param PurchaseOrderInterface $purchaseOrder
     * @return string
     */
    public function getViewUrlForAdmin(PurchaseOrderInterface $purchaseOrder): string
    {
        return $this->getUrl('purchaseorder/order/view', ['id' => $purchaseOrder->getId()]);
    }

    /**
     * Get approve URL
     *
     * @param PurchaseOrderInterface $purchaseOrder
     * @return string
     */
    public function getApproveUrl(PurchaseOrderInterface $purchaseOrder): string
    {
        return $this->getUrl('purchaseorder/order_manage/approve', ['id' => $purchaseOrder->getId()]);
    }

    /**
     * Get reject URL
     *
     * @param PurchaseOrderInterface $purchaseOrder
     * @return string
     */
    public function getRejectUrl(PurchaseOrderInterface $purchaseOrder): string
    {
        return $this->getUrl('purchaseorder/order_manage/reject', ['id' => $purchaseOrder->getId()]);
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
     * Get form key for security validation in POST requests.
     *
     * @return string
     */
    public function getFormKey()
    {
        return $this->formKey->getFormKey();
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
