<?php
/***********************************************************
 Copyright (C) 2011-2015 Hewlett-Packard Development Company, L.P.

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

use Fossology\Lib\Auth\Auth;

/**
 * \file
 * \brief System configuration functions.
 */

/** Integer type config  */
define("CONFIG_TYPE_INT", 1);
/** Text type config     */
define("CONFIG_TYPE_TEXT", 2);
/** Textarea type config */
define("CONFIG_TYPE_TEXTAREA", 3);
/** Password type config */
define("CONFIG_TYPE_PASSWORD", 4);
/** Dropdown type config */
define("CONFIG_TYPE_DROP", 5);
/** Checkbox type config */
define("CONFIG_TYPE_BOOL", 6);


/**
 * \brief Initialize the fossology system after bootstrap().
 *
 * This function also opens a database connection (global PG_CONN).
 *
 * System configuration variables are in four places:
 *  - SYSCONFDIR/fossology.conf (parsed by bootstrap())
 *  - SYSCONFDIR/VERSION
 *  - SYSCONFDIR/Db.conf
 *  - Database sysconfig table
 *
 * VERSION and fossology.conf variables are organized by group. For example,
 * \code{.ini}
 * [DIRECTORIES]
 *   REPODIR=/srv/mydir
 * \endcode
 *
 * But the sysconfig table and Db.conf are not. So all the table values will be put in
 * a made up "SYSCONFIG" group. And all the Db.conf values will be put in a
 * "DBCONF" group.
 *
 * \param string $sysconfdir   Path to SYSCONFDIR
 * \param[out] array &$SysConf Configuration variable array (updated by this function)
 *
 * If the sysconfig table doesn't exist then create it.
 * Write records for the core variables into sysconfig table.
 *
 * The first array dimension of $SysConf is the group, the second is the variable name.
 * For example:
 *  -  $SysConf[SYSCONFIG][LogoLink] => "http://my/logo.gif"
 *  -  $SysConf[DIRECTORIES][MODDIR] => "/mymoduledir/
 *  -  $SysConf[VERSION][COMMIT_HASH] => "4467M"
 *
 * \note Since so many files expect directory paths that used to be in pathinclude.php
 * to be global, this function will define the same globals (everything in the
 * DIRECTORIES section of fossology.conf).
 */
