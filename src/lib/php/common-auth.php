<?php
/*
 SPDX-FileCopyrightText: © 2011-2015 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: LGPL-2.1-only
*/

/**
 * \file
 * \brief This file contains common authentication function
 */


/**
 * \brief Check if SiteMinder is enabled.
 *
 * \note This can be used for other authentication agents by changing
 *  $IDEnvVar
 * \return -1 if not enabled, or the users SEA if enabled
 */
function siteminder_check()
{
  // $IDEnvVar = 'HPPF_AUTH_UID';  // for example for PingIdentity
  $IDEnvVar = 'HTTP_SMUNIVERSALID';
  if (isset($_SERVER[$IDEnvVar])) {
    return $_SERVER[$IDEnvVar];
  }
  return(-1);
} // siteminder_check()

/**
 * \brief Check if the external HTTP authentication is enabled.
 *  The mapping variables should be configured in fossology.conf
 *  Usernames are forced lowercase.
 * \return false if not enabled
 */
function auth_external_check()
{
  $EXT_AUTH_ENABLE = false;
  if (array_key_exists('EXT_AUTH', $GLOBALS['SysConf'])) {
    if (array_key_exists('CONF_EXT_AUTH_ENABLE', $GLOBALS['SysConf']['EXT_AUTH'])) {
        $EXT_AUTH_ENABLE = $GLOBALS['SysConf']['EXT_AUTH']['CONF_EXT_AUTH_ENABLE'];
    }
  }
  if ($EXT_AUTH_ENABLE) {
    $EXT_AUTH_USER_KW = $GLOBALS['SysConf']['EXT_AUTH']['CONF_EXT_AUTH_ENV_USER'];
    $EXT_AUTH_USER = null;
    if (isset($GLOBALS['_SERVER']["{$EXT_AUTH_USER_KW}"])) {
      $EXT_AUTH_USER = $GLOBALS['_SERVER']["{$EXT_AUTH_USER_KW}"];
    }
    if (isset($EXT_AUTH_USER) && !empty($EXT_AUTH_USER)) {
      if ($GLOBALS['SysConf']['EXT_AUTH']['CONF_EXT_AUTH_LOWERCASE_USER']) {
          $EXT_AUTH_USER = strtolower($EXT_AUTH_USER);
      }
      $out['useAuthExternal']         = true;
      $out['loginAuthExternal']       = $EXT_AUTH_USER;
      $out['passwordAuthExternal']    = sha1($EXT_AUTH_USER);
      $EXT_AUTH_MAIL_KW = $GLOBALS['SysConf']['EXT_AUTH']['CONF_EXT_AUTH_ENV_MAIL'];
      $out['emailAuthExternal']       = isset($GLOBALS['_SERVER']["{$EXT_AUTH_MAIL_KW}"]) ? $GLOBALS['_SERVER']["{$EXT_AUTH_MAIL_KW}"] : '';
      $EXT_AUTH_DESC_KW = $GLOBALS['SysConf']['EXT_AUTH']['CONF_EXT_AUTH_ENV_DESC'];
      $out['descriptionAuthExternal'] = isset($GLOBALS['_SERVER']["{$EXT_AUTH_DESC_KW}"]) ? $GLOBALS['_SERVER']["{$EXT_AUTH_DESC_KW}"] : '';
      return $out;
    }
  }
  return $out['useAuthExternal'] = false;
}

/**
 * \brief check if this account is correct
 *
 * \param string &$user   User name, reference variable
 * \param string &$passwd Password, reference variable
 * \param string &$group  Group, reference variable (optional)
 *
 * \return User id on success, exit(1) on failure.
 */
