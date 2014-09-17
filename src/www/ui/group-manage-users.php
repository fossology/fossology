<?php
/***********************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2014, Siemens AG

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

use Fossology\Lib\View\Renderer;

define("TITLE_group_manage_users", _("Manage Group Users"));

/**
 * \class group_manage extends FO_Plugin
 * \brief edit group user permissions
 */
class group_manage_users extends FO_Plugin {
  var $groupPermissions = array(-1 => "None", 0=>"User", 1=>"Admin", 2=>"Advisor");
          
  function __construct(){
    $this->Name = "group_manage_users";
    $this->Title = TITLE_group_manage_users;
    $this->MenuList = "Admin::Groups::Manage Group Users";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->LoginFlag = 1;  /* Don't allow Default User to add a group */
    parent::__construct();
  }
  
  /* @brief Verify user has access to update record
   * @param $user_pk
   * @param $group_pk
   *
   * @return No return.  If access fails, print message and exit.
   **/
  function VerifyAccess($user_pk, $group_pk)
  {
    global $PG_CONN;

    if (@$_SESSION['UserLevel'] == PLUGIN_DB_ADMIN) return;

    $sql = "select group_user_member_pk from group_user_member where user_fk='$user_pk' 
                 and group_fk='$group_pk' and group_perm=1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $NumRows = pg_num_rows($result);
    pg_free_result($result);
    if ($NumRows < 1)
    {
      $text = _("Permission Failure");
      echo "<h2>$text</h2>";
      exit;
    }
  }
  
  function OutputOpen($Type,$ToStdout){
    $this->OutputType = $Type;
    $this->OutputToStdout = $ToStdout;
    if ($ToStdout != 1)
    {
      return parent::OutputOpen($Type, $ToStdout);
    }
  }

