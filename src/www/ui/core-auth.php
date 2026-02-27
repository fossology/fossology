<?php
/*
 SPDX-FileCopyrightText: © 2008-2013 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015-2016, 2021 Siemens AG
 SPDX-FileCopyrightText: © 2020 Robert Bosch GmbH
 SPDX-FileCopyrightText: © Dineshkumar Devarajan <Devarajan.Dineshkumar@in.bosch.com>
 SPDX-FileCopyrightText: © 2021-2022 Orange
 Contributors: Piotr Pszczola, Bartlomiej Drozdz

 SPDX-License-Identifier: GPL-2.0-only
*/

use Fossology\Lib\Auth\Auth;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\UI\Component\Menu;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\RedirectResponse;
use League\OAuth2\Client\Provider\GenericProvider;

define("TITLE_CORE_AUTH", _("Login"));

class core_auth extends FO_Plugin
{
  public static $origReferer;
  /** @var DbManager */
  private $dbManager;
  /** @var UserDao */
  private $userDao;
  /** @var Session */
  private $session;
  /** @var External Authentication */
  private $authExternal;

  function __construct()
  {
    $this->Name = "auth";
    $this->Title = TITLE_CORE_AUTH;
    $this->PluginLevel = 1000; /* make this run first! */
    $this->LoginFlag = 0;
    parent::__construct();

    global $container;
    $this->dbManager = $container->get("db.manager");
    $this->userDao = $container->get('dao.user');
    $this->session = $container->get('session');
    $this->authExternal = auth_external_check();
  }

  /**
   * @brief getter to retreive value of static var
   */
  public function staticValue()
  {
    return self::$origReferer;
  }

  /**
   * \brief Only used during installation.
   * This may be called multiple times.
   * Used to ensure the DB has the right default columns.
   *
   * \return 0 on success, non-zero on failure.
   */
  function Install()
  {
    return $this->userDao->updateUserTable();
  }

  /**
   * \brief This is where the magic for
   * Authentication happens.
   */
  function PostInitialize()
  {
    global $SysConf;

    /* if Site Minder enabled core-auth will be disabled*/
    if (siteminder_check() != -1) {
      return (0);
    }

    if (!$this->session->isStarted()) {
      $this->session->setName('Login');
      $this->session->start();
    }

    //--------- Authentification external connection for auto-login-----------
    if ($this->authExternal !== false && $this->authExternal['useAuthExternal']) {
      $this->checkUsernameAndPassword($this->authExternal['loginAuthExternal'], $this->authExternal['passwordAuthExternal']);
    }

    if (array_key_exists('selectMemberGroup', $_POST)) {
      $selectedGroupId = intval($_POST['selectMemberGroup']);
      $this->userDao->setDefaultGroupMembership(intval($_SESSION[Auth::USER_ID]), $selectedGroupId);
      $_SESSION[Auth::GROUP_ID] = $selectedGroupId;
      $this->session->set(Auth::GROUP_ID, $selectedGroupId);
      $SysConf['auth'][Auth::GROUP_ID] = $selectedGroupId;
    }

    if (array_key_exists(Auth::USER_ID, $_SESSION)) {
      $SysConf['auth'][Auth::USER_ID] = $_SESSION[Auth::USER_ID];
    }
    if (array_key_exists(Auth::GROUP_ID, $_SESSION)) {
      $SysConf['auth'][Auth::GROUP_ID] = $_SESSION[Auth::GROUP_ID];
    }

    $Now = time();
    /* Logins older than 60 secs/min * 480 min = 8 hr are auto-logout */
    if (!empty($_SESSION['time']) && @$_SESSION['time'] + (60 * 480) < $Now) {
      $this->updateSession("");
    }

    $_SESSION['time'] = $Now;
    if (empty($_SESSION['ip'])) {
      $_SESSION['ip'] = $this->getIP();
    } else if ((@$_SESSION['checkip'] == 1) && (@$_SESSION['ip'] != $this->getIP())) {
      /* Sessions are not transferable. */
      $this->updateSession("", true);
      $_SESSION['ip'] = $this->getIP();
    }

    if (@$_SESSION[Auth::USER_NAME]) {
      /* Recheck the user in case he is suddenly blocked or changed. */
      if (empty($_SESSION['time_check'])) {
        $_SESSION['time_check'] = time() + (480 * 60);
      }
      if (time() >= @$_SESSION['time_check']) {
        $userName = @$_SESSION[Auth::USER_NAME];
        $row = $this->userDao->getUserAndDefaultGroupByUserName($userName, @$_SESSION['oauthCheck']);
        /* Check for instant logouts */
        if (empty($row['user_pass'])) {
          $row = "";
        }
        $this->updateSession($row);
      }
    } else {
      $this->updateSession("", true);
    }

    /* Disable all plugins with >= level access */
    plugin_disable($_SESSION[Auth::USER_LEVEL]);
    $this->State = PLUGIN_STATE_READY;
  } // GetIP()

