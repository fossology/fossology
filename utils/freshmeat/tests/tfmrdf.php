#!/usr/bin/php
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
 * try/test FreshmeatRdfs methods
 *
 * @version "$Id: $"
 *
 * Created on Jun 6, 2008
 */
require_once ('../../../tests/fossologyUnitTestCase.php');
require_once ('../Classes/FreshmeatRdfs.php');
require_once ('../Classes/GetFreshmeatRdf.php');

class TestFreshmeatRdfs extends fossologyUnitTestCase
{

  function TestFMRdfs()
  {
    print "Starting TestFMRdfs\n";
      // Get a rdf file from FM.
    $Gfm = new GetFreshmeatRdf();
    if($Gfm->get_rdf($Gfm->rdf_name))
    {
      print "wget failed, error code was:$Gfm->error_code\n";
      print "Error message was:\n$Gfm->error_code\n";
    }

    print "rdf name is:$Gfm->rdf_name\n";

    $Rdf = new FreshmeatRdfs($Gfm->rdf_name);

    echo "uncompressing\n";
    if(!$Rdf->Uncompress($Gfm->rdf_name))
    {
      print "Uncompress return non zero status\n";
      print "Error code was:$Rdf->error_code\n";
      print "Error message was:\n$Rdf->error_out\n";
    }

    echo "extracting\n";
    $info = $Rdf->XtractProjInfo($Rdf->uncompressed_file);

    echo "info is:\n";
    print_r($info);
  }
}
?>
