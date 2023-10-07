<?php
/*
 SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 *
 * \brief base class for fossology integration.
 *
 * This class holds properties that extenders of this class should use, such
 * as srcPath or logPath.
 *
 * @param string $sourcePath The fully qualified path to the fossology sources.
 * If no source path is supplied, the current working directory will be used.
 * This may or may not work for the caller, but is better than failing.
 * Operating in this way should allow the code to run standalone or in Jenkins.
 *
 * @param string $logPath The fully qualified path to the log file. The default
 * is to use the current working directory.  If the the logfile cannot be opened
 * an expection is thrown.
 *
 * @return object or exception
 *
 * @version "$Id$"
 *
 * @author markd
 *
 * Created on Aug 11, 2011 by Mark Donohoe
 */
class FoIntegration
{
  public $srcPath;
  public $logPath;
  protected $LOGFD;

  public function __construct($sourcePath, $logPath=NULL)
  {
    if(empty($sourcePath))
    {
      $this->srcPath = getcwd();
    }
    else
    {
      $this->srcPath = $sourcePath;
    }
    if(is_NULL($logPath))
    {
      $this->logPath = getcwd() . "/fo_integration.log";
      echo "DB: logpath is:$this->logPath\n";
    }
    else
    {
      $this->logPath = $logPath;
      echo "DB: logpath is:$this->logPath\n";
    }

    $this->LOGFD = fopen($this->logPath, 'a+');
    if($this->LOGFD === FALSE)
    {
      $error = "Error! cannot open $this->logPath" . " File: " . __FILE__ .
        " on line: " . __LINE__;
      throw new exception($error);
    }

  } // __construct

  /**
   * \brief log a message in a file
   *
   * @param string $message The message to log.
   *
   * @todo add in a time stamp for each entry written.
   *
   * @return boolean, false if the write failed, true otherwise.
   *
   */
  protected function log($message)
  {
    if(fwrite($this->LOGFD, $message) === FALSE)
    {
      // remove the warning? and have caller do it?
      echo "WARNING! cannot write to log file, there may be no log messages\n";
      return(FALSE);
    }
    return(TRUE);
  } // log

} //fo_integration


/**
 *
 * \brief make fossology, check for warnings and errors
 *
 * @param string $srcPath fully qualified path to the source tree, e.g.
 * /home/myhome/fossology/src/
 *
 * @param string $logPath fully qualified path to the logfile, default is
 * current working directory with filename fo_integration .log
 *
 * @return object reference or exception on failure.
 *
 * @todo if running in hudson, just to a make with output going to console and
 * let jenkins count errors and warnings.
 *
 * @author markd
 *
 */
class Build extends FoIntegration
{

  /**
   * \brief make fossology
   *
   * Cd's into the supplied source path.  Does a make clean then a make.  Checks
   * the result of the make and returns a boolean.
   *
   * @return boolean true for no make errors, false for 1 or more make errors
   *
   */
  function __construct($srcPath, $logPath=NULL)
  {
    parent::__construct($srcPath,$logPath);
    if (!chdir($this->srcPath))
    {
      throw new exception("FATAL! can't cd to $this->srcPath\n");
    }
    $this->log("Executing Make Clean\n");
    $mcLast = exec('make clean > make-clean.out 2>&1', $results, $rtn);
    //print "results of the make clean are:$rtn, $mcLast\n";
    $this->log("Executing Make all\n");
    $makeLast = exec('make > make.out 2>&1', $results, $rtn);
    //print "results of the make are:$rtn, $makeLast\n"; print_r($results) . "\n";
    if ($rtn == 0)
    {
      //print "results of the make are:\n"; print_r($results) . "\n";
      if (array_search('Error', $results))
      {
        //print "Found Error string in the Make output\n";
        // TODO: write results out to: make.out ?? what ??
        throw new exception("Errors in make, inspect make.out for details\n");
      }
    }
    else
    {
        throw new exception("Errors in make, inspect make.out for details\n");
    }
  } // makeFossology

} // build
