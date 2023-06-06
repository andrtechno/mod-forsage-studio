<?php

use panix\engine\bootstrap\ActiveForm;
use yii\helpers\Html;

?>

<?php $form = ActiveForm::begin(['id' => 'changes-form']); ?>
    <div class="card">
        <div class="card-header">
            <h5><?= $this->context->pageName; ?></h5>
        </div>
        <div class="card-body">
            <?= $form->field($model, 'date')->widget(\panix\engine\jui\DatetimePicker::class, [
                'mode' => 'date'
            ]) ?>
            <?= $form->field($model, 'time')->radioList($model->getTimeList(), ['encode' => false]) ?>

        </div>
        <div class="card-footer text-center">
            <?= Html::submitButton($model::t('SUBMIT_CHANGES'), ['class' => 'btn btn-primary', 'name' => 'type', 'value' => 'changes']) ?>
            <?= Html::submitButton($model::t('SUBMIT_PHOTOSESSION'), ['class' => 'btn btn-primary', 'name' => 'type', 'value' => 'new']) ?>
        </div>
    </div>
<?php ActiveForm::end(); ?>

<?php
$this->registerJs("

    $(document).on('beforeValidate', '#changes-form', function (event, messages, deferreds) {
        //console.log('beforeValidate',messages);
        $(this).find('button[type=\"submit\"]').attr('disabled','disabled');
    }).on('afterValidate', '#changes-form', function (event, messages, errorAttributes) {
        //console.log('afterValidate');
        var countErrors = 0;
        if (errorAttributes.length) {
            if(!countErrors){
                $(this).find('button[type=\"submit\"]').removeAttr('disabled');
            }else{
                //$(this).find('button[type=\"submit\"]').attr('disabled','disabled');
            }
        }else{
            //$(this).find('#cart-submit').removeAttr('disabled');
        }
    }).on('beforeSubmit', '#changes-form', function (event) {
        //console.log('beforeSubmit');
        //$(this).find('button[type=\"submit\"]').removeAttr('disabled');
    });

", yii\web\View::POS_END);
