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

/**
 * \class user_del extends FO_Plugin
 * \brief delete a user
 */
class user_del extends FO_Plugin
{
  var $Name       = "user_del";
  var $Title      = TITLE_user_del;
  var $MenuList   = "Admin::Users::Delete";
  var $Version    = "1.0";
  var $Dependency = array();
  var $DBaccess   = PLUGIN_DB_ADMIN;

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
    /* Now delete their group */
    DeleteGroup($GroupRow['group_pk']);

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

    /* Make sure it was deleted */
    $sql = "SELECT * FROM users WHERE user_name = '$UserId' LIMIT 1;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);
    if (!empty($row['user_name']))
    {
      $text = _("Failed to delete user.");
      return($text);
    }

    return(NULL);
  } // Delete()

  /**
   * \brief Generate the text for this plugin.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) { return; }
    global $PG_CONN;
    $V="";
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
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
            $V .= displayMessage($text);
          }
          else
          {
            $V .= displayMessage($rc);
          }
        }

        /* Get the user list */
        $sql = "SELECT user_pk,user_name,user_desc FROM users WHERE user_pk != '" . @$_SESSION['UserId'] . "' AND user_pk != '1' ORDER BY user_name;";
        $result = pg_query($PG_CONN, $sql);
        DBCheckResult($result, $sql, __FILE__, __LINE__);
        $row = pg_fetch_assoc($result);
        if (empty($row['user_name']))
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
          $count = pg_num_rows($result);
          for($i=0; $i < $count and $row = pg_fetch_assoc($result, $i) and !empty($row['user_name']); $i++)
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
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
  }
};
$NewPlugin = new user_del;
?>
