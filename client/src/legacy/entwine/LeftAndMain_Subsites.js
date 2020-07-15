/* global window, jQuery */
import React from 'react';
import ReactDom from 'react-dom';
import { loadComponent } from 'lib/Injector';
import createEvent from 'legacy/createEvent';
import changeActiveSubsite from 'lib/changeActiveSubsite';
import url from 'url';

jQuery.entwine('ss', $ => {
  $('#SubsitesSelect').entwine({
    ModalNode: null,

    onadd() {
      // Storage has been updated - another tab may have changed subsite
      window.addEventListener('storage', ({ key, newValue }) => {
        if (key === 'subsiteInfo') {
          const subsiteChangeEvent = createEvent('subsitechange', { subsiteInfo: newValue });
          window.dispatchEvent(subsiteChangeEvent);
        }
      });

      window.addEventListener('subsitechange', (subsiteChangeEvent) => {
        const subsiteNotice = this.getModalNode();
        const subsiteInfo = JSON.parse(subsiteChangeEvent.subsiteInfo);
        if (subsiteNotice) {
          ReactDom.unmountComponentAtNode(subsiteNotice);
        }
        if (subsiteInfo.subsiteID !== this.val()) {
          this.showReactiveNotice(subsiteInfo);
        }
      });

      // Dropdown change trigger
      this.on('change', () => {
        const subsiteID = this.val();
        const currentURL = url.parse(window.location.toString(), true);

        currentURL.query.SubsiteID = subsiteID;
        window.location = url.format(currentURL);
      });

      // We need to set when a page loads, as it may be e.g. the refresh of a currently blocked tab.
      this.storeSubsiteInfo();
    },

    storeSubsiteInfo() {
      const subsiteID = this.val();
      const subsiteName = $(`[value="${subsiteID}"]`, this).text();

      changeActiveSubsite(subsiteID, subsiteName);
    },

    showReactiveNotice(newSubsite) {
      const modalContainer = this.getModalNode() || document.createElement('div');
      document.body.appendChild(modalContainer);
      const SubsiteChangeAlert = loadComponent('SubsiteChangeAlert');
      const selectedNode = this.get(0);
      const selectedIndex = selectedNode.selectedIndex;

      const currentSubsite = {
        subsiteName: selectedNode.options[selectedIndex].text,
        subsiteID: this.val(),
      };

      ReactDom.render(
        <SubsiteChangeAlert
          newSubsiteID={newSubsite.subsiteID}
          newSubsiteName={newSubsite.subsiteName}
          currentSubsiteID={currentSubsite.subsiteID}
          currentSubsiteName={currentSubsite.subsiteName}
          onRevert={() => changeActiveSubsite(currentSubsite.subsiteID, currentSubsite.subsiteName)}
        />,
        modalContainer
      );
      this.setModalNode(modalContainer);
    }
  });

  /*
   * Reload subsites dropdown when links are processed
   */
  $('.cms-container .cms-menu-list li a').entwine({
    onclick(e) {
      $('.cms-container').loadFragment('admin/subsite_xhr', 'SubsiteList');
      this._super(e);
    }
  });

  /*
   * Reload subsites dropdown when the admin area reloads (for deleting sites)
   */
  $('.cms-container .SubsiteAdmin .cms-edit-form fieldset.ss-gridfield').entwine({
    onreload(e) {
      $('.cms-container').loadFragment('admin/subsite_xhr', 'SubsiteList');
      this._super(e);
    }
  });

  /*
   * Reload subsites dropdown when subsites are added or names are modified
   */
  $('.cms-container .tab.subsite-model').entwine({
    onadd(e) {
      $('.cms-container').loadFragment('admin/subsite_xhr', 'SubsiteList');
      this._super(e);
    }
  });

  // Subsite tab of Group editor
  $('#Form_ItemEditForm_AccessAllSubsites').entwine({
    /**
     * Constructor: onmatch
     */
    onmatch() {
      this.showHideSubsiteList();

      const ref = this;
      $('#Form_ItemEditForm_AccessAllSubsites input').change(() => {
        ref.showHideSubsiteList();
      });
    },

    showHideSubsiteList() {
      $('#Form_ItemEditForm_Subsites').parent().parent().css('display', ($('#Form_ItemEditForm_AccessAllSubsites_1').is(':checked') ? 'none' : ''));
    }
  });

  $('.cms-edit-form').entwine({
    getChangeTrackerOptions() {
      // Figure out if we're still returning the default value
      const isDefault = (this.entwineData('ChangeTrackerOptions') === undefined);
      // Get the current options
      let opts = this._super();

      if (isDefault) {
        // If it is the default then...
        // clone the object (so we don't modify the original),
        opts = $.extend({}, opts);
        // modify it,
        opts.ignoreFieldSelector += ', input[name=IsSubsite]';
        // then set the clone as the value on this element
        // (so next call to this method gets this same clone)
        this.setChangeTrackerOptions(opts);
      }

      return opts;
    }
  });

  $('.cms-edit-form input[name=action_copytosubsite]').entwine({
    onclick() {
      const form = this.closest('form');
      form.trigger('submit', [this]);
    }
  });
});

jQuery.entwine('ss.preview', $ => {
  $('.cms-preview').entwine({

    /**
     * Update links and forms with GET/POST SubsiteID param, so we remaing on the current subsite.
     * The initial link for the iframe comes from SiteTreeSubsites::updatePreviewLink.
     *
     * This is done so we can use the CMS domain for displaying previews so we prevent single-origin
     * violations and SSL cert problems that come up when iframing content from a different URL.
     */
    onafterIframeAdjustedForPreview(event, doc) {
      const subsiteId = $(doc).find('meta[name=x-subsite-id]').attr('content');

      if (!subsiteId) {
        return;
      }

      // Inject the SubsiteID into internal links.
      $(doc).find('a').each(() => {
        const href = $(this).attr('href');

        if (typeof href !== 'undefined' && !href.match(/^https?:\/\//)) {
          $(this).attr('href', $.path.addSearchParams(href, {
            SubsiteID: subsiteId
          }));
        }
      });

      // Inject the SubsiteID as a hidden input into all forms submitting to the local site.
      $(doc).find('form').each(() => {
        const action = $(this).attr('action');

        if (typeof action !== 'undefined' && !action.match(/^https?:\/\//)) {
          $(this).append(`<input type="hidden" name="SubsiteID" value="${subsiteId}" >`);
        }
      });
    }
  });
});
