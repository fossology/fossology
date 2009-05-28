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
$Bsam = prepResults($Bsam);
print "bsam results are:\n";print_r($Bsam) . "\n";

$Cnomos = foLicenseAnalyis($FileList,'chanomos');
$Cnomos = prepResults($Cnomos);
print "Cnomos results are:\n";print_r($Cnomos) . "\n";

/* Cnomos is the standard (for now) */
$diffs = compareResults($Bsam, $Cnomos);
print "differences between Bsam and nomos are:\n";
print_r($diffs) . "\n";
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
 * given two assocative arrays, compare their results, return an array of
 * differences or a empty array
 *
 * @param array $result1 associative array of results, filename is the key.
 * @param array $result2 associative array of results, filename is the key.
 * @return array, either of results or an error, check the first key for 'Error'.
 *
 * TODO: rethink this routine.  It needs to compare against a 'master'.
 * - do you have to always pass in both?
 * - can this routine load the master or is it in the code?
 *   - in the code to start with?
 *   - thinking it should load it for final code.
 */
function compareResults($result1, $result2){

  /* Note result2 is the MASTER */

  if(!is_array($result1)) {
    return(array('Error' => 'Must supply an array as a parameter'));
  }
  if(!is_array($result2)) {
    return(array('Error' => 'Must supply an array as a parameter'));
  }
  $diffs = array();
  $result = array();
  /* for now, create the standard this way
  foreach($result2 as $file => $res2) {
    for($i=0; $i< count($res2); $i++) {
      $result['standard'] = $result2[$file][$i];
    }
  }
  */
  /*
   * each result is compared so we can keep track of passes and failures.
   */
  foreach($result1 as $file => $res1) {
    for($i=0; $i< count($res1); $i++) {
      $res2 = $result2[$file];
      for($r2=0; $r2< count($res2); $r2++) {
        $result["standard$r2"] = $result2[$file][$r2];
      }
      print "resi is:{$res1[$i]}\n";
      print "res2fi is:{$result2[$file][$i]}\n------------\n";
      if($res1[$i] === $result2[$file][$i]) {
        $result['pass'] = $res1[$i];
      }
      else {
        $result['fail'] = $res1[$i];
      }
    }
    $diffs[$file] = $result;
    $result = array();
  }
  return($diffs);
}

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
 * getFossologySynonyms
 * taken from the nomos script license_vetter.pl
 *
 * @param string $string a string a license results
 * @return string $adjusted the adjusted results string
 */
function getFossologySynonyms($string) {

  $adjusted = str_replace('+',' or later',$string);

  $adjusted = str_replace('Apache Software License','Apache',$string);
  $adjusted = str_replace('Artistic License','Artistic',$string);

  $adjusted = str_replace('Adobe AFM','AFM',$string);

  #    $adjusted = str_replace('Adobe Product License Agreement','',$string);

  $adjusted = str_replace('Affero GPL','Affero',$string);

  $adjusted = str_replace('ATI Software EULA','ATI Commercial',$string);

  $adjusted = str_replace('GNU Free Documentation License','GFDL',$string);

  $adjusted = str_replace('Common Public License','CPL',$string);

  $adjusted = str_replace('Eclipse Public License','EPL',$string);

  $adjusted = str_replace('Microsoft Reference License','MRL',$string);
  $adjusted = str_replace('Reciprocal Public License','RPL',$string);

  $adjusted = str_replace('gSOAP Public License','GSOAP',$string);

  $adjusted = str_replace('Apple Public Source License','APSL',$string);
  $adjusted = str_replace('LaTeX Project Public License','LPPL',$string);
  $adjusted = str_replace('World Wide Web.*','W3C',$string);

  $adjusted = str_replace('IBM Public License','IBM\-PL',$string);

  $adjusted = str_replace('MySQL AB Exception','MySQL',$string);
  $adjusted = str_replace('NASA Open Source','NASA',$string);

  $adjusted = str_replace('Sun Microsystems Binary Code License','SBCLA',$string);
  $adjusted = str_replace('Sun Community Source License TSA','SCSL\-TSA',$string);
  $adjusted = str_replace('Sun Community Source License','SCSL',$string);
  $adjusted = str_replace('Sun Microsystems Sun Public License','SPL',$string);

  $adjusted = str_replace('Sun GlassFish Software License','SGF',$string);
  $adjusted = str_replace('Sun Contributor Agreement','Sun\-SCA',$string);

  $adjusted = str_replace('Carnegie Mellon University','CMU',$string);

  $adjusted = str_replace('Eclipse Public License','EPL',$string);
  $adjusted = str_replace('Open Software License','OSL',$string);
  $adjusted = str_replace('Open Public License','OPL',$string);

  $adjusted = str_replace('Beerware','BEER\-WARE',$string);

  //  commercial
  $adjusted = str_replace('Nvidia License','Nvidia',$string);
  $adjusted = str_replace('Agere LT Modem Driver License','Agere Commercial',$string);
  $adjusted = str_replace('ATI Software EULA','ATA Commercial',$string);

  $adjusted = str_replace('Python Software Foundation','Python',$string);

  $adjusted = str_replace('RealNetworks Public Source License','RPSL',$string);
  $adjusted = str_replace('RealNetworks Community Source Licensing','RCSL',$string);

  $adjusted = str_replace('Creative Commons Public Domain','Public Domain',$string);

  return($adjusted);
} // getFossologySynonyms
/**
 * prepResults
 *
 * create an array of the results.  If there are multiple results, they are
 * comma seperated.
 *
 * @param array $result the associative array with results
 * @return array $processed an associative array with results in an array, or
 * empty array on error.
 */
function prepResults($result) {
  /*
   * take a look at the perl script, it does a lot of adjusting of names for
   * fossology, may need to incorporate that into here as well.
   */

  $all       = array();
  $processed = array();

  if(empty($result)) return(array());
  foreach($result as $file => $rlist) {
    $tList = trim($rlist);
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
    $list = getFossologySynonyms($alist);
    $all = explode(",",$list);
    $processed[$file] = $all;
  }
  return($processed);
}
?>