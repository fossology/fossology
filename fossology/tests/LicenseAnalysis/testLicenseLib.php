<?php
/*
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
*/
/**
*
* testLicenseLib
*
* common routines for use by license regression tests
*
* created: May 29, 2009
* @version "$Id:  $"
*/

/**
 * filterFossologyResults
 * taken from the nomos script license_vetter.pl
 *
 * @param string $string a string a license results
 * @return string $adjusted the adjusted results string
 */
function filterFossologyResults($string) {

  $string = str_replace('+',' or later',$string);

  $string = str_replace('Apache Software License','Apache',$string);
  $string = str_replace('Artistic License','Artistic',$string);

  $string = str_replace('Adobe AFM','AFM',$string);

  #    $string = str_replace('Adobe Product License Agreement','',$string);

  $string = str_replace('Affero GPL','Affero',$string);

  $string = str_replace('ATI Software EULA','ATI Commercial',$string);

  $string = str_replace('GNU Free Documentation License','GFDL',$string);

  $string = str_replace('Common Public License','CPL',$string);

  $string = str_replace('Eclipse Public License','EPL',$string);

  $string = str_replace('Microsoft Reference License','MRL',$string);
  $string = str_replace('Reciprocal Public License','RPL',$string);

  $string = str_replace('gSOAP Public License','GSOAP',$string);

  $string = str_replace('Apple Public Source License','APSL',$string);
  $string = str_replace('LaTeX Project Public License','LPPL',$string);
  $string = str_replace('World Wide Web.*','W3C',$string);

  $string = str_replace('IBM Public License','IBM\-PL',$string);

  $string = str_replace('MySQL AB Exception','MySQL',$string);
  $string = str_replace('NASA Open Source','NASA',$string);

  $string = str_replace('Sun Microsystems Binary Code License','SBCLA',$string);
  $string = str_replace('Sun Community Source License TSA','SCSL\-TSA',$string);
  $string = str_replace('Sun Community Source License','SCSL',$string);
  $string = str_replace('Sun Microsystems Sun Public License','SPL',$string);

  $string = str_replace('Sun GlassFish Software License','SGF',$string);
  $string = str_replace('Sun Contributor Agreement','Sun\-SCA',$string);

  $string = str_replace('Carnegie Mellon University','CMU',$string);

  $string = str_replace('Eclipse Public License','EPL',$string);
  $string = str_replace('Open Software License','OSL',$string);
  $string = str_replace('Open Public License','OPL',$string);

  $string = str_replace('Beerware','BEER\-WARE',$string);

  //  commercial
  $string = str_replace('Nvidia License','Nvidia',$string);
  $string = str_replace('Agere LT Modem Driver License','Agere Commercial',$string);
  $string = str_replace('ATI Software EULA','ATA Commercial',$string);

  $string = str_replace('Python Software Foundation','Python',$string);

  $string = str_replace('RealNetworks Public Source License','RPSL',$string);
  $string = str_replace('RealNetworks Community Source Licensing','RCSL',$string);

  $string = str_replace('Creative Commons Public Domain','Public Domain',$string);

  return($string);
} // filterFossologyResults

/**
 * filterNomosResults
 * taken from the nomos script license_vetter.pl
 *
 * @param string $resultString a string a license results, comma separated
 * @return string $resultString the modified input string.
 */
function filterNomosResults($resultString) {
  /*
   * this is taken from license_vetter.pl from the OSRB (Paul Whyman).
   */

  $resultString = str_replace('+',' or later',$resultString);

  $resultString = str_replace('Adobe-AFM','AFM',$resultString);
  $resultString = str_replace('Adobe$','Adobe Commercial',$resultString);

  $resultString = str_replace('Aptana-PL','AptanaPL',$resultString);

  $resultString = str_replace('ATT-Source','ATTSCA',$resultString);

  $resultString = str_replace('AVM','AVM Commercial',$resultString);

  $resultString = str_replace('CC-LGPL','Creative Commons LGPL',$resultString);

  $resultString = str_replace('CC-GPL','Creative Commons GPL',$resultString);

  $resultString = str_replace('GPL-exception','GPL Exception',$resultString);

  $resultString = str_replace('Microsoft-PL','Ms-PL',$resultString);
  $resultString = str_replace('Microsoft-RL','Ms-RL',$resultString);
  $resultString = str_replace('Microsoft-limited-PL','Ms-LPL',$resultString);
  $resultString = str_replace('Microsoft-LRL','Ms-LRL',$resultString);
  $resultString = str_replace('Microsoft-LPL','Ms-LPL',$resultString);
  $resultString = str_replace('Ms-EULA','Microsoft Commercial',$resultString);
  $resultString = str_replace('Ms-SSL','MSSL',$resultString);

  $resultString = str_replace('Public-domain-claim','Public Domain',$resultString);
  $resultString = str_replace('RSA-Security','RSA Commercial',$resultString);
  $resultString = str_replace('Eclipse','EPL',$resultString);
  $resultString = str_replace('Open-PL','OPL',$resultString);
  $resultString = str_replace('Lucent','LPL',$resultString);

  $resultString = str_replace('Genivia','Genivia Commercial',$resultString);

  $resultString = str_replace('CDDL/OpenSolaris','CDDL',$resultString);
  $resultString = str_replace('Sun SCA','Sun-SCA',$resultString);
  $resultString = str_replace('Sun-PL','SPL',$resultString);
  $resultString = str_replace('Sun-BCLA','SBCLA',$resultString);
  $resultString = str_replace('Sun-EULA','Sun Commercial',$resultString);

  $resultString = str_replace('LaTeX-PL','LPPL',$resultString);

  $resultString = str_replace('zlib/libpng','zlib',$resultString);

  $resultString = str_replace('Beerware','BEER-WARE',$resultString);

  $resultString = str_replace('.*Non\-commercial.*','Non-Commercial Only',$resultString);
  $resultString = str_replace('Authorship-inference','Author',$resultString);

  $resultString = str_replace('RealNetworks-RPSL','RPSL',$resultString);
  $resultString = str_replace('RealNetworks-RCSL','RCSL',$resultString);

  $resultString = str_replace('UCWare','UCWare Commercial',$resultString);

  return($resultString);
} //fileterNomosResults
?>