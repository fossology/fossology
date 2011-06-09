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

function check4failures($xmlFile=NULL)
{
  
  $analysis = 'Uploads-Test-Results.xml';
  $fail = 0;

  if(file_exists($xmlFile))
  {
    $sx = simplexml_load_file($xmlFile);
    //print_r($sx) . "\n";
  }
  else
  {
    throw new Exception("can't find file $xmlFile");
  }

  $failures = array();

  foreach($sx->testsuite as $ts)
  {
    foreach($ts->testcase as $tc)
    {
      foreach($tc->failure as $failure)
      {
        //echo "failure is:$failure\n";
        if(!empty($failure))
        {
          $failures[] = $tc['name'];
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
}
?>