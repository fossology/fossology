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
 *  alldirs
 *
 *  given a directory, iterate through it and all subdirectories
 *  Prototype for use in license regression testing
 *
 *  Todo:
 *  1.create a run function that takes fossology(bsam), fo-nomos, nomos(eddy?),
 *  F1 and ?
 *  2. Create compare results routine.  Takes a results file from step one
 *  and compares against the master list (eddy for now).
 *  3. Create a report results routine.
 *  4. Either rename or refactor (or both!) this whole thing.
 *
 * created: May 21, 2009
 */


global $FileList;
$FileList = array();

//$ldir = '/home/markd/Eddy';
$ldir = '/home/fosstester/regression/license/eddy/GPL';

function DirWalk($dir){

  global $FileList;

  foreach(new RecursiveDirectoryIterator($dir) as $file) {
    $thing = $file->GetFilename();
    if($file->isDir($thing)) {
      DirWalk($file->getPathname());
    }
    if($file->isFile($thing)) {
      if($file->isReadable($thing)) {
        $FileList[] = $file->getPathname();
      }
    }
  }
  //print "walk will return:\n";print_r($FileList) . "\n";
  return($FileList);
} // DirWalk

$FileList = DirWalk($ldir);
//print "DirWalk returned:\n";print_r($FileList) . "\n";

$Fossology = array();
foreach($FileList as $file) {
  $cmd = "/usr/bin/fosslic $file 2>&1";
  $bsamLast = exec($cmd, $bsam, $rtn);
  //print "File is:$file\n";
//  print "last is:$bsamLast\n";
  //print "bsam is:\n";print_r($bsam) . "\n";
  // use the filename as the key and store the results
  foreach($bsam as $result) {
    $c = NULL;
    $c = str_replace($file . ':', "", $result);
    //print "c is:$c\n";
    $Fossology[$file] = $c;
  }
  $bsam = array();
}
print "Fossology Results are:\n";print_r($Fossology) . "\n";

/**
 *
 * @param string or array $license a single file to analize or an array or file
 * paths.
 * @return mixed, either a string or array
 */
function foLicenseAnalyis($license) {

  if(is_array($license)) {
    // foreach
  }
  else {
    // call fosslic.
  }
}
?>