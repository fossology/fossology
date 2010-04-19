#!/usr/bin/php
<?php
/*
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
 */


/**
 * compare licenses per file btween gnomos and nomos
 */

$gnFile =  './LicensesPerFile-TestDistro-4-7-2010';
//$gnFile =  './noCached';

$GF = fopen($gnFile, 'r');
/*
 * Can't just use the path or the filename as they are not unique.  Will
 * need to use the complete path!
 *
 * Read the two files into two arrays and sort the keys
 */
while (! feof($GF))
{
	if($line = fgets($GF, 1024))
	{
		list($license, $fpath) = explode(':',$line);
		//print "license:$license\nPath:$fpath\n";
		$gnomos[trim($fpath)] = trim($license);
	}
}
//print "gnomos is:\n";print_r($gnomos) . "\n";


$nFile = './nr3';
//$nFile = './Nomos-licPerFile';

$NF = fopen($nFile, 'r');

while (! feof($NF))
{
	if($line = fgets($NF, 1024))
	{
		list($fpath, $license) = explode(':',$line);
		//print "license:$license\nPath:$fpath\n";
		$nomos[trim($fpath)] = trim($license);
	}
}
//print "nomos is:\n";print_r($nomos) . "\n";

if(!ksort($gnomos))
{
	print "FATAL! could not sort gnomos data\n";
	exit(1);
}
//print "Sorted gnomos is:\n";print_r($gnomos) . "\n";

if(!ksort($nomos))
{
	print "FATAL! could not sort nomos data\n";
	exit(1);
}
//print "nomos is:\n";print_r($nomos) . "\n";
/*
 foreach($nomos as $path => $licenses)
 {
 print "$path: $licenses\n";
 }
 */

/*
 $diffs = array_diff_assoc($nomos, $gnomos);
 //print "the Src only diffs are:\n";print_r($diffs) . "\n";
 if(!empty($diffs))
 {
 foreach($diffs as $path => $licenses)
 {
 print "$path: $licenses\n";
 }
 }
 */
print "could not find the following in fnomos output:\n";
foreach($gnomos as $path => $licenses)
{
	if(!array_key_exists($path,$nomos))
	{
		print "$path: $licenses\n";
	}
}
?>