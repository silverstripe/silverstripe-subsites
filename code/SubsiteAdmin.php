<?php
//@TODO Add form is not used
//@TODO doCreate() is not used
/**
 * Admin interface to manage and create {@link Subsite} instances.
 * 
 * @package subsites
 */
class SubsiteAdmin extends ModelAdmin {
	
	static $managed_models = array('Subsite', 'Subsite_Template');
	static $url_segment = 'subsites';
	
	static $menu_title = "Subsites";
    
    public $showImportForm=false;
	
	public function getEditForm($id = null) {
		$form = parent::getEditForm($id);
		
        if($this->modelClass=='Subsite') {
            $grid=$form->Fields()->dataFieldByName('Subsite');
            if($grid) {
                $grid->getConfig()->addComponent(new GridFieldAddFromTemplateButton('toolbar-header-right'));
                $grid->getConfig()->addComponent(new GridFieldAddFromTemplate());
            }
        }
        
        
		return $form;
	}
}
?>