  /**
   * \brief Set $_SESSION and $SysConf user variables
   * \param $UserRow users table row, if empty, use Default User
   * \return void, updates globals $_SESSION and $SysConf[auth][UserId] variables
   * \oauth variable if false then update vice versa.
   */
  function updateSession($userRow, $oauth=false)
  {
    global $SysConf;

    if (empty($userRow)) {
      $username = 'Default User';
      $userRow = $this->userDao->getUserAndDefaultGroupByUserName($username);
    }

    $_SESSION[Auth::USER_ID] = $userRow['user_pk'];
    $SysConf['auth'][Auth::USER_ID] = $userRow['user_pk'];
    $this->session->set(Auth::USER_ID, $userRow['user_pk']);
    $_SESSION[Auth::USER_NAME] = $userRow['user_name'];
    $this->session->set(Auth::USER_NAME, $userRow['user_name']);
    $_SESSION['Folder'] = $userRow['root_folder_fk'];
    $_SESSION[Auth::USER_LEVEL] = $userRow['user_perm'];
    $this->session->set(Auth::USER_LEVEL, $userRow['user_perm']);
    $_SESSION['UserEmail'] = $userRow['user_email'];
    $_SESSION['UserEnote'] = $userRow['email_notify'];
    $_SESSION[Auth::GROUP_ID] = $userRow['group_fk'];
    $SysConf['auth'][Auth::GROUP_ID] = $userRow['group_fk'];
    $this->session->set(Auth::GROUP_ID, $userRow['group_fk']);
    $_SESSION['GroupName'] = $userRow['group_name'];
    if (!$oauth) {
      $_SESSION['oauthCheck'] = $userRow['oauth'];
    }
    if (array_key_exists(Menu::BANNER_COOKIE, $_COOKIE)) {
      $_COOKIE[Menu::BANNER_COOKIE] = 0;
    }
    setcookie(Menu::BANNER_COOKIE, "", time() - 3600);
  }

