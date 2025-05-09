<?php

namespace panix\mod\forsage\components;


use panix\engine\components\ImageHandler;
use panix\mod\shop\models\Currency;
use panix\mod\shop\models\ProductImage;
use Yii;
use yii\base\Component;
use yii\base\ErrorException;
use yii\helpers\FileHelper;
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
        if (isset($this->settings->categories_clothes))
            $this->categories_clothes = explode(',', $this->settings->categories_clothes);
        if (isset($this->settings->categories_bags))
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
            if ($model) {
                $model->delete();
            }
            return false;
        }


        if (!$model) {
            $model = new Product();
            $model->type_id = $props['type_id'];
            $model->forsage_id = $this->product['id'];
            $model->created_at = $this->product['photosession_date'];
        } else {
            $model->updated_at = time();
        }
        //$model->created_at = $this->product['photosession_date'];
        $model->detachBehavior('timestamp');
        $model->sku = $this->product['vcode'];
        //$categoryName = $this->generateCategory($this->product);
        $model->name_ru = $this->generateProductName(0);
        $model->name_uk = $this->generateProductName(1);
        $model->slug = CMS::slug($model->name_uk);

        $model->switch = 1;
        //$model->ukraine = (isset($props['ukraine'])) ? $props['ukraine'] : 0;
        //$model->leather = (isset($props['leather'])) ? $props['leather'] : 0;
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


        $model->quantity = 1;//$this->product['quantity'];

        $model->video = (isset($props['video'])) ? $props['video'] : NULL;
        //цена за пару
        $old_price = $model->price;
        $model->price = (isset($props['price'])) ? $props['price'] : 0;

        if (!$model->isNewRecord) {
            $model->discount = NULL;
            if (isset($props['price_old'])) {
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


        if ($this->settings->tm && isset($this->product['brand'])) {
            if ($this->product['brand']['name'] != 'No brand') {
                $props['attributes'][9994] = [
                    'id' => 9994,
                    'name' => 'Торговая марка',
                    'value' => $this->product['brand']['name'],
                    'descriptions' => [
                        [
                            'code' => 'ru',
                            'name' => 'Торговая марка',
                            'value' => $this->product['brand']['name'],
                        ],
                        [
                            'code' => 'uk',
                            'name' => 'Торгова марка',
                            'value' => $this->product['brand']['name'],
                        ]
                    ]
                ];
            }
        }


        if (!$model->save(false)) {
            return false;
        }

        if ($model->main_category_id) {
            $this->processCategories($model, $model->main_category_id);
        }
        if (isset($props['attributes'])) {
            if ($reloadAttributes) {
                $this->attributeData($model, $props['attributes']);
            }
        }


        //set image
        if ($reloadImages) {
            if (isset($props['images'])) {
                foreach ($model->getImages()->all() as $im) {
                    $im->delete();
                }
                foreach ($props['images'] as $file) {
                    //$model->attachImage($file['url']);
                    $this->attachImage($model, $file);
                }
            }
        }

        if (Yii::$app->db->driverName == 'pgsql') {
            $eavkeys = [];
            $slugToId = Attribute::slugToId();
            $eav = $model->getEavAttributes();
            foreach ($eav as $key => $e) {
                if (is_array($e)) {
                    foreach ($e as $o) {
                        $key = $slugToId[$key];
                        $eavkeys[$key] = $o;
                    }
                }
            }

            $model->options = $eavkeys;
            $model->save(false);
        }


        if (method_exists($model, 'elastic') && Yii::$app->has('elasticsearch')) {
            $eav = $model->getEavAttributes();
            $eavkeys = [];
            foreach ($eav as $e) {
                if (is_array($e)) {
                    foreach ($e as $o) {
                        $eavkeys[] = $o;
                    }
                }
            }
            $model->elastic($eavkeys);
        }
        return true;
    }


    public function execute_fixsize()
    {
        $this->_eav = []; //clear eav for elastic

        $props = $this->getProductProps($this->product);

        //$errors = (isset($props['error'])) ? true : false;
        $model = Product::findOne(['forsage_id' => $this->product['id']]);


        if (!$model && $this->product['quantity'] == 0) {
            self::log('Product success false quantity ' . $this->product['quantity']);
            return false;
        }


        if ($model && isset($props['attributes'])) {
            $this->attributeDataFixSize($model, $props['attributes'][6]);
        }

        return true;
    }


    public function attachImage($model, $entity)
    {
        $module = Yii::$app->getModule('shop');
        $file = $entity['url'];
        $id = $entity['id'];
        //$uniqueName = md5($model->id . ':' . $model->supplier_id . ':' . $id);
        $uniqueName = mb_strtolower(\panix\engine\CMS::gen(10));
        $isDownloaded = preg_match('/http(s?)\:\/\//i', $file);

        if (!$model->id) {
            throw new \Exception('Owner must have primaryKey when you attach image!');
        }
        if ($module->ftp) {
            $path = Yii::getAlias(Yii::getAlias("@runtime"));
        } else {
            $path = Yii::getAlias(Yii::getAlias("@uploads/store/product/{$model->id}"));
        }

        if ($isDownloaded) {
            $downloaded = $model->downloadFile($file, $path, $uniqueName);
            if ($downloaded) {
                $file = $downloaded;
            } else {
                return false;
            }
        }


        $extension = pathinfo($file, PATHINFO_EXTENSION);

        $pictureFileName = $uniqueName . '.' . $extension;
        $newAbsolutePath = FileHelper::normalizePath($path . DIRECTORY_SEPARATOR . $pictureFileName);

        if (!$module->ftp) {
            $createDir = FileHelper::createDirectory($path, 0775, true);
        }
        $image = new ProductImage();
        $image->product_id = $model->id;
        $image->filename = $pictureFileName;
        $image->alt_title = $model->name_uk;
        $image->created_at = $model->created_at;
        //$image->forsage_id = $id;

        if (!$image->save()) {
            return false;
        }

        if (count($image->getErrors()) > 0) {
            $ar = array_shift($image->getErrors());
            FileHelper::unlink($newAbsolutePath);
            throw new \Exception(array_shift($ar));
        }

        $img = $model->getImage();

        //If main image not exists
        if ($img == null) {
            $model->setMainImage($image);
        }

        if ($module->ftp) {
            $ftpClient = ftp_connect($module->ftp['server']);
            @ftp_login($ftpClient, $module->ftp['login'], $module->ftp['password']);
            @ftp_pasv($ftpClient, true);

            $image->ftp = $ftpClient;
            $ftpPath = "/uploads/product";
            if (!@ftp_mkdir($ftpClient, $ftpPath)) {
                //echo "Не удалось создать директорию";
            }

            $upload = ftp_put($ftpClient, "$ftpPath/{$image->product_id}_{$image->filename}", $newAbsolutePath, FTP_BINARY);

            $original2 = $image->createVersionFtp('small', ['watermark' => false]);
            $original3 = $image->createVersionFtp('medium', ['watermark' => false]);

            ftp_close($ftpClient);
            FileHelper::unlink($newAbsolutePath);
        }

        return $image;
    }

    /**
     * @param $product
     * @param int $index 0=ru, 1=uk
     * @return string
     */
    private function generateCategory($index = 0)
    {
        $categoryName = '';
        if ($this->product['category']) {
            if (isset($this->product['category']['descriptions'])) {
                $categoryName = $this->product['category']['descriptions'][$index]['name'];
                if (isset($this->product['category']['child']['descriptions'])) {
                    if (isset($this->product['category']['child']['descriptions'][$index])) {
                        $categoryName .= '/' . $this->product['category']['child']['descriptions'][$index]['name'];
                    } else {
                        $categoryName .= '/' . $this->product['category']['child']['name'];
                    }
                }
            } else {
                $categoryName = $this->product['category']['name'];
                if (isset($this->product['category']['child']['name'])) {
                    $categoryName .= '/' . $this->product['category']['child']['name'];
                }
            }

        }
        // print_r($categoryName);die;
        return $categoryName;
    }

    private function generateProductName($index = 0)
    {
        $tmpArray = [];
        $tmpArray['{sku}'] = '';
        $category = explode('/', $this->generateCategory($index));
        $category = array_pop($category);
        $tmpArray['{category}'] = $category;
        $tmpArray['{supplier}'] = $this->product['supplier']['company'];
        $tmpArray['{brand}'] = $this->product['brand']['name'];

        if (isset($this->product['vcode'])) {
            $tmpArray['{sku}'] = $this->product['vcode'];
        }

        $name = Yii::$app->settings->get('forsage', 'product_name_tpl');
        foreach ($tmpArray as $from => $to) {
            $name = str_replace($from, $to, $name);
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
        if (isset($props['categories'][0])) {
            foreach ($props['categories'] as $name) {
                if (is_array($name)) {
                    //$path .= '/' . $name['name_ru'] . ':' . $name['name_uk'];
                    $path .= '/' . implode(':', $name);
                } else {
                    $path .= $name;
                }
            }
        }
        if (isset($this->categoriesPathCache[$path]))
            return $this->categoriesPathCache[$path];

        if ($this->rootCategory === null)
            $this->rootCategory = Category::findOne(1);

        $result = preg_split($this->subCategoryPattern, $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $result = array_map('stripcslashes', $result);

        $parent = $this->rootCategory;
        $level = 1; // Level 1 is only root
        /** @var \panix\engine\behaviors\nestedsets\NestedSetsBehavior $model */


        $pathNameUA = '';
        $pathNameRU = '';
        $tree = [];

        foreach ($result as $key => $name) {
            $params = explode(':', trim($name));
            if (count($params) > 1) {
                $pathNameUA .= '/' . trim($params[0]);
                $pathNameRU .= '/' . trim($params[1]);
            } else {
                $pathNameUA .= '/' . trim($name);
                $pathNameRU .= '/' . trim($name);
            }

            $tree[] = [
                substr($pathNameUA, 1),
                substr($pathNameRU, 1),
                //(isset($params[2])) ? $params[2] : false //NEW
            ];
        }


        $external_hash = '';

        foreach ($tree as $key => $lang) {
            if (is_array($props['categories'][$key])) {
                if (isset($props['categories'][$key]['id'])) {
                    $external_hash .= '/' . trim($props['categories'][$key]['id']);
                } else {
                    $external_hash .= '/' . trim($props['categories'][$key]['name_uk']);
                }
            } else {
                $external_hash .= '/' . trim($props['categories'][$key]);
            }

            $external_hash = strtolower($external_hash);


            $objectRu = explode('/', trim($lang[1]));
            $objectUk = explode('/', trim($lang[0]));
            $hash = md5(mb_strtolower($lang[1]));
            $model = Category::find()->where(['path_hash' => $hash])->one();

            if (!$model) {
                $model = new Category;
                $model->name_uk = end($objectUk);
                $model->name_ru = end($objectRu);
                $model->slug = CMS::slug($model->name_ru);
                $model->path_hash = $hash; //NEW remove category modal this
                $model->external_id = $external_hash;
                $model->created_at = time();
                $model->appendTo($parent);
            } else {
               // $model->name_uk = end($objectUk);
               // $model->name_ru = end($objectRu);
                $model->external_id = $external_hash;
                $model->saveNode(false);
            }
           // echo $model->name_uk;
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


    private function attributeDataFixSize($model, $data)
    {
        $attrsdata = [];


        $attributeModel = Attribute::findOne(['forsage_id' => $data['id']]);


        $option = AttributeOption::find();
        $option->where(['attribute_id' => $attributeModel->id]);
        $option->andWhere(['value' => (isset($data['descriptions'][0]['value'])) ? $data['descriptions'][0]['value'] : $data['value']]);
        $opt = $option->one();
        if (!$opt)
            $opt = $this->addOptionToAttribute($attributeModel->id, $data);

        $attrsdata[$attributeModel->name][] = $opt->id;
        $this->_eav[] = $opt->id;


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

        $result = false;
        $result['success'] = true;
        $cat = $this->getChildCategory($product);

        $result['type_id'] = $this->getTypeId($product['category'], $cat);
        $result['unit'] = 3; //по умолчанию ящик

        if (isset($product['characteristics'])) {
            $renew = [];
            foreach ($product['characteristics'] as $new) {
                $renew[$new['id']] = $new;
            }

            foreach ($renew as $id => $characteristic) {

                if (!empty($characteristic['value']) && ($characteristic['value'] != '-')) {
                    if ($characteristic['id'] == 53) { //Видеообзор
                        $result['video'] = $characteristic['value'];
                    }
                    if ($characteristic['type'] == 'image') {
                        $result['images'][] = [
                            'id' => $characteristic['id'],
                            'url' => $characteristic['value']
                        ];
                    }
                    if ($characteristic['id'] == 35) { //Валюта продажи
                        if ($characteristic['value'] == 'доллар' || $characteristic['value'] == 'долар') {
                            $result['currency_id'] = 3;
                        }
                    }
                    if ($characteristic['id'] == 25) { //Цена продажи
                        $result['price'] = str_replace(',', '.', trim($characteristic['value']));
                    }
                    if ($characteristic['id'] == 5) { //Материал изделия
                        //if(preg_match('/шкіра|leather|кожа/ui',$characteristic['value'])){
                        if (preg_match('/^(натуральна\я?)\s(шкіра|кожа|нубук)\-?$/ui', $characteristic['value'])) {
                            $result['leather'] = true;
                        }

                        $ru = explode('-', trim($characteristic['descriptions'][0]['value']));
                        $uk = explode('-', trim($characteristic['descriptions'][1]['value']));
                        $result['attributes'][9995] = [
                            'id' => 9995,
                            'name' => 'Материал изделия (filter)',
                            'value' => $characteristic['value'],
                            'descriptions' => [
                                [
                                    'code' => 'ru',
                                    'name' => $characteristic['descriptions'][0]['name'],
                                    'value' => $ru[0],
                                ],
                                [
                                    'code' => 'uk',
                                    'name' => $characteristic['descriptions'][1]['name'],
                                    'value' => $uk[0],
                                ]
                            ]
                        ];
                    }
                    if ($characteristic['id'] == 11) { //Страна
                        if (preg_match('/україна|ukraine|украина/ui', $characteristic['value'])) {
                            $result['ukraine'] = true;
                        }
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
                    }


                    //attributes
                    if (!in_array($characteristic['id'], [1, 3, 13, 24, 25, 29, 33, 34, 35, 38, 46, 45, 47, 53])) {
                        if ($characteristic['id'] == 6) {
                            $result['attributes'][$characteristic['id']] = [
                                'id' => $characteristic['id'],
                                'name' => $characteristic['name'],
                                'value' => trim($characteristic['value']),
                            ];
                        } else {
                            $result['attributes'][$characteristic['id']] = [
                                'id' => $characteristic['id'],
                                'name' => $characteristic['name'],
                                'value' => trim($characteristic['value']),
                                'descriptions' => $characteristic['descriptions'],
                            ];
                        }

                    }

                    if ($characteristic['id'] == 39) { //женщины, мужчины и дети (Пол)
                        //if (in_array($productData['type_id'], array(self::TYPE_BOOTS_KIDS, self::TYPE_BOOTS))) {
                        if ($product['category']['id'] == self::CATEGORY_BOOTS) {

                            $categories_shoes = explode(',', $this->settings->categories_shoes);
                            if ($categories_shoes) {
                                if (in_array($cat['id'], $categories_shoes)) {
                                    $result['success'] = false;
                                    $result['error'][] = 'Exclude category';
                                }
                            }


                            if (isset($characteristic['descriptions'])) {
                                if (in_array($characteristic['value'], ['жінки', 'женщины'])) {
                                    $sex = 'Woman';
                                } elseif (in_array($characteristic['value'], ['чоловіки', 'мужчины'])) {
                                    $sex = 'Man';
                                } elseif (in_array($characteristic['value'], ['діти', 'дети'])) {
                                    $sex = 'Kids';
                                } elseif (in_array($characteristic['value'], ['унісекс', 'унисекс'])) {

                                    if (preg_match('/^(\d+)\-(\d+)$/', $renew[6]['value'], $match)) {
                                        // $explode = explode('-', $product['characteristics'][6]['value']);

                                        $size_min = (int)$match[1];
                                        $result['size_min'] = $size_min;
                                        if ($size_min >= 41) {
                                            $sex = 'Man';
                                        } elseif ($size_min <= 35) {
                                            $sex = 'Kids';
                                        } elseif ($size_min >= 36 && $size_min < 41) {
                                            $sex = 'Woman';
                                        } else {
                                            $result['success'] = false;
                                            $result['error'][] = 'Пол Унисекс';
                                        }
                                    }
                                } else {
                                    $result['success'] = false;
                                    $result['error'][] = 'Пол не задан 1';
                                }

                                if ($this->settings->structure_shoes == 1) {
                                    $result['categories'][0] = 'Shoes';
                                    $result['categories'][1] = [
                                        'name_uk' => $sex,
                                        'name_ru' => $sex
                                    ];
                                } elseif ($this->settings->structure_shoes == 2) {
                                    $result['categories'][0] = 'Shoes';
                                    $result['categories'][1] = [
                                        'name_uk' => $sex,
                                        'name_ru' => $sex
                                    ];
                                    $result['categories'][2] = [
                                        'name_uk' => $cat['name_uk'],
                                        'name_ru' => $cat['name_ru'],
                                        'id' => $cat['id']
                                    ];
                                } elseif ($this->settings->structure_shoes == 3) {
                                    $result['categories'][0] = 'Shoes';
                                    $result['categories'][1] = [
                                        'name_uk' => $cat['name_uk'],
                                        'name_ru' => $cat['name_ru'],
                                        'id' => $cat['id']
                                    ];
                                } elseif ($this->settings->structure_shoes == 4) {
                                    $result['categories'][0] = $sex;
                                } elseif ($this->settings->structure_shoes == 5) {
                                    $result['categories'][0] = $sex;
                                    $result['categories'][1] = [
                                        'name_uk' => $cat['name_uk'],
                                        'name_ru' => $cat['name_ru'],
                                        'id' => $cat['id']
                                    ];
                                } else {
                                    $result['categories'][0] = 'Shoes';
                                }


                            } else {
                                //вроде не юзаеться нужно протестить

                                if ($characteristic['value'] == 'женщины') {
                                    //$result['categories'][0] = 'Женская';
                                } elseif ($characteristic['value'] == 'мужчины') {
                                    //$result['categories'][0] = 'Мужская';
                                } elseif ($characteristic['value'] == 'дети') {
                                    //$result['categories'][0] = 'Детская';
                                } else {
                                    //$result['success'] = false;
                                    //$result['error'][] = 'Пол не задан 2';
                                }
                            }
                        } elseif ($product['category']['id'] == self::CATEGORY_CLOTHES_ACCESSORIES) {
                            if (isset($characteristic['descriptions'])) {
                                if (in_array($characteristic['value'], ['жінки', 'женщины'])) {
                                    $sex = 'Woman';
                                } elseif (in_array($characteristic['value'], ['чоловіки', 'мужчины'])) {
                                    $sex = 'Man';
                                } elseif (in_array($characteristic['value'], ['діти', 'дети'])) {
                                    $sex = 'Kids';
                                } elseif (in_array($characteristic['value'], ['унісекс', 'унисекс'])) {

                                    if (isset($renew[6])) {
                                        if (preg_match('/^(\d+)\-(\d+)$/', $renew[6]['value'], $match)) {
                                            $size_min = (int)$match[1];
                                            $result['size_min'] = $size_min;
                                            if ($size_min >= 41) {
                                                $sex = 'Man';
                                            } elseif ($size_min <= 35) {
                                                $sex = 'Kids';
                                            } elseif ($size_min >= 36 && $size_min < 41) {
                                                $sex = 'Woman';
                                            } else {
                                                $result['success'] = false;
                                                $result['error'][] = 'Пол Унисекс';
                                            }
                                        } elseif (preg_match('/^(\w+)\-(\w+)$/', $renew[6]['value'], $match)) {
                                            //if else S-XL etc.
                                            //Также проблема с "пледами"
                                        }
                                    }
                                } else {
                                    $result['success'] = false;
                                    $result['error'][] = 'Пол не задан 1';
                                }
                            }
                        }
                    }
                }
            }


            if ($cat) {

                if ($result['type_id'] == $this->settings->boots_type) {
                    if (preg_match('/^(\d+)\-(\d+)$/', $result['attributes'][6]['value'], $match)) { // check 11-22
                        $explode = explode('-', $result['attributes'][6]['value']);
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
                            $result['attributes'][99999] = [
                                'id' => 99999,
                                'name' => 'Размер обуви',
                                'value' => $sizes[0]
                            ];
                        }
                    }
                    $result['attributes'][9998] = [
                        'id' => 9998,
                        'name' => 'Тип взуття',
                        'value' => $cat['name_uk'],
                        'descriptions' => [
                            [
                                'code' => 'ru',
                                'name' => 'Тип обуви',
                                'value' => $cat['name_ru'],
                            ],
                            [
                                'code' => 'uk',
                                'name' => 'Тип взуття',
                                'value' => $cat['name_uk'],
                            ]
                        ]
                    ];
                } elseif ($result['type_id'] == $this->settings->clothes_type) {
                    $result['attributes'][9997] = [
                        'id' => 9997,
                        'name' => 'Тип одягу',
                        'value' => $cat['name_uk'],
                        'descriptions' => [
                            [
                                'code' => 'ru',
                                'name' => 'Тип одежды',
                                'value' => $cat['name_ru'],
                            ],
                            [
                                'code' => 'uk',
                                'name' => 'Тип одягу',
                                'value' => $cat['name_uk'],
                            ]
                        ]
                    ];
                } elseif ($result['type_id'] == $this->settings->bags_type) {
                    //add filter size bags
                } else { //accessories
                    $result['attributes'][9996] = [
                        'id' => 9996,
                        'name' => 'Тип аксесуарів',
                        'value' => $cat['name_uk'],
                        'descriptions' => [
                            [
                                'code' => 'ru',
                                'name' => 'Тип аксессуаров',
                                'value' => $cat['name_ru'],
                            ],
                            [
                                'code' => 'uk',
                                'name' => 'Тип аксесуарів',
                                'value' => $cat['name_uk'],
                            ]
                        ]
                    ];
                }
                if ($product['category']['id'] == self::CATEGORY_CLOTHES_ACCESSORIES) {

                    if (in_array($cat['id'], $this->categories_clothes) && $this->settings->clothes_type) { //Одежда
                        $result['type_id'] = $this->settings->clothes_type;
                        $result['unit'] = 4;//упаковка
                        if ($this->settings->structure_clothes == 1) {
                            $result['categories'][0] = Yii::$app->getModule('forsage')->clothes_key;
                            $result['categories'][1] = [
                                'name_uk' => $sex,
                                'name_ru' => $sex
                            ];
                        } elseif ($this->settings->structure_clothes == 2) {
                            $result['categories'][0] = Yii::$app->getModule('forsage')->clothes_key;
                            $result['categories'][1] = [
                                'name_uk' => $sex,
                                'name_ru' => $sex
                            ];
                            $result['categories'][2] = [
                                'name_uk' => $cat['name_uk'],
                                'name_ru' => $cat['name_ru'],
                                'id' => $cat['id']
                            ];
                        } elseif ($this->settings->structure_clothes == 3) {
                            $result['categories'][0] = Yii::$app->getModule('forsage')->clothes_key;
                            $result['categories'][1] = [
                                'name_uk' => $cat['name_uk'],
                                'name_ru' => $cat['name_ru'],
                                'id' => $cat['id']
                            ];
                        } elseif ($this->settings->structure_clothes == 4) {
                            $result['categories'][0] = [
                                'name_uk' => $sex,
                                'name_ru' => $sex
                            ];
                        } elseif ($this->settings->structure_clothes == 5) {
                            $result['categories'][0] = [
                                'name_uk' => $sex,
                                'name_ru' => $sex
                            ];
                            $result['categories'][1] = [
                                'name_uk' => $cat['name_uk'],
                                'name_ru' => $cat['name_ru'],
                                'id' => $cat['id']
                            ];
                        } else {
                            $result['categories'][0] = Yii::$app->getModule('forsage')->clothes_key;
                        }

                    } elseif (in_array($cat['id'], $this->categories_bags) && $this->settings->bags_type) { //сумки
                        $result['type_id'] = $this->settings->bags_type;
                        $result['unit'] = 4;//упаковка
                        if ($this->settings->structure_bags == 1) {
                            $result['categories'][0] = Yii::$app->getModule('forsage')->bags_key;
                            $result['categories'][1] = [
                                'name_uk' => $sex,
                                'name_ru' => $sex
                            ];
                        } elseif ($this->settings->structure_bags == 2) {
                            $result['categories'][0] = Yii::$app->getModule('forsage')->bags_key;
                            $result['categories'][1] = [
                                'name_uk' => $sex,
                                'name_ru' => $sex
                            ];
                            $result['categories'][2] = [
                                'name_uk' => $cat['name_uk'],
                                'name_ru' => $cat['name_ru'],
                                'id' => $cat['id']
                            ];
                        } elseif ($this->settings->structure_bags == 3) {
                            $result['categories'][0] = Yii::$app->getModule('forsage')->bags_key;
                            $result['categories'][1] = [
                                'name_uk' => $cat['name_uk'],
                                'name_ru' => $cat['name_ru'],
                                'id' => $cat['id']
                            ];
                        } else {
                            $result['categories'][0] = Yii::$app->getModule('forsage')->bags_key;
                        }
                        //} elseif ($this->settings->accessories_type) { //аксессуары
                        //    $result['type_id'] = $this->settings->accessories_type;
                        //     $result['categories'][0] = 'Other';
                    } else {
                        if ($this->settings->accessories_type) {
                            $result['type_id'] = $this->settings->accessories_type;
                            $result['unit'] = 4;//упаковка
                        } else {
                            $result['success'] = false;
                            $result['error'][] = 'Accessories disabled!';
                        }


                        /*if ($this->settings->structure_accessories == 1) {
                            $result['categories'][0] = Yii::$app->getModule('forsage')->accessories_key;
                            $result['categories'][1] = [
                                'name_uk' => $sex,
                                'name_ru' => $sex
                            ];
                        } elseif ($this->settings->structure_accessories == 2) {
                            $result['categories'][0] = Yii::$app->getModule('forsage')->accessories_key;
                            $result['categories'][1] = [
                                'name_uk' => $sex,
                                'name_ru' => $sex
                            ];
                            $result['categories'][2] = [
                                'name_uk' => $cat['name_uk'],
                                'name_ru' => $cat['name_ru']
                            ];*/
                        if ($this->settings->structure_accessories == 3) {
                            $result['categories'][0] = Yii::$app->getModule('forsage')->accessories_key;
                            $result['categories'][1] = [
                                'name_uk' => $cat['name_uk'],
                                'name_ru' => $cat['name_ru'],
                                'id' => $cat['id']
                            ];
                        } else {
                            $result['categories'][0] = Yii::$app->getModule('forsage')->accessories_key;
                        }


                        //$result['success'] = false;
                        //$result['error'][] = 'Type access denied';
                    }

                } elseif ($product['category']['id'] == self::CATEGORY_BOOTS) {
                    //echo 'SHOES';die;
                    //$result['categories'][0] = 'Shoes/???'; // . $result['categories'][0]
                    // $result['categories'][0] = 'Shoes/' . $result['categories'][0];
                    // $result['categories'][0] = $result['categories'][0];
                }

            } else {
                $result['success'] = false;
                $result['error'][] = 'Unknown product category child error';
                self::log('Unknown product category child error');
            }
        }
        if (!isset($result['images'])) {
            $result['success'] = false;
            $result['error'][] = 'Unknown product images error';
            // self::log('Unknown product images error');
        }

        return $result;
    }

    public function getTypeId($mainCategory, $category)
    {

        if (isset($mainCategory, $category)) {
            if ($mainCategory['id'] == self::CATEGORY_CLOTHES_ACCESSORIES) {
                if ($this->settings->clothes_type && in_array($category['id'], $this->categories_clothes)) { //Одежда
                    return $this->settings->clothes_type;
                } elseif ($this->settings->bags_type && in_array($category['id'], $this->categories_bags)) { //сумки
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
            if (isset($category['descriptions'][0], $category['descriptions'][1])) {
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
                if ($response['product_ids']) {
                    return $response['product_ids'];
                }
            }
        }
        self::log('Error: ' . __FUNCTION__ . '(' . $supplier_id . ') - ');
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
        self::log('Error: ' . __FUNCTION__ . ' - ' . $response['message']);
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


    public function getDelete($start = 3600, $end = 0)
    {
        $url = "https://forsage-studio.com/api/get_deleted/";
        $params['start_date'] = $start;
        $params['end_date'] = $end;
        $params['page'] = 1;
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


       // echo '123';die;
        if ($response->isOk) {
            return $response->data;
        } else {
            echo $response->content;die;
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
