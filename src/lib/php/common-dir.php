<?php
/***********************************************************
 Copyright (C) 2008-2014 Hewlett-Packard Development Company, L.P.

 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License version 2.1 as published by the Free Software Foundation.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public License
 along with this library; if not, write to the Free Software Foundation, Inc.0
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 ***********************************************************/

/**
 * \file common-dir.php
 * \brief Common Direcotory Functions
 */

use Fossology\Lib\Db\DbManager;

function Isdir($mode) { return(($mode & 1<<18) + ($mode & 0040000) != 0); }
function Isartifact($mode) { return(($mode & 1<<28) != 0); }
function Iscontainer($mode) { return(($mode & 1<<29) != 0); }

/**
 * \brief Convert a file mode to string values.
 *
 * \param $Mode file mode (as octal integer)
 *
 * \return string of dir mode
 */
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

/**
 * \brief Given an artifact directory (uploadtree_pk),
 *  return the first non-artifact directory (uploadtree_pk).
 *  TBD: "username" will be added in the future and it may change
 *  how this function works.
 *  NOTE: This is recursive!
 *
 * \param $UploadtreePk uploadtree_pk
 * \param $uploadtree_tablename defaults to 'uploadtree' to not break bsam/ui
 *
 * \return the first non-artifact directory uploadtree_pk
 */
$DirGetNonArtifact_Prepared=0;
function DirGetNonArtifact($UploadtreePk, $uploadtree_tablename='uploadtree')
{
  $Children = array();

  /* Get contents of this directory */
  global $DirGetNonArtifact_Prepared;
  global $container;
  $dbManager = $container->get('db.manager');
  if (!$DirGetNonArtifact_Prepared)
  {
    $DirGetNonArtifact_Prepared=1;
    $sql = "SELECT * FROM $uploadtree_tablename LEFT JOIN pfile ON pfile_pk = pfile_fk WHERE parent = $1";
    $dbManager->prepare($stmt=__METHOD__.".$uploadtree_tablename",$sql);
    $result = $dbManager->execute($stmt,array($UploadtreePk));
    while ($child=$dbManager->fetchArray($result) )
    {
      $Children[] = $child;
    }
    $dbManager->freeResult($result);
  }
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
      $Recurse = DirGetNonArtifact($C['uploadtree_pk'], $uploadtree_tablename);
    }
  }
  if (!empty($Recurse))
  {
    return(DirGetNonArtifact($Recurse, $uploadtree_tablename));
  }
  return($UploadtreePk);
} // DirGetNonArtifact()


/**
 * \brief Compare function for usort() on directory items.
 *
 * \param $a $b to compare
 *
 * \return 0 if they are equal
 *         <0 less than
 *         >0 greater than
 */
function _DirCmp($a,$b)
{
  return(strcasecmp($a['ufile_name'],$b['ufile_name']));
} // _DirCmp()


/**
 * \brief Return the path (without artifacts) of an uploadtree_pk.
 *
 * \param $uploadtree_pk
 * \param $uploadtree_tablename
 *
 * \return an array containing the path (with no artifacts).  Each element 
 *         in the path is an array containing the uploadtree record for 
 *         $uploadtree_pk and its parents.
 *         The path begins with the uploadtree_pk record.
 */
function Dir2Path($uploadtree_pk, $uploadtree_tablename='uploadtree')
{
  global $PG_CONN;

  $uploadtreeArray = array();

  if ((empty($uploadtree_pk))) { return $uploadtreeArray; }

  while (!empty($uploadtree_pk))
  {
    $sql = "SELECT parent, upload_fk, ufile_mode, ufile_name, uploadtree_pk from $uploadtree_tablename where uploadtree_pk='$uploadtree_pk'";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    $Row = pg_fetch_assoc($result);
    pg_free_result($result);
    if (!Isartifact($Row['ufile_mode']))
      array_unshift($uploadtreeArray, $Row);
    
    $uploadtree_pk = $Row['parent'];
  }
  
  return($uploadtreeArray);
} // Dir2Path()

