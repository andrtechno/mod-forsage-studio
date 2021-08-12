<?php

namespace panix\mod\forsage\commands;

use app\modules\forsage\components\ForsageProductImage;
use app\modules\forsage\components\Image;
use panix\mod\shop\models\Category;
use Yii;
use app\modules\forsage\components\ForsageExternalFinder;
use app\modules\forsage\components\ForsageStudio;
use panix\engine\CMS;
use panix\engine\console\controllers\ConsoleController;
use panix\mod\shop\models\Attribute;
use panix\mod\shop\models\AttributeOption;
use panix\mod\shop\models\Product;
use panix\mod\shop\components\ExternalFinder;
use yii\helpers\Console;
use yii\helpers\FileHelper;
use yii\httpclient\Client;

/**
 * Class LoadController
 * @property ExternalFinder $external
 * @package panix\mod\forsage\commands
 */
class LoadController extends ConsoleController
{
    private $fs;
    /**
     * @var ExternalFinder
     */
    public $external = ExternalFinder::class;

    public function beforeAction($action)
    {
        $this->external = Yii::createObject([
            'class' => $this->external,
            'table' => '{{%forsage_studio}}'
        ]);
        return parent::beforeAction($action); // TODO: Change the autogenerated stub
    }

    public function execute($product)
    {
        //  $props = $this->getOptionsProduct($product['characteristics']);
        $props = $this->getProductProps($product);
        $errors = (isset($props['error'])) ? true : false;
//print_r($props);die;

        $model = Product::findOne(['custom_id' => $product['id']]);

        if (!$product['quantity']) {
            if ($model) {
                $model->delete();

                if (isset($props['images'])) {
                    foreach ($props['images'] as $imageUrl) {
                        $this->external->deleteExternal($this->external::OBJECT_IMAGE, $product['id'] . '/' . basename($imageUrl));
                    }
                }
            }
            return false;
        }

        if (!$model) {
            $model = new Product();
            $model->type_id = 1;
            $model->custom_id = $product['id'];
            $model->sku = $product['vcode'];
        }
        $categoryName = $this->generateCategory($product);
        $model->name = $this->generateProductName($product);
        $model->slug = CMS::slug($model->name);


        $model->switch = ($product['quantity']) ? 1 : 0;
        if ($product['quantity']) {
            $model->availability = 1;//есть на складе
        } else {
            $model->availability = 2;//нет на складе
        }
        $model->discount = NULL;
        $model->price = (isset($props['price'], $props['in_box'])) ? $props['price'] * $props['in_box'] : 0;
        //$props['price_old'] = $props['price'] - 10;
        if (isset($props['price_old'], $props['in_box'])) {
            if ($props['price_old'] > $props['price']) {
                $model->discount = ($props['price_old'] - $props['price']) * $props['in_box'];
                $model->price = $props['price_old'] * $props['in_box'];
            }

        }

        $model->price_purchase = (isset($props['price_purchase'], $props['in_box'])) ? $props['price_purchase'] * $props['in_box'] : 0;
        //if (isset($options['in_box'])) {
        //    $model->in_box = $options['in_box'];
        //    $model->in_ros = $options['in_box'];
        //}
        $model->quantity = $product['quantity'];

        $model->currency_id = (isset($props['currency_id'])) ? $props['currency_id'] : NULL;
        //$model->video = (isset($props['video'])) ? $props['video'] : NULL;


        /*$categoryId = $this->external->getObject($this->external::OBJECT_MAIN_CATEGORY, $categoryName, false);


        if ($categoryId) {
            $model->main_category_id = $categoryId;
        } else {
            $modelMain = ForsageExternalFinder::getObject($this->external::OBJECT_MAIN_CATEGORY, $categoryName);
            $model->main_category_id = $modelMain->id;
            $modelCategory = ForsageExternalFinder::getObject($this->external::OBJECT_CATEGORY, $categoryName);
            if (!$modelCategory) {
                $modelCategory = new Category;
                $modelCategory->name = $sub_category;
                $modelCategory->slug = CMS::slug($modelCategory->name);
                echo 'CREATE SUB CATEGORY: ' . $sub_category . PHP_EOL;
                if ($modelMain)
                    $modelCategory->appendTo($modelMain);

                $this->createExternalId($this->external::OBJECT_CATEGORY, $modelCategory->id, $categoryName);

            }
        }*/


        $model->main_category_id = $this->getCategoryByPath($categoryName);

        $model->save(false);

        $this->processCategories($model, $model->main_category_id);


        if (isset($props['attributes'])) {
            foreach ($props['attributes'] as $prop) {
                $this->attributeData($model, $prop['name'], $prop['value']);
            }
            if (isset($props['in_box'])) {
                $this->attributeData($model, 'Количество в ящике', $props['in_box']);
            }
        }

        //set image
        if (isset($props['images'])) {
            $hashList=[];
          //  $nameList=[];
            foreach ($model->getImages() as $im){
                $im->delete();
              //  $hashList[] = md5_file(Yii::getAlias($im->path.'/'.$im->object_id.'/'.$im->filePath));

            }
       //     print_r($hashList);
            foreach ($props['images'] as $imageUrl) {
                $hash = md5_file($imageUrl);
//echo $hash.PHP_EOL;
                //if(!in_array($hash,$hashList)){


              //  $imageModel = $this->external->getObject($this->external::OBJECT_IMAGE, $hash);
              //  $current_hash = md5_file(Yii::getAlias('@uploads/store/product/'.$model->id.'/'.$im->filePath));
              //  echo $current_hash.PHP_EOL;
              //  if (!$imageModel) {
                    $res = $model->attachImage($imageUrl);
                  //  if ($res) {

                      //  $this->external->createExternalId($this->external::OBJECT_IMAGE, $model->id, $hash);
                   // }
               // }
              //  }
            }
        }

    }

