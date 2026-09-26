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
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\CsrfToken;

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
  /**
   * @var \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface
   */
  private $csrfTokenManager;

  function __construct()
  {
    $this->Name = "auth";
    $this->Title = TITLE_CORE_AUTH;
    $this->PluginLevel = 1000; /* make this run first! */$this->LoginFlag = 0;
    parent::__construct();

    global $container;
    $this->dbManager =$container->get("db.manager");
    $this->userDao = $container->get('dao.user');$this->session = $container->get('session');$this->authExternal = auth_external_check();

    if ($container->has('security.csrf.token_manager')) {
      $this->csrfTokenManager =$container->get('security.csrf.token_manager');
    } else {
      $this->csrfTokenManager = new CsrfTokenManager();
    }
  }

  public function staticValue()
  {
    return self::$origReferer;
  }

  function Install()
  {
    return $this->userDao->updateUserTable();
  }

  function PostInitialize()
  {
    global $SysConf;

    if (siteminder_check() != -1) {
      return (0);
    }

    if (!$this->session->isStarted()) {
      $this->session->setName('Login');$this->session->start();
    }

    if ($this->authExternal !== false && $this->authExternal['useAuthExternal']) {$this->checkUsernameAndPassword($this->authExternal['loginAuthExternal'],$this->authExternal['passwordAuthExternal']);
    }

    if (array_key_exists('selectMemberGroup', $_POST)) {$selectedGroupId = intval($_POST['selectMemberGroup']);$this->userDao->setDefaultGroupMembership(intval($_SESSION[Auth::USER_ID]),$selectedGroupId);
      $_SESSION[Auth::GROUP_ID] =$selectedGroupId;
      $this->session->set(Auth::GROUP_ID,$selectedGroupId);
      $SysConf['auth'][Auth::GROUP_ID] =$selectedGroupId;
    }

    if (array_key_exists(Auth::USER_ID, $_SESSION)) {
      $SysConf['auth'][Auth::USER_ID] =$_SESSION[Auth::USER_ID];
    }
    if (array_key_exists(Auth::GROUP_ID, $_SESSION)) {
      $SysConf['auth'][Auth::GROUP_ID] =$_SESSION[Auth::GROUP_ID];
    }

    $Now = time();
    if (!empty($_SESSION['time']) && @$_SESSION['time'] + (60 * 480) < $Now) {$this->updateSession("");
    }

    $_SESSION['time'] =$Now;
    if (empty($_SESSION['ip'])) {
      $_SESSION['ip'] =$this->getIP();
    } else if ((@$_SESSION['checkip'] == 1) && (@$_SESSION['ip'] != $this->getIP())) {$this->updateSession("", true);
      $_SESSION['ip'] =$this->getIP();
    }

    if (@$_SESSION[Auth::USER_NAME]) {
      if (empty($_SESSION['time_check'])) {$_SESSION['time_check'] = time() + (480 * 60);
      }
      if (time() >= @$_SESSION['time_check']) {
        $userName = @$_SESSION[Auth::USER_NAME];
        $row =$this->userDao->getUserAndDefaultGroupByUserName($userName, @$_SESSION['oauthCheck']);
        if (empty($row['user_pass'])) {$row = "";
        }
        $this->updateSession($row);
      }
    } else {
      $this->updateSession("", true);
    }

    plugin_disable($_SESSION[Auth::USER_LEVEL]);$this->State = PLUGIN_STATE_READY;
  }

  function updateSession($userRow,$oauth=false)
  {
    global $SysConf;

    if (empty($userRow)) {
      $username = 'Default User';$userRow = $this->userDao->getUserAndDefaultGroupByUserName($username);
    }

    $_SESSION[Auth::USER_ID] =$userRow['user_pk'];
    $SysConf['auth'][Auth::USER_ID] =$userRow['user_pk'];
    $this->session->set(Auth::USER_ID,$userRow['user_pk']);
    $_SESSION[Auth::USER_NAME] =$userRow['user_name'];
    $this->session->set(Auth::USER_NAME,$userRow['user_name']);
    $_SESSION['Folder'] =$userRow['root_folder_fk'];
    $_SESSION[Auth::USER_LEVEL] =$userRow['user_perm'];
    $this->session->set(Auth::USER_LEVEL,$userRow['user_perm']);
    $_SESSION['UserEmail'] =$userRow['user_email'];
    $_SESSION['UserEnote'] =$userRow['email_notify'];
    $_SESSION[Auth::GROUP_ID] =$userRow['group_fk'];
    $SysConf['auth'][Auth::GROUP_ID] =$userRow['group_fk'];
    $this->session->set(Auth::GROUP_ID,$userRow['group_fk']);
    $_SESSION['GroupName'] =$userRow['group_name'];
    if (!$oauth) {
      $_SESSION['oauthCheck'] =$userRow['oauth'];
    }
    if (array_key_exists(Menu::BANNER_COOKIE, $_COOKIE)) {$_COOKIE[Menu::BANNER_COOKIE] = 0;
    }
    setcookie(Menu::BANNER_COOKIE, "", time() - 3600);
  }

  function getIP()
  {
    $Vars = array('HTTP_CLIENT_IP', 'HTTP_X_COMING_FROM', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED');
    foreach ($Vars as$V) {
      if (!empty($_SERVER[$V])) {
        return ($_SERVER[$V]);
      }
    }
    return (@$_SERVER['REMOTE_ADDR']);
  }

  public function Output()
  {
    global $SysConf;

    $action = GetParm("action", PARM_TEXT);

    if ($action === "forgot-password") {
      return $this->handleForgotPassword();
    } elseif ($action === "reset-password") {
      return $this->handleResetPassword();
    }

    $this->vars['loginProvider'] = "password";
    if (array_key_exists('AUTHENTICATION', $SysConf) &&
      array_key_exists('provider', $SysConf['AUTHENTICATION'])) {
      $this->vars['loginProvider'] =$SysConf['AUTHENTICATION']['provider'];
    }
    $this->vars['extAuthEnabled'] = ($this->authExternal !== false && !empty($this->authExternal['useAuthExternal']));

    $userName = GetParm("username", PARM_TEXT);
    $password = GetParm("password", PARM_TEXT);
    $timezone = GetParm("timezone", PARM_TEXT);
    if (empty($timezone) \vert{}\vert{} strpos($timezone,"Unknown") == true) {
      $timezone = date_default_timezone_get();
    }
    $_SESSION['timezone'] =$timezone;
    $referrer = GetParm("HTTP_REFERER", PARM_TEXT);
    $getEmail = "";
    $providerCheck = GetParm("providerCheck", PARM_TEXT);
    $proxy = "";
    if (array_key_exists('http_proxy', $SysConf['FOSSOLOGY']) &&
        ! empty($SysConf['FOSSOLOGY']['http_proxy'])) {
      $proxy =$SysConf['FOSSOLOGY']['http_proxy'];
    }
    if (array_key_exists('https_proxy', $SysConf['FOSSOLOGY']) &&
        ! empty($SysConf['FOSSOLOGY']['https_proxy'])) {
      $proxy =$SysConf['FOSSOLOGY']['https_proxy'];
    }

    if (! empty($providerCheck)) {$provider = new GenericProvider([
        "clientId"                => $SysConf['SYSCONFIG']['OidcAppId'],
        "clientSecret"            => $SysConf['SYSCONFIG']['OidcSecret'],
        "redirectUri"             => $SysConf['SYSCONFIG']['OidcRedirectURL'],
        "urlAuthorize"            => $SysConf['SYSCONFIG']['OidcAuthorizeURL'],
        "urlAccessToken"          => $SysConf['SYSCONFIG']['OidcAccessTokenURL'],
        "urlResourceOwnerDetails" => $SysConf['SYSCONFIG']['OidcResourceURL'],
        "proxy"                   => $proxy
      ]);
      $authorizationUrl =$provider->getAuthorizationUrl([
        "scope" => ['email openid']
      ]);
      $_SESSION['oauth2state'] =$provider->getState();
      $_SESSION['HTTP_REFERER'] =$referrer;
      header('Location: ' . $authorizationUrl);
      exit;
    }

    if (empty($referrer) && array_key_exists('HTTP_REFERER',$_SESSION)) {
      $referrer =$_SESSION['HTTP_REFERER'];
    } else if (empty($referrer)) {
      $referrer = GetArrayVal('HTTP_REFERER',$_SERVER);
    }

    if (array_key_exists("oauthemail", $_SESSION)) {
      $getEmail =$_SESSION['oauthemail'];
      unset($_SESSION['oauthemail']);
    }
    $referrerQuery = parse_url($referrer,PHP_URL_QUERY);
    if ($referrerQuery) {$params = array();
      parse_str($referrerQuery,$params);
      if (array_key_exists('mod', $params) &&$params['mod'] == $this->Name) {$referrer = Traceback_uri();
      }
    }
    if (!empty($getEmail) && empty($userName)) {$validLogin = $this->checkUsernameAndPassword($getEmail, "", true);
    } else {
      $validLogin =$this->checkUsernameAndPassword($userName,$password);
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

    if (isset($_SERVER['HTTPS']) &&$_SERVER['HTTPS'] != "off") {
      $this->vars['protocol'] = "HTTPS";
    } else {
      $this->vars['protocol'] = preg_replace("@/.*@", "", @$_SERVER['SERVER_PROTOCOL']);
    }

    $this->vars['referrer'] =$referrer;
    $this->vars['loginFailure'] = !empty($userName) \vert{}\vert{} !empty($password);
    if (!empty($userName) &&$userName!='Default User') {
      $this->vars['userName'] =$userName;
    }
    if (!empty($SysConf['SYSCONFIG']['OidcAppName'])) {
      $this->vars['providerExist'] =$SysConf['SYSCONFIG']['OidcAppName'];
    } else {
      $this->vars['providerExist'] = 0;
    }
    return $this->render('login.html.twig',$this->vars);
  }

  function OutputOpen()
  {
    if (array_key_exists('User', $_SESSION) &&$_SESSION['User'] != "Default User") {
      global $SysConf;
      if (!empty($SysConf['SYSCONFIG']['OidcLogoutURL'])) {
        $uri =$SysConf['SYSCONFIG']['OidcLogoutURL'];
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

  function checkUsernameAndPassword($userName,$password, $oauth=false,$isRest=false)
  {
    global $SysConf;

    $user_exists = true;
    $options = array('cost' => 10);

    if ($this->authExternal !== false &&$this->authExternal['useAuthExternal']) {
      $username =$this->authExternal['loginAuthExternal'];
      try {
        $this->userDao->getUserAndDefaultGroupByUserName($username);
      } catch (Exception $e) {$user_exists=false;
      }
      if (! $user_exists &&$GLOBALS['SysConf']['EXT_AUTH']['CONF_EXT_AUTH_NEW_USER_AUTO_CREATE']) {
        $User = trim(str_replace("'", "''", $this->authExternal['loginAuthExternal']));
        $Pass =$this->authExternal['passwordAuthExternal'] ;
        $Hash = password_hash($Pass, PASSWORD_DEFAULT, $options);$Desc = $this->authExternal['descriptionAuthExternal'];$Perm = 3;
        $Folder = 1;
        $Email_notify = "y";
        $Email =$this->authExternal['emailAuthExternal'];
        $agentList =$GLOBALS['SysConf']['EXT_AUTH']['CONF_EXT_AUTH_NEW_USER_AGENT_LIST'];
        add_user($User, $Desc,$Hash, $Perm,$Email, $Email_notify,$GLOBALS['SysConf']['SYSCONFIG']['UploadVisibility'], $agentList,$Folder);
      }
    }

    $authProvider = "password";
    if (array_key_exists('AUTHENTICATION', $SysConf) &&
      array_key_exists('provider', $SysConf['AUTHENTICATION'])) {
        $authProvider =$SysConf['AUTHENTICATION']['provider'];
    }

    if (empty($userName) \vert{}\vert{}$userName == 'Default User') {
      return false;
    }
    try {
      $row =$this->userDao->getUserAndDefaultGroupByUserName($userName,$oauth);
    } catch (Exception $e) {
      return false;
    }

    if (empty($row['user_name'])) {
      return false;
    }

    if (! $oauth) {
      if (!$isRest &&$authProvider != "password") {
        return false;
      }
      if (! empty($row['user_pass'])) {$options = array('cost' => 10);
        if (password_verify($password,$row['user_pass'])) {
          if (password_needs_rehash($row['user_pass'], PASSWORD_DEFAULT, $options)) {$newHash = password_hash($password, PASSWORD_DEFAULT,$options);
            update_password_hash($userName,$newHash);
          }
        } else if (! empty($row['user_seed'])) {$passwordHash = sha1($row['user_seed'] .$password);
          if (hash_equals($row['user_pass'], $passwordHash)) {$newHash = password_hash($password, PASSWORD_DEFAULT,$options);
            update_password_hash($userName,$newHash);
          } else {
            return false;
          }
        }
      } else if (!empty($password)) {
        return false;
      }
    }

    if (!$this->userDao->isUserActive($userName)) {$this->vars['userInactive'] = true;
      return false;
    }

    $this->updateSession($row);

    $_SESSION['time_check'] = time() + (480 * 60);
    if ("X" . $row['user_perm'] == "X") {
      $_SESSION[Auth::USER_LEVEL] = PLUGIN_DB_ADMIN;
    } else {
      $_SESSION[Auth::USER_LEVEL] =$row['user_perm'];
    }
    $_SESSION['checkip'] = GetParm("checkip", PARM_STRING);
    if (GetParm("nopopup", PARM_INTEGER) == 1) {
      $_SESSION['NoPopup'] = 1;     } else {$_SESSION['NoPopup'] = 0;
    }

    $this->userDao->updateUserLastConnection($row['user_pk']);

    return true;
  }

  protected function isCsrfTokenValid($id,$token)
  {
    if (empty($token) \vert{}\vert{} empty($this->csrfTokenManager)) {
      return false;
    }
    return $this->csrfTokenManager->isTokenValid(new CsrfToken($id,$token));
  }

  private function handleForgotPassword()
  {
    $identifier = GetParm("identifier", PARM_TEXT);
    $csrfToken = GetParm("csrf_token", PARM_TEXT);
    $message = "";

    if (!empty($this->csrfTokenManager)) {
      $this->vars['csrfToken'] =$this->csrfTokenManager->getToken('forgot-password')->getValue();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (!$this->isCsrfTokenValid('forgot-password', $csrfToken)) {$this->vars['error'] = _("Invalid or expired request. Please reload the page and try again.");
        $this->vars['message'] = "";
        return $this->render('forgot-password.html.twig',$this->vars);
      }

      if (!empty($identifier)) {$user = $this->userDao->getUserByEmailOrUsername($identifier);

        if ($user && !empty($user['user_email'])) {
          $rawToken = bin2hex(random_bytes(32));$tokenHash = hash('sha256', $rawToken);$expiresAt = date('Y-m-d H:i:sP', time() + 3600);

          $this->userDao->createPasswordResetToken($user['user_pk'], $tokenHash,$expiresAt);

          $resetUrl = Traceback_uri() . "?mod=auth&action=reset-password&token=" . $rawToken;
          $subject = "FOSSology Password Reset Request";
          $body = "Hello " . $user['user_name'] . ",\n\n"
                . "A password reset was requested for your account. Click the link below to set a new password:\n"
                . $resetUrl . "\n\n"
                . "This link is valid for 1 hour.\n"
                . "If you did not request this, please ignore this email.";

          if (!@mail($user['user_email'], $subject,$body)) {
            error_log("core-auth: failed to send password reset email to user_pk={$user['user_pk']}");
          }
        }

        $message = _("If an account matching that username or email exists, a password reset link has been sent.");
      }
    }

    $this->vars['message'] =$message;
    return $this->render('forgot-password.html.twig',$this->vars);
  }

  private function handleResetPassword()
  {
    $rawToken = GetParm("token", PARM_TEXT);
    $csrfToken = GetParm("csrf_token", PARM_TEXT);
    $newPassword = GetParm("new_password", PARM_TEXT);
    $confirmPassword = GetParm("confirm_password", PARM_TEXT);

    if (!empty($this->csrfTokenManager)) {
      $this->vars['csrfToken'] =$this->csrfTokenManager->getToken('reset-password')->getValue();
    }

    if (empty($rawToken)) {$this->vars['error'] = _("Invalid or missing password reset token.");
      return $this->render('reset-password.html.twig',$this->vars);
    }

    $tokenHash = hash('sha256', $rawToken);$tokenRecord = $this->userDao->getValidPasswordResetToken($tokenHash);

    if (!$tokenRecord) {$this->vars['error'] = _("This password reset link is invalid or has expired.");
      return $this->render('reset-password.html.twig',$this->vars);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (!$this->isCsrfTokenValid('reset-password', $csrfToken)) {$this->vars['error'] = _("Invalid or expired request. Please reload the page and try again.");
        $this->vars['token'] =$rawToken;
        return $this->render('reset-password.html.twig',$this->vars);
      }

      if (empty($newPassword) \vert{}\vert{}$newPassword !== $confirmPassword) {$this->vars['error'] = _("Passwords do not match or are empty.");
        $this->vars['token'] =$rawToken;
        return $this->render('reset-password.html.twig',$this->vars);
      }

      $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, array('cost' => 10));$this->userDao->updateUserPassword($tokenRecord['user_fk'],$hashedPassword);
      $this->userDao->markTokenAsUsed($tokenRecord['password_reset_pk']);

      $this->vars['success'] = _("Your password has been successfully reset. You can now log in.");
      return $this->render('reset-password.html.twig',$this->vars);
    }

    $this->vars['token'] =$rawToken;
    return $this->render('reset-password.html.twig',$this->vars);
  }
}
  
$NewPlugin = new core_auth();