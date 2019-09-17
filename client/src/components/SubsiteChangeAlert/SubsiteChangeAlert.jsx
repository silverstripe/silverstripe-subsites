/* global window */
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
    const { localStorage, document } = window;
    const request = new XMLHttpRequest();
    const subsiteSelector = document.getElementById('SubsitesSelect');
    const subsiteIdForThisTab = subsiteSelector.value;
    const subsiteNameForThisTab = subsiteSelector.options[subsiteSelector.selectedIndex].text;
    const subsiteInfo = {
      subsiteID: subsiteIdForThisTab,
      subsiteName: subsiteNameForThisTab
    }
    request.open('GET', '?SubsiteID=' + subsiteIdForThisTab);
    // load event is not called for error states (e.g. 500 codes, etc.)
    request.addEventListener('load', () => {
      // notify all other tabs about the change
      localStorage.setItem('subsiteInfo', JSON.stringify(subsiteInfo));
      // update this tab about the change
      window.dispatchEvent(new Event('subsitechange'));
    });
    request.send();
  }

  render() {
    const { newSubsiteID, newSubsiteName, thisSubsite } = this.props;

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
                newSubsiteName,
                thisSubsite
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
  newSubsiteName: PropTypes.string,
  thisSubsite: PropTypes.string
}

export default SubsiteChangeAlert;
