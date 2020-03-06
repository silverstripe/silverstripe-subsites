import Injector from 'lib/Injector';
import SubsiteChangeAlert from 'components/SubsiteChangeAlert/SubsiteChangeAlert';

export default () => {
  Injector.component.registerMany({
    SubsiteChangeAlert
  });
};
