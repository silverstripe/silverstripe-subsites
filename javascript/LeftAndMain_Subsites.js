Behaviour.register({
	'#SubsiteActions select' : {
		onchange: function() {
			var request = new Ajax.Request(SiteTreeHandlers.controller_url + '/changesubsite?ID=' + this.value + '&ajax=1', {
				onSuccess: function(response) {
					$('sitetree').innerHTML = response.responseText;
					SiteTree.applyTo($('sitetree'));
					$('sitetree').getTreeNodeByIdx(0).onselect();
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