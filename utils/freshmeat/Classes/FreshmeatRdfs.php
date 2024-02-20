<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Class to deal with freshmeat rdfs.
 *
 * To download a fresmeat rdf, see the class GetFreshMeatRdf.
 *
 * @version "$Id: FreshmeatRdfs.php 851 2008-06-28 05:18:46Z rrando $"
 *
 * Created on Jun 6, 2008
 */

class FreshmeatRdfs
{
  public $error_code        = NULL;
  public $error_out         = array();
  public $uncompresser      = 'bunzip2';
  public $uncompressed_file = NULL;
  private $project_info     = array ();

  public function Uncompress($file)
  {
    /* should also check for existence? */
    /* what about a try catch? */

    if (!(empty ($file)))
    {
      $toss = exec("$this->uncompresser $file 2>&1", $output, $rtn);
    }
    if ($rtn != 0)
    {
      echo "DBG: UNCOMP-> uncompressor returned:$rtn\n";
      $this->error_code = $rtn;
      $this->error_out = $output[0];
      return (FALSE);
    }
    /* if there is no .bz2 on the end nothing gets chopped... */
    $this->uncompressed_file = rtrim($file, '.bz2');
    return (TRUE);
  }

  /**
   * Given an array of projects, find a project in the array.
   *
   * @param string $name package name
   * @param array  $search_space the array of packages
   *
   * @return True/False
   */

/**
 * public function FindInProjInfo
 *
 * @param string $name project name to find
 * @param array  $search_space the array to search
 *
 * NOTE: project names are not standard.  The names Open Logic uses may
 * not be the same as the name in freshmeat (for the same project).
 *
 * Additionally, the names may not be spelled the same or have the same
 * capitalization.
 */
  public function FindInProjInfo($name, $search_space)
  {
    $found = NULL;
    $match = NULL;
    if (empty ($name))
    {
      return (NULL);
    }
    if (empty ($search_space))
    {
      return (NULL);
    }
    $pkey = array_keys($search_space);
    //print "DB: FMRDFS: pkey is:\n";
    //var_dump($pkey);
    //$this->write2file($pkey);
    if(empty($pkey))
    {
      print "DB: FIPI: Pkey is empty!\n";
      return(NULL);
    }
    else
    {
      $found = array_search($name, $pkey);
      if (!is_null($found))
      {
        //print "DB FIPI: setting match\n";
        $match = $search_space[$pkey[$found]];
      }
      return ($match);
    }
  }

  /**
  * method: XtractProjInfo
  *
  * Reads the input file into a structure and returns the structure sorted
  * by project name. See the internal comments for the format of the
  * structure.
  *
  * The input file is expected to be in the FreashMeat rdf format.
  *
  * @param string $rdf_file path to xml file in FM rdf format
  *
  * @return array of projects (see internal notes)
  *
  * @author mark.donohoe@hp.com
  *
  * @version "$Id: FreshmeatRdfs.php 851 2008-06-28 05:18:46Z rrando $"
  *
  * @todo think about making this a class that can give back any number
  * of fields.
  */
  public function XtractProjInfo($rdf_file)
  {
    /*
     * be nice to check if it's compressed and if so, uncompress it,
     * later
     *
     * NOTE: this routine will return a VERY large array of data.
     * See the data structure below for the data collected.
     */

    /*
     * Data Structure:
     *
     *     Key        		Value (s)
     *     ---						--------
     *   project_name     <zero or more urls to archives>,
     *     								<short-description>, version
     *
     */
    if (!(file_exists("$rdf_file")))
    {
      return;
    }
    $meatdoc = simplexml_load_file("$rdf_file");
    foreach ($meatdoc->project as $project)
    {
      $this->project_info["$project->projectname_short"]
      = array (
        "$project->url_tgz",
        "$project->url_bz2",
        "$project->url_zip",
        "$project->desc_short"
      );
      foreach ($project->latest_release as $verdata)
      {
        array_push(& $this->project_info["$project->projectname_short"],
        $verdata->latest_release_version
                   );
      }
    }
    ksort($this->project_info);
    return ($this->project_info);
  }

  function write2file($array_var)
  {
    $name = 'pkeys' . getmypid();
    $FD = fopen($name, 'w') or die ("can't open $name, $php_errormsg\n");
    foreach($array_var as $key=>$value)
    {
      fwrite($FD, "$value \n");
    }
  }
}
