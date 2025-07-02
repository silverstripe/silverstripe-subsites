<?php

namespace SilverStripe\Subsites\Tasks;

use Closure;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\ORM\DataObject;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Subsites\Pages\SubsitesVirtualPage;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Handy alternative to copying pages when creating a subsite through the UI.
 *
 * Can be used to batch-add new pages after subsite creation, or simply to
 * process a large site outside of the UI.
 *
 * Example: sake tasks:SubsiteCopyPagesTask --from=<subsite-source> --to=<subsite-target>
 *
 * @package subsites
 */
class SubsiteCopyPagesTask extends BuildTask
{
    protected string $title = 'Copy pages to different subsite';

    protected static string $description = 'Handy alternative to copying pages when creating a subsite through the UI';

    protected static string $commandName = 'SubsiteCopyPagesTask';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $subsiteFromId = $input->getOption('from');
        if (!is_numeric($subsiteFromId)) {
            $output->writeln('<error>Missing "from" parameter</>');
            return Command::INVALID;
        }
        $subsiteFrom = Subsite::get()->setUseCache(true)->byID($subsiteFromId);
        if (!$subsiteFrom) {
            $output->writeln('<error>Subsite not found</>');
            return Command::FAILURE;
        }

        $subsiteToId = $input->getOption('to');
        if (!is_numeric($subsiteToId)) {
            $output->writeln('<error>Missing "to" parameter</>');
            return Command::INVALID;
        }
        $subsiteTo = Subsite::get()->setUseCache(true)->byID($subsiteToId);
        if (!$subsiteTo) {
            $output->writeln('<error>Subsite not found</>');
            return Command::FAILURE;
        }

        $useVirtualPages = $input->getOption('virtual');

        Subsite::changeSubsite($subsiteFrom);

        // Copy data from this template to the given subsite. Does this using an iterative depth-first search.
        // This will make sure that the new parents on the new subsite are correct, and there are no funny
        // issues with having to check whether or not the new parents have been added to the site tree
        // when a page, etc, is duplicated
        $stack = [[0, 0]];
        while (count($stack ?? []) > 0) {
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

                    $output->writeln(sprintf('Copied "%s" (#%d, %s)', $child->Title, $child->ID, $child->Link()));
                }
            }

            unset($children);
        }

        return Command::SUCCESS;
    }

    public function getOptions(): array
    {
        $subsiteSuggestionClosure = Closure::fromCallable([static::class, 'getSubsiteCompletion']);
        return [
            new InputOption(
                'from',
                null,
                InputOption::VALUE_REQUIRED,
                'ID of the subsite to copy from',
                suggestedValues: $subsiteSuggestionClosure
            ),
            new InputOption(
                'to',
                null,
                InputOption::VALUE_REQUIRED,
                'ID of the subsite to copy to',
                suggestedValues: $subsiteSuggestionClosure
            ),
            new InputOption(
                'virtual',
                null,
                InputOption::VALUE_NONE,
                'Create virtual pages instead of duplicating pages'
            ),
        ];
    }

    public static function getSubsiteCompletion(): array
    {
        $subsites = Subsite::get()->map('ID', 'Title');
        $suggestions = [];
        foreach ($subsites as $id => $title) {
            $suggestions[] = "{$id}\t{$title}";
        }
        return $suggestions;
    }
}
