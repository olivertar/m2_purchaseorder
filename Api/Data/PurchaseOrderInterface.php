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

interface PurchaseOrderInterface extends ExtensibleDataInterface
{
    const ENTITY_ID = 'entity_id';
    const INCREMENT_ID = 'increment_id';
    const QUOTE_ID = 'quote_id';
    const COMPANY_ID = 'company_id';
    const CREATOR_ID = 'creator_id';
    const STATUS = 'status';
    const GRAND_TOTAL = 'grand_total';
    const SNAPSHOT = 'snapshot';
    const ORDER_ID = 'order_id';
    const ORDER_INCREMENT_ID = 'order_increment_id';
    const EXPIRES_AT = 'expires_at';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_ORDER_PLACED = 'order_placed';
    const STATUS_CANCELED = 'canceled';
    const STATUS_EXPIRED = 'expired';

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
     * @return string|null
     */
    public function getIncrementId();

    /**
     * @param string $incrementId
     * @return $this
     */
    public function setIncrementId($incrementId);

    /**
     * @return int
     */
    public function getQuoteId();

    /**
     * @param int $quoteId
     * @return $this
     */
    public function setQuoteId($quoteId);

    /**
     * @return int
     */
    public function getCompanyId();

    /**
     * @param int $companyId
     * @return $this
     */
    public function setCompanyId($companyId);

    /**
     * @return int|null
     */
    public function getCreatorId();

    /**
     * @param int $creatorId
     * @return $this
     */
    public function setCreatorId($creatorId);

    /**
     * @return string
     */
    public function getStatus();

    /**
     * @param string $status
     * @return $this
     */
    public function setStatus($status);

    /**
     * @return float
     */
    public function getGrandTotal();

    /**
     * @param float $grandTotal
     * @return $this
     */
    public function setGrandTotal($grandTotal);

    /**
     * @return string|null
     */
    public function getSnapshot();

    /**
     * @param string $snapshot
     * @return $this
     */
    public function setSnapshot($snapshot);

    /**
     * @return int|null
     */
    public function getOrderId();

    /**
     * @param int $orderId
     * @return $this
     */
    public function setOrderId($orderId);

    /**
     * @return string|null
     */
    public function getOrderIncrementId();

    /**
     * @param string $orderIncrementId
     * @return $this
     */
    public function setOrderIncrementId($orderIncrementId);

    /**
     * @return string|null
     */
    public function getExpiresAt();

    /**
     * @param string $expiresAt
     * @return $this
     */
    public function setExpiresAt($expiresAt);

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
     * @return string
     */
    public function getUpdatedAt();

    /**
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt);

    /**
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * @param \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderExtensionInterface $extensionAttributes
    );
}
