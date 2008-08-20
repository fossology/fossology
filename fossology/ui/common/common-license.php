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
  $Name = $RawList[0]['licterm_name'];
  if (empty($Name)) { $Name = $RawList[0]['lic_name']; }
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
function LicenseGet(&$PfilePk, &$Lics, $GetField=0)
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

  /* Prepare map */
  $Results = $DB->Action("SELECT * FROM licterm_maplic
	INNER JOIN licterm ON licterm_fk = licterm_pk
	;");
  $MapLic = array();
  for($i=0; !empty($Results[$i]['licterm_maplic_pk']); $i++)
    {
    $MapLic[$Results[$i]['lic_fk']] = $Results[$i]['licterm_name'];
    }

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
      $LicName="";
      if ($Name['licterm_name_confidence'] == 3) { $LicName = $Name['licterm_name']; }
      if (empty($LicName)) { $LicName = LicenseNormalizeName($Name['lic_name'],$Name['licterm_name_confidence'],$MapLic[$Name['lic_id']]); }
      if (empty($LicName)) { $LicName = LicenseNormalizeName($Name['lic_name'],$Name['licterm_name_confidence'],$Name['licterm_name']); }

      if (!empty($LicName))
        {
	if (!empty($GetField)) { $Lics[]=$Name; }
	else
	  {
	  if (empty($Lics[$LicName])) { $Lics[$LicName]=1; }
	  else { $Lics[$LicName]++; }
	  $Lics[' Total ']++;
	  }
	}
      }
    }
  return;
} // LicenseGet()

/************************************************************
 LicenseCount(): Return license count for a uploadtree_pk.
 If uploadtree_pk is a file, the # of licenses in that file 
    is returned.
 If uploadtree_pk is a container, the # of licenses contained
    in that container (and children) is returned.
 ************************************************************/
