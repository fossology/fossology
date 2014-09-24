<?php
/***********************************************************
 * Copyright (C) 2010-2013 Hewlett-Packard Development Company, L.P.
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

define("TITLE_ui_browse", _("Browse"));

class ui_browse extends FO_Plugin
{

  function __construct()
  {
    $this->Name = "browse";
    $this->Title = TITLE_ui_browse;
    $this->MenuList = "Browse";
    $this->MenuOrder = 80; // just to right of Home(100)
    $this->MenuTarget = "";
    $this->DBaccess = PLUGIN_DB_READ;
    $this->LoginFlag = 0;

    parent::__construct();
  }

  /**
   * \brief Create and configure database tables
   */
  function Install()
  {
    global $PG_CONN;
    if (empty($PG_CONN))
    {
      return (1);
    } /* No DB */

    /****************
     * The top-level folder must exist.
     ****************/
    /* check if the table needs population */
    $sql = "SELECT * FROM folder WHERE folder_pk=1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    if ($row['folder_pk'] != "1")
    {
      $sql = "INSERT INTO folder (folder_pk,folder_name,folder_desc) VALUES (1,'Software Repository','Top Folder');";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
      $sql = "INSERT INTO foldercontents (parent_fk,foldercontents_mode,child_id) VALUES (1,0,0);";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
      /* Now fix the sequence number so the first insert does not fail */
      $sql = "SELECT max(folder_pk) AS max FROM folder LIMIT 1;";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $row = pg_fetch_assoc($result);
      pg_free_result($result);
      $Max = intval($row['max']);
      if ($Max < 1)
      {
        $Max = 1;
      } else
      {
        $Max++;
      }
      $sql = "SELECT setval('folder_folder_pk_seq',$Max);";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    }
    return (0);
  } // Install()


  /**
   * \brief Customize submenus.
   */
  function RegisterMenus()
  {
    menu_insert("Main::" . $this->MenuList, $this->MenuOrder, $this->Name, $this->Name);

    $Upload = GetParm("upload", PARM_INTEGER);
    if (empty($Upload))
    {
      return;
    }
    // For the Browse menu, permit switching between detail and simple.
    $URI = $this->Name . Traceback_parm_keep(array(
            "upload",
            "item"
        ));
    if (GetParm("mod", PARM_STRING) == $this->Name)
      menu_insert("Browse::Browse", 1);
    else
      menu_insert("Browse::Browse", 1, $URI);

    return ($this->State == PLUGIN_STATE_READY);
  } // RegisterMenus()

  /**
   * \brief Given a upload_pk, list every item in it.
   * If it is an individual file, then list the file contents.
   */
  function ShowItem($Upload, $Item, $Show, $Folder, $uploadtree_tablename)
  {
    global $PG_CONN;
    $RowStyle1 = "style='background-color:#ecfaff'";  // pale blue
    $RowStyle2 = "style='background-color:#ffffe3'";  // pale yellow
    $ColorSpanRows = 3;  // Alternate background color every $ColorSpanRows
    $RowNum = 0;

    $V = "";
    /* Use plugin "view" and "download" if they exist. */
    $Uri = Traceback_uri() . "?mod=" . $this->Name . "&folder=$Folder";

    /* there are three types of Browse-Pfile menus */
    /* menu as defined by their plugins */
    $MenuPfile = menu_find("Browse-Pfile", $MenuDepth);
    /* menu but without Compare */
    $MenuPfileNoCompare = menu_remove($MenuPfile, "Compare");
    /* menu with only Tag and Compare */
    $MenuTag = array();
    foreach ($MenuPfile as $key => $value)
    {
      if (($value->Name == 'Tag') or ($value->Name == 'Compare'))
      {
        $MenuTag[] = $value;
      }
    }

    /* Get the (non artifact) children  */
    $Results = GetNonArtifactChildren($Item, $uploadtree_tablename);
    $ShowSomething = 0;
    $V .= "<table class='text' style='border-collapse: collapse' border=0 padding=0>\n";
    foreach ($Results as $Row)
    {
      if (empty($Row['uploadtree_pk'])) continue;
      $ShowSomething = 1;
      $Link = NULL;
      $Name = $Row['ufile_name'];

      /* Set alternating row background color - repeats every $ColorSpanRows rows */
      $RowStyle = (($RowNum++ % (2 * $ColorSpanRows)) < $ColorSpanRows) ? $RowStyle1 : $RowStyle2;
      $V .= "<tr $RowStyle>";

      /* Check for children so we know if the file should by hyperlinked */
      $sql = "select uploadtree_pk from uploadtree
                where parent=$Row[uploadtree_pk] limit 1";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $HasChildren = pg_num_rows($result);
      pg_free_result($result);

      $Parm = "upload=$Upload&show=$Show&item=" . $Row['uploadtree_pk'];
      if ($HasChildren)
        $Link = $Uri . "&show=$Show&upload=$Upload&item=" . $Row['uploadtree_pk'];

      /* Show details children */
      if ($Show == 'detail')
      {
        $V .= "<td class='mono'>" . DirMode2String($Row['ufile_mode']) . "</td>";
        if (!Isdir($Row['ufile_mode']))
        {
          $V .= "<td align='right'>&nbsp;&nbsp;" . number_format($Row['pfile_size'], 0, "", ",") . "&nbsp;&nbsp;</td>";
        } else
        {
          $V .= "<td>&nbsp;</td>";
        }
      }
      /* Display item */
      $V .= "<td>";
      if (Iscontainer($Row['ufile_mode']))
      {
        $V .= "<b>";
      }
      if (!empty($Link))
      {
        $V .= "<a href='$Link'>";
      }
      $V .= $Name;
      if (Isdir($Row['ufile_mode']))
      {
        $V .= "/";
      }
      if (!empty($Link))
      {
        $V .= "</a>";
      }
      if (Iscontainer($Row['ufile_mode']))
      {
        $V .= "</b>";
      }
      $V .= "</td>\n";

      if (!Iscontainer($Row['ufile_mode']))
        $V .= menu_to_1list($MenuPfileNoCompare, $Parm, "<td>", "</td>\n", 1, $Upload);
      else if (!Isdir($Row['ufile_mode']))
        $V .= menu_to_1list($MenuPfile, $Parm, "<td>", "</td>\n", 1, $Upload);
      else
        $V .= menu_to_1list($MenuTag, $Parm, "<td>", "</td>\n", 1, $Upload);

      $V .= "</td>";
    } /* foreach($Results as $Row) */
    $V .= "</table>\n";
    if (!$ShowSomething)
    {
      $text = _("No files");
      $V .= "<b>$text</b>\n";
    } else
    {
      $V .= "<hr>\n";
      if (count($Results) == 1)
      {
        $text = _("1 item");
        $V .= "$text\n";
      } else
      {
        $text = _("items");
        $V .= count($Results) . " $text\n";
      }
    }
    return ($V);
  } // ShowItem()

  /**
   * \brief Given a upload_pk, list every item in it.
   * If it is an individual file, then list the file contents.
   */
  function ShowFolder($Folder, $Show)
  {
    $V = "<table border=1 width='100%'>";
    $V .= "<tr><td valign='top' width='20%'>\n";
    $V .= FolderListScript();
    $text = _("Folder Navigation");
    $V .= "<div align='center'><H3>$text</H3></div>\n";
    $V .= "<div align='center'><small>";
    if ($Folder != GetUserRootFolder())
    {
      $text = _("Top");
      $V .= "<a href='" . Traceback_uri() . "?mod=" . $this->Name . "'>$text</a> |";
    }
    $text = _("Expand");
    $V .= "<a href='javascript:Expand();'>$text</a> |";
    $text = _("Collapse");
    $V .= "<a href='javascript:Collapse();'>$text</a> |";
    $text = _("Refresh");
    $V .= "<a href='" . Traceback() . "'>$text</a>";
    $V .= "</small></div>";
    $V .= "<P>\n";
    $V .= "<form>\n";
    $V .= FolderListDiv($Folder, 0, $Folder, 1);
    $V .= "</form>\n";
    $V .= "</td><td valign='top'>\n";
    $text = _("Uploads");
    $V .= "<div align='center'><H3>$text</H3></div>\n";

    global $container;
    $renderer = $container->get('renderer');
    $userDao = $container->get('dao.user');
    $assigneeArray = $userDao->getUserChoices();
    $assigneeArray[$_SESSION['UserId']] = _('-- Me --');
    $assigneeArray[1] = _('Unassigned');
    $assigneeArray[0] = '';
    $assigneeFilter = $renderer->createSelect('assigneeSelector', $assigneeArray, 0, ' onchange="filterAssignee()"');
    $statusArray = array(0 => '');
    $dbManager = $container->get('db.manager');
    $dbManager->prepare($stmt = __METHOD__ . ".status", 'SELECT status_pk,meaning FROM upload_status ORDER BY status_pk');
    $res = $dbManager->execute($stmt);
    while ($row = $dbManager->fetchArray($res))
    {
      $statusArray[$row['status_pk']] = $row['meaning'];
    }
    $dbManager->freeResult($res);
    $statusFilter = $renderer->createSelect('statusSelector', $statusArray, 0, ' onchange="filterStatus()"');

    $V .= "<table class='semibordered' id='browsetbl' width='100%' cellpadding=0>"
        . "<thead><tr><th></th> <th>$statusFilter</th> <th></th> <th>$assigneeFilter</th> <th></th> <th></th> </tr>"
        . "<tr><th></th> <th></th> <th></th> <th></th> <th></th> <th></th> </tr></thead>"
        . "<tbody></tbody>" . "<tfoot></tfoot>"
        . "</table>";
    $V .= "</table>";

    $V .= $this->showFolderCreateFileTable($Folder, $Show);

    return $V;
  }

  /**
   * \brief This function returns the output html
   */
  function Output()
  {
    global $PG_CONN;

    if ($this->State != PLUGIN_STATE_READY)
    {
      return 0;
    }

    $V = "";
    $folder_pk = GetParm("folder", PARM_INTEGER);
    $Upload = GetParm("upload", PARM_INTEGER);  // upload_pk to browse
    $Item = GetParm("item", PARM_INTEGER);  // uploadtree_pk to browse

    /* check permission if $Upload is given */
    if (!empty($Upload))
    {
      $UploadPerm = GetUploadPerm($Upload);
      if ($UploadPerm < PERM_READ)
      {
        $text = _("Permission Denied");
        echo "<h2>$text</h2>";
        return "";
      }
    }

    /* kludge for plugins not supplying a folder parameter.
     * Find what folder this upload is in.  Error if in multiple folders.
     */
    if (empty($folder_pk))
    {
      if (empty($Upload))
        $folder_pk = GetUserRootFolder();
      else
      {
        /* Make sure the upload record exists */
        $sql = "select upload_pk from upload where upload_pk=$Upload";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        if (pg_num_rows($result) < 1)
        {
          echo "This upload no longer exists on this system.";
          return "";
        }

        $sql = "select parent_fk from foldercontents where child_id=$Upload and foldercontents_mode=2";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        if (pg_num_rows($result) > 1)
          Fatal("Upload $Upload found in multiple folders.", __FILE__, __LINE__);
        if (pg_num_rows($result) < 1)
          Fatal("Upload $Upload missing from foldercontents.", __FILE__, __LINE__);

        $row = pg_fetch_assoc($result);
        $folder_pk = $row['parent_fk'];
        pg_free_result($result);
      }
    }

    $V .= $this->outputItemHtml($Item, $folder_pk, $Upload);
    $this->vars['content'] = $V;
  }

  function outputItemHtml($uploadTreeId, $Folder, $Upload)
  {
    global $container;
    $dbManager = $container->get('db.manager');
    $show = 'detail';
    $html = '';
    $uploadtree_tablename = "";
    if (!empty($uploadTreeId))
    {
      $sql = "SELECT ufile_mode, upload_fk FROM uploadtree WHERE uploadtree_pk = $1";
      $row = $dbManager->getSingleRow($sql, array($uploadTreeId));
      $Upload = $row['upload_fk'];
      $UploadPerm = GetUploadPerm($Upload);
      if ($UploadPerm < PERM_READ)
      {
        $text = _("Permission Denied");
        echo "<h2>$text</h2>";
        return "";
      }

      if (!Iscontainer($row['ufile_mode']))
      {
        global $Plugins;
        $View = &$Plugins[plugin_find_id("view")];
        if (!empty($View))
        {
          /** @var ui_view $View */
          return ($View->ShowView(NULL, "browse"));
        }
      }
      $uploadtree_tablename = GetUploadtreeTableName($row['upload_fk']);
      $html .= Dir2Browse($this->Name, $uploadTreeId, NULL, 1, "Browse", -1, '', '', $uploadtree_tablename) . "\n";
    } else if (!empty($Upload))
    {
      $uploadtree_tablename = GetUploadtreeTableName($Upload);
      $html .= Dir2BrowseUpload($this->Name, $Upload, NULL, 1, "Browse", $uploadtree_tablename) . "\n";
    }

    if (!empty($Upload))
    {
      if (empty($uploadTreeId))
      {
        $dbManager->prepare($stmt = __METHOD__ . ".getTreeRoot",
            $sql = "select uploadtree_pk from uploadtree where parent is NULL and upload_fk=$1");
        $result = $dbManager->execute($stmt, array($Upload));
        if (pg_num_rows($result))
        {
          $row = $dbManager->fetchArray($result);
          $uploadTreeId = $row['uploadtree_pk'];
        } else
        {
          $text = _("Missing upload tree parent for upload");
          $html .= "<hr><h2>$text $Upload</h2><hr>";
          $dbManager->freeResult($result);
          return $html;
        }
        $dbManager->freeResult($result);
      }
      $html .= $this->ShowItem($Upload, $uploadTreeId, $show, $Folder, $uploadtree_tablename);
    } else
    {
      $html .= $this->createJavaScriptBlock();
      $html .= $this->ShowFolder($Folder, $show);
    }
    return "<font class='text'>\n$html</font>\n" . $this->rejectModal();
  }


  private function rejectModal()
  {
    $output2 = "<div>" . _('Please enter a reason for status change.') . ":</div>
              <textarea id='commentText' style='overflow:auto;resize:none;width:100%;height:80px;' name='commentText'></textarea></br>
              [<a class='button' onclick='submitComment()'>OK</a>]   &nbsp;&nbsp;&nbsp;
              [<a class='button' onclick='closeCommentModal()'>Cancel</a>] ";

    $output1 = "<form name=\"rejector\">$output2</form>\n";
    $output = "<div class=\"modal\" id=\"commentModal\" hidden>$output1</div>";
    return $output;
  }


  private function showFolderCreateFileTable($Folder, $Show)
  {
    $tableColumns = '[
      { "sTitle" : "' . _("Upload Name and Description") . '", "sClass": "left" },
      { "sTitle" : "' . _("Status") . '", "sClass": "center" , "bSearchable": false},
      { "sTitle" : "' . _("Comment") . '", "sClass": "center", "bSortable": false, "bSearchable": false,
                      "mRender": function ( source, type, val ) { return commentColumn( source, type, val );}
                  },
      { "sTitle" : "' . _("Assigned to") . '", "sClass": "center" , "bSearchable": false},
      { "sTitle" : "' . _("Upload Date") . '", "sClass": "center" , "sType": "string", "bSearchable": false},
      { "sTitle" : "' . _("Priority") . '", "sClass": "center priobucket", "bSearchable": false,
                      "mRender": function ( source, type, val ) { return prioColumn( source, type, val ); }
                  }
    ]';

    $tableSorting = json_encode($this->returnSortOrder());
