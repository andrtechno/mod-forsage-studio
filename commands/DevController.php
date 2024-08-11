<?php

namespace panix\mod\forsage\commands;

use panix\mod\forsage\components\FixSizeQueue;
use panix\mod\forsage\components\ProductByIdQueue;
use panix\mod\shop\models\AttributeOption;
use panix\mod\shop\models\Brand;
use panix\mod\shop\models\Category;
use panix\mod\shop\models\ProductAttributesEav;
use panix\mod\shop\models\ProductCategoryRef;
use panix\mod\shop\models\ProductImage;
use panix\mod\shop\models\Supplier;
use Yii;
use panix\mod\forsage\components\ForsageStudio;
use panix\engine\CMS;
use panix\engine\console\controllers\ConsoleController;
use panix\mod\shop\models\Attribute;
use panix\mod\shop\models\Product;
use panix\mod\shop\components\ExternalFinder;
use yii\base\ErrorException;
use yii\console\ExitCode;
use yii\console\widgets\Table;
use yii\helpers\BaseFileHelper;
use yii\helpers\Console;
use yii\helpers\FileHelper;
use yii\httpclient\Client;


ignore_user_abort(1);
set_time_limit(0);

/**
 * Class LoadController
 * @property ExternalFinder $external
 * @package panix\mod\forsage\commands
 */
class DevController extends ConsoleController
{
    public $tempDirectory = '@runtime/forsage';
    /**
     * @var ForsageStudio
     */
    private $fs;

    public function actionPush($id)
    {
        Yii::$app->queue->push(new ProductByIdQueue([
            'id' => $id,
        ]));

    }

    public function actionFixer()
    {

        $products = Product::find()->groupBy(['main_category_id'])->asArray()->limit(50)->offset(0)->all();

        foreach ($products as $p) {


            if ($p['forsage_id']) {

                $product = $this->fs->getProduct($p['forsage_id']);
                if ($product) {
                    $props = $product->getProductProps($product->product);

                    $external_hash = '';
                    foreach ($props['categories'] as $names) {
                        if (is_array($names)) {
                            if (isset($names['id'])) {
                                $external_hash .= '/' . trim($names['id']);
                            } else {
                                $external_hash .= '/' . trim($names['name_uk']);
                            }
                        } else {
                            $external_hash .= '/' . trim($names);
                        }

                    }

                    $external_hash = strtolower($external_hash);
                    $categry = Category::find()->where(['id' => $p['main_category_id']])->one();
                    $categry->external_id = $external_hash;
                    $categry->saveNode(false);
                } else {
                    echo $p['forsage_id'] . PHP_EOL;
                }
            }
        }
    }

    public function beforeAction($action)
    {
        if (!extension_loaded('intl')) {
            throw new ErrorException('PHP Extension intl not active.');
        }
        $forsageClass = Yii::$app->getModule('forsage')->forsageClass;
        $this->fs = new $forsageClass;
        return parent::beforeAction($action); // TODO: Change the autogenerated stub
    }

    /**
     * [DEV] Reset all queues in the database
     *
     * @throws \yii\db\Exception
     */
    public function actionQueueReset()
    {
        if (Yii::$app->has('elasticsearch')) {

        }
        Yii::$app->db->createCommand()->update('{{%queue}}', ['done_at' => NULL, 'attempt' => NULL, 'reserved_at' => NULL], '')->execute();
    }

    public function actionRefbooks()
    {
        $refbooks = $this->fs->getRefbookCharacteristics();

        foreach ($refbooks as $ref) {
            //$attribute = Attribute::findOne(['title_ru' => $ref['name']]);
            print_r($ref);
            if ($attribute) {

            }
            die;
        }
    }

    public function actionRemoveImg()
    {
        $files = glob(Yii::getAlias('@uploads/store/product/*'));
        foreach ($files as $file) {
            if (is_dir($file)) {
                echo $file . PHP_EOL;
                $product = Product::findOne(basename($file));
                if (!$product) {
                    echo 'remove dir ' . $file . PHP_EOL;
                    BaseFileHelper::removeDirectory($file);
                }

            }
        }

        $filesAssets = glob(Yii::getAlias('@web/assets/product/*'));
        foreach ($filesAssets as $fileAsset) {
            if (is_dir($fileAsset)) {
                echo $fileAsset . PHP_EOL;
                $product = Product::findOne(basename($fileAsset));
                if (!$product) {
                    echo 'remove dir ' . $fileAsset . PHP_EOL;
                    BaseFileHelper::removeDirectory($fileAsset);
                }

            }
        }
    }

