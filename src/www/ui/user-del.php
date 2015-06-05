<?php
/***********************************************************
 Copyright (C) 2008-2013 Hewlett-Packard Development Company, L.P.

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

define("TITLE_user_del", _("Delete A User"));

use \Fossology\Lib\Auth\Auth;

/**
 * \class user_del extends FO_Plugin
 * \brief delete a user
 */
class user_del extends FO_Plugin
{
  function __construct()
  {
    $this->Name       = "user_del";
    $this->Title      = TITLE_user_del;
    $this->MenuList   = "Admin::Users::Delete";
    $this->DBaccess   = PLUGIN_DB_ADMIN;

    parent::__construct();
  }

  /**
   * \brief Delete a user.
   * 
   * \return NULL on success, string on failure.
   */
  function Delete($UserId)
  {
    global $PG_CONN;
    /* See if the user already exists */
    $sql = "SELECT * FROM users WHERE user_pk = '$UserId' LIMIT 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    if (empty($row['user_name']))
    {
      $text = _("User does not exist.");
      return($text);
    }

    /* Delete the users group 
     * First look up the users group_pk
     */
    $sql = "SELECT group_pk FROM groups WHERE group_name = '$row[user_name]' LIMIT 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $GroupRow = pg_fetch_assoc($result);
    pg_free_result($result);

    /* Delete all the group user members for this user_pk */
    $sql = "DELETE FROM group_user_member WHERE user_fk = '$UserId'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    /* Delete the user */
    $sql = "DELETE FROM users WHERE user_pk = '$UserId';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    /* Now delete their group */
    DeleteGroup($GroupRow['group_pk']);

    /* Make sure it was deleted */
    $sql = "SELECT * FROM users WHERE user_name = '$UserId' LIMIT 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $rowCount = pg_num_rows($result);
    pg_free_result($result);
    if ($rowCount != 0)
    {
      $text = _("Failed to delete user.");
      return($text);
    }

    return(NULL);
  } // Delete()

  /**
   * \brief Generate the text for this plugin.
   */
  public function Output()
  {
    global $PG_CONN;
    $V="";
    /* If this is a POST, then process the request. */
    $User = GetParm('userid',PARM_TEXT);
    $Confirm = GetParm('confirm',PARM_INTEGER);
    if (!empty($User))
    {
      if ($Confirm != 1) { $rc = "Deletion not confirmed. Not deleted."; }
      else { $rc = $this->Delete($User); }
      if (empty($rc))
      {
        /* Need to refresh the screen */
        $text = _("User deleted.");
        $this->vars['message'] = $text;
      }
      else
      {
        $this->vars['message'] = $rc;
      }
    }

    /* Get the user list */
    $currentUserId = Auth::getUserId();
    $sql = "SELECT user_pk,user_name,user_desc FROM users WHERE user_pk != '$currentUserId' AND user_pk != '1' ORDER BY user_name";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) == 0)
    {
      $V .= _("No users to delete.");
    }
    else
    {
      /* Build HTML form */
      $V .= _("Deleting a user removes the user entry from the FOSSology system. The user's name, account information, and password will be <font color='red'>permanently</font> removed. (There is no 'undo' to this delete.)<P />\n");
      $V .= "<form name='formy' method='POST'>\n"; // no url = this url
      $V .= _("To delete a user, enter the following information:<P />\n");
      $Style = "<tr><td colspan=3 style='background:black;'></td></tr><tr>";
      $Val = htmlentities(GetParm('userid',PARM_TEXT),ENT_QUOTES);
      $V .= "<ol>\n";
      $V .= _("<li>Select the user to delete.<br />");
      $V .= "<select name='userid'>\n";
      while( $row = pg_fetch_assoc($result))
      {
        $V .= "<option value='" . $row['user_pk'] . "'>";
        $V .= $row['user_name'];
        $V .= "</option>\n";
      }
      $V .= "</select>\n";

      $text = _("Confirm user deletion");
      $V .= "<P /><li>$text: <input type='checkbox' name='confirm' value='1'>";
      $V .= "</ol>\n";

      $text = _("Delete");
      $V .= "<input type='submit' value='$text!'>\n";
      $V .= "</form>\n";
    }
    pg_free_result($result);

    return $V;
  }
}
$NewPlugin = new user_del;
