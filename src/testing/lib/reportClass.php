<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Report class
 *
 *@param string $path the fully qualified path to the results file
 *
 *@version "$Id: reportClass.php 3567 2010-10-15 01:06:58Z rrando $"
 *
 * Created on Dec 12, 2008
 */
/*
 * pattern is Starting < > Tests ... data followed by either OK or
 * FAILURES! then results, skip a line then elapse time.
 */

class TestReport {
	public $Date;
	public $Time;
	public $Svn;
	protected $results;
	private $testRuns;
	private $smarty;
	public $resultsFile = NULL;
	public $resultsPath = NULL;

	public function __construct($Path=NULL, $notesPath=NULL) {

		// defaults for now...
		$Latest = '/home/fosstester/public_html/TestResults/Data/Latest';

		if (empty ($Path)) {
			/* Default is use data in Latest*/
			$this->resultsPath = $Latest;
		}
		else if(is_dir($Path)) {
			$this->resultsPath = $Path;
		}
		else if(is_file($Path)) {
			$this->resultsFILE = $Path;
		}

	} // __construct

	/**
	 * getException
	 *
	 * Find exceptions in the results
	 *
	 *  @param string $suite the results for a particular test suite.
	 *
	 *  @return array $xList the list of exceptions (if any), can return an
	 *  empty array.
	 *
	 */

	protected function getException($suite) {

		/*
		 * Execptions can be identified by ^Exception\s[0-9]+!
		 */
		$matched = preg_match_all('/^Exception\s[0-9]+.*?$/m',$suite, $matches);
		$pm = preg_match_all('/^Unexpected PHP error.*?$/m',$suite, $ematches);

		//print "DB: matched expections is:$matched\n";
		//print "DB: matches are:\n";print_r($matches);
		//print "DB: matched php is:$pm\n";
		//print "DB: ematches are:\n";print_r($ematches);

		$elist = array();
		foreach($matches as $ex){
			foreach($ex as $except) {
				foreach ($ematches as $ematch){
					foreach($ematch as $estring){
						$elist[$except] = $estring;
					}
				}
			}
		}
		return($elist);
	}

	/**
	 *  getFailures
	 *
	 *  Find test failures in the results, capture the first
	 *  2 errors, the rest are usually a result of the first 1 or 2.
	 *
	 *  @param string $suite the results for a particular test suite.
	 *
	 *  @return array $xList the first 2 errors (if any), can return an
	 *  empty array.
	 *
	 */

	protected function getFailures($suite) {
		/*
		 * notes: errors just keep incrementing, and are demarcated with a ^nn)\s
		 * where nn is the failure number
		 *
		 */
		//print "DB: suite is:\n";print_r($suite) . "\n";
		//$matched = preg_match('/^[0-9]+\).*?$/m',$suite, $matches);
		$matched = preg_match_all('/^[0-9]+\).*?$/m',$suite, $matches);
		$exm = preg_match_all('/Expected:.*/', $suite, $expected);
		$gm = preg_match_all('/Got:.*/', $suite, $got);
		//print "DB: matched errors is:$matched\n";
		//print "DB: matches are:\n";print_r($matches) . "\n";
		// unwind the array of arrays preg_match_all returns
		foreach($matches as $flist){
			foreach($flist as $failure){
				$failList[] = $failure;
			}
		}
		if(!empty($expected))
		{
			foreach($expected as $elist)
			{
				foreach($elist as $expResult)
				{
					$failList[] = $expResult;
				}
			}
		}
		if(!empty($got))
		{
			foreach($got as $glist)
			{
				foreach($glist as $gotResult)
				{
					$failList[] = $gotResult;
				}
			}
		}
		return($failList);
	}




	/**
	 * gatherData
	 *
	 * read a file and return the number of passes, failures,exceptions
	 * and elapse time.
	 *
	 * Set attributes Date and Time.
	 */

	/*
	 * pattern is Starting < > Tests ... data followed by either OK or
	 * FAILURES! then results, skip a line then elapse time.
	 */
	public function parseResultsFile($file) {

		$results = array ();
		if (empty ($file)) {
			return FALSE;
		}

		$failures   = array();
		$exceptions = array();

		$FD = fopen($file, 'r');
		while ($line = fgets($FD, 1024)) {
			if (preg_match('/^Running\sAll/', $line)){
				$DateTime = $this->parseDateTime($line);
				list ($this->Date, $this->Time) = $DateTime;
				$svnline = preg_split('/:/', $line);
				$this->Svn = $svnline[4];
				//print "DB: top stuff is:\ndate:$this->Date\ntime:$this->Time\nsvn:$this->Svn\n";
			}
			elseif (preg_match('/^Starting.*?on:/', $line)) {
				$aSuite = $this->getSuite($FD,$line);
				$sum = $this->suiteSummary($aSuite);
				list($pass, $fail, $except) = preg_split('/:/',$sum[1]);
				//print "DB: pass, fail, except are:$pass,$fail,$except\n";
				if($fail != 0) {
					$failures = $this->getFailures($aSuite);
					//print "DB: failure list is:\n";print_r($failures);
				}
				if($except != 0) {
					$exceptions = $this->getException($aSuite);
					//print "DB: exception list is:\n";print_r($exceptions);
				}
				// unroll the summary array into a key value array of 1 level
				//print "DB: sum is:\n";print_r($sum) . "\n";
				for($i=0; $i < count($sum); $i++) {
					$summary[$sum[$i]] = array($sum[$i+1]);
					$i++;
				}
				//print "DB: summary is:\n";print_r($summary) . "\n";
				if(empty($failures)) {
					continue;
				}
				else {
					$suite = $sum[0];
					$summary[$suite][]  = array('failures' => $failures);
					$failures   = array();
				}
				if(empty($exceptions)) {
					continue;
				}
				else {
					$suite = $sum[0];
					$summary[$suite][] = array('exceptions' => $exceptions);
					$exceptions = array();
				}
			}
			else {
				continue;
			}
		} // while

		// return all 3
		//print "summary is:\n";print_r($summary) . "\n";
		return ($summary);
	} // parseResultsFile

