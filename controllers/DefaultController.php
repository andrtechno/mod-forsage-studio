<?php

namespace panix\mod\forsage\controllers;


use panix\engine\CMS;
use Yii;
use yii\base\Controller;
use panix\mod\forsage\components\ForsageStudio;

class DefaultController extends Controller
{
    public function beforeAction($action)
    {
        if(Yii::$app->getModule('forsage')->hookKey != Yii::$app->request->get('hookKey')){
            file_put_contents(Yii::getAlias('@runtime') . '/forsage_webhook.txt', "ERROR KEY\n", FILE_APPEND);
        }
        return parent::beforeAction($action); // TODO: Change the autogenerated stub
    }

    public function actionWebhook()
    {
        $onlySuppliers = Yii::$app->getModule('forsage')->onlySuppliers;
        $fs = new ForsageStudio();
        $input = json_decode($fs->input, true);

        if ($input) {
            //if(in_array('NewProduct',$input['change_type'])){
                foreach ($input['product_ids'] as $product_id) {
                    $product = $fs->getProduct($product_id);

                    Yii::info('NewProduct '.$product_id,'forsage');

                    //$access = ($onlySuppliers) ? in_array($product['supplier']['id'], $onlySuppliers) : false;
                    if ($product) {
                        Yii::info('NewAddProduct '.$product_id,'forsage');
                        file_put_contents(Yii::getAlias('@runtime') . '/forsage_webhook.txt', "[add:{$product_id}]\n", FILE_APPEND);
                        $product->execute();
                    }
                }
            /*}else{
                if (isset($input['product_ids'])) {
                    foreach ($input['product_ids'] as $product_id) {
                        $product = $fs->getProduct($product_id);
                        Yii::info('GetProduct '.$product_id,'forsage');
                        if ($product) {
                            Yii::info('AddProduct '.$product_id,'forsage');
                            file_put_contents(Yii::getAlias('@runtime') . '/forsage_webhook.txt', "[add:{$product_id}]\n", FILE_APPEND);
                            $product->execute();
                        }
                    }
                }
            }*/
        }else{
            Yii::info('Error input','forsage');
        }
    }

}
