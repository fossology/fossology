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

 -----------------------------------------------------

 The Javascript code to move values between tables is based
 on: http://www.mredkj.com/tutorials/tutorial_mixed2b.html
 The page, on 28-Apr-2008, says the code is "public domain".
 His terms and conditions (http://www.mredkj.com/legal.html)
 says "Code marked as public domain is without copyright, and
 can be used without restriction."
 This segment of code is noted in this program with "mredkj.com".
 ***********************************************************/

/*************************************************
 Restrict usage: Every PHP file should have this
 at the very beginning.
 This prevents hacking attempts.
 *************************************************/
global $GlobalReady;
if (!isset($GlobalReady)) { exit; }

/************************************************
 Plugin for creating License Groups
 *************************************************/
class licgroup_default extends FO_Plugin
  {
  var $Name       = "license_groups_default";
  var $Title      = "Create Default License Groups";
  var $Version    = "1.0";
  var $MenuList   = "Organize::License::Default Groups";
  var $Dependency = array("db","license_groups_manage");
  var $DBaccess   = PLUGIN_DB_USERADMIN;
  var $LoginFlag  = 1; /* must be logged in to use this */

  var $DefaultName = "Similar Text";

  /***********************************************************
   CmpGroupPaths(): Sort by group paths.
   ***********************************************************/
  function CmpGroupPaths	($a,$b)
    {
    if ($a['lic_name'] < $b['lic_name']) { return(-1); }
    if ($a['lic_name'] > $b['lic_name']) { return(1); }
    return(0);
    } // CmpGroupPaths()

  /***********************************************************
   DefaultGroupList(): List the potential default groups as
   a list of checkboxes.
   ***********************************************************/
  function DefaultGroupList	()
    {
    global $DB;
    $V = "";
    $V .= "<input type='checkbox' value='Group-" . $this->DefaultName . "'>" . $this->DefaultName . "<br>\n";
    $LastPathName = $this->DefaultName;
    $Lics = $DB->Action("SELECT lic_name FROM agent_lic_raw WHERE lic_pk=lic_id ORDER BY lic_name;");
    for($i=0; !empty($Lics[$i]['lic_name']); $i++)
      {
      $Lics[$i]['lic_name'] = $this->DefaultName . "/" . preg_replace("@/[^/]*\$@","",$Lics[$i]['lic_name']);
      }
    usort($Lics,array("licgroup_default","CmpGroupPaths"));
    for($i=0; !empty($Lics[$i]['lic_name']); $i++)
      {
      $PathName = $Lics[$i]['lic_name'];
      if ($PathName == $LastPathName) { continue; }
      $Path = split("/",$PathName);
      for($j=1; !empty($Path[$j]); $j++)
        {
	if ($Path[$j] != $Path[$j-1]) { $V .= "&nbsp;&zwnj;"; }
	}
      $V .= "&mdash;";
      $Group = htmlentities(preg_replace("@^.*/@","",$PathName),ENT_QUOTES);
      $V .= "<input type='checkbox' value='Group-$Group'>$Group<br>\n";
      $LastPathName = $PathName;
      }
    return($V);
    } // DefaultGroupList()

  /***********************************************************
   DefaultGroupsFedora(): Create a default "Fedora" of groups.
   See http://fedoraproject.org/wiki/Licensing
   ***********************************************************/
  function DefaultGroupsFedora	()
    {
    global $DB;
    global $Plugins;

    /* Get the list of licenses */
    $Lics = $DB->Action("SELECT lic_pk,lic_name FROM agent_lic_raw WHERE lic_pk=lic_id ORDER BY lic_name;");
    $LG = &$Plugins[plugin_find_id("license_groups_manage")];

    /* Fedora has three license groups:
       Good (green)
       Bad (red)
       Unknown (yellow)
     */

    $GroupName = "Fedora Good Licenses";
    $GroupColor = "#00ff00";
    $LicList = array(); /* list of licenses in the group */
    for($i=0; !empty($Lics[$i]['lic_pk']); $i++)
      {
      $Name = $Lics[$i]['lic_name'];
      $InGroup=0;
      if (strstr($Name,"/Glide") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Academic Free License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Academy of Motion Picture Arts and Sciences") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Adobe") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Affero") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/Apache/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/APSL/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/Artistic 2.0") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/BitTorrent") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Boost") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"BSD.new/BSD") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"BSD.old/BSD") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"CeCILL_V2") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/CMU/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/CDDL/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Common Public License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Condor") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/FreeWithCopyright/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Cryptix") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"WTFP") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Eclipse Public License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"eCos") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/MIT/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Eiffel Forum License 2") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"EU DataGrid Software License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Fair License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/FreeType/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Giftware") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/GPL/v1/GPL") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/GPL/v2/GPL") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/GPL/v3/GPL") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/GPL/LGPL/LGPL") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"gnuplot") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/IBM_PL/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Imlib2") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"IJG") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Interbase") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Internet Software Consortium") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Jabber") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"JasPer") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"LaTeX") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/Lucent/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"mecab-ipadic") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"X11") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"MPL 1.") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Naumen Public License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"NCSA") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Nethack") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Netizen") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"NPL") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Nokia Open Source License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"OpenLDAP") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/OSL/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"OpenSSL") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Phorum") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"PHP 3.0") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/Python/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Q Public License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/RealNetworks/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Ruby") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Sleepycat") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Starndard ML of New Jersey") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/SISSL/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Sun Public License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"TCL") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Vim") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Vovida Software License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/W3C/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Zend") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"zlib") != FALSE) { $InGroup = 1; }
      if ($InGroup) { $LicList[] = $Lics[$i]['lic_pk']; }
      }
    $LG->LicGroupInsert(-1,$GroupName,$GroupName,$GroupColor,$LicList,NULL);

    $GroupName = "Fedora Bad Licenses";
    $GroupColor = "#ff0000";
    $LicList = array(); /* list of licenses in the group */
    for($i=0; !empty($Lics[$i]['lic_pk']); $i++)
      {
      $Name = $Lics[$i]['lic_name'];
      $InGroup=0;
      if (strstr($Name,"/Adaptive/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Aladdin") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Apple Public Source License 1") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/Artistic 1.0") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"C_Migemo") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Eiffel Forum License 1") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"GPL for Computer Programs of the Public Administration") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Hacktivismo") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Historical Permission Notice and Disclaimer") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Intel-OSL") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Jahia Community Source License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Maia Mailguard License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"MITRE Collaborative Virtual Workspace License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"MSNTP License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"NASA Open Source 1.3") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Open Motif Public End User License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Pine License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"qmail License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Scilab License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"SGI GLX") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Squeak") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Sun Solaris Source Code") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Sybase") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"University of Utah Public License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"X.Net License") != FALSE) { $InGroup = 1; }
      if ($InGroup) { $LicList[] = $Lics[$i]['lic_pk']; }
      }
    $LG->LicGroupInsert(-1,$GroupName,$GroupName,$GroupColor,$LicList,NULL);

    $GroupName = "Fedora Unknown Licenses";
    $GroupColor = "#ffff00";
    $LicList = array(); /* list of licenses in the group */
    for($i=0; !empty($Lics[$i]['lic_pk']); $i++)
      {
      $Name = $Lics[$i]['lic_name'];
      $InGroup=0;
      if (strstr($Name,"Attribution Assurance License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Computer Associates Trusted Open Source License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"CUA Office Public License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Educational Community License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Entessa Public License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Motosoto") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"OCLC") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Open Group Test Suite License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Ricoh Source Code Public License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/RSA/") != FALSE) { $InGroup = 1; }
      if ($InGroup) { $LicList[] = $Lics[$i]['lic_pk']; }
      }
    $LG->LicGroupInsert(-1,$GroupName,$GroupName,$GroupColor,$LicList,NULL);

    /* Now, get the list of group ids */
    $GroupName = "Fedora License Groups";
    $GroupColor = "#ffffff";
    $Results = $DB->Action("SELECT licgroup_pk FROM licgroup
	WHERE licgroup_name = 'Fedora Good Licenses'
	OR licgroup_name = 'Fedora Bad Licenses'
	OR licgroup_name = 'Fedora Unknown Licenses';");
    $LicList = array();
    for($i=0; !empty($Results[$i]['licgroup_pk']); $i++)
      {
      $LicList[] = $Results[$i]['licgroup_pk'];
      }
    $LG->LicGroupInsert(-1,$GroupName,$GroupName,$GroupColor,NULL,$LicList);
    
    print "Default Fedora groups created.\n<hr>\n";
    } // DefaultGroupsFedora()

  /***********************************************************
   DefaultGroupsFSF(): Create a default "FSF" of groups.
   See http://www.fsf.org/licensing/licenses/index_html
   ***********************************************************/
  function DefaultGroupsFSF	()
    {
    global $DB;
    global $Plugins;

    /* Get the list of licenses */
    $Lics = $DB->Action("SELECT lic_pk,lic_name FROM agent_lic_raw WHERE lic_pk=lic_id ORDER BY lic_name;");
    $LG = &$Plugins[plugin_find_id("license_groups_manage")];

    /* These are the FSF license groups:
       FSF GPL-Compatible Free Software Licenses    (Green)
       FSF GPL-Incompatible Free Software Licenses  (Yellow)
       FSF Non-Free Software Licenses               (Red)
       FSF Free Documentation Licenses              (Green)
       FSF Non-Free Documentation Licenses          (Red)

       These FSF license groups are not going to be created.
       FSF Licenses for Works Besides Software and Documentation
       FSF Licenses for Fonts

       Groups are populated based on the known names in the DB.
       As we modify/add to the DB, this list will likely need to be modifed.
     */

    $GroupName = "FSF GPL-Compatible Free Software Licenses";
    $GroupColor = "#00ff00";
    $LicList = array(); /* list of licenses in the group */
    for($i=0; !empty($Lics[$i]['lic_pk']); $i++)
      {
      $Name = $Lics[$i]['lic_name'];
      $InGroup=0;
      if (strstr($Name,"/LGPL/LGPL") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/GPL/v1") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/GPL/v2") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/GPL/v3") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/Apache/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Artistic 2.") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Affero GPL 3.0") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Sleepycat") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Boost") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/BSD.new/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"CeCILL_V2") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Cryptix") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Eiffel Forum License 2") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"EU DataGrid") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/MIT/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"FreeBSD") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Intel-OSL") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Microsoft Public License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"NCSA") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"OpenLDAP") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Public Domain") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/Free/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/Python Software Foundation 2./") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Ruby") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Starndard ML of New Jersey") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Vim") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/W3C/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"X11") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"zLib") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Zope 2.0") != FALSE) { $InGroup = 1; }
      if ($InGroup) { $LicList[] = $Lics[$i]['lic_pk']; }
      }
    $LG->LicGroupInsert(-1,$GroupName,$GroupName,$GroupColor,$LicList,NULL);

    $GroupName = "FSF GPL-Incompatible Free Software Licenses";
    $GroupColor = "#ffff00";
    $LicList = array(); /* list of licenses in the group */
    for($i=0; !empty($Lics[$i]['lic_pk']); $i++)
      {
      $Name = $Lics[$i]['lic_name'];
      $InGroup=0;
      if (strstr($Name,"Affero GPL 1.0") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/AFL/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Apache Software License 1.") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Apple Public Source License 2.") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/BSD.old/BSD") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/CDDL/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/CPL/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/Condor/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/EPL/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/IBM_PL/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Interbase") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Jabber") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"LaTeX") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Lucent") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Microsoft Reciprocal License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/MPL/MPL") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Netizen") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/MPL/NPL") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Nokia") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"OpenSSL") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Phorum") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/PHP/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Q Public License 1.0") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/RealNetworks/") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"/Sun/Sun") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Zend") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Zope 1.0") != FALSE) { $InGroup = 1; }
      if ($InGroup) { $LicList[] = $Lics[$i]['lic_pk']; }
      }
    $LG->LicGroupInsert(-1,$GroupName,$GroupName,$GroupColor,$LicList,NULL);

    $GroupName = "FSF Non-Free Software Licenses";
    $GroupColor = "#ff0000";
    $LicList = array(); /* list of licenses in the group */
    for($i=0; !empty($Lics[$i]['lic_pk']); $i++)
      {
      $Name = $Lics[$i]['lic_name'];
      $InGroup=0;
      if (strstr($Name,"Aladdin") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Apple Public Source License 1.") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Artistic 1.") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"GPL for Computer Programs of the Public Administration") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Hacktivismo") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Jahia") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Microsoft Limited Public License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Microsoft Limited Reciprical License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Microsoft Reference License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"NASA") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Pine License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"qmail License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Squeak") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"University of Utah Public License") != FALSE) { $InGroup = 1; }
      if ($InGroup) { $LicList[] = $Lics[$i]['lic_pk']; }
      }
    $LG->LicGroupInsert(-1,$GroupName,$GroupName,$GroupColor,$LicList,NULL);

    $GroupName = "FSF Free Documentation Licenses";
    $GroupColor = "#00ff00";
    $LicList = array(); /* list of licenses in the group */
    for($i=0; !empty($Lics[$i]['lic_pk']); $i++)
      {
      $Name = $Lics[$i]['lic_name'];
      $InGroup=0;
      if (strstr($Name,"GNU Free Documentation License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Apple Common Documentation License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Open Publication License") != FALSE) { $InGroup = 1; }
      if ($InGroup) { $LicList[] = $Lics[$i]['lic_pk']; }
      }
    $LG->LicGroupInsert(-1,$GroupName,$GroupName,$GroupColor,$LicList,NULL);

    $GroupName = "FSF Non-Free Documentation Licenses";
    $GroupColor = "#ff0000";
    $LicList = array(); /* list of licenses in the group */
    for($i=0; !empty($Lics[$i]['lic_pk']); $i++)
      {
      $Name = $Lics[$i]['lic_name'];
      $InGroup=0;
      if (strstr($Name,"OpenContent License") != FALSE) { $InGroup = 1; }
      else if (strstr($Name,"Open Directory License") != FALSE) { $InGroup = 1; }
      if ($InGroup) { $LicList[] = $Lics[$i]['lic_pk']; }
      }
    $LG->LicGroupInsert(-1,$GroupName,$GroupName,$GroupColor,$LicList,NULL);

    /* Now, get the list of group ids */
    $GroupName = "FSF License Groups";
    $GroupColor = "#ffffff";
    $Results = $DB->Action("SELECT licgroup_pk FROM licgroup
	WHERE licgroup_name = 'FSF GPL-Compatible Free Software Licenses'
	OR licgroup_name = 'FSF GPL-Incompatible Free Software Licenses'
	OR licgroup_name = 'FSF Non-Free Software Licenses'
	OR licgroup_name = 'FSF Free Documentation Licenses'
	OR licgroup_name = 'FSF Non-Free Documentation Licenses';");
    $LicList = array();
    for($i=0; !empty($Results[$i]['licgroup_pk']); $i++)
      {
      $LicList[] = $Results[$i]['licgroup_pk'];
      }
    $LG->LicGroupInsert(-1,$GroupName,$GroupName,$GroupColor,NULL,$LicList);

    print "Default FSF groups created.\n<hr>\n";
    } // DefaultGroupsFSF()

  /***********************************************************
   DefaultGroups(): Create a default "family" of groups based
   on the installed raw directories.
   ***********************************************************/
  function DefaultGroups	()
    {
    global $DB;
    global $Plugins;

    $LG = &$Plugins[plugin_find_id("license_groups_manage")];

    /* Get the list of licenses */
    $Lics = $DB->Action("SELECT lic_pk,lic_name FROM agent_lic_raw WHERE lic_pk=lic_id ORDER BY lic_name;");

    /* Create default groups */
    /** This will delete and blow away old groups **/
    $GroupPk = array();
    for($i=0; !empty($Lics[$i]['lic_pk']); $i++)
      {
      $Lics[$i]['lic_name'] = "/" . $this->DefaultName . "/" . $Lics[$i]['lic_name'];
      $Name = preg_replace("@/[^/]*$@","",$Lics[$i]['lic_name']);
      foreach(split('/',$Name) as $N)
        {
	if (empty($N)) { continue; }
	if (empty($GroupPk[$N]))
	  {
          $LG->LicGroupInsert(-1,$N,$N,'#ffffff',NULL,NULL);
	  }
	$N1 = str_replace("'","''",$N);
	$Results = $DB->Action("SELECT licgroup_pk FROM licgroup WHERE licgroup_name = '$N1';");
	$GroupPk[$N] = $Results[0]['licgroup_pk'];
	}
      }

    /* Now for the fun part: Populate each of the default groups */
    foreach($GroupPk as $GroupName => $Val)
      {
      $LicList = array(); /* licenses in this group */
      $GrpList = array(); /* groups in this group */
      /* For each group, find all groups that contain the same path.
         Then store the group number and any licenses. */
      for($i=0; !empty($Lics[$i]['lic_pk']); $i++)
        {
	/* Remove filename */
	$Name = preg_replace("@/[^/]*$@","",$Lics[$i]['lic_name']);
	/* Check if it matches a license */
	if (preg_match("@/$GroupName\$@",$Name))
	  {
	  $LicList[] = $Lics[$i]['lic_pk'];
	  }
	/* Check if it matches a group containing a group */
	if (preg_match("@/$GroupName/@",$Name))
	  {
	  $Member = preg_replace("@^.*/$GroupName/@","",$Name);
	  $Member = preg_replace("@/.*@","",$Member);
	  $GrpList[] = $GroupPk[$Member];
	  }
	}
      /* Save the license info */
      $GrpList = array_unique($GrpList);
      sort($GrpList);
      $LG->LicGroupInsert(-1,$GroupName,$GroupName,'#ffffff',$LicList,$GrpList);
      }
    print "Created " . count($GroupPk) . " default license groups,";
    print " containing " . count($Lics) . " licenses.\n";
    print "All default groups are stored under the license group '" . $this->DefaultName . "'.";
    print "<hr>\n";
    } // DefaultGroups()

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
    global $Plugins;

    if ($this->State != PLUGIN_STATE_READY) { return; }
    $V="";
    switch($this->OutputType)
      {
      case "XML":
	break;
      case "HTML":
	$Init = GetParm('Default',PARM_INTEGER);
	if ($Init == 1)
	  {
	  $rc = $this->DefaultGroups();
	  if (!empty($rc))
	    {
	    $V .= "<script language='javascript'>\n";
	    $rc = htmlentities($rc,ENT_QUOTES);
	    $V .= "alert('$rc')\n";
	    $V .= "</script>\n";
	    }
	  }

	$Init = GetParm('Default-FSF',PARM_INTEGER);
	if ($Init == 1)
	  {
	  $rc = $this->DefaultGroupsFSF();
	  if (!empty($rc))
	    {
	    $V .= "<script language='javascript'>\n";
	    $rc = htmlentities($rc,ENT_QUOTES);
	    $V .= "alert('$rc')\n";
	    $V .= "</script>\n";
	    }
	  }

	$Init = GetParm('Default-Fedora',PARM_INTEGER);
	if ($Init == 1)
	  {
	  $rc = $this->DefaultGroupsFedora();
	  if (!empty($rc))
	    {
	    $V .= "<script language='javascript'>\n";
	    $rc = htmlentities($rc,ENT_QUOTES);
	    $V .= "alert('$rc')\n";
	    $V .= "</script>\n";
	    }
	  }

	$V .= "<form method='post'>\n";
	$V .= "License groups provide organization for licenses.\n";
	$V .= "By selecting the 'Create' button, you will initialize the license groups.\n";
	$V .= "This initialization will create many default license groups.";
	$V .= "<ul>\n";
	$V .= "<li>The default license groups are <b>NOT</b> a recommendation or legal interpretation.\n";
	$V .= "In particular, related licenses may have very different legal meanings.\n";
	$V .= "<li>If you create these default groups twice, then any modification you made to the default groups <b>will be lost</b>.\n";
	$V .= "<li>Creating default groups will not impact any new groups you created.\n";
	$V .= "</ul>\n";
	$V .= "After the default groups are created, you can modify, edit, or delete the default groups with the ";
	$P = &$Plugins[plugin_find_id("license_groups_manage")];
	$V .= "<a href='" . Traceback_uri() . "?mod=" . $P->Name . "'>" . $P->Title . "</a>";
	$V .= " menu option.\n";
	$V .= "You can also use the ";
	$V .= "<a href='" . Traceback_uri() . "?mod=" . $P->Name . "'>" . $P->Title . "</a>";
	$V .= " to create new groups.<P/>\n";

	$V .= "Select the default groups to create:\n";
	$V .= "<P/>\n";
	$V .= "<input type='checkbox' value='1' name='Default-Tree'><b>" . $this->DefaultName . "</b>.\n";
	$V .= "These are default license groups based on a heirarchy of similar license text.<br>\n";
	$V .= "<input type='checkbox' value='1' name='Default-FSF'><b>FSF</b>. See <a href='http://www.fsf.org/licensing/licenses/index_html'>FSF Licensing</a> for the list of GPL-compatible, incompatible, and free licenses.<br>\n";
	$V .= "<input type='checkbox' value='1' name='Default-Fedora'><b>Fedora</b>. See <a href='http://fedoraproject.org/wiki/Licensing'>Fedora Licensing</a> for the list of good, bad, and unknown licenses.<br>\n";
	$V .= "<P/>\n";
	$V .= "<input type='submit' value='Create!'>";
	$V .= "</form>\n";
	break;
      case "Text":
	break;
      default:
	break;
      }
    if (!$this->OutputToStdout) { return($V); }
    print($V);
    return;
    }

  };
$NewPlugin = new licgroup_default;
$NewPlugin->Initialize();
?>
