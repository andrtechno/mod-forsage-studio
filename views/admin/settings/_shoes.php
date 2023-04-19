<?php
$categories = $model->getCategories(11);
?>
<?php echo $form->field($model, 'structure_shoes')->radioList([
    0=>html_entity_decode('Shoes'),
    1=>html_entity_decode('Shoes &rarr; [Man,Woman,Kids]'),
    2=>html_entity_decode('Shoes &rarr; [Man,Woman,Kids] &rarr; [Category]'),
    3=>html_entity_decode('Shoes &rarr; [Category]'),
    4=>html_entity_decode('[Man,Woman,Kids]'),
    5=>html_entity_decode('[Man,Woman,Kids] &rarr; [Category]')
]); ?>
<?php echo $form->field($model, 'boots_type')->dropdownList($types, ['prompt' => '---'])->hint($model::t('TYPE_HINT')); ?>

<div class="form-group row">
    <label class="col-sm-2 col-form-label" for="settingsform-1"><?= $model::t('TAB_SHOES'); ?></label>
    <div class="col-sm-10">
        <div class="alert alert-danger mb-3 ml-0 mr-0"><?= Yii::t('forsage/default','Исключить категории, товары с этих категорий <strong>не будут</strong> добавлены.'); ?></div>
        <?php
        echo \panix\ext\jstree\JsTree::widget([
            'id' => 'CategoriesShoes',
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