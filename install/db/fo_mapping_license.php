<?php
/*
 SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file fo_mapping_license.php
 * @brief Replase the old license shortname with new license shortname
 *
 * This should be called by fossinit and dbmigrate_2.1-2.2.
 **/

/*
$PREFIX = "/usr/local/";
require_once("$PREFIX/share/fossology/lib/php/common.php");
*/

/*
$Usage = "Usage: " . basename($argv[0]) . "
  -s old license
  -t new license
  -h  help 
  ";

$options = getopt("s:t:h");

if (empty($options) || !is_array($options))
{
  print $Usage;
  return 1;
}
*/

/**
 * \brief Create map of old_shortname to new_shortname for 2.1 to 2.2
 *        and call renameLicenses
 *
 * \param boolean $verbose Print job info if TRUE
 */

function renameLicenses21to22($verbose)
{
  /* PLEASE PUT THE LICENSE MAP THERE */
  /* will replace old_shortname with new_shortname in the later process */
  $shortname_array = array(
    /* old_shortname => new_shortname */
    'Adaptive' => 'APL-1.0',
    'Adaptive_v1.0' => 'APL-1.0',
    'Adobe-AFM' => 'AdobeAFM',
    'Affero'   => 'AGPL',
    'Affero_v1'   => 'AGPL-1.0',
    'Affero_v3'   => 'AGPL-3.0',
    'Affero_v3+'   => 'AGPL-3.0+',
    'AFL 1.1'   => 'AFL-1.1',
    'AFL 1.2'   => 'AFL-1.2',
    'AFL 2.0'   => 'AFL-2.0',
    'AFL 2.1'   => 'AFL-2.1',
    'AFL 3.0'   => 'AFL-3.0',
    'AFL_v1.1' => 'AFL-1.1',
    'AFL_v1.2' => 'AFL-1.2',
    'AFL_v2.0' => 'AFL-2.0',
    'AFL_v2.1' => 'AFL-2.1',
    'AFL_v3.0' => 'AFL-3.0',
    'AGPL 1.0' => 'AGPL-1.0',
    'AGPL 3.0' => 'AGPL-3.0',
    'Alfresco FLOSS' => 'Alfresco-FLOSS',
    'Apache 1.1' => 'Apache-1.1',
    'Apache2.0' => 'Apache-2.0',
    'Apache_v1.0' => 'Apache-1.0',
    'Apache_v1.1' => 'Apache-1.1',
    'Apache_v2.0' => 'Apache-2.0',
    'Apache_v2-possibility' => 'Apache-2.0-possibility',
    'Apple' => 'APSL-2.0',
    'APSL1.0' => 'APSL-1.0',
    'APSL1.1' => 'APSL-1.1',
    'APSL1.2' => 'APSL-1.2',
    'APSL_v1.0' => 'APSL-1.0',
    'APSL_v1.1' => 'APSL-1.1',
    'APSL_v1.2' => 'APSL-1.2',
    'APSL_v2.0' => 'APSL-2.0',
    'Aptana' => 'Aptana-1.0',
    'Aptana-PL_v1.0' => 'Aptana-1.0',
    'Artistic1.0' => 'Artistic-1.0',
    'Artistic2.0' => 'Artistic-2.0',
    'Artistic_v2.0' => 'Artistic-2.0',
    'Ascender' => 'Ascender-EULA',
    'ATI EULA' => 'ATI-EULA',
    'ATT-Source_v1.2d' => 'ATT-Source-1.2d', 
    'BitTorrent' => 'BitTorrent-1.1',
    'BitTorrent1.0' => 'BitTorrent-1.0',
    'BitTorrent_v1.1' => 'BitTorrent-1.1',
    'Boost' => 'BSL-1.0',
    'Boost-style' => 'BST-style',
    'Boost_v1.0' => 'BST-1.0',
    'BSD Doc' => 'BSD-Doc',
    'CA1.1' => 'CATOSL-1.1',
    'CCA' => 'CC-BY',
    'CCA1.0' => 'CC-BY-1.0',
    'CCA2.5' => 'CC-BY-2.5',
    'CCA3.0' => 'CC-BY-3.0',
    'CCA-SA' => 'CC-BY-SA',
    'CCA-SA1.0' => 'CC-BY-SA-1.0',
    'CCA-SA2.5' => 'CC-BY-SA-2.5',
    'CCA-SA3.0' => 'CC-BY-SA-3.0',
    'CCA-SA_v1.0' => 'CC-BY-SA-1.0',
    'CCA-SA_v2.5' => 'CC-BY-SA-2.5',
    'CCA-SA_v3.0' => 'CC-BY-SA-3.0',
    'CCA_v2.5' => 'CC-BY-2.5',
    'CC-GPL' => 'GPL',
    'CCGPL2.1' => 'LGPL-2.1',
    'CC-GPL_v2' => 'GPL-2.0',
    'CC-LGPL' => 'LGPL',
    'CC-LGPL_v2.1' => 'LGPL-2.1',
    'CCPL' => 'CC-BY',
    'CCPL_v2.0' => 'CC-BY-2.0',
    'CCPL_v2.5' => 'CC-BY-2.5',
    'CCPL_v3.0' => 'CC-BY-3.0',
    'CDDL1.0' => 'CDDL-1.0',
    'CDDL_v1.0' => 'CDDL-1.0',
    'CeCILL1.0' => 'CECILL-1.0',
    'Cecill1.1' => 'CECILL-1.1',
    'CeCILL2.0' => 'CECILL-2.0',
    'CeCILL-B' => 'CECILL-B',
    'CeCILL-C' => 'CECILL-C',
    'CeCILL_v1.1' => 'CECILL-1.1',
    'CeCILL_v2' => 'CECILL-2.0',
    'CeCILL_v2.0' => 'CECILL-2.0',
    'Condor' => 'Condor-1.1',
    'CPAL 1.0' => 'CPAL-1.0',
    'CPAL_v1.0' => 'CPAL-1.0',
    'CPL0.5' => 'CPL-0.5',
    'CPL1.0' => 'CPL-1.0',
    'CPL_v0.5' => 'CPL-0.5',
    'CPL_v1.0' => 'CPL-1.0',
    'CPOL1.2' => 'CPOL-1.02',
    'CUA' => 'CUA-OPL-1.0',
    'CUA_v1.0' => 'CUA-OPL-1.0',
    'DataGrid' => 'EUDatagrid',
    'DOCBOOK' => 'Docbook',
    'ECL1.0' => 'ECL-1.0',
    'ECL2.0' => 'ECL-2.0',
    'Eclipse' => 'EPL-1.0',
    'Eclipse_v1.0' => 'EPL-1.0',
    'Eiffel1.0' => 'EFL-1.0',
    'Eiffel2.0' => 'EFL-2.0', 
    'Eiffel_v1' => 'EFL-1.0',
    'Eiffel_v2' => 'EFL-2.0',
    'Entessa1.0' => 'Entessa',
    'EU-DataGrid' => 'EUDatagrid',
    'Fedora-CLA' => 'FedoraCLA',
    'Frameworx1.0' => 'Frameworx-1.0',
    'Frameworx_v1.0' => 'Frameworx-1.0',
    'FreeArt' => 'Free-Art-1.3',
    'Free-Art_v1.0' => 'Free-Art-1.0',
    'Free-Art_v1.3' => 'Free-Art-1.3',
    'Freetype' => 'FTL',
    'FreeType' => 'FTL',
    'Freetype-style' => 'FTL-style',
    'GFDL' => 'GFDL-1.1',
    'GFDL1.2' => 'GFDL-1.2',
    'GFDL1.3' => 'GFDL-1.3',
    'GFDL_v1.1' => 'GFDL-1.1',
    'GFDL_v1.1+' => 'GFDL-1.1+',
    'GFDL_v1.2' => 'GFDL-1.2',
    'GFDL_v1.2+' => 'GFDL-1.2+',
    'Ghostscript-GPL_v1.1' => 'Ghostscript-GPL-1.1',
    'GPL1.0' => 'GPL-1.0',
    'GPL2.0' => 'GPL-2.0',
    'GPL3.0' => 'GPL-3.0',
    'GPL_v1' => 'GPL-1.0',
    'GPL_v1+' => 'GPL-1.0+',
    'GPL_v1-possibility' => 'GPL-1.0-possibility',
    'GPL_v2' => 'GPL-2.0',
    'GPL_v2+' => 'GPL-2.0+',
    'GPL_v2.1' => 'GPL-2.1',
    'GPL_v2.1+' => 'GPL-2.1+',
    'GPLv2+KDEupgradeClause' => 'GPL-2.0+KDEupgradeClause',
    'GPL_v2-possibility' => 'GPL-2.0-possibility',
    'GPL_v2:v3' => 'GPL-2.0:3.0',
    'GPL_v3' => 'GPL-3.0',
    'GPL_v3+' => 'GPL-3.0+',
    'gSOAP' => 'gSOAP-1.3a',
    'gSOAP_v1.3' => 'gSOAP-1.3b',
    'Helix/RealNetworks EULA' => 'Helix/RealNetworks-EULA',
    'IBM' => 'IPL-1.0',
    'IBM-PL' => 'IPL',
    'IBM-PL_v1.0' => 'IPL-1.0',
    'IDPL_v1.0' => 'IDPL-1.0',
    'InnerNet_v2.00' => 'InnerNet-2.00',
    'Intel-EULA' => 'Intel',
    'Intel' => 'Intel-EULA',
    'Interbase-PL' => 'Interbase',
    'Jabber' => 'Jabber-1.0',
    'LaTeX1.0' => 'LPPL-1.0',
    'LaTeX1.1' => 'LPPL-1.1',
    'LaTeX1.2' => 'LPPL-1.2',
    'LaTeX1.3' => 'LPPL-1.3',
    'LaTeX1.3a' => 'LPPL-1.3a',
    'LaTeX1.3b' => 'LPPL-1.3b',
    'LaTeX1.3c' => 'LPPL-1.3c',
    'LDP_v1A' => 'LDP-1A',
    'LDP_v2.0' => 'LDP-2.0',
    'LGPL2.1' => 'LGPL-2.1',
    'LGPL3.0' => 'LGPL-3.0',
    'LGPL_v1' => 'LGPL-1.0',
    'LGPL_v1+' => 'LGPL-1.0+',
    'LGPL_v2' => 'LGPL-2.0',
    'LGPL_v2+' => 'LGPL-2.0+',
    'LGPL_v2.1' => 'LGPL-2.1',
    'LGPL_v2.1+' => 'LGPL-2.1+',
    'LGPL_v2.1-possibility' => 'LGPL-2.1-possibility',
    'LGPL_v2-possibility' => 'LGPL-2.0-possibility',
    'LGPL_v3' => 'LGPL-3.0',
    'LGPL_v3?' => 'LGPL-3?',
    'LGPL_v3+' => 'LGPL-3.0+',
    'LGPL_v3-possibility' => 'LGPL-3.0-possibility',
    'LPPL_v1.0' => 'LPPL-1.0',
    'LPPL_v1.0+' => 'LPPL-1.0+',
    'LPPL_v1.1' => 'LPPL-1.1',
    'LPPL_v1.1+' => 'LPPL-1.1+',
    'LPPL_v1.2' => 'LPPL-1.2',
    'LPPL_v1.2+' => 'LPPL-1.2+',
    'LPPL_v1.3' => 'LPPL-1.3',
    'LPPL_v1.3+' => 'LPPL-1.3+',
    'LPPL_v1.3a' => 'LPPL-1.3a',
    'LPPL_v1.3a+' => 'LPPL-1.3a+',
    'LPPL_v1.3b' => 'LPPL-1.3b',
    'LPPL_v1.3b+' => 'LPPL-1.3b+',
    'LPPL_v1.3c' => 'LPPL-1.3c',
    'LPPL_v1.3c+' => 'LPPL-1.3c+',
    'Lucent1.0' => 'LPL-1.0', 
    'Lucent1.02' => 'LPL-1.02',
    'Lucent_v1.0' => 'LPL-1.0',
    'Lucent_v1.02' => 'LPL-1.02',
    'Majordomo' => 'Majordomo-1.1',
    'Majordomo_v1.1' => 'Majordomo-1.1',
    'MetroLink1.0' => 'MetroLink-1.0',
    'Motosoto_v0.9.1' => 'Motosoto',
    'Mozilla1.0' => 'MPL-1.0',
    'Mozilla1.1' => 'MPL-1.1',
    'MozillaEULA1.1' => 'MPL-EULA-1.1',
    'MozillaEULA2.0' => 'MPL-EULA-2.0',
    'MozillaEULA3.0' => 'MPL-EULA-3.0', 
    'MPL-EULA_v1.1' => 'MPL-EULA-1.1',
    'MPL-EULA_v3.0' => 'MPL-EULA-3.0',
    'MPL/TPL_v1.0' => 'MPL/TPL-1.0',
    'MPL_v1.0' => 'MPL-1.0',  
    'MPL_v1.1' => 'MPL-1.1',
    'MPL_v1.1+' => 'MPL-1.1+',
    'MPL_v2.0' => 'MPL-2.0',
    'Ms-EULA' => 'MS-EULA', 
    'Ms-indemnity' => 'MS-indemnity',
    'Ms-IP' => 'MS-IP',
    'Ms-LPL' => 'MS-LPL',
    'Ms-LRL' => 'MS-LRL', 
    'Ms-PL' => 'MS-PL',
    'Ms-RL' => 'MS-RL',
    'MySQL_v0.3' => 'MySQL-0.3',
    'NASA1.3' => 'NASA-1.3',  
    'NASA_v1.3' => 'NASA-1.3',  
    'Naumen' => 'NAUMEN',  /** replace */
    'NAUMEN' => 'Naumen',  /** change */
    'Nethack' => 'NGPL',
    'Netizen' => 'NOSL',
    'Netizen1.0' => 'NOSL', 
    'Netscape' => 'NPL-1.0',
    'Netscape1.1' => 'NPL-1.1',
    'Nokia_v1.0a' => 'Nokia-1.0a',
    'NoLicenseFound' => 'No_license_found',
    'NPL_v1.0' => 'NPL-1.0',  
    'NPL_v1.1' => 'NPL-1.1',
    'Nvidia-EULA' => 'NvidiaEULA', /** replase */
    'NvidiaEULA' => 'Nvidia-EULA', /** change */
    'OCLC_v1.0' => 'OCLC-1.0',
    'OCLC_v2.0' => 'OCLC-2.0',  
    'OpenLDAP' => 'OLDAP',
    'OpenLDAP2.8' => 'OLDAP-2.8',
    'OpenLDAP_v1.2' => 'OLDAP-1.2',
    'OpenLDAP_v2.7' => 'OLDAP-2.7',
    'OpenLDAP_v2.8' => 'OLDAP-2.8', 
    'OpenPL' => 'OPL-1.0',
    'Open-PL_v1.0' => 'Open-PL-1.0',
    'OpenPublication' => 'Open-PL-1.0',
    'Open-Publication' => 'Open-PL-1.0',
    'Open-Publication-style' => 'Open-PL-style',  
    'Open-Publication_v1.0' => 'Open-PL-1.0',
    'OpenSoftware' => 'OSL-1.0',
    'OpenSoftware1.1' => 'OSL-1.1',
    'OpenSoftware2.0' => 'OSL-2.0',
    'OpenSoftware2.1' => 'OSL-2.1',
    'OpenSoftware3.0' => 'OSL-3.0',
    'Oracle-Dev' => 'OracleDev',
    'OracleDev' => 'Oracle-Dev',  
    'OSL_v1.0' => 'OSL-1.0',
    'OSL_v1.1' => 'OSL-1.1',
    'OSL_v2.0' => 'OSL-2.0',
    'OSL_v2.1' => 'OSL-2.1',
    'OSL_v3.0' => 'OSL-3.0',  
    'Phorum2.0' => 'Phorum-2.0',
    'PHP2.02' => 'PHP-2.02',
    'PHP3.01' => 'PHP-3.01',
    'PHP_v2.0' => 'PHP-2.0',
    'PHP_v2.0.2' => 'PHP-2.0.2',  
    'PHP_v3.0' => 'PHP-3.0',
    'PHP_v3.01' => 'PHP-3.01',
    'Pixware-EULA' => 'Pixware',  /** replace */
    'Pixware' => 'Pixware-EULA',  /** change */
    'Public-Use_v1.0' => 'Public-Use-1.0',  
    'Python2.0.1' => 'Python-2.0.1',
    'Python2.1.3' => 'Python-2.1.3',
    'Python2.2.3' => 'Python-2.2.3',
    'Python2.3.7' => 'Python-2.3.7',  
    'Python2.4.4' => 'Python-2.4.4',  
    'Python2.5' => 'Python-2.5',  
    'Python2.6.5' => 'Python-2.6.5',  
    'Python2.7' => 'Python-2.7',  
    'Python3.1.1' => 'Python-3.1.1',  
    'Python_v2' => 'Python-2.0',  
    'Python_v2.0.1' => 'Python-2.0.1',  
    'Python_v2.1.1' => 'Python-2.1.1',  
    'Python_v2.1.3' => 'Python-2.1.3',  
    'Python_v2.2' => 'Python-2.2',  
    'Python_v2.2.3' => 'Python-2.2.3',  
    'Python_v2.3' => 'Python-2.3',  
    'Python_v2.3.7' => 'Python-2.3.7',  
    'Python_v2.4.4' => 'Python-2.4.4',  
    'QPL' => 'QPL-1.0', 
    'QPL_v1.0' => 'QPL-1.0',  
    'QT' => 'QT(Commercial)', 
    'Qt(Commercial)' => 'QT(Commercial)', 
    'RCSL_v3.0' => 'RCSL',  
    'RealNetworks-EULA' => 'RealNetworks',  /** replace */
    'RealNetworks' => 'RealNetworks-EULA',  /** change */
    'RedHat-EULA' => 'RedHatEULA',   /** replace */
    'RedHatEULA' => 'RedHat-EULA',   /** change */
    'Ricoh' => 'RSCPL', 
    'Ricoh_v1.0' => 'RSCPL-1.0',  
    'RPL1.1' => 'RPL-1.1',  
    'RPL1.5' => 'RPL-1.5',  
    'RPL_v1.1' => 'RPL-1.1',  
    'RPL_v1.5' => 'RPL-1.5',  
    'RPSL1.0' => 'RPSL-1.0',  
    'RPSL_v1.0' => 'RPSL-1.0',  
    'SCSL-TSA_v1.0' => 'SCSL-TSA-1.0',  
    'SCSL_v2.3' => 'SCSL-2.3',  
    'SCSL_v3.0' => 'SCSL-3.0',  
    'SGI-2.0' => 'SGI-B-2.0', 
    'SGI-B' => 'SGI-B-1.0', 
    'SGI-B1.1' => 'SGI-B-1.1',  
    'SGI-B2.0' => 'SGI-B-2.0',  
    'SGI_GLX_v1.0' => 'SGI_GLX-1.0',  
    'SGI_v1.0' => 'SGI-B-1.0',  
    'SGI_v1.1' => 'SGI_B-1.1',  
    'SGI_v2.0' => 'SGI-B-2.0',  
    'SISSL_v1.1' => 'SISSL-1.1',  
    'Skype' => 'Skype-EULA',  
    /** Sleepycat's license text is incorrect */
    'SleepycatOracle'  =>  'Oracle-Berkeley-DB',
    'Sleepycat(Oracle)'  =>  'Oracle-Berkeley-DB',
    'SNIA1.1'  =>  'SNIA-1.1',
    'SNIA_v1.0'  =>  'SNIA-1.0',
    'SNIA_v1.1'  =>  'SNIA-1.1',
    'SugarCRM'  =>  'SugarCRM-1.1.3',
    'SunPL1.0'  =>  'SPL-1.0',
    'Sun-PL_v1.0'  =>  'SPL-1.0',
    'TrollTech'  =>  'Trolltech',
    'UCWare'  =>  'UCWare-EULA',
    'Vim'  =>  'VIM',
    'VMWare'  =>  'VMWare-EULA',
    'Vovida'  =>  'VSL-1.0',
    'wxWindows'  =>  'WXwindows',
    'Ximian_v1.0'  =>  'Ximian-1.0',
    'Yahoo-EULA'  =>  'Yahoo',  /** replace */
    'Yahoo'  =>  'Yahoo-EULA',  /** change */
    'Zend_v2.0'  =>  'Zend-2.0',
    'ZLib'  =>  'Zlib',
    'ZoneAlarm'  =>  'ZoneAlarm-EULA',
    'Zope'  =>  'ZPL',
    'Zope-PL_v2.0'  =>  'ZPL-2.0',
    'ZPL1.1'  =>  'ZPL-1.1',
    'ZPL2.0'  =>  'ZPL-2.0',
    'ZPL2.1'  =>  'ZPL-2.1'
    );
  renameLicenses($shortname_array, $verbose);
}

