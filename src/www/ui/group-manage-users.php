<?php
/***********************************************************
 Copyright (C) 2013 Hewlett-Packard Development Company, L.P.

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

define("TITLE_group_manage_users", _("Manage Group Users"));

/**
 * \class group_manage extends FO_Plugin
 * \brief edit group user permissions
 */
class group_manage_users extends FO_Plugin {
  var $Name = "group_manage_users";
  var $Title = TITLE_group_manage_users;
  var $MenuList = "Admin::Groups::Manage Group Users";
  var $Dependency = array();
  var $DBaccess = PLUGIN_DB_WRITE;
  var $LoginFlag = 1;  /* Don't allow Default User to add a group */


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

  /*********************************************
   Output(): Generate the text for this plugin.
   *********************************************/
  function Output() 
  {
    global $PG_CONN;
    global $PERM_NAMES;
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
    $sql = "";
    if (!empty($gum_pk))
    { 
      /* Verify user has access */
      if (empty($group_pk))
      {
        $gum_rec = GetSingleRec("group_user_member", "where group_user_member_pk='$gum_pk'");
        $group_pk = $gum_rec['group_fk'];
      }
      $this->VerifyAccess($user_pk, $group_pk);

      if ($perm===0 or $perm===1)
      {
        $sql = "update group_user_member set group_perm='$perm' where group_user_member_pk='$gum_pk'";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        pg_free_result($result);
      } 
      else if ($perm === -1)
      {
        $sql = "delete from group_user_member where group_user_member_pk='$gum_pk'";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        pg_free_result($result);
      }
    }
    else if (!empty($newuser) && (!empty($group_pk)))
    {
      // before inserting this new record, delete any record for the same upload and group since
      // that would be a duplicate
      $sql = "delete from group_user_member where group_fk='$group_pk' and user_fk='$newuser'";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
      
      if ($newperm >= 0)
      {
        $sql = "insert into group_user_member (group_fk, user_fk, group_perm) values ($group_pk, $newuser, $newperm)";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        pg_free_result($result);
      }
      $newperm = $newuser = 0;
    }

    // start building the output buffer
    $V = "";
    /* define js_url */
    $V .= js_url(); 

    /* Get array of groups that this user is an admin of */
    $GroupArray = GetGroupArray($user_pk);
    if (empty($GroupArray))
    {
      $text = _("You have no permission to manage any group.");
      echo "<p>$text<p>";
      return;
    }
    reset($GroupArray);
    if (empty($group_pk)) $group_pk = key($GroupArray);

    $text = _("Select the group to manage:  \n");
    $V.= "$text";

    /*** Display group select list, on change request new page with group= in url ***/
    $url = Traceback_uri() . "?mod=group_manage_users&group=";
    $onchange = "onchange=\"js_url(this.value, '$url')\"";
    $V .= Array2SingleSelect($GroupArray, "groupselect", $group_pk, false, false, $onchange);

    /* Create array of group_user_member group_perm possible values for use in a select list */
    $group_permArray = array(-1 => "None", 0=>"User", 1=>"Admin");

    /* Select all the user members of this group */
    $stmt = __METHOD__."getUsersWithGroup";
    $dbManager->prepare($stmt,"select group_user_member_pk, user_fk, group_perm, user_name from group_user_member GUM INNER JOIN users
             on  GUM.user_fk=users.user_pk where GUM.group_fk=$1  order by user_name");
    $result = $dbManager->execute($stmt,array($group_pk));

    $GroupMembersArray = pg_fetch_all($result);
    pg_free_result($result);

    /* Permissions Table */
    $V .= "<p><table border=1>";
    $UserText = _("User");
    $PermText = _("Permission");
    $V .= "<tr><th>$UserText</th><th>$PermText</th></tr>";
    if (!empty($GroupMembersArray)) { // does this group have childen ?
      foreach ($GroupMembersArray as $GroupMember)
      {
        $V .= "<tr>";
        $V .= "<td>";  // user
        $V .= $GroupMember['user_name'];
        $V .= "</td>";

        $V .= "<td>";  // permission
        $url = Traceback_uri() . "?mod=group_manage_users&gum_pk=$GroupMember[group_user_member_pk]&perm=";
        $onchange = "onchange=\"js_url(this.value, '$url')\"";
        $V .= Array2SingleSelect($group_permArray, "permselect", $GroupMember['group_perm'], false, false, $onchange);
        $V .= "</td>";
        $V .= "</tr>";
      }
    }
    /* Print one extra row for adding perms */
    $V .= "<tr>";
    $V .= "<td>";  // user
    $url = Traceback_uri() . "?mod=group_manage_users&newperm=$newperm&group=$group_pk&newuser=";
    $onchange = "onchange=\"js_url(this.value, '$url')\"";
    $Selected = (empty($newuser)) ? "" : $newuser;
    $UserArray = Table2Array("user_pk", "user_name", "users", " ", "order by user_name");
    $V .= Array2SingleSelect($UserArray, "userselectnew", $Selected, true, false, $onchange);
    $V .= "</td>";
    $V .= "<td>";  // permission
    $url = Traceback_uri() . "?mod=group_manage_users&newuser=$newuser&group=$group_pk&newperm=";
    $onchange = "onchange=\"js_url(this.value, '$url')\"";
    $Selected = $newperm;
    $V .= Array2SingleSelect($group_permArray, "permselectnew", $Selected, false, false, $onchange);
    $V .= "</td>";
    $V .= "</tr>";
 
    $V .= "</table>";

    $text = _("All user permissions take place immediately when a value is changed.  There is no submit button.");
    $V .= "<p>" . $text;
    $text = _("Add new users on the last line.");
    $V .= "<br>" . $text;

    if (!$this->OutputToStdout) return ($V);
    print ("$V");
    return;
  }
}
$NewPlugin = new group_manage_users;
?>