	/**
	 * getResult
	 *
	 * using the open file descriptor, read from the file and create a space
	 * seperated string of results.  A result is everything up to the string
	 * <----->.
	 *
	 * @param resource $FD opened file resource to the results file
	 * @return string $result the result string
	 *
	 */
	protected function getResult($FD) {

		if(!is_resource($FD)) {
			return(FALSE);
		}
		while($line = fgets($FD,1024)) {
			$line = trim($line);
			if(strcasecmp($line,'<----->') == 0) {
				break;  // all done
			}
			$result .= $line .' ';
		}
		return($result);
	}

	/**
	 * getSuite
	 *
	 * Gather all the lines in the results file associated with the suite output.
	 *
	 * @param resource $FD, open file positioned at the start of a suite
	 * @param string $line the line that matched the start of the suite
	 *
	 * @return string $suite one long string of the suite results, imbeded newlines.
	 *
	 */

	protected function getSuite($FD,$line) {

		$suite = null;

		/* Save the initial line, it's the start of the suite! */
		$suite .= $line;

		/*
		 Save every line, looking for the end of the run, marked by either an OK or FAILURES key
		 word.  Then save the last lines and return.
		 */

		while ($line = fgets($FD, 1024)) {
			if (preg_match('/^OK/', $line) || preg_match('/^FAILURES/', $line)) {
				$line = fgets($FD, 1024);
				if (preg_match('/^Test cases run:/', $line))
				$suite .= $line;
				$tossme = fgets($FD, 1024);
				$line = fgets($FD, 1024);
				$suite .= $line;
				//print "DB: suite is:\n$suite\n";
				return($suite);
			}
			else {
				$suite .= $line;
			}
		}
	} // getSuite
	/**
	* globdata
	*
	* Parse the data and then put all the data into one big array and then let
	* smarty display it
	*
	* @param array $results the data array to add to
	* @param array $moData the data array to glob onto the other array
	*
	* @return array the first parameter globed together with the second.
	*
	*/
	function globdata($results, $moData)
	{
		$dataSize = count($moData);
		for ($suite = 0; $suite <= $dataSize; $suite += 3)
		{
			if (($suite +2) > $dataSize)
			{
				break;
			}

			$suiteName = $this->parseSuiteName($moData[$suite]);
			array_push($results, $suiteName);
			//print "parsed suite name:$suiteName\n";

			$pfe_results = $this->parseResults($moData[$suite +1]);
			$pfe = explode(':', $pfe_results);
			array_push($results, $pfe[0]);
			array_push($results, $pfe[1]);
			array_push($results, $pfe[2]);
			//print "<pre>BD-GD: resutlts are:</pre>\n"; print "<pre>"; print_r($results) . "</pre>\n";

			$etime = $this->parseElapseTime($moData[$suite +2]);
			array_push($results, $etime);
			//print "The elapse time was:$etime\n\n";
		}
		return ($results);
	} //globdata

	/**
	 * parseDateTime
	 *
	 * Parse the start line from the test suite output, return the date and
	 * time
	 *
	 * @param string $line the line to parse
	 *
	 * @return array date and time.
	 */
	private function parseDateTime($line)
	{
		//print "<pre>DB:PDT: line is:\n$line</pre>\n";
		if (empty ($line))
		{
			return array ();
		}
		$pat = '.*?s\son:(.*?)\sat\s(.*?)\s';
		$matches = preg_match("/$pat/", $line, $matched);
		$dateTime[] = $matched[1];
		$dateTime[] = $matched[2];
		//print "matched is:\n"; print_r($matched) . "\n";
		return ($dateTime);
	}