/**
 * \brief Get an html linked string of a file browse path.
 *
 * \param $Mod - Module name (e.g. "browse")
 * \param $UploadtreePk
 * \param $LinkLast - create link (a href) for last item and use LinkLast as the module name
 * \param $ShowBox - true to draw a box around the string
 * \param $ShowMicro - true to show micro menu
 * \param $Enumerate - if >= zero number the folder/file path (the stuff in the yellow bar)
 *   starting with the value $Enumerate
 * \param $PreText - optional additional text to preceed the folder path
 * \param $PostText - optional text to follow the folder path
 * \param $uploadtree_tablename
 *
 * \return string of browse paths
 */
function Dir2Browse ($Mod, $UploadtreePk, $LinkLast=NULL,
$ShowBox=1, $ShowMicro=NULL, $Enumerate=-1, $PreText='', $PostText='', $uploadtree_tablename="uploadtree")
{
  $V = "";
  if ($ShowBox)
  {
    $V .= "<div style='border: thin dotted gray; background-color:lightyellow'>\n";
  }

  if ($Enumerate >= 0)
  {
    $V .= "<table border=0 width='100%'><tr><td width='5%'>";
    $V .= "<font size='+2'>" . number_format($Enumerate,0,"",",") . ":</font>";
    $V .= "</td><td>";
  }

  $Opt = Traceback_parm_keep(array("folder","show"));
  $Uri = Traceback_uri() . "?mod=$Mod";

  /* Get array of upload recs for this path, in top down order.
   This does not contain artifacts.
   */
  $Path = Dir2Path($UploadtreePk, $uploadtree_tablename);
  $Last = &$Path[count($Path)-1];

  $V .= "<font class='text'>\n";

  /* Add in additional text */
  if (!empty($PreText)) { $V .= "$PreText<br>\n"; }

  /* Get the FOLDER list for the upload */
  $text = _("Folder");
  $V .= "<b>$text</b>: ";
  if (array_key_exists(0, $Path))
  {
    $List = FolderGetFromUpload($Path[0]['upload_fk']);
    $Uri2 = Traceback_uri() . "?mod=browse" . Traceback_parm_keep(array("show"));
    for($i=0; $i < count($List); $i++)
    {
      $Folder = $List[$i]['folder_pk'];
      $FolderName = htmlentities($List[$i]['folder_name']);
      $V .= "<b><a href='$Uri2&folder=$Folder'>$FolderName</a></b>/ ";
    }
  }

  /* Print the upload, itself (on the next line since it is not a folder) */
  if (count($Path) == -1)
  {
    $Upload = $Path[0]['upload_fk'];
    $UploadName = htmlentities($Path[0]['ufile_name']);
    $UploadtreePk =  $Path[0]['uploadtree_pk'];
    $V .= "<br><b><a href='$Uri2&folder=$Folder&upload=$Upload&item=$UploadtreePk'>$UploadName</a></b>";
  }
  else
  $V .= "<br>";

  /* Show the path within the upload */
  for($p=0; !empty($Path[$p]['uploadtree_pk']); $p++)
  {
    $P = &$Path[$p];
    if (empty($P['ufile_name'])) { continue; }
    $UploadtreePk = $P['uploadtree_pk'];
    if ($p > 0) {
      $V .= "/";
    }
    if (!empty($LinkLast) || ($P != $Last))
    {
      if ($P == $Last)
      {
        $Uri = Traceback_uri() . "?mod=$LinkLast";
      }
      $V .= "<a href='$Uri&upload=" . $P['upload_fk'] . $Opt . "&item=" . $UploadtreePk . "'>";
    }

    if (Isdir($P['ufile_mode']))
    {
      $V .= $P['ufile_name'];
    }
    else
    {
      $V .= "<b>" . $P['ufile_name'] . "</b>";
    }

    if (!empty($LinkLast) || ($P != $Last))
    {
      $V .= "</a>";
    }
  }
  $V .= "</font>\n";

  if (!empty($ShowMicro))
  {
    $MenuDepth = 0; /* unused: depth of micro menu */
    $V .= menu_to_1html(menu_find($ShowMicro,$MenuDepth),1);
  }

  if ($Enumerate >= 0)
  {
    if ($PostText) $V .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$PostText";
    $V .= "</td></tr></table>";
  }

  if ($ShowBox)
  {
    $V .= "</div>\n";
  }
  return($V);
} // Dir2Browse()

