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
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UploadPermissionPage extends DefaultPlugin 
{
  const NAME = 'upload_permissions';
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
    $GroupArray = GetGroupArray(Auth::getUserId());
    if (empty($GroupArray))
    {
      $text = _("You have no permission to manage any group.");
      return $this->render('include/base.html.twig',$this->mergeWithDefault(array('content'=>$text)));
    }
    
    
    global $PG_CONN;
    global $PERM_NAMES;

    $folder_pk = intval($request->get('folder'));
    $upload_pk = intval($request->get('upload'));
    $perm_upload_pk = intval($request->get('permupk'));
    $perm = intval($request->get('perm'));
    $newgroup = intval($request->get('newgroup'));
    $newperm = intval($request->get('newperm'));
    $public_perm = $request->get('public', -1);

    if (!empty($perm_upload_pk))
    { 
      $this->uploadPermDao->updatePermissionId($perm_upload_pk, $perm);
    }
    else if (!empty($newgroup) and (!empty($newperm)))
    {
      $this->uploadPermDao->insertPermission($upload_pk, $newgroup, $newperm);
      $newperm = $newgroup = 0;
    }
    else if ($public_perm >= 0)
    {
      $this->uploadPermDao->setPublicPermission($upload_pk, $public_perm);
    }

    /* @var $folderDao FolderDao */
    $folderDao = $this->getObject('dao.folder');
    $root_folder_pk = $folderDao->getRootFolder(Auth::getUserId())->getId();
    if (empty($folder_pk)) {
      $folder_pk = $root_folder_pk;
    }
    $V = "";

    $UploadList = FolderListUploads_perm($folder_pk, Auth::PERM_WRITE);
    if (empty($upload_pk))
    {
      $upload_pk = $UploadList[0]['upload_pk'];
    }

    if (!empty($UploadList))
    {
      $public_perm = $this->uploadPermDao->getPublicPermission($upload_pk);
      
      $sql = "select perm_upload_pk, perm, group_pk, group_name from groups, perm_upload where group_fk=group_pk and upload_fk='$upload_pk'";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $PermArray = pg_fetch_all($result);
      pg_free_result($result);
      
      foreach ($PermArray as $PermRow)
      {
        $V .= "<tr>";
        $V .= "<td>";  // group
        $V .= $PermRow['group_name'];
        $V .= "</td>";
        $V .= "<td>";  // permission
        $url = Traceback_uri() . "?mod=upload_permissions&upload=$upload_pk&folder=$folder_pk&permupk={$PermRow['perm_upload_pk']}&perm=";
        $onchange = "onchange=\"js_url(this.value, '$url')\"";
        $V .= Array2SingleSelect($PERM_NAMES, "permselect", $PermRow['perm'], false, false, $onchange);
        $V .= "</td>";
        $V .= "</tr>";
      }
    }

    $this->dbManager->prepare($stmt=__METHOD__.'.gum', "SELECT user_name,gum.group_fk FROM group_user_member gum, users WHERE user_fk=user_pk");
    $res = $this->dbManager->execute($stmt);
    $gum = array();
    while($row = $this->dbManager->fetchArray($res))
    {
      if (array_key_exists($row['group_fk'], $GroupArray)) {
        $gum[] = array($row['user_name'], $row['group_fk']);
      }
    }
    $this->dbManager->freeResult($res);

    $vars = array('content'=>$V, 
        'folderStructure' => $folderDao->getFolderStructure($root_folder_pk),
        'groupArray'=>$GroupArray,
        'gumJson'=>json_encode($gum),
        'folderId'=>$folder_pk,
        'baseUri'=> Traceback_uri() . '?mod=upload_permissions',
        'uploadList'=>$UploadList,
        'uploadId'=>$upload_pk,
        'permNames'=>$PERM_NAMES, 'publicPerm'=>$public_perm,
        'newPerm'=>$newperm, 'newGroup'=>$newgroup);
    return $this->render('upload_permissions.html.twig', $this->mergeWithDefault($vars));
  }
}

register_plugin(new UploadPermissionPage());