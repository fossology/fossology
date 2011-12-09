<?php
/***********************************************************
 Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.

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

define("TITLE_group_manage_self", _("Manage Own Group"));

class group_manage_self extends FO_Plugin {
  var $Name = "group_manage_self";
  var $Title = TITLE_group_manage_self;
  var $MenuList = "Admin::Groups::Manage Own Group";
  var $Version = "1.3";
  var $Dependency = array();
  var $DBaccess = PLUGIN_DB_NONE;


  function PostInitialize()
  {
    global $PG_CONN;
    //$UserId = $_SESSION['UserId'];
    global $Plugins;

    if (empty($PG_CONN)) { return(1); } /* No DB */

    if ($this->State != PLUGIN_STATE_VALID) {
      return(0);
    } // don't run
    if (empty($_SESSION['User']) && $this->LoginFlag) {
      return(0);
    }
    // Make sure dependencies are met
    foreach($this->Dependency as $key => $val) {
      $id = plugin_find_id($val);
      if ($id < 0) {
        $this->Destroy();
        return(0);
      }
    }

    if (!empty($_SESSION['UserId'])){
      $UserId = $_SESSION['UserId'];
      $sql = "SELECT * FROM group_user_member WHERE user_fk = $UserId and group_perm=1;";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      if (empty($result) || pg_num_rows($result) < 1){
        pg_free_result($result);
        return(0);
      }
      pg_free_result($result);
    }

    $this->State = PLUGIN_STATE_READY;
    // Add this plugin to the menu
    if ($this->MenuList !== "") {
      menu_insert("Main::" . $this->MenuList,$this->MenuOrder,$this->Name,$this->MenuTarget);
    }
    return($this->State == PLUGIN_STATE_READY);
  }

  /**
   * \brief Show all groups owned by $UserId
   */
  function ShowExistOwnGroups($UserId)
  {
    global $PG_CONN;
    $V = "";
    /* Get the group of this users */
    $sql = "SELECT * FROM group_user_member,groups WHERE user_fk=$UserId AND group_fk=group_pk AND group_perm=1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) > 0)
    {
      $V .= "<table border=1>\n";
      $text = _("Group");
      $text1 = _("User");
      $V .= "<tr><th>$text</th><th>$text1</th><th></th><th></th></tr>\n";
      while ($row = pg_fetch_assoc($result))
      {
        $V .= "<tr><td align='center'>" . $row['group_name'] . "</td><td align='center'>";
        $sql = "SELECT user_name,group_perm FROM group_user_member, users WHERE group_fk=" . $row['group_pk'] . " AND user_pk=user_fk;";
        $result1 = pg_query($PG_CONN, $sql);
        DBCheckResult($result1, $sql, __FILE__, __LINE__);
        if (pg_num_rows($result1) > 0)
        {
          $V .= "<table border=0>\n";
          while ($row1 = pg_fetch_assoc($result1))
          {
            $perm = ($row1['group_perm']==1)?"Admin":"User";
            $V .= "<tr><td align='center'>" . $row1['user_name'] . "</td><td>||</td><td align='center'>$perm</td></tr>";
          }
          $V .= "</table>\n";
        }
        pg_free_result($result1);
        $V .= "<td align='center'><a href='" .  Traceback_uri() . "?mod=group_manage_self&action=edit&group_pk=" . $row['group_pk'] . "&groupname=" . $row['group_name'] . "'>Edit Group Name</a></td><td align='center'><a href='" . Traceback_uri() . "?mod=group_manage_self&action=add&group_pk=" . $row['group_pk'] . "&groupname=" . $row['group_name'] . "'>Add User to this Group</a></td></tr>\n";
      }
      $V .= "</table>\n";
    }
    pg_free_result($result);
    return ($V);
  }

  /**
   * \brief Edit group name.
   */
  function EditGroupNamePage()
  {
    $group_pk = GetParm('group_pk', PARM_INTEGER);
    $VE = "";
    $text = _("Edit Group Name:");
    $VE.= "<h4>$text</h4>\n";
    $VE.= "<form name='form' method='POST' action='" . Traceback_uri() . "?mod=group_manage_self&action=update&group_pk=$group_pk'>\n";
    $Val = htmlentities(GetParm('groupname', PARM_TEXT), ENT_QUOTES);
    $text = _("Enter the groupname:");
    $VE.= "$text\n";
    $VE.= "<input type='text' value='$Val' name='groupname' size=20>\n";
    $text = _("Edit");
    $VE.= "<input type='submit' value='$text!'>\n";
    $VE.= "</form>\n";
    return ($VE);
  }

  /**
   * \brief Edit group name.
   */
  function EditGroupName()
  {
    global $PG_CONN;
    $group_pk = GetParm('group_pk', PARM_INTEGER);
    $group_name = GetParm('groupname', PARM_TEXT);

    $sql = "UPDATE groups SET group_name='$group_name' WHERE group_pk=$group_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
    return (NULL);
  }

  /**
   * \brief add user to this group.
   */
  function AddUserPage()
  {
    global $PG_CONN;
    $group_pk = GetParm('group_pk', PARM_INTEGER);
    $group_name = GetParm('groupname', PARM_TEXT);

    /* Get the list of users */
    $sql = "SELECT user_pk,user_name FROM users WHERE user_pk!=" . $_SESSION['UserId'] . " ORDER BY user_name;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    $VA = "";
    $text = _("Add User to Group");
    $VA.= "<h4>$text '$group_name':</h4>\n";
    $VA.= "<form name='form' method='POST' action='" . Traceback_uri() . "?mod=group_manage_self&action=user&group_pk=$group_pk'>\n";
    $VA.= _("Select the user to add this group: ");
    $VA.= "<select name='userid'>\n";
    while ($row = pg_fetch_assoc($result)){
      $VA.= "<option value='" . $row['user_pk'] . "'>";
      $VA.= htmlentities($row['user_name']);
      $VA.= "</option>\n";
    }
    $VA.= "</select>\n";
    pg_free_result($result);
    $VA.= _("Select Role: ");
    $VA.= "<select name='permid'>\n";
    $VA.= "<option value='0'>User</option>\n";
    $VA.= "<option value='1'>Admin</option>\n";
    $VA.= "</select>\n";
    $text = _("Add");
    $VA.= "<input type='submit' value='$text!'>\n";
    $VA.= "</form>\n";
    return ($VA);
  }

  /**
   * \brief add user to this group.
   */
  function AddUser()
  {
    global $PG_CONN;
    $group_fk = GetParm('group_pk', PARM_INTEGER);
    $user_fk = GetParm('userid', PARM_INTEGER);
    $perm = GetParm('permid', PARM_INTEGER);

    $sql = "SELECT * FROM group_user_member WHERE group_fk=$group_fk AND user_fk=$user_fk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1)
    {
      pg_free_result($result);
      $sql = "INSERT INTO group_user_member (group_fk,user_fk,group_perm) VALUES ($group_fk,$user_fk,$perm);";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    }else{
      pg_free_result($result);
      $text = _("This User already in this Group!");
      return ($text);
    }
    return (NULL);
  }

  /**
   * \brief Generate the text for this plugin.
   */
  function Output() {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V = "";
    switch ($this->OutputType) {
      case "XML":
        break;
      case "HTML":
        /* If this is a POST, then process the request. */
        $UserId = $_SESSION['UserId'];
        $action = GetParm('action', PARM_TEXT);
        if ($action == 'update'){
          $this->EditGroupName();
        }
        if ($action == 'user'){
          $rc = $this->AddUser();
          if (!empty($rc)){
            $V.= displayMessage($rc);
          }
        }
        $V .= $this->ShowExistOwnGroups($UserId);

        if ($action == 'edit'){
          $V .= $this->EditGroupNamePage();
        }
        if ($action == 'add'){
          $V .= $this->AddUserPage();
        }
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) {
      return ($V);
    }
    print ("$V");
    return;
  }
};
$NewPlugin = new group_manage_self;
?>
