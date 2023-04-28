<?php

namespace panix\mod\forsage\components;


use panix\mod\shop\models\Currency;
use Yii;
use yii\base\Component;
use yii\base\ErrorException;
use yii\httpclient\Client;
use yii\helpers\Json;
use yii\helpers\Console;
use panix\engine\CMS;
use panix\mod\shop\models\Attribute;
use panix\mod\shop\models\AttributeOption;
use panix\mod\shop\models\Category;
use panix\mod\shop\models\Product;
use panix\mod\shop\models\Brand;
use panix\mod\shop\models\Supplier;

/**
 * ForsageStudio class
 */
class ForsageStudio extends Component
{

    /**
     * @var string
     */
    protected $data;
    protected $rootCategory = null;
    public $subCategoryPattern = '/\\/((?:[^\\\\\/]|\\\\.)*)/';
    private $_eav = [];
    protected $categoriesPathCache = [],
        $productTypeCache = [],
        $brandCache = [],
        $supplierCache = [],
        $currencyCache = [];

    public $product;
    private $apiKey;

    const CATEGORY_BOOTS = 11;
    const CATEGORY_CLOTHES_ACCESSORIES = 9;


    const TYPE_BOOTS = 1;
    const TYPE_BOOTS_KIDS = 4;
    const TYPE_CLOTHES = 3;
    const TYPE_ACCESSORIES = 2;
    public $categories_clothes = [];
    public $categories_bags = [];
    public $settings;

    public function __construct($config = [])
    {
        $this->settings = Yii::$app->settings->get('forsage');
        $this->categories_clothes = explode(',', $this->settings->categories_clothes);
        $this->categories_bags = explode(',', $this->settings->categories_bags);
        if (!extension_loaded('intl')) {
            throw new ErrorException('PHP Extension intl not active.');
        }
        $this->apiKey = Yii::$app->settings->get('forsage', 'apikey');
        parent::__construct($config);
    }

    /**
     * @param bool $reloadImages
     * @param bool $reloadAttributes
     * @return bool
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function execute($reloadImages = true, $reloadAttributes = true)
    {
        $this->_eav = []; //clear eav for elastic

        $props = $this->getProductProps($this->product);
//print_r($props);die;
        //$errors = (isset($props['error'])) ? true : false;
        $model = Product::findOne(['forsage_id' => $this->product['id']]);


        if ($model) {
            if ($this->product['quantity'] == 0) {
                if (Yii::$app->settings->get('forsage', 'out_stock_delete')) {
                    self::log('Product delete ' . $this->product['id']);
                    $model->delete();
                    return true;
                }
            }
        }
        if (!$model && $this->product['quantity'] == 0) {
            self::log('Product success false quantity ' . $this->product['quantity']);
            return false;
        }
        //self::log('Product quantity ' . $this->product['id'] . ' - ' . $this->product['quantity']);


        if (!$props['success']) {
            self::log('Product success false ' . $this->product['id']);
            return false;
        }

        if (!$model) {
            $model = new Product();
            $model->type_id = $props['type_id'];
            $model->forsage_id = $this->product['id'];
            $model->created_at = $this->product['photosession_date'];
        }
        $model->sku = $this->product['vcode'];
        //$categoryName = $this->generateCategory($this->product);
        $model->name_ru = $this->generateProductName(0);
        $model->name_uk = $this->generateProductName(1);
        $model->slug = CMS::slug($model->name_uk);

        $model->switch = 1;
        $model->ukraine = (isset($props['ukraine'])) ? $props['ukraine'] : 0;
        $model->leather = (isset($props['leather'])) ? $props['leather'] : 0;
        if ($this->product['quantity'] == 1) {
            $model->availability = Product::STATUS_IN_STOCK; //есть на складе
        } elseif ($this->product['quantity'] < 0) {
            $model->availability = Product::STATUS_PREORDER; //под заказ
        } else {
            $model->availability = Product::STATUS_OUT_STOCK; //нет на складе
        }


        /*//цена за ящик
        $model->price = (isset($props['price'], $props['in_box'])) ? $props['price'] * $props['in_box'] : 0;
        if (isset($props['price_old'], $props['in_box'])) {
            if ($props['price_old'] > $props['price']) {
                $model->discount = ($props['price_old'] - $props['price']) * $props['in_box'];
                $model->price = $props['price_old'] * $props['in_box'];
            }
        }
        $model->price_purchase = (isset($props['price_purchase'], $props['in_box'])) ? $props['price_purchase'] * $props['in_box'] : 0;
        */


