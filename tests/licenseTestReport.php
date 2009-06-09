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
 * Driver to generate a report of a license regression test run.
 *
 * e.g. ltr {-a bsam | fonomos} [-h] [-f file of filepaths] -r file-path ?? what else
 * @param $file, license test results file created by the test
 *
 * @version "$Id: $"
 *
 * Created on Dec 12, 2008
 */

require_once('reportClass.php');

/*
 - process parameters
 - figure out columns based on how many input files
 - based on the number of input files, set up the loops/columns
   - note always have at least 5 columns (nomos + file/vetted name)
   - build up result string
 */


// for debugging, just dummy up some values
$file = '/home/markd/Src/fossology/tests/LicenseAnalysis/Bsam-Results.2009Jun04';
$report = new TestReport();
$cols = 5;
$FD = fopen($file, 'r') or die("Cannot open $file $phperrormsg\n");
$result = $report->parseLicenseResults($FD);
$bres = $result[2];
//print "<pre>bres is:\n";print_r($bres) . "\n</pre>";
$report->displayLicenseResults($cols,$result);

exit(0);
?>
