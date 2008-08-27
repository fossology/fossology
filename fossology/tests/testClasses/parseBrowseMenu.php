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
 * Given a fossology Broswe page, parse it and return it.
 *
 * @param string $page the xhtml page to parse
 *
 * @return assocative array with menu names as keys and links as values.
 * Only menus with links are returned. Can return an empty array
 * indicating nothing on the page to browse.
 *
 * @version "$Id$"
 * Created on Aug 19, 2008
 */

class parseBrowseMenu
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
   * function parseBrowseMenuFiles
   * given a fossology browse page gather up leaf entries, that is
   * 'files'
   *
   * @returns array of files names or empty array if no files on that
   * page.
   */
  function parseBrowseMenuFiles()
  {
    $matches = preg_match_all("|.*?class='mono'.*?align='right'>.*?nbsp;</td><td>(.*?)<|", $this->page, $files, PREG_PATTERN_ORDER);
    if ($numFiles = count($files[1]))
    {
      $links = $this->parseBrowseFileMinis();
      for ($i = 0; $i <= $numFiles -1; $i++)
      {
        $fileLinks[$files[1][$i]] = $links[$i];
      }
      return ($fileLinks);
    } else
    {
      return (array ());
    }
  }
  /**
   * function parseBrowseFileMinis
   * given a fossology browse page gather up view|meta|download entries,
   * and the links associated with them.
   *
   * @returns array of v|m|d keys and links or empty array if none found
   * on that page.
   */
  function parseBrowseFileMinis()
  {
    $matches = preg_match_all("/.*?\[<a href='(.*?)'.*?>([V|M|Down].*?)</", $this->page, $fileMini, PREG_PATTERN_ORDER);
    //print "fileMini Menus are:";
    //print_r($fileMini) . "\n";
    return ($this->_createMiniArray($fileMini, $matches));
  }
  /**
   * function parseBrowseDirs
   * given a fossology browse page gather up directory entries, and the
   * links associated with them.
   *
   * @returns array of directory names as  keys and links or empty array
   * if none found on that page.
   */
  function parseBrowseMenuDirs()
  {
    $matches = preg_match_all("/.+class='mono'.*?<a href='(.*)'>(.*?)<\/a>/", $this->page, $dirs, PREG_PATTERN_ORDER);
    //print "dirs is:";
    //print_r($dirs) . "\n";
    return ($this->_createRtnArray($dirs, $matches));
  }

  function _createRtnArray($array, $matches)
  {
    /*
    * if we have a match, the create return array, else return empty
    * array.
    */
    if ($matches > 0)
    {
      $numMenus = count($array[1]);
      $rtnList = array ();
      for ($i = 0; $i <= $numMenus -1; $i++)
      {
        $rtnList[$array[2][$i]] = $array[1][$i];
      }
      return ($rtnList);
    } else
    {
      return (array ());
    }
  }

  /**
   * function _createMiniArray
   *
   * combine two arrays into a single associative array.  One of the
   * arrays is already associative and had duplicate keys.
   *
   * ($array, $matches)
   */
  function _createMiniArray($array, $matches)
  {
    /*
    * if we have a match, then create return array, else return empty
    * array. file mini menus have duplicated keys (view,meta,download)
    * so they must be processed a different way.
    */
    //print "_CMiniA: matches is:$matches\n";
    if ($matches > 0)
    {
      $triple = array ();
      $numMenus = count($array[1]);
      $loopCnt = $numMenus / 3;
      $rtnList = array ();
      /* index is used to step through all the links*/
      $index = 0;
      for ($i = 0; $i <= $loopCnt -1; $i++)
      {
        $triple = array ();
        for ($j = 0; $j <= 2; $j++)
        {
          $triple[$array[2][$j]] = $array[1][$index];
          $index++;
        }
        $rtnList[$i] = $triple;
      }
      return ($rtnList);
    } else
    {
      return (array ());
    }

  }
}
?>
