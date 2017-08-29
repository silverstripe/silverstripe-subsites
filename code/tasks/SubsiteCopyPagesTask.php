<?php

namespace SilverStripe\Subsites\Tasks;

use InvalidArgumentException;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\Pages\SubsitesVirtualPage;
use SilverStripe\Versioned\Versioned;

/**
 * Handy alternative to copying pages when creating a subsite through the UI.
 *
 * Can be used to batch-add new pages after subsite creation, or simply to
 * process a large site outside of the UI.
 *
 * Example: sake dev/tasks/SubsiteCopyPagesTask from=<subsite-source> to=<subsite-target>
 *
 * @package subsites
 */
class SubsiteCopyPagesTask extends BuildTask
{
    protected $title = 'Copy pages to different subsite';
    protected $description = '';

    public function run($request)
    {
        $subsiteFromId = $request->getVar('from');
        if (!is_numeric($subsiteFromId)) {
            throw new InvalidArgumentException('Missing "from" parameter');
        }
        $subsiteFrom = DataObject::get_by_id(Subsite::class, $subsiteFromId);
        if (!$subsiteFrom) {
            throw new InvalidArgumentException('Subsite not found');
        }

        $subsiteToId = $request->getVar('to');
        if (!is_numeric($subsiteToId)) {
            throw new InvalidArgumentException('Missing "to" parameter');
        }
        $subsiteTo = DataObject::get_by_id(Subsite::class, $subsiteToId);
        if (!$subsiteTo) {
            throw new InvalidArgumentException('Subsite not found');
        }

        $useVirtualPages = (bool)$request->getVar('virtual');

        Subsite::changeSubsite($subsiteFrom);

        // Copy data from this template to the given subsite. Does this using an iterative depth-first search.
        // This will make sure that the new parents on the new subsite are correct, and there are no funny
        // issues with having to check whether or not the new parents have been added to the site tree
        // when a page, etc, is duplicated
        $stack = [[0, 0]];
        while (count($stack) > 0) {
            list($sourceParentID, $destParentID) = array_pop($stack);

            $children = Versioned::get_by_stage(SiteTree::class, 'Live', "\"ParentID\" = $sourceParentID", '');

            if ($children) {
                foreach ($children as $child) {
                    if ($useVirtualPages) {
                        $childClone = new SubsitesVirtualPage();
                        $childClone->writeToStage('Stage');
                        $childClone->CopyContentFromID = $child->ID;
                        $childClone->SubsiteID = $subsiteTo->ID;
                    } else {
                        $childClone = $child->duplicateToSubsite($subsiteTo->ID, true);
                    }

                    $childClone->ParentID = $destParentID;
                    $childClone->writeToStage('Stage');
                    $childClone->copyVersionToStage('Stage', 'Live');
                    array_push($stack, [$child->ID, $childClone->ID]);

                    $this->log(sprintf('Copied "%s" (#%d, %s)', $child->Title, $child->ID, $child->Link()));
                }
            }

            unset($children);
        }
    }

    public function log($msg)
    {
        echo $msg . "\n";
    }
}
