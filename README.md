# BigBridge Product Import

Imports product data into Magento 2 via direct database access.

Hi! My name is Patrick van Bergen. I created this library because I needed product import to be fast.

## Warning!

This is a new library. The chance you find bugs is real. Use it only for new projects and make sure to create a database backup before you start to experiment with it. I hope to change this status soon, but this is how it is. Let me know if you run into trouble, and I will try to help you out.

## Important

Use this library if you need speed and don't care about any plugins or custom event handlers that are normally activated when products change in Magento.

This library just helps you to get products into Magento's database quickly, low level.

## Features

* insert, update and delete products
* product identification based on product sku or id
* all product types (simple, configurable, grouped, bundle, downloadable, and virtual)
* automatic category generation
* automatic select and multiselect attribute option creation
* import of images from file or url
* image caching (local and HTTP cache)
* custom options
* unique url_key generation
* whitespace trimming (spaces, tabs, newlines) from all fields, except free field texts
* attribute deletion
* input validation (data type, requiredness,  and length restrictions)
* product type changes
* a solution for the problem that linked products have not been imported yet
* dry run (no products are written to the database)

## Indexing

It is important to think about indexing when using this library.

Magento supports "Update on Save" and "Update by Schedule". The relevant indexes are Category Products, product Categories, Product Price, Product EAV, and Stock.

If these indexes are set to "Update by Schedule", a Magento cron job based indexer will update the indexes. This mode works well with this library.

If these indexes are set to "Update on Save", you will need to run the indexers manually after the import is done (bin/magento indexer:reindex). This is less advisable.

## Example Code

The following example shows you a simple case of importing a simple product

    // load the import factory (preferably via DI)
    $factory = ObjectManager::getInstance()->get(ImporterFactory::class);

    // your own log functionality
    $log = "";

    $config = new ImportConfig();

    // a callback function to postprocess imported products
    $config->resultCallback[] = function(Product $product) use (&$log) {

        if ($product->isOk()) {
            $log .= sprintf("%s: success! sku = %s, id = %s\n", $product->lineNumber, $product->getSku(), $product->id);
        } else {
            $log .= sprintf("%s: failed! error = %s\n", $product->lineNumber, implode('; ', $product->getErrors()));
        }
    };

    $lines = [
        ['Purple Box', "purple-box", "3.95", "Lila Box", "3.85"],
        ['Yellow Box', "yellow-box", "2.95", "Gelbe Box", "2.85"]
    ];

    try {

        $importer = $factory->createImporter($config);

        foreach ($lines as $i => $line) {

            $product = new SimpleProduct($line[1]);
            $product->lineNumber = $i + 1;

            // global eav attributes
            $global = $product->global();
            $global->setName($line[0]);
            $global->setPrice($line[2]);

            // German eav attributes
            $german = $product->storeView('de_store');
            $german->setName($line[3]);
            $german->setPrice($line[4]);

            $importer->importSimpleProduct($product);
        }

        // process any remaining products in the pipeline
        $importer->flush();

    } catch (\Exception $e) {
        $log .= $e->getMessage();
    }

The following code pieces are extensions on this basic code.

## Required attributes

Required attributes for a new product are:

* sku (which is a required parameter of the Product constructor)
* name (global)
* price (global; it is not required for bundled and grouped products)

## Important attributes

If you want to make sure the product appears on the store front, specify at least the following attributes.

These attributes are given with example values. You have to change them.

    $product->setAttributeSetByName("Default");
    $product->setCategoriesByGlobalName(['Default Category/Desks', 'Default Category/Chairs', 'Default Category/Boards']);
    $product->setWebsitesByCode(['base']);

    $global = $product->global();
    $global->setName("My product");
    $global->setPrice("9.95");
    $global->setVisibility(ProductStoreView::VISIBILITY_BOTH);
    $global->setStatus(ProductStoreView::STATUS_DISABLED);
    $global->setTaxClassName("Taxable Goods");
    $global->generateUrlKey();

    $stockItem = $product->defaultStockItem();
    $stockItem->setQty('100');
    $stockItem->setIsInStock(true);

## Standard attributes

