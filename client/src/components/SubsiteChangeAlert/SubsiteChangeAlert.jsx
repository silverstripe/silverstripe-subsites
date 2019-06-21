import { Component } from 'react';
import PropTypes from 'prop-types';
import { Button, Modal, ModalHeader, ModalBody, ModalFooter } from 'reactstrap';
import i18n from 'i18n';

class SubsiteChangeAlert extends Component {
  constructor(props) {
    super(props);
    this.state = {
      modalOpen: true
    };

    this.toggle = this.toggle.bind(this);
  }

  toggle() {
    this.setState(prevState => ({
      modalOpen: !prevState.modalOpen
    }));
  }

  render() {
    const { newSubsiteID } = this.props;
    const { modalOpen } = this.state;

    return (
      <Modal isOpen={true} backdrop="static">
       <ModalHeader>Modal title</ModalHeader>
        <ModalBody>
          {
            i18n.inject(
              i18n._t(
                'SubsiteChangeAlert.SUBSITE_CHANGED',
                `The subsite has changed, continuing to edit will cause problems.
                To continue editing please set the subsite ID back to the original one in the tab you chaned it in.`
              ),
              { id: newSubsiteID }
            )
          }
        </ModalBody>
      </Modal>
    );
  }
}

SubsiteChangeAlert.propTypes = {
  newSubsiteID: PropTypes.number,
}

export default SubsiteChangeAlert;
