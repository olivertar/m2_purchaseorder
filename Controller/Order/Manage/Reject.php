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
use Orangecat\PurchaseOrder\Model\PurchaseOrderManagement;
use Orangecat\Company\Api\CompanyManagementInterface;
use Orangecat\Company\Api\Data\RoleInterface;

class Reject extends Action implements HttpPostActionInterface
{
    /**
     * @param Context $context
     * @param Session $customerSession
     * @param PurchaseOrderManagement $purchaseOrderManagement
     * @param Validator $formKeyValidator
     * @param CompanyManagementInterface $companyManagement
     */
    public function __construct(
        Context $context,
        protected Session $customerSession,
        protected PurchaseOrderManagement $purchaseOrderManagement,
        protected Validator $formKeyValidator,
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
                __('Access denied. You do not have permission to reject company purchase orders.')
            );
            return $this->_redirect('customer/account');
        }

        if (!$this->formKeyValidator->validate($this->getRequest())) {
            return $this->_redirect('*/*/index');
        }

        $poId = (int)$this->getRequest()->getParam('id');
        $actorId = (int)$this->customerSession->getCustomerId();
        $comment = (string)$this->getRequest()->getParam('comment', '');

        try {
            $this->purchaseOrderManagement->rejectPurchaseOrder($poId, $actorId, $comment);
            $this->messageManager->addSuccessMessage(__('The purchase order has been rejected.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('We can\'t reject the purchase order right now.'));
        }

        return $this->_redirect('*/*/index');
    }
}
