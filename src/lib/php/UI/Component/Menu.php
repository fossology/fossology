<?php
/***********************************************************
 * Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.
 * Copyright (C) 2014 Siemens AG
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
  var $MenuTarget = "treenav";

  function PostInitialize()
  {
    foreach ($this->Dependency as $key => $val)
    {
      $id = plugin_find_id($val);
      if ($id < 0)
      {
        Destroy();
        return (0);
      }
    }

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
    /*** NOTE: http://www.cssplay.co.uk/menus/final_drop.html identifies
     * why menus fail for IE6. IE6 needs the </a> to exist outside the
     * submenus rather than before the submenus. ***/
    /*** Since his menus work under IE6 (and mine don't), I should
     * use one of his menus instead: http://www.cssplay.co.uk/menus/.
     * I'll make this change TBD...
     * This looks like a good one:
     * http://www.cssplay.co.uk/menus/simple_vertical.html
     ***/
    foreach ($menu as $menuEntry)
    {
      $output .= '<li>';

      if (!empty($menuEntry->HTML))
      {
        $output .= $menuEntry->HTML;
      } else /* create HTML */
      {
        if (!empty($menuEntry->URI))
        {
          $output .= '<a href="' . Traceback_uri() . "?mod=" . $menuEntry->URI;
          if (empty($menuEntry->Target) || ($menuEntry->Target == ""))
          {
            // $V .= '" target="basenav">';
            $output .= '">';
          } else
          {
            $output .= '" target="' . $menuEntry->Target . '">';
          }
          if (@$_SESSION['fullmenudebug'] == 1)
          {
            $output .= $menuEntry->FullName . "(" . $menuEntry->Order . ")";
          } else
          {
            $output .= $menuEntry->Name;
          }
        } else
        {
          $output .= '<a href="#">';
          if (empty($menuEntry->SubMenu))
          {
            $output .= "<font color='#C0C0C0'>";
            if (@$_SESSION['fullmenudebug'] == 1)
            {
              $output .= $menuEntry->FullName . "(" . $menuEntry->Order . ")";
            } //else { $V .= $M->Name; }
            else
            {
              $output .= '';
            }
            $output .= "</font>";
          } else
          {
            if (@$_SESSION['fullmenudebug'] == 1)
            {
              $output .= $menuEntry->FullName . "(" . $menuEntry->Order . ")";
            } else
            {
              $output .= $menuEntry->Name;
            }
          }
        }

        if (!empty($menuEntry->SubMenu) && ($indent > 0))
        {
          $output .= " <span>&raquo;</span>";
        }
        $output .= "</a>\n";
      }

      if (!empty($menuEntry->SubMenu))
      {
        $output .= $this->menu_html($menuEntry->SubMenu, $indent + 1);
      }
    }
    $output .= "</ul>\n";
    $output .= "<!--[if lt IE 7]></td></tr></table></a><![endif]-->\n";
    // Remove all empty menus of the form
    // <li><a href=\"#\"><font color='#C0C0C0'></font></a>
    $NewV = preg_replace("|<li><a href=\"#\"><font color(.*)*?$|m", '', $output);
    return ($NewV);
  } // menu_html()

  /**
   * \brief Create the output CSS.
   */
  function OutputCSS()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return (0);
    }
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
   * \brief Create the output.
   */
  function Output($Title = NULL)
  {
    global $SysConf;

    if ($this->State != PLUGIN_STATE_READY)
    {
      return (0);
    }
    $output = "";
    if (empty($Title))
    {
      $Title = _("Welcome to FOSSology");
    }

    /* Banner Message? */
    if (@$SysConf['SYSCONFIG']['BannerMsg'])
    {
      $output .= "<h4 style='background-color:#ffbbbb'>" . $SysConf['SYSCONFIG']['BannerMsg'] . "</h4>";
    }

    if (!$this->_CSSdone)
    {
      $output .= $this->OutputCSS();
    }
    $menu = menu_find("Main", $MenuDepth);

    /** Same height at FOSSology logo **/
    $output .= "<table border=0 width='100%'>";
    $output .= "<tr>";
    /* custom or default logo? */
    if (@$SysConf['SYSCONFIG']['LogoImage'] and @$SysConf['SYSCONFIG']['LogoLink'])
    {
      $logoUrl = $SysConf['SYSCONFIG']['LogoLink'];
      $logoImage = $SysConf['SYSCONFIG']['LogoImage'];
    } else
    {
      $logoUrl = 'http://fossology.org';
      //$LogoImg = Traceback_uri() ."images/fossology-logo.gif";
      $logoImage = "images/fossology-logo.gif";
    }

    $output .= "<td width='150' rowspan='2'><a href='$logoUrl' target='_top' style='background:white;'><img alt='FOSSology' title='FOSSology' src='" . "$logoImage' border=0></a></td>";

    $output .= "<td colspan='2'>";
    $output .= $this->menu_html($menu, 0);
    $output .= "</td>";
    $output .= "</tr><tr>";
    $output .= "<td>";
    $output .= "<font size='+2'><b>$Title</b></font>";
    $output .= "</td>";

    $output .= "<td align='right' valign='bottom'>";
    /* Handle login information */
    if (plugin_find_id("auth") >= 0 || plugin_find_id("smauth") >= 0)
    {
      if ((empty($_SESSION['User'])) or ($_SESSION['User'] == "Default User"))
      {
        $text = _("login");
        $output .= "<small><a href='" . Traceback_uri() . "?mod=auth'><b>$text</b></a></small>";
      } else
      {
        $text = _("User");
        $output .= "<small>$text:</small> " . @$_SESSION['User'] . "<br>";
        if (plugin_find_id("auth") >= 0)
          $output .= "<small><a href='" . Traceback_uri() . "?mod=auth'><b>logout</b></a></small>";
        else
          $output .= "<small><a href='" . Traceback_uri() . "?mod=smauth'><b>logout</b></a></small>";
      }

      /* Use global system SupportEmail variables, if addr and label are set */
      if (@$SysConf['SYSCONFIG']['SupportEmailLabel'] and @$SysConf['SYSCONFIG']['SupportEmailAddr'])
      {
        $output .= " | ";
        $output .= "<small><a href='mailto:" . $SysConf['SYSCONFIG']['SupportEmailAddr'] . "?subject=" . $SysConf['SYSCONFIG']['SupportEmailSubject'] . "'>" . $SysConf['SYSCONFIG']['SupportEmailLabel'] . "</a>";
      }
    }
    $output .= "</td>";
    $output .= "</tr>";
    $output .= "</table>";
    $output .= "<hr />";

    return $output;
  }

}

