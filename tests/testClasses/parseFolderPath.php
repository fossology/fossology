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
 * Parse the part of the page that has the folder path and mini menu.
 *
 * @param string $page the xhtml page to parse
 *
 * @return array of assocative arrays. Each associative array uses the
 * folder or leaf name for the key and the value is a link (if there
 * is one.)
 *
 * Can return an empty array indicating nothing on the page to browse.
 *
 * @version "$Id$" Created on Aug 21, 2008
 */

require_once ('../commonTestFunc.php');

class parseFolderPath
{
  public $page;
  public $host;
  private $test;

  function __construct($page, $url)
  {
    if (empty ($page)) { return; }
    $this->page = $page;
    if(empty($url)) { return; }
    $this->host = getHost($url);
  }
  /**
   * function parseFolderPath
   *
	 * Parse the part of the page that has the folder path and mini-menu,
   * this method only parses the folder path, see parseMiniMenu.
   *
   * @returns array of assocative arrays. Each assocative array
   * is ordered by folder names with the last key being the
   * leafname, which can be and empty directory.  Usually no link is
   * associated with the leaf node, so it's typically NULL.
   *
   * An empty array is returned if no license paths on that page.
   */
  function parseFolderPath()
  {
    /* Extract the folder path line from the page */
    $regExp = "Folder<\/b>:.*";
    $matches = preg_match_all("|$regExp|", $this->page, $pathLines, PREG_SET_ORDER);
    foreach ($pathLines as $aptr)
    {
      foreach ($aptr as $path)
      {
        $paths[] = $path;
      }
    }
    foreach ($paths as $apath)
    {
      $regExp = ".*?href='(.*?)'>(.*?)<\/a>(.*?)<";
      $matches = preg_match_all("|$regExp|i", $apath, $pathList, PREG_SET_ORDER);
      //print "pathList is:\n"; print_r($pathList) . "\n";
    if ($matches > 0)
    {
      $dirList[] = $this->_createRtnArray($pathList, $matches);
      return ($dirList);
    }
    else
    {
      return (array ());
    }

  }
  }
  function _createRtnArray($list, $matches)
  {
    global $host;
    /*
     * if we have a match, the create return array, else return empty
     * array
     */
      $size = count($list);
      /*
       * The last entry in the array is always a leaf name with no link
       * but it has to be cleaned up a bit....
       */
      for ($i = 0; $i < $size; $i++)
      {
        $cleanKey = trim($list[$i][2], "\/<>b");
        //print "after trim of html cleanKey is:$cleanKey\n";
        if (empty($cleanKey)) { continue; }
        // Make a real link that can be used
        $partLink = $list[$i][1];
        $link = makeUrl($this->host, $partLink);
        $rtnList[$cleanKey] = $link;
        /* check for anything in the leaf entry, if there is, remove
         * the preceeding /
         */
        if (!empty($list[$i][3]))
        {
          $cleanKey = trim($list[$i][3], "\/ ");
          //print "after trim of / cleanKey is:$cleanKey\n";
          if(empty($cleanKey)) { continue; }
          $rtnList[$cleanKey] = NULL;
        }
      }
      return ($rtnList);
  }

  public function setPage($page)
  {
    if(!empty($page)) {$this->page = $page; }
  }
}
?>
