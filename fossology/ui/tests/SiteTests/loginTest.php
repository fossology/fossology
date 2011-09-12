<?php
/***********************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
 * @version "$Id$"
 *
 * Created on Jul 21, 2008
 */

$WORKSPACE = NULL;
if(array_key_exists('WORKSPACE', $_ENV))
{
  $WORKSPACE = $_ENV['WORKSPACE'];
}

if($WORKSPACE)
{
  require_once $WORKSPACE . '/fossology/tests/TestEnvironment.php';
  require_once $WORKSPACE . '/fossology/tests/fossologyTestCase.php';
}
else
{
  require_once '../../tests/TestEnvironment.php';
  require_once '../../tests/fossologyTestCase.php';
}

global $URL;
global $USER;
global $PASSWORD;

class TestRepoLogin extends fossologyTestCase{

  function testLogin(){

    global $URL;
    //print "login test starting\n";
    $browser = new SimpleBrowser();
    $this->setBrowser($browser);
    $this->Login();
    $page = $this->mybrowser->getContent();
    //print "************LOGIN: Page after Login is:************\n";
    //$this->dump($page);
    preg_match('/FOSSology/', $page, $matches);
    //$this->dump($matches);
    $this->assertTrue($matches);
  }
}

?>
