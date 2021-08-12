<?php

namespace panix\mod\forsage\components;

use Yii;
use yii\httpclient\Client;
use panix\engine\CMS;
use panix\mod\shop\models\Attribute;
use panix\mod\shop\models\AttributeOption;
use panix\mod\shop\models\translate\AttributeOptionTranslate;
use panix\mod\shop\models\Category;
use panix\mod\shop\models\Manufacturer;
use panix\mod\shop\models\Product;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\helpers\Json;

/**
 * Imports products from XML file
 */
class ForsageProductsImport
{

    public $load_time = 0;
    public $apikey = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOjMxNzUsImlzcyI6Imh0dHBzOi8vZm9yc2FnZS1zdHVkaW8uY29tL2dlbmVyYXRlVG9rZW4vMzE3NSIsImlhdCI6MTYxMzc1NTk1OCwiZXhwIjo0NjgwOTU1OTU4LCJuYmYiOjE2MTM3NTU5NTgsImp0aSI6InBXTEVNdHFaN1hxamhNNXQifQ.y3Meudu7MwACk6ujbKxlK8K3ZHZoNeGR5tRehp4aAQ8';

    public $logstring = '';
    public $replacesDirsName = array('.', ' ');


    /**
     * ID of the ShopType model to apply to new attributes and products
     */
    const DEFAULT_TYPE = 1;

    /**
     * @var string alias where to save uploaded files
     */
    public $tempDirectory = '@runtime/forsage';
    public $countNoAdd = 0;
    public $countAdd = 0;
    /**
     * @var string
     */
    protected $data;

    public $product_ids = array();
    /**
     * @var Category
     */
    protected $_rootCategory;


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

    public $image = false;


    /**
     * @param $model Product
     * @param $attributeName
     * @param $attributeValue
     * @param array $params
     */
    private function attributeData($model, $attributeName, $attributeValue, $params = array())
    {
        if (isset($attributeValue)) {
            $attrsdata = array();
            $attributeModel = ForsageExternalFinder::getObject(ForsageExternalFinder::OBJECT_TYPE_ATTRIBUTE, $attributeName);
            if (!$attributeModel) {

                //if not exists create attribute
                $attributeModel = new Attribute();
                $attributeModel->title = $attributeName;
                $attributeModel->name = CMS::slug($attributeName);
                $attributeModel->type = Attribute::TYPE_RADIO_LIST;
                $attributeModel->save(false);
                $this->createExternalId(ForsageExternalFinder::OBJECT_TYPE_ATTRIBUTE, $attributeModel->id, $attributeModel->title);
            }
            if ($attributeModel) {

                if ($params['isFilter']) {
                    $option = AttributeOption::find();
                    //$option->joinWith('translations');
                    $option->where(['attribute_id' => $attributeModel->id]);
                    //$option->andWhere([AttributeOptionTranslate::tableName() . '.value' => $attributeValue]);
                    $opt = $option->one();
                    if (!$opt)
                        $opt = $this->addOptionToAttribute($attributeModel->id, $attributeValue);

                    // print_r($opt);die;
                    //$attrsdata[$attributeModel->name] = $option->id;
                    $attrsdata[$attributeModel->name] = ($params['isFilter']) ? $opt->id : $attributeValue;
                }

            }

            if (!empty($attrsdata)) {

                $model->setEavAttributes($attrsdata, true);
            }
        }
    }

    /**
     * importAll catalog products
     *
     * @param $offset int
     * @param $limit int
     */
    public function importAll($offset, $limit)
    {

        $getSuppliers = $this->getSuppliers();
        //foreach ($this->disallow_supplier_ids2 as $d)
        //    unset($suppliers2[$d]);
        if ($getSuppliers) {
            //if ($limit) {

            //var_dump($offset);
            //var_dump($limit);
            //die;
            $suppliers2 = array_slice($getSuppliers, $offset, $limit, true);
            //} else {
            //    $suppliers2 = $getSuppliers;
            // }

            foreach ($suppliers2 as $supplier_id => $supplier) {
                //ignore suppliers
                if (!in_array($supplier, $this->disallow_supplier_ids)) {
                    //for first run
                    $supplier_products = $this->getSupplierProductIds($supplier_id);

                    if ($supplier_products) {
                        if (count($supplier_products) > 0) {
                            echo count($supplier_products) . $supplier . PHP_EOL;
                            foreach ($supplier_products as $product_key => $product_id) {

                                echo '------- Loading product: ' . $product_id . ' -------' . PHP_EOL;
                                $product = $this->getProduct($product_id);
                                $this->insert_update($product);
                            }
                        }
                    }
                }
            }
        }
    }