        $model->price_purchase = (isset($props['price_purchase'])) ? $props['price_purchase'] : 0;
        $model->in_box = (isset($props['in_box'])) ? $props['in_box']['value'] : 1;

        $model->quantity_min = (isset($props['in_box'])) ? $props['in_box']['value'] : 1;
        //$model->quantity_step = (isset($props['in_box'])) ? $props['in_box']['value'] : 1;


        $model->unit = (isset($props['unit'])) ? $props['unit'] : 1;
        //Если в я боксе 1, делаем еденице измерения 'штука'
        if ($model->in_box == 1) {
            $model->unit = 1;
        }

//print_r($this->product['quantity']);die;
        $model->quantity = 1;//$this->product['quantity'];

        $model->video = (isset($props['video'])) ? $props['video'] : NULL;
        //цена за пару
        $old_price = $model->price;
        $model->price = (isset($props['price'])) ? $props['price'] : 0;

        if (!$model->isNewRecord) {
            $model->discount = NULL;
            if (isset($props['price_old'])) {
                /*if ($props['price_old'] > $props['price']) {
                    $model->discount = ($props['price_old'] - $props['price']);
                    $model->price = $props['price_old'];
                }*/
                if ($props['price_old'] > $props['price']) {
                    if (($props['price_old'] - $props['price']) < $props['price']) {
                        $model->discount = ($props['price_old'] - $props['price']);
                        $model->price = $props['price_old'];
                    }
                }
            }
        }
        $model->currency_id = null;
        if (isset($props['currency_id'])) {
            $model->currency_id = $props['currency_id'];
        }
        if (isset($prop['description'])) {
            $model->short_description_ru = $prop['description'];
            $model->short_description_uk = $prop['description'];
            $model->full_description_ru = $prop['description'];
            $model->full_description_uk = $prop['description'];
        }

        /*$full_name_category = '';

        if (isset($props['categories'][0])) {
            $main_category = $props['categories'][0];
            $full_name_category = $main_category;
            if (isset($props['categories'][1])) {
                $sub_category = $props['categories'][1]['name'];
                $full_name_category .= '/' . $sub_category;
            }
        }*/


        $model->main_category_id = $this->getCategoryByPath($props);


        if (isset($this->product['supplier']) && $model->isNewRecord) {
            $supplier = Supplier::findOne(['forsage_id' => $this->product['supplier']['id']]);
            if (!$supplier) {
                $supplier = new Supplier();
                $supplier->name = $this->product['supplier']['company'];
                $supplier->email = $this->product['supplier']['email'];
                $supplier->address = $this->product['supplier']['address'];
                $supplier->phone = CMS::phoneFormat($this->product['supplier']['phone']);
                $supplier->forsage_id = $this->product['supplier']['id'];
                $supplier->save(false);
            }
            $model->supplier_id = $supplier->id;
        }


