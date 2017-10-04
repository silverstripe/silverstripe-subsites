<?php

namespace SilverStripe\Subsites\Admin;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Subsites\Forms\GridFieldSubsiteDetailForm;
use SilverStripe\Subsites\Model\Subsite;

/**
 * Admin interface to manage and create {@link Subsite} instances.
 *
 * @package subsites
 */
class SubsiteAdmin extends ModelAdmin
{
    private static $managed_models = [Subsite::class];

    private static $url_segment = 'subsites';

    private static $menu_title = 'Subsites';

    private static $menu_icon_class = 'font-icon-tree';

    public $showImportForm = false;

    private static $tree_class = Subsite::class;

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $grid = $form->Fields()->dataFieldByName(str_replace('\\', '-', Subsite::class));
        if ($grid) {
            $grid->getConfig()->getComponentByType(GridFieldPaginator::class)->setItemsPerPage(100);
            $grid->getConfig()->removeComponentsByType(GridFieldDetailForm::class);
            $grid->getConfig()->addComponent(new GridFieldSubsiteDetailForm());
        }

        return $form;
    }
}
