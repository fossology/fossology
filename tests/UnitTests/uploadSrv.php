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
 * Upload from server (unit test?)
 *
 * Uses the simpletest framework, this way it doesn't matter where the
 * repo is, it will get uploaded, and this is another set of tests.
 *
 * @param URL obtained from the test enviroment globals
 *
 * @version "$Id: $"
 *
 * Created on April 1, 2009
 */
require_once ('fossologyTestCase.php');
require_once ('TestEnvironment.php');
global $URL;
global $PROXY;

class uploadSrvDataTest extends fossologyTestCase {
  public $mybrowser;
  public $webProxy;

  function setUp() {
    global $URL;
    $this->Login();
  }

  function testuploadSrvDataTest() {
    global $URL;
    global $PROXY;
    print "starting uploadSrvDataTest\n";
    $License = '~fosstester/licenses/BSD_style_z.txt';
    $Archive = '~fosstester/archives/foss23D1F1L.tar.bz2';

    print "Starting Srv uploads: License\n";
    $this->uploadServer(1, $License, 'License uploaded by uploadServer Test', NULL, NULL);
    print "Starting Srv uploads: Archive\n";
    $this->uploadServer(1, $Archive, 'Archive uploaded by uploadServer Test', NULL, NULL);
  }
}
?>
