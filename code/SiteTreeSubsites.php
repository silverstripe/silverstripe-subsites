<?php

/**
 * Extension for the SiteTree object to add subsites support
 */
class SiteTreeSubsites extends SiteTreeDecorator {
	static $template_variables = array(
		'((Company Name))' => 'Title'
	);
	
	static $template_fields = array(
		"URLSegment",
		"Title",
		"MenuTitle",
		"Content",
		"MetaTitle",
		"MetaDescription",
		"MetaKeywords",
	);

	/**
	 * Set the fields that will be copied from the template.
	 * Note that ParentID and Sort are implied.
	 */
	static function set_template_fields($fieldList) {
		self::$template_fields = $fieldList;
	}

	
	function extraStatics() {
		if(!method_exists('DataObjectDecorator', 'load_extra_statics') && $this->owner->class != 'SiteTree') return null;
		return array(
			'has_one' => array(
				'Subsite' => 'Subsite', // The subsite that this page belongs to
				'MasterPage' => 'SiteTree',// Optional; the page that is the content master
			),
			'has_many' => array(
				'RelatedPages' => 'RelatedPageLink'
			),
			'many_many' => array(
				'CrossSubsiteLinkTracking' => 'SiteTree' // Stored separately, as the logic for URL rewriting is different
			),
			'belongs_many_many' => array(
				'BackCrossSubsiteLinkTracking' => 'SiteTree'
			)
		);
	}
	
	function isMainSite() {
		if($this->owner->SubsiteID == 0) return true;
		return false;
	}
	
	/**
	 * Update any requests to limit the results to the current site
	 */
	function augmentSQL(SQLQuery &$query) {
		if(Subsite::$disable_subsite_filter) return;
		
		// Don't run on delete queries, since they are always tied to
		// a specific ID.
		if ($query->delete) return;
		
		// If you're querying by ID, ignore the sub-site - this is a bit ugly...
		// if(!$query->where || (strpos($query->where[0], ".\"ID\" = ") === false && strpos($query->where[0], ".`ID` = ") === false && strpos($query->where[0], ".ID = ") === false && strpos($query->where[0], "ID = ") !== 0)) {
		if (!$query->where || (!preg_match('/\.(\'|"|`|)ID(\'|"|`|)( ?)=/', $query->where[0]))) {

			if (Subsite::$force_subsite) $subsiteID = Subsite::$force_subsite;
			else {
				if($context = DataObject::context_obj()) $subsiteID = (int)$context->SubsiteID;
				else $subsiteID = (int)Subsite::currentSubsiteID();
			}
			
			// The foreach is an ugly way of getting the first key :-)
			foreach($query->from as $tableName => $info) {
				// The tableName should be SiteTree or SiteTree_Live...
				if(strpos($tableName,'SiteTree') === false) break;
				$query->where[] = "\"$tableName\".\"SubsiteID\" IN ($subsiteID)";
				break;
			}
		}
	}
	
	/**
	 * Call this method before writing; the next write carried out by the system won't
	 * set the CustomContent value
	 */
	function nextWriteDoesntCustomise() {
		$this->nextWriteDoesntCustomise = true;
	}
	
	protected $nextWriteDoesntCustomise = false;
	
	function onBeforeWrite() {
		if(!$this->owner->ID && !$this->owner->SubsiteID) $this->owner->SubsiteID = Subsite::currentSubsiteID();

		// If the content has been changed, then the page should be marked as 'custom content'
		if(!$this->nextWriteDoesntCustomise && $this->owner->ID && $this->owner->MasterPageID && !$this->owner->CustomContent) {
			$changed = $this->owner->getChangedFields();

			foreach(self::$template_fields as $field) {
				if(isset($changed[$field]) && $changed[$field]) {
					$this->owner->CustomContent = true;
					FormResponse::add("if($('Form_EditForm_CustomContent')) $('Form_EditForm_CustomContent').checked = true;");
					break;
				}
			}
		}
		
		$this->nextWriteDoesntCustomise = false;
		
		parent::onBeforeWrite();
	}
	
