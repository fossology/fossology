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
 * testLicenseLib
 *
 * common routines for use by license regression tests
 *
 * created: May 29, 2009
 * @version "$Id:  $"
 */

require_once('../commonTestFuncs.php');

/**
 * array_implode
 *
 *  used for imploding multiple arrays into 1 array.  The resulting array
 *  can then be passed to join to produce a string.
 *
 *  Taken from the PHP site (mjong)
 *
 * @param $arrays the arrays to implode
 * @param $target the array to implode into
 * @return $target
 */
function array_implode($arrays, &$target = array()) {
	foreach ($arrays as $item) {
		if (is_array($item)) {
			array_implode($item, $target);
		} else {
			$target[] = $item;
		}
	}
	return $target;
}
/**
 * compare2Master
 *
 * Compare license results to a master result list.
 *
 * @param array $results the license results to compare
 * @param array $master the license Master results to compare to
 *
 * format for the input arrays is:
 * dir/filename is the key
 * results is an array of one or more comma separated results
 *
 * @return array $allResults an array of two arrays, the first array is an
 * associative totals array with 'pass' and 'fail' keys.   This records the
 * total passes and failures.  The second array  is also an associative array
 * of all the results for each file in the format:
 *
 * [license/filename] => Array
 (
 [standard] => Array
 (
 [0] => One or
 [1] => more standards
 )

 [pass] => Array
 (
 [0] => On or more passes
 )

 [fail] => Array
 (
 [0] => On or more failures
 )

 standard is the agreed upon standard for reporting this license.  Initially
 this standard is based on Nomos results, this could change in the future.
 *
 * on error  a single associative array is returned with the first key as
 *  'Error' and the value will a string (the be error message).
 *
 */
function compare2Master($results,$Master) {

	//  error_reporting(-1);    // all errors

  if(!is_array($results)) {
    return(array('Error' => 'Must supply an array of arrays of results'));
  }
  if(empty($results)) {
    return(array('Error' => 'No results to compare against'));
  }
	if(!is_array($Master)) {
		return(array('Error' => 'No Master Array supplied!'));
	}

	$Total         = array();
	$Total['pass'] = 0;
	$Total['fail'] = 0;

	$pass        = array();
	$fail        = array();
	$comparisons = array();
	$standard    = array();
	$passCount   = 0;
	$failCount   = 0;


	// sort inputs by key so comparisons work
	ksort($Master);
	ksort($results);

	$msize = count($Master);
	$rsize = count($results);

	//print "compare2Master: size of results is:$rsize\n";
	
	// don't think this check makes sense anymore? Turn it into a warning?
	if($msize != $rsize) {
		print "FATAL! arrays are not the same size!\n";
		return(array('Error' => 'arrays are not the same size!'));
	}

	//while(list($rpath, $answers) = each($results)) {
	foreach($results as $rpath => $answers){
		list($mpath, $standard) = each($Master);
		$standard = $Master[$rpath];
		if(empty($standard)) {
			print "WARNING!, no standard for $rpath\n";
			continue;
		}
		
		$allDiffs = array_diff($answers,$standard);
    //print "compare2Master: alldiffs  is:\n";print_r($allDiffs) . "\n";

		/* any diffs represent a failure */
		if(count($allDiffs) == 0) {
			$pass = $answers;
		}
		else {
			foreach($allDiffs as $diff) {
				
				//print "compare2Master: (fail) diff is:$diff\n----------------\n";
				$failCount += count($diff);
				$fail[$rpath] = $diff;

				 // remove all diffs from test results to get passes
				 $index = array_search($diff,$answers);
				 $sliced = array_splice($answers,$index,1);
			} 
		}
		$pass = $answers;
		//$pass = array_unique($results);
		$passCount += count($pass);
		$comparisons[$rpath]['standard'] = $standard;
		$comparisons[$rpath]['pass'] = $pass;
		$comparisons[$rpath]['fail'] = $fail;
		$allDiffs = array();
		$pass = array();
		$fail = array();
	} // while
	
	$Total['pass'] += $passCount;
	$Total['fail'] += $failCount;

	$allResults = array($Total,$comparisons);
	//print "compare2Master: all results looks like:\n";print_r($allResults) . "\n";
	return($allResults);

} // compare2Master

function trim_value(&$value) {
	$value = trim($value);
}

/**
 * filterFossologyResults
 * taken from the nomos script license_vetter.pl
 *
 * This is for BSAM.
 *
 * @param string $string a string a license results
 * @return string $adjusted the adjusted results string
 */
