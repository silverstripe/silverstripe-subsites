<?php

namespace SilverStripe\Subsites\Tests;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Member;
use SilverStripe\Subsites\Extensions\FileSubsites;
use SilverStripe\Subsites\Model\Subsite;

class FileSubsitesTest extends BaseSubsiteTest
{
    protected static $fixture_file = 'SubsiteTest.yml';

    /**
     * Disable other file extensions
     *
     * @var array
     */
    protected static $illegal_extensions = [
        SiteTree::class => ['Translatable'], // @todo implement namespace for Translatable
    ];

    public function testTrivialFeatures()
    {
        $this->assertTrue(is_array(singleton(FileSubsites::class)->extraStatics()));
        $file = new File();
        $file->Name = 'FileTitle';
        $file->Title = 'FileTitle';
        $this->assertEquals(' * FileTitle', $file->alternateTreeTitle());
        $file->SubsiteID = $this->objFromFixture(Subsite::class, 'domaintest1')->ID;
        $this->assertEquals('FileTitle', $file->getTreeTitle());
        $this->assertInstanceOf(FieldList::class, singleton(Folder::class)->getCMSFields());
        Subsite::changeSubsite(1);
        $this->assertEquals('subsite-1', $file->getExtensionInstance(FileSubsites::class)->cacheKeyComponent());
    }

    public function testWritingSubsiteID()
    {
        $this->logInAs('admin');

        $subsite = $this->objFromFixture(Subsite::class, 'domaintest1');
        FileSubsites::$default_root_folders_global = true;

        Subsite::changeSubsite(0);
        $file = new File();
        $file->write();
        $file->onAfterUpload();
        $this->assertEquals((int)$file->SubsiteID, 0);

        Subsite::changeSubsite($subsite->ID);
        $this->assertTrue($file->canEdit());

        $file = new File();
        $file->write();
        $this->assertEquals((int)$file->SubsiteID, 0);
        $this->assertTrue($file->canEdit());

        FileSubsites::$default_root_folders_global = false;

        Subsite::changeSubsite($subsite->ID);
        $file = new File();
        $file->write();
        $this->assertEquals($file->SubsiteID, $subsite->ID);

        // Test inheriting from parent folder
        $folder = new Folder();
        $folder->write();
        $this->assertEquals($folder->SubsiteID, $subsite->ID);
        FileSubsites::$default_root_folders_global = true;
        $file = new File();
        $file->ParentID = $folder->ID;
        $file->onAfterUpload();
        $this->assertEquals($folder->SubsiteID, $file->SubsiteID);
    }

    public function testSubsitesFolderDropdown()
    {
        $this->logInAs('admin');

        $file = new Folder();

        $source = array_values($file->getCMSFields()->dataFieldByName('SubsiteID')->getSource());
        asort($source);

        $this->assertEquals([
            'Main site',
            'Subsite1 Template',
            'Subsite2 Template',
            'Template',
            'Test 1',
            'Test 2',
            'Test 3',
            'Test Non-SSL',
            'Test SSL',
            'Test Vagrant VM on port 8080'
        ], array_values($source));
    }
}
