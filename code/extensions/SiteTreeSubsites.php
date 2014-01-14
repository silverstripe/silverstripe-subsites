<?php

/**
 * Extension for the SiteTree object to add subsites support
 */
class SiteTreeSubsites extends DataExtension {

	private static $has_one = array(
		'Subsite' => 'Subsite', // The subsite that this page belongs to
	);

	private static $many_many = array(
		'CrossSubsiteLinkTracking' => 'SiteTree' // Stored separately, as the logic for URL rewriting is different
	);

	private static $many_many_extraFields = array(
		"CrossSubsiteLinkTracking" => array("FieldName" => "Varchar")
	);

	function isMainSite() {
		if($this->owner->SubsiteID == 0) return true;
		return false;
	}
	
	/**
	 * Update any requests to limit the results to the current site
	 */
	function augmentSQL(SQLQuery &$query, DataQuery &$dataQuery = null) {
		if(Subsite::$disable_subsite_filter) return;
		if($dataQuery->getQueryParam('Subsite.filter') === false) return;
		
		// Don't run on delete queries, since they are always tied to
		// a specific ID.
		if ($query->getDelete()) return;
		
		// If you're querying by ID, ignore the sub-site - this is a bit ugly...
		// if(!$query->where || (strpos($query->where[0], ".\"ID\" = ") === false && strpos($query->where[0], ".`ID` = ") === false && strpos($query->where[0], ".ID = ") === false && strpos($query->where[0], "ID = ") !== 0)) {
		if (!$query->where || (!preg_match('/\.(\'|"|`|)ID(\'|"|`|)( ?)=/', $query->where[0]))) {

			if (Subsite::$force_subsite) $subsiteID = Subsite::$force_subsite;
			else {
				/*if($context = DataObject::context_obj()) $subsiteID = (int)$context->SubsiteID;
				else */$subsiteID = (int)Subsite::currentSubsiteID();
			}
			
			// The foreach is an ugly way of getting the first key :-)
			foreach($query->getFrom() as $tableName => $info) {
				// The tableName should be SiteTree or SiteTree_Live...
				if(strpos($tableName,'SiteTree') === false) break;
				$query->addWhere("\"$tableName\".\"SubsiteID\" IN ($subsiteID)");
				break;
			}
		}
	}
	
	function onBeforeWrite() {
		if(!$this->owner->ID && !$this->owner->SubsiteID) $this->owner->SubsiteID = Subsite::currentSubsiteID();
		
		parent::onBeforeWrite();
	}

	function updateCMSFields(FieldList $fields) {
		$subsites = Subsite::accessible_sites("CMS_ACCESS_CMSMain");
		$subsitesMap = array();
		if($subsites && $subsites->Count()) {
			$subsitesMap = $subsites->map('ID', 'Title');
			unset($subsitesMap[$this->owner->SubsiteID]);
		} 

		// Master page edit field (only allowed from default subsite to avoid inconsistent relationships)
		$isDefaultSubsite = $this->owner->SubsiteID == 0 || $this->owner->Subsite()->DefaultSite;
		if($isDefaultSubsite && $subsitesMap) {
			$fields->addFieldToTab(
				'Root.Main',
				new DropdownField(
					"CopyToSubsiteID", 
					_t('SiteTreeSubsites.CopyToSubsite', "Copy page to subsite"), 
					$subsitesMap,
					''
				)
			);
			$fields->addFieldToTab(
				'Root.Main',
				$copyAction = new InlineFormAction(
					"copytosubsite", 
					_t('SiteTreeSubsites.CopyAction', "Copy")
				)
			);
			$copyAction->includeDefaultJS(false);
		}

		// replace readonly link prefix
		$subsite = $this->owner->Subsite();
		$nested_urls_enabled = Config::inst()->get('SiteTree', 'nested_urls');
		if($subsite && $subsite->ID) {
			$baseUrl = 'http://' . $subsite->domain() . '/';
			$baseLink = Controller::join_links (
				$baseUrl,
				($nested_urls_enabled && $this->owner->ParentID ? $this->owner->Parent()->RelativeLink(true) : null)
			);
			
			$url = (strlen($baseLink) > 36 ? "..." .substr($baseLink, -32) : $baseLink);

			$urlsegment = $fields->dataFieldByName('URLSegment');
			$urlsegment->setURLPrefix($url);
		}
	}
	