function account_check(&$user, &$passwd, &$group = "")
{
  global $SysConf;
  $dbManager = $GLOBALS['container']->get('db.manager');
  /* get username/passwd from ~/.fossology.rc */
  $user_passwd_file = getenv("HOME") . "/.fossology.rc";
  if (empty($user) && empty($passwd) && file_exists($user_passwd_file)) {
    $user_passwd_array = parse_ini_file($user_passwd_file, true, INI_SCANNER_RAW);

    /* get username and password from conf file */
    if (! empty($user_passwd_array) && ! empty($user_passwd_array['user'])) {
      $user = $user_passwd_array['user'];
    }
    if (! empty($user_passwd_array) && ! empty($user_passwd_array['username'])) {
      $user = $user_passwd_array['username'];
    }
    if (! empty($user_passwd_array) && ! empty($user_passwd_array['groupname'])) {
      $group = $user_passwd_array['groupname'];
    }
    if (! empty($user_passwd_array) && ! empty($user_passwd_array['password'])) {
      $passwd = $user_passwd_array['password'];
    }
  }
  /* check if the user name/passwd is valid */
  if (empty($user)) {
    /*
     * $uid_arr = posix_getpwuid(posix_getuid());
     * $user = $uid_arr['name'];
     */
    echo "FATAL: You should add '--username USERNAME' when running OR add " .
      "'username=USERNAME' in ~/.fossology.rc before running.\n";
    exit(1);
  }
  if (empty($passwd)) {
    echo "The user is: $user, please enter the password:\n";
    system('stty -echo');
    $passwd = trim(fgets(STDIN));
    system('stty echo');
    if (empty($passwd)) {
      echo "You entered an empty password.\n";
    }
  }

  if (! empty($user)) {
    $userDao = $GLOBALS['container']->get('dao.user');
    try {
      $row = $userDao->getUserAndDefaultGroupByUserName($user);
    } catch (Exception $e) {
      echo $e->getMessage(), "\n";
      exit(1);
    }
    $userId = $row['user_pk'];
    $SysConf['auth']['UserId'] = $userId;

    if (empty($group)) {
      $group = $row['group_name'];
      $groupId = $row['group_fk'];
    } else {
      $rowGroup = $dbManager->getSingleRow(
        "SELECT group_pk
        FROM group_user_member INNER JOIN groups ON groups.group_pk = group_user_member.group_fk
        WHERE user_fk = $1 AND group_name = $2", array($userId, $group),
        __METHOD__ . ".lookUpGroup");
      if (false === $rowGroup) {
        echo "User is not in group.\n";
        exit(1);
      }
      $groupId = $rowGroup['group_pk'];
    }
    $SysConf['auth']['GroupId'] = $groupId;
    if (empty($groupId)) {
      echo "Group '$group' not found.\n";
      exit(1);
    }

    if (! empty($row['user_pass'])) {
      $options = array('cost' => 10);
      if (password_verify($passwd, $row['user_pass'])) {
        if (password_needs_rehash($row['user_pass'], PASSWORD_DEFAULT, $options)) {
          $newHash = password_hash($passwd, PASSWORD_DEFAULT, $options);
          /* Update old hash with new hash  */
          update_password_hash($user, $newHash);
        }
        return true;
      } else if (! empty($row['user_seed'])) {
        $passwd_hash = sha1($row['user_seed'] . $passwd);
        /* If verify with new hash fails check with the old hash */
        if (strcmp($passwd_hash, $row['user_pass']) == 0) {
          $newHash = password_hash($passwd, PASSWORD_DEFAULT, $options);
          /* Update old hash with new hash */
          update_password_hash($user, $newHash);
          return true;
        } else {
          echo "User name or password is invalid.\n";
          exit(1);
        }
      }
    }
  }
  return $userId;
}

/**
 * \brief Check if the user has the permission to read the
 * copyright/license/etc information of this upload
 *
 * \param int    $upload Upload id
 * \param string $user   User name
 *
 * \return 1: has the permission; 0: no permission
 */
