<?php
/***********************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.
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

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE =>  _("Edit Uploaded File Permissions"),
        self::MENU_LIST => "Admin::Upload Permissions",
        self::PERMISSION => Auth::PERM_WRITE,
        self::REQUIRES_LOGIN => TRUE
    ));
    $this->uploadPermDao = $this->getObject('dao.upload.permission');
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
    if (empty($groupsWhereUserIsAdmin))
    {
      $text = _("You have no permission to manage any group.");
      return $this->render('include/base.html.twig',$this->mergeWithDefault(array('content'=>$text)));
    }
    
    $folder_pk = intval($request->get('folder'));
    $upload_pk = intval($request->get('upload'));
    $perm_upload_pk = intval($request->get('permupk'));
    $perm = intval($request->get('perm'));
    $newgroup = intval($request->get('newgroup'));
    $newperm = intval($request->get('newperm'));
    $public_perm = $request->get('public', -1);
    
    /* @var $folderDao FolderDao */
    $folderDao = $this->getObject('dao.folder');
    $root_folder_pk = $folderDao->getRootFolder(Auth::getUserId())->getId();
    if (empty($folder_pk)) {
      $folder_pk = $root_folder_pk;
    }
    
    $UploadList = FolderListUploads_perm($folder_pk, Auth::PERM_WRITE);
    if (empty($upload_pk) && !empty($UploadList))
    {
      $upload_pk = $UploadList[0]['upload_pk'];
    }
    
    if (!empty($perm_upload_pk))
    { 
      $this->uploadPermDao->updatePermissionId($perm_upload_pk, $perm);
    }
    else if (!empty($newgroup) && !empty($newperm))
    {
      $this->insertPermission($newgroup,$upload_pk,$newperm,$UploadList);
      $newperm = $newgroup = 0;
    }
    else if ($public_perm >= 0)
    {
      $this->uploadPermDao->setPublicPermission($upload_pk, $public_perm);
    }

    $vars = array('folderStructure' => $folderDao->getFolderStructure($root_folder_pk),
        'groupArray'=>$groupsWhereUserIsAdmin,
        'uploadId'=>$upload_pk,
        'folderId'=>$folder_pk,
        'baseUri'=> Traceback_uri() . '?mod=upload_permissions',
        'newPerm'=>$newperm, 'newGroup'=>$newgroup,
        'uploadList'=>$UploadList, 'permNames'=>$GLOBALS['PERM_NAMES']);

    if (!empty($UploadList))
    {
      $vars['publicPerm'] = $this->uploadPermDao->getPublicPermission($upload_pk);
      $permGroups = $this->uploadPermDao->getPermissionGroups($upload_pk);
      $vars['permGroups'] = $permGroups;
      $additableGroups = array(0=>'-- select group --');
      foreach($groupsWhereUserIsAdmin as $gId=>$gName)
      {
        if (!array_key_exists($gId, $permGroups)) {
          $additableGroups[$gId] = $gName;
        }
      }
      $vars['additableGroups'] = $additableGroups;
    }
    $vars['gumJson'] = json_encode($this->getGroupMembers($groupsWhereUserIsAdmin));
    
    if(!empty($upload_pk)){
      $vars['permNamesWithReuse'] = $this->getPermNamesWithReuse($upload_pk);
    }
        
    return $this->render('upload_permissions.html.twig', $this->mergeWithDefault($vars));
  }
  
  private function getPermNamesWithReuse($uploadId)
  {
    $permNamesWithReuse = $GLOBALS['PERM_NAMES'];
    try
    {
      $uploadBrowseProxy = new UploadBrowseProxy(Auth::getGroupId(), Auth::PERM_READ, $this->dbManager);
      $uploadStatus = $uploadBrowseProxy->getStatus($uploadId);
    }
    catch(\Exception $e)
    {
      return $permNamesWithReuse;
    }
    if($uploadStatus==UploadStatus::IN_PROGRESS || $uploadStatus==UploadStatus::CLOSED)
    {
      foreach($GLOBALS['PERM_NAMES'] as $perm=>$name)
      {
        $permNamesWithReuse[$perm|self::MOD_REUSE] = $name._(' with reuse');
      }
    }
    return $permNamesWithReuse;
  }
  
  private function insertPermission($groupId,$uploadId,$permission,$uploadList)
  {
    $fileName = false;
    foreach($uploadList as $uploadEntry)
    {
      if ($uploadEntry['upload_pk']) {
        $fileName = $uploadEntry['name'];
      }
    }
    if(empty($fileName))
    {
      throw new \Exception('This upload is missing or inaccessible');
    }

    $reuseBit = $permission&self::MOD_REUSE;
    if($reuseBit){
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
    while($row = $this->dbManager->fetchArray($res))
    {
      if (array_key_exists($row['group_fk'], $groupsWhereUserIsAdmin)) {
        $gum[] = array($row['user_name'], $row['group_fk']);
      }
    }
    $this->dbManager->freeResult($res);
    return $gum;
  }
}

register_plugin(new UploadPermissionPage());