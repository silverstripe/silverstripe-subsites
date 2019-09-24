/* global window */
// Polyfill IE11
function createEvent(type, extraData) {
  const { document, Event } = window;
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
