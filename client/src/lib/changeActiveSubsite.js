import createEvent from 'legacy/createEvent';

export default function changeActiveSubsite(toSubsiteID, toSubsiteName) {
  const { localStorage } = window;
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
    localStorage.setItem('subsiteInfo', storageValue);
    // update this tab about the change
    window.dispatchEvent(createEvent('subsitechange', { subsiteInfo: storageValue }));
  });
  request.send();
}
