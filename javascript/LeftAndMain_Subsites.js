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

	$.entwine('ss.preview', function($){

		$('.cms-preview').entwine({

			/**
			 * Update links and forms with GET/POST SubsiteID param, so we remaing on the current subsite.
			 * The initial link for the iframe comes from SiteTreeSubsites::alternatePreviewLink.
			 *
			 * This is done so we can use the CMS domain for displaying previews so we prevent single-origin
			 * violations and SSL cert problems that come up when iframing content from a different URL.
			 */
			onafterIframeAdjustedForPreview: function(event, doc) {
				var subsiteId = $(doc).find('meta[name=x-subsite-id]').attr('content');

				if (!subsiteId) return;

				// Inject the SubsiteID into internal links.
				$(doc).find('a').each(function() {
					var href = $(this).attr('href');

					if (!href.match(/^http:\/\//)) {

						$(this).attr('href', $.path.addSearchParams(href, {
							'SubsiteID': subsiteId
						}));

					}
				});

				// Inject the SubsiteID as a hidden input into all forms submitting to the local site.
				$(doc).find('form').each(function() {

					if (!$(this).attr('action').match(/^http:\/\//)) {
						$(doc).find('form').append('<input type=hidden name="SubsiteID" value="' + subsiteId + '" >');
					}

				});

			}

		});

	});

})(jQuery);
