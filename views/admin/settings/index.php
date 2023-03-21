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


$forsageClass = Yii::$app->getModule('forsage')->forsageClass;
$fs = new $forsageClass;

?>


<?php

$form = ActiveForm::begin(['id' => 'forsage-form']);
//560406
//811645 обвусь унисекс
//790624 обувь.украина
//784824 чемодан
$product = $fs->getProduct(784824);
//$test = $product->execute();
//\panix\engine\CMS::dump($product->product);
//\panix\engine\CMS::dump($fs->getProductProps($product->product));
$types = ArrayHelper::map(ProductType::find()->all(), 'id', 'name');
$categories = $model->getCategories(0, 9);
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

    <div class="card">
        <div class="card-header">
            <h5>Guides</h5>
        </div>
        <div class="card-body p-3">
            <?php
            $content = file_get_contents(Yii::getAlias('@forsage') . DIRECTORY_SEPARATOR . 'guide.md');
            echo \yii\helpers\Markdown::process($content, 'gfm');
            ?>
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
    var checked_ids = $('#CategoriesCloth').jstree('get_checked');
    var checked_ids2 = $('#CategoriesBags').jstree('get_checked');
    var inputName = 'SettingsForm[categories_clothes]';
    var input = $('input[name=\"'+inputName+'\"]');
    if(checked_ids.length){
        if(!input.length){
            form.prepend('<input type=\"hidden\" name=\"'+inputName+'\" value=\"'+checked_ids.join(',')+'\" />');
        }
        input.val(checked_ids.join(','));
    }
    
    
    var inputName2 = 'SettingsForm[categories_bags]';
    var input2 = $('input[name=\"'+inputName2+'\"]');
    if(checked_ids2.length){
        if(!input2.length){
            form.prepend('<input type=\"hidden\" name=\"'+inputName2+'\" value=\"'+checked_ids2.join(',')+'\" />');
        }
        input2.val(checked_ids2.join(','));
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

}