Here are the possible standard attribute functions, with examples for the global level. They can be applied both at the global and the store view level.

    $global->setName('Colorful cube');
    $global->setStatus(ProductStoreView::STATUS_ENABLED);
    $global->setGiftMessageAvailable(ProductStoreView::AVAILABLE);
    $global->setDescription('A mathematical curiosity that will twist your mind');
    $global->setShortDescription('A twistable colorful cube');
    $global->setMetaTitle('Six sides, twelve edges');
    $global->setMetaDescription('Can you find the solution on this magical device');
    $global->setMetaKeywords('Cube, colors, mathematics');
    $global->setPrice('6.95');
    $global->setCost('3.01');
    $global->setMsrp('8.95');
    $global->setMsrpDisplayActualPriceType(ProductStoreView::MSRP_USE_CONFIG);
    $global->setVisibility(ProductStoreView::VISIBILITY_BOTH);
    $global->setUrlKey('ruby-colored-cube');
    $global->setTaxClassName('Taxable Goods');
    $global->setWeight('0.105');
    $global->setSpecialPrice('5.95');
    $global->setSpecialFromDate('2018-01-01');
    $global->setSpecialToDate('2018-03-01');
    $global->setNewsFromDate('2018-01-01');
    $global->setNewsToDate('2018-01-15');
    $global->setManufacturer('Ruby');
    $global->setCountryOfManufacture('Hungaria');
    $global->setColor('Multicolor');

An example of the name attribute on the Danish store view 'store_dk':

    $product->storeView('store_dk')->setName('Farverige terning');

## Naming

It will help you use this library by understanding how I use certain words:

* by "id" I mean the primary key of a field (an integer)
* by "code" I mean short non-translatable identifier (a small string)
* by "set", as in "setSpecialPrice", I mean "replace"
* by "add", as in "addCategories" I mean "insert or update, but not remove"

When Magento 2 uses "name" where I would use "code", I follow Magento. "attribute_set_name" For instance, would qualify as a code in my view, but I call it "name" to be consistent with Magento.

## Empty values and removing attributes

Any simple (scalar) attribute may be removed from the database (on a global level, or per store view) by setting it to null. For example:

    $global->setMsrp(null);

Attributes with the empty string value ("") are ignored by default. They are not imported.

If that to remove the attribute value, you have two options:

For textual attributes (datatype varchar and text):

    $config->emptyTextValueStrategy = ImportConfig::EMPTY_TEXTUAL_VALUE_STRATEGY_REMOVE;

For non-textual attributes (datetime, decimal and integer):

    $config->emptyNonTextValueStrategy = ImportConfig::EMPTY_NONTEXTUAL_VALUE_STRATEGY_REMOVE;

## Trimming values

All input is trimmed directly, because database corruption based on extra whitespace is common.
Leading and trailing whitespace is removed from all values.

An exception is made for attributes with textarea as input (description, short_description, meta_keywords, meta_description) and custom attributes. They are not trimmed.

## Errors

The library detects problems in the input in its id-resolution and validation phrases. When it does, it adds descriptive error messages to the product this is processed.

A product that one or more errors is not imported. Errors can be inspected via a custom callback function you can provide.

    $config->resultCallback[] = function(Product $product)) {
        $errors = $product->getErrors();
    }

Callbacks are called in the order that the products were added. However, configurables are collected in a different set from simples, and will be processed at a later time.

## Global scope and store view scope

Many attributes (eav attributes) can be specified both on a global level and on a store view level.

An attribute that is specified globally will be used on all store view levels, unless it is overridden by a store view value.

The library makes this distinction explicit with these constructs:

    $product->global()->setName();
    $product->storeView('store_fr')->setName();

Where storeView accepts a store view code.

In the code below I will give examples using global() and storeView(). When I do, remember that both levels are available to you at all times.

Attributes with site-scope will need to be imported for each store individually (the way it is stored in the database).

## EAV attributes

You can set any attribute by calling a setter, like this

    $product->global()->setWeight('1.21');

Setters for all other attributes are available.

Set a custom attribute like this

    $product->storeView('nl')->setCustomAttribute('door_count', '3');

## Select and multiple select attributes

Values of Select attributes can best be entered with the admin name of the select option

    $product->global()->setSelectAttribute('color', 'maroon');

Values of Multiple Select attributes are entered as an array of admin names of options

    $product->storeView('us')->setMultipleSelectAttribute('color_group', ['red', 'orange']);

