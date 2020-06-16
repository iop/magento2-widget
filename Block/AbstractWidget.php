<?php
declare(strict_types=1);

namespace Iop\Widget\Block;

use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Customer\Model\SessionFactory;
use Magento\Framework\Data\CollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Item\Collection as OrderItemsCollection;
use Magento\Sales\Model\ResourceModel\Order\Item\CollectionFactory as OrderItemCollectionFactory;
use Magento\Widget\Block\BlockInterface;

/**
 * Class AbstractWidget
 */
abstract class AbstractWidget extends AbstractProduct implements BlockInterface
{
    /**
     * Default products count that will be shown
     */
    const DEFAULT_PAGE_SIZE = 4;
    /**
     * Default days value for search period
     */
    const SEARCH_DAYS_PERIOD = 28;
    /**
     * Extend days search period
     */
    const EXTENDS_DAYS_IF_NOTHING_FOUND = true;
    /**
     * @var ProductCollectionFactory
     */
    protected $productCollectionFactory;
    /**
     * @var OrderCollectionFactory
     */
    protected $orderCollectionFactory;
    /**
     * @var OrderItemCollectionFactory
     */
    protected $orderItemCollectionFactory;
    /**
     * @var OrderCollectionFactory
     */
    protected $orders;
    /**
     * @var ProductVisibility
     */
    protected $catalogProductVisibility;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;
    /**
     * Customer id
     *
     * @var null|int
     */
    protected $customerId = null;
    /**
     * @var SessionFactory
     */
    protected $currentSessionFactory;

    public function __construct(
        Context $context,
        ProductCollectionFactory $productCollectionFactory,
        ProductVisibility $catalogProductVisibility,
        OrderCollectionFactory $orderCollectionFactory,
        OrderItemCollectionFactory $orderItemCollectionFactory,
        CollectionFactory $collectionFactory,
        SessionFactory $currentSessionFactory,
        array $data = []
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->catalogProductVisibility = $catalogProductVisibility;
        $this->collectionFactory = $collectionFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderItemCollectionFactory = $orderItemCollectionFactory;
        $this->currentSessionFactory = $currentSessionFactory;

        parent::__construct(
            $context,
            $data
        );
    }

    /**
     * Get items by order id(s).
     *
     * @param array $orderIds
     * @return OrderItemsCollection
     */
    abstract public function getItemsByOrderIds(array $orderIds): OrderItemsCollection;

    /**
     * Retrieve items collection
     *
     * @return \Magento\Framework\Data\Collection|null
     * @throws \Exception
     */
    public function getItemsCollection()
    {
        $collection = null;

        /** @var array $productIds */
        $productIds = $this->findProductIdsFromOrders();

        if (is_array($productIds)) {

            $ids = array_reverse($productIds);

            /** @var ProductCollection $productCollection */
            $productCollection = $this->createProductCollection($ids);

            if ($productCollection->getSize()) {
                /** @var Magento\Framework\Data\CollectionFactory $collection */
                $collection = $this->getCollectionFactory()->create();

                foreach ($productCollection as $item) {
                    $collection->addItem($item);
                    if ($this->getPageSize() == $collection->getSize()) {
                        break;
                    }
                }

                return $collection;
            }
        }

        return $collection;
    }

    /**
     * @return array
     */
    protected function findProductIdsFromOrders(): array
    {
        $productIds = [];

        /** @var OrderCollectionFactory $ordersCollection */
        $ordersCollection = $this->findOrdersByPeriod($this->getSearchDaysPeriod());

        if ($ordersCollection && $ordersCollection->getSize()) {

            $itemsCollection = $this->getItemsByOrderIds($ordersCollection->getAllIds());

            foreach ($itemsCollection as $item) {
                if (!in_array($item->getProductId(), $productIds)) {
                    $productIds[] = $item->getProductId();
                }
            }
        }

        return $productIds;
    }

    /**
     * @param int $daysPeriod
     * @return bool|Collection
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function findOrdersByPeriod(int $daysPeriod)
    {
        /** @var OrderCollectionFactory $ordersCollection */
        $ordersCollection = $this->getOrdersByPeriod($daysPeriod);

        if (($ordersCollection && !$ordersCollection->getSize()) && $this->isExtendedDaySearchPeriod()) {
            /** @var OrderCollectionFactory $ordersCollection */
            $ordersCollection = $this->findOrdersByPeriod($daysPeriod * 3);
        }

