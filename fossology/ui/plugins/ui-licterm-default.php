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
class licterm_default extends FO_Plugin
  {
  var $Name       = "license_terms_default";
  var $Title      = "Create Default License Terms";
  var $Version    = "1.0";
  var $MenuList   = "Organize::License::Default Terms";
  var $Dependency = array("db","licterm_manage");
  var $DBaccess   = PLUGIN_DB_USERADMIN;
  var $LoginFlag  = 1; /* must be logged in to use this */

  /***********************************************************
   ExportTerms(): Display the entire term table system as a big array.
   This array should be pasted into the Default() function for
   use as the default values.
   ***********************************************************/
  function ExportTerms	()
    {
    global $DB;
    $Names = $DB->Action("SELECT * FROM licterm ORDER BY licterm_name;");
    print "<H3>Export Data</H3>\n";
    print "Here is the PHP code to use for importing the data:<br>\n";
    print "<pre>";
    print '    $Term=array();' . "\n";
    for($n=0; !empty($Names[$n]['licterm_name']); $n++)
      {
      $Name = $Names[$n]['licterm_name'];
      $Name = str_replace('"','\\"',$Name);
      $Desc = $Names[$n]['licterm_desc'];
      $Desc = str_replace('"','\\"',$Desc);
      $Pk = $Names[$n]['licterm_pk'];

      print "    /* Canonical name: $Pk */\n";
      print '    $Term["' . $Name . '"]["Desc"]="' . $Desc . '";' . "\n";

      $SQL = "SELECT DISTINCT licterm_words_text FROM licterm_words INNER JOIN licterm_map ON licterm_fk='$Pk' AND licterm_words_fk = licterm_words_pk ORDER BY licterm_words_text;";
      $Terms = $DB->Action($SQL);
      for($t=0; !empty($Terms[$t]['licterm_words_text']); $t++)
        {
	$Term = $Terms[$t]['licterm_words_text'];
        $Term = str_replace('"','\\"',$Term);
	print '    $Term["' . $Name . '"]["Term"][' . $t . ']="' . $Term . '";' . "\n";
	}

      $SQL = "SELECT DISTINCT lic_name FROM agent_lic_raw INNER JOIN licterm_maplic ON licterm_fk='$Pk' AND lic_fk = lic_pk ORDER BY lic_name;";
      $Lics = $DB->Action($SQL);
      for($l=0; !empty($Lics[$l]['lic_name']); $l++)
        {
	$Lic = $Lics[$l]['lic_name'];
        $Lic = str_replace('"','\\"',$Lic);
	print '    $Term["' . $Name . '"]["License"][' . $l . ']="' . $Lic . '";' . "\n";
	}
      }
    print "</pre>";
    print "<hr>\n";
    } // ExportTerms()

  /***********************************************************
   DefaultTerms(): Create a default terms, canonical names, and
   associations.
   The huge array list was created by the Export() call.
   ***********************************************************/
  function DefaultTerms()
    {
    global $DB;
    global $Plugins;

    /**************************************/
    /** BEGIN: Term list from ExportTerms() **/
    /**************************************/
    $Term=array();
    /* Canonical name: 71 */
    $Term["3dfx GLIDE"]["Desc"]="3dfx GLIDE Source Code General Public License";
    $Term["3dfx GLIDE"]["Term"][0]="3dfx glide";
    /* Canonical name: 45 */
    $Term["Adaptive Public License"]["Desc"]="Adaptive Public License";
    $Term["Adaptive Public License"]["Term"][0]="adaptive public license";
    /* Canonical name: 47 */
    $Term["Affero GPL"]["Desc"]="Affero General Public License";
    $Term["Affero GPL"]["Term"][0]="affero";
    $Term["Affero GPL"]["Term"][1]="affero general public license";
    $Term["Affero GPL"]["License"][0]="GPL/Affero/Affero GPL 1.0";
    $Term["Affero GPL"]["License"][1]="GPL/Affero/Affero GPL 3.0";
    /* Canonical name: 10 */
    $Term["AFL"]["Desc"]="Academic Free License";
    $Term["AFL"]["Term"][0]="academic free license";
    $Term["AFL"]["Term"][1]="afl";
    $Term["AFL"]["License"][0]="AFL/AFL/Academic Free License 1.1";
    $Term["AFL"]["License"][1]="AFL/AFL/Academic Free License 1.2";
    $Term["AFL"]["License"][2]="AFL/AFL/Academic Free License 2.0";
    $Term["AFL"]["License"][3]="AFL/AFL/Academic Free License 2.1";
    $Term["AFL"]["License"][4]="AFL/AFL/Academic Free License 3.0";
    /* Canonical name: 53 */
    $Term["Apache License"]["Desc"]="Apache Software Foundation License";
    $Term["Apache License"]["Term"][0]="apache license";
    /* Canonical name: 54 */
    $Term["Apple Common Documentation License"]["Desc"]="Apple Common Documentation License";
    $Term["Apple Common Documentation License"]["Term"][0]="cdl";
    $Term["Apple Common Documentation License"]["Term"][1]="common documentation license";
    /* Canonical name: 55 */
    $Term["Apple Public Source License"]["Desc"]="Apple Public Source License";
    $Term["Apple Public Source License"]["Term"][0]="apl";
    $Term["Apple Public Source License"]["Term"][1]="apple public source license";
    $Term["Apple Public Source License"]["Term"][2]="apsl";
    /* Canonical name: 56 */
    $Term["Artistic License"]["Desc"]="Artistic License";
    $Term["Artistic License"]["Term"][0]="artistic licence";
    $Term["Artistic License"]["Term"][1]="artistic license";
    $Term["Artistic License"]["License"][0]="Artistic/Artistic 1.0";
    $Term["Artistic License"]["License"][1]="Artistic/Artistic 1.0 short";
    $Term["Artistic License"]["License"][2]="Artistic/Artistic 2.0";
    $Term["Artistic License"]["License"][3]="Artistic/Artistic 2.0beta4";
    /* Canonical name: 58 */
    $Term["BitTorrent Open Source License"]["Desc"]="BitTorrent Open Source License";
    $Term["BitTorrent Open Source License"]["Term"][0]="bittorrent open source license";
    $Term["BitTorrent Open Source License"]["Term"][1]="jabber open source license";
    /* Canonical name: 78 */
    $Term["BSD"]["Desc"]="BSD";
    $Term["BSD"]["Term"][0]="bsd";
    $Term["BSD"]["Term"][1]="freebsd";
    $Term["BSD"]["Term"][2]="openbsd";
    $Term["BSD"]["License"][0]="BSD/BSD.new/BSD new";
    $Term["BSD"]["License"][1]="BSD/BSD.new/BSD new short";
    $Term["BSD"]["License"][2]="BSD/BSD.new/Cryptix";
    $Term["BSD"]["License"][3]="BSD/BSD.new/Entessa Public License";
    $Term["BSD"]["License"][4]="BSD/BSD.new/Vovida Software License 1.0";
    $Term["BSD"]["License"][5]="BSD/BSD.old/BSD As-Is clause";
    $Term["BSD"]["License"][6]="BSD/BSD.old/BSD Harvard";
    $Term["BSD"]["License"][7]="BSD/BSD.old/BSD NRL";
    $Term["BSD"]["License"][8]="BSD/BSD.old/BSD old";
    $Term["BSD"]["License"][9]="BSD/BSD.old/BSD UCRegents";
    $Term["BSD"]["License"][10]="BSD/BSD.old/BSD UCRegents 2";
    $Term["BSD"]["License"][11]="BSD/BSD.old/BSD zlib";
    $Term["BSD"]["License"][12]="BSD/BSD.old/FreeBSD";
    $Term["BSD"]["License"][13]="BSD/BSD.old/INRIA-OSL";
    $Term["BSD"]["License"][14]="BSD/BSD.old/Intel-OSL";
    /* Canonical name: 60 */
    $Term["CDDL"]["Desc"]="Common Development and Distribution License";
    $Term["CDDL"]["Term"][0]="cddl";
    $Term["CDDL"]["Term"][1]="common development and distribution license";
    /* Canonical name: 84 */
    $Term["CeCILL"]["Desc"]="CeCILL Free Software License Agreement";
    $Term["CeCILL"]["Term"][0]="cecill";
    $Term["CeCILL"]["License"][0]="Gov/CeCILL-B_V1-en";
    $Term["CeCILL"]["License"][1]="Gov/CeCILL-B_V1-fr";
    $Term["CeCILL"]["License"][2]="Gov/CeCILL-C_V1-en";
    $Term["CeCILL"]["License"][3]="Gov/CeCILL-C_V1-fr";
    $Term["CeCILL"]["License"][4]="Gov/CeCILL_V1.1-US";
    $Term["CeCILL"]["License"][5]="Gov/CeCILL_V1-fr";
    $Term["CeCILL"]["License"][6]="Gov/CeCILL_V2-en";
    $Term["CeCILL"]["License"][7]="Gov/CeCILL_V2-fr";
    /* Canonical name: 62 */
    $Term["Common Public License"]["Desc"]="Common Public License";
    $Term["Common Public License"]["Term"][0]="common public license";
    $Term["Common Public License"]["Term"][1]="cpl";
    /* Canonical name: 63 */
    $Term["Computer Associates Trusted Open Source License"]["Desc"]="Computer Associates Trusted Open Source License";
    $Term["Computer Associates Trusted Open Source License"]["Term"][0]="catosl";
    $Term["Computer Associates Trusted Open Source License"]["Term"][1]="ca tosl";
    $Term["Computer Associates Trusted Open Source License"]["Term"][2]="computer associates trusted open source license";
    $Term["Computer Associates Trusted Open Source License"]["Term"][3]="tosl";
    /* Canonical name: 85 */
    $Term["CUAPL"]["Desc"]="CUA Office Public License";
    $Term["CUAPL"]["Term"][0]="cua office public license";
    $Term["CUAPL"]["Term"][1]="cuapl";
    /* Canonical name: 65 */
    $Term["Eclipse Public License"]["Desc"]="Eclipse Public License";
    $Term["Eclipse Public License"]["Term"][0]="eclipse public license";
    $Term["Eclipse Public License"]["Term"][1]="epl";
    /* Canonical name: 30 */
    $Term["ecos"]["Desc"]="Embedded Configurable Operating System";
    $Term["ecos"]["Term"][0]="ecos";
    /* Canonical name: 66 */
    $Term["Educational Community License"]["Desc"]="Educational Community License";
    $Term["Educational Community License"]["Term"][0]="educational community license";
    /* Canonical name: 67 */
    $Term["Entessa Public License"]["Desc"]="Entessa Public License";
    $Term["Entessa Public License"]["Term"][0]="entessa public license";
    $Term["Entessa Public License"]["Term"][1]="openseal";
    /* Canonical name: 69 */
    $Term["Free Art License"]["Desc"]="Free Art License";
    $Term["Free Art License"]["Term"][0]="free art license";
    /* Canonical name: 70 */
    $Term["GNU Free Documentation License"]["Desc"]="GNU Free Documentation License";
    $Term["GNU Free Documentation License"]["Term"][0]="gfdl";
    $Term["GNU Free Documentation License"]["Term"][1]="gnu free documentation license";
    /* Canonical name: 4 */
    $Term["GPL"]["Desc"]="Gnu General Public License";
    $Term["GPL"]["Term"][0]="gnu general public licence";
    $Term["GPL"]["Term"][1]="gnu general public license";
    $Term["GPL"]["Term"][2]="gnu public license";
    $Term["GPL"]["Term"][3]="gpl";
    /* Canonical name: 7 */
    $Term["GPLv1"]["Desc"]="Gnu Public License version 1";
    $Term["GPLv1"]["Term"][0]="gnu general public licence 1";
    $Term["GPLv1"]["Term"][1]="gnu general public licence version 1";
    $Term["GPLv1"]["Term"][2]="gnu general public license 1";
    $Term["GPLv1"]["Term"][3]="gnu general public license version 1";
    $Term["GPLv1"]["Term"][4]="gplv1";
    $Term["GPLv1"]["Term"][5]="gpl v1";
    $Term["GPLv1"]["Term"][6]="gpl version 1";
    $Term["GPLv1"]["License"][0]="GPL/v1/GPLv1";
    $Term["GPLv1"]["License"][1]="GPL/v1/GPLv1 Preamble";
    $Term["GPLv1"]["License"][2]="GPL/v1/GPLv1 reference";
    /* Canonical name: 5 */
    $Term["GPLv2"]["Desc"]="Gnu Public License version 2";
    $Term["GPLv2"]["Term"][0]="gnu general public licence 2";
    $Term["GPLv2"]["Term"][1]="gnu general public licence as published by the free software foundation either version 2";
    $Term["GPLv2"]["Term"][2]="gnu general public licence either version 2";
    $Term["GPLv2"]["Term"][3]="gnu general public licence version 2";
    $Term["GPLv2"]["Term"][4]="gnu general public license 2";
    $Term["GPLv2"]["Term"][5]="gnu general public license as published by the free software foundation either version 2";
    $Term["GPLv2"]["Term"][6]="gnu general public license either version 2";
    $Term["GPLv2"]["Term"][7]="gnu general public license v2";
    $Term["GPLv2"]["Term"][8]="gnu general public license version 2";
    $Term["GPLv2"]["Term"][9]="gpl 2 0 licence";
    $Term["GPLv2"]["Term"][10]="gpl 2 0 license";
    $Term["GPLv2"]["Term"][11]="gpl 2 licence";
    $Term["GPLv2"]["Term"][12]="gpl 2 license";
    $Term["GPLv2"]["Term"][13]="gplv2";
    $Term["GPLv2"]["Term"][14]="gpl v2";
    $Term["GPLv2"]["Term"][15]="gpl version 2";
    $Term["GPLv2"]["License"][0]="GPL/v2/eCos";
    $Term["GPLv2"]["License"][1]="GPL/v2/GPL from FSF reference 1";
    $Term["GPLv2"]["License"][2]="GPL/v2/GPL from FSF reference 2";
    $Term["GPLv2"]["License"][3]="GPL/v2/GPLv2";
    $Term["GPLv2"]["License"][4]="GPL/v2/GPLv2 Preamble";
    $Term["GPLv2"]["License"][5]="GPL/v2/GPLv2 reference";
    $Term["GPLv2"]["License"][6]="GPL/v2/GPLv2 reference 1";
    $Term["GPLv2"]["License"][7]="GPL/v2/GPLv2 reference 2";
    $Term["GPLv2"]["License"][8]="GPL/v2/GPLv2 reference 3";
    $Term["GPLv2"]["License"][9]="GPL/v2/GPLv2 reference 4";
    $Term["GPLv2"]["License"][10]="GPL/v2/GPLv2 reference 5";
    $Term["GPLv2"]["License"][11]="GPL/v2/GPLv2 reference 6";
    $Term["GPLv2"]["License"][12]="GPL/v2/GPLv2 reference 7";
    $Term["GPLv2"]["License"][13]="GPL/v2/GPLv2 reference 8";
    /* Canonical name: 6 */
    $Term["GPLv3"]["Desc"]="Gnu Public License version 3";
    $Term["GPLv3"]["Term"][0]="gnu general public licence 3";
    $Term["GPLv3"]["Term"][1]="gnu general public licence as published by the free software foundation either version 3";
    $Term["GPLv3"]["Term"][2]="gnu general public licence either version 3";
    $Term["GPLv3"]["Term"][3]="gnu general public licence version 3";
    $Term["GPLv3"]["Term"][4]="gnu general public license 3";
    $Term["GPLv3"]["Term"][5]="gnu general public license as published by the free software foundation either version 3";
    $Term["GPLv3"]["Term"][6]="gnu general public license either version 3";
    $Term["GPLv3"]["Term"][7]="gnu general public license v3";
    $Term["GPLv3"]["Term"][8]="gnu general public license version 3";
    $Term["GPLv3"]["Term"][9]="gpl 3 0 licence";
    $Term["GPLv3"]["Term"][10]="gpl 3 0 license";
    $Term["GPLv3"]["Term"][11]="gpl 3 licence";
    $Term["GPLv3"]["Term"][12]="gpl 3 license";
    $Term["GPLv3"]["Term"][13]="gplv3";
    $Term["GPLv3"]["Term"][14]="gpl v3";
    $Term["GPLv3"]["Term"][15]="gpl version 3";
    $Term["GPLv3"]["License"][0]="GPL/v3/GPLv3";
    $Term["GPLv3"]["License"][1]="GPL/v3/GPLv3 Preamble";
    $Term["GPLv3"]["License"][2]="GPL/v3/GPLv3 reference 1";
    $Term["GPLv3"]["License"][3]="GPL/v3/GPLv3 reference 2";
    /* Canonical name: 98 */
    $Term["gSOAP"]["Desc"]="gSOAP Public License";
    $Term["gSOAP"]["Term"][0]="gsoap public license";
    /* Canonical name: 72 */
    $Term["Independent JPEG Group License"]["Desc"]="Independent JPEG Group";
    $Term["Independent JPEG Group License"]["Term"][0]="ijg";
    $Term["Independent JPEG Group License"]["Term"][1]="independent jpeg group license";
    /* Canonical name: 76 */
    $Term["LaTeX Project Public License"]["Desc"]="LaTeX Project Public License";
    $Term["LaTeX Project Public License"]["Term"][0]="latex project public license";
    $Term["LaTeX Project Public License"]["Term"][1]="lppl";
    /* Canonical name: 75 */
    $Term["LGPL"]["Desc"]="GNU Library General Public License";
    $Term["LGPL"]["Term"][0]="gnu lesser general public licence";
    $Term["LGPL"]["Term"][1]="gnu lesser general public license";
    $Term["LGPL"]["Term"][2]="gnu lesser public licence";
    $Term["LGPL"]["Term"][3]="gnu lesser public license";
    $Term["LGPL"]["Term"][4]="gnu library general public licence";
    $Term["LGPL"]["Term"][5]="gnu library general public license";
    $Term["LGPL"]["Term"][6]="lesser general public licence";
    $Term["LGPL"]["Term"][7]="lesser general public license";
    $Term["LGPL"]["Term"][8]="lesser gpl";
    $Term["LGPL"]["Term"][9]="lgpl";
    $Term["LGPL"]["Term"][10]="lgplv2";
    $Term["LGPL"]["Term"][11]="lgplv3";
    $Term["LGPL"]["Term"][12]="lgpl version 2";
    $Term["LGPL"]["Term"][13]="lgpl version 3";
    $Term["LGPL"]["Term"][14]="library general public licence";
    $Term["LGPL"]["Term"][15]="library general public license";
    $Term["LGPL"]["Term"][16]="library gpl";
    /* Canonical name: 82 */
    $Term["MPL"]["Desc"]="MPL";
    $Term["MPL"]["Term"][0]="mozillapl";
    $Term["MPL"]["Term"][1]="mozilla public license";
    $Term["MPL"]["Term"][2]="mozpl";
    $Term["MPL"]["Term"][3]="mpl";
    /* Canonical name: 94 */
    $Term["Ms-LPL"]["Desc"]="Microsoft Limited Public License";
    $Term["Ms-LPL"]["Term"][0]="microsoft limited public license";
    $Term["Ms-LPL"]["Term"][1]="ms lpl";
    $Term["Ms-LPL"]["License"][0]="Corporate/Microsoft/Microsoft Limited Public License";
    /* Canonical name: 95 */
    $Term["Ms-LRL"]["Desc"]="Microsoft Limited Reciprocal License";
    $Term["Ms-LRL"]["Term"][0]="microsoft limited reciprocal license";
    $Term["Ms-LRL"]["Term"][1]="ms lrl";
    $Term["Ms-LRL"]["License"][0]="Corporate/Microsoft/Microsoft Limited Reciprocal License";
    /* Canonical name: 89 */
    $Term["Ms-PL"]["Desc"]="Microsoft Public License";
    $Term["Ms-PL"]["Term"][0]="microsoft public license";
    $Term["Ms-PL"]["Term"][1]="ms pl";
    $Term["Ms-PL"]["License"][0]="Corporate/Microsoft/Microsoft Public License";
    /* Canonical name: 90 */
    $Term["Ms-RL"]["Desc"]="Microsoft Reciprocal License";
    $Term["Ms-RL"]["Term"][0]="microsoft reciprocal license";
    $Term["Ms-RL"]["Term"][1]="ms rl";
    $Term["Ms-RL"]["License"][0]="Corporate/Microsoft/Microsoft Reciprocal License";
    /* Canonical name: 86 */
    $Term["Non-commercial"]["Desc"]="Generic non-commercial";
    $Term["Non-commercial"]["Term"][0]="non commercial";
    /* Canonical name: 83 */
    $Term["NPL"]["Desc"]="NPL";
    $Term["NPL"]["Term"][0]="mozilla public license";
    $Term["NPL"]["Term"][1]="mpl";
    $Term["NPL"]["Term"][2]="netscape public license";
    $Term["NPL"]["Term"][3]="npl";
    /* Canonical name: 99 */
    $Term["Nvidia License"]["Desc"]="Nvidia family of licenses";
    $Term["Nvidia License"]["License"][0]="Corporate/Nvidia/Nvidia Software License";
    $Term["Nvidia License"]["License"][1]="Corporate/Nvidia/Nvidia Software License variant 1";
    $Term["Nvidia License"]["License"][2]="Corporate/Nvidia/Nvidia Source Code";
    /* Canonical name: 96 */
    $Term["OPL"]["Desc"]="OpenContent License";
    $Term["OPL"]["Term"][0]="opencontent";
    $Term["OPL"]["Term"][1]="open content license";
    $Term["OPL"]["Term"][2]="opl";
    /* Canonical name: 9 */
    $Term["OSL"]["Desc"]="The Open Software License";
    $Term["OSL"]["Term"][0]="open software license";
    $Term["OSL"]["Term"][1]="osl";
    /* Canonical name: 87 */
    $Term["Public Domain"]["Desc"]="Generic Public Domain";
    $Term["Public Domain"]["Term"][0]="public domain";
    /* Canonical name: 97 */
    $Term["Python 2.2"]["Desc"]="Python 2.2 License";
    $Term["Python 2.2"]["Term"][0]="python 2 2 license";
    /* Canonical name: 80 */
    $Term["RealNetworks Community Source License"]["Desc"]="RealNetworks Community Source License";
    $Term["RealNetworks Community Source License"]["Term"][0]="rcsl";
    $Term["RealNetworks Community Source License"]["Term"][1]="realnetworks community source license";
    /* Canonical name: 79 */
    $Term["RealNetworks Public Source License"]["Desc"]="";
    $Term["RealNetworks Public Source License"]["Term"][0]="realnetworks public source license";
    $Term["RealNetworks Public Source License"]["Term"][1]="rpsl";
    /* Canonical name: 101 */
    $Term["RSA"]["Desc"]="RSA Commercial License";
    $Term["RSA"]["Term"][0]="rsa";
    $Term["RSA"]["License"][0]="Corporate/RSA/RSA MD5";
    /* Canonical name: 88 */
    $Term["Solaris "]["Desc"]="Sun Solaris Source Code License";
    $Term["Solaris "]["Term"][0]="sun solaris";
    /* Canonical name: 93 */
    $Term["SPL"]["Desc"]="Sun Public License";
    $Term["SPL"]["Term"][0]="spl";
    $Term["SPL"]["Term"][1]="sun public license";
    /* Canonical name: 29 */
    $Term["Standard Function Library"]["Desc"]="SFL License Agreement";
    $Term["Standard Function Library"]["Term"][0]="sfl";
    $Term["Standard Function Library"]["Term"][1]="standard function library";
    /* Canonical name: 92 */
    $Term["SunVariant1"]["Desc"]="Sun Microsystems variant 1";
    /* Canonical name: 91 */
    $Term["SunVariant2"]["Desc"]="Sun Microsystems variant 2";
    $Term["SunVariant2"]["Term"][0]="sun or x consortium";
    /* Canonical name: 41 */
    $Term["UofUtahPL"]["Desc"]="UNIVERSITY OF UTAH RESEARCH FOUNDATION PUBLIC LICENSE";
    $Term["UofUtahPL"]["Term"][0]="university of utah research foundation public license";
    /* Canonical name: 39 */
    $Term["Vovida"]["Desc"]="Vovida Software License";
    $Term["Vovida"]["Term"][0]="vovida";
    $Term["Vovida"]["Term"][1]="vovida software license";
    /* Canonical name: 37 */
    $Term["WC3"]["Desc"]="World Wide Web Consortium";
    $Term["WC3"]["Term"][0]="w3c";
    $Term["WC3"]["Term"][1]="world wide web consortium";
    /* Canonical name: 38 */
    $Term["WTFPL"]["Desc"]="Do What The Fuck You Want To Public License";
    $Term["WTFPL"]["Term"][0]="do what the fuck you want";
    $Term["WTFPL"]["Term"][1]="wtfpl";
    $Term["WTFPL"]["License"][0]="Free/WTFPL";
    /* Canonical name: 35 */
    $Term["X11"]["Desc"]="X Consortium License";
    $Term["X11"]["Term"][0]="x consortium";
    /* Canonical name: 36 */
    $Term["X.Net"]["Desc"]="X.Net, Inc. License";
    $Term["X.Net"]["Term"][0]="x net";
    /* Canonical name: 34 */
    $Term["Zend"]["Desc"]="Zend Engine License";
    $Term["Zend"]["Term"][0]="zend";
    /* Canonical name: 81 */
    $Term["Zope"]["Desc"]="Zope Public License";
    $Term["Zope"]["Term"][0]="zope public license";
    $Term["Zope"]["Term"][1]="zpl";
    /* Canonical name: 32 */
    $Term["ZopeV1"]["Desc"]="Zope Public License v1";
    $Term["ZopeV1"]["Term"][0]="zope public license version 1";
    $Term["ZopeV1"]["Term"][1]="zpl version 1";
    /* Canonical name: 33 */
    $Term["ZopeV2"]["Desc"]="Zope Public License v2";
    $Term["ZopeV2"]["Term"][0]="zope public license version 2";
    $Term["ZopeV2"]["Term"][1]="zpl version 2";
    /**************************************/
    /** END: Term list from ExportTerms() **/
    /**************************************/

    $LT = &$Plugins[plugin_find_id("licterm_manage")];
    foreach($Term as $Key => $Val)
      {
      /* Get the list of licenses */
      $SQL = "SELECT DISTINCT lic_id FROM agent_lic_raw WHERE lic_pk=lic_id AND (";
      $First=0;
      for($L=0; !empty($Val['License'][$L]); $L++)
        {
	if ($First) { $SQL .= " OR"; }
	$First=1;
	$Name = $Val['License'][$L];
	$Name = str_replace("'","''",$Name);
	$SQL .= " lic_name='$Name'";
	}
      $SQL .= ");";
      if ($First)
        {
	$LicListDB = $DB->Action($SQL);
	$LicList = array();
	for($L=0; !empty($LicListDB[$L]['lic_id']); $L++)
	  {
	  $LicList[] = $LicListDB[$L]['lic_id'];
	  }
	}
      else { $LicList = NULL; }
      /* Get the list of terms */
      $TermList = $Val['Term'];
      /* Create! */
      /** Delete terms and license mappings, but not the canonical names **/
      $LT->LicTermInsert('',$Key,$Val['Desc'],$TermList,$LicList,0);
      }
    } // DefaultTerms()

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
	  $rc = $this->DefaultTerms();
	  if (!empty($rc))
	    {
	    $V .= "<script language='javascript'>\n";
	    $rc = htmlentities($rc,ENT_QUOTES);
	    $V .= "alert('$rc')\n";
	    $V .= "</script>\n";
	    }
	  }

	/* Undocumented parameter: Used for exporting the current terms. */
	$Init = GetParm('Export',PARM_INTEGER);
	if ($Init == 1)
	  {
	  $rc = $this->ExportTerms();
	  if (!empty($rc))
	    {
	    $V .= "<script language='javascript'>\n";
	    $rc = htmlentities($rc,ENT_QUOTES);
	    $V .= "alert('$rc')\n";
	    $V .= "</script>\n";
	    }
	  }

	$V .= "<form method='post'>\n";
	$V .= "License terms associate common license names with canonical license names.\n";
	$V .= "For example, the terms 'GPL' and 'Gnu Public License' are both commonly used to describe the Free Software Foundation's GNU General Public License. These terms are all commonly referred to be the canonical name 'GPL'.\n";
	$V .= "<P />\n";
	$V .= "This initialization creates the default license terms, canonical names, and associations between terms, license templates, and canonical names.";
	$V .= "<ul>\n";
	$V .= "<li>The default license settings are <b>NOT</b> a recommendation or legal interpretation.\n";
	$V .= "In particular, related terms, templates, and canonical names may have very different legal meanings.\n";
	$V .= "<li>If you create these defaults twice, then any modification you made to the default settings <b>will be lost</b>.\n";
	$V .= "<li>Creating the default settings will not impact any new terms or associations that you created.\n";
	$V .= "</ul>\n";
	$V .= "After the defaults are created, you can modify, edit, or delete the default groups with the ";
	$P = &$Plugins[plugin_find_id("licterm_manage")];
	$V .= "<a href='" . Traceback_uri() . "?mod=" . $P->Name . "'>" . $P->Title . "</a>";
	$V .= " menu option.\n";
	$V .= "You can also use the ";
	$V .= "<a href='" . Traceback_uri() . "?mod=" . $P->Name . "'>" . $P->Title . "</a>";
	$V .= " to create new terms, canonical names, and associations.<P/>\n";

	$V .= "<P/>\n";
	// $V .= "<input type='checkbox' value='1' name='Export'>Check to export term-related information.<br>\n";
	$V .= "<input type='checkbox' value='1' name='Default'>Check to create the default terms, canonical names, and license template associations.\n";
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
$NewPlugin = new licterm_default;
$NewPlugin->Initialize();
?>