	function onAfterWrite(&$original) {
		// Update any subsite virtual pages that might need updating
		$oldState = Subsite::$disable_subsite_filter;
		Subsite::$disable_subsite_filter = true;
		
		$linkedPages = DataObject::get("SubsitesVirtualPage", "\"CopyContentFromID\" = {$this->owner->ID}");
		if($linkedPages) foreach($linkedPages as $page) {
			$page->copyFrom($page->CopyContentFrom());
			$page->write();
		}
		
		Subsite::$disable_subsite_filter = $oldState;
	}
	
	function onAfterPublish(&$original) {
		// Publish any subsite virtual pages that might need publishing
		$oldState = Subsite::$disable_subsite_filter;
		Subsite::$disable_subsite_filter = true;
		
		$linkedPages = DataObject::get("SubsitesVirtualPage", "\"CopyContentFromID\" = {$this->owner->ID}");
		if($linkedPages) foreach($linkedPages as $page) {
			$page->copyFrom($page->CopyContentFrom());
			if($page->ExistsOnLive) $page->doPublish();
		}
		
		Subsite::$disable_subsite_filter = $oldState;
	}

	function updateCMSFields(&$fields) {
		if($this->owner->MasterPageID) $fields->insertFirst(new HeaderField('This page\'s content is copied from a master page: ' . $this->owner->MasterPage()->Title, 2));
		
		// replace readonly link prefix
		$subsite = $this->owner->Subsite();
		if($subsite && $subsite->ID) {
			$baseUrl = 'http://' . $subsite->domain() . '/';
			$fields->removeByName('BaseUrlLabel');
			$fields->addFieldToTab(
				'Root.Content.Metadata',
				new LabelField('BaseUrlLabel',$baseUrl),
				'URLSegment'
			);
		}
		
		$relatedCount = 0;
		$reverse = $this->ReverseRelated();
		if($reverse) $relatedCount += $reverse->Count();
		$normalRelated = $this->NormalRelated();
		if($normalRelated) $relatedCount += $normalRelated->Count();
		
		$tabName = $relatedCount ? 'Related (' . $relatedCount . ')' : 'Related';
		$tab = $fields->findOrMakeTab('Root.Related', $tabName);
		// Related pages
		$tab->push(new LiteralField('RelatedNote', '<p>You can list pages here that are related to this page.<br />When this page is updated, you will get a reminder to check whether these related pages need to be updated as well.</p>'));
		$tab->push(
			$related = new ComplexTableField(
					$this,
					'RelatedPages',
					'RelatedPageLink',
					array(
						'RelatedPageAdminLink' => 'Page',
						'AbsoluteLink' => 'URL',
					)
			)
		);
		
		// The 'show' link doesn't provide any useful info
		$related->setPermissions(array('add', 'edit', 'delete'));
		
		if($reverse) {
			$text = '<p>In addition, this page is marked as related by the following pages: </p><p>';
			foreach($reverse as $rpage) {
				$text .= $rpage->RelatedPageAdminLink(true) . " - " . $rpage->AbsoluteLink(true) . "<br />\n";
			}
			$text .= '</p>';
			
			$tab->push(new LiteralField('ReverseRelated', $text));
		}
		
		$virtualPagesTable = new SubsiteAgnosticTableListField(
			'VirtualPageTracking',
			'SiteTree',
			array(
				'Title' => 'Title',
				'AbsoluteLink' => 'URL',
				'Subsite.Title' => 'Subsite'
			),
			'"CopyContentFromID" = ' . $this->owner->ID,
			''
		);
		$virtualPagesTable->setFieldFormatting(array(
			'Title' => '<a href=\"admin/show/$ID\">$Title</a>'
		));
		$virtualPagesTable->setPermissions(array(
			'show',
			'export'
		));
		
		
		
		if ($tab = $fields->fieldByName('Root.VirtualPages')) {
			$tab->removeByName('VirtualPageTracking');
			$tab->push($virtualPagesTable);
		} else {
			if ($virtualPagesTable->TotalCount()) {
				$virtualPagesNote = new LiteralField('BackLinksNote', '<p>' . _t('SiteTree.VIRTUALPAGESLINKING', 'The following virtual pages pull from this page:') . '</p>');
				$fields->fieldByName('Root')->push($tabVirtualPages = new Tab('VirtualPages',
					$virtualPagesNote,
					$virtualPagesTable
				));
			}
		}
	
	}
	
