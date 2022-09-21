<?php

namespace panix\mod\forsage;

use Yii;
use panix\engine\WebModule;
use yii\base\BootstrapInterface;

class Module extends WebModule implements BootstrapInterface
{
    public $apiKey = '';
    public $icon = '';
    public $unit = 1;
    public $type_id = 1;
    public $outStockDelete = true;
    public $onlySuppliers = [];
    public $hookKey;

    public $excludeCategories = [];

    public function bootstrap($app)
    {
        $app->urlManager->addRules(
            [
                'forsage/webhook/<hook:\w+>' => 'forsage/default/webhook',
            ],
            true
        );
    }

    public function getInfo()
    {
        return [
            'label' => Yii::t('forsage/default', 'MODULE_NAME'),
            'author' => $this->author,
            'version' => '1.0',
            'icon' => $this->icon,
            'description' => Yii::t('forsage/default', 'MODULE_DESC'),
            'url' => ['/admin/forsage'],
        ];
    }


}