function ConfigInit($sysconfdir, &$SysConf)
{
  global $PG_CONN;

  /*************  Parse VERSION *******************/
  $versionFile = "{$sysconfdir}/VERSION";
  $versionConf = parse_ini_file($versionFile, true);

  /* Add this file contents to $SysConf, then destroy $VersionConf
   * This file can define its own groups and is eval'd.
   */
  foreach ($versionConf as $groupName => $groupArray) {
    foreach ($groupArray as $var => $assign) {
      $toeval = "\$$var = \"$assign\";";
      eval($toeval);
      $SysConf[$groupName][$var] = ${$var};
      $GLOBALS[$var] = ${$var};
    }
  }
  unset($versionConf);

  /*************  Parse Db.conf *******************/
  $dbPath = "{$sysconfdir}/Db.conf";
  $dbConf = parse_ini_file($dbPath, true);

  /* Add this file contents to $SysConf, then destroy $dbConf
   * This file can define its own groups and is eval'd.
   */
  foreach ($dbConf as $var => $val) {
    $SysConf['DBCONF'][$var] = $val;
  }
  unset($dbConf);

  /*
   * Connect to the database.  If the connection fails,
   * DBconnect() will print a failure message and exit.
   */
  $PG_CONN = DBconnect($sysconfdir);

  global $container;
  $postgresDriver = new \Fossology\Lib\Db\Driver\Postgres($PG_CONN);
  $container->get('db.manager')->setDriver($postgresDriver);

  /**************** read/create/populate the sysconfig table *********/
  /* create if sysconfig table if it doesn't exist */
  $newTable  = Create_sysconfig();
  $newColumn = Create_option_value();

  /* populate it with core variables */
  Populate_sysconfig();

  /* populate the global $SysConf array with variable/value pairs */
  $sql = "SELECT variablename, conf_value FROM sysconfig;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);

  while ($row = pg_fetch_assoc($result)) {
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
  $sql = "SELECT typlen  FROM pg_type WHERE typname='sysconfig' limit 1;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $numrows = pg_num_rows($result);
  pg_free_result($result);
  if ($numrows > 0) {
    return 0;
  }

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
    validation_function character varying(40) DEFAULT NULL,
    option_value text DEFAULT NULL
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
COMMENT ON COLUMN sysconfig.vartype IS 'variable type.  1=int, 2=text, 3=textarea, 4=password, 5=dropdown';
COMMENT ON COLUMN sysconfig.option_value IS 'If vartype is 5, provide options in format op1{val1}|op2{val2}|...';
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

  $columns = array("variablename", "conf_value", "ui_label", "vartype", "group_name",
    "group_order", "description", "validation_function", "option_value");
  $valueArray = array();

  /*  CorsOrigin */
  $variable = "CorsOrigins";
  $prompt = _('Allowed origins for REST API');
  $desc = _('[scheme]://[hostname]:[port], "*" for anywhere');
  $valueArray[$variable] = array("'$variable'", "'localhost:3000'", "'$prompt'",
    strval(CONFIG_TYPE_TEXT), "'PAT'", "3", "'$desc'", "null", "null");

  /*  Email */
  $variable = "SupportEmailLabel";
  $supportEmailLabelPrompt = _('Support Email Label');
  $supportEmailLabelDesc = _('e.g. "Support"<br>Text that the user clicks on to create a new support email. This new email will be preaddressed to this support email address and subject.  HTML is ok.');
  $valueArray[$variable] = array("'$variable'", "'Support'", "'$supportEmailLabelPrompt'",
    strval(CONFIG_TYPE_TEXT), "'Support'", "1", "'$supportEmailLabelDesc'", "null", "null");

  $variable = "SupportEmailAddr";
  $supportEmailAddrPrompt = _('Support Email Address');
  $supportEmailAddrValid = "check_email_address";
  $supportEmailAddrDesc = _('e.g. "support@mycompany.com"<br>Individual or group email address to those providing FOSSology support.');
  $valueArray[$variable] = array("'$variable'", "null", "'$supportEmailAddrPrompt'",
    strval(CONFIG_TYPE_TEXT), "'Support'", "2", "'$supportEmailAddrDesc'", "'$supportEmailAddrValid'", "null");

  $variable = "SupportEmailSubject";
  $supportEmailSubjectPrompt = _('Support Email Subject line');
  $supportEmailSubjectDesc = _('e.g. "fossology support"<br>Subject line to use on support email.');
  $valueArray[$variable] = array("'$variable'", "'FOSSology Support'", "'$supportEmailSubjectPrompt'",
    strval(CONFIG_TYPE_TEXT), "'Support'", "3", "'$supportEmailSubjectDesc'", "null", "null");

  /*  Banner Message */
  $variable = "BannerMsg";
  $bannerMsgPrompt = _('Banner message');
  $bannerMsgDesc = _('This is message will be displayed on every page with a banner.  HTML is ok.');
  $valueArray[$variable] = array("'$variable'", "null", "'$bannerMsgPrompt'",
    strval(CONFIG_TYPE_TEXTAREA), "'Banner'", "1", "'$bannerMsgDesc'", "null", "null");

  /*  Logo  */
  $variable = "LogoImage";
  $logoImagePrompt = _('Logo Image URL');
  $logoImageValid = "check_logo_image_url";
  $logoImageDesc = _('e.g. "http://mycompany.com/images/companylogo.png" or "images/mylogo.png"<br>This image replaces the fossology project logo. Image is constrained to 150px wide.  80-100px high is a good target.  If you change this URL, you MUST also enter a logo URL.');
  $valueArray[$variable] = array("'$variable'", "null", "'$logoImagePrompt'",
    strval(CONFIG_TYPE_TEXT), "'Logo'", "1", "'$logoImageDesc'", "'$logoImageValid'", "null");

  $variable = "LogoLink";
  $logoLinkPrompt = _('Logo URL');
  $logoLinkDesc = _('e.g. "http://mycompany.com/fossology"<br>URL a person goes to when they click on the logo.  If you change the Logo URL, you MUST also enter a Logo Image.');
  $logoLinkValid = "check_logo_url";
  $valueArray[$variable] = array("'$variable'", "null", "'$logoLinkPrompt'",
    strval(CONFIG_TYPE_TEXT), "'Logo'", "2", "'$logoLinkDesc'", "'$logoLinkValid'", "null");

  $variable = "FOSSologyURL";
  $urlPrompt = _("FOSSology URL");
  $hostname = exec("hostname -f");
  if (empty($hostname)) {
    $hostname = "localhost";
  }
  $fossologyURL = $hostname."/repo/";
  $urlDesc = _("URL of this FOSSology server, e.g. $fossologyURL");
  $urlValid = "check_fossology_url";
  $valueArray[$variable] = array("'$variable'", "'$fossologyURL'", "'$urlPrompt'",
    strval(CONFIG_TYPE_TEXT), "'URL'", "1", "'$urlDesc'", "'$urlValid'", "null");

  $variable = "ClearlyDefinedURL";
  $urlPrompt = _("ClearlyDefined URL");
  $cdURL = "https://api.clearlydefined.io/";
  $urlDesc = _("URL of ClearlyDefined server, e.g. $cdURL");
  $urlValid = "check_url";
  $valueArray[$variable] = array("'$variable'", "'$cdURL'", "'$urlPrompt'",
    strval(CONFIG_TYPE_TEXT), "'URL'", "2", "'$urlDesc'", "'$urlValid'", "null");

  $variable = "NomostListNum";
  $nomosNumPrompt = _("Maximum licenses to List");
  $nomostListNum = "2200";
  $NomosNumDesc = _("For License List and License List Download, you can set the maximum number of lines to list/download. Default 2200.");
  $valueArray[$variable] = array("'$variable'", "'$nomostListNum'", "'$nomosNumPrompt'",
    strval(CONFIG_TYPE_TEXT), "'Number'", "1", "'$NomosNumDesc'", "null", "null");

  $variable = "BlockSizeHex";
  $hexPrompt = _("Chars per page in hex view");
  $hexDesc = _("Number of characters per page in hex view");
  $valueArray[$variable] = array("'$variable'", "'8192'", "'$hexPrompt'",
    strval(CONFIG_TYPE_TEXT), "'Number'", "2", "'$hexDesc'", "null", "null");

  $variable = "BlockSizeText";
  $textPrompt = _("Chars per page in text view");
  $textDesc = _("Number of characters per page in text view");
  $valueArray[$variable] = array("'$variable'", "'81920'", "'$textPrompt'",
    strval(CONFIG_TYPE_TEXT), "'Number'", "3", "'$textDesc'", "null", "null");

  $variable = "ShowJobsAutoRefresh";
  $contextNamePrompt = _("ShowJobs Auto Refresh Time");
  $contextValue = "10";
  $contextDesc = _("No of seconds to refresh ShowJobs");
  $valueArray[$variable] = array("'$variable'", "'$contextValue'", "'$contextNamePrompt'",
    strval(CONFIG_TYPE_TEXT), "'Number'", "4", "'$contextDesc'", "null", "null");

  /* Report Header Text */
  $variable = "ReportHeaderText";
  $contextNamePrompt = _("Report Header Text");
  $contextValue = "FOSSology";
  $contextDesc = _("Report Header Text at right side corner");
  $valueArray[$variable] = array("'$variable'", "'$contextValue'", "'$contextNamePrompt'",
    strval(CONFIG_TYPE_TEXT), "'ReportText'", "1", "'$contextDesc'", "null", "null");

  $variable = "CommonObligation";
  $contextNamePrompt = _("Common Obligation");
  $contextValue = "";
  $contextDesc = _("Common Obligation Text, add line break at the end of the line");
  $valueArray[$variable] = array("'$variable'", "'$contextValue'", "'$contextNamePrompt'",
    strval(CONFIG_TYPE_TEXTAREA), "'ReportText'", "2", "'$contextDesc'", "null", "null");

  $variable = "AdditionalObligation";
  $contextNamePrompt = _("Additional Obligation");
  $contextValue = "";
  $contextDesc = _("Additional Obligation Text, add line break at the end of the line");
  $valueArray[$variable] = array("'$variable'", "'$contextValue'", "'$contextNamePrompt'",
    strval(CONFIG_TYPE_TEXTAREA), "'ReportText'", "3", "'$contextDesc'", "null", "null");

  $variable = "ObligationAndRisk";
  $contextNamePrompt = _("Obligation And Risk Assessment");
  $contextValue = "";
  $contextDesc = _("Obligations and risk assessment, add line break at the end of the line");
  $valueArray[$variable] = array("'$variable'", "'$contextValue'", "'$contextNamePrompt'",
    strval(CONFIG_TYPE_TEXTAREA), "'ReportText'", "4", "'$contextDesc'", "null", "null");

  /*  "Upload from server"-configuration  */
  $variable = "UploadFromServerWhitelist";
  $contextNamePrompt = _("Whitelist for serverupload");
  $contextValue = "/tmp";
  $contextDesc = _("List of allowed prefixes for upload, separated by \":\" (colon)");
  $valueArray[$variable] = array("'$variable'", "'$contextValue'", "'$contextNamePrompt'",
    strval(CONFIG_TYPE_TEXT), "'UploadFromServer'", "1", "'$contextDesc'", "null", "null");

  $variable = "UploadFromServerAllowedHosts";
  $contextNamePrompt = _("List of allowed hosts for serverupload");
  $contextValue = "localhost";
  $contextDesc = _("List of allowed hosts for upload, separated by \":\" (colon)");
  $valueArray[$variable] = array("'$variable'", "'$contextValue'", "'$contextNamePrompt'",
    strval(CONFIG_TYPE_TEXT), "'UploadFromServer'", "2", "'$contextDesc'", "null", "null");

  /*  SMTP config */
  $variable = "SMTPHostName";
  $smtpHostPrompt = _('SMTP Host Name');
  $smtpHostDesc = _('e.g.: "smtp.domain.com"<br>The domain to be used to send emails.');
  $valueArray[$variable] = array("'$variable'", "null", "'$smtpHostPrompt'",
    strval(CONFIG_TYPE_TEXT), "'SMTP'", "1", "'$smtpHostDesc'", "null", "null");

  $variable = "SMTPPort";
  $smtpPortPrompt = _('SMTP Port');
  $smtpPortDesc = _('e.g.: "25"<br>SMTP port to be used.');
  $valueArray[$variable] = array("'$variable'", "25", "'$smtpPortPrompt'",
    strval(CONFIG_TYPE_INT), "'SMTP'", "2", "'$smtpPortDesc'", "null", "null");

  $variable = "SMTPAuth";
  $smtpAuthPrompt = _('SMTP Auth Type');
  $smtpAuthDesc = _('Algorithm to use for login.<br>Login => Encrypted<br>None => No authentication<br>Plain => Send as plain text');
  $valueArray[$variable] = array("'$variable'", "'L'", "'$smtpAuthPrompt'",
    strval(CONFIG_TYPE_DROP), "'SMTP'", "3", "'$smtpAuthDesc'", "null", "'Login{L}|None{N}|Plain{P}'");

  $variable = "SMTPFrom";
  $smtpFrom = _('SMTP Email');
  $smtpFromDesc = _('e.g.: "user@domain.com"<br>Sender email.');
  $valueArray[$variable] = array("'$variable'", "null", "'$smtpFrom'",
    strval(CONFIG_TYPE_TEXT), "'SMTP'", "4", "'$smtpFromDesc'", "'check_email_address'", "null");

  $variable = "SMTPAuthUser";
  $smtpAuthUserPrompt = _('SMTP User');
  $smtpAuthUserDesc = _('e.g.: "user"<br>Login to be used for login on SMTP Server.');
  $valueArray[$variable] = array("'$variable'", "null", "'$smtpAuthUserPrompt'",
    strval(CONFIG_TYPE_TEXT), "'SMTP'", "5", "'$smtpAuthUserDesc'", "null", "null");

  $variable = "SMTPAuthPasswd";
  $smtpAuthPasswdPrompt = _('SMTP Login Password');
  $smtpAuthPasswdDesc = _('Password used for SMTP login.');
  $valueArray[$variable] = array("'$variable'", "null", "'$smtpAuthPasswdPrompt'",
    strval(CONFIG_TYPE_PASSWORD), "'SMTP'", "6", "'$smtpAuthPasswdDesc'", "null", "null");

  $variable = "SMTPSslVerify";
  $smtpSslPrompt = _('SMTP SSL Verify');
  $smtpSslDesc = _('The SSL verification for connection is required?');
  $valueArray[$variable] = array("'$variable'", "'S'", "'$smtpSslPrompt'",
    strval(CONFIG_TYPE_DROP), "'SMTP'", "7", "'$smtpSslDesc'", "null", "'Ignore{I}|Strict{S}|Warn{W}'");

  $variable = "SMTPStartTls";
  $smtpTlsPrompt = _('Start TLS');
  $smtpTlsDesc = _('Use TLS connection for SMTP?');
  $valueArray[$variable] = array("'$variable'", "'1'", "'$smtpTlsPrompt'",
    strval(CONFIG_TYPE_DROP), "'SMTP'", "8", "'$smtpTlsDesc'", "null", "'Yes{1}|No{2}'");

  $variable = "UploadVisibility";
  $prompt = _('Default Upload Visibility');
  $desc = _('Default Visibility for uploads by the user');
  $valueArray[$variable] = array("'$variable'", "'protected'", "'$prompt'",
    strval(CONFIG_TYPE_DROP), "'UploadFlag'", "1", "'$desc'", "null", "'Visible only for active group{private}|Visible for all groups{protected}|Make Public{public}'");

  /* Password policy config */
  $variable = "PasswdPolicy";
  $prompt = _('Enable password policy');
  $desc = _('Enable password policy check');
  $valueArray[$variable] = array("'$variable'", "false", "'$prompt'",
    strval(CONFIG_TYPE_BOOL), "'PASSWD'", "1", "'$desc'",
    "'check_boolean'", "null");

  $variable = "PasswdPolicyMinChar";
  $prompt = _('Minimum characters');
  $desc = _('Blank for no limit');
  $valueArray[$variable] = array("'$variable'", "8", "'$prompt'",
    strval(CONFIG_TYPE_INT), "'PASSWD'", "2", "'$desc'", "null", "null");

  $variable = "PasswdPolicyMaxChar";
  $prompt = _('Maximum characters');
  $desc = _('Blank for no limit');
  $valueArray[$variable] = array("'$variable'", "16", "'$prompt'",
    strval(CONFIG_TYPE_INT), "'PASSWD'", "3", "'$desc'", "null", "null");

  $variable = "PasswdPolicyLower";
  $prompt = _('Lowercase');
  $desc = _('Minimum one lowercase character.');
  $valueArray[$variable] = array("'$variable'", "true", "'$prompt'",
    strval(CONFIG_TYPE_BOOL), "'PASSWD'", "4", "'$desc'",
    "'check_boolean'", "null");

  $variable = "PasswdPolicyUpper";
  $prompt = _('Uppercase');
  $desc = _('Minimum one uppercase character.');
  $valueArray[$variable] = array("'$variable'", "true", "'$prompt'",
    strval(CONFIG_TYPE_BOOL), "'PASSWD'", "5", "'$desc'",
    "'check_boolean'", "null");

  $variable = "PasswdPolicyDigit";
  $prompt = _('Digit');
  $desc = _('Minimum one digit.');
  $valueArray[$variable] = array("'$variable'", "true", "'$prompt'",
    strval(CONFIG_TYPE_BOOL), "'PASSWD'", "6", "'$desc'",
    "'check_boolean'", "null");

  $variable = "PasswdPolicySpecial";
  $prompt = _('Allowed special characters');
  $desc = _('Empty for do not care');
  $valueArray[$variable] = array("'$variable'", "'@$!%*?&'", "'$prompt'",
    strval(CONFIG_TYPE_TEXT), "'PASSWD'", "7", "'$desc'", "null", "null");

  $variable = "PATMaxExipre";
  $patTokenValidityPrompt = _('Max token validity');
  $patTokenValidityDesc = _('Maximum validity of tokens (in days)');
  $valueArray[$variable] = array("'$variable'", "30", "'$patTokenValidityPrompt'",
    strval(CONFIG_TYPE_INT), "'PAT'", "1", "'$patTokenValidityDesc'", "null", "null");

  $variable = "PATMaxPostExpiryRetention";
  $patTokenRetentionPrompt = _('Max expired token retention period');
  $patTokenRetentionDesc = _('Maximum retention period of expired tokens (in days) for Maintagent');
  $valueArray[$variable] = array("'$variable'", "30", "'$patTokenRetentionPrompt'",
    strval(CONFIG_TYPE_INT), "'PAT'", "2", "'$patTokenRetentionDesc'", "null", "null");

  $variable = "SkipFiles";
  $mimeTypeToSkip = _("Skip MimeTypes from scanning");
  $mimeTypeDesc = _("add  comma (,) separated mimetype to exclude files from scanning");
  $valueArray[$variable] = array("'$variable'", "null", "'$mimeTypeToSkip'",
    strval(CONFIG_TYPE_TEXT), "'Skip'", "1", "'$mimeTypeDesc'", "null", "null");

  $perm_admin=Auth::PERM_ADMIN;
  $perm_write=Auth::PERM_WRITE;
  $variable = "SourceCodeDownloadRights";
  $SourceDownloadRightsPrompt = _('Acces rights required to download source code');
  $SourceDownloadRightsDesc = _('Choose which acces level will be required for user to be able to download source code.');
  $valueArray[$variable] = array("'$variable'", "'$perm_write'", "'$SourceDownloadRightsPrompt'",
  strval(CONFIG_TYPE_DROP), "'DOWNLOAD'", "1", "'$SourceDownloadRightsDesc'", "null", "'Administrator{{$perm_admin}}|Read_Write{{$perm_write}}'");

  /* SoftwareHeritage agent config */
  $variable = "SwhURL";
  $prompt = _('SoftwareHeritage URL');
  $desc = _('URL to Software Heritage servers');
  $valueArray[$variable] = array("'$variable'",
    "'https://archive.softwareheritage.org'", "'$prompt'",
    strval(CONFIG_TYPE_TEXT), "'SWH'", "1", "'$desc'", "'check_url'", "null");

  $variable = "SwhBaseURL";
  $prompt = _('SoftwareHeritage API base URI');
  $desc = _('Base URI for API calls');
  $valueArray[$variable] = array("'$variable'", "'/api/1/content/sha256:'",
    "'$prompt'", strval(CONFIG_TYPE_TEXT), "'SWH'", "2", "'$desc'", "null",
    "null");

  $variable = "SwhContent";
  $prompt = _('Content endpoint');
  $desc = _('Endpoint to get content about file');
  $valueArray[$variable] = array("'$variable'", "'/license'", "'$prompt'",
    strval(CONFIG_TYPE_TEXT), "'SWH'", "3", "'$desc'", "null", "null");

  $variable = "SwhSleep";
  $prompt = _('Max sleep time');
  $desc = _('Max time to sleep for rate-limit. Note: This concerns with scheduler heartbeat.');
  $valueArray[$variable] = array("'$variable'", "100", "'$prompt'",
    strval(CONFIG_TYPE_INT), "'SWH'", "4", "'$desc'", "null", "null");

  $variable = "SwhToken";
  $prompt = _('Auth token');
  $desc = _('');
  $valueArray[$variable] = array("'$variable'", "''", "'$prompt'",
    strval(CONFIG_TYPE_PASSWORD), "'SWH'", "5", "'$desc'", "null", "null");

  /* Doing all the rows as a single insert will fail if any row is a dupe.
   So insert each one individually so that new variables get added.
  */
  foreach ($valueArray as $variable => $values) {
    /*
     * Check if the variable already exists. Insert it if it does not.
     * This is better than an insert ignoring duplicates, because that
     * generates a postresql log message.
     */
    $VarRec = GetSingleRec("sysconfig", "WHERE variablename='$variable'");
    if (empty($VarRec)) {
      $sql = "INSERT INTO sysconfig (" . implode(",", $columns) . ") VALUES (" .
        implode(",", $values) . ");";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    } else { // Values exist, update them
      $updateString = [];
      foreach ($columns as $index => $column) {
        if ($index != 0 && $index != 1) { // Skip variablename and conf_value
          $updateString[] = $column . "=" . $values[$index];
        }
      }
      $sql = "UPDATE sysconfig SET " . implode(",", $updateString) .
        " WHERE variablename='$variable';";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    }
    unset($VarRec);
  }
}

/**
 * \brief Create the option_value column in sysconfig table.
 *
 * \return 0 if column already exists.
 * 1 if it was created
 */
function Create_option_value()
{
  global $PG_CONN;

  /* If sysconfig exists, then we are done */
  $sql = "SELECT column_name, data_type FROM information_schema.columns WHERE "
       . "table_name='sysconfig' and column_name='option_value' limit 1;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $numrows = pg_num_rows($result);
  $rows = pg_fetch_assoc($result);
  pg_free_result($result);
  if ($numrows > 0) {
    if ($rows['data_type'] == 'text') {
      return 0;
    }
  }

  if ($numrows < 1) {
    /* Create the option_value column */
    $sql = "ALTER TABLE sysconfig ADD COLUMN option_value text DEFAULT NULL;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
    /* Document columns */
    $sql = "COMMENT ON COLUMN sysconfig.option_value IS 'If vartype is 5, "
         . "provide options in format op1{val1}|op2{val2}|...';";
    /* this is a non critical update */
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
    return 1;
  }

  /* Column exists but with old data type */
  pg_query($PG_CONN, "BEGIN;");

  try {
    $sql = "ALTER TABLE sysconfig RENAME COLUMN option_value TO old_option_value;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    $sql = "ALTER TABLE sysconfig ADD COLUMN option_value text DEFAULT NULL;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    $sql = "COMMENT ON COLUMN sysconfig.option_value IS 'If vartype is 5, "
      . "provide options in format op1{val1}|op2{val2}|...';";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    $sql = "UPDATE sysconfig SET option_value = old_option_value;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);

    $sql = "ALTER TABLE sysconfig DROP COLUMN old_option_value;";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
  } catch (\Exception $e) {
    pg_query($PG_CONN, "ROLLBACK;");
    throw $e;
  }
  pg_query($PG_CONN, "COMMIT;");
}

/**
 * \brief Validation function check_boolean().
 *
 * Check if the value format is valid,
 * only true/false is valid
 *
 * \param string $value The value which will be checked
 *
 * \return 1, if the value is valid, or 0
 */
function check_boolean($value)
{
  if (! strcmp($value, 'true') || ! strcmp($value, 'false')) {
    return 1;
  } else {
    return 0;
  }
}

/**
 * \brief Validation function check_fossology_url().
 *
 * Check if the URL is valid.
 *
 * \param string $url The URL which will be checked
 *
 * \return  1: valid, 0: invalid
 */
function check_fossology_url($url)
{
  $url_array = explode("/", $url, 2);
  $name = $url_array[0];
  if (! empty($name)) {
    $hostname = exec("hostname -f");
    if (empty($hostname)) {
      $hostname = "localhost";
    }
    if (check_IP($name)) {
      $hostname1 = gethostbyaddr($name);
      if (strcmp($hostname, $hostname1) == 0) {
        return 0; // host is not reachable
      }
    }
    $server_name = $_SERVER['SERVER_NAME'];

    /* intput $name must match either the hostname or the server name */
    if (strcmp($name, $hostname) && strcmp($name, $server_name)) {
      return 0;
    }
  } else {
    return 0;
  }
  return 1;
}

/**
 * \brief Validation function check_logo_url().
 *
 * Check if the URL is available.
 *
 * \param string $url The URL which will be checked
 *
 * \return 1: available, 0: unavailable
 */
function check_logo_url($url)
{
  if (empty($url)) {
    return 1; /* logo url can be null, with the default */
  }
  // $res = check_url($url);
  $res = is_available($url);
  if (1 == $res) {
    return 1;
  } else {
    return 0;
  }
}

/**
 * \brief Validation function check_logo_image_url().
 *
 * Check if the URL is available.
 *
 * \param string $url The url which will be checked
 *
 * \return 1: the url is available, 0: unavailable
 */
function check_logo_image_url($url)
{
  global $SysConf;

  if (empty($url)) {
    return 1; /* logo url can be null, with the default */
  }
  $logoLink = @$SysConf["LogoLink"];
  $new_url = $logoLink . $url;
  if (is_available($url) || is_available($new_url)) {
    return 1;
  } else {
    return 0;
  }

}

/**
 * \brief Validation function check_email_address().
 *
 * Check if the email address is valid.
 * \todo Implement this function if needed in the future.
 *
 * \param string $email_address The email address which will be checked
 *
 * \return 1: valid, 0: invalid
 */
function check_email_address($email_address)
{
  return 1;
}

/**
 * \brief Check if the URL is available
 *
 * \param string $url  URL
 * \param int $timeout Timeout interval, default 2 seconds
 * \param int $tries   If unavailable, will try several times, default 2 times
 *
 * \return 1: available, 0: unavailable
 */
function is_available($url, $timeout = 2, $tries = 2)
{
  global $SysConf;

  $proxyStmts = "";
  if (array_key_exists('http_proxy', $SysConf['FOSSOLOGY']) &&
    $SysConf['FOSSOLOGY']['http_proxy']) {
    $proxyStmts .= "export http_proxy={$SysConf['FOSSOLOGY']['http_proxy']};";
  }
  if (array_key_exists('https_proxy', $SysConf['FOSSOLOGY']) &&
    $SysConf['FOSSOLOGY']['https_proxy']) {
    $proxyStmts .= "export https_proxy={$SysConf['FOSSOLOGY']['https_proxy']};";
  }
  if (array_key_exists('ftp_proxy', $SysConf['FOSSOLOGY']) &&
    $SysConf['FOSSOLOGY']['ftp_proxy']) {
    $proxyStmts .= "export ftp_proxy={$SysConf['FOSSOLOGY']['ftp_proxy']};";
  }

  $commands = "$proxyStmts wget --spider '$url' --tries=$tries --timeout=$timeout";
  system($commands, $return_var);
  if (0 == $return_var) {
    return 1;
  } else {
    return 0;
  }
}

/**
 * \brief Check if the url is valid
 * \param string $url The url which will be checked
 * \return 1: the url is valid, 0: invalid
 */
function check_url($url)
{
  if (empty($url) ||
    preg_match("@^((http)|(https)|(ftp))://([[:alnum:]]+)@i", $url) != 1 ||
    preg_match("@[[:space:]]@", $url) != 0) {
    return 0;
  } else {
    return 1;
  }
}

/**
 * \brief Check if the ip address is valid
 * \param string $ip IP address
 * \return 1: yes
 */
function check_IP($ip)
{
  $e = "([0-9]|1[0-9]{2}|[1-9][0-9]|2[0-4][0-9]|25[0-5])";
  return preg_match("/^$e\.$e\.$e\.$e$/", $ip);
}