function LicenseCount($UploadtreePk)
{
  global $Plugins;
  global $DB;

  if (empty($DB)) { return 0; }
  if (empty($UploadtreePk)) { return 0; }

  $Results = $DB->Action("select count(*)
    FROM uploadtree as UT1 
    INNER JOIN uploadtree as UT2 on UT1.lft BETWEEN UT2.lft and UT2.rgt
          and UT2.uploadtree_pk=$UploadtreePk
          and UT1.upload_fk=UT2.upload_fk
    INNER JOIN agent_lic_meta on UT1.pfile_fk=agent_lic_meta.pfile_fk");
  return($Results[0]["count"] );
} // LicenseCount()

/************************************************************
 LicenseGetAll(): Return licenses for a uploadtree_pk.
 Array returned looks like Array[license_name] = count.
 An array is always returned unless the db is not open 
 or no uploadtreepk is passed in.
 $Max: $Max # of returned records
 $Offset: offset into $Results of first returned rec
 Returns NULL if not processed.
 ************************************************************/
function LicenseGetAll(&$UploadtreePk, &$Lics, $GetField=0, $WantLic=NULL, $Max=-1, $Offset=0)
{
  global $Plugins;
  global $DB;

  if (empty($DB)) { return; }
  if (empty($UploadtreePk)) { return NULL; }

  /* Number of licenses */
  if (empty($Lics[' Total ']) && empty($GetField)) { $Lics[' Total ']=0; }

  if ($Offset > 0) 
    $OffsetPhrase = " OFFSET $Offset";
  else
    $OffsetPhrase = "";

  if ($Max > 0)
    $LimitPhrase = " LIMIT $Max";
  else
    $LimitPhrase = "";

  /*  Get every license for every file in this subtree */
  $Results = $DB->Action("select licterm_name, count(licterm_name) as liccount
    FROM uploadtree as UT1, uploadtree as UT2, licterm_name, licterm
    WHERE UT1.lft BETWEEN UT2.lft and UT2.rgt
          and UT1.upload_fk=UT2.upload_fk
          and UT2.uploadtree_pk=$UploadtreePk
          and licterm_name.pfile_fk=UT1.pfile_fk
          and licterm_pk=licterm_name.licterm_fk
    GROUP BY licterm_name
    ORDER BY liccount DESC  $LimitPhrase $OffsetPhrase");

   $Lics[' Total '] = count($Results);

   /* Rewrite results for consumption */
   foreach ($Results as $Rec) $Lics[$Rec['licterm_name']] = $Rec['liccount'];
   
  return; 
} // LicenseGetAll()


/* Return license records that match the license in $WantLic, including and under UploadtreePK  */
function LicenseGetAllFiles2($UploadtreePk, &$Lics, $WantLic, $Max=-1, $Offset=0)
{
  global $Plugins;
  global $DB;

  if (empty($DB)) { return; }
  if (empty($UploadtreePk)) { return NULL; }

  if ($Offset > 0) 
    $OffsetPhrase = " OFFSET $Offset";
  else
    $OffsetPhrase = "";

  if ($Max > 0)
    $LimitPhrase = " LIMIT $Max";
  else
    $LimitPhrase = "";

  /*  Get the recs with $WantLic license in this subtree */
  $sql = "select UT1.*, licterm_name, agent_lic_meta.*, lic_tokens
    FROM uploadtree as UT1, uploadtree as UT2, licterm_name, licterm, agent_lic_meta, agent_lic_raw
    WHERE UT1.lft BETWEEN UT2.lft and UT2.rgt
          and UT1.upload_fk=UT2.upload_fk
          and UT2.uploadtree_pk=$UploadtreePk
          and licterm_name.pfile_fk=UT1.pfile_fk
          and agent_lic_meta_pk=licterm_name.agent_lic_meta_fk
          and licterm_pk=licterm_name.licterm_fk
          and licterm_name = '$WantLic'
          and agent_lic_raw.lic_pk=agent_lic_meta.lic_fk
    ORDER BY UT1.pfile_fk DESC  $LimitPhrase $OffsetPhrase";
  $Results = $DB->Action($sql);

   $Lics[' Total '] = count($Results);

   /* Rewrite results for consumption */
   foreach ($Results as $Rec) $Lics[] = $Rec;

  return; 
} // LicenseGetAllFiles2()


/************************************************************
 LicenseGetAllFiles(): Return licenses for a uploadtree_pk.
 This is only for search-file-by-licgroup.php
 License Groups only use the the license stored in agent_lic_meta
 Can return empty array if there is no license.
 $Max: $Max # of returned records
 $Offset: offset into $Results of first returned rec
 Returns NULL if not processed.
 ************************************************************/
function LicenseGetAllFiles(&$UploadtreePk, &$Lics, &$WantLic, $Max, $Offset)
{
  global $Plugins;
  global $DB;

  if (empty($DB)) { return; }
  if (empty($UploadtreePk)) { return NULL; }

  /*  Get every license for every file in this subtree */
    /* SQL to get all files with a specific license */
  $Results = $DB->Action("select UT1.*,lic_fk,lic_id,tok_pfile,tok_license,tok_match,phrase_text
    FROM uploadtree as UT1 
    INNER JOIN uploadtree as UT2 on UT1.lft BETWEEN UT2.lft and UT2.rgt
          and UT1.upload_fk=UT2.upload_fk
          and UT2.uploadtree_pk=$UploadtreePk
	INNER JOIN agent_lic_meta ON agent_lic_meta.pfile_fk = UT1.pfile_fk
    INNER JOIN agent_lic_raw on agent_lic_meta.lic_fk=agent_lic_raw.lic_pk
	AND ( $WantLic )
    ORDER BY UT1.ufile_name");
  $Count = count($Results);

    if ($Max == -1) $Max = $Count;

    /* Got canonical name */
    $Found = 0;
    for ($i=0; ($Found < $Max+$Offset) && $Results[$i]; $i++)
    {
      //if (empty($Results[$i]['lic_fk'])) { continue; }
      if ($Found >= $Offset)
      {
         $Lics[]=$Results[$i]; 
      }
      $Found++;
    }

  return; 
} // LicenseGetAllFiles()

?>
