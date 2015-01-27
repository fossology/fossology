<?php
/***********************************************************
 * Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.
 * Copyright (C) 2014-2015 Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/

namespace Fossology\Lib\UI\Component;


use Fossology\Lib\Util\Object;

class Menu extends Object
{
  const FULL_MENU_DEBUG = 'fullmenudebug';
  var $MenuTarget = "treenav";

  public function __construct()
  {
    // Add default menus (with no actions linked to plugins)
    menu_insert("Main::Upload", 70);
    menu_insert("Main::Jobs", 60);
    menu_insert("Main::Organize", 50);
    menu_insert("Main::Help", -1);
    menu_insert("Main::Help::Documentation", 0, NULL, NULL, NULL, "<a href='http://www.fossology.org/projects/fossology/wiki/User_Documentation'>Documentation</a>");
  }

  /**
   * \brief Recursively generate the menu in HTML.
   */
  function menu_html(&$menu, $indent)
  {
    if (empty($menu))
    {
      return;
    }
    $output = "";
    $output .= "<!--[if lt IE 7]><table><tr><td><![endif]-->\n";
    $output .= "<ul id='menu-$indent'>\n";
    
    foreach ($menu as $M)
    {
      $output .= '<li>';

      if (!empty($M->HTML))
      {
        $output .= $M->HTML;
      } else /* create HTML */
      {
        $output .= $this->createHtmlFromMenuEntry($M, $indent);
      }

      if (!empty($M->SubMenu))
      {
        $output .= $this->menu_html($M->SubMenu, $indent + 1);
      }
    }
    $output .= "</ul>\n";
    $output .= "<!--[if lt IE 7]></td></tr></table></a><![endif]-->\n";
    $NewV = preg_replace("|<li><a href=\"#\"><font color(.*)*?$|m", '', $output);
    return ($NewV);
  }
  
  function createHtmlFromMenuEntry(\menu $M, $Indent)
  {
    $isFullMenuDebug = array_key_exists(self::FULL_MENU_DEBUG, $_SESSION) && $_SESSION[self::FULL_MENU_DEBUG] == 1;
    $V = "";
    if (!empty($M->URI))
    {
      $V .= '<a  id="'. htmlentities($M->FullName) .'" href="' . Traceback_uri() . "?mod=" . $M->URI;
      if (empty($M->Target) || ($M->Target == ""))
      {
        // $V .= '" target="basenav">';
        $V .= '">';
      } else
      {
        $V .= '" target="' . $M->Target . '">';
      }
      if ($isFullMenuDebug)
      {
        $V .= $M->FullName . "(" . $M->Order . ")";
      } else
      {
        $V .= $M->Name;
      }
    } else
    {
      $V .= '<a id="'. htmlentities($M->FullName) .'" href="#">';
      if (empty($M->SubMenu))
      {
        $V .= "<font color='#C0C0C0'>";
        if ($isFullMenuDebug)
        {
          $V .= $M->FullName . "(" . $M->Order . ")";
        } //else { $V .= $M->Name; }
        else
        {
          $V .= '';
        }
        $V .= "</font>";
      } else
      {
        if ($isFullMenuDebug)
        {
          $V .= $M->FullName . "(" . $M->Order . ")";
        } else
        {
          $V .= $M->Name;
        }
      }
    }

    if (!empty($M->SubMenu) && ($Indent > 0))
    {
      $V .= " <span>&raquo;</span>";
    }
    $V .= "</a>\n";
    return $V;
  }

  /**
   * \brief Create the output CSS.
   */
  function OutputCSS()
  {
    $output = "";

    $output .= "<style type=\"text/css\">\n";
    /* Depth 0 is special: position is relative, colors are blue */
    $Depth = 0;
    $Label = "";
    $Menu = menu_find("Main", $MenuDepth);
    $Border = "border-color:#CCC #CCC #CCC #CCC; border-width:1px 1px 1px 1px;";
    $Padding = "padding:4px 0px 4px 4px;";
    $FOSScolor1 = "#c50830";
    $FOSScolor2 = "#808080";
    $FOSSbg = "white";

    $FOSSfg1 = "black";
    $FOSSbg1 = "white";
    $FOSSfg1h = $FOSScolor1; // highlight colors
    $FOSSbg1h = "beige";

    $FOSSfg2 = "black";
    $FOSSbg2 = "beige";
    $FOSSfg2h = $FOSScolor1; // highlight colors
    $FOSSbg2h = "beige";

    $FOSSfg3 = "black";
    $FOSSbg3 = "beige";
    $FOSSfg3h = $FOSScolor1; // highlight colors
    $FOSSbg3h = "beige";

    if ($Depth < $MenuDepth)
    {
      /** The "float:left" is needed to fix IE **/
      $output .= "\n/* CSS for Depth $Depth */\n";
      $Label = "ul#menu-" . $Depth;
      $output .= $Label . "\n";
      $output .= "  { z-index:0; margin:0; padding:0px; list-style:none; background:$FOSSbg1; width:100%; height:24px; font:normal 10pt verdana, arial, helvetica; font-weight: bold; }\n";
      $Label .= " li";
      $output .= $Label . "\n";
      $output .= "  { float:left; margin:0; padding:0px; display:block; position:relative; width:auto; border:0px solid #000; }\n";
      $output .= $Label . " a:link,\n";
      $output .= $Label . " a:visited\n";
      $output .= "  { float:left; padding:4px 10px; text-decoration:none; color:$FOSSfg1; background:$FOSSbg1; width:auto; display:block; }\n";
      $output .= $Label . ":hover a,\n";
      $output .= $Label . " a:hover,\n";
      $output .= $Label . " a:active\n";
      $output .= "  { float:left; padding:4px 10px; color:$FOSSfg1h; background:$FOSSbg1h; $Border width:auto; display:block; }\n";
      $output .= $Label . " a span\n";
      $output .= "  { float:left; position:absolute; top:0; left:135px; font-size:12pt; }\n";
      $Depth++;
    }

    /* Depth 1 is special: position is absolute. Left is 0, top is 24 */
    if ($Depth < $MenuDepth)
    {
      $output .= "\n/* CSS for Depth $Depth */\n";
      $output .= $Label . " ul#menu-" . $Depth . "\n";
      $output .= "  { margin:0; padding:0px 0; list-style:none; display:none; visibility:hidden; left:0px; width:150px; position:absolute; top:24px; font-weight: bold; }\n";
      $output .= $Label . ":hover ul#menu-" . $Depth . "\n";
      $output .= "  { float:left; display:block; visibility:visible; }\n";
      $Label .= " ul#menu-" . $Depth . " li";
      $output .= $Label . "\n";
      $output .= "  { z-index:$Depth; margin:0; padding:0; display:block; visibility:visible; position:relative; width:150px; }\n";
      $output .= $Label . " a:link,\n";
      $output .= $Label . " a:visited\n";
      $output .= "  { z-index:$Depth; $Padding color:$FOSSfg2; background:$FOSSbg2; border:1px solid #000; $Border width:150px; display:block; visibility:visible; }\n";
      $output .= $Label . ":hover a,\n";
      $output .= $Label . " a:active,\n";
      $output .= $Label . " a:hover\n";
      $output .= "  { z-index:$Depth; $Padding color:$FOSSfg2h; background:$FOSSbg2h; width:150px; display:block; visibility:visible; }\n";
      $output .= $Label . " a span\n";
      $output .= "  { text-align:left; }\n";
      $Depth++;
    }

    /* Depth 2+ is recursive: position is absolute. Left is 150*(Depth-1), top is 0 */
    for (; $Depth < $MenuDepth; $Depth++)
    {
      $output .= "\n/* CSS for Depth $Depth */\n";
      $output .= $Label . " ul#menu-" . $Depth . "\n";
      $output .= "  { margin:0; padding:1px 0; list-style:none; display:none; visibility:hidden; left:156px; width:150px; position:absolute; top:-1px; font-weight: bold; }\n";
      $output .= $Label . ":hover ul#menu-" . $Depth . "\n";
      $output .= "  { float:left; display:block; visibility:visible; }\n";
      $Label .= " ul#menu-" . $Depth . " li";
      $output .= $Label . "\n";
      $output .= "  { z-index:$Depth; margin:0; padding:0; display:block; visibility:visible; position:relative; width:150px; }\n";
      $output .= $Label . " a:link,\n";
      $output .= $Label . " a:visited\n";
      $output .= "  { z-index:$Depth; $Padding color:$FOSSfg3; background:$FOSSbg2h; border:1px solid #000; $Border width:150px; display:block; }\n";
      $output .= $Label . ":hover a,\n";
      $output .= $Label . " a:active,\n";
      $output .= $Label . " a:hover\n";
      $output .= "  { z-index:$Depth; $Padding color:$FOSSfg3h; background:$FOSSbg3h; width:150px; display:block; visibility:visible; }\n";
      $output .= $Label . " a span\n";
      $output .= "  { text-align:left; }\n";
    }
    $output .= "</style>\n";

    /* For IE's screwed up CSS: this defines "hover". */
    $output .= "<!--[if lt IE 8]>\n";
    $output .= "<style type='text/css' media='screen'>\n";
    /** csshover.htc provides ":hover" support for IE **/
    $output .= "body { behavior:url(csshover.htc); }\n";
    /** table definition needed to get rid of extra space under items **/
    for ($i = 1; $i < $MenuDepth; $i++)
    {
      $output .= "#menu-$i table {height:0px; border-collapse:collapse; margin:0; padding:0; }\n";
      $output .= "#menu-$i td {height:0px; border:none; margin:0; padding:0; }\n";
    }
    $output .= "</style>\n";
    $output .= "<![endif]-->\n";

    $this->_CSSdone = 1;
    return ($output);
  } // OutputCSS()

  /**
   * @brief Create the output.
   */
  function Output($Title = NULL)
  {
    global $SysConf;
    $sysConfig = $SysConf['SYSCONFIG'];

    $vars = array();
    $vars['title'] = empty($Title) ? _("Welcome to FOSSology") : $Title;
    $vars['bannerMsg'] = @$sysConfig['BannerMsg'];
    $vars['logoLink'] =  $sysConfig['LogoLink']?: 'http://fossology.org';
    $vars['logoImg'] =  $sysConfig['LogoImage']?: 'images/fossology-logo.gif';

    if ( array_key_exists('SupportEmailLabel',$sysConfig) && !empty($sysConfig['SupportEmailLabel'])
            && array_key_exists('SupportEmailAddr',$sysConfig) && !empty($sysConfig['SupportEmailAddr'])){
      $menuItem = '<a href="mailto:'.$sysConfig['SupportEmailAddr'].'?subject='.@$sysConfig['SupportEmailSubject'].'">'.$sysConfig['SupportEmailLabel'].'</a>';
      menu_insert("Main::Help::".$sysConfig['SupportEmailLabel'], 0, NULL, NULL, NULL, $menuItem);
    }    
    
    $Menu = menu_find("Main", $MenuDepth);
    $vars['mainMenu'] = $this->menu_html($Menu, 0);
    $vars['uri'] = Traceback_uri();
    
    /* Handle login information */
    $vars['isLoggedOut'] = ((empty($_SESSION['User'])) or ($_SESSION['User'] == "Default User"));
    
    if(!$vars['isLoggedOut'])
    {
      $this->mergeUserLoginVars($vars);
    }
    
    global $container;
    $renderer = $container->get('twig.environment');
    $out = $renderer->loadTemplate('menu.html.twig')->render($vars);
    return $out;
  }

  private function mergeUserLoginVars(&$vars)
  {
    global $container;
    $dbManager = $container->get("db.manager");

    $vars['logOutUrl'] = Traceback_uri() . '?mod=' . ((plugin_find_id('auth')>=0) ? 'auth' : 'smauth');
    $vars['userName'] = $_SESSION['User'];
    
    $sql = 'SELECT group_pk, group_name FROM group_user_member LEFT JOIN groups ON group_fk=group_pk WHERE user_fk=$1';
    $stmt = __METHOD__ . '.availableGroups';
    $dbManager->prepare($stmt, $sql);
    $res = $dbManager->execute($stmt, array($_SESSION['UserId']));
    $allAssignedGroups = array();
    while ($row = $dbManager->fetchArray($res))
    {
      $allAssignedGroups[$row['group_pk']] = $row['group_name'];
    }
    $dbManager->freeResult($res);
    if (count($allAssignedGroups) > 1)
    {
      $vars['backtraceUri'] = Traceback_uri() . "?mod=" . Traceback_parm();
      $vars['groupId'] = $_SESSION['GroupId'];
      $vars['allAssignedGroups'] = $allAssignedGroups;
    }
    else
    {
      $vars['singleGroup'] = @$_SESSION['GroupName'];
    }
  }
}

