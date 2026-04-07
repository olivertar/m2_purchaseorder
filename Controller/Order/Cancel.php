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
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Orangecat\PurchaseOrder\Model\PurchaseOrderManagement;

class Cancel extends Action implements HttpPostActionInterface
{
    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * @var PurchaseOrderManagement
     */
    protected $purchaseOrderManagement;

    /**
     * @var Validator
     */
    protected $formKeyValidator;

    /**
     * @param Context $context
     * @param Session $customerSession
     * @param PurchaseOrderManagement $purchaseOrderManagement
     * @param Validator $formKeyValidator
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        PurchaseOrderManagement $purchaseOrderManagement,
        Validator $formKeyValidator
    ) {
        $this->customerSession = $customerSession;
        $this->purchaseOrderManagement = $purchaseOrderManagement;
        $this->formKeyValidator = $formKeyValidator;
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

        if (!$this->formKeyValidator->validate($this->getRequest())) {
            return $this->_redirect('*/*/index');
        }

        $poId = (int)$this->getRequest()->getParam('id');
        $customerId = (int)$this->customerSession->getCustomerId();

        try {
            $this->purchaseOrderManagement->cancelPurchaseOrder($poId, $customerId);
            $this->messageManager->addSuccessMessage(__('You successfully canceled the purchase order.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('We can\'t cancel the purchase order right now.'));
        }

        return $this->_redirect('*/*/view', ['id' => $poId]);
    }
}
