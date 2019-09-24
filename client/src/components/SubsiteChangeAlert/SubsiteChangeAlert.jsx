/* global window */
import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { Button, Modal, ModalHeader, ModalBody, ModalFooter } from 'reactstrap';
import i18n from 'i18n';
import createEvent from 'legacy/createEvent';

class SubsiteChangeAlert extends Component {
  constructor(props) {
    super(props);
    this.handleRevert = this.handleRevert.bind(this);
  }

  handleRevert() {
    const { myTabSubsiteID, myTabSubsiteName, revertCallback } = this.props;
    revertCallback(myTabSubsiteID, myTabSubsiteName);
  }

  getMessage() {
    const { otherTabSubsiteName, myTabSubsiteName } = this.props;

    return i18n.inject(
      i18n._t(
        'SubsiteChangeAlert.SUBSITE_CHANGED',
        `Your current subsite has changed to {otherTabSubsiteName}, continuing to edit this content will cause problems.
        To continue editing {myTabSubsiteName}, please change the active subsite back.`
      ),
      {
        otherTabSubsiteName,
        myTabSubsiteName
      }
    );
  }

  render() {
    return (
      <Modal isOpen={true} backdrop="static">
        <ModalHeader>
          {i18n._t('SubsiteChangeAlert.SUBSITE_CHANGED_TITLE', 'Subsite changed')}
        </ModalHeader>
        <ModalBody>{this.getMessage()}</ModalBody>
        <ModalFooter>
          <Button color="danger" onClick={this.handleRevert}>
            {i18n._t(SubsiteChangeAlert.REVERT, 'Change back')}
          </Button>
        </ModalFooter>
      </Modal>
    );
  }
}

SubsiteChangeAlert.propTypes = {
  otherTabSubsiteName: PropTypes.string,
  myTabSubsiteID: PropTypes.string,
  myTabSubsiteName: PropTypes.string,
  revertCallback: PropTypes.func,
}

export default SubsiteChangeAlert;