/**
 * \brief Create map of old_shortname to new_shortname for SPDX
 *        and call renameLicenses
 *
 * \param boolean $verbose Print job info if TRUE
 */
function renameLicensesForSpdxValidation($verbose)
{
  $shortname_array = array(
     'Adaptec(RESTRICTED)' => 'Adaptec.RESTRICTED',
     'AGFA(RESTRICTED)' => 'AGFA.RESTRICTED',
     'Alfresco/FLOSS' => 'Alfresco-FLOSS',
     'AndroidSDK(Commercial)' => 'AndroidSDK.Commercial',
     'AndroidFraunhofer(Commercial)' => 'AndroidFraunhofer.Commercial',
     'Apple(FontForge)' => 'Apple.FontForge',
     'Apple(Sample)' => 'Apple.Sample', 
     'ATT-Source_v1.2d' => 'ATT-Source-1.2d',
     'ATT(Non-commercial)' => 'ATT.Non-commercial', 
     'Baekmuk(Hwan)' => 'Baekmuk.Hwan',
     'Broadcom(Commercial)' => 'Broadcom.Commercial',
     'BSD(non-commercial)' => 'BSD.non-commercial',
     'Genivia(Commercial)' => 'Genivia.Commercial',
     "Gov''t-work" => 'Govt-work',
     "Gov''t-rights" => 'Govt-rights',
     'GNU-style(EXECUTE)' => 'GNU-style.EXECUTE',
     'GNU-style(interactive)' => 'GNU-style.interactive',
     'Helix/RealNetworks EULA' => 'Helix.RealNetworks-EULA',
     'Helix/RealNetworks-EULA' => 'Helix.RealNetworks-EULA',
     'Intel(Commercial)' => 'Intel.Commercial',
     'Intel(RESTRICTED)' => 'Intel.RESTRICTED',
     'IoSoft(COMMERCIAL)' => 'IoSoft.COMMERCIAL',
     'JPEG/netpbm' => 'JPEG.netpbm',
     'MIT/BSD' => 'MIT.BSD',
     'MPL/TPL_v1.0' => 'MPL.TPL-1.0',
     'MPL/TPL' => 'MPL.TPL',
     'MySQL/FLOSS' => 'MySQL.FLOSS',
     'Non-commercial!' => 'Non-commercial',
     'Non-profit!' => 'Non-profit',
     'Not-Free!' => 'Not-Free',
     'Not-for-sale!' => 'Not-for-sale',
     'Not-OpenSource!' => 'Not-OpenSource', 
     "O''Reilly" => 'OReilly',
     "O''Reilly-style" => 'OReilly-style',
     'Proprietary!' => 'Proprietary',
     'QT' => 'QT.Commercial', 
     'Qt(Commercial)' => 'QT.Commercial',
     'QT(Commercial)' => 'QT.Commercial', 
     'RedHat(Non-commercial)' => 'RedHat.Non-commercial',
     'SCO(commercial)' => 'SCO.commercial',
     'See-doc(OTHER)' => 'See-doc.OTHER',
     'See-file(COPYING)' => 'See-file.COPYING',
     'See-file(LICENSE)' => 'See-file.LICENSE',
     'See-file(README)' => 'See-file.README',  
     'Sun(Non-commercial)'  =>  'Sun.Non-commercial',
     'Sun(RESTRICTED)'  =>  'Sun.RESTRICTED',
     "URA(gov''t)"  =>  'URA.govt',
     'U-Wash(Free-Fork)'  =>  'U-Wash.Free-Fork',
     'USC(Non-commercial)'  =>  'USC.Non-commercial',
     'WTI(Not-free)'  =>  'WTI.Not-free',
     'YaST(SuSE)'  =>  'YaST.SuSE'
      );
  renameLicenses($shortname_array, $verbose);
}
  
