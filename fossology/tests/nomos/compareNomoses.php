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

$Usage = "compareNomoses [-h] [-g gnomosResults] [-n fnomosResults]\n" .
"If no parameters given then the following files will be used:\n" .
"./LicensesPerFile-TestDistro-4-7-2010 for gnomos results and \n" .
"./Nomos-licPerFile for FOSSology results\n";

$options = getopt('hg:n:');
if (empty($options)) {
	$gnFile =  './LicensesPerFile-TestDistro-4-7-2010';
	$nFile = './Nomos-licPerFile';
}
if (array_key_exists('h', $options)) {
	print "$Usage\n";
	exit(0);
}
if (array_key_exists('g', $options)) {
	$gnFile = $options['g'];
}
else
{
	$gnFile =  './LicensesPerFile-TestDistro-4-7-2010';
}
if (array_key_exists('n', $options)) {
	$nFile = $options['n'];
}
else
{
	$nFile = './Nomos-licPerFile';
}

//$gnFile =  './LicensesPerFile-TestDistro-4-7-2010';

$GF = fopen($gnFile, 'r') or die("Can't open $gnFile\n");
/*
 * Can't just use the path or the filename as they are not unique.  Will
 * need to use much of the complete paths, some hand editing of results
 * files are needed for both versions.
 *
 * Read the two fixed up files into two arrays and sort the keys
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

$NF = fopen($nFile, 'r') or die("Can't open $nFile\n");

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

$diffs = array_diff_assoc($nomos, $gnomos);
//print "the diffs are:\n";print_r($diffs) . "\n";
if(!empty($diffs))
{
	$DF = fopen('nomos-diffs', 'w') or die("Can't open nomos-diffs " .
 							"File: " . __FILE__ . " on line: " . __LINE__ . "\n"); 							
	foreach($diffs as $path => $licenses)
	{
		fwrite($DF,"$path: $licenses\n");
	}
}

fclose($DF);

//print "could not find the following in fnomos output:\n";
$NF = fopen('nomos-NotFound', 'w') or die("Can't open nomos-NotFound " .
 							"File: " . __FILE__ . " on line: " . __LINE__ . "\n"); 
foreach($gnomos as $path => $licenses)
{
	//print "CDB: looking for:\n$path\n";
	if(!array_key_exists($path,$nomos))
	{
		fwrite($NF,"$path: $licenses\n");
	}
}
fclose($NF);
?>