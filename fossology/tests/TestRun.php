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
 * @version "$Id: $"
 *
 * Created on Dec 18, 2008
 */

class TestRun
{
  public $srcPath;

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

  public function makeInstall()
  {
    if(!chdir($this->srcPath))
    {
      print "Error can't cd to $this->srcPath\n";
    }
    $miLast = exec('sudo make install 2>&1', $results, $rtn);
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
    $mcLast = exec('make clean 2>&1', $results, $rtn);
    $makeLast = exec('make 2>&1', $results, $rtn);
    if($rtn === 0 )
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

  public function schedTest()
  {

  }

  public function setSrcPath($path)
  {

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
    /*
     * cd to the srcpath, svn update...
     */
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
