<?php
/*
 SPDX-FileCopyrightText: © 2008-2011 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\UI\Component;

use Fossology\Lib\Auth\Auth;
use Twig\Environment;

class Menu
{
  const FULL_MENU_DEBUG = 'fullmenudebug';
  /**
   * @var string
   * Name of cookie to handle banner close state.
   */
  const BANNER_COOKIE = 'close_banner';
  var $MenuTarget = "treenav";
  protected $renderer;

  public function __construct(Environment $renderer)
  {
    // Add default menus (with no actions linked to plugins)
    menu_insert("Main::Upload", 70);
    menu_insert("Main::Jobs", 60);
    menu_insert("Main::Organize", 50);
    menu_insert("Main::Help", -1);
    menu_insert("Main::Help::Documentation", 0, NULL, NULL, NULL, "<a href='https://github.com/fossology/fossology/wiki'>Documentation</a>");
    $this->renderer = $renderer;
  }

  /**
   * \brief Recursively generate the menu in HTML.
   */
  function menu_html(&$menu, $indent)
  {
    if (empty($menu)) {
      return;
    }
    $output = "<!--[if lt IE 7]><table><tr><td><![endif]-->\n";
    $output .= "<ul id='menu-$indent'>\n";

    foreach ($menu as $menuEntry) {
      $output .= '<li>';

      if (!empty($menuEntry->HTML)) {
        $output .= $menuEntry->HTML;
      } else { /* create HTML */
        $output .= $this->createHtmlFromMenuEntry($menuEntry, $indent);
      }

      if (!empty($menuEntry->SubMenu)) {
        $output .= $this->menu_html($menuEntry->SubMenu, $indent + 1);
      }
    }
    $output .= "</ul>\n";
    $output .= "<!--[if lt IE 7]></td></tr></table></a><![endif]-->\n";
    return preg_replace("|<li><a href=\"#\"><font color(.*)*?$|m", '', $output);
  }

  function createHtmlFromMenuEntry(\menu $menuEntry, $indent)
  {
    $isFullMenuDebug = array_key_exists(self::FULL_MENU_DEBUG, $_SESSION) && $_SESSION[self::FULL_MENU_DEBUG] == 1;
    $output = "";
    if (!empty($menuEntry->URI)) {
      $output .= '<a  id="'. htmlentities($menuEntry->FullName) .'" href="' . Traceback_uri() . "?mod=" . $menuEntry->URI;
      if (empty($menuEntry->Target) || ($menuEntry->Target == "")) {
        $output .= '">';
      } else {
        $output .= '" target="' . $menuEntry->Target . '">';
      }
      if ($isFullMenuDebug) {
        $output .= $menuEntry->FullName . "(" . $menuEntry->Order . ")";
      } else {
        $output .= $menuEntry->Name;
      }
    } else {
      $output .= '<a id="'. htmlentities($menuEntry->FullName) .'" href="#">';
      if (empty($menuEntry->SubMenu)) {
        $output .= "<font color='#C0C0C0'>";
        if ($isFullMenuDebug) {
          $output .= $menuEntry->FullName . "(" . $menuEntry->Order . ")";
        } else {
          $output .= '';
        }
        $output .= "</font>";
      } else {
        if ($isFullMenuDebug) {
          $output .= $menuEntry->FullName . "(" . $menuEntry->Order . ")";
        } else {
          $output .= $menuEntry->Name;
        }
      }
    }

    if (!empty($menuEntry->SubMenu) && ($indent > 0)) {
      $output .= " <span>&raquo;</span>";
    }
    $output .= "</a>\n";
    return $output;
  }

  /**
   * \brief Create the output CSS.
   */
  function OutputCSS()
  {

    $output = "<style type=\"text/css\">\n";
    /* Depth 0 is special: position is relative, colors are blue */
    $depth = 0;
    $label = "";
    $Menu = menu_find("Main", $MenuDepth);
    $cssBorder = "border-color:#bee5eb #bee5eb #bee5eb #bee5eb; border-width:1px 1px 1px 1px; border-radius:3px;";
    $cssPadding = "padding:4px 0px 4px 4px;";
    $FOSScolor1 = "#c50830";
    $FOSSbg = "white";

    $FOSSfg1 = "black";
    $FOSSbg1 = "white";
    $FOSSfg1h = $FOSScolor1; // highlight colors
    $FOSSbg1h = "#d1ecf1";

    $FOSSfg2 = "#0c5460";
    $FOSSbg2 = "#d1ecf1";
    $FOSSfg2h = $FOSScolor1; // highlight colors
    $FOSSbg2h = "#d1ecf1";

    $FOSSfg3 = "#0c5460";
    $FOSSbg3 = "#d1ecf1";
    $FOSSfg3h = $FOSScolor1; // highlight colors
    $FOSSbg3h = "#d1ecf1";

    if ($depth < $MenuDepth) {
      /** The "float:left" is needed to fix IE **/
      $output .= "\n/* CSS for Depth $depth */\n";
      $label = "ul#menu-" . $depth;
      $output .= $label . "\n";
      $output .= "  { z-index:0; margin:0; padding:0px; list-style:none; background:$FOSSbg1; width:100%; height:24px; font:normal 14px verdana, arial, helvetica; font-weight: bold; }\n";
      $label .= " li";
      $output .= $label . "\n";
      $output .= "  { float:left; margin:0; padding:0px; display:block; position:relative; width:auto; border:0px solid #000; }\n";
      $output .= $label . " a:link,\n";
      $output .= $label . " a:visited\n";
      $output .= "  { float:left; padding:4px 10px; text-decoration:none; color:$FOSSfg1; background:$FOSSbg1; width:auto; display:block; }\n";
      $output .= $label . ":hover a,\n";
      $output .= $label . " a:hover,\n";
      $output .= $label . " a:active\n";
      $output .= "  { float:left; padding:4px 10px; color:$FOSSfg1h; background:$FOSSbg1h; $cssBorder width:auto; display:block; }\n";
      $output .= $label . " a span\n";
      $output .= "  { float:left; position:absolute; top:0; left:135px; font-size:12pt; }\n";
      $depth++;
    }

    /* Depth 1 is special: position is absolute. Left is 0, top is 24 */
    if ($depth < $MenuDepth) {
      $output .= "\n/* CSS for Depth $depth */\n";
      $output .= $label . " ul#menu-" . $depth . "\n";
      $output .= "  { margin:0; padding:0px 0; list-style:none; display:none; visibility:hidden; left:0px; width:150px; position:absolute; top:24px; font-weight: bold; }\n";
      $output .= $label . ":hover ul#menu-" . $depth . "\n";
      $output .= "  { float:left; display:block; visibility:visible; }\n";
      $label .= " ul#menu-" . $depth . " li";
      $output .= $label . "\n";
      $output .= "  { z-index:$depth; margin:0; padding:0; display:block; visibility:visible; position:relative; width:150px; }\n";
      $output .= $label . " a:link,\n";
      $output .= $label . " a:visited\n";
      $output .= "  { z-index:$depth; $cssPadding color:$FOSSfg2; background:$FOSSbg2; border:1px solid #000; $cssBorder width:150px; display:block; visibility:visible; }\n";
      $output .= $label . ":hover a,\n";
      $output .= $label . " a:active,\n";
      $output .= $label . " a:hover\n";
      $output .= "  { z-index:$depth; $cssPadding color:$FOSSfg2h; background:$FOSSbg2h; width:150px; display:block; visibility:visible; }\n";
      $output .= $label . " a span\n";
      $output .= "  { text-align:left; }\n";
      $depth++;
    }

    /* Depth 2+ is recursive: position is absolute. Left is 150*(Depth-1), top is 0 */
    for (; $depth < $MenuDepth; $depth++) {
      $output .= "\n/* CSS for Depth $depth */\n";
      $output .= $label . " ul#menu-" . $depth . "\n";
      $output .= "  { margin:0; padding:1px 0; list-style:none; display:none; visibility:hidden; left:156px; width:150px; position:absolute; top:-1px; font-weight: bold; }\n";
      $output .= $label . ":hover ul#menu-" . $depth . "\n";
      $output .= "  { float:left; display:block; visibility:visible; }\n";
      $label .= " ul#menu-" . $depth . " li";
      $output .= $label . "\n";
      $output .= "  { z-index:$depth; margin:0; padding:0; display:block; visibility:visible; position:relative; width:150px; margin-left:-6px; }\n";
      $output .= $label . " a:link,\n";
      $output .= $label . " a:visited\n";
      $output .= "  { z-index:$depth; $cssPadding color:$FOSSfg3; background:$FOSSbg2h; border:1px solid #000; $cssBorder width:150px; display:block; }\n";
      $output .= $label . ":hover a,\n";
      $output .= $label . " a:active,\n";
      $output .= $label . " a:hover\n";
      $output .= "  { z-index:$depth; $cssPadding color:$FOSSfg3h; background:$FOSSbg3h; width:150px; display:block; visibility:visible; }\n";
      $output .= $label . " a span\n";
      $output .= "  { text-align:left; }\n";
    }
    $output .= "</style>\n";

    /* For IE's screwed up CSS: this defines "hover". */
    $output .= "<!--[if lt IE 8]>\n";
    $output .= "<style type='text/css' media='screen'>\n";
    /** csshover.htc provides ":hover" support for IE **/
    $output .= "body { behavior:url(csshover.htc); }\n";
    /** table definition needed to get rid of extra space under items **/
    for ($i = 1; $i < $MenuDepth; $i++) {
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
  function Output($title = NULL)
  {
    global $SysConf;
    $sysConfig = $SysConf['SYSCONFIG'];

    $hide_banner = (array_key_exists(self::BANNER_COOKIE, $_COOKIE)
                    && $_COOKIE[self::BANNER_COOKIE] == 1);

    $vars = array();
    $vars['title'] = empty($title) ? _("Welcome to FOSSology") : $title;
    if ($hide_banner) {
      $vars['bannerMsg'] = "";
      $vars['systemLoad'] = "";
    } else {
      $vars['bannerMsg'] = @$sysConfig['BannerMsg'];
      $vars['systemLoad'] = get_system_load_average().'<br/>';
    }
    $vars['logoLink'] =  $sysConfig['LogoLink']?: 'http://fossology.org';
    $vars['logoImg'] =  $sysConfig['LogoImage']?: 'images/fossology-logo.gif';

    if ( array_key_exists('SupportEmailLabel',$sysConfig) && !empty($sysConfig['SupportEmailLabel'])
            && array_key_exists('SupportEmailAddr',$sysConfig) && !empty($sysConfig['SupportEmailAddr'])) {
      $menuItem = '<a href="mailto:'.$sysConfig['SupportEmailAddr'].'?subject='.@$sysConfig['SupportEmailSubject'].'">'.$sysConfig['SupportEmailLabel'].'</a>';
      menu_insert("Main::Help::".$sysConfig['SupportEmailLabel'], 0, NULL, NULL, NULL, $menuItem);
    }

    $menu = menu_find("Main", $MenuDepth);
    $vars['mainMenu'] = $this->menu_html($menu, 0);
    $vars['uri'] = Traceback_uri();

    /* Handle login information */
    $vars['isLoggedOut'] = ((empty($_SESSION[Auth::USER_NAME])) or ($_SESSION[Auth::USER_NAME] == "Default User"));
    $vars['isLoginPage'] = GetParm("mod", PARM_STRING)=='auth';

    global $SysConf;
    if (array_key_exists('BUILD', $SysConf)) {
      $vars['versionInfo'] = array(
          'version' => $SysConf['BUILD']['VERSION'],
          'buildDate' => $SysConf['BUILD']['BUILD_DATE'],
          'commitHash' => $SysConf['BUILD']['COMMIT_HASH'],
          'commitDate' => $SysConf['BUILD']['COMMIT_DATE'],
          'branchName' => $SysConf['BUILD']['BRANCH']
      );
    }

    if (!$vars['isLoggedOut']) {
      $this->mergeUserLoginVars($vars);
    }

    return $this->renderer->load('components/menu.html.twig')->render($vars);
  }

  private function mergeUserLoginVars(&$vars)
  {
    global $container;
    $dbManager = $container->get("db.manager");

    $vars['logOutUrl'] = Traceback_uri() . '?mod=' . ((plugin_find_id('auth')>=0) ? 'auth' : 'smauth');
    $vars['userName'] = $_SESSION[Auth::USER_NAME];

    $sql = 'SELECT group_pk, group_name FROM group_user_member LEFT JOIN groups ON group_fk=group_pk WHERE user_fk=$1';
    $stmt = __METHOD__ . '.availableGroups';
    $dbManager->prepare($stmt, $sql);
    $res = $dbManager->execute($stmt, array($_SESSION['UserId']));
    $allAssignedGroups = array();
    while ($row = $dbManager->fetchArray($res)) {
      $allAssignedGroups[$row['group_pk']] = $row['group_name'];
    }
    $dbManager->freeResult($res);
    if (count($allAssignedGroups) > 1) {
      $vars['backtraceUri'] = Traceback_uri() . "?mod=" . Traceback_parm();
      $vars['groupId'] = $_SESSION[Auth::GROUP_ID];
      $vars['allAssignedGroups'] = $allAssignedGroups;
    } else {
      $vars['singleGroup'] = @$_SESSION['GroupName'];
    }
  }
}
