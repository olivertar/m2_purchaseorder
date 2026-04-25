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
 * Interface PurchaseOrderInterface
 */
interface PurchaseOrderInterface extends ExtensibleDataInterface
{
    public const ENTITY_ID = 'entity_id';
    public const INCREMENT_ID = 'increment_id';
    public const QUOTE_ID = 'quote_id';
    public const COMPANY_ID = 'company_id';
    public const CREATOR_ID = 'creator_id';
    public const STATUS = 'status';
    public const GRAND_TOTAL = 'grand_total';
    public const SNAPSHOT = 'snapshot';
    public const ORDER_ID = 'order_id';
    public const ORDER_INCREMENT_ID = 'order_increment_id';
    public const EXPIRES_AT = 'expires_at';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ORDER_PLACED = 'order_placed';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_EXPIRED = 'expired';

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
     * Get increment ID.
     *
     * @return string|null
     */
    public function getIncrementId();

    /**
     * Set increment ID.
     *
     * @param string $incrementId
     * @return $this
     */
    public function setIncrementId($incrementId);

    /**
     * Get quote ID.
     *
     * @return int
     */
    public function getQuoteId();

    /**
     * Set quote ID.
     *
     * @param int $quoteId
     * @return $this
     */
    public function setQuoteId($quoteId);

    /**
     * Get company ID.
     *
     * @return int
     */
    public function getCompanyId();

    /**
     * Set company ID.
     *
     * @param int $companyId
     * @return $this
     */
    public function setCompanyId($companyId);

    /**
     * Get creator ID.
     *
     * @return int|null
     */
    public function getCreatorId();

    /**
     * Set creator ID.
     *
     * @param int $creatorId
     * @return $this
     */
    public function setCreatorId($creatorId);

    /**
     * Get status.
     *
     * @return string
     */
    public function getStatus();

    /**
     * Set status.
     *
     * @param string $status
     * @return $this
     */
    public function setStatus($status);

    /**
     * Get grand total.
     *
     * @return float
     */
    public function getGrandTotal();

    /**
     * Set grand total.
     *
     * @param float $grandTotal
     * @return $this
     */
    public function setGrandTotal($grandTotal);

    /**
     * Get snapshot.
     *
     * @return string|null
     */
    public function getSnapshot();

    /**
     * Set snapshot.
     *
     * @param string $snapshot
     * @return $this
     */
    public function setSnapshot($snapshot);

    /**
     * Get order ID.
     *
     * @return int|null
     */
    public function getOrderId();

    /**
     * Set order ID.
     *
     * @param int $orderId
     * @return $this
     */
    public function setOrderId($orderId);

    /**
     * Get order increment ID.
     *
     * @return string|null
     */
    public function getOrderIncrementId();

    /**
     * Set order increment ID.
     *
     * @param string $orderIncrementId
     * @return $this
     */
    public function setOrderIncrementId($orderIncrementId);

    /**
     * Get expires at.
     *
     * @return string|null
     */
    public function getExpiresAt();

    /**
     * Set expires at.
     *
     * @param string $expiresAt
     * @return $this
     */
    public function setExpiresAt($expiresAt);

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
     * Get updated at.
     *
     * @return string
     */
    public function getUpdatedAt();

    /**
     * Set updated at.
     *
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt);

    /**
     * Get extension attributes.
     *
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set extension attributes.
     *
     * @param \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderExtensionInterface $extensionAttributes
    );
}
