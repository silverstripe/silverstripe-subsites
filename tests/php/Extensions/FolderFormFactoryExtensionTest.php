<?php

namespace SilverStripe\Subsites\Tests\Extensions;

use SilverStripe\AssetAdmin\Forms\FolderFormFactory;
use SilverStripe\Assets\Folder;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormFactory;

class FolderFormFactoryExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'FolderFormFactoryExtensionTest.yml';

    public function testSubsitesFolderDropdown()
    {
        $this->logInWithPermission('ADMIN');

        /** @var Folder $folder */
        $folder = $this->objFromFixture(Folder::class, 'folder_a');

        /** @var Form $folderForm */
        $folderForm = FolderFormFactory::create()->getForm(null, FormFactory::DEFAULT_NAME, [
            'Record' => $folder
        ]);

        $source = array_values($folderForm->Fields()->fieldByName('SubsiteID')->getSource());
        $result = array_values($source);

        $this->assertContains('Main site', $result);
        $this->assertContains('Subsite A', $result);
        $this->assertContains('Subsite B', $result);
    }
}
