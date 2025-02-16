<?php

namespace SilverStripe\Subsites\Pages;

use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Model\VirtualPage;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Subsites\Forms\SubsitesTreeDropdownField;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\State\SubsiteState;
use SilverStripe\Model\ArrayData;

class SubsitesVirtualPage extends VirtualPage
{
    private static $table_name = 'SubsitesVirtualPage';

    private static $class_description = 'Displays the content of a page on another subsite';

    private static $non_virtual_fields = [
        'SubsiteID'
    ];

    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $subsites = DataObject::get(Subsite::class);
            if (!$subsites) {
                $subsites = ArrayList::create();
            } else {
                $subsites = ArrayList::create($subsites->toArray());
            }
            $subsites->push(ArrayData::create(['Title' => 'Main site', 'ID' => 0]));

            $fields->addFieldToTab(
                'Root.Main',
                DropdownField::create(
                    'CopyContentFromID_SubsiteID',
                    _t(__CLASS__ . '.SubsiteField', 'Subsite'),
                    $subsites->map('ID', 'Title')
                )->addExtraClass('subsitestreedropdownfield-chooser no-change-track'),
                'CopyContentFromID'
            );

            // Setup the linking to the original page.
            $pageSelectionField = SubsitesTreeDropdownField::create(
                'CopyContentFromID',
                _t('SilverStripe\\CMS\\Model\\VirtualPage.CHOOSE', 'Linked Page'),
                SiteTree::class,
                'ID',
                'MenuTitle'
            );
            $request = Controller::curr()?->getRequest();
            if ($request) {
                $subsiteID = (int) $request->requestVar('CopyContentFromID_SubsiteID');
                $pageSelectionField->setSubsiteID($subsiteID);
            }
            $fields->replaceField('CopyContentFromID', $pageSelectionField);

            // Create links back to the original object in the CMS
            if ($this->CopyContentFromID) {
                $editLink = Controller::join_links(
                    CMSPageEditController::singleton()->Link('show'),
                    $this->CopyContentFromID
                );

                $linkToContent = "
                    <a class=\"cmsEditlink\" href=\"$editLink\">" .
                    _t('SilverStripe\\CMS\\Model\\VirtualPage.EDITCONTENT', 'Click here to edit the content') .
                    '</a>';
                $fields->addFieldToTab(
                    'Root.Main',
                    LiteralField::create('VirtualPageContentLinkLabel', $linkToContent),
                    'Title'
                );
            }
        });
        return parent::getCMSFields();
    }

    public function getCopyContentFromID_SubsiteID()
    {
        if ($this->CopyContentFromID) {
            return (int) $this->CopyContentFrom()->SubsiteID;
        }
        return SubsiteState::singleton()->getSubsiteId();
    }

    public function getVirtualFields()
    {
        $fields = parent::getVirtualFields();
        foreach ($fields as $k => $v) {
            if ($v == 'SubsiteID') {
                unset($fields[$k]);
            }
        }
        return $fields;
    }

    public function syncLinkTracking()
    {
        $oldState = Subsite::$disable_subsite_filter;
        Subsite::$disable_subsite_filter = true;
        if ($this->CopyContentFromID) {
            $this->HasBrokenLink = DataObject::get_by_id(SiteTree::class, $this->CopyContentFromID) ? false : true;
        }
        Subsite::$disable_subsite_filter = $oldState;
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if ($this->CustomMetaTitle) {
            $this->MetaTitle = $this->CustomMetaTitle;
        } else {
            $this->MetaTitle = $this->ContentSource()->MetaTitle ?: $this->MetaTitle;
        }
        if ($this->CustomMetaKeywords) {
            $this->MetaKeywords = $this->CustomMetaKeywords;
        } else {
            $this->MetaKeywords = $this->ContentSource()->MetaKeywords ?: $this->MetaKeywords;
        }
        if ($this->CustomMetaDescription) {
            $this->MetaDescription = $this->CustomMetaDescription;
        } else {
            $this->MetaDescription = $this->ContentSource()->MetaDescription ?: $this->MetaDescription;
        }
        if ($this->CustomExtraMeta) {
            $this->ExtraMeta = $this->CustomExtraMeta;
        } else {
            $this->ExtraMeta = $this->ContentSource()->ExtraMeta ?: $this->ExtraMeta;
        }
    }

    public function validURLSegment()
    {
        $isValid = parent::validURLSegment();

        // Veto the validation rules if its false. In this case, some logic
        // needs to be duplicated from parent to find out the exact reason the validation failed.
        if (!$isValid) {
            $filters = [
                'URLSegment' => $this->URLSegment,
                'ID:not' => $this->ID,
            ];

            if (Config::inst()->get(SiteTree::class, 'nested_urls')) {
                $filters['ParentID'] = $this->ParentID ?: 0;
            }

            $origDisableSubsiteFilter = Subsite::$disable_subsite_filter;
            Subsite::disable_subsite_filter();
            $existingPage = SiteTree::get()->filter($filters)->first();
            Subsite::disable_subsite_filter($origDisableSubsiteFilter);
            $existingPageInSubsite = SiteTree::get()->filter($filters)->first();

            // If URL has been vetoed because of an existing page,
            // be more specific and allow same URLSegments in different subsites
            $isValid = !($existingPage && $existingPageInSubsite);
        }

        return $isValid;
    }
}
