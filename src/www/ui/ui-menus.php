<?php

use Fossology\Lib\Dao\UserDao;
/***********************************************************
 * Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.
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

define("TITLE_ui_menu", _("Menus"));

class ui_menu extends FO_Plugin
{
  const NAME = "menus";
  const FULL_MENU_DEBUG = 'fullmenudebug';

  var $LoginFlag = 0;
  var $_CSSdone = 0; /* has the CSS been displayed? */

  function __construct()
  {
    $this->Name = self::NAME;
    $this->Title = TITLE_ui_menu;
    $this->Version = "1.0";
    $this->MenuTarget = "treenav";
    parent::__construct();
  }
  
  function PostInitialize()
  {
    global $Plugins;
    if ($this->State != PLUGIN_STATE_VALID)
    {
      return (0);
    } // don't run
    // Make sure dependencies are met
    foreach ($this->Dependency as $val)
    {
      $id = plugin_find_id($val);
      if ($id < 0)
      {
        $this->Destroy();
        return (0);
      }
    }

    // Add default menus (with no actions linked to plugins)
    menu_insert("Main::Upload", 70);
    menu_insert("Main::Jobs", 60);
    menu_insert("Main::Organize", 50);
    menu_insert("Main::Help", -1);
    menu_insert("Main::Help::Documentation", 0, NULL, NULL, NULL, "<a href='http://www.fossology.org/projects/fossology/wiki/User_Documentation'>Documentation</a>");

    // It worked, so mark this plugin as ready.
    $this->State = PLUGIN_STATE_READY;
    return ($this->State == PLUGIN_STATE_READY);
  }

  /**
   * \brief Recursively generate the menu in HTML.
   */
  function menu_html(&$Menu, $Indent)
  {
    if (empty($Menu))
    {
      return;
    }
    $V = "";
    $V .= "<!--[if lt IE 7]><table><tr><td><![endif]-->\n";
    $V .= "<ul id='menu-$Indent'>\n";

    foreach ($Menu as $M)
    {
      $V .= '<li>';

      if (!empty($M->HTML))
      {
        $V .= $M->HTML;
      } else /* create HTML */
      {
        $V .= $this->createHtmlFromMenuEntry($M, $Indent);
      }

      if (!empty($M->SubMenu))
      {
        $V .= $this->menu_html($M->SubMenu, $Indent + 1);
      }
    }
    $V .= "</ul>\n";
    $V .= "<!--[if lt IE 7]></td></tr></table></a><![endif]-->\n";
    $NewV = preg_replace("|<li><a href=\"#\"><font color(.*)*?$|m", '', $V);
    return ($NewV);
  }


  function createHtmlFromMenuEntry(menu $M, $Indent)
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
    if ($this->State != PLUGIN_STATE_READY)
    {
      return (0);
    }
    $V = "";

    $V .= "<style type=\"text/css\">\n";
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
      $V .= "\n/* CSS for Depth $Depth */\n";
      $Label = "ul#menu-" . $Depth;
      $V .= $Label . "\n";
      $V .= "  { z-index:0; margin:0; padding:0px; list-style:none; background:$FOSSbg1; width:100%; height:24px; font:normal 10pt verdana, arial, helvetica; font-weight: bold; }\n";
      $Label .= " li";
      $V .= $Label . "\n";
      $V .= "  { float:left; margin:0; padding:0px; display:block; position:relative; width:auto; border:0px solid #000; }\n";
      $V .= $Label . " a:link,\n";
      $V .= $Label . " a:visited\n";
      $V .= "  { float:left; padding:4px 10px; text-decoration:none; color:$FOSSfg1; background:$FOSSbg1; width:auto; display:block; }\n";
      $V .= $Label . ":hover a,\n";
      $V .= $Label . " a:hover,\n";
      $V .= $Label . " a:active\n";
      $V .= "  { float:left; padding:4px 10px; color:$FOSSfg1h; background:$FOSSbg1h; $Border width:auto; display:block; }\n";
      $V .= $Label . " a span\n";
      $V .= "  { float:left; position:absolute; top:0; left:135px; font-size:12pt; }\n";
      $Depth++;
    }

    /* Depth 1 is special: position is absolute. Left is 0, top is 24 */
    if ($Depth < $MenuDepth)
    {
      $V .= "\n/* CSS for Depth $Depth */\n";
      $V .= $Label . " ul#menu-" . $Depth . "\n";
      $V .= "  { margin:0; padding:0px 0; list-style:none; display:none; visibility:hidden; left:0px; width:150px; position:absolute; top:24px; font-weight: bold; }\n";
      $V .= $Label . ":hover ul#menu-" . $Depth . "\n";
      $V .= "  { float:left; display:block; visibility:visible; }\n";
      $Label .= " ul#menu-" . $Depth . " li";
      $V .= $Label . "\n";
      $V .= "  { z-index:$Depth; margin:0; padding:0; display:block; visibility:visible; position:relative; width:150px; }\n";
      $V .= $Label . " a:link,\n";
      $V .= $Label . " a:visited\n";
      $V .= "  { z-index:$Depth; $Padding color:$FOSSfg2; background:$FOSSbg2; border:1px solid #000; $Border width:150px; display:block; visibility:visible; }\n";
      $V .= $Label . ":hover a,\n";
      $V .= $Label . " a:active,\n";
      $V .= $Label . " a:hover\n";
      $V .= "  { z-index:$Depth; $Padding color:$FOSSfg2h; background:$FOSSbg2h; width:150px; display:block; visibility:visible; }\n";
      $V .= $Label . " a span\n";
      $V .= "  { text-align:left; }\n";
      $Depth++;
    }

    /* Depth 2+ is recursive: position is absolute. Left is 150*(Depth-1), top is 0 */
    for (; $Depth < $MenuDepth; $Depth++)
    {
      $V .= "\n/* CSS for Depth $Depth */\n";
      $V .= $Label . " ul#menu-" . $Depth . "\n";
      $V .= "  { margin:0; padding:1px 0; list-style:none; display:none; visibility:hidden; left:156px; width:150px; position:absolute; top:-1px; font-weight: bold; }\n";
      $V .= $Label . ":hover ul#menu-" . $Depth . "\n";
      $V .= "  { float:left; display:block; visibility:visible; }\n";
      $Label .= " ul#menu-" . $Depth . " li";
      $V .= $Label . "\n";
      $V .= "  { z-index:$Depth; margin:0; padding:0; display:block; visibility:visible; position:relative; width:150px; }\n";
      $V .= $Label . " a:link,\n";
      $V .= $Label . " a:visited\n";
      $V .= "  { z-index:$Depth; $Padding color:$FOSSfg3; background:$FOSSbg2h; border:1px solid #000; $Border width:150px; display:block; }\n";
      $V .= $Label . ":hover a,\n";
      $V .= $Label . " a:active,\n";
      $V .= $Label . " a:hover\n";
      $V .= "  { z-index:$Depth; $Padding color:$FOSSfg3h; background:$FOSSbg3h; width:150px; display:block; visibility:visible; }\n";
      $V .= $Label . " a span\n";
      $V .= "  { text-align:left; }\n";
    }
    $V .= "</style>\n";

    /* For IE's screwed up CSS: this defines "hover". */
    $V .= "<!--[if lt IE 8]>\n";
    $V .= "<style type='text/css' media='screen'>\n";
    /** csshover.htc provides ":hover" support for IE **/
    $V .= "body { behavior:url(csshover.htc); }\n";
    /** table definition needed to get rid of extra space under items **/
    for ($i = 1; $i < $MenuDepth; $i++)
    {
      $V .= "#menu-$i table {height:0px; border-collapse:collapse; margin:0; padding:0; }\n";
      $V .= "#menu-$i td {height:0px; border:none; margin:0; padding:0; }\n";
    }
    $V .= "</style>\n";
    $V .= "<![endif]-->\n";

    $this->_CSSdone = 1;
    return ($V);
  } // OutputCSS()

  /**
   * \brief Create the output.
   */
  function Output($Title = NULL)
  {
    global $SysConf;
    $sysConfig = $SysConf['SYSCONFIG'];
    if ($this->State != PLUGIN_STATE_READY)
    {
      return (0);
    }
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
    
    $out = $this->renderTemplate('menu.html.twig', $vars);
    return $out;
  }

  private function mergeUserLoginVars(&$vars)
  {
    global $container;
    /** @var UserDao */
    $userDao = $container->get("dao.user");
    $allAssignedGroups = $userDao->getUserGroupMap($_SESSION['UserId']);
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
    $vars['logOutUrl'] = Traceback_uri() . '?mod=' . ((plugin_find_id('auth')>=0) ? 'auth' : 'smauth');
    $vars['userName'] = $_SESSION['User'];
  }
}

$NewPlugin = new ui_menu();
$NewPlugin->Initialize();

