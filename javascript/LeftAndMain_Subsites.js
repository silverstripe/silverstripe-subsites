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
	
	'#SubsiteActions a' : {
		onclick: function() {
			var subsiteName = prompt('Enter the name of the new site','');
			if(subsiteName && subsiteName != '') {
				var request = new Ajax.Request(this.href + '?Name=' + encodeURIComponent(subsiteName) + '&ajax=1', {
					onSuccess: function(response) {
						var origSelect = $('SubsitesSelect');
						var div = document.createElement('div');
						div.innerHTML = response.responseText;
						var newSelect = div.firstChild;
						
						while(origSelect.length > 0)
							origSelect.remove(0);
						
						for(var j = 0; j < newSelect.length; j++) {
							var opt = newSelect.options.item(j).cloneNode(true);
							var newOption = document.createElement('option');
							
							/*if(opt.text)
								newOption.text = opt.text;*/
							if(opt.firstChild)
								newOption.text = opt.firstChild.nodeValue;
							
							newOption.value = opt.value;
							//console.log(newOption.text + ' ' + newOption.value);
							try {
								origSelect.add(newOption, null);
							} catch(ex) {
								origSelect.add(newOption);
							}
						}
						
						statusMessage('Created ' + subsiteName, 'good');
					},
					onFailure: function(response) {
						errorMessage('Could not create new subsite', response);
					}
				});
			}
			
			return false;
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