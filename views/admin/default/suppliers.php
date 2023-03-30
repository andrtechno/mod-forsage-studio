<?php

use panix\engine\widgets\Pjax;
use panix\engine\Html;

Pjax::begin(['dataProvider' => $dataProvider]);

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
            'attribute' => 'name'
        ],
        'DEFAULT_CONTROL' => [
            'class' => 'panix\engine\grid\columns\ActionColumn',
            'template' => '{reload}',
            'buttons' => [
                'reload' => function ($url, $model) {

                    return Html::a(Html::icon('refresh'), ['supplier-load-products', 'id' => $model->forsage_id],['title'=>'Reload products','class'=>'btn btn-sm btn-outline-primary','data-pjax'=>0]);
                }
            ]
        ],

    ]

]);

Pjax::end();
