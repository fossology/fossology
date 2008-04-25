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
  var $Name       = "licgrp";
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

     /* Create tables, ignore error if they already exist */
     $sql = "CREATE SEQUENCE licgroup_licgroup_pk_seq START 1;
             CREATE TABLE licgroup (
                licgroup_pk integer PRIMARY KEY DEFAULT nextval('licgroup_licgroup_pk_seq'),
                licgroup_name text UNIQUE,
                licgroup_desc text,
                licgroup_color integer UNIQUE
             );
             COMMENT ON COLUMN licgroup_name IS 'Name of License Group';
             COMMENT ON COLUMN licgroup_description IS 'Description of License Group';
             ";
     $Results = $DB->Action($sql);
     $sql = "CREATE SEQUENCE licgroup_lics_licgroup_lics_pk_seq START 1;
             CREATE TABLE licgroup_lics (
                licgroup_lics_pk integer PRIMARY KEY DEFAULT nextval('licgroup_lics_licgroup_lics_pk_seq'),
                licgroup_fk      integer FOREIGN KEY('licgroup.licgroup_pk'),
                lic_fk           integer FOREIGN KEY('agent_lic_raw.lic_pk')
             );
             ";
     $Results = $DB->Action($sql);
     $sql = "CREATE SEQUENCE licgroup_grps_licgroup_grps_pk_seq START 1;
             CREATE TABLE licgroup_grps (
                licgroup_grps_pk integer PRIMARY KEY DEFAULT nextval('licgroup_grps_licgroup_grps_pk_seq'),
                licgroup_fk      integer FOREIGN KEY('licgroup.licgroup_pk'),
                licgroup_memberfk integer FOREIGN KEY('licgroup.licgroup_pk'),
             );
             COMMENT ON COLUMN licgroup_fk IS 'Key of parent license group';
             COMMENT ON COLUMN licgroup_memberfk IS 'Key of license group that belongs to licgroup_fk';
             ";
     $Results = $DB->Action($sql);
   }

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
