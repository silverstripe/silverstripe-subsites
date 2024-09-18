<?php

namespace SilverStripe\Subsites\Tests;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Subsites\Extensions\FileSubsites;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Security\Member;
use ReflectionMethod;
use PHPUnit\Framework\Attributes\DataProvider;

class FileSubsitesTest extends BaseSubsiteTest
{
    protected static $fixture_file = 'SubsiteTest.yml';

    public function testTrivialFeatures()
    {
        $file = new File();
        $file->Name = 'FileTitle';
        $file->Title = 'FileTitle';
        $this->assertEquals(' * FileTitle', $file->alternateTreeTitle());
        $file->SubsiteID = $this->objFromFixture(Subsite::class, 'domaintest1')->ID;
        $this->assertEquals('FileTitle', $file->getTreeTitle());
        $this->assertInstanceOf(FieldList::class, singleton(Folder::class)->getCMSFields());
        Subsite::changeSubsite(1);
        $ext = $file->getExtensionInstance(FileSubsites::class);
        $method = new ReflectionMethod(FileSubsites::class, 'cacheKeyComponent');
        $method->setAccessible(true);
        $result = $method->invoke($ext);
        $this->assertEquals('subsite-1', $result);
    }

    public function testWritingSubsiteID()
    {
        $this->logInAs('admin');

        $subsite = $this->objFromFixture(Subsite::class, 'domaintest1');
        Config::modify()->set(FileSubsites::class, 'default_root_folders_global', true);

        $method = new ReflectionMethod(File::class, 'onAfterUpload');
        $method->setAccessible(true);

        Subsite::changeSubsite(0);
        $file = new File();
        $file->write();
        $method->invoke($file);
        $this->assertEquals((int)$file->SubsiteID, 0);

        Subsite::changeSubsite($subsite->ID);
        $this->assertTrue($file->canEdit());

        $file = new File();
        $file->write();
        $this->assertEquals((int)$file->SubsiteID, 0);
        $this->assertTrue($file->canEdit());

        Config::modify()->set(FileSubsites::class, 'default_root_folders_global', false);

        Subsite::changeSubsite($subsite->ID);
        $file = new File();
        $file->write();
        $this->assertEquals($file->SubsiteID, $subsite->ID);

        // Test inheriting from parent folder
        $folder = new Folder();
        $folder->write();
        $this->assertEquals($folder->SubsiteID, $subsite->ID);
        Config::modify()->set(FileSubsites::class, 'default_root_folders_global', true);
        $file = new File();
        $file->ParentID = $folder->ID;
        $method->invoke($file);
        $this->assertEquals($folder->SubsiteID, $file->SubsiteID);
    }

    #[DataProvider('provideTestCanEdit')]
    public function testCanEdit(
        string $fileKey,
        string $memberKey,
        string $currentSubsiteKey,
        bool $expected
    ): void {
        $file = $this->objFromFixture(File::class, $fileKey);
        $subsiteID = ($currentSubsiteKey === 'mainsite')
            ? 0 : $this->objFromFixture(Subsite::class, $currentSubsiteKey)->ID;
        $member = $this->objFromFixture(Member::class, $memberKey);
        Subsite::changeSubsite($subsiteID);
        $this->assertSame($expected, $file->canEdit($member));
    }

    public static function provideTestCanEdit(): array
    {
        $ret = [];
        $data = [
            // file
            'subsite1file' => [
                // member - has permissions to edit the file
                'filetestyes' => [
                    // current subite => expected canEdit()
                    'subsite1' => true,
                    'subsite2' => false,
                    'mainsite' => true
                ],
                // member - does not have permissions to edit the file
                'filetestno' => [
                    'subsite1' => false,
                    'subsite2' => false,
                    'mainsite' => false
                ],
            ],
            'mainsitefile' => [
                'filetestyes' => [
                    'subsite1' => true,
                    'subsite2' => true,
                    'mainsite' => true
                ],
                'filetestno' => [
                    'subsite1' => false,
                    'subsite2' => false,
                    'mainsite' => false
                ],
            ]
        ];
        foreach (array_keys($data) as $fileKey) {
            foreach (array_keys($data[$fileKey]) as $memberKey) {
                foreach ($data[$fileKey][$memberKey] as $currentSubsiteKey => $expected) {
                    $ret[] = [$fileKey, $memberKey, $currentSubsiteKey, $expected];
                }
            }
        }
        return $ret;
    }
}
