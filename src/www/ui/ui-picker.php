<?php
/*
 SPDX-FileCopyrightText: © 2010-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;

/**
 * \file ui-picker.php
 * \brief permit people to positively pick a pair of paths,
 * Path pairs are used by reports that do file comparisons and differences between
 * files (like isos, packages, directories, etc.).
 */

/**
 * \brief Sort folder and upload names
 */
function picker_name_cmp($rowa, $rowb)
{
  return (strnatcasecmp($rowa['name'], $rowb['name']));
}


/**
 * \brief Sort filenames
 */
function picker_ufile_name_cmp($rowa, $rowb)
{
  return (strnatcasecmp($rowa['ufile_name'], $rowb['ufile_name']));
}


class ui_picker extends FO_Plugin
{
  var $HighlightColor = '#4bfe78';
  /** @var UploadDao */
  private $uploadDao;

  function __construct()
  {
    $this->Name       = "picker";
    $this->Title      = _("File Picker");
    $this->Dependency = array("browse","view");
    $this->DBaccess   = PLUGIN_DB_READ;
    $this->LoginFlag  = 0;
    parent::__construct();
    $this->uploadDao = $GLOBALS['container']->get('dao.upload');
  }


  /**
   * \brief Create and configure database tables
   */
  function Install()
  {
    global $PG_CONN;
    if (empty($PG_CONN)) {
      return(1);
    } /* No DB */

    /* create it if it doesn't exist */
    $this->Create_file_picker();

    return(0);
  } // Install()

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    $text = _("Compare this file to another.");
    menu_insert("Browse-Pfile::Compare",0,$this->Name,$text);