function filterFossologyResults($string) {

	$string = str_replace('+',' or later',$string);

	$string = str_replace('Apache Software License','Apache',$string);
	$string = str_replace('Artistic License','Artistic',$string);

	$string = str_replace('Adobe AFM','AFM',$string);

	#    $string = str_replace('Adobe Product License Agreement','',$string);

	$string = str_replace('Affero GPL','Affero',$string);

	$string = str_replace('ATI Software EULA','ATI Commercial',$string);

	$string = str_replace('GNU Free Documentation License','GFDL',$string);

	$string = str_replace('Common Public License','CPL',$string);

	$string = str_replace('Eclipse Public License','EPL',$string);

	$string = str_replace('Microsoft Reference License','MRL',$string);
	$string = str_replace('Reciprocal Public License','RPL',$string);

	$string = str_replace('gSOAP Public License','GSOAP',$string);

	$string = str_replace('Apple Public Source License','APSL',$string);
	$string = str_replace('LaTeX Project Public License','LPPL',$string);
	$string = str_replace('World Wide Web.*','W3C',$string);

	$string = str_replace('IBM Public License','IBM\-PL',$string);

	$string = str_replace('MySQL AB Exception','MySQL',$string);
	$string = str_replace('NASA Open Source','NASA',$string);

	$string = str_replace('Sun Microsystems Binary Code License','SBCLA',$string);
	$string = str_replace('Sun Community Source License TSA','SCSL\-TSA',$string);
	$string = str_replace('Sun Community Source License','SCSL',$string);
	$string = str_replace('Sun Microsystems Sun Public License','SPL',$string);

	$string = str_replace('Sun GlassFish Software License','SGF',$string);
	$string = str_replace('Sun Contributor Agreement','Sun\-SCA',$string);

	$string = str_replace('Carnegie Mellon University','CMU',$string);

	$string = str_replace('Eclipse Public License','EPL',$string);
	$string = str_replace('Open Software License','OSL',$string);
	$string = str_replace('Open Public License','OPL',$string);

	$string = str_replace('Beerware','BEER\-WARE',$string);

	//  commercial
	$string = str_replace('Nvidia License','Nvidia',$string);
	$string = str_replace('Agere LT Modem Driver License','Agere Commercial',$string);
	$string = str_replace('ATI Software EULA','ATA Commercial',$string);

	$string = str_replace('Python Software Foundation','Python',$string);

	$string = str_replace('RealNetworks Public Source License','RPSL',$string);
	$string = str_replace('RealNetworks Community Source Licensing','RCSL',$string);

	$string = str_replace('Creative Commons Public Domain','Public Domain',$string);

	return($string);
} // filterFossologyResults

/**
 * filterNomosResults
 * taken from the nomos script license_vetter.pl
 *
 * this is for filtering both versions of nomos (Glen's and FOSSology)
 *
 * @param string $resultString a string a license results, comma separated
 * @return string $resultString the modified input string.
 */
function filterNomosResults($resultString) {
	/*
	 * this is taken from license_vetter.pl from the OSRB (Paul Whyman).
	 */

	$resultString = str_replace('+',' or later',$resultString);

	$resultString = str_replace('Adobe-AFM','AFM',$resultString);
	$resultString = str_replace('Adobe$','Adobe Commercial',$resultString);

	$resultString = str_replace('Aptana-PL','AptanaPL',$resultString);

	$resultString = str_replace('ATT-Source','ATTSCA',$resultString);

	$resultString = str_replace('AVM','AVM Commercial',$resultString);

	$resultString = str_replace('CC-LGPL','Creative Commons LGPL',$resultString);

	$resultString = str_replace('CC-GPL','Creative Commons GPL',$resultString);

	$resultString = str_replace('GPL-exception','GPL Exception',$resultString);

	$resultString = str_replace('Microsoft-PL','Ms-PL',$resultString);
	$resultString = str_replace('Microsoft-RL','Ms-RL',$resultString);
	$resultString = str_replace('Microsoft-limited-PL','Ms-LPL',$resultString);
	$resultString = str_replace('Microsoft-LRL','Ms-LRL',$resultString);
	$resultString = str_replace('Microsoft-LPL','Ms-LPL',$resultString);
	$resultString = str_replace('Ms-EULA','Microsoft Commercial',$resultString);
	$resultString = str_replace('Ms-SSL','MSSL',$resultString);

	$resultString = str_replace('Public-domain-claim','Public Domain',$resultString);
	$resultString = str_replace('RSA-Security','RSA Commercial',$resultString);
	$resultString = str_replace('Eclipse','EPL',$resultString);
	$resultString = str_replace('Open-PL','OPL',$resultString);
	$resultString = str_replace('Lucent','LPL',$resultString);

	$resultString = str_replace('Genivia','Genivia Commercial',$resultString);

	$resultString = str_replace('CDDL/OpenSolaris','CDDL',$resultString);
	$resultString = str_replace('Sun SCA','Sun-SCA',$resultString);
	$resultString = str_replace('Sun-PL','SPL',$resultString);
	$resultString = str_replace('Sun-BCLA','SBCLA',$resultString);
	$resultString = str_replace('Sun-EULA','Sun Commercial',$resultString);

	$resultString = str_replace('LaTeX-PL','LPPL',$resultString);

	$resultString = str_replace('zlib/libpng','zlib',$resultString);

	$resultString = str_replace('Beerware','BEER-WARE',$resultString);

	$resultString = str_replace('.*Non\-commercial.*','Non-Commercial Only',$resultString);
	$resultString = str_replace('Authorship-inference','Author',$resultString);

	$resultString = str_replace('RealNetworks-RPSL','RPSL',$resultString);
	$resultString = str_replace('RealNetworks-RCSL','RCSL',$resultString);

	$resultString = str_replace('UCWare','UCWare Commercial',$resultString);

	return($resultString);
} //fileterNomosResults

