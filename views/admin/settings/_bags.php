<div class="form-group">
    <div class="alert alert-info"><?= Yii::t('forsage/default','CATEGORIES_HIT'); ?></div>
</div>
<?php echo $form->field($model, 'bags_type')->dropdownList($types, ['prompt' => '---'])->hint($model::t('TYPE_HINT')); ?>
<div class="form-group row required">
    <label class="col-sm-2 col-form-label" for="settingsform-1"><?= $model::t('TAB_BAGS'); ?></label>
    <div class="col-sm-10">

        <?php
        echo \panix\ext\jstree\JsTree::widget([
            'id' => 'CategoriesBags',
            'allOpen' => false,
            'iconEye' => false,
            //'data' => $model->getCategories(),
            'core' => [
                'data' => $categories,
                'force_text' => true,
                'animation' => 0,
                'strings' => [
                    'Loading ...' => Yii::t('app/default', 'LOADING')
                ],
                "themes" => [
                    "stripes" => true,
                    'responsive' => true,
                    "variant" => "large"
                ],
                'check_callback' => true
            ],
            'checkbox' => [
                'three_state' => false, // check full tree
                'tie_selection' => true,
                'whole_node' => true,
                "keep_selected_style" => true,
            ],
            'plugins' => ['checkbox'],
        ]);
        ?>

    </div>
</div>