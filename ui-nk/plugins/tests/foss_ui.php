#!/usr/bin/php

<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
***********************************************************/
/*
 *  first try at testing web interface...
 */

class TestRepoLogin extends WebTestCase{

  function testLogin(){
    $this->useProxy('http://web-proxy.fc.hp.com:8088', 'web-proxy', '');
    $this->assertTrue($this->get('http://repo.fossology.org/'));
    $this->assertAuthentication('Basic');
    $this->authenticate('fossology', 'OeyiW5go');
    $this->assertText('Software Repository');
  }
}
class TestAboutMenu extends WebTestCase {
  
  function testMenuAbout(){
    $this->useProxy('http://web-proxy.fc.hp.com:8088', 'web-proxy', '');
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
    $this->assertTrue($this->get('http://osrb-1.fc.hp.com/~markd/ui-md/'));
    //$this->assertAuthentication('Basic');
    //$this->authenticate('fossology', 'OeyiW5go');
    $this->assertText('Software Repository');
    
    $this->click('Tools');
    $this->clicklink('Folders (refresh)', 0);
  }
}

class TestAdminFolder extends WebTestCase {
  
  function testFolderCreate(){
    $this->assertTrue($this->get('http://osrb-1.fc.hp.com/~markd/ui-md/'));
    //$this->assertAuthentication('Basic');
    //$this->authenticate('fossology', 'OeyiW5go');
    $this->assertText('Software Repository');
    
    $this->click('Admin');
    $this->click('Folder');
    $this->click('Create');
    //$this->assertText('Create Folder');
  }
  
}
?>