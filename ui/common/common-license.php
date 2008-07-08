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
      $Name = preg_replace("/ \(.*/","",$Name);
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
    $DB->Prepare("LicenseGetName_Raw1",'SELECT licterm.licterm_name,lic_name,phrase_text,lic_id
	FROM agent_lic_raw
	INNER JOIN agent_lic_meta ON agent_lic_meta_pk = $1
	AND lic_fk = lic_pk
	INNER JOIN licterm_maplic ON licterm_maplic.lic_fk = lic_id
        INNER JOIN licterm ON licterm_fk = licterm_pk
	;');

    $DB->Prepare("LicenseGetName_Raw2",'SELECT lic_name,phrase_text,lic_id
	FROM agent_lic_raw
	INNER JOIN agent_lic_meta ON agent_lic_meta_pk = $1
	AND lic_fk = lic_pk
	;');

    $DB->Prepare("LicenseGetName_CanonicalName",'SELECT licterm_name_confidence,licterm_name
	FROM licterm
	INNER JOIN licterm_name ON agent_lic_meta_fk = $1
	AND licterm_fk = licterm_pk
	UNION
	SELECT licterm_name_confidence,' . "''" . '
	FROM licterm_name
	WHERE agent_lic_meta_fk = $1 AND licterm_fk IS NULL
	;');
    $LicenceGetName_Prepared=1;
    }

  $FullName='';
  $CanonicalList =  $DB->Execute("LicenseGetName_CanonicalName",array($MetaId));
  $RawList =  $DB->Execute("LicenseGetName_Raw1",array($MetaId));
  if (empty($RawList)) { $RawList =  $DB->Execute("LicenseGetName_Raw2",array($MetaId)); }

  $LastConfidence = $CanonicalList[0]['licterm_name_confidence'];
  $Phrase = $RawList[0]['phrase_text'];
  $Name = $RawList[0]['lic_name'];
  foreach($CanonicalList as $C)
    {
    if (empty($C)) { continue; }
    /* Get the components */
    $Confidence = $C['licterm_name_confidence'];
    $LicTerm = $C['licterm_name'];

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

  if (empty($FullName))
    {
    $Name = LicenseNormalizeName($RawList[0]['lic_name'],0,"");
    if (!empty($Phrase))
      {
      $Name = "Phrase";
      if ($IncludePhrase) { $Name .= ": $Phrase"; }
      }
    $FullName .= $Name;
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
    $DB->Prepare("LicenseGet_Raw1",'SELECT licterm.licterm_name,lic_id,phrase_text,agent_lic_meta_pk
	FROM agent_lic_meta
	INNER JOIN agent_lic_raw ON lic_fk = lic_pk AND pfile_fk = $1
	INNER JOIN licterm_maplic ON licterm_maplic.lic_fk = lic_id
        INNER JOIN licterm ON licterm_fk = licterm_pk
	;');
    $DB->Prepare("LicenseGet_Raw2",'SELECT lic_name as licterm_name,lic_id,phrase_text,agent_lic_meta_pk
	FROM agent_lic_meta
	INNER JOIN agent_lic_raw ON lic_fk = lic_pk AND pfile_fk = $1
	;');
    $DB->Prepare("LicenseGet_Canonical",'SELECT licterm.licterm_name,licterm_name_confidence,lic_name,phrase_text,lic_id,agent_lic_meta_pk
	FROM agent_lic_meta
	INNER JOIN agent_lic_raw ON lic_fk = lic_pk AND pfile_fk = $1
	INNER JOIN licterm_name ON agent_lic_meta_fk = agent_lic_meta_pk
	INNER JOIN licterm ON licterm_fk = licterm_pk
	UNION
	SELECT '."''".',licterm_name_confidence,lic_name,phrase_text,lic_id,agent_lic_meta_pk
	FROM agent_lic_meta
	INNER JOIN agent_lic_raw ON lic_fk = lic_pk AND pfile_fk = $1
	INNER JOIN licterm_name ON agent_lic_meta_fk = agent_lic_meta_pk
	AND licterm_fk IS NULL
	;');
    $LicenseGet_Prepared=1;
    }
  if (empty($Lics[' Total '])) { $Lics[' Total ']=0; }

  $CanonicalList =  $DB->Execute("LicenseGet_Canonical",array($PfilePk));
  $RawList = $DB->Execute("LicenseGet_Raw1",array($PfilePk));
  if (empty($RawList)) { $RawList = $DB->Execute("LicenseGet_Raw2",array($PfilePk)); }
  $Results=array();
  $PfileList=array(); /* used to omit duplicates */
  foreach($CanonicalList as $R)
    {
    $PfileList[$R['agent_lic_meta_pk']] = 1;
    $Results[] = $R;
    }
  foreach($RawList as $R)
    {
    $R['licterm_name'] = LicenseNormalizeName($R['licterm_name'],0,"");
    if (empty($PfileList[$R['agent_lic_meta_pk']]))
      {
      $PfileList[$R['agent_lic_meta_pk']] = 1;
      $Results[] = $R;
      }
    }

  if (!empty($Results) && (count($Results) > 0))
    {
    /* Got canonical name */
    foreach($Results as $Name)
      {
      if ($GetPks) { $LicName = $Name['lic_id']; }
      else { $LicName = LicenseNormalizeName($Name['lic_name'],$Name['licterm_name_confidence'],$Name['licterm_name']); }
      if (!empty($LicName))
	{
	if (empty($Lics[$LicName])) { $Lics[$LicName]=1; }
	else { $Lics[$LicName]++; }
	$Lics[' Total ']++;
	}
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
function LicenseGetAll(&$UploadtreePk, &$Lics, $GetPks=0, $Depth=0)
{
  global $Plugins;
  global $DB;
  if (empty($DB)) { return; }
  if (empty($UploadtreePk)) { return NULL; }

  if (empty($Lics[' Total '])) { $Lics[' Total ']=0; }
  global $LicenseGetAll_Prepared;
  if (!$LicenseGetAll_Prepared)
    {
    $DB->Prepare("LicenseGetAll_Traverse",'SELECT uploadtree_pk,ufile_mode FROM uploadtree WHERE parent = $1;');

    $DB->Prepare("LicenseGetAll_Raw1",'SELECT licterm.licterm_name,lic_name,lic_id,phrase_text,agent_lic_meta_pk
	FROM uploadtree
	INNER JOIN agent_lic_meta ON parent = $1 AND agent_lic_meta.pfile_fk = uploadtree.pfile_fk
	INNER JOIN agent_lic_raw ON agent_lic_meta.lic_fk = lic_pk
	INNER JOIN licterm_maplic ON licterm_maplic.lic_fk = lic_id
	INNER JOIN licterm ON licterm_fk = licterm_pk
	;');

    $DB->Prepare("LicenseGetAll_Raw2",'SELECT lic_name,lic_id,phrase_text,agent_lic_meta_pk
	FROM uploadtree
	INNER JOIN agent_lic_meta ON parent = $1 AND agent_lic_meta.pfile_fk = uploadtree.pfile_fk
	INNER JOIN agent_lic_raw ON agent_lic_meta.lic_fk = lic_pk
	;');

    $DB->Prepare("LicenseGetAll_Canonical",'SELECT licterm.licterm_name,licterm_name_confidence,lic_name,phrase_text,lic_id,agent_lic_meta_pk
	FROM uploadtree
	INNER JOIN agent_lic_meta ON parent = $1 AND agent_lic_meta.pfile_fk = uploadtree.pfile_fk
	INNER JOIN agent_lic_raw ON lic_fk = lic_pk
	INNER JOIN licterm_name ON agent_lic_meta_fk = agent_lic_meta_pk
	INNER JOIN licterm ON licterm_fk = licterm_pk
	UNION
	SELECT '."''".',licterm_name_confidence,lic_name,phrase_text,lic_id,agent_lic_meta_pk
	FROM uploadtree
	INNER JOIN agent_lic_meta ON parent = $1 AND agent_lic_meta.pfile_fk = uploadtree.pfile_fk
	INNER JOIN agent_lic_raw ON lic_fk = lic_pk
	INNER JOIN licterm_name ON agent_lic_meta_fk = agent_lic_meta_pk
	AND licterm_fk IS NULL
	;');
    $LicenseGetAll_Prepared = 1;
    }

  /* Get every license */
  $CanonicalList =  $DB->Execute("LicenseGetAll_Canonical",array($UploadtreePk));
  $RawList = $DB->Execute("LicenseGetAll_Raw1",array($UploadtreePk));
  if (empty($RawList)) { $RawList = $DB->Execute("LicenseGetAll_Raw2",array($UploadtreePk)); }
  $Results=array();
  $PfileList=array(); /* used to omit duplicates */
  foreach($CanonicalList as $R)
    {
    $PfileList[$R['agent_lic_meta_pk']] = 1;
    $Results[] = $R;
    }
  foreach($RawList as $R)
    {
    if (empty($PfileList[$R['agent_lic_meta_pk']]))
      {
      $PfileList[$R['agent_lic_meta_pk']] = 1;
      $Results[] = $R;
      }
    }

  if (!empty($Results) && (count($Results) > 0))
    {
    /* Got canonical name */
    foreach($Results as $Name)
      {
      if ($GetPks) { $LicName = $Name['lic_id']; }
      else { $LicName = LicenseNormalizeName($Name['lic_name'],$Name['licterm_name_confidence'],$Name['licterm_name']); }
      if (!empty($LicName))
	{
	if (empty($Lics[$LicName])) { $Lics[$LicName]=1; }
	else { $Lics[$LicName]++; }
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
	LicenseGetAll($Results[$i]['uploadtree_pk'],$Lics,$GetPks,$Depth+1);
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
    $DB->Prepare("LicenseGetAllFilesByCanonicalName_Raw1",'SELECT licterm.licterm_name,uploadtree.pfile_fk AS pfile,ufile_name,uploadtree_pk,uploadtree.ufile_mode,ufile.ufile_pk,agent_lic_raw.lic_name,lic_pk,phrase_text,agent_lic_meta_pk
	FROM uploadtree
	INNER JOIN agent_lic_meta ON parent = $1 AND agent_lic_meta.pfile_fk = uploadtree.pfile_fk
	INNER JOIN ufile ON ufile_fk = ufile_pk
	INNER JOIN agent_lic_raw ON lic_fk = lic_pk
	INNER JOIN licterm_maplic ON licterm_maplic.lic_fk = lic_id
        INNER JOIN licterm ON licterm_fk = licterm_pk
        ;');

    $DB->Prepare("LicenseGetAllFilesByCanonicalName_Raw2",'SELECT uploadtree.pfile_fk AS pfile,ufile_name,uploadtree_pk,uploadtree.ufile_mode,ufile.ufile_pk,agent_lic_raw.lic_name,lic_pk,phrase_text,agent_lic_meta_pk
	FROM uploadtree
	INNER JOIN agent_lic_meta ON parent = $1 AND agent_lic_meta.pfile_fk = uploadtree.pfile_fk
	INNER JOIN ufile ON ufile_fk = ufile_pk
	INNER JOIN agent_lic_raw ON lic_fk = lic_pk
        ;');

    $DB->Prepare("LicenseGetAllFilesByCanonicalName_Canonical",'SELECT uploadtree.pfile_fk AS pfile,ufile_name,uploadtree_pk,uploadtree.ufile_mode,ufile.ufile_pk,agent_lic_raw.lic_name,licterm_name.licterm_name_confidence,licterm.licterm_name,lic_pk,phrase_text,agent_lic_meta_pk
	FROM uploadtree
	INNER JOIN agent_lic_meta ON parent = $1 AND agent_lic_meta.pfile_fk = uploadtree.pfile_fk
	INNER JOIN ufile ON ufile_fk = ufile_pk
	INNER JOIN licterm_name ON agent_lic_meta_fk = agent_lic_meta_pk
	INNER JOIN licterm ON licterm_pk = licterm_fk
	INNER JOIN agent_lic_raw ON lic_fk = lic_pk
	UNION
	SELECT uploadtree.pfile_fk AS pfile,ufile_name,uploadtree_pk,uploadtree.ufile_mode,ufile.ufile_pk,agent_lic_raw.lic_name,licterm_name_confidence,'."''".',lic_pk,phrase_text,agent_lic_meta_pk
	FROM uploadtree
	INNER JOIN agent_lic_meta ON parent = $1 AND agent_lic_meta.pfile_fk = uploadtree.pfile_fk
	INNER JOIN ufile ON ufile_fk = ufile_pk
	INNER JOIN licterm_name ON agent_lic_meta_fk = agent_lic_meta_pk
	AND licterm_fk IS NULL
	INNER JOIN agent_lic_raw ON lic_fk = lic_pk
	;');

    $DB->Prepare("LicenseGetAllFilesByCanonicalName_Traverse",'SELECT uploadtree_pk,ufile_mode FROM uploadtree WHERE parent = $1;');
    $LicenseGetAllFilesByCanonicalName_Prepared = 1;
    }

  /* Find every license under this UploadtreePk... */
  $CanonicalList = $DB->Execute("LicenseGetAllFilesByCanonicalName_Canonical",array($UploadtreePk));
  $RawList = $DB->Execute("LicenseGetAllFilesByCanonicalName_Raw1",array($UploadtreePk));
  if (empty($RawList)) { $RawList = $DB->Execute("LicenseGetAllFilesByCanonicalName_Raw2",array($UploadtreePk)); }
  /* Combine Raw and Canonical */
  $PfileList=array(); /* list of Pfiles that I have seen */
  $Results = array();
  foreach($CanonicalList as $R)
    {
    if (empty($PfileList[$R['pfile']."+".$R['agent_lic_meta_pk']]))
      {
      $PfileList[$R['pfile']."+".$R['agent_lic_meta_pk']] = 1;
      $Results[] = $R;
      }
    }
  foreach($RawList as $R)
    {
    if (empty($PfileList[$R['pfile']."+".$R['agent_lic_meta_pk']]))
      {
      $PfileList[$R['pfile']."+".$R['agent_lic_meta_pk']] = 1;
      $Results[] = $R;
      }
    }

  if (!empty($Results) && (count($Results) > 0))
    {
    foreach($Results as $R)
	{
	$LicName = LicenseNormalizeName($R['lic_name'],$R['licterm_name_confidence'],$R['licterm_name']);
        if ($LicName == $WantName)
	  {
	  $Lics[] = $R;
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
