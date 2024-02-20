<?php
/*
SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * Test Run class for nightly regression tests
 *
 * This class provides the methods needed to automate nightly
 * regressions tests for fossology using thi UI test suite.
 *
 * @version "$Id: TestRun.php 3579 2010-10-20 01:59:52Z rrando $"
 *
 * Created on Dec 18, 2008
 */
class TestRun
{
	public $srcPath;
	private $NotRunning = FALSE;
	private $Running = TRUE;
	private $schedulerPid = -1;
	
	/**
	 *  constructor
	 *
	 * @param string $srcPath optional path to the fossology sources.
	 *        If fossology is being checked out for the first time, the path
	 *        should not include fossology, that will get created as part of the
	 *        checkout. For example, /home/testdir/tryit/.
	 *        Or this path indicates where fossology is located (if not using
	 *        the default location).  In this case fossology should be included in
	 *        the path.  For example, /home/mydir/fossology.
	 *
	 * @return resource object reference
	 *
	 */
	public function __construct($srcPath = NULL) {

		if (empty($srcPath)) {
			// default
			$this->srcPath = '/home/fosstester/fossology';
		}
		else
		{
			$scrPath = rtrim($srcPath, '/');
			$this->srcPath = $srcPath . "/fossology";
		}
		return;
	}
	/**
	 * checkFop
	 * \brief checks the output file from a fo-postinstall for the strings
	 * FATAL, error and Error. Assumes fop.out file is in the cwd.
	 *
	 * @return Boolean. True if any of the strings are found, false if not found.
	 *
	 */
	protected function checkFop()
	{
		$fMatches = array();
		$eMatches = array();
		$fatalPat = '/FATAL/';
		$errPat = '/error/i';
		 
		$fMatches = preg_grep($fatalPat, file('fop.out'));
		$eMatches = preg_grep($errPat, file('fop.out'));
		if(empty($fMatches) && empty($eMatches))
		{
			print "DEBUG: returning false, matched arrays are:\n";
			print_r($fMatches) . "\n";
			print_r($eMatches) . "\n";
			return(FALSE);
		}
		else
		{
			return(TRUE);
		}
	}

	/**
	 * \brief check out the top of trunk fossology sources.
	 * Uses attribute set by the constructor.
	 *
	 * @return boolen
	 */
	public function checkOutTot() {

		$Tot = 'svn co https://fossology.svn.sourceforge.net/svnroot/fossology/trunk/fossology';

		/* remove fossology from the path so we don't get fossology/fossology */
		$home = rtrim($this->srcPath, '/fossology');

		if (chdir($home)) {
			$last = exec($Tot, $output, $rtn);
			//print "checkout results are, last and output:$last\n";
			//print_r($output) . "\n";
			if ($rtn != 0) {
				print "ERROR! Could not check out FOSSology sources at\n$Tot\n";
				print "Error was: $output\n";
				return (FALSE);
			}
		}
		else {
			print "ERROR! could not cd to $home\n";
			return (FALSE);
		}
		return(TRUE);
	}
	/**
	 * checkScheduler
	 *
	 * Check to see if the scheduler is running, if so stop it:
	 * 1. stop it with the standard /etc/initd./fossology stop
	 * 2. If it is still running find the pid and kill -9
	 */
	private function checkScheduler() {
		$pLast = NULL;
		$pLast = exec('ps -ef | grep scheduler | grep -v grep', $results, $rtn);
		//print "DB: CKS: pLast is:$pLast\n";
		if (empty($pLast)) {
			return ($this->NotRunning);
		}
		else {
			return ($this->Running);
		}
	}
	private function getSchedPid() {
	  
	  $parts = array();
		$psLast = NULL;
		$cmd = 'ps -ef | grep fossology-scheduler | grep -v grep';
		$psLast = exec($cmd, $results, $rtn);
    // scheduler is not running.
		if($psLast == "")
		{
		  return(NULL);
		}
		$parts = split(' ', $psLast);
		//print "parts is:\n"; print_r($parts) . "\n";
		return ($parts[5]);
	}
	public function foPostinstall() {
		 
		if (!chdir($this->srcPath)) {
			print "Error can't cd to $this->srcPath\n";
		}
		$foLast = exec('sudo /usr/local/lib/fossology/fo-postinstall > fop.out 2>&1', $results, $rtn);

		// remove this check as fo-postinstall reports true (0) when there are errors
		//        if ($rtn == 0) {
		if ($this->checkFop() === FALSE)
		{
			return (TRUE);
		}
		else {$cmd = 'ps -ef | grep fossology-scheduler | grep -v grep';
			return (FALSE);
		}
		}
		public function makeInstall() {
			if (!chdir($this->srcPath)) {
				print "Error can't cd to $this->srcPath\n";
			}
			$miLast = exec('sudo make install > make-install.out 2>&1', $results, $rtn);
			if ($rtn == 0) {
				if (array_search('Error', $results)) {
					// TODO: write results out to: make-install.out
					//print "Found Error string in the Make output\n";
					return (FALSE);
				}
				else {
					return (TRUE);
				}
			}
			else {
				return (FALSE);
			}
		}
		public function makeSrcs() {

			if (!chdir($this->srcPath)) {
				print "Error can't cd to $this->srcPath\n";
			}
			$mcLast = exec('make clean > make-clean.out 2>&1', $results, $rtn);
			//print "results of the make clean are:$rtn, $mcLast\n";
			$makeLast = exec('make > make.out 2>&1', $results, $rtn);
			//print "results of the make are:$rtn, $makeLast\n"; print_r($results) . "\n";
			if ($rtn == 0) {
				//print "results of the make are:\n"; print_r($results) . "\n";
				if (array_search('Error', $results)) {
					//print "Found Error string in the Make output\n";
					// TODO: write results out to: make.out
					return (FALSE);
				}
				else {
					return (TRUE);
				}
			}
			else {
				return (FALSE);
			}
		} // makeSrcs