	/**
	 * Returns the RelatedPageLink objects that are reverse-associated with this page.
	 */
	function ReverseRelated() {
		return DataObject::get('RelatedPageLink', "\"RelatedPageLink\".\"RelatedPageID\" = {$this->owner->ID}
			AND R2.\"ID\" IS NULL", '',
			"INNER JOIN \"SiteTree\" ON \"SiteTree\".\"ID\" = \"RelatedPageLink\".\"MasterPageID\"
			LEFT JOIN \"RelatedPageLink\" AS R2 ON R2.\"MasterPageID\" = {$this->owner->ID}
			AND R2.\"RelatedPageID\" = \"RelatedPageLink\".\"MasterPageID\"
			"
		);
	}
	
	function NormalRelated() {
		$return = new DataObjectSet();
		$links = DataObject::get('RelatedPageLink', '"MasterPageID" = ' . $this->owner->ID);
		if($links) foreach($links as $link) {
			if($link->RelatedPage()->exists()) {
				$return->push($link->RelatedPage());
			}
		}
		
		return $return->Count() > 0 ? $return : false;
	}
	
	function alternateSiteConfig() {
		if(!$this->owner->SubsiteID) return false;
		$sc = DataObject::get_one('SiteConfig', '"SubsiteID" = ' . $this->owner->SubsiteID);
		if(!$sc) {
			$sc = new SiteConfig();
			$sc->SubsiteID = $this->owner->SubsiteID;
			$sc->Title = 'Your Site Name';
			$sc->Tagline = 'your tagline here';
			$sc->write();
		}
		return $sc;
	}
	
	/**
	 * Only allow editing of a page if the member satisfies one of the following conditions:
	 * - Is in a group which has access to the subsite this page belongs to
	 * - Is in a group with edit permissions on the "main site"
	 * 
	 * @return boolean
	 */
	function canEdit($member = null) {
		if(!$member) $member = Member::currentUser();
		
		// Find the sites that this user has access to
		$goodSites = Subsite::accessible_sites('CMS_ACCESS_CMSMain',true,'all',$member)->column('ID');
		
		// Return true if they have access to this object's site
		if(!(in_array(0, $goodSites) || in_array($this->owner->SubsiteID, $goodSites))) return false;
	}
	
	/**
	 * @return boolean
	 */
	function canDelete($member = null) {
		if(!$member && $member !== FALSE) $member = Member::currentUser();
		
		return $this->canEdit($member);
	}
	
	/**
	 * @return boolean
	 */
	function canAddChildren($member = null) {
		if(!$member && $member !== FALSE) $member = Member::currentUser();
		
		return $this->canEdit($member);
	}
	
	/**
	 * @return boolean
	 */
	function canPublish($member = null) {
		if(!$member && $member !== FALSE) $member = Member::currentUser();

		return $this->canEdit($member);
	}

	/**
	 * Create a duplicate of this page and save it to another subsite
	 * @param $subsiteID int|Subsite The Subsite to copy to, or its ID
	 * @param $isTemplate boolean If this is true, then the current page will be treated as the template, and MasterPageID will be set
	 */
	public function duplicateToSubsite($subsiteID = null, $isTemplate = true) {
		if(is_object($subsiteID)) {
			$subsite = $subsiteID;
			$subsiteID = $subsite->ID;
		} else $subsite = DataObject::get_by_id('Subsite', $subsiteID);
		
		$page = $this->owner->duplicate(false);

		$page->CheckedPublicationDifferences = $page->AddedToStage = true;
		$subsiteID = ($subsiteID ? $subsiteID : Subsite::currentSubsiteID());
		$page->SubsiteID = $subsiteID;
		
		if($isTemplate) $page->MasterPageID = $this->owner->ID;
		
		$page->write();

		return $page;
	}
        