	/**
	 * parseLicenseResults
	 *
	 * read the results file and parse it.
	 *
	 * @param resource $FD opened file resource.
	 * @return $array array of all of the results
	 *
	 * @todo rename this to parsePassFailResults
	 *
	 */
	public function parseLicenseResults($FD) {

		if(!is_resource($FD)) {
			return(FALSE);
		}

		$All         = array();
		$FileName    = array();
		$LicenseType = array();
		$VettedName  = array();
		$results     = array();

		while($line = $this->getResult($FD)){
			//$line = getResult($FD);
			$resultParts = explode(';',$line);
			list($lKey,$licenseType) = explode('=',$resultParts[0]);
			list($fnKey,$fileName)   = explode('=',$resultParts[1]);
			$FileName[] = rtrim($fileName,'.txt');
			$LicenseType[$licenseType] = $FileName;
			//print "PLR: before = explode results is:{$resultParts[1]}\n<br>";
			list($fnKey,$std) = explode('=',$resultParts[1]);
			$VettedName[]     = str_replace(',',",<br>",$std);
			list($pKey,$pass) = explode('=',$resultParts[2]);
			$results[]        = str_replace(',',",<br>",$pass);
			list($fKey,$fail) = explode('=',$resultParts[3]);
			$results[]        = str_replace(',',",<br>",$fail);
		}
		$All[] = $LicenseType;
		$All[] = $VettedName;
		$All[] = $results;
		return($All);
	}

	/**
	 * parseLicenseTotals
	 *
	 *  parse the liscense total file
	 *
	 *  @param resource $FD opened file descriptor
	 *  @return array an array of 3 arrays, agent, pass, fail.
	 *
	 */
	public function parseLicenseTotals($FD) {

		if(is_resource($FD)) {
			$agent = array();
			$pass  = array();
			$fail  = array();

			while (!feof($FD)) {
				$line = trim(fgets($FD, 1024));
				list($agent[],$pass[],$fail[]) = explode(':',$line);
			}
			fclose($FD);
			return(array($agent,$pass,$fail));
		}
		else {
			return(FALSE);
		}
	} // parseLicenseTotals

	/**
	 * parseSuiteName
	 *
	 * parse a line of text, return the 2nd and 3rd token as a hyphonated
	 * name.
	 *
	 * @param string $string the string to parse
	 *
	 * @return boolean (false or a string)
	 */
	private function parseSuiteName($string) {
		if (empty ($string))
		{
			return (FALSE);
		}
		$pat = '^Starting\s(.*?)\son:';
		$matches = preg_match("/$pat/", $string, $matched);
		//print "<pre>matched is:<pre>\n"; print_r($matched) . "\n";
		return ($matched[1]);
	}

	/**
	 * parseResults
	 *
	 * parse a line of text that represents simpletest test result line.
	 * Return an associative array with passes, failures and exceptions as
	 * the keys,
	 *
	 * @param string $string the string to parse
	 *
	 * @return string? $res
	 */
	private function parseResults($string)
	{
		if (empty ($string))
		{
			return (FALSE);
		}
		//$pat = '.*?(Passes):(.*?).\s(Failures):\s(.*?).+(Exceptions):\s(.*)';
		$pat = '.*?(Passes):\s(.*?),\s(Failures):\s(.*?),\s(Exceptions):\s(.*)';
		$matches = preg_match("/$pat/", $string, $matched);
		$results = array ();
		if ($matches)
		{
			$results[$matched[1]] = $matched[2];
			$results[$matched[3]] = $matched[4];
			$results[$matched[5]] = $matched[6];
			$res = $matched[2] . ":" . $matched[4] . ":" . $matched[6];
		}
		//return ($results);
		return ($res);
	}

	/**
	 * parseElapseTime
	 *
	 * Given a string that represents the elapse time printed by the
	 * fossology tests, parse it and return a string in the form hh:mm:ss.
	 *
	 * @param string $string
	 * @return boolean (string or false)
	 */
	private function parseElapseTime($string)
	{
		if (empty ($string))
		{
			return (FALSE);
		}
		$parts = array ();
		$pat = '.+took\s(.*?)\sto\srun$';
		$matches = preg_match("/$pat/", $string, $matched);
		//print "the array looks like:\n"; print_r($matched) . "\n";
		$parts = explode(' ', $matched[1]);
		//print "explode array looks like:\n"; print_r($parts) . "\n";
		//$time = 'infinity';
		$sizep = count($parts);
		$etime = NULL;
		for ($i = 0; $i < $sizep; $i++)
		{
			$etime .= $parts[$i] . substr($parts[$i +1], 0, 1) . ":";
			$i++;
		}
		$etime = rtrim($etime, ':');
		return ($etime);
	}


	/**
	 * suiteSummary
	 *
	 * produce a summary list from the input:
	 * - suite name
	 * - Number of tests
	 * - Number of failures
	 * - Number of exceptions
	 *
	 * @param string $suite  the suite output as one long string.
	 *
	 * @return array $summary
	 *
	 */
	public function suiteSummary($suite) {
		$suiteName = $this->parseSuiteName($suite);
		$results = $this->parseResults($suite);
		//print "DB: suiteName is:$suiteName\n";
		//print "DB: results is:$results\n";
		return(array($suiteName,$results));
	}
}
