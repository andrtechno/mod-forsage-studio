<?php

namespace panix\mod\forsage\components;


use panix\mod\shop\models\Currency;
use Yii;
use yii\base\Component;
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
    private $subCategoryPattern = '/\\/((?:[^\\\\\/]|\\\\.)*)/';

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


    public function __construct($config = [])
    {
        $this->apiKey = Yii::$app->settings->get('forsage', 'apikey');
        parent::__construct($config);
    }

    public function execute()
    {
        //$tr = Yii::$app->db->beginTransaction();
        //try {

        $props = $this->getProductProps($this->product);

        //$errors = (isset($props['error'])) ? true : false;
        $model = Product::findOne(['forsage_id' => $this->product['id']]);
        if (!$model && $this->product['quantity'] == 0) {
            return false;
        }

        if (!$this->product['quantity']) {
            if ($model) {
                if (Yii::$app->settings->get('forsage', 'out_stock_delete')) {
                    //self::log('Product delete ' . $this->product['id']);
                    //$model->delete();
                }

            }
            // return false;
        }
        self::log('Product quantity ' . $this->product['id'] . ' - ' . $this->product['quantity']);


        if (!$props['success']) {
            self::log('Product success false ' . $this->product['id']);
            return false;
        }

        if (!$model) {
            $model = new Product();
            $model->type_id = $props['type_id'];
            $model->forsage_id = $this->product['id'];
            $model->sku = $this->product['vcode'];
            $model->created_at = $this->product['photosession_date'];
        }
        //$categoryName = $this->generateCategory($this->product);
        $model->name_ru = $this->generateProductName($this->product);
        $model->name_uk = $model->name_ru;
        $model->slug = CMS::slug($model->name);
        $model->unit = Yii::$app->getModule('forsage')->unit;

        //$model->switch = ($this->product['quantity']) ? 1 : 0;
        if ($this->product['quantity'] == 1) {
            $model->availability = Product::STATUS_IN_STOCK; //есть на складе
        } elseif ($this->product['quantity'] < 0) {
            $model->availability = Product::STATUS_PREORDER; //под заказ
        } else {
            $model->availability = Product::STATUS_OUT_STOCK; //нет на складе
        }
        // print_r($this->product);die;


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
        $model->in_box = (isset($props['in_box'])) ? $props['in_box']['value'] : NULL;
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

        if (!$model->save(false)) {
            return false;
        }
        $this->processCategories($model, $model->main_category_id);

        if (isset($props['attributes'])) {
            if (isset($props['attributes'][6]['value'])) { // && $model->type_id == self::TYPE_BOOTS

                $explode = explode('-', $props['attributes'][6]['value']);

                $size_min = (int)$explode[0];
                //$size_max = (int)$explode[1];

                $sizes = [];
                if ($size_min) {
                    foreach (Yii::$app->getModule('forsage')->sizeGroup as $key => $l) {
                        $liste = explode('-', $key);

                        if (in_array($size_min, range($liste[0], $liste[1]))) {
                            // if (in_array($liste[0], range($size_min, $size_max))) {
                            $sizes[] = $l;
                            break;
                        }

                    }
                } else {
                    $sizes[] = $props['attributes'][6]['value'];
                }
                if (!empty($sizes[0])) {
                    $props['attributes'][99999] = [
                        'id' => 99999,
                        'name' => 'Размер',
                        'value' => $sizes[0]
                    ];
                }

            }
            //print_r($props);
            //die;
            $this->attributeData($model, $props['attributes']);

        }


        //set image
        if (isset($props['images'])) {
            foreach ($model->getImages()->all() as $im) {
                $im->delete();
            }
            foreach ($props['images'] as $file) {
                $model->attachImage($file);
            }
        }


        //    $tr->commit();
        //} catch (Exception $e) {
        //    self::log('no add ' . $this->product['id']);
        //    $tr->rollBack();
        //}
        return true;

    }


    private function generateCategory($product)
    {
        $categoryName = '';
        if ($product['category']) {
            if (isset($product['category']['descriptions'])) {
                $categoryName = $product['category']['descriptions'][1]['name'];
                if (isset($product['category']['child']['descriptions'])) {
                    $categoryName .= '/' . $product['category']['child']['descriptions'][1]['name'];
                }
            } else {
                $categoryName = $product['category']['name'];
                if (isset($product['category']['child'])) {
                    $categoryName .= '/' . $product['category']['child']['name'];
                }
            }

        }
        //echo $categoryName;die;
        return $categoryName;
    }

    private function generateProductName($product)
    {
        $name = '';
        $category = explode('/', $this->generateCategory($product));
        $category = array_pop($category);
        $name .= $category;
        if (isset($product['brand'])) {
            if (isset($product['brand']['name'])) {
                if ($product['brand']['name'] == 'No brand') {
                    $name .= ' ' . $product['supplier']['company'];
                } else {
                    $name .= ' ' . $product['brand']['name'];
                }
            } else {
                $name .= $category;
            }
        }
        if (isset($product['vcode'])) {
            $name .= ' ' . $product['vcode'];
        }

        $props = $this->getProductProps($product);

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
                        $result['price'] = trim($characteristic['value']);
                    }
                    if ($characteristic['id'] == 47) { //Старая цена продажи
                        $result['price_old'] = trim($characteristic['value']);
                    }
                    if ($characteristic['id'] == 24) { //Цена закупки
                        $result['price_purchase'] = trim($characteristic['value']);
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
                $result['type_id'] = $this->getTypeId($product);
                $result['categories'][1] = $this->getChildCategory($product);

                //for optikon
                if ($product['category']['id'] == self::CATEGORY_CLOTHES_ACCESSORIES) {
                    if (in_array($result['categories'][1]['id'], array_keys($this->categories_clothes))) { //Одежда
                        $result['type_id'] = Yii::$app->settings->get('forsage', 'cloth_type');
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

    public function getTypeId($product)
    {
        if (isset($product['category'])) {
            if ($product['category']['id'] == self::CATEGORY_CLOTHES_ACCESSORIES && Yii::$app->settings->get('forsage', 'cloth_type')) {
                return Yii::$app->settings->get('forsage', 'cloth_type');
            } elseif ($product['category']['id'] == self::CATEGORY_BOOTS && Yii::$app->settings->get('forsage', 'boots_type')) {
                return Yii::$app->settings->get('forsage', 'boots_type');
            }
        }
        return false;
    }

    public function getChildCategory($product)
    {
        $flag = false;
        if (isset($product['category'])) {
            if ($product['category']['id'] == self::CATEGORY_CLOTHES_ACCESSORIES && Yii::$app->settings->get('forsage', 'cloth_type')) {
                $flag = true;
            } elseif ($product['category']['id'] == self::CATEGORY_BOOTS && Yii::$app->settings->get('forsage', 'boots_type')) {
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
            if (isset($category['descriptions'])) {
                //ukraine lang
                return [
                    'id' => $category['id'],
                    'name_ru' => $category['descriptions'][0]['name'],
                    'name_uk' => $category['descriptions'][1]['name']
                ];
            } else {
                return ['id' => $category['id'], 'name' => $category['name']];
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
     * @param $supplier_id
     * @param array $params
     * @return bool|mixed
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function getSupplierProductIds($supplier_id, $params = [])
    {
        $url = "https://forsage-studio.com/api/get_products_by_supplier/{$supplier_id}"; //&start_date={$date}&end_date={$date}
        $response = $this->conn_curl($url, $params);
        if (isset($response['success'])) {
            if ($response['success'] == 'true') {
                return $response['product_ids'];
            } else {
                print_r($response);
                self::log($supplier_id . " - " . $response['message']);
            }
        } else {
            self::log('Method getSupplierProductIds Error success SID: ' . $supplier_id);
        }
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
            } else {
                self::log(" - " . $response['message']);
            }
        } else {
            self::log('Method getBrands Error success: ');
        }
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
        } else {
            self::log('Method getRefbookCharacteristics Error success');
        }
        return false;
    }

    /**
     * @param $product_id
     * @return $this|bool
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function getProduct($product_id)
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
        } else {
            self::log('Method getProduct Error success PID: ' . $product_id);
        }
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
            } else {
                self::log($response['message']);
            }
        }
        self::log('Method getSuppliers Error success');
        return false;
    }

    /**
     * @param int $start
     * @param int $end
     * @return bool|mixed
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function getChanges($start = 3600, $end = 0)
    {


        $hour = 3600;
        $day = 86400;
        //for CRON
        $end_date = time() + $end;
        $start_date = time() - $start;

        //products = "full" or "changes"
        Yii::$app->controller->stdout('end_date ' . date('Y-m-d H:i:s', $end_date) . PHP_EOL, Console::FG_GREEN);
        Yii::$app->controller->stdout('start_date ' . date('Y-m-d H:i:s', $start_date) . PHP_EOL, Console::FG_GREEN);
        Yii::$app->controller->stdout('Loading...' . PHP_EOL, Console::FG_GREEN);
        $url = "https://forsage-studio.com/api/get_changes/";
        $params['start_date'] = $start_date;
        $params['end_date'] = $end_date;
        $params['products'] = 'full';
        //$params['quantity'] = 1;

        $params['with_descriptions'] = 1;
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
     * @param int $start
     * @param int $end
     * @return bool
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function getChanges2($start = 3600, $end = 0)
    {


        $url = "https://forsage-studio.com/api/get_changes/";
        $params['start_date'] = $start;
        $params['end_date'] = $end;
        //$params['products'] = 'full';
        //$params['quantity'] = 1;

        $response = $this->conn_curl($url, $params);
        print_r($response);
        die;
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
                //'sslVerifyPeer' => false,
                //'timeout' => 8888
            ])
            ->setData($params)
            ->send();

        if ($response->isOk) {
            return $response->data;
        } else {
            return Json::decode($response->content);
        }
    }

    private function setMessage($message_code)
    {
        return \Yii::$app->name . ': ' . iconv('UTF-8', 'windows-1251', Yii::t('exchange1c/default', $message_code));
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