/**
 * \brief Get an html linkes string of a file browse path.
 *  This calls Dir2Browse().
 *
 * \param $Mod - Module name (e.g. "browse")
 * \param $UploadPk
 * \param $LinkLast - create link (a href) for last item and use LinkLast as the module name
 * \param $ShowBox - draw a box around the string (default true)
 * \param $ShowMicro - show micro menu (default false)
 * \param $uploadtree_tablename
 *
 * \return string of browse paths
 */
function Dir2BrowseUpload ($Mod, $UploadPk, $LinkLast=NULL, $ShowBox=1, $ShowMicro=NULL, $uploadtree_tablename='uploadtree')
{
  global $PG_CONN;
  /* Find the file associated with the upload */
  $sql = "SELECT uploadtree_pk FROM upload INNER JOIN $uploadtree_tablename ON upload_fk = '$UploadPk' AND parent is null;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  $UploadtreePk = $row['uploadtree_pk'];
  pg_free_result($result);
  return(Dir2Browse($Mod,$UploadtreePk,$LinkLast,$ShowBox,$ShowMicro, -1, '','', $uploadtree_tablename));
} // Dir2BrowseUpload()

/**
 * \brief Given an array of pfiles/uploadtree, sorted by
 *  pfile, list all of the breadcrumbs for each file.
 *  If the pfile is a duplicate, then indent it.
 *
 * DEPRECATED - convert your code to UploadtreeFileList()
 *
 * \param $Listing = array from a database selection.  The SQL query should
 *	use "ORDER BY pfile_fk" so that the listing can indent duplicate pfiles
 * \param $IfDirPlugin = string containing plugin name to use if this is a directory or any other container
 * \param $IfFilePlugin = string containing plugin name to use if this is a file
 * \param $Count = first number for indexing the entries (may be -1 for no count)
 * \param $ShowPhrase Obsolete from bsam
 *
 * \return string containing the listing.
 */
function Dir2FileList	(&$Listing, $IfDirPlugin, $IfFilePlugin, $Count=-1, $ShowPhrase=0)
{
  $LastPfilePk = -1;
  $V = "";
  while (($R = pg_fetch_assoc($Listing)) and !empty($R['uploadtree_pk']))
  {
    if (array_key_exists("licenses", $R))
      $Licenses = $R["licenses"];
    else
      $Licenses = '';

    $Phrase='';
    if ($ShowPhrase && !empty($R['phrase_text']))
    {
      $text = _("Phrase");
      $Phrase = "<b>$text:</b> " . htmlentities($R['phrase_text']);
    }

    if ((IsDir($R['ufile_mode'])) || (Iscontainer($R['ufile_mode'])))
    {
      $V .= "<P />\n";
      $V .= Dir2Browse("browse",$R['uploadtree_pk'],$IfDirPlugin,1,
      NULL,$Count,$Phrase, $Licenses) . "\n";
    }
    else if ($R['pfile_fk'] != $LastPfilePk)
    {
      $V .= "<P />\n";
      $V .= Dir2Browse("browse",$R['uploadtree_pk'],$IfFilePlugin,1,
      NULL,$Count,$Phrase, $Licenses) . "\n";
      $LastPfilePk = $R['pfile_fk'];
    }
    else
    {
      $V .= "<div style='margin-left:2em;'>";
      $V .= Dir2Browse("browse",$R['uploadtree_pk'],$IfFilePlugin,1,
      NULL,$Count,$Phrase, $Licenses) . "\n";
      $V .= "</div>";
    }
    $Count++;
  }
  return($V);
} // Dir2FileList()

