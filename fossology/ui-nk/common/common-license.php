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
$LicenseGet_Prepared=0;
function LicenseGet($PfilePk, &$Lics)
{
  global $LicenseGet_Prepared;
  global $DB;
  if (empty($DB)) { return; }
  if (!$LicenseGet_Prepared)
    {
    $DB->Prepare("LicenseGet",'SELECT lic_fk FROM agent_lic_meta WHERE pfile_fk = $1;');
    $LicenseGet_Prepared=1;
    }
  $Results = $DB->Execute("LicenseGet",array($PfilePk));
  if (empty($Lics['Total'])) { $Lics['Total']=0; }
  foreach($Results as $R)
	{
	$LicFk = $R['lic_fk'];
	if (!empty($LicFk))
	  {
	  if (empty($Lics[$LicFk])) { $Lics[$LicFk]=1; }
	  else { $Lics[$LicFk]++; }
	  $Lics['Total']++;
	  }
	}
  return;
} // LicenseGet()

/************************************************************
 LicenseGetAll(): Return licenses for a uploadtree_pk.
 Can return empty array if there is no license.
 Returns NULL if not processed.
 NOTE: This is recursive!
 ************************************************************/
$LicenseGetAll_Prepared=0;
function LicenseGetAll($UploadtreePk, &$Lics)
{
  global $Plugins;
  global $DB;
  if (empty($DB)) { return; }
  if (empty($UploadtreePk)) { return NULL; }

  global $LicenseGetAll_Prepared;
  if (!$LicenseGetAll_Prepared)
    {
    $DB->Prepare("LicenseGetAll",'SELECT uploadtree_pk,ufile_mode,pfile_fk FROM uploadtree INNER JOIN ufile ON ufile_fk = ufile_pk WHERE parent = $1;');
    $LicenseGetAll_Prepared = 1;
    }
  /* Find every item under this UploadtreePk... */
  $Results = $DB->Execute("LicenseGetAll",array($UploadtreePk));
  if (!empty($Results) && (count($Results) > 0))
    {
    foreach($Results as $R)
      {
      if (!empty($R['pfile_fk']))
	{
	LicenseGet($R['pfile_fk'],$Lics);
	}
      if (Iscontainer($R['ufile_mode']))
	{
	LicenseGetAll($R['uploadtree_pk'],$Lics);
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
  global $DB;
  if (empty($DB)) { return; }

} // LicenseHist()

/************************************************************
 LicenseShowText(): Given a pfile, display the license contents.
 This writes to stdout!
 ************************************************************/
function LicenseShowText($PfilePk, $Flow=1)
{
  global $Plugins;
  global $DB;
  if (empty($DB)) { return; }

  return($Results);
} // LicenseShowText()

?>
