/* global jest, describe, it, expect */
import React from 'react';
import SubsiteChangeAlert from '../SubsiteChangeAlert';
import { configure, shallow } from 'enzyme';
import Adapter from 'enzyme-adapter-react-16';

configure({ adapter: new Adapter() });

describe('SubsiteChangeAlert', () => {
  describe('handleRevert', () => {
    it('delegates to the callback with relevant properties', () => {
      const callbackFn = jest.fn();
      const subsiteChangeAlertComponentInstance = shallow(
        <SubsiteChangeAlert
          currentSubsiteID="1"
          currentSubsiteName="one"
          onRevert={callbackFn}
        />
      );
      subsiteChangeAlertComponentInstance.instance().handleRevert();
      expect(callbackFn.mock.calls).toEqual([['1', 'one']]);
    });
  });
  describe('getMessage', () => {
    it('should show the old subsite name correctly', () => {
      const subsiteChangeAlertComponentInstance = shallow(
        <SubsiteChangeAlert
          currentSubsiteName="oldSite"
          newSubsiteName="newSite"
        />
      );
      expect(subsiteChangeAlertComponentInstance.instance().getMessage()[0]).toContain('back to "oldSite"');
    });
    it('should show the new active subsite name', () => {
      const subsiteChangeAlertComponentInstance = shallow(
        <SubsiteChangeAlert
          currentSubsiteName="oldSite"
          newSubsiteName="newSite"
        />
      );
      expect(subsiteChangeAlertComponentInstance.instance().getMessage()[0]).toContain('selected subsite "newSite"');
    });
  });
});
