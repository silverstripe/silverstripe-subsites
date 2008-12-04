SubsitesTreeDropdownField = Class.extend('TreeDropdownField');
SubsitesTreeDropdownField.prototype = {
	
	//subsiteID: null,
	
	ajaxGetTree: function(after) {
		// This if block is necessary to maintain both 2.2 and 2.3 support
		var baseURL = this.options.dropdownField.helperURLBase();
        if(baseURL.match('action_callfieldmethod') var ajaxURL =  baseURL+ '&methodName=gettree&forceValues=' + this.getIdx();
        else var ajaxURL =  baseURL+ 'gettree?forceValues=' + this.getIdx();
		
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
		
		// This if block is necessary to maintain both 2.2 and 2.3 support
		var baseURL = this.options.dropdownField.helperURLBase();
        if(baseURL.match('action_callfieldmethod') var ajaxURL =  baseURL+ '&methodName=gettree&SubtreeRootID=' + this.getIdx();
        else var ajaxURL =  baseURL+ 'gettree?SubtreeRootID=' + this.getIdx();
		
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