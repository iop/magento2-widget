<?php
declare(strict_types=1);

namespace Iop\Widget\Block;

use Magento\Sales\Model\ResourceModel\Order\Item\Collection as OrderItemsCollection;
use Magento\Sales\Model\ResourceModel\Order\Item\CollectionFactory as OrderItemsCollectionFactory;

/**
 * Class FrequentlyOrderedWidget
 *
 */
class FrequentlyOrderedWidget extends AbstractWidget
{
    /**
     * @param array $orderIds
     * @return OrderItemsCollection
     */
    public function getItemsByOrderIds(array $orderIds): OrderItemsCollection
    {
        $agrigatedFieldName = 'total_ordered';
        /** @var OrderItemsCollectionFactory $itemsCollection */
        $itemsCollection = $this->getOrderItemCollectionFactory()
            ->create()
            ->addFieldToFilter(
                'order_id',
                ['in' => $orderIds]
            )->setOrder($agrigatedFieldName, 'desc');

        $itemsCollection->getSelect()
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns(
                [
                    $agrigatedFieldName => new \Zend_Db_Expr('SUM(qty_ordered)'),
                    'product_id',
                    'sku'
                ]
            )
            ->group('product_id');

        return $itemsCollection;
    }
}