Entering values by option id is possible as well.

    $product->global()->setSelectAttributeOptionId('color', 2);
    $product->global()->setMultiSelectAttributeOptionIds('color_group', [2, 3]);

## Automatic attribute option creation

The library will create options for attributes if they do not exist, but only for attributes listed in the config array:

    $config->autoCreateAttributeOptions(['color_group', 'length']);

## Stock items

Inventory information (stock) is kept in a separate table. Currently Magento supports only a single (Default) stock.

Stock information can be entered this way:

    $product->defaultStockItem()->setQty('100');
    $product->defaultStockItem()->setIsInStock(true);

The other 20 stock info attributes are available as well.

## Related, Up Sell, and Cross Sell Products

These so called "linked" products are stored as references to other products. When entering them, specify a product with an sku.

    $product1->setRelatedProducts([
        "microsoft-natural-keyboard",
        "hp-classic-keyboard"
    ]);

    $product1->setUpSellProducts([
       "hp-supersonic",
       "microsoft-keyless-keyboard"
    ]);

    $product->setCrossSellProducts([
        "logitech-wired-mouse",
        "some-batteries"
    ]);

The order (position) of the linked products stored in the database is that specified in the array.

Linked products may have a dependency conflict. One product link to another product that has not been imported yet. And yet the id of the other product is necessary to store the links in the database.

Two products may even linked to each other. This is common for related products. When attempting to create the first product, it needs the id of the other product for a link (e.g. being related). But the same problem exists when we start with the second problem. It's a deadlock.

In order to get out of this situation, this library creates temporary "placeholder" products for linked products that do not yet exist. These products are stored in Magento as disabled simple products with the name "Product Placeholder", and with a price of 123456.78.

While other solutions are thinkable, this solution has the following advantages:

* it is simple to implement and easy to understand
* products and their links can be imported in a single run
* the linked products do not have to be available in the current job. A later job may import them.

The user of the library must make sure the placeholder products will be imported at a later time. Placeholder Products that were not used can be removed via the backend product overview page by searching for the name "Product Placeholder".

## Configurables

Configurable products are defined as the configuration of configuration attributes and variants

    $configurable = new ConfigurableProduct('scottish-table');
    $configurable->setSuperAttributeCodes(['color', 'item_weight']);
    $configurable->setVariantSkus([
        "scottish-table-red-2st",
        "scottish-table-brown-2st",
        "scottish-table-brown-3st",
    ]);

Here the configurable with sku 'scottish-table' defines two "super attributes": color and weight. The attributes must have global scope and input type Dropdown.

The three variants each need to have a unique combination of attribute values for these super attributes.

Importing is done with

    $importer->importConfigurableProduct($configurable);

## Grouped products

Grouped products are defined as an array of group members. Each member has an sku and a default quantity. The order of the members in the array is used for the position.

    $group = new GroupedProduct("bucky-cutlery")
    $group->setMembers([
        new GroupedProductMember("bucky-knife", 5),
        new GroupedProductMember("bucky-fork", 5),
        new GroupedProductMember("bucky-spoon", 5),
    ]);

    $importer->importGroupedProduct($group);

The member products need not have been imported before. If an sku does not belong to a known product, a temporary placeholder is created. See Related Products.

If a group is imported with no members, any members it might have had will be removed.

## Bundle products

Add a bundle product

    $bundle = new BundleProduct("ibm-pc");

Add at least these attributes (global and store view specific)

    $global = $bundle->global();
    $global->setPriceType(BundleProductStoreView::PRICE_TYPE_DYNAMIC);
    $global->setSkuType(BundleProductStoreView::SKU_TYPE_DYNAMIC);
    $global->setWeightType(BundleProductStoreView::WEIGHT_TYPE_DYNAMIC);
    $global->setPriceView(BundleProductStoreView::PRICE_VIEW_PRICE_RANGE);
    $global->setShipmentType(BundleProductStoreView::SHIPMENT_TYPE_TOGETHER);

The values used here are also the defaults that will be used if no values are set for these attributes.

Add some options with

    $bundle->setOptions([
        $option = new BundleProductOption(BundleProduct::INPUT_TYPE_DROP_DOWN, true)
    ]);

