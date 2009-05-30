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
 *  fo-LicenseAnalysisTest
 *
 *  Regression test for bsam, chanomos (the ported nomos agent), nomos, and
 *  others.
 *
 *  Todo:
 *  1.create a run function that takes fossology(bsam), fo-nomos, nomos(eddy?),
 *  F1 and ?
 *  2. Create compare results routine.  Takes a results file from step one
 *  and compares against the master list (eddy for now).
 *  2.1 Create master compare!
 *
 *  3. Create a report results routine.
 *
 * created: May 21, 2009
 * @version "$Id: $"
 */
//error_reporting(E_ALL & E_STRICT);

require_once('../commonTestFuncs.php');
require_once('testLicenseLib.php');

$ldir = '/home/fosstester/regression/license/eddy/GPL/GPL_v3';
//$ldir = '/home/fosstester/regression/license/eddy';

/* process parameters
 $Usage = "{$argv[0]} [-h] {-f filepath | -d directorypath}\n" .
 $options = getopt("hf::d::");
 if (empty($options)) {
 print $Usage;
 exit(1);
 }
 if (array_key_exists("h",$options)) {
 print $Usage;
 exit(0);
 }
 if (array_key_exists("f",$options)) {
 $file = $options['f'];
 }
 if (array_key_exists("d",$options)) {
 $directory = $options['d'];
 }
 if (!array_key_exists("d",$options) && !array_key_exists("f",$options)) {
 print $Usage;
 exit(1);
 }
 */
$Master = array();

/* load the results to compare against */
$masterFile = 'OSRB-nomos-license-matches';
$FD = fopen($masterFile, 'r') or die("can't open $masterFile, $phperrormsg\n");
while(($line = fgets($FD, 1024)) !== FALSE) {
  list($file,$result) = explode(' ',$line);
  $Results = explode(',',$result);
  /*
   * a single result gets stored as a string, multiple results are stored as
   * an array.
   */
  if((count($Results)) == 1){
    $Master[$file] = $Results[0];
  }
  else {
    $Master[$file] = $Results;
  }
}
//print "Master results are:\n";print_r($Master) . "\n";

$FileList = array();
$FileList = allFilePaths($ldir);
//print "allFilePaths returned:\n";print_r($FileList) . "\n";

$BsamRaw = array();
$Bsam    = array();
$Cnomos  = array();

$BsamRaw = foLicenseAnalyis($FileList,'bsam');
if(empty($BsamRaw)) {
  print "Bsam analysis Failed!\n";
}
/* clean up results, fosslic reports the filepath as part of the resutls */
foreach($BsamRaw as $file => $result) {
  $Bsam[$file] = preg_replace("/.*?:/",'',$result);
}
$Bsam = prepResults($Bsam,'bsam');
print "bsam results are:\n";print_r($Bsam) . "\n";

$Cnomos = foLicenseAnalyis($FileList,'chanomos');
$Cnomos = prepResults($Cnomos,'nomos');
//print "Cnomos results are:\n";print_r($Cnomos) . "\n";

/* Cnomos is the standard (for now) */
$bsamDiffs = compareResults($Bsam, $Master);
$CnomosDiffs = compareResults($Chanomos, $Master);
print "differences between Bsam and Master(nomos) are:\n";
print_r($bsamDiffs) . "\n";
//reset($diffs);
/*
 foreach($diffs as $filename => $result) {
 foreach($result as $pf => $res) {
 if($result['failed']){
 print "bsam failed on file: $filename\nBecause {$result['failed'][$res]}\n";
 }
 }
 }
 */

/**
 * compareResult
 *
 * Given two assocative arrays, compare the first array to the second return
 * an array of differences or a empty array.
 *
 * @param array $result1 associative array of results, filename is the key.
 * @param array $Master associative array of results, filename is the key.
 * @return array, either of results or an error, check the first key for 'Error'.
 *
 * TODO: rethink this routine.
 *
 * it is mostly working... still need to think about reporting results, I
 * think the double loop when both are arrays is confusing... think about this.
 * look at debug output, there are missing entries for the master, why?
 *
 */
