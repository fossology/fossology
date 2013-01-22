<?php
/***********************************************************
 Copyright (C) 2010-2013 Hewlett-Packard Development Company, L.P.

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

define("TITLE_group_manage", _("Manage Group"));

/**
 * \class group_manage extends FO_Plugin
 * \brief manage group, such as add, delete, show, etc
 */
class group_manage extends FO_Plugin {
  var $Name = "group_manage";
  var $Title = TITLE_group_manage;
  var $MenuList = "Admin::Groups::Manage Group";
  var $Version = "1.3";
  var $Dependency = array();
  var $DBaccess = PLUGIN_DB_ADMIN;


  /**
   * \brief Delete a group.
   * Returns NULL on success, string on failure.
   */
  function Delete() {
    global $PG_CONN;

    $group_pk = GetParm('group_pk', PARM_INTEGER);

    $sql = "SELECT * FROM tag_ns_group WHERE group_fk = $group_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) > 0)
    {
      pg_free_result($result);
      $text = _("Group Delete Failed:As there are group permissions related to this group, if you want to delete this group you should first delete permissions about this group! ");
      return ($text);
    }

    pg_free_result($result);

    pg_exec("BEGIN;");
    $sql = "DELETE FROM group_user_member WHERE group_fk = $group_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    $sql = "DELETE FROM groups WHERE group_pk = $group_pk;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
    pg_exec("COMMIT;");

    return (NULL);
  }

  /**
   * \biref Add a group.
   * Returns NULL on success, string on failure.
   */
  function Add() {
    global $PG_CONN;

    /* Get the parameters */
    $Group = str_replace("'", "''", GetParm('groupname', PARM_TEXT));
    $UserId = GetParm('userid', PARM_INTEGER);

    /* Make sure groupname looks valid */
    if (empty($Group)) {
      $text = _("Groupname must be specified. Not added.");
      return ($text);
    }
    /* Make sure groupname not exceed the length */

    pg_exec("BEGIN;");
    /* See if the group already exists */
    $sql = "SELECT * FROM groups WHERE group_name = '$Group' LIMIT 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1)
    {
      pg_free_result($result);
      $sql = "INSERT INTO groups (group_name) VALUES ('$Group');";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    }else{
      pg_free_result($result);
      $text = _("Group already exists.  Not added.");
      return ($text);
    }

    /* Make sure it was added */
    $sql = "SELECT * FROM groups WHERE group_name = '$Group' LIMIT 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1){
      pg_free_result($result);
      $text = _("Failed to insert user.");
      return ($text);
    }
    $row = pg_fetch_assoc($result);
    $Group_pk = $row['group_pk'];
    pg_free_result($result);

    /* Add group admin to table group_user_member */
    $sql = "INSERT INTO group_user_member (group_fk,user_fk,group_perm) VALUES ($Group_pk,$UserId,1);";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    pg_exec("COMMIT;");

    return (NULL);
  } // Add()

  /**
   * \brief Show all groups
   */
  function ShowExistGroups()
  {
    global $PG_CONN;
    $VE = "";
    $VE = _("<h3>Current Groups:</h3>\n");
    $sql = "SELECT * FROM groups;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) > 0)
    {
      $VE .= "<table border=1>\n";
      $text = _("Group");
      $text1 = _("User");
      $text2 = _("Permission");
      $VE .= "<tr><th>$text</th><th>$text1||$text2</th><th></th></tr>\n";
      while ($row = pg_fetch_assoc($result))
      {
        $VE .= "<tr><td align='center'>" . $row['group_name'] . "</td><td align='center'>";
        $sql = "SELECT user_name,group_perm FROM group_user_member, users WHERE group_fk=" . $row['group_pk'] . " AND user_pk=user_fk;";
        $result1 = pg_query($PG_CONN, $sql);
        DBCheckResult($result1, $sql, __FILE__, __LINE__);
        if (pg_num_rows($result1) > 0)
        {
          $VE .= "<table border=0>\n";
          while ($row1 = pg_fetch_assoc($result1))
          {
            $perm = ($row1['group_perm']==1)?"Admin":"User";
            $VE .= "<tr><td align='center'>" . $row1['user_name'] . "</td><td>||</td><td align='center'>$perm</td></tr>";
          }
          $VE .= "</table>\n";
        }
        pg_free_result($result1);
        $VE .= "</td><td align='center'><a href='" . Traceback_uri() . "?mod=group_manage&action=delete&group_pk=" . $row['group_pk'] . "'>Delete</a></td></tr>\n";
      }
      $VE .= "</table><p>\n";
    }
    pg_free_result($result);
    return $VE;
  }

  /**
   * \brief Generate the text for this plugin.
   */
  function Output() {
    global $PG_CONN;
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }
    $V = "";
    switch ($this->OutputType) {
      case "XML":
        break;
      case "HTML":
        /* If this is a POST, then process the request. */
        $Group = GetParm('groupname', PARM_TEXT);
        $UserId = GetParm('userid', PARM_INTEGER);
        $action = GetParm('action', PARM_TEXT);
        if (!empty($Group)) {
          $rc = $this->Add();
          if (empty($rc)) {
            /* Need to refresh the screen */
            $text = _("Group");
            $text1 = _("added");
            $V.= displayMessage("$text $Group $text1.");
          } else {
            $V.= displayMessage($rc);
          }
        }
        if ($action == 'delete'){
          $rc = $this->Delete();
          if (empty($rc)) {
            $text = _("Group Delete Successful");
            $V.= displayMessage("$text!");
          } else {
            $V.= displayMessage($rc);
          }
        }
        /* Get the list of users */
        $sql = "SELECT user_pk,user_name FROM users ORDER BY user_name;";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);

        /* Build HTML form */
        $text = _("Add a Group");
        $V.= "<h4>$text</h4>\n";
        $V.= "<form name='formy' method='POST' action=" . Traceback_uri() ."?mod=group_manage>\n";
        $Val = htmlentities(GetParm('groupname', PARM_TEXT), ENT_QUOTES);
        $text = _("Enter the groupname:");
        $V.= "$text\n";
        $V.= "<input type='text' value='$Val' name='groupname' size=20>\n";
        $V.= _("Select the user as this group admin: ");
        $V.= "<select name='userid'>\n";
        while ($row = pg_fetch_assoc($result)){
          $Selected = "";
          if ($UserId == $row['user_pk']) {
            $Selected = "selected";
          }
          $V.= "<option $Selected value='" . $row['user_pk'] . "'>";
          $V.= htmlentities($row['user_name']);
          $V.= "</option>\n";
        }
        $V.= "</select>\n";
        pg_free_result($result);
        $text = _("Add");
        $V.= "<input type='submit' value='$text!'>\n";
        $V.= "</form>\n";

        $V.= $this->ShowExistGroups();
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
$NewPlugin = new group_manage;
?>
