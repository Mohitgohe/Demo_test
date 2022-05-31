<?php
namespace Mt\AutoSearch\Model\Autocomplete;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\Search\SearchCriteriaFactory as FullTextSearchCriteriaFactory;
use Magento\Framework\Api\Search\SearchInterface as FullTextSearchApi;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Search\Model\Autocomplete\DataProviderInterface;
use Magento\Search\Model\Autocomplete\ItemFactory;
use Magento\Search\Model\QueryFactory;
use Magento\Store\Model\StoreManagerInterface;

class SearchDataProvider implements DataProviderInterface
{
    const PRODUCTS_NUMBER_IN_SUGGEST = 5;

    /** @var QueryFactory */
    protected $queryFactory;

    /** @var ItemFactory */
    protected $itemFactory;

    /** @var \Magento\Framework\Api\Search\SearchInterface */
    protected $fullTextSearchApi;

    /** @var FullTextSearchCriteriaFactory */
    protected $fullTextSearchCriteriaFactory;

    /** @var FilterGroupBuilder */
    protected $searchFilterGroupBuilder;

    /** @var FilterBuilder */
    protected $filterBuilder;

    /** @var ProductRepositoryInterface */
    protected $productRepository;

    /** @var SearchCriteriaBuilder */
    protected $searchCriteriaBuilder;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var ProductHelper
     */
    protected $productHelper;

    /** @var \Magento\Catalog\Helper\Image */
    protected $imageHelper;

    /**
     * Initialize dependencies.
     *
     * @param QueryFactory                                      $queryFactory
     * @param ItemFactory                                       $itemFactory
     * @param FullTextSearchApi                                 $search
     * @param FullTextSearchCriteriaFactory                     $searchCriteriaFactory
     * @param FilterGroupBuilder                                $searchFilterGroupBuilder
     * @param FilterBuilder                                     $filterBuilder
     * @param ProductRepositoryInterface                        $productRepository
     * @param SearchCriteriaBuilder                             $searchCriteriaBuilder
     * @param StoreManagerInterface                             $storeManager
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
     * @param \Magento\Catalog\Helper\Image                     $imageHelper
     */
    public function __construct(
        QueryFactory $queryFactory,
        ItemFactory $itemFactory,
        FullTextSearchApi $search,
        FullTextSearchCriteriaFactory $searchCriteriaFactory,
        FilterGroupBuilder $searchFilterGroupBuilder,
        FilterBuilder $filterBuilder,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        StoreManagerInterface $storeManager,
        PriceCurrencyInterface $priceCurrency,
        Image $imageHelper
    )
    {
        $this->queryFactory                  = $queryFactory;
        $this->itemFactory                   = $itemFactory;
        $this->fullTextSearchApi             = $search;
        $this->fullTextSearchCriteriaFactory = $searchCriteriaFactory;
        $this->filterBuilder                 = $filterBuilder;
        $this->searchFilterGroupBuilder      = $searchFilterGroupBuilder;
        $this->productRepository             = $productRepository;
        $this->searchCriteriaBuilder         = $searchCriteriaBuilder;
        $this->storeManager                  = $storeManager;
        $this->priceCurrency                 = $priceCurrency;
        $this->imageHelper                   = $imageHelper;
    }

    /**
     * getItems method
     *
     * @return array
     */
    public function getItems()
    {
        $result     = [ ];
        $query      = $this->queryFactory->get()->getQueryText();
        $productIds = $this->searchProductsFullText($query);

      
        if ( $productIds )
        {
            $searchCriteria = $this->searchCriteriaBuilder->addFilter('entity_id', $productIds, 'in')->create();
            $products       = $this->productRepository->getList($searchCriteria);

            foreach ( $products->getItems() as $product )
            {

                $image = $this->imageHelper->init($product, 'product_page_image_thumbail')->getBigUrl();

                $resultItem = $this->itemFactory->create([
                    'title'             => $product->getName(),
                    'price'             => $this->priceCurrency->format($product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue(),false),
                    'special_price'     => $this->priceCurrency->format($product->getPriceInfo()->getPrice('special_price')->getAmount()->getValue(),false),
                    'has_special_price' => $product->getSpecialPrice() > 0 ? true : false,
                    'image'             => $image,
                    'url'               => $product->getProductUrl()
                ]);
                $result[]   = $resultItem;
            }
        }

        return $result;
    }

    /**
     * Perform full text search and find IDs of matching products.
     *
     * @param $query
     *
     * @return array
     */
    protected function searchProductsFullText($query)
    {
        $searchCriteria = $this->fullTextSearchCriteriaFactory->create();

        
        $searchCriteria->setRequestName('quick_search_container');
        $filter      = $this->filterBuilder->setField('search_term')->setValue($query)->setConditionType('like')->create();
        $filterGroup = $this->searchFilterGroupBuilder->addFilter($filter)->create();
        $currentPage = 1;
        $searchCriteria->setFilterGroups([ $filterGroup ])
            ->setCurrentPage($currentPage)
            ->setPageSize(self::PRODUCTS_NUMBER_IN_SUGGEST);
        $searchResults = $this->fullTextSearchApi->search($searchCriteria);
        $productIds    = [ ];

        foreach ( $searchResults->getItems() as $searchDocument )
        {
            $productIds[] = $searchDocument->getId();
        }

        return $productIds;
    }
}