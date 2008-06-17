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
 Developer notes:

 The confidence is a number used to identify how good the template is.
 Values are:
   NULL (not present) = use the template's name.
   0 = high confidence. Use the template's name.  (Ignore canonical.)
   1 = medium confidence. Most of the template matched.  Call it 'style'.
   2 = low confidence. Part of the template matched.  Call it 'partial'.
   3 = no confidence. The canonical identifier must be present and
       will be used.

 The canonical is the canonical name (licterm_pk from licterm).
 When the confidence is 3, the canonical name will be used.
 ************************************************************/

/************************************************************
 LicenseNormalizeName(): Given a name, remove all of the
 extraneous text.
 ************************************************************/
function LicenseNormalizeName	($LicName,$Confidence,$CanonicalName)
{
  /* Find the right name to use */
  $Name = '';
  if ($Confidence >= 3) { $Name = $CanonicalName; }
  else
    {
    if (!empty($CanonicalName)) { $Name = $CanonicalName; }
    else
      {
      $Name = $LicName;
      $Name = preg_replace("@.*/@","",$Name);
      $Name = preg_replace("/ part.*/","",$Name);
      $Name = preg_replace("/ short.*/","",$Name);
      $Name = preg_replace("/ variant.*/","",$Name);
      $Name = preg_replace("/ reference.*/","",$Name);
      }
    if ($Confidence == 1) { $Name = "'$Name'-style"; }
    else if ($Confidence == 2) { $Name = "'$Name'-partial"; }
    }
  return($Name);
} // LicenseNormalizeName()

/************************************************************
 LicenseGetName(): Given a meta id (agent_lic_meta_pk), return
 the license name.
 ************************************************************/