Note that an option object is returned. Use this object to add products to the option:

    $option->setProductSelections([
        new BundleProductSelection('monitor-import-product', true, BundleProductOption::PRICE_TYPE_FIXED, '300.00', '1', false)
    ]);

The object is also used to specify the title of the option, globally and per store view:

    $bundle->global->setOptionTitle($option, 'Monitor');
    $bundle->storeView('dk')->setOptionTitle($option, 'Overvåge');

Finally, import the product

    $importer->importBundleProduct($bundle);

## Downloadable products

A downloadable product has some download links and samples. The titles and prices of the links can a different value per store view.

Create a downloadable product

    $downloadable = new DownloadableProduct("morlord-the-game");

Set at least these product attributes:

    $downloadable->global()->setLinksPurchasedSeparately(true);
    $downloadable->global()->setLinksTitle("Links");
    $downloadable->global()->setSamplesTitle("Samples");

Add a link (or several links). Add a url or a file, specify the number of downloads (0 = unlimited), and if the link may be shared. Add an optional sample url or file. Save the resulting object in a variable.

If a file or url starts with "http://", "https://" or "//:" (case insensitive) it is considered a url. This type is stored in the database.

    $downloadable->setDownloadLinks([
        $link1 = new DownloadLink('http://download-resources.net/morlord-setup.exe', 0, true, "morlord sample.jpg")
    ]);

Create a global title and price for the link. Use the link object just created.

    $downloadable->global()->setDownloadLinkInformation($link1, "Morlord The Game", "12.95");

Add a title and price per store view

    $downloadable->storeView('store_de')->setDownloadLinkInformation($link1, "Morlord Das Spiel", "12.45");
    $downloadable->storeView('store_nl')->setDownloadLinkInformation($link1, "Morlord Het Spel", "13.45");

Note: Download link prices are stored per website, not per store view. The prices of all store views of a website should be the same.

The "sort order" of the links is determined by the order in which you add the links in code.

Create a sample with a file or a url:

    $downloadable->setDownloadSamples([
        $sample1 =  new DownloadSample("morlord sample 2.jpg")
    ]);

Add a global title for the sample

    $downloadable->global()->setDownloadSampleTitle($sample1, "Morlord The Game - Example");

Add a title per store view

    $downloadable->storeView('store_de')->setDownloadSampleTitle($sample1, "Morlord Das Spiel - Beispiel");

The "sort order" of the samples is determined by the order in which you add the samples in code.

Import the downloadable product

    $importer->importDownloadableProduct($downloadable);

## Virtual products

A virtual product is exactly like a simple product. The only difference is the type, and the fact that it should not have a weight attribute.

    $product = new VirtualProduct("single-consult");

    $importer->importVirtualProduct($product);

## Changing product type

The type of a product may be changed. When a product was imported as a SimpleProduct before, it may be stored later as a ConfigurableProduct, for instance.

When the old type contained data structures that the new type no longer needs, these will be deleted (this is called "destructive").

By default, only non-destructive changes are allowed. Virtual to Downloadable is fine. Configurable to Downloadable is not. The parent-child links would be lost.

You can allow both non-destructive and destructive type changes with

    $config->ImportConfig::PRODUCT_TYPE_CHANGE_ALLOWED;

Forbid any kind of type change with

    $config->ImportConfig::PRODUCT_TYPE_CHANGE_FORBIDDEN;

## Categories

Categories are imported by paths of category-names, like this "Doors/Wooden Doors/Specials". Separate category names with "/".

    $product->addCategoriesByGlobalName(['Chairs', 'Tables', 'Chairs/Chaises Longues', 'Carpets/Persian Rugs']);

When the category does not exist, it is created. The name is added to the global scope. If you don't want auto-creation, and rather just see an error, use

    $config->autoCreateCategories = false;

You can also use ids

    $product->addCategoryIds([123, 125]);

The importer does not test whether the the ids exist and will throw an database exception if they don't.

When your import set contains categories with a / in the name, like "Summer / Winter collection", you may want to change the category name separator into something else, like "$"
Make sure to update the imported category paths when you do.

    $config->categoryNamePathSeparator = "$";

Note: the library only adds and updates categories. It does not remove categories that are not mentioned in the set call.

## Websites

You can specify on which websites a product is used, by specifying their codes

    $product->setWebsitesByCode(['clothes', 'bicycles']);

