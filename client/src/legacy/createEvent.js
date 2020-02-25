/* global document, Event */

/**
 * Helper function for IE11 - create custom Events in a consistent way
 *
 * @param {String} type
 * @param {Object} extraData
 */
function createEvent(type, extraData) {
  let event;
  if (typeof Event === 'object') {
    event = document.createEvent('Event', true, true);
    event.initEvent(type);
  } else {
    event = new Event(type);
  }
  if (extraData) {
    Object.keys(extraData).forEach((key) => {
      event[key] = extraData[key];
    });
  }
  return event;
}

export default createEvent;
