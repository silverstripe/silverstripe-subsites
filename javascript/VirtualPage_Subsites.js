Behaviour.register({
	'#CopyContentFromID_SubsiteID select' : {
		initialize: function() {
			var treeField = $('TreeDropdownField_Form_EditForm_CopyContentFromID');
			if(!treeField) return false;
			
			treeField.subsiteID = this.value;
		},
		onchange: function() {
			var treeField = $('TreeDropdownField_Form_EditForm_CopyContentFromID');
			if(!treeField) return false;
			
			treeField.subsiteID = this.value;
			treeField.refresh();
		}
	}
});