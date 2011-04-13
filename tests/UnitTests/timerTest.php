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

/**
 * Unit test for Timer class
 *
 *
 * @version "$Id: $"
 *
 * Created on Sep 19, 2008
 */

 require_once('../testClasses/timer.php');
 //class timerTest extends UnitTest
 //{
//  function testTimer()
//  {
    print "starting Timer tests\n";
    print "Before: " . date('r') . "\n";
    $t = new timer();
    $now = $t->getStartTime();
    // 1 day 5 min 30 sec's ago
    $ago = $now - 86700;
    sleep(30);
    $orig = $t->TimeAgo($t->getStartTime());
    $a = $t->TimeAgo($ago);
    //print "now is:$now\n";
    print "After: " . date('r') . "\n";
    print "the orig is: $orig\n";
    print "the ago is: $a\n";
//  }
// }
?>
