<?php

use panix\mod\shop\models\ProductType;
use yii\helpers\ArrayHelper;

?>

<?php echo $form->field($model, 'accessories_type')->dropdownList($types, ['prompt' => '---'])->hint($model::t('TYPE_HINT')); ?>
<?php echo $form->field($model, 'structure_accessories')->radioList([
    0=>Yii::$app->getModule('forsage')->accessories_key,
    //1=>html_entity_decode('Bags &rarr; [Man,Woman,Kids] -- Нужно протестить с разными товарами'),
    //2=>html_entity_decode('Bags &rarr; [Man,Woman,Kids] &rarr; [Category] -- Нужно протестить с разными товарами'),
    3=>html_entity_decode(Yii::$app->getModule('forsage')->accessories_key.' &rarr; [Category]'),
]); ?>

<?php echo $form->field($model, 'out_stock_delete')->checkbox(); ?>
<?php echo $form->field($model, 'brand')->checkbox(); ?>
<?php echo $form->field($model, 'apikey')->textarea(); ?>
<?php //echo $form->field($model, 'accessories_type')->dropdownList($types, ['prompt' => '---'])->hint($model::t('TYPE_HINT')); ?>
<?php echo $form->field($model, 'hook_key')->textInput()->hint(((Yii::$app->request->isSecureConnection) ? 'https://' : 'http://') . Yii::$app->request->serverName . '/forsage/webhook/' . $model->hook_key); ?>