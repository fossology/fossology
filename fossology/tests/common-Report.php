<?php
/*
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
 */

/**
 * \brief common-Report is a library that contains functions used for help
 * in reporting test results.
 *
 * @version "$Id$"
 *
 * Created on Jun 9, 2011 by Mark Donohoe
 */

/**
 * \brief Check the xml reports for failures
 *
 *@param string $file path to the xml file to check
 *
 *@return null on success, array of testcase names that failed on failure
 * Throws exception if input file does not exist.
 *
 * @version "$Id $"
 *
 * Created on Jun 8, 2011 by Mark Donohoe
 */

function check4Failures($xmlFile=NULL)
{

  $failures = array();

  if(file_exists($xmlFile))
  {
    $sx = simplexml_load_file($xmlFile);
    //print_r($sx) . "\n";
  }
  else
  {
    throw new Exception("Can't find file $xmlFile");
  }

  foreach($sx->testsuite as $ts)
  {
    foreach($ts->testcase as $tc)
    {
      foreach($tc->failure as $failure)
      {
        //echo "failure is:$failure\n";
        if(!empty($failure))
        {
          $failures[] = $failure->name;
        }
      }
    }
  }
  if($failures)
  {
    //echo "would return fail, as there are $fail failures\n";
    return($failures);
  }
  return(NULL);
} // check4Failures


/**
 * \brief Check the CUnit xml reports for failures
 *
 *@param string $file path to the xml file to check
 *
 *@return null on success, array of testcase names that failed on failure
 * Throws exception if input file does not exist.
 *
 * @version "$Id$"
 *
 * Created on Jun 9, 2011 by Mark Donohoe
 */
function check4CUnitFail($xmlFile=NULL)
{
  $failures = array();

  if(file_exists($xmlFile))
  {
    $sx = simplexml_load_file($xmlFile);
    //echo "cunit looks like:\n";print_r($sx) . "\n";
  }
  else
  {
    throw new Exception("can't find file $xmlFile");
  }

  // dive deep to get the data we need.
  foreach($sx->CUNIT_RESULT_LISTING as $cuResutList)
  {
    foreach($cuResutList->CUNIT_RUN_SUITE as $cuRunSuite)
    {
      //echo "cuRunSuite is:\n";print_r($cuRunSuite) . "\n";
      foreach($cuRunSuite->CUNIT_RUN_SUITE_SUCCESS as $cuTestRecord)
      {
        foreach($cuTestRecord as $cuRunStat)
        {
          foreach($cuRunStat->CUNIT_RUN_TEST_FAILURE as $failRecord)
          {
            echo "failRecord is:\n";print_r($failRecord) . "\n";
            $failures[] = $failRecord;
          }
        }
      }
    } // foreach($cuResutList->CUNIT_RUN_SUITE
  } // foreach($sx->CUNIT_RESULT_LISTING
  if(empty($failures))
  {
    return(NULL);  // success
  }
  else{
    return($failures);
  }
} //check4CUnitFail
?>