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

namespace Orangecat\PurchaseOrder\Block\Account\Navigation;

use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\DefaultPathInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Orangecat\Company\Api\CompanyManagementInterface;
use Orangecat\Company\Api\Data\RoleInterface;

class Link extends \Magento\Framework\View\Element\Html\Link\Current
{
    /**
     * Link constructor.
     * @param Context $context
     * @param DefaultPathInterface $defaultPath
     * @param CustomerSession $customerSession
     * @param CompanyManagementInterface $companyManagement
     * @param array $data
     */
    public function __construct(
        Context $context,
        DefaultPathInterface $defaultPath,
        protected CustomerSession $customerSession,
        protected CompanyManagementInterface $companyManagement,
        array $data = []
    ) {
        parent::__construct($context, $defaultPath, $data);
    }

    /**
     * Render link.
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return '';
        }

        $customerId = (int)$this->customerSession->getCustomerId();
        $roleId = (int)$this->companyManagement->getRoleIdByCustomerId($customerId);

        if ($roleId === RoleInterface::ADMIN_ROLE_ID || $roleId === RoleInterface::MANAGER_ROLE_ID) {
            return parent::_toHtml();
        }

        return '';
    }
}
