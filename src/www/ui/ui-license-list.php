<?php
/***********************************************************
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
 **********************************************************/
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * \file ui-license-list.php
 * \brief list license for files/directories
 */
define("TITLE_ui_license_list", _("License List"));

class ui_license_list extends FO_Plugin {
  var $Name = "license-list";
  var $Title = TITLE_ui_license_list;
  var $Version = "1.0";
  var $Dependency = array("browse");
  var $DBaccess = PLUGIN_DB_READ;
  var $LoginFlag = 0;
  var $NoHeader = 0;

  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    // For all other menus, permit coming back here.
    $URI = $this->Name . Traceback_parm_keep(array(
                "show",
                "format",
                "page",
                "upload",
                "item",
    ));
    $MenuDisplayString = _("License List");
    $MenuDisplayStringDL = _("License List Download");
    $Item = GetParm("item", PARM_INTEGER);
    $Upload = GetParm("upload", PARM_INTEGER);
    if (empty($Item) || empty($Upload))
    {
      return;
    }
    if (GetParm("mod", PARM_STRING) == $this->Name)
    {
      menu_insert("Browse::$MenuDisplayString", 1);
      menu_insert("Browse::$MenuDisplayStringDL", 1, $URI . "&output=dltext");
    }
    else
    {
      menu_insert("Browse::$MenuDisplayString", 1, $URI, $MenuDisplayString);
      menu_insert("Browse::$MenuDisplayStringDL", 1, $URI . "&output=dltext", $MenuDisplayStringDL);
      /* bobg - This is to use a select list in the micro menu to replace the above List
        and Download, but put this select list in a form
        $LicChoices = array("Lic Download" => "Download", "Lic display" => "Display");
        $LicChoice = Array2SingleSelect($LicChoices, $SLName="LicDL");
        menu_insert("Browse::Nomos License List Download2", 1, $URI . "&output=dltext", NULL,NULL, $LicChoice);
       */
    }
  }

  /**
   * \brief This function returns the scheduler status.
   */
  function Output()
  {
    global $SysConf;
    global $PG_CONN;
    if (!$PG_CONN)
    {
      echo _("NO DB connection");
    }

    if ($this->State != PLUGIN_STATE_READY)
      return (0);
    $V = "";
    $uploadtree_pk = GetParm("item", PARM_INTEGER);
    if (empty($uploadtree_pk))
      return;

    $upload_pk = GetParm("upload", PARM_INTEGER);
    if (empty($upload_pk))
      return;
    $UploadPerm = GetUploadPerm($upload_pk);
    if ($UploadPerm < PERM_READ)
    {
      $text = _("Permission Denied");
      echo "<h2>$text<h2>";
      return;
    }

    if (GetParm("output", PARM_STRING) == 'dltext')
      $dltext = true;
    else
      $dltext = false;

    /* get last nomos agent_pk that has data for this upload */
    $Agent_name = "nomos";
    $AgentRec = AgentARSList("nomos_ars", $upload_pk, 1);
    $agent_pk = $AgentRec[0]["agent_fk"];
    if ($AgentRec === false)
    {
      echo _("No data available");
      return;
    }

    /* how many lines of data do you want to display */
    $NomostListNum = @$SysConf['SYSCONFIG']['NomostListNum'];

    /* get the top of tree */
    $sql = "SELECT upload_fk, lft, rgt from uploadtree where uploadtree_pk='$uploadtree_pk'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $toprow = pg_fetch_assoc($result);
    pg_free_result($result);

    /* loop through all the records in this tree */
    $sql = "select uploadtree_pk, ufile_name, lft, rgt from uploadtree
              where upload_fk='$toprow[upload_fk]' 
                    and lft>'$toprow[lft]'  and rgt<'$toprow[rgt]'
                    and ((ufile_mode & (1<<28)) = 0) and ((ufile_mode & (1<<29)) = 0) limit $NomostListNum";
    $outerresult = pg_query($PG_CONN, $sql);
    DBCheckResult($outerresult, $sql, __FILE__, __LINE__);

    /* Select each uploadtree row in this tree, write out text:
     * filepath : license list
     * e.g. Pound-2.4.tgz/Pound-2.4/svc.c: GPL_v3+, Indemnity
     */
    $uploadtreeTablename = GetUploadtreeTableName($toprow['upload_fk']);

    $lines = array();
    while ($row = pg_fetch_assoc($outerresult)) {
      $filepatharray = Dir2Path($row['uploadtree_pk'], $uploadtreeTablename);
      $filepath = "";
      foreach ($filepatharray as $uploadtreeRow)
      {
        if (!empty($filepath))
          $filepath .= "/";
        $filepath .= $uploadtreeRow['ufile_name'];
      }
      $lines[] = $filepath . ": " . GetFileLicenses_string($agent_pk, 0, $row['uploadtree_pk'], $uploadtreeTablename);
    }
    $RealNumber = pg_num_rows($outerresult);
    pg_free_result($outerresult);

    if ($RealNumber == $NomostListNum)
    {
      $V .= _("<br><b>Warning: Only the last $NomostListNum lines are displayed.  To see the whole list, run fo_nomos_license_list from the command line.</b><br>");
    }

    if ($dltext) {
      $request = $this->getRequest();
      $itemId = intval($request->get('item'));
      $path = Dir2Path($itemId, $uploadtreeTablename);
      $fileName = $path[count($path) - 1]['ufile_name'] . ".txt";

      $headers = array(
          "Content-Type" => "text",
          "Content-Disposition" => "attachment; filename=\"$fileName\""
      );

      $response = new Response(implode("\n", $lines), Response::HTTP_OK, $headers);
      return $response;
    } else {
      return $V;
    }
  }
}

$NewPlugin = new ui_license_list;
$NewPlugin->Initialize();
