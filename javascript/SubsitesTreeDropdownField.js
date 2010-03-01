SubsitesTreeDropdownField = Class.extend('TreeDropdownField');
SubsitesTreeDropdownField.prototype = {
	
	subsiteID: function() {
		var subsiteSel = $$('#CopyContentFromID_SubsiteID select')[0];
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
		// This if block is necessary to maintain both 2.2 and 2.3 support
		var baseURL = this.helperURLBase();
		if(baseURL.match('action_callfieldmethod')) var ajaxURL =  baseURL+ '&methodName=gettree&forceValues=' + this.inputTag.value;
        else var ajaxURL =  baseURL+ 'gettree?forceValues=' + this.inputTag.value;
		
		// Customized: Append subsiteid (evaluated in SubsitesVirtualPage.php)
		ajaxURL += '&' + this.id + '_SubsiteID=' + parseInt(this.subsiteID());
		
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
		
		// This if block is necessary to maintain both 2.2 and 2.3 support
		var baseURL = this.options.dropdownField.helperURLBase();

        if(baseURL.match('action_callfieldmethod')) var ajaxURL =  baseURL+ '&methodName=getsubtree&SubtreeRootID=' + this.getIdx();
        else var ajaxURL =  baseURL+ 'getsubtree?SubtreeRootID=' + this.getIdx();
		
		// Find the root of the tree - this points to a list item in the tree, not the root div we actually want
		// @todo: We should be using framework API calls to find the tree
		var tree = this;
		while (tree && !tree.className.match(/(^| )SubsitesTreeDropdownField( |$)/)) tree = tree.parentNode;
		
		// Customized: Append subsiteid (evaluated in SubsitesVirtualPage.php)
		ajaxURL += '&' + this.id + '_SubsiteID=' + parseInt(this.tree.options.dropdownField.subsiteID());
		ajaxURL += $('SecurityID') ? '&SecurityID=' + $('SecurityID').value : '';
		
		new Ajax.Request(ajaxURL, {
			onSuccess : this.installSubtree.bind(this),
			onFailure : function(response) { errorMessage('error loading subtree', response); }
		});
	}
}
SubsitesTreeDropdownField.applyTo('div.SubsitesTreeDropdownField');