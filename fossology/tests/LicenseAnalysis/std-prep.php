#!/usr/bin/php
<?php
/*
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
 */
/**
 *
 * stdNomos-prep
 *
 * Read the output of osrb-nomos from a file and transform it into a form that
 * is easily uploaded by a consumer, (e.g. LicenseRegression test).
 *
 * Input file format should be <filepath>: <results>, multiple results comma separated
 * Output file format is: dir/file: <results>,... multiple results comma separated
 *
 * @todo: add input parameter for file input and check.
 *
 * created: May 28, 2009
 * @version "$Id:  $"
 */

require_once('../testClasses/ReadInputFile.php');
require_once('testLicenseLib.php');

$file = "/home/markd/GnomosResults-2009-05-28";
$resultList = array();

/*
 build the output
 */
$iFile = new ReadInputFile($file);
if($iFile) {
  while(FALSE !== ($line = $iFile->getLine($iFile->getFileResource()))){
    buildOutput($line,&$resultList);
  }
}

try{
  $Std = @fopen('OSRB-nomos-license-matches','w');
  if($Std === FALSE) {
    throw new Exception("Cannot Save Results to file OSRB-nomos-license-matches\n");
  }
}
catch(Exception $e){
  print $e->getMessage();
  exit(1);
}

foreach($resultList as $file => $rlist){
  $many = fwrite($Std, "$file $rlist\n");
}
fclose($Std);

/**
 * buildOutput
 *
 * Build the output file in the format:
 *
 * dir/filename: result, multiple results separated by comma
 *
 * @param string $line the input line to process
 * @param array ref $resultList the reference to array to build
 * @return False on error, or void: builts the array passed in.
 */
function buildOutput($line,&$resultList) {

  if(!strlen($line)) {
    return(FALSE);
  }
  list($filePath, $results) = explode(' ', $line);
  $filePath = rtrim($filePath,':');
  //$file = basename($filePath);
  $licenseFile = pathinfo($filePath);
  $ld = explode('/',$licenseFile['dirname']);
  $licenseDir = end($ld);
  // filename is just the filename without the extension .txt, add it back in
  $licenseKey = $licenseDir . '/' . trim($licenseFile['filename'])
                . '.' . $licenseFile['extension'] . ':';
  $results = trim($results);
  $filtered = filterNomosResults($results);
  $filtered = trim($filtered);
  $resultList[$licenseKey] = $filtered;
}
?>