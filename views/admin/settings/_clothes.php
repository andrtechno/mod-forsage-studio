<?php echo $form->field($model, 'structure_clothes')->radioList([
    0=>Yii::$app->getModule('forsage')->clothes_key,
    //1=>html_entity_decode('Clothes &rarr; [Man,Woman,Kids] -- Нужно протестить с разными товарами'),
    //2=>html_entity_decode('Clothes &rarr; [Man,Woman,Kids] &rarr; [Category] -- Нужно протестить с разными товарами'),
    3=>html_entity_decode(Yii::$app->getModule('forsage')->clothes_key.' &rarr; [Category]'),
    //4=>html_entity_decode('[Man,Woman,Kids] ??'),
    //5=>html_entity_decode('[Man,Woman,Kids] &rarr; [Category] ??')
]); ?>
<?php echo $form->field($model, 'clothes_type')->dropdownList($types, ['prompt' => '---'])->hint($model::t('TYPE_HINT')); ?>
<div class="form-group row required">
    <label class="col-sm-2 col-form-label" for="settingsform-1"><?= $model::t('TAB_CHOTLES'); ?></label>
    <div class="col-sm-10">
        <div class="alert alert-info mb-3 ml-0 mr-0"><?= Yii::t('forsage/default','CATEGORIES_HIT'); ?></div>
        <?php
        echo \panix\ext\jstree\JsTree::widget([
            'id' => 'CategoriesClothes',
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