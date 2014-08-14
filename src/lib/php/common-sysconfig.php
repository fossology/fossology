<?php
/***********************************************************
 Copyright (C) 2011-2013 Hewlett-Packard Development Company, L.P.

 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
***********************************************************/

/**
 * \file common-sysconfig.php
 * \brief System configuration functions.
 */

/** Data types for sysconfig table */
//@{
define("CONFIG_TYPE_INT", 1);
define("CONFIG_TYPE_TEXT", 2);
define("CONFIG_TYPE_TEXTAREA", 3);
//}@


/**
 * \brief Initialize the fossology system after bootstrap().  
 * This function also opens a database connection (global PG_CONN).
 *
 * System configuration variables are in four places:
 *  - SYSCONFDIR/fossology.conf (parsed by bootstrap())
 *  - SYSCONFDIR/VERSION
 *  - SYSCONFDIR/Db.conf
 *  - Database sysconfig table
 *
 * VERSION and fossology.conf variables are organized by group.  For example,
 * [DIRECTORIES] 
 *   REPODIR=/srv/mydir
 *
 * but the sysconfig table and Db.conf are not.  So all the table values will be put in
 * a made up "SYSCONFIG" group.  And all the Db.conf values will be put in a
 * "DBCONF" group.
 *
 * \param $sysconfdir - path to SYSCONFDIR
 * \param $SysConf - configuration variable array (updated by this function)
 * 
 * If the sysconfig table doesn't exist then create it.
 * Write records for the core variables into sysconfig table.
 *
 * The first array dimension of $SysConf is the group, the second is the variable name.
 * For example:
 *  -  $SysConf[SYSCONFIG][LogoLink] => "http://my/logo.gif"
 *  -  $SysConf[DIRECTORIES][MODDIR] => "/mymoduledir/
 *  -  $SysConf[VERSION][SVN_REV] => "4467M"
 *
 * \Note Since so many files expect directory paths that used to be in pathinclude.php
 * to be global, this function will define the same globals (everything in the 
 * DIRECTORIES section of fossology.conf).
 */
function ConfigInit($sysconfdir, &$SysConf)
{
  global $PG_CONN;

  /*************  Parse VERSION *******************/
  $VersionFile = "{$sysconfdir}/VERSION";
  $VersionConf = parse_ini_file($VersionFile, true);

  /* Add this file contents to $SysConf, then destroy $VersionConf 
   * This file can define its own groups and is eval'd.
   */
  foreach($VersionConf as $GroupName=>$GroupArray)
    foreach($GroupArray as $var=>$assign)
    {
      $toeval = "\$$var = \"$assign\";";
      eval($toeval);
      $SysConf[$GroupName][$var] = ${$var};
      $GLOBALS[$var] = ${$var};
    }
  unset($VersionConf);

  /*************  Parse Db.conf *******************/
  $dbPath = "{$sysconfdir}/Db.conf";
  $dbConf = parse_ini_file($dbPath, true);

  /* Add this file contents to $SysConf, then destroy $dbConf 
   * This file can define its own groups and is eval'd.
   */
  foreach($dbConf as $var=>$val) $SysConf['DBCONF'][$var] = $val;
  unset($dbConf);

  /**
   * Connect to the database.  If the connection fails,
   * DBconnect() will print a failure message and exit.
   */
  $PG_CONN = DBconnect($sysconfdir);

  global $container;
  $container->get('db.manager')->setDriver(new \Fossology\Lib\Db\Driver\Postgres($PG_CONN));

  /**************** read/create/populate the sysconfig table *********/
  /* create if sysconfig table if it doesn't exist */
  $NewTable = Create_sysconfig();

  /* populate it with core variables */
  Populate_sysconfig();

  /* populate the global $SysConf array with variable/value pairs */
  $sql = "select variablename, conf_value from sysconfig;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);

  while($row = pg_fetch_assoc($result))
  {
    $SysConf['SYSCONFIG'][$row['variablename']] = $row['conf_value'];
  }
  pg_free_result($result);

  return;
} // ConfigInit()