    return 0;
  } // RegisterMenus()


  /**
   * \brief This is called before the plugin is used.
   *
   * \return true on success, false on failure.
   * A failed initialize is not used by the system.
   *
   * \note This function must NOT assume that other plugins are installed.
   */
  function Initialize()
  {
    if ($this->State != PLUGIN_STATE_INVALID) {
      return (1);
    } // don't re-run
    if ($this->Name !== "") {
      // Name must be defined
      global $Plugins;
      $this->State = PLUGIN_STATE_VALID;
      $Plugins[] = $this;
    }
    return($this->State == PLUGIN_STATE_VALID);
  } // Initialize()


  /**
   * \brief Create file_picker table.
   */
  function Create_file_picker()
  {
    global $PG_CONN;

    /* If table exists, then we are done */
    $sql = "SELECT typlen  FROM pg_type where typname='file_picker' limit 1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) > 0) {
      pg_free_result($result);
      return 0;
    }
    pg_free_result($result);

    /* Create table */
    $sql = "CREATE TABLE file_picker (
    file_picker_pk serial NOT NULL PRIMARY KEY,
    user_fk integer NOT NULL,
    uploadtree_fk1 integer NOT NULL,
    uploadtree_fk2 integer NOT NULL,
    last_access_date date NOT NULL
    );
    ALTER TABLE ONLY file_picker
    ADD CONSTRAINT file_picker_user_fk_key UNIQUE (user_fk, uploadtree_fk1, uploadtree_fk2);";

    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
  }


  /**
   * \brief Given an $File1uploadtree_pk,
   * $Children are non artifact children of $File1uploadtree_pk
   *
   * \return a string with the html table, file listing (the browse tree),
   * for these children.
   */
  function HTMLFileList($File1uploadtree_pk, $Children, $FolderContents)
  {
    global $PG_CONN;
    global $Plugins;

    $OutBuf=""; // return values for file listing
    $Uri = Traceback_uri() . "?mod=$this->Name";

    $OutBuf .= "<table style='text-align:left;' >";

    if (!empty($FolderContents)) {
      usort($FolderContents, 'picker_name_cmp');

      /* write subfolders */
      foreach ($FolderContents as $Folder) {
        if ($Folder && array_key_exists('folder_pk', $Folder)) {
          $folder_pk = $Folder['folder_pk'];
          $folder_name = htmlentities($Folder['name']);
          $OutBuf .= "<tr>";

          $OutBuf .= "<td></td>";

          $OutBuf .= "<td>";
          $OutBuf .= "<a href='$Uri&folder=$folder_pk&item=$File1uploadtree_pk'><b>$folder_name</b></a>/";
          $OutBuf .= "</td></tr>";
        } else if ($Folder && array_key_exists('uploadtree_pk', $Folder)) {
          $bitem = $Folder['uploadtree_pk'];
          $upload_filename = htmlentities($Folder['name']);
          $OutBuf .= "<tr>";
          $OutBuf .= "<td>";
          $text = _("Select");
          $Options = "id=filepick2 onclick='AppJump($bitem)')";
          $OutBuf .= "<button type='button' $Options> $text </button>\n";
          $OutBuf .= "</td>";

          $OutBuf .= "<td>";
          $OutBuf .= "<a href='$Uri&bitem=$bitem&item=$File1uploadtree_pk'><b>$upload_filename</b></a>/";
          $OutBuf .= "</td></tr>";
        }
      }
    } else {
      if (empty($Children)) {
        $text = _("No children to compare");
        $OutBuf .= "<tr><td colspan=2>$text</td></tr>";
      } else {
        usort($Children, 'picker_ufile_name_cmp');
        foreach ($Children as $Child) {
          if (empty($Child)) {
            continue;
          }
          $OutBuf .= "<tr>";

          $IsDir = Isdir($Child['ufile_mode']);
          $IsContainer = Iscontainer($Child['ufile_mode']);

          $LinkUri = $Uri . "&bitem=$Child[uploadtree_pk]&item=$File1uploadtree_pk";

          $OutBuf .= "<td>";
          $text = _("Select");
          $Options = "id=filepick onclick='AppJump($Child[uploadtree_pk])')";
          $OutBuf .= "<button type='button' $Options> $text </button>\n";
          $OutBuf .= "</td>";
          $OutBuf .= "<td>";
          if ($IsContainer) {
            $OutBuf .= "<a href='$LinkUri'> $Child[ufile_name]</a>";
          } else {
            $OutBuf .= $Child['ufile_name'];
          }
          if ($IsDir) {
            $OutBuf .= "/";
          }
          $OutBuf .= "</td>";
          $OutBuf .= "</tr>";
        }
      }
    }
    $OutBuf .= "</table>";
    return($OutBuf);
  } // HTMLFileList()


  /**
   * \brief get a a path to a file
   *
   * \param $File1uploadtree_pk - pk of file1
   * \param $FolderList - folder path for the file (or folder).
   * \param $DirectoryList - directory path to the file.  May be empty. \n
   *
   * \example
   * $FolderList array example: \n
   *  [0] => Array \n
   *          [folder_pk] => 1 \n
   *          [folder_name] => Software Repository \n
   *  [1] => Array \n
   *          [folder_pk] => 5 \n
   *          [folder_name] => cpio \n
   * \n
   * $DirectoryList array example: \n
   * [0] => Array \n
   * [uploadtree_pk] => 897121 \n
   * [parent] => \n
   * [upload_fk] => 11 \n
   * [pfile_fk] => 691036 \n
   * [ufile_mode] => 536904704 \n
   * [lft] => 1 \n
   * [rgt] => 1048 \n
   * [ufile_name] => cpio-2.10-9.el6.src.rpm \n
   *
   * \return string which is a linked path to a file.
   * The path includes folders and files.
   * This is the stuff in the yellow box
   */
  function HTMLPath($File1uploadtree_pk, $FolderList, $DirectoryList)
  {
    if (empty($FolderList)) {
      return "__FILE__ __LINE__ No folder list specified";
    }

    $OutBuf = "";
    $Uri2 = Traceback_uri() . "?mod=$this->Name";

    /* Box decorations */
    $OutBuf .= "<div class='alert alert-info' style='padding:5px;'>\n";

    /* write the FolderList */
    $text = _("Folder");
    $OutBuf .= "<b>$text</b>: ";

    foreach ($FolderList as $Folder) {
      $folder_pk = $Folder['folder_pk'];
      $folder_name = htmlentities($Folder['folder_name']);
      $OutBuf .= "<a href='$Uri2&folder=$folder_pk&item=$File1uploadtree_pk'><b>$folder_name</b></a>/";
    }

      /* write the DirectoryList */
    if (! empty($DirectoryList)) {
      $OutBuf .= "<br>";
      $First = true; /* If $First is true, directory path starts a new line */

      /* Show the path within the upload */
      foreach ($DirectoryList as $uploadtree_rec) {
        if (! $First) {
          $OutBuf .= "/ ";
        }

        $href = "$Uri2&bitem=$uploadtree_rec[uploadtree_pk]&item=$File1uploadtree_pk";
        $OutBuf .= "<a href='$href'>";

        if (!$First && Iscontainer($uploadtree_rec['ufile_mode'])) {
          $OutBuf .= "<br>&nbsp;&nbsp;";
        }

        $OutBuf .= "<b>" . $uploadtree_rec['ufile_name'] . "</b>";
        $OutBuf .= "</a>";
        $First = false;
      }
    }

    $OutBuf .= "</div>\n";  //  box
    return($OutBuf);
  } // HTMLPath()


  /**
   * \brief pick history
   *
   * \param $uploadtree_pk - for File 1 (aka item1)
   *
   * return html for the history pick, may be empty array if no history.
   */
  function HistoryPick($uploadtree_pk, &$rtncount)
  {
    global $PG_CONN;

    $PickerRows = array();

    /* select possible item2's from pick history for this user */
    $user_pk = $_SESSION['UserId'];
    if (empty($user_pk)) {
      return $PickerRows;
    }

    $sql = "select file_picker_pk, uploadtree_fk1, uploadtree_fk2 from file_picker
              where user_fk= '$user_pk' and ($uploadtree_pk=uploadtree_fk1 or $uploadtree_pk=uploadtree_fk2)";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $rtncount = pg_num_rows($result);
    if ($rtncount > 0) {
      $PickerRows = pg_fetch_all($result);
      pg_free_result($result);
    } else {
      /* No rows in history for this item and user */
      pg_free_result($result);
      return "";
    }

    /* reformat $PickHistRecs for select list */
    $PickSelectArray = array();
    foreach ($PickerRows as $PickRec) {
      if ($PickRec['uploadtree_fk1'] == $uploadtree_pk) {
        $item2 = $PickRec["uploadtree_fk2"];
      } else {
        $item2 = $PickRec["uploadtree_fk1"];
      }
      $PathArray = Dir2Path($item2, 'uploadtree');
      $Path = $this->Uploadtree2PathStr($PathArray);
      $PickSelectArray[$item2] = $Path;
    }
    $Options = "id=HistoryPick onchange='AppJump(this.value)')";
    $SelectList = Array2SingleSelect($PickSelectArray, "HistoryPick", "", true,
      true, $Options);
    return $SelectList;
  } /* End HistoryPick() */


  /**
   * \brief Search the whole repository for containers with names
   * similar to $FileName (based on the beggining text of $FileName)
   *
   * \param $uploadtree_pk - the pk of $FileName.
   *
   * \return html (select list) for picking suggestions.
   */
  function SuggestionsPick($FileName, $uploadtree_pk, &$rtncount)
  {
    global $PG_CONN;

    /* find the root of $FileName.  Thats the beginning alpha part. */
    $BaseFN = basename($FileName);
    $delims= "/-.0123456789 \t\n\r\0\0xb";
    $NameRoot = ltrim($BaseFN, $delims);
    $NameRoot = strtok($NameRoot, $delims);

    /* Only make suggestions with matching file extensions */
    $ext = GetFileExt($FileName);
    $tail = ".$ext";

    if (empty($NameRoot)) {
      return "";
    }

    /* find non artifact containers with names similar to $FileName */
    $sql = "select uploadtree_pk from uploadtree
              where ((ufile_mode & (1<<29))!=0) AND ((ufile_mode & (1<<28))=0)
                and (ufile_name like '$NameRoot%$tail')
                and (uploadtree_pk != '$uploadtree_pk') limit 100";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $SuggestionsArray = array();
    while ($row = pg_fetch_assoc($result)) {
      $PathArray = Dir2Path($row['uploadtree_pk'], 'uploadtree');
      $SuggestionsArray[$row['uploadtree_pk']] = $this->Uploadtree2PathStr($PathArray);
    }
    pg_free_result($result);

    $rtncount = count($SuggestionsArray);
    if ($rtncount == 0) {
      return "";
    }

    /* Order the select list by the  beginning of the path */
    natsort($SuggestionsArray);

    $Options = "id=SuggestPick onchange='AppJump(this.value)')";
    $SelectList = Array2SingleSelect($SuggestionsArray, "SuggestionsPick", "",
      true, true, $Options);
    return $SelectList;
  } /* End SuggestionsPick */


  /**
   * \brief file browser
   *
   * \return the HTML for the File browser.
   */
  function BrowsePick($uploadtree_pk, $inBrowseuploadtree_pk, $infolder_pk, $PathArray)
  {
    $OutBuf = "";
    if (empty($inBrowseuploadtree_pk)) {
      $Browseuploadtree_pk = $uploadtree_pk;
    } else {
      $Browseuploadtree_pk = $inBrowseuploadtree_pk;
    }

    if (empty($infolder_pk)) {
      $folder_pk = GetFolderFromItem("", $Browseuploadtree_pk);
    } else {
      $folder_pk = $infolder_pk;
    }

    // Get list of folders that this $Browseuploadtree_pk is in
    $FolderList = Folder2Path($folder_pk);

    // If you aren't browsing folders,
    //   Get list of directories that this $Browseuploadtree_pk is in
    if (empty($infolder_pk)) {
      $DirectoryList = Dir2Path($Browseuploadtree_pk, 'uploadtree');
    } else {
      $DirectoryList = '';
    }

    // Get HTML for folder/directory list.
    // This is the stuff in the yellow bar.
    $OutBuf .= $this->HTMLPath($uploadtree_pk, $FolderList, $DirectoryList);

    /*
     * Get list of folders in this folder
     * That is, $DirectoryList is empty
     */
    if (empty($infolder_pk)) {
      $FolderContents = array();
      $Children = GetNonArtifactChildren($Browseuploadtree_pk);
    } else {
      $Children = array();
      $FolderContents = $this->GetFolderContents($folder_pk);
    }
    $OutBuf .= $this->HTMLFileList($uploadtree_pk, $Children, $FolderContents);

    return $OutBuf;
  } /* End BrowsePick */


  /**
   * \brief get the contents for the folder,
   *  This includes subfolders and uploads.
   *
   *  \example $FolderContents array example: \n
   *  [0] => Array \n
   *          [folder_pk] => 1 \n
   *          [folder_name] => Software Repository \n
   *  [1] => Array \n
   *          [folder_pk] => 5 \n
   *          [folder_name] => cpio \n
   *  [2] => Array \n
   *          [upload_pk] => 123 \n
   *          [upload_filename] =>cpio-1.2.3.rpm \n
   *          [uploadtree_pk] => 987653   (top level uploadtree_pk for this upload) \n
   *
   * \return $FolderContents array:
   */
  function GetFolderContents($folder_pk)
  {
    global $PG_CONN;

    $FolderContents = array();
    $Uri2 = Traceback_uri() . "?mod=$this->Name";

    /* Display all the folders in this folder_pk */
    $sql = "select * from foldercontents where parent_fk='$folder_pk'";
    $FCresult = pg_query($PG_CONN, $sql);
    DBCheckResult($FCresult, $sql, __FILE__, __LINE__);

      /* Display folder contents */
    while ($FCrow = pg_fetch_assoc($FCresult)) {
      switch ($FCrow['foldercontents_mode']) {
        case 1:  /*******   child is folder   *******/
          $sql = "select folder_pk, folder_name as name from folder where folder_pk=$FCrow[child_id]";
          $FolderResult = pg_query($PG_CONN, $sql);
          DBCheckResult($FolderResult, $sql, __FILE__, __LINE__);
          $FolderRow = pg_fetch_assoc($FolderResult);
          pg_free_result($FolderResult);

          $FolderContents[] = $FolderRow;
          break;
        case 2:  /*******   child is upload   *******/
          $sql = "select upload_pk, upload_filename as name from upload"
               . " where upload_pk=$FCrow[child_id] and ((upload_mode & (1<<5))!=0)";
          $UpResult = pg_query($PG_CONN, $sql);
          DBCheckResult($UpResult, $sql, __FILE__, __LINE__);
          $NumRows = pg_num_rows($UpResult);
          if ($NumRows) {
            $UpRow = pg_fetch_assoc($UpResult);
            pg_free_result($UpResult);
          } else {
            pg_free_result($UpResult);
            break;
          }

          /* get top level uploadtree_pk for this upload_pk */
          $sql = "select uploadtree_pk from uploadtree where upload_fk=$FCrow[child_id] and parent is null";
          $UtreeResult = pg_query($PG_CONN, $sql);
          DBCheckResult($UtreeResult, $sql, __FILE__, __LINE__);
          $UtreeRow = pg_fetch_assoc($UtreeResult);
          pg_free_result($UtreeResult);
          $UpRow['uploadtree_pk'] = $UtreeRow['uploadtree_pk'];
          $FolderContents[] = $UpRow;
          break;
        case 4:  /******* child_id is uploadtree_pk (unused)   *******/
        default:
      }
    }
    pg_free_result($FCresult);
    return $FolderContents;
  } /* End GetFolderContents */


  /**
   * \brief the  html format out info
   *
   * \param $RtnMod - module to run after a file is picked
   * \param $uploadtree_pk - of file1
   * \param $Browseuploadtree_pk - uploadtree_pk selected in file browser (may be empty)
   * \param $folder_pk - folder_pk selected in file browser (may be empty)
   * \param $PathArray - path to uploadtree_pk (array of uploadtree recs)
   */
  function HTMLout($RtnMod, $uploadtree_pk, $Browseuploadtree_pk, $folder_pk, $PathArray)
  {
    $OutBuf = '';
    $uri = Traceback_uri() . "?mod=$this->Name";

    /**
     * Script to run when item2 is selected
     * Compare app is id=apick
     * arg: "rtnmod" is the compare app
     * arg: "item" is uploadtree_pk
     * arg: "item2" is val
     */
    $OutBuf .= "<script language='javascript'>\n";
    $OutBuf .= "function AppJump(val) {";
    $OutBuf .=  "var rtnmodelt = document.getElementById('apick');";
    $OutBuf .=  "var rtnmod = rtnmodelt.value;";
    $OutBuf .=  "var uri = '$uri' + '&rtnmod=' + rtnmod + '&item=' + $uploadtree_pk + '&item2=' + val;";
    $OutBuf .=  "window.location.assign(uri);";
    $OutBuf .= "}";
    $OutBuf .= "</script>\n";

    /* Explain what the picker is for */
    $OutBuf .= "The purpose of the picker is to permit people to positively pick a pair of paths.";
    $OutBuf .= "<br>Path pairs are used by reports that do file comparisons and "
             . "differences between files (like isos, packages, directories, etc.).";

    $OutBuf .= "<hr>";

    /* Print file 1 so people know what they are comparing to */
    $OutBuf .= "<div style=background-color:lavender>";
    $OutBuf .= "<center><table style='border:5px groove red'>";
    $OutBuf .= "<tr><td><b>File 1: </b></td><td>&nbsp;&nbsp;</td><td>";
    $PathStr = $this->Uploadtree2PathStr($PathArray);
    $OutBuf .= "$PathStr";
    $OutBuf .= "</td></tr>";
    $OutBuf .= "</table></center>";

    $text = _("Choose the program to run after you select the second file.");
    $OutBuf .= "<b>$text</b><br>";
    $OutBuf .= $this->ApplicationPick("PickRtnApp", $RtnMod, "will run after chosing a file");
    $OutBuf .= "</div>";
    $OutBuf .= "<br>";

    /* Display the history pick, if there is a history for this user. */
    $HistPick = $this->HistoryPick($uploadtree_pk, $rtncount);
    if (! empty($HistPick)) {
      $text = _("Select from your pick history");
      $OutBuf .= "<h3>$text ($rtncount):</h3>";
      $OutBuf .= "$HistPick";
    }

    /**
     *  Suggestions.
     * Suggestions are restricted to the same file type (rpm, bz2, etc)
     * to keep the user from being overwhelmed with choices.
     * So if they want to compare a .bz2 with a .gz, they will have to
     * use the Browse Window.
     */

    /* Browse window */
    $text = _("Browse");
    $OutBuf .= "<hr><h3>$text:</h3>";

    /* Folder/directory bar */
    $OutBuf .= $this->BrowsePick($uploadtree_pk, $Browseuploadtree_pk, $folder_pk, $PathArray);

    return $OutBuf;
  }


  /**
   * \brief The Picker page
   */
  function Output()
  {
    global $PG_CONN;
    if ($this->State != PLUGIN_STATE_READY) {
      return(0);
    }

    /**
     * create table if it doesn't exist (not assuming Install() was run.
     * eg. source update
     */
    $this->Create_file_picker();

    $RtnMod = GetParm("rtnmod",PARM_TEXT);
    $uploadtree_pk = GetParm("item", PARM_INTEGER);
    if (! $uploadtree_pk) {
      return "<h2>Unidentified item 1</h2>";
    }
    $uploadtree_pk2 = GetParm("item2",PARM_INTEGER);
    $folder_pk = GetParm("folder",PARM_INTEGER);
    $user_pk = Auth::getUserId();

    /* Item to start Browse window on */
    $Browseuploadtree_pk = GetParm("bitem",PARM_INTEGER);

    /* Check item1 and item2 upload permissions */
    $Item1Row = GetSingleRec("uploadtree", "WHERE uploadtree_pk = $uploadtree_pk");
    if (! $this->uploadDao->isAccessible($Item1Row['upload_fk'],
      Auth::getGroupId())) {
      $text = _("Permission Denied");
      return "<h2>$text item 1</h2>";
    }

    if (! empty($uploadtree_pk2)) {
      $Item2Row = GetSingleRec("uploadtree", "WHERE uploadtree_pk = $uploadtree_pk2");
      if (! $this->uploadDao->isAccessible($Item2Row['upload_fk'], Auth::getGroupId())) {
        $text = _("Permission Denied");
        return "<h2>$text item 2</h2>";
      }
    }

    /**
     * After picking an item2, this logic will record the pick in
     * the picker history, and then redirect both item1 and item2 to the
     * comparison app.
     */
    if (!empty($user_pk) && !empty($RtnMod) && !empty($uploadtree_pk) && !empty($uploadtree_pk2)) {
      $sql = "insert into file_picker (user_fk, uploadtree_fk1, uploadtree_fk2, last_access_date)
             values($user_pk, $uploadtree_pk, $uploadtree_pk2, now())";
      // ignore errors (most probably a duplicate key)
      @$result = pg_query($PG_CONN, $sql);

      // Redirect to diff module
      $uri = Traceback_uri() . "?mod=$RtnMod&item1=$uploadtree_pk&item2=$uploadtree_pk2";
      echo "<script type='text/javascript'> window.location.assign('$uri');</script>";
      exit();
    }

    $OutBuf = "";

    if ($this->OutputType == 'HTML') {
      if (empty($uploadtree_pk)) {
        $OutBuf = "<h2>Picker URL is missing the first comparison file.</h2>";
      } else {
        $PathArray = Dir2Path($uploadtree_pk, 'uploadtree');
        $OutBuf .= $this->HTMLout($RtnMod, $uploadtree_pk, $Browseuploadtree_pk, $folder_pk, $PathArray);
      }
    }

    return $OutBuf;
  }

  /**
   * \brief Get string representation of uploadtree path.
   *  Use Dir2Path to get $PathArray.
   *
   * \param $PathArry an array containing the path
   *
   * \return string representation of uploadtree path
   */
  private function Uploadtree2PathStr ($PathArray)
  {
    $Path = "";
    if (count($PathArray)) {
      foreach ($PathArray as $PathRow) {
        $Path .= "/" . $PathRow['ufile_name'];
      }
    }
    return $Path;
  }

  /**
   * \brief Generate html to pick the application that will be called after
   * the items are identified.
   *
   * Select list element ID is "apick"
   *
   * \param $SLName - select list name
   * \param $SelectedVal - selected value
   * \param $label - label of select list
   *
   * \return string containing html to pick the application that will be called after
   * the items are identified
   */
  protected function ApplicationPick($SLName, $SelectedVal, $label)
  {
    /* select the apps that are registered to accept item1, item2 pairs.
     * At this time (pre 2.x) we don't know enough about the plugins
     * to know if they can take a pair.  Till then, the list is
     * hardcoded.
     */
    $AppList = array("nomosdiff" => "License Difference",
                     "bucketsdiff" => "Bucket Difference");
    $Options = "id=apick";
    $SelectList = Array2SingleSelect($AppList, $SLName, $SelectedVal,
                                     false, true, $Options);
    $StrOut = "$SelectList $label";
    return $StrOut;
  }
}

$NewPlugin = new ui_picker;
$NewPlugin->Initialize();
