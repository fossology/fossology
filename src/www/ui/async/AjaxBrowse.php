<?php
/***********************************************************
 * Copyright (C) 2014-2015 Siemens AG
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

namespace Fossology\UI\Ajax;

use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Util\DataTablesUtility;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AjaxBrowse extends DefaultPlugin
{
  const PRIO_COLUMN = 'priority';
  const NAME = "browse-processPost";

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
  /** @var array */
  private $statusTypes;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::PERMISSION => self::PERM_WRITE
      ));
        
    global $container;
    $this->uploadDao = $container->get('dao.upload');
    $this->userDao = $container->get('dao.user');
    $this->dbManager = $container->get('db.manager');
    $this->dataTablesUtility = $container->get('utils.data_tables_utility');
  }

  /**
   * @brief Display the loaded menu and plugins.
   */
  protected function handle(Request $request)
  {
    $gup = $this->dbManager->getSingleRow('SELECT group_perm FROM group_user_member WHERE user_fk=$1 AND group_fk=$2',
        array($_SESSION['UserId'], $_SESSION['GroupId']), __METHOD__ . '.user_perm');
    if (!$gup)
    {
      throw new \Exception('You are assigned to wrong group.');
    }
    $this->userPerm = $gup['group_perm'];

    $columnName = $request->get('columnName');
    $uploadId = intval($request->get('uploadId'));
    $statusId = intval($request->get('statusId'));
    $value = intval($request->get('value'));
    $moveUpload = intval($request->get("move"));
    $beyondUpload = intval($request->get("beyond"));
    $commentText = $request->get('commentText');
    $direction = $request->get('direction');

    if (!empty($columnName) and !empty($uploadId) and !empty($value))
    {
      $this->updateTable($columnName, $uploadId, $value);
    } else if (!empty($moveUpload) && !empty($beyondUpload))
    {
      $this->moveUploadBeyond($moveUpload, $beyondUpload);
    } else if (!empty($uploadId) && !empty($direction))
    {
      $this->moveUploadToInfinity($uploadId, $direction == 'top');
    } else if (!empty($uploadId) && !empty($commentText) && !empty($statusId))
    {
      $this->setStatusAndComment($uploadId, $statusId, $commentText);
    } else
    {
      list($aaData, $iTotalRecords, $iTotalDisplayRecords) = $this->showFolderGetTableData($request);
      return new JsonResponse(array(
              'sEcho' => intval($request->get('sEcho')),
              'aaData' => $aaData,
              'iTotalRecords' => $iTotalRecords,
              'iTotalDisplayRecords' => $iTotalDisplayRecords
          ));
    }
    return new Response('');
  }


  private function updateTable($columnName, $uploadId, $value)
  {
    if ($columnName == 'status_fk')
    {
      $this->changeStatus($uploadId, $value);
    } else if ($columnName == 'assignee' && $this->userPerm)
    {
      $sql = "update upload SET assignee=$1 where upload_pk=$2";
      $this->dbManager->getSingleRow($sql, array($value, $uploadId), $sqlLog = __METHOD__);
    } else
    {
      throw new \Exception('invalid column');
    }
  }

  private function changeStatus($uploadId, $value)
  {
    if ($value == 4 && $this->userPerm)
    {
      $this->setStatusAndComment($uploadId, $value, $commentText = '');
    } else if ($value == 4)
    {
      throw new \Exception('missing permission');
    } else if ($this->userPerm)
    {
      $sql = "update upload SET status_fk=$1 where upload_pk=$2";
      $this->dbManager->getSingleRow($sql, array($value, $uploadId), $sqlLog = __METHOD__ . '.advisor');
    } else
    {
      $sql = "update upload SET status_fk=$1 where upload_pk=$2 AND status_fk<4";
      $this->dbManager->getSingleRow($sql, array($value, $uploadId), $sqlLog = __METHOD__ . '.user');
    }
  }

  private function moveUploadBeyond($moveUpload, $beyondUpload)
  {
    $this->dbManager->begin();
    $this->dbManager->prepare($stmt = __METHOD__ . '.get.single.Upload',
        $sql='SELECT upload_pk,'.self::PRIO_COLUMN.' FROM upload WHERE upload_pk=$1');
    $movePoint = $this->dbManager->getSingleRow($sql, array($moveUpload), $stmt);
    $beyondPoint = $this->dbManager->getSingleRow($sql, array($beyondUpload), $stmt);
    if ($movePoint[self::PRIO_COLUMN] > $beyondPoint[self::PRIO_COLUMN])
    {
      $farPoint = $this->dbManager->getSingleRow("SELECT ".self::PRIO_COLUMN." FROM upload WHERE ".self::PRIO_COLUMN."<$1 ORDER BY ".self::PRIO_COLUMN." DESC LIMIT 1",
              array($beyondPoint[self::PRIO_COLUMN]), 'get.upload.with.lower.priority');
    } else
    {
      $farPoint = $this->dbManager->getSingleRow("SELECT ".self::PRIO_COLUMN." FROM upload WHERE ".self::PRIO_COLUMN.">$1 ORDER BY ".self::PRIO_COLUMN." ASC LIMIT 1",
              array($beyondPoint[self::PRIO_COLUMN]), 'get.upload.with.higher.priority');
    }
    if (false !== $farPoint)
    {
      $newPriority = ($farPoint[self::PRIO_COLUMN] + $beyondPoint[self::PRIO_COLUMN]) / 2;
    } else if ($movePoint[self::PRIO_COLUMN] > $beyondPoint[self::PRIO_COLUMN])
    {
      $newPriority = $beyondPoint[self::PRIO_COLUMN] - 0.5;
    } else
    {
      $newPriority = $beyondPoint[self::PRIO_COLUMN] + 0.5;
    }
    $this->dbManager->getSingleRow('UPDATE upload SET '.self::PRIO_COLUMN.'=$1 WHERE upload_pk=$2', array($newPriority, $moveUpload), __METHOD__.'.update.priority');
    $this->dbManager->commit();
  }

  private function showFolderGetTableData(Request $request)
  {
    /* Get list of uploads in this folder */
    list($result, $iTotalDisplayRecords, $iTotalRecords) = $this->getListOfUploadsOfFolder($request);

    $uri = Traceback_uri() . "?mod=license";
    /* Browse-Pfile menu */
    $menuPfile = menu_find("Browse-Pfile", $menuDepth);
    /* Browse-Pfile menu without the compare menu item */
    $menuPfileNoCompare = menu_remove($menuPfile, "Compare");

    $this->statusTypes = $this->uploadDao->getStatusTypeMap();
    $users = $this->userDao->getUserChoices();

    $statusTypesAvailable = $this->statusTypes;
    if (!$this->userPerm)
    {
      unset($statusTypesAvailable[4]);
    }

    $output = array();
    $rowCounter = 0;
    while ($row = $this->dbManager->fetchArray($result))
    {
      if (empty($row['upload_pk']) || (GetUploadPerm($row['upload_pk']) < PERM_READ))
      {
        continue;
      }
      $rowCounter++;
      $output[] = $this->showRow($row, $request, $uri, $menuPfile, $menuPfileNoCompare, $statusTypesAvailable, $users, $rowCounter);
    }
    $this->dbManager->freeResult($result);
    return array($output, $iTotalRecords, $iTotalDisplayRecords);
  }


  /**
   * @param array $row fetched row
   * @param Request $request
   * @param $uri
   * @param $menuPfile
   * @param $menuPfileNoCompare
   * @param array $statusTypesAvailable
   * @param array $users
   * @param string (unique)
   * @return array
   */
  private function showRow($row,Request $request, $uri, $menuPfile, $menuPfileNoCompare, $statusTypesAvailable, $users, $rowCounter)
  {
    $show = $request->get('show');
    $folder = $request->get('folder');
    
    $uploadId = intval($row['upload_pk']);
    $description = htmlentities($row['upload_desc']);

    $fileName = $row['ufile_name'];
    if (empty($fileName))
    {
      $fileName = $row['upload_filename'];
    }

    $itemId = Isartifact($row['ufile_mode']) ? DirGetNonArtifact($row['uploadtree_pk']) : $row['uploadtree_pk'];

    $nameColumn = "<b>$fileName</b>";
    if (IsContainer($row['ufile_mode']))
    {
      $nameColumn = "<a href='$uri&upload=$uploadId&folder=$folder&item=$itemId&show=$show'>$nameColumn</a>";
    }
    $nameColumn .= "<br>";
    if (!empty($description))
    {
      $nameColumn .= "<i>$description</i><br>";
    }
    $Parm = "upload=$uploadId&show=$show&item=" . $row['uploadtree_pk'];
    if (Iscontainer($row['ufile_mode']))
    {
      $nameColumn .= menu_to_1list($menuPfile, $Parm, " ", " ", 1, $uploadId);
    }
    else
    {
      $nameColumn .= menu_to_1list($menuPfileNoCompare, $Parm, " ", " ", 1, $uploadId);
    }

    /* Job queue link */
    $text = _("History");
    if (plugin_find_id('showjobs') >= 0)
    {
      $nameColumn .= "[<a href='" . Traceback_uri() . "?mod=showjobs&upload=$uploadId'>$text</a>]";
    }
    $dateCol = substr($row['upload_ts'], 0, 19);
    $pairIdPrio = array($uploadId, floatval($row[self::PRIO_COLUMN]));
    if (!$this->userPerm && 4 == $row['status_fk'])
    {
      $currentStatus = $this->statusTypes[4];
    }
    else
    {
      $statusAction = " onchange =\"changeTableEntry(this, $uploadId,'status_fk' )\" ";
      $currentStatus = $this->createSelect("Status" . $this->userPerm . "Of_$rowCounter", $statusTypesAvailable, $row['status_fk'], $statusAction);
    }
    if ($this->userPerm)
    {
      $action = " onchange =\"changeTableEntry(this, $uploadId, 'assignee')\"";
      $currentAssignee = $this->createSelectUsers("AssignedTo_$rowCounter", $users, $row['assignee'], $action );
    }
    else
    {
      $currentAssignee = array_key_exists($row['assignee'], $users) ? $users[$row['assignee']] : _('Unassigned');
    }
    $rejectableUploadId = ($this->userPerm || $row['status_fk'] < 4) ? $uploadId : 0;
    $tripleComment = array($rejectableUploadId, $row['status_fk'], htmlspecialchars($row['status_comment']));
    $output = array($nameColumn, $currentStatus, $tripleComment, $currentAssignee, $dateCol, $pairIdPrio);
    return $output;
  }
  

  /**
   * @param string $selectElementName
   * @param array $databaseMap
   * @param int $selectedValue
   * @return array
   */
  private function createSelectUsers($selectElementName, $databaseMap, $selectedValue, $action = "")
  {
    if (array_key_exists($_SESSION['UserId'], $databaseMap))
    {
      $databaseMap[$_SESSION['UserId']] = _('-- Me --');
    }
    $databaseMap[1] = _('Unassigned');
    return $this->createSelect($selectElementName,$databaseMap, $selectedValue,$action);
  }
  
  
  private function createSelect($id,$options,$select='',$action='')
  {
    $html = "<select name=\"$id\" id=\"$id\" $action>";
    foreach($options as $key=>$disp)
    {
      $html .= '<option value="'.$key.'"';
      if ($key == $select)
      {
        $html .= ' selected';
      }
      $html .= ">$disp</option>";
    }
    $html .= '</select>';
    return $html;    
  }
  

  /**
   * @param Request $request
   * @return array
   */
  private function getListOfUploadsOfFolder(Request $request)
  {
    $orderString = $this->getOrderString();
    $this->filterParams = array($folder=$request->get('folder'));
    $filter = $this->getSearchString($request->get('sSearch'));
    $filter .= $this->getIntegerFilter(intval($request->get('assigneeSelected')), 'assignee');
    $filter .= $this->getIntegerFilter(intval($request->get('statusSelected')), 'status_fk');
    $stmt = __METHOD__ . "getFolderContents" . $orderString . $filter;

    $offset = intval($request->get('iDisplayStart'));
    $limit = intval($request->get('iDisplayLength'));
    $unorderedQuery = "FROM upload
        INNER JOIN uploadtree ON upload_fk = upload_pk
        AND upload.pfile_fk = uploadtree.pfile_fk
        AND parent IS NULL
        AND lft IS NOT NULL
        WHERE upload_pk IN
        (SELECT child_id FROM foldercontents WHERE foldercontents_mode & 2 != 0 AND parent_fk = $1 ) ";

    $statementString = "SELECT upload.*,uploadtree.* $unorderedQuery $filter $orderString";
    $params = $this->filterParams;
    $params[] = $offset;
    $statementString .= ' OFFSET $' . count($params);
    $params[] = $limit;
    $statementString .= ' LIMIT $' . count($params);
    $this->dbManager->prepare($stmt, $statementString);
    $result = $this->dbManager->execute($stmt, $params);

    $iTotalDisplayRecordsRow = $this->dbManager->getSingleRow("SELECT count(*) $unorderedQuery $filter",
        $this->filterParams, __METHOD__ . ".count");
    $iTotalDisplayRecords = $iTotalDisplayRecordsRow['count'];

    $iTotalRecordsRow = $this->dbManager->getSingleRow("SELECT count(*) $unorderedQuery", array($folder), __METHOD__ . "count.all");
    $iTotalRecords = $iTotalRecordsRow['count'];
    return array($result, $iTotalDisplayRecords, $iTotalRecords);
  }


  private function getOrderString()
  {
    $columnNamesInDatabase = array('upload_filename', 'status_fk', 'UNUSED', 'assignee', 'upload_ts', 'priority');

    $orderString = $this->dataTablesUtility->getSortingString($_GET, $columnNamesInDatabase);

    return $orderString;
  }

  private function getSearchString($searchPattern)
  {
    if (empty($searchPattern))
    {
      return '';
    }
    $this->filterParams[] = "%$searchPattern%";
    return ' AND upload_filename ilike $' . count($this->filterParams) . ' ';
  }

  /**
   * @param int $var
   * @param string $columnName in database table
   */
  private function getIntegerFilter($var, $columnName)
  {
    if (empty($var))
    {
      return '';
    }
    $this->filterParams[] = $var;
    return " AND $columnName=$" . count($this->filterParams) . ' ';
  }


  private function setStatusAndComment($uploadId, $statusId, $commentText)
  {
    print_r("$statusId, $commentText, $uploadId");
    $sql = "UPDATE upload SET status_fk=$1, status_comment=$2 WHERE upload_pk=$3";
    $this->dbManager->getSingleRow($sql, array($statusId, $commentText, $uploadId), __METHOD__);
    $sel = $this->dbManager->getSingleRow("select status_comment from upload where upload_pk=$1", array($uploadId), __METHOD__ . '.question');
    print_r('#' . $sel['status_comment']);

    // do we need to log $_SESSION['UserId'] ?
  }

  public function moveUploadToInfinity($uploadId, $top)
  {
    $fun = $top ? 'MAX' : 'MIN';
    $this->dbManager->begin();
    $prioRow = $this->dbManager->getSingleRow($sql = "SELECT $fun(priority) p FROM upload", array(), ".priority.$fun");
    $newPriority = $top ? $prioRow['p'] + 1 : $prioRow['p'] - 1;
    $this->dbManager->getSingleRow('UPDATE upload SET priority=$1 WHERE upload_pk=$2', array($newPriority, $uploadId), '.update.priority' . "$newPriority,$uploadId");
    $this->dbManager->commit();
  }

}

register_plugin(new AjaxBrowse());