<?php

use panix\engine\Html;
use panix\engine\bootstrap\ActiveForm;
use panix\engine\jui\DatePicker;
use yii\helpers\ArrayHelper;
use panix\mod\shop\models\ProductType;
/**
 * @var \panix\engine\bootstrap\ActiveForm $form
 * @var \panix\mod\forsage\models\SettingsForm $model
 */

$types = ArrayHelper::map(ProductType::find()->all(), 'id', 'name');
?>




<?= Html::beginForm('/admin/forsage/settings/products', 'GET'); ?>
<div class="card d-none" id="card-filter-collapse">
    <div class="card-header">
        <h5>
            <a class="" data-toggle="collapse" href="#filter-collapse" role="button" aria-expanded="false"
               aria-controls="filter-collapse">
                <i class="icon-arrow-down" id="filter-collapse-icon"></i> Печать
            </a>
        </h5>
    </div>
    <div class="collapse" id="filter-collapse">
        <div class="card-body">

            <div class="pl-3 pr-3">
                <div class="form-group">
                    <div class="input-group">
                        <div class="input-group-append">
                            <span class="input-group-text">с</span>
                        </div>
                        <?php
                        echo DatePicker::widget([
                            'name' => 'start',
                            'value' => (Yii::$app->request->get('start')) ? Yii::$app->request->get('start') : date('Y-m-d'),
                            //'language' => 'ru',
                            'dateFormat' => 'yyyy-MM-dd',
                        ]);
                        ?>
                        <div class="input-group-prepend">
                            <span class="input-group-text">по</span>
                        </div>
                        <?php
                        echo DatePicker::widget([
                            'name' => 'end',
                            'value' => (Yii::$app->request->get('end')) ? Yii::$app->request->get('end') : date('Y-m-d'),
                            //'language' => 'ru',
                            'dateFormat' => 'yyyy-MM-dd',
                        ]);
                        ?>


                    </div>


                </div>
            </div>
            <div class="card-footer text-center">

                <?= Html::submitButton('Показать', ['class' => 'btn btn-success', 'name' => '']); ?>
            </div>
        </div>
    </div>
</div>
<?= Html::endForm(); ?>

<?php
$form = ActiveForm::begin();
?>
<div class="card">
    <div class="card-header">
        <h5><?= $this->context->pageName ?></h5>
    </div>
    <div class="card-body">
        <?php echo $form->field($model, 'out_stock_delete')->checkbox(); ?>
        <?php echo $form->field($model, 'apikey')->textarea(); ?>
        <?php echo $form->field($model, 'boots_type')->dropdownList($types,['prompt'=>'---'])->hint($model::t('TYPE_HINT')); ?>
        <?php echo $form->field($model, 'cloth_type')->dropdownList($types,['prompt'=>'---'])->hint($model::t('TYPE_HINT')); ?>
        <?php echo $form->field($model, 'hook_key')->textInput()->hint(((Yii::$app->request->isSecureConnection) ? 'https://' : 'http://') . Yii::$app->request->serverName . '/forsage/webhook/' . $model->hook_key); ?>
    </div>
    <div class="card-footer text-center">
        <?= $model->submitButton(); ?>
    </div>
</div>
<?php ActiveForm::end(); ?>