    public function actionProduct($id)
    {


      //  file_put_contents('example.txt', 'Наглый коричневый лисёнок прыгает вокруг ленивой собаки.');

       // $path = 'https://forsage-studio.com/storage/export/excel_images/19532/paliament-com/paliament/d826-1_img2.jpg';
      //  $path = Yii::getAlias('@uploads/store/product/1/d826-1_img2.jpg');
        //  $path = Yii::getAlias('@uploads/store/product/1/shhA7w7z6O.jpg');
       // echo md5_file ($path);

      // die;
        $this->fs = new ForsageStudio();
        $response = $this->fs->getProduct($id);
        // print_r($response);die;
        $this->execute($response);
    }

    public function actionChanges()
    {
        $this->fs = new ForsageStudio();

        $response = $this->fs->getChanges();
        if ($response) {
            $count = Product::find()->where(['custom_id' => $response['product_ids']])->count();
            $i = 0;

            Console::startProgress($i, $count, ' - ', 100);
            foreach ($response['products'] as $index => $item) {
                if ($item['supplier']['id'] == 448) {
                    $result = $this->execute($item);
                    $i++;
                    Console::updateProgress($i, $count, $item['vcode'] . ' - ');
                }


            }
            Console::endProgress(false);
        }
    }


    public function actionProducts()
    {
        $this->fs = new ForsageStudio();

        $response = $this->fs->getProducts();

        if ($response) {
           // $count = Product::find()->where(['custom_id' => $response['product_ids']])->count();
           // $i = 0;

           // Console::startProgress($i, $count, ' - ', 100);
            foreach ($response as $index => $item) {
                if ($item['supplier']['id'] == 448) {
                    $result = $this->execute($item);
                    //$i++;
                  //  Console::updateProgress($i, $count, $item['vcode'] . ' - ');
                }


            }
           // Console::endProgress(false);
        }else{
            echo 'error';
        }
    }

    public function actionIndex()
    {
        $this->fs = new ForsageStudio();

        $list = $this->fs->getSupplierProductIds(448, ['quantity' => 1]);
        $count = count($list);
        $i = 0;
        Console::startProgress($i, $count, ' - ', 100);
        foreach ($list as $index => $item) {
            $product = $this->fs->getProduct($item);

            $this->execute($product);

            $i++;
            Console::updateProgress($i, $count, ' - ');
        }
        Console::endProgress(false);
    }

