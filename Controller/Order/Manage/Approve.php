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

namespace Orangecat\PurchaseOrder\Controller\Order\Manage;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Orangecat\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Orangecat\PurchaseOrder\Model\PurchaseOrderManagement;
use Orangecat\Company\Api\CompanyManagementInterface;
use Orangecat\Company\Api\Data\RoleInterface;

class Approve extends Action implements HttpPostActionInterface
{
    /**
     * @param Context $context
     * @param Session $customerSession
     * @param PurchaseOrderManagement $purchaseOrderManagement
     * @param Validator $formKeyValidator
     * @param CompanyManagementInterface $companyManagement
     * @param PurchaseOrderRepositoryInterface $purchaseOrderRepository
     */
    public function __construct(
        Context $context,
        protected Session $customerSession,
        protected PurchaseOrderManagement $purchaseOrderManagement,
        protected Validator $formKeyValidator,
        protected CompanyManagementInterface $companyManagement,
        protected PurchaseOrderRepositoryInterface $purchaseOrderRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->_redirect('customer/account/login');
        }

        $customerId = (int)$this->customerSession->getCustomerId();
        $roleId = (int)$this->companyManagement->getRoleIdByCustomerId($customerId);

        if ($roleId !== RoleInterface::ADMIN_ROLE_ID && $roleId !== RoleInterface::MANAGER_ROLE_ID) {
            $this->messageManager->addErrorMessage(
                __('Access denied. You do not have permission to approve company purchase orders.')
            );
            return $this->_redirect('customer/account');
        }

        if (!$this->formKeyValidator->validate($this->getRequest())) {
            return $this->_redirect('*/*/index');
        }

        $poId = (int)$this->getRequest()->getParam('id');

        try {
            $purchaseOrder = $this->purchaseOrderRepository->getById($poId);
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Purchase order not found.'));
            return $this->_redirect('*/*/index');
        }

        $actorCompanyId = (int)$this->companyManagement->getCompanyIdByCustomerId($customerId);
        if (!$actorCompanyId || $actorCompanyId !== (int)$purchaseOrder->getCompanyId()) {
            $this->messageManager->addErrorMessage(
                __('Access denied. You do not have permission to manage this purchase order.')
            );
            return $this->_redirect('*/*/index');
        }

        $actorId = $customerId;

        try {
            $order = $this->purchaseOrderManagement->approvePurchaseOrder($poId, $actorId);
            $this->messageManager->addSuccessMessage(
                __('Purchase order approved successfully. Order %1 has been created.', $order->getIncrementId())
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('We can\'t approve the purchase order right now.'));
        }

        return $this->_redirect('*/*/index');
    }
}
