<?php
class SubsitesVirtualPage extends VirtualPage
{
    private static $description = 'Displays the content of a page on another subsite';

    private static $db = array(
        'CustomMetaTitle' => 'Varchar(255)',
        'CustomMetaKeywords' => 'Varchar(255)',
        'CustomMetaDescription' => 'Text',
        'CustomExtraMeta' => 'HTMLText'
    );
    
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        
        $subsites = DataObject::get('Subsite');
        if (!$subsites) {
            $subsites = new ArrayList();
        } else {
            $subsites=ArrayList::create($subsites->toArray());
        }
        
        $subsites->push(new ArrayData(array('Title' => 'Main site', 'ID' => 0)));

        $fields->addFieldToTab(
            'Root.Main',
            DropdownField::create(
                "CopyContentFromID_SubsiteID",
                _t('SubsitesVirtualPage.SubsiteField', "Subsite"),
                $subsites->map('ID', 'Title')
            )->addExtraClass('subsitestreedropdownfield-chooser no-change-track'),
            'CopyContentFromID'
        );
        
        // Setup the linking to the original page.
        $pageSelectionField = new SubsitesTreeDropdownField(
            "CopyContentFromID",
            _t('VirtualPage.CHOOSE', "Choose a page to link to"),
            "SiteTree",
            "ID",
            "MenuTitle"
        );
        
        if (Controller::has_curr() && Controller::curr()->getRequest()) {
            $subsiteID = Controller::curr()->getRequest()->requestVar('CopyContentFromID_SubsiteID');
            $pageSelectionField->setSubsiteID($subsiteID);
        }
        $fields->replaceField('CopyContentFromID', $pageSelectionField);
        
        // Create links back to the original object in the CMS
        if ($this->CopyContentFromID) {
            $editLink = "admin/pages/edit/show/$this->CopyContentFromID/?SubsiteID=" . $this->CopyContentFrom()->SubsiteID;
            $linkToContent = "
				<a class=\"cmsEditlink\" href=\"$editLink\">" .
                _t('VirtualPage.EDITCONTENT', 'Click here to edit the content') .
                "</a>";
            $fields->removeByName("VirtualPageContentLinkLabel");
            $fields->addFieldToTab(
                "Root.Main",
                $linkToContentLabelField = new LabelField('VirtualPageContentLinkLabel', $linkToContent),
                'Title'
            );
            $linkToContentLabelField->setAllowHTML(true);
        }
        
        
        $fields->addFieldToTab(
            'Root.Main',
            TextField::create(
                'CustomMetaTitle',
                $this->fieldLabel('CustomMetaTitle')
            )->setDescription(_t('SubsitesVirtualPage.OverrideNote', 'Overrides inherited value from the source')),
            'MetaTitle'
        );
        $fields->addFieldToTab(
            'Root.Main',
            TextareaField::create(
                'CustomMetaKeywords',
                $this->fieldLabel('CustomMetaTitle')
            )->setDescription(_t('SubsitesVirtualPage.OverrideNote')),
            'MetaKeywords'
        );
        $fields->addFieldToTab(
            'Root.Main',
            TextareaField::create(
                'CustomMetaDescription',
                $this->fieldLabel('CustomMetaTitle')
            )->setDescription(_t('SubsitesVirtualPage.OverrideNote')),
            'MetaDescription'
        );
        $fields->addFieldToTab(
            'Root.Main',
            TextField::create(
                'CustomExtraMeta',
                $this->fieldLabel('CustomMetaTitle')
            )->setDescription(_t('SubsitesVirtualPage.OverrideNote')),
            'ExtraMeta'
        );
        
