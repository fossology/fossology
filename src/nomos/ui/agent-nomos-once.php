<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2015 Siemens AG

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
use Fossology\Lib\Auth\Auth;

/**
 * \file agent-nomos-once.php
 * \brief Run an analysis for a single file, do not store results in the DB.
 */

define("TITLE_agent_nomos_once", _("One-Shot License Analysis"));

class agent_nomos_once extends FO_Plugin
{
  const FILE_INPUT = 'file_input';
  public $HighlightInfoKeywords = array();
  public $HighlightInfoLicenses = array();

  function __construct()
  {
    $this->Name = "agent_nomos_once";
    $this->Title = TITLE_agent_nomos_once;
    $this->Dependency = array();
    $this->NoHTML = 0;  // always print text output for now
    /** For anyone to access, without login, use: **/
    $this->DBaccess   = PLUGIN_DB_READ;
    /** To require login access, use: **/
    //  public $DBaccess = PLUGIN_DB_WRITE;
    //  public $LoginFlag = 1;
    $this->LoginFlag  = 0;
    parent::__construct();
  }

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

   function Install() {
      global $PG_CONN;
    if (empty($PG_CONN)) {
          return(1);
    }
    else return(0);
   }

  /**
   * \brief Change the type of output
   * based on user-supplied parameters.
   *
   * \return 1 on success.
   */
  function RegisterMenus() {
    if ($this->State != PLUGIN_STATE_READY) {
      return 0;
    }
    $ShowHeader = GetParm('showheader', PARM_INTEGER);
    if (empty($ShowHeader)) {
      $ShowHeader = 0;
    }
    $ThisMod = (GetParm("mod", PARM_STRING) == $this->Name) ? 1 : 0;
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
        $tooltipText = _("FATAL: your file did not get passed throught.  Make sure this page wasn't a result of a web server redirect, or that it didn't exceed your php POST limit.");
        echo $tooltipText;
      }
    }

    /* Only register with the menu system if the user is logged in. */
    if (!empty($_SESSION['User'])) {
      if ($_SESSION[Auth::USER_LEVEL] >= PLUGIN_DB_WRITE) {
        menu_insert("Main::Upload::One-Shot Analysis", $this->MenuOrder, $this->Name, $this->MenuTarget);
      }
      // Debugging changes to license analysis
      if ($_SESSION[Auth::USER_LEVEL] >= PLUGIN_DB_ADMIN) {
        $URI = $this->Name . Traceback_parm_keep(array(
          "format",
          "item"
        ));
        $menuText = "One-Shot License";
        $menuPosition = 100;
        $tooltipText = _("One-shot License, real-time license analysis");
        menu_insert("View::[BREAK]", $menuPosition);
        menu_insert("View::{$menuText}", $menuPosition + 1, $URI, $tooltipText);
        menu_insert("View-Meta::[BREAK]", $menuPosition);
        menu_insert("View-Meta::{$menuText}", $menuPosition + 1, $URI, $tooltipText);
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

    $tmp_name = '';
    if (array_key_exists(self::FILE_INPUT, $_FILES) && array_key_exists('tmp_name', $_FILES[self::FILE_INPUT]))
    {
      $tmp_name = $_FILES[self::FILE_INPUT]['tmp_name'];
    }

    /* For REST API:
     wget -qO - --post-file=myfile.c http://myserv.com/?mod=agent_nomos_once
    */
    if ($this->OutputType!='HTML' && file_exists($tmp_name))
    {
      echo $this->AnalyzeFile($tmp_name)."\n";
      unlink($tmp_name);
      return;
    }
    if (file_exists($tmp_name)) {
      $this->vars['content'] = $this->htmlAnalyzedContent($tmp_name, $_FILES[self::FILE_INPUT]['name']);
    }
    else if ($this->OutputType=='HTML') {
      return $this->render('oneshot-upload.html.twig',$this->vars);
    }
    if (array_key_exists('licfile', $_FILES) && array_key_exists('unlink_flag',$_FILES['licfile'])) {
      unlink($tmp_name);
    }
    unset($_FILES[self::FILE_INPUT]);
    $this->vars['styles'] .= "<link rel='stylesheet' href='css/highlights.css'>\n";
    return $this->render($this->getTemplateName(),$this->vars);
  }
  
  private function htmlAnalyzedContent($tmp_name, $filename){
    $text = _("A one shot license analysis shows the following license(s) in file");
    $keep = "$text <em>$filename:</em> ";
    $keep .= "<strong>" . $this->AnalyzeFile($tmp_name) . "</strong><br>";
    $this->vars['message'] = $keep;

    global $Plugins;
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
      $rtn = $view->getView($inputFile, $ModBack, 0, NULL, $highlights);
      fclose($inputFile);
      return $rtn;
    }
  }

}

$NewPlugin = new agent_nomos_once;
$NewPlugin->Install();
