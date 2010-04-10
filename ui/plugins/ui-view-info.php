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

class ui_view_info extends FO_Plugin
  {
  var $Name       = "view_info";
  var $Title      = "View File Information";
  var $Version    = "1.0";
  var $Dependency = array("db","browse");
  var $DBaccess   = PLUGIN_DB_READ;
  var $LoginFlag  = 0;

  /***********************************************************
   RegisterMenus(): Customize submenus.
   ***********************************************************/
  function RegisterMenus()
    {
    menu_insert("Browse-Pfile::Info",5,$this->Name,"View file information");	
    // For the Browse menu, permit switching between detail and summary.
    $Parm = Traceback_parm_keep(array("upload","item","format"));
    $URI = $this->Name . $Parm;
    if (GetParm("mod",PARM_STRING) == $this->Name)
	{
	menu_insert("View::Info",1);
	menu_insert("View-Meta::Info",1);
	}
    else
	{
	menu_insert("View::Info",1,$URI,"View information about this file");
	menu_insert("View-Meta::Info",1,$URI,"View information about this file");
	}
    } // RegisterMenus()

  /***********************************************************
   ShowView(): Display the info data associated with the file.
   ***********************************************************/
  function ShowView($ShowMenu=0,$ShowHeader=0)
  {
    global $DB;
    $V = "";
    $Folder = GetParm("folder",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);
    if (empty($Upload) || empty($Item)) { return; }

    $Page = GetParm("page",PARM_INTEGER);
    if (empty($Page)) { $Page=0; }
    $Max = 50;
    $Offset = $Page * $Max;

    /**********************************
     Display micro header
     **********************************/
    if ($ShowHeader)
      {
      $V .= Dir2Browse("browse",$Item,NULL,1,"View-Meta");
      } // if ShowHeader

    /**********************************
     List File Info
     **********************************/
    if ($Page == 0)
      {
      $V .= "<H2>Repository Locator</H2>\n";
      $SQL = "SELECT * FROM uploadtree
	INNER JOIN pfile ON uploadtree_pk = $Item
	AND pfile_fk = pfile_pk
	LIMIT 1;";
      $Results = $DB->Action($SQL);
      $R = &$Results[0];
      $V .= "<table border=1>\n";
      $V .= "<tr><th>Attribute</th><th>Value</th></tr>\n";
      $Bytes = $R['pfile_size'];
      $BytesH = Bytes2Human($Bytes);
      $Bytes = number_format($Bytes, 0, "", ",");
      if ($BytesH == $Bytes) { $BytesH = ""; }
      else { $BytesH = '(' . $BytesH . ')'; }
      $V .= "<tr><td align='center'>File Size</td><td align='right'>$Bytes $BytesH</td></tr>\n";
      $V .= "<tr><td align='center'>SHA1 Checksum</td><td align='right'>" . $R['pfile_sha1'] . "</td></tr>\n";
      $V .= "<tr><td align='center'>MD5 Checksum</td><td align='right'>" . $R['pfile_md5'] . "</td></tr>\n";
      $V .= "<tr><td align='center'>Repository ID</td><td align='right'>" . $R['pfile_sha1'] . "." . $R['pfile_md5'] . "." . $R['pfile_size'] . "</td></tr>\n";
      $V .= "<tr><td align='center'>Pfile ID</td><td align='right'>" . $R['pfile_fk'] . "</td></tr>\n";
      $V .= "</table>\n";
      }
    return($V);
  } // ShowView()

  /***********************************************************
   Show Sightings, List the directory locations where this pfile is found
   ***********************************************************/
  function ShowSightings()
  {
    global $DB;
    $V = "";
    $Folder = GetParm("folder",PARM_INTEGER);
    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);
    if (empty($Upload) || empty($Item)) { return; }

    $Page = GetParm("page",PARM_INTEGER);
    if (empty($Page)) { $Page=0; }
    $Max = 50;
    $Offset = $Page * $Max;

    /**********************************
     List the directory locations where this pfile is found
     **********************************/
    $V .= "<H2>Sightings</H2>\n";
    $SQL = "SELECT * FROM pfile,uploadtree
        WHERE pfile_pk=pfile_fk
        AND pfile_pk IN
        (SELECT pfile_fk FROM uploadtree WHERE uploadtree_pk = $Item)
        LIMIT $Max OFFSET $Offset";
    $Results = $DB->Action($SQL);
    $Count = count($Results);
    if (($Page > 0) || ($Count >= $Max))
      {
      $VM = "<P />\n" . MenuEndlessPage($Page, ($Count >= $Max)) . "<P />\n";
      }
    else { $VM = ""; }
    if ($Count > 0)
        {
        $V .= "This exact file appears in the following locations:\n";
        $V .= $VM;
        $Offset++;
        $V .= Dir2FileList($Results,"browse","view",$Offset);
        $V .= $VM;
        }
    else if ($Page > 0)
        {
        $V .= "End of listing.\n";
        }
    else
        {
        $V .= "This file does not appear in any other known location.\n";
        }
    return($V);
  }//ShowSightings()
	
  /***********************************************************
   ShowMetaView(): Display the meta data associated with the file.
   ***********************************************************/
  function ShowMetaView()
  {
    global $DB;
    $V = "";
    $Upload = GetParm("upload",PARM_INTEGER);
    $Folder = GetParm("folder",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);
    if (empty($Item) || empty($Upload))
        { return; }

    /**********************************
     Display meta data
     **********************************/

    $SQL = "SELECT *
        FROM uploadtree
        INNER JOIN pfile ON uploadtree_pk = $Item
        AND pfile_fk = pfile_pk
        INNER JOIN mimetype ON pfile_mimetypefk = mimetype_pk;";
    $Results = $DB->Action($SQL);
    $Count=1;

    $V .= "<H2>Meta Data</H2>\n";
    $V .= "<table border='1'>\n";
    $V .= "<tr><th width='5%'>Item</th><th width='20%'>Meta Data</th><th>Value</th></tr>\n";
    foreach($Results as $R)
    for($i=0; !empty($Results[$i]['mimetype_pk']); $i++)
        {
        $R = &$Results[$i];
        $V .= "<tr><td align='right'>$Count</td><td>Unpacked file type";
        $V .= "</td><td>" . htmlentities($R['mimetype_name']) . "</td></tr>\n";
        $Count++;
        }
    $V .= "</table>\n";
    /*  Display meta-data get from  pkgmetagetta agent */

    $V .= "<H4>Meta Data From PkgMetaGetta Agent</H4>\n";
    $V .= "<table border='1'>\n";
    $V .= "<tr><th width='5%'>Item</th><th width='20%'>Meta Data</th><th>Value</th></tr>\n";
    $SQL = "SELECT DISTINCT key_name,attrib_value FROM attrib
        INNER JOIN key ON key_pk = attrib_key_fk
        AND key_parent_fk IN
        (SELECT key_pk FROM key WHERE key_parent_fk=0 AND
          (key_name = 'pkgmeta') )
        INNER JOIN uploadtree ON uploadtree_pk = $Item
        AND uploadtree.pfile_fk = attrib.pfile_fk
        AND key_name != 'Processed' ORDER BY key_name;";
    $Results = $DB->Action($SQL);

    for($i=0; !empty($Results[$i]['key_name']); $i++)
        {
        $R = &$Results[$i];
        $V .= "<tr><td align='right'>$Count</td><td>" . htmlentities($R['key_name']);
        $Val = htmlentities($R['attrib_value']);
        $Val = preg_replace("@((http|https|ftp)://[^{}<>&[:space:]]*)@i","<a href='\$1'>\$1</a>",$Val);
        $V .= "</td><td>$Val</td></tr>\n";
        $Count++;
        }

    $V .= "</table>\n";
    $Count--;
    $V .= "<P />Total meta data records: " . number_format($Count,0,"",",") . "<br />\n";
    return($V);
  } // ShowMetaView()

  /*********************************************************** 
   ShowPackageInfo(): Display the package info associated with
   the rpm/debian package.
   ***********************************************************/
  function ShowPackageInfo($ShowMenu=0,$ShowHeader=0)
  {
    global $DB;
    $V = "";
    $Upload = GetParm("upload",PARM_INTEGER);
    $Item = GetParm("item",PARM_INTEGER);
    $Require = "";
    $MIMETYPE = "";
    $Count = 0;	

    if (empty($Item) || empty($Upload))
        { return; }

    /**********************************
     Display micro header
     **********************************/
    if ($ShowHeader)
      {
      $V .= Dir2Browse("browse",$Item,NULL,1,"View");
      } // if ShowHeader

    /**********************************
     Check if pkgagent disabled
    ***********************************/		
    $SQL = "SELECT agent_enabled FROM agent WHERE agent_name ='pkgagent' order by agent_ts LIMIT 1;";
    $Results = $DB->Action($SQL);
    if (isset($Results[0]) && ($Results[0]['agent_enabled']== 'f')){return;}

    /**********************************
     Display package info
     **********************************/
    $V .= "<H2>Package Info</H2>\n";

    $SQL = "SELECT mimetype_name
        FROM uploadtree
        INNER JOIN pfile ON uploadtree_pk = $Item
        AND pfile_fk = pfile_pk
        INNER JOIN mimetype ON pfile_mimetypefk = mimetype_pk;";
    $Results = $DB->Action($SQL);
    foreach($Results as $R)
       {
       if (!empty($R['mimetype_name']))
          {
          $MIMETYPE = $R['mimetype_name'];
          }
       }
    /** RPM Package Info **/
    if ($MIMETYPE == "application/x-rpm")
       {
       $SQL = "SELECT *
                FROM pkg_rpm
                INNER JOIN uploadtree ON uploadtree_pk = $Item
                AND uploadtree.pfile_fk = pkg_rpm.pfile_fk;";
       $Results = $DB->Action($SQL);
       foreach($Results as $R)
          {
          if((!empty($R['source_rpm']))and(trim($R['source_rpm']) != "(none)"))
              {
              $V .= "RPM Binary Package";
              }
          else
              {
              $V .= "RPM Source Package";
              }
          }
       $Count=1;

       $V .= "<table border='1'>\n";
       $V .= "<tr><th width='5%'>Item</th><th width='20%'>Type</th><th>Value</th></tr>\n";

       for($i=0; !empty($Results[$i]['pkg_pk']); $i++)
          {
          $R = &$Results[$i];
          $Require = $R['pkg_pk'];

          $V .= "<tr><td align='right'>$Count</td><td>Package";
          $V .= "</td><td>" . htmlentities($R['pkg_name']) . "</td></tr>\n";
          $Count++;

          $V .= "<tr><td align='right'>$Count</td><td>Alias";
          $V .= "</td><td>" . htmlentities($R['pkg_alias']) . "</td></tr>\n";
          $Count++;
          $V .= "<tr><td align='right'>$Count</td><td>Architecture";
          $V .= "</td><td>" . htmlentities($R['pkg_arch']) . "</td></tr>\n";
          $Count++;
          $V .= "<tr><td align='right'>$Count</td><td>Version";
          $V .= "</td><td>" . htmlentities($R['version']) . "</td></tr>\n";
          $Count++;
	  	
          $V .= "<tr><td align='right'>$Count</td><td>License";
          $V .= "</td><td>" . htmlentities($R['license']) . "</td></tr>\n";
          $Count++;
          $V .= "<tr><td align='right'>$Count</td><td>Group";
          $V .= "</td><td>" . htmlentities($R['pkg_group']) . "</td></tr>\n";
          $Count++;
          $V .= "<tr><td align='right'>$Count</td><td>Packager";
          $V .= "</td><td>" . htmlentities($R['packager']) . "</td></tr>\n";
          $Count++;
          $V .= "<tr><td align='right'>$Count</td><td>Release";
          $V .= "</td><td>" . htmlentities($R['release']) . "</td></tr>\n";
          $Count++;
          $V .= "<tr><td align='right'>$Count</td><td>BuildDate";
          $V .= "</td><td>" . htmlentities($R['build_date']) . "</td></tr>\n";
          $Count++;
          $V .= "<tr><td align='right'>$Count</td><td>Vendor";
          $V .= "</td><td>" . htmlentities($R['vendor']) . "</td></tr>\n";
          $Count++;
          $V .= "<tr><td align='right'>$Count</td><td>URL";
          $V .= "</td><td>" . htmlentities($R['url']) . "</td></tr>\n";
          $Count++;
          $V .= "<tr><td align='right'>$Count</td><td>Summary";
          $V .= "</td><td>" . htmlentities($R['summary']) . "</td></tr>\n";
          $Count++;
          $V .= "<tr><td align='right'>$Count</td><td>Description";
          $V .= "</td><td>" . htmlentities($R['description']) . "</td></tr>\n";
          $Count++;
          $V .= "<tr><td align='right'>$Count</td><td>Source";
          $V .= "</td><td>" . htmlentities($R['source_rpm']) . "</td></tr>\n";
          $Count++;
          }

       $SQL = "SELECT * FROM pkg_rpm_req WHERE pkg_fk = $Require;";
       $Results = $DB->Action($SQL);

       for($i=0; !empty($Results[$i]['req_pk']); $i++)
            {
            $R = &$Results[$i];
            $V .= "<tr><td align='right'>$Count</td><td>Requires";
            $Val = htmlentities($R['req_value']);
            $Val = preg_replace("@((http|https|ftp)://[^{}<>&[:space:]]*)@i","<a href='\$1'>\$1</a>",$Val);
            $V .= "</td><td>$Val</td></tr>\n";
            $Count++;
            }

       $V .= "</table>\n";
       $Count--;

       }
    else if ($MIMETYPE == "application/x-debian-package")
       {
       $V .= "Debian Binary Package\n";

       $SQL = "SELECT *
                FROM pkg_deb
                INNER JOIN uploadtree ON uploadtree_pk = $Item
                AND uploadtree.pfile_fk = pkg_deb.pfile_fk;";
       $Results = $DB->Action($SQL);
       $Count=1;

       $V .= "<table border='1'>\n";
       $V .= "<tr><th width='5%'>Item</th><th width='20%'>Type</th><th>Value</th></tr>\n";
       
       for($i=0; !empty($Results[$i]['pkg_pk']); $i++)
            {
            $R = &$Results[$i];
            $Require = $R['pkg_pk'];

            $V .= "<tr><td align='right'>$Count</td><td>Package";
            $V .= "</td><td>" . htmlentities($R['pkg_name']) . "</td></tr>\n";
            $Count++;

            $V .= "<tr><td align='right'>$Count</td><td>Architecture";
            $V .= "</td><td>" . htmlentities($R['pkg_arch']) . "</td></tr>\n";
            $Count++;
            $V .= "<tr><td align='right'>$Count</td><td>Version";
            $V .= "</td><td>" . htmlentities($R['version']) . "</td></tr>\n";
            $Count++;
            $V .= "<tr><td align='right'>$Count</td><td>Section";
            $V .= "</td><td>" . htmlentities($R['section']) . "</td></tr>\n";
            $Count++;
            $V .= "<tr><td align='right'>$Count</td><td>Priority";
            $V .= "</td><td>" . htmlentities($R['priority']) . "</td></tr>\n";
            $Count++;
            $V .= "<tr><td align='right'>$Count</td><td>Installed Size";
            $V .= "</td><td>" . htmlentities($R['installed_size']) . "</td></tr>\n";
            $Count++;
            $V .= "<tr><td align='right'>$Count</td><td>Maintainer";
            $V .= "</td><td>" . htmlentities($R['maintainer']) . "</td></tr>\n";
            $Count++;
            $V .= "<tr><td align='right'>$Count</td><td>Homepage";
            $V .= "</td><td>" . htmlentities($R['homepage']) . "</td></tr>\n";
            $Count++;
            $V .= "<tr><td align='right'>$Count</td><td>Source";
            $V .= "</td><td>" . htmlentities($R['source']) . "</td></tr>\n";
            $Count++;
            $V .= "<tr><td align='right'>$Count</td><td>Summary";
            $V .= "</td><td>" . htmlentities($R['summary']) . "</td></tr>\n";
            $Count++;
            $V .= "<tr><td align='right'>$Count</td><td>Description";
            $V .= "</td><td>" . htmlentities($R['description']) . "</td></tr>\n";
            $Count++;

            }

       $SQL = "SELECT * FROM pkg_deb_req WHERE pkg_fk = $Require;";
       $Results = $DB->Action($SQL);

       for($i=0; !empty($Results[$i]['req_pk']); $i++)
            {
            $R = &$Results[$i];
            $V .= "<tr><td align='right'>$Count</td><td>Depends";
            $Val = htmlentities($R['req_value']);
            $Val = preg_replace("@((http|https|ftp)://[^{}<>&[:space:]]*)@i","<a href='\$1'>\$1</a>",$Val);
            $V .= "</td><td>$Val</td></tr>\n";
            $Count++;
            }

       $V .= "</table>\n";
       $Count--;
       }

    else if ($MIMETYPE == "application/x-debian-source")
       {
       $V .= "Debian Source Package\n";

       $SQL = "SELECT *
                FROM pkg_deb
                INNER JOIN uploadtree ON uploadtree_pk = $Item
                AND uploadtree.pfile_fk = pkg_deb.pfile_fk;";
       $Results = $DB->Action($SQL);
       $Count=1;

       $V .= "<table border='1'>\n";
       $V .= "<tr><th width='5%'>Item</th><th width='20%'>Type</th><th>Value</th></tr>\n";

       for($i=0; !empty($Results[$i]['pkg_pk']); $i++)
            {
            $R = &$Results[$i];
            $Require = $R['pkg_pk'];

            $V .= "<tr><td align='right'>$Count</td><td>Format";
            $V .= "</td><td>" . htmlentities($R['format']) . "</td></tr>\n";
            $Count++;

            $V .= "<tr><td align='right'>$Count</td><td>Source";
            $V .= "</td><td>" . htmlentities($R['source']) . "</td></tr>\n";
            $Count++;
            $V .= "<tr><td align='right'>$Count</td><td>Binary";
            $V .= "</td><td>" . htmlentities($R['pkg_name']) . "</td></tr>\n";
            $Count++;
            $V .= "<tr><td align='right'>$Count</td><td>Architecture";
            $V .= "</td><td>" . htmlentities($R['pkg_arch']) . "</td></tr>\n";
            $Count++;
            $V .= "<tr><td align='right'>$Count</td><td>Version";
            $V .= "</td><td>" . htmlentities($R['version']) . "</td></tr>\n";
            $Count++;
            $V .= "<tr><td align='right'>$Count</td><td>Maintainer";
            $V .= "</td><td>" . htmlentities($R['maintainer']) . "</td></tr>\n";
            $Count++;
            $V .= "<tr><td align='right'>$Count</td><td>Uploaders";
            $V .= "</td><td>" . htmlentities($R['uploaders']) . "</td></tr>\n";
            $Count++;
            $V .= "<tr><td align='right'>$Count</td><td>Standards-Version";
            $V .= "</td><td>" . htmlentities($R['standards_version']) . "</td></tr>\n";
            $Count++;
            }

       $SQL = "SELECT * FROM pkg_deb_req WHERE pkg_fk = $Require;";
       $Results = $DB->Action($SQL);

       for($i=0; !empty($Results[$i]['req_pk']); $i++)
            {
            $R = &$Results[$i];
            $V .= "<tr><td align='right'>$Count</td><td>Build-Depends";
            $Val = htmlentities($R['req_value']);
            $Val = preg_replace("@((http|https|ftp)://[^{}<>&[:space:]]*)@i","<a href='\$1'>\$1</a>",$Val);
            $V .= "</td><td>$Val</td></tr>\n";
            $Count++;
            }

       $V .= "</table>\n";
       $Count--;
       }

    else
       {
       $V .= "NOT RPM/DEBIAN Package.";
       }
    $V .= "<P />Total package info records: " . number_format($Count,0,"",",") . "<br />\n";
    return($V);
  } // ShowPackageInfo()

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
    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$V .= $this->ShowPackageinfo(1,1);
	$V .= $this->ShowSightings();
	$V .= $this->ShowView(0,0);
	$V .= $this->ShowMetaView();
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print("$V");
    return;
    }

  };
$NewPlugin = new ui_view_info;
$NewPlugin->Initialize();
?>
