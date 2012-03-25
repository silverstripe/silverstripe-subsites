<?php
class GridFieldAddFromTemplateButton implements GridField_HTMLProvider {
	protected $targetFragment;
	
	public function __construct($targetFragment = 'before') {
		$this->targetFragment = $targetFragment;
	}
	
	public function getHTMLFragments($gridField) {
		$data = new ArrayData(array(
			'NewFromTemplateLink' => $gridField->Link('newFromTemplate'),
		));
		return array(
			$this->targetFragment => $data->renderWith('GridFieldAddFromTemplateButton'),
		);
	}

}

class GridFieldAddFromTemplate extends GridFieldDetailForm {
    public function getURLHandlers($gridField) {
        return array(
                    'newFromTemplate'=>'newFromTemplate',
                );
    }
    
    public function newFromTemplate($gridField, $request) {
		$controller = $gridField->getForm()->Controller();

		if(is_numeric($request->param('ID'))) {
			$record = $gridField->getList()->byId($request->param("ID"));
		} else {
			$record = Object::create($gridField->getModelClass());	
		}


		$handler = Object::create('GridFieldAddFromTemplate_ItemRequest', $gridField, $this, $record, $controller, $this->name);
		$handler->setTemplate($this->template);

		return $handler->handleRequest($request, DataModel::inst());
    }
}

class GridFieldAddFromTemplate_ItemRequest extends GridFieldDetailForm_ItemRequest {
	public function Link($action = null) {
		return $this->gridField->Link('newFromTemplate');
	}
    
    function edit($request) {
		$controller = $this->getToplevelController();
		$form = $this->NewFromTemplateForm($this->gridField, $request);

		$return = $this->customise(array(
			'Backlink' => $controller->Link(),
			'ItemEditForm' => $form,
		))->renderWith($this->template);

		if($controller->isAjax()) {
			return $return;	
		} else {
			// If not requested by ajax, we need to render it within the controller context+template
			return $controller->customise(array(
				// TODO CMS coupling
				'Content' => $return,
			));	
		}
	}
    
    public function NewFromTemplateForm() {
        $templates=DataObject::get('Subsite_Template');
        
        $fields=new FieldList(
                                new DropdownField('TemplateID', _t('GridFieldAddFromTemplate.TEMPLATE', '_Template'), $templates->map('ID', 'Name'))
                            );
        
        $actions=new FieldList(
                                FormAction::create('doCreateFromTemplate', _t('GridFieldDetailsForm.Create', 'Create'))->setUseButtonTag(true)->addExtraClass('ss-ui-action-constructive')->setAttribute('data-icon', 'add')
                            );
        
        // Add a Cancel link which is a button-like link and link back to one level up.
        $curmbs = $this->Breadcrumbs();
        if($curmbs && $curmbs->count()>=2){
            $one_level_up = $curmbs->offsetGet($curmbs->count()-2);
            $text = "
            <a class=\"crumb ss-ui-button ss-ui-action-destructive cms-panel-link ui-corner-all\" href=\"".$one_level_up->Link."\">
                Cancel
            </a>";
            $actions->push(new LiteralField('cancelbutton', $text));
        }
        
        $validator=new RequiredFields('TemplateID');
        
        $form=new Form($this, 'NewFromTemplateForm', $fields, $actions, $validator);
        
        // TODO Coupling with CMS
		$toplevelController = $this->getToplevelController();
		if($toplevelController && $toplevelController instanceof LeftAndMain) {
			// Always show with base template (full width, no other panels), 
			// regardless of overloaded CMS controller templates.
			// TODO Allow customization, e.g. to display an edit form alongside a search form from the CMS controller
			$form->setTemplate('LeftAndMain_EditForm');
			$form->addExtraClass('cms-content cms-edit-form center ss-tabset');
			if($form->Fields()->hasTabset()) $form->Fields()->findOrMakeTab('Root')->setTemplate('CMSTabSet');
			// TODO Link back to controller action (and edited root record) rather than index,
			// which requires more URL knowledge than the current link to this field gives us.
			// The current root record is held in session only, 
			// e.g. page/edit/show/6/ vs. page/edit/EditForm/field/MyGridField/....
			$form->Backlink = $toplevelController->Link();
		}
        
        return $form;
    }
    
    public function doCreateFromTemplate($data, Form $form) {
        $template=DataObject::get_by_id('Subsite_Template', intval($data['TemplateID']));
        
        if($template) {
            $subsite=$template->createInstance($data['Title']);
            $subsite->write();
            
            $this->record($subsite);
            return $this->redirect(parent::Link()); 
        }else {
            $form->sessionMessage(_t('GridFieldAddFromTemplate.TEMPLATE_NOT_FOUND', '_The selected template could not be found'), 'bad');
            return $this->redirectBack();
        }
    }

	/**
	 * CMS-specific functionality: Passes through navigation breadcrumbs
	 * to the template, and includes the currently edited record (if any).
	 * see {@link LeftAndMain->Breadcrumbs()} for details.
	 * 
	 * @param boolean $unlinked 
	 * @return ArrayData
	 */
	function Breadcrumbs($unlinked = false) {
		if(!$this->popupController->hasMethod('Breadcrumbs')) return;

		$items = $this->popupController->Breadcrumbs($unlinked);
        $items->push(new ArrayData(array(
            'Title' => sprintf(_t('GridFieldAddFromTemplate.NewFromTemplate', 'New %s from template'), $this->record->singular_name()),
            'Link' => false
        )));	
		
		return $items;
	}
}