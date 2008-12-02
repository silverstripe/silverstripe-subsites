SubsitesTreeDropdownField = Class.extend('TreeDropdownField');
SubsitesTreeDropdownField.prototype = {
	
	//subsiteID: null,
	
	ajaxGetTree: function(after) {
		var ajaxURL = this.helperURLBase() + 'gettree?forceValues=' + this.inputTag.value;
		
		// Customized: Append subsiteid (evaluated in SubsitesVirtualPage.php)
		if(this.subsiteID) ajaxURL += '&' + this.id + '_SubsiteID=' + this.subsiteID;
		
		ajaxURL += $('SecurityID') ? '&SecurityID=' + $('SecurityID').value : '';
		new Ajax.Request(ajaxURL, {
			method : 'get', 
			onSuccess : after,
			onFailure : function(response) { errorMessage("Error getting data", response); }
		})
	},
	
	ajaxExpansion: function() {
		this.addNodeClass('loading');
		var ul = this.treeNodeHolder();
		ul.innerHTML = ss.i18n._t('LOADING');
		
		var ajaxURL = this.options.dropdownField.helperURLBase() + 'getsubtree?&SubtreeRootID=' + this.getIdx();
		
		// Customized: Append subsiteid (evaluated in SubsitesVirtualPage.php)
		if(this.subsiteID) ajaxURL += '&' + this.id + '_SubsiteID=' + this.subsiteID;
		
		ajaxURL += $('SecurityID') ? '&SecurityID=' + $('SecurityID').value : '';
		new Ajax.Request(ajaxURL, {
			onSuccess : this.installSubtree.bind(this),
			onFailure : function(response) { errorMessage('error loading subtree', response); }
		});
	}
}
SubsitesTreeDropdownField.applyTo('div.SubsitesTreeDropdownField');