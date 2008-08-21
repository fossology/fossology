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
 * Test to check the Help/About menu item, make sure page loads
 *
 * @version "$Id$"
 *
 * Created on Jul 21, 2008
 */

require_once('../../../../tests/fossologyWebTestCase.php');
require_once ('../../../../tests/TestEnvironment.php');

global $URL;
global $USER;
global $PASSWORD;

class TestAboutMenu extends fossologyWebTestCase
{

  function testMenuAbout()
  {
    global $URL;
    print "starting testMenuAbout\n";
    $page = $this->get($URL);
    $this->assertTrue($page);
    $this->assertText($page, '/Welcome to FOSSology/');
    $this->click('Help');
    $this->click('About');
    $this->assertText($page, '/About FOSSology/');
  }
}
?>
