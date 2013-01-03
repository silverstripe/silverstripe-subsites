(function($) {
	$.entwine('ss', function($) {
		$('#SubsitesSelect').live('change', function() {
			window.location.search=$.query.set('SubsiteID', $(this).val());
		});
		
		// Subsite tab of Group editor
		$('#Form_ItemEditForm_AccessAllSubsites').entwine({
			/**
			 * Constructor: onmatch
			 */
			onmatch: function () {
				this.showHideSubsiteList();
				
				var ref=this;
				$('#Form_ItemEditForm_AccessAllSubsites input').change(function() {
					ref.showHideSubsiteList();
				});
			},
			
			showHideSubsiteList: function () {
				$('#Form_ItemEditForm_Subsites').parent().parent().css('display', ($('#Form_ItemEditForm_AccessAllSubsites_1').is(':checked') ? 'none':''));
			}
		});
		
		$('.cms-edit-form').entwine({
			getChangeTrackerOptions: function() {
				this.ChangeTrackerOptions.ignoreFieldSelector+=', input[name=IsSubsite]';
			}
		});
		
		/**
		 * Binding a visibility toggle anchor to a longer list of checkboxes.
		 * Hidden by default, unless either the toggle checkbox, or any of the 
		 * actual value checkboxes are selected.
		 */
		$('#PageTypeBlacklist').entwine({
			onmatch: function() {
				var hasLimits=Boolean($('#PageTypeBlacklist').find('input:checked').length);
				jQuery('#PageTypeBlacklist').toggle(hasLimits);
				
				
				//Bind listener
				$('a#PageTypeBlacklistToggle').click(function(e) {
					jQuery('#PageTypeBlacklist').toggle();
					e.stopPropagation();
					return false;
				});
			}
		});

		$('.cms-edit-form input[name=action_copytosubsite]').entwine({
			onclick: function(e) {
				var form = this.closest('form');
				form.trigger('submit', [this]);
			}
		});

	});
})(jQuery);