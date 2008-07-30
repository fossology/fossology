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
    $mysid = session_id();
    print "DBL: SID IS:$mysid\n";
    $this->useProxy('http://web-proxy.fc.hp.com:8088', 'web-proxy', '');
    //$this->assertTrue($this->get('http://fluffy.ostt/repo/'));
    $browser = & new SimpleBrowser();
    $page = $browser->get('http://osrb-1.fc.hp.com/~markd/ui-md/');
    $mysid = session_id();
    print "DBL: SID after GET IS:$mysid\n";
    //print "Page is:\n";
    //var_dump($page);
    $this->assertTrue($page);
    $this->assertTrue(is_object($browser));
    print "DBL: Before Login dump of \$_SESSION is:\n";
    var_dump($_SESSION);
    print "DBL: trying core-auth keys\n";
    echo "User:" . $_SESSION['User'] . "\n";
    echo "UserId:" . $_SESSION['UserId'] . "\n";
    echo "UserEmail:" . $_SESSION['UserEmail'] . "\n";
    echo "Folder:" . $_SESSION['Folder'] . "\n";
    echo "time_check:" . $_SESSION['time_check'] . "\n";
    $this->repoLogin($browser);
    print "DBL: After Login dump of \$_SESSION is:\n";
    var_dump($_SESSION);
    print "DBL: trying core-auth keys\n";
    echo "User:" . $_SESSION['User'] . "\n";
    echo "UserId:" . $_SESSION['UserId'] . "\n";
    echo "UserEmail:" . $_SESSION['UserEmail'] . "\n";
    echo "Folder:" . $_SESSION['Folder'] . "\n";
    echo "time_check:" . $_SESSION['time_check'] . "\n";
    $loggedIn = $browser->getContent();
    $mysid = session_id();
    print "DBL: SID after GET Content:$mysid\n";
    print "*****Page after Login is:********\n";
    $this->dump($page);
    //$page = $browser->get('http://osrb-1.fc.hp.com/~markd/ui-md/?mod=folder_create');
    $page = $browser->get('http://osrb-1.fc.hp.com/~markd/ui-md/');
    $mysid = session_id();
    print "DBL: SID Create URI:$mysid\n";
    $mycookie = session_get_cookie_params();
    $mysid = session_id();
    print "SID/COOKIE AFter Create Call IS:$mysid\n";
    $loggedIn = $browser->getContent();
    print "*****Page after Home page get is:********\n";
    $this->dump($loggedIn);

  }
}
?>
