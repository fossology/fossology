<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Given a fossology page, parse all the href's in it and return them in
 * an array
 *
 * @param string $page the xhtml page to parse
 *
 * @return assocative array.  Can return an empty array indicating
 * nothing on the page to browse.
 *
 * @version "$Id: parsePgLinks.php 1546 2008-10-18 04:25:08Z rrando $"
 * Created on Aug 22, 2008
 */

class parsePgLinks
{
  public $page;
  private $test;

  function __construct($page)
  {
    if (empty ($page))
    {
      return;
    }
    $this->page = $page;
  }
  /**
   * function parseLicFileList
   * given a fossology List Files based on License page parse the
   * list(s) on the page.
   *
   * @returns array of assocative arrays. Each assocative array
   * is ordered by folder names with the last key being the
   * filename. An empty array is returned if no license paths on that
   * page.
   */
  function parsePgLinks()
  {
    // The line below is great for pasring hrefs out of a page, from the net
    $regExp = "<a\s[^>]*href=(\'??)([^\'>]*?)\\1[^>]*>(.*)<\/a>";
    $matches = preg_match_all("|$regExp|iU", $this->page, $links, PREG_SET_ORDER);
    print "links are:\n";
    print_r($links) . "\n";
    //$lstFilesLic[] = $this->_createRtnArray($pathList, $matches);
    //return ($lstFilesLic);
  }
  function _createRtnArray($list, $matches)
  {
    /*
     * if we have a match, the create return array, else return empty
     * array
     */
    if ($matches > 0)
    {
      $numPaths = count($list[3]);
      //print "numPaths is:$numPaths\n";
      //print "list is:\n";
      //print_r($list) . "\n";

      $rtnList = array ();
      for ($i = 0; $i <= $numPaths -1; $i++)
      {
        $cleanKey = trim($list[3][$i], "\/<>b");
        $rtnList[$cleanKey] = $list[2][$i];
      }
      return ($rtnList);
    } else
    {
      return (array ());
    }
  }
}