        /**
	 * Duplicates each child of this node recursively and returns the
	 * duplicate node.
	 *
	 * @return SiteTree The duplicated object.
	 */
	public function duplicateToSubsiteWithChildren($subsiteID = null, $destParentID) {

                $stack = array(array($this->owner->ID,$destParentID));
		while(count($stack) > 0) {
			list($sourceParentID, $destParentID) = array_pop($stack);

			$children = Versioned::get_by_stage('Page', 'Live', "\"ParentID\" = $sourceParentID", '');

			if($children) {
				foreach($children as $child) {
					$childClone = $child->duplicateToSubsite($subsiteID, false);
					$childClone->ParentID = $destParentID;
					$childClone->writeToStage('Stage');
					$childClone->publish('Stage', 'Live');
					array_push($stack, array($child->ID, $childClone->ID));
				}
			}
		}

                /*
		$clone = $this->owner->duplicateToSubsite($subsiteID, $isTemplate);

                if($this->owner->isPublished()){
                    $clone->writeToStage('Stage');
                    $clone->publish('Stage', 'Live');
                }

		// only catch live status of children
                //$children = Versioned::get_by_stage('Page', 'Live', "\"ParentID\" = ".$this->owner->ID, '');
                $this->owner->AllChildren();

		if($children) {
			foreach($children as $child) {
				$childClone = $child->duplicateToSubsiteWithChildren($subsiteID, $isTemplate);
				$childClone->ParentID = $clone->ID;
				$childClone->write();
			}
		}

		return $clone;
                */
	}

	/**
	 * move this page to other subsite
	 * @param $subsiteID int|Subsite The Subsite to copy to, or its ID
	 * @param $isTemplate boolean If this is true, then the current page will be treated as the template, and MasterPageID will be set
	 */
	public function moveToSubsite($subsiteID = null) {
		$this->owner->SubsiteID = $subsiteID;

		$this->owner->write();

		return $this;
	}

        /**
	 * moves each child of this node recursively and returns the
	 * moved node.
	 *
	 * @return SiteTree The duplicated object.
	 */
	public function moveToSubsiteWithChildren($subsiteID = null) {
		$this->owner->moveToSubsite($subsiteID);

		$children = $this->owner->AllChildren();

		if($children) {
			foreach($children as $child) {
				$child->moveToSubsiteWithChildren($subsiteID);
				$child->write();
			}
		}

		return $this;
	}

	/**
	 * Called by ContentController::init();
	 */
	static function contentcontrollerInit($controller) {
		// Need to set the SubsiteID to null incase we've been in the CMS
		Session::set('SubsiteID', null);
		$subsite = Subsite::currentSubsite();
		if($subsite && $subsite->Theme) SSViewer::set_theme(Subsite::currentSubsite()->Theme);
	}
	
	/**
	 * Called by ModelAsController::init();
	 */
	static function modelascontrollerInit($controller) {
		// Need to set the SubsiteID to null incase we've been in the CMS
		Session::set('SubsiteID', null);
	}
	
	function alternateAbsoluteLink() {
		// Generate the existing absolute URL and replace the domain with the subsite domain.
		// This helps deal with Link() returning an absolute URL.
		$url = Director::absoluteURL($this->owner->Link());
		if($this->owner->SubsiteID) {
			$url = preg_replace('/\/\/[^\/]+\//', '//' .  $this->owner->Subsite()->domain() . '/', $url);
		}
		return $url;
	}
	
	function augmentSyncLinkTracking() {
		// Set LinkTracking appropriately
		$links = HTTP::getLinksIn($this->owner->Content);
		$linkedPages = array();
		
		if($links) foreach($links as $link) {
			if(substr($link, 0, strlen('http://')) == 'http://') {
				$withoutHttp = substr($link, strlen('http://'));
				if(strpos($withoutHttp, '/') && strpos($withoutHttp, '/') < strlen($withoutHttp)) {
					$domain = substr($withoutHttp, 0, strpos($withoutHttp, '/'));
					$rest = substr($withoutHttp, strpos($withoutHttp, '/') + 1);
					
					$subsiteID = Subsite::getSubsiteIDForDomain($domain);
					if($subsiteID == 0) continue; // We have no idea what the domain for the main site is, so cant track links to it
					
					Subsite::disable_subsite_filter(true);
					$candidatePage = DataObject::get_one("SiteTree", "\"URLSegment\" = '" . urldecode( $rest). "' AND \"SubsiteID\" = " . $subsiteID, false);
					Subsite::disable_subsite_filter(false);
					
					if($candidatePage) {
						$linkedPages[] = $candidatePage->ID;
					} else {
						$this->owner->HasBrokenLink = true;
					}
				}
			}
		}
		
		$this->owner->CrossSubsiteLinkTracking()->setByIDList($linkedPages);
	}
	
	/**
	 * Return a piece of text to keep DataObject cache keys appropriately specific
	 */
	function cacheKeyComponent() {
		return 'subsite-'.Subsite::currentSubsiteID();
	}
}

?>
