<?php

namespace panix\mod\forsage\controllers;


use panix\mod\shop\models\Product;
use Yii;
use yii\base\Controller;
use yii\web\Response;
use yii\widgets\ActiveForm;
use panix\mod\forsage\components\ForsageStudio;

class DefaultController extends Controller
{

    public function actionWebhook()
    {
        $onlySuppliers = Yii::$app->getModule('forsage')->onlySuppliers;
        $fs = new ForsageStudio();
        $input = json_decode($fs->input, true);

        Yii::info('actionWebhook','forsage');


        if ($input) {
            file_put_contents(Yii::getAlias('@runtime') . '/forsage_webhook.txt', $fs->input, FILE_APPEND);

            if(in_array('NewProduct',$input['change_type'])){
                foreach ($input['product_ids'] as $product_id) {
                    $product = $fs->getProduct($product_id);

                    Yii::info('GetProduct '.$product_id,'forsage');

                    //$access = ($onlySuppliers) ? in_array($product['supplier']['id'], $onlySuppliers) : false;


                    if ($product) {
                        file_put_contents(Yii::getAlias('@runtime') . '/forsage_webhook.txt', "[add:{$product_id}]", FILE_APPEND);
                        // $fs->execute($product);
                    }
                }
            }else{
                if (isset($input['product_ids'])) {
                    foreach ($input['product_ids'] as $product_id) {
                        $product = Product::findOne(['forsage_id' => $product_id]);
                        if ($product) {
                            file_put_contents(Yii::getAlias('@runtime') . '/forsage_webhook.txt', "[update:{$product_id}]", FILE_APPEND);
                            //$p = $fs->getProduct($product_id);
                        }

                    }
                }
            }
        }
    }

}
