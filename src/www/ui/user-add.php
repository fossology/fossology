<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Symfony\Component\HttpFoundation\Request;

define("TITLE_USER_ADD", _("Add A User"));

class user_add extends DefaultPlugin
{
  const NAME = "user_add";

  /** @var DbManager */
  private $dbManager;

  function __construct()
  {
    parent::__construct(self::NAME, array(
      self::TITLE => TITLE_USER_ADD,
      self::MENU_LIST => 'Admin::Users::Add',
      self::REQUIRES_LOGIN => true,
      self::PERMISSION => Auth::PERM_ADMIN
    ));
    $this->dbManager = $this->getObject('db.manager');
  }

  /**
   * \brief Add a user.
   *
   * \return NULL on success, string on failure.
   */
  public function add(Request $request)
  {
    /* Get the parameters */
    $User = str_replace("'", "''", $request->get('username'));
    $User = trim($User);
    $Pass = $request->get('pass1');
    $Pass2 = $request->get('pass2');
    $options = array('cost' => 10);
    $Hash = password_hash($Pass, PASSWORD_DEFAULT, $options);
    $Desc = str_replace("'", "''", $request->get('description'));
    $Perm = $request->get('permission');
    $Folder = $request->get('folder');
    $Email_notify = $request->get('enote');
    $Email = str_replace("'", "''", $request->get('email'));
    $Upload_visibility = $request->get('public');
    $agentList = is_null($request->get('user_agent_list')) ? userAgents() : $request->get('user_agent_list');
    $default_bucketpool_fk = $request->get('default_bucketpool_fk');

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

    /* Make sure email looks valid (If email field not empty) */
    if (! empty($Email) && ! filter_var($Email, FILTER_VALIDATE_EMAIL)) {
      $text = _("Invalid email address.  Not added.");
      return ($text);
    }

    /* Make sure email is unique (If email field not empty) */
    $email_count = 0;
    if (! empty($Email)) { 
      $email_count = $this->dbManager->getSingleRow(
        "SELECT COUNT(*) as count FROM users WHERE user_email = $1 LIMIT 1;",
        array($Email))["count"];
    }
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


  public function handle(Request $request)
  {
    /* If this is a POST, then process the request. */
    $User = $request->get('username');
    if (! empty($User)) {
      $rc = $this->add($request);
      if (empty($rc)) {
        $text = _("User");
        $text1 = _("added");
        $vars['message'] = "$text $User $text1.";
      } else {
        $vars['message'] = $rc;
      }
    }

    $vars['userName'] = htmlentities($request->get('username'), ENT_QUOTES);
    $vars['userDescription'] = htmlentities($request->get('description'), ENT_QUOTES);
    $vars['userEmail'] = htmlentities($request->get('email'), ENT_QUOTES);
    $vars['accessLevel'] = [
      PLUGIN_DB_NONE,
      PLUGIN_DB_READ,
      PLUGIN_DB_WRITE,
      PLUGIN_DB_CADMIN,
      PLUGIN_DB_ADMIN
    ];
    $vars['folderListOption'] = FolderListOption(-1, 0);
    $vars['passOptional'] = " (Optional)";
    if (passwordPolicyEnabled()) {
      $vars['passOptional'] = "";
    }
    $vars['passwordPolicy'] = generate_password_policy_string();
    if ($vars['passwordPolicy'] == "No policy defined.") {
      $vars['passwordPolicy'] = "";
    }
    $vars['agentSelector'] = AgentCheckBoxMake(-1, array("agent_unpack", "agent_adj2nest", "wget_agent"));

    $default_bucketpool_fk = 0;
    $vars['bucketPool'] = SelectBucketPool($default_bucketpool_fk);
    $vars['formName'] = "user_add";
    $vars['policyDisabled'] = passwordPolicyEnabled() ? "false" : "true";
    $vars['policyRegex'] = generate_password_policy();
    return $this->render('user_add.html.twig', $this->mergeWithDefault($vars));
  }
}

register_plugin(new user_add());