        return $ordersCollection;
    }

    /**
     * @param int $daysPeriod
     * @return bool|OrderCollectionFactory
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getOrdersByPeriod(int $daysPeriod)
    {
        $customerId = $this->getCustomerId();

        if (!$customerId) {
            return false;
        }

        $now = new \DateTime(date('Y-m-d', strtotime("-$daysPeriod days")));

        /** @var Magento\Sales\Model\ResourceModel\Order\CollectionFactory $collection */
        $collection = $this->getOrderCollectionFactory()->create($customerId)
            ->addFieldToSelect(
                'entity_id'
            )->addFieldToFilter(
                'store_id',
                $this->_storeManager->getStore()->getId()
            )->addFieldToFilter(
                'created_at',
                ['gteq' => $now->format('Y-m-d H:i:s')]
            )->setOrder(
                'entity_id',
                'desc'
            );

        return $collection;
    }

    /**
     * @param array $productIds
     * @return ProductCollection
     */
    protected function createProductCollection(array $productIds)
    {
        /** @var ProductCollection $collection */
        $collection = $this->getProductCollectionFactory()->create();
        $collection->setVisibility($this->getCatalogProductVisibility()->getVisibleInCatalogIds());

        $collection = $this->_addProductAttributesAndPrices($collection)
            ->addStoreFilter()
            ->addIdFilter($productIds);

        return $collection;
    }

    /**
     * Return HTML block with price
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param string $priceType
     * @param string $renderZone
     * @param array $arguments
     * @return string
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getProductPriceHtml(
        \Magento\Catalog\Model\Product $product,
        $priceType = null,
        $renderZone = \Magento\Framework\Pricing\Render::ZONE_ITEM_LIST,
        array $arguments = []
    ) {
        if (!isset($arguments['zone'])) {
            $arguments['zone'] = $renderZone;
        }
        $arguments['zone'] = isset($arguments['zone'])
            ? $arguments['zone']
            : $renderZone;
        $arguments['price_id'] = isset($arguments['price_id'])
            ? $arguments['price_id']
            : 'old-price-' . $product->getId() . '-' . $priceType;
        $arguments['include_container'] = isset($arguments['include_container'])
            ? $arguments['include_container']
            : true;
        $arguments['display_minimal_price'] = isset($arguments['display_minimal_price'])
            ? $arguments['display_minimal_price']
            : true;

        /** @var \Magento\Framework\Pricing\Render $priceRender */
        $priceRender = $this->getLayout()->getBlock('product.price.render.default');

        $price = '';
        if ($priceRender) {
            $price = $priceRender->render(
                \Magento\Catalog\Pricing\Price\FinalPrice::PRICE_CODE,
                $product,
                $arguments
            );
        }
        return $price;
    }

    /**
     * @param int $customerId
     */
    protected function setCustomerId(int $customerId): void
    {
        $this->customerId = $customerId;
    }

    /**
     * @return int|null
     */
    protected function getCustomerId()
    {
        return $this->currentSessionFactory->create()->getId();
    }

    /**
     * @return OrderCollectionFactory
     */
    protected function getOrderCollectionFactory(): OrderCollectionFactory
    {
        return $this->orderCollectionFactory;
    }

    /**
     * @return OrderItemCollectionFactory
     */
    protected function getOrderItemCollectionFactory(): OrderItemCollectionFactory
    {
        return $this->orderItemCollectionFactory;
    }

    /**
     * @return ProductCollectionFactory
     */
    protected function getProductCollectionFactory(): ProductCollectionFactory
    {
        return $this->productCollectionFactory;
    }

    /**
     * @return ProductVisibility
     */
    protected function getCatalogProductVisibility(): ProductVisibility
    {
        return $this->catalogProductVisibility;
    }

    /**
     * @return CollectionFactory
     */
    protected function getCollectionFactory(): CollectionFactory
    {
        return $this->collectionFactory;
    }

    /**
     * @return array
     */
    public function getShowButtons(): array
    {
        $value = $this->getData('show_buttons');

        return explode(",", $value);
    }

    /**
     * @return array
     */
    public function getShowAttributes(): array
    {
        $value = $this->getData('show_attributes');

        return explode(",", $value);
    }

    /**
     * @return bool
     */
    public function isShowAddtoCart(): bool
    {
        return in_array('add_to_cart', $this->getShowButtons());
    }

    /**
     * @return bool
     */
    public function isShowAddtoCompare(): bool
    {
        return in_array('add_to_compare', $this->getShowButtons());
    }

    /**
     * @return bool
     */
    public function isShowAddtoWishlist(): bool
    {
        return in_array('add_to_wishlist', $this->getShowButtons());
    }

    /**
     * @return bool
     */
    public function isShowProductName(): bool
    {
        return in_array('name', $this->getShowAttributes());
    }

    /**
     * @return bool
     */
    public function isShowProductImg(): bool
    {
        return in_array('image', $this->getShowAttributes());
    }

    /**
     * @return bool
     */
    public function isShowProductPrice(): bool
    {
        return in_array('price', $this->getShowAttributes());
    }

    /**
     * @return bool
     */
    public function isShowLearnMoreLink(): bool
    {
        return in_array('learn_more', $this->getShowAttributes());
    }

    /**
     * @return int
     */
    protected function getPageSize(): int
    {
        if (!$this->getData('page_size')) {
            $this->setData('page_size', self::DEFAULT_PAGE_SIZE);
        }
        return (int)$this->getData('page_size');
    }

    /**
     * @return int
     */
    protected function getSearchDaysPeriod(): int
    {
        if (!$this->getData('search_days_period')) {
            $this->setData('search_days_period', self::SEARCH_DAYS_PERIOD);
        }
        return (int)$this->getData('search_days_period');
    }

    /**
     * @return bool
     */
    protected function isExtendedDaySearchPeriod(): bool
    {
        if (!$this->getData('extends_days_if_nothing_found')) {
            $this->setData('extends_days_if_nothing_found', self::EXTENDS_DAYS_IF_NOTHING_FOUND);
        }
        return (bool)$this->getData('extends_days_if_nothing_found');
    }
}