        return $fields;
    }

    public function fieldLabels($includerelations = true)
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['CustomMetaTitle'] = _t('Subsite.CustomMetaTitle', 'Title');
        $labels['CustomMetaKeywords'] = _t('Subsite.CustomMetaKeywords', 'Keywords');
        $labels['CustomMetaDescription'] = _t('Subsite.CustomMetaDescription', 'Description');
        $labels['CustomExtraMeta'] = _t('Subsite.CustomExtraMeta', 'Custom Meta Tags');

        return $labels;
    }

    public function getCopyContentFromID_SubsiteID()
    {
        return ($this->CopyContentFromID) ? (int)$this->CopyContentFrom()->SubsiteID : (int)Session::get('SubsiteID');
    }
    
    public function getVirtualFields()
    {
        $fields = parent::getVirtualFields();
        foreach ($fields as $k => $v) {
            if ($v == 'SubsiteID') {
                unset($fields[$k]);
            }
        }
        
        foreach (self::$db as $field => $type) {
            if (in_array($field, $fields)) {
                unset($fields[array_search($field, $fields)]);
            }
        }

        return $fields;
    }
    
    public function syncLinkTracking()
    {
        $oldState = Subsite::$disable_subsite_filter;
        Subsite::$disable_subsite_filter = true;
        if ($this->CopyContentFromID) {
            $this->HasBrokenLink = DataObject::get_by_id('SiteTree', $this->CopyContentFromID) ? false : true;
        }
        Subsite::$disable_subsite_filter = $oldState;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
    
        if ($this->CustomMetaTitle) {
            $this->MetaTitle = $this->CustomMetaTitle;
        } else {
            $this->MetaTitle = $this->ContentSource()->MetaTitle ? $this->ContentSource()->MetaTitle : $this->MetaTitle;
        }
        if ($this->CustomMetaKeywords) {
            $this->MetaKeywords = $this->CustomMetaKeywords;
        } else {
            $this->MetaKeywords = $this->ContentSource()->MetaKeywords ? $this->ContentSource()->MetaKeywords : $this->MetaKeywords;
        }
        if ($this->CustomMetaDescription) {
            $this->MetaDescription = $this->CustomMetaDescription;
        } else {
            $this->MetaDescription = $this->ContentSource()->MetaDescription ? $this->ContentSource()->MetaDescription : $this->MetaDescription;
        }
        if ($this->CustomExtraMeta) {
            $this->ExtraMeta = $this->CustomExtraMeta;
        } else {
            $this->ExtraMeta = $this->ContentSource()->ExtraMeta ? $this->ContentSource()->ExtraMeta : $this->ExtraMeta;
        }
    }
    
    public function validURLSegment()
    {
        $isValid = parent::validURLSegment();
        
        // Veto the validation rules if its false. In this case, some logic
        // needs to be duplicated from parent to find out the exact reason the validation failed.
        if (!$isValid) {
            $IDFilter = ($this->ID) ? "AND \"SiteTree\".\"ID\" <> $this->ID" :  null;
            $parentFilter = null;

            if (Config::inst()->get('SiteTree', 'nested_urls')) {
                if ($this->ParentID) {
                    $parentFilter = " AND \"SiteTree\".\"ParentID\" = $this->ParentID";
                } else {
                    $parentFilter = ' AND "SiteTree"."ParentID" = 0';
                }
            }
            
            $origDisableSubsiteFilter = Subsite::$disable_subsite_filter;
            Subsite::$disable_subsite_filter = true;
            $existingPage = DataObject::get_one(
                'SiteTree',
                "\"URLSegment\" = '$this->URLSegment' $IDFilter $parentFilter",
                false // disable cache, it doesn't include subsite status in the key
            );
            Subsite::$disable_subsite_filter = $origDisableSubsiteFilter;
            $existingPageInSubsite = DataObject::get_one(
                'SiteTree',
                "\"URLSegment\" = '$this->URLSegment' $IDFilter $parentFilter",
                false // disable cache, it doesn't include subsite status in the key
            );

            // If URL has been vetoed because of an existing page,
            // be more specific and allow same URLSegments in different subsites
            $isValid = !($existingPage && $existingPageInSubsite);
        }
        
        return $isValid;
    }
}

class SubsitesVirtualPage_Controller extends VirtualPage_Controller
{
    public function reloadContent()
    {
        $this->failover->copyFrom($this->failover->CopyContentFrom());
        $this->failover->write();
        return;
    }
    
    public function init()
    {
        $origDisableSubsiteFilter = Subsite::$disable_subsite_filter;
        Subsite::$disable_subsite_filter = true;
        
        parent::init();
        
        Subsite::$disable_subsite_filter = $origDisableSubsiteFilter;
    }
    
    public function getViewer($action) {
        $originalClass = get_class($this->CopyContentFrom());
        if ($originalClass == 'SiteTree') $name = 'Page_Controller';
        else $name = $originalClass."_Controller";
        $controller = new $name($this->CopyContentFrom());
        return $controller->getViewer($action);
    }
}
