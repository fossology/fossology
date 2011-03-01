<?php
/*
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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
 */
/*
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 */
global $GlobalReady;
if (!isset($GlobalReady)) {
  exit;
}

define("TITLE_ajax_oneShotCopyright", _("One-Shot Copyright/Email/URL Analysis"));

class ajax_oneShotCopyright extends FO_Plugin {
  public $Name = "ajax_oneShotCopyright";
  public $Title = TITLE_ajax_oneShotCopyright;
  public $Version = "1.0";
  public $Dependency = array("view","copyrightview",);
  public $NoHTML = 1;
  /** For anyone to access, without login, use: **/
  // public $DBaccess   = PLUGIN_DB_NONE;
  // public $LoginFlag  = 0;

  /** To require login access, use: **/
  public $DBaccess = PLUGIN_DB_ANALYZE;
  public $LoginFlag = 1;

  /*********************************************
   RegisterMenus(): Change the type of output
   based on user-supplied parameters.
   Returns 1 on success.
   *********************************************/
  function RegisterMenus()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return (0);
    } // don't run
    $Highlight = GetParm('highlight', PARM_INTEGER);
    if (empty($Hightlight))
    {
      $Highlight = 0;
    }
    $ShowHeader = GetParm('showheader', PARM_INTEGER);
    if (empty($ShowHeader))
    {
      $ShowHeader = 0;
    }
    if (GetParm("mod", PARM_STRING) == $this->Name)
    {
      $ThisMod = 1;
    }
    else
    {
      $ThisMod = 0;
    }
    /* Check for a wget post (wget cannot post to a variable name) */
    if ($ThisMod && empty($_POST['licfile']))
    {
      $Fin = fopen("php://input", "r");
      $Ftmp = tempnam(NULL, "fosslic-alo-");
      $Fout = fopen($Ftmp, "w");
      while (!feof($Fin))
      {
        $Line = fgets($Fin);
        fwrite($Fout, $Line);
      }
      fclose($Fout);
      if (filesize($Ftmp) > 0)
      {
        $_FILES['licfile']['tmp_name'] = $Ftmp;
        $_FILES['licfile']['size'] = filesize($Ftmp);
        $_FILES['licfile']['unlink_flag'] = 1;
      }
      else
      {
        unlink($Ftmp);
      }
      fclose($Fin);
    }
    if ($ThisMod && file_exists(@$_FILES['licfile']['tmp_name']) && ($Highlight != 1) && ($ShowHeader != 1))
    {
      $this->NoHTML = 1;
      /* default header is plain text */
    }
    /*
     // Only register with the menu system if the user is logged in.
     if (!empty($_SESSION['User']))
     {
     // Debugging changes to license analysis NOTE: this comment doesn't make sense.
     if (@$_SESSION['UserLevel'] >= PLUGIN_DB_ANALYZE)
     {
     menu_insert("Main::Upload::One-Shot Copyright/Email/URL", $this->MenuOrder, $this->Name, $this->MenuTarget);
     }
     // Debugging changes to license analysis
     if (@$_SESSION['UserLevel'] >= PLUGIN_DB_DEBUG)
     {
     $URI = $this->Name . Traceback_parm_keep(array(
     "format",
     "upload",
     "item"
     ));

     $text = _("Copyright/Email/URL One-shot, real-time analysis");
     menu_insert("View::[BREAK]", 100);
     menu_insert("View::One-Shot Copyright/Email/URL", 101, $URI, $text);
     menu_insert("View-Meta::[BREAK]", 100);
     menu_insert("View-Meta::One-Shot Copyright/Email/URL", 101, $URI, $text);
     }
     }
     */
  } // RegisterMenus()
  /*********************************************
  Output(): Generate the text for this plugin.
  *********************************************/
  function Output() {

    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V = "";
    switch ($this->OutputType) {
      case "XML":
        break;
      case "HTML":

        /* Display instructions */
        $text.= _("This analyzer allows you to upload a single file for
                copyright/email/url analysis from your computer.\n");
        $text1 .= _("The limitations:\n");
        $V .= "$text<br>$text1";
        $V .= "<ul>\n";
        $V .= _("<li>The analysis is done in real-time. Large files may take a while. This method will not work for files larger than 100 kilobytes.\n");
        $V .= _("Files larger than 100K will be discarded and not analyzed.\n");
        $V .= _("<li>Files that contain files are <b>not</b> unpacked. If you upload a 'zip' or 'deb' file, then the binary file will be scanned for copyright/email/urls and nothing will likely be found.\n");
        $V .= _("<li>Results are <b>not</b> stored. As soon as you get your results, your uploaded file is removed from the system.\n");
        $V .= "</ul>\n";
        /* Display the form */
        $V .= "<form name='oscopyright' enctype='multipart/form-data' method='post'>\n";
        $V .= "<input type='hidden' name='uploadform' value='oneShotCopyright'>\n";
        $V .= "<ol>\n";
        $V .= _("<li>Select the file to upload:<br />\n");
        $V .= "<input name='licfile' size='60' type='file' /><br /><br>\n";
        $text = _("Check if you want to see the highlighted text");
        $V .= "<li><input type='checkbox' name='highlight' value='1'>$text.\n";
        $V .= _("Unchecked returns a simple list that summarizes the identified types.");
        $V .= "<P />\n";
        $V .= "</ol>\n";
        $V .= "<input type='hidden' name='showheader' value='1'>";
        $text = _("Analyze");
        $V .= "<input type='submit' value='$text!'>\n";
        $V .= "</form>\n";
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) {
      return ($V);
    }
    print ($V);
    return;
  }
};
$NewPlugin = new ajax_oneShotCopyright;
?>
