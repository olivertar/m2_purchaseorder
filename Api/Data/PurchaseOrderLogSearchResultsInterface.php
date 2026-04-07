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

use Magento\Framework\Api\SearchResultsInterface;

interface PurchaseOrderLogSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogInterface[]
     */
    public function getItems();

    /**
     * @param \Orangecat\PurchaseOrder\Api\Data\PurchaseOrderLogInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
