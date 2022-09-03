<?php

namespace panix\mod\forsage\components;

use panix\mod\shop\components\ExternalFinder;
use panix\mod\shop\models\Brand;
use panix\mod\shop\models\Supplier;
use Yii;
use yii\base\Component;
use yii\db\Exception;
use yii\httpclient\Client;
use panix\engine\CMS;
use panix\mod\shop\models\Attribute;
use panix\mod\shop\models\AttributeOption;
use panix\mod\shop\models\Category;
use panix\mod\shop\models\Product;
use yii\helpers\Json;
use yii\helpers\Console;

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

    /**
     * @var ExternalFinder
     */
    public $external;

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
        $this->external = new ExternalFinder('{{%forsage_studio}}');
        $this->apiKey = Yii::$app->getModule('forsage')->apiKey;
        parent::__construct($config);
    }


    /**
     * Webhook
     * @return false|string
     */
    public function getInput()
    {
        return file_get_contents('php://input');
    }

    public function my_ucfirst($string, $e = 'utf-8')
    {
        if (function_exists('mb_strtoupper') && function_exists('mb_substr') && !empty($string)) {
            $string = mb_strtolower($string, $e);
            $upper = mb_strtoupper($string, $e);
            preg_match('#(.)#us', $upper, $matches);
            $string = $matches[1] . mb_substr($string, 1, mb_strlen($string, $e), $e);
        } else {
            $string = ucfirst($string);
        }
        return $string;
    }


    public function getRefbookCharacteristics()
    {
        $url = "https://forsage-studio.com/api/get_refbook_characteristics"; //&start_date={$date}&end_date={$date}
        $response = $this->conn_curl($url);
        if (isset($response['success'])) {
            if ($response['success'] == 'true') {
                return $response['characteristics'];
            }
        } else {
            self::log('Method getRefbookCharacteristics Error success');
        }
    }


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

    public function getSuppliers()
    {
        $url = "https://forsage-studio.com/api/get_suppliers"; //&start_date={$date}&end_date={$date}
        $response = $this->conn_curl($url);
        if (isset($response)) {

            return (array)$response;
        } else {
            self::log('Method getSuppliers Error success');
            return false;
        }
    }

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

    public function getChanges2($start = 3600, $end = 0)
    {
        //for CRON
        $end_date = time() - $end;
        $start_date = time() - $start;

        //products = "full" or "changes"
        Yii::$app->controller->stdout('end_date ' . date('Y-m-d H:i:s', $end_date) . PHP_EOL, Console::FG_GREEN);
        Yii::$app->controller->stdout('start_date ' . date('Y-m-d H:i:s', $start_date) . PHP_EOL, Console::FG_GREEN);
        Yii::$app->controller->stdout('Loading...' . PHP_EOL, Console::FG_GREEN);
        //die;
        $url = "https://forsage-studio.com/api/get_changes/";
        $params['start_date'] = $start_date;
        $params['end_date'] = $end_date;
        //$params['products'] = 'changes';
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
     * @param null $start_data
     * @return bool|mixed
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function getProducts($start_data = null)
    {

        $hour = 3600;
        $day = 86400;
        //for CRON

        $start_date = time() - $hour * 1 - (86400);
        $end_date = time();
        /*if (!$start_data) {
            $start_date = strtotime(date('Y-m-d'));// - 86400 * 1;
        } else {
            $start_date = strtotime($start_data);// - 86400 * 1;
        }*/
        //back days
        // $start_date = $start_date; // - $day
        // $end_date = $end_date;
        // $start_date = strtotime(date('Y-m-d'));

        echo date('Y-m-d H:i:s', $start_date) . PHP_EOL;
        echo date('Y-m-d H:i:s', $end_date) . PHP_EOL;
        //echo date('Y-m-d H:i:s',$end_date).PHP_EOL;
        // die;
        $start_date = strtotime('04-08-2022');
        $end_date = strtotime('05-08-2022');


        $params['start_date'] = $start_date;
        $params['end_date'] = $end_date;
        $params['with_descriptions'] = 1;
        $params['quantity'] = 1;

        $url = "https://forsage-studio.com/api/get_products/";

        $response = $this->conn_curl($url, $params);

        if (isset($response['success'])) {
            if ($response['success'] == true) {
                echo count($response['products']);
                die;
                // return $response['products'];
            }
        } else {
            self::log('Method getProducts Error success');
        }
        return false;
    }


    private function setMessage($message_code)
    {
        return \Yii::$app->name . ': ' . iconv('UTF-8', 'windows-1251', Yii::t('exchange1c/default', $message_code));
    }

    private static function log($msg)
    {
        \Yii::info($msg, 'forsage');
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
            //->setMethod('GET')
            ->setOptions([
                'sslVerifyPeer' => false,
                'timeout' => 8888
            ])
            ->setData($params)
            ->send();

        if ($response->isOk) {
            return $response->data;
        } else {
            return Json::decode($response->content);
        }
    }


    public function execute()
    {
        //$tr = Yii::$app->db->beginTransaction();
        //try {

        $props = $this->getProductProps($this->product);
        $errors = (isset($props['error'])) ? true : false;


        $model = Product::findOne(['forsage_id' => $this->product['id']]);

        if (!$this->product['quantity']) {
            if ($model) {
                if (Yii::$app->getModule('forsage')->outStockDelete) {
                    self::log('Product delete ' . $this->product['id']);
                    $model->delete();
                    if (isset($props['images'])) {
                        foreach ($props['images'] as $imageUrl) {
                            $this->external->deleteExternal($this->external::OBJECT_IMAGE, $this->product['id'] . '/' . basename($imageUrl));
                        }
                    }
                }

            }
            return false;
        }

        if (!$model) {
            $model = new Product();
            $model->type_id = Yii::$app->getModule('forsage')->type_id;
            $model->forsage_id = $this->product['id'];
            $model->sku = $this->product['vcode'];
        }
        $categoryName = $this->generateCategory($this->product);
        $model->name_ru = $this->generateProductName($this->product);
        $model->name_uk = $this->generateProductName($this->product);
        $model->slug = CMS::slug($model->name);
        $model->unit = Yii::$app->getModule('forsage')->unit;

        $model->switch = ($this->product['quantity']) ? 1 : 0;
        if ($this->product['quantity']) {
            $model->availability = 1;//есть на складе
        } else {
            $model->availability = 2;//нет на складе
        }
        $model->discount = NULL;

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

        //цена за пару
        $model->price = (isset($props['price'])) ? $props['price'] : 0;
        if (isset($props['price_old'])) {
            if ($props['price_old'] > $props['price']) {
                $model->discount = ($props['price_old'] - $props['price']);
                $model->price = $props['price_old'];
            }
        }
        $model->price_purchase = (isset($props['price_purchase'])) ? $props['price_purchase'] : 0;
        $model->in_box = (isset($props['in_box'])) ? $props['in_box'] : NULL;

        $model->quantity = $this->product['quantity'];
        $model->currency_id = (isset($props['currency_id'])) ? $props['currency_id'] : NULL;
        $model->video = (isset($props['video'])) ? $props['video'] : NULL;


        $full_name_category = '';

        if (isset($props['categories'][0])) {
            $main_category = $props['categories'][0];
            $full_name_category = $main_category;
            if (isset($props['categories'][1])) {
                $sub_category = $props['categories'][1];
                $full_name_category .= '/' . $sub_category;
            }
        }
        //$model->main_category_id = $this->getCategoryByPath($categoryName);
        $model->main_category_id = $this->getCategoryByPath($full_name_category);


        if (isset($this->product['supplier'])) {
            $supplier = Supplier::findOne(['forsage_id' => $this->product['supplier']['id']]);


            //$supplier = $this->external->getObject($this->external::OBJECT_SUPPLIER, $this->product['supplier']['company']);
            if (!$supplier) {
                $supplier = new Supplier();
                $supplier->name = $this->product['supplier']['company'];
                $supplier->email = $this->product['supplier']['email'];
                $supplier->address = $this->product['supplier']['address'];
                $supplier->phone = CMS::phoneFormat($this->product['supplier']['phone']);
                $supplier->forsage_id = $this->product['supplier']['id'];
                $supplier->save(false);
                //$this->external->createExternalId($this->external::OBJECT_SUPPLIER, $supplier->id, $supplier->name);
            }
            $model->supplier_id = $supplier->id;


        }
        $model->brand_id = null;
        if (isset($this->product['brand'])) {
            if (isset($this->product['brand']['name'])) {
                if ($this->product['brand']['name'] != 'No brand') {
                    $manufacturer = $this->external->getObject($this->external::OBJECT_BRAND, $this->product['brand']['name']);
                    if (!$manufacturer) {
                        $manufacturer = new Brand;
                        $manufacturer->name_ru = $this->product['brand']['name'];
                        $manufacturer->name_uk = $this->product['brand']['name'];
                        $manufacturer->slug = CMS::slug($manufacturer->name);
                        //$manufacturer->container = $product->supplier->address;
                        $manufacturer->save(false);
                        $this->external->createExternalId($this->external::OBJECT_BRAND, $manufacturer->id, $manufacturer->name);
                    }
                    $model->brand_id = $manufacturer->id;
                }
            }
        }


        if(!$model->save(false)){
            return false;
        }
        $this->processCategories($model, $model->main_category_id);

        if (isset($props['attributes'])) {
            foreach ($props['attributes'] as $prop) {
                $this->attributeData($model, $prop['name'], $prop['value']);
            }
            if (isset($props['in_box'])) {
                $this->attributeData($model, 'Количество в ящике', $props['in_box']);
            }
        }


        if (isset($props['attributes'][6]['value'])) {
            /*$explode = explode('-', $props['attributes'][6]['value']);
            $size_min = (int)$explode[0];
            $size_max = (int)$explode[1];
            $list = [
                '0-20' => 'до 20',
                '20-25' => '20-25',
                '26-31' => '26-31',
                '27-32' => '27-32',
                '31-36' => '31-36',
                '32-37' => '32-37',
                '36-41' => '36-41',
                '39-44' => '39-44',
                '40-45' => '40-45',
                '41-46' => '41-46',
                '45-99' => 'более 45'
            ];
            foreach ($list as $key => $l) {
                $liste = explode('-', $key);
                if ($liste[0] == $size_min) {
                    $result = in_array($size_min, range($liste[0], $liste[1]));
                    if ($result) {
                        //echo $key;
                        $this->attributeData($model, 'Размер', $l);
                        break;
                    }else{
                        Yii::info('no size1 '.$this->product['id'],'forsage');
                    }
                }elseif($size_min < $liste[1]){
                    $this->attributeData($model, 'Размер', $l);
                    break;
                }else{
                    Yii::info('no size2 '.$this->product['id'],'forsage');
                }
            }*/


            $explode = explode('-', $props['attributes'][6]['value']);
            $size_min = (int)$explode[0];
            $size_max = (int)$explode[1];
            $list2 = [
                '0-20' => 'до 20',
                '20-25' => '20-25',
                '26-31' => '26-31',
                '27-32' => '27-32',
                '31-36' => '31-36',
                '32-37' => '32-37',
                '36-41' => '36-41',
                '39-44' => '39-44',
                '40-45' => '40-45',
                '41-46' => '41-46',
                '45-99' => 'более 45'
            ];

            $list = [
                '45-99' => 'более 45',
                '41-46' => '41-46',
                '40-45' => '40-45',
                '39-44' => '39-44',
                '36-41' => '36-41',
                '32-37' => '32-37',
                '31-36' => '31-36',
                '27-32' => '27-32',
                '26-31' => '26-31',
                '20-25' => '20-25',

            ];
            $sizes = [];
            foreach ($list as $key => $l) {
                $liste = explode('-', $key);
                if (in_array($size_min, range($liste[0], $liste[1]))) {
                    // if (in_array($liste[0], range($size_min, $size_max))) {
                    $sizes[] = $l;
                    break;
                }
            }
            $this->attributeData($model, 'Размер', $sizes);
        }


        // $this->attributeDataList($model, 'TEST', ['test1', 'test2']);

        //set image
        if (isset($props['images'])) {
            $hashList = [];
            foreach ($model->getImages()->all() as $im) {
                $im->delete();
            }
            foreach ($props['images'] as $imageUrl) {
                //if(md5_file($imageUrl)){
                //
                // }
                //$res = $model->attachImage($imageUrl);

                $ii=0;
                while($res = $model->attachImage($imageUrl)){
                    if($res != false || $ii == 5){
                        break;
                    }
                    $ii++;
                }
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
                $name .= ' ' . $props['attributes'][6]['value'];
            }

            if (isset($props['in_box'])) {
                $name .= ' / ' . $props['in_box'];
            }
        }

        return $name;


    }

    /**
     * Get category id by path. If category not exits it will new one.
     * @param $path string Catalog/Shoes/Nike
     * @return integer category id
     */
    public function getCategoryByPath($path)
    {

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
            //$exist = Category::find()->where(['path_hash' => md5($name)])->one();
            //if ($exist) {
            //    $model = $exist;
            if (!$model) {
                $model = new Category;
                $model->name_ru = end($object);
                $model->name_uk = end($object);
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

    private function attributeData2($model, $attributeName, $attributeValue)
    {
        if (isset($attributeValue)) {
            $attrsdata = array();
            $attributeModel = $this->external->getObject($this->external::OBJECT_ATTRIBUTE, $attributeName);
            if (!$attributeModel) {

                //if not exists create attribute
                $attributeModel = new Attribute();
                $attributeModel->title = $attributeName;
                $attributeModel->name = CMS::slug($attributeModel->title, '_');
                $attributeModel->type = Attribute::TYPE_RADIO_LIST;
                $attributeModel->save(false);
                $this->external->createExternalId($this->external::OBJECT_ATTRIBUTE, $attributeModel->id, $attributeModel->title);
            }
            if ($attributeModel) {

                // if ($params['isFilter']) {
                $option = AttributeOption::find();
                //$option->joinWith('translations');
                $option->where(['attribute_id' => $attributeModel->id]);
                //$option->andWhere([AttributeOptionTranslate::tableName() . '.value' => $attributeValue]);
                $option->andWhere(['value' => $attributeValue]);
                $opt = $option->one();
                if (!$opt)
                    $opt = $this->addOptionToAttribute($attributeModel->id, $attributeValue);

                // print_r($opt);die;
                $attrsdata[$attributeModel->name] = $opt->id;
                //$attrsdata[$attributeModel->name] =  $attributeValue;
                //  }

            }

            if (!empty($attrsdata)) {

                $model->setEavAttributes($attrsdata, true);
            }
        }
    }

    private function attributeData($model, $attributeName, $attributeValues)
    {
        if (!is_array($attributeValues)) {
            $attributeValues = [$attributeValues];
        }
        if ($attributeValues) {

            $attributeModel = $this->external->getObject($this->external::OBJECT_ATTRIBUTE, $attributeName);
            if (!$attributeModel) {
                //if not exists create attribute
                $attributeModel = new Attribute();
                $attributeModel->title_ru = $attributeName;
                $attributeModel->title_uk = $attributeName;
                $attributeModel->name = CMS::slug($attributeModel->title, '_');
                $attributeModel->type = Attribute::TYPE_DROPDOWN;
                $attributeModel->save(false);
                if (count($attributeValues) > 1) {
                    $attributeModel->use_in_filter = 1;
                    $attributeModel->select_many = 1;
                }
                $this->external->createExternalId($this->external::OBJECT_ATTRIBUTE, $attributeModel->id, $attributeModel->title);
            }
            $attrsdata = [];
            foreach ($attributeValues as $attributeValue) {

                $option = AttributeOption::find();
                //$option->joinWith('translations');
                $option->where(['attribute_id' => $attributeModel->id]);
                //$option->andWhere([AttributeOptionTranslate::tableName() . '.value' => $attributeValue]);
                $option->andWhere(['value' => $attributeValue]);
                $opt = $option->one();
                if (!$opt)
                    $opt = $this->addOptionToAttribute($attributeModel->id, $attributeValue);

                $attrsdata[$attributeModel->name][] = $opt->id;
                //$attrsdata[$attributeModel->name] =  $attributeValue;

            }

            if (!empty($attrsdata)) {
                $model->setEavAttributes($attrsdata, true);
            }
        }
    }

    public function addOptionToAttribute($attribute_id, $value)
    {
        // Add option
        $option = new AttributeOption;
        $option->attribute_id = $attribute_id;
        $option->value = $value;
        $option->save(false);
        $this->external->createExternalId($this->external::OBJECT_ATTRIBUTE_OPTION, $option->id, $option->value);
        return $option;
    }

    public function getProductProps($product)
    {
        // $productData = $this->getProductData($product);

        $result = false;
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
                    if ($characteristic['id'] == 8) {
                        $result['in_box'] = trim($characteristic['value']);
                    }
                    // if ($characteristic['id'] == 39) { //Пол

                    //  $result['sex'] = $characteristic['value'];

                    // }//attributes
                    if (!in_array($characteristic['id'], [3, 8, 13, 24, 25, 29, 33, 34, 35, 38, 46, 45, 47, 53])) {
                        $result['attributes'][$characteristic['id']] = [
                            'name' => $characteristic['name'],
                            'value' => trim($characteristic['value'])
                        ];
                    }
                    if ($characteristic['id'] == 39) { //женщины, мужчины и дети (Пол)
                        //if (in_array($productData['type_id'], array(self::TYPE_BOOTS_KIDS, self::TYPE_BOOTS))) {

                        if (isset($characteristic['descriptions'])) {
                            if ($characteristic['value'] == 'жінки') {
                                $result['categories'][0] = 'Жіноча';
                            } elseif ($characteristic['value'] == 'чоловіки') {
                                $result['categories'][0] = 'Чоловіча';
                            } elseif ($characteristic['value'] == 'діти') {
                                $result['categories'][0] = 'Дитяча';
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
                $result['categories'][1] = $this->getChildCategory($product);
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


    public function getChildCategory($product)
    {

        if (isset($product['category'])) {
            //if (in_array($product->category->id, array(self::CATEGORY_BOOTS, self::CATEGORY_CLOTHES_ACCESSORIES))) {
            if (isset($product['category']['child'])) {
                //if ($product->category->child->name == 'Сандалии') {
                //    return 'Сандали';
                //}
                if (isset($product['category']['child']['descriptions'])) {
                    //ukraine
                    return $product['category']['child']['descriptions'][1]['name'];
                } else {
                    return $product['category']['child']['name'];
                }

            } else {
                self::log('no find category child, Product ID: ' . $product['id']);
            }
            //}
        } else {
            self::log('no find category, Product ID: ' . $product['id']);
        }
        return false;
    }
}