/**
 * \brief Rename old shortname to new shortname
 *
 * \param mixed $shortname_array Map of short names [old_shortname]=>new_shortname
 * \param boolean $Verbose Print job info if TRUE
 */
function renameLicenses($shortname_array, $Verbose)
{
  foreach ($shortname_array as $old_shortname => $new_shortname)
  {
    $old_rf_pk = check_shortname($old_shortname);
    $new_rf_pk = check_shortname($new_shortname);
    if (-1 != $old_rf_pk && -1 != $new_rf_pk)
    {
      $res = update_license($old_rf_pk, $new_rf_pk);
      if (0 == $res) 
      {
        if($Verbose)
          print "update successfully, substitute rf_id(license_name) from $old_rf_pk($old_shortname) to $new_rf_pk($new_shortname).\n";
      }
    }
    else if (-1 != $old_rf_pk && -1 == $new_rf_pk)
    {
      $res =  change_license_name($old_shortname, $new_shortname);
      if (0 == $res)
      {
        if($Verbose)
          print "change license name successfully, substitute license shortname from $old_shortname to $new_shortname.\n";
      }

    } 
    else if (-1 == $old_rf_pk)
    {
      if($Verbose)
        print "the $old_shortname is not existing.\n";
    }
  }

  if($Verbose)
    print "End!\n";
}

