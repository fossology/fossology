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

use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;

define("TITLE_ui_browse", _("Browse"));

class ui_browse extends FO_Plugin
{
  /** @var UploadDao */
  private $uploadDao;
  /** @var FolderDao */
  private $folderDao;

  function __construct()
  {
    $this->Name = "browse";
    $this->Title = TITLE_ui_browse;
    $this->MenuList = "Browse";
    $this->MenuOrder = 80; // just to right of Home(100)
    $this->MenuTarget = "";
    $this->DBaccess = PLUGIN_DB_READ;
    $this->LoginFlag = 0;

    global $container;
    $this->uploadDao = $container->get('dao.upload');
    $this->folderDao = $container->get('dao.folder');

    parent::__construct();
  }

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
    $URI = $this->Name . Traceback_parm_keep(array("upload","item"));
    if (GetParm("mod", PARM_STRING) == $this->Name)
    {
      menu_insert("Browse::Browse", 1);
    }
    else
    {
      menu_insert("Browse::Browse", 1, $URI);
    }
    return ($this->State == PLUGIN_STATE_READY);
  }

  /**
   * \brief Given a upload_pk, list every item in it.
   * If it is an individual file, then list the file contents.
   */
  function ShowItem($Upload, $Item, $Show, $Folder, $uploadtree_tablename)
  {
    global $container;
    /** @var DbManager */
    $dbManager = $container->get('db.manager');
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
    foreach ($MenuPfile as $value)
    {
      if (($value->Name == 'Tag') or ($value->Name == 'Compare'))
      {
        $MenuTag[] = $value;
      }
    }

    $Results = GetNonArtifactChildren($Item, $uploadtree_tablename);
    $ShowSomething = 0;
    $V .= "<table class='text' style='border-collapse: collapse' border=0 padding=0>\n";
    $stmtGetFirstChild = __METHOD__.'.getFirstChild';
    $dbManager->prepare($stmtGetFirstChild,'SELECT uploadtree_pk FROM uploadtree WHERE parent=$1 limit 1');
    foreach ($Results as $Row)
    {
      if (empty($Row['uploadtree_pk'])) continue;
      $ShowSomething = 1;
      $Name = $Row['ufile_name'];

      /* Set alternating row background color - repeats every $ColorSpanRows rows */
      $RowStyle = (($RowNum++ % (2 * $ColorSpanRows)) < $ColorSpanRows) ? $RowStyle1 : $RowStyle2;
      $V .= "<tr $RowStyle>";

      /* Check for children so we know if the file should by hyperlinked */
      $result = $dbManager->execute($stmtGetFirstChild,array($Row['uploadtree_pk']));
      $HasChildren = $dbManager->fetchArray($result);
      $dbManager->freeResult($result);

      $Parm = "upload=$Upload&show=$Show&item=" . $Row['uploadtree_pk'];
      $Link = $HasChildren ? "$Uri&show=$Show&upload=$Upload&item=$Row[uploadtree_pk]" : NULL;

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
  }

  /**
   * @brief Given a upload_pk, list every item in it.
   * If it is an individual file, then list the file contents.
   */
  private function ShowFolder($Folder, $Show)
  {
    $V = "<div align='center'><small>";
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
    $this->vars['folderNav'] = $V;

    $assigneeArray = $this->getAssigneeArray();
    $this->vars['assigneeOptions'] = $assigneeArray;
    $this->vars['statusOptions'] = $this->uploadDao->getStatusTypeMap();
    $this->vars['folder'] = $Folder;
    $this->vars['show'] = $Show;
    return '';
  }

  /**
   * \brief This function returns the output html
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY)
    {
      return 0;
    }
    $this->folderDao->ensureTopLevelFolder();

    $folder_pk = GetParm("folder", PARM_INTEGER);
    $Upload = GetParm("upload", PARM_INTEGER);  // upload_pk to browse
    $Item = GetParm("item", PARM_INTEGER);  // uploadtree_pk to browse

    /* check permission if $Upload is given */
    if (!empty($Upload))
    {
      $UploadPerm = GetUploadPerm($Upload);
      if ($UploadPerm < PERM_READ)
      {
        $this->vars['message'] = _("Permission Denied");
        return $this->render('include/base.html.twig');
      }
    }

    if (empty($folder_pk))
    {
      try {
        $folder_pk = $this->getFolderId($Upload);
      }
      catch (Exception $exc) {
        return $exc->getMessage();
      }
    }

    $this->vars['content'] = $this->outputItemHtml($Item, $folder_pk, $Upload);
    return $this->render('ui-browse.html.twig');
  }

  /**
   * @brief kludge for plugins not supplying a folder parameter.
   * Find what folder this upload is in.  Error if in multiple folders.
   */
  private function getFolderId($uploadId)
  {
    if (empty($uploadId))
    {
      return GetUserRootFolder();
    }
    global $container;
    /** @var Fossology\Lib\Db\DbManager */
    $dbManager = $container->get('db.manager');
    $uploadExists = $dbManager->getSingleRow("SELECT count(*) cnt FROM upload WHERE upload_pk=$1",array($uploadId));
    if ($uploadExists['cnt']< 1)
    {
      throw new \Exception("This upload no longer exists on this system.");
    }
    $dbManager->prepare($stmt=__METHOD__.'.parent',
           $sql = "select parent_fk from foldercontents where child_id=$1 and foldercontents_mode=$2");
    $result = $dbManager->execute($stmt,array($uploadId,2));
    $allParents = $dbManager->fetchAll($result);
    $dbManager->freeResult($result);
    if (count($allParents) > 1)
    {
      Fatal("Upload $uploadId found in multiple folders.", __FILE__, __LINE__);
    }
    if (count($allParents) < 1)
    {
      Fatal("Upload $uploadId missing from foldercontents.", __FILE__, __LINE__);
    }
    return $allParents[0]['parent_fk'];
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
        $this->vars['message'] = _("Permission Denied");
        echo $this->render('include/base.html.twig');
        exit;
      }

      if (!Iscontainer($row['ufile_mode']))
      {
        global $Plugins;
        $View = &$Plugins[plugin_find_id("view")];
        if (!empty($View))
        {
          $this->vars['content'] = $View->ShowView(NULL, "browse");
          echo $this->render('include/base.html.twig');
          exit;
        }
      }
      $uploadtree_tablename = GetUploadtreeTableName($row['upload_fk']);
      $html .= Dir2Browse($this->Name, $uploadTreeId, NULL, 1, "Browse", -1, '', '', $uploadtree_tablename) . "\n";
    }
    else if (!empty($Upload))
    {
      $uploadtree_tablename = GetUploadtreeTableName($Upload);
      $html .= Dir2BrowseUpload($this->Name, $Upload, NULL, 1, "Browse", $uploadtree_tablename) . "\n";
    }

    if (empty($Upload))
    {
      $html .= $this->ShowFolder($Folder, $show);
    }
    else {
      if (empty($uploadTreeId))
      {
        $row = $dbManager->getSingleRow(
            $sql = "select uploadtree_pk from uploadtree where parent is NULL and upload_fk=$1", array($Upload),
            $sqlLog=__METHOD__.".getTreeRoot");
        if ($row)
        {
          $uploadTreeId = $row['uploadtree_pk'];
        } else
        {
          $this->vars['message'] = _("Missing upload tree parent for upload");
          echo $this->render('include/base.html.twig');
          exit;
        }
      }
      $html .= $this->ShowItem($Upload, $uploadTreeId, $show, $Folder, $uploadtree_tablename);
      $this->vars['content'] = $html;
      echo $this->render('include/base.html.twig');
      exit;
    }
    return $html;
  }

  /**
   * @return array
   */
  private function getAssigneeArray()
  {
    global $container;
    /** @var UserDao $userDao */
    $userDao = $container->get('dao.user');
    $assigneeArray = $userDao->getUserChoices();
    $assigneeArray[$_SESSION['UserId']] = _('-- Me --');
    $assigneeArray[1] = _('Unassigned');
    $assigneeArray[0] = '';
    return $assigneeArray;
  }
}

$NewPlugin = new ui_browse();
$NewPlugin->Install();
