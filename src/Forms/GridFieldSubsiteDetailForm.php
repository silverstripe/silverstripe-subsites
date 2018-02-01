<?php

namespace SilverStripe\Subsites\Forms;

use SilverStripe\Forms\GridField\GridFieldDetailForm;

class GridFieldSubsiteDetailForm extends GridFieldDetailForm
{
    protected $itemRequestClass = GridFieldSubsiteDetailFormItemRequest::class;
}