    /**
     * [DEV] Only if enable DEBUG
     * @throws \yii\db\Exception
     */
    public function actionClearDb()
    {
        if (YII_DEBUG) {
            $db = Yii::$app->db;
            //$db->createCommand()->truncateTable('{{%forsage_studio}}')->execute();
            /*$db->createCommand()->truncateTable(ProductImage::tableName())->execute();
            $db->createCommand()->truncateTable(ProductCategoryRef::tableName())->execute();
            $db->createCommand()->truncateTable(ProductAttributesEav::tableName())->execute();

            $db->createCommand()->truncateTable(AttributeOption::tableName())->execute();
            $db->createCommand()->truncateTable(Attribute::tableName())->execute();*/

            $db->createCommand()->truncateTable(ProductCategoryRef::tableName())->execute();
            $db->createCommand()->truncateTable(Category::tableName())->execute();


            if ($db->createCommand('SELECT * FROM ' . Product::tableName() . ' WHERE forsage_id IS NOT NULL')->query()->count()) {
                $db->createCommand()->delete(Product::tableName(), ['not', ['forsage_id' => null]])->execute();
                //$db->createCommand()->truncateTable(Product::tableName() . ' WHERE forsage_id IS NOT NULL')->execute();
            }
            if ($db->createCommand('SELECT * FROM ' . Brand::tableName() . ' WHERE forsage_id IS NOT NULL')->query()->count()) {
                //$db->createCommand()->truncateTable(Brand::tableName() . ' WHERE (forsage_id IS NOT NULL)')->execute();
                $db->createCommand()->delete(Brand::tableName(), ['not', ['forsage_id' => null]])->execute();
            }
            if ($db->createCommand('SELECT * FROM ' . Supplier::tableName() . ' WHERE forsage_id IS NOT NULL')->query()->count()) {
                //$db->createCommand()->truncateTable(Supplier::tableName())->execute();
                $db->createCommand()->delete(Supplier::tableName(), ['not', ['forsage_id' => null]])->execute();
            }
            $db->createCommand()->truncateTable('{{%shop__type_attribute}}')->execute();
            $db->createCommand()->truncateTable(ProductImage::tableName())->execute();
            $db->createCommand()->truncateTable(AttributeOption::tableName())->execute();

            $model = new Category;
            $model->name = 'Каталог продукции';
            $model->lft = 1;
            $model->rgt = 2;
            $model->depth = 1;
            $model->slug = 'root';
            $model->full_path = '';
            if ($model->validate()) {
                $model->saveNode();
            }
        } else {
            echo 'YII_DEBUG disabled!.';
        }
    }

    public function actionFixSize($page = 0)
    {
        $products = Product::find()->offset($page)->limit(500)->orderBy(['id' => SORT_ASC])->asArray()->all();
        $rows = [];
        $queue = Yii::$app->queue;
        foreach ($products as $product) {
            if (isset($product['forsage_id'])) {
                $job = new FixSizeQueue(['id' => $product['forsage_id']]);
                if (Yii::$app->db->driverName == 'pgsql') {
                    $queue->push($job);
                } else {
                    $rows[] = [
                        'default',
                        $queue->serializer->serialize($job),
                        time(),
                        120,
                        1024
                    ];
                }
            }
        }
        Yii::$app->db->createCommand()->batchInsert($queue->tableName, [
            'channel',
            'job',
            'pushed_at',
            'ttr',
            'priority'
        ], $rows)->execute();
    }

    /**
     * Add all products in queue
     *
     * @param int $quantity
     * @return int
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     * @throws \yii\httpclient\Exception
     */
    public function actionQueueAll($quantity = 1)
    {
        $confirmMsg = '';
        $confirmMsg .= "Starting confirm: says (yes|no)\r\n";
        $confirm = $this->confirm($confirmMsg, false);
        $queue = Yii::$app->queue;
        if ($confirm) {
            $suppliers = $this->fs->getSuppliers();
            if (!$suppliers) {
                echo 'ERROR!';
            }

            foreach ($suppliers as $supplier) {
                $products = $this->fs->getSupplierProductIds($supplier['id'], ['quantity' => $quantity]);
                if ($products) {
                    $rows = [];
                    foreach ($products as $product) {
                        $job = new ProductByIdQueue(['id' => $product]);
                        if (Yii::$app->db->driverName == 'pgsql') {
                            $queue->push($job);
                        } else {
                            $rows[] = [
                                'default',
                                $queue->serializer->serialize($job),
                                time(),
                                120,
                                1024
                            ];
                        }
                    }
                    Yii::$app->db->createCommand()->batchInsert($queue->tableName, [
                        'channel',
                        'job',
                        'pushed_at',
                        'ttr',
                        'priority'
                    ], $rows)->execute();
                }
            }

        } else {
            echo "\r\n";
            $this->stdout("--- Cancelled! ---\r\nYou can specify the paths using:");
            echo "\r\n\r\n";
            $this->stdout("    php cmd forsage/load/queue-all --interactive=1|0", Console::FG_BLUE);
            echo "\r\n";
            return ExitCode::OK;
        }
    }