    public function change()
    {
        $supplier_products = $this->getChanges();

        if (is_array($supplier_products)) {
            if (count($supplier_products) > 0) {
                foreach ($supplier_products as $product_key => $product) {
                    print_r($product);die;
                    //$product = $this->getProduct($product_id);
                    //if ($product) {
                       // $this->insert_update($product, 1);
                    //}
                }
            }
        }
    }

    /**
     * Add new products
     */
    public function products()
    {
        $products = $this->getProducts();
        if ($products) {
            foreach ($products as $product) {
                $this->insert_update($product);
            }
        }
    }


    public function insert_update($product, $change = 0)
    {


        $starttime = microtime(true);

        $this->logstring = '------- ';
        $characteristics = array();

        if (isset($product['characteristics'])) {

            //$this->logstring .='FID: '.$product->id.' ';
            $characteristics = $this->getOptionsProduct($product['characteristics'], $change);
        } else {
            //$this->logstring .='FID: Unknown ';
            $characteristics['ignoreFlag'] = true;

            echo 'No add by characteristics product_id: ' . PHP_EOL;
            print_r($product);
            self::log('No add by characteristics product_id: ');
        }

        $hasAdd = true;
        //if (!$change && isset($product->quantity) && !$product->quantity) {
        //    $hasAdd = false;
        //}

        $sub_category = $this->getProductCategory($product);
        // echo $sub_category;

        if ($sub_category && !$characteristics['ignoreFlag'] && $hasAdd) {
            if (isset($characteristics['supplier_name']) && !in_array($characteristics['supplier_name'], $this->disallow_supplier_ids)) {
                $imageBuild = $this->buildPathToTempFile($characteristics['image'], $characteristics['supplier_name']);

                if ($imageBuild) {
                    $createExId = false;
                    $model = ForsageExternalFinder::getObject(ForsageExternalFinder::OBJECT_TYPE_PRODUCT, $product['id']);


                    if (!$model) {
                        $model = new Product();
                        $model->type_id = self::DEFAULT_TYPE;
                        $model->sku = $product['vcode'];
                        $createExId = true;
                        $this->logstring .= "Insert: FID: {$product['id']} ";
                    } else {
                        $this->logstring .= "Update: FID: {$product['id']}, PID: {$model->id} ";
                    }


                    $model->switch = ($product['quantity']) ? 1 : 0;

                    $this->logstring .= "Visible: {$model->switch} ";

                    if ($product['quantity']) {
                        $model->availability = 1;//есть на складе
                    } else {
                        $model->availability = 2;//нет на складе
                    }

                    $model->price = (isset($characteristics['price'])) ? $characteristics['price'] : 0;
                    $model->price_purchase = (isset($characteristics['price_purchase'])) ? $characteristics['price_purchase'] : 0;
                    //if (isset($characteristics['in_box'])) {
                    //    $model->in_box = $characteristics['in_box'];
                    //    $model->in_ros = $characteristics['in_box'];
                    //}
                    $model->quantity = $product['quantity'];
                    // $model->exchange_service = 'forsage';
                    if (isset($characteristics['currency_id'])) {
                        $model->currency_id = $characteristics['currency_id'];
                    }


                    if (isset($characteristics['main_category_name'])) {
                        $fullCategoryName = $this->my_ucfirst($characteristics['main_category_name']) . '/' . $sub_category;
                    }

                    if (isset($characteristics['main_category_name'])) {
                        if (isset($product['supplier']['company']) && !empty($product['supplier']['company'])) {
                            $manufacturer = ForsageExternalFinder::getObject(ForsageExternalFinder::OBJECT_TYPE_MANUFACTURER, $product['supplier']['company']); //$supplier->name
                            if (!$manufacturer) {
                                $manufacturer = new Manufacturer();
                                $manufacturer->name = $product['supplier']['company']; //$supplier->name;
                                $manufacturer->slug = CMS::slug($manufacturer->name);
                                $manufacturer->save(false);
                                $this->createExternalId(ForsageExternalFinder::OBJECT_TYPE_MANUFACTURER, $manufacturer->id, $manufacturer->name);
                            }
                            $model->manufacturer_id = $manufacturer->id;
                            $model->name = $this->my_ucfirst($characteristics['main_category_name']) . ' ' . $manufacturer->name . ' ' . $product['vcode'];
                            $model->slug = CMS::slug($model->name);
                        } else {
                            $model->name = $this->my_ucfirst($characteristics['main_category_name']) . ' ' . $product['vcode'];
                            $model->slug = CMS::slug($model->name);
                        }


                        /*if (($supplier = $this->getProductSupplier($product))) {
                            $supplierModal = ForsageExternalFinder::getObject(ForsageExternalFinder::OBJECT_TYPE_SUPPLIER, $supplier->name); //$supplier->name
                            if (!$supplierModal) {
                                $supplierModal = new Suppliers;
                                $supplierModal->name = $supplier->name;
                                if (isset($supplier->address))
                                    $supplierModal->address = $supplier->address;
                                $supplierModal->save(false);
                                $this->createExternalId(ForsageExternalFinder::OBJECT_TYPE_SUPPLIER, $supplierModal->id, $supplierModal->name);
                            }
                            $model->supplier_id = $supplierModal->id;
                        }*/

                    }
                    //this for test vor validate
                    $fullCategoryName = 'test/ddddddddddddddddd';
                    $categoryId = ForsageExternalFinder::getObject(ForsageExternalFinder::OBJECT_TYPE_MAIN_CATEGORY, $fullCategoryName, false);


                    if ($categoryId) {
                        $model->main_category_id = $categoryId;
                    } else {
                        $modelMain = ForsageExternalFinder::getObject(ForsageExternalFinder::OBJECT_TYPE_MAIN_CATEGORY, $fullCategoryName);
                        $model->main_category_id = $modelMain->id;
                        $modelCategory = ForsageExternalFinder::getObject(ForsageExternalFinder::OBJECT_TYPE_CATEGORY, $fullCategoryName);
                        if (!$modelCategory) {
                            $modelCategory = new Category;
                            $modelCategory->name = $sub_category;
                            $modelCategory->slug = CMS::slug($modelCategory->name);
                            echo 'CREATE SUB CATEGORY: ' . $sub_category . PHP_EOL;
                            if ($modelMain)
                                $modelCategory->appendTo($modelMain);

                            $this->createExternalId(ForsageExternalFinder::OBJECT_TYPE_CATEGORY, $modelCategory->id, $fullCategoryName);

                        }
                    }
                    //  print_r($categoryId);die;

                    $model->save(false);

                    if ($model->price_purchase && $model->price && !$model->currency_id) {
                        self::log('ADD ADDON PRICE BY ' . $model->id);
                        // $model->processPrices(array(
                        //     array('order_from' => 5, 'value' => $model->price - ($model->price % $model->price_purchase / 2))
                        // ));
                    }

                    // Create product external id
                    if ($createExId === true)
                        $this->createExternalId(ForsageExternalFinder::OBJECT_TYPE_PRODUCT, $model->id, $product['id']);

                    /* $categoryId = ForsageExternalFinder::getObject(ForsageExternalFinder::OBJECT_TYPE_CATEGORY, $fullCategoryName, false);
                     if (is_numeric($categoryId)) {
                         $category1 = Category::findOne($categoryId);

                         $categories = array();
                         $subCategory = $category1->ancestors()->excludeRoot()->findAll();
                         if (isset($subCategory)) {
                             foreach ($subCategory as $cat) {
                                 $categories[] = $cat->id;
                             }
                             $model->setCategories($categories, $categoryId);
                         } else {
                             $model->setCategories(array($categoryId), $categoryId);
                         }
                     }*/

                    //print_r($product);die;
                    if ($sub_category)
                        $this->attributeData($model, 'Тип', $sub_category, ['isFilter' => true]);
                    if (isset($characteristics['size']))
                        $this->attributeData($model, 'Размер', $characteristics['size'], ['isFilter' => true]);
                    if (isset($characteristics['season']))
                        $this->attributeData($model, 'Сезон', $characteristics['season'], ['isFilter' => true]);
                    if (isset($characteristics['color']))
                        $this->attributeData($model, 'Цвет', $characteristics['color'], ['isFilter' => true]);
                    if (isset($characteristics['material_ware']))
                        $this->attributeData($model, 'Материал изделия', $characteristics['material_ware'], ['isFilter' => true]);
                    if (isset($characteristics['material_lining']))
                        $this->attributeData($model, 'Материал подкладки', $characteristics['material_lining'], ['isFilter' => true]);
                    if (isset($characteristics['material_foot']))
                        $this->attributeData($model, 'Материал подошвы', $characteristics['material_foot'], ['isFilter' => true]);
                    if (isset($characteristics['country']))
                        $this->attributeData($model, 'Страна производителя', $characteristics['country'], ['isFilter' => true]);
                    if (isset($characteristics['in_box'])) {
                        $this->attributeData($model, 'Пар в ящике', $characteristics['in_box'], ['isFilter' => false]);
                    }


                    //set image
                    if ($characteristics['image']) {
                        $imageModel = ForsageExternalFinder::getObject(ForsageExternalFinder::OBJECT_TYPE_IMAGE, $characteristics['supplier_name'] . '/' . $product['id'] . '/' . basename($characteristics['image']));


                        if (!$imageModel) {
                            // && $model->getImage()
                            //if ($imageBuild) {
                            $res = $model->attachImage($imageBuild);
                            //  var_dump($res);die;
                            if ($res) {
                                $this->createExternalId(ForsageExternalFinder::OBJECT_TYPE_IMAGE, $model->id, $characteristics['supplier_name'] . '/' . $product['id'] . '/' . basename($characteristics['image']));
                            }
                            // }
                        }
                    }


                }
            }
        }


        echo $this->logstring . sprintf("[Time: %f sec]", microtime(true) - $starttime) . PHP_EOL;
        self::log($this->logstring);
    }


