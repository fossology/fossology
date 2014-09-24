<?php
/***********************************************************
 * Copyright (C) 2014 Siemens AG
 * Author: J.Najjar, S. Weber
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

use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Util\DataTablesUtility;
use Fossology\Lib\View\Renderer;

define("TITLE_browseProcessPost", _("Private: Browse post"));

class browseProcessPost extends FO_Plugin
{
  /** @var  UploadDao $uploadDao */
  private $uploadDao;
  /** @var  UserDao $userDao */
  private $userDao;
  /** @var  DbManager dbManager */
  private $dbManager;
  /** @var DataTablesUtility $dataTablesUtility */
  private $dataTablesUtility;
  /** @var array */
  private $filterParams;
  /** @var int */
  private $userPerm;
  /** @var Renderer */
  private $renderer;
  /** @var array */
  private $statusTypes;

  function __construct()
  {
    $this->Name = "browse-processPost";
    $this->Title = TITLE_browseProcessPost;
    $this->Version = "1.0";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->NoHTML = 1;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;

    parent::__construct();
    global $container;
    $this->uploadDao = $container->get('dao.upload');
    $this->userDao = $container->get('dao.user');
    $this->dbManager = $container->get('db.manager');
    $this->dataTablesUtility = $container->get('utils.data_tables_utility');
    $this->renderer = $container->get('renderer');
  }

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    
    $gup = $this->dbManager->getSingleRow('SELECT group_perm FROM group_user_member WHERE user_fk=$1 AND group_fk=$2',
            array($_SESSION['UserId'],$_SESSION['GroupId']),$logNote=__METHOD__.'.user_perm');
    if($gup===false){
      throw new Exception('You are assigned to wrong group.');
    }
    $this->userPerm = $gup['group_perm'];

    $columnName = GetParm('columnName', PARM_STRING);
    $uploadId = GetParm('uploadId', PARM_INTEGER);
    $statusId = GetParm('statusId', PARM_INTEGER);
    $value = GetParm('value', PARM_INTEGER);
    $moveUpload = GetParm("move", PARM_INTEGER);
    $beyondUpload = GetParm("beyond", PARM_INTEGER);
    $commentText = GetParm('commentText', PARM_STRING);
    $direction = GetParm('direction', PARM_STRING);
    
    if(!empty($columnName) and !empty($uploadId) and !empty($value)) {
      $this->updateTable($columnName,$uploadId,$value);
    }
    else if (!empty($moveUpload) && !empty($beyondUpload))
    {
      $this->moveUploadBeyond($moveUpload, $beyondUpload);
    }
    else if(!empty($uploadId) && !empty($direction)) {
      $this->moveUploadToInfinity($uploadId,$direction=='top');
    }
    else if(!empty($uploadId) && !empty($commentText) && !empty($statusId)) {
      $this->setStatusAndComment($uploadId,$statusId,$commentText);
    }
    else {
      $folder = GetParm('folder', PARM_STRING);
      $show = GetParm('show', PARM_STRING);
      header('Content-type: text/json');
      list($aaData, $iTotalRecords, $iTotalDisplayRecords) = $this->ShowFolderGetTableData($folder , $show);
      return json_encode(array(
              'sEcho' => intval($_GET['sEcho']),
              'aaData' =>$aaData,
              'iTotalRecords' =>$iTotalRecords,
              'iTotalDisplayRecords' => $iTotalDisplayRecords
          )
      );
    }
  }


  private function updateTable($columnName, $uploadId, $value)
  {
    if($columnName=='status_fk')
    {
      $this->changeStatus($uploadId, $value);
    }
    else if ($columnName=='assignee' && $this->userPerm)
    {
      $sql = "update upload SET assignee=$1 where upload_pk=$2";
      $this->dbManager->getSingleRow($sql,array($value, $uploadId),$sqlLog=__METHOD__);
    }
    else
    {
      throw new Exception('invalid column');
    }
  }

  private function changeStatus($uploadId, $value){
    if($value==4 && $this->userPerm)
    {
      $this->setStatusAndComment($uploadId, $value, $commentText = '');
    }
    else if($value==4) {
      throw new Exception('missing permission');
    }
    else if ($this->userPerm)
    {
      $sql = "update upload SET status_fk=$1 where upload_pk=$2";
      $this->dbManager->getSingleRow($sql,array($value, $uploadId),$sqlLog=__METHOD__.'.advisor');
    }
    else
    {
      $sql = "update upload SET status_fk=$1 where upload_pk=$2 AND status_fk<4";
      $this->dbManager->getSingleRow($sql,array($value, $uploadId),$sqlLog=__METHOD__.'.user');
    }
  }
  
  private function moveUploadBeyond($moveUpload, $beyondUpload)
  {
    $this->dbManager->begin();
    $this->dbManager->prepare($stmt=__METHOD__.'.get.single.Upload',
      $sql='SELECT upload_pk,priority FROM upload WHERE upload_pk=$1');
    $movePoint = $this->dbManager->getSingleRow($sql,array($moveUpload),$stmt);
    $beyondPoint = $this->dbManager->getSingleRow($sql,array($beyondUpload),$stmt);
    if ($movePoint['priority'] > $beyondPoint['priority'])
    {
      $farPoint = $this->dbManager->getSingleRow("SELECT priority FROM upload WHERE priority<$1 ORDER BY priority DESC LIMIT 1", array($beyondPoint['priority']), 'get.upload.with.lower.priority');
    }
    else
    {
      $farPoint = $this->dbManager->getSingleRow("SELECT priority FROM upload WHERE priority>$1 ORDER BY priority ASC LIMIT 1", array($beyondPoint['priority']), 'get.upload.with.higher.priority');
    }
    if (false !== $farPoint)
    {
      $newPriority = ($farPoint['priority'] + $beyondPoint['priority'] )/2;
    }
    else if ($movePoint['priority'] > $beyondPoint['priority'])
    {
      $newPriority = $beyondPoint['priority'] - 0.5;
    }
    else
    {
      $newPriority = $beyondPoint['priority'] + 0.5;
    }
    $this->dbManager->getSingleRow('UPDATE upload SET priority=$1 WHERE upload_pk=$2',array($newPriority,$moveUpload),'update.priority');
    $this->dbManager->commit();
  }

  private function ShowFolderGetTableData($Folder, $Show)
  {
    /* Get list of uploads in this folder */
    list($result, $iTotalDisplayRecords, $iTotalRecords) = $this->getListOfUploadsOfFolder($Folder);

    $Uri = Traceback_uri() . "?mod=browse";

    /* Browse-Pfile menu */
    $MenuPfile = menu_find("Browse-Pfile", $MenuDepth);

    /* Browse-Pfile menu without the compare menu item */
    $MenuPfileNoCompare = menu_remove($MenuPfile, "Compare");

    $this->statusTypes = $this->uploadDao->getStatusTypeMap();
    $users = $this->userDao->getUserChoices();

    $statusTypesAvailable = $this->statusTypes;
    if(!$this->userPerm) {
      unset($statusTypesAvailable[4]);
    }
      
    $output = array();
    $rowCounter = 0;
    while ($Row = $this->dbManager->fetchArray($result))
    {
      if (empty($Row['upload_pk']) || (GetUploadPerm($Row['upload_pk']) < PERM_READ))
      {
        continue;
      }
      $rowCounter++;
      $output[] = $this->showRow($Row, $Folder, $Show, $Uri, $MenuPfile, $MenuPfileNoCompare, $statusTypesAvailable, $users, $rowCounter);
    }
    $this->dbManager->freeResult($result);
    return array($output, $iTotalRecords, $iTotalDisplayRecords);
  }


  /**
   * @param array $Row fetched row
   * @param $Folder
   * @param $Show
   * @param $Uri
   * @param $MenuPfile
   * @param $MenuPfileNoCompare
   * @param array $statusTypesAvailable
   * @param array $users
   * @param string (unique)
   * @return array
   */
  private function showRow($Row, $Folder, $Show, $Uri, $MenuPfile, $MenuPfileNoCompare, $statusTypesAvailable, $users, $rowCounter)
  {
    $uploadPk = intval($Row['upload_pk']);
    $Desc = htmlentities($Row['upload_desc']);

    $Name = $Row['ufile_name'];
    if (empty($Name))
    {
      $Name = $Row['upload_filename'];
    }

    /* If UploadtreePk is not an artifact, then use it as the root.
     Else get the first non artifact under it.
     */
    if (Isartifact($Row['ufile_mode']))
      $UploadtreePk = DirGetNonArtifact($Row['uploadtree_pk']);
    else
      $UploadtreePk = $Row['uploadtree_pk'];

    $nameColumn = "<b>$Name</b>";
    if (IsContainer($Row['ufile_mode']))
    {
      $nameColumn = "<a href='$Uri&upload=$uploadPk&folder=$Folder&item=$UploadtreePk&show=$Show'>$nameColumn</a>";
    }
    $nameColumn .= "<br>";
    if (!empty($Desc))
    {
      $nameColumn .= "<i>$Desc</i><br>";
    }
    $Upload = $Row['upload_pk'];
    $Parm = "upload=$Upload&show=$Show&item=" . $Row['uploadtree_pk'];
    if (Iscontainer($Row['ufile_mode']))
      $nameColumn .= menu_to_1list($MenuPfile, $Parm, " ", " ", 1, $uploadPk);
    else
      $nameColumn .= menu_to_1list($MenuPfileNoCompare, $Parm, " ", " ", 1, $uploadPk);

    /* Job queue link */
    $text = _("History");
    if (plugin_find_id('showjobs') >= 0)
    {
      $nameColumn .= "[<a href='" . Traceback_uri() . "?mod=showjobs&upload=$uploadPk'>$text</a>]";
    }
    $dateCol = substr($Row['upload_ts'], 0, 19);
    $pairIdPrio = array($uploadPk, floatval($Row['priority']));
    if (!$this->userPerm && 4==$Row['status_fk'])
    {
      $currentStatus = $this->statusTypes[4];
    }
    else {
      $statusAction = " onchange =\"changeTableEntry(this, $uploadPk,'status_fk' )\" ";
      $currentStatus = $this->renderer->createSelect("Status".$this->userPerm."Of_$rowCounter",$statusTypesAvailable,$Row['status_fk'],$statusAction);
    }
    if ($this->userPerm)
    {
      $currentAssignee = $this->userDao->createSelectUsers("AssignedTo_$rowCounter", $users, $Row['assignee'], "changeTableEntry", $uploadPk . ", 'assignee'");
    } else
    {
      $currentAssignee = array_key_exists($Row['assignee'], $users) ? $users[$Row['assignee']] : _('Unassigned');
    }
    $rejectableUploadId = ($this->userPerm || $Row['status_fk'] < 4) ? $uploadPk : 0;
    $tripleComment = array($rejectableUploadId, $Row['status_fk'], htmlspecialchars($Row['status_comment']));
    $output = array($nameColumn, $currentStatus, $tripleComment, $currentAssignee, $dateCol, $pairIdPrio);
    return $output;
  }

  /**
   * @param $Folder
   * @return array
   */
  private function getListOfUploadsOfFolder($Folder)
  {
    $orderString = $this->getOrderString();
    $this->filterParams = array($Folder);
    $filter = $this->getSearchString();
    $filter .= $this->getIntegerFilter('assigneeSelected','assignee');
    $filter .= $this->getIntegerFilter('statusSelected','status_fk');
    $stmt = __METHOD__ . "getFolderContents" . $orderString. $filter;

    $offset = GetParm('iDisplayStart',PARM_INTEGER);
    $limit = GetParm('iDisplayLength',PARM_INTEGER);
    $unorderedQuery = "FROM upload
        INNER JOIN uploadtree ON upload_fk = upload_pk
        AND upload.pfile_fk = uploadtree.pfile_fk
        AND parent IS NULL
        AND lft IS NOT NULL
        WHERE upload_pk IN
        (SELECT child_id FROM foldercontents WHERE foldercontents_mode & 2 != 0 AND parent_fk = $1 ) ";

    $statementString =  "SELECT upload.*,uploadtree.* $unorderedQuery $filter $orderString";
    $params = $this->filterParams;
    $params[] = $offset;
    $statementString .= ' OFFSET $'.count($params);
    $params[] = $limit;
    $statementString .= ' LIMIT $'.count($params);
    $this->dbManager->prepare($stmt, $statementString);
    $result = $this->dbManager->execute($stmt, $params);

    $iTotalDisplayRecordsRow = $this->dbManager->getSingleRow("SELECT count(*) $unorderedQuery $filter",
            $this->filterParams, __METHOD__ . ".count");
    $iTotalDisplayRecords = $iTotalDisplayRecordsRow['count'];

    $iTotalRecordsRow = $this->dbManager->getSingleRow("SELECT count(*) $unorderedQuery", array($Folder), __METHOD__ . "count.all");
    $iTotalRecords = $iTotalRecordsRow['count'];
    return array($result, $iTotalDisplayRecords, $iTotalRecords);
  }


  private function getOrderString(){

    $columnNamesInDatabase=array('upload_filename', 'status_fk', 'UNUSED', 'assignee','upload_ts' ,'priority');

    $defaultOrder = ui_browse::returnSortOrder();

    $orderString = $this->dataTablesUtility->getSortingString($_GET,$columnNamesInDatabase, $defaultOrder);

    return $orderString;
  }

  private function getSearchString()
  {
    $searchPattern = GetParm('sSearch', PARM_STRING);
    if (empty($searchPattern))
    {
      return '';
    }
    $this->filterParams[] = "%$searchPattern%";
    return ' AND upload_filename ilike $'.count($this->filterParams).' ';
  }

  /**
   * @param string $inputName in input
   * @param string $columnName in database table
   */
  private function getIntegerFilter($inputName,$columnName)
  {
    $var = GetParm($inputName, PARM_INTEGER);
    if (empty($var))
    {
      return '';
    }
    $this->filterParams[] = $var;
    return " AND $columnName=$". count($this->filterParams).' ';
  }

  
  private function setStatusAndComment($uploadId, $statusId, $commentText)
  {
    print_r("$statusId, $commentText, $uploadId");
    $sql = "UPDATE upload SET status_fk=$1, status_comment=$2 WHERE upload_pk=$3";
    $this->dbManager->getSingleRow($sql,array($statusId, $commentText, $uploadId),$stmt=__METHOD__);
    $sel = $this->dbManager->getSingleRow("select status_comment from upload where upload_pk=$1",array($uploadId),$stmt=__METHOD__.'.question');
    print_r('#'.$sel['status_comment']);
    
    // do we need to log $_SESSION['UserId'] ?
  }

  public function moveUploadToInfinity($uploadId, $top)
  {
    $fun = $top ? 'MAX' : 'MIN';
    $this->dbManager->begin();
    $prioRow = $this->dbManager->getSingleRow($sql="SELECT $fun(priority) p FROM upload",array(),"priority.$fun");
    $newPriority = $top ? $prioRow['p']+1 : $prioRow['p']-1;
    $this->dbManager->getSingleRow('UPDATE upload SET priority=$1 WHERE upload_pk=$2',array($newPriority,$uploadId),'update.priority'."$newPriority,$uploadId");
    $this->dbManager->commit();
  }

}

$NewPlugin = new browseProcessPost;
$NewPlugin->Initialize();