/**
 * \brief Create the sysconfig table.
 *
 * \return 0 if table already exists.
 * 1 if it was created
 */
function Create_sysconfig()
{
  global $PG_CONN;

  /* If sysconfig exists, then we are done */
  $sql = "SELECT typlen  FROM pg_type where typname='sysconfig' limit 1;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $numrows = pg_num_rows($result);
  pg_free_result($result);
  if ($numrows > 0) return 0;

  /* Create the sysconfig table */
  $sql = "
CREATE TABLE sysconfig (
    sysconfig_pk serial NOT NULL PRIMARY KEY,
    variablename character varying(30) NOT NULL UNIQUE,
    conf_value text,
    ui_label character varying(60) NOT NULL,
    vartype int NOT NULL,
    group_name character varying(20) NOT NULL,
    group_order int,
    description text NOT NULL,
    validation_function character varying(40) DEFAULT NULL
);
";

  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  /* Document columns */
  $sql = "
COMMENT ON TABLE sysconfig IS 'System configuration values';
COMMENT ON COLUMN sysconfig.variablename IS 'Name of configuration variable';
COMMENT ON COLUMN sysconfig.conf_value IS 'value of config variable';
COMMENT ON COLUMN sysconfig.ui_label IS 'Label that appears on user interface to prompt for variable';
COMMENT ON COLUMN sysconfig.group_name IS 'Name of this variables group in the user interface';
COMMENT ON COLUMN sysconfig.group_order IS 'The order this variable appears in the user interface group';
COMMENT ON COLUMN sysconfig.description IS 'Description of variable to document how/where the variable value is used.';
COMMENT ON COLUMN sysconfig.validation_function IS 'Name of function to validate input. Not currently implemented.';
COMMENT ON COLUMN sysconfig.vartype IS 'variable type.  1=int, 2=text, 3=textarea';
    ";
  /* this is a non critical update */
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);
  return 1;
}


/**
 * \brief Populate the sysconfig table with core variables.
 */
