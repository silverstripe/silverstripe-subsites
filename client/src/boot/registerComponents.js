import Injector from 'lib/Injector';
import SubsiteChangeAlert from 'components/SubsiteChangeAlert/SubsiteChangeAlert';

export default () => {
  Injector.component.registerMany({
    // List your React components here so Injector is aware of them
    SubsiteChangeAlert
  });
};
