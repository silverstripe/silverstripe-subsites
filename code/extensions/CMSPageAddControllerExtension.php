<?php
class CMSPageAddControllerExtension extends Extension
{
    public function updatePageOptions(&$fields)
    {
        $fields->push(new HiddenField('SubsiteID', 'SubsiteID', Subsite::currentSubsiteID()));
    }
}
