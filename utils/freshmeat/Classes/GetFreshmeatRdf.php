<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Class to get the Freshmeat RDF file
 *
 * @param $name path to the downloaded rdf file
 *
 * Sets: $rdf_name
 *
 * @version "$Id: GetFreshmeatRdf.php 842 2008-06-25 21:04:24Z rrando $"
 *
 * Created on Jun 6, 2008
 */

 /*
  * Where will this leave the file? we just pass in some name...so it's
  * current dir or a full path to where...?
  */

class GetFreshMeatRdf
{
  public $rdf_url = "http://freshmeat.net/backend/fm-projects.rdf.bz2";
  public $rdf_name;
  public $error_code;
  public $error_out;
  private $Date;

  public function __construct($name = NULL)
  {
    if (empty ($name))
    {
      $this->Date = date('Y-n-d');
      $this->rdf_name = "fm-projects.rdf-$this->Date.bz2";
    }
    else
    {
      $this->rdf_name = $name;
    }
    //echo "__CON: rdf_name is:$this->rdf_name\n";
  }

  /**
   * method: get_rdf
   *
   * @param string $name name of the rdf file, default is
   * fm-projects.rdf-yyyy-m-dd.bz2
   */
  public function get_rdf($name = NULL)
  {
    if (empty ($name))
    {
      $name = $this->rfd_name;
    }
    $cmd = "wget -q -O $name $this->rdf_url";
    //echo "will do\n$cmd\n";
    $toss = exec($cmd, $output, $rtn);
    $this->error_code = $rtn;
    $this->error_out  = $output;
  }
}
