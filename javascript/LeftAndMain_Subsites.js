/*jslint browser: true, nomen: true*/
/*global $, window, jQuery*/

(function($) {
	'use strict';
	$.entwine('ss', function($) {

		$('#SubsitesSelect').entwine({
			onadd:function(){
				this.on('change', function(){
					window.location.search=$.query.set('SubsiteID', $(this).val());
				});
			}
		});

		$('.cms-container').entwine({

			SubsiteCurrentXHR: null,

			/**
			 * LeftAndMain does not give us possibility to parallel-fetch a PJAX fragment.
			 * We provide our own fetcher that bypasses the history - that's because we
			 * don't want to load a panel, but rather just a subsite dropdown.
			 */
			subsiteFetchPjaxFragment: function(url, pjaxFragment) {

				// Make sure only one subsite XHR request is ongoing.
				if(this.getSubsiteCurrentXHR()){
					this.getSubsiteCurrentXHR().abort();
				}

				var self = this,
					xhr,
					headers = {},
					baseUrl = $('base').attr('href');
					
				url = $.path.isAbsoluteUrl(url) ? url : $.path.makeUrlAbsolute(url, baseUrl);
				headers['X-Pjax'] = pjaxFragment;

				xhr = $.ajax({
					headers: headers,
					url: url,
					complete: function() {
						self.setSubsiteCurrentXHR(null);
					},
					success: function(data, status, xhr) {
						self.handleAjaxResponse(data, status, xhr, null);
					}
				});

				this.setSubsiteCurrentXHR(xhr);
			}

		});

		/* 
		 * Reload subsites dropdown when links are processed 
		 */
		$('.cms-container .cms-menu-list li a').entwine({
			onclick: function(e) {
				$('.cms-container').subsiteFetchPjaxFragment('SubsiteXHRController', 'SubsiteList');
				this._super(e);
			}
		});

		/* 
		 * Reload subsites dropdown when the admin area reloads (for deleting sites) 
		 */
		$('.cms-container .SubsiteAdmin .cms-edit-form fieldset.ss-gridfield').entwine({
			onreload: function(e) {
				$('.cms-container').subsiteFetchPjaxFragment('SubsiteXHRController', 'SubsiteList');
				this._super(e);
			}
		});



		
		/* 
		 * Reload subsites dropdown when subsites are added or names are modified
		 */
		$('.cms-container .cms-content-fields .subsite-model').entwine({
			onadd: function(e) {
				$('.cms-container').subsiteFetchPjaxFragment('SubsiteXHRController', 'SubsiteList');
				this._super(e);
			}
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
			/**
			 * TODO: Fix with Entwine API extension. See https://github.com/silverstripe/silverstripe-subsites/pull/125
			 */
			getChangeTrackerOptions: function() {
				// Figure out if we're still returning the default value
				var isDefault = (this.entwineData('ChangeTrackerOptions') === undefined);
				// Get the current options
				var opts = this._super();

				if (isDefault) {
					// If it is the default then...
					// clone the object (so we don't modify the original),
					var opts = $.extend({}, opts);
					// modify it,
					opts.ignoreFieldSelector +=', input[name=IsSubsite]';
					// then set the clone as the value on this element
					// (so next call to this method gets this same clone)
					this.setChangeTrackerOptions(opts);
				}

				return opts;
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

				if (!subsiteId) {
					return;
				}

				// Inject the SubsiteID into internal links.
				$(doc).find('a').each(function() {
					var href = $(this).attr('href');

					if (typeof href!=='undefined' && !href.match(/^http:\/\//)) {

						$(this).attr('href', $.path.addSearchParams(href, {
							'SubsiteID': subsiteId
						}));

					}
				});

				// Inject the SubsiteID as a hidden input into all forms submitting to the local site.
				$(doc).find('form').each(function() {
					var action = $(this).attr('action');

					if (typeof action!=='undefined' && !action.match(/^http:\/\//)) {
						$(this).append('<input type=hidden name="SubsiteID" value="' + subsiteId + '" >');
					}

				});
			}
		});
	});
}(jQuery));
