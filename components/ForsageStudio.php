<?php

namespace panix\mod\forsage\components;

use Yii;
use yii\base\Component;
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
use yii\helpers\Console;
use yii\console\ExitCode;

/**
 * ForsageStudio class
 */
class ForsageStudio extends Component
{

    /**
     * @var string
     */
    protected $data;

    public function getApiKey()
    {
        return Yii::$app->getModule('forsage')->apiKey;
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

    public function getChanges($start = 3600, $end = 0)
    {


        $hour = 3600;
        $day = 86400;
        //for CRON
        $end_date = time() + $end;
        $start_date = time() - $start;

        //products = "full" or "changes"
        Yii::$app->controller->stdout('Loading...' . PHP_EOL, Console::FG_GREEN);
        $url = "https://forsage-studio.com/api/get_changes/";
        $params['start_date'] = $start_date;
        $params['end_date'] = $end_date;
        $params['products'] = 'full';
        $response = $this->conn_curl($url, $params);

        if ($response) {
            if (isset($response['success'])) {
                if ($response['success'] == 'true') {
                    //return $response['product_ids'];
                    return $response;
                } else {
                    return false;
                }
            } else {
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
        //$start_date = strtotime('28.01.2019');
        //$end_date = strtotime('28.01.2019');


        $params['start_date'] = $start_date;
        $params['end_date'] = $end_date;

        $url = "https://forsage-studio.com/api/get_products/";

        $response = $this->conn_curl($url, $params);

        if (isset($response['success'])) {
            if ($response['success'] == true) {
                return $response['products'];
            } else {
                return false;
            }
        } else {
            self::log('Method getProducts Error success');
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

    public function getSupplierProductIds($supplier_id, $params = [])
    {
        $url = "https://forsage-studio.com/api/get_products_by_supplier/{$supplier_id}"; //&start_date={$date}&end_date={$date}

        $response = $this->conn_curl($url, $params);

        if (isset($response['success'])) {
            if ($response['success'] == 'true') {
                return $response['product_ids'];
            } else {

                echo $response['message'];
                die;
            }
        } else {
            return false;
            self::log('Method getSupplierProductIds Error success SID: ' . $supplier_id);
        }
    }

    /**
     * @param string $url
     * @return bool|mixed
     */
    private function conn_curl($url, $params = [])
    {

        $params['token'] = $this->apiKey;

        $client = new Client(['baseUrl' => $url]);
        $response = $client->createRequest()
            // ->setFormat(Client::FORMAT_JSON)
            //->setMethod('GET')
            ->setOptions([
                'sslVerifyPeer' => false
            ])
            ->setData($params)
            ->send();

        if ($response->isOk) {
            return $response->data;
        } else {
            return Json::decode($response->content);
        }
    }



    public function execute($product)
    {
        //  $props = $this->getOptionsProduct($product['characteristics']);
        $props = $this->getProductProps($product);
        $errors = (isset($props['error'])) ? true : false;
//print_r($props);die;

        $model = Product::findOne(['forsage_id' => $product['id']]);

        if (!$product['quantity']) {
            if ($model) {
                if(Yii::$app->getModule('forsage')->outStockDelete){
                    self::log('Product delete ' . $product['id']);
                    $model->delete();

                    if (isset($props['images'])) {
                        foreach ($props['images'] as $imageUrl) {
                            $this->external->deleteExternal($this->external::OBJECT_IMAGE, $product['id'] . '/' . basename($imageUrl));
                        }
                    }
                }

            }
            return false;
        }

        if (!$model) {
            $model = new Product();
            $model->type_id = Yii::$app->getModule('forsage')->type_id;
            $model->forsage_id = $product['id'];
            $model->sku = $product['vcode'];
        }
        $categoryName = $this->generateCategory($product);
        $model->name = $this->generateProductName($product);
        $model->slug = CMS::slug($model->name);
        $model->unit = Yii::$app->getModule('forsage')->unit;

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
        $model->quantity = $product['quantity'];
        $model->currency_id = (isset($props['currency_id'])) ? $props['currency_id'] : NULL;
        //$model->video = (isset($props['video'])) ? $props['video'] : NULL;




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
            $hashList = [];
            foreach ($model->getImages()->all() as $im) {
                $im->delete();
            }
            foreach ($props['images'] as $imageUrl) {
                $hash = md5_file($imageUrl);
                $res = $model->attachImage($imageUrl);

            }
        }

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
                            $result['currency_id'] = 3;
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
}
