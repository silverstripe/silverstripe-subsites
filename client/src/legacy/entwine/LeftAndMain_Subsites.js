/*jslint browser: true, nomen: true*/
/*global $, window, jQuery*/
import ReactDom from 'react-dom';
import { loadComponent } from 'lib/Injector';

(function($) {
  'use strict';
  $.entwine('ss', function($) {

    $('#SubsitesSelect').entwine({
      ModalNode: null,
      onadd:function(){
        let subsiteSelect = $(this);

        // Storage has updated trigger
        window.addEventListener('storage', function(storageEvent) {
          if (storageEvent.key === "subsiteInfo") {
            window.dispatchEvent(new Event('subsitechange'));
          }
        }, false);

        window.addEventListener('subsitechange', () => {
          const subsiteNotice = subsiteSelect.getModalNode();
          if(subsiteNotice){
            ReactDom.unmountComponentAtNode(subsiteNotice)
          }
          if(JSON.parse(localStorage.getItem('subsiteInfo')).subsiteID !== subsiteSelect.val()) {
            showReactiveNotice()
          }
        }, false);

        function storeSubsiteInfo() {
          const subsiteID = subsiteSelect.val();
          const subsiteInfo = {
            subsiteID,
            subsiteName: $(`[value="${subsiteID}"]`, subsiteSelect).text()
          }
          window.localStorage.setItem('subsiteInfo', JSON.stringify(subsiteInfo));
          return subsiteInfo;
        }

        // We need to set when a page loads, as it may be e.g. the refresh of a currently blocked tab.
        storeSubsiteInfo();

        // Dropdown change trigger
        this.on('change', () => {
          const subsiteInfo = storeSubsiteInfo();
          window.location.search=$.query.set('SubsiteID', subsiteInfo.subsiteID);
        });

        function showReactiveNotice() {
          // React business
          const modalContainer = window.document.createElement('div');
          window.document.body.appendChild(modalContainer);
          const ChangeAlert = loadComponent('SubsiteChangeAlert');
          const subsiteInfo = JSON.parse(localStorage.getItem('subsiteInfo'));
          const selectedIndex = subsiteSelect.get(0).selectedIndex;
          ReactDom.render(
            <ChangeAlert
              newSubsiteID={parseInt(subsiteSelect.val(), 10)}
              newSubsiteName={subsiteInfo.subsiteName}
              thisSubsite={subsiteSelect.get(0).options[selectedIndex].text}
            />,
            modalContainer
          );
          subsiteSelect.setModalNode(modalContainer);
        }
      }
		});

		/*
		 * Reload subsites dropdown when links are processed
		 */
		$('.cms-container .cms-menu-list li a').entwine({
			onclick: function(e) {
				$('.cms-container').loadFragment('admin/subsite_xhr', 'SubsiteList');
				this._super(e);
			}
		});

		/*
		 * Reload subsites dropdown when the admin area reloads (for deleting sites)
		 */
		$('.cms-container .SubsiteAdmin .cms-edit-form fieldset.ss-gridfield').entwine({
			onreload: function(e) {
				$('.cms-container').loadFragment('admin/subsite_xhr', 'SubsiteList');
				this._super(e);
			}
		});

		/*
		 * Reload subsites dropdown when subsites are added or names are modified
		 */
		$('.cms-container .tab.subsite-model').entwine({
			onadd: function(e) {
				$('.cms-container').loadFragment('admin/subsite_xhr', 'SubsiteList');
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
			 * The initial link for the iframe comes from SiteTreeSubsites::updatePreviewLink.
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
