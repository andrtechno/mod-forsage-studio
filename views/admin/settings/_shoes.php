<?php echo $form->field($model, 'structure_shoes')->radioList([
    0=>html_entity_decode('Shoes'),
    1=>html_entity_decode('Shoes &rarr; [Man,Woman,Kids]'),
    2=>html_entity_decode('Shoes &rarr; [Man,Woman,Kids] &rarr; [Category]'),
    3=>html_entity_decode('Shoes &rarr; [Category]'),
    4=>html_entity_decode('[Man,Woman,Kids]'),
    5=>html_entity_decode('[Man,Woman,Kids] &rarr; [Category]')
]); ?>
<?php echo $form->field($model, 'boots_type')->dropdownList($types, ['prompt' => '---'])->hint($model::t('TYPE_HINT')); ?>