or their ids

    $product->setWebsiteIds([1, 3, 4]);

## Images

To import images, use this syntax

    $image = $product1->addImage('/path/to/peanut_butter.png');

You can use a url:

    $image = $product1->addImage('http://sandwiches4you.com/path/to/peanut_butter.png');

It is also possible to use local files (these will be hard linked to their destination) and network files (these will be copied).

This will attach the image to the product and it will show up in the backend section "Images and Videos" of the product.

How the library deals with existing images:

* if Magento already contained an image with this name, for another product, the image will get a serial number suffix (i.e _1)
* if Magento already contained an image with this name, for the same product, and it is the same image, nothing happens
* if Magento already contained an image with this name, for the same product, and it is a different image, it is overwritten

If you want to add one or more roles (image, small_image, thumbnail, swatch_image) to it, use this:

    $product1->global()->setImageRole($image, ProductStoreView::BASE_IMAGE);

It is also possible to use the attribute code of a custom media image attribute.

If necessary, you can even change this role per store view

    $product->storeView('store_de')->setImageRole($image, ProductStoreView::SMALL_IMAGE);

If you want to add a label, specify the gallery position, and show/hide it on the product page, use this:

    $product->global()->setImageGalleryInformation($image, "Large jar of peanut butter", 2, true);

Again, this can be store on the store view level:

    $product->storeView('store_nl')->setImageGalleryInformation($image, "Grote pot pindakaas", 2, true);

Note: the library does not remove existing images that are not mentioned by any of your addImage calls.

### Image caching

Downloading images can be a slow process. That's why the library offers different strategies of dealing with images.

The default strategy is to download all images.

Before storing the product, each image is stored in a temporary location (pub/media/import) under a name that is the md5 hash of the source path, concatenated with the base name. For example

    9c4b8815ec803006ec1fd691501ae3a4-duck1.jpg

If the contents of your images never changes (i.e. different contents, but same name), you can use the strongest form of caching:

    $config->existingImageStrategy = ImportConfig::EXISTING_IMAGE_STRATEGY_CHECK_IMPORT_DIR;

It checks if the file is in the temporary location, and if so, uses this one. The source location of the image is only checked if the image is not cached.

If you want to cache images just with HTTP cache (like a browser), choose:

    $config->existingImageStrategy = ImportConfig::EXISTING_IMAGE_STRATEGY_HTTP_CACHING;

The headers of the cached file are stored along with the image as a JSON file in the pub/media/import directory.

## Tier prices

Import all tier prices of a product with

    $product->setTierPrices([
        new TierPrice(10, '12.25', 'General', 'base'),
        new TierPrice(20, '12.10'),
    ]);

The first tier price in this example contains a minimum quantity, a price, the name (code) of the customer group, and the code of a website.

The second tier price does not contain a customer group and no website code. This signifies that all customer groups and all websites are affected by this tier price.

## URL keys

The url_key of a product is used by Magento to create the url of the product page. The url_key is not added to a product automatically by the library. You must do so explicitly with

    $product->global()->setUrlKey('synthetisch-kinderdekbed-4-seizoenen');

It is common practise to generate url_keys based on the name of the product. You can do this with

    $product->storeView('sweden')->generateUrlKey();

If you want to use the "sku" field as the basis for the url_key, in stead of "name", use

    $config->urlKeyScheme = ImportConfig::URL_KEY_SCHEME_FROM_SKU;

A url_key needs to be unique within a store view or within the global level. If it is not, an error is added to the product.

The library has two ways to deal with this problem. You can tell it to add a serial number to the new url_key in case the url_key was already in use by another product.

    $config->duplicateUrlKeyStrategy = ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SERIAL;

The url of the new product with the same name as an existing product will then look like this

    https://myshop.com/synthetisch-kinderdekbed-4-seizoenen-1.html

or you can add the sku (transformed to url)

    $config->duplicateUrlKeyStrategy = ImportConfig::DUPLICATE_KEY_STRATEGY_ADD_SKU;

The url will then look something like this

    https://myshop.com/synthetisch-kinderdekbed-4-seizoenen-kdb-18004.html

## Custom options

Magento allows you to specify unique "attributes" to a product that are applicable to that single product alone. These are called custom options.

Some custom options have multiple values (dropdown, radio buttons, check box group, multiple select), others are simple.

