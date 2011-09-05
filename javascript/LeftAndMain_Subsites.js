Behaviour.register({
	'#SubsiteActions select' : {
		onchange: function() {
			if($('Form_AddPageOptionsForm_SubsiteID')) {
				$('Form_AddPageOptionsForm_SubsiteID').value = this.value;
			}
			var request = new Ajax.Request(SiteTreeHandlers.controller_url + '/changesubsite?SubsiteID=' + this.value + '&ajax=1', {
				onSuccess: function(response) {
					if ($('sitetree')) {
						$('sitetree').innerHTML = response.responseText;
						SiteTree.applyTo($('sitetree'));
						$('sitetree').getTreeNodeByIdx(0).onselect();
						$('siteTreeFilterList').reapplyIfNeeded();
					}
				},
				
				onFailure: function(response) {
					errorMessage('Could not change subsite', response);
				}
			});
		}
	},
	
	// Subsite tab of Group editor
	'#Form_EditForm_AccessAllSubsites' : {
		initialize: function () {
			this.showHideSubsiteList();
			var i=0,items=this.getElementsByTagName('input');
			for(i=0;i<items.length;i++) {
				items[i].onchange = this.showHideSubsiteList;
			}
		},
		
		showHideSubsiteList : function () {
			$('Form_EditForm_Subsites').parentNode.style.display = 
				Form.Element.getValue($('Form_EditForm').AccessAllSubsites)==1 ? 'none' : '';
		}
	},
	
	/**
	 * Binding a visibility toggle anchor to a longer list of checkboxes.
	 * Hidden by default, unless either the toggle checkbox, or any of the 
	 * actual value checkboxes are selected.
	 */
	'a#PageTypeBlacklistToggle': {
		onclick: function(e) {
			jQuery('#PageTypeBlacklist').toggle();
		}
	},
	
	'#PageTypeBlacklist': {
		initialize: function() {
			var hasLimits = Boolean(jQuery(this).find('input:checked').length);
			jQuery(this).toggle(hasLimits);
		}
	}
});

// Add an item to fieldsToIgnore
Behaviour.register({
	'#Form_EditForm' : {
		initialize: function () {
			this.changeDetection_fieldsToIgnore.IsSubsite = true;
		}
	}	
});

fitToParent('ResultTable_holder');
