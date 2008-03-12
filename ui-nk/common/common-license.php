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
function LicenseGet(&$PfilePk, &$Lics)
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
function LicenseGetAll(&$UploadtreePk, &$Lics)
{
  global $Plugins;
  global $DB;
  if (empty($DB)) { return; }
  if (empty($UploadtreePk)) { return NULL; }

  global $LicenseGetAll_Prepared;
  if (!$LicenseGetAll_Prepared)
    {
    $DB->Prepare("LicenseGetAll",'SELECT uploadtree_pk,ufile_mode,ufile.ufile_pk,ufile.pfile_fk,lic_fk FROM uploadtree INNER JOIN ufile ON ufile_fk = ufile_pk AND parent = $1 LEFT OUTER JOIN agent_lic_meta ON agent_lic_meta.pfile_fk = ufile.pfile_fk;');
    $LicenseGetAll_Prepared = 1;
    }
  /* Find every item under this UploadtreePk... */
  $Results = $DB->Execute("LicenseGetAll",array($UploadtreePk));
  if (!empty($Results) && (count($Results) > 0))
    {
    foreach($Results as $R)
      {
      $LicFk = $R['lic_fk'];
      if (!empty($LicFk))
	{
	if (empty($Lics[$LicFk])) { $Lics[$LicFk]=1; }
	else { $Lics[$LicFk]++; }
	$Lics['Total']++;
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
 LicenseGetAllFiles(): Returns all files under a tree that
 contain the same license.
 Returns NULL if no files.
 NOTE: This is recursive!
 ************************************************************/
$LicenseGetAllFiles_1_Prepared = 0;
$LicenseGetAllFiles_2_Prepared = 0;
function LicenseGetAllFiles(&$UploadtreePk, &$Lics, &$WantLic, &$Max, &$Offset)
{
  global $Plugins;
  global $DB;
  if (empty($DB)) { return; }
  if (empty($UploadtreePk)) { return NULL; }

  global $LicenseGetAllFiles_1_Prepared;
  global $LicenseGetAllFiles_2_Prepared;

  if (!$LicenseGetAllFiles_1_Prepared)
    {
    /* SQL to get all files with a specific license */
    $DB->Prepare("LicenseGetAllFiles_1",'SELECT DISTINCT ufile_name,uploadtree_pk,ufile_mode,ufile.ufile_pk,ufile.pfile_fk,lic_fk,lic_id,tok_pfile,tok_license,tok_match,phrase_text
	FROM uploadtree
	INNER JOIN ufile ON ufile_fk = ufile_pk AND uploadtree.parent = $1
	INNER JOIN agent_lic_meta ON agent_lic_meta.pfile_fk = ufile.pfile_fk
	INNER JOIN agent_lic_raw ON agent_lic_meta.lic_fk = agent_lic_raw.lic_pk
	AND agent_lic_raw.lic_id = $2
	ORDER BY ufile.ufile_pk
	;');
    $LicenseGetAllFiles_1_Prepared = 1;
    }

  if (!$LicenseGetAllFiles_2_Prepared)
    {
    /* SQL to get all containers for recursing */
    $Bit = 1<<29;
    $DB->Prepare("LicenseGetAllFiles_2","SELECT uploadtree_pk
	FROM uploadtree
	INNER JOIN ufile ON ufile_fk = ufile_pk AND uploadtree.parent = $1
	AND ufile_mode & $Bit != 0
	ORDER BY ufile.ufile_pk
	;");
    $LicenseGetAllFiles_2_Prepared = 1;
    }

  /* Find every item under this UploadtreePk... */
  $Results = $DB->Execute("LicenseGetAllFiles_1",array($UploadtreePk,$WantLic));
  foreach($Results as $R)
    {
    if (empty($R['pfile_fk'])) { continue; }
    if ($Offset <= 0)
      {
      array_push($Lics,$R);
      $Max--;
      if ($Max <= 0) { return; }
      }
    else { $Offset--; }
    }

  /* Find the items to recurse on */
  $Results = $DB->Execute("LicenseGetAllFiles_2",array($UploadtreePk));
  foreach($Results as $R)
	{
	LicenseGetAllFiles($R['uploadtree_pk'],$Lics,$WantLic,$Max,$Offset);
	if ($Max <= 0) { return; }
	}
  return;
} // LicenseGetAllFiles()

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
