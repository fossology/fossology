<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

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
 * @version "$Id: parseFolderPath.php 2866 2010-03-10 19:32:44Z rrando $" Created on Aug 21, 2008
 */

class parseFolderPath
{
  public $page;
  public $host;
  public $filesWithLicense;
  private $test;

  function __construct($page, $url)
  {
    /* to do: check for http?  if not return null...)? */
    if (empty ($page))
    {
      return;
    }
    $this->page = $page;
    if (empty ($url))
    {
      return;
    }
    $this->host = getHost($url);
  }

  /**
   * function countFiles()
   *
   * Parse  the part of the page that has the folder path and mini menu,
   * return the count of 'Folder' items found.
   *
   * @return int the count of items found, can be 0.
   *
   */
   function countFiles()
   {
    /* Extract the folder path line from the page */
    $regExp = "Folder<\/b>:.*?/font>";
    $numberMatched = preg_match_all("|$regExp|s", $this->page, $pathLines, PREG_SET_ORDER);
    $this->filesWithLicense = $pathLines;
    //print "PFP:countFiles:matched is:$numberMatched\nFilesWithLicense:\n";
    //print_r($this->filesWithLicense) . "\n";
    
    return($numberMatched);
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
  function parseFolderPath() {

    $paths = array();

    /* Gather up the line(s) with Folder*/
    $this->countFiles();
    foreach ($this->filesWithLicense as $aptr)
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
      if ($matches > 0)
      {
        $dirList[] = $this->_createRtnArray($pathList, $matches);
        return ($dirList);
      } else
      {
        return (array ());
      }

    }
  } // parseFolderPath
  
  /**
   * clean up the links to be usable
   *
   * @param array $list, the list to clean up
   * @param int $matches, the size of the list
   *
   * @return array, the cleaned up list_bucket_files
   *
   * @todo fix the docs above to much more detailed.
   */
  function _createRtnArray($list, $matches)
  {
    global $host;

    /*
     * The last entry in the array is always a leaf name with no link
     * but it has to be cleaned up a bit....
     */
    
    for ($i = 0; $i < $matches; $i++)
    {
      $cleanKey = trim($list[$i][2], "\/<>b");
      if (empty ($cleanKey))
      {
        continue;
      }
      // Make a real link that can be used
      $partLink = $list[$i][1];
      $link = makeUrl($this->host, $partLink);
      $rtnList[$cleanKey] = $link;
      /* check for anything in the leaf entry, if there is, remove
       * the preceeding /
       */
      if (!empty ($list[$i][3]))
      {
        $cleanKey = trim($list[$i][3], "\/ ");
        if (empty ($cleanKey))
        {
          continue;
        }
        $rtnList[$cleanKey] = NULL;
      }
    }
    return ($rtnList);
  } // _createRtnArray

  public function setPage($page)
  {
    if (!empty ($page))
    {
      $this->page = $page;
    }
  }
}
?>
