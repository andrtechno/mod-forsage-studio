<?php

namespace panix\mod\forsage\controllers\admin;

use panix\engine\CMS;
use panix\mod\banner\models\BannerSearch;
use panix\mod\forsage\components\ForsageStudio;
use panix\mod\forsage\components\ProductByIdQueue;
use panix\mod\forsage\components\ProductIdQueue;
use panix\mod\shop\models\search\SupplierSearch;
use panix\mod\shop\models\Supplier;
use Yii;
use panix\engine\controllers\AdminController;
use panix\mod\forsage\models\SettingsForm;
use yii\data\ArrayDataProvider;

class DefaultController extends AdminController
{

    public $icon = '';
    /**
     * @var ForsageStudio
     */
    public $fs;

    public function beforeAction($action)
    {
        $forsageClass = Yii::$app->getModule('forsage')->forsageClass;
        $this->fs = new $forsageClass;

        return parent::beforeAction($action); // TODO: Change the autogenerated stub
    }

    public function actionSuppliers()
    {
        $this->pageName = Yii::t('forsage/default', 'Поставщики');
        $this->view->params['breadcrumbs'] = [
            [
                'label' => Yii::t('forsage/default', 'MODULE_NAME'),
                'url' => ['suppliers']
            ],
            $this->pageName
        ];

        //$searchModel = new SupplierSearch();
        //$dataProvider = $searchModel->search(Yii::$app->request->getQueryParams());

        $suppliers = $this->fs->getSuppliers();

        $dataProvider = new ArrayDataProvider([
            'allModels' => $suppliers,
            'sort' => [
                'attributes' => ['id', 'company', 'email'],
            ],
            'pagination' => [
                'pageSize' => 50,
            ],
        ]);

        return $this->render('suppliers', [
            'dataProvider' => $dataProvider,
            // 'searchModel' => $searchModel
        ]);
    }

    public function actionSupplierLoadProducts($id, $name)
    {
        $queue = Yii::$app->queue;
        $products = $this->fs->getSupplierProductIds($id, ['quantity' => 1]);
        //$supplier = Supplier::findOne(['forsage_id' => $id]);
        if ($products) {
            $rows = [];
            Yii::$app->session->setFlash('success', Yii::t('forsage/default', 'RELOAD_SUPPLIER', [
                count($products),
                $name

            ]));
            foreach ($products as $product) {
                $job = new ProductByIdQueue(['id' => $product, 'images' => true, 'attributes' => true]);
                if (Yii::$app->db->driverName == 'pgsql') {
                    $queue->push($job);
                } else {
                    $rows[] = [
                        'default',
                        $queue->serializer->serialize($job),
                        time(),
                        120,
                        1024
                    ];
                }
            }
            Yii::$app->db->createCommand()->batchInsert($queue->tableName, [
                'channel',
                'job',
                'pushed_at',
                'ttr',
                'priority'
            ], $rows)->execute();

        } else {
            Yii::$app->session->setFlash('error', 'Error!');
        }

        return $this->redirect('suppliers');
    }

}