    /**
     * TEST
     *
     * @param $characteristics
     * @param $changes
     * @return array
     */
    public function getOptionsProduct($characteristics, $changes = 0)
    {

        $result = [];
        // $result['image'] = false;
        $result['ignoreFlag'] = true;
        $result['errors'] = [];
        $result['images'] = [];
        $sex = false;
        $type = false;

        foreach ($characteristics as $characteristic) {
            $children = false;
            $cattype = false;
            if ($characteristic['name'] == 'Фото 1') {
                $result['ignoreFlag'] = false;
                $result['image'] = $characteristic['value'];
                $result['images'][] = $characteristic['value'];
            }
            if ($characteristic['name'] == 'Фото 2') {
                $result['ignoreFlag'] = false;
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
            /*-if ($characteristic->name == 'Категория') {
                if (!empty($characteristic->value)) {
                    $result['sub_category'] = $characteristic->value;
                    if ($result['sub_category'] == 'Сандалии') {
                        $result['sub_category'] = 'Сандали';
                    }

                    if (in_array($result['sub_category'], array('Рюкзак', 'Сумка', 'Кошелек', 'Клатч'))) {
                        $result['ignoreFlag'] = true;
                    }
                } else {
                    $result['ignoreFlag'] = true;
                }
            }*/
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
                        $result['ignoreFlag'] = true;
                    }
                } else {
                    $result['errors'][$characteristic['name']] = "Пустой";
                    $result['ignoreFlag'] = true;
                }

            }

