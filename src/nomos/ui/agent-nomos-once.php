<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.

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
use Fossology\Lib\Data\Highlight;

/**
 * \file agent-nomos-once.php
 * \brief Run an analysis for a single file, do not store results in the DB.
 */

define("TITLE_agent_nomos_once", _("One-Shot License Analysis"));

class agent_nomos_once extends FO_Plugin {

  public $Name = "agent_nomos_once";
  public $Title = TITLE_agent_nomos_once;
  public $Version = "1.0";
  /* note: no menulist needed, it's insterted in the code below */
  public $Dependency = array();
  public $NoHTML = 0;  // always print text output for now
  /** For anyone to access, without login, use: **/
  public $DBaccess   = PLUGIN_DB_NONE;
  public $LoginFlag  = 0;

  public $HighlightInfoKeywords = array();
  public $HighlightInfoLicenses = array();
  /** To require login access, use: **/
  //  public $DBaccess = PLUGIN_DB_WRITE;
  //  public $LoginFlag = 1;

  /**
   * @brief Analyze one uploaded file.
   * @param string $FilePath the filepath to the file to analyze.
   * @return string $V, html to display the results.
   */
  function AnalyzeFile($FilePath) {
    global $SYSCONFDIR;

    exec("$SYSCONFDIR/mods-enabled/nomos/agent/nomos -S $FilePath",$out,$rtn);
    $licensesFromAgent = explode('contains license(s)',$out[0]);
    $licenses_and_Highlight = end( $licensesFromAgent );
    $licenses = explode ('Highlighting Info at',  $licenses_and_Highlight);
   
     preg_match_all('/Keyword at (?P<position>\d+), length (?P<length>\d+),/',
            $licenses[1],$this->HighlightInfoKeywords );
    preg_match_all('/License #(?P<name>[^#]*)# at (?P<position>\d+), length (?P<length>\d+),/',
            $licenses[1],$this->HighlightInfoLicenses);
   
    return ($licenses[0]);
  } // AnalyzeFile()

  /**
   * \brief Change the type of output
   * based on user-supplied parameters.
   *
   * \return 1 on success.
   */
  function RegisterMenus() {
    if ($this->State != PLUGIN_STATE_READY) {
      return (0);
    } // don't run
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
    /*
     * This if stmt is true only for wget.
     * For wget, populate the $_FILES array, just like the UI post would do.
     * Sets the unlink_flag if there is a temp file.
     */
    if ($ThisMod && empty($_POST['showheader']) && ($_SERVER['REQUEST_METHOD'] == "POST"))
    {
      $Fin = fopen("php://input", "r");
      $Ftmp = tempnam(NULL, "fosslic-alo-");
      $Fout = fopen($Ftmp, "w");
      while (!feof($Fin)) {
        $Line = fgets($Fin);
        fwrite($Fout, $Line);
      }
      fclose($Fin);
      fclose($Fout);

      /* Populate _FILES from wget so the processing logic only has to look in one
       * place wether the data came from wget or the UI
       */
      if (filesize($Ftmp) > 0)
      {
        $_FILES['licfile']['tmp_name'] = $Ftmp;
        $_FILES['licfile']['size'] = filesize($Ftmp);
        $_FILES['licfile']['unlink_flag'] = 1;
        $this->NoHTML = 1;
      }
      else
      {
        unlink($Ftmp);
        /* If there is no input data, then something is wrong.
         * For example the php POST limit is too low and prevented
         * the data from coming through.  Or there was an apache redirect,
         * which removes the POST data.
         */
        $text = _("FATAL: your file did not get passed throught.  Make sure this page wasn't a result of a web server redirect, or that it didn't exceed your php POST limit.");
        echo $text;
      }
    }

    /* Only register with the menu system if the user is logged in. */
    if (!empty($_SESSION['User'])) {
      if (@$_SESSION['UserLevel'] >= PLUGIN_DB_WRITE) {
        menu_insert("Main::Upload::One-Shot Analysis", $this->MenuOrder, $this->Name, $this->MenuTarget);
      }
      // Debugging changes to license analysis
      if (@$_SESSION['UserLevel'] >= PLUGIN_DB_ADMIN) {
        $URI = $this->Name . Traceback_parm_keep(array(
          "format",
          "item"
        ));
        menu_insert("View::[BREAK]", 100);
        $text = _("One-shot License, real-time license analysis");
        menu_insert("View::One-Shot License", 101, $URI, $text);
        menu_insert("View-Meta::[BREAK]", 100);
        $text = _("Nomos One-shot, real-time license analysis");
        menu_insert("View-Meta::One-Shot License", 101, $URI, $text);
      }
    }
  } // RegisterMenus()

