<?php

use yii\widgets\Pjax;
use panix\engine\Html;

Pjax::begin();

echo \panix\engine\grid\GridView::widget([
    'tableOptions' => ['class' => 'table table-striped'],
    'dataProvider' => $dataProvider,
    //'filterModel' => $searchModel,
    'layoutOptions' => ['title' => $this->context->pageName],
    'showFooter' => true,
    //   'footerRowOptions' => ['class' => 'text-center'],
    'rowOptions' => ['class' => 'sortable-column'],
    'columns' => [
        [
            'attribute' => 'company'
        ],
        [
            'header' => Yii::t('shop/admin', 'PRODUCT_COUNT'),
            'contentOptions' => ['class' => 'text-center'],
            'format' => 'raw',
            'value' => function ($model) {
                $supplier = \panix\mod\shop\models\Supplier::findOne(['forsage_id' => $model['id']]);
                if ($supplier) {
                    return Html::a($supplier->productsCount, ['/admin/shop/product', 'ProductSearch[supplier_id]' => $supplier->id]);
                }
                //return $model->productsCount;
            }
        ],
        'DEFAULT_CONTROL' => [
            'class' => 'panix\engine\grid\columns\ActionColumn',
            'template' => '{reload}',
            'buttons' => [
                'reload' => function ($url, $model) {
                    //if ($model->forsage_id) {
                    //    return Html::a(Html::icon('refresh'), ['supplier-load-products', 'id' => $model->forsage_id], ['title' => 'Reload products', 'class' => 'btn btn-sm btn-outline-primary', 'data-pjax' => 0]);
                    //}

                    if ($model['id']) {
                        return Html::a(Html::icon('refresh'), ['supplier-load-products', 'id' => $model['id'],'name'=>$model['company']], ['title' => 'Reload products', 'class' => 'btn btn-sm btn-outline-primary', 'data-pjax' => 0]);
                    }
                }
            ]
        ],
    ]

]);

Pjax::end();
