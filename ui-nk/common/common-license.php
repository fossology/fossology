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

/************************************************************
 LicenseGet(): Return licenses for a pfile.
 Can return empty array if there is no license.
 ************************************************************/
function LicenseGet(&$DB, $PfilePk, &$Lics)
{
  // global $Plugins;
  // $DB = &$Plugins[plugin_find_id("db")];
  // if (empty($DB)) { return; }
  // if (empty($PfilePk)) { return; }

  $Sql = "SELECT lic_fk FROM agent_lic_meta WHERE pfile_fk = $PfilePk;";
  $Results = $DB->Action($Sql);
  foreach($Results as $R)
	{
	if (!empty($R['lic_fk'])) { $Lics[] = $R['lic_fk']; }
	}
  return;
} // LicenseGet()

/************************************************************
 LicenseGetAll(): Return licenses for a uploadtree_pk.
 Can return empty array if there is no license.
 Returns NULL if not processed.
 NOTE: This is recursive!
 ************************************************************/
function LicenseGetAll(&$DB, $UploadtreePk, &$Lics, $Prepare=0)
{
  global $Plugins;
  $DB = &$Plugins[plugin_find_id("db")];
  if (empty($DB)) { return; }
  if (empty($UploadtreePk)) { return NULL; }

  if ($Prepare==0)
    {
    $Prepare = 1;
    $DB->Prepare("LicenseGetAll",'SELECT uploadtree_pk,ufile_mode,pfile_fk FROM uploadtree INNER JOIN ufile ON ufile_fk = ufile_pk WHERE parent = $1;');
    }
  /* Find every item under this UploadtreePk... */
  $Results = $DB->Execute("LicenseGetAll",array("$UploadtreePk"));
  if (!empty($Results) && (count($Results) > 0))
    {
    foreach($Results as $R)
      {
      if (!empty($R['pfile_fk']))
	{
	LicenseGet($DB,$R['pfile_fk'],$Lics);
	}
      if (Iscontainer($R['ufile_mode']))
	{
	LicenseGetAll($DB,$R['uploadtree_pk'],$Lics,1);
	}
      }
    }
  return;
} // LicenseGetAll()

/************************************************************
 LicenseHist(): Given an artifact directory (uploadtree_pk),
 return the license historgram.
 NOTE: This is recursive!
 ************************************************************/
function LicenseHist($UploadtreePk)
{
  global $Plugins;
  $DB = &$Plugins[plugin_find_id("db")];
  if (empty($DB)) { return; }

} // LicenseHist()

/************************************************************
 LicenseShowText(): Given a pfile, display the license contents.
 This writes to stdout!
 ************************************************************/
function LicenseShowText($PfilePk, $Flow=1)
{
  global $Plugins;
  $DB = &$Plugins[plugin_find_id("db")];
  if (empty($DB)) { return; }

  return($Results);
} // LicenseShowText()

?>
