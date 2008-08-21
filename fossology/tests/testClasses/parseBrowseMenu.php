
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
 * @version "$Id: $"
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
    print "files is:";
    print_r($files) . "\n";
    print "files[1] is:";
    print_r($files[1]) . "\n";
    if ($numMenus = count($files[1]))
    {
      return ($files[1]);
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
    print "fileMini Menus are:";
    print_r($fileMini) . "\n";
    return (_createRtnArray($fileMini, $matches));
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
    print "dirs is:";
    print_r($dirs) . "\n";
    return (_createRtnArray($dirs, $matches));
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
      $menus = array ();
      for ($i = 0; $i <= $numMenus -1; $i++)
      {
        $rtnList[$parsed[2][$i]] = $parsed[1][$i];
      }
      print "menus after construct:\n";
      print_r($rtnList) . "\n";
      return ($rtnList);
    } else
    {
      return (array ());
    }

  }
}
?>