/**
 * foLicenseAnalyis
 *
 * @param string or array $license a single file to analize or an array of file
 * paths.
 * @param string agent to use for analysis one of: bsam, chanomos or nomos
 *
 * @return mixed, either a string or array based on the first parameter,
 * empty string or array on error. See _runAnalysis for the format of the array.
 *
 * \todo make this function smart, have it look in the two locations it would
 * be installed.Right now it assumes upstream.
 */
function foLicenseAnalyis($license,$agent) {

	$Nomos = '/usr/local/lib/fossology/agents/nomos ';      // use this path for now

	switch($agent) {
		case 'bsam':
			$cmd = "/usr/bin/fosslic ";
			//      return(_runAnalysis($license,$cmd));
			print "Starting bsam license analysis\n";
			//$bsam = _runAnalysis($license,$cmd);
			return(_runAnalysis($license,$cmd));
			break;
		case 'nomos':
			print "Starting nomos license analysis\n";
			return(_runAnalysis($license,$Nomos));
			break;
			//    case 'nomos':
			// either use the OSRB one or one installed
			//      return(NULL);
			//      break;
		default:
			return(NULL);
	}
} //foLicenseAnalysis

/**
 * _runAnalysis
 *
 * \brief Run the license analysis
 *
 * @param mixed $licenseList as string or array of filepaths to licenses to
 * analyze.
 * \todo What is the format expected for the incomming array!
 *
 * @param string $cmd the command to run (e.g. /usr/bin/fosslic).
 *
 * @return mixed, either string or array depending on the first parameter.
 *
 * If an array is returned it is in the format:
 * DirName => array
 *    fileName.txt => array
 *       results
 */
function _runAnalysis($licenseList,$cmd){

	global $directory;

	$last = "";
	$all = array();
	$Fossology = array();

	if(is_array($licenseList)) {
		foreach($licenseList as $path => $licenseFileList) {
			//print "_runAnalysis: Path is:$path\n";
			$testFile = $directory ."/" . $path;
			//print "_runAnalysis: running $cmd $testFile 2>&1\n";
			$last = exec("$cmd $testFile 2>&1", $output, $rtn);
			//print "_runAnalysis: last is:$last\n";
			//print "_runAnalysis: nomos output:\n";print_r($result) . "\n";
			/* check for this string... FATAL: Cannot copy */

			/* isolate the answer, the results come out as a sentance */
			$tokens = explode(' ',$last);
			$results = end($tokens);
			$results = trim($results);
			/*
			 Filter the raw results.  We have to filter them, as the naming format has
			 changed over time, so the filtering takes care of that.  This was modeled
			 after the eddy code, since the eddy test files are what we are processing here.
			 */
			$list = filterNomosResults($results);     // name filter
			$all = explode(",",$list);
			$Fossology[$path] = $all;
			$all = array();
			$last = "";
		}
		return($Fossology);
	}

	// process the file
	else {
		$last = exec("$cmd $licenseList 2>&1", $result, $rtn);
		return($last);
	}
} // _runAnalysis

