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

function Isdir($mode) { return(($mode & 1<<18) + ($mode & 0040000) != 0); }
function Isartifact($mode) { return(($mode & 1<<28) != 0); }

/************************************************************
 DirMode2String(): Convert a mode to string values.
 ************************************************************/
function DirMode2String($Mode)
{
  $V="";
  if (Isartifact($Mode)) { $V .= "a"; } else { $V .= "-"; }
  if (($Mode & 0120000) == 0120000) { $V .= "l"; } else { $V .= "-"; }
  if (Isdir($Mode)) { $V .= "d"; } else { $V .= "-"; }

  if ($Mode & 0000400) { $V .= "r"; } else { $V .= "-"; }
  if ($Mode & 0000200) { $V .= "w"; } else { $V .= "-"; }
  if ($Mode & 0000100)
    {
    if ($Mode & 0004000) { $V .= "s"; } /* setuid */
    else { $V .= "x"; }
    }
  else
    {
    if ($Mode & 0004000) { $V .= "S"; } /* setuid */
    else { $V .= "-"; }
    }

  if ($Mode & 0000040) { $V .= "r"; } else { $V .= "-"; }
  if ($Mode & 0000020) { $V .= "w"; } else { $V .= "-"; }
  if ($Mode & 0000010)
    {
    if ($Mode & 0002000) { $V .= "s"; } /* setgid */
    else { $V .= "x"; }
    }
  else
    {
    if ($Mode & 0002000) { $V .= "S"; } /* setgid */
    else { $V .= "-"; }
    }

  if ($Mode & 0000004) { $V .= "r"; } else { $V .= "-"; }
  if ($Mode & 0000002) { $V .= "w"; } else { $V .= "-"; }
  if ($Mode & 0000001)
    {
    if ($Mode & 0001000) { $V .= "t"; } /* sticky bit */
    else { $V .= "x"; }
    }
  else
    {
    if ($Mode & 0001000) { $V .= "T"; } /* setgid */
    else { $V .= "-"; }
    }

  return($V);
} // DirMode2String()

/************************************************************
 DirGetNonArtifact(): Given an artifact directory (uploadtree_pk),
 return the first non-artifact directory (uploadtree_pk).
 TBD: "username" will be added in the future and it may change
 how this function works.
 NOTE: This is recursive!
 ************************************************************/
function DirGetNonArtifact($UploadtreePk)
{
  global $Plugins;
  $DB = &$Plugins[plugin_find_id("db")];
  if (empty($DB)) { return; }

  /* Get contents of this directory */
  $Sql = "SELECT * FROM uploadtree LEFT JOIN ufile ON ufile.ufile_pk = uploadtree.ufile_fk LEFT JOIN pfile ON pfile.pfile_pk = ufile.pfile_fk WHERE parent = $UploadtreePk;";
  $Children = $DB->Action($Sql);
  $Recurse=NULL;
  foreach($Children as $C)
    {
    if (empty($C['ufile_mode'])) { continue; }
    if (!Isartifact($C['ufile_mode']))
	{
	return($UploadtreePk);
	}
    if (($C['ufile_name'] == 'artifact.dir') ||
        ($C['ufile_name'] == 'artifact.unpacked'))
	{
	$Recurse = DirGetNonArtifact($C['uploadtree_pk']);
	}
    }
  if (!empty($Recurse))
    {
    return(DirGetNonArtifact($Recurse));
    }
  return($UploadtreePk);
} // DirGetNonArtifact()

/************************************************************
 _DirCmp(): Compare function for usort() on directory items.
 ************************************************************/
function _DirCmp($a,$b)
{
  return(strcmp($a['ufile_name'],$b['ufile_name']));
}

/************************************************************
 DirGetList(): Given a directory (uploadtree_pk),
 return the directory contents.
 TBD: "username" will be added in the future and it may change
 how this function works.
 Returns array containing:
   uploadtree_pk,ufile_pk,pfile_pk,ufile_name,ufile_mode
 ************************************************************/
function DirGetList($Upload,$UploadtreePk)
{
  global $Plugins;
  $DB = &$Plugins[plugin_find_id("db")];
  if (empty($DB)) { return; }

  /* Get the basic directory contents */
  $Sql = "SELECT * FROM uploadtree LEFT JOIN ufile ON ufile.ufile_pk = uploadtree.ufile_fk LEFT JOIN pfile ON pfile.pfile_pk = ufile.pfile_fk WHERE upload_fk = $Upload";
  if (empty($UploadtreePk)) { $Sql .= " AND uploadtree.parent IS NULL"; }
  else { $Sql .= " AND uploadtree.parent = $UploadtreePk"; }
  $Sql .= " ORDER BY ufile.ufile_name ASC;";
  $Results = $DB->Action($Sql);
  usort($Results,_DirCmp);

  /* Replace all artifact directories */
  foreach($Results as $Key => $Val)
    {
    /* if artifact and directory */
    $R = &$Results[$Key];
    if (Isartifact($R['ufile_mode']) && Isdir($R['ufile_mode']))
	{
	$R['uploadtree_pk'] = DirGetNonArtifact($R['uploadtree_pk']);
	}
    }
  return($Results);
} // DirGetList()

?>
