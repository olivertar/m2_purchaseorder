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

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface PurchaseOrderLogSearchResultsInterface
 */
interface PurchaseOrderLogSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get purchase order logs list.
     *
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogInterface[]
     */
    public function getItems();

    /**
     * Set purchase order logs list.
     *
     * @param \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
