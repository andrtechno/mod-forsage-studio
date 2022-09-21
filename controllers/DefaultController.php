<?php

namespace panix\mod\forsage\controllers;

use Yii;
use yii\base\Controller;
use panix\mod\forsage\components\ForsageStudio;
use panix\mod\forsage\components\ProductByIdQueue;

class DefaultController extends Controller
{
    public function beforeAction($action)
    {
        if (Yii::$app->settings->get('forsage', 'hook_key') != Yii::$app->request->get('hook')) {
            Yii::info('ERROR HOOK KEY', 'forsage');
        }
        return parent::beforeAction($action);
    }

    public function actionWebhook()
    {
        $fs = new ForsageStudio();
        $input = json_decode($fs->input, true);

        if ($input) {
            if (isset($input['product_ids'])) {
                Yii::info('push: ' . implode(',', $input['product_ids']), 'forsage');
            } else {
                Yii::info('push no ids: ', 'forsage');
            }

            foreach ($input['product_ids'] as $product_id) {
                Yii::$app->queue->push(new ProductByIdQueue([
                    'product' => $product_id,
                ]));
            }
        } else {
            Yii::info('Error input', 'forsage');
        }
    }

}