function Populate_sysconfig()
{
  global $PG_CONN;

  $Columns = "variablename, conf_value, ui_label, vartype, group_name, group_order, description, validation_function";
  $ValueArray = array();

  /*  Email */
  $Variable = "SupportEmailLabel";
  $SupportEmailLabelPrompt = _('Support Email Label');
  $SupportEmailLabelDesc = _('e.g. "Support"<br>Text that the user clicks on to create a new support email. This new email will be preaddressed to this support email address and subject.  HTML is ok.');
  $ValueArray[$Variable] = "'$Variable', 'Support', '$SupportEmailLabelPrompt',"
  . CONFIG_TYPE_TEXT .
                    ",'Support', 1, '$SupportEmailLabelDesc', ''";

  $Variable = "SupportEmailAddr";
  $SupportEmailAddrPrompt = _('Support Email Address');
  $SupportEmailAddrValid = "check_email_address";
  $SupportEmailAddrDesc = _('e.g. "support@mycompany.com"<br>Individual or group email address to those providing FOSSology support.');
  $ValueArray[$Variable] = "'$Variable', null, '$SupportEmailAddrPrompt', "
  . CONFIG_TYPE_TEXT .
                    ",'Support', 2, '$SupportEmailAddrDesc', '$SupportEmailAddrValid'";

  $Variable = "SupportEmailSubject";
  $SupportEmailSubjectPrompt = _('Support Email Subject line');
  $SupportEmailSubjectDesc = _('e.g. "fossology support"<br>Subject line to use on support email.');
  $ValueArray[$Variable] = "'$Variable', 'FOSSology Support', '$SupportEmailSubjectPrompt',"
  . CONFIG_TYPE_TEXT .
                    ",'Support', 3, '$SupportEmailSubjectDesc', ''";

  /*  Banner Message */
  $Variable = "BannerMsg";
  $BannerMsgPrompt = _('Banner message');
  $BannerMsgDesc = _('This is message will be displayed on every page with a banner.  HTML is ok.');
  $ValueArray[$Variable] = "'$Variable', null, '$BannerMsgPrompt', "
  . CONFIG_TYPE_TEXTAREA .
                    ",'Banner', 1, '$BannerMsgDesc', ''";

  /*  Logo  */
  $Variable = "LogoImage";
  $LogoImagePrompt = _('Logo Image URL');
  $LogoImageValid = "check_logo_image_url";
  $LogoImageDesc = _('e.g. "http://mycompany.com/images/companylogo.png" or "images/mylogo.png"<br>This image replaces the fossology project logo. Image is constrained to 150px wide.  80-100px high is a good target.  If you change this URL, you MUST also enter a logo URL.');
  $ValueArray[$Variable] = "'$Variable', null, '$LogoImagePrompt', "
  . CONFIG_TYPE_TEXT .
                    ",'Logo', 1, '$LogoImageDesc', '$LogoImageValid'";

  $Variable = "LogoLink";
  $LogoLinkPrompt = _('Logo URL');
  $LogoLinkDesc = _('e.g. "http://mycompany.com/fossology"<br>URL a person goes to when they click on the logo.  If you change the Logo URL, you MUST also enter a Logo Image.');
  $LogoLinkValid = "check_logo_url";
  $ValueArray[$Variable] = "'$Variable', null, '$LogoLinkPrompt', "
  . CONFIG_TYPE_TEXT .
                    ",'Logo', 2, '$LogoLinkDesc', '$LogoLinkValid'" ;
   
  $Variable = "FOSSologyURL";
  $URLPrompt = _("FOSSology URL");
  $hostname = exec("hostname -f");
  if (empty($hostname)) $hostname = "localhost";
  $FOSSologyURL = $hostname."/repo/";
  $URLDesc = _("URL of this FOSSology server, e.g. $FOSSologyURL");
  $URLValid = "check_fossology_url";
  $ValueArray[$Variable] = "'$Variable', '$FOSSologyURL', '$URLPrompt', "
  . CONFIG_TYPE_TEXT .
                    ",'URL', 1, '$URLDesc', '$URLValid'";

  $Variable = "NomostListNum";
  $NomosNumPrompt = _("Number of Nomost List");
  $NomostListNum = "2200";
  $NomosNumDesc = _("For the Nomos List/Nomost List Download, you can set the number of lines to list/download. Default 2200.");
  $ValueArray[$Variable] = "'$Variable', '$NomostListNum', '$NomosNumPrompt', "
  . CONFIG_TYPE_TEXT .
                    ",'Number', 4, '$NomosNumDesc', null";

   
  /* Doing all the rows as a single insert will fail if any row is a dupe.
   So insert each one individually so that new variables get added.
  */
  foreach ($ValueArray as $Variable => $Values)
  {
    /* Check if the variable already exists.  Insert it if it does not.
     * This is better than an insert ignoring duplicates, because that
    * generates a postresql log message.
    */
    $VarRec = GetSingleRec("sysconfig", "where variablename='$Variable'");
    if (empty($VarRec))
    {
      $sql = "insert into sysconfig ({$Columns}) values ($Values);";
      $result = @pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    }
    unset($VarRec);
  }
}

/**
 * \brief validation function check_boolean().
 * check if the value format is valid,
 * only true/false is valid
 *
 * \param $value - the value which will be checked 
 *
 * \return 1, if the value is valid, or 0
 */
function check_boolean($value)
{
  if (!strcmp($value, 'true') || !strcmp($value, 'false'))
  {
    return 1;
  }
  else
  {
    return 0;
  }
}

/**
 * \brief validation function check_fossology_url().
 * check if the url is valid,
 *
 * \param $url - the url which will be checked 
 *
 * \return  1: valid, 0: invalid
 */
