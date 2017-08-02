<?php

class FileSubsitesTest extends BaseSubsiteTest {
	static $fixture_file = 'subsites/tests/SubsiteTest.yml';
	
	function testTrivialFeatures() {
		$this->assertTrue(is_array(singleton('FileSubsites')->extraStatics()));
		$file = new File();
		$file->Name = 'FileTitle';
		$file->Title = 'FileTitle';
		$this->assertEquals(' * FileTitle', $file->alternateTreeTitle());
		$file->SubsiteID = $this->objFromFixture('Subsite', 'domaintest1')->ID;
		$this->assertEquals('FileTitle', $file->getTreeTitle());
		$this->assertTrue(singleton('Folder')->getCMSFields() instanceof FieldList);
		Subsite::changeSubsite(1);
		$this->assertEquals($file->cacheKeyComponent(), 'subsite-1');
	}
	
	function testWritingSubsiteID() {
		$this->objFromFixture('Member', 'admin')->logIn();
		
		$subsite = $this->objFromFixture('Subsite', 'domaintest1');
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

	function testSubsitesFolderDropdown() {
		$this->objFromFixture('Member', 'admin')->logIn();

		$file = new Folder();

		$source = array_values($file->getCMSFields()->dataFieldByName('SubsiteID')->getSource());
		asort($source);

		$this->assertEquals(array(
			'Main site',
			'Template',
			'Subsite1 Template',
			'Subsite2 Template',
			'Test 1',
			'Test 2',
			'Test 3'
		), $source);
	}
	
}
