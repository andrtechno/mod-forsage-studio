<?php

namespace panix\mod\forsage\controllers\admin;

use panix\engine\CMS;
use panix\mod\forsage\components\ForsageStudio;
use panix\mod\forsage\components\ProductIdQueue;
use Yii;
use panix\engine\controllers\AdminController;
use panix\mod\forsage\models\SettingsForm;

class SettingsController extends AdminController
{

    public $icon = 'settings';

    public function actionIndex()
    {
        $this->pageName = Yii::t('app/default', 'SETTINGS');
        $this->view->params['breadcrumbs'] = [
            [
                'label' => $this->module->info['label'],
                'url' => $this->module->info['url'],
            ],
            $this->pageName
        ];
        $model = new SettingsForm();
        if ($model->load(Yii::$app->request->post())) {
            if ($model->validate()) {
                $model->save();
                Yii::$app->session->setFlash("success", Yii::t('app/default', 'SUCCESS_UPDATE'));
            } else {
                foreach ($model->getErrors() as $error) {
                    Yii::$app->session->setFlash("error", $error);
                }
            }
            return $this->refresh();
        }
        return $this->render('index', [
            'model' => $model
        ]);
    }

    public function actionProducts()
    {
        $fs = new ForsageStudio();
        $start = strtotime(Yii::$app->request->get('start'));
        $end = strtotime(Yii::$app->request->get('end'));
        $changes = $fs->getChanges2($start, $end);
        $products = $fs->getProducts($start, $end, ['with_descriptions' => 0]);

        foreach ($products as $product) {
            array_push($changes['product_ids'], $product['id']);
        }


        foreach (array_chunk($changes['product_ids'], 100) as $product_ids) {
            Yii::$app->queue->push(new ProductIdQueue([
                'product_ids' => $product_ids,
            ]));

        }


        $model = new SettingsForm();
        return $this->render('index', [
            'model' => $model
        ]);
    }

}