The sku of a product with custom options is formed by concatenating the product sku with the sku's of the custom options.
For example: the sku of a product "oak-door" that has two custom options: Delivery date (a date, with option sku "date") and "size" (a multiple select, with option sku's "large", "medium") will be formed as "oak-door-date-medium" in the customers shopping cart.

Note 1: Magento has a long standing bug that does not allow you to specify title and price per store view, at least not via the backend. See [https://github.com/magento/magento2/issues/6165] This means that only global() is supported for the moment, not storeView().

I will treat the simple and multiple value custom options separately.

### Simple

Create the options. Here are are examples with all possible simple types:

    $option1 = CustomOption::createCustomOptionTextField("inscription", true, 40);
    $option2 = CustomOption::createCustomOptionTextArea("note", true, 250);
    $option3 = CustomOption::createCustomOptionFile("id-card", true, ".jpg .jpeg", 5000, 7000);
    $option4 = CustomOption::createCustomOptionDate("date", true);
    $option5 = CustomOption::createCustomOptionDateTime("datetime", true);
    $option6 = CustomOption::createCustomOptionTime("time", true);

    $product->setCustomOptions([$option1, $option2, $option3, $option4, $option5, $option6]);

Note: setCustomOptions replaces any existing custom options with these new ones.

Set the titles (I will just give one example)

    $product->global()->setCustomOptionTitle($option1, "Inscription");

Set the prices and the prices type (fixed or a percentage)

    $product->global()->setCustomOptionPrice($option1, "0.50", Product::PRICE_TYPE_FIXED);

### Multiple valued

Create the option. Here are are examples with all possible multiple value types:

    $option1 = CustomOption::createCustomOptionDropDown(true, ["red", "green"]);
    $option2 = CustomOption::createCustomOptionRadioButtons(true, ["red", "green"]);
    $option3 = CustomOption::createCustomOptionCheckboxGroup(true, ["red", "green"]);
    $option4 = CustomOption::createCustomOptionMultipleSelect(true, ["red", "green"]);

    $product->setCustomOptions([$option1, $option2, $option3, $option4]);

Set the title

    $product->global()->setCustomOptionTitle($option1, "Color");

Set the the option sku, the price, the price type, and the title per value like this:

    $product->global()->setCustomOptionValue($option1, "red", "1.00", Product::PRICE_TYPE_FIXED, 'Red');
    $product->global()->setCustomOptionValue($option1, "green", "1.20", Product::PRICE_TYPE_FIXED, 'Green');

Note that each of the value's sku's (here: red, green) must have a custom option value.

## Import by ID

While it is not required to specify the id of a product to import it, it may sometimes be necessary.
Particularly, if the sku of a product may change during the import, it is necessary to specify the id.

Import by ID happens the same as import by sku, except that the ID is specified:

    $product = new SimpleProduct('new-identity-product-import');
    $product->id = 32711;

When the id is specified, it is treated as the identifier of an object. The sku will be updated to match the one given in the import.

Import by id always concerns updates, not inserts. When a non-existing id is used, an error is added to the product.

## Delete products

While the library's main purpose is to import products, it can delete products as well.

Either specify an array of product ids:

    $importer->deleteProductsByIds($ids);

or products skus:

    $importer->deleteProductsBySkus($skus);

## Dry run

If you want to see what errors an import produces without actually adding products to the database, set the config to "dry run"

    $config->dryRun = true;

Note that dry run does not imply that no changes are made to the database in a dry run. Categories may be added and attribute options may be created.

## Changes to Magento

The extension adds an index CATALOG_PRODUCT_ENTITY_VARCHAR_ATTRIBUTE_ID_VALUE to catalog_product_entity_varchar because it drastically speeds up checking for duplicate url_keys.

## Assumptions

* For Magento 2.1+ Opensource Edition
* Requires >= PHP 7.0
* Input in UTF-8 (Magento standard)
* MySQL max_packet_size on both MySQL client and MySQL server must be at least 1 MB (Which will be the case if the value wasn't deliberately lowered from the default)

## Thanks to

Thanks to Marco de Vries for telling me about the intricacies of product import.

This project owes a great deal of ideas and inspiration from Magmi / Magento 1 [Magmi](https://github.com/dweeves/magmi-git)