        //Записывать бренд как бренд или как поставщика.
        if ($this->settings->brand) {
            if (isset($this->product['supplier']) && $model->isNewRecord) {
                $brand = Brand::findOne(['forsage_id' => $this->product['supplier']['id']]);
                if (!$brand) {
                    $brand = new Brand;
                    $brand->name_ru = $this->product['supplier']['company'];
                    $brand->name_uk = $this->product['supplier']['company'];
                    $brand->forsage_id = $this->product['supplier']['id'];
                    $brand->slug = CMS::slug($brand->name);
                    $brand->save(false);

                }
                $model->brand_id = $brand->id;
            }
        } else {
            $model->brand_id = null;
            if (isset($this->product['brand'])) {
                if (isset($this->product['brand']['name'])) {
                    if ($this->product['brand']['name'] != 'No brand') {
                        $brand = Brand::findOne(['forsage_id' => $this->product['brand']['id']]);
                        if (!$brand) {
                            $brand = new Brand;
                            $brand->name_ru = $this->product['brand']['name'];
                            $brand->name_uk = $this->product['brand']['name'];
                            $brand->forsage_id = $this->product['brand']['id'];
                            $brand->slug = CMS::slug($brand->name);
                            $brand->save(false);

                        }
                        $model->brand_id = $brand->id;
                    }
                }
            }
        }


        if (!$model->save(false)) {
            return false;
        }

        if ($model->main_category_id) {
            $this->processCategories($model, $model->main_category_id);
        }
        if (isset($props['attributes'])) {
            if (isset($props['attributes'][6]['value'])) { // && $model->type_id == self::TYPE_BOOTS
                /*if (preg_match('/^(\d+)\-(\d+)$/', $props['attributes'][6]['value'], $match)) { // check 11-22
                    $explode = explode('-', $props['attributes'][6]['value']);

                    $size_min = (int)$explode[0];
                    //$size_max = (int)$explode[1];

                    $sizes = [];
                    //if ($size_min) { //comment for 0-12 size "0" = false;
                    foreach (Yii::$app->getModule('forsage')->sizeGroup as $key => $l) {
                        $liste = explode('-', $key);
                        if (in_array($size_min, range($liste[0], $liste[1]))) {
                            // if (in_array($liste[0], range($size_min, $size_max))) {
                            $sizes[] = $l;
                            break;
                        }

                    }
                    //} else {
                    //    $sizes[] = $props['attributes'][6]['value'];
                    // }
                    if (!empty($sizes[0])) {
                        $props['attributes'][99999] = [
                            'id' => 99999,
                            'name' => 'Размер обуви',
                            'value' => $sizes[0]
                        ];
                    }
                } elseif (preg_match('/^([a-zA-Z+])\-([a-zA-Z+])$/', $props['attributes'][6]['value'], $match)) { // check S-XL
                    $props['attributes'][99998] = [
                        'id' => 99998,
                        'name' => 'Размер',
                        'value' => $props['attributes'][6]['value']
                    ];
                }*/
                /*if (preg_match('/^([a-zA-Z+])\-([a-zA-Z+])$/', $props['attributes'][6]['value'], $match)) { // check S-XL
                    $props['attributes'][99998] = [
                        'id' => 99998,
                        'name' => 'Размер',
                        'value' => $props['attributes'][6]['value']
                    ];
                }*/

            }
            if ($reloadAttributes) {
                $this->attributeData($model, $props['attributes']);
            }
        }


        //set image
        if ($reloadImages) {
            if (isset($props['images'])) {

                $ftp = Yii::$app->getModule('shop')->ftpClient;
                if($ftp){
                    $ftp->connect(Yii::$app->getModule('shop')->ftp['server']);
                    $ftp->login(Yii::$app->getModule('shop')->ftp['login'], Yii::$app->getModule('shop')->ftp['password']);
                    $ftp->pasv(true);
                }
               // $model->setFtpClient($ftp);
                foreach ($model->getImages()->all() as $im) {
                    $im->delete();
                }

                foreach ($props['images'] as $file) {
                    $model->attachImage($file);
                }
                if($ftp){
                    $ftp->close();
                }
            }
        }


