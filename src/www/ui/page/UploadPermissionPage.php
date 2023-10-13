<?php
/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015, 2020 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\FolderDao;
use Fossology\Lib\Dao\UploadPermissionDao;
use Fossology\Lib\Data\UploadStatus;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Proxy\UploadBrowseProxy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UploadPermissionPage extends DefaultPlugin
{
  const NAME = 'upload_permissions';
  const MOD_REUSE = 16;

  /** @var UploadPermissionDao */
  private $uploadPermDao;

  /** @var DbManager */
  private $dbManager;

  /** @var FolderDao */
  private $folderDao;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE =>  _("Edit Uploaded File Permissions"),
        self::MENU_LIST => "Admin::Upload Permissions",
        self::PERMISSION => Auth::PERM_WRITE,
        self::REQUIRES_LOGIN => true
    ));
    $this->uploadPermDao = $this->getObject('dao.upload.permission');
    $this->folderDao = $this->getObject('dao.folder');
    $this->dbManager = $this->getObject('db.manager');
  }

  /**
   * @param Request $request
   * @return Response
   */
  protected function handle(Request $request)
  {
    /* Get array of groups that this user is an admin of */
    $groupsWhereUserIsAdmin = GetGroupArray(Auth::getUserId());
    if (empty($groupsWhereUserIsAdmin)) {
      $text = _("You have no permission to manage any group.");
      return $this->render('include/base.html.twig',$this->mergeWithDefault(array('content'=>$text)));
    }

    $folder_pk = intval($request->get('folder'));
    $upload_pk = intval($request->get('upload'));
    $allUploadsPerm = ($request->get('alluploadsperm') == 1) ? 1 : 0;
    $perm_upload_pk = intval($request->get('permupk'));
    $perm = intval($request->get('perm'));
    $newgroup = intval($request->get('newgroup'));
    $newperm = intval($request->get('newperm'));
    $public_perm = $request->get('public', -1);

    $commu_status = fo_communicate_with_scheduler('status', $response_from_scheduler, $error_info);
    if ($commu_status) {
      $response_from_scheduler = "";
    } else {
      $response_from_scheduler = "Error: Scheduler is not running!";
      $error_info = null;
    }

    $res = $this->editPermissionsForUpload($commu_status, $folder_pk, $upload_pk, $allUploadsPerm, $perm_upload_pk, $perm,$newgroup, $newperm, $public_perm);
    $vars = array(
            'folderStructure' => $this->folderDao->getFolderStructure($res['root_folder_pk']),
            'groupArray' => $groupsWhereUserIsAdmin,
            'uploadId' => $res['upload_pk'],
            'allUploadsPerm' => $res['allUploadsPerm'],
            'folderId' => $res['folder_pk'],
            'baseUri' => Traceback_uri() . '?mod=upload_permissions',
            'newPerm' => $res['newperm'],
            'newGroup' => $res['newgroup'],
            'uploadList' => $res['UploadList'],
            'permNames' => $GLOBALS['PERM_NAMES'],
            'message' => $response_from_scheduler
            );

    if (!empty($vars['uploadList'])) {
      $vars['publicPerm'] = $this->uploadPermDao->getPublicPermission($vars['uploadId']);
      $permGroups = $this->uploadPermDao->getPermissionGroups($vars['uploadId']);
      $vars['permGroups'] = $permGroups;
      $additableGroups = array(0 => '-- select group --');
      foreach ($groupsWhereUserIsAdmin as $gId=>$gName) {
        if (!array_key_exists($gId, $permGroups)) {
          $additableGroups[$gId] = $gName;
        }
      }
      $vars['additableGroups'] = $additableGroups;
    }
    $vars['gumJson'] = json_encode($this->getGroupMembers($groupsWhereUserIsAdmin));

    if (!empty($vars['uploadId'])) {
      $vars['permNamesWithReuse'] = $this->getPermNamesWithReuse($vars['uploadId']);
    }

    return $this->render('upload_permissions.html.twig', $this->mergeWithDefault($vars));
  }

  function editPermissionsForUpload($commu_status, $folder_pk,$upload_pk,$allUploadsPerm,$perm_upload_pk,$perm,$newgroup,$newperm,$public_perm)
  {
    $root_folder_pk = $this->folderDao->getRootFolder(Auth::getUserId())->getId();
    if (empty($folder_pk)) {
      $folder_pk = $root_folder_pk;
    }

    $UploadList = FolderListUploads_perm($folder_pk, Auth::PERM_WRITE);
    if (empty($allUploadsPerm)) {
      if (empty($upload_pk) && !empty($UploadList)) {
        $upload_pk = $UploadList[0]['upload_pk'];
      }
      if (!empty($perm_upload_pk)) {
        $this->uploadPermDao->updatePermissionId($perm_upload_pk, $perm);
      } else if (!empty($newgroup) && !empty($newperm)) {
        if ($commu_status) {
          $this->insertPermission($newgroup,$upload_pk,$newperm,$UploadList);
        }
        $newperm = $newgroup = 0;
      } else if ($public_perm >= 0) {
        $this->uploadPermDao->setPublicPermission($upload_pk, $public_perm);
      }
    } else {
      foreach ($UploadList as $uploadDetails) {
        $upload_pk = $uploadDetails['upload_pk'];
        if (!empty($newgroup) && !empty($newperm)) {
          if ($commu_status) {
            $this->insertPermission($newgroup, $upload_pk, $newperm, $UploadList);
          }
        } else if ($public_perm >= 0) {
          $this->uploadPermDao->setPublicPermission($upload_pk, $public_perm);
        }
      }
    }

    return array(
      'root_folder_pk' => $root_folder_pk,
      'upload_pk' => $upload_pk,
      'allUploadsPerm' => $allUploadsPerm,
      'folder_pk' => $folder_pk,
      'newperm' => $newperm,
      'newgroup' => $newgroup,
      'UploadList' => $UploadList,
    );
  }

  private function getPermNamesWithReuse($uploadId)
  {
    $permNamesWithReuse = $GLOBALS['PERM_NAMES'];
    try {
      $uploadBrowseProxy = new UploadBrowseProxy(Auth::getGroupId(), Auth::PERM_READ, $this->dbManager);
      $uploadStatus = $uploadBrowseProxy->getStatus($uploadId);
    } catch(\Exception $e) {
      return $permNamesWithReuse;
    }
    if ($uploadStatus==UploadStatus::IN_PROGRESS || $uploadStatus==UploadStatus::CLOSED) {
      foreach ($GLOBALS['PERM_NAMES'] as $perm=>$name) {
        $permNamesWithReuse[$perm|self::MOD_REUSE] = $name._(' with reuse');
      }
    }
    return $permNamesWithReuse;
  }

  function insertPermission($groupId,$uploadId,$permission,$uploadList)
  {
    $fileName = false;
    foreach ($uploadList as $uploadEntry) {
      if ($uploadEntry['upload_pk']) {
        $fileName = $uploadEntry['name'];
      }
    }
    if (empty($fileName)) {
      throw new \Exception('This upload is missing or inaccessible');
    }

    $reuseBit = $permission&self::MOD_REUSE;
    if ($reuseBit) {
      $jobId = \JobAddJob(Auth::getUserId(), $groupId, $fileName, $uploadId);
      $reuserAgent = \plugin_find('agent_reuser');
      $request = new Request(array('uploadToReuse'=>"$uploadId,".Auth::getGroupId(),'groupId'=>$groupId));
      $reuserAgent->scheduleAgent($jobId, $uploadId, $errorMsg, $request);
      if (!empty($errorMsg)) {
        throw new Exception($errorMsg);
      }
      $permission ^= $reuseBit;
    }
    $this->uploadPermDao->insertPermission($uploadId, $groupId, $permission);
  }

  private function getGroupMembers($groupsWhereUserIsAdmin)
  {
    $this->dbManager->prepare($stmt=__METHOD__,
            "SELECT user_name,gum.group_fk FROM group_user_member gum, users WHERE user_fk=user_pk");
    $res = $this->dbManager->execute($stmt);
    $gum = array();
    while ($row = $this->dbManager->fetchArray($res)) {
      if (array_key_exists($row['group_fk'], $groupsWhereUserIsAdmin)) {
        $gum[] = array($row['user_name'], $row['group_fk']);
      }
    }
    $this->dbManager->freeResult($res);
    return $gum;
  }
}

register_plugin(new UploadPermissionPage());
