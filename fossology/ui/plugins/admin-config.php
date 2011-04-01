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
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

define("TITLE_foconfig", _("Configuration Variables"));

class foconfig extends FO_Plugin
{
  var $Name       = "foconfig";
  var $Version    = "1.0";
  var $Title      = TITLE_foconfig;
  var $MenuList   = "Admin::Customize";
  var $Dependency = array("db");
  var $DBaccess   = PLUGIN_DB_USERADMIN;
  var $CreateAttempts = 0;
  public $PluginLevel = 50;    // run before 'regular' plugins

  /* constants but defined as variables because of easier usage in code */
  var $vartype_int = 1;
  var $vartype_text = 2;
  var $vartype_textarea = 3;


  /***********************************************************
   Install(): Create and configure database tables
   If the sysconfig table doesn't exist then
   create it
   create records for the core variables.
   ***********************************************************/
  function Install()
  {
    global $PG_CONN;

    if (empty($PG_CONN)) { return(1); } /* No DB */

    /* create if it doesn't exist */
    $this->Create_sysconfig();

    /* populate it with core variables */
    $this->Populate_sysconfig();

    return(0);
  } // Install()


  /************************************************
   Create_sysconfig()
   Create the sysconfig table.
   ************************************************/
  function Create_sysconfig()
  {
    global $PG_CONN;

    /* If sysconfig exists, then we are done */
    $sql = "SELECT typlen  FROM pg_type where typname='sysconfig' limit 1";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    if (pg_num_rows($result) > 0) return 0;
    pg_free_result($result);

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
    $result = @pg_send_query($PG_CONN, $sql);
    return 0;
  }


  /************************************************
   Populate_sysconfig()
   Populate the sysconfig table with core variables.
   Any plugins will load their own config variables.
   ************************************************/
  function Populate_sysconfig()
  {
    global $PG_CONN;

    $Columns = "variablename, conf_value, ui_label, vartype, group_name, group_order, description";
    $ValueArray = array();

    /*  Email */
    $SupportEmailLabelPrompt = _('Support Email Label');
    $SupportEmailLabelDesc = _('e.g. "Support"<br>Text that the user clicks on to create a new support email. This new email will be preaddressed to this support email address and subject.  HTML is ok.');
    $ValueArray[] = "'SupportEmailLabel', 'Support', '$SupportEmailLabelPrompt',  $this->vartype_text, 'Support', 1, '$SupportEmailLabelDesc'";

    $SupportEmailAddrPrompt = _('Support Email Address');
    $SupportEmailAddrDesc = _('e.g. "support@mycompany.com"<br>Individual or group email address to those providing FOSSology support.');
    $ValueArray[] = "'SupportEmailAddr', null, '$SupportEmailAddrPrompt', $this->vartype_text, 'Support', 2, '$SupportEmailAddrDesc'";

    $SupportEmailSubjectPrompt = _('Support Email Subject line');
    $SupportEmailSubjectDesc = _('e.g. "fossology support"<br>Subject line to use on support email.');
    $ValueArray[] = "'SupportEmailSubject', 'FOSSology Support', '$SupportEmailSubjectPrompt', $this->vartype_text, 'Support', 3, '$SupportEmailSubjectDesc'";

    /*  Banner Message */
    $BannerMsgPrompt = _('Banner message');
    $BannerMsgDesc = _('This is message will be displayed on every page with a banner.  HTML is ok.');
    $ValueArray[] = "'BannerMsg', null, '$BannerMsgPrompt', $this->vartype_textarea, 'Banner', 1, '$BannerMsgDesc'";

    /*  Logo  */
    $LogoImagePrompt = _('Logo Image URL');
    $LogoImageDesc = _('e.g. "http://mycompany.com/images/companylogo.png" or "images/mylogo.png"<br>This image replaces the fossology project logo. Image is constrained to 150px wide.  80-100px high is a good target.');
    $ValueArray[] = "'LogoImage', null, '$LogoImagePrompt', $this->vartype_text, 'Logo', 1, '$LogoImageDesc'";

    $LogoLinkPrompt = _('Logo URL');
    $LogoLinkDesc = _('e.g. "http://mycompany.com/fossology"<br>URL a person goes to when they click on the logo');
    $ValueArray[] = "'LogoLink', null, '$LogoLinkPrompt', $this->vartype_text, 'Logo', 2, '$LogoLinkDesc'" ;
     
    $BrowsePrompt = _("Allow Public Browsing");
    $BrowseDesc = _("Allow anyone to browse the repository, even if not logged in.");
    $ValueArray[] = "'PublicBrowse', TRUE, '$BrowsePrompt', $this->vartype_int,
      'UI', 1, '$BrowseDesc'";
     
    $SearchPrompt = _("Allow Global Searches");
    $SearchDesc = _("Allow searching all folders in the system, even if not logged in.");
    $ValueArray[] = "'GlobalSearch', TRUE, '$SearchPrompt', $this->vartype_int,
      'UI', 1, '$SearchDesc'";
     
    /* Doing all the rows as a single insert will fail if any row is a dupe.
     So insert each one individually so that new variables get added.
     */
    foreach ($ValueArray as $Values)
    {
      $sql = "insert into sysconfig ({$Columns}) values ($Values);";
      $result = @pg_query($PG_CONN, $sql);
      if ($result===false && strpos(pg_last_error($PG_CONN), 'duplicate key') === FALSE)
      DBCheckResult($result, $sql, __FILE__, __LINE__);
    }
  }


