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
 * Class to deal with freshmeat rdfs.
 *
 * To download a fresmeat rdf, see the class GetFreshMeatRdf.
 *
 * @version "$Id: $"
 *
 * Created on Jun 6, 2008
 */

class FreshmeatRdfs
{
  public $uncompresser = 'bunzip2';
  private $project_info = array ();

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
      return (FALSE);
    }
    return (TRUE);
  }

  public function FindInProjInfo($name, $search_space)
  {
    if (empty ($name))
    {
      return (FALSE);
    }
    if (empty ($search_space))
    {
      return (FALSE);
    }
    $pkey = array_keys($search_space, $name);
    if(empty($pkey))
    {
      return(NULL);
    }
    else
    {
      $found = $search_space[$key];
      return ($found);
    }
  }

  /**
  * method: XtractProjInfo
  *
  * Reads the input file into a structure and returns the structure sorted
  * by project anme. See the internal comments for the format of the
  * structure.
  *
  * The input file is expected to be in the FreashMeat rdf format.
  *
  * @param string $xml_file path to xml file in FM rdf format
  *
  * @author mark.donohoe@hp.com
  *
  * @version "$Id: $"
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
     *     Key          Key          Value(s)
     *     ---          ---          --------
     * project_rank project_name     project_id,
     *                               <zero  or more urls to archives>,
     *                               <home_url>, <short-description>,
     *                               version-info (3 tokens).
     *
     */
    if (!(file_exists("$rdf_file")))
    {
      return;
    }
    $meatdoc = simplexml_load_file("$rdf_file");
    foreach ($meatdoc->project as $project)
    {
      $this->project_info["$project->popularity_rank"] ["$project->projectname_short"]
      = array (
        "$project->url_tgz",
        "$project->url_bz2",
        "$project->url_zip",
        "$project->url_homepage",
        "$project->desc_short"
      );
      foreach ($project->latest_release as $verdata)
      {
        array_push(& $this->project_info["$project->popularity_rank"],
        $verdata->latest_release_version, $verdata->latest_release_id,
        $verdata->latest_release_date
                  );
      }
    }
    ksort($this->project_info);
    return ($this->project_info);
  }
}
?>