    private function generateCategory($product)
    {
        $categoryName = '';
        if ($product['category']) {
            $categoryName = $product['category']['name'];
            if (isset($product['category']['child'])) {
                $categoryName .= '/' . $product['category']['child']['name'];
            }
        }
        return $categoryName;
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

    public $replacesDirsName = array('.', ' ');

    public function getOptionsProduct($characteristics, $changes = 0)
    {

        $result = [];
        // $result['image'] = false;
        $result['hasError'] = true;
        $result['errors'] = [];
        $result['images'] = [];
        //$sex = false;
        //$type = false;

        foreach ($characteristics as $characteristic) {
            if ($characteristic['name'] == 'Фото 1') {
                $result['hasError'] = false;
                $result['image'] = $characteristic['value'];
                $result['images'][] = $characteristic['value'];
            }
            if ($characteristic['name'] == 'Фото 2') {
                $result['hasError'] = false;
                $result['image'] = $characteristic['value'];
                $result['images'][] = $characteristic['value'];
            }
            if ($characteristic['name'] == 'Пар в ящике') {
                $result['in_box'] = $characteristic['value'];
            }
            if ($characteristic['name'] == 'Поставщик') {
                $result['supplier_name'] = $characteristic['value'];
                $result['supplier_id'] = $characteristic['id'];
            }

            if ($characteristic['name'] == 'Цена продажи') {
                $result['price'] = $characteristic['value'];
            }
            if ($characteristic['name'] == 'Цена закупки') {
                $result['price_purchase'] = $characteristic['value'];
            }
            if ($characteristic['name'] == 'Размерная сетка') {
                $result['size'] = str_replace(' - ', '-', $characteristic['value']);
            }

            if ($characteristic['name'] == 'Цвет') {
                if (!empty($characteristic['value'])) {
                    $result['color'] = $characteristic['value'];
                }
            }
            if ($characteristic['name'] == 'Материал изделия') {
                if (!empty($characteristic['value'])) {
                    $result['material_ware'] = $characteristic['value'];
                }
            }
            if ($characteristic['name'] == 'Материал подкладки') {
                if (!empty($characteristic['value'])) {
                    $result['material_lining'] = $characteristic['value'];
                }
            }
            if ($characteristic['name'] == 'Материал подошвы') {
                if (!empty($characteristic['value'])) {
                    $result['material_foot'] = $characteristic['value'];
                }
            }
            if ($characteristic['name'] == 'Страна') {
                if (!empty($characteristic['value'])) {
                    $result['country'] = $characteristic['value'];
                }
            }


            if ($characteristic['name'] == 'Валюта продажи') {
                if ($characteristic['value'] == 'доллар') {
                    $result['currency_id'] = 2;
                }

            }
            if ($characteristic['name'] == 'Сезон') {
                if (!empty($characteristic['value'])) {
                    if (isset($this->getSeasonData($characteristic['value'])->name)) {
                        $result['season'] = $this->getSeasonData($characteristic['value'])->name;
                    } else {
                        $result['errors'][$characteristic['name']] = 'Не правильный';
                        $result['hasError'] = true;
                    }
                } else {
                    $result['errors'][$characteristic['name']] = "Пустой";
                    $result['hasError'] = true;
                }

            }


        }
        if (!isset($result['price'])) {
            $result['hasError'] = true;
            $result['errorMessages']['Цена'] = 'Не найдена.';
        }


        return $result;
    }

    private function getSeasonData($id)
    {
        $result = [];
        $id = mb_strtolower($id);
        if ($id == 'демисезон') {
            $result = ['name' => 'Весна-Осень', 'id' => 8];
        } elseif ($id == 'лето') {
            $result = ['name' => 'Лето', 'id' => 4];
        } elseif ($id == 'зима') {
            $result = ['name' => 'Зима', 'id' => 2];
        } else {
            echo('SEASION: ' . $id);
        }
        return (object)$result;
    }

    private function attributeData($model, $attributeName, $attributeValue, $params = array())
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
                        if ($characteristic['value'] == 'доллар') {
                            $result['currency_id'] = 2;
                        }
                    }
                    if ($characteristic['id'] == 25) { //Цена продажи
                        $result['price'] = $characteristic['value'];
                    }
                    if ($characteristic['id'] == 47) { //Старая цена продажи
                        $result['price_old'] = $characteristic['value'];
                    }
                    if ($characteristic['id'] == 24) { //Цена закупки
                        $result['price_purchase'] = $characteristic['value'];
                    }
                    if ($characteristic['id'] == 8) {
                        $result['in_box'] = $characteristic['value'];
                    }
                    // if ($characteristic['id'] == 39) { //Пол

