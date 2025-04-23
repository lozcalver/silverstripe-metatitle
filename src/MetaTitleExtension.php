<?php

namespace Kinglozzer\SilverStripeMetaTitle;

use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\CMS\Model\VirtualPage;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Core\Extension;
use SilverStripe\Model\ArrayData;
use SilverStripe\View\HTML;
use SilverStripe\View\TemplateEngine;
use SilverStripe\View\ViewLayerData;

/**
 * @property string $MetaTitle
 */
class MetaTitleExtension extends Extension
{
    private static array $db = [
        'MetaTitle' => 'Varchar(255)'
    ];

    private static string $title_format = '$MetaTitle &raquo; $SiteConfig.Title';

    public function updateCMSFields(FieldList $fields)
    {
        if ($this->owner instanceof RedirectorPage || $this->owner instanceof VirtualPage) {
            return;
        }

        $metaFieldTitle = TextField::create('MetaTitle', $this->owner->fieldLabel('MetaTitle'))
            ->setRightTitle(_t(
                'SilverStripe\\CMS\\Model\\SiteTree.METATITLEHELP',
                'Shown at the top of the browser window and used as the "linked text" by search engines.'
            ))
            ->addExtraClass('help');

        $fields->insertBefore('MetaDescription', $metaFieldTitle);
    }

    /**
     * @param array &$labels
     */
    public function updateFieldLabels(&$labels)
    {
        $labels['MetaTitle'] = _t('SilverStripe\\CMS\\Model\\SiteTree.METATITLE', 'Title');
    }

    /**
     * Replace the <title> tag (if present) with the format provided in the title_format
     * config setting. Will fall back to 'Title' if 'MetaTitle' is empty
     *
     * @param string &$tags
     */
    public function updateMetaTags(&$tags): void
    {
        // Only attempt to replace <title> tag if it has been included, as it won't
        // be included if called via $MetaTags(false)
        if (preg_match("/<title>(.+)<\/title>/i", $tags)) {
            $format = Config::inst()->get(static::class, 'title_format');

            $data = ArrayData::create([
                'MetaTitle' => $this->owner->MetaTitle ? $this->owner->obj('MetaTitle') : $this->owner->obj('Title')
            ]);

            $templateEngine = Injector::inst()->create(TemplateEngine::class, $format);
            $newTitleTag = HTML::createTag('title', [], $templateEngine->renderString($format, ViewLayerData::create($data)));

            $tags = preg_replace("/<title>(.+)<\/title>/i", $newTitleTag, $tags);
        }
    }
}