function read_permission($upload, $user)
{
  $ADMIN_PERMISSION = 10;
  $dbManager = $GLOBALS['container']->get('db.manager');

  /* check if the user if the owner of this upload */
  $row = $dbManager->getSingleRow(
    "SELECT 1
    FROM upload INNER JOIN users ON users.user_pk = upload.user_fk
    WHERE users.user_name = $1 AND upload.upload_pk = $2",
    array($user, $upload),
    __METHOD__.".checkUpload"
  );

  if (! empty($row)) {
    /* user has permission */
    return 1;
  }

  /* check if the user is administrator */
  $row = $dbManager->getSingleRow(
    "SELECT 1
    FROM users
    WHERE user_name = $1 AND user_perm = $2",
    array($user, $ADMIN_PERMISSION),
    __METHOD__.".checkPerm"
  );

  if (! empty($row)) {
    /* user has permission */
    return 1;
  }

  /* user does not have permission */
  return 0;
}

/**
 * Check if the password policy has been enabled
 * @return boolean
 */
function passwordPolicyEnabled()
{
  $sysconfig = $GLOBALS['SysConf']['SYSCONFIG'];
  if (! array_key_exists('PasswdPolicy', $sysconfig) ||
    $sysconfig['PasswdPolicy'] == 'false') {
    return false;
  }
  return true;
}

/**
 * Generate the password policy regex from sysconfig
 * @return string Regex based on policy selected
 */
function generate_password_policy()
{
  $sysconfig = $GLOBALS['SysConf']['SYSCONFIG'];
  if (! passwordPolicyEnabled()) {
    return ".*";
  }
  $limit = "*";
  $min = trim($sysconfig['PasswdPolicyMinChar']);
  $max = trim($sysconfig['PasswdPolicyMaxChar']);
  if (!empty($min) || !empty($max)) {
    if (empty($min)) {
      $min = 0;
    }
    $min = intval($min) < 0 ? 0 : $min;
    $max = intval($max) < 0 ? 0 : $max;
    $limit = '{' . $min . ",$max}";
  }
  $lookAhead = "";
  $charset = "a-zA-Z\\d";
  if ($sysconfig['PasswdPolicyLower'] == 'true') {
    $lookAhead .= '(?=.*[a-z])';
  }
  if ($sysconfig['PasswdPolicyUpper'] == 'true') {
    $lookAhead .= '(?=.*[A-Z])';
  }
  if ($sysconfig['PasswdPolicyDigit'] == 'true') {
    $lookAhead .= '(?=.*\\d)';
  }
  $special = trim($sysconfig['PasswdPolicySpecial']);
  if (!empty($special)) {
    $lookAhead .= "(?=.*[$special])";
    $charset .= $special;
    $charset = '[' . $charset . ']';
  } else {
    $charset = '.';  // Allow any special character
  }
  return $lookAhead . $charset . $limit;
}

/**
 * Translate selected password policy into user understandable string
 * @return string
 */
function generate_password_policy_string()
{
  $sysconfig = $GLOBALS['SysConf']['SYSCONFIG'];
  if (! passwordPolicyEnabled()) {
    return "No policy defined.";
  }
  $limit = "Any length.";
  $min = trim($sysconfig['PasswdPolicyMinChar']);
  $max = trim($sysconfig['PasswdPolicyMaxChar']);
  if (!empty($min) || !empty($max)) {
    if (empty($min)) {
      $min = 0;
    }
    $limit = "Minimum $min";
    if (!empty($max)) {
      $limit .= ", maximum $max";
    }
    $limit .= " characters.";
  }
  $others = [];
  if ($sysconfig['PasswdPolicyLower'] == 'true') {
    $others[] = "lower case";
  }
  if ($sysconfig['PasswdPolicyUpper'] == 'true') {
    $others[] = "upper case";
  }
  if ($sysconfig['PasswdPolicyDigit'] == 'true') {
    $others[] = "digit";
  }
  if (!empty($others)) {
    $others = "At least one " . join(", ", $others);
  } else {
    $others = "";
  }
  $special = trim($sysconfig['PasswdPolicySpecial']);
  if (!empty($special)) {
    if (!empty($others)) {
      $others .= " and";
    }
    $others .= " one of <em>$special</em>";
  }
  return "$limit $others.";
}
