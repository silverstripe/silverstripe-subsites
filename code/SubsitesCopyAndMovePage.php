<?php

/**
 * Containerpage, to move or copy branches of other subsite trees
 * to the current subsite
 */
class SubsitesCopyAndMovePage extends SiteTree {
	static $singular_name = 'Subsite Content Copy/Move Page';
	static $plural_name = 'Subsite Content Copy/Move Pages';

        static $icon = "cms/images/treeicons/folder";

	//static $allowed_children = "none";
            
        static $db = array(
            'CopyOrMove' => "Enum('1, 2')"
	);

        static $has_one = array(
		"CopyMoveContentFrom" => "SiteTree",
	);

        static protected $publish_check = true;

	static $defaults = array(
            "ShowInMenus" => 0,
            "ShowInSearch" => 0,
            "ProvideComments" => 0,
            "Status" => "New Copy Folder",
            "CanViewType" => 0,
            "CanEditType" => 0
        );

        /**
         * Page can only be installed once per subsite
         */
        public function canCreate() {

            if(Permission::check('ADMIN')) return true;

            return !DataObject::get_one(get_class($this), 'SubsiteID = '.(int)Subsite::currentSubsiteID());
        }

        /**
         * can be deleted by ADMIN
         */
        public function canDelete() {
            // Admin can always delete
            if(Permission::check('ADMIN')) return true;

            return false;
        }
	
	function getCMSFields() {
		$fields = parent::getCMSFields();

                // remove unwanted/unneeded tabs
                $fields->removefieldFromTab('Root.Content.Main','Content');
		$fields->removefieldFromTab('Root.Content', 'Metadata');
		$fields->removefieldFromTab('Root.Content.Main','Title');
		$fields->removefieldFromTab('Root.Content.Main','MenuTitle');
		$fields->removefieldFromTab('Root','Behaviour');


		
		$subsites = DataObject::get('Subsite');
		if(!$subsites) $subsites = new DataObjectSet();
		$subsites->push(new ArrayData(array('Title' => 'Main site', 'ID' => 0)));

		$subsiteSelectionField = new DropdownField(
			"CopyMoveContentFromID_SubsiteID",
			"Subsite", 
			$subsites->toDropdownMap('ID', 'Title'),
			($this->CopyMoveContentFromID) ? $this->CopyMoveContentFrom()->SubsiteID : Session::get('SubsiteID')
		);
		$fields->addFieldToTab(
			'Root.Content.Main',
			$subsiteSelectionField
		);
		
		// Setup the linking to the original page.
		$pageSelectionField = new CopyMoveSubsitesTreeDropdownField(
			"CopyMoveContentFromID",
			_t('VirtualPage.CHOOSE', "Choose a page to link to"), 
			"SiteTree",
			"ID",
			"MenuTitle"
		);
				
		$pageSelectionField->setFilterFunction(create_function('$item', 'return !($item instanceof VirtualPage);'));
		
		if(Controller::has_curr() && Controller::curr()->getRequest()) {
			$subsiteID = Controller::curr()->getRequest()->getVar('CopyMoveContentFromID_SubsiteID');
			$pageSelectionField->setSubsiteID($subsiteID);
		}
		$fields->addFieldToTab(
			'Root.Content.Main',
			$pageSelectionField
		);

                $fields->addFieldToTab('Root.Content.Main', new OptionsetField(
                        $name = "CopyOrMove",
                        $title = "Copy/Move the Page (and it's children)",
                        $source = array(
                            "1" => "Copy",
                            "2" => "Move"
                        ),
                        $value = "1"
                ));

		return $fields;
	}
        
        /**
	 * Get the actions available in the CMS for this page - eg Save, Publish.
	 * @return FieldSet The available actions for this page.
	 */
	function getCMSActions() {
		$actions = new FieldSet();

		$actions->push(new FormAction('save',_t('CMSMain.SAVE','Save')));

                return $actions;
	}

        function  onBeforeWrite() {
            parent::onBeforeWrite();

                if($this->CopyMoveContentFromID && self::$publish_check){

                    $currSubID = Subsite::currentSubsiteID();

                    $Parent = DataObject::get_by_id('SiteTree', $this->CopyMoveContentFromID);

                    if($this->CopyOrMove == 1){
                        // Copy the Tree to this Subsite

                        if($Parent){
                            Subsite::changeSubsite($Parent->SubsiteID);
                            $Parent->duplicateToSubsiteWithChildren($currSubID, $this->ID);
                            //$clone->ParentID = $this->ID;
                            //$clone->write();
                            Subsite::changeSubsite($currSubID);
                        }

                    }elseif($this->CopyOrMove == 2){
                        // Move the Tree to this Subsite

                        if($Parent){
                            Subsite::changeSubsite($Parent->SubsiteID);
                            $Parent->moveToSubsiteWithChildren($currSubID);
                            $Parent->ParentID = $this->ID;
                            $Parent->write();
                            Subsite::changeSubsite($currSubID);
                        }
                    }

                    $this->CopyMoveContentFromID = 0;
                }
        }

        function  onAfterWrite() {
            parent::onAfterWrite();

                // publish and set Title if not set yet
                // compare with "defaults -> Status"
                if($this->Status == "New Copy Folder" && self::$publish_check){
                    // prevent onBeforeWrite from running an endless circle when doing publish
                    self::$publish_check = false;
                    $this->Title = 'Copy and Move Container';
                    $this->MenuTitle = 'Copy and Move Container';
                    $this->doPublish();
                    self::$publish_check = true;
                }

                // delete all versioned SubsitesCopyAndMovePage entries
                DB::query("DELETE FROM SiteTree_versions WHERE ClassName = 'SubsitesCopyAndMovePage';");
                DB::query("TRUNCATE SubsitesCopyAndMovePage_versions;");
        }
}
class SubsitesCopyAndMovePage_Controller extends ContentController {

}

?>