//    $tableLanguage = '[
//        { "sInfo" : "Showing _START_ to _END_ of _TOTAL_ files" },
//        { "sSearch" : "Search _INPUT_ in filenames" },
//        { "sLengthMenu" : "Display <select><option value=\"10\">10</option><option value=\"25\">25</option><option value=\"50\">50</option><option value=\"100\">100</option></select> files" }
//    ]';


    $dataTableConfig =
        '{  "bServerSide": true,
       "sAjaxSource": "?mod=browse-processPost",
       "fnServerData": function ( sSource, aoData, fnCallback ) {
            aoData.push( { "name":"folder" , "value" : "' . $Folder . '" } );
            aoData.push( { "name":"show" , "value" : "' . $Show . '" } );
            aoData.push( { "name":"assigneeSelected" , "value" : assigneeSelected } );
            aoData.push( { "name":"statusSelected" , "value" : statusSelected } );
            $.getJSON( sSource, aoData, fnCallback ).fail( function() {
              if (confirm("You are not logged in. Go to login page?"))
                window.location.href="' . Traceback_uri() . '?mod=auth";
            });
          },
      "aoColumns": ' . $tableColumns . ',
      "aaSorting": ' . $tableSorting . ',
      "iDisplayLength": 50,
      "bProcessing": true,
      "bStateSave": true,
      "bRetrieve": true
    }';

    $VF = "<script>
              function createBrowseTable() {
                    var otable = $('#browsetbl').dataTable(" . $dataTableConfig . ");
                    return otable;
                }
            </script>";

    return $VF;

  }

  private function createJavaScriptBlock()
  {
    $this->vars['scripts'] =
        "<script src=\"scripts/jquery.dataTables.js\" type=\"text/javascript\"></script>
         <script src=\"scripts/browse.js\" type=\"text/javascript\"></script>
         <script src=\"scripts/jquery.plainmodal.min.js\" type=\"text/javascript\"></script>";
  }


  static public function returnSortOrder()
  {
    $defaultOrder = array(
        array(5, "desc"),
        array(0, "asc"),
        array(3, "desc"),
        array(1, "desc"),
        array(4, "desc")
    );
    return $defaultOrder;
  }

}

$NewPlugin = new ui_browse();
$NewPlugin->Install();
