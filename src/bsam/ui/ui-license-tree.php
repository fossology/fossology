<?php
/***********************************************************
 Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

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
 * \file ui-license-tree.php
 * \brief License Tree View for bsam
 */

define("TITLE_ui_license_tree", _("License Tree View"));

class ui_license_tree extends FO_Plugin {
  var $Name = "license-tree";
  var $Title = TITLE_ui_license_tree;
  var $Version = "1.0";
  var $Dependency = array(
    "browse",
    "license",
    "view-license"
  );
  var $DBaccess = PLUGIN_DB_READ;
  var $LoginFlag = 0;
  var $NoHeader = 0;

  /**
   * \brief This function is called when user output is
   * requested.  This function is responsible for assigning headers.
   * If $Type is "HTML" then generate an HTTP header.
   * If $Type is "XML" then begin an XML header.
   * If $Type is "Text" then generate a text header as needed.
   * The $ToStdout flag is "1" if output should go to stdout, and
   * 0 if it should be returned as a string.  (Strings may be parsed
   * and used by other plugins.)
   */
  function OutputOpen($Type, $ToStdout) {
    global $Plugins;
    if ($this->State != PLUGIN_STATE_READY) {
      return (0);
    }
    if (GetParm("output", PARM_STRING) == 'csv') {
      $Type = 'CSV';
    }
    $this->OutputType = $Type;
    $this->OutputToStdout = $ToStdout;
    $Item = GetParm("item", PARM_INTEGER);
    if (empty($Item)) {
      return;
    }
    switch ($this->OutputType) {
      case "CSV":
        $this->NoHeader = 1;
        $Path = Dir2Path($Item);
        $Name = $Path[count($Path) - 1]['ufile_name'] . ".csv";
        header("Content-Type: text/comma-separated-values");
        header('Content-Disposition: attachment; filename="' . $Name . '"');
        break;
      case "XML":
        $V = "<xml>\n";
        break;
      case "HTML":
        header('Content-type: text/html');
        if ($this->NoHTML) {
          return;
        }
        $V = "";
        if (($this->NoMenu == 0) && ($this->Name != "menus")) {
          $Menu = & $Plugins[plugin_find_id("menus") ];
          $Menu->OutputSet($Type, $ToStdout);
        }
        else {
          $Menu = NULL;
        }
        /* DOCTYPE is required for IE to use styles! (else: css menu breaks) */
        $V.= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "xhtml1-frameset.dtd">' . "\n";
        // $V .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">' . "\n";
        // $V .= '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Loose//EN" "http://www.w3.org/TR/html4/loose.dtd">' . "\n";
        // $V .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "xhtml1-strict.dtd">' . "\n";
        $V.= "<html>\n";
        $V.= "<head>\n";
        if ($this->NoHeader == 0) {
          /** Known bug: DOCTYPE "should" be in the HEADER
           and the HEAD tags should come first.
           Also, IE will ignore <style>...</style> tags that are NOT
           in a <head>...</head> block.
           *
           */
          if (!empty($this->Title)) {
            $V.= "<title>" . htmlentities($this->Title) . "</title>\n";
          }
          $V.= "<link rel='stylesheet' href='fossology.css'>\n";
          print $V;
          $V = "";
          if (!empty($Menu)) {
            print $Menu->OutputCSS();
          }
          $V.= "</head>\n";
          $V.= "<body class='text'>\n";
          print $V;
          $V = "";
          if (!empty($Menu)) {
            $Menu->Output($this->Title);
          }
        }
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) {
      return ($V);
    }
    print $V;
    return;
  } // OutputOpen()

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus() {
    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array(
      "show",
      "format",
      "page",
      "upload",
      "item",
    ));
    $Item = GetParm("item", PARM_INTEGER);
    $Upload = GetParm("upload", PARM_INTEGER);
    if (!empty($Item) && !empty($Upload)) {
      if (GetParm("mod", PARM_STRING) == $this->Name) {
        menu_insert("Browse::License Tree", -6);
        //menu_insert("Browse::[BREAK]", -4);
        menu_insert("Browse::CSV", -4, $URI . "&output=csv");
      }
      else {
        $text = _("View license tree");
        menu_insert("Browse::License Tree",-6 , $URI, $text);
      }
    }
  } // RegisterMenus()

  /**
   * \brief Given two elements sort them by name.
   * Used for sorting the histogram.
   */
  function SortName($a, $b) {
    list($A0, $A1, $A2) = explode("\|", $a, 3);
    list($B0, $B1, $B2) = explode("\|", $b, 3);
    /* Sort by count */
    if ($A0 < $B0) {
      return (1);
    }
    if ($A0 > $B0) {
      return (-1);
    }
    /* Same count? sort by root name.
     Same root? place real before style before partial. */
    $A0 = str_replace('-partial$', "", $A1);
    if ($A0 != $A1) {
      $A1 = '-partial';
    }
    else {
      $A0 = str_replace('-style', "", $A1);
      if ($A0 != $A1) {
        $A1 = '-style';
      }
      else {
        $A1 = '';
      }
    }
    $B0 = str_replace('-partial$', "", $B1);
    if ($B0 != $B1) {
      $B1 = '-partial';
    }
    else {
      $B0 = str_replace('-style', "", $B1);
      if ($B0 != $B1) {
        $B1 = '-style';
      }
      else {
        $B1 = '';
      }
    }
    if ($A0 != $B0) {
      return (strcmp($A0, $B0));
    }
    if ($A1 == "") {
      return (-1);
    }
    if ($B1 == "") {
      return (1);
    }
    if ($A1 == "-partial") {
      return (-1);
    }
    if ($B1 == "-partial") {
      return (1);
    }
    return (strcmp($A1, $B1));
  } // SortName()

  /**
   * \brief Generate CSV output.
   * Use "|" as the divider (not a comma) because commas can appear  in file names.
   */
  function ShowOutputCSV(&$LicCount, &$LicSum, &$IsContainer, &$IsArtifact, &$IsDir, &$Path, &$Name, &$LicUri, &$LinkUri) {
    print number_format($LicCount, 0, "", ",");
    print "|";
    /* Show license summary */
    print $LicSum . "|";
    /* Show the history path */
    for ($i = 0;!empty($Path[$i]);$i++) {
      print $Path[$i] . "|";
    }
    print $Name;
    if ($IsDir) {
      print "/";
      $Name.= "/";
    }
    else if ($IsContainer) {
      $Name.= "|::";
    }
    print "\n";
  } // ShowOutputCSV()

  /**
   * \brief Generate HTML output.
   */
  function ShowOutputHTML(&$LicCount, &$LicSum, &$IsContainer, &$IsArtifact, &$IsDir, &$Path, &$Name, &$LicUri, &$LinkUri) {
    {
      print "<tr><td align='right' width='10%' valign='top'>";
      print "[" . number_format($LicCount, 0, "", ",") . "&nbsp;";
      print "license" . ($LicCount == 1 ? "" : "s");
      print "</a>";
      print "]";
      /* Compute license summary */
      print "</td><td width='1%'></td><td width='10%' valign='top'>";
      print htmlentities($LicSum);
      /* Show the history path */
      print "</td><td width='1%'></td><td valign='top'>";
      for ($i = 0;!empty($Path[$i]);$i++) {
        print $Path[$i];
      }
      $HasHref = 0;
      if ($IsContainer) {
        print "<a href='$LicUri'>";
        $HasHref = 1;
      }
      else if (!empty($LinkUri)) {
        print "<a href='$LinkUri'>";
        $HasHref = 1;
      }
      if ($IsContainer) {
        print "<b>";
      };
      print $Name;
      if ($IsContainer) {
        print "</b>";
      };
      if ($IsDir) {
        print "/";
        $Name.= "/";
      }
      else if ($IsContainer) {
        $Name.= " :: ";
      }
      if ($HasHref) {
        print "</a>";
      }
      print "</td></tr>";
    }
  } // ShowOutputHTML()

  /**
   * \brief Given an Upload and UploadtreePk item, display:
   * - The file listing for the directory, with license navigation.
   * - Recursively traverse the tree.
   * \note This is recursive! 
   * Output goes to stdout!
   */
  function ShowLicenseTree($Upload, $Item, $Uri, $Path = NULL) {
    /*****
     Get all the licenses PER item (file or directory) under this
    UploadtreePk.
    Save the data 3 ways:
    - Number of licenses PER item.
    - Number of items PER license.
    - Number of items PER license family.
    *****/
    global $Plugins;
    $Time = time();
    $ModLicView = & $Plugins[plugin_find_id("view-license") ];
    if ($Path == NULL) {
      $Path = array();
    }
    /****************************************/
    /* Get the items under this UploadtreePk */
    $Children = DirGetList($Upload, $Item);
    $Name = "";
    foreach($Children as $C) {
      if (empty($C)) {
        continue;
      }
      /* Store the item information */
      $IsDir = Isdir($C['ufile_mode']);
      $IsContainer = Iscontainer($C['ufile_mode']);
      $IsArtifact = Isartifact($C['ufile_mode']);
      /* Load licenses for the item */
      $Lics = array();
      LicenseGetAll($C['uploadtree_pk'], $Lics);
      /* Determine the hyperlinks */
      if (!empty($C['pfile_fk'])) {
        $LinkUri = "$Uri&item=" . $C['uploadtree_pk'];
        $LinkUri = str_replace("mod=license-tree", "mod=view-license", $LinkUri);
      }
      else {
        $LinkUri = NULL;
      }
      if (Iscontainer($C['ufile_mode'])) {
        $uploadtree_pk = DirGetNonArtifact($C['uploadtree_pk']);
        $LicUri = "$Uri&item=" . $uploadtree_pk;
        $LicUri = str_replace("mod=license-tree", "mod=license", $LicUri);
      }
      else {
        $LicUri = NULL;
      }
      /* Populate the output */
      ksort($Lics);
      $LicCount = $Lics[' Total '];
      $LicSum = "";
      foreach($Lics as $Key => $Val) {
        if ($Key == " Total ") {
          continue;
        }
        if (!empty($LicSum)) {
          $LicSum.= ",";
        }
        $LicSum.= $Key;
      }
      /* Display the results */
      if ($LicCount > 0) {
        $LicSum = "";
        foreach($Lics as $Key => $Val) {
          if ($Key == " Total ") {
            continue;
          }
          if (!empty($LicSum)) {
            $LicSum.= ",";
          }
          $LicSum.= $Key;
        }
        $Name = $C['ufile_name'];
        if ($IsArtifact) {
          $Name = str_replace("artifact.", "", $Name);
        }
        if ($this->OutputType == 'HTML') {
          $this->ShowOutputHTML($LicCount, $LicSum, $IsContainer, $IsArtifact, $IsDir, $Path, $Name, $LicUri, $LinkUri);
        }
        else if ($this->OutputType == 'CSV') {
          $this->ShowOutputCSV($LicCount, $LicSum, $IsContainer, $IsArtifact, $IsDir, $Path, $Name, $LicUri, $LinkUri);
        }
      }
      /* Recurse! */
      if (($IsDir || $IsContainer) && ($LicCount > 0)) {
        $NewPath = $Path;
        $NewPath[] = $Name;
        $this->ShowLicenseTree($Upload, $C['uploadtree_pk'], $Uri, $NewPath);
      }
    } /* for each item in the directory */
    flush();
  } // ShowLicenseTree()

  /**
   * \brief This function returns the scheduler status.
   */
  function Output() {
    if ($this->State != PLUGIN_STATE_READY) {
      return (0);
    }
    $V = "";
    $Folder = GetParm("folder", PARM_INTEGER);
    $Upload = GetParm("upload", PARM_INTEGER);
    $Item = GetParm("item", PARM_INTEGER);
    if (empty($Item)) {
      return;
    }
    switch ($this->OutputType) {
      case "CSV":
        $text = _("License Count|License Summary|Path");
        print "$text\n";
        $this->ShowLicenseTree($Upload, $Item, $Uri);
        break;
      case "XML":
        break;
      case "HTML":
        $V.= "<font class='text'>\n";
        /************************/
        /* Show the folder path */
        /************************/
        $V.= Dir2Browse($this->Name, $Item, NULL, 1, "Browse") . "<P />\n";
        /******************************/
        /* Get the folder description */
        /******************************/
        if (!empty($Folder)) {
          // $V .= $this->ShowFolder($Folder);

        }
        if (!empty($Upload)) {
          print $V;
          $V = "";
          $Uri = preg_replace("/&item=([0-9]*)/", "", Traceback());
          print "<table border='0' width='100%'>";
          $this->ShowLicenseTree($Upload, $Item, $Uri);
          print "</table>";
        }
        $V.= "</font>\n";
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) {
      return ($V);
    }
    print "$V";
    return;
  }
};
$NewPlugin = new ui_license_tree;
$NewPlugin->Initialize();
?>
