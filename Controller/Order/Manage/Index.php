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
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Orangecat\Company\Api\CompanyManagementInterface;
use Orangecat\Company\Api\Data\RoleInterface;

class Index extends Action implements HttpGetActionInterface
{
    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param Session $customerSession
     * @param CompanyManagementInterface $companyManagement
     */
    public function __construct(
        Context $context,
        protected PageFactory $resultPageFactory,
        protected Session $customerSession,
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

        $customerId = (int)$this->customerSession->getCustomerId();
        $roleId = (int)$this->companyManagement->getRoleIdByCustomerId($customerId);

        if ($roleId !== RoleInterface::ADMIN_ROLE_ID && $roleId !== RoleInterface::MANAGER_ROLE_ID) {
            $this->messageManager->addErrorMessage(
                __('Access denied. You do not have permission to manage company purchase orders.')
            );
            return $this->_redirect('customer/account');
        }

        $companyId = $this->companyManagement->getCompanyIdByCustomerId($customerId);
        if (!$companyId) {
            return $this->_redirect('customer/account');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Manage Company Purchase Orders'));
        return $resultPage;
    }
}