    /**
     * Add to queue supplier products "<id> <quantity>" default quantity is 1
     *
     * @param int $id
     * @param int $quantity
     * @return int
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     * @throws \yii\httpclient\Exception
     */
    public function actionQueueSupplier($id, $quantity = 1)
    {
        $confirmMsg = '';
        $confirmMsg .= "Starting confirm: says (yes|no)\r\n";
        $queue = Yii::$app->queue;
        $confirm = $this->confirm($confirmMsg, false);
        if ($confirm) {
            $products = $this->fs->getSupplierProductIds($id, ['quantity' => $quantity]);
            if ($products) {
                $rows = [];
                foreach ($products as $product) {
                    $job = new ProductByIdQueue(['id' => $product]);
                    if (Yii::$app->db->driverName == 'pgsql') {
                        $queue->push($job);
                    } else {
                        $rows[] = [
                            'default',
                            $queue->serializer->serialize($job),
                            time(),
                            120,
                            1024
                        ];
                    }
                }
                Yii::$app->db->createCommand()->batchInsert($queue->tableName, [
                    'channel',
                    'job',
                    'pushed_at',
                    'ttr',
                    'priority'
                ], $rows)->execute();
            }
        } else {
            echo "\r\n";
            $this->stdout("--- Cancelled! ---\r\nYou can specify the paths using:");
            echo "\r\n\r\n";
            $this->stdout("    php cmd forsage/load/queue-all --interactive=1|0", Console::FG_BLUE);
            echo "\r\n";
            return ExitCode::OK;
        }
    }

    /**
     * Add to queue changes by datetime "<start> <end>"
     *
     * @param int $start
     * @param int $end
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function actionQueueChanges($start = 3600, $end = 0)
    {
        $start = eval('return ' . $start . ';');
        $end = eval('return ' . $end . ';');
        //for CRON
        $end_date = time() - $end;
        $start_date = time() - $start;

        //products = "full" or "changes"
        $this->stdout('end: ' . date('Y-m-d H:i:s', $end_date) . PHP_EOL, Console::FG_GREEN);
        $this->stdout('start: ' . date('Y-m-d H:i:s', $start_date) . PHP_EOL, Console::FG_GREEN);
        $this->stdout('Loading...' . PHP_EOL, Console::FG_GREEN);

        $response = $this->fs->getChanges($start_date, $end_date);

        if ($response) {
            $i = 0;
            foreach ($response['product_ids'] as $index => $product) {
                Yii::$app->queue->push(new ProductByIdQueue([
                    'id' => $product,
                ]));
            }
        }
    }

    /**
     * Add to queue products by datetime "<start> <end>"
     *
     * @param int $start
     * @param int $end
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     * @throws \yii\httpclient\Exception
     */
    public function actionQueueProducts($start = 3600, $end = 0)
    {
        $start = eval('return ' . $start . ';');
        $end = eval('return ' . $end . ';');
        $end_date = time() - $end;
        $start_date = time() - $start;

        $this->stdout('start: ' . date('Y-m-d H:i:s', $start_date) . PHP_EOL, Console::FG_GREEN);
        $this->stdout('end: ' . date('Y-m-d H:i:s', $end_date) . PHP_EOL, Console::FG_GREEN);
        $this->stdout('Loading...' . PHP_EOL, Console::FG_GREEN);

        $response = $this->fs->getProducts($start_date, $end_date, ['with_descriptions' => 0]);
        $queue = Yii::$app->queue;
        if ($response) {
            $rows = [];
            foreach ($response as $index => $item) {
                $job = new ProductByIdQueue(['id' => $item['id']]);
                if (Yii::$app->db->driverName == 'pgsql') {
                    $queue->push($job);
                } else {
                    $rows[] = [
                        'default',
                        $queue->serializer->serialize($job),
                        time(),
                        120,
                        1024
                    ];
                }
            }
            Yii::$app->db->createCommand()->batchInsert($queue->tableName, [
                'channel',
                'job',
                'pushed_at',
                'ttr',
                'priority'
            ], $rows)->execute();
        } else {
            echo 'response empty';
        }
    }

    public function actionImageMain($page = 0, $limit = 10000)
    {
        $offset = $limit * $page;
        $db = Yii::$app->db;
        $products = $db->createCommand("SELECT product.id, im.filename
  FROM {{%shop__product}} AS product JOIN {{%shop__product_image}} AS im ON (product.id = im.product_id) WHERE im.is_main = 1 LIMIT {$limit} OFFSET {$offset}")->queryAll();

        foreach ($products as $p) {
            $db->createCommand()->update('{{%shop__product}}', ['image' => $p['filename']], 'id=' . $p['id'])->execute();
        }

        /*$products = Product::find()
            ->limit($limit)
            ->offset($offset)
            ->all();
        foreach ($products as $p) {
            $p->image = $p->mainImage2->filename;
            $p->save(false);
        }*/
    }


}
