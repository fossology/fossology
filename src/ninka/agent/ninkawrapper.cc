/*
 SPDX-FileCopyrightText: © 2014-2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

#include <iostream>
#include <boost/tokenizer.hpp>
#include "ninkawrapper.hpp"
#include "utils.hpp"

string scanFileWithNinka(const State& state, const fo::File& file)
{
  FILE* in;
  char buffer[512];
  string command = "ninka " + file.getFileName();
  string result;

  if (!(in = popen(command.c_str(), "r")))
  {
    cout << "could not execute ninka command: " << command << endl;
    bail(1);
  }

  while (fgets(buffer, sizeof(buffer), in) != NULL)
  {
    result += buffer;
  }

  if (pclose(in) != 0)
  {
    cout << "could not execute ninka command: " << command << endl;
    bail(1);
  }

  return result;
}

vector<string> extractLicensesFromNinkaResult(string ninkaResult)
{
  string licensePart = extractLicensePartFromNinkaResult(ninkaResult);
  return splitLicensePart(licensePart);
}

// Ninka result format: filename;license1,license2,...,licenseN;details...
string extractLicensePartFromNinkaResult(string ninkaResult)
{
  string delimiters = ";\r\n";

  size_t first = ninkaResult.find_first_of(delimiters);
  size_t last = ninkaResult.find_first_of(delimiters, first + 1);

  return ninkaResult.substr(first + 1, last - 1 - first);
}

vector<string> splitLicensePart(string licensePart)
{
  typedef boost::tokenizer<boost::char_separator<char>> tokenizer;
  boost::char_separator<char> separator(",");
  tokenizer tokens(licensePart, separator);

  vector<string> licenses;

  for (tokenizer::iterator iter = tokens.begin(); iter != tokens.end(); ++iter)
  {
    licenses.push_back(*iter);
  }

  return licenses;
}

vector<LicenseMatch> createMatches(vector<string> ninkaLicenseNames)
{
  vector<LicenseMatch> matches;
  for (vector<string>::const_iterator it = ninkaLicenseNames.begin(); it != ninkaLicenseNames.end(); ++it)
  {
    const string& ninkaLicenseName = *it;
    if(isLicenseCollection(ninkaLicenseName,matches))
    {
      continue;
    }    
    string fossologyLicenseName = mapLicenseFromNinkaToFossology(ninkaLicenseName);
    unsigned percentage = (ninkaLicenseName.compare("NONE") == 0 || ninkaLicenseName.compare("UNKNOWN") == 0) ? 0 : 100;
    LicenseMatch match = LicenseMatch(fossologyLicenseName, percentage);
    matches.push_back(match);
  }
  return matches;
}

string mapLicenseFromNinkaToFossology(string name)
{
  if (name.compare("NONE") == 0) return string("No_license_found");
  if (name.compare("UNKNOWN") == 0) return string("UnclassifiedLicense");
  if (name.compare("spdxMIT") ==0 ) return string("MIT");
  if (name.compare("Apachev1.0") == 0) return string("Apache-1.0");
  if (name.compare("Apachev2") == 0
          || name.compare("Apache-2") == 0) return string("Apache-2.0");
  if (name.compare("GPLv1+") == 0) return string("GPL-1.0+");
  if (name.compare("GPLv2") == 0) return string("GPL-2.0");
  if (name.compare("GPLv2+") == 0) return string("GPL-2.0+");
  if (name.compare("GPLv3") == 0) return string("GPL-3.0");
  if (name.compare("GPLv3+") == 0) return string("GPL-3.0+");
  if (name.compare("LGPLv2") == 0) return string("LGPL-2.0");
  if (name.compare("LGPLv2+") == 0) return string("LGPL-2.0+");
  if (name.compare("LGPLv2_1") == 0
          || name.compare("LGPLv2.1") == 0) return string("LGPL-2.1");
  if (name.compare("LGPLv2_1+") == 0) return string("LGPL-2.1+");
  if (name.compare("LGPLv3") == 0) return string("LGPL-3.0");
  if (name.compare("LGPLv3+") == 0) return string("LGPL-3.0+");
  if (name.compare("GPLnoVersion") == 0) return string("GPL");
  if (name.compare("LesserGPLnoVersion") == 0
          || name.compare("LibraryGPLnoVersion")==0) return string("LGPL");
  if (name.compare("intelBSDLicense") == 0) return string("Intel-EULA");
  if (name.compare("spdxSleepyCat") == 0
          || name.compare("SleepyCat") == 0) return string("Sleepycat");
  if (name.compare("spdxBSD2") == 0
          || name.compare("BSD2") == 0) return string("BSD-2-Clause");
  if (name.compare("spdxBSD3") == 0
          || name.compare("BSD3") == 0) return string("BSD-3-Clause");
  if (name.compare("BSD3") == 0) return string("BSD-4-Clause");
  if (name.compare("spdxMIT") == 0) return string("MIT");
  if (name.compare("ZLIB") == 0) return string("Zlib");
  if (name.compare("openSSL") == 0
          || name.compare("openSSLvar1") ==0
          || name.compare("openSSLvar3") ==0) return string("OpenSSL");
  if (name.compare("QPLt") == 0) return string("QT(Commercial)");
  if (name.compare("Cecill") == 0) return string("CECILL");
  if (name.compare("QPLv1") == 0) return string("QPL-1.0");
  if (name.compare("MPLv1_1") == 0) return string("MPL-1.1");
  if (name.compare("NPLv1_1") == 0) return string("NPL-1.1");
  if (name.compare("MPLv1_0") == 0) return string("MPL-1.0");
  if (name.compare("NPLv1_0") == 0) return string("NPL-1.0");
  if (name.compare("MPLv2") == 0) return string("MPL-2.0");
  if (name.compare("MITVariant") == 0) return string("MIT-style");
  if (name.compare("EPLv1") == 0) return string("EPL-1.0");
  if (name.compare("CDDLic") == 0) return string("CDDL");
  if (name.compare("CDDLicV1") == 0) return string("CDDL-1.0");
  if (name.compare("publicDomain") == 0) return string("Public-domain");
  if (name.compare("ClassPathExceptionGPLv2") == 0) return string("GPL-2.0-with-classpath-exception");
  if (name.compare("CPLv1") == 0) return string("CPL-1.0");
  if (name.compare("CPLv0.5") == 0) return string("CPL-0.5");
  if (name.compare("SeeFile") == 0) return string("See-file");
  if (name.compare("LibGCJLic") == 0) return string("LIBGCJ");
  if (name.compare("W3CLic") == 0) return string("W3C");
  if (name.compare("IBMv1") == 0) return string("IPL-1.0");
  if (name.compare("ArtisticLicensev1") == 0) return string("Artistic-1.0");
  if (name.compare("MX4JLicensev1") == 0) return string("MX4J-1.0");
  if (name.compare("phpLicV3.01") == 0) return string("PHP-3.01");
  if (name.compare("postgresql") == 0 
          || name.compare("postgresqlRef") == 0) return string("PostgreSQL");
  if (name.compare("FSFUnlimited") == 0) return string("FSF");
  
  return name;
};

bool isLicenseCollection(string name, vector<LicenseMatch>& matches)
{
  if (name.compare("spdxBSD4") == 0)
  {
    matches.push_back(LicenseMatch(string("BSD-4-Clause"), 50));
    matches.push_back(LicenseMatch(string("BSD-4-Clause-UC"), 50));
    return true;
  }
  if (name.compare("GPL2orBSD3") == 0)
  {
    matches.push_back(LicenseMatch(string("BSD-3-Clause"), 50));
    matches.push_back(LicenseMatch(string("GPL-2.0"), 50));
    return true;
  }
  if (name.compare("LGPLv2orv3") == 0)
  {
    matches.push_back(LicenseMatch(string("LGPL-2.0"), 50));
    matches.push_back(LicenseMatch(string("LGPL-3.0"), 50));
    return true;
  }
  if (name.compare("LGPLv2_1orv3") == 0)
  {
    matches.push_back(LicenseMatch(string("LGPL-2.1"), 50));
    matches.push_back(LicenseMatch(string("LGPL-3.0"), 50));
    return true;
  }
  if (name.compare("LGPLv2+MISTAKE") == 0)
  {
    matches.push_back(LicenseMatch(string("LGPL-2.1+"), 50));
    matches.push_back(LicenseMatch(string("LGPL-2.0+"), 50));
    return true;
  }
  if (name.compare("LGPLv2MISTAKE") == 0)
  {
    matches.push_back(LicenseMatch(string("LGPL-2.1"), 50));
    matches.push_back(LicenseMatch(string("LGPL-2.0"), 50));
    return true;
  }
  if (name.compare("GPLv1orArtistic") == 0)
  {
    matches.push_back(LicenseMatch(string("GPL-1.0"), 50));
    matches.push_back(LicenseMatch(string("Artistic-1.0"), 25));
    matches.push_back(LicenseMatch(string("Artistic-2.0"), 25));
    return true;
  }
  if (name.compare("GPL2orOpenIB") == 0)
  {
    matches.push_back(LicenseMatch(string("GPL-2.0"), 50));
    matches.push_back(LicenseMatch(string("BSD-2-Clause"), 50));
    return true;
  }
  if (name.compare("CDDLv1orGPLv2") == 0)
  {
    matches.push_back(LicenseMatch(string("CDDL-1.0"), 50));
    matches.push_back(LicenseMatch(string("GPL-2.0"), 50));
    return true;
  }
  if (name.compare("Apache-2orLGPLgeneric") == 0)
  {
    matches.push_back(LicenseMatch(string("Apache-2.0"), 50));
    matches.push_back(LicenseMatch(string("LGPL"), 50));
    return true;
  }
  if (name.compare("orLGPLVer2.1") == 0)
  {
    matches.push_back(LicenseMatch(string("QT(Commercial)"), 50));
    matches.push_back(LicenseMatch(string("LGPL-2.1"), 50));
    return true;
  }
  if (name.compare("orLGPLVer2") == 0)
  {
    matches.push_back(LicenseMatch(string("QT(Commercial)"), 50));
    matches.push_back(LicenseMatch(string("LGPL-2.0"), 50));
    return true;
  }
  if (name.compare("orGPLv3") == 0)
  {
    matches.push_back(LicenseMatch(string("QT(Commercial)"), 50));
    matches.push_back(LicenseMatch(string("GPL-3.0"), 50));
    return true;
  }
  if (name.compare("CDDLv1orGPLv2") == 0)
  {
    matches.push_back(LicenseMatch(string("CDDL-1.0"), 50));
    matches.push_back(LicenseMatch(string("GPL-2.0"), 50));
    return true;
  }
  if (name.compare("CDDLorGPLv2") == 0)
  {
    matches.push_back(LicenseMatch(string("CDDL"), 50));
    matches.push_back(LicenseMatch(string("GPL-2.0"), 50));
    return true;
  }
  if (name.compare("MPLGPL2orLGPLv2_1") == 0)
  {
    matches.push_back(LicenseMatch(string("MPL-1.0"), 32));
    matches.push_back(LicenseMatch(string("GPL-2.0"), 34));
    matches.push_back(LicenseMatch(string("LGPL-2.1"), 34));
    return true;
  }
  if (name.compare("MPL1_1andLGPLv2_1") == 0)
  {
    matches.push_back(LicenseMatch(string("MPL-1.1"), 99));
    matches.push_back(LicenseMatch(string("GPL-2.1"), 99));
    return true;
  }
  if (name.compare("MPL_LGPLsee") == 0)
  {
    matches.push_back(LicenseMatch(string("MPL-1.0"), 50));
    matches.push_back(LicenseMatch(string("LGPL"), 40));
    return true;
  }
  if (name.compare("MITX11BSDvar") == 0)
  {
    matches.push_back(LicenseMatch(string("MIT"), 33));
    matches.push_back(LicenseMatch(string("X11"), 33));
    matches.push_back(LicenseMatch(string("BSD-style"), 34));
    return true;
  }
  if (name.compare("MITCMU") == 0 || name.compare("MITCMUvar2") == 0 || name.compare("MITCMUvar3") == 0)
  {
    matches.push_back(LicenseMatch(string("MIT"), 50));
    matches.push_back(LicenseMatch(string("CMU"), 50));
    return true;
  }
  if (name.compare("MITX11") == 0 || name.compare("MITX11noNotice") == 0 || name.compare("MITX11simple") == 0)
  {
    matches.push_back(LicenseMatch(string("MIT"), 50));
    matches.push_back(LicenseMatch(string("X11"), 50));
    return true;
  }
  if (name.compare("MITandGPL") == 0)
  {
    matches.push_back(LicenseMatch(string("MIT"), 99));
    matches.push_back(LicenseMatch(string("GPL"), 99));
    return true;
  }
  if (name.compare("BisonException") == 0)
  {
    matches.push_back(LicenseMatch(string("GPL-2.0-with-bison-exception"), 50));
    matches.push_back(LicenseMatch(string("GPL-3.0-with-bison-exception"), 50));
    return true;
  }
  if (name.compare("ClassPathException") == 0)
  {
    matches.push_back(LicenseMatch(string("GPL-2.0-with-classpath-exception"), 30));
    matches.push_back(LicenseMatch(string("GPL-2.0+-with-classpath-exception"), 30));
    matches.push_back(LicenseMatch(string("GPL-3.0-with-classpath-exception"), 40));
    return true;
  }
  if (name.compare("autoConfException") == 0)
  {
    matches.push_back(LicenseMatch(string("GPL-2.0-with-autoconf-exception"), 50));
    matches.push_back(LicenseMatch(string("GPL-3.0-with-autoconf-exception"), 50));
    return true;
  }
  if (name.compare("CPLv1orGPLv2+orLGPLv2+") == 0)
  {
    matches.push_back(LicenseMatch(string("CPL-1.0"), 34));
    matches.push_back(LicenseMatch(string("GPL-2.0+"), 33));
    matches.push_back(LicenseMatch(string("LGPL-2.0+"), 33));
    return true;
  }
  if (name.compare("GPLVer2or3KDE+") == 0)
  {
    matches.push_back(LicenseMatch(string("GPL-2.0+-KDE-exception"), 50));
    matches.push_back(LicenseMatch(string("GPL-3.0+-KDE-exception"), 50));
    return true;
  }
  if (name.compare("LGPLVer2.1or3KDE+") == 0)
  {
    matches.push_back(LicenseMatch(string("LGPL-2.1+-KDE-exception"), 50));
    matches.push_back(LicenseMatch(string("LGPL-3.0+-KDE-exception"), 50));
    return true;
  }
  if (name.compare("GPLv2orLGPLv2.1") == 0)
  {
    matches.push_back(LicenseMatch(string("GPL-2.0"), 50));
    matches.push_back(LicenseMatch(string("LGPL-2.1"), 50));
    return true;
  }
  if (name.compare("GPLv2+orLGPLv2.1") == 0)
  {
    matches.push_back(LicenseMatch(string("GPL-2.0+"), 50));
    matches.push_back(LicenseMatch(string("LGPL-2.1"), 50));
    return true;
  }
  
  return false;
}

/*
 * unmatched in lib/Ninka/rules.dict:
 * 
BSD2AdvInsteadOfBinary:BSDpre,BSDcondSource,BSDcondAdvRULE,BSDasIs,BSDWarr
BSD1:BSDpre,BSDcondBinary,BSDasIs,BSDWarr
BSDOnlyAdv:BSDpre,BSDcondAdvRULE,BSDasIs,BSDWarr
BSDOnlyEndorseNoWarranty:BSDpreLike,BSDcondEndorseRULE,BSDasIs
BSD2var1:BSDpre,BSDCondSourceVariant,BSDcondBinary,BSDasIs,BSDWarr
BSD2var2:BSDpre,BSDCondSourceVariant2,BSDcondBinary,BSDasIs,BSDWarr
BSD2aic700:BSDpre,BSDcondSource,BSDcondBinaryVar1,AsIsVariant2,LiabilityBSDVariantAIC700
BSD2SoftAndDoc:BSDpreSoftAndDoc,BSDcondSourceOrDoc,BSDcondBinary,BSDasIsSoftAndDoc,BSDWarr
BSDCairoStyleWarr:BSDpre,BSDcondSource,BSDcondBinary,BSDcondAdvPart2,OpenSSLwritCond,OpenSSLName,BSDasIs,BSDWarr
BSDdovecotStyle:BSDpre,BSDcondSource,BSDcondBinary,OpenSSLendorse,DovecotwriteCod,OpenSSLAckPart1,BSDcondAdvPart2,MITstyleCairoWarranty
ZLIBref:ZLibRef
boost-1:boostPermission,boostPreserve,boostAsIs,boostWarr
boost-1:boostRefv1
boost-1ref:boostSeev1
SSLeay:SSLCopy,SSLeayAttrib,SSLeayAdType,BSDpre,BSDcondSource,BSDcondBinary,BSDcondAdvRULE,SSLeayCrypto,SSLeayWindows,BSDasIs,BSDWarr,SSLeayCantChangeLic
SimpleOnlyKeepCopyright:SimpleOnlyKeepCopyright
MPL-MIT-dual:MPL-MIT-dual1,MPL-MIT-dual2
orGPLv2+orLGPLv2.1+:Altern,GPLv2orLGPLv2\.1Ver2\+,MPLoptionNOTGPLVer0,MPLoptionIfNotDelete3licsVer0
MIToldwithoutSell:MITperNoSell,MITnorep,MITasis
MIToldwithoutSellCMUVariant:MITpermNoSell,X11CMUAsIs,X11CMULiability,X11CMUredistribute
MIToldwithoutSell:MITpermNoSell,MITandGPLasis,MITandGPLwar
MIToldwithoutSellandNoDocumentationRequi:MITpermNoSellNoDoc,BSDasIs,BSDWarr
MIToldwithoutSellandNoDocumentationRequi:MITpermNoSellNoDoc,MITnorep,MITasis
MIToldMichiganVersion:MITpermNoSell,WarrantySupplied
X11mit:MITpermWithoutEndor,X11notice,X11asIs,X11asLiable,X11adv
X11Festival:X11FestivalPerm,X11FestivalNotice,X11FestivalNoEndorse,MITstyleCairoWarranty
MITmodern:MITmodermPerm,MITmodernLiable,MITmodermWarr,MitmodernAsIs
MITX11NoSellNoDocDocBSDvar:MITpermNoSellNoDoc,X11asIsLike,BSDWarr
BindMITX11Var:MITpermAndOr,X11asIsLike,BSDWarr
Exception:Exception
LinkException:LinkException
LinkExceptionBison:LinkExceptionBison
LinkExceptionGPL:LinkExceptionGPL
LinkExceptionLeGPL:LinkExceptionLeGPL
LinkExceptionOpenSSL:LinkExceptionOpenSSL
WxException:wxLinkExceptionPart1,wxLinkExceptionPart2,wxLinkExceptionPart3Ver0,wxLinkExceptionPart4,wxLinkExceptionPart5,wxLinkExceptionPart6
qtWindowsException1.3:qtExceptionNoticeVer1.3
qtExceptionWindows:qtExceptionWindows
digiaQTExceptionNoticeVer1.1:digiaQTExceptionNoticeVer1.1
BeerWareVer42:BeerWareVer42LicPart1,BeerWareVer42LicPart2,BeerWareVer42LicPart3,BeerWareVer42LicPart4
IntelACPILic:IntelPart02,IntelPart03,IntelPart04,IntelPart05,IntelPart06,IntelPart07,IntelPart08,IntelPart09,IntelPart10,IntelPart11,IntelPart12,IntelPart13,IntelPart14,IntelPart15,IntelPart16,IntelPart17,IntelPart18,IntelPart19,IntelPart20,IntelPart21,IntelPart22,IntelPart23,IntelPart24,IntelPart25,IntelPart26,IntelPart27,IntelPart28
simpleLicense1:simpleLic1part1
simpleLic2:simpleLic2
simpleLic:simpleLic
sunRPC:sunRPCLic1,sunRPCLic2,sunRCPnoWarranty,sunRCPnoSupport
SunSimpleLic:SunSimpleLic
emacsLic:EmacsLicense
SameTermsAs:SameTermsAs
GhostscriptGPL:GhostscriptGPL
SameAsPerl:SameAsPerl
QplGPLv2or3:qtGPLv2or3
FreeType:FreeType
Postfix:Postfix
subversion+:subversion,subversionPlus
subversion:subversion
svnkit+:svnkitPlus
svnkit:svnkit
sequenceLic:sequenceLic
tmate+:tmatePlus
subversionError:subversionError
artifex:artifex
SimpleLic:SimpleLic
dovecotSeeCopying:dovecotSeeCopying
zendv2:zendv2
kerberos:exportLicRequired,exportNoLia,exportMITper,exportMITmodify,MITnorep,MITasis
GPL-2or3,AlternTrolltechKDE-approved:GPLv2orv3Ver0,Altern,laterTrolltechKDE-approvedVer0
 *  */