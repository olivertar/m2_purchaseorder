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

namespace Orangecat\PurchaseOrder\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * Interface PurchaseOrderLogInterface
 */
interface PurchaseOrderLogInterface extends ExtensibleDataInterface
{
    public const LOG_ID = 'log_id';
    public const PURCHASE_ORDER_ID = 'purchase_order_id';
    public const ACTOR_ID = 'actor_id';
    public const ACTION = 'action';
    public const COMMENT = 'comment';
    public const CREATED_AT = 'created_at';

    /**
     * Get ID.
     *
     * @return int|null
     */
    public function getId();

    /**
     * Set ID.
     *
     * @param int $id
     * @return $this
     */
    public function setId($id);

    /**
     * Get purchase order ID.
     *
     * @return int
     */
    public function getPurchaseOrderId();

    /**
     * Set purchase order ID.
     *
     * @param int $purchaseOrderId
     * @return $this
     */
    public function setPurchaseOrderId($purchaseOrderId);

    /**
     * Get actor ID.
     *
     * @return int|null
     */
    public function getActorId();

    /**
     * Set actor ID.
     *
     * @param int $actorId
     * @return $this
     */
    public function setActorId($actorId);

    /**
     * Get action.
     *
     * @return string
     */
    public function getAction();

    /**
     * Set action.
     *
     * @param string $action
     * @return $this
     */
    public function setAction($action);

    /**
     * Get comment.
     *
     * @return string|null
     */
    public function getComment();

    /**
     * Set comment.
     *
     * @param string $comment
     * @return $this
     */
    public function setComment($comment);

    /**
     * Get created at.
     *
     * @return string
     */
    public function getCreatedAt();

    /**
     * Set created at.
     *
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt);

    /**
     * Get extension attributes.
     *
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set extension attributes.
     *
     * @param \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogExtensionInterface $extensionAttributes
    );
}
