<?php
/***********************************************************
 Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

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

/************************************************
 Plugin for License Groups
 *************************************************/
class licgroup extends FO_Plugin
  {
  var $Name       = "license_groups";
  var $Title      = "License Groups";
  var $Version    = "1.0";
  var $Dependency = array("db","browse");
  var $DBaccess   = PLUGIN_DB_WRITE;
  var $LoginFlag  = 0;

  /***********************************************************
   RegisterMenus(): Customize submenus.
   ***********************************************************/
  function RegisterMenus()
  {
    $URI = $this->Name . Traceback_parm_keep(array("show","format","page","upload","item","ufile","pfile"));

    $Item = GetParm("item",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    if (!empty($Item) && !empty($Upload))
    {
       menu_insert("Browse::License Groups",1, $URI);
    }

  } // RegisterMenus()

  /***********************************************************
   Install(): Create and configure database tables
   ***********************************************************/
   function Install()
   {
     global $DB;
     if (empty($DB)) { return(1); } /* No DB */

    /* Create TABLE licgroup if it does not exist */
    $SQL = "SELECT table_name AS table
	FROM information_schema.tables
	WHERE table_type = 'BASE TABLE'
	AND table_schema = 'public'
	AND table_name = 'licgroup';";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['table']))
      {
      $SQL1 = "CREATE SEQUENCE licgroup_licgroup_pk_seq START 1;";
      $DB->Action($SQL1);
      $SQL1 = "CREATE TABLE licgroup (
	licgroup_pk integer PRIMARY KEY DEFAULT nextval('licgroup_licgroup_pk_seq'),
	licgroup_name text UNIQUE,
	licgroup_desc text,
	licgroup_color text
        );
	COMMENT ON COLUMN licgroup.licgroup_name IS 'Name of License Group';
	COMMENT ON COLUMN licgroup.licgroup_desc IS 'Description of License Group';
	COMMENT ON COLUMN licgroup.licgroup_color IS 'Color to associate with License Group (#RRGGBB)';
	";
      $DB->Action($SQL1);
      $Results = $DB->Action($SQL);
      if (empty($Results[0]['table']))
        {
	printf("ERROR: Failed to create table: licgroup\n");
	return(1);
	}
      } /* create TABLE licgroup */

    /* Create TABLE licgroup_lics if it does not exist */
    $SQL = "SELECT table_name AS table
	FROM information_schema.tables
	WHERE table_type = 'BASE TABLE'
	AND table_schema = 'public'
	AND table_name = 'licgroup_lics';";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['table']))
      {
      $SQL1 = "CREATE SEQUENCE licgroup_lics_licgroup_lics_pk_seq START 1;";
      $DB->Action($SQL1);
      $SQL1 = "CREATE TABLE licgroup_lics (
	licgroup_lics_pk integer PRIMARY KEY DEFAULT nextval('licgroup_lics_licgroup_lics_pk_seq'),
	licgroup_fk      integer,
	lic_fk   integer,
	CONSTRAINT only_one UNIQUE (licgroup_lics_pk, licgroup_fk),
	CONSTRAINT licgroup_exist FOREIGN KEY(licgroup_fk) REFERENCES licgroup(licgroup_pk) ON UPDATE RESTRICT ON DELETE RESTRICT
	);
	COMMENT ON COLUMN licgroup_lics.licgroup_fk IS 'Parent License Group';
	COMMENT ON COLUMN licgroup_lics.lic_fk IS 'License in Group';
	";
// Commented out because 'there is no unique constraint matching given keys for referenced table "agent_lic_raw"'  -- Leave it for Bob to resolve. :-)
//	CONSTRAINT lic_exist FOREIGN KEY(lic_fk) REFERENCES agent_lic_raw(lic_pk) ON UPDATE RESTRICT ON DELETE RESTRICT
      $DB->Action($SQL1);
      $Results = $DB->Action($SQL);
      if (empty($Results[0]['table']))
        {
	printf("ERROR: Failed to create table: licgroup_lics\n");
	return(1);
	}
      } /* create TABLE licgroup_lics */

    /* Check if TABLE licgroup_grps exists */
    $SQL = "SELECT table_name AS table
	FROM information_schema.tables
	WHERE table_type = 'BASE TABLE'
	AND table_schema = 'public'
	AND table_name = 'licgroup_grps';";
    $Results = $DB->Action($SQL);
    if (empty($Results[0]['table']))
      {
      $SQL1 = "CREATE SEQUENCE licgroup_grps_licgroup_grps_pk_seq START 1;
	CREATE TABLE licgroup_grps (
	licgroup_grps_pk integer PRIMARY KEY DEFAULT nextval('licgroup_grps_licgroup_grps_pk_seq'),
	licgroup_fk      integer,
	licgroup_memberfk integer,
	CONSTRAINT only_one UNIQUE (licgroup_fk, licgroup_memberfk),
	CONSTRAINT licgroup_exist FOREIGN KEY(licgroup_fk) REFERENCES licgroup(licgroup_pk) ON UPDATE RESTRICT ON DELETE RESTRICT,
	CONSTRAINT licgroupmember_exist FOREIGN KEY(licgroup_memberfk) REFERENCES licgroup(licgroup_pk) ON UPDATE RESTRICT ON DELETE RESTRICT
	);
	COMMENT ON COLUMN licgroup_grps.licgroup_fk IS 'Key of parent license group';
	COMMENT ON COLUMN licgroup_grps.licgroup_memberfk IS 'Key of license group that belongs to licgroup_fk';
	";
      $DB->Action($SQL1);
      $Results = $DB->Action($SQL);
      if (empty($Results[0]['table']))
        {
	printf("ERROR: Failed to create table: licgroup_grps\n");
	return(1);
	}
      } /* create TABLE licgroup_grps */
   return(0);
   } // Install()

  /***********************************************************
   Output(): This function is called when user output is
   requested.  This function is responsible for content.
   (OutputOpen and Output are separated so one plugin
   can call another plugin's Output.)
   This uses $OutputType.
   The $ToStdout flag is "1" if output should go to stdout, and
   0 if it should be returned as a string.  (Strings may be parsed
   and used by other plugins.)
   ***********************************************************/
  function Output()
    {
echo "<h1>in ui-licgroup Output()</h1>";
    }

  };
$NewPlugin = new licgroup;
$NewPlugin->Initialize();
?>