/**
 * \brief Given an array of pfiles/uploadtree, sorted by
 *  pfile, list all of the breadcrumbs for each file.
 *  If the pfile is a duplicate, then indent it.
 *
 * \param $Listing = array from a database selection.  The SQL query should
 *	use "ORDER BY pfile_fk" so that the listing can indent duplicate pfiles
 * \param $IfDirPlugin = string containing plugin name to use if this is a directory or any other container
 * \param $IfFilePlugin = string containing plugin name to use if this is a file
 * \param $Count = first number for indexing the entries (may be -1 for no count)
 * \param $ShowPhrase Obsolete from bsam
 *
 * \return string containing the listing.
 */
function UploadtreeFileList($Listing, $IfDirPlugin, $IfFilePlugin, $Count=-1, $ShowPhrase=0)
{
  $LastPfilePk = -1;
  $V = "";
  foreach($Listing as $R)
  {
    if (array_key_exists("licenses", $R))
      $Licenses = $R["licenses"];
    else
      $Licenses = '';

    $Phrase='';
    if ($ShowPhrase && !empty($R['phrase_text']))
    {
      $text = _("Phrase");
      $Phrase = "<b>$text:</b> " . htmlentities($R['phrase_text']);
    }

    $uploadtree_tablename = GetUploadtreeTableName($R['upload_fk']);

    if ((IsDir($R['ufile_mode'])) || (Iscontainer($R['ufile_mode'])))
    {
      $V .= "<P />\n";
      $V .= Dir2Browse("browse",$R['uploadtree_pk'],$IfDirPlugin,1,NULL,$Count,$Phrase,$Licenses,$uploadtree_tablename) . "\n";
    }
    else if ($R['pfile_fk'] != $LastPfilePk)
    {
      $V .= "<P />\n";
      $V .= Dir2Browse("browse",$R['uploadtree_pk'],$IfFilePlugin,1,NULL,$Count,$Phrase,$Licenses,$uploadtree_tablename) . "\n";
      $LastPfilePk = $R['pfile_fk'];
    }
    else
    {
      $V .= "<div style='margin-left:2em;'>";
      $V .= Dir2Browse("browse",$R['uploadtree_pk'],$IfFilePlugin,1,NULL,$Count,$Phrase,$Licenses,$uploadtree_tablename) . "\n";
      $V .= "</div>";
    }
    $Count++;
  }
  return($V);
} // UploadtreeFileList()


/**
 * \brief Find the non artifact children of an uploadtree pk.
 * If any children are artifacts, resolve them until you get
 * to a non-artifact.
 *
 * \param $uploadtree_pk
 * \param $uploadtree_tablename
 *
 * \return list of child uploadtree recs + pfile_size + pfile_mimetypefk on success.
 *         list may be empty if there are no children.
 * Child list is sorted by ufile_name.
 */
function GetNonArtifactChildren($uploadtree_pk, $uploadtree_tablename='uploadtree')
{
  global $container;
  /** @var DbManager */
  $dbManager = $container->get('db.manager');

  /* Find all the children */
  $sql = "select {$uploadtree_tablename}.*, pfile_size, pfile_mimetypefk from $uploadtree_tablename
          left outer join pfile on (pfile_pk=pfile_fk)
          where parent=$1 ORDER BY lft";
  $dbManager->prepare($stmt=__METHOD__."$uploadtree_tablename",$sql);
  $result = $dbManager->execute($stmt,array($uploadtree_pk));
  $children = $dbManager->fetchAll($result);
  $dbManager->freeResult($result);
  if (count($children) == 0)
  {
    return $children;
  }

  /* Loop through each child and replace any artifacts with their
   non artifact child.  Or skip them if they are not containers.
   */
  $foundChildren = array();
  foreach($children as $key => $child)
  {
    if (Isartifact($child['ufile_mode']))
    {
      if (Iscontainer($child['ufile_mode']))
      {
        unset($children[$key]);
        $NonAChildren = GetNonArtifactChildren($child['uploadtree_pk'], $uploadtree_tablename);
        if ($NonAChildren)
          $foundChildren = array_merge($foundChildren, $NonAChildren);
      }
      else
        unset($children[$key]);
    }
    else
    $foundChildren[$key] = $child;
  }
  // uasort($foundChildren, '_DirCmp');
  return $foundChildren;
}
