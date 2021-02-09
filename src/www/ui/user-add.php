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

use Fossology\Lib\Db\DbManager;

define("TITLE_USER_ADD", _("Add A User"));

class user_add extends FO_Plugin
{

  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    $this->Name = "user_add";
    $this->Title = TITLE_USER_ADD;
    $this->MenuList = "Admin::Users::Add";
    $this->DBaccess = PLUGIN_DB_ADMIN;
    parent::__construct();
    $this->dbManager = $GLOBALS['container']->get('db.manager');
  }

  /**
   * \brief Add a user.
   *
   * \return NULL on success, string on failure.
   */
  function Add()
  {

    global $PG_CONN;

    if (! $PG_CONN) {
      DBconnect();
      if (! $PG_CONN) {
        $text = _("NO DB connection!");
        echo "<pre>$text\n</pre>";
      }
    }

    /* Get the parameters */
    $User = str_replace("'", "''", GetParm('username', PARM_TEXT));
    $User = trim($User);
    $Pass = GetParm('pass1', PARM_TEXT);
    $Pass2 = GetParm('pass2', PARM_TEXT);
    $options = array('cost' => 10);
    $Hash = password_hash($Pass, PASSWORD_DEFAULT, $options);
    $Desc = str_replace("'", "''", GetParm('description', PARM_TEXT));
    $Perm = GetParm('permission', PARM_INTEGER);
    $Folder = GetParm('folder', PARM_INTEGER);
    $Email_notify = GetParm('enote', PARM_TEXT);
    $Email = str_replace("'", "''", GetParm('email', PARM_TEXT));
    $Upload_visibility = GetParm('public', PARM_TEXT);
    $agentList = userAgents();
    $default_bucketpool_fk = GetParm('default_bucketpool_fk', PARM_INTEGER);

    /* Make sure username looks valid */
    if (empty($User)) {
      $text = _("Username must be specified. Not added.");
      return ($text);
    }
    /* limit the user name size to 64 characters when creating an account */
    if (strlen($User) > 64) {
      $text = _("Username exceed 64 characters. Not added.");
      return ($text);
    }
    /* Make sure password matches */
    if ($Pass != $Pass2) {
      $text = _("Passwords did not match. Not added.");
      return ($text);
    }

    /* Make sure password matches policy */
    $policyRegex = generate_password_policy();
    $result = preg_match('/^' . $policyRegex . '$/m', $Pass);
    if ($result !== 1) {
      $text = _("Password does not match policy.");
      $text .= "<br />" . generate_password_policy_string();
      return ($text);
    }

    if (empty($Email)) {
      $text = _("Email must be specified. Not added.");
      return ($text);
    }

    /* Make sure email looks valid */
    if (! filter_var($Email, FILTER_VALIDATE_EMAIL)) {
      $text = _("Invalid email address.  Not added.");
      return ($text);
    }

    /* Make sure email is unique */
    $email_count = $this->dbManager->getSingleRow(
      "SELECT COUNT(*) as count FROM users WHERE user_email = $1 LIMIT 1;",
      array($Email))["count"];
    if ($email_count > 0) {
      $text = _("Email address already exists.  Not added.");
      return ($text);
    }

    /* See if the user already exists (better not!) */
    $row = $this->dbManager->getSingleRow("SELECT * FROM users WHERE LOWER(user_name) = LOWER($1) LIMIT 1;",
        array($User), $stmt = __METHOD__ . ".getUserIfExisting");
    if (! empty($row['user_name'])) {
      $text = _("User already exists.  Not added.");
      return ($text);
    }

    /* check email notification, if empty (box not checked), or if no email
     * specified for the user set to 'n'.
     */
    if (empty($Email_notify) || empty($Email)) {
      $Email_notify = '';
    }

    if (empty($Upload_visibility)) {
      $Upload_visibility = null;
    }

    $ErrMsg = add_user($User, $Desc, $Hash, $Perm, $Email, $Email_notify, $Upload_visibility,
      $agentList, $Folder, $default_bucketpool_fk);

    return ($ErrMsg);
  } // Add()


  public function Output()
  {
    /* If this is a POST, then process the request. */
    $User = GetParm('username', PARM_TEXT);
    if (! empty($User)) {
      $rc = $this->Add();
      if (empty($rc)) {
        $text = _("User");
        $text1 = _("added");
        $this->vars['message'] = "$text $User $text1.";
      } else {
        $this->vars['message'] = $rc;
      }
    }

    $V = "<form name='user_add' method='POST'>\n";
    $V.= _("To create a new user, enter the following information:<P />\n");
    $Style = "<tr><td colspan=2 style='background:black;'></td></tr><tr>";
    $V.= "<table style='border:1px solid black; text-align:left; background:lightyellow;' width='75%'>";
    $Val = htmlentities(GetParm('username', PARM_TEXT), ENT_QUOTES);
    $text = _("Username");
    $V.= "$Style<th width='25%' >$text</th>";
    $V.= "<td><input type='text' value='$Val' name='username' size=20></td>\n";
    $V.= "</tr>\n";
    $Val = htmlentities(GetParm('description', PARM_TEXT), ENT_QUOTES);
    $text = _("Description, full name, contact, etc. (optional)");
    $V.= "$Style<th>$text</th>\n";
    $V.= "<td><input type='text' name='description' value='$Val' size=60></td>\n";
    $V.= "</tr>\n";
    $Val = htmlentities(GetParm('email', PARM_TEXT), ENT_QUOTES);
    $text = _("Email address");
    $V .= "$Style<th>$text</th>\n";
    $V.= "<td><input type='text' name='email' value='$Val' size=60></td>\n";
    $V.= "</tr>\n";
    $text = _("Access level");
    $V.= "$Style<th>$text</th>";
    $V.= "<td><select name='permission'>\n";
    $text = _("None (very basic, no database access)");
    $V.= "<option value='" . PLUGIN_DB_NONE . "'>$text</option>\n";
    $text = _("Read-only (read, but no writes or downloads)");
    $V.= "<option selected value='" . PLUGIN_DB_READ . "'>$text</option>\n";
    $text = _("Read-Write (read, download, or edit information)");
    $V.= "<option value='" . PLUGIN_DB_WRITE . "'>$text</option>\n";
    $text = _("Clearing Administrator (read, download, edit information and edit decisions)");
    $V.= "<option value='" . PLUGIN_DB_CADMIN . "'>$text</option>\n";
    $text = _("Full Administrator (all access including adding and deleting users)");
    $V.= "<option value='" . PLUGIN_DB_ADMIN . "'>$text</option>\n";
    $V.= "</select></td>\n";
    $V.= "</tr>\n";
    $text = _("User root folder");
    $V.= "$Style<th>$text";
    $V.= "</th>";
    $V.= "<td><select name='folder' class='ui-render-select2'>";
    $V.= FolderListOption(-1, 0);
    $V.= "</select></td>\n";
    $V.= "</tr>\n";
    $text = _("Password (optional)");
    if (passwordPolicyEnabled()) {
      $text = _("Password");
    }
    $V.= "$Style<th>$text</th><td><input type='password' name='pass1' id='passcheck' size=20>";
    $policy = generate_password_policy_string();
    if ($policy != "No policy defined.") {
      $V.= "<br /><span class='passPolicy'>$policy</span>";
    }
    $V.= "</td>\n</tr>\n";
    $text = _("Re-enter password");
    $V.= "$Style<th>$text</th><td><input type='password' name='pass2' id='pass2' size=20 style='margin:4px'></td>\n";
    $V.= "</tr>\n";
    $text = _("E-mail Notification");
    $text1 = _("Check to enable email notification when upload scan completes .");
    $V .= "$Style<th>$text</th><td><input type='checkbox'" .
            "name='enote' value='y' checked='checked'>" .
            "$text1</td>\n";
    $V.= "</tr>\n";
    $text = _("Default upload visibility");
    $text1 = _("Visible only for active group");
    $text2 = _("Visible for all groups");
    $text3 = _("Make Public");
    $text4 = _("which is the currently selected group");
    $text5 = _("which are accessible by you now");
    $text6 = _("visible for all users");
    $V.= "$Style<th>$text</th><td>" .
    "<input type='radio' name='public' value='private'/>$text1<img src='images/info_16.png' title='$text4' alt='' class='info-bullet'/><br/>" .
    "<input type='radio' name='public' value='protected'/>$text2<img src='images/info_16.png' title='$text5' alt='' class='info-bullet'/><br/>" .
    "<input type='radio' name='public' value='public'/>$text3<img src='images/info_16.png' title='$text6' alt='' class='info-bullet'/><br/></td>\n";
    $V.= "</tr>\n";
    $text = _("Agents selected by default when uploading");
    $V .= "$Style<th>$text\n</th><td> ";
    $V.= AgentCheckBoxMake(-1, array("agent_unpack", "agent_adj2nest", "wget_agent"));

    $V .= "</td>\n";
    $text = _("Default bucketpool");
    $V.= "$Style<th>$text</th>";
    $V.= "<td>";
    $default_bucketpool_fk = 0;
    $V.= SelectBucketPool($default_bucketpool_fk);
    $V.= "</td>";
    $V .= "</tr>\n";
    $V.= "</table border=0><P />";

    $text = _("Add User");
    $V.= "<input type='submit' value='$text'>\n";
    $V.= "</form>\n";

    $this->vars['formName'] = "user_add";
    $this->vars['policyDisabled'] = passwordPolicyEnabled() ? "false" : "true";
    $this->vars['policyRegex'] = generate_password_policy();
    $passwordScript = $this->renderString("password-policy-check.js.twig");
    $this->renderScripts('<script type="text/javascript">' . $passwordScript .
      '</script>');

    return $V;
  }
}
$NewPlugin = new user_add;
