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
 * Class to get the Freshmeat RDF file
 *
 * @param
 *
 * @return
 *
 * @version "$Id: $"
 *
 * Created on Jun 6, 2008
 */

class GetFreshMeatRdf
{
  public $rdf_url = "http://freshmeat.net/backend/fm-projects.rdf.bz2";
  public $rdf_name;
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
  }
}
?>