  /**
   * \brief Generate the text for this plugin.
   */
  function Output() {
    global $Plugins;
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }

    /* Ignore php Notice is array keys don't exist */
    $errlev = error_reporting(E_ERROR | E_WARNING | E_PARSE);
    $tmp_name = $_FILES['licfile']['tmp_name'];
    error_reporting($errlev);

    /* For REST API:
     wget -qO - --post-file=myfile.c http://myserv.com/?mod=agent_nomos_once
    */
    if ($this->NoHTML && file_exists($tmp_name))
    {
      echo $this->AnalyzeFile($tmp_name);
      echo "\n";
      unlink($tmp_name);
      return;
    }
    if (file_exists($tmp_name)) {
      $text = _("A one shot license analysis shows the following license(s) in file");
      $keep = "$text <em>{$_FILES['licfile']['name']}:</em> ";
      $keep .= "<strong>" . $this->AnalyzeFile($tmp_name) . "</strong><br>";
      $_FILES['licfile'] = NULL;
      print $keep;

      if (!empty($_FILES['licfile']['unlink_flag'])) {
        unlink($tmp_name);
      }

      /** @var ui_view $view */
      $view = & $Plugins[plugin_find_id("view") ];
      $ModBack = GetParm("modback",PARM_STRING);
      
      $highlights = array();

      for ($index = 0; $index < count($this->HighlightInfoKeywords['position']); $index++)
      {
        $position = $this->HighlightInfoKeywords['position'][$index];
        $length = $this->HighlightInfoKeywords['length'][$index];

        $highlights[] = new Highlight($position, $position + $length, Highlight::KEYWORD);
      }

      for ($index = 0; $index < count($this->HighlightInfoLicenses['position']); $index++)
      {
        $position = $this->HighlightInfoLicenses['position'][$index];
        $length = $this->HighlightInfoLicenses['length'][$index];
        $name = $this->HighlightInfoLicenses['name'][$index];

        $highlights[] = new Highlight($position, $position + $length, Highlight::SIGNATURE, $name);
      }
      
      $inputFile = fopen($tmp_name, "r");
      if ($inputFile) {
        $view->ShowView($inputFile, $ModBack, 0, 0, NULL, True, False, $highlights);
        fclose($inputFile);
      }
      return "";
    }

    $V = "";
    switch ($this->OutputType) {
      case "XML":
        break;
      case "HTML":
        /* Display instructions */
        $V.= _("This analyzer allows you to upload a single file for license analysis.\n");
        $V.= _("The limitations:\n");
        $V.= "<ul>\n";
        $V.= _("<li>The analysis is done in real-time. Large files may take a while." .
             " This method is not recommended for files larger than a few hundred kilobytes.\n");
        $text = _("Files that contain files are");
        $text1 = _("not");
        $text2 = _("unpacked. If you upload a 'zip' or 'deb' file, then the binary file will be scanned for licenses and nothing will likely be found.");
        $V.= "<li>$text <b>$text1</b> $text2\n";
        $text = _("Results are");
        $text1 = _("not");
        $text2 = _("stored. As soon as you get your results, your uploaded file is removed from the system. ");
        $V.= "<li>$text <b>$text1</b> $text2\n";
        $V.= "</ul>\n";
        /* Display the form */
        $V.= "<form enctype='multipart/form-data' method='post'>\n";
        $V.= "<ul>\n";
        $V.= _("<li>Select the file to upload:<br />\n");
        $V.= "<input name='licfile' size='60' type='file' /><br />\n";
        $V.= "</ul>\n";
        $V.= "<input type='hidden' name='showheader' value='1'>";
        $text = _("Analyze");
        $V.= "<input type='submit' value='$text!'>\n";
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
  }
}

$NewPlugin = new agent_nomos_once;
