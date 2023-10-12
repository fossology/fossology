<?php
/*
 SPDX-FileCopyrightText: © 2014 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2021-2022 Orange
 Contributors: Piotr Pszczola, Bartlomiej Drozdz

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Exceptions\DuplicateTokenKeyException;
use Fossology\Lib\Exceptions\DuplicateTokenNameException;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\UI\Api\Exceptions\HttpBadRequestException;
use Fossology\UI\Api\Helper\AuthHelper;
use Fossology\UI\Api\Helper\DbHelper;
use Fossology\UI\Api\Helper\RestHelper;
use Symfony\Component\HttpFoundation\Request;

class UserEditPage extends DefaultPlugin
{
  const NAME = "user_edit";

  /** @var DbManager */
  private $dbManager;

  /**
   * @var AuthHelper
   * Auth helper object */
  private $authHelper;

  /**
   * @var UserDao
   * UserDao object */
  private $userDao;

  function __construct()
  {
    parent::__construct(self::NAME, array(
        self::TITLE => _("Edit User Account"),
        self::MENU_LIST => 'Admin::Users::Edit User Account',
        self::REQUIRES_LOGIN => true,
        self::PERMISSION => Auth::PERM_READ
    ));

    $this->dbManager = $this->getObject('db.manager');
    $this->authHelper = $this->getObject('helper.authHelper');
    $this->userDao = $this->getObject('dao.user');
  }

  /**
   * @brief Allow user to change their account settings (users db table).
   *
   * If the user is an Admin, they can change settings for any user.\n
   * This is called in the following circumstances:\n
   * 1) User clicks on Admin > Edit User Account\n
   * 2) User has chosen a user to edit from the 'userid' select list  \n
   * 3) User hit submit to update user data\n
   */
  function handle(Request $request)
  {
    global $SysConf;
    /* Is the session owner an admin? */
    $user_pk = Auth::getUserId();
    $SessionUserRec = $this->GetUserRec($user_pk);
    $SessionIsAdmin = $this->IsSessionAdmin($SessionUserRec);
    $newToken = "";
    $newClient = "";

    if (GetParm('new_client', PARM_STRING)) {
      try {
        $newClient = $this->addNewClient($request);
      } catch (\Exception $e) {
        $newClient = $e->getMessage();
      }
    }
    if (GetParm('new_pat', PARM_STRING)) {
      try {
        $newToken = $this->generateNewToken($request);
      } catch (\Exception $e) {
        $vars['message'] = $e->getMessage();
      }
    }

    $user_pk_to_modify = intval($request->get('user_pk'));
    if (! ($SessionIsAdmin || empty($user_pk_to_modify) ||
      $user_pk == $user_pk_to_modify)) {
      $vars['content'] = _("Your request is not valid.");
      return $this->render('include/base.html.twig', $this->mergeWithDefault($vars));
    }

    $vars = array('refreshUri' => Traceback_uri() . "?mod=" . self::NAME);

    /*
     * If this is a POST (the submit button was clicked), then process the
     * request.
     */
    $BtnText = $request->get('UpdateBtn');
    if (! empty($BtnText)) {
      /* Get the form data to in an associated array */
      $UserRec = $this->CreateUserRec($request, "");

      $rv = $this->UpdateUser($UserRec, $SessionIsAdmin);
      if (empty($rv)) {
        // Successful db update
        $vars['message'] = "User $UserRec[user_name] updated.";

        /* Reread the user record as update verification */
        $UserRec = $this->CreateUserRec($request, $UserRec['user_pk']);
        if ($user_pk == $user_pk_to_modify) {
          $_SESSION['User'] = $UserRec['user_name'];
        }
      } else {
        if (empty($UserRec['user_name']) || $_SESSION['User'] != $UserRec['user_name']) {
          $UserRec = $this->CreateUserRec($request, $UserRec['user_pk']);
        }
        $vars['message'] = $rv;
      }
    } else {
      $NewUserpk = intval($request->get('newuser'));
      $UserRec = empty($NewUserpk) ? $this->CreateUserRec($request, $user_pk) : $this->CreateUserRec($request, $NewUserpk);
    }

    /* display the edit form with the requested user data */
    $vars = array_merge($vars, $this->DisplayForm($UserRec, $SessionIsAdmin));
    $vars['userId'] = $UserRec['user_pk'];
    $vars['newToken'] = $newToken;
    $vars['newClient'] = $newClient;
    $vars['tokenList'] = $this->getListOfActiveTokens();
    $vars['expiredTokenList'] = $this->getListOfExpiredTokens();
    $vars['clientList'] = $this->getListOfActiveClients();
    $vars['revokedClientList'] = $this->getListOfExpiredClients();
    $vars['maxTokenDate'] = $this->authHelper->getMaxTokenValidity();
    $vars['writeAccess'] = ($_SESSION[Auth::USER_LEVEL] >= 3);
    $vars['policyRegex'] = generate_password_policy();
    $vars['policyDisabled'] = "true"; // Form allows empty password for unchanged
    $vars['formName'] = "user_edit";
    $vars['passwordPolicy'] = "";
    $policy = generate_password_policy_string();
    if ($policy != "No policy defined.") {
      $vars['passwordPolicy'] = $policy;
    }
    $restToken = Auth::getRestTokenType();
    if ($restToken == Auth::TOKEN_OAUTH) {
      $restToken = "oauth";
    } elseif ($restToken == Auth::TOKEN_BOTH) {
      $restToken = "both";
    } else {
      $restToken = "token";
    }
    $vars['resttoken'] = $restToken;

    return $this->render('user_edit.html.twig', $this->mergeWithDefault($vars));
  }

  /**
   * \brief Display the user record edit form
   *
   * \param $UserRec - Database users record for the user to be edited.
   * \param $SessionIsAdmin - Boolean: This session is by an admin
   * \return the text of the display form on success, or error on failure.
   */
  private function DisplayForm($UserRec, $SessionIsAdmin)
  {
    global $SysConf;

    $vars = array('isSessionAdmin' => $SessionIsAdmin,
                  'userId' => $UserRec['user_pk']);
    $vars['userDescReadOnly'] = $SysConf['SYSCONFIG']['UserDescReadOnly'];

    /* For Admins, get the list of all users
     * For non-admins, only show themself
     */
    if ($SessionIsAdmin) {
      $stmt = __METHOD__ . '.asSessionAdmin';
      $sql = "SELECT * FROM users ORDER BY user_name";
      $this->dbManager->prepare($stmt, $sql);
      $res = $this->dbManager->execute($stmt);
      $allUsers = array();
      while ($row = $this->dbManager->fetchArray($res)) {
        $allUsers[$row['user_pk']] = htmlentities($row['user_name']);
      }
      $this->dbManager->freeResult($res);
      $vars['allUsers'] = $allUsers;
    }

    $vars['userName'] = $UserRec['user_name'];
    $vars['userDescription'] = $UserRec['user_desc'];
    $vars['userEMail'] = $UserRec["user_email"];
    $vars['eMailNotification'] = ($UserRec['email_notify'] == 'y');

    if ($SessionIsAdmin) {
      $vars['allAccessLevels'] = array(
          PLUGIN_DB_NONE => _("None (very basic, no database access)"),
          PLUGIN_DB_READ => _("Read-only (read, but no writes or downloads)"),
          PLUGIN_DB_WRITE => _("Read-Write (read, download, or edit information)"),
          PLUGIN_DB_CADMIN => _("Clearing Administrator (read, download, edit information and edit decisions)"),
          PLUGIN_DB_ADMIN => _("Full Administrator (all access including adding and deleting users)")
        );
      $vars['accessLevel'] = $UserRec['user_perm'];

      $vars['allUserStatuses'] = array(
        "active" => _("Active"),
        "inactive" => _("Inactive")
      );

      $vars['userStatus'] = $UserRec['user_status'];

      $SelectedFolderPk = $UserRec['root_folder_fk'];
      $vars['folderListOption'] = FolderListOption($ParentFolder = -1, $Depth = 0, $IncludeTop = 1, $SelectedFolderPk);

    }
      $SelectedDefaultFolderPk = $UserRec['default_folder_fk'];
      $vars['folderListOption2'] = FolderListOption($ParentFolder = $UserRec['root_folder_fk'], $Depth = 0, $IncludeTop = 1, $SelectedDefaultFolderPk);

    $vars['isBlankPassword'] = ($UserRec['_blank_pass'] == 'on');
    $vars['agentSelector'] = AgentCheckBoxMake(-1, array("agent_unpack",
      "agent_adj2nest", "wget_agent"), $UserRec['user_name']);
    $vars['bucketPool'] = SelectBucketPool($UserRec["default_bucketpool_fk"]);
    $vars['defaultGroupOption'] = $this->getUserGroupSelect($UserRec);
    $vars['uploadVisibility'] = $UserRec['upload_visibility'];

    return $vars;
  }

  /**
   * \brief Validate and update the user data.
   * \param $UserRec - Database record for the user to be edited.
   *
   * \return NULL on success, string (error text) on failure.
   */
  function UpdateUser($UserRec, $SessionIsAdmin)
  {
    global $PG_CONN;

    $Errors = "";

    /**** Validations ****/
    /* Make sure we have a user_pk */
    if (empty($UserRec['user_pk'])) {
      $Errors .= "<li>" . _("Consistency error (User_pk missing).  Please start over.") . "</li>";
    }

    /* Make sure username looks valid */
    if (empty($UserRec['user_name'])) {
      $Errors .= "<li>" . _("Username must be specified.") . "</li>";
    }

    /* Verify the user_name is not a duplicate  */
    $CheckUserRec = GetSingleRec("users", "WHERE user_name='$UserRec[user_name]'");
    if ((!empty($CheckUserRec)) and ( $CheckUserRec['user_pk'] != $UserRec['user_pk'])) {
      $Errors .= "<li>" . _("Username is not unique.") . "</li>";
    }

    /* Make sure password matches */
    if ($UserRec['_pass1'] != $UserRec['_pass2']) {
      $Errors .= "<li>" . _("Passwords do not match.") . "</li>";
    }

    /* Make sure email looks valid */
    $Check = preg_replace("/[^a-zA-Z0-9@_.+-]/", "", $UserRec['user_email']);
    if ($Check != $UserRec['user_email']) {
      $Errors .= "<li>" . _("Invalid email address.") . "</li>";
    }

    /* Make sure email is unique */
    $email_count = 0;
    if (!empty($UserRec['user_email'])) {
      $email_count = $this->dbManager->getSingleRow(
        "SELECT COUNT(*) as count FROM users WHERE user_email = $1 LIMIT 1;",
        array($UserRec['user_email']))["count"];
    }
    if ($email_count > 0) {
      $Errors .= "<li>" . _("Email address already exists.") . "</li>";
    }

    /* Make sure user can't ask for blank password if policy is enabled */
    if (passwordPolicyEnabled() && !empty($UserRec['_blank_pass'])) {
      $Errors .= "<li>" . _("Password policy enabled, can't have a blank password.") . "</li>";
    }

    /* Did they specify a password and also request a blank password?  */
    if (!empty($UserRec['_blank_pass']) && ( !empty($UserRec['_pass1']) || ! empty($UserRec['_pass2']))) {
      $Errors .= "<li>" . _("You cannot specify both a password and a blank password.") . "</li>";
    }

    /* Make sure password matches policy */
    if (!empty($UserRec['_pass1']) && !empty($UserRec['_pass2'])) {
      $policyRegex = generate_password_policy();
      $result = preg_match('/^' . $policyRegex . '$/m', $UserRec['_pass1']);
      if ($result !== 1) {
        $Errors .= "<li>" . _("Password does not match policy.");
        $Errors .= "<br />" . generate_password_policy_string();
        $Errors .= "</li>";
      }
    }

    /* Check if the user is member of the group */
    if (!empty($UserRec['group_fk'])) {
      $group_map = $this->userDao->getUserGroupMap($UserRec['user_pk']);
      if (array_search($UserRec['group_fk'], array_keys($group_map)) === false) {
        $Errors .= "<li>" . _("User is not member of provided group.") .
          "</li>";
      }
    }

    /* Make sure only admin can change the username */
    if ((!Auth::isAdmin()) && ($UserRec['user_name'] != $_SESSION['User'])) {
      $Errors .= "<li>" . _("Only admin can change the username.") . "</li>";
    }

    /* If we have any errors, return them */
    if (!empty($Errors)) {
      return _("Errors") . ":<ol>$Errors </ol>";
    }

    /**** Update the users database record ****/
    /* First remove user_pass and user_seed if the password wasn't changed. */
    if (!empty($UserRec['_blank_pass']) ) {
      $UserRec['user_seed'] = '';
      $options = array('cost' => 10);
      $UserRec['user_pass'] = password_hash("", PASSWORD_DEFAULT, $options);
    } else if (empty($UserRec['_pass1'])) { // password wasn't changed
      unset( $UserRec['user_pass']);
      unset( $UserRec['user_seed']);
    }

    /* Build the sql update */
    $sql = "UPDATE users SET ";
    $first = true;
    foreach ($UserRec as $key=>$val) {
      if ($key[0] == '_' || $key == "user_pk") {
        continue;
      }
      if (!$SessionIsAdmin && ($key == "user_perm" || $key == "root_folder_fk" || $key == "user_status")) {
        continue;
      }
      if (!$first) {
        $sql .= ",";
      }
      $sql .= "$key='" . pg_escape_string($val) . "'";
      $first = false;
    }
    $sql .= " WHERE user_pk=$UserRec[user_pk]";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    return (null);
  } // UpdateUser()

  /**
   * \brief Get a user record
   * \param $user_pk  fetch this users db record
   *
   * \return users db record
   */
  function GetUserRec($user_pk)
  {
    if (empty($user_pk)) {
      throw new Exception("Invalid access.  Your session has expired.",1);
    }

    $UserRec = GetSingleRec("users", "WHERE user_pk=$user_pk");
    if (empty($UserRec)) {
      throw new Exception("Invalid user. ",1);
    }
    return $UserRec;
  }

  /**
   * \brief Determine if the session user is an admin
   *
   * \return TRUE if the session user is an admin.  Otherwise, return FALSE
   */
  function IsSessionAdmin($UserRec)
  {
    return ($UserRec['user_perm'] == PLUGIN_DB_ADMIN);
  }

  /**
   * \brief Create a user record.
   * \param integer $user_pk: If empty, use form data
   *
   * \return A user record in the same associated array format that you get from a pg_fetch_assoc().
   *         However, there may be additional fields from the data input form that are not in the
   *         users table.  These additional fields start with an underscore (_pass1, _pass2, _blank_pass)
   *         that come from the edit form.
   */
  function CreateUserRec(Request $request, $user_pk="")
  {
    /* If a $user_pk was given, use it to read the user db record.
     * Otherwise, use the form data.
     */
    if (!empty($user_pk)) {
      $UserRec = $this->GetUserRec($user_pk);
      $UserRec['_pass1'] = "";
      $UserRec['_pass2'] = "";
      $UserRec['_blank_pass'] = password_verify('', $UserRec['user_pass']) ? "on" : "";
    } else {
      $UserRec = array();
      $UserRec['user_pk'] = intval($request->get('user_pk'));
      $UserRec['user_name'] = stripslashes($request->get('user_name'));
      $UserRec['root_folder_fk'] = intval($request->get('root_folder_fk'));
      $UserRec['upload_visibility'] = stripslashes($request->get('public'));
      $UserRec['default_folder_fk'] = intval($request->get('default_folder_fk'));
      $UserRec['user_desc'] = stripslashes($request->get('user_desc'));
      $defaultGroup = $request->get('default_group_fk', null);
      if ($defaultGroup !== null) {
        $UserRec['group_fk'] = intval($defaultGroup);
      }

      $UserRec['_pass1'] = stripslashes($request->get('_pass1'));
      $UserRec['_pass2'] = stripslashes($request->get('_pass2'));
      if (!empty($UserRec['_pass1'])) {
        $UserRec['user_seed'] = 'Seed';
        $options = array('cost' => 10);
        $UserRec['user_pass'] = password_hash($UserRec['_pass1'], PASSWORD_DEFAULT, $options);
        $UserRec['_blank_pass'] = "";
      } else {
        $UserRec['user_pass'] = "";
        $UserRec['_blank_pass'] = stripslashes($request->get("_blank_pass"));
        if (empty($UserRec['_blank_pass'])) { // check for blank password
          $StoredUserRec = $this->GetUserRec($UserRec['user_pk']);
          $options = array('cost' => 10);
          $UserRec['_blank_pass'] = password_verify($StoredUserRec['user_pass'], password_hash("", PASSWORD_DEFAULT, $options)) ? "on" : "";
        }
      }

      $UserRec['user_perm'] = intval($request->get('user_perm'));
      $UserRec['user_status'] = stripslashes($request->get('user_status'));
      $UserRec['user_email'] = stripslashes($request->get('user_email'));
      $UserRec['email_notify'] = stripslashes($request->get('email_notify'));
      if (!empty($UserRec['email_notify'])) {
        $UserRec['email_notify'] = 'y';
      }
      $UserRec['user_agent_list'] = is_null($request->get('user_agent_list')) ? userAgents() : $request->get('user_agent_list');
      $UserRec['default_bucketpool_fk'] = intval($request->get("default_bucketpool_fk"));
    }
    return $UserRec;
  }

  /**
   * Generate new token based on the request sent by user.
   *
   * @param Request $request
   * @return string The new token if no error occured.
   * @throws \UnexpectedValueException Throws an exception if the request is
   * @throws DuplicateTokenKeyException
   * @uses Fossology::UI::Api::Helper::RestHelper::validateTokenRequest()
   * @uses Fossology::UI::Api::Helper::DbHelper::insertNewTokenKey()
   */
  function generateNewToken(Request $request)
  {
    global $container;

    $user_pk = Auth::getUserId();
    $tokenName = $request->get('pat_name');
    $tokenExpiry = $request->get('pat_expiry');
    if ($_SESSION[Auth::USER_LEVEL] < 3) {
      $tokenScope = 'r';
    } else {
      $tokenScope = $request->get('pat_scope');
    }
    $tokenScope = array_search($tokenScope, RestHelper::SCOPE_DB_MAP);
    /** @var RestHelper $restHelper */
    $restHelper = $container->get('helper.restHelper');
    try {
      $restHelper->validateTokenRequest($tokenExpiry, $tokenName, $tokenScope);
    } catch (HttpBadRequestException $e) {
      throw new \UnexpectedValueException($e->getMessage());
    }

    /** @var DbHelper $restDbHelper */
    $restDbHelper = $container->get('helper.dbHelper');
    $key = bin2hex(
      openssl_random_pseudo_bytes(RestHelper::TOKEN_KEY_LENGTH / 2));
    try {
      $jti = $restDbHelper->insertNewTokenKey($user_pk, $tokenExpiry,
        RestHelper::SCOPE_DB_MAP[$tokenScope], $tokenName, $key);
    } catch (DuplicateTokenKeyException $e) {
      // Key already exists, try again.
      $key = bin2hex(
        openssl_random_pseudo_bytes(RestHelper::TOKEN_KEY_LENGTH / 2));
      try {
        $jti = $restDbHelper->insertNewTokenKey($user_pk, $tokenExpiry,
          RestHelper::SCOPE_DB_MAP[$tokenScope], $tokenName, $key);
      } catch (DuplicateTokenKeyException $e) {
        // New key also failed, give up!
        throw new DuplicateTokenKeyException("Please try again later.");
      }
    } catch (DuplicateTokenNameException $e) {
      throw new \UnexpectedValueException($e->getMessage());
    }
    return $this->authHelper->generateJwtToken($tokenExpiry,
      $jti['created_on'], $jti['jti'], $tokenScope, $key);
  }

  /**
   * @brief Get a list of active tokens for current user.
   *
   * Fetches the tokens for current user from DB and format it for twig
   * template. Also check if the token is expired.
   * @return array
   */
  function getListOfActiveTokens()
  {
    $user_pk = Auth::getUserId();
    $sql = "SELECT pat_pk, user_fk, expire_on, token_scope, token_name, created_on, active " .
           "FROM personal_access_tokens " .
           "WHERE user_fk = $1 AND active = true AND client_id IS NULL;";
    $rows = $this->dbManager->getRows($sql, [$user_pk],
      __METHOD__ . ".getActiveTokens");
    $response = [];
    foreach ($rows as $row) {
      if ($this->authHelper->isTokenActive($row, $row["pat_pk"]) === true) {
        $entry = [
          "id" => $row["pat_pk"] . "." . $user_pk,
          "name" => $row["token_name"],
          "created" => $row["created_on"],
          "expire" => $row["expire_on"],
          "scope" => $row["token_scope"]
        ];
        $response[] = $entry;
      }
    }
    array_multisort(array_column($response, "created"), SORT_ASC, $response);
    return $response;
  }

  /**
   * Get a list of expired tokens for current user.
   * @return array
   */
  function getListOfExpiredTokens()
  {
    $user_pk = Auth::getUserId();
    $retentionPeriod = $this->getMaxExpiredTokenRetentionPeriod();
    $sql = "SELECT pat_pk, user_fk, expire_on, token_scope, token_name, created_on " .
      "FROM personal_access_tokens " .
      "WHERE user_fk = $1 AND active = false " .
      "AND expire_on >= (SELECT CURRENT_DATE - ($2)::integer) " .
      "AND client_id IS NULL;";
    $rows = $this->dbManager->getRows($sql, [$user_pk, $retentionPeriod],
      __METHOD__ . ".getExpiredTokens");
    $response = [];
    foreach ($rows as $row) {
      $entry = [
        "id" => $row["pat_pk"] . "." . $user_pk,
        "name" => $row["token_name"],
        "created" => $row["created_on"],
        "expire" => $row["expire_on"],
        "scope" => $row["token_scope"]
      ];
      $response[] = $entry;
    }
    array_multisort(array_column($response, "created"), SORT_ASC, $response);
    return $response;
  }

  /**
   * Generate the HTML option list of groups for the user
   * @param array $userRec User record being updated
   * @return string HTML option list
   */
  private function getUserGroupSelect($userRec)
  {
    $groups = $this->userDao->getUserGroupMap($userRec['user_pk']);
    $userDefaults = $this->userDao->getUserAndDefaultGroupByUserName($userRec['user_name']);
    $options = "";
    foreach ($groups as $groupId => $groupName) {
      $options .= "<option value='$groupId' ";
      if ($groupId == $userDefaults['group_fk']) {
        $options .= "selected='selected'";
      }
      $options .= ">$groupName</option>";
    }
    return $options;
  }

  /**
   * Add new oauth client to user.
   *
   * @param Request $request
   * @throws \UnexpectedValueException Throws an exception if the request is
   *         not valid.
   * @return boolean True if no error occured.
   * @uses Fossology::UI::Api::Helper::RestHelper::validateNewOauthClient()
   * @uses Fossology::UI::Api::Helper::DbHelper::addNewClient()
   */
  private function addNewClient(Request $request)
  {
    global $container;

    $user_pk = Auth::getUserId();
    $clientName = GetParm('client_name', PARM_STRING);
    $clientId = GetParm('client_id', PARM_STRING);
    if ($_SESSION[Auth::USER_LEVEL] < 3) {
      $clientScope = 'r';
    } else {
      $clientScope = GetParm('client_scope', PARM_STRING);
    }
    /** @var RestHelper $restHelper */
    $restHelper = $container->get('helper.restHelper');
    $isTokenRequestValid = $restHelper->validateNewOauthClient($user_pk,
      $clientName, $clientScope, $clientId);

    if ($isTokenRequestValid !== true) {
      throw new \UnexpectedValueException($isTokenRequestValid->getMessage());
    } else {
      $restHelper->getDbHelper()->addNewClient($clientName, $user_pk,
        $clientId, $clientScope);
      return "Client \"$clientName\" added with ID \"$clientId\"";
    }
  }

  /**
   * @brief Get a list of active clients for current user.
   *
   * Fetches the clients for current user from DB and format it for twig
   * template.
   * @return array
   */
  private function getListOfActiveClients()
  {
    $user_pk = Auth::getUserId();
    $sql = "SELECT pat_pk, user_fk, token_scope, token_name, " .
           "created_on, active, client_id " .
           "FROM personal_access_tokens " .
           "WHERE user_fk = $1 AND active = true AND token_key IS NULL;";
    $rows = $this->dbManager->getRows($sql, [$user_pk],
      __METHOD__ . ".getActiveClients");
    $response = [];
    foreach ($rows as $row) {
      $entry = [
        "id" => $row["pat_pk"] . "." . $user_pk,
        "name" => $row["token_name"],
        "created" => $row["created_on"],
        "clientid" => $row["client_id"],
        "scope" => $row["token_scope"]
      ];
      $response[] = $entry;
    }
    array_multisort(array_column($response, "created"), SORT_ASC, $response);
    return $response;
  }

  /**
   * Get a list of revoked clients for current user.
   * @return array
   */
  private function getListOfExpiredClients()
  {
    $user_pk = Auth::getUserId();
    $sql = "SELECT pat_pk, user_fk, token_scope, token_name, " .
           "created_on, active, client_id " .
           "FROM personal_access_tokens " .
           "WHERE user_fk = $1 AND active = false AND token_key IS NULL;";
    $rows = $this->dbManager->getRows($sql, [$user_pk],
      __METHOD__ . ".getRevokedClients");
    $response = [];
    foreach ($rows as $row) {
      $entry = [
        "id" => $row["pat_pk"] . "." . $user_pk,
        "name" => $row["token_name"],
        "created" => $row["created_on"],
        "clientid" => $row["client_id"],
        "scope" => $row["token_scope"]
      ];
      $response[] = $entry;
    }
    array_multisort(array_column($response, "created"), SORT_ASC, $response);
    return $response;
  }

  /**
   * @brief getMaxExpiredTokenRetentionPeriod() get the refresh time from DB.
   * @Returns number of days to retain expired token.
   **/
  public function getMaxExpiredTokenRetentionPeriod()
  {
    global $SysConf;
    return $SysConf['SYSCONFIG']['PATMaxPostExpiryRetention'];
  } /* getMaxExpiredTokenRetentionPeriod() */
}
register_plugin(new UserEditPage());