/**
 * loadMasterResults
 *
 * load the master license analysis results from a file, return an array of
 * results.
 *
 * @param string $file optional file name of results file, if no file is passed in
 * the default filename of 'OSRB-nomos-license-matches' is used.
 *
 * @return array $Master the results or FALSE on error
 *
 * The format for the associative array is:
 *
 * key is the filename of the test file, a space, then one or more results strings
 * seperated by comma.
 *
 * for example: Master['somefile'] => result1
 *              Master['otherfile'] => resutlt1,resutl2,resultn....
 *
 */
function loadMasterResults($file=NULL){

	/* load the results to compare against */

	$Master   = array();
	$Standard = array();

	if(strlen($file)) {
		$masterFile = $file;
	}
	else { // default file
		$masterFile = 'OSRB-nomos-license-matches';
	}

	try {
		$FD = @fopen($masterFile, 'r');
	}
	catch (Exception $e){
		print "can't open master file $masterFile\n";
		print $e->getMessage();
		debug_print_backtrace();
		return(FALSE);
	}

	while(($line = fgets($FD, 1024)) !== FALSE) {
		list($file,$standard) = explode(':',$line);
		$standard = trim($standard);
		$list = filterNomosResults($standard);     // name filter
		$Standard = explode(',',$list);
		$Master[$file] = $Standard;
	}

	//print "loadMasterResults: Master results are:\n";print_r($Master) . "\n";

	return($Master);
} // loadMasterResults

/**
 * saveALLResults
 *
 * save the license test results, passed in as an associative array of arrays
 *
 * @param string $fileName the filepath/name to save the results to
 * @param array $results the array of results to save
 *
 * @return boolean: True on success, False on failure
 *
 */
function saveAllResults($fileName,$results) {

	if(!strlen($fileName)) return(FALSE);
	if(empty($results)) return(FALSE);

	try{
		$Std = @fopen($fileName,'w');
		if($Std === FALSE) {
			throw new Exception("Cannot Save Results to file $fileName\n");
		}
	}
	catch(Exception $e){
		print "FATAL!" . $e->getMessage();
		print $e->getMessage();
		return(FALSE);
	}
	foreach($results as $licenseFile => $resultArray) {
		list($licenseType,$filename) = explode('/',$licenseFile);
		$oneResult = "license-type=$licenseType;\n";
		$oneResult .= "file-name=$filename;\n";
		foreach($resultArray as $keyWord => $results) {
			if(is_array($results)) {
				switch($keyWord) {
					case 'standard':
						$oneResult .= "standard=";
						foreach($results as $res){
							$oneResult .= "$res,";
						}
						$oneResult = rtrim($oneResult,',');
						$oneResult .= ";\n";
						break;
					case 'pass':
						$oneResult .= "pass=";
						foreach($results as $res){
							$oneResult .= "$res,";
						}
						$oneResult = rtrim($oneResult,',');
						$oneResult .= ";\n";
						break;
					case 'fail':
						$oneResult .= "fail=";
						foreach($results as $res){
							$oneResult .= "$res,";
						}
						$oneResult = rtrim($oneResult,',');
						$oneResult .= ";\n";
						break;
						/*       case 'missed':
						 $oneResult .= "missed=";
						 foreach($results as $res){
						 $oneResult .= "$res,";
						 }
						 $oneResult = rtrim($oneResult,',');
						 $oneResult .= "\n";
						 break;
						 */
				}
			}
		}
		$many = fwrite($Std, "$oneResult<----->\n");
		//print "oneResult is:\n$oneResult\n";
		$oneResult = '';
	}
	fclose($Std);
	return(TRUE);
} // saveAllResults

/**
 * saveTotals
 *
 * Save the license test totals in a file
 *
 * format is : seperated fields
 * agent:pass:fail
 * where agent is the agent name, e.g. fo-nomos
 * pass is the total passes
 * fail if the total failures
 *
 * @param string $filename the file name (path) to save the results to
 * @param string $agent the agent name e.g. fo-nomos
 * @param associative array $totals using pass and fail for keys the totals for
 * each
 * @return boolean
 */
function saveTotals($fileName,$agent,$totals) {

	if(!strlen($fileName)) {
		return(FALSE);
	}
	if(!strlen($agent)) {
		return(FALSE);
	}
	if(empty($totals)) {
		return(FALSE);
	}
	try{
		$Total = @fopen($fileName,'w');
		if($Total === FALSE) {
			throw new Exception("Cannot Save Results to file $fileName\n");
		}
	}
	catch(Exception $e){
		print "FATAL!" . $e->getMessage();
		return(FALSE);
	}
	$entry = $agent . ':' . $totals['pass'] . ':' . $totals['fail'] . "\n";
	$many = fwrite($Total, $entry);
	fclose($Total);
	return(TRUE);
}
?>