function check_fossology_url($url)
{
  $url_array = explode("/", $url, 2);
  $name = $url_array[0];
  if (!empty($name))
  {
    $hostname = exec("hostname -f");
    if (empty($hostname)) $hostname = "localhost";
    if(check_IP($name))
    {
      $hostname1 = gethostbyaddr($name);
      if (strcmp($hostname, $hostname1) == 0)  return 0;  // host is not reachable
    }
    $server_name = $_SERVER['SERVER_NAME'];

    /* intput $name must match either the hostname or the server name */
    if (strcmp($name, $hostname) && strcmp($name, $server_name))
    {
      return 0;
    }
  }
  else return 0;
  return 1;
}

/**
 * \brief validation function check_logo_url().
 * check if the url is available,
 *
 * \param $url - the url which will be checked 
 *
 * \return 1: available, 0: unavailable
 */
function check_logo_url($url)
{
  if (empty($url)) return 1; /* logo url can be null, with the default */

  //$res = check_url($url);
  $res = is_available($url);
  if (1 == $res)
  {
    return 1;
  }
  else return 0;
}

/**
 * \brief validation function check_logo_image_url().
 * check if the url is available,
 *
 * \param $url - the url which will be checked 
 *
 * \return 1: the url is available, 0: unavailable
 */
function check_logo_image_url($url)
{
  global $SysConf;

  if (empty($url)) return 1; /* logo url can be null, with the default */

  $LogoLink = @$SysConf["LogoLink"];
  $new_url = $LogoLink.$url;
  if (is_available($url) || is_available($new_url))
  {
    return 1;
  }
  else return 0;

}

/**
 * \brief validation function check_email_address().
 * implement this function if needed in the future
 * check if the email address is valid
 *
 * \param $email_address - the email address which will be checked 
 *
 * \return 1: valid, 0: invalid
 */
function check_email_address($email_address)
{
  return 1;
}

/**
 * \brief check if the url is available
 *
 * \param $url - url
 * \param $timeout - timeout interval, default 2 seconds
 * \param $tries - if unavailable, will try several times, default 2 times
 *
 * \return 1: available, 0: unavailable
 */
function is_available($url, $timeout = 2, $tries = 2)
{
  global $SysConf;

  $ProxyStmts = "";
  if (array_key_exists('http_proxy', $SysConf['FOSSOLOGY']) && $SysConf['FOSSOLOGY']['http_proxy']) $ProxyStmts .= "export http_proxy={$SysConf['FOSSOLOGY']['http_proxy']};";
  if (array_key_exists('https_proxy', $SysConf['FOSSOLOGY']) && $SysConf['FOSSOLOGY']['https_proxy']) $ProxyStmts .= "export https_proxy={$SysConf['FOSSOLOGY']['https_proxy']};";
  if (array_key_exists('ftp_proxy', $SysConf['FOSSOLOGY']) && $SysConf['FOSSOLOGY']['ftp_proxy']) $ProxyStmts .= "export ftp_proxy={$SysConf['FOSSOLOGY']['ftp_proxy']};";

  $commands = "$ProxyStmts wget --spider '$url' --tries=$tries --timeout=$timeout";
  system($commands, $return_var);
  if (0 == $return_var)
  {
    return 1;
  }
  else return 0;
}

/**
 * \brief check if the url is valid
 * \param $url - the url which will be checked 
 * \return 1: the url is valid, 0: invalid
 */
function check_url($url)
{
  if (empty($url) || preg_match("@^((http)|(https)|(ftp))://([[:alnum:]]+)@i", $url) != 1 || preg_match("@[[:space:]]@", $url) != 0)
  {
    return 0;
  }
  else return 1;
}

/**
 * \brief check if the ip address is valid
 * \param $ip - IP address
 * \return 1: yes
 */
function check_IP($ip)
{
  $e="([0-9]|1[0-9]{2}|[1-9][0-9]|2[0-4][0-9]|25[0-5])";
  return preg_match("/^$e\.$e\.$e\.$e$/",$ip);
}
