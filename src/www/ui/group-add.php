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

define("TITLE_add_group", _("Add Group"));

/**
 * \class group_add extends FO_Plugin
 * \brief add a new group
 */
class group_add extends FO_Plugin {
  function __construct()
  {
    $this->Name = "group_add";
    $this->Title = TITLE_add_group;
    $this->MenuList = "Admin::Groups::Add Group";
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->LoginFlag = 1;  /* Don't allow Default User to add a group */
    parent::__construct();
  }


  /**
   * \brief Add a group.
   * \param $GroupName raw group name as entered by the user
   * Returns NULL on success, string on failure.
   */
  function Add($GroupName) 
  {
    global $PG_CONN;
    global $SysConf;

    $user_pk = $SysConf['auth']['UserId'];

    /* Get the parameters */
    $Group = str_replace("'", "''", $GroupName);

    /* Make sure groupname looks valid */
    if (empty($Group)) 
    {
      $text = _("Error: Group name must be specified.");
      return ($text);
    }

    /* See if the group already exists */
    $sql = "SELECT * FROM groups WHERE group_name = '$Group'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1)
    {
      pg_free_result($result);
      $sql = "INSERT INTO groups (group_name) VALUES ('$Group')";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    }
    else
    {
      pg_free_result($result);
      $text = _("Group already exists.  Not added.");
      return ($text);
    }

    /* Make sure it was added and get the group_pk */
    $sql = "SELECT group_pk FROM groups WHERE group_name = '$Group'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) < 1)
    {
      pg_free_result($result);
      $text = _("Failed to create group");
      return ($text . " $Group");
    }
    $row = pg_fetch_assoc($result);
    $Group_pk = $row['group_pk'];
    pg_free_result($result);

    /* Add group admin to table group_user_member */
    $sql = "INSERT INTO group_user_member (group_fk,user_fk,group_perm) VALUES ($Group_pk, $user_pk, 1)";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    return (NULL);
  }


  protected function htmlContent() 
  {
    $V = "";
    /* If this is a POST, then process the request. */
    $Group = GetParm('groupname', PARM_TEXT);
    if (!empty($Group)) 
    {
      $rc = $this->Add($Group);
      if (empty($rc)) 
      {
        $text = _("Group");
        $text1 = _("added");
        $this->vars['message'] = "$text $Group $text1.";
      } 
      else 
      {
        $this->vars['message'] = $rc;
      }
    }

    /* Build HTML form */
    $text = _("Add a Group");
    $V.= "<h4>$text</h4>\n";
    $V.= "<form name='formy' method='POST' action=" . Traceback_uri() ."?mod=group_add>\n";
    $Val = htmlentities(GetParm('groupname', PARM_TEXT), ENT_QUOTES);
    $text = _("Enter the groupname:");
    $V.= "$text\n";
    $V.= "<input type='text' value='$Val' name='groupname' size=20>\n";
    $text = _("Add");
    $V.= "<input type='submit' value='$text'>\n";
    $V.= "</form>\n";

    return $V;
  }
}
$NewPlugin = new group_add;
