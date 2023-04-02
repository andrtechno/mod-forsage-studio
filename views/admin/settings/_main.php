<?php

use panix\mod\shop\models\ProductType;
use yii\helpers\ArrayHelper;

?>


<?php echo $form->field($model, 'out_stock_delete')->checkbox(); ?>
<?php echo $form->field($model, 'brand')->checkbox(); ?>
<?php echo $form->field($model, 'apikey')->textarea(); ?>
<?php //echo $form->field($model, 'accessories_type')->dropdownList($types, ['prompt' => '---'])->hint($model::t('TYPE_HINT')); ?>
<?php echo $form->field($model, 'hook_key')->textInput()->hint(((Yii::$app->request->isSecureConnection) ? 'https://' : 'http://') . Yii::$app->request->serverName . '/forsage/webhook/' . $model->hook_key); ?>