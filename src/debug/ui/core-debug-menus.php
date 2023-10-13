<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

define("TITLE_core_debug_menus", _("Debug Menus"));

/**
 * @class core_debug_menus
 * @brief Plugin to debug menus
 */
class core_debug_menus extends FO_Plugin
{
  function __construct()
  {
    $this->Name       = "debug-menus";
    $this->Title      = TITLE_core_debug_menus;
    $this->MenuList   = "Help::Debug::Debug Menus";
    $this->DBaccess   = PLUGIN_DB_ADMIN;
    parent::__construct();
  }

  /**
   * @brief This is where we check for
   * changes to the full-debug setting.
   * @copydoc FO_Plugin::PostInitialize()
   * @see FO_Plugin::PostInitialize()
   */
  function PostInitialize()
  {
    if ($this->State != PLUGIN_STATE_VALID) {
      return(0);
    }
    // Make sure dependencies are met
    foreach($this->Dependency as $key => $val)
    {
      $id = plugin_find_id($val);
      if ($id < 0) {
        $this->Destroy(); return(0);
      }
    }

    $FullMenuDebug = GetParm("fullmenu",PARM_INTEGER);
    if ($FullMenuDebug == 2)
    {
      $_SESSION['fullmenudebug'] = 1;
    }
    if ($FullMenuDebug == 1)
    {
      $_SESSION['fullmenudebug'] = 0;
    }

    // It worked, so mark this plugin as ready.
    $this->State = PLUGIN_STATE_READY;
    // Add this plugin to the menu
    if ($this->MenuList !== "")
    {
      menu_insert("Main::" . $this->MenuList,$this->MenuOrder,$this->Name,$this->MenuTarget);
    }
    return(1);
  } // PostInitialize()

  /**
   * @brief Display the full menu as an ordered list.
   * This is recursive.
   * @param array $Menu Menu object to print
   * @return string HTML ordered list
   */
  function Menu2HTML(&$Menu)
  {
    $V = "<ol>\n";
    foreach($Menu as $M)
    {
      $V .= "<li>" . htmlentities($M->Name);
      $V .= " (" . htmlentities($M->Order);
      $V .= " -- " . htmlentities($M->URI);
      $V .= " @ " . htmlentities($M->Target);
      $V .= ")\n";
      if (!empty($M->SubMenu)) {
        $this->Menu2HTML($M->SubMenu);
      }
    }
    $V .= "</ol>\n";
    return $V;
  }

  /**
   * @copydoc FO_Plugin::Output()
   * @see FO_Plugin::Output()
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return 0;
    }
    if ($this->OutputToStdout && $this->OutputType=="Text") {
      global $MenuList;
      print_r($MenuList);
    }
    $output = "";
    if ($this->OutputType=='HTML')
    {
      $output = $this->htmlContent();
    }
    if (!$this->OutputToStdout)
    {
      $this->vars['content'] = $output;
      return; // $output;
    }
    print $output;
  }

  /**
   * @brief Get the output as HTML
   * @return string HTML output
   */
  protected function htmlContent()
  {
    $V = '';
    global $MenuList;

    $FullMenuDebug = GetParm("fullmenu",PARM_INTEGER);
    if ($FullMenuDebug == 2)
    {
      $text = _("Full debug ENABLED!");
      $text1 = _("Now view any page.");
      $V .= "<b>$text</b> $text1<P>\n";
    }
    if ($FullMenuDebug == 1)
    {
      $text = _("Full debug disabled!");
      $text1 = _("Now view any page.");
      $V .= "<b>$text</b> $text1<P>\n";
    }
    $text = _("This developer tool lists all items in the menu structure.");
    $V .= "$text\n";
    $text = _("Since some menu inserts are conditional, not everything may appear here (the conditions may not lead to the insertion).");
    $V .= "$text\n";
    $text = _("Fully-debugged menus show the full menu path and order number");
    $text1 = _("in the menu");
    $V .= "$text <i>$text1</i>.\n";
    $V .= "<ul>\n";
    $text = _("The full debugging is restricted to");
    $text1 = _("your");
    $text2 = _(" login session. (Nobody else will see it.)\n");
    $V .= "<li>$text <b>$text1</b>$text2";
    $text = _("Full debugging shows the full menu path for each menu\n");
    $V .= "<li>$text";
    $text = _("and the order is included in parenthesis.");
    $V .= "$text\n";
    $text = _("However, menus that use HTML instead of text will");
    $text1 = _("not");
    $text2 = _("show the full path.\n");
    $V .= "$text <i>$text1</i>$text2";
    $text = _("To disable full debugging, return here and unselect the option.\n");
    $V .= "<li>$text";
    $V .= "</ul>\n";
    $V .= "<br>\n";
    $V .= "<form method='post'>\n";
    if (@$_SESSION['fullmenudebug'] == 1)
    {
      $V .= "<input type='hidden' name='fullmenu' value='1'>";
      $text = _("Disable Full Debug");
    }
    else
    {
      $V .= "<input type='hidden' name='fullmenu' value='2'>";
      $text = _("Enable Full Debug");
    }
    $V .= "<input type='submit' value='$text!'>";
    $V .= "</form>\n";
    $V .= "<hr>";
    $this->Menu2HTML($MenuList);
    $V .= "<hr>\n";
    $V .= "<pre>";
    $V .= htmlentities(print_r($MenuList,1));
    $V .= "</pre>";

    return $V;
  }

}
$NewPlugin = new core_debug_menus;
$NewPlugin->Initialize();
