<?php
/**
 * Admin interface to manage and create {@link Subsite} instances.
 * 
 * @package subsites
 */
class SubsiteAdmin extends GenericDataAdmin {
	
	static $tree_class = "Subsite";
	static $subitem_class = "Subsite";
	static $data_type = 'Subsite';
	
	static $url_segment = 'subsites';
	
	static $url_rule = '/$Action/$ID/$OtherID';
	
	static $menu_title = 'Subsites';
	
	function performSearch() {
		
	}
	
	function getSearchFields() {
		return singleton('Subsite')->adminSearchFields();
	}
	
	function getLink() {
		return 'admin/subsites/';
	}
	
	function Link() {
		return $this->getLink();
	}
	
	function Results($data = null) {
		if(!$data) $data = $this->requestParams;
		
		$where = '';
		if(isset($data['Name']) && $data['Name']) {
			$SQL_name = Convert::raw2sql($data['Name']);
			$where = "`Title` LIKE '%$SQL_name%'";
		} else {
			$where = "`Title` != ''";
		}
		
		$intranets = DataObject::get('Subsite', $where, "if(ClassName = 'Subsite_Template',0,1), Title");
		if(!$intranets)
			return null;
			
		$html = "<table class=\"ResultTable\"><thead><tr><th>Name</th><th>Domain</th></tr></thead><tbody>";
		
		$numIntranets = 0;
		foreach($intranets as $intranet) {
			$numIntranets++;
			$evenOdd = ($numIntranets % 2) ? 'odd':'even';
			$prefix = ($intranet instanceof Subsite_Template) ? " * " : "";
			$html .= "<tr class=\"$evenOdd\"><td><a class=\"show\" href=\"admin/subsites/show/{$intranet->ID}\">$prefix{$intranet->Title}</a></td><td><a class=\"show\" href=\"admin/subsites/show/{$intranet->ID}\">{$intranet->Subdomain}.{$intranet->Domain}</a></td></tr>";
		}
		$html .= "</tbody></table>";
		return $html;
	}
	
	/**
	 * Returns the form for adding subsites.
	 * @returns Form A nerw form object
	 */
	function AddSubsiteForm() {
		$templates = $this->getIntranetTemplates();
	
		$templateArray = array('' => "(No template)");
		if($templates) {
			$templateArray = $templateArray + $templates->map('ID', 'Title');
		}
		
		return new Form($this, 'AddSubsiteForm', new FieldSet(
			new TextField('Name', 'Name:'),
			new TextField('Subdomain', 'Subdomain:'),
			new DropdownField('Type', 'Type', array(
				'subsite' => 'New site',
				'template' => 'New template',
			)),
			new DropdownField('TemplateID', 'Use template:', $templateArray)//,
			/*new TextField('AdminName', 'Admin name:'),
			new EmailField('AdminEmail', 'Admin email:')*/
		),
		new FieldSet(
			new FormAction('addintranet', 'Add')
		));
	}
	
	public function getIntranetTemplates() {
		return DataObject::get('Subsite_Template', '', 'Title');
	}
	
	function addintranet($data, $form) {
		if($data['Name'] && ($data['Subdomain'] || $data['Type'] == 'template')) {
			if(isset($data['TemplateID']) && $data['TemplateID']) {
				$template = DataObject::get_by_id('Subsite_Template', $data['TemplateID']);
			} else {
				$template = null;
			}
		
			// Create intranet from existing template
			switch($data['Type']) {
				case 'template':
					if($template) $intranet = $template->duplicate();
					else $intranet = new Subsite_Template();
					
					$intranet->Title = $data['Name'];
					$intranet->write();
					break;

				case 'subsite':
				default:
					if($template) $intranet = $template->createInstance($data['Name'], $data['Subdomain']);		
					else {
						$intranet = new Subsite();
						$intranet->Title = $data['Name'];
						$intranet->Subdomain = $data['Subdomain'];
						$intranet->write();
					}
					break;
			}
		
			Director::redirect('admin/subsites/show/' . $intranet->ID);
		} else {
			if($data['Type'] == 'template') echo "You must provide a name for your new template.";
			else echo "You must provide a name and subdomain for your new site.";
		}
	}

	/**
	 * Use this as an action handler for custom CMS buttons.
	 */
	function callPageMethod2($data, $form) {
		return $this->callPageMethod($data, $form);
	}
}
?>
