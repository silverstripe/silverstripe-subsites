Behaviour.register({
	'#SubsiteActions select' : {
		onchange: function() {
			document.location.href = SiteTreeHandlers.controller_url + '?SubsiteID=' + this.value;
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
			return false;
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
