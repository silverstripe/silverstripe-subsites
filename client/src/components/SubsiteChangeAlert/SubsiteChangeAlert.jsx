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

    this.revertActiveSubsite = this.revertActiveSubsite.bind(this);
  }

  revertActiveSubsite() {
    const { localStorage } = window;
    const request = new XMLHttpRequest();
    const subsiteForThisTab = window.document.getElementById('SubsitesSelect').value;
    request.open('GET', '?SubsiteID=' + subsiteForThisTab);
    request.addEventListener('load', () => {
      localStorage.setItem('subsiteID', subsiteForThisTab);
      window.dispatchEvent(new Event('subsitechange'));
    });
    request.send();

    // this.setState(prevState => ({
    //   modalOpen: !prevState.modalOpen
    // }));
  }

  render() {
    const { newSubsiteID, newSubsiteName } = this.props;
    const { modalOpen } = this.state;

    return (
      <Modal isOpen={true} backdrop="static">
        <ModalHeader>
          {i18n._t('SubsiteChangeAlert.SUBSITE_CHANGED_TITLE', 'Subsite changed')}
        </ModalHeader>
        <ModalBody>
          {
            i18n.inject(
              i18n._t(
                'SubsiteChangeAlert.SUBSITE_CHANGED',
                `Your current subsite has changed to {newSubsiteName}, continuing to edit this content will cause problems.
                To continue editing {thisSubsite}, please change the active subsite back.`
              ),
              {
                id: newSubsiteID,
                newSubsiteName
              }
            )
          }
        </ModalBody>
        <ModalFooter>
          <Button color="danger" onClick={this.revertActiveSubsite}>
            {i18n._t(SubsiteChangeAlert.REVERT, 'Change back')}
          </Button>
        </ModalFooter>
      </Modal>
    );
  }
}

SubsiteChangeAlert.propTypes = {
  newSubsiteID: PropTypes.number,
}

export default SubsiteChangeAlert;
