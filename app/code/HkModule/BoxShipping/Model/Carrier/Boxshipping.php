<?php

namespace HkModule\BoxShipping\Model\Carrier;
 
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
 
class Boxshipping extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'boxshipping';
    
     /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

     /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    protected $_collectionFactory;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var array
     */
    protected $box = ['A'=>[],'B'=>[],'C'=>[]];


    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $collecionFactory,
        array $data = []
    ) {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_logger = $logger;
        $this->_checkoutSession = $checkoutSession;
        $this->_collectionFactory = $collecionFactory;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }
 
    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return ['boxshipping' => $this->getConfigData('name')];
    }
 
    /**
     * @param RateRequest $request
     * @return bool|Result
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $items = $this->getAllItems($request);
        $boxes = [];
        $quoteId = (count($items) >0)?$items[0]->getQuoteId():null;

        $item_size = count($items);
        for ($i = 0; $i < $item_size; $i++) {
            $categoryIds = $items[$i]->getProduct()->getCategoryIds();
            $weight = $items[$i]->getWeight();
            $product_id = $items[$i]->getProductId();
            $name = $items[$i]->getName();
            $item_price = $items[$i]->getPrice();
            $qty = $items[$i]->getQty();

            # Get category id
            $category_a_name = trim($this->getConfigData('box_a_category'));
            $category_b_name = trim($this->getConfigData('box_b_category'));
            $category_c_name = explode(',', trim($this->getConfigData('box_c_category')));

            switch ($categoryIds) {
                case in_array($this->getCategoryId($category_a_name), $categoryIds):
                    $boxes['A'][] = ['weight'=>$weight,'name'=>$name, 'price'=>$item_price, 'qty'=>$qty, 'product_id'=>$product_id, 'category'=>$category_a_name];
                    break;
                case in_array($this->getCategoryId($category_b_name), $categoryIds):
                    $boxes['B'][] = ['weight'=>$weight, 'name'=>$name,'price'=>$item_price, 'qty'=>$qty, 'product_id'=>$product_id, 'category'=>$category_b_name];
                    break;
                case isset($category_c_name[0]) && in_array($this->getCategoryId($category_c_name[0]), $categoryIds):
                    $boxes['C']['nutrition'][] = ['weight'=>$weight, 'name'=>$name,'price'=>$item_price, 'qty'=>$qty, 'product_id'=>$product_id, 'category'=>$category_c_name[0]];
                    break;
                case isset($category_c_name[1]) && in_array($this->getCategoryId($category_c_name[1]), $categoryIds):
                    $boxes['C']['cosmetic'][] = ['weight'=>$weight, 'name'=>$name,'price'=>$item_price, 'qty'=>$qty, 'product_id'=>$product_id, 'category'=>$category_c_name[1]];
                    break;
            }
        }
        
        $box_price = 0;

        # Box A calculation
        if (isset($boxes['A'])) {
            $box_price += $this->getBoxPrice(
                $boxes['A'],
                (float)$this->getConfigData('box_a_price'),
                (float)$this->getConfigData('box_a_weight'),
                intval($this->getConfigData('box_a_max_items')),
                'A'
            );
        }

        

        # Box B calculation
        if (isset($boxes['B'])) {
            $box_price += $this->getBoxPrice(
                $boxes['B'],
                (float)$this->getConfigData('box_b_price'),
                (float)$this->getConfigData('box_b_weight'),
                intval($this->getConfigData('box_b_max_items')),
                'B'
            );
        }

        # Box C calculation
        if (isset($boxes['C'])) {
            $box_price += $this->getBoxPrice(
                $boxes['C'],
                (float)$this->getConfigData('box_c_price'),
                (float)$this->getConfigData('box_c_weight'),
                intval($this->getConfigData('box_c_max_items')),
                'C'
            );
        }
        
        
        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->_rateResultFactory->create();
 
        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->_rateMethodFactory->create();
 
        $method->setCarrier('boxshipping');
        $method->setCarrierTitle($this->getConfigData('title'));
 
        $method->setMethod('boxshipping');
        $method->setMethodTitle($this->getConfigData('name'));
 
        /*you can fetch shipping price from different sources over some APIs, we used price from config.xml - xml node price*/
        //$amount = $this->getConfigData('price');

        $amount = round($box_price, 2);

        $method->setPrice($amount);
        $method->setCost($amount);
 
        $result->append($method);

        //update box amount
        $this->_checkoutSession->setBox($this->box);
 
        return $result;
    }

    /**
     * Return items for further shipment rate evaluation. We need to pass children of a bundle instead passing the
     * bundle itself, otherwise we may not get a rate at all (e.g. when total weight of a bundle exceeds max weight
     * despite each item by itself is not)
     *
     * @param RateRequest $request
     * @return array
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @api
     */
    public function getAllItems(RateRequest $request)
    {
        $items = [];
        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                /* @var $item \Magento\Quote\Model\Quote\Item */
                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    // Don't process children here - we will process (or already have processed) them below
                    continue;
                }

                if ($item->getHasChildren() && $item->isShipSeparately()) {
                    foreach ($item->getChildren() as $child) {
                        if (!$child->getFreeShipping() && !$child->getProduct()->isVirtual()) {
                            $items[] = $child;
                        }
                    }
                } else {
                    // Ship together - count compound item as one solid
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * @param string $categoryTitle
     * @return int|null
     */
    public function getCategoryId($categoryTitle)
    {
        $categoryId = null;
        $collection = $this->_collectionFactory
                ->create()
                ->addAttributeToFilter('name', $categoryTitle)
                ->setPageSize(1);

        if ($collection->getSize()) {
            $categoryId = $collection->getFirstItem()->getId();
        }

        return $categoryId;
    }

    /**
     * @param array $box
     * @param float $box_price
     * @param float $box_weight
     * @param float $box_max_items
     * @return float|null
     */
    public function getBoxPrice($box, $box_price, $box_weight, $box_max_items, $box_type)
    {
        // get box count
        if ($box_type == 'C') {
            $box_count = $this->getCBoxCount($box, $box_max_items, $box_type);
            $box_cosmetic = isset($box['cosmetic'])?$box['cosmetic']:[];
            $box_nutrition = isset($box['nutrition'])?$box['nutrition']:[];
            $box = array_merge($box_cosmetic, $box_nutrition);
        } else {
            $box_count = $this->getBoxCount($box, $box_max_items, $box_type);
        }
        $this->_logger->info('Shipping: '.print_r($this->box, true));
        // get formula category products weight
        $box_products_weight = 0;
        $boxprice = 0;
        # Box price calculation
        if (isset($box)) {
            foreach ($box as $key => $value) {
                $box_products_weight += ($value['qty']*$value['weight']);
            }
            $boxprice += (($box_products_weight + ($box_count * $box_weight)) * $box_price);
        }
        $this->_logger->info('Shipping:'.print_r($this->box, true));
        return $boxprice;
    }

    
    /**
     * @param array $box
     * @param int $max_items
     * @return int|null
     */
    public function getBoxCount($box, $max_items, $box_type)
    {
        # Box Count calculation
        $qty = 0;
        $box_count = 0;
        $products = [];
        if (isset($box)) {
            foreach ($box as $value) {
                $qty += $value['qty'];

                for ($i=1; $i <= $value['qty']; $i++) {
                    $products[] = ['id'=>$value['product_id'], 'name'=>$value['name'],
                    'qty'=>$value['qty'], 'price'=>$value['price'],'weight'=>$value['weight']];
                }
            }

            if ($qty > $max_items) {
                $extra_qty = $qty % $max_items;
                $box_count = ($qty - $extra_qty)/$max_items;
                if ($extra_qty > 0) {
                    $box_count +=1;
                }
            } else {
                $box_count = 1;
            }
            $this->box[$box_type] = array_chunk($products, $max_items);
        }
        
        return $box_count;
    }

    /**
     * @param array $box
     * @param int $max_items
     * @return int|null
     */
    public function getCBoxCount($box, $max_items, $box_type)
    {
        $nutrition_box = isset($box['nutrition'])?$box['nutrition']:[];
        $nutrition = $this->getNutritionBoxCount($nutrition_box, $max_items);
        
        $box_count = $nutrition['box_count'];
        $max_price = (float)$this->getConfigData('max_price_value_box_c');
        $max_weight_box_c = floatval($this->getConfigData('max_weight_box_c'));
        $allowed_same_nutrition = intval($this->getConfigData('same_nutrition_items_c'));
        $qtyArray = [];
        $itemQty = 0;
        $itemPrice = 0;
        $itemWeight = 0;
        $item_qty = 0;
        $products = [];
        $extra_products = [];
        if (!isset($box['cosmetic'])) {
            $box['cosmetic'] = [];
        }

        $box = array_merge($nutrition['extra'], $box['cosmetic']);

        foreach ($box as $value) {
            $x = 1;
            $price = 0;
            $weight = 0;
            $item_count = 0;
            $used_qty = 0;
            for ($i = 1; $i <= $value['qty']; $i++) {
                $new_price = isset($value['price'])?$value['price']:0;
                $new_weight = isset($value['weight'])?$value['weight']:0;

                if ($i == $value['qty']) {
                    $new_price = 1;
                    $new_weight = 1;
                }
                $price += $value['price'];
                $weight += $value['weight'];

                if ((($weight < $max_weight_box_c) && (($price + $new_price) == $max_price))
                    || (($price < $max_price) && (($weight + $new_weight) == $max_weight_box_c))) {
                    #pass
                } elseif (($price + $new_price) >= $max_price || ($weight + $new_weight) >= $max_weight_box_c) {
                    $item_count = $x;
                    $used_qty += $item_count;
                    $box_count += 1;
                    $product = $value;
                    $product['qty'] = $item_count;
                    $this->box['C'][] = [$product];
                    #Reset value
                    $x = 0;
                    $price = 0;
                    $weight = 0;
                } elseif (($i - $used_qty)%$max_items == 0) {
                    $item_count = $x;
                    $used_qty += $item_count;
                    $box_count += 1;
                    $product = $value;
                    $product['qty'] = $item_count;
                    $this->box['C'][] = [$product];
                    #Reset value
                    $x = 0;
                    $price = 0;
                    $weight = 0;
                }

                if ($i == $value['qty']) {
                    $itemPrice += $price;
                    $itemWeight += $weight;
                    $extra_qty = $value['qty'] - $used_qty;
                    $itemQty += $extra_qty;
                    if ($extra_qty > 0) {
                        $value['qty'] = $extra_qty;
                        $extra_products[] = $value;
                    }
                }

                $x++;
            }
        }
        
        $box_count = $box_count + $this->getExtraBoxCount($extra_products, $max_items, $max_weight_box_c, $max_price);

        return $box_count;
    }

    
    /**
     * @param array $box
     * @param int $max_items
     * @return array|null
     */
    public function getNutritionBoxCount($box, $max_items)
    {
        # get box count with price range
        $box_count = 0;
        $max_price = (float)$this->getConfigData('max_price_value_box_c');
        $max_weight_box_c = floatval($this->getConfigData('max_weight_box_c'));
        $allowed_same_nutrition = intval($this->getConfigData('same_nutrition_items_c'));
        $qtyArray = [];
        $itemQty = 0;
        $itemPrice = 0;
        $itemWeight = 0;
        $item_qty = 0;
        $products = [];
        $extra_products = [];

        foreach ($box as $value) {
            $x = 1;
            $price = 0;
            $weight = 0;
            $item_count = 0;
            $used_qty = 0;
            for ($i = 1; $i <= $value['qty']; $i++) {
                $new_price = isset($value['price'])?$value['price']:0;
                $new_weight = isset($value['weight'])?$value['weight']:0;

                if ($i == $value['qty']) {
                    $new_price = 1;
                    $new_weight = 1;
                }
                $price += $value['price'];
                $weight += $value['weight'];

                if ((($weight < $max_weight_box_c) && (($price + $new_price) == $max_price))
                    || (($price < $max_price) && (($weight + $new_weight) == $max_weight_box_c))) {
                    #pass
                } elseif (($price + $new_price) >= $max_price || ($weight + $new_weight) >= $max_weight_box_c) {
                    $item_count = $x;
                    $used_qty += $item_count;
                    $box_count += 1;
                    $product = $value;
                    $product['qty'] = $item_count;
                    $this->box['C'][] = [$product];
                    #Reset value
                    $x = 0;
                    $price = 0;
                    $weight = 0;
                } elseif (($i - $used_qty)%$allowed_same_nutrition == 0) {
                    $item_count = $x;
                    $used_qty += $item_count;
                    $box_count += 1;
                    $product = $value;
                    $product['qty'] = $item_count;
                    $this->box['C'][] = [$product];
                    #Reset value
                    $x = 0;
                    $price = 0;
                    $weight = 0;
                }

                if ($i == $value['qty']) {
                    $itemPrice += $price;
                    $itemWeight += $weight;
                    $extra_qty = $value['qty'] - $used_qty;
                    $itemQty += $extra_qty;
                    if ($extra_qty > 0) {
                        $value['qty'] = $extra_qty;
                        $extra_products[] = $value;
                    }
                }

                $x++;
            }
        }


        return ['box_count'=>$box_count, 'price'=>$itemPrice, 'weight'=>$itemWeight, 'qty'=>$itemQty, 'extra'=>$extra_products];
    }

    public function getExtraBoxCount($products, $max_items, $max_weight_box_c, $max_price_value_box_c)
    {
        $box_count = 0;
        $cosmeticCollections = [];
        $nutritionCollections = [];
        $allowed_same_nutrition = intval($this->getConfigData('same_nutrition_items_c'));
        $category_c_name = explode(',', trim($this->getConfigData('box_c_category')));
        foreach ($products as $product) {
            for ($i=0; $i < $product['qty']; $i++) {
                $product['qty'] = 1;
                if (strtolower($product['category']) == strtolower($category_c_name[0])) {
                    $nutritionCollections[] = $product;
                } else {
                    $cosmeticCollections[] = $product;
                }
            }
        }

        $nutritions = $this->getNutritionCosmeticBoxCount($nutritionCollections, $max_weight_box_c, $max_price_value_box_c, $allowed_same_nutrition);

        $box_count += $nutritions['box_count'];
        $nextra_products = $nutritions['extra_products'];

        $cosmetics = $this->getNutritionCosmeticBoxCount($cosmeticCollections, $max_weight_box_c, $max_price_value_box_c, $max_items);

        $box_count += $cosmetics['box_count'];
        $cextra_products = $cosmetics['extra_products'];

        $collections = array_merge($nextra_products, $cextra_products);

        $combine = $this->getNutritionCosmeticBoxCount($collections, $max_weight_box_c, $max_price_value_box_c, $max_items, false);

        $box_count += $combine['box_count'];
        
        return $box_count;
    }

    public function getNutritionCosmeticBoxCount($collections, $max_weight, $max_price, $max_items, $separate = true)
    {
        $weight = 0;
        $price = 0;
        $qty = 0;
        $box_count = 0;
        $lenght = count($collections);
        $used_qty = 0;
        $boxProducts = [];
        $extraProducts = [];

        $x = 0;
        for ($i=0; $i < $lenght; $i++) {
            $weight += $collections[$i]['weight'];
            $price += $collections[$i]['price'];
            $new_price = isset($collections[$i+1])?$collections[$i+1]['price']:0;
            $new_weight = isset($collections[$i+1])?$collections[$i+1]['weight']:0;

            $boxProducts[] = $collections[$i];
            $x++;
            if ($weight >= $max_weight || $price >= $max_price) {
                $this->box['C'][] = $boxProducts;
                $used_qty += $x;
                $box_count += 1;
                # Reset value
                $boxProducts = [];
                $weight = 0;
                $price = 0;
                $x = 0;
            } elseif (($weight + $new_weight) >= $max_weight || ($price + $new_price)  >= $max_price) {
                $this->box['C'][] = $boxProducts;
                $used_qty += $x;
                $box_count += 1;
                # Reset value
                $boxProducts = [];
                $weight = 0;
                $price = 0;
                $x = 0;
            } elseif (($i + 1)%$max_items == 0) {
                $this->box['C'][] = $boxProducts;
                $used_qty += $x;
                $box_count += 1;
                # Reset value
                $boxProducts = [];
                $weight = 0;
                $price = 0;
                $x = 0;
            } elseif (($i + 1) == $lenght && !$separate) {
                $extra_qty = $lenght - $used_qty;
                if ($extra_qty >= $max_items) {
                    $a = array_chunk($boxProducts, $max_items);
                    $box_count = $box_count + ceil($extra_qty/$max_items);
                    $this->box['C'] = array_merge($a, $this->box['C']);
                    $boxProducts = [];
                } else {
                    $a = array_chunk($boxProducts, $max_items);
                    $this->box['C'] = array_merge($a, $this->box['C']);
                    $box_count+=1;
                    $boxProducts = [];
                }
            }
        }
        #$this->_logger->info('Data: '.print_r($extraProducts, true));
        return ['extra_products'=>$boxProducts,'box_count'=>$box_count];
    }
}
