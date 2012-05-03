CopyMoveSubsitesTreeDropdownField = Class.extend('TreeDropdownField');
CopyMoveSubsitesTreeDropdownField.prototype = {
	
	subsiteID: function() {
		var subsiteSel = $$('#CopyMoveContentFromID_SubsiteID select')[0];
		subsiteSel.onchange = (function() {
			this.createTreeNode(true);
			this.ajaxGetTree((function(response) {
				this.newTreeReady(response, true);
				this.updateTreeLabel();
			}).bind(this));
		}).bind(this);
		return subsiteSel.options[subsiteSel.selectedIndex].value;
	},
	
	ajaxGetTree: function(after) {
		// Can't force value because it might be on a different subsite!
		var ajaxURL =  this.buildURL('gettree?forceValues=' + 0); //this.inputTag.value;
		
		// Customised: Append subsiteid (evaluated in SubsitesVirtualPage.php)
		ajaxURL += '&' + this.inputTag.name + '_SubsiteID=' + parseInt(this.subsiteID());
		
		ajaxURL += $('SecurityID') ? '&SecurityID=' + $('SecurityID').value : '';
		new Ajax.Request(ajaxURL, {
			method : 'get', 
			onSuccess : after,
			onFailure : function(response) { errorMessage("Error getting data", response); }
		})
	},
	
	// This ajaxExpansion function is actually attached as a method on the tree object; therefore, this.getIdx() is a method
	// note also this.tree.options.dropdownField.subsiteID() must be called, not this.subsiteID()
	ajaxExpansion: function() {
		this.addNodeClass('loading');
		var ul = this.treeNodeHolder();
		ul.innerHTML = ss.i18n._t('LOADING');
		
		var ajaxURL =  this.options.dropdownField.buildURL('getsubtree?SubtreeRootID=' + this.getIdx());
		
		// Find the root of the tree - this points to a list item in the tree, not the root div we actually want
		// @todo: We should be using framework API calls to find the tree
		var tree = this;
		while (tree && !tree.className.match(/(^| )CopyMoveSubsitesTreeDropdownField( |$)/)) tree = tree.parentNode;
		
		// Customized: Append subsiteid (evaluated in SubsitesVirtualPage.php)
		ajaxURL += '&' + this.options.dropdownField.inputTag.name + '_SubsiteID=' + parseInt(this.options.dropdownField.subsiteID());
		
		new Ajax.Request(ajaxURL, {
			onSuccess : this.installSubtree.bind(this),
			onFailure : function(response) { errorMessage('error loading subtree', response); }
		});
	}
}
CopyMoveSubsitesTreeDropdownField.applyTo('div.CopyMoveSubsitesTreeDropdownField');
