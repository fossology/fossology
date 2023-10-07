#!/usr/bin/php

<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/
/*
 *  first try at testing web interface...
 */

class TestRepoLogin extends WebTestCase{

  function testLogin(){
    $this->useProxy('http://web-proxy.fc.hp.com:8088', 'web-proxy', '');
    $this->assertTrue($this->get('http://repo.fossology.org/'));
    $this->assertAuthentication('Basic');
    $this->authenticate('fossology', 'xxxxxxxx');
    $this->assertText('Software Repository Viewer');
  }
}
class TestAboutMenu extends WebTestCase {

  function testMenuAbout(){
    $this->useProxy('http://web-proxy.fc.hp.com:8088', 'web-proxy', '');
    $this->assertTrue($this->get('http://repo.fossology.org/'));
    $this->assertAuthentication('Basic');
    $this->authenticate('fossology', 'xxxxxxx');
    $this->assertText('Software Repository');

    $this->click('About');
    $this->assertText('FOSSology');
  }
}
/*
 * NOTE: the url is for the internal machines... fix this!
 * they fail when run from home, can't seem to get a url to work on doc....
 * They work when run from sirius.
 */
class TestToolsMenu extends WebTestCase {

  function testMenuTools(){
    $this->assertTrue($this->get('http://osrb-1.fc.hp.com/~markd/ui-md/'));
    //$this->assertAuthentication('Basic');
    //$this->authenticate('fossology', 'xxxxxxx');
    $this->assertText('Software Repository');

    $this->click('Tools');
    $this->clicklink('Folders (refresh)', 0);
  }
}

class TestOrganizeFolders extends WebTestCase {

  function testFolderCreate(){
    $this->assertTrue($this->get('http://osrb-1.fc.hp.com/~markd/ui-md/'));
    //$this->assertAuthentication('Basic');
    //$this->authenticate('fossology', 'xxxxxxxx');
    $this->assertText('Software Repository');

    $this->click('Organize');
    $this->click('Folders');
    $this->click('Create');
    $this->assertText('Create a new');
    $this->assertField('parentid', '1');
  }
  /*
   * testFolderMove assumes that folder created in testFolderCreate above
   * exists.
   */
  function testFolderMove(){
    $this->assertTrue($this->get('http://osrb-1.fc.hp.com/~markd/ui-md/'));
    //$this->assertAuthentication('Basic');
    //$this->authenticate('fossology', 'xxxxxxxx');
    $this->assertText('Software Repository');

    $this->click('Organize');
    $this->click('Folders');
    $this->click('Move');
    $this->assertText('Move Folder');
  }

  function createfolder($name) {
    // Web page load
    $this->assertTrue($this->get('http://osrb-1.fc.hp.com/~markd/ui-md/'));
    //$this->assertAuthentication('Basic');
    //$this->authenticate('fossology', 'xxxxxxxx');
    $this->assertText('Software Repository');
    // Navigate test (plugin is there)
    $this->click('Organize');
    $this->click('Folders');
    $this->setField('parentid', 'Software Repository');
    $this->setField('newname', '$name');
    $this->setField('description', 'edit properties Test folder');
    $this->clickSubmit('Create!');
  }

  function testEditProperties(){
    // Web page load
    $this->assertTrue($this->get('http://osrb-1.fc.hp.com/~markd/ui-md/'));
    //$this->assertAuthentication('Basic');
    //$this->authenticate('fossology', 'xxxxxxxx');
    $this->assertText('Software Repository');
    // Navigate test (plugin is there)
    $this->click('Organize');
    $this->click('Folders');
    $this->click('Edit Properties');
    $this->assertText('Edit Folder Properties');
    // functional test name change, no description changed. (can't verify
    // on the screen)
    $this->setField('parentid', 'Software Repository');
    $this->createfolder('epTfolder');
    $this->setField('parentid', 'epTfolder');
    $this->setField('newname', 'EditPropTest');
    $this->clickSubmit('Edit!');
    // functional test, don't change name, change description.
    // Note, relys on the name change in the test case above.
    $this->setField('parentid', 'EditPropTest');
    $this->setField('description', 'Changed description for EditPropTest');
    $this->clickSubmit('Edit!');
    // This tests that the error text is shown (how do you tell if it's red?)
    // and that the pulldown is reset to the root folder
    // doesn't work that way!  We are seeing the css sheet hmmmm
    $this->setField('parentid', 'Software Repository');
    $this->setField('description', 'Should see Please Select... in red');
    $this->clickSubmit('Edit!');
    // This doesn't work, need to investigate.
    //$this->assertText('Please Select');
    $this->setField('parentid', 'Software Repository');
  }

}

class TestCreateFolder extends WebTestCase {

  function testFuncCreateFolder(){
    $this->assertTrue($this->get('http://osrb-1.fc.hp.com/~markd/ui-md/'));
    //$this->assertAuthentication('Basic');
    //$this->authenticate('fossology', 'xxxxxxxx');
    $this->assertText('Software Repository');

    $this->click('Organize');
    $this->click('Folders');
    $this->click('Create');
    $this->assertTrue($this->setField('parentid', 'Software Repository'));
    // Generate a random number so names will not collide with multiple
    // test runs.
    $tail = random_int(0, getrandmax());
    $folder_name = 'TestFolder' . "$tail";
    $this->setField('newname', $folder_name);
    $this->clickSubmit('Create!');
    // Wonder why we don't have to click the OK button for the test to
    // pass.
    //$this->click('OK');
    // The assertion below will try to set the pull down to the just created
    // folder.  If the folder did not get created, it will fail.
    $this->click('Organize');
    $this->click('Folders');
    $this->click('Create');
    echo "folder name:$folder_name\n";
    $this->setField('parentid', $folder_name);
  }
}
