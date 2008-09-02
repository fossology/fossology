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
 * Upload a file using the UI
 *
 *@TODO need to make sure testing folder exists....
 *
 * @version "$Id: $"
 *
 * Created on Aug 1, 2008
 */

/*
 * Yuk! This test is ugly!  NOTE: Will need to set a proxy for this to
 * work inside hp.
 */

require_once ('../../../../tests/fossologyTestCase.php');
require_once ('../../../../tests/TestEnvironment.php');

global $URL;

class UploadUrlTest extends fossologyTestCase
{

  function testUploadUrl()
  {
    global $URL;

    print "starting UploadUrlTest\n";
    $this->useProxy('http://web-proxy.fc.hp.com:8088', 'web-proxy', '');
    $browser = & new SimpleBrowser();
    $page = $browser->get($URL);
    $this->assertTrue($page);
    $this->assertTrue(is_object($browser));
    $cookie = $this->repoLogin($browser);
    $host = $this->getHost($URL);
    $browser->setCookie('Login', $cookie, $host);

    $loggedIn = $browser->get($URL);
    $this->assertTrue($this->assertText($loggedIn, '/Upload/'));
    $this->assertTrue($this->assertText($loggedIn, '/From URL/'));
    $page = $browser->get("$URL?mod=upload_url");
    $this->assertTrue($this->assertText($page, '/Upload from URL/'));
    $this->assertTrue($this->assertText($page, '/Enter the URL to the file:/'));

    /* select Testing folder, filename based on pid or session number */

    $FolderId = $this->getFolderId('Testing', $page);
    $this->assertTrue($browser->setField('folder', $FolderId));
    $simpletest = 'http://downloads.sourceforge.net/simpletest/simpletest_1.0.1.tar.gz';
    $this->assertTrue($browser->setField('geturl', $simpletest));
    $desc = 'File uploaded by test UploadUrlTest';
    $this->assertTrue($browser->setField('description', "$desc"));
    $pid = getmypid();
    $upload_name = 'TestUploadUrl-' . "$pid";
    $this->assertTrue($browser->setField('name', $upload_name));
    /* we won't select any agents this time' */
    $page = $browser->clickSubmit('Upload!');
    $this->assertTrue(page);
    $this->assertTrue($this->assertText($page, '/Upload added to job queue/'));

    //print  "************ page after Upload! *************\n$page\n";
  }
}
?>
