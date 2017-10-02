<?php

use Fossology\Lib\Auth\Auth;
use Symfony\Component\HttpFoundation\Session\Session;

class AuthHelper
{
  private $session;
  private $userDao;

  /**
   * AuthHelper constructor.
   * @param $userDao \Fossology\Lib\Dao\UserDao
   */
  public function __construct($userDao)
  {
    $this->userDao = $userDao;
    $this->session = new Session();
    $this->session->save();
  }

  function checkUsernameAndPassword($userName, $password)
  {
    if (empty($userName) || $userName == 'Default User')
    {
      return false;
    }
    try
    {
      $row = $this->userDao->getUserAndDefaultGroupByUserName($userName);
    }
    catch(Exception $e)
    {
      return false;
    }

    if (empty($row['user_name']))
    {
      return false;
    }

    /* Check the password -- only if a password exists */
    if (!empty($row['user_seed']) && !empty($row['user_pass']))
    {
      $passwordHash = sha1($row['user_seed'] . $password);
      if (strcmp($passwordHash, $row['user_pass']) != 0)
      {
        return false;
      }
    } else if (!empty($row['user_seed']))
    {
      /* Seed with no password hash = no login */
      return false;
    } else if (!empty($password))
    {
      /* empty password required */
      return false;
    }

    /* If you make it here, then username and password were good! */
    $this->updateSession($row);

    $_SESSION['time_check'] = time() + (480 * 60);
    /* No specified permission means ALL permission */
    if ("X" . $row['user_perm'] == "X")
    {
      $_SESSION[Auth::USER_LEVEL] = PLUGIN_DB_ADMIN;
    } else
    {
      $_SESSION[Auth::USER_LEVEL] = $row['user_perm'];
    }
    $_SESSION['checkip'] = GetParm("checkip", PARM_STRING);
    /* Check for the no-popup flag */
    if (GetParm("nopopup", PARM_INTEGER) == 1)
    {
      $_SESSION['NoPopup'] = 1;
    } else
    {
      $_SESSION['NoPopup'] = 0;
    }
    return true;
  }

  /**
   * \brief Set $_SESSION and $SysConf user variables
   * \param $UserRow users table row, if empty, use Default User
   * \return void, updates globals $_SESSION and $SysConf[auth][UserId] variables
   */
  function updateSession($userRow)
  {
    global $SysConf;

    if (empty($userRow))
    {
      $userRow = $this->userDao->getUserAndDefaultGroupByUserName('Default User');
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
  }

  /**
   * @return mixed
   */
  public function getSession()
  {
    return $this->session;
  }



}
