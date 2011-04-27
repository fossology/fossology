<?php
/***********************************************************
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

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

/*************************************************
 System configuration function library.
 *************************************************/

/* Global Constants */
  /* Data types for sysconfig table */
  define("CONFIG_TYPE_INT", 1);
  define("CONFIG_TYPE_TEXT", 2);
  define("CONFIG_TYPE_TEXTAREA", 3);


  /***********************************************************
   ConfigInit(): Initialize the system with anything that
   must happen before plugins are loaded.

   If the sysconfig table doesn't exist then create it.
   Write records for the core variables into sysconfig table.

   Return the $SysConf array of values (for global $SysConf).
   ***********************************************************/
  function ConfigInit()
  {
    global $PG_CONN;

    $SysConf = array();

    /* create if it doesn't exist */
    $NewTable = Create_sysconfig();

    /* populate it with core variables */
    Populate_sysconfig();

    /* populate the global $SysConf array with variable/value pairs */
    $sql = "select variablename, conf_value from sysconfig";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);

    while($row = pg_fetch_assoc($result))
    {
      $SysConf[$row['variablename']] = $row['conf_value'];
    }
    pg_free_result($result);

    return($SysConf);
  } // ConfigInit()


  /************************************************
   Create_sysconfig()
   Create the sysconfig table.
   Return 0 if table already exists.
          1 if it was created
   ************************************************/
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
    $result = @pg_query($PG_CONN, $sql);
    return 1;
  }


  /************************************************
   Populate_sysconfig()
   Populate the sysconfig table with core variables.
   ************************************************/
  function Populate_sysconfig()
  {
    global $PG_CONN;

    $Columns = "variablename, conf_value, ui_label, vartype, group_name, group_order, description";
    $ValueArray = array();

    /*  Email */
    $Variable = "SupportEmailLabel";
    $SupportEmailLabelPrompt = _('Support Email Label');
    $SupportEmailLabelDesc = _('e.g. "Support"<br>Text that the user clicks on to create a new support email. This new email will be preaddressed to this support email address and subject.  HTML is ok.');
    $ValueArray[$Variable] = "'$Variable', 'Support', '$SupportEmailLabelPrompt',"
                    . CONFIG_TYPE_TEXT .
                    ",'Support', 1, '$SupportEmailLabelDesc'";

    $Variable = "SupportEmailAddr";
    $SupportEmailAddrPrompt = _('Support Email Address');
    $SupportEmailAddrDesc = _('e.g. "support@mycompany.com"<br>Individual or group email address to those providing FOSSology support.');
    $ValueArray[$Variable] = "'$Variable', null, '$SupportEmailAddrPrompt', "
                    . CONFIG_TYPE_TEXT .
                    ",'Support', 2, '$SupportEmailAddrDesc'";

    $Variable = "SupportEmailSubject";
    $SupportEmailSubjectPrompt = _('Support Email Subject line');
    $SupportEmailSubjectDesc = _('e.g. "fossology support"<br>Subject line to use on support email.');
    $ValueArray[$Variable] = "'$Variable', 'FOSSology Support', '$SupportEmailSubjectPrompt',"
                    . CONFIG_TYPE_TEXT .
                    ",'Support', 3, '$SupportEmailSubjectDesc'";

    /*  Banner Message */
    $Variable = "BannerMsg";
    $BannerMsgPrompt = _('Banner message');
    $BannerMsgDesc = _('This is message will be displayed on every page with a banner.  HTML is ok.');
    $ValueArray[$Variable] = "'$Variable', null, '$BannerMsgPrompt', "
                    . CONFIG_TYPE_TEXTAREA .
                    ",'Banner', 1, '$BannerMsgDesc'";

    /*  Logo  */
    $Variable = "LogoImage";
    $LogoImagePrompt = _('Logo Image URL');
    $LogoImageDesc = _('e.g. "http://mycompany.com/images/companylogo.png" or "images/mylogo.png"<br>This image replaces the fossology project logo. Image is constrained to 150px wide.  80-100px high is a good target.');
    $ValueArray[$Variable] = "'$Variable', null, '$LogoImagePrompt', "
                    . CONFIG_TYPE_TEXT .
                    ",'Logo', 1, '$LogoImageDesc'";

    $Variable = "LogoLink";
    $LogoLinkPrompt = _('Logo URL');
    $LogoLinkDesc = _('e.g. "http://mycompany.com/fossology"<br>URL a person goes to when they click on the logo');
    $ValueArray[$Variable] = "'$Variable', null, '$LogoLinkPrompt', "
                    . CONFIG_TYPE_TEXT .
                    ",'Logo', 2, '$LogoLinkDesc'" ;
     
    $Variable = "GlobalBrowse";
    $BrowsePrompt = _("Global Browsing");
    $BrowseDesc = _("true = allow browsing and searching the entire repository.<br>false = user can only browse/search their own uploads.");
    $ValueArray[$Variable] = "'$Variable', 'false', '$BrowsePrompt', "
                    . CONFIG_TYPE_INT .
                    ",'UI', 1, '$BrowseDesc'";
     
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
      }
      unset($VarRec);
    }
  }
?>