function compareResults($result1, $Master){

  /* Note result2 is the MASTER */

  if(!is_array($result1)) {
    return(array('Error' => 'Must supply an array as a parameter'));
  }
  if(!is_array($Master)) {
    return(array('Error' => 'Must supply an array as a parameter'));
  }
  $diffs = array();
  $result = array();

  /*
   * compare each result is compared so we can keep track of passes and failures.
   */
  foreach($result1 as $file => $res1) {
    print "processing $file\n";
    $res2 = $Master[$file];
    if(is_string($res2)) {
      if(is_string($res1)) {
        print "Both-Strings-Case:$file:res1i:$res1,res2:$res2\n";
        $result["standard"] = $res2;
        if($res1 == $res2) {
          $result['pass'] = $res1;
        }
        else {
          $result['fail'] = $res1;
        }
      }
      else if(is_array($res1)){
        for($i=0; $i< count($res1); $i++) {
          $result["standard"] = $res2;
          print "r1S-R2Array:$file:res1i:{$res1[$i]}\n";
          print "R1S-R2Arrays:$file:res2i:$res2\n";
          if($res1[$i] == $res2) {
            $result['pass'] = $res1[$i];
          }
          else {
            $result['fail'] = $res1[$i];
          }
        }
      }
    }
    else if(is_array($res2)){
      if(is_array($res1)){
        for($i=0; $i< count($res1); $i++) {
          for($r2=0; $r2< count($res2); $r2++) {
            $result["standard$r2"] = $res2[$r2];
            print "Both-Arrays:$file:res1i:{$res1[$i]}\n";
            print "Both-Arrays:$file:res2i:{$res2[$i]}\n";
            if($res1[$i] == $res2[$r2]) {
              $result['pass'] = $res1[$i];
            }
            else {
              $result['fail'] = $res1[$i];
            }
          }
        }
      }
      else {   // res1 a string
        for($r2=0; $r2< count($res2); $r2++) {
          $result["standard$r2"] = $res2[$r2];
          print "res1-String/r2Array:$file:res1i:$res1,res2:$res2\n";
          if($res1 == $res2[$r2]) {

            $result['pass'] = $res1;
          }
          else {
            $result['fail'] = $res1;
          }
        }
      }
    }
    else {    // neither string nor array, no Master result, false id: fail
      print "in fail case, no master result\n";
      $result["standard"] = $res2;
      $result['fail'] = $res1[$i];
    }
    $diffs[$file] = $result;
    $result = array();
  }
  return($diffs);
} // compareResults

/**
 * foLicenseAnalyis
 *
 * @param string or array $license a single file to analize or an array or file
 * paths.
 * @param string agent to use for analysis one of: bsam, chanomos or nomos
 *
 * @return mixed, either a string or array, empty string or array on error
 */
function foLicenseAnalyis($license,$agent) {

  $chanNomos = '../../agents/nomos/nomos';      // use this path for now
  $bsam = array();
  switch($agent) {
    case 'bsam':
      $cmd = "/usr/bin/fosslic ";
      //      return(_runAnalysis($license,$cmd));
      print "Running bsam analysis\n";
      //$bsam = _runAnalysis($license,$cmd);
      return(_runAnalysis($license,$cmd));
      break;
    case 'chanomos':
      print "Running chanomos analysis\n";
      $cmd = "../../agents/nomos/nomos ";
      return(_runAnalysis($license,$cmd));
      break;
    case 'nomos':
      // either use the OSRB one or one installed
      return(NULL);
      break;
    default:
      return(NULL);
  }
} //foLicenseAnalysis

/**
 * _runAnalysis
 *
 * Run the license analysis
 * @param mixed $licenseList as string or array of filepaths to licenses to
 * analyze.
 * @param string $cmd the command to run (e.g. /usr/bin/fosslic).
 * @return mixed, either string or array depending on the first parameter.
 */
function _runAnalysis($licenseList,$cmd){

  $Fossology = array();
  if(is_array($licenseList)) {
    foreach($licenseList as $license){
      $license = trim($license);
      $last = exec("$cmd $license 2>&1", $result, $rtn);
      $Fossology[basename($license)] = $last;
    }
    return($Fossology);
  }
  else {
    $last = exec("$cmd $file 2>&1", $result, $rtn);
    return($last);
  }
}

/**
 * prepResults
 *
 * create an array of the results.  If there are multiple results, they are
 * comma seperated.
 *
 * @param array $result the associative array with results
 * @param string $agent, one of 'bsam' or 'nomos'
 * @return array $processed an associative array with results in a string or
 * an array, or an empty array on error.
 */
function prepResults($result,$agent='nomos') {

  $agent = strtolower($agent);
  $all       = array();
  $processed = array();

  if(empty($result)) return(array());
  foreach($result as $file => $rlist) {
    $tList = trim($rlist);
    if($agent == 'nomos') {
      $list = filterNomosResults($alist);
    }
    else if($agent == 'bsam'){
      /*
       * cleanup for bsam results:
       * 1. change ,\s to ,
       * 2. change spaces in words to _ (e.g. GPL v3 -> GPL_v3)
       * 3. remove -style
       * 4. remove ' around name
       */
      $noClist = str_replace(', ', ',', $tList);
      $slist = str_replace(' ', '_', $noClist);
      $qlist = str_replace('-style', '', $slist);
      $alist = str_replace("'", '', $qlist);
      $list = filterFossologyResults($alist);
    }
    $all = explode(",",$list);
    if((count($all)) == 1) {
      $processed[$file] = $all[0];
    }
    else {
      $processed[$file] = $all;
    }
  }
  return($processed);
}
?>