        /*if (Yii::$app->has('elasticsearch')) {
            $options = [];
            $options['name'] = $model->name;
            // $optionse['name_ru'] = $this->name_ru;
            //$optionse['name_uk'] = $this->name_uk;
            if ($model->currency_id) {
                $currency = Currency::findOne($model->currency_id);
                $options['price'] = (double)$model->price * $currency->rate;
            } else {
                $options['price'] = (double)$model->price;
            }
            $options['brand_id'] = $model->brand_id;
            $options['slug'] = $model->slug;
            $options['created_at'] = (int)$model->created_at;
            $options['availability'] = (int)$model->availability;
            $options['sku'] = $model->sku;
            $options['switch'] = (int)$model->switch;
            $options['discount'] = (int)$model->discount;
            $options['leather'] = (int)$model->leather;
            $options['ukraine'] = (int)$model->ukraine;
            $options['options'] = $this->_eav;
            //$eav = $model->getEavAttributes();

            /*foreach ($eav as $e) {
                foreach ($e as $o) {
                    $options['options'][] = $o;
                }
            }*/

            //$options['categories'][] = $model->main_category_id;
            /*foreach ($model->categorization as $category) {
                $options['categories'][] = $category->category;
            }

            $options['categories'] = array_unique($options['categories']);
            $result = Yii::$app->elasticsearch->post('product/_doc/' . $model->id, [], Json::encode($options));

        }*/

