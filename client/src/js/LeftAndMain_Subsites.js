/* jslint browser: true, nomen: true */
/* global $, ss, window, jQuery */
(function ($) {
  // eslint-disable-next-line no-shadow
  $.entwine('ss', ($) => {
    $('#SubsitesSelect').entwine({
      /**
       * Store current subsite id and ask to reload the page if it detects any change
       */
      detectSubsiteChange(selectedId) {
        const sessionKey = 'admin_subsite_id';
        let reloadPending = false;
        try {
          localStorage.setItem(sessionKey, selectedId);

          window.addEventListener('storage', () => {
            if (reloadPending) {
              return;
            }
            const tabId = localStorage.getItem(sessionKey);
            // eslint-disable-next-line eqeqeq
            if (tabId && selectedId != tabId) {
              const msg = ss.i18n._t('Admin.SUBSITECHANGED', 'You\'ve changed subsite in another tab, do you want to reload the page?');
              reloadPending = true; // Don't trigger multiple confirm dialog
              // eslint-disable-next-line no-alert
              if (confirm(msg)) {
                window.location.reload();
              }
              // Don't ask again if cancelled
            }
          });
        } catch (e) {
          // Maybe storage is full or not available, disable this feature and ignore error
        }
      },

      onmatch() {
        this.detectSubsiteChange(this.find('option[selected]').attr('value'));
      },
      onadd() {
        this.on('change', function () {
          window.location.search = $.query.set('SubsiteID', $(this).val());
        });
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
      /**
       * TODO: Fix with Entwine API extension. See https://github.com/silverstripe/silverstripe-subsites/pull/125
       */
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
      onclick(e) {
        const form = this.closest('form');
        form.trigger('submit', [this]);
      }
    });
  });

  // eslint-disable-next-line no-shadow
  $.entwine('ss.preview', ($) => {
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
        $(doc).find('a').each(function () {
          const href = $(this).attr('href');

          if (typeof href !== 'undefined' && !href.match(/^http:\/\//)) {
            $(this).attr('href', $.path.addSearchParams(href, {
              SubsiteID: subsiteId
            }));
          }
        });

        // Inject the SubsiteID as a hidden input into all forms submitting to the local site.
        $(doc).find('form').each(function () {
          const action = $(this).attr('action');

          if (typeof action !== 'undefined' && !action.match(/^http:\/\//)) {
            $(this).append(`<input type=hidden name="SubsiteID" value="${subsiteId}" >`);
          }
        });
      }
    });
  });
}(jQuery));