  /**
   * \brief Retrieve the user's IP address.
   * Some proxy systems pass forwarded IP address info.
   * This ensures that someone who steals the cookie won't
   * gain access unless they come from the same IP.
   */
  function getIP()
  {
    /* NOTE: This can be easily defeated wtih fake HTTP headers. */
    $Vars = array('HTTP_CLIENT_IP', 'HTTP_X_COMING_FROM', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED');
    foreach ($Vars as $V) {
      if (!empty($_SERVER[$V])) {
        return ($_SERVER[$V]);
      }
    }
    return (@$_SERVER['REMOTE_ADDR']);
  }

  /**
   * \brief This is only called when the user logs out.
   */
  public function Output($fromRest=false)
  {
    global $SysConf;
    if (! $fromRest) {
      $this->vars['loginProvider'] = "password";
      if (array_key_exists('AUTHENTICATION', $SysConf) &&
        array_key_exists('provider', $SysConf['AUTHENTICATION'])) {
        $this->vars['loginProvider'] = $SysConf['AUTHENTICATION']['provider'];
      }

      $userName = GetParm("username", PARM_TEXT);
      $password = GetParm("password", PARM_TEXT);
      $timezone = GetParm("timezone", PARM_TEXT);
      if (empty($timezone) || strpos($timezone,"Unknown") == true) {
        $timezone = date_default_timezone_get();
      }
      $_SESSION['timezone'] = $timezone;
      $referrer = GetParm("HTTP_REFERER", PARM_TEXT);
      $getEmail = "";
      $providerCheck = GetParm("providerCheck", PARM_TEXT);
    }
    $proxy = "";
    if (array_key_exists('http_proxy', $SysConf['FOSSOLOGY']) &&
        ! empty($SysConf['FOSSOLOGY']['http_proxy'])) {
      $proxy = $SysConf['FOSSOLOGY']['http_proxy'];
    }
    if (array_key_exists('https_proxy', $SysConf['FOSSOLOGY']) &&
        ! empty($SysConf['FOSSOLOGY']['https_proxy'])) {
      $proxy = $SysConf['FOSSOLOGY']['https_proxy'];
    }

    if ((! empty($providerCheck)) || $fromRest) {
      $provider = new GenericProvider([
        "clientId"                => $SysConf['SYSCONFIG']['OidcAppId'],
        "clientSecret"            => $SysConf['SYSCONFIG']['OidcSecret'],
        "redirectUri"             => $SysConf['SYSCONFIG']['OidcRedirectURL'],
        "urlAuthorize"            => $SysConf['SYSCONFIG']['OidcAuthorizeURL'],
        "urlAccessToken"          => $SysConf['SYSCONFIG']['OidcAccessTokenURL'],
        "urlResourceOwnerDetails" => $SysConf['SYSCONFIG']['OidcResourceURL'],
        "proxy"                   => $proxy
      ]);
      $authorizationUrl = $provider->getAuthorizationUrl([
        "scope" => ['email openid']
      ]);
      if ($fromRest) {
        return $authorizationUrl;
      }
      $_SESSION['oauth2state'] = $provider->getState();
      $_SESSION['HTTP_REFERER'] = $referrer;
      header('Location: ' . $authorizationUrl);
      exit;
    }

    if (empty($referrer) && array_key_exists('HTTP_REFERER', $_SESSION)) {
      $referrer = $_SESSION['HTTP_REFERER'];
    } else if (empty($referrer)) {
      $referrer = GetArrayVal('HTTP_REFERER', $_SERVER);
    }

    if (array_key_exists("oauthemail", $_SESSION)) {
      $getEmail = $_SESSION['oauthemail'];
      unset($_SESSION['oauthemail']);
    }
    $referrerQuery = parse_url($referrer,PHP_URL_QUERY);
    if ($referrerQuery) {
      $params = array();
      parse_str($referrerQuery,$params);
      if (array_key_exists('mod', $params) && $params['mod'] == $this->Name) {
        $referrer = Traceback_uri();
      }
    }
    if (!empty($getEmail) && empty($userName)) {
      $validLogin = $this->checkUsernameAndPassword($getEmail, "", true);
    } else {
      $validLogin = $this->checkUsernameAndPassword($userName, $password);
    }
    if ($validLogin) {
      if (empty($referrer)) {
        if (plugin_find_id('browse') < 0) {
          $newReferrer = Traceback_uri() . '?mod=' . 'browse' . '&oauth=true';
        }
        return new RedirectResponse($newReferrer);
      } else {
        return new RedirectResponse($referrer);
      }
    }

    $initPluginId = plugin_find_id("init");
    if ($initPluginId >= 0) {
      global $Plugins;
      $this->vars['info'] = $Plugins[$initPluginId]->infoFirstTimeUsage();
    }

    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "off") {
      $this->vars['protocol'] = "HTTPS";
    } else {
      $this->vars['protocol'] = preg_replace("@/.*@", "", @$_SERVER['SERVER_PROTOCOL']);
    }

    $this->vars['referrer'] = $referrer;
    $this->vars['loginFailure'] = !empty($userName) || !empty($password);
    if (!empty($userName) && $userName!='Default User') {
      $this->vars['userName'] = $userName;
    }
    if (!empty($SysConf['SYSCONFIG']['OidcAppName'])) {
      $this->vars['providerExist'] = $SysConf['SYSCONFIG']['OidcAppName'];
    } else {
      $this->vars['providerExist'] = 0;
    }
    return $this->render('login.html.twig',$this->vars);
  }

  /**
   * @brief perform logout
   */
  function OutputOpen()
  {
    if (array_key_exists('User', $_SESSION) && $_SESSION['User'] != "Default User") {
      global $SysConf;
      if (!empty($SysConf['SYSCONFIG']['OidcLogoutURL'])) {
        $uri = $SysConf['SYSCONFIG']['OidcLogoutURL'];
      } else {
        $uri = Traceback_uri();
      }
      $this->updateSession("");
      $_SESSION['oauth2state'] = "";
      header("Location: $uri");
      exit;
    }
    parent::OutputOpen();
  }

