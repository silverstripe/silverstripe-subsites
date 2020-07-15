/* global window */
import React, { Component } from 'react';
import PropTypes from 'prop-types';
import { Button, Modal, ModalHeader, ModalBody, ModalFooter } from 'reactstrap';
import i18n from 'i18n';
import url from 'url';

class SubsiteChangeAlert extends Component {
  constructor(props) {
    super(props);
    this.handleRevert = this.handleRevert.bind(this);
    this.handleReload = this.handleReload.bind(this);
  }

  /**
   * Gets a translated string to display to the user
   */
  getMessage() {
    const { newSubsiteName, currentSubsiteName } = this.props;

    return i18n.inject(
      i18n._t(
        'SubsiteChangeAlert.SUBSITE_CHANGED',
        `You have selected subsite "{newSubsiteName}" in another browser tab. In order to continue editing this content,
        you must change the active subsite back to "{currentSubsiteName}".[SPLIT]Alternatively, you can reload this tab to switch to
        subsite "{newSubsiteName}". Any unsaved changes you have made to this content will be lost.`
      ),
      {
        newSubsiteName,
        currentSubsiteName
      }
    ).split('[SPLIT]');
  }

  /**
   * Changes active subsite back what the page was using before the server side session was altered
   */
  handleRevert() {
    const { currentSubsiteID, currentSubsiteName, onRevert } = this.props;
    onRevert(currentSubsiteID, currentSubsiteName);
  }

  handleReload() {
    const { newSubsiteID } = this.props;
    const currentURL = url.parse(window.location.toString(), true);

    currentURL.query.SubsiteID = newSubsiteID;
    window.location = url.format(currentURL);
  }

  render() {
    return (
      <Modal isOpen backdrop="static">
        <ModalHeader>
          {i18n._t('SubsiteChangeAlert.SUBSITE_CHANGED_TITLE', 'Subsite changed')}
        </ModalHeader>
        <ModalBody>{this.getMessage().map(p => (<p key={p}>{p}</p>))}</ModalBody>
        <ModalFooter>
          <Button color="primary" onClick={this.handleRevert}>
            {i18n._t('SubsiteChangeAlert.REVERT', 'Change back')}
          </Button>
          <Button color="danger" onClick={this.handleReload}>
            {i18n._t('SubsiteChangeAlert.REVERT', 'Reload')}
          </Button>
        </ModalFooter>
      </Modal>
    );
  }
}

SubsiteChangeAlert.propTypes = {
  newSubsiteID: PropTypes.string,
  newSubsiteName: PropTypes.string,
  currentSubsiteID: PropTypes.string,
  currentSubsiteName: PropTypes.string,
  onRevert: PropTypes.func,
};

export default SubsiteChangeAlert;