  /************************************************
   HTMLout(): Generate HTML output.
   ************************************************/
  function HTMLout()
  {
    global $PG_CONN;
    $OutBuf="";

    /* get config variables from db */
    $sql = "select * from sysconfig order by group_name, group_order";
    $result = @pg_query($PG_CONN, $sql);
    if ($result === false)
    {
      if (($this->CreateAttempts > 0) || (strpos(pg_last_error(), 'relation "sysconfig" does not exist') === FALSE))
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      else
      {
        /* failure was because the sysconfig table hasn't been created yet.
         So create it, but don't try more than once.
         */
        $this->CreateAttempts++;
        $this->Create_sysconfig();
        $this->Populate_sysconfig();
        $this->HTMLout();
        return;
      }
    }

    $Group = "";
    $InputStyle = "style='background-color:#dbf0f7'";
    $OutBuf .= "<form method='POST'>";
    while ($row = pg_fetch_assoc($result))
    {
      if ($Group != $row['group_name'])
      {
        if ($Group) $OutBuf .= "</table><br>";
        $Group = $row['group_name'];
        $OutBuf .= "<table border=1>";
      }

      $OutBuf .= "<tr><td>$row[ui_label]</td><td>";
      switch ($row['vartype'])
      {
        case $this->vartype_int:
        case $this->vartype_text:
          $ConfVal = htmlentities($row['conf_value']);
          $OutBuf .= "<INPUT type='text' name='new[$row[variablename]]' size='70' value='$ConfVal' title='$row[description]' $InputStyle>";
          $OutBuf .= "<br>$row[description]";
          break;
        case $this->vartype_textarea:
          $ConfVal = htmlentities($row['conf_value']);
          $OutBuf .= "<br><textarea name='new[$row[variablename]]' rows=3 cols=80 title='$row[description]' $InputStyle>$ConfVal</textarea>";
          $OutBuf .= "<br>$row[description]";
          break;
        default:
          $OutBuf .= "Invalid configuration variable.  Unknown type.";
      }
      $OutBuf .= "</td></tr>";
      $OutBuf .= "<INPUT type='hidden' name='old[$row[variablename]]' value='$ConfVal'>";
    }
    $OutBuf .= "</table>";
    pg_free_result($result);

    $btnlabel = _("Update");
    $OutBuf .= "<p><input type='submit' value='$btnlabel'>";
    $OutBuf .= "</form>";

    return $OutBuf;
  }

  /************************************************
   Output(): Generate output.
   ************************************************/
  function Output()
  {
    global $PG_CONN;
    global $Plugins;

    if ($this->State != PLUGIN_STATE_READY) { return; }
    if (empty($PG_CONN)) return;

    $newarray = GetParm("new", PARM_RAW);
    $oldarray = GetParm("old", PARM_RAW);

    //debugprint($newarray, "New array");
    //debugprint($oldarray, "Old array");

    /* Compare new and old array
     * and update DB with new values */
    $UpdateMsg = "";
    if (!empty($newarray))
    {
      foreach($newarray as $VarName => $VarValue)
      {
        if ($VarValue != $oldarray[$VarName])
        {
          $sql = "update sysconfig set conf_value='" .
          pg_escape_string($VarValue) .
                    "' where variablename='$VarName'";
          $result = pg_query($PG_CONN, $sql);
          DBCheckResult($result, $sql, __FILE__, __LINE__);
          if (!empty($UpdateMsg)) $UpdateMsg .= ", ";
          $UpdateMsg .= "$VarName";
        }
      }
      if (!empty($UpdateMsg)) $UpdateMsg .= " updated.";
    }

    /* Verify that the tables and all the core variables are defined  */
    $this->Create_sysconfig();
    $this->Populate_sysconfig();

    $OutBuf = '';
    switch($this->OutputType)
    {
      case "XML":
        break;
      case "HTML":
        if ($UpdateMsg) $OutBuf .= "<span style='background-color:#ff8a8a'>$UpdateMsg</style><hr>";
        $OutBuf .= $this->HTMLout();
        break;
      case "Text":
        break;
      default:
        break;
    }
    if (!$this->OutputToStdout) { return($OutBuf); }
    print($OutBuf);
    return;
  } // Output()

};
$NewPlugin = new foconfig;
$NewPlugin->Initialize();
?>