		public function schedulerTest() {
			$StLast = exec('sudo /usr/local/lib/fossology/fossology-scheduler -t -L stdout > ST.out 2>&1', $results, $rtn);
			if ($rtn != 0) {
				if (array_search('FATAL', $results)) {
					return (FALSE);
				}
				return (FALSE);
			}
			return (TRUE);
		}
		public function setSrcPath($path) {
		}
		/**
		 * startScheduler
		 *
		 * Check to see if the scheduler is running, if so stop it:
		 * 1. stop it with the standard /etc/initd./fossology stop
		 * 2. If it is still running find the pid and kill -9
		 */
		public function startScheduler() {
			if ($this->checkScheduler() === $this->Running) {
				return ($this->Running);
			}
			else {
				$stdStart = exec("sudo /etc/init.d/fossology start > /dev/null 2>&1 &", $results, $rtn);
				sleep(5);
				if ($this->checkScheduler() === $this->Running) {
					return ($this->Running);
				}
				else {
					return ($this->NotRunning);
				}
			}
		}
		/**
		 * stopScheduler
		 *
		 * \breief Check to see if the scheduler is running, if so stop it:
		 * 1. stop it with the standard /etc/initd./fossology stop
		 * 2. If it is still running find the pid and kill -9
		 *
		 * @return void
		 */
		public function stopScheduler() {
			if ($this->checkScheduler() === $this->NotRunning) {
				return (TRUE);
			}
			else {
				$stdStop = exec('sudo /etc/init.d/fossology stop 2>&1', $results, $rtn);
			}
			// still running, kill with -9
			if ($this->checkScheduler() === $this->Running) {
				$this->schedulerPid = $this->getSchedPid();
				// no pid? Must be stopped, not running
				if($this->schdulerPid === NULL)
				{
				  return(TRUE);
				}
				$killLast = exec("sudo kill -9 $this->schedulerPid 2>&1", $results, $rtn);
			}
			return (TRUE);
		}
		/**
		 * svnUpdate
		 *
		 * run a svn update at $this->srcPath/fossology
		 *
		 * return boolean
		 */
		public function svnUpdate() {
			if (!chdir($this->srcPath)) {
				print "Error can't cd to $this->srcPath\n";
			}
			$svnLast = exec('svn update', $results, $rtn);
			if ($rtn != 0) {
				return (FALSE);
			}
			else {
				return (TRUE);
			}
		}
}
