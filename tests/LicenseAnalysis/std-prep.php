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
 * File format should be <filepath>: <results>, results can be comma seperated
 * results.
 *
 * created: May 28, 2009
 * @version "$Id:  $"
 */

require_once('../testClasses/ReadInputFile.php');
require_once('testLicenseLib.php');

$file = "/home/markd/GnomosResults-2009-05-28";
$resultList = array();

$iFile = new ReadInputFile($file);
if($iFile) {
  while(FALSE !== ($line = $iFile->getLine($iFile->getFileResource()))){
    buildOutput($line,&$resultList);
    /*
     if(!buildOutput($line,&$resultList)){
     print "Error! could not add $line to the output\n";
     exit(1);
     }
     */
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
function buildOutput($line,&$resultList) {

  if(!strlen($line)) {
    return(FALSE);
  }
  list($filePath, $results) = explode(' ', $line);
  $filePath = rtrim($filePath,':');
  $file = basename($filePath);
  $results = trim($results);
  $filtered = filterNomosResults($results);
  $resultList[$file] = $filtered;
}
?>