<?php

namespace BigBridge\ProductImport\Test\Integration;

use BigBridge\ProductImport\Api\ImportConfig;
use BigBridge\ProductImport\Api\ImporterFactory;
use BigBridge\ProductImport\Api\Product;
use BigBridge\ProductImport\Api\SimpleProduct;
use BigBridge\ProductImport\Model\Db\Magento2DbConnection;
use BigBridge\ProductImport\Model\Resource\MetaData;
use Magento\Framework\App\ObjectManager;

/**
 * @author Patrick van Bergen
 */
class IdTest extends \PHPUnit_Framework_TestCase
{
    /** @var  ImporterFactory */
    private static $factory;

    /** @var  Magento2DbConnection */
    protected static $db;

    /** @var  Metadata */
    protected static $metadata;

    public static function setUpBeforeClass()
    {
        // include Magento
        require_once __DIR__ . '/../../../../../index.php';

        /** @var ImporterFactory $factory */
        self::$factory = ObjectManager::getInstance()->get(ImporterFactory::class);

        /** @var Magento2DbConnection $db */
        self::$db = ObjectManager::getInstance()->get(Magento2DbConnection::class);

        self::$metadata = ObjectManager::getInstance()->get(MetaData::class);
    }

    /**
     * @throws \Exception
     */
    public function testUpdateById()
    {
        $errors = [];

        $config = new ImportConfig();
        $config->resultCallbacks[] = function (Product $product) use (&$errors) {
            $errors = array_merge($errors, $product->getErrors());
        };

        $importer = self::$factory->createImporter($config);

        // create an object
        $product1 = new SimpleProduct('identity-product-import');
        $product1->setAttributeSetByName("Default");

        $global = $product1->global();
        $global->setName("Identity is the Name");
        $global->setPrice('99.95');

        $importer->importSimpleProduct($product1);
        $importer->flush();

        $this->assertEquals([], $errors);

        // ---

        // create second object, same id, different sku
        $product2 = new SimpleProduct('new-identity-product-import');
        $product2->id = $product1->id;

        // change the name
        $product2->global()->setName("IDentity is the Name");

        $importer->importSimpleProduct($product2);
        $importer->flush();

        $this->assertEquals([], $errors);

        // ---

        // if we now import the object with the new sku, we get the original id
        $product3 = new SimpleProduct('new-identity-product-import');
        $importer->importSimpleProduct($product3);
        $importer->flush();

        $this->assertEquals([], $errors);

        $this->assertSame($product1->id, $product3->id);

        // ---

        // non-existing id should create error
        $product4 = new SimpleProduct('new-identity-product-import');
        $product4->id = -1;
        $importer->importSimpleProduct($product4);
        $importer->flush();

        $this->assertEquals(['Id does not belong to existing product: -1'], $errors);
    }
}