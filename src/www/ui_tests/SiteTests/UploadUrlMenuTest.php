<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Is the folder edit properties menu available?
 *
 * @version "$Id: UploadUrlMenuTest.php 4018 2011-03-31 20:34:17Z rrando $"
 *
 * Created on Jul 31, 2008
 */
require_once ('../../../tests/fossologyTestCase.php');
require_once ('../../../tests/TestEnvironment.php');

global $URL;

class UploadUrlMenuTest extends fossologyTestCase
{

  function testUploadUrlMenu()
  {
    global $URL;
    print "starting UploadUrlMenuTest\n";
    $this->Login();
    /* we get the home page to get rid of the user logged in page */
    $loggedIn = $this->mybrowser->get($URL);
    $this->assertTrue($this->myassertText($loggedIn, '/Upload/'));
    $this->assertTrue($this->myassertText($loggedIn, '/Instructions/'));
    $this->assertTrue($this->myassertText($loggedIn, '/From File/'));
    $this->assertTrue($this->myassertText($loggedIn, '/From Server/'));
    $this->assertTrue($this->myassertText($loggedIn, '/From URL/'));
    $this->assertTrue($this->myassertText($loggedIn, '/One-Shot Analysis/'));
    /* ok, this proves the text is on the page, let's see if we can
     * get to the delete page.
     */
    $page = $this->mybrowser->get("$URL?mod=upload_url");
    $this->assertTrue($this->myassertText($page, '/Upload from URL/'));
    $this->assertTrue($this->myassertText($page, '/Enter the URL to the file/'));
  }
}
