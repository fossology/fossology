<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Is the folder edit properties menu available?
 *
 * @version "$Id: OrgUploadsMenuTest-Delete.php 4017 2011-03-31 20:24:42Z rrando $"
 *
 * Created on Jul 31, 2008
 */
require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class UploadsDeleteMenuTest extends fossologyTestCase
{

  function testUploadsDeleteMenu()
  {
    global $URL;
    print "starting UploadsDeleteMenuTest\n";
    $this->Login();
    /* we get the home page to get rid of the user logged in page */
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Organize/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Uploads/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Delete Uploaded File/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Edit Properties/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Move/'));
    /* ok, this proves the text is on the page, let's see if we can
     * get to the delete page.
     */
    $page = $this->mybrowser->get("$URL?mod=admin_upload_delete");
    $this->assertTrue($this->myassertText($page, '/Delete Uploaded File/'));
    $this->assertTrue($this->myassertText($page, '/THERE IS NO UNDELETE/'));
  }
}
