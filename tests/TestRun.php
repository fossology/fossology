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
 * Test Run class for nightly regression tests
 *
 * This class provides the methods needed to automate nightly
 * regressions tests for fossology using thi UI test suite.
 *
 * @param
 *
 * @return
 *
 * @version "$Id$"
 *
 * Created on Dec 18, 2008
 */

class TestRun
{
  public $srcPath;
  private $NotRunning = FALSE;
  private $Running    = TRUE;

  public function __construct($srcPath=NULL)
  {
    if(empty($srcPath))
    {
      // default
      $this->srcPath = '/home/fosstester/Src/fossology';
    }
    else
    {
      $scrPath = rtrim($scrPath, '/');
      $this->srcPath = $srcPath . "/fossology";
    }
  }

  /**
   * checkScheduler
   *
   * Check to see if the scheduler is running, if so stop it:
   * 1. stop it with the standard /etc/initd./fossology stop
   * 2. If it is still running find the pid and kill -9
   */
  private function checkScheduler()
  {
    $pLast = NULL;
    $pLast = exec('ps -ef | grep scheduler | grep -v grep', $results, $rtn);
    //print "DB: CKS: pLast is:$pLast\n";
    if(empty($pLast)) { return($this->NotRunning); }
    else { return($this->Running); }
  }

  private function getSchedPid()
  {
    $psLast = NULL;
    $cmd = 'ps -ef | grep fossology-scheduler | grep -v grep';
    $psLast = exec($cmd, $results, $rtn );
    //print "DB: psLast is:$psLast\nresults are:\n"; print_r($results) . "\n";
    $parts = split(' ', $psLast);
    //print "parts is:\n"; print_r($parts) . "\n";
    return($parts[5]);
  }

  public function foPostinstall()
  {
    if(!chdir($this->srcPath))
    {
      print "Error can't cd to $this->srcPath\n";
    }
    $foLast = exec('sudo /usr/local/lib/fossology/fo-postinstall > fop.out 2>&1', $results, $rtn);
    if($rtn == 0)
    {
      return(TRUE);
    }
    else { return(FALSE); }
  }

  public function makeInstall()
  {
    if(!chdir($this->srcPath))
    {
      print "Error can't cd to $this->srcPath\n";
    }
    $miLast = exec('sudo make install > make-install.out 2>&1', $results, $rtn);
    if($rtn == 0)
    {
      if(array_search('Error', $results))
      {
         // TODO: write results out to: make-install.out
        //print "Found Error string in the Make output\n";
        return(FALSE);
      }
      else { return(TRUE); }
    }
    else { return(FALSE); }
  }
  public function makeSrcs()
  {
    if(!chdir($this->srcPath))
    {
      print "Error can't cd to $this->srcPath\n";
    }
    $mcLast = exec('make clean > make-clean.out 2>&1', $results, $rtn);
    $makeLast = exec('make > make.out 2>&1', $results, $rtn);
    if($rtn == 0 )
      {
        //print "results of the make are:\n"; print_r($results) . "\n";
        if(array_search('Error', $results))
        {
          //print "Found Error string in the Make output\n";
          // TODO: write results out to: make.out
          return(FALSE);
        }
        else { return(TRUE); }
      }
    else { return(FALSE); }
  }

  public function schedulerTest()
  {
    $StLast = exec('sudo /usr/local/lib/fossology/fossology-scheduler -t -L stdout > ST.out 2>&1', $results, $rtn);
    if($rtn != 0 )
    {
      if(array_search('FATAL', $results))
      {
          return(FALSE);
      }
      return(FALSE);
    }
    return(TRUE);
  }
 public function setSrcPath($path)
  {

  }

  /**
   * startScheduler
   *
   * Check to see if the scheduler is running, if so stop it:
   * 1. stop it with the standard /etc/initd./fossology stop
   * 2. If it is still running find the pid and kill -9
   */
  public function startScheduler()
  {
    if($this->checkScheduler() === $this->Running) { return($this->Running); }
    else
    {
      $stdStart = exec("sudo /etc/init.d/fossology start > /dev/null 2>&1 &", $results, $rtn);
      sleep(5);
      if($this->checkScheduler() === $this->Running)
      {
        return($this->Running);
      }
      else {
        return($this->NotRunning);
        }
    }
  }

  /**
   * stopScheduler
   *
   * Check to see if the scheduler is running, if so stop it:
   * 1. stop it with the standard /etc/initd./fossology stop
   * 2. If it is still running find the pid and kill -9
   */
  public function stopScheduler()
  {
    if($this->checkScheduler() === $this->NotRunning) { return(TRUE); }
    else
    {
      $stdStop = exec('sudo /etc/init.d/fossology stop 2>&1', $results, $rtn);
    }
    // still running, kill with -9
    if($this->checkScheduler() === $this->Running)
    {
      $this->schedulerPid = $this->getSchedPid();
      $killLast = exec("sudo kill -9 $this->schedulerPid 2>&1", $results, $rtn);
    }
    return(TRUE);
  }

  /**
   * svnUpdate
   *
   * run a svn update at $this->srcPath/fossology
   *
   * return boolean
   */
  public function svnUpdate()
  {
     if(!chdir($this->srcPath))
     {
       print "Error can't cd to $this->srcPath\n";
     }
    $svnLast = exec('svn update', $results, $rtn);
    if($rtn != 0 ) { return(FALSE); }
    else { return(TRUE); }
  }
}
?>
