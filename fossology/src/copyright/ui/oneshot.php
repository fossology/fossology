<?php
/***********************************************************
 Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.

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
 * \file oneshot.php
 * \brief One-Shot Copyright/Email/URL Analysis
 */

define("TITLE_agent_copyright_once", _("One-Shot Copyright/Email/URL Analysis"));

class agent_copyright_once extends FO_Plugin {
  public $Name = "agent_copyright_once";
  public $Title = TITLE_agent_copyright_once;
  // Note: menuList is not needed for this plugin, it inserts into the menu
  // in the code below.
  //public $MenuList = "Upload::One-Shot Bsam";
  public $Version = "1.0";
  public $Dependency = array(
        "view",
        "copyrightview"
  );
  public $NoHTML = 0;
  /** For anyone to access, without login, use: **/
  // public $DBaccess   = PLUGIN_DB_NONE;
  // public $LoginFlag  = 0;

  /** To require login access, use: **/
  public $DBaccess = PLUGIN_DB_ANALYZE;
  public $LoginFlag = 1;

  /**
   * \brief Analyze one uploaded file.
   * \param $Highlight - if copyright info lines would be highlight, now always yes
   */
  function AnalyzeOne($Highlight) {
    global $Plugins;
    global $SYSCONFDIR;
    $ModBack = GetParm("modback",PARM_STRING);

    $V = "";
    $View = & $Plugins[plugin_find_id("view") ];
    $TempFile = $_FILES['licfile']['tmp_name'];
    $ui_dir = getcwd();
    $copyright_dir =  "$SYSCONFDIR/mods-enabled/copyright/agent/";
    if(!chdir($copyright_dir)) {
      $errmsg = _("unable to change working directory to $copyright_dir\n");
      return $errmsg;
    }
    $Sys = "./copyright -C $TempFile -c $SYSCONFDIR";
    $Fin = popen($Sys, "r");
    $colors = Array();
    $colors['statement'] = 0;
    $colors['email'] = 1;
    $colors['url'] = 2;
    $stuff = Array();
    $stuff['statement'] = Array();
    $stuff['email'] = Array();
    $stuff['url'] = Array();
    while (!feof($Fin)) {
      $Line = fgets($Fin);
      if (strlen($Line) > 0) {
        //print $Line;
        $match = array();
        preg_match_all("/\t\[(?P<start>\d+)\:(?P<end>\d+)\:(?P<type>[A-Za-z]+)\] \'(?P<content>.+)\'/", $Line, $match);
        //print_r($match);
        if (!empty($match['start'])) {
          $stuff[$match['type'][0]][] = $match['content'][0];
          $View->AddHighlight($match['start'][0], $match['end'][0], $colors[$match['type'][0]], '', $match['content'][0],-1);
        }
      }
    }
    pclose($Fin);
    $Fin = fopen($TempFile, "r");
    if ($Fin) {
      $View->SortHighlightMenu();
      $View->ShowView($Fin,$ModBack, 1,1,NULL,True, False);
      fclose($Fin);
    }
    if(!chdir($ui_dir)) {
      $errmsg = _("unable to change back to working directory $ui_dir\n");
      return $errmsg;
    }
    /* Clean up */
    return ($V);
  } // AnalyzeOne()