	function alternateSiteConfig() {
		if(!$this->owner->SubsiteID) return false;
		$sc = DataObject::get_one('SiteConfig', '"SubsiteID" = ' . $this->owner->SubsiteID);
		if(!$sc) {
			$sc = new SiteConfig();
			$sc->SubsiteID = $this->owner->SubsiteID;
			$sc->Title = _t('Subsite.SiteConfigTitle','Your Site Name');
			$sc->Tagline = _t('Subsite.SiteConfigSubtitle','Your tagline here');
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

		if (!is_null($this->owner->SubsiteID)) {
			$subsiteID = $this->owner->SubsiteID;
		} else {
			// The relationships might not be available during the record creation when using a GridField.
			// In this case the related objects will have empty fields, and SubsiteID will not be available.
			//
			// We do the second best: fetch the likely SubsiteID from the session. The drawback is this might
			// make it possible to force relations to point to other (forbidden) subsites.
			$subsiteID = Subsite::currentSubsiteID();
		}

		// Return true if they have access to this object's site
		if(!(in_array(0, $goodSites) || in_array($subsiteID, $goodSites))) return false;
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
	 */
	public function duplicateToSubsite($subsiteID = null) {
		if(is_object($subsiteID)) {
			$subsite = $subsiteID;
			$subsiteID = $subsite->ID;
		} else $subsite = DataObject::get_by_id('Subsite', $subsiteID);
		
		$oldSubsite=Subsite::currentSubsiteID();
		if($subsiteID) {
			Subsite::changeSubsite($subsiteID);
		}else {
			$subsiteID=$oldSubsite;
		}

		$page = $this->owner->duplicate(false);

		$page->CheckedPublicationDifferences = $page->AddedToStage = true;
		$subsiteID = ($subsiteID ? $subsiteID : $oldSubsite);
		$page->SubsiteID = $subsiteID;

		// MasterPageID is here for legacy purposes, to satisfy the subsites_relatedpages module
		$page->MasterPageID = $this->owner->ID;
		$page->write();

		Subsite::changeSubsite($oldSubsite);

		return $page;
	}

	/**
	 * Called by ContentController::init();
	 */
	static function contentcontrollerInit($controller) {
		$subsite = Subsite::currentSubsite();
		if($subsite && $subsite->Theme) SSViewer::set_theme(Subsite::currentSubsite()->Theme);
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

	/**
	 * Use the CMS domain for iframed CMS previews to prevent single-origin violations
	 * and SSL cert problems.
	 */
	function alternatePreviewLink($action = null) {
		$url = Director::absoluteURL($this->owner->Link());
		if($this->owner->SubsiteID) {
			$url = HTTP::setGetVar('SubsiteID', $this->owner->SubsiteID, $url);
		}
		return $url;
	}

	/**
	 * Inject the subsite ID into the content so it can be used by frontend scripts.
	 */
	function MetaTags(&$tags) {
		if($this->owner->SubsiteID) {
			$tags .= "<meta name=\"x-subsite-id\" content=\"" . $this->owner->SubsiteID . "\" />\n";
		}

		return $tags;
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

					$origDisableSubsiteFilter = Subsite::$disable_subsite_filter;
					Subsite::disable_subsite_filter(true);
					$candidatePage = DataObject::get_one("SiteTree", "\"URLSegment\" = '" . urldecode( $rest). "' AND \"SubsiteID\" = " . $subsiteID, false);
					Subsite::disable_subsite_filter($origDisableSubsiteFilter);
					
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
	
	/**
	 * @param Member
	 * @return boolean|null
	 */
	function canCreate($member = null) {
		// Typically called on a singleton, so we're not using the Subsite() relation
		$subsite = Subsite::currentSubsite();
		if($subsite && $subsite->exists() && $subsite->PageTypeBlacklist) {
			$blacklisted = explode(',', $subsite->PageTypeBlacklist);
			// All subclasses need to be listed explicitly
			if(in_array($this->owner->class, $blacklisted)) return false;
		}
	}
}
