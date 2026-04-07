<?php
/**
 * This file is part of the Orangecat PurchaseOrder package.
 *
 * (c) Oliverio Gombert <olivertar@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Orangecat\PurchaseOrder\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

interface PurchaseOrderLogInterface extends ExtensibleDataInterface
{
    const LOG_ID = 'log_id';
    const PURCHASE_ORDER_ID = 'purchase_order_id';
    const ACTOR_ID = 'actor_id';
    const ACTION = 'action';
    const COMMENT = 'comment';
    const CREATED_AT = 'created_at';

    /**
     * @return int|null
     */
    public function getId();

    /**
     * @param int $id
     * @return $this
     */
    public function setId($id);

    /**
     * @return int
     */
    public function getPurchaseOrderId();

    /**
     * @param int $purchaseOrderId
     * @return $this
     */
    public function setPurchaseOrderId($purchaseOrderId);

    /**
     * @return int|null
     */
    public function getActorId();

    /**
     * @param int $actorId
     * @return $this
     */
    public function setActorId($actorId);

    /**
     * @return string
     */
    public function getAction();

    /**
     * @param string $action
     * @return $this
     */
    public function setAction($action);

    /**
     * @return string|null
     */
    public function getComment();

    /**
     * @param string $comment
     * @return $this
     */
    public function setComment($comment);

    /**
     * @return string
     */
    public function getCreatedAt();

    /**
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt);

    /**
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * @param \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogExtensionInterface $extensionAttributes
    );
}