  /**
   * \brief Change the type of output
   *  based on user-supplied parameters.
   * 
   * \brief return 1 on success.
   */
  function RegisterMenus() {
    if ($this->State != PLUGIN_STATE_READY) {
      return (0);
    } // don't run
    $Highlight = GetParm('highlight', PARM_INTEGER);
    if (empty($Hightlight)) {
      $Highlight = 0;
    }
    $ShowHeader = GetParm('showheader', PARM_INTEGER);
    if (empty($ShowHeader)) {
      $ShowHeader = 0;
    }
    if (GetParm("mod", PARM_STRING) == $this->Name) {
      $ThisMod = 1;
    }
    else {
      $ThisMod = 0;
    }
    /* Check for a wget post (wget cannot post to a variable name) */
    if ($ThisMod && empty($_POST['licfile'])) {
      $Fin = fopen("php://input", "r");
      $Ftmp = tempnam(NULL, "fosslic-alo-");
      $Fout = fopen($Ftmp, "w");
      while (!feof($Fin)) {
        $Line = fgets($Fin);
        fwrite($Fout, $Line);
      }
      fclose($Fout);
      if (filesize($Ftmp) > 0) {
        $_FILES['licfile']['tmp_name'] = $Ftmp;
        $_FILES['licfile']['size'] = filesize($Ftmp);
        $_FILES['licfile']['unlink_flag'] = 1;
      }
      else {
        unlink($Ftmp);
      }
      fclose($Fin);
    }
    if ($ThisMod && file_exists(@$_FILES['licfile']['tmp_name']) && ($Highlight != 1) && ($ShowHeader != 1)) {
      $this->NoHTML = 1;
      /* default header is plain text */
    }
    /* Only register with the menu system if the user is logged in. */
    if (!empty($_SESSION['User']))
    {
      // Debugging changes to license analysis NOTE: this comment doesn't make sense.
      if (@$_SESSION['UserLevel'] >= PLUGIN_DB_ANALYZE)
      {
        menu_insert("Main::Upload::One-Shot Copyright/Email/URL", $this->MenuOrder, $this->Name, $this->MenuTarget);

        $text = _("Copyright/Email/URL One-shot, real-time analysis");
        menu_insert("View::[BREAK]", 100);
        menu_insert("View::One-Shot Copyright/Email/URL", 101, $this->Name, $text);
        menu_insert("View-Meta::[BREAK]", 100);
        menu_insert("View-Meta::One-Shot Copyright/Email/URL", 101, $this->Name, $text);
      }
    }
  } // RegisterMenus()

  /**
   * \brief Generate the text for this plugin.
   */
  function Output() {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V = "";
    switch ($this->OutputType) {
      case "XML":
        break;
      case "HTML":
        /* If this is a POST, then process the request. */
        $Highlight = GetParm('highlight', PARM_INTEGER); // may be null
        if (file_exists(@$_FILES['licfile']['tmp_name'])) {
          if ($_FILES['licfile']['size'] <= 1024 * 1024 * 10) {
            /* Size is not too big.  */
            print $this->AnalyzeOne($Highlight) . "\n";
          }
          if (!empty($_FILES['licfile']['unlink_flag'])) {
            unlink($_FILES['licfile']['tmp_name']);
          }
          return;
        }

        /* Display instructions */
        $V.= _("This analyzer allows you to upload a single file for copyright/email/url analysis.\n");
        $V.= "<ul>\n";
        $V.= "<li>" . _("The analysis is done in real-time.");
        $V.= "<li>" . _("Files that contain files are <b>not</b> unpacked. If you upload a container like a gzip file, then only that binary file will be scanned.\n");
        $V.= "<li>" . _("Results are <b>not</b> stored. As soon as you get your results, your uploaded file is removed from the system.\n");
        $V.= "</ul>\n";
        /* Display the form */
        $V.= "<form enctype='multipart/form-data' method='post'>\n";
        $V.= _("Select the file to upload:");
        $V.= "<br><input name='licfile' size='60' type='file' /><br />\n";
        $V.= "<input type='hidden' name='showheader' value='1'>";

        $text = _("Upload and scan");
        $V.= "<p><input type='submit' value='$text'>\n";
        $V.= "</form>\n";
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!empty($_FILES['licfile']['unlink_flag'])) {
      unlink($_FILES['licfile']['tmp_name']);
    }
    if (!$this->OutputToStdout) {
      return ($V);
    }
    print ($V);
    return;
  }
};
$NewPlugin = new agent_copyright_once;
?>