            if ($characteristic['name'] == 'Тип') {
                if (!empty($characteristic['value'])) {
                    $result['main_category_name'] = $characteristic['value'];
                    $type = $characteristic['value'];
                    // $cattype = $result['main_category_name'];
                    // if (in_array($characteristic->value, array('девочка', 'мальчик'))) {
                    //     $children = $characteristic->value;
                    // }

                } else {
                    //$result['errors'][$characteristic->name]= "Пустой";
                }
            }

            if ($characteristic['name'] == 'Пол') { //женщины, мужчины и дети
                if ($characteristic['value'] == 'женщины') {
                    $result['main_category_name'] = 'Женские';
                    $sex = $result['main_category_name'];
                } elseif ($characteristic['value'] == 'мужчины') {
                    $result['main_category_name'] = 'Мужские';
                    $sex = $result['main_category_name'];
                } elseif ($characteristic['value'] == 'дети') {
                    $sex = 'Дети';
                    /*if ($cattype) {
                        $result['main_category_name'] = $cattype;
                    } else {
                        $result['ignoreFlag'] = true;
                    }*/
                }
            }
        }
        if (!isset($result['price'])) {
            $result['ignoreFlag'] = true;
            $result['errorMessages']['Цена'] = 'Не найдена.';
        }
        if ($sex == 'Женские') {
            $result['main_category_name'] = $sex;
        } elseif ($sex == 'Женские') {
            $result['main_category_name'] = $sex;
        } elseif ($sex == 'Дети') {
            if (in_array($type, ['девочка', 'мальчик'])) {
                //  $children = $characteristic->value;
                $result['main_category_name'] = $this->my_ucfirst($type);
            } else {
                $result['errors'][$sex] = 'Не мальчик и не девочка';
                $result['ignoreFlag'] = true;
            }
        }
        if (!isset($result['main_category_name'])) {
            $result['ignoreFlag'] = true;
            $result['errors']['Категория'] = 'Основная категория отсуствует.';
        }

        return $result;
    }

    public function getProductCategory($product)
    {
        if (isset($product['category'])) {
            if ($product['category']['name'] == 'Обувь') {
                if (isset($product['category']['child'])) {
                    return $product['category']['child']['name'];
                } else {
                    self::log('no find category child');
                    return false;
                }
            }
        } else {
            self::log('no find category');
            return false;
        }
        return false;
    }

    public function getProductSupplier($product)
    {
        $result = [];
        if (isset($product['supplier'])) {
            if (isset($product['supplier']['company'])) {
                $result['name'] = $product['supplier']['company'];
            }
            if (isset($product['supplier']['address'])) {
                $result['address'] = $product['supplier']['address'];
            }
            return (object)$result;
        }
        self::log('no find supplier');
        return false;
    }

    private function getSeasonData($id)
    {
        $result = [];
        $id = mb_strtolower($id);
        if ($id == 'демисезон') {
            $result = array('name' => 'Весна-Осень', 'id' => 8);
        } elseif ($id == 'лето') {
            $result = array('name' => 'Лето', 'id' => 4);
        } elseif ($id == 'зима') {
            $result = array('name' => 'Зима', 'id' => 2);
        } else {
            self::log('SEASION: ' . $id);
        }
        return (object)$result;
    }

    /**
     * @param $attribute_id
     * @param $value
     * @return AttributeOption
     */
    public function addOptionToAttribute($attribute_id, $value)
    {
        // Add option
        $option = new AttributeOption;
        $option->attribute_id = $attribute_id;
        $option->value = $value;
        $option->save(false);
        $this->createExternalId(ForsageExternalFinder::OBJECT_TYPE_ATTRIBUTE_OPTION, $option->id, $option->value);
        return $option;
    }


    public function getSupplierProductIds($supplier_id)
    {
        $url = "https://forsage-studio.com/api/get_products_by_supplier/{$supplier_id}?token={$this->apikey}"; //&start_date={$date}&end_date={$date}

        $response = $this->conn_curl($url);
        if (isset($response['success'])) {
            if ($response['success'] == 'true') {
                return $response['product_ids'];
            }
        } else {
            self::log('Method getSupplierProductIds Error success SID: ' . $supplier_id);
        }
    }

    public function getRefbookCharacteristics()
    {
        $url = "https://forsage-studio.com/api/get_refbook_characteristics?token={$this->apikey}"; //&start_date={$date}&end_date={$date}

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

        $url = "https://forsage-studio.com/api/get_product/{$product_id}?token={$this->apikey}"; //&start_date={$date}&end_date={$date}

        $response = $this->conn_curl($url);
        if (isset($response['success'])) {
            if ($response['success'] == 'true') {
                return $response['product'];
            }
        } else {
            self::log('Method getProduct Error success PID: ' . $product_id);
        }

    }

    public function getSuppliers()
    {
        $url = "https://forsage-studio.com/api/get_suppliers/?token={$this->apikey}"; //&start_date={$date}&end_date={$date}
        $response = $this->conn_curl($url);
        if (isset($response)) {

            return (array)$response;
        } else {
            self::log('Method getSuppliers Error success');
            return false;
        }
    }

    public function getChanges()
    {
        $start_date = strtotime(date('Y-m-d'));
        $end_date = strtotime(date('Y-m-d')) + 86400;

        //products = "full" or "changes"
        $url = "https://forsage-studio.com/api/get_changes/?token={$this->apikey}&start_date={$start_date}&end_date={$end_date}&products=full";

        $response = $this->conn_curl($url);

        if ($response) {
            if (isset($response['success'])) {
                if ($response['success'] == 'true') {
                    //return $response['product_ids'];
                    return $response;
                } else {
                    self::log('Method getChanges response no true');
                    return false;
                }
            } else {
                self::log('Method getChanges Error success');
                return false;
            }
        }
    }

    /**
     * @param null $start_data
     * @return bool
     */
    public function getProducts($start_data = null)
    {
        if (!$start_data) {
            $start_date = strtotime(date('Y-m-d'));// - 86400 * 1;
        } else {
            $start_date = strtotime($start_data);// - 86400 * 1;
        }
        $end_date = strtotime(date('Y-m-d'));

        //$start_date = strtotime('28.01.2019');
        //$end_date = strtotime('28.01.2019');

        $url = "https://forsage-studio.com/api/get_products/?token={$this->apikey}&start_date={$start_date}";

        $response = $this->conn_curl($url);

        if (isset($response['success'])) {
            if ($response['success'] == 'true') {
                return $response['products'];
            } else {
                return false;
            }
        } else {
            self::log('Method getProducts Error success');
            return false;
        }
    }

    /**
     * @param $type
     * @param $id
     * @param $externalId
     */
    public function createExternalId($type, $id, $externalId)
    {
        \Yii::$app->db->createCommand()->insert('{{%exchange_forsage}}', array(
            'object_type' => $type,
            'object_id' => $id,
            'external_id' => $externalId
        ))->execute();
    }

    /**
     * Builds path to downloaded files. E.g: we receive
     * file with name 'import/df3/fl1.jpg' and build path to temp dir,
     * runtime/fl1.jpg
     *
     * @param $fileName
     * @return string
     */
    public function buildPathToTempFile2($fileName, $dir = false)
    {
        $fullFileName = $fileName;

        $tmp = explode('/', $fileName);
        $fileName = end($tmp);
        if ($dir) {
            //создаем папку если ее нету.
            if (!file_exists(Yii::getPathOfAlias($this->tempDirectory) . DS . $dir)) {
                CFileHelper::createDirectory(Yii::getPathOfAlias($this->tempDirectory) . DS . $dir, $mode = 0775, $recursive = true);
            }
            $path = Yii::getPathOfAlias($this->tempDirectory) . DS . $dir . DS . $fileName;
            if (!file_exists($path)) {
                $get = @file_get_contents($fullFileName);
                if ($get) {
                    if (file_put_contents($path, $get, FILE_APPEND)) {
                        return $path;
                    } else {
                        return false;
                    }
                } else {
                    self::log('IMG ERROR:' . $path . ' - ' . $dir);
                    return false;
                }
            } else {
                return $path;
            }
        }

    }

    public function buildPathToTempFile3($fileName, $dir)
    {
        if (!file_exists(Yii::getPathOfAlias($this->tempDirectory) . DS . $dir)) {
            FileHelper::createDirectory(Yii::getPathOfAlias($this->tempDirectory) . DS . $dir, $mode = 0775, $recursive = true);
        }
        $fullFileName = $fileName;

        $tmp = explode('/', $fileName);

        //открываем сеанс
        $curl = curl_init($fullFileName);
        $fileName = end($tmp);
        $newFilePath = Yii::getPathOfAlias($this->tempDirectory) . DS . $dir . DS . $fileName;
        //задаем параметры
        curl_setopt($curl, CURLOPT_USERAGENT, 'mariya7km');

        //открываем файловый дескриптор (куда сохранять файл)
        $fp = fopen($newFilePath, 'w+b');

        //сохраняем файл
        curl_setopt($curl, CURLOPT_FILE, $fp);

        //запускаем сеанс
        curl_exec($curl);
        //закрываем сеанс
        curl_close($curl);

        //закрываем дескриптор
        fclose($fp);
        if ($fp) {
            return $newFilePath;
        } else {
            return false;
        }
    }

    public function buildPathToTempFile4($fileName, $dir)
    {
        if (!$dir && !$fileName) {
            return false;
        }
        // if (!file_exists(Yii::getPathOfAlias($this->tempDirectory) . DS . $dir)) {
        //     CFileHelper::createDirectory(Yii::getPathOfAlias($this->tempDirectory) . DS . $dir, $mode = 0775, $recursive = true);
        // }
        $fullFileName = $fileName;

        $tmp = explode('/', $fileName);
        $fileName = end($tmp);
        $newFilePath = \Yii::getAlias($this->tempDirectory) . DS . $dir . DS . $fileName;
        //if (file_exists($newFilePath)) {
        //     return $newFilePath;
        // }
        $userAgent = 'Googlebot/2.1 (http://www.googlebot.com/bot.html)';
        $ch = curl_init($fullFileName);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        $output = curl_exec($ch);
        $fh = fopen($newFilePath, 'w');
        fwrite($fh, $output);
        fclose($fh);
        if ($fh) {
            return $newFilePath;
        } else {
            return false;
        }


    }

    public function buildPathToTempFile($fileName,$dir){

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

        if($response->isOk){
           // print_r($response);die;
            return $newFilePath;
        }else{
            return false;
        }

    }
    public function _buildPathToTempFile($fileName, $dir)
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


        //if (file_exists($newFilePath)) {
        //     return $newFilePath;
        //}
        $ch_check = curl_init(str_replace(" ", "%20", $fullFileName));
        curl_setopt($ch_check, CURLOPT_TIMEOUT, 50);
        curl_setopt($ch_check, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch_check, CURLOPT_HEADER, false);
        curl_setopt($ch_check, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_check, CURLOPT_BINARYTRANSFER, true);
        //curl_setopt($ch_check, CURLOPT_FILE, $fullFileName);


        $output = curl_exec($ch_check);
        $httpcode = curl_getinfo($ch_check, CURLINFO_HTTP_CODE);
        curl_close($ch_check);

        if ($httpcode >= 200 && $httpcode < 300) {
            $fp = fopen($newFilePath, 'w+');
            if (!$fp)
                return false;
            fwrite($fp, $output);
            fclose($fp);
            return $newFilePath;
        } else {
            return false;
        }
    }

    private function setMessage($message_code)
    {
        return \Yii::$app->name . ': ' . iconv('UTF-8', 'windows-1251', Yii::t('exchange1c/default', $message_code));
    }

    private static function log($msg, $level = 'info')
    {
        \Yii::debug($msg, $level);
    }

    /**
     * @param string $url
     * @return bool|mixed
     */
    private function conn_curl($url)
    {
        $client = new Client(['baseUrl' => $url]);
        $response = $client->createRequest()
            ->setFormat(Client::FORMAT_JSON)
            ->send();

        if ($response->isOk) {
            return $response->data;
        } else {
            return false;
        }
    }

}