$LicenceGetName_Prepared=0;
function LicenseGetName(&$MetaId, $IncludePhrase=0)
{
  global $DB;
  global $LicenceGetName_Prepared;
  if (!$LicenceGetName_Prepared)
    {
    $DB->Prepare("LicenseGetName_List",'SELECT agent_lic_raw.lic_name,agent_lic_meta.phrase_text,licterm_name.licterm_name_confidence,licterm.licterm_name
	FROM licterm_name
	INNER JOIN agent_lic_meta ON agent_lic_meta_fk = agent_lic_meta_pk
	AND agent_lic_meta_fk = $1
	INNER JOIN agent_lic_raw ON lic_fk = lic_pk
	LEFT OUTER JOIN licterm_maplic ON licterm_maplic.lic_fk = lic_id
	LEFT OUTER JOIN licterm ON licterm_pk = licterm_name.licterm_fk
	OR licterm_pk = licterm_maplic.licterm_fk
	ORDER BY licterm_name_confidence,licterm_name;');
    $LicenceGetName_Prepared=1;
    }

  $FullName='';
  $Results = $DB->Execute("LicenseGetName_List",array($MetaId));
  $LastConfidence=$Results[0]['licterm_name_confidence'];
  for($i=0; !empty($Results[$i]['lic_name']); $i++)
    {
    /* Get the components */
    $Name = $Results[$i]['lic_name'];
    $Confidence = $Results[$i]['licterm_name_confidence'];
    $LicTerm = $Results[$i]['licterm_name'];
    $Phrase =  $Results[$i]['phrase_text'];

    /* Normalize the name */
    $Name = LicenseNormalizeName($Name,$Confidence,$LicTerm);

    if (!empty($Phrase) && ($Confidence < 3))
      {
      $Name = "Phrase";
      if ($IncludePhrase) { $Name .= ": $Phrase"; }
      }

    /* Store it */
    if (!empty($FullName))
	{
	if (empty($LastConfidence) || ($LastConfidence < 3) && ($Confidence >= 3) ) { $FullName .= " + "; }
	else { $FullName .= ", "; }
	}
    $FullName .= $Name;
    $LastConfidence = $Confidence;
    }

  return($FullName);
} // LicenseGetName()

/************************************************************
 LicenseGet(): Return licenses for a pfile.
 May return empty array if there is no license.
 ************************************************************/
$LicenseGet_Prepared=0;
function LicenseGet(&$PfilePk, &$Lics, $GetPks=0)
{
  global $LicenseGet_Prepared;
  global $DB;
  if (empty($DB)) { return; }
  if (!$LicenseGet_Prepared)
    {
    $DB->Prepare("LicenseGet_License",'SELECT lic_name,licterm_name_confidence,licterm_name,lic_pk
	FROM licterm_name
	INNER JOIN agent_lic_meta ON licterm_name.pfile_fk = $1
	AND agent_lic_meta_pk = agent_lic_meta_fk
	INNER JOIN agent_lic_raw ON lic_fk = lic_pk
	LEFT OUTER JOIN licterm_maplic ON licterm_maplic.lic_fk = lic_pk
	LEFT OUTER JOIN licterm ON licterm_pk = licterm_name.licterm_fk
	OR licterm_pk = licterm_maplic.licterm_fk;');
    $LicenseGet_Prepared=1;
    }
  $Results = $DB->Execute("LicenseGet_License",array($PfilePk));

  if (empty($Lics[' Total '])) { $Lics[' Total ']=0; }
  foreach($Results as $R)
    {
    if ($GetPks)
	{
	$LicName = $R['lic_pk'];
	}
    else
	{
	$LicName = LicenseNormalizeName($R['lic_name'],$R['licterm_name_confidence'],$R['licterm_name']);
	}
    if (!empty($LicName))
	{
	if (empty($Lics[$LicName])) { $Lics[$LicName]=1; }
	else { $Lics[$LicName]++; }
	$Lics[' Total ']++;
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
function LicenseGetAll(&$UploadtreePk, &$Lics, $GetPks=0)
{
  global $Plugins;
  global $DB;
  if (empty($DB)) { return; }
  if (empty($UploadtreePk)) { return NULL; }

  global $LicenseGetAll_Prepared;
  if (!$LicenseGetAll_Prepared)
    {
    $DB->Prepare("LicenseGetAll_Licenses",'SELECT agent_lic_raw.lic_name,licterm_name.licterm_name_confidence,licterm.licterm_name,lic_pk
	FROM uploadtree
	INNER JOIN agent_lic_meta ON agent_lic_meta.pfile_fk = uploadtree.pfile_fk
	INNER JOIN licterm_name ON agent_lic_meta_fk = agent_lic_meta_pk
	INNER JOIN agent_lic_raw ON lic_fk = lic_pk
	LEFT OUTER JOIN licterm_maplic ON licterm_maplic.lic_fk = lic_pk
	LEFT OUTER JOIN licterm ON licterm_pk = licterm_name.licterm_fk
	OR licterm_pk = licterm_maplic.licterm_fk
	WHERE parent = $1;');
    $DB->Prepare("LicenseGetAll_Traverse",'SELECT uploadtree_pk,ufile_mode FROM uploadtree WHERE parent = $1;');
    $LicenseGetAll_Prepared = 1;
    }

  /* Find every license under this UploadtreePk... */
  $Results = $DB->Execute("LicenseGetAll_Licenses",array($UploadtreePk));
  if (empty($Lics[' Total '])) { $Lics[' Total '] = 0; }
  if (!empty($Results) && (count($Results) > 0))
    {
    foreach($Results as $R)
      {
      if ($GetPks)
	{
	$LicFk = $R['lic_pk'];
	}
      else
	{
	$LicFk = LicenseNormalizeName($R['lic_name'],$R['licterm_name_confidence'],$R['licterm_name']);
	}
      if (!empty($LicFk))
	{
	if (empty($Lics[$LicFk])) { $Lics[$LicFk]=1; }
	else { $Lics[$LicFk]++; }
	$Lics[' Total ']++;
	}
      }
    }

  /* Recurse */
  $Results = $DB->Execute("LicenseGetAll_Traverse",array($UploadtreePk));
  for($i=0; !empty($Results[$i]['uploadtree_pk']); $i++)
    {
    if (Iscontainer($Results[$i]['ufile_mode']))
	{
	LicenseGetAll($Results[$i]['uploadtree_pk'],$Lics,$GetPks);
	}
    }
  return;
} // LicenseGetAll()

/************************************************************
 LicenseGetAllFilesByCanonicalName(): Given an uploadtree_pk,
 return all Pfile_pks that have the correct canonical/normalized name.
 NOTE: This is recursive!
 NOTE: Duplicate names are NOT returned. If the same file sees the
 same license 10 times, it will only be listed once.
 ************************************************************/
$LicenseGetAllFilesByCanonicalName_Prepared=0;
function LicenseGetAllFilesByCanonicalName (&$UploadtreePk, &$Lics, &$WantName)
{
  global $Plugins;
  global $DB;
  if (empty($DB)) { return; }
  if (empty($UploadtreePk)) { return NULL; }

  global $LicenseGetAllFilesByCanonicalName_Prepared;
  if (!$LicenseGetAllFilesByCanonicalName_Prepared)
    {
    $DB->Prepare("LicenseGetAllFilesByCanonicalName_Lics",'SELECT DISTINCT uploadtree.pfile_fk AS pfile,ufile_name,uploadtree_pk,uploadtree.ufile_mode,ufile.ufile_pk,agent_lic_raw.lic_name,licterm_name.licterm_name_confidence,licterm.licterm_name,lic_pk
	FROM uploadtree
	INNER JOIN agent_lic_meta ON agent_lic_meta.pfile_fk = uploadtree.pfile_fk
	INNER JOIN ufile ON ufile_fk = ufile_pk AND uploadtree.parent = $1
	INNER JOIN licterm_name ON agent_lic_meta_fk = agent_lic_meta_pk
	INNER JOIN agent_lic_raw ON lic_fk = lic_pk
	LEFT OUTER JOIN licterm_maplic ON licterm_maplic.lic_fk = lic_pk
	LEFT OUTER JOIN licterm ON licterm_pk = licterm_name.licterm_fk
	OR licterm_pk = licterm_maplic.licterm_fk
	WHERE parent = $1 ORDER BY uploadtree.pfile_fk;');
    $DB->Prepare("LicenseGetAllFilesByCanonicalName_Traverse",'SELECT uploadtree_pk,ufile_mode FROM uploadtree WHERE parent = $1;');
    $LicenseGetAllFilesByCanonicalName_Prepared = 1;
    }

  /* Find every license under this UploadtreePk... */
  $Results = $DB->Execute("LicenseGetAllFilesByCanonicalName_Lics",array($UploadtreePk));
  if (!empty($Results) && (count($Results) > 0))
    {
    $LastName="";
    foreach($Results as $R)
	{
	$LicName = LicenseNormalizeName($R['lic_name'],$R['licterm_name_confidence'],$R['licterm_name']);
        if (($LicName == $WantName) && ($LicName != $LastName))
	  {
	  $Lics[] = $R;
	  $LastName = $LicName;
	  }
	}
    }

  /* Recurse */
  $Results = $DB->Execute("LicenseGetAllFilesByCanonicalName_Traverse",array($UploadtreePk));
  for($i=0; !empty($Results[$i]['uploadtree_pk']); $i++)
    {
    if (Iscontainer($Results[$i]['ufile_mode']))
	{
	LicenseGetAllFilesByCanonicalName($Results[$i]['uploadtree_pk'],$Lics,$WantName);
	}
    }
  return;
} // LicenseGetAllFilesByCanonicalName()

/************************************************************
 LicenseGetAllFiles(): Returns all files under a tree that
 contain the same license.
 Returns NULL if no files.
 NOTE: This is recursive!
 NOTE: $WantLic can be a specific license ID (in which case, the
 percent match is returned), or a SQL string (in which case, no
 percent match is returned).
 ************************************************************/
$LicenseGetAllFiles_Prepared = array();
function LicenseGetAllFiles(&$UploadtreePk, &$Lics, &$WantLic, &$Max, &$Offset)
{
  global $Plugins;
  global $DB;
  if (empty($DB)) { return; }
  if (empty($UploadtreePk)) { return NULL; }

  global $LicenseGetAllFiles_Prepared;

  $PrepName = sha1(preg_replace("@[^a-zA-Z0-9]@","_",$WantLic));
  if (empty($LicenseGetAllFiles_Prepared[$PrepName]))
    {
    /* SQL to get all files with a specific license */
    $DB->Prepare("LicenseGetAllFiles_$PrepName","SELECT DISTINCT ufile_name,uploadtree_pk,uploadtree.ufile_mode,ufile.ufile_pk,uploadtree.pfile_fk,lic_fk,lic_id,tok_pfile,tok_license,tok_match,phrase_text
	FROM uploadtree
	INNER JOIN ufile ON ufile_fk = ufile_pk AND uploadtree.parent = \$1
	INNER JOIN agent_lic_meta ON agent_lic_meta.pfile_fk = ufile.pfile_fk
	INNER JOIN agent_lic_raw ON agent_lic_meta.lic_fk = agent_lic_raw.lic_pk
	AND ( $WantLic )
	ORDER BY ufile.ufile_pk
	;");
    $LicenseGetAllFiles_Prepared[$PrepName] = 1;
    }

  /* Find every item under this UploadtreePk... */
  $Results = $DB->Execute("LicenseGetAllFiles_$PrepName",array($UploadtreePk));

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
  if (empty($LicenseGetAllFiles_Prepared["a0"]))
    {
    /* SQL to get all containers for recursing */
    $Bit = 1<<29;
    $DB->Prepare("LicenseGetAllFiles__a0","SELECT uploadtree_pk
	FROM uploadtree
	INNER JOIN ufile ON ufile_fk = ufile_pk AND uploadtree.parent = \$1
	AND uploadtree.ufile_mode & $Bit != 0
	ORDER BY ufile.ufile_pk
	;");
    $LicenseGetAllFiles_Prepared["a0"] = 1;
    }

  $Results = $DB->Execute("LicenseGetAllFiles__a0",array($UploadtreePk));
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
