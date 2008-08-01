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

/**
 * Describe your PHP program or function here
 *
 * @param
 *
 * @return
 *
 * @version "$Id: $"
 *
 * Created on Jul 21, 2008
 */

require_once('../../../../tests/fossologyWebTestCase.php');
require_once ('../../../../tests/TestEnvironment.php');

global $URL;
global $USER;
global $PASSWORD;

class TestRepoLogin extends fossologyWebTestCase{

  function testLogin(){

    global $URL;
    print "login test starting\n";
    $browser = & new SimpleBrowser();
    $this->repoLogin($browser);
    $page = $browser->getContent();
    //print "************LOGIN: Page after Login is:************\n";
    //$this->dump($page);
    preg_match('/FOSSology/', $page, $matches);
    //$this->dump($matches);
    $this->assertTrue($matches);
  }
}

?>
