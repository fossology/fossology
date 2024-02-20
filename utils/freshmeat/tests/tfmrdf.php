#!/usr/bin/php
<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

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
