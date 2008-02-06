#!/usr/bin/php

<?php
/*
 *  first try at testing web interface...
 */

class TestRepoLogin extends WebTestCase{

  function testLogin(){
    $this->assertTrue($this->get('http://repo.fossology.org/'));
    $this->assertAuthentication('Basic');
    $this->authenticate('fossology', 'OeyiW5go');
    $this->assertText('Software Repository');
  }
}
class TestAboutMenu extends WebTestCase {
  
  function testMenuAbout(){
    $this->assertTrue($this->get('http://repo.fossology.org/'));
    $this->assertAuthentication('Basic');
    $this->authenticate('fossology', 'OeyiW5go');
    $this->assertText('Software Repository');
    
    $this->click('About');
    $this->assertText('FOSSology');
  }
}
/*
 * NOTE: the url is for the internal machines... fix this!
 * they fail when run from home, can't seem to get a url to work on doc....
 */
class TestToolsMenu extends WebTestCase {
  
  function testMenuTools(){
    $this->assertTrue($this->get('http://osrb-1.fc.hp.com:3128/~markd/ui-md/'));
    $this->assertAuthentication('Basic');
    $this->authenticate('fossology', 'OeyiW5go');
    $this->assertText('Software Repository');
    
    $this->click('Tools');
    $this->clicklink('Folders (refresh)', 0);
  }
}

class TestAdminFolder extends WebTestCase {
  
  function testFolderCreate(){
    $this->assertTrue($this->get('http://osrb-1.fc.hp.com:3128/~markd/ui-md/'));
    $this->assertAuthentication('Basic');
    $this->authenticate('fossology', 'OeyiW5go');
    $this->assertText('Software Repository');
    
    $this->click('Admin');
    $this->click('Folder');
    $this->click('Create');
    //$this->assertText('Create Folder');
  }
  
}
?>