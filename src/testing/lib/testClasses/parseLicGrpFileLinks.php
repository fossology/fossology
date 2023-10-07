<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * DO NOT USE THIS CLASS.  IT's not done.
 *
 *
 * Given a fossology License
 * Groups page, parse it and return the license Groups table.  The rest
 * of the page can be parsed by the browseMenu class.
 *
 * @param string $page the xhtml page to parse
 *
 * @return assocative array with  Can return an empty array indicating
 * nothing on the page to browse.
 *
 * @version "$Id: parseLicGrpFileLinks.php 1556 2008-10-22 01:34:24Z rrando $"
 * Created on Aug 21, 2008
 */

//require_once ('../commonTestFuncs.php');

class parseLicenseGrpTbl
{
  public $page;
  private $test;

  function __construct($page)
  {
    if (empty ($page)) { return; }
    $this->page = $page;
  }
  /**
   * parseLicenseTbl
   * Given a fossology license browse page parse the license table on
   * the page.
   *
   * @returns array of empty array if license table on that page.
   */
  function parseLicenseGrpTbl()
  {
    /*
     * The pattern below matches the license group file links NOT the table!
     */
    $matches = preg_match_all(
      "|.*?id='LicItem.*?href='(.*?)'>(.*?)<.*?href=\"(.*?)\">(.*?)<|",
      $this->page, $tableEntries, PREG_PATTERN_ORDER);
   print "tableEntries are:\n"; print_r($tableEntries) . "\n";
   //return($this->_createRtnLicTbl($tableEntries, $matches));
  }

  function _createRtnLicTbl($toCombine, $matches)
  {
    /*
    * if we have a match, the create return array, else return empty
    * array.
    */
    if ($matches > 0)
    {
      $numTblEntries = count($toCombine[1]);
      $rtnList = array ();
      for ($i = 0; $i <= $numTblEntries-1; $i++)
      {
        $links = array ();        // initialize/reset
        $pushed = array_push($links ,$toCombine[1][$i], $toCombine[4][$i]);
        if($pushed == 0) { print "parseLicenseTbl: Internal Error! Nothing Inserted!\n"; }
        $rtnList[$toCombine[5][$i]] = $links;
      }
      return ($rtnList);
    }
    else
    {
      return (array ());
    }
  }
}
