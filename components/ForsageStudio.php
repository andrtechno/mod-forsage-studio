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
 * ForsageStudio class
 */
class ForsageStudio
{


    public $apikey = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOjMxNzUsImlzcyI6Imh0dHBzOi8vZm9yc2FnZS1zdHVkaW8uY29tL2dlbmVyYXRlVG9rZW4vMzE3NSIsImlhdCI6MTYxMzc1NTk1OCwiZXhwIjo0NjgwOTU1OTU4LCJuYmYiOjE2MTM3NTU5NTgsImp0aSI6InBXTEVNdHFaN1hxamhNNXQifQ.y3Meudu7MwACk6ujbKxlK8K3ZHZoNeGR5tRehp4aAQ8';

    /**
     * @var string
     */
    protected $data;


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

    public function getChanges()
    {


        $hour = 3600;
        $day = 86400;
        //for CRON
        $end_date = time() - $hour + ($hour / 2);
        $start_date = time() - $hour * 2;


        //back days
       // $start_date = strtotime(date('Y-m-d'))- $day * 4; // - $day
       // $end_date = $start_date + $day - 1;


        echo 'start: ' . date('Y-m-d H:i:s', $start_date);
        echo PHP_EOL;
        echo 'end: ' . date('Y-m-d H:i:s', $end_date);
//die;

       // $start_date = strtotime(date('Y-m-d'));
       // $end_date = strtotime(date('Y-m-d')) + 86400;

        //products = "full" or "changes"
        $url = "https://forsage-studio.com/api/get_changes/";
        $params['start_date']=$start_date;
        $params['end_date']=$end_date;
        $params['products']='full';
        $response = $this->conn_curl($url,$params);

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

        $start_date = time() - $hour * 1;
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

        echo date('Y-m-d H:i:s',$start_date).PHP_EOL;
        echo date('Y-m-d H:i:s',$end_date).PHP_EOL;
        //echo date('Y-m-d H:i:s',$end_date).PHP_EOL;
       // die;
        //$start_date = strtotime('28.01.2019');
        //$end_date = strtotime('28.01.2019');



        $params['start_date']=$start_date;
        $params['end_date']=$end_date;

        $url = "https://forsage-studio.com/api/get_products/";

        $response = $this->conn_curl($url,$params);

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
    public function getSupplierProductIds($supplier_id,$params=[])
    {
        $url = "https://forsage-studio.com/api/get_products_by_supplier/{$supplier_id}"; //&start_date={$date}&end_date={$date}

        $response = $this->conn_curl($url,$params);
        if (isset($response['success'])) {
            if ($response['success'] == 'true') {
                return $response['product_ids'];
            }
        } else {
            self::log('Method getSupplierProductIds Error success SID: ' . $supplier_id);
        }
    }
    /**
     * @param string $url
     * @return bool|mixed
     */
    private function conn_curl($url,$params = [])
    {

        $params['token']=$this->apikey;

        $client = new Client(['baseUrl' => $url]);
        $response = $client->createRequest()
           // ->setFormat(Client::FORMAT_JSON)
            //->setMethod('GET')
            ->setOptions([
                'sslVerifyPeer' => false])
            ->setData($params)
            ->send();

        if ($response->isOk) {
            return $response->data;
        } else {
            return false;
        }
    }

}