  /*********************************************
   Output(): Generate the text for this plugin.
   *********************************************/
  function Output() 
  {
    global $SysConf;

    global $container;
    $dbManager = $container->get('db.manager');
    $user_pk = $SysConf['auth']['UserId'];

    /* GET parameters */
    $group_pk = GetParm('group', PARM_INTEGER); /* group_pk to manage */
    $gum_pk = GetParm('gum_pk', PARM_INTEGER);  /* group_user_member_pk */
    $perm = GetParm('perm', PARM_INTEGER);     /* Updated permission for gum_pk */
    $newuser = GetParm('newuser', PARM_INTEGER); /* New group      */
    $newperm = GetParm('newperm', PARM_INTEGER);   /* New permission */
    if (empty($newperm)) $newperm = 0;

    /* If gum_pk is passed in, update either the group_perm or user_pk */
    if (!empty($gum_pk))
    { 
      /* Verify user has access */
      if (empty($group_pk))
      {
        $gum_rec = $dbManager->getSingleRow("SELECT group_fk FROM group_user_member WHERE group_user_member_pk=$1",
                array($gum_pk),$stmt=__METHOD__.".getGroupByGUM");
        $group_pk = $gum_rec['group_fk'];
      }
      $this->VerifyAccess($user_pk, $group_pk);

      if ($perm === -1)
      {
        $dbManager->prepare($stmt=__METHOD__.".delByGUM",
                          "delete from group_user_member where group_user_member_pk=$1");
        $dbManager->freeResult($dbManager->execute($stmt,array($gum_pk)));
      }
      else if (array_key_exists ($perm, $this->groupPermissions))
      {
        $dbManager->getSingleRow("update group_user_member set group_perm=$1 where group_user_member_pk=$2",
                array($perm,$gum_pk),$stmt=__METHOD__.".updatePermInGUM");
      } 
    }
    else if (!empty($newuser) && (!empty($group_pk)))
    {
      // before inserting this new record, delete any record for the same upload and group since
      // that would be a duplicate
      $dbManager->prepare($stmt=__METHOD__.".delByGroupAndUser",
              "delete from group_user_member where group_fk=$1 and user_fk=$2");
      $dbManager->freeResult($dbManager->execute($stmt,array($group_pk,$newuser)));
      if ($newperm >= 0)
      {
        $dbManager->prepare($stmt=__METHOD__.".insertGUP",
                "insert into group_user_member (group_fk, user_fk, group_perm) values ($1,$2,$3)");
        $dbManager->freeResult($dbManager->execute($stmt,array($group_pk, $newuser, $newperm)));
      }
      $newperm = $newuser = 0;
    }

    /* Get array of groups that this user is an admin of */
    $GroupArray = GetGroupArray($user_pk);
    if (empty($GroupArray))
    {
      $text = _("You have no permission to manage any group.");
      echo "<p>$text<p>";
      return;
    }
        
    // start building the output buffer
    $V = js_url();
    /** @var Renderer   */
    $renderer = $container->get('renderer');

    reset($GroupArray);
    if (empty($group_pk)) $group_pk = key($GroupArray);

    $text = _("Select the group to manage:  \n");
    $V.= "$text";

    /*** Display group select list, on change request new page with group= in url ***/
    $url = Traceback_uri() . "?mod=group_manage_users&group=";
    $onchange = "onchange=\"js_url(this.value, '$url')\"";
    $V .= $renderer->createSelect('groupselect', $GroupArray, $group_pk, $onchange);

    /* Select all the user members of this group */
    $stmt = __METHOD__."getUsersWithGroup";
    $dbManager->prepare($stmt,"select group_user_member_pk, user_fk, group_perm, user_name from group_user_member GUM INNER JOIN users
             on  GUM.user_fk=users.user_pk where GUM.group_fk=$1  order by user_name");
    $result = $dbManager->execute($stmt,array($group_pk));
    $groupMembersContent = '';
    while ($GroupMember = $dbManager->fetchArray($result)) {
      $url = Traceback_uri() . "?mod=group_manage_users&gum_pk=$GroupMember[group_user_member_pk]&perm=";
      $onchange = "onchange=\"js_url(this.value, '$url')\"";

      $groupMembersContent .= "<tr>";
      $groupMembersContent .= "<td>$GroupMember[user_name]</td>";
      $groupMembersContent .= "<td>".$renderer->createSelect("permselect", $this->groupPermissions, $GroupMember['group_perm'], $onchange)."</td>";
      $groupMembersContent .= "</tr>";
    }
    $dbManager->freeResult($result);

    /* Permissions Table */
    $V .= "<p><table border=1>";
    $UserText = _("User");
    $PermText = _("Permission");
    $V .= "<tr><th>$UserText</th><th>$PermText</th></tr>";
    $V .= $groupMembersContent;
    
    $dbManager->prepare($stmt=__METHOD__.".selectUsersNotInGroup",
            "SELECT user_pk,user_name FROM users LEFT JOIN group_user_member GUM ON user_pk=user_fk AND GUM.group_fk=$1"
            . " WHERE group_user_member_pk IS NULL ORDER BY user_name");
    $usersNotInGroup = $dbManager->execute($stmt,array($group_pk));
    $otherUsers = array(''=>'');
    while($row=$dbManager->fetchArray($usersNotInGroup))
    {
      $otherUsers[$row['user_pk']] = $row['user_name'];
    }
    $dbManager->freeResult($usersNotInGroup);
    if(count($otherUsers)){
      $url = Traceback_uri() . "?mod=group_manage_users&newperm=$newperm&group=$group_pk&newuser=";
      $onchange = "onchange=\"js_url(this.value, newpermurl)\"";
      $V .= "<tr>";
      $V .= "<td>".$renderer->createSelect("userselectnew", $otherUsers, '', $onchange)."</td>";
      $onPermChange = ' onchange=\'setNewPermUrl(this.value)\'';
      $newPermArray = $this->groupPermissions;
      unset($newPermArray[-1]);
      $V .= "<td>".$renderer->createSelect("permselectnew", $newPermArray,$newperm,$onPermChange)."</td>";
      $V .= "</tr>";
            $script = "var newpermurl;
            function setNewPermUrl(newperm){
               newpermurl='".Traceback_uri()."?mod=group_manage_users&newperm='+newperm+'&group=$group_pk&newuser=';
            }
            setNewPermUrl($newperm);";
      $V .= '<script type="text/javascript"> '.$script.'</script>';
    } 
    $V .= "</table>";

    $text = _("All user permissions take place immediately when a value is changed.  There is no submit button.");
    $V .= "<p>" . $text;
    $text = _("Add new users on the last line.");
    $V .= "<br>" . $text;

    if (!$this->OutputToStdout)
    {
      return $V;
    }
    print($this->OutputOpen($this->OutputType,0));
    $this->OutputToStdout = 1;
    print ($V);
    return;
  }
}
$NewPlugin = new group_manage_users;
