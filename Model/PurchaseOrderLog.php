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

use Magento\Framework\Model\AbstractExtensibleModel;
use Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogInterface;
use Orangecat\PurchaseOrder\Model\ResourceModel\PurchaseOrderLog as PurchaseOrderLogResource;

class PurchaseOrderLog extends AbstractExtensibleModel implements PurchaseOrderLogInterface
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(PurchaseOrderLogResource::class);
    }

    /**
     * @inheritdoc
     */
    public function getPurchaseOrderId()
    {
        return $this->getData(self::PURCHASE_ORDER_ID);
    }

    /**
     * @inheritdoc
     */
    public function setPurchaseOrderId($purchaseOrderId)
    {
        return $this->setData(self::PURCHASE_ORDER_ID, $purchaseOrderId);
    }

    /**
     * @inheritdoc
     */
    public function getActorId()
    {
        return $this->getData(self::ACTOR_ID);
    }

    /**
     * @inheritdoc
     */
    public function setActorId($actorId)
    {
        return $this->setData(self::ACTOR_ID, $actorId);
    }

    /**
     * @inheritdoc
     */
    public function getAction()
    {
        return $this->getData(self::ACTION);
    }

    /**
     * @inheritdoc
     */
    public function setAction($action)
    {
        return $this->setData(self::ACTION, $action);
    }

    /**
     * @inheritdoc
     */
    public function getComment()
    {
        return $this->getData(self::COMMENT);
    }

    /**
     * @inheritdoc
     */
    public function setComment($comment)
    {
        return $this->setData(self::COMMENT, $comment);
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritdoc
     */
    public function getExtensionAttributes()
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * @inheritdoc
     */
    public function setExtensionAttributes(
        \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogExtensionInterface $extensionAttributes
    ) {
        return $this->_setExtensionAttributes($extensionAttributes);
    }
}
