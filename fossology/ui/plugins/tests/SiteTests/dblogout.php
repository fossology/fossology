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
 * Stub to try to figure out why we get logged out during testing
 *
 * @version "$Id: $"
 *
 * Created on Jul 29, 2008
 */

require_once ('../../../../tests/fossologyWebTestCase.php');
//global $_SESSION;

//error_reporting(E_ALL);

class DBLogoutTest extends fossologyWebTestCase
{

  function testdbLogout()
  {
    print "starting DB Login\n";
    //$mysid = session_id();
    //print "DBL: SID IS:$mysid\n";
    $this->useProxy('http://web-proxy.fc.hp.com:8088', 'web-proxy', '');
    $browser = & new SimpleBrowser();
    $request = $browser->getRequest();
    $header = $browser->getHeaders();
    print "\nDBL: Before Get(http://osrb-1.fc.hp.com/~markd/ui-md/):headers are\nRequest:\n$request\nHeader:\n$header\n";
    $page = $browser->get('http://osrb-1.fc.hp.com/~markd/ui-md/');
    $request = $browser->getRequest();
    $header = $browser->getHeaders();
    print "\nDBL: After Get(http://osrb-1.fc.hp.com/~markd/ui-md/):headers are\nRequest:\n$request\nHeader:\n$header\n";
    //$mysid = session_id();
    //print "DBL: SID after GET IS:$mysid\n";
    //print "Page is:\n";
    //var_dump($page);
    $this->assertTrue($page);
    $this->assertTrue(is_object($browser));

    $this->repoLogin($browser);
    $page = $browser->get('http://osrb-1.fc.hp.com/~markd/ui-md/');
    $request = $browser->getRequest();
    $header = $browser->getHeaders();
    print "\nDBL: After LOGIN get of(http://osrb-1.fc.hp.com/~markd/ui-md/):headers are:\nRequest:\n$request\nHeader:\n$header\n";
    $loggedIn = $browser->getContent();
    //$mysid = session_id();
    //print "DBL: SID after GET Content:$mysid\n";
    //print "*****Page after Login is:********\n";
    //$this->dump($page);
    //$page = $browser->get('http://osrb-1.fc.hp.com/~markd/ui-md/?mod=folder_create');
    $page = $browser->get('http://osrb-1.fc.hp.com/~markd/ui-md/');
    $loggedIn = $browser->getContent();
    //print "*****Page after Home page get is:********\n";
    //$this->dump($loggedIn);

  }
}
?>