        return true;
    }


    /**
     * @param $product
     * @param int $index 0=ru, 1=uk
     * @return string
     */
    private function generateCategory($index = 0)
    {
        // print_r($this->product);
        $categoryName = '';
        if ($this->product['category']) {
            if (isset($this->product['category']['descriptions'])) {
                $categoryName = $this->product['category']['descriptions'][$index]['name'];
                if (isset($this->product['category']['child']['descriptions'])) {
                    $categoryName .= '/' . $this->product['category']['child']['descriptions'][$index]['name'];
                }
            } else {
                $categoryName = $this->product['category']['name'];
                if (isset($this->product['category']['child'])) {
                    $categoryName .= '/' . $this->product['category']['child']['name'];
                }
            }

        }
        return $categoryName;
    }

    private function generateProductName($index = 0)
    {
        $name = '';
        $category = explode('/', $this->generateCategory($index));
        $category = array_pop($category);
        $name .= $category;
        /*if (isset($product['brand'])) {
            if (isset($product['brand']['name'])) {
                if ($product['brand']['name'] == 'No brand') {
                    $name .= ' ' . $product['supplier']['company'];
                } else {
                    $name .= ' ' . $product['brand']['name'];
                }
            } else {
                $name .= $category;
            }
        }*/


        $name .= ' ' . $this->product['supplier']['company'];


        if (isset($this->product['vcode'])) {
            $name .= ' ' . $this->product['vcode'];
        }

        $props = $this->getProductProps($this->product);

        if (isset($props['attributes'])) {
            if (isset($props['attributes'][6])) {
                //$name .= ' ' . $props['attributes'][6]['value'];
            }

            //if (isset($props['in_box'])) {
            //    $name .= ' / ' . $props['in_box']['value'];
            //}
        }

        return $name;


    }

    /**
     * Get category id by path. If category not exits it will new one.
     * @param $path string Catalog/Shoes/Nike
     * @return integer category id
     */
    public function getCategoryByPath($props)
    {

        $path = '';
        $sub_category_uk = '';

        if (isset($props['categories'][0])) {
            $main_category = $props['categories'][0];
            $path = $main_category;
            if (isset($props['categories'][1])) {
                $sub_category = $props['categories'][1]['name_ru'];
                $path .= '/' . $sub_category;
                $sub_category_uk = $props['categories'][1]['name_uk'];
            }
        }

        //print_r($sub_category_ru);
        //die;
        if (isset($this->categoriesPathCache[$path]))
            return $this->categoriesPathCache[$path];

        if ($this->rootCategory === null)
            $this->rootCategory = Category::findOne(1);

        $result = preg_split($this->subCategoryPattern, $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $result = array_map('stripcslashes', $result);


        // $test = $result;
        // krsort($test);

        $parent = $this->rootCategory;
        $level = 2; // Level 1 is only root
        /** @var \panix\engine\behaviors\nestedsets\NestedSetsBehavior $model */

        /*$leaf = array_pop($result);
        $tree = [];
        $branch = &$tree;
        foreach ($result as $name) {
            $branch[$name] = [];
            $branch = &$branch[$name];
        }
        $branch = $leaf;*/


        $pathName = '';
        $tree = [];
        foreach ($result as $key => $name) {
            $pathName .= '/' . trim($name);
            $tree[] = substr($pathName, 1);
        }


        foreach ($tree as $key => $name) {
            $object = explode('/', trim($name));

            $model = Category::find()->where(['path_hash' => md5(mb_strtolower($name))])->one();

            if (!$model) {
                $model = new Category;
                $model->name_uk = end($object);
                $model->name_ru = end($object);
                $model->slug = CMS::slug($model->name_ru);
                $model->appendTo($parent);
            }

            $parent = $model;
            $level++;

        }

        // Cache category id
        if (isset($model)) {
            $this->categoriesPathCache[$path] = $model->id;
            return $model->id;
        }

        return 1; // root category
    }

    public function processCategories($model, int $main_category_id)
    {
        $categories = [$main_category_id];

        //if (!$newProduct) {
        //foreach ($model->categorization as $c)
        //    $categories[] = $c->category;
        $categories = array_unique($categories);
        //}
        $category = Category::findOne($main_category_id);

        if ($category) {
            $tes = $category->ancestors()->excludeRoot()->all();
            foreach ($tes as $cat) {
                $categories[] = $cat->id;
            }
        }
        // Update categories
        $model->setCategories($categories, $main_category_id);
    }


    /**
     * @param $model
     * @param $data
     */
    private function attributeData($model, $datas)
    {
        $attrsdata = [];
        foreach ($datas as $data) {


            $attributeModel = Attribute::findOne(['forsage_id' => $data['id']]);
            if (!$attributeModel) {
                $attributeModel = new Attribute();
                $attributeModel->title_ru = (isset($data['descriptions'][0])) ? $data['descriptions'][0]['name'] : $data['name'];
                $attributeModel->title_uk = (isset($data['descriptions'][1])) ? $data['descriptions'][1]['name'] : $data['name'];
                $attributeModel->name = CMS::slug($data['name'], '_');
                $attributeModel->type = Attribute::TYPE_DROPDOWN;
                $attributeModel->forsage_id = $data['id'];
                //if (count($attributeValues) > 1) {
                //$attributeModel->use_in_filter = 1;
                $attributeModel->select_many = 1;
                //}
                $attributeModel->save(false);


            }

            //foreach ($attributeValues as $attributeValue) {

            $option = AttributeOption::find();
            //$option->joinWith('translations');
            $option->where(['attribute_id' => $attributeModel->id]);
            //$option->andWhere([AttributeOptionTranslate::tableName() . '.value' => $attributeValue]);
            $option->andWhere(['value' => (isset($data['descriptions'][0]['value'])) ? $data['descriptions'][0]['value'] : $data['value']]);
            $opt = $option->one();
            if (!$opt)
                $opt = $this->addOptionToAttribute($attributeModel->id, $data);

            $attrsdata[$attributeModel->name][] = $opt->id;
            $this->_eav[] = $opt->id;

        }
        if (!empty($attrsdata)) {
            $model->setEavAttributes($attrsdata, true);
        }
    }

    public function addOptionToAttribute($attribute_id, $value)
    {

        // Add option
        $option = new AttributeOption;
        $option->attribute_id = $attribute_id;
        if (is_array($value)) {
            $option->value = (isset($value['descriptions'][0]['value'])) ? $value['descriptions'][0]['value'] : $value['value'];
            $option->value_uk = (isset($value['descriptions'][1]['value'])) ? $value['descriptions'][1]['value'] : $value['value'];
            $option->value_en = (isset($value['descriptions'][0]['value'])) ? $value['descriptions'][0]['value'] : $value['value'];
        } else {
            $option->value = $value['value'];
            $option->value_uk = $value['value'];
            $option->value_en = $value['value'];
        }
        $option->save(false);
        return $option;
    }

    public function getProductProps($product)
    {
        // $productData = $this->getProductData($product);

        $result = false;
        $result['success'] = true;
        $result['currency_id'] = NULL;
        if (isset($product['characteristics'])) {
            foreach ($product['characteristics'] as $characteristic) {
                if (!empty($characteristic['value']) && ($characteristic['value'] != '-')) {
                    if ($characteristic['id'] == 53) { //Видеообзор
                        $result['video'] = $characteristic['value'];
                    }
                    if ($characteristic['type'] == 'image') {
                        $result['images'][] = $characteristic['value'];
                    }
                    if ($characteristic['id'] == 35) { //Валюта продажи
                        if ($characteristic['value'] == 'доллар' || $characteristic['value'] == 'долар') {
                            $result['currency_id'] = 3;
                        }
                    }
                    if ($characteristic['id'] == 25) { //Цена продажи
                        $result['price'] = str_replace(',', '.', trim($characteristic['value']));
                    }
                    if ($characteristic['id'] == 47) { //Старая цена продажи
                        $result['price_old'] = str_replace(',', '.', trim($characteristic['value']));
                    }
                    if ($characteristic['id'] == 24) { //Цена закупки
                        $result['price_purchase'] = str_replace(',', '.', trim($characteristic['value']));
                    }
                    if ($characteristic['id'] == 1) { //Описание
                        $result['description'] = trim($characteristic['value']);
                    }
                    if ($characteristic['id'] == 8) {
                        $result['in_box'] = [
                            'name' => $characteristic['name'],
                            'value' => trim($characteristic['value'])
                        ];
                        //$result['in_box'] = trim($characteristic['value']);
                    }
                    // if ($characteristic['id'] == 39) { //Пол

                    //  $result['sex'] = $characteristic['value'];

                    // }

                    //attributes
                    if (!in_array($characteristic['id'], [1, 3, 13, 24, 25, 29, 33, 34, 35, 38, 46, 45, 47, 53])) {
                        $result['attributes'][$characteristic['id']] = [
                            'id' => $characteristic['id'],
                            'name' => $characteristic['name'],
                            'value' => trim($characteristic['value']),
                            'descriptions' => $characteristic['descriptions'],
                        ];
                    }
                    if ($characteristic['id'] == 39) { //женщины, мужчины и дети (Пол)
                        //if (in_array($productData['type_id'], array(self::TYPE_BOOTS_KIDS, self::TYPE_BOOTS))) {

                        if (isset($characteristic['descriptions'])) {
                            if (in_array($characteristic['value'], ['жінки', 'женщины'])) {
                                $result['categories'][0] = 'Wooman';
                            } elseif (in_array($characteristic['value'], ['чоловіки', 'мужчины'])) {
                                $result['categories'][0] = 'Man';
                            } elseif (in_array($characteristic['value'], ['діти', 'дети'])) {
                                $result['categories'][0] = 'Kids';
                            } else {
                                $result['success'] = false;
                                $result['error'][] = 'Пол не задан';
                            }
                        } else {
                            if ($characteristic['value'] == 'женщины') {
                                $result['categories'][0] = 'Женская';
                            } elseif ($characteristic['value'] == 'мужчины') {
                                $result['categories'][0] = 'Мужская';
                            } elseif ($characteristic['value'] == 'дети') {
                                $result['categories'][0] = 'Детская';
                            } else {
                                $result['success'] = false;
                                $result['error'][] = 'Пол не задан';
                            }
                        }
                        //}
                    }

                    // }
                }
            }

            if (!isset($result['images'])) {
                $result['success'] = false;
                $result['error'][] = 'Unknown product images error';
                // self::log('Unknown product images error');
            }


            if ($this->getChildCategory($product)) {
                $result['type_id'] = $this->getTypeId($product['category'], $product);
                $result['categories'][1] = $this->getChildCategory($product);

                //for optikon
                if ($product['category']['id'] == self::CATEGORY_CLOTHES_ACCESSORIES) {
                    if (in_array($result['categories'][1]['id'], array_keys($this->categories_clothes))) { //Одежда
                        $result['type_id'] = Yii::$app->settings->get('forsage', 'clothes_type');
                        $result['categories'][0] = 'Cloth';
                    } else { //аксессуары
                        $result['type_id'] = 3;
                        $result['categories'][0] = 'Other';
                    }
                }

            } else {
                $result['success'] = false;
                $result['error'][] = 'Unknown product category child error';
                self::log('Unknown product category child error');
            }
        }


        //if (!isset($result['price'])) {
        //    $result['ignoreFlag'] = true;
        //     $result['error'][] = 'Нет цены';
        // }

        //  return (!isset($result['error']))?$result:false;
        return $result;
    }

    public function getTypeId($mainCategory, $category)
    {

        if (isset($mainCategory, $category)) {
            if ($mainCategory['id'] == self::CATEGORY_CLOTHES_ACCESSORIES) {
                if (in_array($category['id'], $this->categories_clothes) && $this->settings->clothes_type) { //Одежда
                    return $this->settings->clothes_type;
                } elseif (in_array($category['id'], $this->categories_bags) && $this->settings->bags_type) { //сумки
                    return $this->settings->bags_type;
                } else {
                    return $this->settings->accessories_type;
                }
            } elseif ($mainCategory['id'] == self::CATEGORY_BOOTS && $this->settings->boots_type) {
                return $this->settings->boots_type;
            }
        }
        return false;
    }

    public function getChildCategory($product)
    {
        $flag = false;
        if (isset($product['category'])) {
            if ($product['category']['id'] == self::CATEGORY_CLOTHES_ACCESSORIES && $this->settings->clothes_type) {
                $flag = true;
            } elseif ($product['category']['id'] == self::CATEGORY_CLOTHES_ACCESSORIES && $this->settings->accessories_type) {
                $flag = true;
            } elseif ($product['category']['id'] == self::CATEGORY_BOOTS && $this->settings->boots_type) {
                $flag = true;
            }
            if ($flag) {
                return $this->lastChildCategory($product['category']);
            }
        }
        return false;
    }

    private function lastChildCategory($category)
    {
        if (!isset($category['child'])) {
            if (isset($category['descriptions'][0],$category['descriptions'][1])) {
                //ukraine lang
                return [
                    'id' => $category['id'],
                    'name_ru' => $category['descriptions'][0]['name'],
                    'name_uk' => $category['descriptions'][1]['name']
                ];
            } else {
                return [
                    'id' => $category['id'],
                    'name_ru' => $category['name'],
                    'name_uk' => $category['name']
                ];
            }
        } else {
            return $this->lastChildCategory($category['child']);
        }
    }

    /**
     * @param int $start
     * @param int $end
     * @param array $params
     * @return bool|mixed
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function getProducts($start = 3600, $end = 0, $params = [])
    {

        $params['start_date'] = $start;
        $params['end_date'] = $end;
        if (!isset($params['with_descriptions'])) {
            $params['with_descriptions'] = 1;
        }
        if (!isset($params['quantity'])) {
            $params['quantity'] = 1;
        }


        $url = "https://forsage-studio.com/api/get_products/";

        $response = $this->conn_curl($url, $params);

        if (isset($response['success'])) {
            if ($response['success']) {
                return $response['products'];
            }
        } else {
            self::log('Method getProducts Error success');
        }
        return false;
    }


    /**
     * @param int $supplier_id
     * @param array $params
     * @return bool|mixed
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function getSupplierProductIds(int $supplier_id, $params = [])
    {
        $url = "https://forsage-studio.com/api/get_products_by_supplier/{$supplier_id}"; //&start_date={$date}&end_date={$date}
        $response = $this->conn_curl($url, $params);
        if (isset($response['success'])) {
            if ($response['success'] == 'true') {
                return $response['product_ids'];
            }
        }
        self::log('Error: ' . __FUNCTION__ . '(' . $supplier_id . ') - ' . $response['message']);
        return false;
    }

    public function getCategories($with_descriptions = 1)
    {
        $url = "https://forsage-studio.com/api/get_categories";
        $params['with_descriptions'] = $with_descriptions;
        $response = $this->conn_curl($url, $params);
        if (isset($response['success'])) {
            if ($response['success'] == 'true') {
                return $response['categories'];
            }
        }
        self::log('Error: ' . __FUNCTION__ . '(' . $supplier_id . ') - ' . $response['message']);
        return false;
    }

    /**
     * Brands list
     * @return bool|mixed
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function getBrands()
    {
        $url = "https://forsage-studio.com/api/get_brands";
        $response = $this->conn_curl($url, []);
        if (isset($response['success'])) {
            if ($response['success'] == 'true') {
                return $response['brands'];
            }
        }
        self::log('Error: ' . __FUNCTION__ . ' - ' . $response['message']);
        return false;
    }

    /**
     * @return bool|mixed
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function getRefbookCharacteristics()
    {
        $url = "https://forsage-studio.com/api/get_refbook_characteristics";
        $params['with_descriptions'] = 1;
        $response = $this->conn_curl($url, $params);
        if (isset($response['success'])) {
            if ($response['success']) {
                return $response['characteristics'];
            }
        }
        self::log('Error: ' . __FUNCTION__ . ' - ' . $response['message']);
        return false;
    }

    /**
     * @param int $product_id
     * @return $this|bool
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function getProduct(int $product_id)
    {
        $url = "https://forsage-studio.com/api/get_product/{$product_id}";
        $response = $this->conn_curl($url, ['with_descriptions' => 1]);
        if (isset($response['success'])) {
            if ($response['success'] == 'true') {
                if (in_array($response['product']['category']['id'], Yii::$app->getModule('forsage')->excludeCategories)) {
                    return false;
                }
                $this->product = $response['product'];
                return $this;
            }
        }
        self::log('Error: ' . __FUNCTION__ . '(' . $product_id . ')');
        return false;
    }

    /**
     * @return bool|mixed
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function getSuppliers()
    {
        $url = "https://forsage-studio.com/api/get_suppliers";
        $response = $this->conn_curl($url);
        if (isset($response)) {
            if ($response['success'] == 'true') {
                return $response['suppliers'];
            }
        }
        return false;
    }

    /**
     * @param int $start
     * @param int $end
     * @return bool
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function getChanges($start = 3600, $end = 0)
    {

        $url = "https://forsage-studio.com/api/get_changes/";
        $params['start_date'] = $start;
        $params['end_date'] = $end;
        //$params['products'] = 'full';
        //$params['quantity'] = 1;

        $response = $this->conn_curl($url, $params);
        if ($response) {
            if (isset($response['success'])) {
                if ($response['success'] == 'true') {
                    //return $response['product_ids'];
                    return $response;
                }
            }
        }
        return false;
    }

    /**
     * @param $url
     * @param array $params
     * @return mixed|null
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    private function conn_curl($url, $params = [])
    {
        $params['token'] = $this->apiKey;
        $client = new Client(['baseUrl' => $url]);
        $response = $client->createRequest()
            // ->setFormat(Client::FORMAT_JSON)
            ->setMethod('GET')
            ->setOptions([
                'sslVerifyPeer' => false,
                'timeout' => 10000
            ])
            ->setData($params)
            ->send();
        if ($response->isOk) {
            return $response->data;
        } else {
            return Json::decode($response->content);
        }
    }

    protected static function log($msg)
    {
        Yii::info($msg, 'forsage');
    }

    /**
     * Webhook
     * @return false|string
     */
    public function getInput()
    {
        return file_get_contents('php://input');
    }
}
