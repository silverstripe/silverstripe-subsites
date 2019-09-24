/* global jest, describe, it, expect */
import createEvent from '../createEvent';

describe('changeEvent', () => {
  it('creates Event Objects', () => {
    expect(createEvent('click')).toBeInstanceOf(Event);
  });
  it('stores arbitrary extra data on events created', () => {
    const event = createEvent('load', { one: 1, two: 'rua' });
    expect(event.one).toBe(1);
    expect(event.two).toBe('rua');
  });
  it('creates custom event types', () => {
    expect(createEvent('customType').type).toBe('customType');
  });
});
