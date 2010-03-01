SubsitesTreeDropdownField = Class.extend('TreeDropdownField');
SubsitesTreeDropdownField.prototype = {
	
	//subsiteID: null,
	
	ajaxGetTree: function(after) {
		var baseURL = this.helperURLBase();
		// Can't force value because it might be on a different subsite!
		var ajaxURL =  baseURL+ 'gettree?forceValues=' + 0; //this.inputTag.value;
		
		// Customised: Append subsiteid (evaluated in SubsitesVirtualPage.php)
		ajaxURL += '&' + this.id + '_SubsiteID=' + parseInt(this.subsiteID);
		
		ajaxURL += $('SecurityID') ? '&SecurityID=' + $('SecurityID').value : '';
		new Ajax.Request(ajaxURL, {
			method : 'get', 
			onSuccess : after,
			onFailure : function(response) { errorMessage("Error getting data", response); }
		})
	},
	
	// This ajaxExpansion function is actually attached as a method on the tree object; therefore, this.getIdx() is a method
	ajaxExpansion: function() {
		this.addNodeClass('loading');
		var ul = this.treeNodeHolder();
		ul.innerHTML = ss.i18n._t('LOADING');
		
		var baseURL = this.options.dropdownField.helperURLBase();
		var ajaxURL =  baseURL+ 'getsubtree?SubtreeRootID=' + this.getIdx();
		
		// Find the root of the tree - this points to a list item in the tree, not the root div we actually want
		// @todo: We should be using framework API calls to find the tree
		var tree = this;
		while (tree && !tree.className.match(/(^| )SubsitesTreeDropdownField( |$)/)) tree = tree.parentNode;
		
		// Customized: Append subsiteid (evaluated in SubsitesVirtualPage.php)
		ajaxURL += '&' + tree.id + '_SubsiteID=' + parseInt(tree.subsiteID);
		ajaxURL += $('SecurityID') ? '&SecurityID=' + $('SecurityID').value : '';
		
		new Ajax.Request(ajaxURL, {
			onSuccess : this.installSubtree.bind(this),
			onFailure : function(response) { errorMessage('error loading subtree', response); }
		});
	}
}
SubsitesTreeDropdownField.applyTo('div.SubsitesTreeDropdownField');
