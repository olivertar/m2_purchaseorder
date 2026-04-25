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

namespace Orangecat\PurchaseOrder\Controller\Order;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use Orangecat\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Orangecat\Company\Api\CompanyManagementInterface;
use Orangecat\Company\Api\Data\RoleInterface;

class View extends Action implements HttpGetActionInterface
{
    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param Session $customerSession
     * @param PurchaseOrderRepositoryInterface $purchaseOrderRepository
     * @param CompanyManagementInterface $companyManagement
     */
    public function __construct(
        Context $context,
        protected PageFactory $resultPageFactory,
        protected Session $customerSession,
        protected PurchaseOrderRepositoryInterface $purchaseOrderRepository,
        protected CompanyManagementInterface $companyManagement
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

        $poId = (int)$this->getRequest()->getParam('id');
        $customerId = (int)$this->customerSession->getCustomerId();

        try {
            $purchaseOrder = $this->purchaseOrderRepository->getById($poId);
            
            $isOwner = (int)$purchaseOrder->getCreatorId() === $customerId;
            $canManage = false;

            if (!$isOwner) {
                $roleId = (int)$this->companyManagement->getRoleIdByCustomerId($customerId);
                if ($roleId === RoleInterface::ADMIN_ROLE_ID || $roleId === RoleInterface::MANAGER_ROLE_ID) {
                    $userCompanyId = (int)$this->companyManagement->getCompanyIdByCustomerId($customerId);
                    $poCompanyId = (int)$purchaseOrder->getCompanyId();
                    if ($userCompanyId && $userCompanyId === $poCompanyId) {
                        $canManage = true;
                    }
                }
            }

            if (!$isOwner && !$canManage) {
                $this->messageManager->addErrorMessage(
                    __('Access denied. You do not have permission to view this purchase order.')
                );
                return $this->_redirect('customer/account');
            }

        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('This purchase order no longer exists.'));
            return $this->_redirect('*/*/index');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Purchase Order # %1', $purchaseOrder->getIncrementId()));
        return $resultPage;
    }
}
