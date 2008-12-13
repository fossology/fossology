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
 * Report class
 *
 *@param string $path the fully qualified path to the results file
 *
 *@version "$Id$"
 *
 * Created on Dec 12, 2008
 */

// put full path to Smarty.class.php
require_once ('/usr/share/php/smarty/libs/Smarty.class.php');

class TestReport
{
  private $Date;
  private $Time;
  private $Svn;
  private $results;
  private $testRuns;
  private $smarty;
  public $resultsPath;
  public $notesPath;

  public function __construct($resultsPath=NULL, $notesPath=NULL)
  {
    $this->smarty = new Smarty();
    // defaults for now...
    $Latest = '/home/fosstester/public_html/TestResults/Data/Latest';
    $NotesFile = '/home/fosstester/public_html/TestResults/Data/Latest/Notes';

    if (empty ($resultsPath))
    {
      /* Default is use data in Latest*/
      $this->resultsPath = $Latest;
    }
    if (empty ($notesPath))
    {
      $this->notesPath = $NotesFile;
    }
  }
  /**
  * fileReport
  *
  * read a file and return the number of passes, failures and
  * exceptions, elapse time.
  */

  /*
   * pattern is Starting < > Tests ... data followed by either OK or
   * FAILURES! then results, skip a line then elapse time.
   */
  public function displayReport()
  {
    $this->smarty->template_dir = '/home/markd/public_html/smarty/templates';
    $this->smarty->compile_dir = '/home/markd/public_html/smarty/templates_c';
    $this->smarty->cache_dir = '/home/markd/public_html/smarty/cache';
    $this->smarty->config_dir = '/home/markd/public_html/smarty/configs';

    $notes = file_get_contents($this->notesPath);
    $dt = $this->Date . " " . $this->Time;
    $cols = 5;

    $this->smarty->assign('runDate', $dt);
    $this->smarty->assign('svnVer', $this->Svn);
    $this->smarty->assign('cols', $cols);
    $this->smarty->assign('results', $this->results);
    $this->smarty->display('testResults.tpl');
    //$smarty->assign('TestNotes', $notes);
  }

  /**
  * gatherData
  *
  * read a file and return the number of passes, failures,exceptions
  * and elapse time.
  *
  * Set $Date and $Time.
  */

  /*
   * pattern is Starting < > Tests ... data followed by either OK or
   * FAILURES! then results, skip a line then elapse time.
   */
  public function gatherData($file)
  {
    $resultLines = array ();
    if (empty ($file))
    {
      return FALSE;
    }
    $FD = fopen($file, 'r');
    while ($line = fgets($FD, 1024))
    {
      if (preg_match('/^Running\sAll/', $line))
      {
        $DateTime = $this->parseDateTime($line);
        //$this->testRuns['rundate'] = "{$DateTime[0]}" . "{$DateTime[1]}";
        list ($this->Date, $this->Time) = $DateTime;
        $svnline = preg_split('/:/', $line);
        //print "<pre>DB: SVN: svnline is:</pre>\n";
        //print "<pre>"; print_r($svnline) . "</pre>\n";
        //$this->testRuns['svnVer'] = $svnline[4];
        $this->Svn = $svnline[4];
      }
      elseif (preg_match('/^Starting.*?on:/', $line))
      {
        array_push($resultLines, $line);
        $suiteName = $this->parseSuiteName($line);
      }
      elseif (preg_match('/^OK/', $line) || preg_match('/^FAILURES/', $line))
      {
        $line = fgets($FD, 1024);
        array_push($resultLines, $line);
        $tossme = fgets($FD, 1024);
        $line = fgets($FD, 1024);
        array_push($resultLines, $line);
        $started = 0;
      } else
      {
        continue;
      }
    }
    return ($resultLines);
  }
  /**
   * globdata
   *
   * put all the data into one big glob and then let smarty display it
   *
   * @param array $data the data array to add to
   * @param array $moData the data array to glob onto the other array
   *
   * @returns array the first parameter globed together with the second.
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
      $pfe = split(':', $pfe_results);
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
   * parseSuiteName
   *
   * parse a line of text, return the 2nd and 3rd token as a hyphonated
   * name.
   *
   * @param string $string the string to parse
   *
   * @return boolean (false or a string)
   */
  private function parseSuiteName($string)
  {
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
    $parts = split(' ', $matched[1]);
    //print "split array looks like:\n"; print_r($parts) . "\n";
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

  public function readResultsFiles()
  {
    $this->smarty->template_dir = '/home/markd/public_html/smarty/templates';
    $this->smarty->compile_dir = '/home/markd/public_html/smarty/templates_c';
    $this->smarty->cache_dir = '/home/markd/public_html/smarty/cache';
    $this->smarty->config_dir = '/home/markd/public_html/smarty/configs';

    $this->smarty->display('testRun.tpl');
    foreach (new DirectoryIterator($this->resultsPath) as $file)
    {
      if (!$file->isDot())
      {
        $this->results = array ();
        $filePath = $file->getPathname();
        //print "<pre>DB: RRF:filepath is:$fp</pre>\n";
        $name = basename($filePath);
        if($name == 'Notes') { continue; }
        $temp = $this->gatherData($filePath);
        $this->results = $this->globdata($this->results, $temp);
        //print "<pre>DB: RRF: results are:\n"; print_r($this->results) . "</pre>\n";
        $this->displayReport();
      }
    }
  }
}
?>
