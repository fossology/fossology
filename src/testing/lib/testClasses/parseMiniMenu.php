<?php
/*
 SPDX-FileCopyrightText: © 2008 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * Given a fossology page, if there is a mini menu on it, parse it and
 * return it.
 *
 * @param string $page the xhtml page to parse
 *
 * @return assocative array with menu names as keys and links as values.
 * Can return an empty array indicating no mini menus found on the page.
 * Only menus with links are returned.
 *
 * @todo add in link fixups and adjust consumers
 *
 * @version "$Id: parseMiniMenu.php 3012 2010-04-08 03:44:57Z rrando $"
 *
 * Created on Aug 19, 2008
 */

//require_once ('../commonTestFuncs.php');

class parseMiniMenu
{
  public $page;

  function __construct($page)
  {
    if (empty ($page))
    {
      return (array ());
    }
    $this->page = $page;
  }
  function parseMiniMenu()
  {
    /* take the front part of the string off, this should leave only menus */
    $matches = preg_match("/.*?id='menu1html-..*?<small>(.*)/", $this->page, $menuList);
    /*
     * parse the menus.  The first menu in the list is ignored if it
     * doesn't have a link associated with it.
     */
    $matches = preg_match_all("/<a href='((.*?).*?)'.*?>(.*?)</", $menuList[1], $parsed, PREG_PATTERN_ORDER);
    //print "PMINIDB: parsed is:"; print_r($parsed) . "\n";
    //print "PMINIDB: matches is:$matches\n";
    /*
     * if we have a match, the create return array, else return empty
     * array.
     */
    if ($matches > 0)
    {
      $numMenus = count($parsed[1]);
      $menus = array ();
      for ($i = 0; $i <= $numMenus -1; $i++)
      {
        $menus[$parsed[3][$i]] = $parsed[1][$i];
      }
      //print "PMINIDB: menus after construct:\n"; print_r($menus) . "\n";
      return ($menus);
    } else
    {
      return (array ());
    }
  }
}