/** 
 * \brief check if the shortname is existing in license_ref table
 * 
 * \param string $shortname - the license which you want to check 
 *
 * \return int rf_id on existing; -1 on not existing
 */
function check_shortname($shortname)
{
  global $PG_CONN;
  $sql = "SELECT rf_pk from license_ref where rf_shortname = '$shortname'";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $row = pg_fetch_assoc($result);
  pg_free_result($result);
  if ($row && $row['rf_pk']) return $row['rf_pk'];
  else return -1;
}

/**
 * \brief update license from old to new
 * 1) update license_file set rf_fk=new_rf_pk where rf_fk=old_rf_pk
 * 2) update license_file_audit set rf_fk=new_rf_pk where rf_fk=old_rf_pk
 * 3) delete from license_ref where rf_pk=old_rf_pk
 *
 * \param int $old_rf_pk - the rf_pk of old license shortname
 * \param int $new_rf_pk - the rf_pk of new license shortname
 * 
 * \return int 0 on sucess 
 */
function update_license($old_rf_pk, $new_rf_pk)
{
  global $PG_CONN;

  $updateTables = array(
    "clearing_event",
    "license_file",
    "license_set_bulk",
    "upload_clearing_license"
  );

  /** transaction begin */
  $sql = "BEGIN;";
  $result_begin = pg_query($PG_CONN, $sql);
  DBCheckResult($result_begin, $sql, __FILE__, __LINE__);
  pg_free_result($result_begin);

  /* Update all relevant tables, substituting the old_rf_id with the new_rf_id */
  foreach ($updateTables as $table) {
    $sql = "update $table set rf_fk = $new_rf_pk where rf_fk = $old_rf_pk;";
    $result_license_file = pg_query($PG_CONN, $sql);
    DBCheckResult($result_license_file, $sql, __FILE__, __LINE__);
    pg_free_result($result_license_file);
  }

  /* Check if license_file_audit table exists */
  $sql = "select count(tablename) from pg_tables where tablename like 'license_file_audit';";
  $result_count_license_file_audit = pg_query($PG_CONN, $sql);
  DBCheckResult($result_count_license_file_audit, $sql, __FILE__, __LINE__);
  $row = pg_fetch_row($result_count_license_file_audit);
  if($row[0] > 0){
    /* Update license_file_audit table, substituting the old_rf_id  with the new_rf_id */
    $sql = "update license_file_audit set rf_fk = $new_rf_pk where rf_fk = $old_rf_pk;";
    $result_license_file_audit = pg_query($PG_CONN, $sql);
    DBCheckResult($result_license_file_audit, $sql, __FILE__, __LINE__);
    pg_free_result($result_license_file_audit);
  }

  /** delete data of old license */
  $sql = "DELETE FROM license_ref where rf_pk = $old_rf_pk;";
  $result_delete = pg_query($PG_CONN, $sql);
  DBCheckResult($result_delete, $sql, __FILE__, __LINE__);
  pg_free_result($result_delete);

  /** transaction end */
  $sql = "COMMIT;";
  $result_end = pg_query($PG_CONN, $sql);
  DBCheckResult($result_end, $sql, __FILE__, __LINE__);
  pg_free_result($result_end);

  return 0;
}

/**
 * \brief change license shortname
 *
 * \param string $old_shortname - old license shortname
 * \param string $new_shortname - new license shortname
 * 
 * \return int 0 on sucess
 */
function change_license_name($old_shortname, $new_shortname)
{
  global $PG_CONN;

  $sql = "update license_ref set rf_shortname = '$new_shortname' where rf_shortname = '$old_shortname';";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  return 0;
}
