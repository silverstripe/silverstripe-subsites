/* global window */
import createEvent from 'legacy/createEvent';

/**
 * Sends out an XHR to update the session on the server which holds the active Subsite ID.
 * This will not update the CMS in itself nor reload the window - it will just keep the
 * localstorage in sync with the active Subsite ID. The localstorage event may in turn trigger
 * other UI widgets to update however.
 *
 * @param {String} toSubsiteID
 * @param {String} toSubsiteName
 */
export default function changeActiveSubsite(toSubsiteID, toSubsiteName) {
  const request = new XMLHttpRequest();
  const subsiteInfo = {
    subsiteID: toSubsiteID,
    subsiteName: toSubsiteName
  };
  request.open('GET', `?SubsiteID=${toSubsiteID}`);
  // load event is not called for error states (e.g. 500 codes, etc.)
  request.addEventListener('load', () => {
    const storageValue = JSON.stringify(subsiteInfo);
    // notify all other tabs about the change
    window.localStorage.setItem('subsiteInfo', storageValue);
    // update this tab about the change
    window.dispatchEvent(createEvent('subsitechange', { subsiteInfo: storageValue }));
  });
  request.send();
}
