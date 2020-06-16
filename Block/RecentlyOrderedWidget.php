<?php
declare(strict_types=1);

namespace Iop\Widget\Block;

use Magento\Sales\Model\ResourceModel\Order\Item\Collection as OrderItemsCollection;
use Magento\Sales\Model\ResourceModel\Order\Item\CollectionFactory as OrderItemsCollectionFactory;

/**
 * Class RecentlyOrderedWidget
 */
class RecentlyOrderedWidget extends AbstractWidget
{
    /**
     * @param array $orderIds
     * @return OrderItemsCollection
     */
    public function getItemsByOrderIds(array $orderIds): OrderItemsCollection
    {
        /** @var OrderItemsCollectionFactory $itemsCollection */
        $itemsCollection = $this->getOrderItemCollectionFactory()
            ->create()
            ->addFieldToFilter(
                'order_id',
                ['in' => $orderIds]
            )
            ->setOrder('item_id', 'desc');

        return $itemsCollection;
    }
}
