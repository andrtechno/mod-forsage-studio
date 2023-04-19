<?php

use panix\engine\Html;
use panix\engine\bootstrap\ActiveForm;
use yii\helpers\ArrayHelper;
use panix\mod\shop\models\ProductType;
use yii\web\View;

/**
 * @var \panix\engine\bootstrap\ActiveForm $form
 * @var \panix\mod\forsage\models\SettingsForm $model
 */

$form = ActiveForm::begin(['id' => 'forsage-form']);
$types = ArrayHelper::map(ProductType::find()->all(), 'id', 'name');
$categories = $model->getCategories(9);
?>


    <div class="card">
        <div class="card-header">
            <h5><?= $this->context->pageName ?></h5>
        </div>
        <div class="card-body">
            <?php

            $tabs = [];
            $tabs[] = [
                'label' => $model::t('TAB_MAIN'),
                'content' => $this->render('_main', ['form' => $form, 'model' => $model, 'types' => $types]),
                'active' => true,
                'options' => ['class' => 'flex-sm-fill text-center nav-item'],
            ];
            $tabs[] = [
                'label' => $model::t('TAB_SHOES'),
                'content' => $this->render('_shoes', ['form' => $form, 'model' => $model, 'types' => $types]),
                'headerOptions' => [],
                'options' => ['class' => 'flex-sm-fill text-center nav-item'],
            ];
            $tabs[] = [
                'label' => $model::t('TAB_CHOTLES'),
                'content' => $this->render('_clothes', ['form' => $form, 'model' => $model, 'types' => $types, 'categories' => $categories]),
                'headerOptions' => [],
                'options' => ['class' => 'flex-sm-fill text-center nav-item'],
            ];
            $tabs[] = [
                'label' => $model::t('TAB_BAGS'),
                'content' => $this->render('_bags', ['form' => $form, 'model' => $model, 'types' => $types, 'categories' => $categories]),
                'headerOptions' => [],
                'options' => ['class' => 'flex-sm-fill text-center nav-item'],
            ];

            echo \panix\engine\bootstrap\Tabs::widget([
                //'encodeLabels'=>true,
                'options' => [
                    'class' => 'nav-pills flex-column flex-sm-row nav-tabs-static'
                ],
                'items' => $tabs,
            ]);

            ?>


        </div>
        <div class="card-footer text-center">
            <?= $model->submitButton(); ?>
        </div>
    </div>

<?php ActiveForm::end(); ?>
<?php
$this->registerJs("

;(function ($) {
    $.fn.checkNode = function (id) {
        $(this).bind('loaded.jstree', function () {
            $(this).jstree('check_node', id);
        });
    };
    
    $.fn.checkNodes = function (ids) {
        var _ids = ids.split(',');
        $(this).bind('loaded.jstree', function () {
            $(this).jstree('check_node', _ids);
        });
    };
})(jQuery);


$(document).on('beforeSubmit','#forsage-form',function (e) {
    var form = $(this);
    var checked_ids = $('#CategoriesClothes').jstree('get_checked');
    var checked_ids2 = $('#CategoriesBags').jstree('get_checked');
    var checked_ids3 = $('#CategoriesShoes').jstree('get_checked');
    var inputName = 'SettingsForm[categories_clothes]';
    var input = $('input[name=\"'+inputName+'\"]');
    if(checked_ids.length){
        if(!input.length){
            form.prepend('<input type=\"hidden\" name=\"'+inputName+'\" value=\"'+checked_ids.join(',')+'\" />');
        }
        input.val(checked_ids.join(','));
    }else{
        form.prepend('<input type=\"hidden\" name=\"'+inputName+'\" value=\"\" />');
    }
    
    
    var inputName2 = 'SettingsForm[categories_bags]';
    var input2 = $('input[name=\"'+inputName2+'\"]');
    if(checked_ids2.length){
        if(!input2.length){
            form.prepend('<input type=\"hidden\" name=\"'+inputName2+'\" value=\"'+checked_ids2.join(',')+'\" />');
        }
        input2.val(checked_ids2.join(','));
    }else{
        form.prepend('<input type=\"hidden\" name=\"'+inputName2+'\" value=\"\" />');
    }
    
    var inputName3 = 'SettingsForm[categories_shoes]';
    var input3 = $('input[name=\"'+inputName3+'\"]');
    if(checked_ids3.length){
        if(!input3.length){
            form.prepend('<input type=\"hidden\" name=\"'+inputName3+'\" value=\"'+checked_ids3.join(',')+'\" />');
        }
        input3.val(checked_ids3.join(','));
    }else{
        form.prepend('<input type=\"hidden\" name=\"'+inputName3+'\" value=\"\" />');
    }
    //return false;
});

", View::POS_END);
if (isset($_POST['categories_clothes']) && !empty($_POST['categories_clothes'])) {

    foreach (Yii::$app->request->post('categories_cloths') as $id) {

        //$this->registerJs("$('#CategoryTree').checkNode({$id});", View::POS_END, 'check-' . $id);
    }
} else {
    // Check tree nodes

    //$model->categories_bags = explode(',',$model->categories_bags);
    //$model->categories_cloth = explode(',',$model->categories_cloth);
    // foreach ($model->categories_bags as $c) {
    //    $this->registerJs("$('#CategoriesBags').checkNode({$c});", View::POS_END, 'check-b-' . $c);
    //}

    $this->registerJs("$('#CategoriesBags').checkNodes('{$model->categories_bags}');", View::POS_END, 'categories_bags');
    $this->registerJs("$('#CategoriesClothes').checkNodes('{$model->categories_clothes}');", View::POS_END, 'categories_clothes');
    $this->registerJs("$('#CategoriesShoes').checkNodes('{$model->categories_shoes}');", View::POS_END, 'categories_shoes');

}