  /**
   * See if a username/password is valid.
   * @param string $userName
   * @param string $password
   * @param boolean $oauth   Request from oauth login
   * @param boolean $isRest  Request from API token
   * @return boolean
   */
  function checkUsernameAndPassword($userName, $password, $oauth=false,
    $isRest=false)
  {
    global $SysConf;

    $user_exists = true;
    $options = array('cost' => 10);

    /* Check the user for external authentication */
    if ($this->authExternal !== false && $this->authExternal['useAuthExternal']) {
      $username = $this->authExternal['loginAuthExternal'];
      /* checking if user exists */
      try {
        $this->userDao->getUserAndDefaultGroupByUserName($username);
      } catch (Exception $e) {
        $user_exists=false;
      }
      if (! $user_exists && $GLOBALS['SysConf']['EXT_AUTH']['CONF_EXT_AUTH_NEW_USER_AUTO_CREATE']) {
        /* If user does not exist then we create it */
        $User = trim(str_replace("'", "''", $this->authExternal['loginAuthExternal']));
        $Pass = $this->authExternal['passwordAuthExternal'] ;
        $Hash = password_hash($Pass, PASSWORD_DEFAULT, $options);
        $Desc = $this->authExternal['descriptionAuthExternal'];
        $Perm = 3;
        $Folder = 1;
        $Email_notify = "y";
        $Email = $this->authExternal['emailAuthExternal'];
        /* Set default list of agents when a new user is created */
        $agentList = $GLOBALS['SysConf']['EXT_AUTH']['CONF_EXT_AUTH_NEW_USER_AGENT_LIST'];
        add_user($User, $Desc, $Hash, $Perm, $Email, $Email_notify,
          $GLOBALS['SysConf']['SYSCONFIG']['UploadVisibility'], $agentList, $Folder);
      }
    }

    $authProvider = "password";
    if (array_key_exists('AUTHENTICATION', $SysConf) &&
      array_key_exists('provider', $SysConf['AUTHENTICATION'])) {
        $authProvider = $SysConf['AUTHENTICATION']['provider'];
    }

    if (empty($userName) || $userName == 'Default User') {
      return false;
    }
    try {
      $row = $this->userDao->getUserAndDefaultGroupByUserName($userName, $oauth);
    } catch (Exception $e) {
      return false;
    }

    if (empty($row['user_name'])) {
      return false;
    }

    if (! $oauth) {
      if (!$isRest && $authProvider != "password") {
        // Password authentication is not allowed and request if not from REST
        // API
        return false;
      }
      /* Check the password -- only if a password exists */
      if (! empty($row['user_pass'])) {
        $options = array('cost' => 10);
        /* Check if the password matches by password_verify */
        if (password_verify($password, $row['user_pass'])) {
          if (password_needs_rehash($row['user_pass'], PASSWORD_DEFAULT, $options)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT, $options);
            /* Update old hash with new hash */
            update_password_hash($userName, $newHash);
          }
        } else if (! empty($row['user_seed'])) {
          $passwordHash = sha1($row['user_seed'] . $password);
          /* If verify with new hash fails check with the old hash */
          if (strcmp($passwordHash, $row['user_pass']) == 0) {
            $newHash = password_hash($password, PASSWORD_DEFAULT, $options);
            /* Update old hash with new hash */
            update_password_hash($userName, $newHash);
          } else {
            return false;
          }
        }
      } else if (!empty($password)) {
        /* empty password required */
        return false;
      }
    }

    if (!$this->userDao->isUserActive($userName)) {
      /* user not active */
      $this->vars['userInactive'] = true;
      return false;
    }

    /* If you make it here, then username and password were good! */
    $this->updateSession($row);

    $_SESSION['time_check'] = time() + (480 * 60);
    /* No specified permission means ALL permission */
    if ("X" . $row['user_perm'] == "X") {
      $_SESSION[Auth::USER_LEVEL] = PLUGIN_DB_ADMIN;
    } else {
      $_SESSION[Auth::USER_LEVEL] = $row['user_perm'];
    }
    $_SESSION['checkip'] = GetParm("checkip", PARM_STRING);
    /* Check for the no-popup flag */
    if (GetParm("nopopup", PARM_INTEGER) == 1) {
      $_SESSION['NoPopup'] = 1;
    } else {
      $_SESSION['NoPopup'] = 0;
    }

    $this->userDao->updateUserLastConnection($row['user_pk']);

    return true;
  }
}

$NewPlugin = new core_auth();
