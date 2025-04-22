<?php

namespace Kinglozzer\SilverStripeMetaTitle\Tests;

use Kinglozzer\SilverStripeMetaTitle\MetaTitleExtension;
use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\i18n\i18n;
use SilverStripe\i18n\Messages\MessageProvider;
use SilverStripe\i18n\Messages\Symfony\ModuleYamlLoader;
use SilverStripe\i18n\Messages\Symfony\SymfonyMessageProvider;
use SilverStripe\i18n\Messages\YamlReader;
use SilverStripe\ORM\DataObject;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class MetaTitleExtensionTest extends SapphireTest
{

    public static $extra_dataobjects = [
        MetaTitleExtensionTest_DataObject::class
    ];

    protected string $originalLocale = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalLocale = i18n::get_locale();
        i18n::set_locale('en');

        // Setup uncached translator
        // This should pull the module list from the above manifest
        $translator = new Translator('en');
        $translator->setFallbackLocales(['en']);
        $loader = new ModuleYamlLoader();
        $loader->setReader(new YamlReader());
        $translator->addLoader('ss', $loader); // Standard ss module loader
        $translator->addLoader('array', new ArrayLoader()); // Note: array loader isn't added by default
        $provider = new SymfonyMessageProvider();
        $provider->setTranslator($translator);
        Injector::inst()->registerService($provider, MessageProvider::class);
    }

    protected function tearDown(): void
    {
        i18n::set_locale($this->originalLocale);

        parent::tearDown();
    }

    public function testUpdateCMSFields(): void
    {
        // Add custom translation for testing
        $provider = Injector::inst()->get(MessageProvider::class);
        $provider->getTranslator()->addResource(
            'array',
            [ SiteTree::class.'.METATITLEHELP' => 'TRANS-EN Meta Title Help' ],
            'en'
        );

        $page = new Page();
        $fields = $page->getCMSFields();
        $metaTitleField = $fields->dataFieldByName('MetaTitle');
        $this->assertNotNull($metaTitleField);
        $this->assertEquals('TRANS-EN Meta Title Help', $metaTitleField->RightTitle());
    }

    public function testUpdateFieldLabels(): void
    {
        // Add custom translation for testing
        $provider = Injector::inst()->get(MessageProvider::class);
        $provider->getTranslator()->addResource(
            'array',
            [ SiteTree::class.'.METATITLE' => 'TRANS-EN Meta Title' ],
            'en'
        );

        $page = new Page();
        $this->assertEquals('TRANS-EN Meta Title', $page->fieldLabel('MetaTitle'));

        // Set different locale, clear field label cache
        i18n::set_locale('de_DE');
        DataObject::reset();

        // Add custom translation for testing
        $provider = Injector::inst()->get(MessageProvider::class);
        $provider->getTranslator()->addResource(
            'array',
            [ SiteTree::class.'.METATITLE' => 'TRANS-DE Meta Title' ],
            'de_DE'
        );
        $this->assertEquals('TRANS-DE Meta Title', $page->fieldLabel('MetaTitle'));
    }

    public function testDataIntegrity(): void
    {
        $page = new Page();
        $page->MetaTitle = 'Custom meta title';
        $page->write();

        $siteTreeTest = Page::get()->byID($page->ID);
        $this->assertEquals('Custom meta title', $siteTreeTest->MetaTitle);

        $obj = new MetaTitleExtensionTest_DataObject();
        $obj->MetaTitle = 'Custom DO meta title';
        $obj->write();

        $objTest = MetaTitleExtensionTest_DataObject::get()->byID($obj->ID);
        $this->assertEquals('Custom DO meta title', $objTest->MetaTitle);
    }

    public function testMetaTags(): void
    {
        $format = '$MetaTitle - Site Name';
        Config::modify()->set(MetaTitleExtension::class, 'title_format', $format);

        $page = new Page();
        $page->Title = 'Page title';

        $tags = $page->MetaTags();
        $result = preg_match("/<title>(.+)<\/title>/i", $tags, $matches);
        $this->assertEquals(1, $result, 'Meta title tag not found');
        $this->assertEquals('Page title - Site Name', $matches[1], 'Meta title incorrect');

        $page->MetaTitle = 'Page meta title';
        $tags = $page->MetaTags();
        $result = preg_match("/<title>(.+)<\/title>/i", $tags, $matches);
        $this->assertEquals(1, $result, 'Meta title tag not found');
        $this->assertEquals('Page meta title - Site Name', $matches[1], 'Meta title incorrect');
    }
}
