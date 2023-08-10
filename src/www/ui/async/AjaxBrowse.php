<?php
/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG
 Author: J.Najjar, S. Weber

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\UI\Ajax;

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Proxy\UploadBrowseProxy;
use Fossology\Lib\UI\MenuHook;
use Fossology\Lib\UI\MenuRenderer;
use Fossology\Lib\Util\DataTablesUtility;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AjaxBrowse extends DefaultPlugin
{
  const NAME = "browse-processPost";

  /** @var UploadDao $uploadDao */
  private $uploadDao;
  /** @var UserDao $userDao */
  private $userDao;
  /** @var DbManager dbManager */
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
      self::REQUIRES_LOGIN => false,
      self::PERMISSION => Auth::PERM_READ
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
    $groupId = Auth::getGroupId();
    $gup = $this->dbManager->getSingleRow('SELECT group_perm FROM group_user_member WHERE user_fk=$1 AND group_fk=$2',
        array(Auth::getUserId(), $groupId), __METHOD__ . '.user_perm');
    if (!$gup) {
      throw new \Exception('You are assigned to wrong group.');
    }
    $this->userPerm = $gup['group_perm'];

    $uploadId = intval($request->get('uploadId'));
    if ($uploadId && !$this->uploadDao->isAccessible($uploadId, $groupId)) {
      throw new \Exception('You cannot access to this upload');
    }

    $columnName = $request->get('columnName');
    $statusId = intval($request->get('statusId'));
    $value = intval($request->get('value'));
    $moveUpload = intval($request->get("move"));
    $beyondUpload = intval($request->get("beyond"));
    $commentText = $request->get('commentText');
    $direction = $request->get('direction');

    if (! empty($columnName) && ! empty($uploadId) && ! empty($value)) {
      $uploadBrowseProxy = new UploadBrowseProxy($groupId, $this->userPerm, $this->dbManager);
      $uploadBrowseProxy->updateTable($columnName, $uploadId, $value);
    } else if (! empty($moveUpload) && ! empty($beyondUpload)) {
      $uploadBrowseProxy = new UploadBrowseProxy($groupId, $this->userPerm, $this->dbManager);
      $uploadBrowseProxy->moveUploadBeyond($moveUpload, $beyondUpload);
    } else if (! empty($uploadId) && ! empty($direction)) {
      $uploadBrowseProxy = new UploadBrowseProxy($groupId, $this->userPerm, $this->dbManager);
      $uploadBrowseProxy->moveUploadToInfinity($uploadId, $direction == 'top');
    } else if (!empty($uploadId) && !empty($commentText) && !empty($statusId)) {
      $uploadBrowseProxy = new UploadBrowseProxy($groupId, $this->userPerm, $this->dbManager);
      $uploadBrowseProxy->setStatusAndComment($uploadId, $statusId, $commentText);
    } else {
      return $this->respondFolderGetTableData($request);
    }
    return new Response('');
  }


  /**
   * @param Request $request
   * @return JsonResponse
   */
  protected function respondFolderGetTableData(Request $request)
  {
    /* Get list of uploads in this folder */
    list($result, $iTotalDisplayRecords, $iTotalRecords) = $this->getListOfUploadsOfFolder($request);

    $uri = Traceback_uri() . "?mod=license";
    /* Browse-Pfile menu */
    $menuPfile = menu_find("Browse-Pfile", $menuDepth);
    /* Browse-Pfile menu without the compare menu item */
    $menuPfileNoCompare = menu_remove($menuPfile, "Compare");

    $users = $this->userDao->getUserChoices();

    $statusTypesAvailable = $this->uploadDao->getStatusTypeMap();
    if (!$this->userPerm) {
      unset($statusTypesAvailable[4]);
    }

    $output = array();
    $rowCounter = 0;
    while ($row = $this->dbManager->fetchArray($result)) {
      if (empty($row['upload_pk']) || !$this->uploadDao->isAccessible($row['upload_pk'],Auth::getGroupId())) {
        continue;
      }
      $rowCounter++;
      $output[] = $this->showRow($row, $request, $uri, $menuPfile, $menuPfileNoCompare, $statusTypesAvailable, $users, $rowCounter);
    }
    $this->dbManager->freeResult($result);
    return new JsonResponse(array(
              'sEcho' => intval($request->get('sEcho')),
              'aaData' => $output,
              'iTotalRecords' => $iTotalRecords,
              'iTotalDisplayRecords' => $iTotalDisplayRecords
          ));
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
    if (empty($fileName)) {
      $fileName = $row['upload_filename'];
    }

    $itemId = Isartifact($row['ufile_mode']) ? DirGetNonArtifact($row['uploadtree_pk']) : $row['uploadtree_pk'];
    if (strlen($fileName) > 20) {
      $splitFileName = str_split($fileName, 20);
      $fileName = "";
      foreach ($splitFileName as $key => $value) {
        if (strlen($value) > 3 && $key > 0) {
          $fileName .= "<br/>".$value;
        } else {
          $fileName .= $value;
        }
      }
    }
    $nameColumn = "<strong class='btn btn-sm font-weight-bold' style='margin-left:10px;font-size:11pt;'>$fileName</strong>";
    if (IsContainer($row['ufile_mode'])) {
      $nameColumn = "<a href='$uri&upload=$uploadId&folder=$folder&item=$itemId&show=$show'>$nameColumn</a>";
    }
    $nameColumn .= "<br>";
    if (!empty($description)) {
      $nameColumn .= "<i>$description</i><br>";
    }
    $Parm = "upload=$uploadId&show=$show&item=" . $row['uploadtree_pk'];
    if (Iscontainer($row['ufile_mode'])) {
      $nameAction = MenuRenderer::menuToActiveSelect($menuPfile, $Parm, $uploadId, $folder);
    } else {
      $nameAction = MenuRenderer::menuToActiveSelect($menuPfileNoCompare, $Parm, $uploadId, $folder);
    }

    $modsUploadMulti = MenuHook::getAgentPluginNames('UploadMulti');
    if (!empty($modsUploadMulti)) {
      $nameColumn = '<input type="checkbox" name="uploads[]" class="browse-upload-checkbox" style="width:1.10rem;height:1.10rem;" value="'.$uploadId.'"/>'.$nameColumn;
    }

    $dateCol = Convert2BrowserTime(substr($row['upload_ts'], 0, 19));
    if (!$this->userPerm && 4 == $row['status_fk']) {
      $currentStatus = $this->statusTypes[4];
    } else {
      $statusAction = " onchange =\"changeTableEntry(this, $uploadId,'status_fk' )\" ";
      $currentStatus = $this->createSelect("Status" . $this->userPerm . "Of_$rowCounter", $statusTypesAvailable, $row['status_fk'], $statusAction);
    }
    if ($this->userPerm) {
      $action = " onchange =\"changeTableEntry(this, $uploadId, 'assignee')\"";
      $currentAssignee = $this->createSelectUsers("AssignedTo_$rowCounter", $users, $row['assignee'], $action );
    } else {
      $currentAssignee = array_key_exists($row['assignee'], $users) ? $users[$row['assignee']] : _('Unassigned');
    }
    $rejectableUploadId = ($this->userPerm || $row['status_fk'] < 4) ? $uploadId : 0;
    $tripleComment = array($rejectableUploadId, $row['status_fk'], htmlspecialchars($row['status_comment']));

    $sql = "SELECT rf_pk, rf_shortname FROM license_ref lf JOIN upload_clearing_license ucl"
            . " ON lf.rf_pk=ucl.rf_fk WHERE upload_fk=$1 AND ucl.group_fk=$2";
    $stmt = __METHOD__.'.collectMainLicenses';
    $mainParams = array($uploadId, Auth::getGroupId());
    $lic = $this->dbManager->getRows($sql, $mainParams, $stmt);
    $mainLicenses = array();
    foreach ($lic as $mainLic) {
      $mainLicenses[] = '<a onclick="javascript:window.open(\''.Traceback_uri()
              ."?mod=popup-license&rf=$mainLic[rf_pk]','License text','width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes');"
              .'" href="javascript:;">'.$mainLic['rf_shortname'].'</a>'
              ."<img onclick=\"removeMainLicense($uploadId,$mainLic[rf_pk]);\" class=\"delete\" src=\"images/space_16.png\" alt=\"\"/></img>";
    }

    $output = array($nameColumn, $nameAction, $currentStatus, $tripleComment, implode(', ', $mainLicenses), $dateCol, $currentAssignee);
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
    if (array_key_exists($_SESSION['UserId'], $databaseMap)) {
      $databaseMap[$_SESSION['UserId']] = _('-- Me --');
    }
    $databaseMap[1] = _('Unassigned');
    return $this->createSelect($selectElementName,$databaseMap, $selectedValue,$action);
  }


  private function createSelect($id,$options,$select='',$action='')
  {
    $html = "<select class='form-control-sm' style=\"max-width:250px;\" name=\"$id\" id=\"$id\" $action class=\"ui-render-select2\">";
    foreach ($options as $key=>$disp) {
      $html .= '<option value="'.$key.'"';
      if ($key == $select) {
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
    $uploadBrowseProxy = new UploadBrowseProxy(Auth::getGroupId(), $this->userPerm, $this->dbManager);
    $params = array($request->get('folder'));
    $partQuery = $uploadBrowseProxy->getFolderPartialQuery($params);

    $iTotalRecordsRow = $this->dbManager->getSingleRow("SELECT count(*) FROM $partQuery", $params, __METHOD__ . "count.all");
    $iTotalRecords = $iTotalRecordsRow['count'];

    $this->filterParams = $params;
    $filter = $this->getSearchString($request->get('sSearch'));
    $filter .= $this->getIntegerFilter(intval($request->get('assigneeSelected')), 'assignee');
    $filter .= $this->getIntegerFilter(intval($request->get('statusSelected')), 'status_fk');

    $iTotalDisplayRecordsRow = $this->dbManager->getSingleRow("SELECT count(*) FROM $partQuery $filter",
        $this->filterParams, __METHOD__ . ".count.". $filter);
    $iTotalDisplayRecords = $iTotalDisplayRecordsRow['count'];

    $orderString = $this->getOrderString();
    $stmt = __METHOD__ . "getFolderContents" . $orderString . $filter;

    $statementString = "SELECT upload.*,upload_clearing.*,uploadtree.ufile_name,uploadtree.ufile_mode,uploadtree.uploadtree_pk"
            . " FROM $partQuery $filter $orderString";
    $rangedFilterParams = $this->filterParams;
    $rangedFilterParams[] = intval($request->get('iDisplayStart'));
    $statementString .= ' OFFSET $' . count($rangedFilterParams);
    $rangedFilterParams[] = intval($request->get('iDisplayLength'));
    $statementString .= ' LIMIT $' . count($rangedFilterParams);

    $this->dbManager->prepare($stmt, $statementString);
    $result = $this->dbManager->execute($stmt, $rangedFilterParams);

    return array($result, $iTotalDisplayRecords, $iTotalRecords);
  }

  private function getOrderString()
  {
    $columnNamesInDatabase = array('upload_filename', 'upload_clearing.status_fk', 'UNUSED', 'UNUSED', 'upload_clearing.assignee', 'upload_ts', 'upload_clearing.priority');

    $orderString = $this->dataTablesUtility->getSortingString($_GET, $columnNamesInDatabase);

    return $orderString;
  }

  private function getSearchString($searchPattern)
  {
    if (empty($searchPattern)) {
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
    if (empty($var)) {
      return '';
    }
    $this->filterParams[] = $var;
    return " AND $columnName=$" . count($this->filterParams) . ' ';
  }
}

register_plugin(new AjaxBrowse());