                    //  $result['sex'] = $characteristic['value'];

                    // }//attributes
                    if (!in_array($characteristic['id'], array(3, 8, 13, 24, 25, 29, 33, 34, 35, 38, 46, 45, 47, 53))) {
                        $result['attributes'][$characteristic['id']] = [
                            'name' => $characteristic['name'],
                            'value' => $characteristic['value']
                        ];
                    }


                    // }
                }
            }

            if (!isset($result['images'])) {
                $result['success'] = false;
                $result['error'][] = 'Unknown product images error';
                // self::log('Unknown product images error');
            }
            /* if ($this->getChildCategory($product)) {
                 $result['categories'][1] = $this->getChildCategory($product);
             } else {
                 $result['success'] = false;
                 $result['error'][] = 'Unknown product category child error';
                // self::log('Unknown product category child error');
             }*/
        }


        //if (!isset($result['price'])) {
        //    $result['ignoreFlag'] = true;
        //     $result['error'][] = 'Нет цены';
        // }

        //  return (!isset($result['error']))?$result:false;
        return $result;
    }

    private function generateProductName($product)
    {
        $name = '';
        $category = explode('/', $this->generateCategory($product));
        $category = array_pop($category);
        $name .= $category;
        if (isset($product['vcode'])) {
            $name .= ' ' . $product['vcode'];
        }

        $props = $this->getProductProps($product);
        if (isset($props['attributes'])) {
            if (isset($props['attributes'][6])) {
                $name .= ' ' . $props['attributes'][6]['value'];
            }
        }

        return $name;


    }

    protected $categoriesPathCache = [],
        $productTypeCache = [],
        $manufacturerCache = [],
        $supplierCache = [],
        $currencyCache = [];
    private $subCategoryPattern = '/\\/((?:[^\\\\\/]|\\\\.)*)/';
    protected $rootCategory = null;

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
                $model->slug = CMS::slug($model->name_ru);
                $model->appendTo($parent);
            }

            $parent = $model;
            $level++;

        }
        // Cache category id
        $this->categoriesPathCache[$path] = $model->id;
        if (isset($model)) {
            return $model->id;
        }

        return 1; // root category
    }

    public $tempDirectory = '@runtime/forsage';

    public function buildPathToTempFile($fileName, $dir)
    {

        $dir = str_replace($this->replacesDirsName, '', $dir);
        $dir = mb_strtolower($dir);
        if (!$dir && !$fileName) {
            return false;
        }
        if (!file_exists(\Yii::getAlias($this->tempDirectory) . DIRECTORY_SEPARATOR . $dir)) {
            FileHelper::createDirectory(\Yii::getAlias($this->tempDirectory) . DIRECTORY_SEPARATOR . $dir, $mode = 0775, $recursive = true);
        }
        $fullFileName = $fileName;

        $tmp = explode('/', $fileName);
        $fileName = end($tmp);
        $newFilePath = \Yii::getAlias($this->tempDirectory) . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $fileName;


        $fh = fopen($newFilePath, 'w');
        $client = new Client([
            'transport' => 'yii\httpclient\CurlTransport'
        ]);
        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl(str_replace(" ", "%20", $fullFileName))
            ->setOutputFile($fh)
            ->send();

        if ($response->isOk) {
            // print_r($response);die;
            return $newFilePath;
        } else {
            return false;
        }

    }
}
