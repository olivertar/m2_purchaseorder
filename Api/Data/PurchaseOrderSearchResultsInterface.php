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
 * Interface PurchaseOrderSearchResultsInterface
 */
interface PurchaseOrderSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get purchase orders list.
     *
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface[]
     */
    public function getItems();

    /**
     * Set purchase orders list.
     *
     * @param \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
