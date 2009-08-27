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
 * Driver to generate a summary report from a single test run
 *
 * @param string $resultsFile -f <file>
 *
 * @return
 *
 * @version "$Id: $"
 *
 * Created on Aug. 25, 1009
 */

 require_once('reportClass.php');

 $res = '/home/markd/Src/fossology/tests/FossTestResults-2009-08-25-10:31:39-pm';

 $tr = new TestReport($res);

 $results = $tr->parseResultsFile($res);
 //print "got back the following from parseResultsFile:\n";
 //print_r($results) . "\n";

 exit(777);
?>
