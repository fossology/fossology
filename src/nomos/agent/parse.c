/***************************************************************
 Copyright (C) 2006-2013 Hewlett-Packard Development Company, L.P.

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

 ***************************************************************/
/* Equivalent to version 1.83 of Core Nomos code. */
#include <ctype.h>

#include "nomos.h"

#include "parse.h"
#include "list.h"
#include "util.h"
#include "nomos_regex.h"
#include "_autodefs.h"

/* DEBUG  
#define DOCTOR_DEBUG 1
#define PROC_TRACE 1
   DEBUG */

/**
 * \file parse.c
 * \brief searches for licenses
 *
 * The main workhorse of nomos. This file contains most of the logic for finding
 * licenses in nomos.
 */

#define INVISIBLE       (int) '\377'

/**
 * \name license definitions
 * Instead of keeping a potentially-growing list of variables used to
 * recall specific flags/text, etc., manage it in an array.  A little
 * slower, sure, but it keeps the number of variables we allocate to
 * a more-reasonable minimum.
 */
//@{
#define _mGPL            0
#define _mLGPL           1
#define _mGFDL           2
#define _mQPL            3
#define _mPYTHON         4
#define _mPYTH_TEXT      5
#define _mAPACHE         6
#define _mHP             7
#define _mPHP            8
#define _mMIT            9
#define _mXOPEN         10
#define _mREDHAT        11
#define _mISC           12
#define _mCMU           13
#define _mOSF           14
#define _mSUN           15
#define _mALADDIN       16
#define _mCUPS          17
#define _fOPENLDAP      18
#define _fBSD           19
#define _fGPL           20
#define _mCDDL          21
#define _mLIBRE         22
#define _mGSOAP         23
#define _mMPL           24
#define _fATTRIB        25
#define _fREAL          26
#define _fIETF          27
#define _fDOC           28
#define _fMSCORP        29
#define _fW3C           30
#define _mAPTANA        31
#define _tOPENLDAP      32
#define _mNTP			33 // To avoid W3C-style detection
#define _fIP            34
#define _fANTLR         35
#define _fCCBY          36
#define _fZPL           37
#define _fCLA           38
#define _fODBL          39
#define _fPDDL          40
#define _fRUBY          41
#define _fSAX           42
#define _fAPL           43
#define _fARTISTIC      44
#define _msize          _fARTISTIC+1
//@}

static struct {
  char *base;
  int sso;
  int seo;
  int index;
} kludge;


#ifdef  PRECHECK
extern void preloadResults(char *filetext, char *ltsr);
#endif  /* PRECHECK */

/**
 * \name local static functions
 * Local (static) Functions
 */
//@{
int findPhrase(int, char *,int, int, int, int);
int famOPENLDAP(char *, int ,int, int);
int checkUnclassified(char *, int, int, int, int, int);
int checkPublicDomain(char *, int, int, int, int, int);
static int dbgIdxGrep(int, char *, int);
#ifdef  LTSR_DEBUG
void showLTCache(char *);
#endif  /* LTSR_DEBUG */
void checkCornerCases(char *, int, int, int, int, int, int, int);
void checkFileReferences(char *, int, int, int, int, int);
void  addRef(char *, int);
#ifdef DOCTOR_DEBUG
void dumpMatch(char *, char *);
#endif /* DOCTOR_DEBUG */
void locateRegex(char *, item_t *, int, int, int, int);
void saveRegexLocation(int, int, int, int);
void saveUnclBufLocation(int);
void saveLicenseParagraph(char *, int , int , int);
char *cplVersion(char *, int, int, int);
static char *gplVersion(char *, int, int, int);
char *lgplVersion(char *, int, int, int);
char *agplVersion(char *, int, int, int);
char *gfdlVersion(char *, int, int, int);
char *lpplVersion(char *, int, int, int);
char *mplNplVersion(char *, int, int, int);
char *pythonVersion(char *, int, int, int);
static char *realVersion(char *, int, int, int, int);
static char *sisslVersion(char *, int, int, int);
char *aslVersion(char *, int, int, int);
char *cddlVersion(char *, int, int, int);
char *ccVersion(char *, int, int, int);
char *ccsaVersion(char *, int, int, int);
char *oslVersion(char *, int, int, int);
char *aflVersion(char *, int, int, int);
static int match3(int, char *, int, int, int, int);
//@}

/**
 * \name local variables
 * File local variables
 */
//@{
static char licStr[myBUFSIZ];

static char ltsr[NFOOTPRINTS]; /* License Text Search Results,
           a bytemask for each possible match string */
static char name[256];
static char lmem[_msize];
static list_t searchList;
static list_t whereList;
static list_t whCacheList;
static int refOffset;
static int maxInterest;
static int pd;     /* Flag for whether we've checked for a
          public domain "license" */
static int crCheck;
static int checknw;
static int lDebug = 0;  /* set this to non-zero for more debugging */
static int lDiags = 0;  /* set this to non-zero for printing diagnostics */
//@}

/**
 * \name micro function definitions
 * These #define's save a LOT of typing and indention... :)
 */
//@{
#define PARSE_ARGS      filetext, size, isML, isPS
#define LVAL(x)         (ltsr[x] & LTSR_RMASK)
#define SEEN(x)         (ltsr[x] & LTSR_SMASK)
#define INFILE(x)       fileHasPatt(x, PARSE_ARGS, 0)
#define RM_INFILE(x)    fileHasPatt(x, PARSE_ARGS, 1)
#define GPL_INFILE(x)   fileHasPatt(x, PARSE_ARGS, 2)
#define PERL_INFILE(x)  fileHasPatt(x, PARSE_ARGS, 3)
#define NY_INFILE(x)    fileHasPatt(x, PARSE_ARGS, 4)
#define X_INFILE(x, y)  fileHasPatt(x, PARSE_ARGS, y)
#define DEBUG_INFILE(x) printf(" Regex[%d] = \"%s\"\nINFILE(%d) = %d\n", x, _REGEX(x), x, INFILE(x));
#define HASREGEX(x, cp) idxGrep(x, cp, REG_ICASE|REG_EXTENDED)
#define HASTEXT(x, fl)  idxGrep(x, filetext, REG_ICASE|fl)
#define URL_INFILE(x)   (INFILE(x) || fileHasPatt(x, PARSE_ARGS, -1))
#define CANSKIP(i,x,y,z)        ((i >= y) && (i <= z) && !(kwbm & (1 << (x - _KW_first))))
#define HASKW(x, y)     (x & (1 << (y - _KW_first)))
#define TRYGROUP(x)     x(PARSE_ARGS)
#define LOWINTEREST(x)  addRef(x, IL_LOW)
#define MEDINTEREST(x)  addRef(x, IL_MED)
//#define INTERESTING(x)  printf("INTERESTING: %s, %d, %s\n", __FILE__, __LINE__, x);addRef(x, IL_HIGH)
#define INTERESTING(x)  addRef(x, IL_HIGH)
#define ASLVERS()       aslVersion(PARSE_ARGS)
#define CCVERS()        ccVersion(PARSE_ARGS)
#define CCSAVERS()      ccsaVersion(PARSE_ARGS)
#define AFLVERS()       aflVersion(PARSE_ARGS)
#define OSLVERS()       oslVersion(PARSE_ARGS)
#define CPLVERS()       cplVersion(PARSE_ARGS)
#define GPLVERS()       gplVersion(PARSE_ARGS)
#define LGPLVERS()      lgplVersion(PARSE_ARGS)
#define AGPLVERS()      agplVersion(PARSE_ARGS)
#define GFDLVERS()      gfdlVersion(PARSE_ARGS)
#define CDDLVERS()      cddlVersion(PARSE_ARGS)
#define LPPLVERS()      lpplVersion(PARSE_ARGS)
#define MPLVERS()       mplNplVersion(PARSE_ARGS)
#define PYTHVERS()      pythonVersion(PARSE_ARGS)
#define SISSLVERS()     sisslVersion(PARSE_ARGS)
#define REALVERS(x)     realVersion(PARSE_ARGS, x)
#define PR_REGEX(x)     printf("check %d = %s\n", x, _REGEX(x));
#define mCR_CMU()       (INFILE(_CR_CMU_1) || INFILE(_CR_CMU_2))
#define mCR_EDIN()      (INFILE(_CR_EDINBURGH_1) || INFILE(_CR_EDINBURGH_2))
#define mCR_FSF()       (INFILE(_CR_FSF1) || INFILE(_CR_FSF2))
#define mCR_HP()        (INFILE(_CR_HP_1)|| INFILE(_CR_HP_2) || INFILE(_CR_DEC) || INFILE(_CR_EDS))
#define mCR_IETF()      (INFILE(_CR_IETF_1) || INFILE(_CR_IETF_2))
#define mCR_MIT()       (INFILE(_CR_MIT1) || INFILE(_CR_MIT2))
#define mCR_X11()       (INFILE(_CR_X11) || INFILE(_CR_XFREE86))
#define mCR_IPTC()      (INFILE(_CR_IPTC1) || INFILE(_CR_IPTC2))
//@}

static int fileHasPatt(int licTextIdx, char *filetext, int size,
    int isML, int isPS, int qType)
{
  int ret;
  int show = 0;
  item_t *ip;

#ifdef PROC_TRACE
  traceFunc("== fileHasPatt(size=%d, isML=%d, isPS=%d, qType=%d, idx=%d)\n",
      size, isML, isPS, qType, licTextIdx);

#endif  /* PROC_TRACE */

  /*
   * If qType is negative, then we should call idxGrep to look at the
   * raw text of the file; non-negative value means look in the doctored
   * text buffers...
   */
  if ((qType >= 0) && (qType & FL_SHOWMATCH)) {
    qType &= ~FL_SHOWMATCH;
    show = FL_SHOWMATCH;
  }
  if (qType < 0) {
    ret = idxGrep(licTextIdx, filetext, REG_ICASE|REG_EXTENDED|show);
    if (lDiags && ret) {
#ifdef  DOCTOR_DEBUG
      dumpMatch(filetext, "RAW-Text");
#endif  /* DEBUG */
      printRegexMatch(licTextIdx, NO);
      saveRegexLocation(licTextIdx, cur.regm.rm_so,
          cur.regm.rm_eo - cur.regm.rm_so, YES);
#ifdef  DEBUG
      printf("WINDOW-RAW: offset %d, length %d\n",
          cur.regm.rm_so, cur.regm.rm_eo - cur.regm.rm_so);
#endif  /* DEBUG */
    }
    return(ret);
  }
  if (SEEN(licTextIdx)) {
#ifdef  LTSR_DEBUG
    printf("Cache hit: ltsr[%d] = 0x%x\n", licTextIdx, ltsr[licTextIdx]);
#endif  /* LTSR_DEBUG */
    if (lDiags && (ltsr[licTextIdx] & LTSR_YES) == LTSR_YES) {
      printRegexMatch(licTextIdx, YES);
      (void) sprintf(name, "reg%04d", licTextIdx);
      ip = listGetItem(&whCacheList, name);
      if (ip->bIndex != licTextIdx) {
        listDump(&whCacheList, NO);
        LOG_FATAL("Offset-cache (\"%s\") == %d, not %d!", name, ip->bIndex, licTextIdx)
        Bail(-__LINE__);
      }
      saveRegexLocation(licTextIdx, ip->bStart, ip->bLen, NO);
    }
    return(ltsr[licTextIdx] & LTSR_RMASK);
  }
  return(findPhrase(licTextIdx, PARSE_ARGS, qType));
}


static int dbgIdxGrep(int licTextIdx, char *buf, int show)
{
  int ret;
  int flags = REG_ICASE|REG_EXTENDED;

  if (SEEN(licTextIdx)) {
    return(ltsr[licTextIdx] & LTSR_RMASK);
  }

  if (show) {
    flags |= FL_SHOWMATCH;
  }
  ret = idxGrep(licTextIdx, buf, flags);
  if (lDiags && ret) {
    printRegexMatch(licTextIdx, NO);
    saveRegexLocation(licTextIdx, cur.regm.rm_so,
        cur.regm.rm_eo - cur.regm.rm_so, YES);
  }
  ltsr[licTextIdx] |= ret;
  return ret;
}


char *parseLicenses(char *filetext, int size, scanres_t *scp,
    int isML, int isPS)
{
  static int first = 1;
  char *cp;
  int i;
  int j;
  int nw = 0;
  int rs = 0;
  int score = scp->score;
  int kwbm = scp->kwbm;
#ifdef  PRECHECK
  extern void preloadResults(char *, char *);
#endif  /* PRECHECK */

#if     defined(PROC_TRACE) || defined(DOCTOR_DEBUG)
  traceFunc("== parseLicenses(%p, %d, [%d, 0x%x], %d, %d)\n",
      filetext, size, score, kwbm, isML, isPS );
#endif  /* PROC_TRACE || DOCTOR_DEBUG */

  if (size == 0) {
    LOWINTEREST("Empty-file-no-data!");
    return(licStr+1);
  }

  if (first) {
    if (optionIsSet(OPTS_DEBUG)) {
      lDebug = 1;
      lDiags = 1;
    }
    listInit(&searchList, 0, "pattern-search list");
    listInit(&whereList, 0, "regex-match locations list");
    listInit(&whCacheList, 0, "regex-cache-match list");
    first = 0;
  }
  crCheck = 0;
  kludge.base = NULL_STR;
  /*
   * Interestingly enough, the headers for Nomos-generated file (e.g., the
   * page containing the keywords found, file attributes and file text, etc.)
   * contain enough data to confuse the parser in multiple ways...  in the
   * rare event we encounter a data file we generated, skip the header.
   *****
   * AND, not all nomos-created files have the same header(s).
   */
  pd = -1;        /* unchecked */
  cp = filetext;
  maxInterest = IL_INIT;
  cur.licPara = NULL_STR;  /* unclassified license data */
  gl.flags &= ~FL_FRAGMENT;
#ifdef FLAG_NO_COPYRIGHT
  gl.flags &= ~FL_NOCOPYRIGHT;
#endif /* FLAG_NO_COPYRIGHT */
  if (scp->dataOffset && lDiags) {
    LOG_NOTICE("%s-generated link, ignore header (%d bytes)!",
        gl.progName, scp->dataOffset);
  }

  /*
   * It's been observed over time that the file-magic() stuff doesn't always
   * identify everything correctly.  One case in particular is PostScript files
   * when the "%%PS" directive isn't the first line in a file... but the rest
   * of the data really IS PostScript
   */
  if (!isPS && strncasecmp(filetext, "%%page:", 7) == 0) {
#if defined(DEBUG) || defined(DOCTOR_DEBUG)
    printf("File is really postscript!\n");
#endif  /* DEBUG || DOCTOR_DEBUG */
    isPS = 1;
  }

  *licStr = NULL_CHAR;
  refOffset = 0;
  (void) memset(ltsr, 0, sizeof(ltsr));
  (void) memset(lmem, 0, sizeof(lmem));
#if defined(DEBUG) && defined(LTSR_DEBUG)
  showLTCache("LTSR-results START:");
#endif  /* DEBUG && LTSR_DEBUG */
#ifdef  PRECHECK
  preloadResults(/*PARSE_ARGS*/filetext, ltsr);
#endif  /* PRECHECK */
#ifdef  MEMSTATS
  memStats("parseLicenses: BOP");
#endif  /* MEMSTATS */
  lmem[_mPYTH_TEXT] = HASTEXT(_TEXT_PYTHON, 0);
  lmem[_tOPENLDAP] = HASTEXT(_TEXT_OPENLDAP, 0);
  (void) INFILE(_TEXT_GNU_LIC_INFO);
#ifdef  LTSR_DEBUG
  showLTCache("LTSR-results INIT-SCAN:");
#endif  /* LTSR_DEBUG */
  /*
   * MySQL/FLOSS exception
   */
  if (INFILE(_LT_MYSQL_EXCEPT) || INFILE(_PHR_FREE_LIBRE)) {
    if (INFILE(_TITLE_ALFRESCO)) {
      INTERESTING("Alfresco/FLOSS");
    }
    else if (HASTEXT(_TEXT_ALFRESCO, 0)) {
      INTERESTING("Alfresco");
    }
    else if (INFILE(_CR_MYSQL) || INFILE(_TITLE_MYSQL_EXCEPT)) {
      if (INFILE(_TITLE_MYSQL_V03)) {
        INTERESTING("MySQL-0.3");
      }
      else {
        INTERESTING("MySQL/FLOSS");
      }
    }
    else {
      INTERESTING("MySQL-style");
    }
    lmem[_mLIBRE] = 1;
  }
  /*
   * Some RealNetworks licenses included a list of "compatible" licenses that
   * can confuse the license-detection algorithms within.  Look for these early
   * in the process, and ignore the known (false) matches when we detect the
   * RPSL/RCSL licenses.
   */
  if (HASTEXT(_TEXT_REALNET, REG_EXTENDED)) {
    if (INFILE(_LT_REAL_RPSL)) {
      cp = REALVERS(_TITLE_RPSL);
      INTERESTING(lDebug ? "RPSL" : cp);
      lmem[_mMPL] = 1;
      lmem[_fREAL] = 1;
    }
    else if (INFILE(_LT_REAL_RPSLref)) {
      cp = REALVERS(_TITLE_RPSL);
      INTERESTING(lDebug ? "Real-RPSL(ref)" : cp);
      lmem[_mMPL] = 1;
      lmem[_fREAL] = 1;
    }
    if (INFILE(_LT_REAL_RCSL)) {
      cp = REALVERS(_TITLE_RCSL);
      INTERESTING(lDebug ? "RCSL" : cp);
      lmem[_mMPL] = 1;
      lmem[_fREAL] = 1;
    }
    else if (INFILE(_LT_REAL_RCSLref)) {
      cp = REALVERS(_TITLE_RCSL);
      INTERESTING(lDebug ? "Real-RCSL(ref)" : cp);
      lmem[_mMPL] = 1;
      lmem[_fREAL] = 1;
    }
    if (INFILE(_TITLE_REAL_EULA)) {
      INTERESTING("RealNetworks-EULA");
    }
    else if (INFILE(_LT_HELIX_TITLE)) {
      INTERESTING("Helix/RealNetworks-EULA");
    }
  }
  /*
   * Zope - this license is explicitly listed (by title) in several other
   * licenses...
   */
  if (!lmem[_mLIBRE] && !lmem[_fREAL] && INFILE(_TITLE_ZOPE)) {
    if (INFILE(_TITLE_ZOPE_V21)) {
      INTERESTING("ZPL-2.1");
      lmem[_fZPL] = 1;
    }
    else if (INFILE(_TITLE_ZOPE_V20)) {
      INTERESTING("ZPL-2.0");
      lmem[_fZPL] = 1;
    }
    else if (INFILE(_TITLE_ZOPE_V10)) {
      INTERESTING("ZPL-1.0");
      lmem[_fZPL] = 1;
    }
    else if (INFILE(_TITLE_ZOPE_V11)) {
      INTERESTING("ZPL-1.1");
      lmem[_fZPL] = 1;
    }
    else if (INFILE(_TITLE_ZIMBRA_13)) {
      INTERESTING("Zimbra-1.3");
    }
    else {
      INTERESTING(lDebug ? "Zope(ref)" : "ZPL");
      lmem[_fZPL] = 1;
    }
  }

  /*
   * Check Apache licenses before BSD
   */
  if (INFILE(_LT_ASL)) {
    cp = ASLVERS();
    INTERESTING(cp);
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_LT_ASLref3)) {
    cp = ASLVERS();
    INTERESTING(cp);
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_LT_ASL20)) {
    INTERESTING(lDebug ? "Apache(2.0#2)" : "Apache-2.0");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_LT_ASL20ref) || INFILE(_LT_ASL20ref_2)) {
    INTERESTING(lDebug ? "Apache(2.0#3)" : "Apache-2.0");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_TITLE_ASL20)) {
    INTERESTING(lDebug ? "Apache(2.0#4)" : "Apache-2.0");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_LT_APACHE_1)) {
    INTERESTING(lDebug ? "Apache(1)" : "Apache");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_LT_APACHE_2)) {
    INTERESTING(lDebug ? "Apache(2)" : "Apache");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_LT_APACHEref1)) {
    INTERESTING(lDebug ? "Apache(ref#1)" : "Apache");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_LT_APACHEref2)) {
    INTERESTING(lDebug ? "Apache(ref#2)" : "Apache");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_LT_APACHEref3)) {
    INTERESTING(lDebug ? "Apache(ref#3)" : "Apache");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_LT_APACHESTYLEref)) {
    INTERESTING("Apache-style");
    lmem[_mAPACHE] = 1;
  }
  else if (URL_INFILE(_URL_ASL_V2_1)) {
    INTERESTING(lDebug ? "Apache2(url#1)" : "Apache-2.0");
    lmem[_mAPACHE] = 1;
  }
  else if (URL_INFILE(_URL_ASL_V2_2)) {
    INTERESTING(lDebug ? "Apache2(url#2)" : "Apache-2.0");
    lmem[_mAPACHE] = 1;
  }
  else if (URL_INFILE(_URL_ASL_1)) {
    INTERESTING(lDebug ? "Apache(url#1)" : "Apache");
    lmem[_mAPACHE] = 1;
  }
  else if (URL_INFILE(_URL_ASL_2)) {
    INTERESTING(lDebug ? "Apache(url#2)" : "Apache");
    lmem[_mAPACHE] = 1;
  }
  /*
   * BSD and all the variant 'flavors'.  BSD licenses are kind of like
   * the cooking concept of 'the mother sauces' -- MANY things are derived
   * from the wordings of these licenses.  There are still many more, for
   * certain, but LOTS of licenses are based on ~10 originally-BSD-phrases.
   */
  if (INFILE(_LT_BSD_1)) {
    if (!lmem[_mAPACHE] && (INFILE(_CR_APACHE) || INFILE(_TITLE_ASL))) {
      if (INFILE(_LT_ASL20ref)) {
        INTERESTING("Apache-2.0");
        lmem[_mAPACHE] = 1;
      }
      else if (INFILE(_LT_ASL11ref)) {
        INTERESTING(lDebug ? "Apache(1.1#2)" : "Apache-1.1");
        lmem[_mAPACHE] = 1;
      }
      else if ((INFILE(_LT_ASLref1) || INFILE(_LT_ASLref2))) {
        INTERESTING(lDebug ? "Apache(1.0#2)" : "Apache-1.0");
        lmem[_mAPACHE] = 1;
      }
      else {
        cp = ASLVERS();
        INTERESTING(cp);
        lmem[_mAPACHE] = 1;
      }
    }
    else if (INFILE(_TITLE_PHP301)) {
      INTERESTING(lDebug ? "PHP(v3.01#1)" : "PHP-3.01");
      lmem[_mPHP] = 1;
    }
    else if (INFILE(_TITLE_PHP30)) {
      INTERESTING(lDebug ? "PHP(v3.0#1)" : "PHP-3.0");
      lmem[_mPHP] = 1;
    }
    else if (INFILE(_TITLE_PHP202)) {
      INTERESTING(lDebug ? "PHP(v2.0.2#1)" : "PHP-2.0.2");
      lmem[_mPHP] = 1;
    }
    else if (INFILE(_CR_VOVIDA) || INFILE(_TITLE_VOVIDA)) {
      INTERESTING("VSL-1.0");
      lmem[_fBSD] = 1;
    }
    else if (INFILE(_CR_NAUMEN) || INFILE(_TITLE_NAUMEN)) {
      INTERESTING("Naumen");
    }
    else if (INFILE(_CR_ENTESSA) || INFILE(_TITLE_ENTESSA)) {
      INTERESTING("Entessa");
    }
    else if (INFILE(_LT_ATTRIB) || INFILE(_TITLE_ATTRIBUTION)) {
      INTERESTING("AAL");
      lmem[_fATTRIB] = 1;
    }
    else if (INFILE(_CR_ZOPE)) {
      INTERESTING(lDebug ? "Zope(bsd)" : "ZPL");
    }
    else if (INFILE(_CR_NETBSD)) {
      INTERESTING("BSD-2-Clause-NetBSD");
    }
    else if (INFILE(_CR_SLEEPYCAT)) {
      INTERESTING(lDebug ? "Sleepycat(1)" : "Sleepycat");
    }
    else if (INFILE(_CR_ORACLE)) {
      INTERESTING("Oracle-Berkeley-DB");
    }
    else if (mCR_CMU()) {
      INTERESTING(lDebug ? "CMU(BSD-ish)" : "CMU");
    }
    else if (INFILE(_TITLE_PHP202)) {
      INTERESTING(lDebug ? "PHP(v2.0.2#2)" : "PHP-2.0.2");
      lmem[_mPHP] = 1;
    }
    else if (INFILE(_TITLE_ZEND_V20)) {
      INTERESTING("Zend-2.0");
    }
    /*
     * Discussion from Raino on why some BSD-style is better called Google-BSD: 
     * WebM license page contains BSD-license with Google copyright.
     * I think it should be detected as Google-BSD license rather than
     * BSD-style. I think still that WebM reference should be recognized
     * separately rather than as Google-BSD. The reason is that the project
     * may change the license terms/license text. In that case interpretation
     * of WebM as Google-BSD could be misleading.
     */
    else if (INFILE(_CR_GOOGLE)) {
      INTERESTING("Google-BSD");
    }
    else if (!lmem[_fOPENLDAP] && !TRYGROUP(famOPENLDAP)) {
      if (HASTEXT(_LT_OPENSSLref5, REG_EXTENDED)) {
        INTERESTING(lDebug ? "OpenSSL(ref)" : "OpenSSL");
      } else if (INFILE(_CR_BSDCAL)) {
        INTERESTING(lDebug ? "BSD(1)" : "BSD");
      } else if (INFILE(_LT_BSD_CLAUSE_0) && INFILE(_LT_BSD_CLAUSE_1) && INFILE(_LT_BSD_CLAUSE_2)
          && INFILE(_LT_BSD_CLAUSE_3) && INFILE(_LT_BSD_CLAUSE_4) && INFILE(_LT_UC)) {
        INTERESTING("BSD-4-Clause-UC");
      } else if (INFILE(_LT_BSD_CLAUSE_0) && INFILE(_LT_BSD_CLAUSE_1) && INFILE(_LT_BSD_CLAUSE_2) 
          && INFILE(_LT_BSD_CLAUSE_3) && INFILE(_LT_BSD_CLAUSE_4)) {
        INTERESTING("BSD-4-Clause");
      } else if (INFILE(_LT_BSD_CLAUSE_0) && INFILE(_LT_BSD_CLAUSE_1) && INFILE(_LT_BSD_CLAUSE_2)
          && INFILE(_LT_BSD_CLAUSE_4) && INFILE(_LT_BSD_CLAUSE_CLEAR)) {
        INTERESTING("BSD-3-Clause-Clear");
      } else if (INFILE(_LT_BSD_CLAUSE_0) && INFILE(_LT_BSD_CLAUSE_1) && INFILE(_LT_BSD_CLAUSE_2)
          && INFILE(_LT_BSD_CLAUSE_4)) {
        INTERESTING("BSD-3-Clause");
      } else if (INFILE(_LT_BSD_CLAUSE_0) && INFILE(_LT_BSD_CLAUSE_1) && INFILE(_LT_BSD_CLAUSE_2)
          && INFILE(_LT_FREE_BSD)) {
        INTERESTING("BSD-2-Clause-FreeBSD");
      } else if (INFILE(_LT_BSD_CLAUSE_0) && INFILE(_LT_BSD_CLAUSE_1) && INFILE(_LT_BSD_CLAUSE_2)) {
        INTERESTING("BSD-2-Clause");
      }
      else if (!lmem[_fZPL]) {
        INTERESTING(lDebug ? "BSD-style(1)" : "BSD-style");
      }
    }
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSD_2)) {
    /*
     * Python, OSF, SecretLabs, some universities, some vendors, etc., have
     * variants here.
     */
    if (INFILE(_CR_PYTHON) || INFILE(_TITLE_PYTHON)) {
      cp = PYTHVERS();
      INTERESTING(lDebug ? "Python(3)" : cp);
      lmem[_mPYTHON] = 1;
    }
    else if (INFILE(_CR_OSF)) {
      INTERESTING(lDebug ? "OSF(1)" : "OSF");
      lmem[_mOSF] = 1;
    }
    else if (INFILE(_CR_UI)) {
      INTERESTING(lDebug ? "UI(1)" : "Unix-Intl");
    }
    else if (INFILE(_CR_XOPEN)) {
      INTERESTING(lDebug ? "XOpen(1)" : "X/Open");
      lmem[_mXOPEN] = 1;
    }
    else if (INFILE(_PHR_HISTORICAL)) {
      INTERESTING("HPND");
    }
    else if (INFILE(_CR_BSDCAL)) {
      INTERESTING(lDebug ? "BSD(2)" : "BSD");
    }
    else {
      INTERESTING(lDebug ? "BSD-style(2)" : "BSD-style");
    }
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSD_3) && !INFILE(_TITLE_OPENLDAP)) {
    if (INFILE(_CR_APACHE)) {
      cp = ASLVERS();
      INTERESTING(lDebug ? "Apache(g)" : cp);
    }
    else if (INFILE(_CR_BSDCAL)) {
      INTERESTING(lDebug ? "BSD(3)" : "BSD");
    }
    else if (!INFILE(_CR_XFREE86)) {
      INTERESTING(lDebug ? "BSD-style(3)" : "BSD-style");
    }
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSD_4)) {
    if (INFILE(_CR_BSDCAL)) {
      INTERESTING(lDebug ? "BSD(4)" : "BSD");
    }
    else {
      INTERESTING(lDebug ? "BSD-style(4)" : "BSD-style");
    }
    lmem[_fBSD] = 1;
  }
  /*
   * FIX-ME: this license text explicitly mentions "for no-profit", and as
   * such it should list it in the license-summary, yes?
   */
  else if (INFILE(_LT_BSD_5)) {
    if (!lmem[_mPYTHON] && INFILE(_CR_PYTHON)) {
      INTERESTING(lDebug ? "Python(2)" : "Python");
      lmem[_mPYTHON] = 1;
    }
    else if (INFILE(_CR_USL_EUR)) {
      INTERESTING(lDebug ? "USLE(1)" : "USL-Europe");
    }
    else if (INFILE(_CR_BSDCAL)) {
      INTERESTING(lDebug ? "BSD(5)" : "BSD");
    }
    else {
      INTERESTING(lDebug ? "BSD-style(5)" : "BSD-style");
    }
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSD_6)) {
    if (INFILE(_CR_BSDCAL)) {
      INTERESTING(lDebug ? "BSD(6)" : "BSD");
    }
    else {
      INTERESTING(lDebug ? "BSD-style(6)" : "BSD-style");
    }
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSD_7)) {
    if (INFILE(_CR_BSDCAL)) {
      INTERESTING(lDebug ? "BSD(7)" : "BSD");
    }
    else {
      INTERESTING(lDebug ? "BSD-style(7)" : "BSD-style");
    }
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSD_8)) {
    if (INFILE(_CR_BSDCAL)) {
      INTERESTING(lDebug ? "BSD(8)" : "BSD");
    }
    else {
      INTERESTING(lDebug ? "BSD-style(8)" : "BSD-style");
    }
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSD_9)) {
    if (INFILE(_CR_BSDCAL)) {
      INTERESTING(lDebug ? "BSD(9)" : "BSD");
    }
    else {
      INTERESTING(lDebug ? "BSD-style(8)" : "BSD-style");
    }
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSD_10)) {
    if (INFILE(_CR_BSDCAL)) {
      INTERESTING(lDebug ? "BSD(10)" : "BSD");
    }
    else {
      INTERESTING(lDebug ? "BSD-style(9)" : "BSD-style");
    }
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSD_11)) {
    if (INFILE(_CR_BSDCAL)) {
      INTERESTING(lDebug ? "BSD(11)" : "BSD");
    }
    else {
      INTERESTING(lDebug ? "BSD-style(10)" : "BSD-style");
    }
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSD_12)) {
    if (INFILE(_CR_BSDCAL)) {
      INTERESTING(lDebug ? "BSD(12)" : "BSD");
    }
    else {
      INTERESTING(lDebug ? "BSD-style(11)" : "BSD-style");
    }
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSD_13)) {
    if (INFILE(_CR_BSDCAL)) {
      INTERESTING(lDebug ? "BSD(13)" : "BSD");
    }
    else {
      INTERESTING(lDebug ? "BSD-style(12)" : "BSD-style");
    }
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSD_NONC)) {
    if (INFILE(_CR_BSDCAL)) {
      INTERESTING(lDebug ? "BSD(NonC)" : "BSD(non-commercial)");
    }
    else {
      INTERESTING(lDebug ? "BSD-style(NonC)" : "Non-commercial");
    }
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSDref1)) {
    INTERESTING(lDebug ? "BSD(ref1)" : "BSD");
    /* lmem[_fBSD] = 1; */
  }
  else if (INFILE(_LT_BSDref2)) {
    INTERESTING(lDebug ? "BSD(ref2)" : "BSD");
    /* lmem[_fBSD] = 1; */
  }
  else if (INFILE(_LT_BSDref3)) {
    INTERESTING(lDebug ? "BSD(ref3)" : "BSD");
    /* lmem[_fBSD] = 1; */
  }
  else if (INFILE(_LT_BSDref4)) {
    INTERESTING(lDebug ? "BSD(ref4)" : "BSD");
    /* lmem[_fBSD] = 1; */
  }
  else if (INFILE(_LT_BSDref5)) {
    INTERESTING(lDebug ? "BSD(ref5)" : "BSD");
    /* lmem[_fBSD] = 1; */
  }
  else if (INFILE(_LT_BSDref6)) {
    INTERESTING(lDebug ? "BSD(ref6)" : "BSD");
    /* lmem[_fBSD] = 1; */
  }
  else if (INFILE(_LT_BSDref7)) {
    INTERESTING(lDebug ? "BSD(ref7)" : "BSD");
    /* lmem[_fBSD] = 1; */
  }
  else if (INFILE(_LT_BSDref8)) {
    INTERESTING(lDebug ? "BSD(ref8)" : "BSD");
    /* lmem[_fBSD] = 1; */
  }
  else if (INFILE(_LT_BSDref9)) {
    INTERESTING(lDebug ? "BSD(ref9)" : "BSD");
    /* lmem[_fBSD] = 1; */
  }
  else if (URL_INFILE(_URL_BSD_1) || URL_INFILE(_URL_BSD_2)) {
    INTERESTING(lDebug ? "BSD(url)" : "BSD");
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSDSTYLEref1)) {
    INTERESTING(lDebug ? "BSD-st(1)" : "BSD-style");
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSDSTYLEref2)) {
    INTERESTING(lDebug ? "BSD-st(2)" : "BSD-style");
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSDSTYLEref3)) {
    INTERESTING(lDebug ? "BSD-st(3)" : "BSD-style");
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSDSTYLEref4)) {
      INTERESTING(lDebug ? "BSD-st(4)" : "BSD-style");
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_FILE_BSD1) || INFILE(_FILE_BSD2)) {
    INTERESTING(lDebug ? "BSD(deb)" : "BSD");
  }
  /*
   * Aptana public license (based on MPL)
   */
  if (INFILE(_LT_APTANA)) {
    if (INFILE(_TITLE_APTANA_V10)) {
      INTERESTING("Aptana-PL-1.0");
    }
    else {
      INTERESTING("Aptana");
    }
    lmem[_mMPL] = 1;
    lmem[_mAPTANA] = 1;
  }
  /*
   * PHP variants
   */
  if (!lmem[_mPHP] && INFILE(_LT_PHP)) {
    if (INFILE(_TITLE_PHP301)) {
      INTERESTING(lDebug ? "PHP(v3.01#2)" : "PHP-3.01");
    }
    else if (INFILE(_TITLE_PHP30)) {
      INTERESTING(lDebug ? "PHP(v3.0#2)" : "PHP-3.0");
    }
    else if (INFILE(_TITLE_PHP202)) {
      INTERESTING(lDebug ? "PHP(v2.0.2#3)" : "PHP-2.0.2");
    }
    else if (INFILE(_CR_PHP)) {
      INTERESTING(lDebug ? "PHP(1)" : "PHP");
    }
    else {
      INTERESTING("PHP-style");
    }
    lmem[_mPHP] = 1;
  }
  else if (!lmem[_mPHP] && INFILE(_LT_PHPref1)) {
    if (INFILE(_PHR_PHP_V301)) {
      INTERESTING(lDebug ? "PHP(ref-3.01)" : "PHP-3.01");
    }
    else if (INFILE(_PHR_PHP_V20)) {
      INTERESTING(lDebug ? "PHP(ref-2.0)" : "PHP-2.0");
    }
    else {
      INTERESTING(lDebug ? "PHP(ref)" : "PHP");
    }
    lmem[_mPHP] = 1;
  }
  else if (!lmem[_mPHP] && URL_INFILE(_URL_PHP)) {
    INTERESTING(lDebug ? "PHP(url)" : "PHP");
    lmem[_mPHP] = 1;
  }
  /*
   * Licenses between here and all the GPL/LGPL/GFDL/FSF checks (below) MUST
   * be tested PRIOR to checking GPL/FSF and friends
   */
  if ((INFILE(_CR_EASYSW) || INFILE(_TITLE_CUPS)) && INFILE(_LT_CUPS)) {
    if (INFILE(_LT_CUPS_COMMERCIAL)) {
      INTERESTING("CUPS-EULA");
    }
    else {
      INTERESTING("CUPS");
    }
    lmem[_mCUPS] = 1;
  }
  if (INFILE(_LT_HACKTIVISMO)) {
    INTERESTING("Hacktivismo");
    lmem[_mGPL] = 1;        /* don't look for GPL references */
  }
  if (INFILE(_LT_NESSUS) && INFILE(_TITLE_NESSUS)) {
    INTERESTING("NESSUS-EULA");
    lmem[_mLGPL] = 1;       /* don't look for LGPL references */
    lmem[_mGPL] = 1;
  }
  /*
   * Oracle
   */
  if (INFILE(_LT_ORACLE_PROTO) && INFILE(_TITLE_ORACLE_PROTO)) {
    INTERESTING(lDebug ? "Oracle(proto)" : "Oracle-EULA");
    lmem[_mGPL] = 1;
  }
  else if (INFILE(_LT_ORACLE_DEVEL) && INFILE(_TITLE_ORACLE_DEVEL)) {
    INTERESTING(lDebug ? "Oracle(dev)" : "Oracle-Dev");
  }
  else if (INFILE(_URL_ORACLE_BERKELEY_DB)) {
    INTERESTING(lDebug ? "URL_ORACLE_BERKELEY_DB" : "Oracle-Berkeley-DB");
  }
  /*
   * CeCILL
   * According to digikam-0.9.4/digikam/libs/greycstoration/CImg.h:
   * The CeCILL-C (C_V1) license is close to the GNU LGPL
   * The CeCILL (V2.0) license is compatible with the GNU GPL
   */
  if (INFILE(_TITLE_CECILL_V11_2)) {
    INTERESTING(lDebug ? "CeCILL_v1.1(#2)" : "CECILL-1.1");
    lmem[_mGPL] = lmem[_mLGPL] = 1;
  }
  else if (INFILE(_TITLE_CECILL_B) || INFILE(_TITLE_CECILL_B1)) {
    INTERESTING("CECILL-B");
  }
  else if (INFILE(_TITLE_CECILL_C) || INFILE(_TITLE_CECILL_C1)) {
    INTERESTING("CECILL-C");
  }
  else if (INFILE(_LT_CECILL_DUALref)) {
    INTERESTING(lDebug ? "CeCILL(dual)" : "CECILL");
    lmem[_mGPL] = lmem[_mLGPL] = 1;
  }
  else if (INFILE(_LT_CECILL_ref) || INFILE(_LT_CECILL_ref1)) {
    if (URL_INFILE(_URL_CECILL_C_V11)) {
      INTERESTING(lDebug ? "CeCILL_v1.1(url)" : "CECILL-1.1");
    }
    else if (URL_INFILE(_URL_CECILL_C_V1)) {
      INTERESTING(lDebug ? "CeCILL_v1(url)" : "CECILL-1.0");
    }
    else if (URL_INFILE(_URL_CECILL_V2)) {
      INTERESTING(lDebug ? "CeCILL_v2(url)" : "CECILL-2.0");
    }
    else if (URL_INFILE(_URL_CECILL)) {
      INTERESTING(lDebug ? "CeCILL(url)" : "CECILL");
    }
    else {
      INTERESTING(lDebug ? "CeCILL(#3)" : "CECILL");
    }
    lmem[_mGPL] = 1;
  }
  else if (INFILE(_LT_CECILL_1)) {
    if (INFILE(_TITLE_CECILL_V10)) {
      INTERESTING(lDebug ? "CeCILL_v1.0(#1)" : "CECILL-1.0");
    }
    else if (INFILE(_TITLE_CECILL_V20)) {
      INTERESTING(lDebug ? "CeCILL_v2.0(#1)" : "CECILL-2.0");
    }
    lmem[_mGPL] = 1;
  }
  else if (INFILE(_LT_CECILL_2) || INFILE(_TITLE_CECILL1) || INFILE(_TITLE_CECILL2)) {
    if (INFILE(_TITLE_CECILL_V10)) {
      INTERESTING(lDebug ? "CeCILL_v1.0(#2)" : "CECILL-1.0");
    }
    else if (INFILE(_TITLE_CECILL_V11)) {
      INTERESTING(lDebug ? "CeCILL_v1.1(#1)" : "CECILL-1.1");
    }
    else if (INFILE(_TITLE_CECILL_V20)) {
      INTERESTING(lDebug ? "CeCILL_v2.0(#2)" : "CECILL-2.0");
    }
    else {
      INTERESTING(lDebug ? "CeCILL(#2)" : "CECILL");
    }
    lmem[_mGPL] = 1;
  }
  /*
   * Monash University
   */
  if (INFILE(_CR_UMONASH) && INFILE(_LT_UMONASH)) {
    INTERESTING("U-Monash");
    if (INFILE(_PHR_GPL_NO_MORE)) {
      lmem[_mGPL] = 1;
    }
  }

  /* Open Font License   */
  if (INFILE(_LT_OPEN_FONT_V10))
  {
    INTERESTING("OFL-1.0");
    lmem[_mMIT] = 1;
  }
  else if (INFILE(_LT_OPEN_FONT_V11))
  {
    INTERESTING("OFL-1.1");
    lmem[_mMIT] = 1;
  }

  /** Simple Public License 2.0 */
  if (INFILE(_TITLE_SimPL_V2)) {
    INTERESTING("SimPL-2.0");
    lmem[_mGPL] = 1;
  }

  /** Leptonica license */
  if (INFILE(_TITLE_LEPTONICA)) {
    INTERESTING("Leptonica");
  }

  /*
   * GPL, LGPL, GFDL
   * QUESTION: do we need to check for the FSF copyright since we also
   * check for "GNU" or "free"?
   */
  if ((!INFILE(_LT_FORMER_GNU) && (mCR_FSF() ||
      HASTEXT(_TEXT_GNUTERMS, REG_EXTENDED)))) {
    /*
     * Affero
     */
    if (INFILE(_PHR_AFFERO)) {
      if (INFILE(_LT_AFFERO1) || INFILE(_LT_AFFERO2) ||
          INFILE(_LT_AFFERO3)) {
        cp = AGPLVERS();
        INTERESTING(lDebug ? "Affero(#1)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_LT_AFFEROref1)) {
        cp = AGPLVERS();
        INTERESTING(lDebug ? "Affero(#2)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_LT_AFFEROref2) && !INFILE(_LT_NOTAFFEROref1)) {
        cp = AGPLVERS();
        INTERESTING(lDebug ? "Affero(#3)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (mCR_FSF() && !INFILE(_LT_GPL3_NOT_AFFERO)) {
        cp = AGPLVERS();
        INTERESTING(lDebug ? "Affero(CR)" : cp);
        lmem[_mGPL] = 1;
      }
    }
    /*
     * Some packages have a single file containing both a GPL and an LGPL
     * license.  Therefore, these checks should NOT be exclusive.
     */
     /* * The Nethack General Public License (NGPL) */
    else if (INFILE(_TITLE_NGPL)) {
      INTERESTING("NGPL");
      lmem[_mGPL] = 1;
    }
    else if (INFILE(_LT_GPL_V1)) {
      INTERESTING("GPL-1.0");
      lmem[_mGPL] = 1;
    }
    else if (INFILE(_LT_GPL_V2)) {
      INTERESTING("GPL-2.0");
      lmem[_mGPL] = 1;
    }
    else if (INFILE(_LT_GPL_1)) {
      if (GPL_INFILE(_PHR_FSF_V2_OR_LATER) ||
          INFILE(_PHR_GPL2_OR_LATER))
      {
        INTERESTING("GPL-2.0+");
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_TITLE_GPL2)) {
        INTERESTING("GPL-2.0");
        lmem[_mGPL] = 1;
      }
      else if (GPL_INFILE(_PHR_FSF_V1_OR_LATER) ||
          INFILE(_PHR_GPL1_OR_LATER))
      {
        INTERESTING("GPL-1.0+");
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_TITLE_GPL1)) {
        INTERESTING("GPL-1.0");
        lmem[_mGPL] = 1;
      }
      else {
        INTERESTING("GPL");
        lmem[_mGPL] = 1;
      }
    }
    else if (INFILE(_LT_GPL3_PATENTS)) {
      if (INFILE(_TITLE_GPL3)) {
        INTERESTING(lDebug ? "GPL_v3(#1)" : "GPL-3.0");
        lmem[_mGPL] = 1;
      }
      else {
        INTERESTING("GPL-3?");
        lmem[_mGPL] = 1;
      }
    }
    if (INFILE(_LT_LGPL_1) || INFILE(_LT_LGPL_2)) {
      if (INFILE(_PHR_LGPL21_OR_LATER) ||
                RM_INFILE(_PHR_FSF_V21_OR_LATER))
      {
        INTERESTING("LGPL-2.1+");
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_PHR_LGPL2_OR_LATER) ||
                RM_INFILE(_PHR_FSF_V2_OR_LATER))
      {
        INTERESTING("LGPL-2.0+");
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_TITLE_LGPLV21)) {
        INTERESTING("LGPL-2.1");
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_TITLE_LGPLV2)) {
        INTERESTING("LGPL-2.0");
        lmem[_mLGPL] = 1;
      }
      else {
        INTERESTING("LGPL");
        lmem[_mLGPL] = 1;
      }
    }
    else if (INFILE(_LT_LGPL_3)) {
      if (INFILE(_PHR_LGPL3_OR_LATER) ||
                RM_INFILE(_PHR_FSF_V3_OR_LATER))
      {
        INTERESTING("LGPL-3.0+");
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_TITLE_LGPL3)) {
        INTERESTING("LGPL-3.0");
        lmem[_mLGPL] = 1;
      }
      else {
        INTERESTING("LGPL-3?");
        lmem[_mLGPL] = 1;
      }
    }
    if (INFILE(_LT_GFDL)) {
      cp = GFDLVERS();
      INTERESTING(lDebug ? "GFDL(#1)" : cp);
      lmem[_mGFDL] = 1;
    }
    if (!lmem[_mLGPL] && !INFILE(_LT_MPL_SECONDARY)) {            /* no FSF/GPL-like match yet */
      /*
        NOTE: search for LGPL before GPL; the latter matches
        occurrences of former
       */
      if (INFILE(_LT_GPL_FONT1) && INFILE(_LT_GPL_FONT2)) {
        INTERESTING(lDebug ? "GPL(fonts)" : "GPL-exception");
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_LT_LGPL_ALT)) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(alternate)" : cp);
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_LT_LGPL3ref) && !INFILE(_PHR_NOT_UNDER_LGPL)) {
        INTERESTING("LGPL-3.0");
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_LT_LGPLref1)) {
        if (INFILE(_PHR_WXWINDOWS)) {
          INTERESTING("WXwindows");
        }
        else {
          cp = LGPLVERS();
          INTERESTING(lDebug ? "LGPL(ref1)" : cp);
        }
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_LT_LGPLref2) &&
          !INFILE(_PHR_NOT_UNDER_LGPL)) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(ref2#1)" : cp);
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_LT_LGPLref3)) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(ref3)" : cp);
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_LT_LGPLref4)) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(ref4)" : cp);
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_LT_LGPLref5)) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(ref5)" : cp);
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_LT_LGPLref6)) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(ref6)" : cp);
        lmem[_mLGPL] = 1;
      }
      else if (!lmem[_mLIBRE] && !lmem[_fREAL] &&
          INFILE(_LT_LGPLref7) &&
          !INFILE(_PHR_NOT_UNDER_LGPL)) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(ref7)" : cp);
        lmem[_mLGPL] = 1;
      }
      else if (!lmem[_fREAL] && !lmem[_mAPTANA] &&
          !LVAL(_TEXT_GNU_LIC_INFO) && INFILE(_LT_LGPLref8) &&
          !INFILE(_PHR_NOT_UNDER_LGPL)) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(ref8)" : cp);
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_LT_LGPLref9) &&
          !INFILE(_PHR_NOT_UNDER_LGPL)) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(ref9)" : cp);
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_LT_LGPLref10) &&
          !INFILE(_PHR_NOT_UNDER_LGPL)) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(ref10)" : cp);
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_LT_LGPLref11)) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(ref11)" : cp);
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_FILE_LGPL1) || INFILE(_FILE_LGPL2)) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(deb)" : cp);
        lmem[_mLGPL] = 1;
      }
      else if (!LVAL(_TEXT_GNU_LIC_INFO) &&
          (URL_INFILE(_URL_LGPL_1) ||
              URL_INFILE(_URL_LGPL_2))) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(url)" : cp);
        lmem[_mLGPL] = 1;
      }
      else if (!LVAL(_TEXT_GNU_LIC_INFO) && lmem[_fREAL] &&
          GPL_INFILE(_LT_LGPL_OR)) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(or)" : cp);
        lmem[_mLGPL] = 1;
      }
    }
    if (!lmem[_mGPL]) {
      if (GPL_INFILE(_LT_GPL_ALT)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(alternate)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (GPL_INFILE(_LT_GPL3ref)) {
        INTERESTING(lDebug ? "GPL_v3(#2)" : "GPL-3.0");
        lmem[_mGPL] = 1;
      }
      else if (GPL_INFILE(_LT_GPL3ref2) || GPL_INFILE(_PHR_FSF_V3_OR_LATER) || GPL_INFILE(_PHR_GPL3_OR_LATER)) {
                INTERESTING("GPL-3.0+");
                lmem[_mGPL] = 1;
           }
      else if (GPL_INFILE(_LT_GPL3ref3)) {
                INTERESTING("GPL-3.0");
                lmem[_mGPL] = 1;
           }
      else if (!lmem[_mLIBRE] && GPL_INFILE(_LT_GPLref1)
          && !INFILE(_PHR_NOT_UNDER_GPL)
          && !INFILE(_LT_LGPLref2)) {
        /*
         * Special exceptions:
         * (1) LaTeX uses the following phrase:
         * "... why distributing LaTeX under the GNU General Public License (GPL)
         * was considered inappropriate".
         * (2) Python uses the following phrases:
         * "GPL-compatible doesn't mean that we're distributing Python under the GPL"
         * AND, "GPL-compatible licenses make it possible to combine Python with
         *       other software that is released under the GPL.
         *****
         * These MUST be filtered.  Do so by marking the GPL flag but don't assign
         * a license component (e.g., ignore GPL for this file)
         */
        if (INFILE(_PHR_LATEX_GPL_INAPPROPRIATE) ||
            INFILE(_PHR_PYTHON_NOTGPL_1) ||
            INFILE(_PHR_PYTHON_NOTGPL_2)) {
          lmem[_mGPL] = 1;
        }
        else if (!HASTEXT(_TEXT_GCC, REG_EXTENDED) && !HASTEXT(_LT_GPL_EXCEPT_AUTOCONF, REG_EXTENDED) 
            && !INFILE(_LT_GPL_EXCEPT_BISON_1) && !INFILE(_LT_GPL_EXCEPT_BISON_2)){
          cp = GPLVERS();
          INTERESTING(lDebug ? "GPL(ref1#1)" : cp);
          lmem[_mGPL] = 1;
        }
      }
      else if (INFILE(_LT_GPL_FSF)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(FSF)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (GPL_INFILE(_LT_GPLref2)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(ref2)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (GPL_INFILE(_LT_GPLref3)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(ref3)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_LT_GPLref4)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(ref4)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (GPL_INFILE(_LT_GPLref5)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(ref5)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (GPL_INFILE(_LT_GPLref6)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(ref6)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (GPL_INFILE(_LT_GPLref7)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(ref7)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (GPL_INFILE(_LT_GPLref8)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(ref8)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_LT_GPLref9)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(ref9)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (GPL_INFILE(_LT_GPLref10)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(ref10)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_LT_GPLref11)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(ref11)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_LT_GPLref12)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(ref12)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (GPL_INFILE(_LT_GPLref13)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(ref13)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (GPL_INFILE(_LT_GPLref14) &&
          !INFILE(_LT_LGPLref2)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(ref14)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_LT_GPLref16)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(ref16)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_LT_GPLref18) && !HASTEXT(_TEXT_CLASSPATH, REG_EXTENDED)) {
        if (INFILE(_LT_EXCEPT_1)) {
          INTERESTING(lDebug ? "GPL-except-4" : "GPL-exception");
        }
        else {
          cp = GPLVERS();
          INTERESTING(lDebug ? "GPL(ref18)" : cp);
        }
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_LT_GPLref19)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(ref19)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_LT_GPLref20)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(ref20)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (!LVAL(_TEXT_GNU_LIC_INFO) &&
          (URL_INFILE(_URL_GPL_1) ||
              URL_INFILE(_URL_GPL_2) ||
              URL_INFILE(_URL_GPL_3))) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(url)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (URL_INFILE(_URL_AGPL)) {
        cp = AGPLVERS();
        INTERESTING(lDebug ? "Affero(url)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (!ltsr[_LT_LGPL_3] && INFILE(_LT_GPL_OR)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(or)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (!lmem[_mGPL] && !lmem[_mLGPL] &&
          (INFILE(_LT_GNU_1) + INFILE(_LT_GNU_2) +
              INFILE(_LT_GNU_3) + INFILE(_LT_GNU_4) > 2)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(GNU)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (!lmem[_mGPL] && !lmem[_mLGPL] &&
          INFILE(_LT_SEE_GPL) && INFILE(_LT_RECV_GPL)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(see)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (!lmem[_mGPL] && !lmem[_mLGPL] &&
          INFILE(_LT_SEE_LGPL) && INFILE(_LT_RECV_LGPL)) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(see)" : cp);
        lmem[_mLGPL] = 1;
      }
    }
    if (!lmem[_mGFDL] && (INFILE(_LT_GFDLref1) ||
        INFILE(_TITLE_GFDL))) {
      cp = GFDLVERS();
      INTERESTING(lDebug ? "GFDL(#2)" : cp);
      lmem[_mGFDL] = 1;
    }
    /*
     * Listed _explictly_ as an exception to the GPL -- this is NOT an 'else'
     * clause!
     */
    if (INFILE(_LT_GPL_EXCEPT_CLASSPATH_1)) {
      INTERESTING(lDebug ? "GPL-except-classpath_1" : "GPL-2.0-with-classpath-exception");
    }
    else
      if (INFILE(_LT_GPL_EXCEPT_CLASSPATH_2)) {
        INTERESTING(lDebug ? "GPL-except-classpath_2" : "GPL-2.0-with-classpath-exception");
      }
      else
        if (INFILE(_LT_GPL_EXCEPT_AUTOCONF) && (INFILE(_TITLE_GPL3_ref1) || INFILE(_TITLE_GPL3_ref2))) {
          INTERESTING("GPL-3.0-with-autoconf-exception");
          lmem[_mGPL] = 1;
        }
        else if (INFILE(_LT_GPL_EXCEPT_AUTOCONF) && (INFILE(_TITLE_GPL2_ref1) || INFILE(_TITLE_GPL2_ref2))) {
          INTERESTING("GPL-2.0-with-autoconf-exception");
          lmem[_mGPL] = 1;
        }
        else if (HASTEXT(_TEXT_CLASSPATH, REG_EXTENDED) && (INFILE(_TITLE_GPL2_ref1) || INFILE(_TITLE_GPL2_ref2))) {
          INTERESTING("GPL-2.0-with-classpath-exception");
          lmem[_mGPL] = 1;
        }
        else if (HASTEXT(_TEXT_FONT, REG_EXTENDED) && (INFILE(_TITLE_GPL2_ref1) || INFILE(_TITLE_GPL2_ref2))) {
          INTERESTING("GPL-2.0-with-font-exception");
          lmem[_mGPL] = 1;
        }
        else if (HASTEXT(_TEXT_GCC, REG_EXTENDED) && (INFILE(_TITLE_GPL3_ref1) || INFILE(_TITLE_GPL3_ref2))) {
          INTERESTING("GPL-3.0-with-GCC-exception");
          lmem[_mGPL] = 1;
        }
        else if (HASTEXT(_TEXT_GCC, REG_EXTENDED) && (INFILE(_TITLE_GPL2_ref1) || INFILE(_TITLE_GPL2_ref2))) {
          INTERESTING("GPL-2.0-with-GCC-exception");
          lmem[_mGPL] = 1;
        }
        else if (INFILE(_LT_GPL_EXCEPT_BISON_1)) {
          INTERESTING(lDebug ? "GPL-except-Bison-1" : "GPL-2.0-with-bison-exception");
        }
        else if (INFILE(_LT_GPL_EXCEPT_BISON_2)) {
          INTERESTING(lDebug ? "GPL-except-Bison-2" : "GPL-2.0-with-bison-exception");
        }
        else if (INFILE(_LT_GPL_EXCEPT_1)) {
          INTERESTING(lDebug ? "GPL-except-1" : "GPL-exception");
        }
        else if (INFILE(_LT_GPL_EXCEPT_2)) {
          INTERESTING(lDebug ? "GPL-except-2" : "GPL-exception");
        }
        else if (INFILE(_LT_GPL_EXCEPT_3)) {
          INTERESTING(lDebug ? "GPL-except-3" : "GPL-exception");
        }
        else if (INFILE(_PHR_GPL_DESCRIPTIONS)) {
          INTERESTING(lDebug ? "GPL-kinda" : "GPL");
        }
        else if (INFILE(_LT_GPL_EXCEPT_ECOS)) {
          INTERESTING("eCos-2.0");
        }
      /* checking for FSF */
      if (INFILE(_LT_FSF_1)) {
        INTERESTING(lDebug ? "FSF(1)" : "FSF");
      }
      else if (INFILE(_LT_FSF_2)) {
        INTERESTING(lDebug ? "FSF(2)" : "FSF");
      }
      else if (INFILE(_LT_FSF_3)) {
        INTERESTING(lDebug ? "FSF(3)" : "FSF");
      }
      else if (mCR_FSF() && INFILE(_LT_FSF_4)) {
        INTERESTING(lDebug ? "FSF(4)" : "FSF");
      }
      else if (mCR_FSF() && INFILE(_LT_FSF_5) && !lmem[_mGPL]) {
        INTERESTING(lDebug ? "FSF(5)" : "FSF");
      }
      else if (INFILE(_LT_FSFref1) && !lmem[_mGPL]) {
        INTERESTING(lDebug ? "FSF(ref1)" : "FSF");
      }
      else if (INFILE(_LT_FSFref2)) {
        INTERESTING(lDebug ? "FSF(ref2)" : "FSF");
      }
      else if (INFILE(_LT_LGPLrefFSF) &&
          !INFILE(_PHR_NOT_UNDER_LGPL)) {
        INTERESTING(lDebug ? "LGPL(FSF)" : "LGPL");
        lmem[_mLGPL] = 1;
      }
      if (!lmem[_mGPL] && !lmem[_mLGPL] && !lmem[_mGFDL]) {
      /*
       * Check these patterns AFTER checking for FSF and GFDL, and only if the
       * CUPS license isn't present.
       */
      if (!lmem[_mCUPS] && !lmem[_mLGPL] && !lmem[_mGPL]) {
        if (GPL_INFILE(_LT_GPLpatt1) &&
            !INFILE(_PHR_NOT_UNDER_LGPL)) {
          cp = GPLVERS();
          INTERESTING(lDebug ? "GPL(patt1)" : cp);
          lmem[_mGPL] = 1;
        }
        else if (GPL_INFILE(_LT_GPLpatt2)) {
          cp = GPLVERS();
          INTERESTING(lDebug ? "GPL(patt2)" : cp);
          lmem[_mGPL] = 1;
        }
        else if (INFILE(_CR_rms) && INFILE(_LT_GPL_2)) {
          INTERESTING("GPL(rms)");
          lmem[_mGPL] = 1;
        }
        else if (INFILE(_PHR_GPLISH_SAMPLE)) {
          INTERESTING("GPL-or-LGPL");
          lmem[_mLGPL] = lmem[_mGPL] = 1;
        }
      }
    }
    else if (INFILE(_LT_GNU_COPYLEFT)) {
      INTERESTING("GNU-copyleft");
      lmem[_fGPL] = 1;
    }
    lmem[_fGPL] = lmem[_mLGPL]+lmem[_mGPL]+lmem[_mGFDL];
  }
  if (!lmem[_mGPL] && INFILE(_LT_GNU_PROJECTS)) {
    cp = GPLVERS();
    INTERESTING(lDebug ? "GPL(proj)" : cp);
    lmem[_mGPL] = 1;
  }
  if (!lmem[_mGPL] && !lmem[_mGFDL] && !lmem[_mLGPL] && !lmem[_fZPL]
      && (!INFILE(_LT_MPL_SECONDARY))
      && (!INFILE(_TEXT_NOT_GPL))
      && (!INFILE(_TEXT_NOT_GPL2))
      && (!INFILE(_LT_CNRI_PYTHON_GPL))
      && (!INFILE(_LT_GPL_EXCEPT_BISON_1))
      && (!INFILE(_LT_GPL_EXCEPT_BISON_2))
      && (!INFILE(_LT_W3Cref4))
      && (INFILE(_LT_GPL_NAMED) 
        || INFILE(_LT_GPL_NAMED2)
        || INFILE(_LT_GPL_NAMED3))
      && (!INFILE(_LT_GPL_NAMED3_EXHIBIT))
      && (!INFILE(_LT_GPL_NAMED_EXHIBIT))) {
    cp = GPLVERS();
    INTERESTING(lDebug ? "GPL(named)" : cp);
  }
  if (!lmem[_mLGPL] && (INFILE(_LT_LGPL_NAMED)
        || INFILE(_LT_LGPL_NAMED2)) && !INFILE(_LT_GPL_NAMED_EXHIBIT)) {
    cp = LGPLVERS();
    INTERESTING(lDebug ? "LGPL(named)" : cp);
  }

  
  if (INFILE(_LT_WXWINDOWS)) {
    INTERESTING("WXwindows");
  }

  /*
   * MIT, X11, Open Group, NEC -- text is very long, search in 2 parts
   */
  if (INFILE(_LT_JSON) && INFILE(_LT_MIT_NO_EVIL)) { // JSON license 
    INTERESTING("JSON");
    lmem[_mMIT] = 1;
  }
  if ((INFILE(_LT_MIT_1) || INFILE(_TITLE_MIT)) && !INFILE(_TITLE_MIT_EXHIBIT)) {
    if(INFILE(_LT_MIT_NO_EVIL)) {
      INTERESTING(lDebug ? "MIT-style(no evil)" : "JSON");
      lmem[_mMIT] = 1;
    }
    else if (INFILE(_LT_MIT_2)) {
      if (INFILE(_CR_XFREE86)) {
        INTERESTING("XFree86-1.1");
      }
      else if (mCR_X11()) {
        INTERESTING(lDebug ? "X11(1)" : "X11");
      }
      else if (INFILE(_CR_SPI)) {
        INTERESTING("Debian-SPI");
      }
      else if (mCR_MIT() || INFILE(_TITLE_MIT)) {
        INTERESTING(lDebug ? "MIT(1)" : "MIT");
        lmem[_mMIT] = 1;
      }
      else if (INFILE(_TITLE_XNET)) {
        INTERESTING("Xnet");
        lmem[_mMIT] = 1;
      }
      else {
        INTERESTING(lDebug ? "MIT-style(1)" : "MIT-style");
        lmem[_mMIT] = 1;
      }
    }
    if (INFILE(_LT_BITSTREAM_1)) {
      INTERESTING(lDebug ? "Bitstream(1)" : "Bitstream");
      lmem[_mMIT] = 1;
    }
    else if (INFILE(_CR_XFREE86)) {
      INTERESTING("XFree86-1.1");
    }
    else if (mCR_X11()) {
      INTERESTING("X11");
    }
    else if (!lmem[_mMPL] && INFILE(_LT_MPL_1)) {
      cp = MPLVERS(); /* NPL, too */
      INTERESTING(lDebug ? "MPL/NPL#5" : cp);
      lmem[_mMPL] = 1;
    }
    else if (!lmem[_mMIT] && (mCR_MIT() || INFILE(_TITLE_MIT)) && !INFILE(_TITLE_MIT_EXHIBIT)) {
      INTERESTING(lDebug ? "MIT(2)" : "MIT");
      lmem[_mMIT] = 1;
    }
    /*
     * BOOST (relatively new, circa August 2003)
     * hmm, some references have a Copyright Notre Dame, some don't
     */
    else if (INFILE(_LT_BOOST_1)) {
      if (INFILE(_TITLE_BOOST10)) {
        INTERESTING("BSL-1.0");
      }
      else if (INFILE(_CR_NOTREDAME)) {
        INTERESTING(lDebug ? "Boost(ND)" : "BSL-1.0");
      }
      else if (INFILE(_TITLE_BOOST)) {
        INTERESTING("BSL-1.0");
      }
      else {
        INTERESTING("BSL-style");
      }
    }
    else if (!lmem[_mMIT]) {
      if (mCR_MIT()) {
        INTERESTING(lDebug ? "MIT(3)" : "MIT");
      }
      else {
        INTERESTING(lDebug ? "MIT-style(2)" : "MIT-style");
      }
      lmem[_mMIT] = 1;
    }
  }
  else if (INFILE(_LT_MIT_5)) {
    if (mCR_MIT()) {
      INTERESTING(lDebug ? "MIT(4)" : "MIT");
    }
    else {
      INTERESTING(lDebug ? "MIT-style(3)" : "MIT-style");
    }
    lmem[_mMIT] = 1;
  }
  /*
   * Open Group, NEC, MIT use the same text in licenses
   */
  if (INFILE(_LT_MIT_6)) {
    if (INFILE(_CR_OG)) {
      INTERESTING(lDebug ? "OpenGroup(1)" : "OpenGroup");
    }
    else if (!lmem[_mCMU] && mCR_CMU()) {
      INTERESTING(lDebug ? "CMU(2)" : "CMU");
      lmem[_mCMU] = 1;
    }
    else if (!lmem[_mMIT] && mCR_MIT()) {
      INTERESTING(lDebug ? "MIT(6)" : "MIT");
      lmem[_mMIT] = 1;
    }
    else {
      INTERESTING(lDebug ? "MIT-style(4)" : "MIT-style");
      lmem[_mMIT] = 1;
    }
  }
  else if (INFILE(_LT_MIT_7)) {
    if (INFILE(_CR_OG)) {
      INTERESTING(lDebug ? "OpenGroup(2)" : "OpenGroup");
    }
    else if (!lmem[_mMIT] && mCR_MIT()) {
      INTERESTING(lDebug ? "MIT(7)" : "MIT");
      lmem[_mMIT] = 1;
    }
    else {
      INTERESTING(lDebug ? "MIT-style(5)" : "MIT-style");
      lmem[_mMIT] = 1;
    }
  }
  else if (INFILE(_LT_MITref1)) {
    if (mCR_MIT()) {
      INTERESTING(lDebug ? "MIT(ref1)" : "MIT");
      lmem[_mMIT] = 1;
    }
    else {
      INTERESTING(lDebug ? "MIT-style(ref)" : "MIT-style");
      lmem[_mMIT] = 1;
    }
  }
  else if (INFILE(_LT_MITref2)) {
    INTERESTING(lDebug ? "MIT(ref2)" : "MIT");
    lmem[_mMIT] = 1;
  }
  else if (INFILE(_LT_OG_1)) {
    if (INFILE(_CR_OG)) {
      INTERESTING(lDebug ? "OpenGroup(3)" : "OpenGroup");
    }
    else {
      INTERESTING(lDebug ? "OG-style(3)" : "OpenGroup-style");
    }
  }
  else if (INFILE(_LT_OG_2)) {
    if (INFILE(_CR_OG)) {
      INTERESTING(lDebug ? "OpenGroup(4)" : "OpenGroup");
    }
    else {
      INTERESTING(lDebug ? "OG-style(4)" : "OpenGroup-style");
    }
  }
  else if (INFILE(_LT_OG_3)) {
    if (INFILE(_CR_OG)) {
      INTERESTING(lDebug ? "OpenGroup(5)" : "OpenGroup");
    }
    else {
      INTERESTING(lDebug ? "OG-style(5)" : "OpenGroup-style");
    }
  }
  else if (INFILE(_LT_OG_PROP)) {
    if (!lmem[_mXOPEN] && INFILE(_CR_XOPEN)) {
      INTERESTING("XOPEN-EULA");
      lmem[_mXOPEN] = 1;
    }
    else if (INFILE(_CR_OG)) {
      INTERESTING("OpenGroup-Proprietary");
    }
    else {
      INTERESTING("Proprietary!");
    }
  }
  else if (INFILE(_LT_X11_1)) {
    if (mCR_X11()) {
      INTERESTING(lDebug ? "X11(2)" : "X11");
    }
    else {
      INTERESTING(lDebug ? "X11-style(2)" : "X11-style");
    }
  }
  else if (INFILE(_LT_X11_2)) {
    if (mCR_X11()) {
      INTERESTING(lDebug ? "X11(3)" : "X11");
    }
    else {
      INTERESTING(lDebug ? "X11-style(3)" : "X11-style");
    }
  }
  else if (INFILE(_LT_X11_3)) {
    if (mCR_X11()) {
      INTERESTING(lDebug ? "X11(4)" : "X11");
    }
    else {
      INTERESTING(lDebug ? "X11-style(4)" : "X11-style");
    }
  }
  else if (INFILE(_LT_X11_4)) {
    if (mCR_X11()) {
      INTERESTING(lDebug ? "X11(5)" : "X11");
    }
    else {
      INTERESTING(lDebug ? "X11-style(5)" : "X11-style");
    }
  }
  else if (INFILE(_LT_X11_STYLE)) {
    INTERESTING(lDebug ? "X11-style(6)" : "X11-style");
  }
  /*
   * NTP License
   */
  if (INFILE(_TITLE_NTP) || INFILE(_LT_NTP)) {
    INTERESTING("NTP");
    lmem[_mNTP] = 1;
  }

  /** MirOS License (MirOS) */
  if (INFILE(_TITLE_MIROS)) { 
    INTERESTING("MirOS"); 
    lmem[_mMIT] = 1;
  }

  /** Libpng license */
  if (INFILE(_TITLE_LIBPNG)) {
    INTERESTING("Libpng");
  }
  else if (INFILE(_LT_W3C_1)) {
    if (INFILE(_CR_W3C)) {
      INTERESTING(lDebug ? "W3C(1)" : "W3C");
    }
    else {
      INTERESTING(lDebug ? "W3C-style(1)" : "W3C-style");
    }
    lmem[_fW3C] = 1;
  }
  else if (!lmem[_mNTP] && !lmem[_mPYTHON] && !lmem[_fBSD] && INFILE(_LT_W3C_2)) {
    if (INFILE(_CR_W3C)) {
      INTERESTING(lDebug ? "W3C(2)" : "W3C");
    }
    else {
      INTERESTING(lDebug ? "W3C-style(2)" : "W3C-style");
    }
    lmem[_fW3C] = 1;
  }
  else if (INFILE(_LT_W3C_3)) {
    if (INFILE(_CR_W3C)) {
      INTERESTING(lDebug ? "W3C(3)" : "W3C");
    }
    if (INFILE(_LT_W3Cref4)) {
      INTERESTING(lDebug ? "W3C(ref4)" : "W3C");
    }
    else {
      INTERESTING(lDebug ? "W3C-style(3)" : "W3C-style");
    }
    lmem[_fW3C] = 1;
  }
  else if (INFILE(_LT_W3C_4)) {
    if (INFILE(_CR_W3C)) {
      INTERESTING(lDebug ? "W3C(4)" : "W3C");
    }
    else {
      INTERESTING(lDebug ? "W3C-style(4)" : "W3C-style");
    }
    lmem[_fW3C] = 1;
  }
  else if (INFILE(_LT_W3C_5)) {
    if (INFILE(_CR_W3C)) {
      INTERESTING(lDebug ? "W3C(5)" : "W3C");
    }
    else {
      INTERESTING(lDebug ? "W3C-style(5)" : "W3C-style");
    }
    lmem[_fW3C] = 1;
  }
  else if (INFILE(_LT_W3C_6)) {
    if (INFILE(_CR_W3C)) {
      INTERESTING(lDebug ? "W3C(6)" : "W3C");
    }
    else {
      INTERESTING(lDebug ? "W3C-style(6)" : "W3C-style");
    }
    lmem[_fW3C] = 1;
  }
  else if (INFILE(_LT_W3C_7)) {
    if (INFILE(_CR_W3C)) {
      INTERESTING(lDebug ? "W3C(7)" : "W3C");
    }
    else {
      INTERESTING(lDebug ? "W3C-style(7)" : "W3C-style");
    }
    lmem[_fW3C] = 1;
  }
  else if (INFILE(_LT_W3Cref1)) {
    INTERESTING(lDebug ? "W3C(ref1)" : "W3C");
    lmem[_fW3C] = 1;
  }
  else if (INFILE(_LT_W3Cref2)) {
    INTERESTING(lDebug ? "W3C(ref2)" : "W3C");
    lmem[_fW3C] = 1;
  }
  else if (INFILE(_LT_W3Cref3)) {
    INTERESTING(lDebug ? "W3C(ref3)" : "W3C");
    lmem[_fW3C] = 1;
  }
  else if (INFILE(_LT_W3Cref4)) {
    INTERESTING(lDebug ? "W3C(ref4)" : "W3C");
    lmem[_fW3C] = 1;
  }
  else if (URL_INFILE(_URL_W3C_IP)) {
    INTERESTING(lDebug ? "W3C-IP(url)" : "W3C-IP");
    lmem[_fW3C] = 1;
  }
  else if (URL_INFILE(_URL_W3C)) {
    INTERESTING(lDebug ? "W3C(url)" : "W3C");
    lmem[_fW3C] = 1;
  }
  else if (!lmem[_mMIT] && INFILE(_LT_MIT_3)) {
    if (mCR_MIT()) {
      INTERESTING(lDebug ? "MIT(8)" : "MIT");
    }
    else {
      INTERESTING(lDebug ? "MIT-style(6)" : "MIT-style");
    }
    lmem[_mMIT] = 1;
  }
  else if (!lmem[_mMIT] && INFILE(_LT_MIT_4)) {
    if (mCR_FSF()) {
      INTERESTING(lDebug ? "FSF(7)" : "FSF");
    }
    else if (mCR_MIT()) {
      INTERESTING(lDebug ? "MIT(9)" : "MIT");
    }
    else {
      INTERESTING(lDebug ? "MIT-style(7)" : "MIT-style");
    }
    lmem[_mMIT] = 1;
  }
  else if (!lmem[_mMIT] && INFILE(_LT_MIT_8)) {
    if (INFILE(_CR_VIXIE)) {
      INTERESTING("Vixie");
    }
    else if (!lmem[_mISC] && INFILE(_CR_ISC)) {
      INTERESTING("ISC");
    }
    else if (mCR_MIT()) {
      INTERESTING(lDebug ? "MIT(10)" : "MIT");
    }
    else {
      INTERESTING(lDebug ? "MIT-style(8)" : "MIT-style");
    }
    lmem[_mMIT] = 1;
  }
  else if (INFILE(_LT_MIT_9)) {
    if (INFILE(_CR_SLEEPYCAT)) {
      INTERESTING(lDebug ? "Sleepycat(2)" : "Sleepycat");
    }
    else if (INFILE(_CR_BSDCAL)) {
      INTERESTING(lDebug ? "BSD(mit)" : "BSD");
      lmem[_fBSD] = 1;
    }
    else if (INFILE(_CR_SUN)) {
      INTERESTING(lDebug ? "SUN(mit)" : "SUN");
      lmem[_fBSD] = 1;
    }
    else if (!lmem[_mMIT] && mCR_MIT()) {
      INTERESTING(lDebug ? "MIT(11)" : "MIT");
      lmem[_mMIT] = 1;
    }
    else if (!lmem[_mMIT]) {
      INTERESTING(lDebug ? "MIT-style(9)" : "MIT-style");
      lmem[_mMIT] = 1;
    }
  }
  else if (INFILE(_LT_MIT_10)) {
    if (!lmem[_mMIT] && mCR_MIT()) {
      INTERESTING(lDebug ? "MIT(12)" : "MIT");
      lmem[_mMIT] = 1;
    }
    else {
      INTERESTING(lDebug ? "MIT-style(10)" : "MIT-style");
      lmem[_mMIT] = 1;
    }
  }
  else if (INFILE(_LT_MIT_11) && !INFILE(_TITLE_MIROS)) {
    INTERESTING(lDebug ? "MIT-style(11)" : "MIT-style");
    lmem[_mMIT] = 1;
  }
  else if (!lmem[_mMIT] && INFILE(_LT_MITDOC)) {
    if (mCR_MIT()) {
      INTERESTING(lDebug ? "MIT(13)" : "MIT");
    }
    else {
      INTERESTING(lDebug ? "MIT-style(11)" : "MIT-style");
    }
    lmem[_mMIT] = 1;
  }
  else if (INFILE(_LT_MPL_OR_MITX)) {
    if (!lmem[_mMPL]) {
      cp = MPLVERS();
      INTERESTING(lDebug ? "MPL(with-MIT)" : cp);
      lmem[_mMPL] = 1;
    }
    if (!lmem[_mMIT]) {
      INTERESTING(lDebug ? "MIT(with-MPL)" : "MIT");
      lmem[_mMIT] = 1;
    }
  }
  else if (!lmem[_mMIT] && URL_INFILE(_URL_MIT)) {
    INTERESTING(lDebug ? "MIT(url)" : "MIT");
    lmem[_mMIT] = 1;
  }
  /*
   * Generic CopyLeft licenses
   */
  if (INFILE(_LT_COPYLEFT_1)) {
    INTERESTING("CopyLeft[1]");
  }
  else if (INFILE(_LT_COPYLEFT_2)) {
    INTERESTING("CopyLeft[2]");
  }
  /*
   * OpenContent license
   */
  if (INFILE(_LT_OPENCONTENT)) {
    if (INFILE(_TITLE_OPENCL10)) {
      INTERESTING("OCL-1.0");
    }
    else if (INFILE(_TITLE_OPENCL)) {
      INTERESTING("OCL");
    }
    else {
      INTERESTING("OCL-style");
    }
  }
  /*
   * Software in the Public Interest (Debian), aka SPI
   * FIX-ME: look for Red Hat and Novell/SUSE copyrights/trademarks here!
   */
  if (!lmem[_fGPL] && INFILE(_LT_SPI)) {
    if (mCR_FSF()) {
      INTERESTING(lDebug ? "FSF(8)" : "FSF");
    }
    else if (INFILE(_CR_SPI)) {
      INTERESTING("Debian-SPI");
    }
    else {
      INTERESTING("Debian-SPI-style");
    }
  }
  /*
   * jpeg/netpbm and png/zlib and others...
   */
  if (INFILE(_TITLE_ZLIB)) {
    INTERESTING("Zlib");
  }
  else if (INFILE(_TITLE_LIBPNG)) {
    INTERESTING("Libpng");
  }
  else if (INFILE(_LT_JPEG_1)) {
    INTERESTING(lDebug ? "JPEG(1)" : "JPEG/netpbm");
  }
  else if (INFILE(_LT_JPEG_2)) {
    INTERESTING(lDebug ? "JPEG(2)" : "JPEG/netpbm");
  }
  else if (INFILE(_LT_PNG_ZLIB_1)) {
    INTERESTING(lDebug ? "ZLIB(1)" : "Zlib");
  }
  else if (INFILE(_LT_PNG_ZLIBref4) && !INFILE(_LT_PNG_ZLIBref4_EXHIBIT)) {
    INTERESTING(lDebug ? "ZLIB(6)" : "Zlib");
  }
  else if (!lmem[_fW3C] && INFILE(_LT_PNG_ZLIB_2)) {
    INTERESTING(lDebug ? "ZLIB(2)" : "Zlib");
  }
  else if (INFILE(_LT_PNG_ZLIBref1)) {
    INTERESTING(lDebug ? "ZLIB(3)" : "Zlib");
  }
  else if (INFILE(_LT_PNG_ZLIBref2)) {
    INTERESTING(lDebug ? "ZLIB(4)" : "Zlib");
  }
  else if (INFILE(_LT_PNG_ZLIBref3)) { /* might be zlib/libpng license, not sure */
    INTERESTING(lDebug ? "ZLIB(5)" : "Zlib-possibility");
  }
  else if (!LVAL(_TEXT_GNU_LIC_INFO) && INFILE(_URL_ZLIB)) {
    INTERESTING(lDebug ? "ZLIB(url)" : "Zlib");
  }

  if (INFILE(_LT_INFO_ZIP) || INFILE(_URL_INFO_ZIP)) {
    INTERESTING("info-zip");
  }

  /*
   * IETF (primarily docs, RFCs, and protocol/standard proposals) .  This
   * one is a little strange as text-formatters that print this license
   * will occasionally split the text across a page boundary.  Here we
   * look for 2 separate footprints.
   */
  if (INFILE(_LT_IETF_1) || INFILE(_LT_IETF_2) || INFILE(_LT_IETF_3) ||
      INFILE(_LT_IETF_4)) {
    if (mCR_IETF()) {
      INTERESTING("IETF");
    }
    else if (INFILE(_CR_OASIS)) {
      INTERESTING("OASIS");
    }
    else {
      INTERESTING("IETF-style");
    }
  }
  /*
   * MPL (Mozilla)
   * ... Sun SISSL and one Mozilla licensing derivative share wording
   */
  if (INFILE(_LT_MPL_OR)) {
    cp = MPLVERS(); /* NPL, too */
    INTERESTING(lDebug ? "MPL/NPL#2" : cp);
    lmem[_mMPL] = 1;
  }
  if (INFILE(_LT_CPALref)) {
    if (INFILE(_TITLE_CPAL10)) {
      INTERESTING(lDebug ? "CPAL_v1.0(#2)" : "CPAL-1.0");
      lmem[_mMPL] = 1;
      lmem[_fATTRIB] = 1;
    }
    else if (INFILE(_TITLE_CPAL)) {
      INTERESTING(lDebug ? "CPAL(#2)" : "CPAL");
      lmem[_mMPL] = 1;
      lmem[_fATTRIB] = 1;
    }
  }
  if (!lmem[_mMPL] && INFILE(_LT_MPL_2)) {
    if (INFILE(_TITLE_SISSL)) {
      cp = SISSLVERS();
      INTERESTING(lDebug ? "SISSL(MPL)" : cp);
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_TITLE_SUN_PL10)) {
      INTERESTING("SPL-1.0");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_TITLE_SUN_PL)) {
      INTERESTING("SPL");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_TITLE_IDPL_V10)) {
      INTERESTING("IDPL-1.0");
      lmem[_mMPL] = 1;
    }
    else if (INFILE(_TITLE_IDPL)) {
      INTERESTING("IDPL");
      lmem[_mMPL] = 1;
    }
    else if (INFILE(_TITLE_INTERBASE)) {
      INTERESTING("Interbase-PL");
      lmem[_mMPL] = 1;
    }
    else if (INFILE(_TITLE_NETIZEN)) {
      INTERESTING("Netizen");
      lmem[_mMPL] = 1;
    }
    else if (INFILE(_TITLE_TPL10)) {
      INTERESTING(lDebug ? "TPL(v1.0#1)" : "MPL/TPL-1.0");
      lmem[_mMPL] = 1;
    }
    else if (INFILE(_TITLE_TPL)) {
      INTERESTING(lDebug ? "TPL(#1)" : "MPL/TPL");
      lmem[_mMPL] = 1;
    }
    else if (INFILE(_PHR_CDDL)) {
      if (INFILE(_LT_CDDL)) {
        cp = CDDLVERS();
        INTERESTING(lDebug ? "CDDL" : cp);
      }
      else if (INFILE(_LT_CDDL10ref)) {
        cp = CDDLVERS();
        INTERESTING(lDebug ? "CDDL(v1-ref1)" : cp);
      }
      else {
        cp = CDDLVERS();
        INTERESTING(lDebug ? "CDDL(phr)" : cp);
      }
      lmem[_mCDDL] = 1;
    }
    else if (INFILE(_TITLE_GSOAP_V13)) {
      INTERESTING("gSOAP-1.3b");
      lmem[_mGSOAP] = 1;
    }
    else if (INFILE(_TITLE_GSOAP)) {
      INTERESTING("gSOAP");
      lmem[_mGSOAP] = 1;
    }
    else if (INFILE(_TITLE_FLASH2XML10)) {
      INTERESTING("Flash2xml-1.0");
    }
    else if (INFILE(_TITLE_NOKIA10A)) {
      INTERESTING("Nokia");
      lmem[_mMPL] = 1;
    }
    else if (INFILE(_LT_NOKIA)) {
      INTERESTING("Nokia");
      lmem[_mMPL] = 1;
    }
    else if (INFILE(_TITLE_CUA10)) {
      INTERESTING("CUA-OPL-1.0");
    }
    else if (INFILE(_TITLE_OPENPL10)) {
      INTERESTING("Open-PL-1.0");
    }
    else if (INFILE(_TITLE_SNIA_V11)) {
      INTERESTING("SNIA-1.1");
    }
    else if (INFILE(_TITLE_SNIA_V10)) {
      INTERESTING("SNIA-1.0");
    }
    else if (INFILE(_TITLE_OPENPL)) {
      INTERESTING(lDebug ? "Open-PL(title)" : "Open-PL");
    }
    else if (INFILE(_TITLE_CPAL10)) {
      INTERESTING(lDebug ? "CPAL_v1.0(#1)" : "CPAL-1.0");
      lmem[_mMPL] = 1;
      lmem[_fATTRIB] = 1;
    }
    else if (INFILE(_TITLE_CPAL)) {
      INTERESTING(lDebug ? "CPAL(#1)" : "CPAL");
      lmem[_mMPL] = 1;
      lmem[_fATTRIB] = 1;
    }
    else if (HASTEXT(_TEXT_MOZNET, REG_EXTENDED)) {
      if (INFILE(_TITLE_SUGARCRM_PL)) {
        INTERESTING("SugarCRM-1.1.3");
        lmem[_mMPL] = 1;
        lmem[_fATTRIB] = 1;
      }
      else if (!lmem[_mMPL] && INFILE(_TITLE_MOZNET_PL)) {
        cp = MPLVERS(); /* NPL, too */
        INTERESTING(lDebug ? "MPL/NPL#1" : cp);
        lmem[_mMPL] = 1;
      }
    }
    else if (!lmem[_mCDDL] && URL_INFILE(_URL_CDDL_V1)) {
      cp = CDDLVERS();
      INTERESTING(lDebug ? "CDDL(url-v1#1)" : cp);
      lmem[_mCDDL] = 1;
    }
    else if (!lmem[_mCDDL] && URL_INFILE(_URL_CDDL)) {
      cp = CDDLVERS();
      INTERESTING(lDebug ? "CDDL(url#1)" : cp);
      lmem[_mCDDL] = 1;
    }
    else if (INFILE(_TITLE_RHeCos_v11)) {
      INTERESTING("RHeCos-1.1");
    }
    else {
      INTERESTING("MPL-style");
      lmem[_mMPL] = 1;
    }
  }
  else if (!lmem[_mMPL] && (INFILE(_LT_NPLref) || INFILE(_LT_NPL_1))) {
    cp = MPLVERS(); /* NPL, too */
    INTERESTING(lDebug ? "MPL/NPL#3" : cp);
    lmem[_mMPL] = 1;
  }
  else if (!lmem[_mMPL] && INFILE(_LT_MPLref1)) {
    cp = MPLVERS(); /* NPL, too */
    INTERESTING(lDebug ? "MPL/NPL-ref#1" : cp);
    lmem[_mMPL] = 1;
  }
  else if (!lmem[_mMPL] && INFILE(_LT_IDPLref)) {
    if (INFILE(_TITLE_IDPL_V10)) {
      INTERESTING(lDebug ? "IDPL-v1(ref)" : "IDPL-1.0");
    }
    else {
      INTERESTING(lDebug ? "IDPL(ref)" : "IDPL");
    }
    lmem[_mMPL] = 1;
  }
  else if (!lmem[_mMPL] && !lmem[_mLIBRE] &&
      !LVAL(_TEXT_GNU_LIC_INFO) && INFILE(_LT_MPLref2)) {
    cp = MPLVERS(); /* NPL, too */
    INTERESTING(lDebug ? "MPL/NPL-ref#2" : cp);
    lmem[_mMPL] = 1;
  }
  else if (!lmem[_mMPL] && !lmem[_mLIBRE] &&
      !LVAL(_TEXT_GNU_LIC_INFO) && INFILE(_TITLE_MOZNET_PL)) {
    cp = MPLVERS(); /* NPL, too */
    INTERESTING(lDebug ? "MPL/NPL#4" : cp);
    lmem[_mMPL] = 1;
  }
  else if (!lmem[_mMPL] && INFILE(_TITLE_NETSCAPE_EULA)) {
    INTERESTING("NPL-EULA");
    lmem[_mMPL] = 0;
  }
  else if (INFILE(_LT_NPL_2)) {
    INTERESTING(lDebug ? "NPL(#1)" : "NPL");
    lmem[_mMPL] = 0;
  }
  /*
   * Other CDDL
   */
  if (!lmem[_mCDDL]) {
    if (INFILE(_LT_CDDL10ref)) {
      cp = CDDLVERS();
      INTERESTING(lDebug ? "CDDL(v1-ref2)" : cp);
      lmem[_mCDDL] = 1;
    }
    else if (INFILE(_LT_CDDLref)) {
      cp = CDDLVERS();
      INTERESTING(lDebug ? "CDDL(ref)" : cp);
      lmem[_mCDDL] = 1;
    }
    else if (URL_INFILE(_URL_CDDL_V1)) {
      cp = CDDLVERS();
      INTERESTING(lDebug ? "CDDL(url-v1#2)" : cp);
      lmem[_mCDDL] = 1;
    }
    else if (URL_INFILE(_URL_CDDL)) {
      cp = CDDLVERS();
      INTERESTING(lDebug ? "CDDL(url#2)" : cp);
      lmem[_mCDDL] = 1;
    }
  }
  /*
   * Microsoft licenses: open and proprietary/EULA
   */
  if (INFILE(_LT_MSCORP_SSLref)) {
    INTERESTING(lDebug ? "MS-SSL(ref)" : "MS-SSL");
    lmem[_fMSCORP] = 1;
  }
  if (INFILE(_LT_MSCORP_PL)) {
    int ms_l = INFILE(_LT_MSCORP_LIMITED);
    int ms_r = INFILE(_LT_MSCORP_RL);
    if (ms_r && ms_l) {
      INTERESTING("MS-LRL");
    }
    else if (ms_r) {
      INTERESTING(lDebug ? "MS-RL(#1)" : "MS-RL");
    }
    else if (ms_l) {
      INTERESTING("MS-LPL");
    }
    else {
      INTERESTING(lDebug ? "MS-PL(#1)" : "MS-PL");
    }
  }
  if (INFILE(_TEXT_MICROSOFT)) {
    if (INFILE(_LT_MSCORP_INDEMNIFY)) {
      INTERESTING("MS-indemnity");
      lmem[_fMSCORP] = 1;
    }
    else if (INFILE(_LT_MSCORP_IP_1)) {
      INTERESTING(lDebug ? "MS-IP(1)" : "MS-IP");
      lmem[_fMSCORP] = 1;
    }
    else if (INFILE(_LT_MSCORP_IP_2)) {
      INTERESTING(lDebug ? "MS-IP(2)" : "MS-IP");
      lmem[_fMSCORP] = 1;
    }
    else if (INFILE(_LT_MSCORP_PLref1)) {
      INTERESTING(lDebug ? "MS-PL(ref1)" : "MS-PL");
      lmem[_fMSCORP] = 1;
    }
    else if (INFILE(_LT_MSCORP_PLref2)) {
      INTERESTING(lDebug ? "MS-PL(ref2)" : "MS-PL");
      lmem[_fMSCORP] = 1;
    }
    else if (INFILE(_LT_MSCORP_RLref)) {
      INTERESTING(lDebug ? "MS-RL(ref)" : "MS-RL");
      lmem[_fMSCORP] = 1;
    }
    else if (INFILE(_LT_MSCORP_REFLIC)) {
      INTERESTING("MRL");
      lmem[_fMSCORP] = 1;
    }
    else if (INFILE(_LT_MSCORP_EULA_1) ||
        INFILE(_TITLE_MSCORP_EULA)) {
      INTERESTING(lDebug ? "MS-EULA(1)" : "MS-EULA");
      lmem[_fMSCORP] = 1;
    }
    else if (INFILE(_LT_MSCORP_EULA_2)) {
      INTERESTING(lDebug ? "MS-EULA(2)" : "MS-EULA");
      lmem[_fMSCORP] = 1;
    }
    else if (INFILE(_LT_MSCORP_EULA_3)) {
      INTERESTING(lDebug ? "MS-EULA(3)" : "MS-EULA");
      lmem[_fMSCORP] = 1;
    }
    else if (INFILE(_LT_MSCORP_EULA_4)) {
      INTERESTING(lDebug ? "MS-EULA(4)" : "MS-EULA");
      lmem[_fMSCORP] = 1;
    }
    else if (INFILE(_LT_MSCORP_EULA_5)) {
      INTERESTING(lDebug ? "MS-EULA(5)" : "MS-EULA");
      lmem[_fMSCORP] = 1;
    }
    else if (INFILE(_LT_MSCORP_1)) {
      INTERESTING(lDebug ? "MS(1)" : "Microsoft");
      lmem[_fMSCORP] = 1;
    }
    else if (INFILE(_LT_MSCORP_2)) {
      INTERESTING(lDebug ? "MS(2)" : "Microsoft");
      lmem[_fMSCORP] = 1;
    }
    else if (INFILE(_LT_MSCORP_3)) {
      INTERESTING(lDebug ? "MS(3)" : "Microsoft");
      lmem[_fMSCORP] = 1;
    }
    else if (INFILE(_LT_MSCORP_4)) {
      INTERESTING(lDebug ? "MS(4)" : "Microsoft");
      lmem[_fMSCORP] = 1;
    }
    else if (INFILE(_LT_MSCORP_5)) {
      INTERESTING(lDebug ? "MS(5)" : "Microsoft");
      lmem[_fMSCORP] = 1;
    }
    else if (INFILE(_LT_MSCORPref1)) {
          INTERESTING("Microsoft");
          lmem[_fMSCORP] = 1;
         }
  }
  /*
   * Santa Cruz Operation (SCO)
   */
  if (INFILE(_LT_SCO_COMM) && INFILE(_CR_SCO)) {
    INTERESTING("SCO(commercial)");
  }
  /*
   * Zonealarm
   */
  if (INFILE(_LT_ZONEALARM) && INFILE(_TITLE_ZONEALARM_EULA)) {
    INTERESTING("ZoneAlarm-EULA");
  }
  /*
   * Ximian
   */
  if (HASTEXT(_TEXT_XIMIAN, 0)) {
    if (INFILE(_CR_XIMIAN)) {
      if (INFILE(_LT_XIMIAN)) {
        if (INFILE(_TITLE_XIMIANLOGO10)) {
          INTERESTING("Ximian-1.0");
        }
        else if (INFILE(_TITLE_XIMIANLOGO)) {
          INTERESTING("Ximian");
        }
      }
    }
  }
  /*
   * Xerox
   */
  if (HASTEXT(_TEXT_XEROX, 0)) {
    if (INFILE(_LT_XEROX_1) || INFILE(_LT_XEROX_2)) {
      if (INFILE(_CR_XEROX_1) || INFILE(_CR_XEROX_2)) {
        INTERESTING("Xerox");
      }
      else {
        INTERESTING("Xerox-style");
      }
    }
  }
  /*
   * Artifex Software
   */
  if (INFILE(_LT_ARTIFEX) && INFILE(_CR_ARTIFEX)) {
    INTERESTING("Artifex");
  }
  /*
   * AGE logic
   */
  if (INFILE(_LT_AGE) && INFILE(_CR_AGE)) {
    INTERESTING("AGE-Logic");
  }
  /*
   * OpenSSL
   */
  if (INFILE(_LT_OPENSSLref1) || INFILE(_LT_OPENSSLref2) ||
      INFILE(_LT_OPENSSLref3) || INFILE(_LT_OPENSSLref4)) {
    INTERESTING(lDebug ? "OpenSSL(ref)" : "OpenSSL");
  }
  /*
   * Dual OpenSSL SSLeay
   */
  if (INFILE(_LT_COMBINED_OPENSSL_SSLEAY)) {
    INTERESTING("Combined_OpenSSL+SSLeay");
  }
  /*
   * Ruby
   */
  if (INFILE(_LT_RUBY)) {
    INTERESTING("Ruby");
    lmem[_fRUBY] = 1;
  }
  else if (INFILE(_LT_RUBYref1)) {
    INTERESTING(lDebug ? "Ruby(ref1)" : "Ruby");
    if (!lmem[_mGPL]) {
      INTERESTING(lDebug ? "GPL(Ruby1)" : "GPL");
    }
  }
  else if (INFILE(_LT_RUBYref2)) {
    INTERESTING(lDebug ? "Ruby(ref2)" : "Ruby");
    if (!lmem[_mGPL]) {
      INTERESTING(lDebug ? "GPL(Ruby2)" : "GPL");
    }
  }
  else if (INFILE(_LT_RUBYref3)) {
    INTERESTING(lDebug ? "Ruby(ref3)" : "Ruby");
    if (!lmem[_mGPL]) {
      INTERESTING(lDebug ? "GPL(Ruby3)" : "GPL");
    }
  }
  else if (INFILE(_LT_RUBYref4)) {
    INTERESTING(lDebug ? "Ruby(ref4)" : "Ruby");
    if (!lmem[_mGPL]) {
      INTERESTING(lDebug ? "GPL(Ruby4)" : "GPL");
    }
  }
  else if (INFILE(_LT_RUBYref5)) {
    INTERESTING(lDebug ? "Ruby(ref5)" : "Ruby");
    if (!lmem[_mGPL]) {
      INTERESTING(lDebug ? "GPL(Ruby5)" : "GPL");
    }
  }
  /*
   * Python and EGenix.com look a bit alike
   * Q: should all these Python checks be a family-check like OpenLDAP?
   */
  if (INFILE(_LT_EGENIX_COM)) {
    INTERESTING("eGenix");
  }
  else if (INFILE(_LT_PYTHON_4)) {
        INTERESTING("Python");
        lmem[_mPYTHON] = 1;
       }
  else if (!lmem[_mPYTHON] && lmem[_mPYTH_TEXT]) {
    if (INFILE(_LT_PYTHON_1) || INFILE(_LT_PYTHON_2)) {
      if (INFILE(_LT_CNRI_PYTHON_GPL)) {
        INTERESTING("CNRI-Python-GPL-Compatible");
      }
      else if (INFILE(_CR_PYTHON) || INFILE(_TITLE_PYTHON)) {
        cp = PYTHVERS();
        INTERESTING(lDebug ? "Python(1)" : cp);
      }
      else if (INFILE(_LT_CNRI_PYTHON)) {
        INTERESTING("CNRI-Python");
      }
      else {
        INTERESTING("Python-style");
      }
      lmem[_mPYTHON] = 1;
    }
    else if (INFILE(_LT_PYTHON_3)) {
      cp = PYTHVERS();
      INTERESTING(lDebug ? "Python(4)" : cp);
      lmem[_mPYTHON] = 1;
    }
    else if (INFILE(_LT_PYTHONSTYLEref)) {
      cp = PYTHVERS();
      INTERESTING(lDebug ? "Python(ref1)" : "Python-style");
      lmem[_mPYTHON] = 1;
    }
    else if (!lmem[_mLIBRE] && (INFILE(_LT_PYTHONref1) ||
        INFILE(_LT_PYTHONref2))) {
      cp = PYTHVERS();
      INTERESTING(lDebug ? "Python(ref2)" : cp);
      lmem[_mPYTHON] = 1;
    }
    else if (!lmem[_mLIBRE] && !lmem[_fREAL] &&
        !LVAL(_TEXT_GNU_LIC_INFO) && INFILE(_LT_PYTHONref3)) {
      cp = PYTHVERS();
      INTERESTING(lDebug ? "Python(ref3)" : cp);
      lmem[_mPYTHON] = 1;
    }
    else if (!lmem[_mPYTHON] && URL_INFILE(_URL_PYTHON)) {
      cp = PYTHVERS();
      INTERESTING(lDebug ? "Python(url)" : cp);
      lmem[_mPYTHON] = 1;
    }
  }
  /*
   * Intel
   */
  if (HASTEXT(_TEXT_INTELCORP, 0)) {
    if (INFILE(_LT_INTEL_1)) {
      if (INFILE(_LT_INTEL_FW)) {
        INTERESTING(lDebug ? "Intel(2)" :
            "Intel-only-FW");
      }
      else {
        INTERESTING(lDebug ? "Intel(3)" : "Intel");
      }
    }
    else if (INFILE(_LT_INTEL_2)) {
      INTERESTING(lDebug ? "Intel(4)" : "Intel");
    }
    else if (INFILE(_LT_INTEL_3)) {
      INTERESTING(lDebug ? "Intel(5)" : "Intel");
    }
    else if (INFILE(_LT_INTEL_4)) {
      INTERESTING(lDebug ? "Intel(6)" : "Intel");
    }
    else if (INFILE(_LT_INTEL_RESTRICT)) {
      INTERESTING("Intel(RESTRICTED)");
    }
  }
  else if (INFILE(_LT_INTEL_5)) {
    INTERESTING(lDebug ? "CPL(Intel)" : "CPL");
    INTERESTING(lDebug ? "Intel(7)" : "Intel");
  }
  else if (INFILE(_LT_INTEL_EULA)) {
    INTERESTING("Intel-EULA");
  }
  /*
   * Bellcore
   */
  if (INFILE(_LT_BELLCORE)) {
    if  (INFILE(_CR_BELLCORE)) {
      INTERESTING("Bellcore");
    }
    else {
      INTERESTING("Bellcore-style");
    }
  }
  /*
   * Cisco systems
   */
  if (INFILE(_LT_CISCO)) {
    if (INFILE(_CR_CISCO)) {
      INTERESTING("Cisco");
    }
    else {
      INTERESTING("Cisco-style");
    }
  }
  /*
   * HP
   */
  if (INFILE(_LT_HP_DEC)) {
    if (mCR_HP()) {
      INTERESTING(lDebug ? "HP(8)" : "HP");
    }
    else if (INFILE(_CR_ADOBE)) {
      INTERESTING(lDebug ? "Adobe(8)" : "Adobe");
    }
    else {
      INTERESTING(lDebug ? "HP-DEC-style(1)" : "HP-DEC-style");
    }
  }
  else if (HASTEXT(_TEXT_HP, REG_EXTENDED)) {
    if (INFILE(_LT_HP_1)) {
      INTERESTING(lDebug ? "HP(2)" : "HP");
    }
    else if (INFILE(_LT_HP_3)) {
      INTERESTING(lDebug ? "HP(3)" : "HP");
    }
    else if (INFILE(_LT_HP_4)) {
      INTERESTING(lDebug ? "HP(4)" : "HP");
    }
    else if (INFILE(_LT_HP_5)) {
      INTERESTING(lDebug ? "HP(5)" : "HP");
    }
    else if (INFILE(_LT_HP_6)) {
      INTERESTING(lDebug ? "HP(6)" : "HP");
    }
    else if (INFILE(_LT_HP_7)) {
      INTERESTING(lDebug ? "HP(7)" : "HP");
    }
    else if (INFILE(_LT_COMPAQ_1)) {
      INTERESTING(lDebug ? "Compaq(1)" : "HP-Compaq");
    }
    else if (INFILE(_LT_HP_EULA1)) {
      INTERESTING(lDebug ? "HP-EULA(1)" : "HP-EULA");
    }
    else if (INFILE(_LT_HP_EULA2)) {
      INTERESTING(lDebug ? "HP-EULA(2)" : "HP-EULA");
    }
    else if (INFILE(_LT_HP_EULA3)) {
      INTERESTING(lDebug ? "HP-EULA(3)" : "HP-EULA");
    }
    else if (INFILE(_LT_HP_EULA4)) {
      INTERESTING(lDebug ? "HP-EULA(4)" : "HP-EULA");
    }
    else if (INFILE(_LT_COMPAQ_EULA)) {
      INTERESTING(lDebug ? "Compaq(EULA)" : "HP-Compaq");
    }
    else if (INFILE(_LT_HP_PROPRIETARY_1)) {
      INTERESTING(lDebug ? "HP-prop(1)" : "HP-Proprietary");
    }
    else if (INFILE(_LT_HP_PROPRIETARY_2)) {
      INTERESTING(lDebug ? "HP-prop(2)" : "HP-Proprietary");
    }
    else if (INFILE(_LT_HP_PROPRIETARY_3)) {
      INTERESTING(lDebug ? "HP-prop(3)" : "HP-Proprietary");
    }
    else if (INFILE(_LT_HP_IBM_1)) {
      INTERESTING(lDebug ? "HP+IBM(1)" : "HP+IBM");
    }
    else if (INFILE(_LT_HP_IBM_2)) {
      INTERESTING(lDebug ? "HP+IBM(2)" : "HP+IBM");
    }
    else if (!lmem[_mHP] && INFILE(_CR_DEC) && INFILE(_LT_DEC_1)) {
      INTERESTING(lDebug ? "HP-DEC(3)" : "HP-DEC");
      lmem[_mHP] = 1;
    }
    else if (!lmem[_mHP] && INFILE(_CR_DEC) && INFILE(_LT_DEC_2)) {
      INTERESTING(lDebug ? "HP-DEC(4)" : "HP-DEC");
      lmem[_mHP] = 1;
    }
    else if (INFILE(_LT_EDS_1) && INFILE(_CR_EDS)) {
      INTERESTING(lDebug ? "HP-EDS(1)" : "HP");
    }
    else if (INFILE(_LT_EDS_2) && INFILE(_CR_EDS)) {
      INTERESTING(lDebug ? "HP-EDS(2)" : "HP");
    }
  }
  else if (!lmem[_mHP] && INFILE(_LT_DEC_1)) {
    INTERESTING(lDebug ? "HP-DEC-style(2)" : "HP-DEC-style");
  }
  else if (!lmem[_mHP] && INFILE(_LT_DEC_2)) {
    INTERESTING(lDebug ? "HP-DEC-style(3)" : "HP-DEC-style");
  }
  else if (INFILE(_LT_HP_4)) {
    INTERESTING(lDebug ? "HP-style(1)" : "HP-style");
  }
  else if (INFILE(_LT_COMPAQ_1)) {
    INTERESTING(lDebug ? "HP-style(2)" : "HP-style");
  }
  else if (INFILE(_LT_EDS_1)) {
    INTERESTING(lDebug ? "HP-EDS(1#2)" : "HP");
  }
  else if (INFILE(_LT_EDS_2)) {
    INTERESTING(lDebug ? "HP-EDS(2#2)" : "HP");
  }

  /*
   * SUN Microsystems
   */
  if (!lmem[_mSUN] && (INFILE(_CR_SUN) || INFILE(_TEXT_MICROSYSTEMS))) {
    if (INFILE(_LT_SUN_PROPRIETARY)) {
      INTERESTING(lDebug ? "Sun(Prop)" : "Sun-Proprietary");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_1)) {
      INTERESTING(lDebug ? "Sun(3)" : "Sun");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_2)) {
      INTERESTING(lDebug ? "Sun(4)" : "Sun");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_3)) {
      INTERESTING(lDebug ? "Sun(5)" : "Sun");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_4)) {
      INTERESTING(lDebug ? "Sun(6)" : "Sun");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_5)) {
      INTERESTING(lDebug ? "Sun(7)" : "Sun");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_6)) {
      INTERESTING(lDebug ? "Sun(8)" : "Sun");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_NC)) {
      INTERESTING("Sun(Non-commercial)");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUNrestrict)) {
      INTERESTING("Sun(RESTRICTED)");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_BCLA_1) && INFILE(_TITLE_SUN_BCLA)) {
      INTERESTING(lDebug ? "BCLA(1)" : "Sun-BCLA");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_BCLA_2)) {
      INTERESTING(lDebug ? "BCLA(2)" : "Sun-BCLA");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_BCLAref)) {
      INTERESTING(lDebug ? "BCLA(ref)" : "Sun-BCLA");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_ENTITLE)) {
      INTERESTING(lDebug ? "Sun(entitlement)" : "Sun");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_ANYagreement)) {
      INTERESTING("Sun-EULA");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_RPC)) {
      INTERESTING("Sun-RPC");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_JAVA)) {
      INTERESTING("Sun-Java");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_IP)) {
      INTERESTING("Sun-IP");
      lmem[_mSUN] = 1;
      lmem[_fIP] = 1;
    }
    else if (INFILE(_LT_SUN_SCA)) {
      INTERESTING("Sun-SCA");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_SCSLref)) {
      if (INFILE(_TITLE_SCSL_V23)) {
        INTERESTING("SCSL-2.3");
      }
      else if (INFILE(_TITLE_SCSL_V30)) {
        INTERESTING("SCSL-3.0");
      }
      else {
        INTERESTING("SCSL");
      }
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_TITLE_SUN_SCSL_TSA) &&
        INFILE(_LT_SUN_SCSL_TSA)) {
      if (INFILE(_TITLE_TSA_10)) {
        INTERESTING("SCSL-TSA-1.0");
      }
      else {
        INTERESTING("SCSL-TSA");
      }
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_SISSLref1)
        || INFILE(_LT_SUN_SISSLref2)) {
      cp = SISSLVERS();
      INTERESTING(lDebug ? "SISSL(ref#1)" : cp);
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_PHR_SUN_TM)) {
      INTERESTING("Sun(tm)");
      lmem[_mSUN] = 1;
    }
  }
  else if (INFILE(_LT_SUN_PLref)) {
    INTERESTING(lDebug ? "Sun-PL(ref)" : "SPL");
    lmem[_mSUN] = 1;
  }
  else if (!lmem[_mSUN] && INFILE(_URL_SUN_BINARY_V150)) {
    INTERESTING("Sun-BCLA-1.5.0");
    lmem[_mSUN] = 1;
  }
  else if (!lmem[_mSUN] && INFILE(_URL_SUN_BINARY)) {
    INTERESTING(lDebug ? "BCLA(url)" : "Sun-BCLA");
    lmem[_mSUN] = 1;
  }
  else if (!lmem[_mSUN] && INFILE(_LT_SUN_GRAPHICS)) {
    INTERESTING(lDebug ? "Sun(8)" : "Sun");
    lmem[_mSUN] = 1;
  }
  else if (!lmem[_mSUN] && INFILE(_LT_SUN_GRAPHICS)) {
    INTERESTING(lDebug ? "Sun(9)" : "Sun");
    lmem[_mSUN] = 1;
  }
  else if ((!lmem[_mSUN] && INFILE(_LT_SUN_SISSLref1)) ||
      INFILE(_LT_SUN_SISSLref2)) {
    cp = SISSLVERS();
    INTERESTING(lDebug ? "SISSL(ref#2)" : cp);
    lmem[_mSUN] = 1;
  }
  else if (!lmem[_mSUN] && URL_INFILE(_URL_SISSL)) {
    cp = SISSLVERS();
    INTERESTING(lDebug ? "SISSL(url)" : cp);
    lmem[_mSUN] = 1;
  }
  if (INFILE(_LT_SUN_PRO)) {
    INTERESTING("SunPro");
  }
  /*
   * IBM
   */
  if (INFILE(_TEXT_IBM)) {
    if (INFILE(_LT_IBM_1)) {
      INTERESTING(lDebug ? "IBM(1)" : "IBM");
    }
    else if (INFILE(_LT_IBM_2)) {
      INTERESTING(lDebug ? "IBM(2)" : "IBM");
    }
    else if (INFILE(_LT_IBM_3)) {
      INTERESTING(lDebug ? "IBM(3)" : "IBM");
    }
    else if (INFILE(_LT_IBM_OWNER)) {
      INTERESTING(lDebug ? "IBM(4)" : "IBM");
    }
    else if (INFILE(_LT_IBM_RECIP)) {
      INTERESTING("IBM-reciprocal");
    }
    else if (INFILE(_LT_IBM_JIKES)) {
      INTERESTING("IBM-JCL");
    }
    else if (INFILE(_LT_IBM_COURIER)) {
      INTERESTING("IBM-Courier");
    }
    else if (INFILE(_LT_IBM_EULA)) {
      INTERESTING(lDebug ? "IBM-EULA(1)" : "IBM-EULA");
    }
    else if (INFILE(_PHR_IBM_EULA)) {
      INTERESTING(lDebug ? "IBM-EULA(2)" : "IBM-EULA");
    }
  }
  /*
   * Motorola
   */
  if (INFILE(_CR_MOT_1) || INFILE(_CR_MOT_2)) {
    if (INFILE(_LT_MOT_1)) {
      INTERESTING(lDebug ? "Motorola(1)" : "Motorola");
    }
    else if (INFILE(_LT_MOT_2)) {
      INTERESTING(lDebug ? "Motorola(2)" : "Motorola");
    }
  }
  /*
   * Java
   */
  if (INFILE(_LT_JAVA_WSDL4J)) {
    INTERESTING("Java-WSDL4J");
  }
  else if (INFILE(_LT_JAVA_WSDL_SCHEMA)) {
    INTERESTING("Java-WSDL-Schema");
  }
  else if (INFILE(_LT_JAVA_WSDL_POLICY)) {
    INTERESTING("Java-WSDL-Policy");
  }
  else if (INFILE(_LT_JAVA_WSDL_ENUM)) {
    INTERESTING("Java-WSDL-Spec");
  }
  else if (INFILE(_LT_MULTICORP_1)) {
    INTERESTING(lDebug ? "Java-Multi(1)" : "Java-Multi-Corp");
  }
  else if (INFILE(_LT_MULTICORP_2)) {
    INTERESTING(lDebug ? "Java-Multi(2)" : "Java-Multi-Corp");
  }
  /*
   * Mibble
   */
  if (INFILE(_LT_MIBBLE)) {
    if (INFILE(_TITLE_MIBBLE_28)) {
      INTERESTING("Mibble-2.8");
    }
    else {
      INTERESTING("Mibble");
    }
  }
  /*
   * Comtrol Corp
   */
  if (INFILE(_CR_COMTROL) && INFILE(_LT_COMTROL)) {
    INTERESTING("Comtrol");
  }
  /*
   * TrollTech
   */
  if (INFILE(_LT_TROLLTECH)) {
    INTERESTING("TrollTech");
  }
  else if (INFILE(_LT_QT_COMMref)) {
    INTERESTING("Qt(Commercial)");
  }
  /*
   * SNIA (Storage Network Industry) public license
   */
  if (!lmem[_mMPL] && !lmem[_mSUN] && INFILE(_LT_SNIA_PL)) {
    if (INFILE(_TITLE_SNIA_V11)) {
      INTERESTING("SNIA-1.1");
    }
    else if (INFILE(_TITLE_SNIA_V10)) {
      INTERESTING("SNIA-1.0");
    }
    else {
      INTERESTING("SNIA");
    }
  }
  else if (INFILE(_LT_SNIAref)) {
    if (INFILE(_TITLE_SNIA_V11)) {
      INTERESTING(lDebug ? "SNIA-1.1(ref)" : "SNIA-1.1");
    }
    else if (INFILE(_TITLE_SNIA_V10)) {
      INTERESTING(lDebug ? "SNIA-1.0(ref)" : "SNIA-1.0");
    }
    else {
      INTERESTING(lDebug ? "SNIA(ref)" : "SNIA");
    }
  }
  else if (URL_INFILE(_URL_SNIA_V11)) {
    INTERESTING(lDebug ? "SNIA-1.1(url)" : "SNIA-1.1");
  }
  else if (URL_INFILE(_URL_SNIA)) {
    INTERESTING(lDebug ? "SNIA(url)" : "SNIA");
  }
  /*
   * BEA
   */
  if (HASTEXT(_TEXT_BEASYS, 0)) {
    if (INFILE(_LT_BEA_1)) {
      INTERESTING(lDebug ? "BEA(1)" : "BEA");
    }
    else if (INFILE(_LT_BEA_2)) {
      INTERESTING(lDebug ? "BEA(2)" : "BEA");
    }
  }
  /*
   * ADOBE/FRAME
   */
  if (HASTEXT(_TEXT_ADOBE_FRAME, REG_EXTENDED)) {
    if (INFILE(_LT_ADOBE_1)) {
      INTERESTING(lDebug ? "Adobe(1)" : "Adobe");
    }
    else if (INFILE(_LT_ADOBE_2)) {
      INTERESTING(lDebug ? "Adobe(2)" : "Adobe");
    }
    else if (INFILE(_LT_ADOBE_3)) {
      INTERESTING(lDebug ? "Adobe(3)" : "Adobe");
    }
    else if (INFILE(_LT_ADOBE_4)) {
      INTERESTING(lDebug ? "Adobe(4)" : "Adobe-EULA");
    }
    else if (INFILE(_LT_ADOBE_5)) {
      INTERESTING(lDebug ? "Adobe(5)" : "Adobe-EULA");
    }
    else if (INFILE(_LT_ADOBE_6)) {
      INTERESTING(lDebug ? "Adobe(6)" : "Adobe-EULA");
    }
    else if (INFILE(_LT_ADOBE_7)) {
      INTERESTING(lDebug ? "Adobe(7)" : "Adobe-EULA");
    }
    else if (INFILE(_LT_FRAME)) {
      INTERESTING(lDebug ? "Adobe(Frame)" : "Adobe");
    }
    else if (INFILE(_LT_ADOBE_SRC) || INFILE(_TITLE_ADOBE_SRC)) {
      INTERESTING(lDebug ? "Adobe(src)" : "Adobe-SCLA");
    }
    else if (INFILE(_LT_ADOBE_DATA)) {
      INTERESTING(lDebug ? "Adobe(data)" : "Adobe");
    }
    else if (INFILE(_LT_ADOBE_EULA)) {
      INTERESTING("Adobe-EULA");
    }
    else if (INFILE(_LT_ADOBE_AFM)) {
      INTERESTING("Adobe-AFM");
    }
    else if (INFILE(_LT_ADOBE_OTHER)) {
      INTERESTING(lDebug ? "Adobe(other)" : "Adobe");
    }
    else if (INFILE(_LT_ADOBE_SUB)) {
      INTERESTING(lDebug ? "Adobe(sub)" : "Adobe");
    }
  }
  /*
   * Docbook and Open Source Metadata Framework
   */
  if (INFILE(_LT_DOCBOOK)) {
    if (HASTEXT(_TEXT_DOCBOOK, 0)) {
      INTERESTING("DOCBOOK");
    }
    else if (HASTEXT(_TEXT_METADATA, 0)) {
      INTERESTING("OMF");
    }
    else {
      INTERESTING("DOCBOOK-style");
    }
  }
  /*
   * MP3 decoder
   */
  if (INFILE(_LT_MPEG3)) {
    INTERESTING("MPEG3-decoder");
  }
  /*
   * Google
   */
  if (INFILE(_LT_GOOGLE_1)) {
    INTERESTING(lDebug ? "Google(1)" : "Google");
  }
  else if (INFILE(_LT_GOOGLE_2)) {
    INTERESTING(lDebug ? "Google(2)" : "Google");
  }
  /*
   * Mandriva
   */
  if (INFILE(_LT_MANDRIVA)) {
    INTERESTING("Mandriva");
  }
  /*
   * Irondoc
   */
  if (INFILE(_LT_IRONDOC)) {
    INTERESTING("IronDoc");
  }
  /*
   * Quarterdeck Office Systems
   */
  if (INFILE(_LT_QUARTERDECK) && INFILE(_CR_QUARTERDECK)) {
    INTERESTING("QuarterDeck");
  }
  /*
   * Electronic Book Technologies
   */
  if (INFILE(_LT_EBT)) {
    INTERESTING(INFILE(_CR_EBT) ? "EBT" : "EBT-style");
  }
  /*
   * SGML
   */
  if (HASTEXT(_TEXT_SGMLUG, 0) && INFILE(_LT_SGML)) {
    INTERESTING("SGML");
  }
  /*
   * LaTeX (KOMA-Script)
   */
  if (HASTEXT(_TEXT_LATEX, REG_EXTENDED)) {
    if (INFILE(_LT_LATEXPL_1) || INFILE(_LT_LATEXPL_2) ||
        INFILE(_LT_LATEXPL_3)) {
      cp = LPPLVERS();
      INTERESTING(lDebug ? "LPPL(1)" : cp);
    }
    else if (INFILE(_LT_LATEX)) {
      cp = LPPLVERS();
      INTERESTING(lDebug ? "LPPL(2)" : cp);
    }
    else if (INFILE(_LT_LATEXPLref1) || INFILE(_LT_LATEXPLref2) ||
        INFILE(_LT_LATEXPLref3)) {
      cp = LPPLVERS();
      INTERESTING(lDebug ? "LPPL(3)" : cp);
    }
    else if (INFILE(_LT_LATEXref1)) {
      cp = LPPLVERS();
      INTERESTING(lDebug ? "LPPL(4)" : cp);
    }
    else if (INFILE(_LT_LATEXref2)) {
      cp = LPPLVERS();
      INTERESTING(lDebug ? "LPPL(5)" : cp);
    }
    else if (INFILE(_LT_LATEXref3)) {
      cp = LPPLVERS();
      INTERESTING(lDebug ? "LPPL(6)" : cp);
    }
    else if (INFILE(_LT_LATEXref4)) {
      cp = LPPLVERS();
      INTERESTING(lDebug ? "LPPL(7)" : cp);
    }
    else if (INFILE(_LT_LATEXref5)) {
      cp = LPPLVERS();
      INTERESTING(lDebug ? "LPPL(8)" : cp);
    }
    else if (URL_INFILE(_URL_LATEX)) {
      cp = LPPLVERS();
      INTERESTING(lDebug ? "LPPL(url)" : cp);
    }
  }
  /*
   * QPL
   */
  if (INFILE(_LT_QPL) || INFILE(_LT_QPLref)) {
    if (INFILE(_TITLE_QPL10_1) || INFILE(_TITLE_QPL10_2)) {
      INTERESTING("QPL-1.0");
      lmem[_mQPL] = 1;
    }
    else if (INFILE(_TITLE_QPL)) {
      INTERESTING("QPL");
      lmem[_mQPL] = 1;
    }
  }
  /*
   * FREE Public License (not really open/free) and GHOSTSCRIPT
   */
  if (HASTEXT(_TEXT_GHOSTSCRIPT, 0)) {
    if (INFILE(_LT_GS)) {
      if (INFILE(_TITLE_GS11)) {
        INTERESTING("Ghostscript-GPL-1.1");
      }
      else {
        INTERESTING(lDebug ? "GS-GPL(1)" : "Ghostscript-GPL");
      }
    }
    else if (INFILE(_CR_ALADDIN) || INFILE(_CR_ARTOFCODE)) {
      if (INFILE(_LT_GS_GNU1) || INFILE(_LT_GS_GNU2)) {
        INTERESTING("GNU-Ghostscript");
      }
      else if (INFILE(_LT_GNU_1) && INFILE(_LT_GNU_2)) {
        INTERESTING(lDebug ? "GS-GPL(2)" : "Ghostscript-GPL");
      }
      else if (INFILE(_LT_FREEPL) || INFILE(_LT_FREEPLref)) {
        if (INFILE(_PHR_NOT_OPEN)) {
          INTERESTING("Aladdin(Closed-Source!)");
          lmem[_mALADDIN] = 1;
        }
        else {
          INTERESTING("Aladdin-Ghostscript");
        }
      }
      else if (INFILE(_LT_ALADDIN_RESTRICT)) {
        INTERESTING("Aladdin(RESTRICTED)");
      }
    }
    else if (INFILE(_LT_AFPL)) {
      INTERESTING("AFPL-Ghostscript");
    }
  }
  else if (INFILE(_LT_FREEPL) || INFILE(_LT_FREEPLref)) {
    INTERESTING("Free-PL");
  }
  /*
   * IPTC (International Press Telecommunications Council)
   */
  else if (INFILE(_LT_IPTC) && mCR_IPTC()) {
    INTERESTING("IPTC");
  }
  /*
   * Macromedia
   */
  else if (INFILE(_TITLE_MACROMEDIA_EULA)) {
    INTERESTING("MacroMedia-RPSL");
  }
  /*
   * Ontopia
   */
  else if (INFILE(_LT_ONTOPIA) && INFILE(_TITLE_ONTOPIA)) {
    INTERESTING("Ontopia");
  }
  /*
   * Ascender
   */
  if (INFILE(_LT_ASCENDER_EULA) && INFILE(_TITLE_ASCENDER_EULA)) {
    INTERESTING("Ascender-EULA");
  }
  /*
   * JPNIC
   */
  if (HASTEXT(_TEXT_JPNIC, 0) && INFILE(_LT_JPNIC)) {
    INTERESTING("JPNIC");
  }
  /*
   * ADAPTEC
   */
  if (INFILE(_LT_ADAPTEC_OBJ)) {
    INTERESTING("Adaptec(RESTRICTED)");
  }
  else if (INFILE(_CR_ADAPTEC) && INFILE(_LT_ADAPTEC_GPL)) {
    INTERESTING("Adaptec-GPL");
  }
  /*
   * Artistic and Perl
   */
  if (INFILE(_LT_PERL_1)) {
    INTERESTING(lDebug ? "Artistic(Perl#1)" : "Artistic-1.0");
    if (!lmem[_mGPL]) {
      INTERESTING(lDebug ? "GPL(Perl#1)" : "GPL");
    }
  }
  else if (INFILE(_LT_PERL_2)) {
    INTERESTING(lDebug ? "Artistic(Perl#2)" : "Artistic-1.0");
    if (!lmem[_mGPL]) {
      INTERESTING(lDebug ? "GPL(Perl#2)" : "GPL");
    }
  }
  else if (INFILE(_LT_PERL_3)) {
    if (!lmem[_fOPENLDAP] && !TRYGROUP(famOPENLDAP)) {
      INTERESTING(lDebug ? "Artistic(Perl#3)" : "Artistic-1.0");
    }
  }
  /*
   * Licensed "same as perl itself" will actually be Artistic AND GPL, per
   * Larry Wall and the documented licensing terms of "perl"
   */
  else if (INFILE(_LT_PERLref1)) {
    INTERESTING(lDebug ? "Artistic(Perl-ref1)" : "Artistic-1.0");
    if (!lmem[_mGPL]) {
      INTERESTING(lDebug ? "GPL(Perl-ref1)" : "GPL");
    }
  }
  else if (PERL_INFILE(_LT_PERLref2)) {
    INTERESTING(lDebug ? "Artistic(Perl-ref2)" : "Artistic-1.0");
    if (!lmem[_mGPL]) {
      INTERESTING(lDebug ? "GPL(Perl-ref2)" : "GPL");
    }
  }
  else if (INFILE(_LT_PERLref3)) {
    INTERESTING(lDebug ? "Artistic(Perl-ref3)" : "Artistic-1.0");
    if (!lmem[_mGPL]) {
      INTERESTING(lDebug ? "GPL(Perl-ref3)" : "GPL");
    }
  }
  else if (INFILE(_LT_PERLref4)) {
    INTERESTING(lDebug ? "Artistic(Perl-ref4)" : "Artistic-1.0");
    if (!lmem[_mGPL]) {
      INTERESTING(lDebug ? "GPL(Perl-ref4)" : "GPL");
    }
  }
  else if (INFILE(_LT_PERLref5)) {
    INTERESTING(lDebug ? "Artistic(Perl-ref5)" : "Artistic-1.0");
    if (!lmem[_mGPL]) {
      INTERESTING(lDebug ? "GPL(Perl-ref5)" : "GPL");
    }
  }
  else if (INFILE(_TITLE_CLARTISTIC)) {
     INTERESTING("ClArtistic");
     lmem[_fCLA] = 1;
  }
  else if (!lmem[_fREAL] && !LVAL(_TEXT_GNU_LIC_INFO) &&
      (INFILE(_LT_ART_1) || INFILE(_LT_ARTref1) ||
          INFILE(_LT_ARTref2) || INFILE(_LT_ARTref3) ||
          INFILE(_LT_ARTref4) || PERL_INFILE(_LT_ARTref5) ||
          PERL_INFILE(_LT_ARTref6))) {
    if (INFILE(_TITLE_OGTSL)) {
      INTERESTING("OGTSL");
    }
    else if (!lmem[_mLIBRE] && !lmem[_fOPENLDAP] &&
        !TRYGROUP(famOPENLDAP)) {
      if (INFILE(_LT_ART_V2) || INFILE(_TITLE_ART20)) {
        INTERESTING(lDebug ? "Artistic(v2.0#1)" : "Artistic-2.0");
      }
      else {
        INTERESTING("Artistic-1.0");
        lmem[_fARTISTIC] = 1;
      }
    }
  }
  else if (INFILE(_LT_ART_2) && INFILE(_TITLE_ART20)) {
    INTERESTING(lDebug ? "Artistic(v2.0#2)" : "Artistic-2.0");
  }
  else if (INFILE(_FILE_ART1) || INFILE(_FILE_ART2)) {
    INTERESTING(lDebug ? "Artistic(deb)" : "Artistic-1.0");
  }
  else if (URL_INFILE(_URL_ART)) {
    INTERESTING(lDebug ? "Artistic(url)" : "Artistic-1.0");
  }
  /*
   * LDP, Manpages, OASIS, GPDL, Linux-HOWTO and Linux-doc
   */
  if (INFILE(_TITLE_LDP20)) {
    INTERESTING("LDP-2.0");
  }
  else if (INFILE(_TITLE_LDP1A)) {
    INTERESTING("LDP-1A");
  }
  else if (INFILE(_LT_LDP)) {
    INTERESTING(lDebug ? "LDP(1)" : "LDP");
  }
  else if (INFILE(_LT_LDPref1)) {
    INTERESTING(lDebug ? "LDP(ref1)" : "LDP");
  }
  else if (INFILE(_LT_LDPref2)) {
    INTERESTING(lDebug ? "LDP(ref2)" : "LDP");
  }
  else if (INFILE(_LT_MANPAGE)) {
    INTERESTING("GNU-Manpages");
  }
  else if (INFILE(_LT_GPDLref)) {
    INTERESTING(lDebug ? "GPDL(1)" : "GPDL");
  }
  else if (INFILE(_TITLE_GPDL)) {
    INTERESTING(lDebug ? "GPDL(2)" : "GPDL");
  }
  else if (INFILE(_LT_FREEBSD_DOC) && INFILE(_TITLE_FREEBSD_DOC)) {
    INTERESTING("FreeBSD-Doc");
  }
  else if (INFILE(_LT_LINUX_HOWTO)) {
    INTERESTING("Linux-HOWTO");
  }
  else if (INFILE(_LT_LINUXDOC)) {
    INTERESTING("LinuxDoc");
  }
  else if (INFILE(_LT_IEEE_DOC)) {
    INTERESTING("IEEE-Doc");
  }
  /*
   * U-Washington
   */
  if (INFILE(_LT_UW1)) {
    if (INFILE(_CR_UWASHINGTON)) {
      INTERESTING("U-Wash(Free-Fork)");
    }
    else {
      INTERESTING(lDebug ? "U-Wash-style(1)" : "U-Wash-style");
    }
  }
  else if (INFILE(_LT_UW2)) {
    if (INFILE(_CR_UWASHINGTON)) {
      INTERESTING(lDebug ? "U-Wash(2)" : "U-Washington");
    }
    else {
      INTERESTING(lDebug ? "U-Wash-style(2)" : "U-Wash-style");
    }
  }
  else if (INFILE(_LT_UW3)) {
    if (INFILE(_CR_UWASHINGTON)) {
      INTERESTING(lDebug ? "U-Wash(3)" : "U-Washington");
    }
    else {
      INTERESTING(lDebug ? "U-Wash-style(3)" : "U-Wash-style");
    }
  }
  else if (INFILE(_LT_UW4)) {
    if (INFILE(_CR_UWASHINGTON)) {
      INTERESTING(lDebug ? "U-Wash(4)" : "U-Washington");
    }
    else {
      INTERESTING(lDebug ? "U-Wash-style(4)" : "U-Wash-style");
    }
  }
  /*
   * WU-ftpd (not the school north of Oregon!
   */
  if (INFILE(_LT_WU_FTPD)) {
    if (INFILE(_TITLE_WU_FTPD) || INFILE(_CR_WASHU)) {
      INTERESTING(lDebug ? "Wash-U(1)" : "Wash-U-StLouis");
    }
    else {
      INTERESTING("Wash-U-style");
    }
  }
  else if (INFILE(_LT_WU_FTPDref)) {
    INTERESTING(lDebug ? "Wash-U(ref)" : "Wash-U-StLouis");
  }
  /*
   * Delaware
   */
  else if (INFILE(_LT_DELAWARE)) {
    if (INFILE(_CR_DELAWARE)) {
      INTERESTING("U-Del");
    }
    else {
      INTERESTING("U-Del-style");
    }
  }
  /*
   * Princeton
   */
  else if (INFILE(_LT_PRINCETON)) {
    if (INFILE(_CR_PRINCETON)) {
      INTERESTING("Princeton");
    }
    else {
      INTERESTING("Princeton-style");
    }
  }
  /*
   * University of Michigan
   */
  else if (INFILE(_LT_UMICHIGAN_1)) {
    if (INFILE(_CR_MICHIGAN)) {
      INTERESTING(lDebug ? "U-Mich(1)" : "U-Michigan");
    }
    else {
      INTERESTING(lDebug ? "U-Mich-style(1)" : "U-Mich-style");
    }
  }
  else if (INFILE(_LT_UMICHIGAN_2)) {
    if (INFILE(_CR_MICHIGAN)) {
      INTERESTING(lDebug ? "U-Mich(2)" : "U-Michigan");
    }
    else {
      INTERESTING(lDebug ? "U-Mich-style(2)" : "U-Mich-style");
    }
  }
  /*
   * Southern Cal
   */
  else if (INFILE(_LT_USC_NC)) {
    if (INFILE(_CR_USC)) {
      INTERESTING("USC(Non-commercial)");
    }
    else {
      INTERESTING(lDebug ? "NonC(5)" : "Non-commercial!");
    }
  }
  else if (INFILE(_LT_USC)) {
    if (INFILE(_CR_USC)) {
      INTERESTING("USC");
    }
    else {
      INTERESTING("USC-style");
    }
  }
  /*
   * Unversity Corporation for Atmospheric Research (UCAR)
   */
  else if (INFILE(_LT_UCAR_1) || INFILE(_LT_UCAR_2)) {
    if (INFILE(_CR_UCAR)) {
      INTERESTING("UCAR");
    }
    else {
      INTERESTING("UCAR-style");
    }
  }
  /*
   * Stanford
   */
  else if (INFILE(_LT_STANFORD)) {
    if (INFILE(_CR_STANFORD)) {
      INTERESTING("Stanford");
    }
    else {
      INTERESTING("Stanford-style");
    }
  }
  /*
   * Cambridge University
   */
  else if (INFILE(_LT_CAMBRIDGE)) {
    if (INFILE(_CR_CAMBRIDGE_1) || INFILE(_CR_CAMBRIDGE_2)) {
      INTERESTING("U-Cambridge");
    }
    else {
      INTERESTING("U-Cambridge-style");
    }
  }
  /*
   * Columbia University
   */
  else if (INFILE(_CR_COLUMBIA) && INFILE(_LT_COLUMBIA)) {
    INTERESTING("U-Columbia");
  }
  /*
   * University of Notre Dame (Different from Boost!)
   */
  if (INFILE(_LT_ND_1)) {
    if (INFILE(_CR_NOTREDAME)) {
      INTERESTING(lDebug ? "ND(1)" : "NotreDame");
    }
    else {
      INTERESTING(lDebug ? "ND-style(1)" : "NotreDame-style");
    }
  }
  else if (INFILE(_LT_ND_2)) {
    if (INFILE(_CR_NOTREDAME)) {
      INTERESTING(lDebug ? "ND(2)" : "NotreDame");
    }
    else {
      INTERESTING(lDebug ? "ND-style(2)" : "NotreDame-style");
    }
  }
  else if (INFILE(_LT_ND_3)) {
    if (INFILE(_CR_NOTREDAME)) {
      INTERESTING(lDebug ? "ND(3)" : "NotreDame");
    }
    else {
      INTERESTING(lDebug ? "ND-style(3)" : "NotreDame-style");
    }
  }
  /*
   * Boost references
   */
  else if (!lmem[_mMIT] && INFILE(_LT_BOOST_2)) {
    if (INFILE(_CR_BOOST)) {
      INTERESTING(lDebug ? "Boost(2)" : "BSL-1.0");
    }
    else {
      INTERESTING(lDebug ? "Boost-style(2)" : "BSL-style");
    }
  }
  else if (INFILE(_LT_BOOSTref1)) {
    if (INFILE(_TITLE_BOOST10) || INFILE(_PHR_BOOST_V10) ||
        URL_INFILE(_URL_BOOST_10)) {
      INTERESTING(lDebug ? "Boost1.0(ref)" : "BSL-1.0");
    }
    else {
      INTERESTING(lDebug ? "Boost(ref)" : "BSL-1.0");
    }
  }
  else if (INFILE(_LT_BOOST_GRAPH)) {
    INTERESTING(lDebug ? "Boost(graph)" : "BSL-1.0");
  }
  else if (INFILE(_LT_BOOST_LAMBDA)) {
    INTERESTING(lDebug ? "Boost(lambda)" : "BSL-1.0");
  }
  else if (URL_INFILE(_URL_BOOST_10)) {
    INTERESTING(lDebug ? "Boost-1.0(ref)" : "BSL-1.0");
  }
  /*
   * Sleepycat NON-Commerical
   */
  else if (INFILE(_LT_SLEEPYCAT_NC)) {
    INTERESTING("Sleepycat(Non-commercial)");
  }
  /*
   * Vim license
   */
  if ((INFILE(_LT_VIM_1) || INFILE(_LT_VIM_2)) && INFILE(_TITLE_VIM)) {
    INTERESTING("Vim");
  }
  /*
   * Vixie license
   */
  if (INFILE(_LT_VIXIE)) {
    INTERESTING("Vixie-license");
  }
  /*
   * Yahoo!
   */
  if (INFILE(_LT_YAHOO_1)) {
    if (INFILE(_LT_YAHOO_BINARY)) {
      INTERESTING("Yahoo-EULA");
    }
    else {
      INTERESTING("YPL");
    }
  }
  if(INFILE(_TITLE_YPL_V10)) {
    INTERESTING("YPL-1.0");
  }
  else if(INFILE(_TITLE_YPL_V11)) {
    INTERESTING("YPL-1.1");
  }
  /*
   * Public Use
   */
  if (INFILE(_LT_PUBLIC_USE)) {
    if (INFILE(_TITLE_PUBUSE_V10)) {
      INTERESTING("Public-Use-1.0");
    }
    else {
      INTERESTING("Public-Use");
    }
  }
  /*
   * Apple
   */
  if (INFILE(_TEXT_APPLE)) {
    if (INFILE(_LT_APPLE_1)) {
      INTERESTING(lDebug ? "Apple(1)" : "Apple");
    }
    else if (INFILE(_LT_APPLE_2)) {
      INTERESTING(lDebug ? "Apple(2)" : "Apple");
    }
    else if (INFILE(_LT_APPLE_3)) { /* squeak license */
      INTERESTING(lDebug ? "Apple(3)" : "Apple");
    }
    else if (INFILE(_LT_APPLE_4)) { /* squeak license */
      INTERESTING(lDebug ? "Apple(4)" : "Apple-EULA");
    }
    else if (INFILE(_LT_APPLE_FONTFORGE)) {
      INTERESTING("Apple(FontForge)");
    }
    else if (INFILE(_LT_APPLE_SAMPLE)) {
      INTERESTING("Apple(Sample)");
    }
    else if (INFILE(_LT_APSLref1) || INFILE(_LT_APSLref2) ||
        INFILE(_TITLE_APSL)) {
      if (INFILE(_TITLE_APSL20)) {
        INTERESTING("APSL-2.0");
      }
      else if (INFILE(_TITLE_APSL12)) {
        INTERESTING("APSL-1.2");
      }
      else if (INFILE(_TITLE_APSL11)) {
        INTERESTING("APSL-1.1");
      }
      else if (INFILE(_TITLE_APSL10)) {
        INTERESTING("APSL-1.0");
      }
      else {
        INTERESTING("APSL");
      }
    }
    else if (INFILE(_LT_ACDL)) {
      INTERESTING("ACDL");
    }
    else if (INFILE(_TITLE_APPLE_SLA)) {
      INTERESTING(lDebug ? "Apple(SLA)" : "Apple-EULA");
    }
    else if (URL_INFILE(_URL_APSL)) {
      INTERESTING(lDebug ? "APSL(url)" : "APSL");
    }
    else if (URL_INFILE(_URL_ACDL)) {
      INTERESTING(lDebug ? "ACDL(url)" : "ACDL");
    }
  }
  /*
   * Redland
   */
  if (INFILE(_LT_REDLAND)) {
    INTERESTING("Redland");
  }
  /*
   * Red Hat and Fedora
   */
  if (INFILE(_LT_RH_PKGS)) {
    if (INFILE(_LT_RH_NONCOMMERCIAL)) {
      INTERESTING(lDebug ? "RH(NC)" : "RedHat(Non-commercial)");
      lmem[_mREDHAT] = 1;
    }
    else if (INFILE(_LT_RH_FEDORA)) {
      INTERESTING(lDebug ? "Fedora(1)" : "Fedora");
      lmem[_mREDHAT] = 1;
    }
  }
  else if (INFILE(_LT_RH_REDHAT)) {
    INTERESTING(lDebug ? "RH(2)" : "RedHat");
    lmem[_mREDHAT] = 1;
  }
  else if (INFILE(_LT_RH_SPECIFIC)) {
    INTERESTING(lDebug ? "RH(4)" : "RedHat-specific");
    lmem[_mREDHAT] = 1;
  }
  else if (INFILE(_LT_FEDORA)) {
    INTERESTING(lDebug ? "Fedora(2)" : "Fedora");
    lmem[_mREDHAT] = 1;
  }
  else if (INFILE(_LT_FEDORA_CLA) || INFILE(_TITLE_FEDORA_CLA)) {
    INTERESTING("Fedora-CLA");
    lmem[_mREDHAT] = 1;
  }
  else if (INFILE(_CR_REDHAT)) {
    if (INFILE(_LT_RH_1)) {
      INTERESTING(lDebug ? "RH(1)" : "RedHat");
      lmem[_mREDHAT] = 1;
    }
    else if (INFILE(_LT_RH_EULA)) {
      INTERESTING("RedHat-EULA");
      lmem[_mREDHAT] = 1;
    }
  }
  /*
   * SUSE/Novell/UnitedLinux
   */
  if (INFILE(_CR_SUSE) && INFILE(_PHR_YAST_CR)) {
    INTERESTING("YaST(SuSE)");
  }
  else if (INFILE(_TITLE_NOVELL_EULA)) {
    INTERESTING("Novell/SUSE");
  }
  else if (INFILE(_TITLE_UL_EULA)) {
    INTERESTING("UnitedLinux-EULA");
  }
  else if (INFILE(_LT_NOVELL)) {
    INTERESTING("Novell");
    lmem[_fIP] = 1;
  }
  else if (INFILE(_LT_NOVELL_IP_1)) {
    INTERESTING(lDebug ? "Novell-IP(1)" : "Novell-IP");
    lmem[_fIP] = 1;
  }
  else if (INFILE(_LT_NOVELL_IP_2)) {
    INTERESTING(lDebug ? "Novell-IP(2)" : "Novell-IP");
    lmem[_fIP] = 1;
  }
  /*
   * Epson Public license
   */
  if (INFILE(_LT_EPSON_PL) && INFILE(_TITLE_EPSON_PL)) {
    INTERESTING("Epson-PL");
  }
  else if (INFILE(_LT_EPSON_EULA) && INFILE(_TITLE_EPSON_EULA)) {
    INTERESTING("Epson-EULA");
  }
  /*
   * Open Publication license
   */
  if (INFILE(_LT_OPENPUBL_1) || INFILE(_LT_OPENPUBL_2)) {
    if (INFILE(_TITLE_OPENPUBL10)) {
      INTERESTING("Open-Publication-1.0");
    }
    else if (INFILE(_TITLE_OPENPUBL)) {
      INTERESTING("Open-Publication");
    }
    else {
      INTERESTING("Open-Publication-style");
    }
  }
  else if (INFILE(_LT_OPENPUBLref)) {
    INTERESTING(lDebug ? "Open-Publ(ref)" : "Open-Publication");
  }
  /*
   * Free Art License
   */
  if (INFILE(_LT_FREEART_V10)) {
    INTERESTING("Free-Art-1.0");
  }
  else if (INFILE(_LT_FREEART_V13)) {
    INTERESTING("Free-Art-1.3");
  }
  /*
   * RSA Security, Inc.
   */
  if (INFILE(_CR_RSA)) {
    if (INFILE(_LT_RSA_1)) {
      INTERESTING(lDebug ? "RSA(1)" : "RSA-Security");
    }
    else if (INFILE(_LT_RSA_2)) {
      INTERESTING(lDebug ? "RSA(2)" : "RSA-Security");
    }
  }
  else if (INFILE(_LT_RSA_3)) {
    INTERESTING(lDebug ? "RSA(3)" : "RSA-Security");
  }
  else if (INFILE(_LT_RSA_4)) {
    INTERESTING(lDebug ? "RSA(4)" : "RSA-Security");
  }
  else if (INFILE(_LT_RSA_5)) {
    INTERESTING(lDebug ? "RSA(5)" : "RSA-DNS");
  }
  /* Some licenses only deal with fonts */
  if (HASTEXT(_TEXT_FONT, 0)) {
    /*
     * AGFA Monotype
     */
    if (INFILE(_LT_AGFA)) {
      INTERESTING("AGFA(RESTRICTED)");
    }
    else if (INFILE(_LT_AGFA_EULA)) {
      INTERESTING("AGFA-EULA");
    }
    /*
     * Bigelow and Holmes
     */
    if (INFILE(_LT_BH_FONT)) {
      if (INFILE(_CR_BH)) {
        INTERESTING("BH-Font");
      }
      else {
        INTERESTING("BH-Font-style");
      }
    }
    /*
     * BIZNET
     */
    if (INFILE(_LT_BIZNET)) {
      if (INFILE(_CR_BIZNET)) {
        INTERESTING("BIZNET");
      }
      else {
        INTERESTING("BIZNET-style");
      }
    }
    /*
     * BITSTREAM
     */
    if (INFILE(_LT_BITSTREAM_1)) {
      INTERESTING(lDebug ? "Bitstream(2)" : "Bitstream");
    }
    else if (INFILE(_LT_BITSTREAM_2)) {
      INTERESTING(lDebug ? "Bitstream(3)" : "Bitstream");
    }
    /*
     * Larabie Fonts
     */
    if (INFILE(_LT_LARABIE_EULA) && INFILE(_TITLE_LARABIE_EULA)) {
      INTERESTING("Larabie-EULA");
    }
    /*
     * Baekmuk Fonts and Hwan Design
     */
    if (INFILE(_LT_BAEKMUK_1)) {
      INTERESTING("Baekmuk-Font");
    }
    else if (INFILE(_LT_BAEKMUK_2)) {
      INTERESTING("Baekmuk(Hwan)");
    }
    /*
     * Information-Technology Promotion Agency (IPA)
     */
    if (INFILE(_LT_IPA_EULA)) {
      INTERESTING("IPA-Font-EULA");
    }
    /*
     * Arphic Public License
     */
    if (INFILE(_LT_ARPHIC)) {
      if (INFILE(_CR_ARPHIC)) {
        INTERESTING("Arphic-Font-PL");
      }
      else {
        INTERESTING("Arphic-style");
      }
    }
  }

  /*
   * AT&T
   */
  if (INFILE(_LT_ATT_1)) {
    if (INFILE(_CR_ATT)) {
      INTERESTING(lDebug ? "ATT(1)" : "ATT");
    }
    else {
      INTERESTING(lDebug ? "ATT-style(1)" : "ATT-style");
    }
  }
  else if (INFILE(_LT_ATT_2)) {
    if (!lmem[_fBSD] && INFILE(_CR_BSDCAL)) {
      INTERESTING(lDebug ? "BSD(14)" : "BSD");
      lmem[_fBSD] = 1;
    }
    else if (INFILE(_CR_ATT)) {
      INTERESTING(lDebug ? "ATT(2)" : "ATT");
    }
    else {
      INTERESTING(lDebug ? "ATT-style(2)" : "ATT-style");
    }
  }
  else if (INFILE(_LT_ATT_SRC_1) || INFILE(_LT_ATT_SRC_2)) {
    if (INFILE(_TITLE_ATT_SRC_12D)) {
      INTERESTING("ATT-Source-1.2d");
    }
    else if (INFILE(_TITLE_ATT_SRC_10)) {
      INTERESTING("ATT-Source-1.0");
    }
    else {
      INTERESTING("ATT-Source");
    }
  }
  else if (INFILE(_LT_ATT_NONCOMMERC1) || INFILE(_LT_ATT_NONCOMMERC2)) {
    INTERESTING("ATT(Non-commercial)");
  }
  /*
   * Unix System Laboratories
   */
  else if (INFILE(_LT_USL_EUR)) {
    INTERESTING(lDebug ? "USLE(2)" : "USL-Europe");
  }
  /*
   * Silicon Graphics
   */
  if (INFILE(_TITLE_SGI_V10)) {
    INTERESTING("SGI-1.0");
  }
  else if (INFILE(_TITLE_SGI_V11)) {
    INTERESTING("SGI-1.1");
  }
  else if (INFILE(_TITLE_SGI_V20)) {
    INTERESTING("SGI-2.0");
  }
  else if (INFILE(_LT_SGI_1) || INFILE(_LT_SGI_2)) {
    if (INFILE(_CR_SGI) || URL_INFILE(_URL_SGI)) {
      INTERESTING("SGI");
    }
    else {
      INTERESTING("SGI-style");
    }
  }
  else if (INFILE(_LT_SGI_FREEW)) {
    INTERESTING("SGI-Freeware");
  }
  else if (INFILE(_LT_SGI_GLX)) {
    if (INFILE(_TITLE_SGI_GLX_V10)) {
      INTERESTING(lDebug ? "SGI_GLX(1.0)" : "SGI_GLX-1.0");
    }
    else {
      INTERESTING("SGI_GLX");
    }
  }
  else if (INFILE(_LT_SGI_GLXref) && INFILE(_CR_SGI)) {
    if (INFILE(_TITLE_SGI_GLX_V10)) {
      INTERESTING(lDebug ? "SGI_GLX(10ref)" : "SGI_GLX-1.0");
    }
    else {
      INTERESTING(lDebug ? "SGI_GLX(ref)" : "SGI_GLX");
    }
  }
  else if (INFILE(_LT_SGI_PROPRIETARY) && INFILE(_CR_SGI)) {
    INTERESTING("SGI-Proprietary");
  }

  /*
   * 3DFX (Glide)
   */
  if (INFILE(_CR_3DFX_1) || INFILE(_CR_3DFX_2)) {
    if (INFILE(_LT_GLIDE_3DFX)) {
      INTERESTING("3DFX");
    }
    else if (INFILE(_LT_GLIDE_GPL)) {
      INTERESTING("3DFX-PL");
    }
  }
  /*
   * Nvidia Corp
   */
  else if (INFILE(_CR_NVIDIA) && INFILE(_LT_NVIDIA)) {
    INTERESTING(lDebug ? "Nvidia(1)" : "Nvidia");
  }
  else if (INFILE(_LT_NVIDIA_EULA) || INFILE(_LT_NVIDIA_EULA_2) || INFILE(_TITLE_NVIDIA)) {
    INTERESTING(lDebug ? "Nvidia(2)" : "Nvidia-EULA");
  }
  /*
   * ATI Corp
   */
  else if (INFILE(_TITLE_ATI_EULA) && INFILE(_LT_ATI_EULA)) {
    INTERESTING("ATI-EULA");
  }
  /*
   * Agere Systems
   */
  else if (INFILE(_TITLE_AGERE_EULA) && INFILE(_LT_AGERE_EULA)) {
    INTERESTING("Agere-EULA");
  }
  /*
   * KD Tools, AB
   */
  if (INFILE(_TITLE_KDAB_EULA)) {
    if (INFILE(_LT_KDAB_1)) {
      INTERESTING(lDebug ? "KDAB(1)" : "KD-Tools-EULA");
    }
    if (INFILE(_LT_KDAB_2)) {
      INTERESTING(lDebug ? "KDAB(2)" : "KD-Tools-EULA");
    }
  }
  /*
   * KDE
   */
  if (INFILE(_PHR_KDE_FILE) && INFILE(_LT_KDE)) {
    INTERESTING("KDE");
  }
  /*
   * Broadcom
   */
  if (INFILE(_LT_BROADCOM_EULA) && INFILE(_CR_BROADCOM)) {
    INTERESTING("Broadcom-EULA");
  }
  /*
   * DARPA (Defense Advanved Research Projects Agency)
   */
  if (INFILE(_LT_DARPA_COUGAAR)) {
    INTERESTING("DARPA-Cougaar");
  }
  else if (INFILE(_LT_DARPA)) {
    INTERESTING("DARPA");
  }
  /*
   * Tektronix
   */
  if (INFILE(_LT_TEKTRONIX)) {
    if (INFILE(_CR_TEKTRONIX)) {
      INTERESTING("Tektronix");
    }
    else {
      INTERESTING("Tektronix-style");
    }
  }
  /*
   * Open Market, Inc
   */
  if (INFILE(_LT_CADENCE) && INFILE(_CR_CADENCE)) {
    INTERESTING("Cadence");
  }
  /*
   * Open Market, Inc
   */
  if (INFILE(_LT_OPENMKT)) {
    INTERESTING("OpenMarket");
  }
  /*
   * Unicode
   */
  if (INFILE(_LT_UNICODE_1) && INFILE(_CR_UNICODE)) {
    INTERESTING(lDebug ? "Unicode(1)" : "Unicode");
  }
  else if (INFILE(_LT_UNICODE_2)) {
    INTERESTING(lDebug ? "Unicode(2)" : "Unicode");
  }
  else if (INFILE(_LT_UNICODE_3)) {
    INTERESTING(lDebug ? "Unicode(3)" : "Unicode");
  }
  else if (INFILE(_URL_UNICODE)) {
    INTERESTING(lDebug ? "Unicode(5)" : "Unicode");
  }

  /*
   * Software Research Assoc
   */
  if (INFILE(_LT_SRA) && INFILE(_CR_SRA)) {
    INTERESTING("SW-Research");
  }
  /*
   * MITRE Collaborative Virtual Workspace (CVW) License
   */
  if (INFILE(_LT_MITRE_1)) {
    if (INFILE(_CR_MITRE)) {
      INTERESTING(lDebug ? "MitreCVW(1)" : "MitreCVW");
    }
    else if (INFILE(_TITLE_CVW)) {
      INTERESTING(lDebug ? "MitreCVW(2)" : "MitreCVW");
    }
    else {
      INTERESTING("MitreCVW-style");
    }
  }
  else if (INFILE(_LT_MITRE_2)) {
    INTERESTING(lDebug ? "Mitre(2)" : "Mitre");
  }
  /*
   * Jabber, Motosoto
   */
  if (INFILE(_LT_JABBER_1)) {
    if (INFILE(_TITLE_MOTOSOTO091)) {
      INTERESTING("Motosoto");
    }
    else if (INFILE(_TITLE_JABBER)) {
      INTERESTING("Jabber");
    }
  }
  else if (INFILE(_LT_JABBER_2)) {
    if (INFILE(_TITLE_JABBER_V10)) {
      INTERESTING(lDebug ? "Jabber(1.0)" : "Jabber-1.0");
    }
    else {
      INTERESTING(lDebug ? "Jabber(2)" : "Jabber");
    }
  }
  else if (INFILE(_URL_JABBER)) {
    INTERESTING(lDebug ? "Jabber(url)" : "Jabber");
  }
  /*
   * CPL, Lucent Public License, Eclipse PL
   */
  if (INFILE(_LT_CPL_1) || INFILE(_LT_CPL_2)) {
    if (INFILE(_TITLE_IBM_PL20)) {
      INTERESTING("IPL-2.0");
    }
    else if (INFILE(_TITLE_IBM_PL10)) {
      INTERESTING("IPL-1.0");
    }
    else if (INFILE(_TITLE_IBM_PL)) {
      INTERESTING("IPL");
    }
    else if (INFILE(_TITLE_OGPL)) {
      INTERESTING("OpenGroup-PL");
    }
    else if (INFILE(_TITLE_ECLIPSE10)) {
      INTERESTING(lDebug ? "Eclipse(v.0#1)" : "EPL-1.0");
    }
    else if (INFILE(_TITLE_ECLIPSE)) {
      INTERESTING(lDebug ? "Eclipse(#1)" : "EPL");
    }
    else if (INFILE(_TITLE_LUCENT102)) {
      INTERESTING("LPL-1.0");
    }
    else if (INFILE(_TITLE_LUCENT10)) {
      INTERESTING("LPL-1.0");
    }
    else if (!INFILE(_LT_CA)) {
      cp = CPLVERS();
      INTERESTING(lDebug ? "CPL(#1)" : cp);
    }
  }
  else if (INFILE(_LT_CPLref1)) {
    cp = CPLVERS();
    INTERESTING(lDebug ? "CPL(ref)" : cp);
  }
  else if (URL_INFILE(_URL_CPL)) {
    cp = CPLVERS();
    INTERESTING(lDebug ? "CPL(url)" : cp);
  }
  else if (INFILE(_LT_IBM_PLref1)) {
    INTERESTING(lDebug ? "IBM-PL(ref)" : "IPL");
  }
  else if (URL_INFILE(_URL_IBM_PL)) {
    INTERESTING(lDebug ? "IBM-PL(url)" : "IPL");
  }
  else if (INFILE(_LT_ECLIPSEref)) {
    if (INFILE(_TITLE_ECLIPSE10)) {
      INTERESTING(lDebug ? "Eclipse(v.0#2)" : "EPL-1.0");
    }
    else {
      INTERESTING(lDebug ? "Eclipse(#2)" : "EPL");
    }
  }
  /*
   * SyBase/Watcom
   */
  if (INFILE(_LT_SYBASE)) {
    if (INFILE(_TITLE_SYBASE10)) {
      INTERESTING("Watcom-1.0");
    }
    else {
      INTERESTING("Watcom");
    }
  }
  /*
   * Ricoh
   */
  if (INFILE(_LT_RICOH)) {
    if (INFILE(_TITLE_RICOH10)) {
      INTERESTING("RSCPL");
    }
  }
  /*
   * OCLC
   */
  if (INFILE(_LT_OCLC)) {
    if (INFILE(_TITLE_OCLC20)) {
      INTERESTING("OCLC-2.0");
    }
    else if (INFILE(_TITLE_OCLC10)) {
      INTERESTING("OCLC-1.0");
    }
    else {
      INTERESTING("OCLC");
    }
  }
  /*
   * Educational Community License
   */
  if (INFILE(_LT_ECL1)) {
    INTERESTING("ECL-1.0");
  }
  else if (INFILE(_LT_ECL2)) {
    INTERESTING("ECL-2.0");
  }
  else if (INFILE(_LT_ECL)) {
    INTERESTING(lDebug ? "ECL(1)" : "ECL-1.0");
  }
  /*
   * EU DataGrid and Condor PL
   */
  if (INFILE(_LT_EU)) {
    if (INFILE(_TITLE_CONDOR)) {
      INTERESTING("Condor-PL");
    }
    else {
      INTERESTING("EUDatagrid");
    }
  }
  /*
   * Adaptive Public License
   */
  if (INFILE(_LT_ADAPTIVE)) {
    if (INFILE(_TITLE_ADAPTIVE10)) {
      INTERESTING("APL-1.0");
    }
    else {
      INTERESTING("APL");
    }
    lmem[_fAPL] = 1;
  }
  /*
   * gSOAP Public License
   */
  if (!lmem[_mGSOAP] && INFILE(_LT_GSOAPref13)) {
    INTERESTING("gSOAP-1.3b");
  }
  else if (!lmem[_mGSOAP] && INFILE(_LT_GSOAPref)) {
    INTERESTING("gSOAP");
  }
  /*
   * Computer Associates
   */
  if (INFILE(_LT_CA)) {
    if (INFILE(_TITLE_CA11)) {
      INTERESTING("CATOSL-1.1");
    }
    else {
      INTERESTING("CA");
    }
  }
  /*
   * Frameworx
   */
  if (INFILE(_LT_FRAMEWORX)) {
    if (INFILE(_TITLE_FRAMEWORX10)) {
      INTERESTING("Frameworx-1.0");
    }
    else {
      INTERESTING("Frameworx");
    }
  }
  /*
   * NASA
   */
  if (INFILE(_LT_NASA)) {
    if (INFILE(_TITLE_NASA13)) {
      INTERESTING("NASA-1.3");
    }
    else {
      INTERESTING("NASA");
    }
  }
  /*
   * Freetype
   */
  if (INFILE(_LT_FREETYPE)) {
    if (INFILE(_TITLE_CATHARON)) {
      INTERESTING(lDebug ? "Catharon(1)" : "Catharon");
    }
    else if (INFILE(_CR_CATHARON)) {
      INTERESTING(lDebug ? "Catharon(2)" : "Catharon");
    }
    else if (INFILE(_TITLE_FREETYPE)) {
      INTERESTING("Freetype");
    }
    else {
      INTERESTING("Freetype-style");
    }
  }
  else if (INFILE(_LT_CATHARON)) {
    INTERESTING(lDebug ? "Catharon(3)" : "Catharon");
  }
  else if (INFILE(_LT_FREETYPEref)) {
    INTERESTING(lDebug ? "Freetype(ref)" : "Freetype");
  }
  /*
   * Eiffel Forum License
   */
  if (INFILE(_LT_EIFFEL)) {
    if (INFILE(_TITLE_EIFFEL2)) {
      INTERESTING("EFL-2.0");
    }
    else if (INFILE(_TITLE_EIFFEL1)) {
      INTERESTING("EFL-1.0");
    }
    else {
      INTERESTING("EFL");
    }
  }
  /*
   * BISON, Nethack, etc.
   */
  if (!lmem[_fGPL] && (INFILE(_LT_BISON) || INFILE(_LT_BISONref))) {
    if (INFILE(_TITLE_NETHACK)) {
      INTERESTING("NGPL");
    }
    else {
      INTERESTING("BISON");
    }
  }
  /*
   * Open Software License (OSL) and Academic Free License (AFL) are similar
   */
  if (INFILE(_LT_OSL_BAD)) {
    cp = (INFILE(_TITLE_AFL) ?  AFLVERS() : OSLVERS());
    INTERESTING(lDebug? "OSL(bad)" : cp);
  }
  else if (INFILE(_LT_OSLref1)) {
    cp = OSLVERS();
    INTERESTING(lDebug? "OSL(ref1)" : cp);
  }
  else if (INFILE(_LT_OSLref2)) {
    cp = OSLVERS();
    INTERESTING(lDebug? "OSL(ref2)" : cp);
  }
  else if (INFILE(_LT_AFL)) {
    cp = (INFILE(_TITLE_AFL) ?  AFLVERS() : OSLVERS());
    INTERESTING(lDebug? "AFL#1" : cp);
  }
  else if (INFILE(_TITLE_OSL21) && !INFILE(_TITLE_OSL21_EXHIBIT)) {
    cp = OSLVERS();
    INTERESTING(lDebug? "OSL(T2.1)" : cp);
  }
  else if (INFILE(_TITLE_AFL21)) {
    cp = AFLVERS();
    INTERESTING(lDebug? "AFL(T2.1)" : cp);
  }
  else if (INFILE(_TITLE_OSL30) && !INFILE(_TITLE_OSL30_EXHIBIT)) {
    cp = OSLVERS();
    INTERESTING(lDebug? "OSL(T3.0)" : cp);
  }
  else if (INFILE(_TITLE_AFL30)) {
    cp = AFLVERS();
    INTERESTING(lDebug? "AFL(T3.0)" : cp);
  }
  else if (URL_INFILE(_URL_OSL11)) {
    INTERESTING(lDebug ? "OSL_v1.1(url)" : "OSL-1.1");
  }
  else if (URL_INFILE(_URL_OSL)) {
    INTERESTING(lDebug ? "OSL(url)" : "OSL");
  }
  else if (URL_INFILE(_URL_AFL)) {
    INTERESTING(lDebug ? "AFL(url)" : "AFL");
  }
  /*
   * There are occasions where something is licensed under *either* AFL
   * or OSL, so don't keep AFL-refs in the if-then-else-if chain here.
   */
  if (INFILE(_LT_AFLref1)) {
    cp = AFLVERS();
    INTERESTING(lDebug? "AFL(ref1)" : cp);
  }
  else if (INFILE(_LT_AFLref2)) {
    cp = AFLVERS();
    INTERESTING(lDebug? "AFL(ref2)" : cp);
  }
  /*
   * Inner Net license
   */
  if (INFILE(_LT_INNERNET)) {
    if (INFILE(_TITLE_INNERNET200)) {
      INTERESTING("InnerNet-2.00");
    }
    else if (HASTEXT(_TEXT_INNERNET, 0)) {
      INTERESTING("InnerNet");
    }
    else {
      INTERESTING("InnerNet-style");
    }
  }
  else if (INFILE(_LT_INNERNETref_V2)) {
    INTERESTING(lDebug ? "InnetNet(v2ref)" : "InnerNet-2.00");
  }
  /*
   * Creative Commons Public License, Mindterm, and the Reciprocal PL
   */
  if (INFILE(_LT_CCPL)) {
    if (INFILE(_LT_RECIP_1) || INFILE(_LT_RECIP_2)) {
      if (INFILE(_TITLE_RPL15)) {
        INTERESTING(lDebug ? "RPL-1.5#1" : "RPL-1.5");
      }
      else if (INFILE(_TITLE_RPL11)) {
        INTERESTING(lDebug ? "RPL-1.1#1" : "RPL-1.1");
      }
      else if (INFILE(_TITLE_RPL10)) {
        INTERESTING(lDebug ? "RPL-1.0#1" : "RPL-1.0");
      }
      else {
        INTERESTING(lDebug ? "RPL#1" : "RPL");
      }
    }
    else if (INFILE(_TITLE_NC_SA_V30)) {
      INTERESTING("CC-BY-NC-SA-3.0");
      lmem[_fCCBY] = 1;
    }
    else if (INFILE(_TITLE_NC_SA_V25)) {
      lmem[_fCCBY] = 1;
      INTERESTING("CC-BY-NC-SA-2.5");
    }
    else if (INFILE(_TITLE_NC_SA_V20)) {
      lmem[_fCCBY] = 1;
      INTERESTING("CC-BY-NC-SA-2.0");
    }
    else if (INFILE(_TITLE_NC_SA_V10)) {
      lmem[_fCCBY] = 1;
      INTERESTING("CC-BY-NC-SA-1.0");
    }
    else if (INFILE(_TITLE_NC_ND_V30)) {
      lmem[_fCCBY] = 1;
      INTERESTING("CC-BY-NC-ND-3.0");
    }
    else if (INFILE(_TITLE_NC_ND_V25)) {
      lmem[_fCCBY] = 1;
      INTERESTING("CC-BY-NC-ND-2.5");
    }
    else if (INFILE(_TITLE_NC_ND_V20)) {
      lmem[_fCCBY] = 1;
      INTERESTING("CC-BY-NC-ND-2.0");
    }
    else if (INFILE(_TITLE_NC_ND_V10) || INFILE(_TITLE_NC_ND_V10_1)) {
      lmem[_fCCBY] = 1;
      INTERESTING("CC-BY-NC-ND-1.0");
    }
    else if (INFILE(_TITLE_SA_V30)) {
      lmem[_fCCBY] = 1;
      INTERESTING("CC-BY-SA-3.0");
    }
    else if (INFILE(_TITLE_SA_V25)) {
      lmem[_fCCBY] = 1;
      INTERESTING("CC-BY-SA-2.5");
    }
    else if (INFILE(_TITLE_SA_V20)) {
      lmem[_fCCBY] = 1;
      INTERESTING("CC-BY-SA-2.0");
    }
    else if (INFILE(_TITLE_SA_V10)) {
      lmem[_fCCBY] = 1;
      INTERESTING("CC-BY-SA-1.0");
    }
    else if (INFILE(_TITLE_NC_V30)) {
      lmem[_fCCBY] = 1;
      INTERESTING("CC-BY-NC-3.0");
    }
    else if (INFILE(_TITLE_NC_V25)) {
      lmem[_fCCBY] = 1;
      INTERESTING("CC-BY-NC-2.5");
    }
    else if (INFILE(_TITLE_NC_V20)) {
      lmem[_fCCBY] = 1;
      INTERESTING("CC-BY-NC-2.0");
    }
    else if (INFILE(_TITLE_NC_V10)) {
      INTERESTING("CC-BY-NC-1.0");
      lmem[_fCCBY] = 1;
    }
    else if (INFILE(_TITLE_ND_V30)) {
      INTERESTING("CC-BY-ND-3.0");
      lmem[_fCCBY] = 1;
    }
    else if (INFILE(_TITLE_ND_V25)) {
      INTERESTING("CC-BY-ND-2.5");
      lmem[_fCCBY] = 1;
    }
    else if (INFILE(_TITLE_ND_V20)) {
      INTERESTING("CC-BY-ND-2.0");
      lmem[_fCCBY] = 1;
    }
    else if (INFILE(_TITLE_ND_V10)) {
      INTERESTING("CC-BY-ND-1.0");
      lmem[_fCCBY] = 1;
    }
    else if (INFILE(_TITLE_ATTR_V30)) {
      INTERESTING("CC-BY-3.0");
      lmem[_fCCBY] = 1;
    }
    else if (INFILE(_TITLE_ATTR_V25)) {
      INTERESTING("CC-BY-2.5");
      lmem[_fCCBY] = 1;
    }
    else if (INFILE(_TITLE_ATTR_V20)) {
      INTERESTING("CC-BY-2.0");
      lmem[_fCCBY] = 1;
    }
    else if (INFILE(_TITLE_ATTR_V10)) {
      INTERESTING("CC-BY-1.0");
      lmem[_fCCBY] = 1;
    }
    else if (INFILE(_TITLE_CCPL)) {
      INTERESTING("CCPL");
      lmem[_fCCBY] = 1;
    }
    else if (INFILE(_LT_RECIP15)) {
      INTERESTING("RPL-1.5");
    }
    else if (INFILE(_TITLE_MINDTERM)) {
      INTERESTING("MindTerm");
    }
  }
  else if (INFILE(_LT_RECIP_1) || INFILE(_LT_RECIP_2)) {
    if (INFILE(_TITLE_RPL15)) {
      INTERESTING(lDebug ? "RPL-1.5#2" : "RPL-1.5");
    }
    else if (INFILE(_TITLE_RPL11)) {
      INTERESTING(lDebug ? "RPL-1.1#2" : "RPL-1.1");
    }
    else if (INFILE(_TITLE_RPL10)) {
      INTERESTING(lDebug ? "RPL-1.0#2" : "RPL-1.0");
    }
    else {
      INTERESTING(lDebug ? "RPL#2" : "RPL");
    }
  }
  else if (INFILE(_LT_CCA_1) && INFILE(_LT_CCA_2)) {
    if (INFILE(_LT_CCA_SA)) {
      cp = CCSAVERS();
      INTERESTING(cp);
    }
    else {
      cp = CCVERS();
      INTERESTING(cp);
    }
  }
  else if (INFILE(_LT_CCA_SAref)) {
    cp = CCSAVERS();
    INTERESTING(lDebug ? "CCA-SA(ref)" : cp);
  }
  else if (INFILE(_LT_CCA_ref)) {
    cp = CCVERS();
    INTERESTING(lDebug ? "CCA(ref)" : cp);
  }
  else if (URL_INFILE(_URL_RPL)) {
    INTERESTING(lDebug ? "RPL(url)" : "RPL");
  }
  else if (URL_INFILE(_URL_CCA_SA)) {
    cp = CCSAVERS();
    INTERESTING(lDebug ? "CCA-SA(url)" : cp);
  }
  else if (URL_INFILE(_URL_CCLGPL)) {
    cp = LGPLVERS();
    INTERESTING(lDebug ? "CC-LGPL(url)" : cp);
  }
  else if (URL_INFILE(_URL_CCGPL)) {
    cp = GPLVERS();
    INTERESTING(lDebug ? "CC-GPL(url)" : cp);
  }
  /*
   * SpikeSource
   */
  if (INFILE(_CR_SPIKESOURCE) && INFILE(_LT_SPIKESOURCE)) {
    INTERESTING("SpikeSource");
  }
  /*
   * Legato systems
   */
  if (INFILE(_LT_LEGATO_1) || INFILE(_LT_LEGATO_2)) {
    INTERESTING("Legato");
  }
  /*
   * Paradigm associates
   */
  if (INFILE(_LT_PARADIGM) && INFILE(_CR_PARADIGM)) {
    INTERESTING("Paradigm");
  }
  /*
   * Wintertree Software
   */
  if (INFILE(_LT_WINTERTREE)) {
    INTERESTING("Wintertree");
  }
  /*
   * Genivia
   */
  if (INFILE(_LT_GENIVIAref)) {
    INTERESTING("Genivia(Commercial)");
  }
  /*
   * Open Directory License
   */
  if (INFILE(_LT_ODL)) {
    INTERESTING("ODL");
  }
  /*
   * Open Directory License
   */
  if (INFILE(_LT_OSD)) {
    INTERESTING("OSD");
  }
  /*
   * Zveno
   */
  if (INFILE(_LT_ZVENO) && INFILE(_CR_ZVENO)) {
    INTERESTING("Zveno");
  }
  /*
   * Brainstorm
   */
  if (INFILE(_LT_BRAINSTORM_EULA) && INFILE(_TITLE_BRAINSTORM_EULA)) {
    INTERESTING("BrainStorm-EULA");
  }
  /*
   * BancTec
   */
  if (INFILE(_LT_BANCTEC) && INFILE(_CR_BANCTEC)) {
    INTERESTING("BancTec");
  }
  /*
   * AOL
   */
  if (INFILE(_LT_AOL_EULA)) {
    INTERESTING("AOL-EULA");
  }
  /*
   * Algorithmics
   */
  if (INFILE(_LT_ALGORITHMICS)) {
    INTERESTING("Algorithmics");
  }
  /*
   * Pixware
   */
  if (INFILE(_LT_PIXWARE_EULA)) {
    INTERESTING("Pixware-EULA");
  }
  /*
   * Compuserve
   */
  if (HASTEXT(_TEXT_COMPUSERVE, 0) && INFILE(_LT_COMPUSERVE)) {
    INTERESTING("CompuServe");
  }
  /*
   * Advanved Micro Devices (AMD)
   */
  if (INFILE(_CR_AMD) && INFILE(_LT_AMD)) {
    INTERESTING("AMD");
  }
  else if (INFILE(_LT_AMD_EULA) && INFILE(_TITLE_AMD_EULA)) {
    INTERESTING("AMD-EULA");
  }
  /*
   * OMRON Corp
   */
  if ((INFILE(_CR_OMRON_1) || INFILE(_CR_OMRON_2)) &&
      (INFILE(_LT_OMRON1) || INFILE(_LT_OMRON2))) {
    INTERESTING(lDebug ? "OMRON(2)" : "OMRON");
  }
  /*
   * MetroLink
   */
  if (INFILE(_TITLE_METROLINK)) {
    if (INFILE(_LT_METRO)) {
      if (INFILE(_LT_METRO_NONFREE)) {
        INTERESTING("MetroLink-nonfree");
      }
      else {
        INTERESTING("MetroLink");
      }
    }
  }
  else if (INFILE(_LT_METROLINKref)) {
    INTERESTING(lDebug ? "MetroLink(ref)" : "MetroLink");
  }
  /*
   * University of Edinburgh (and a CMU derivative)
   */
  if (INFILE(_LT_EDINBURGH_1)) {
    if (mCR_CMU()) {
      INTERESTING(lDebug ? "CMU(3)" : "CMU");
      lmem[_mCMU] = 1;
    }
    else if (mCR_EDIN()) {
      INTERESTING(lDebug ? "U-Edinburgh(1)" : "U-Edinburgh");
    }
    else {
      INTERESTING(lDebug ? "UE-style(1)" : "U-Edinburgh-style");
    }
  }
  else if (INFILE(_LT_EDINBURGH_2)) {
    if (mCR_EDIN()) {
      INTERESTING(lDebug ? "U-Edinburgh(2)" : "U-Edinburgh");
    }
    else {
      INTERESTING(lDebug ? "UE-style(2)" : "U-Edinburgh-style");
    }
  }
  /*
   * CMU (including the weird "fnord" text)
   */
  if (INFILE(_LT_CMU_1)) {
    if (!lmem[_mSUN] && INFILE(_CR_SUN)) {
      INTERESTING(lDebug ? "CMU(4)" : "CMU");
      lmem[_mCMU] = 1;
    }
    else if (!lmem[_mREDHAT] && INFILE(_CR_REDHAT)) {
      INTERESTING(lDebug ? "RH(5)" : "RedHat");
      lmem[_mREDHAT] = 1;
    }
    else if (INFILE(_CR_NRL)) {
      INTERESTING("NRL");
    }
    else if (!lmem[_mCMU] && mCR_CMU()) {
      INTERESTING(lDebug ? "CMU(5)" : "CMU");
      lmem[_mCMU] = 1;
    }
    else {
      INTERESTING("CMU-style"); }
  }
  else if (!lmem[_mCMU] && INFILE(_LT_CMU_2) && mCR_CMU()) {
    INTERESTING(lDebug ? "CMU(6)" : "CMU");
    lmem[_mCMU] = 1;
  }
  else if (INFILE(_LT_CMU_3)) {
    INTERESTING(lDebug ? "CMU(7)" : "CMU");
    lmem[_mCMU] = 1;
  }
  else if (INFILE(_LT_CMU_4)) {
    INTERESTING(lDebug ? "CMU(8)" : "CMU");
    lmem[_mCMU] = 1;
  }
  else if (INFILE(_LT_CMU_5)) {
    INTERESTING(lDebug ? "CMU(9)" : "CMU");
    lmem[_mCMU] = 1;
  }
  else if (INFILE(_LT_CMU_6)) {
    INTERESTING(lDebug ? "CMU(10)" : "CMU");
    lmem[_mCMU] = 1;
  }
  /*
   * University of Chicago
   */
  if (INFILE(_CR_UCHICAGO) && INFILE(_LT_UCHICAGO)) {
    INTERESTING("U-Chicago");
  }
  /*
   * University of Utah
   */
  if (INFILE(_CR_UUTAH) && INFILE(_LT_UUTAH)) {
    INTERESTING("U-Utah");
  }
  /*
   * University of British Columbia
   */
  if (INFILE(_CR_UBC) && INFILE(_LT_UBC)) {
    INTERESTING("U-BC");
  }
  /*
   * ImageMagick Studios - DON'T RECALL THE TEXT FOR THIS LICENSE!
   */
  if (INFILE(_LT_IMAGEMAGICK)) {
    if (INFILE(_CR_IMAGEMAGICK)) {
      INTERESTING("ImageMagick");
    }
    else {
      INTERESTING("ImageMagick-style");
    }
  }
  else if (URL_INFILE(_URL_IMAGEMAGICK)) {
    INTERESTING(lDebug ? "ImageMagick(url)" : "ImageMagick");
  }
  /*
   * Riverbank
   */
  if (INFILE(_LT_RIVERBANK) && INFILE(_TITLE_RIVERBANK_EULA)) {
    INTERESTING("Riverbank-EULA");
  }
  /*
   * Polyserve
   */
  if (INFILE(_CR_POLYSERVE) && INFILE(_LT_POLYSERVE)) {
    INTERESTING("Polyserve-CONFIDENTIAL");
  }
  /*
   * Fujitsu Limited
   */
  if (INFILE(_CR_FUJITSU) && INFILE(_LT_FUJITSU)) {
    INTERESTING("Fujitsu");
  }
  /*
   * Cypress Semiconductor
   */
  if (INFILE(_CR_CYPRESS) && INFILE(_LT_CYPRESS)) {
    INTERESTING("Cypress-FW");
  }
  /*
   * Keyspan
   */
  else if (INFILE(_CR_KEYSPAN) && INFILE(_LT_KEYSPAN)) {
    INTERESTING("Keyspan-FW");
  }
  /*
   * ATMEL
   */
  else if (INFILE(_CR_ATMEL) && INFILE(_LT_ATMEL)) {
    INTERESTING("ATMEL-FW");
  }
  /*
   * Quest Software
   */
  if (INFILE(_LT_QUEST_EULA) && INFILE(_TITLE_QUEST_EULA)) {
    INTERESTING("Quest-EULA");
  }
  /*
   * International Organization for Standarization
   */
  if (INFILE(_LT_IOS) && INFILE(_CR_IOS)) {
    INTERESTING("IOS");
  }
  /*
   * Garmin Ltd.
   */
  if (INFILE(_LT_GARMIN_EULA) && INFILE(_TITLE_GARMIN_EULA)) {
    INTERESTING("Garmin-EULA");
  }
  /*
   * AVM GmbH
   */
  if (INFILE(_CR_AVM)) {
    if (INFILE(_LT_AVM_1)) {
      INTERESTING(lDebug ? "AVM(1)" : "AVM");
    }
    else if (INFILE(_LT_AVM_2)) {
      INTERESTING(lDebug ? "AVM(2)" : "AVM");
    }
  }
  else if (INFILE(_LT_AVM_3)) {
    INTERESTING(lDebug ? "AVM(3)" : "AVM");
  }
  /*
   * Fair license
   */
  if (INFILE(_LT_FAIR)) {
    if (INFILE(_TITLE_FAIR)) {
      INTERESTING("Fair");
    }
    else {
      INTERESTING("Fair-style");
    }
  }
  /*
   * GCA (Majordomo)
   */
  if (INFILE(_LT_GCA)) {
    if (INFILE(_TITLE_MAJORDOMO11)) {
      INTERESTING("Majordomo-1.1");
    }
    else {
      INTERESTING("Majordomo");
    }
  }
  /*
   * Zeus Technology -- this one is kind of a corner-case since the wording
   * is VERY general.  If there's a Zeus copyright with the license text,
   * spell it out; else, look for the same text in the "generic" section.
   */
  if (INFILE(_CR_ZEUS) && INFILE(_LT_ZEUS)) {
    INTERESTING("Zeus");
  }
  /*
   * Information-technology promotion agency
   */
  if (!lmem[_mXOPEN] && INFILE(_LT_XOPEN_1)) {
    if (!lmem[_mOSF] && INFILE(_CR_OSF)) {
      INTERESTING(lDebug ? "OSF(2)" : "OSF");
      lmem[_mOSF] = 1;
    }
    else if (INFILE(_CR_UI)) {
      INTERESTING(lDebug ? "UI(2)" : "Unix-Intl");
    }
    else if (INFILE(_CR_XOPEN)) {
      INTERESTING(lDebug ? "XOpen(2)" : "X/Open");
      lmem[_mXOPEN] = 1;
    }
    else if (INFILE(_CR_IPA)) {
      INTERESTING("IPA");
    }
    else if (!lmem[_mSUN] && INFILE(_CR_SUN)) {
      INTERESTING(lDebug ? "Sun(10)" : "Sun");
      lmem[_mSUN] = 1;
    }
    else {
      INTERESTING("X/Open-style");
    }
  }
  /* This one is funky - it includes part of the copyright */
  else if (!lmem[_mXOPEN] && INFILE(_LT_XOPEN_2)) {
    INTERESTING(lDebug ? "XOpen(3)" : "X/Open");
    lmem[_mXOPEN] = 1;
  }
  /*
   * Interlink networks EULA (seen in HP proprietary code)
   */
  if (INFILE(_LT_INTERLINK_EULA) && INFILE(_TITLE_INTERLINK_EULA)) {
    INTERESTING("Interlink-EULA");
  }
  /*
   * Mellanox Technologies
   */
  if (INFILE(_LT_MELLANOX) && INFILE(_CR_MELLANOX)) {
    INTERESTING("Mellanox");
  }
  /*
   * nCipher Corp
   */
  if (INFILE(_LT_NCIPHER) && INFILE(_CR_NCIPHER)) {
    INTERESTING("nCipher");
  }
  /*
   * Distributed Processing Technology Corp
   */
  if (INFILE(_CR_DPTC) && INFILE(_LT_DPTC)) {
    INTERESTING("DPTC");
  }
  /*
   * Distributed Management Task Force
   */
  else if (HASTEXT(_TEXT_REPRODUCED, 0) && INFILE(_CR_DMTF) &&
      INFILE(_LT_DMTF)) {
    INTERESTING("DMTF");
    lmem[_fATTRIB] = 1;
  }
  /*
   * DSC Technologies Corp
   */
  if (INFILE(_CR_DSCT) && INFILE(_LT_DSCT)) {
    INTERESTING("DSCT");
  }
  /*
   * Epinions, Inc.
   */
  if (INFILE(_CR_EPINIONS) && INFILE(_LT_EPINIONS)) {
    INTERESTING("Epinions");
  }
  /*
   * MITEM, Ltd
   */
  if (INFILE(_CR_MITEM) && INFILE(_LT_MITEM)) {
    INTERESTING("MITEM");
  }
  /*
   * Cylink corp
   */
  if ((INFILE(_LT_CYLINK_ISC_1) || INFILE(_LT_CYLINK_ISC_2))) {
    INTERESTING("Cylink-ISC");
  }
  /*
   * SciTech software
   */
  if (INFILE(_CR_SCITECH) && INFILE(_LT_SCITECH)) {
    INTERESTING("SciTech");
  }
  /*
   * O'Reilly and Associates
   */
  if (INFILE(_LT_OREILLY_1)) {
    if (INFILE(_CR_OREILLY)) {
      INTERESTING("O'Reilly");
    }
    else {
      INTERESTING("O'Reilly-style");
    }
  }
  else if (INFILE(_LT_OREILLY_2)) {
    if (INFILE(_CR_OREILLY)) {
      INTERESTING(lDebug ? "O'Reilly-2" : "O'Reilly");
    }
    else {
      INTERESTING(lDebug ? "OReilly-st-2" : "O'Reilly-style");
    }
  }
  /*
   * Bit-Torrent
   */
  if (INFILE(_LT_BITTORRENT)) {
    if (INFILE(_TITLE_BITTORRENT11)) {
      INTERESTING("BitTorrent-1.1");
    }
    else if (INFILE(_TITLE_BITTORRENT10)) {
      INTERESTING("BitTorrent-1.0");
    }
    else {
      INTERESTING("BitTorrent");
    }
  }
  else if (INFILE(_LT_BITTORRENTref)) {
    INTERESTING(lDebug ? "BitTorrent(ref)" : "BitTorrent");
  }
  /*
   * Open Software Foundation
   */
  if (INFILE(_LT_OSF_1)) {
    if (INFILE(_CR_OSF)) {
      INTERESTING(lDebug ? "OSF(3)" : "OSF");
      lmem[_mOSF] = 1;
    }
    else {
      INTERESTING(lDebug ? "OSF-style(1)" : "OSF-style");
    }
  }
  else if (INFILE(_LT_OSF_2)) {
    if (INFILE(_CR_OSF)) {
      INTERESTING(lDebug ? "OSF(4)" : "OSF");
      lmem[_mOSF] = 1;
    }
    else {
      INTERESTING(lDebug ? "OSF-style(2)" : "OSF-style");
    }
  }
  /*
   * OpenLDAP (if not already determined above -- the OpenLDAP public license
   * borrows text from LOTS of different sources)
   */
  if (!lmem[_fBSD] && lmem[_tOPENLDAP] && !lmem[_fOPENLDAP]) {
    if (INFILE(_LT_OPENLDAP_1)) {
      if (!TRYGROUP(famOPENLDAP)) {
        INTERESTING("OLDAP-style");
      }
    }
    else if (INFILE(_LT_OPENLDAP_2)) {
      INTERESTING("OLDAP");
    }
  }
  /*
   * The Knuth license is sufficiently bizarre enough it belongs down here,
   * towards the end of the search
   *****
   * Lachman Associates - includes a proprietary-to-the-max paragraph
   * IoSoft Ltd.
   */
  if (INFILE(_CR_KNUTH) && INFILE(_LT_KNUTH_1)) {
    INTERESTING(lDebug ? "Knuth(1)" : "D.E.Knuth");
  }
  else if (INFILE(_LT_KNUTH_2)) {
    INTERESTING(lDebug ? "Knuth(2)" : "D.E.Knuth");
  }
  else if (INFILE(_LT_KNUTH_STYLE)) {
    INTERESTING("Knuth-style");
  }
  else if (INFILE(_LT_LACHMAN_SECRET)) {
    INTERESTING("Lachman-Proprietary");
  }
  else if (INFILE(_LT_IOSOFT_SRCONLY)) {
    INTERESTING("IoSoft(COMMERCIAL)");
  }
  /*
   * The Free Software License
   */
  if (INFILE(_LT_FREE_SW)) {
    INTERESTING("Free-SW");
  }
  /*
   * NOT free software and explicitly free software
   */
  else if (INFILE(_PHR_NOT_FREE_SW)) {
    if (INFILE(_LT_NOT_FREE) && INFILE(_CR_WTI)) {
      INTERESTING("WTI(Not-free)");
    }
    else {
      INTERESTING("Not-Free!");
    }
  }
  /*
   * Code Project Open License
   */
  if (INFILE(_LT_CPOL)) {
    INTERESTING("CPOL");
  }
  /*
   * Macrovision
   */
  if (INFILE(_LT_MACROV_IA) && INFILE(_PHR_MACROVISION)) {
    if (INFILE(_PHR_EULA)) {
      INTERESTING("Macrovision-EULA");
    }
    else {
      INTERESTING("Macrovision");
    }
  }
  /*
   * VMware
   */
  if (INFILE(_LT_VMWARE) && INFILE(_TITLE_VMWARE)) {
    INTERESTING("VMware-EULA");
  }
  /*
   * UCWARE.com
   */
  if (INFILE(_LT_UCWARE_EULA_1) || INFILE(_LT_UCWARE_EULA_2)) {
    INTERESTING("UCWare-EULA");
  }
  /*
   * InfoSeek Corp
   */
  if (INFILE(_LT_INFOSEEK) && INFILE(_CR_INFOSEEK)) {
    INTERESTING("InfoSeek");
  }
  /*
   * Trident Microsystems
   */
  if (INFILE(_LT_TRIDENT_EULA) && INFILE(_CR_TRIDENT)) {
    INTERESTING("Trident-EULA");
  }
  /*
   * ARJ Software Inc
   */
  if (INFILE(_LT_ARJ) && INFILE(_CR_ARJ)) {
    INTERESTING("ARJ");
  }
  /*
   * Piriform Ltd
   */
  if (INFILE(_LT_PIRIFORM) && INFILE(_CR_PIRIFORM)) {
    INTERESTING("Piriform");
  }
  /*
   * Design Science License (DSL)
   */
  if (INFILE(_LT_DSL)) {
    INTERESTING("DSL");
  }
  /*
   * Skype
   */
  if (INFILE(_TITLE_SKYPE) && INFILE(_LT_SKYPE)) {
    INTERESTING("Skype(Non-commercial)");
  }
  /*
   * Hauppauge
   */
  if (INFILE(_LT_HAUPPAUGE)) {
    INTERESTING("Hauppauge");
  }
  /*
   * Platform Computing Corp (or a generic on-your-intranet-only restriction)
   */
  if (INFILE(_LT_INTRANET_ONLY)) {
    if (INFILE(_CR_PLATFORM_COMP)) {
      INTERESTING(lDebug ? "Platfm(1)" : "Platform-Computing(RESTRICTED)");
    } else {
      MEDINTEREST("Intranet-only");
    }
  } else if (INFILE(_LT_NOT_INTERNET)) {
    if (INFILE(_CR_PLATFORM_COMP)) {
      INTERESTING(lDebug ? "Platfm(2)" : "Platform-Computing(RESTRICTED)");
    } else {
      MEDINTEREST("Not-Internet");
    }
  }
  /*
   * Curl
   */
  if (URL_INFILE(_URL_CURL)) {
    INTERESTING(lDebug ? "Curl(URL)" : "MIT-style");
  }
  /*
   * ID Software
   */
  if (INFILE(_LT_ID_EULA)) {
    INTERESTING("ID-EULA");
  }
  /*
   * M+ Fonts Project
   */
  if (INFILE(_LT_MPLUS_FONT) && INFILE(_CR_MPLUS)) {
    INTERESTING("M-Plus-Project");
  }
  /*
   * Powder Development
   */
  if (INFILE(_LT_POWDER)) {
    INTERESTING("Powder-Proprietary");
  }
  /*
   * Against DRM
   */
  if (INFILE(_LT_AGAINST_DRM)) {
    INTERESTING("Against-DRM");
  }
  /*
   * The TeXinfo exception clause
   */
  if (INFILE(_LT_TEX_EXCEPT)) {
    INTERESTING(lDebug ? "TeX-except" : "TeX-exception");
  }
  /*
   * The U.S. Gummint
   */
  if (INFILE(_LT_USGOVT_1)) {
    if (INFILE(_CR_URA)) {
      MEDINTEREST("URA(gov't)");
    } else {
      MEDINTEREST(lDebug ? "Govt-Wk(1)" : "Gov't-work");
    }
  }
  else if (INFILE(_LT_USGOVT_2)) {
    MEDINTEREST(lDebug ? "Govt-Wk(2)" : "Gov't-work");
  } else if (INFILE(_LT_USGOVT_RIGHTS1) && INFILE(_LT_PUBLIC)) {
    MEDINTEREST(lDebug ? "US-Govt(1)" : "Gov't-rights");
  }
  else if (INFILE(_LT_USGOVT_RIGHTS2)) {
    MEDINTEREST(lDebug ? "US-Govt(2)" : "Gov't-rights");
  }
  /*
   * AACA (Ada Conformity Assessment Authority)
   */
  if (INFILE(_LT_ACAA_RIGHTS) && INFILE(_LT_PUBLIC)) {
    INTERESTING("ACAA");
  }
  /* Zend Engine License
   */
  if (INFILE(_LT_ZEND_1) || INFILE(_URL_ZEND)) {
    INTERESTING("Zend-2.0");
  }

  /* WebM */
  if (INFILE(_URL_WEBM)) {
    INTERESTING("WebM");
  }

  /* Dyade Public License
   * http://old.koalateam.com/jackaroo/DYADE_PUBLIC_LICENSE.TXT
   */
  if (INFILE(_LT_DYADE) && INFILE(_LT_DYADE_2))
  {
    INTERESTING("Dyade");
  }
  /*
   * Zimbra
   */ 
  if (INFILE(_TITLE_ZIMBRA_13)) {
     INTERESTING("Zimbra-1.3");
  }
  else if (INFILE(_TITLE_ZIMBRA)) {
     INTERESTING("Zimbra");
  }
  /*
   * Open Database
   */
  if (INFILE(_TITLE_ODBL)) {
     INTERESTING("ODbl-1.0");
     lmem[_fODBL] = 1;
  }
  /*
   * Multics
   */
  if (INFILE(_LT_MULTICS)) {
     INTERESTING("Multics");
  }

  /*
   * The Stallman paper "Why Software Should Be Free" is a red-herring.
   * His 1986 interview in Byte magazine also is, too.
   */
  if ((INFILE(_TITLE_RMS_WSSBF) && INFILE(_PHR_RMS1) &&
      INFILE(_PHR_RMS2)) || INFILE(_TITLE_RMS_BYTE86)) {
    if (lDiags) {
      printf("... RMS propaganda\n");
    }
    lmem[_fDOC] = 1;
  }
  else if (!lmem[_mGPL] && INFILE(_LT_EXCEPT_1)) {
    INTERESTING("Link-exception");
  }

  /*
   * If there's a no-warranty statement in the file, remember the regex.
   * Ditto for phrase stating restrictions.
   */
  if (maxInterest != IL_HIGH) {
    for (i = 0; i < NNOWARRANTY; i++) {
      if (INFILE((j = _NO_WARRANTY_first+i))) {
        nw = j;
        break;
      }
    }
    if (HASTEXT(_PHR_RESTRICTIONS_1, REG_EXTENDED)) {
      rs = _PHR_RESTRICTIONS_1;
    }
    else if (INFILE(_PHR_RESTRICTIONS_2)) {
      rs = _PHR_RESTRICTIONS_2;
    }
  }
  /*
   * Statements about IP (Intellectual Property) rights
   */
  if (!lmem[_fIP] && INFILE(_LT_GEN_IP_1)) {
    INTERESTING(lDebug ? "IP(1)" : "IP-claim");
  }
  else if (!lmem[_fIP] && INFILE(_LT_GEN_IP_2) && !INFILE(_TITLE_MIROS)) {
    INTERESTING(lDebug ? "IP(2)" : "IP-claim");
  }
  else if (!lmem[_fIP] && INFILE(_LT_GEN_IP_3)) {
    INTERESTING(lDebug ? "IP(3)" : "IP-claim");
  }

  /* 
   * Dual licenses
   */
  if (INFILE(_LT_DUAL_LICENSE_1)) {
    INTERESTING(lDebug ? "Dual-license(1)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_2)) { 
    INTERESTING(lDebug ? "Dual-license(2)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_3) && !INFILE(_LT_DUAL_LICENSE_3_EXHIBIT)) {
    INTERESTING(lDebug ? "Dual-license(3)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_4)) {
    INTERESTING(lDebug ? "Dual-license(4)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_5)) {
    INTERESTING(lDebug ? "Dual-license(5)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_6)) {
    INTERESTING(lDebug ? "Dual-license(6)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_7)) {
    INTERESTING(lDebug ? "Dual-license(7)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_8)) {
    INTERESTING(lDebug ? "Dual-license(8)" : "Dual-license");
  }

  /*
   * The Beer-ware license(!)
   */
  if (*licStr == NULL_CHAR && INFILE(_LT_BEERWARE)) {
    LOWINTEREST("Beerware");
  }
  /* 
   * CMake license
   */
  if (INFILE(_URL_CMAKE)) {
    INTERESTING("CMake");
  }
  /*
   * unRAR restriction
   */
  if (INFILE(_LT_UNRARref1) || INFILE(_LT_UNRARref2)) {
    INTERESTING("unRAR restriction");
  }

  /*
   * ANTLR Software Rights Notice
   */
  if (INFILE(_LT_ANTLR)) {
    INTERESTING("ANTLR-PD");
    lmem[_fANTLR] = 1;
  }

  /*
   * Creative Commons Zero v1.0 Universal
   */
  if (INFILE(_TITLE_CC0_10)) {
    INTERESTING("CC0-1.0");
  }

  /*
   * PA Font License (IPA)
   */
  if (INFILE(_TITLE_IPA)) {
    INTERESTING("IPA");
  }

  /*
   * European Union Public Licence 
   */
  if (INFILE(_TITLE_EUPL_V10)) {
    INTERESTING("EUPL-1.0");
  }
  else if (INFILE(_TITLE_EUPL_V11)) {
    INTERESTING("EUPL-1.1");
  }

  /** University of Illinois/NCSA Open Source License */
  if (INFILE(_TITLE_NCSA) && !INFILE(_TITLE_NCSA_EXHIBIT)) {
    INTERESTING("NCSA");
    lmem[_fBSD] = 1;
    lmem[_mMIT] = 1;
  }

  /** ODC Public Domain Dedication & License 1.0 */
  if (INFILE(_TITLE_PDDL)) {
    INTERESTING("PDDL-1.0");
    lmem[_fPDDL] = 1;
  }

  /** PostgreSQL License */
  if (INFILE(_TITLE_POSTGRES)) {
    INTERESTING("PostgreSQL");
    lmem[_fBSD] = 1;
  }

  /**   Sax Public Domain Notice */
  if (INFILE(_LT_SAX_PD)) {
    INTERESTING("SAX-PD");
    lmem[_fSAX] = 1;
  }

  /*
   * WTF Public "license"
   */
  if (*licStr == NULL_CHAR && INFILE(_LT_WTFPL)) {
    LOWINTEREST("WTF-PL");
  }
  if (*licStr == NULL_CHAR && INFILE(_LT_WTFPLref)) {
    LOWINTEREST(lDebug ? "WTF-PL(ref)" : "WTF-PL");
  }
  /*
   * Some licenses point you to files/URLs...
   */
  if (*licStr == NULL_CHAR) {
    checkFileReferences(filetext, size, score, kwbm, isML, isPS);
  }
  /*
   * Some licenses say "licensed under the same terms as FOO".
   */
  gl.flags |= FL_SAVEBASE; /* save match buffer (if any) */
  if (*licStr == NULL_CHAR) {
    i = 0;
    if (INFILE(_LT_SAME_LICENSE_1)) {
      INTERESTING(lDebug ? "Same-lic-1" : "Same-license-as");
      i = 1;
    } else if (INFILE(_LT_SAME_LICENSE_2)) {
      INTERESTING(lDebug ? "Same-lic-2" : "Same-license-as");
      i = 2;
    }
    if (i) {
      if (cur.licPara == NULL_STR) {
        saveLicenseParagraph(cur.matchBase, isML, isPS, NO);
      }
    }
  }
  gl.flags |= ~FL_SAVEBASE; /* turn off, regardless */
  /*
   * ... and, there are several generic claims that "this is free software".
   * For lack of a better, more generally-accepted term, we called these
   * claims "BSD-lite", after the notion of the BSD "gift" licenses.
   */
  if (*licStr == NULL_CHAR) {
    for (i = 0; i < NFREECLAIM; i++) {
      if (CANSKIP(i, _KW_permission, _FREE_first_perm,
          _FREE_last_perm)) {
        i = _FREE_last_perm;
        continue;
      }
      if (CANSKIP(i, _KW_distribut, _FREE_first_dist,
          _FREE_last_dist)) {
        i = _FREE_last_dist;
        continue;
      }
      if (INFILE(_FREECLAIM_first+i)) {
        (void) strcpy(name, "BSD-lite");
        if (lDebug) {
          (void) sprintf(name+8, "(%d)", i+1);
        }
        INTERESTING(name);
        break;
      }
    }
  }

  /* Check for Public Domain */
  if (!lmem[_fANTLR] && !lmem[_fCCBY] && !lmem[_fCLA] && !lmem[_mPYTHON] && !lmem[_mGFDL] &&
      !lmem[_fODBL] && !lmem[_fPDDL] && !lmem[_fRUBY] && !lmem[_fSAX] && !lmem[_fAPL] &&!lmem[_mAPACHE] &&
      !lmem[_fARTISTIC]) {
    pd = checkPublicDomain(filetext, size, score, kwbm, isML, isPS);
  }

  /*
   * NOW look for unclassified licenses, if we haven't found anything yet.
   * And avoid checking .po files -- message catalogues are known to have
   * false-positives.
   *****
   * FIX-ME: if a file contains ONLY a "no warranty" description/clause, it
   * will (currently) get flagged as an UnclassifiedLicense (so the check
   * for no-warranty was moved ABOVE this check in case we can use that info)
   */
  if (maxInterest != IL_HIGH && !lmem[_fDOC]) {
    if (!pd &&
        checkUnclassified(filetext, size, scp->score, isML,
            isPS, nw)) {
      strcpy(name, LS_UNCL);
      if (isPS) {
        strcat(name, "(PS)");
      }
      MEDINTEREST(name);
      checkCornerCases(filetext, size, score, kwbm, isML, isPS, nw, YES);
    }
#ifdef  UNKNOWN_CHECK_DEBUG
    else {
      printf("... NOT an Unclassified license, NW %d PD %d\n",
          nw, pd);
    }
#endif  /* UNKNOWN_CHECK_DEBUG */
  }
  listClear(&whereList, NO);      /* clear "unused" matches */
  /*
   * And, If no other licenses are present but there's a reference to
   * something being non-commercial, better note it now.
   */
#if 0
  if (*licStr == NULL_CHAR && !HASKW(kwbm, _KW_public_domain))
#endif
    if (maxInterest != IL_HIGH && !HASKW(kwbm, _KW_public_domain) &&
        !INFILE(_PHR_COMMERC_NONCOMM)) {
      if (INFILE(_LT_NONCOMMERCIAL_1)) {
        INTERESTING(lDebug ? "NonC(1)" : "Non-commercial!");
      }
      else if (INFILE(_LT_ZZNON_COMMERC1)) {
        INTERESTING(lDebug ? "NonC(2)" : "Non-commercial!");
      }
      else if (INFILE(_LT_ZZNON_COMMERC2)) {
        INTERESTING(lDebug ? "NonC(3)" : "Non-commercial!");
      }
      else if (HASTEXT(_TEXT_COMMERC, 0) &&
          INFILE(_PHR_NONCOMMERCIAL)) {
        INTERESTING(lDebug ? "NonC(4)" : "Non-commercial!");
      }
    }
  if (INFILE(_LT_NOT_OPENSOURCE)) {
    INTERESTING("Not-OpenSource!");
  }
  listClear(&whereList, NO);      /* again, clear "unused" matches */
  /*
   * Look for footprints that declare something as proprietary... if we such
   * a statement, we care about the Copyright owner, too.
   */
  if (maxInterest != IL_HIGH) { /* if (*licStr == NULL_CHAR) { */
    j = 0;  /* just a flag */
    if (INFILE(_LT_GEN_PROPRIETARY_1)) {
      INTERESTING(lDebug ? "Prop(1)" : "Proprietary!");
      j++;
    }
    else if (INFILE(_LT_GEN_PROPRIETARY_2)) {
      INTERESTING(lDebug ? "Prop(2)" : "Proprietary!");
      j++;
    }
    else if (INFILE(_LT_GEN_PROPRIETARY_3)) {
      INTERESTING(lDebug ? "Prop(3)" : "Proprietary!");
      j++;
    }
    if (j) {
      checkCornerCases(filetext, size, score, kwbm, isML,
          isPS, nw, YES);
    }
  }
  listClear(&whereList, NO);      /* again, clear "unused" matches */
  /*
   * END of license-footprint checking of known patterns/strings
   ******
   * Plan-B: look for things attorneys generally believe aren't "overly
   * legally-binding" or "uninterestring from a license perspective':
   * ... notable references to commercial, non-commercial, not-for-profit,
   * not-an-open-source-license, etc.
   */
  if (maxInterest != IL_HIGH) {
    if (INFILE(_LT_COMMERCIAL_1)) {
      MEDINTEREST(lDebug ? "COMM(1)" : "COMMERCIAL");
    }
    else if (INFILE(_LT_COMMERCIAL_2)) {
      MEDINTEREST(lDebug ? "COMM(2)" : "COMMERCIAL");
    }
    else if (HASTEXT(_TEXT_BOOK, 0) && INFILE(_LT_BOOKPURCHASE)) {
      MEDINTEREST(lDebug ? "PurchBook" : "COMMERCIAL");
    }
    if (INFILE(_LT_NONPROFIT_1)) {
      MEDINTEREST(lDebug ? "NonP(1)" : "Non-profit!");
    }
    else if (!lmem[_mPYTH_TEXT] && HASTEXT(_TEXT_PROFIT, 0) &&
        INFILE(_PHR_NONPROFIT)) {
      if (!(lmem[_fIETF] + lmem[_fDOC])) {
        MEDINTEREST(lDebug ? "NonP(2)" : "Non-profit!");
      }
    }
    if (INFILE(_PHR_NO_SALE)) {
      MEDINTEREST("Not-for-sale!");
    }
    if (!lmem[_mALADDIN] && INFILE(_PHR_NOT_OPEN)) {
      MEDINTEREST("NOT-Open-Source!");
    }
    if (HASKW(kwbm, _KW_patent) && INFILE(_PHR_PATENT)) {
      MEDINTEREST("Patent-ref");
    }
    if (INFILE(_PHR_RESTRICT_RIGHTS)) {
      if (INFILE(_PHR_USGOVT_RESTRICT)) {
        MEDINTEREST("Govt-restrict");
      }
      else {
        MEDINTEREST("Restricted-rights");
      }
    }
    if (INFILE(_LT_EXPORTS_USA)) {
      MEDINTEREST("US-Export-restrict");
    }
    if (pd < 0) {
      checkPublicDomain(filetext, size, score, kwbm, isML, isPS);
    }
  }
  listClear(&whereList, NO);      /* again, clear "unused" matches */
  /*
   * These "minimal-reference possibilities" MUST adhere to a prescribed
   * format: the name to be used MUST be the first of several name to match:
   * "(NAME identifier|NAME license|license NAME)"
   *****
   * Furthermore, licenses should be ordered from the most-specific to the
   * least-specific; e.g., look for LGPL_v3 before looking for LGPL -- the
   * strGrep() calls below ensure reporting only more-specific license
   * versions if found.  For instance, if you order LGPL *before* LGPL_v3
   * we'll get both LGPLv3 and LGPL, which is redundant AND looks stupid.
   */
  if (*licStr == NULL_CHAR && HASKW(kwbm, _KW_license)) {
    for (i = 0, j = _MINlicense_first; i < NMINlicense; i++, j++) {
      if (dbgIdxGrep(j, filetext, lDiags)) {
        cp = strchr(_REGEX(j), ' ');
        if (cp == NULL_STR) {
          Assert(NO, "Bad reference[1] %d", j);
          continue;
        }
        *cp = NULL_CHAR;
        if (!(*licStr) || !strGrep(_REGEX(j)+1, licStr,
            REG_ICASE)) {
          (void) sprintf(name, "%s-possibility",
              _REGEX(j)+1);
          LOWINTEREST(name);
        }
        *cp = ' ';
      }
    }
  }
  listClear(&whereList, NO);      /* again, clear "unused" matches */
  if (*licStr == NULL_CHAR && HASTEXT(_TEXT_SOURCE, 0)) {
    for (i = 0, j = _MINsource_first; i < NMINsource; i++, j++) {
      if (dbgIdxGrep(j, filetext, lDiags)) {
        cp = strchr(_REGEX(j), ' ');
        if (cp == NULL_STR) {
          Assert(NO, "Bad reference[2] %d", j);
          continue;
        }
        *cp = NULL_CHAR;
        if (!(*licStr) || !strGrep(_REGEX(j)+1, licStr,
            REG_ICASE)) {
          (void) sprintf(name, "%s-possibility",
              _REGEX(j)+1);
          LOWINTEREST(name);
        }
        *cp = ' ';
      }
    }
  }
  listClear(&whereList, NO);      /* again, clear "unused" matches */
  if (*licStr == NULL_CHAR && HASTEXT(_KW_copyright, 0)) {
    for (i = 0, j = _MINcpyrt_first; i < NMINcpyrt; i++, j++) {
      if (dbgIdxGrep(j, filetext, lDiags)) {
        cp = strchr(_REGEX(j), ' ');
        if (cp == NULL_STR) {
          Assert(NO, "Bad reference[2] %d", j);
          continue;
        }
        *cp = NULL_CHAR;
        if (!(*licStr) || !strGrep(_REGEX(j)+1, licStr,
            REG_ICASE)) {
          (void) sprintf(name, "%s-possibility",
              _REGEX(j)+1);
          LOWINTEREST(name);
        }
        *cp = ' ';
      }
    }
  }
  listClear(&whereList, NO);      /* again, clear "unused" matches */
  /*
   * If we still haven't found anything, check for the really-low interest
   * items such as copyrights, and references to keywords (patent/trademark)
   */
  if (maxInterest != IL_HIGH && pd <= 0) {
    checkCornerCases(filetext, size, score, kwbm, isML, isPS,
        nw, NO);
  }
#ifdef  MEMSTATS
  printf("DEBUG: static lists in parseLicenses():\n");
  listDump(&searchList, -1);
  memStats("parseLicenses: pre-Free");
#endif  /* MEMSTATS */
  listClear(&searchList, NO);
#ifdef  MEMSTATS
  memStats("parseLicenses: EOP");
#endif  /* MEMSTATS */
#ifdef  LTSR_DEBUG
  showLTCache("LTSR-results AFTER:");
#endif  /* LTSR_DEBUG */
#ifdef  FLAG_NO_COPYRIGHT
  if (!SEEN(_CR_ZZZANY)) {
    (void) INFILE(_CR_ZZZANY);
  }
  if (!SEEN(_CR_ZZZWRONG_1)) {
    (void) INFILE(_CR_ZZZWRONG_1);
  }
  if (LVAL(_CR_ZZZANY)+LVAL(_CR_ZZZWRONG_1)+
      HASREGEX(_CR_ZZZWRONG_2, filetext) == 0) {
    gl.flags |= FL_NOCOPYRIGHT;
  }
#endif  /* FLAG_NO_COPYRIGHT */
  listClear(&whCacheList, NO);
  if (whereList.used) {
    listClear(&whereList, NO);      /* may already be cleared! */
  }
  return(licStr+1);       /* don't include the leading comma */
}


char *sisslVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;

#ifdef  PROC_TRACE
  traceFunc("== sisslVersion()\n");
#endif  /* PROC_TRACE */

  if (INFILE(_TITLE_SISSL_V11)) {
    lstr = "SISSL-1.1";
  } else {
    lstr = "SISSL";
  }
  return lstr;
}

char *aslVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;

#ifdef  PROC_TRACE
  traceFunc("== aslVersion()\n");
#endif  /* PROC_TRACE */

  if (INFILE(_TITLE_PHORUM) || INFILE(_CR_PHORUM)) {
    lstr = "Phorum";
  }
  else if (INFILE(_CR_IMAGEMAGICK)) {
    lstr = "ImageMagick(Apache)";
  }
  else if (INFILE(_TITLE_ASL20)) {
    lstr = (lDebug ? "Apache-2(f)" : "Apache-2.0");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_TITLE_ASL11)) {
    lstr = (lDebug ? "Apache-1.1(f)" : "Apache-1.1");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_LT_ASLref1)) {
    lstr = (lDebug ? "Apache-1.0(f)" : "Apache-1.0");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_LT_ASLref2)) {
    lstr = (lDebug ? "Apache-1.0(g)" : "Apache-1.0");
    lmem[_mAPACHE] = 1;
  }
  else if (URL_INFILE(_URL_APACHE_20)) {
    lstr = (lDebug ? "Apache-2.0(u)" : "Apache-2.0");
    lmem[_mAPACHE] = 1;
  }
  else if (URL_INFILE(_URL_APACHE_11)) {
    lstr = (lDebug ? "Apache-1.1(u)" : "Apache-1.1");
    lmem[_mAPACHE] = 1;
  }
  else if (URL_INFILE(_URL_APACHE_10)) {
    lstr = (lDebug ? "Apache-1.0(u)" : "Apache-v1.0");
    lmem[_mAPACHE] = 1;
  }
  else {
    lstr = (lDebug ? "Apache(def)" : "Apache");
  }
  return lstr;
}

char *mplNplVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;

#ifdef  PROC_TRACE
  traceFunc("== mplNplVersion()\n");
#endif  /* PROC_TRACE */

  if (INFILE(_TITLE_NPL11_OR_LATER)) {
    lstr = "NPL-1.1+";
  }
  else if (INFILE(_TITLE_NPL11)) {
    lstr = "NPL-1.1";
  }
  else if (INFILE(_TITLE_NPL10)) {
    lstr = "NPL-1.0";
  }
  else if (INFILE(_TITLE_MPL11_OR_LATER)) {
    lstr = "MPL-1.1+";
  }
  else if (INFILE(_TITLE_MPL11)) {
    lstr = "MPL-1.1";
  }
  else if (INFILE(_TITLE_MPL20) || INFILE(_URL_MPL20)) {
    lstr = "MPL-2.0";
  }
  else if (INFILE(_TITLE_MPL10) && INFILE(_TITLE_ERLPL)) {
    lstr = "ErlPL-1.1";
  }
  else if (INFILE(_TITLE_MPL10)) {
    lstr = "MPL-1.0";
  }
  else if (INFILE(_TITLE_MPL_EULA_30)) {
    lstr = "MPL-EULA-3.0";
  }
  else if (INFILE(_TITLE_MPL_EULA_20)) {
    lstr = "MPL-EULA-2.0";
  }
  else if (INFILE(_TITLE_MPL_EULA_11)) {
    lstr = "MPL-EULA-1.1";
  }
  else if (URL_INFILE(_URL_NPL10)) {
    lstr = (lDebug ? "NPL1.0(url)" : "NPL-1.0");
  }
  else if (URL_INFILE(_URL_NPL11)) {
    lstr = (lDebug ? "NPL1.1(url)" : "NPL-1.1");
  }
  else if (URL_INFILE(_URL_MPL10)) {
    lstr = (lDebug ? "MPL1.0(url)" : "MPL-1.0");
  }
  else if (URL_INFILE(_URL_MPL11)) {
    lstr = (lDebug ? "MPL1.1(url)" : "MPL-1.1");
  }
  else if (INFILE(_FILE_MPL10)) {
    lstr = (lDebug ? "MPL1.0(file)" : "MPL-1.0");
  }
  else if (INFILE(_FILE_MPL11)) {
    lstr = (lDebug ? "MPL1.1(file)" : "MPL-1.1");
  }
  else if (URL_INFILE(_URL_NPL)) {
    lstr = (lDebug ? "NPL(url)" : "NPL");
  }
  else if (URL_INFILE(_URL_MPL)) {
    lstr = (lDebug ? "MPL(url)" : "MPL");
  }
  else if (INFILE(_TITLE_NPL)) {
    lstr = "NPL";
  }
  else if (INFILE(_TITLE_MPL)) {
    lstr = "MPL";
  }
  else {
    lstr = (lDebug ? "MPL(last)" : "MPL");
  }
  return lstr;
}


char *realVersion(char *filetext, int size, int isML, int isPS, int ref)
{
  char *lstr = NULL_STR;

#ifdef  PROC_TRACE
  traceFunc("== realVersion()\n");
#endif  /* PROC_TRACE */

  if (ref == _TITLE_RPSL) {
    if (INFILE(_TITLE_RPSL_V30)) {
      lstr = "RPSL-3.0";
    }
    else if (INFILE(_TITLE_RPSL_V20)) {
      lstr = "RPSL-2.0";
    }
    else if (INFILE(_TITLE_RPSL_V10)) {
      lstr = "RPSL-1.0";
    }
    else if (INFILE(_TITLE_RPSL)) {
      lstr = "RPSL";
    }
  }
  else if (ref == _TITLE_RCSL) {
    if (INFILE(_TITLE_RCSL_V30)) {
      lstr = "RCSL-3.0";
    }
    else if (INFILE(_TITLE_RCSL_V20)) {
      lstr = "RCSL-2.0";
    }
    else if (INFILE(_TITLE_RCSL_V10)) {
      lstr = "RCSL-1.0";
    }
    else if (INFILE(_TITLE_RCSL)) {
      lstr = "RCSL";
    }
  }
  else {
    lstr = "RealNetworks-Unknown";
  }
  return lstr;
}


char *pythonVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;

#ifdef  PROC_TRACE
  traceFunc("== pythonVersion()\n");
#endif  /* PROC_TRACE */

  if (INFILE(_TITLE_PYTHON201)) {
    lstr = "Python-2.0.1";
  }
  else if (INFILE(_TITLE_PYTHON202)) {
    lstr = "Python-2.0.2";
  }
  else if (INFILE(_TITLE_PYTHON211)) {
    lstr = "Python-2.1.1";
  }
  else if (INFILE(_TITLE_PYTHON213)) {
    lstr = "Python-2.1.3";
  }
  else if (INFILE(_TITLE_PYTHON223)) {
    lstr = "Python-2.2.3";
  }
  else if (INFILE(_TITLE_PYTHON227)) {
    lstr = "Python-2.2.7";
  }
  else if (INFILE(_TITLE_PYTHON237)) {
    lstr = "Python-2.3.7";
  }
  else if (INFILE(_TITLE_PYTHON244)) {
    lstr = "Python-2.4.4";
  }
  else if (INFILE(_TITLE_PYTHON22)) {
    lstr = "Python-2.2";
  }
  else if (INFILE(_TITLE_PYTHON23)) {
    lstr = "Python-2.3";
  }
  else if (INFILE(_TITLE_PYTHON2)) {
    lstr = "Python-2.0";
  }
  else {
    lstr = "Python";
  }
  return lstr;
}

char *aflVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;

#ifdef  PROC_TRACE
  traceFunc("== aflVersion()\n");
#endif  /* PROC_TRACE */

  if (INFILE(_TITLE_AFL30)) {
    lstr = lDebug? "AFL(v3.0#1)" : "AFL-3.0";
  }
  else if (INFILE(_TITLE_AFL21)) {
    lstr = lDebug? "AFL(v2.1#1)" : "AFL-2.1";
  }
  else if (INFILE(_TITLE_AFL20)) {
    lstr = lDebug? "AFL(v2.0#1)" : "AFL-2.0";
  }
  else if (INFILE(_TITLE_AFL12)) {
    lstr = lDebug? "AFL(v1.2#1)" : "AFL-1.2";
  }
  else if (INFILE(_TITLE_AFL11)) {
    lstr = lDebug? "AFL(v1.1#1)" : "AFL-1.1";
  }
  else if (INFILE(_TITLE_AFL10)) {
    lstr = lDebug? "AFL(v1.0#1)" : "AFL-1.0";
  }
  else {
    lstr = "AFL";
  }
  return lstr;
}


char *oslVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;
  /* */
#ifdef  PROC_TRACE
  traceFunc("== oslVersion()\n");
#endif  /* PROC_TRACE */
  /* */
  if (INFILE(_TITLE_NON_PROFIT_OSL30)) {
    lstr = "NPOSL-3.0";
  }
  else if (INFILE(_TITLE_OSL30)) {
    lstr = lDebug? "OSL(v3.0#1)" : "OSL-3.0";
  }
  else if (INFILE(_TITLE_OSL21)) {
    lstr = lDebug? "OSL(v2.1#1)" : "OSL-2.1";
  }
  else if (INFILE(_TITLE_OSL20)) {
    lstr = lDebug? "OSL(v2.0#1)" : "OSL-2.0";
  }
  else if (INFILE(_TITLE_OSL11)) {
    lstr = lDebug? "OSL(v1.1#1)" : "OSL-1.1";
  }
  else if (INFILE(_TITLE_OSL10)) {
    lstr = lDebug? "OSL(v1.0#1)" : "OSL-1.0";
  }
  else {
    lstr = "OSL";
  }
  return lstr;
}


char *cddlVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;
  /* */
#ifdef  PROC_TRACE
  traceFunc("== cddlVersion()\n");
#endif  /* PROC_TRACE */
  /* */
  if (INFILE(_TITLE_CDDL_V10)) {
    lstr = "CDDL-1.0";
  }
  else if (INFILE(_LT_CDDL10ref)) {
    lstr = "CDDL-1.0";
  }
  else if (URL_INFILE(_URL_CDDL_V1)) {
    lstr = "CDDL-1.0";
  }
  else if (INFILE(_TITLE_CDDL_V11)) {
    lstr = "CDDL-1.1";
  }
  else {
    lstr = "CDDL";
  }
  return lstr;
}


char *lpplVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;
  /* */
#ifdef  PROC_TRACE
  traceFunc("== lpplVersion()\n");
#endif  /* PROC_TRACE */
  /* */
  if (INFILE(_PHR_LATEX_PL13A_OR_LATER_1) ||
      INFILE(_PHR_LATEX_PL13A_OR_LATER_2)) {
    if (INFILE(_LT_LATEX_PREAMBLE)) {
      lstr = lDebug ? "LPPL-v1.3a(#1)" : "LPPL-1.3a";
    }
    else {
      lstr = "LPPL-1.3a+";
    }
  }
  else if (INFILE(_TITLE_LATEX_PL13A) || INFILE(_PHR_LATEX_PL13A)) {
    lstr = lDebug ? "LPPL-v1.3a(#2)" : "LPPL-1.3a";
  }
  else if (INFILE(_PHR_LATEX_PL13B_OR_LATER_1) ||
      INFILE(_PHR_LATEX_PL13B_OR_LATER_2)) {
    if (INFILE(_LT_LATEX_PREAMBLE)) {
      lstr = lDebug ? "LPPL-v1.3b(#1)" : "LPPL-1.3b";
    }
    else {
      lstr = "LPPL-1.3b+";
    }
  }
  else if (INFILE(_TITLE_LATEX_PL13B) || INFILE(_PHR_LATEX_PL13B)) {
    lstr = lDebug ? "LPPL-v1.3b(#2)" : "LPPL-1.3b";
  }
  else if (INFILE(_PHR_LATEX_PL13C_OR_LATER_1) ||
      INFILE(_PHR_LATEX_PL13C_OR_LATER_2)) {
    if (INFILE(_LT_LATEX_PREAMBLE)) {
      lstr = lDebug ? "LPPL-v1.3c(#1)" : "LPPL-1.3c";
    }
    else {
      lstr = "LPPL-1.3c+";
    }
  }
  else if (INFILE(_TITLE_LATEX_PL13C) || INFILE(_PHR_LATEX_PL13C)) {
    lstr = lDebug ? "LPPL-v1.3c(#2)" : "LPPL-1.3c";
  }
  else if (INFILE(_PHR_LATEX_PL13_OR_LATER_1) ||
      INFILE(_PHR_LATEX_PL13_OR_LATER_2)) {
    if (INFILE(_LT_LATEX_PREAMBLE)) {
      lstr = lDebug ? "LPPL-v1.3(#1)" : "LPPL-1.3";
    }
    else {
      lstr = "LPPL-1.3+";
    }
  }
  else if (INFILE(_TITLE_LATEX_PL13) || INFILE(_PHR_LATEX_PL13)) {
    lstr = lDebug ? "LPPL-v1.3(#2)" : "LPPL-1.3";
  }
  else if (INFILE(_PHR_LATEX_PL12_OR_LATER_1) ||
      INFILE(_PHR_LATEX_PL12_OR_LATER_2)) {
    if (INFILE(_LT_LATEX_PREAMBLE)) {
      lstr = lDebug ? "LPPL-v1.2(#1)" : "LPPL-1.2";
    }
    else {
      lstr = "LPPL-1.2+";
    }
  }
  else if (INFILE(_TITLE_LATEX_PL12) || INFILE(_PHR_LATEX_PL12)) {
    lstr = lDebug ? "LPPL-v1.2(#2)" : "LPPL-1.2";
  }
  else if (INFILE(_PHR_LATEX_PL11_OR_LATER_1) ||
      INFILE(_PHR_LATEX_PL11_OR_LATER_2)) {
    if (INFILE(_LT_LATEX_PREAMBLE)) {
      lstr = lDebug ? "LPPL-v1.1(#1)" : "LPPL-1.1";
    }
    else {
      lstr = "LPPL-1.1+";
    }
  }
  else if (INFILE(_TITLE_LATEX_PL11) || INFILE(_PHR_LATEX_PL11)) {
    lstr = lDebug ? "LPPL-v1.1(#2)" : "LPPL-1.1";
  }
  else if (INFILE(_PHR_LATEX_PL10_OR_LATER_1) ||
      INFILE(_PHR_LATEX_PL10_OR_LATER_2)) {
    if (INFILE(_LT_LATEX_PREAMBLE)) {
      lstr = lDebug ? "LPPL-v1.0(#1)" : "LPPL-1.0";
    }
    else {
      lstr = "LPPL-1.0+";
    }
  }
  else if (INFILE(_TITLE_LATEX_PL10) || INFILE(_PHR_LATEX_PL10)) {
    lstr = lDebug ? "LPPL-v1.0(#2)" : "LPPL-1.0";
  }
  else {
    lstr = "LPPL";
  }
  return lstr;
}


char *agplVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;
  /* */
#ifdef  PROC_TRACE
  traceFunc("== agplVersion()\n");
#endif  /* PROC_TRACE */
  /* */
  /*
   * Look for version 1 BEFORE version 3; the text of version 1 license says
   * you can also license it under GPL version 3... same reasoning goes with
   * the actual v3 license (vs the reference).
   */
  if (INFILE(_PHR_AFFERO_V1_OR_LATER) ||
      INFILE(_TITLE_AFFERO_V1_OR_LATER)) {
    lstr = "AGPL-1.0+";
  }
  else if (INFILE(_PHR_FSF_V1_ONLY) || INFILE(_TITLE_AFFERO_V1_ONLY)) {
    lstr = "AGPL-1.0";
  }
  else if (INFILE(_PHR_AFFERO_V3_OR_LATER) ||
      INFILE(_TITLE_AFFERO_V3_OR_LATER)) {
    if (INFILE(_LT_AFFERO_V3)) {
      lstr = lDebug ? "Affero-v3(#1)" : "AGPL-3.0";
    }
    else {
      lstr = "AGPL-3.0+";
    }
  }
  else if (GPL_INFILE(_PHR_FSF_V3_ONLY)) {
    if (INFILE(_TITLE_GPL3)) {
      lstr = lDebug ? "GPLv3(Affero#1)" : "GPL-3.0";
    }
    else {
      lstr = lDebug ? "Affero-v3(#2)" : "AGPL-3.0";
    }
  }
  else if (INFILE(_TITLE_AFFERO_V3_ONLY)) {
    lstr = lDebug ? "Affero-v3(#3)" : "AGPL-3.0";
  }
  else if (INFILE(_TITLE_GPL3)) {
    lstr = lDebug ? "GPLv3(Affero#2)" : "GPL-3.0";
  }
  else if (URL_INFILE(_URL_AGPL3)) {
    lstr = lDebug ? "Affero-v3(url)" : "AGPL-3.0";
  }
  else {
    lstr = "Affero";
  }
  return lstr;
}


char *gfdlVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;
  /* */
#ifdef  PROC_TRACE
  traceFunc("== gfdlVersion()\n");
#endif  /* PROC_TRACE */
  /* */
  /*
   * Have to be careful here; the text of the 1.2 license says it's licensed
   * under 1.1 or later - have to be careful what we're looking for, and in a
   * specific order
   */
  if (INFILE(_TITLE_GFDL_V13_ONLY)) {
    lstr = lDebug ? "GFDL-1.3(#1)" : "GFDL-1.3";
  }
  else if (INFILE(_TITLE_GFDL_V12_ONLY)) {
    lstr = lDebug ? "GFDL-1.2(#1)" : "GFDL-1.2";
  }
  else if (INFILE(_TITLE_GFDL_V11_ONLY)) {
    lstr = lDebug ? "GFDL-1.1(#1)" : "GFDL-1.1";
  }
  else if (INFILE(_PHR_FSF_V12_OR_LATER) ||
      INFILE(_TITLE_GFDL_V12_OR_LATER)) {
    lstr = "GFDL-1.2+";
  }
  else if (INFILE(_PHR_FSF_V12_ONLY)) {
    lstr = lDebug ? "GFDL-1.2(#2)" : "GFDL-v1.2";
  }
  else if (INFILE(_PHR_FSF_V11_OR_LATER) ||
      INFILE(_TITLE_GFDL_V11_OR_LATER)) {
    lstr = "GFDL-1.1+";
  }
  else if (INFILE(_PHR_FSF_V11_ONLY)) {
    lstr = lDebug ? "GFDL-1.1(#2)" : "GFDL-1.1";
  }
  else {
    lstr = "GFDL";
  }
  return lstr;
}


char *lgplVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;
  /* */
#ifdef  PROC_TRACE
  traceFunc("== lgplVersion()\n");
#endif  /* PROC_TRACE */
  /* */
  if (INFILE(_PHR_LGPL3_OR_LATER) || INFILE(_PHR_FSF_V3_OR_LATER)) {
    lstr = "LGPL-3.0+";
  }
  else if (GPL_INFILE(_PHR_LGPL3_ONLY) || GPL_INFILE(_PHR_FSF_V3_ONLY) ||
      INFILE(_FILE_LGPLv3)) {
    lstr = "LGPL-3.0";
  }
  else if (INFILE(_PHR_LGPL21_OR_LATER) 
      || INFILE(_PHR_FSF_V21_OR_LATER)) {
    lstr = "LGPL-2.1+";
  }
  else if (INFILE(_PHR_LGPL21_ONLY) || INFILE(_PHR_FSF_V21_ONLY) ||
      INFILE(_FILE_LGPLv21) || INFILE(_URL_LGPL_V21)) {
    lstr = "LGPL-2.1";
  }
  else if (INFILE(_PHR_LGPL2_OR_LATER) ||
      RM_INFILE(_PHR_FSF_V2_OR_LATER)) {
    lstr = "LGPL-2.0+";
  }
  else if (RM_INFILE(_PHR_LGPL2_ONLY) || INFILE(_PHR_FSF_V2_ONLY) ||
      INFILE(_FILE_LGPLv2)) {
    lstr = "LGPL-2.0";
  }
  else if (INFILE(_PHR_LGPL1_OR_LATER) || INFILE(_PHR_FSF_V1_OR_LATER)) {
    lstr = "LGPL-1.0+";
  }
  else if (INFILE(_PHR_LGPL1_ONLY) || INFILE(_PHR_FSF_V1_ONLY)) {
    lstr = "LGPL-1.0";
  }
  else if (URL_INFILE(_URL_CCLGPL_V21)) {
    lstr = "CC-LGPL-2.1";
  }
  else if (INFILE(_LT_CC_GPL) || INFILE(_TITLE_CC_LGPL)) {
    lstr = "CC-LGPL";
  }
  else if (NY_INFILE(_TEXT_LGPLV3) && !INFILE(_TEXT_LGPLV3_FOOTNOTE) &&
      HASREGEX(_TEXT_LGPLV3, filetext)) {
    lstr = lDebug ? "LGPL-v3(#2)" : "LGPL-3.0";
  }
  else if (INFILE(_TEXT_LGPLV21) &&
      HASREGEX(_TEXT_LGPLV21, filetext)) {
    lstr = lDebug ? "LGPL-v2.1(#2)" : "LGPL-2.1";
  }
  else if (INFILE(_TEXT_LGPLV2) &&
      HASREGEX(_TEXT_LGPLV2, filetext)) {
    lstr = lDebug ? "LGPL-v2(#2)" : "LGPL-2.0";
  }
  else {
    lstr = "LGPL";
  }
  return lstr;
}


char *gplVersion(char *filetext, int size, int isML, int isPS)
{
  char *cp, *lstr = NULL_STR;
  /* */
#ifdef  PROC_TRACE
  traceFunc("== gplVersion()\n");
#endif  /* PROC_TRACE */
  /* */
  /* special case for Debian copyright files */
  if (INFILE(_TEXT_GPLV3_CR)) {
    INTERESTING("GPL-3.0+");
  }

  if (GPL_INFILE(_PHR_FSF_V21_OR_LATER) ||
      INFILE(_PHR_GPL21_OR_LATER)) {
    lstr = "GPL-2.1+";
  }
  else if (GPL_INFILE(_PHR_FSF_V2_OR_LATER) ||
      INFILE(_PHR_GPL2_OR_LATER)) {
    if (INFILE(_TITLE_GPL_KDE)) {
      lstr = "GPLv2+KDEupgradeClause";
    }
    else if (INFILE(_TITLE_GPL2)) {
      lstr = lDebug ? "GPL-v2(#1)" : "GPL-2.0";
    }
    else {
        lstr = "GPL-2.0+";
    }
  }
  else if (GPL_INFILE(_PHR_GPL2_OR_GPL3)) {
    lstr = "GPL-2:3";
  }
  else if (GPL_INFILE(_PHR_FSF_V3_OR_LATER) ||
      GPL_INFILE(_PHR_GPL3_OR_LATER)) {
    if (INFILE(_TITLE_GPL3)) {
      lstr = lDebug ? "GPL-v3(#1)" : "GPL-3.0";
    }
    else {
      lstr = "GPL-3.0+";
    }
  }
  else if (GPL_INFILE(_PHR_FSF_V3_ONLY) || GPL_INFILE(_PHR_GPL3_ONLY) ||
      INFILE(_FILE_GPLv3)) {
    lstr = lDebug ? "GPL-v3(#2)" : "GPL-3.0";
  }
  else if (INFILE(_PHR_FSF_V21_ONLY) || INFILE(_PHR_GPL21_ONLY)) {
    lstr = lDebug ? "GPL-v2.1" : "GPL-2.1";
  }
  else if (INFILE(_PHR_FSF_V2_ONLY) || INFILE(_PHR_GPL2_ONLY) ||
      INFILE(_FILE_GPLv2)) {
    lstr = lDebug ? "GPL-v2(#2)" : "GPL-2.0";
  }
  else if (GPL_INFILE(_PHR_FSF_V1_OR_LATER) ||
      INFILE(_PHR_GPL1_OR_LATER)) {
    if (INFILE(_TITLE_GPL1)) {
      lstr = lDebug ? "GPL-v1(#1)" : "GPL-1.0";
    }
    else {
      lstr = "GPL-1.0+";
    }
  }
  else if (INFILE(_PHR_FSF_V1_ONLY) || INFILE(_PHR_GPL1_ONLY)) {
    lstr = lDebug ? "GPL-v1(#2)" : "GPL-1.0";
  }
  else if (URL_INFILE(_URL_CCGPL_V2)) {
    lstr = "CC-GPL-2";
  }
  else if (INFILE(_LT_CC_GPL) || INFILE(_TITLE_CC_GPL)) {
    lstr = "CC-GPL";
  }
  else if (NY_INFILE(_TEXT_GPLV3) && !INFILE(_TEXT_GPLV3_FOOTNOTE) &&
      HASREGEX(_TEXT_GPLV3, filetext)) {
    lstr = lDebug ? "GPL-v3(#3)" : "GPL-3.0";
  }
  else if (NY_INFILE(_TEXT_GPLV2)) {
    lstr = lDebug ? "GPL-v2(#3)" : "GPL-2.0";
  }
  else if (NY_INFILE(_TEXT_GPLV1) &&
      HASREGEX(_TEXT_GPLV1, filetext)) {
    lstr = lDebug ? "GPL-v1(#3)" : "GPL-1.0";
  }
  else if (INFILE(_FILE_GPL1) || INFILE(_FILE_GPL2)) {
    lstr = lDebug ? "GPL(deb)" : "GPL";
  }
  /*
   * Special case, HACK: "Debian packaging ... licensed under GPL"
   *****
   * IF we've scanned the regex that might contain it, then kludge.buf != NULL.
   * Make darn sure that pointer IS set to NULL before leaving this routine.
   */
  if (lstr == NULL_STR && kludge.base != NULL_STR) {
#ifdef  PHRASE_DEBUG
    printf("GPL-META-CHECK: base %p, so %d eo %d\n",
        kludge.base, kludge.sso, kludge.seo);
#endif  /* PHRASE_DEBUG */
    cp = kludge.base + (kludge.sso < 256 ? 0 : kludge.sso-256);
    if (HASREGEX(_LT_GPL_META_DEBIAN, cp)) {
      lstr = "GPL-Meta";
    }
    kludge.base = NULL_STR;
  }
  if (lstr == NULL_STR) {
    lstr = "GPL";
  }
  return lstr;
}


char *cplVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;
  /* */
#ifdef  PROC_TRACE
  traceFunc("== cplVersion()\n");
#endif  /* PROC_TRACE */
  /* */
  if (INFILE(_TITLE_CPL10)) {
    lstr = "CPL-1.0";
  }
  else if (URL_INFILE(_URL_CPL_V10)) {
    lstr = "CPL-1.0";
  }
  else if (INFILE(_TITLE_CPL05)) {
    lstr = "CPL-0.5";
  }
  else {
    lstr = "CPL";
  }
  return lstr;
}


char *ccsaVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;
  /* */
#ifdef  PROC_TRACE
  traceFunc("== ccsaVersion()\n");
#endif  /* PROC_TRACE */
  /* */
  if (INFILE(_TITLE_CCA_SA_V10)) {
    lstr = "CC-BY-SA-1.0";
  }
  else if (INFILE(_TITLE_CCA_SA_V25)) {
    lstr = "CC-BY-SA-2.5";
  }
  else if (INFILE(_TITLE_CCA_SA_V30)) {
    lstr = "CC-BY-SA-3.0";
  }
  else if (INFILE(_TITLE_CCA_SA)) {
    lstr = lDebug ? "CCA-SA(1)" : "CC-BY-SA";
  }
  else if (INFILE(_URL_CCA_SA_V30)) {
    lstr = "CC-BY-SA-3.0";
  }
  else if (INFILE(_URL_CCA_SA_V25)) {
    lstr = "CC-BY-SA-2.5";
  }
  else if (INFILE(_URL_CCA_SA_V10)) {
    lstr = "CC-BY-SA-1.0";
  }
  else if (INFILE(_URL_CCA_SA)) {
    lstr = lDebug ? "CCA-SA(2)" : "CC-BY-SA";
  }
  else {
    lstr = lDebug ? "CCA-SA(def)" : "CC-BY-SA";
  }
  lmem[_fCCBY] = 1;
  return lstr;
}


char *ccVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;
  /* */
#ifdef  PROC_TRACE
  traceFunc("== ccVersion()\n");
#endif  /* PROC_TRACE */
  /* */
  if (INFILE(_TITLE_CCA_V10)) {
    lstr = "CC-BY-1.0";
  }
  else if (INFILE(_TITLE_CCA_V25)) {
    lstr = "CC-BY-2.5";
  }
  else if (INFILE(_TITLE_CCA_V30)) {
    lstr = "CC-BY-3.0";
  }
  else if (INFILE(_LT_CCA_ND_ref)) {
    lstr = "CC-BY-ND-3.0";
  }
  else if (INFILE(_TITLE_CCA)) {
    lstr = lDebug ? "CCA(1)" : "CC-BY";
  }
  else {
    lstr = lDebug ? "CCA(def)" : "CC-BY";
  } 
  lmem[_fCCBY] = 1;
  return lstr;
}

/**
 * \brief Check for the presence of a phrase in a file by first searching for
 * the search key provided.
 *
 * Cache the search results of, as we are very likely to be looking up the
 * same word/phrase again.
 *
 * @param int index, index of the phrase to be searched for
 * @param char *filetext, the text to search
 * @param int size the size of??
 * @param int isML medium level interest??
 * @param int isPS postscript file??
 * @param int qtype ??
 *
 * @return int ?? 0 means ??
 */
int findPhrase(int index, char *filetext, int size, int isML, int isPS,
    int qType)
{
  char *ptr = NULL_STR;
  char *q = ltsr+index;
  char *cp = 0;
  int ret;
  int i;
  int j;
  int n;
  int wordMatch = 0;
  int metaKludge = 0;
  int saved = 0;
  int sso;
  int seo;
  item_t *sp;
  item_t *op;
  list_t *lp;
  licText_t *ltp;
#ifdef  PARSE_STOPWATCH
  DECL_TIMER;     /* timer declaration */
  char timerName[64];
#endif  /* PARSE_STOPWATCH */

#if defined(PROC_TRACE) || defined(PHRASE_DEBUG) || defined(DOCTOR_DEBUG)
  traceFunc("== findPhrase(%d, %p, %d, %d, %d, %d)\n", index, filetext,
      size, isML, isPS, qType);
  traceFunc("... (regex) \"%s\"\n", _REGEX(index));
  traceFunc("... (seed) \"%s\"\n", _SEED(index));
#endif  /* PROC_TRACE || PHRASE_DEBUG || DOCTOR_DEBUG */

  ltp = licText + index;                    /* &licText[index] */
  if (ltp->tseed == NULL_STR) {
    LOG_FATAL("Regex #%d not suitable for findPhrase()", index)
              Bail(-__LINE__);
  }
  *q |= LTSR_SMASK;                       /* init: tested, no match */
#ifdef  PARSE_STOPWATCH
  (void) sprintf(timerName, "findPhrase(%03d): ", index);
  START_TIMER;
#endif  /* PARSE_STOPWATCH */
#if     (DEBUG > 5)
  printf("findPhrase: firstword[%d] == \"%s\", used = %d\n", index,
      ltp->tseed, searchList.used);
#endif  /* DEBUG > 5 */
  /*
   * See if the first-word of the requested entry has been searched
   * previously.  The entry we get from listGetItem() refCount (val) field is:
   *      =0 if this is the first search for this word (non-zero == "seen it"!)
   *      <0 if the word was NOT FOUND
   *      >0 if we have doctored-text results cached
   */
  if ((sp = listGetItem(&searchList, ltp->tseed)) == NULL_ITEM) {
    LOG_FATAL("search cache")
              Bail(-__LINE__);
  }
  if (sp->refCount < 0) {         /* tseed not found in text */
    sp->refCount--;
#ifdef  PARSE_STOPWATCH
    END_TIMER;      /* stop the timer */
    (void) strcat(timerName, "Cache-NO - ");
    (void) strcat(timerName, ltp->tseed);
    PRINT_TIMER(timerName, 0);
#endif  /* PARSE_STOPWATCH */
    return(0);
  }
  else if (sp->refCount == 0) {   /* e.g., first occurence */

    /*
     * Since this is the first search of this word, see if it's in the text.
     * NOTE: getInstances() returns a pointer to static (non-allocated) storage
     */
    if ((cur.nLines) <= 5) {
      i = 1;
      j = 2;
    } else if ((size / cur.nLines) <= 10) {
      i = 2;
      j = 4;
    } else {
      i = 3;
      j = 6;
    }

    /* change to not get record offsets since there is a good sized memory
     leak in that code */
    //ptr = getInstances(filetext, size, i, j, sp->str, YES);
    ptr = getInstances(filetext, size, i, j, sp->str, NO);

    if (ptr == NULL_STR) {
      sp->refCount = -1;
      /* sp->buf = NULL_STR; */
#ifdef  PARSE_STOPWATCH
      END_TIMER;      /* stop the timer */
      (void) strcat(timerName, "tseed-NO - ");
      (void) strcat(timerName, ltp->tseed);
      PRINT_TIMER(timerName, 0);
#endif  /* PARSE_STOPWATCH */
      return(0);      /* known !match */
    }
    sp->buf = copyString(ptr, "paragraph");
#ifdef  MEMSTATS
    printf("... adding %d bytes (search-lookup: \"%s\")\n",
        strlen(sp->buf), sp->str);
#endif  /* MEMSTATS */
    /*
     * If the search-seed and regex are the same, we found what we're looking
     * for.  Else, use doctorBuffer() to strip out punctuation, XML/HTML
     * directives, etc.
     */
#ifdef  PARSE_STOPWATCH
    START_TIMER;
#endif  /* PARSE_STOPWATCH */
#ifdef  DOCTOR_DEBUG
    printf(" ... doctoring buffer for \"%s\"\n", sp->str);
#endif  /* DOCTOR_DEBUG */
    (void) doctorBuffer(sp->buf, isML, isPS, NO);
#ifdef  PARSE_STOPWATCH
    RESET_TIMER;
    (void) sprintf(timerName, "... doctor(%03d): %s (%d)",
        index, ltp->tseed, strlen(sp->buf));
    PRINT_TIMER(timerName, 0);
#endif  /* PARSE_STOPWATCH */
  }
  sp->refCount++;                 /* incr ref-count for this seed */
  /*
   * We need populate the list of "paragraph matches" with offsets in the
   * doctored-up buffers; this seems the best place to do it.
   */
  op = listGetItem(&cur.offList, sp->str);
  if (op->nMatch <= 0) {
    LOG_FATAL("File-offset list, nMatch(%s): bad entry", sp->str)
              Bail(-__LINE__);
  }
#if     DEBUG>5
  printf("matches for key \"%s\": %d\n", sp->str, op->nMatch);
#endif  /* DEBUG>5 */
  n = strlen(sp->buf);
  lp = (list_t *)op->bList;
  if ((lp) && (lp->items[0].bDocLen == 0)) {
    if (op->nMatch == 1) {
      lp->items[0].bDocLen = n;
    } else {
      ptr = sp->buf;
      i = j = 0;      /* i is index, j is total offset */
      while (strGrep(" xyzzy ", ptr, REG_ICASE)) {
        lp->items[i++].bDocLen = j + cur.regm.rm_so;
        ptr += cur.regm.rm_eo;
        j += (cur.regm.rm_eo + 7);   /* strlen(" xyzzy ") */
      }
      lp->items[i].bDocLen = n + 7;     /* last paragraph */
    }
  }
  if  (ltp->tseed == ltp->regex) {        /* e.g., regex IS seed/key */
    wordMatch++;
    ret = 1;
  } else {
    metaKludge = ((qType == 2) && (index == _LT_GPLref1));
    if (metaKludge || qType == 4) {
      saved = 1;
      gl.flags |= FL_SAVEBASE;
    }
    ret = HASREGEX(index, sp->buf);
    if (saved) {
      if (ret) {
        kludge.base = cur.matchBase;
        kludge.sso = cur.regm.rm_so;
        kludge.seo = cur.regm.rm_eo;
      }
      gl.flags &= ~FL_SAVEBASE;
    }
  }
  sso = cur.regm.rm_so;            /* do NOT modify this!! */
  seo = cur.regm.rm_eo;            /* ... or this, either! */
  if (ret && !wordMatch) {
    *q = LTSR_YES;  /* remember this "yes" search result */

    /*
     * VERY low-level string-search kludges happen here.  This is VERY ugly!!
     *****
     * If we used idxGrep to search for "GPL" in a "doctored" buffer, we do NOT
     * want to match "lesser GPL" or "library GPL" in our searches (it's the
     * wrong license name/title).  Unfortunately, two aliases for LGPL are
     * exactly those above.  If we match "GPL" with a wildcard preceeding it, it
     * JUST MIGHT BE those words.  Rather than perform 2 exclusionary searches
     * one for "lesser gpl" and another for "library gpl", look here and if we
     * find it, (ugh) KLUDGE the return value to indicate "no match"...
     *****
     * However, since it's here, it's now extensible.  Next search-string kludge
     * (that cannot be quickly fixed via changing a regex) should be number 2.
     * Just make a new macro (defined above parseLicenses), ala GPL_INFILE().
     */
    if ((qType > 0) && !wordMatch) {
      if ((qType > 4) || (qType < 0)) {
        LOG_FATAL("Unknown string-search kludge %d", qType)
                Bail(-__LINE__);
      }
      /*
       * Special filter #1: over-write matched text with commas -- this choice is
       * significant because doctorBuffer() ensures 'stripped' text has no commas
       */
      if (qType == 1) {       /* destroy text-match */
        cp = sp->buf+seo;
        ptr = sp->buf+sso;
        ltsr[index] = 0;        /* reset search cache */
        while (ptr <= cp) {
          *ptr++ = ',';
        }
#ifdef  DEBUG
        if (lDiags) {
          printf("Now, buf %p contains:\n%s\n",
              sp->buf, (char *)sp->buf);
        }
#endif  /* DEBUG */
        /*
         * Special filter #2: various checks to make sure LGPL/GFDL/GPL license
         * references are not confused -- they CAN look quite similar.  Specifically
         * we don't want to confuse a SINGLE reference to the LGPL as a reference to
         * the GPL.  BUT -- sometimes references to BOTH are in the same file.
         */
      }
      if ((qType == 2) &&
          HASREGEX(_TEXT_LGPL_NOT_GPL, sp->buf+sso) &&
          !HASREGEX(_TEXT_GPL_NOT_LGPL, sp->buf+sso) &&
          !INFILE(_TEXT_LGPL_DETAILS) &&
          !INFILE(_TEXT_LICSET)) {
        if (lDiags) {
          printf("... \"GPL\" -> LGPL (%d)\n",
              index);
        }
        ltsr[_TEXT_LGPL_NOT_GPL] = LTSR_YES;
        ret = 0;
        *q = LTSR_NO;   /* oops, make that a "no" */
      } else if ((qType == 2) &&
          HASREGEX(_TEXT_GFDL_NOT_GPL, sp->buf+sso)) {
        if (lDiags) {
          printf("... \"GPL\" -> GFDL (%d)\n",
              index);
        }
        ltsr[_TEXT_GFDL_NOT_GPL] = LTSR_YES;
        ret = 0;
        *q = LTSR_NO;
      } else if ((index == _LT_GPL3ref) && (qType == 2)) {
        if (HASREGEX(_PHR_QEMU_NOT_GPLV3, sp->buf+sso)) {
          if (lDiags) {
            printf("... \"GPL\" -> QEMU\n");
          }
          ltsr[_PHR_QEMU_NOT_GPLV3] = LTSR_YES;
          ret = 0;
          *q = LTSR_NO;
        } else if (INFILE(_PHR_SCF_HOWTO)) {
          if (lDiags) {
            printf("... SCF-Howto\n");
          }
          ret = 0;
          *q = LTSR_NO;
        } else if (HASREGEX(_TEXT_DRBD_NOT_GPL3, sp->buf)) {
          if (lDiags) {
            printf("... mysgl/DRBD\n");
          }
          ltsr[_TEXT_DRBD_NOT_GPL3] = LTSR_YES;
          ret = 0;
          *q = LTSR_NO;
        }
#ifdef  GPLV2_BEATS_GPLV3
        else if (strNbuf(sp->buf+sso, "version 2")) {
          if (sp->buf + sso + cur.regm.rm_eo <
              sp->buf + seo) {
            if (lDiags) {
              printf("... v%c!\n", *cp);
            }
            ret = 0;
            *q = LTSR_NO;
          }
        }
#endif  /* GPLV2_BEATS_GPLV3 */
      } else if ((index == _PHR_GPL3_OR_LATER) &&
          (qType == 2) &&
          strNbuf(sp->buf+sso, "v2 ")) {
        if (lDiags) {
          printf("... v2 and version 3\"\n");
        }
        ret = 0;
        *q = LTSR_NO;
      } else if ((index == _PHR_GPL3_OR_LATER) &&
          (qType == 2) &&
          HASREGEX(_TEXT_NOT_GPLV3_DRAFT, sp->buf+sso)) {
        if (lDiags) {
          printf("... exclude \"GPLv3 draft\"\n");
        }
        ltsr[_TEXT_NOT_GPLV3_DRAFT] = LTSR_YES;
        ret = 0;
        *q = LTSR_NO;
      } else if ((index == _PHR_GPL3_ONLY) &&
          (qType == 2) &&
          HASREGEX(_TEXT_NOT_LIBSTDC, sp->buf+sso)) {
        if (lDiags) {
          printf("... exclude libstdc vers\"\n");
        }
        ltsr[_TEXT_NOT_LIBSTDC] = LTSR_YES;
        ret = 0;
        *q = LTSR_NO;
      }
      /*
       * POSIX regex matches the longest string possible, and a '3' can follow a
       * "version 2 or later phrase" -- we want to match the '2'. "Vim" has this:
       *****
       *    e) When the GNU General Public License (GPL) applies to the changes,
       *       you may distribute the modified code under the GNU GPL version 2 or
       *       any later version.
       * 3) A message must be added, at least in the output of the ":version"
       *    command and in the intro screen, such that the user ...
       *****
       * So... if there's a NUMBER (!= '3') between the word "version" and the
       * end-of-match (at sp->buf+seo), that matches the number AND NOT the 3.
       */
      else if ((qType == 2) &&
          ((index == _PHR_GPL3_ONLY) || (index == _PHR_LGPL3_ONLY))) {
        if (strNbuf(sp->buf+sso, "version") ||
            strNbuf(sp->buf+sso, "v3")) {
          cp = sp->buf + cur.regm.rm_eo;
        } else {
          cp = sp->buf + seo;       /* "nil" loop */
        }
        for (ptr = sp->buf+seo; cp < ptr; cp++) {
          if (isdigit(*cp) && *cp != '3') {
            if (lDiags) {
              printf("... vers %c!\n",
                  *cp);
            }
            ret = 0;
            *q = LTSR_NO;
            break;
          }
        }
      } else if (index == _PHR_FSF_V3_ONLY && qType == 2) {
        if (strNbuf(sp->buf+sso, "version")) {
#ifdef  GPLV2_BEATS_GPLV3
          ptr = sp->buf + sso + cur.regm.rm_so + 7;
#endif  /* GPLV2_BEATS_GPLV3 */
          cp = strchr(sp->buf+sso, '3');
          if (strncasecmp(cp, "3 tlb", 5) == 0) {
            if (lDiags) {
              printf("... v3 tlb\n");
            }
            ret = 0;
            *q = LTSR_NO;
          }
#ifdef  GPLV2_BEATS_GPLV3
          else if ((*ptr == ' ') && (*(ptr+1) == '2')) {
            if (lDiags) {
              printf("... v2, !v3\n");
            }
            ret = 0;
            *q = LTSR_NO;
          }
#endif  /* GPLV2_BEATS_GPLV3 */
        }
        else if (strNbuf(sp->buf+sso, "v3")) {
          cp = sp->buf + sso + cur.regm.rm_so;
          if (strncasecmp(cp-4, "arm ", 4) == 0) {
            if (lDiags) {
              printf("... arm v3\n");
            }
            ret = 0;
            *q = LTSR_NO;
          } else if (strncasecmp(cp, "v3020 ",
              6) == 0) {
            if (lDiags) {
              printf("... v3020\n");
            }
            ret = 0;
            *q = LTSR_NO;
          }
        }
      } else if ((index == _LT_LGPL_OR) &&
          (strncasecmp(sp->buf+sso, "or fitness f", 12) == 0)) {
        if (lDiags) {
          printf("... merch-or-fitness\n");
        }
        ret = 0;
        *q = LTSR_NO;
      } else if ((index == _LT_GPLref1) &&
          (qType == 2) &&
          INFILE(_PHR_LIC_CHANGE)) {
        if (lDiags) {
          printf("... exclude lic-change\"\n");
        }
        ret = 0;
        *q = LTSR_NO;
      } else if ((qType == 2) && (sso > 4)) {
        cp = sp->buf+sso-4;
        if (strncasecmp(cp, "not ", 4) == 0) {
          if (lDiags) {
            printf("... NOT l?gpl-ish\n");
          }
          ret = 0;
          *q = LTSR_NO;   /* "no" */
        }
      } else if (qType == 3 && INFILE(_PHR_ARTISTIC_DESC1)) {
        /*
        Special filter #3: match specific versions of Perl
        references, but not all
         */
        if (lDiags) {
          printf("... exclude artistic defn\"\n");
        }
        ret = 0;
        *q = LTSR_NO;
      } else if (qType == 4) {
        /*
        Special filter #4: look for a numerical version
        number IFF NOT IN a  string of (at least) 4 numerical
        characters (signifying a year/datestamp)
         */
        char *x;
        x = cp = cur.matchBase + sso;
        ptr = cp - (sso < 100 ? sso : 100);
        while (!isdigit(*cp)) {
          cp++;
        }
        if (isdigit(*(cp+1)) && isdigit(*(cp+2)) && isdigit(*(cp+3))) {
          if (lDiags) {
            printf("... don't want year\n");
          }
          ret = 0;
          *q = LTSR_NO;
        } else if (HASREGEX(_TEXT_GNU_HELLO_23, ptr)) {
          if (lDiags) {
            printf("... gnu example\n");
          }
          ret = 0;
          *q = LTSR_NO;
        }
#ifdef  GPLV2_BEATS_GPLV3
        else if (strncasecmp(x-5, "v2 or ", 5) == 0) {
          if (lDiags) {
            printf("... v2 or v3\n");
          }
          ret = 0;
          *q = LTSR_NO;
        }
#endif  /* GPLV2_BEATS_GPLV3 */
        /*
         * Special case - don't know where better to look for this... other strings
         * match TEXT_GPLV3 and should be filtered.  This should be a fairly low
         * frequency check.
         */
        else if (index == _TEXT_GPLV3) {
          x = cur.matchBase + seo;
          if (isdigit(*x) && *x != '0') {
            if (lDiags) {
              printf("... v3#!0\n");
            }
            ret = 0;
            *q = LTSR_NO;
          }
        }
      }
    }
#if     (DEBUG > 5)
    printf(">>===> \"%s\"\n", ltp->regex);
#endif  /* DEBUG > 5 */
  }
#ifdef  PARSE_STOPWATCH
  END_TIMER;      /* stop the timer */
  (void) sprintf(timerName, "findPhrase(%03d): RET=%d (%s:%d)", index,
      ret, ltp->tseed, strlen(sp->buf));
  PRINT_TIMER(timerName, 0);
#endif  /* PARSE_STOPWATCH */
  if (lDiags && ret) {
    printRegexMatch(index, NO);
    locateRegex(filetext, op, index, size, sso, seo);
  }
  return(ret);
}

void locateRegex(char *text, item_t *op, int index, int size, int sso, int seo)
{
  int i;
  int j;
  int n;
  int off;
  int len;
  item_t *sp;
  list_t *lp = (list_t *)op->bList;
  char *cp;
  char *ptr;
  char *start;
  char save;

#if defined(PROC_TRACE) || defined(PHRASE_DEBUG)
  traceFunc("== locateRegex(%p, %p, %d, %d, %d, %d)\n", text, op, index,
      size, sso, seo);
#endif  /* PROC_TRACE || PHRASE_DEBUG */

  /*
   * First step, simplest case - try to locate the regex in the file.  It
   * *might* be that easy (but not very often, it turns out).
   */
  if (idxGrep(index, text, REG_ICASE|REG_EXTENDED)) {
    saveRegexLocation(index, cur.regm.rm_so,
        cur.regm.rm_eo - cur.regm.rm_so, YES);
    return;
  }
  /*
   * Regex is not directly in the raw text file, so now we need to try to
   * map a location from a 'doctored buffer' to a location in the original
   * text file.  Not impossible, yet not overly straight-forward.
   */
#ifdef  DEBUG
  listDump(lp, NO);
  printf("Doc-buffer match @ %d:%d\n", sso, seo);
  printf("Possible \"%s\" entries to search: %d (%d)\n", op->str,
      op->nMatch, lp->used);
  for (n = i = 0; (sp=listIterate(lp)); i++) {
    printf("Ent[%d]: bDocLen %d (len %d) == file %d+%d (%d)\n",
        i, sp->bDocLen, (sp->bDocLen)-n, sp->bStart, sp->bLen,
        sp->bStart+sp->bLen);
    n = sp->bDocLen;
  }
#endif  /* DEBUG */
  /*
   * At this point, the matched phrase should be bounded by {sso} and {seo}
   * as offsets within the doctored buffer we're scanning.  We also have a
   * mapping of offsets within the doctored buffer to more-or-less where it
   * exists in the original (raw text) file -- so go find it.  Walk through the
   * offsets-list; the entry with the LOWEST end-of-doctored-buffer offset
   * exceeding the value of 'sso' IS the paragraph where we found the regex.
   */
  i = -1;
  j = -1;
  n = 0;
  while ((sp = listIterate(lp))) {
    if (sso > sp->bDocLen) {
      n = sp->bDocLen;
      continue;
    }
    i = sp->bStart;
    j = sp->bLen;
#ifdef  DEBUG
    printf("sso(%d), limit(%d), Possible: @%d+%d\n", sso,
        sp->bDocLen, i, j);
#endif  /* DEBUG */
    listIterationReset(lp);
    break;
  }
  if (i < 0) {    /* something is wrong... */
    LOG_FATAL("Cannot map reduced-text to raw file contents (#%d)", index)
              Bail(-__LINE__);
  }
  /*
   * Remember, the length of text matched in the doctored-buffer will likely
   * not match the length of the "same" (unmodified) text in the raw file!
   * And, an offset within the doctored buffer CANNOT be any larger than the
   * corresponding text segment in the raw file (we only _remove_ characters).
   * Next, find the last occurrence (in the raw file) of the last word in
   * the license-footprint
   */
  sso -= n;
  seo -= n;
  off = i + sso;
  len = j - sso;
#ifdef  DEBUG
  printf("WINDOW-first: offset %d, len %d\n", off, len);
#endif  /* DEBUG */
  /*printf("BUF: %s\n", sp->buf);*/
  start = _REGEX(index);
  cp = start + licSpec[index].text.csLen - 1; /* 'end' of regex */
#ifdef  DEBUG
  printf("EO-Regex, cp == '%c'\n", *cp);
#endif  /* DEBUG */
  n = 0;
  while (*cp == ' ') {
    cp--;
  }
  while ((n == 1) || ((cp != start) && (*cp != ' ') && (*cp != ')') &&
      (*cp != '}'))) {
    if (*cp == ']') {
      n = 1;
    } else if (*cp == '[') {
      n = 0;
    }
    if (cp == _REGEX(index)) {
      cp = ":no:match";
      break;
    }
    cp--;
  }
  if (*cp == ')') {
    cp--;
    n = 1;
    while (n) {
      if (*cp == '(') {
        n--;
      } else if (*cp == ')') {
        n++;
      }
      /*printf("'%c' -- n %d\n", *cp, n);*/
      if (cp != start) {
        cp--;
      }
    }
    while ((cp != start) && (*cp != ' ') && (*cp != '.') && (*cp != ')')
        && (*cp != ']') && (*cp != '}')) {
      /*printf("skip '%c'...\n", *cp);*/
      cp--;
    }
#ifdef  DEBUG
    printf("_END_@%ld '%c'\n", cp-_REGEX(index), *cp);
#endif  /* DEBUG */
  }
  if (cp != start) {
    if (*cp != '.') {
      cp++;
    }
    if ((*cp == '?') || (*cp == '+')) {
      cp++;
    }
  }
  ptr = sp->buf + sso;
  i = j = 0;
#ifdef  DEBUG
  printf("SEARCH @foot is now \"%s\"\n", cp);
#endif  /* DEBUG */
  while (strGrep(cp, ptr, REG_ICASE|REG_EXTENDED)) {
    i++;
    ptr += cur.regm.rm_eo;
    j += cur.regm.rm_eo;
#ifdef  DEBUG
    printf("Found match (%d bytes) @ offset %d (%d tot)\n",
        cur.regm.rm_eo - cur.regm.rm_so, cur.regm.rm_so, j);
#endif  /* DEBUG */
  }
#ifdef  DEBUG
  printf("Total # of matches == %d\n", i);
#endif  /* DEBUG */
  if (i && j) {
    len = j;
#ifdef  DEBUG
    printf("WINDOW-adjst: offset %d, len %d\n", off, len);
#endif  /* DEBUG */
  } else {
    Assert(NO, "Regex \"%s\" (foot-end) not in raw text", cp);
  }
  /*
   * Then, find the first occurrence (in the raw file) of the first word
   * in the license-footprint; the exception here is that if the "last word"
   * turned out to be the entire regex, then the "first word" will be, too.
   */
  if (cp != start) {
    cp = _REGEX(index);
    j = 1;
#ifdef  DEBUG
    printf("BO-Regex, cp == '%c'\n", *cp);
#endif  /* DEBUG */
    while (*cp == ' ') {
      cp++;
    }
    while (*cp && (*cp != ' ') && (*cp != '.') && (*cp != '(') &&
        (*cp != '{')) {
      if (*cp == '[') {
        if (*(cp + 1) == '^') {
          j = 0;
        }
        while (*cp && (*cp != ']')) {
          cp++;
        }
        if (*(cp+1) && (*(cp+1) == '?')) {
          cp++;
        }
        if (j) {
          cp++;
          break;
        }
      }
      cp++;
    }
    if (*cp == '(') {
      /*printf("Start@%d '%c'\n", cp-_REGEX(index), *cp);*/
      for (n = 1, cp++; n; cp++) {
        /*printf("... '%c'\n", *cp);*/
        if ((*cp == '(') && (*(cp-1) != '\\')) {
          n++;
        } else if ((*cp == ')') && (*(cp-1) != '\\')) {
          n--;
        }
      }
      while (*cp && (*cp != ' ') && (*cp != '.') && (*cp != '(') &&
          (*cp != '[')) {
        cp++;
      }
      /*printf("_END_@%d '%c'\n", cp-_REGEX(index), *cp);*/
    }
    if ((*cp == '?') ||
        (*cp == '+') ||
        ((*cp == '.') && (*(cp-1) == '\\'))) {
      cp++;
    }
    if (*cp) {
      save = *cp;
      *cp = NULL_CHAR;
#ifdef  DEBUG
      printf("SEARCH @head is now \"%s\"\n", _REGEX(index));
#endif  /* DEBUG */
      ptr = sp->buf+sso;
      if (strGrep(_REGEX(index), ptr,
          REG_ICASE|REG_EXTENDED)) {
        len -= cur.regm.rm_so;
        off += cur.regm.rm_so;
      } else {
        LOG_NOTICE("Regex \"%s\" (foot-start) not in raw text", _REGEX(index));
      }
      *cp = save;     /* restore to original text */
    }
#ifdef  DEBUG
    else {
      Note("Nothing to trim from the front (*cp == NULL)");
    }
#endif  /* DEBUG */
  }
#ifdef  DEBUG
  else {
    printf("Hey, last-word IS the entire regex!\n");
  }
#endif  /* DEBUG */
  saveRegexLocation(index, off, len, YES);
#ifdef  DEBUG
  printf("WINDOW-FINAL: offset %d, len %d\n", off, len);
#endif  /* DEBUG */
  /*
   * At this point, the window is as small as we can (reasonably) make it,
   * given that we mundged the original file-text and didn't make a complete
   * map of every character.  The variable off contains the start of the
   * window (the absolute offset within the raw file) and the variable len
   * contains the length of the text window.
   *****
   * DON'T FORGET that some license-footprints are determined merely by
   * searching for a string in the raw-text file (calling idxGrep()), e.g.,
   * not using the doctored buffers.  See fileHasPatt() for details.
   *****
   * And finally, note how the list of entries matched (in 'whereList') is
   * manipulated -- see the very bottom of "addRef()".
   */
  if ((off + len) > size) {
    LOG_FATAL("off %d + len %d (== %d) exceeds filesize %d!", off, len, off + len, size);
    Bail(-__LINE__);
  }
  return;
}


void saveRegexLocation(int index, int offset, int length, int saveCache)
{
  item_t *ip;
  item_t *sp;

#if defined(PROC_TRACE) || defined(PHRASE_DEBUG)
  traceFunc("== saveRegexLocation(%d, %d, %d, %d)\n", index, offset, length,
      saveCache);
#endif  /* PROC_TRACE || PHRASE_DEBUG */

  (void) sprintf(name, "reg%04d", index);
  sp = listGetItem(&whereList, name);
  sp->bIndex = whereList.used;
  sp->bStart = offset;
  sp->bLen = length;
  if (saveCache) {
    ip = listGetItem(&whCacheList, name);
    ip->bIndex = index;
    ip->bStart = offset;
    ip->bLen = length;
  }
  return;
}


void saveUnclBufLocation(int bufNum)
{
  item_t *p;
  item_t *bp;
  list_t *lp;

#if defined(PROC_TRACE) || defined(PHRASE_DEBUG)
  traceFunc("== saveUnclBufLocation(%d, %d, %d, %d)\n", bufNum);
#endif  /* PROC_TRACE || PHRASE_DEBUG */

  listClear(&whereList, NO);      /* empty all prior matches */
  p = listGetItem(&cur.offList, _REGEX(_LEGAL_first));
  lp = (list_t *) p->bList;
  (void) sprintf(name, "buf%05d", bufNum);
  p = listGetItem(lp, name);
  bp = listGetItem(&whereList, LS_UNCL);
  bp->bStart = p->bStart;
  bp->bLen = p->bLen;
  bp->bIndex = -1;
  return;
}


void doctorBuffer(char *buf, int isML, int isPS, int isCR)
{
  char *cp;
  char *x;
  int f;
  int g;
  int n;

#if     defined(PROC_TRACE) || defined(DOCTOR_DEBUG)
  traceFunc("== doctorBuffer(%p, %d, %d, %d)\n", buf, isML, isPS, isCR);
#endif  /* PROC_TRACE || DOCTOR_DEBUG */

  /*
   * convert a buffer of multiple *stuff* to text-only, separated by spaces
   * We really only care about text "in a license" here, so strip out
   * comments and other unwanted punctuation.
   */
#ifdef  DOCTOR_DEBUG
  printf("***** Processing %p (%d data bytes)\n", buf, (int)strlen(buf));
  printf("----- [Dr-BEFORE:] -----\n%s\n[==END==]\n", buf);
#endif  /* DOCTOR_DEBUG */
  /*
   * step 1: take care of embedded HTML/XML and special HTML-chars like
   * &quot; and &nbsp; -- but DON'T remove the text in an HTML comment.
   * There might be licensing text/information in the comment!
   *****
   * Later on (in parseLicenses()) we search for URLs in the raw-text
   */
  if (isML) {
#ifdef  DOCTOR_DEBUG
    printf("DEBUG: markup-languange directives found!\n");
#endif  /* DOCTOR_DEBUG */
    f = 0;
    g = 0;
    for (cp = buf; cp && *cp; cp++) {
      if ((*cp == '<') &&
          (*(cp+1) != '<') &&
          (*(cp+1) != ' ')) {
#if     (DEBUG>5) && defined(DOCTOR_DEBUG)
        int x = strncasecmp(cp, "<string", 7);
        printf("CHECK: %c%c%c%c%c%c%c == %d\n", *cp,
            *(cp+1), *(cp+2), *(cp+3), *(cp+4),
            *(cp+5), *(cp+6), x);
#endif  /* DEBUG>5 && DOCTOR_DEBUG */
        if (strncasecmp(cp, "<string", 7)) {
          *cp = ' ';
          if (*(cp+1) != '-' || *(cp+2) != '-') {
            f = 1;
          }
        }
      } else if (*cp == '&') {
#if     (DEBUG>5) && defined(DOCTOR_DEBUG)
        int x = strncasecmp(cp, "&copy;", 6);
        printf("CHECK: %c%c%c%c%c%c == %d\n", *cp,
            *(cp+1), *(cp+2), *(cp+3), *(cp+4),
            *(cp+5), x);
#endif  /* DEBUG>5 && DOCTOR_DEBUG */
        if (strncasecmp(cp, "&copy;", 6)) {
          *cp = ' ';
          g = 1;
        }
      } else if (f && (*cp == '>')) {
        *cp = ' ';
        f = 0;
      } else if (g && (*cp == ';')) {
        *cp = ' ';
        g = 0;
      } else if (isEOL(*cp)) {
        g = 0;
      }
      /* Don't remove text in an HTML comment (e.g., turn the flag off) */
      else if ((*cp == '!') &&
          f &&
          (cp != buf) &&
          (*(cp-1) == ' ')) {
        *cp = ' ';
        f = 0;
      } else if (f || g) {
        *cp = INVISIBLE;
      } else if ((*cp == '<') || (*cp == '>')) {
        *cp = ' ';
      }
    }
  }
  /*
   * step 2: remove comments that start at the beginning of a line, * like
   * ^dnl, ^xcomm, ^comment, and //
   */
  cp = buf;
  while (idxGrep(_UTIL_BOL_MAGIC, cp, REG_ICASE|REG_NEWLINE|REG_EXTENDED)) {
#ifdef  DOCTOR_DEBUG
    dumpMatch(cp, "Found \"comment\"-text");
#endif  /* DOCTOR_DEBUG */
    cp += cur.regm.rm_so;
    switch (*cp) {
      case '>':
        *cp++ = ' ';
        break;
      case '@':       /* texi special processing */
        *cp++ = INVISIBLE;
        if (strncasecmp(cp, "author", 6) == 0) {
          (void) memset(cp, ' ', 6);
          cp += 6;
        } else if (strncasecmp(cp, "comment", 7) == 0) {
          (void) memset(cp, ' ', 7);
          cp += 7;
        } else if (strncasecmp(cp, "center", 6) == 0) {
          (void) memset(cp, ' ', 6);
          cp += 6;
        }
        else if (strncasecmp(cp, "rem", 3) == 0) {
          (void) memset(cp, ' ', 3);
          cp += 3;
        } else if (*cp == 'c') {
          *cp++ = INVISIBLE;
          if (strncasecmp(cp, " essay", 6) == 0) {
            (void) memset(cp, ' ', 6);
            cp += 6;
          }
        }
        break;
      case '/':       /* c++ style comment // */
        (void) memset(cp, INVISIBLE, 2);
        cp += 2;
        break;
      case '\\':      /* c++ style comment // */
        if (strncasecmp(cp+1, "par ", 3) == 0) {
          (void) memset(cp, ' ', 4);
        }
        cp += 4;
        break;
      case 'r':
      case 'R':       /* rem */
      case 'd':
      case 'D':       /* dnl */
        (void) memset(cp, INVISIBLE, 3);
        cp += 3;
        break;
      case 'x':
      case 'X':       /* xcomm */
        (void) memset(cp, INVISIBLE, 5);
        cp += 5;
        break;
      case 'c':
      case 'C':       /* comment */
        (void) memset(cp, INVISIBLE, 7);
        cp += 7;
        break;
      case '%':       /* %%copyright: */
        (void) memset(cp, INVISIBLE, 12);
        cp += 12;
        break;
    }
  }
  /*
   * Step 3 - strip out crap at end-of-line on postscript documents
   */
  if (isPS) {
#ifdef  DOCTOR_DEBUG
    printf("DEBUG: postscript stuff detected!\n");
#endif  /* DOCTOR_DEBUG */
    cp = buf;
    while (idxGrep(_UTIL_POSTSCR, cp, REG_EXTENDED|REG_NEWLINE)) {
#ifdef  DOCTOR_DEBUG
      dumpMatch(cp, "FOUND postscript-thingy");
#endif  /* DOCTOR_DEBUG */
      x = cp + cur.regm.rm_so;
      cp += cur.regm.rm_eo;
      while (x < cp) {
        *x++ = ' '/*INVISIBLE*/;
      }
    }
  }
  /*
   *      - step 4: remove groff/troff font-size indicators, the literal
   *              string backslash-n and all backslahes, ala:
   *==>   perl -pe 's,\\s[+-][0-9]*,,g;s,\\s[0-9]*,,g;s/\\n//g;' |
     f*/
  for (cp = buf; *cp; cp++) {
    if (*cp == '\\') {
      x = cp + 1;
      if (*x && (*x == 's')) {
        x++;
        if (*x && ((*x == '+') || (*x == '-'))) {
          x++;
        }
        while (*x && isdigit(*x)) {
          x++;
        }
      } else if (*x && *x == 'n') {
        x++;
      }
      memset(cp, /*INVISIBLE*/' ', (size_t) (x-cp));
    }
  }
  /*
   *      - step 5: convert white-space to real spaces, and remove
   *              unnecessary punctuation, ala:
   *==>   tr -d '*=+#$|%.,:;!?()\\][\140\047\042' | tr '\011\012\015' '   '
   *****
   * NOTE: we purposely do NOT process backspace-characters here.  Perhaps
   * there's an improvement in the wings for this?
   */
  for (cp = buf; /*cp < end &&*/ *cp; cp++) {
    if ((*cp == '\302') && (*(cp+1) == '\251')) {
      cp += 2;
      continue;
    }
    if (*cp & (char) 0x80) {
      *cp = INVISIBLE;
      continue;
    }
    switch (*cp) {
      /*
        Convert eol-characters AND some other miscellaneous
        characters into spaces (due to comment-styles, etc.)
       */
      case '\a': case '\t': case '\n': case '\r':
      case '\v': case '\f': case '[': case ']':
      case '{': case '}': case '*': case '=':
      case '#': case '$': case '|': case '%': case '!':
      case '?': case '`': case '"': case '\'':
        *cp = ' ';
        break;
        /* allow + only within the regex " [Mm]\+ " */
      case '+':
        if (cp > buf+1 && (*(cp-1) == 'M' ||
            *(cp-1) == 'm') && *(cp-2) == ' ' &&
            *(cp+1) == ' ') {
          f = 0; /* no-op */
        }
        else {
          *cp = ' ';
        }
        break;
      case '(':
        if ((*(cp+1) == 'C' || *(cp+1) == 'c') &&
            *(cp+2) == ')') {
          cp += 2;
          continue;
        }
        else {
          *cp = ' ';
        }
        break;
      case ')': case ',': case ':': case ';':
        if (!isCR) {
          *cp = ' ';
        }
        break;
      case '.':
        if (!isCR) {
          *cp = INVISIBLE;
        }
        break;
      case '<':
        if (strncasecmp(cp, "<string", 7) == 0) {
          (void) strncpy(cp, "          ", 7);
        }
        break;
        /* CDB - Big #ifdef 0 left out */
      case '\001': case '\002': case '\003': case '\004':
      case '\005': case '\006': case '\016': case '\017':
      case '\020': case '\021': case '\022': case '\023':
      case '\024': case '\025': case '\026': case '\027':
      case '\030': case '\031': case '\032': case '\033':
      case '\034': case '\035': case '\036': case '\037':
      case '~':
        *cp = INVISIBLE;
        break;
#ifdef  DOCTOR_DEBUG
      case ' ': case '/': case '-': case '@': case '&':
      case '>': case '^': case '_':
      case INVISIBLE:
        break;
      default:
        if (!isalpha(*cp) && !isdigit(*cp)) {
          printf("DEBUG: \\0%o @ %ld\n",
              *cp & 0xff, cp-buf);
        }
        break;
#endif  /* DOCTOR_DEBUG */
    }
  }
  /*
   * Look for hyphenations of words, to compress both halves into a sin-
   * gle (sic) word.  Regex == "[a-z]- [a-z]".
   *****
   * NOTE: not sure this will work based on the way we strip punctuation
   * out of the buffer above -- work on this later.
   */
  for (cp = buf; idxGrep(_UTIL_HYPHEN, cp, REG_ICASE); /*nada*/) {
#ifdef  DOCTOR_DEBUG
    x = cp + cur.regm.rm_so;
    while ((x > cp) && !isspace(*x)) {
      x--;
    }
    printf("Hey! hyphenated-word [");
    for (++x; x <= (cp + cur.regm.rm_eo); x++) {
      printf("%c", *x);
    }
    while (!isspace(*x)) {
      printf("%c", *x++);
    }
    printf("]\n");

#endif  /* DOCTOR_DEBUG */
    cp += cur.regm.rm_so + 1;
    *cp++ = INVISIBLE;
    while (isspace(*cp)) {
      *cp++ = INVISIBLE;
    }
  }
  /*
   *      - step 6: clean up miscellaneous punctuation, ala:
   *==>           perl -pe 's,[-_/]+ , ,g;s/print[_a-zA-Z]* //g;s/  / /g;'
   */
  for (cp = buf; idxGrep(_UTIL_MISCPUNCT, cp, REG_EXTENDED); /*nada*/) {
    x = cp + cur.regm.rm_so;
    cp += cur.regm.rm_eo - 1;  /* leave ' ' alone */
    while (x < cp) {
      *x++ = ' ';
    }
    cp++;
  }
  for (cp = buf; idxGrep(_UTIL_LATEX, cp, REG_ICASE); /*nada*/) {
    x = cp + cur.regm.rm_so;
    cp += cur.regm.rm_eo;
    while (x <= cp) {
      *x++ = ' ';
    }
    cp++;
  }
  /*
   * Ignore function calls to print routines: only concentrate on what's being
   * printed (sometimes programs do print licensing information) -- but don't
   * ignore real words that END in 'print', like footprint and fingerprint.
   * Here, we take a risk and just look for a 't' (in "footprint"), or for an
   * 'r' (in "fingerprint").  If someone has ever coded a print routine that
   * is named 'rprint' or tprint', we're spoofed.
   */
  for (cp = buf; idxGrep(_UTIL_PRINT, cp, REG_ICASE); /*nada*/) {
    x = cp + cur.regm.rm_so;
    cp += (cur.regm.rm_eo - 1);
    if ((x > buf) && ((*(x-1) == 'r') || (*(x-1) == 't'))) {
      continue;
    }
    while (x < cp) {
      *x++ = ' ';
    }
    cp++;
  }
  /*
   * Convert the regex ' [X ]+' (where X is really the character #defined as
   * INVISIBLE) to a single space (and a string of INVISIBLE characters).
   */
  for (cp = buf; *cp; /*nada*/) {
    if (*cp++ == ' ') {
      while (*cp) {
        if (*cp == ' ') {
          *cp++ = INVISIBLE;
        } else if (*cp == INVISIBLE) {
          cp++;
        } else {
          break;
        }
      }
    }
  }
  /*
   * garbage collect: eliminate all INVISIBLE characters in the buffer
   */
  x = cp = buf;
  n = 0;
  while (/*cp < end &&*/ *cp) {
    while (/*cp < end &&*/ *cp == INVISIBLE) {
      n++;
      cp++;
    }
    if (*cp) {
      *x++ = *cp++;
    }
  }
  *x = NULL_CHAR;
#ifdef  DOCTOR_DEBUG
  printf("***** Now buffer %p contains %d bytes (%d clipped)\n", buf,
      (int)strlen(buf), n);
  printf("+++++ [Dr-AFTER] +++++:\n%s\n[==END==]\n", buf);
#endif  /* DOCTOR_DEBUG */
  return;
}


/**
 * \brief This function fills in a character-buffer for a license of a CURRENT
 * file being evaluated, and enqueues a list if components to help make
 * a package-level summary.
 */
void addRef(char *str, int interest)
{
  item_t *p;
  char *bp;
  char *sp = str;
  char *cp;

#ifdef  PROC_TRACE
  traceFunc("== addRef(\"%s\", %d)\n", str, interest);
#endif  /* PROC_TRACE */

#if defined(DEBUG)
  listDump(&whereList, YES);
#endif  /* DEBUG */
  /*
   * Add this to the list of individual pieces found, and mark the license
   * to make generating a license-summary easier.  The general strategy is
   * to COMPLETELY ignore anything NOT considered 'noteworthy'.  So if the
   * license is noteworthy, we add one to the count, so that we can call
   * listCount() on the list to find if there's a 'real license' in here.
   * see makeLicenseSummary() in license.c for more details.
   *****
   * This little trick is also used in distroReport() and rawSourceReport(),
   * see report.c
   */
  if (str == NULL_STR) {
    Assert(YES, "license detected != NULL");
  }
  if (*str == NULL_CHAR) {
    Assert(YES, "license string not start with NULL");
  }
  bp = licStr+refOffset;
  *bp++ = ',';
  cp = bp;        /* later->NOW! */
  /*
      CDB - Opportunity for optimization via memcpy
   */
  while (*sp) {
    *bp++ = *sp++;
  }
  *bp = NULL_CHAR;
  refOffset = bp - licStr;
  /*
   * Stuff this license in to several lists:
   * - parseList is used to create a package "computed license summary"
   * - briefList is used to compute a "terse/brief" license summary
   */
  p = listGetItem(&cur.parseList, str);
  if (interest) {
    p->iFlag++;
    if (interest > IL_LOW) {
      p->iLevel = interest;
    }
  }
  if (interest > maxInterest) {
    maxInterest = interest;
  }
  if (lDiags && whereList.used) {
    int i = 0;
    listSort(&whereList, SORT_BY_COUNT_ASC);
    printf("WINDOW for \"%s\": ", str);
    while ((p = listIterate(&whereList))) {
      if (i++ > 0) {
        printf(", ");
      }
      printf("%d+%d", p->bStart, p->bLen);
    }
    printf("\n");
  }
  listClear(&whereList, NO);
#ifdef DEBUG
  if (lDiags) {
    printf("++ \"%s\" [int=%d]\n", str, interest);
  }
#endif /* DEBUG */
  return;
}

/**
 * \brief Utility function to search for OpenLDAP licenses.  So many different
 * footprints are used by OpenLDAP, we had to either duplicate code in
 * several places, or funnel it all into one function.
 */
int famOPENLDAP(char *filetext, int size, int isML, int isPS)
{
  int ret = 0;

  if (lmem[_tOPENLDAP]) {
    if (INFILE(_TITLE_OPENLDAP25)) {
      INTERESTING("OLDAP-2.5");
      ret = 1;
    }
    else if (INFILE(_TITLE_OPENLDAP26)) {
      INTERESTING("OLDAP-2.6");
      ret = 1;
    }
    else if (INFILE(_TITLE_OPENLDAP27)) {
      INTERESTING("OLDAP-2.7");
      ret = 1;
    }
    else if (INFILE(_TITLE_OPENLDAP28)) {
      INTERESTING("OLDAP-2.8");
      ret = 1;
    }
    else if (INFILE(_TITLE_OPENLDAP12)) {
      INTERESTING("OLDAP-1.2");
      ret = 1;
    }
    else if (INFILE(_TITLE_OPENLDAP14)) {
      INTERESTING("OLDAP-1.4");
      ret = 1;
    }
    else if (INFILE(_CR_OPENLDAP) && INFILE(_TITLE_OPENLDAP)) {
      INTERESTING("OLDAP");
      ret = 1;
    }
  }
  lmem[_fOPENLDAP] = ret;
  return(ret);
}


/**
 * \brief This function is called when all the above license-checks don't turn
 * up anything useful.  Now we need to determine if the current file
 * likely contains a license or not.
 *
 * Basic strategy is to look for 4 classes (groups) of words all within
 * the same paragraph-or-two.  Here we estimate the size of a paragaph
 * to be 6 lines of legal text.  To be conservative, we'll look for 6
 * contiguous lines ABOVE AND BELOW the line that matches our first
 * search.  In order words, we're using "grep -A6 -B6 pattern textfile".
 *
 * A paragraph containing legal-VERBS, legal-DOCUMENTS, legal-NOUNS, and
 * legal-PERMISSIONS are quite likely to be a license.  This doesn't
 * have to be 100% accurate but it IS nice to know whether a file that
 * fails the known-license-footprints really contains a license or not.
 * Knowing so makes the legal department's job easier.
 *
 * Some text-files are determined by this function to contain some sort
 * of license, but really only deal with the notion of a public-domain
 * claim.  If we find one here, report it; this way we don't bother
 * calling the corner-case license-check function.
 */

int checkUnclassified(char *filetext, int size, int score,
    int isML, int isPS, int nw)
{
  char *buf;
  char *curptr;
  char *cp;
  int m = 0;
#ifdef  UNKNOWN_CHECK_DEBUG
  int pNum = 0;
#endif  /* UNKNOWN_CHECK_DEBUG */
  int i = 0;

#ifdef  PROC_TRACE
  traceFunc("== checkUnclassified(%p, %d, %d, %d, %d, %d)\n", filetext,
      size, score, isML, isPS, nw);
#endif  /* PROC_TRACE */

  /*
   * Based on experience, this is a good place to look for false-positives:
   *****
   * Occasionally IETF documents (RFCs, technical descriptions and the like)
   * have enough text to trip the unclassified-license detector falsely.
   * As a first quick check, see if there's an IETF copyright in the file
   * and if so, avoid this (somewhat expensive) check.
   *****
   * The "Debian social contract" has some very legal-looking verbage, too.
   */
  if (mCR_IETF()) {
    return(0);
  }
  if (INFILE(_LT_DEB_CONTRACT) || INFILE(_LT_DEB_CONTRACTref)){
    INTERESTING("Debian-social-DFSG");
    return(0);
  }

  /*
   * A Generic EULA 'qualifies' as an UnclassifiedLicense, too... check this
   * one before trying the word-matching magic checks (below).
   */
  gl.flags |= FL_SAVEBASE; /* save match buffer (if any) */
  m = INFILE(_LT_GEN_EULA);
  /* gl.flags & ~FL_SAVEBASE;  CDB -- This makes no sense, given line above */
  if (m) {
    if (cur.licPara == NULL_STR) {
      saveLicenseParagraph(cur.matchBase, isML, isPS, NO);
    }
    return(1);
  }
  checknw = nw;
  /*
   * Look for paragraphs of text that could be licenses.  We'll check the
   * resulting text for 4 types of different words (all in proximity leads
   * us to believe it's a license of some sort).  If we don't get a paragraph
   * to search based on the first set of words, look no further.
   */
#ifdef  UNKNOWN_CHECK_DEBUG
  printf("... first regex: \"%s\"\n", _REGEX(_LEGAL_first));
#endif  /* UNKNOWN_CHECK_DEBUG */
  if ((buf = getInstances(filetext, size, gl.uPsize, gl.uPsize,
      _REGEX(_LEGAL_first), YES)) == NULL_STR) {
#ifdef  UNKNOWN_CHECK_DEBUG
    printf("... getInstances returns NULL\n");
#endif  /* UNKNOWN_CHECK_DEBUG */
    return(0);
  }
  if (lDiags) {
    printRegexMatch(_LEGAL_first, NO);
  }
  /*
   * Without examining each paragraph, make sure the file contains the
   * components we're looking for... if not, don't check any further.
   */
  if (/*size > 102400 && */
      !match3(_LEGAL_first, buf, score, NO, isML, isPS)) {
#ifdef  UNKNOWN_CHECK_DEBUG
    printf("... first check fails\n");
#endif  /* UNKNOWN_CHECK_DEBUG */
    return(0);
  }
  /*
   * buf now points to a series of paragraphs that have 6 lines above
   * AND below the regex we've matched, PLUS a separator line between
   * each paragraph.  The LAST paragraph doesn't have a separator-line.
   *****
   * For the sake of the search, make the separator line 'disappear'.
   */
  curptr = buf;
  while (idxGrep(_UTIL_XYZZY, curptr, 0)) {
    cp = curptr + cur.regm.rm_so;
    *cp = NULL_CHAR;
#ifdef  UNKNOWN_CHECK_DEBUG
    printf("DEBUG: paragraph #%d:\n[START-PARA]\n%s\n[END-PARA]\n",
        ++pNum, curptr);
#endif  /* UNKNOWN_CHECK_DEBUG */
    i++;
    /*
     * Now that we have our 'paragraph span', check the contents of the
     * paragraph for the other 3 groups of words.  A match in EACH group
     * (plus other criteria checked, of course) means we've likely found
     * as-of-yet unclassified license.
     *****
     * A generic "no warranty" paragraph also looks like a license, so in
     * that case don't return a false positive.
     */
    if (match3(_LEGAL_first, curptr, score, YES, isML, isPS)) {
      saveUnclBufLocation(i);
      return(1);
    }
#ifdef UNKNOWN_CHECK_DEBUG
    else {
      printf("DEBUG: match() returns 0, look again\n");
    }
#endif /* UNKNOWN_CHECK_DEBUG */
    /*
     * NO-match means this paragraph doesn't contain the magic words we
     * seek.  However, this file still _may_ contain the magic paragraph --
     * it'll be searched in this while-loop until either the magic OR
     * end-of-file is found)...
     */
    *cp++ = '=';    /* reset line */
    if ((cp = findEol(cp)) == NULL_STR) {
      LOG_FATAL("Cannot find delimeter!")
                  Bail(-__LINE__);
    }
    curptr = cp+1;
  }
  /*
   * One last buffer to try...
   */
#ifdef  UNKNOWN_CHECK_DEBUG
  printf("DEBUG: %s paragraph (#%d):\n[START-PARA]\n%s\n[END-PARA]\n",
      pNum == 0 ? "ONLY" : "LAST", ++pNum, curptr);
#endif  /* UNKNOWN_CHECK_DEBUG */
  if (match3(_LEGAL_first, curptr, score, YES, isML, isPS)) {
    saveUnclBufLocation(++i);
    return(1);
  }
  return(0);
}


/**
 * \brief Generic license-phrases referring to other files or running commands
 */
void checkFileReferences(char *filetext, int size, int score, int kwbm,
    int isML, int isPS)
{
  int i;

#ifdef  PROC_TRACE
  traceFunc("== checkFileReferences(%p, %d, %d, 0x%x, %d, %d)\n", filetext,
      size, score, kwbm, isML, isPS);
#endif  /* PROC_TRACE */
  for (i = 0; i < NSEECOPYING; i++) {
    if (INFILE(_SEECOPYING_first+i)) {
      if (lDebug) {
        (void) sprintf(name, "Gen-CPY-%d", ++i);
        INTERESTING(name);
      } else {
        INTERESTING("See-file(COPYING)");
      }
      return;
    }
  }
  /* */
  for (i = 0; i < NSEELICENSE; i++) {
    if (INFILE(_SEELICENSE_first+i)) {
      if (lDebug) {
        (void) sprintf(name, "Gen-CPY-%d", ++i);
        INTERESTING(name);
      } else {
        INTERESTING("See-file(LICENSE)");
      }
      return;
    }
  }
  /* */
  for (i = 0; i < NSEEREADME; i++) {
    if (INFILE(_SEEREADME_first+i)) {
      if (lDebug) {
        (void) sprintf(name, "Gen-CPY-%d", ++i);
        INTERESTING(name);
      } else {
        INTERESTING("See-file(README)");
      }
      return;
    }
  }
  /* */
  for (i = 0; i < NSEEOTHER; i++) {
    if (INFILE(_SEEOTHER_first+i)) {
      if (lDebug) {
        (void) sprintf(name, "Gen-CPY-%d", ++i);
        INTERESTING(name);
      } else {
        INTERESTING("See-doc(OTHER)");
      }
      return;
    }
  }
  /* */
  if (INFILE(_LT_SEE_OUTPUT_1)) {
    INTERESTING(lDebug ? "Gen-EXC-1" : "GNU-style(EXECUTE)");
  }
#if 0
  else if (INFILE(_LT_SEE_OUTPUT_2)) {
    INTERESTING(lDebug ? "Gen-EXC-2" : "Free-SW(run-COMMAND)");
  } else if (INFILE(_LT_SEE_OUTPUT_3)) {
    INTERESTING(lDebug ? "Gen-EXC-3" : "Free-SW(run-COMMAND)");
  }
#endif
  return;

#ifdef OLD_VERSION
  if (INFILE(_LT_SEE_COPYING_1)) {
    INTERESTING(lDebug ? "Gen-CPY-1" : "See-file(COPYING)");
  }
  else if (INFILE(_LT_SEE_COPYING_2)) {
    INTERESTING(lDebug ? "Gen-CPY-2" : "See-file(COPYING)");
  }
  else if (INFILE(_LT_SEE_COPYING_3)) {
    INTERESTING(lDebug ? "Gen-CPY-3" : "See-file(COPYING)");
  }
  else if (INFILE(_LT_SEE_COPYING_4)) {
    INTERESTING(lDebug ? "Gen-CPY-4" : "See-file(COPYING)");
  }
  else if (INFILE(_LT_SEE_COPYING_5)) {
    INTERESTING(lDebug ? "Gen-CPY-5" : "See-file(COPYING)");
  }
  else if (INFILE(_LT_SEE_COPYING_6)) {
    INTERESTING(lDebug ? "Gen-CPY-6" : "See-file(COPYING)");
  }
  else if (INFILE(_LT_SEE_COPYING_7)) {
    INTERESTING(lDebug ? "Gen-CPY-7" : "See-file(COPYING)");
  }
  else if (INFILE(_LT_SEE_COPYING_8)) {
    INTERESTING(lDebug ? "Gen-CPY-8" : "See-file(COPYING)");
  }
  else if (INFILE(_LT_SEE_COPYING_9)) {
    INTERESTING(lDebug ? "Gen-CPY-9" : "See-file(COPYING)");
  }
  else if (INFILE(_LT_SEE_COPYING_10)) {
    INTERESTING(lDebug ? "Gen-CPY-10" : "See-file(COPYING)");
  }
  else if (INFILE(_LT_SEE_COPYING_LAST1)) {
    INTERESTING(lDebug ? "Gen-CPY-L1" : "See-file(COPYING)");
  }
  else if (INFILE(_LT_SEE_COPYING_LAST2)) {
    INTERESTING(lDebug ? "Gen-CPY-L2" : "See-file(COPYING)");
  }
  else if (INFILE(_LT_SEE_LICENSE_1)) {
    INTERESTING(lDebug ? "Gen-LIC-1" : "See-file(LICENSE)");
  }
  else if (INFILE(_LT_SEE_LICENSE_2)) {
    INTERESTING(lDebug ? "Gen-LIC-2" : "See-file(LICENSE)");
  }
  else if (INFILE(_LT_SEE_LICENSE_3)) {
    INTERESTING(lDebug ? "Gen-LIC-3" : "See-file(LICENSE)");
  }
  else if (INFILE(_LT_SEE_LICENSE_4)) {
    INTERESTING(lDebug ? "Gen-LIC-4" : "See-file(LICENSE)");
  }
  else if (INFILE(_LT_SEE_LICENSE_5)) {
    INTERESTING(lDebug ? "Gen-LIC-5" : "See-file(LICENSE)");
  }
  else if (INFILE(_LT_SEE_LICENSE_6)) {
    INTERESTING(lDebug ? "Gen-LIC-6" : "See-file(LICENSE)");
  }
  else if (INFILE(_LT_SEE_LICENSE_7)) {
    INTERESTING(lDebug ? "Gen-LIC-7" : "See-file(LICENSE)");
  }
  else if (INFILE(_LT_SEE_LICENSE_8)) {
    INTERESTING(lDebug ? "Gen-LIC-8" : "See-file(LICENSE)");
  }
  else if (INFILE(_LT_SEE_LICENSE_9)) {
    INTERESTING(lDebug ? "Gen-LIC-9" : "See-file(LICENSE)");
  }
  else if (INFILE(_LT_SEE_LICENSE_10)) {
    INTERESTING(lDebug ? "Gen-LIC-10" : "See-file(LICENSE)");
  }
  else if (INFILE(_LT_SEE_LICENSE_LAST1)) {
    INTERESTING(lDebug ? "Gen-LIC-L1" : "See-file(LICENSE)");
  }
  else if (INFILE(_LT_SEE_LICENSE_LAST2)) {
    INTERESTING(lDebug ? "Gen-LIC-L2" : "See-file(LICENSE)");
  }
  else if (INFILE(_LT_SEE_README_1)) {
    INTERESTING(lDebug ? "Gen-RDM-1" : "See-file(README)");
  }
  else if (INFILE(_LT_SEE_README_2)) {
    INTERESTING(lDebug ? "Gen-RDM-2" : "See-file(README)");
  }
  else if (INFILE(_LT_SEE_README_3)) {
    INTERESTING(lDebug ? "Gen-RDM-3" : "See-file(README)");
  }
  else if (INFILE(_LT_SEE_README_4)) {
    INTERESTING(lDebug ? "Gen-RDM-4" : "See-file(README)");
  }
  else if (INFILE(_LT_SEE_README_5)) {
    INTERESTING(lDebug ? "Gen-RDM-5" : "See-file(README)");
  }
  else if (INFILE(_LT_SEE_README_6)) {
    INTERESTING(lDebug ? "Gen-RDM-6" : "See-file(README)");
  }
  else if (INFILE(_LT_SEE_README_7)) {
    INTERESTING(lDebug ? "Gen-RDM-7" : "See-file(README)");
  }
  else if (INFILE(_LT_SEE_README_LAST1)) {
    INTERESTING(lDebug ? "Gen-RDM-L1" : "See-file(README)");
  }
  else if (INFILE(_LT_SEE_README_LAST2)) {
    INTERESTING(lDebug ? "Gen-RDM-L2" : "See-file(README)");
  }
  else if (INFILE(_LT_SEE_OTHER_1)) {
    INTERESTING(lDebug ? "Gen-OTH-1" : "See-doc(OTHER)");
  }
  else if (INFILE(_LT_SEE_OTHER_2)) {
    INTERESTING(lDebug ? "Gen-OTH-2" : "See-doc(OTHER)");
  }
  else if (INFILE(_LT_SEE_OTHER_3)) {
    INTERESTING(lDebug ? "Gen-OTH-3" : "See-doc(OTHER)");
  }
  else if (INFILE(_LT_SEE_OTHER_4)) {
    INTERESTING(lDebug ? "Gen-OTH-4" : "See-doc(OTHER)");
  }
  else if (INFILE(_LT_SEE_OTHER_5)) {
    INTERESTING(lDebug ? "Gen-OTH-5" : "See-doc(OTHER)");
  }
  else if (INFILE(_LT_SEE_OTHER_6)) {
    INTERESTING(lDebug ? "Gen-OTH-6" : "See-doc(OTHER)");
  }
  else if (INFILE(_LT_SEE_OTHER_7)) {
    INTERESTING(lDebug ? "Gen-OTH-7" : "See-doc(OTHER)");
  }
  else if (INFILE(_LT_SEE_OTHER_8)) {
    INTERESTING(lDebug ? "Gen-OTH-8" : "See-doc(OTHER)");
  }
  else if (INFILE(_LT_SEE_OTHER_9)) {
    INTERESTING(lDebug ? "Gen-OTH-9" : "See-doc(OTHER)");
  }
  else if (INFILE(_LT_SEE_OTHER_LAST1)) {
    INTERESTING(lDebug ? "Gen-OTH-L1" : "See-doc(OTHER)");
  }
  else if (INFILE(_LT_SEE_OTHER_LAST2)) {
    INTERESTING(lDebug ? "Gen-OTH-L2" : "See-doc(OTHER)");
  }
  else if (INFILE(_LT_SEE_OTHER_LAST3)) {
    INTERESTING(lDebug ? "Gen-OTH-L3" : "See-doc(OTHER)");
  }
  else if (INFILE(_LT_SEE_OUTPUT_1)) {
    INTERESTING(lDebug ? "Gen-EXC-1" : "GNU-style(interactive)");
  }
  return;
#endif  /* OLD_VERSION */
}


int checkPublicDomain(char *filetext, int size, int score, int kwbm,
    int isML, int isPS)
{
  int ret;

#ifdef  PROC_TRACE
  traceFunc("== checkPublicDomain(%p, %d, %d, 0x%x, %d, %d)\n", filetext,
      size, score, kwbm, isML, isPS);
#endif  /* PROC_TRACE */

  if (pd >= 0) {  /* already tried? */
    return(pd);
  }
  ret = 0;        /* default answer is "no" */
  if (INFILE(_LT_PUBDOM_CC)) {
    INTERESTING(lDebug ? "Pubdom(CC)" : LS_PD_CLM);
    ret = 1;
  } else if (INFILE(_LT_PUBDOM_ODC)) {
    INTERESTING(lDebug ? "Pubdom(ODC)" : LS_PD_CLM);
    ret = 1;
  } else if (INFILE(_LT_PUBDOM_PDD)) {
    INTERESTING(lDebug ? "Pubdom(PDD)" : LS_PD_CLM);
    ret = 1;
  } else if (INFILE(_LT_PUBDOM_USE)) {
    INTERESTING(lDebug ? "Pubdom(use)" : LS_PD_CLM);
    ret = 1;
  } else if (INFILE(_LT_PUBDOM_NOTclaim)) {
    INTERESTING(LS_NOT_PD);
    ret = 1;
  } else if (INFILE(_CR_PUBDOM)) {
    if (INFILE(_LT_PUBDOMNOTcpyrt)) {
      INTERESTING(LS_PD_CLM);
    } else {
      INTERESTING(LS_PD_CPRT);
    }
    ret = 1;
  } else if (INFILE(_CR_NONE)) {
    INTERESTING(lDebug ? "Pubdom(no-CR)" : LS_PD_CLM);
    ret = 1;
  } else if (INFILE(_LT_UNLIN) || URL_INFILE(_LT_UNLINref)) {
    INTERESTING("Unlicense");
    ret = 1;
  } else if (INFILE(_LT_PUBDOM_1)) {
    INTERESTING(lDebug ? "Pubdom(1)" : LS_PD_CLM);
    ret = 1;
  } else if (INFILE(_LT_PUBDOM_2) && !INFILE(_PHR_PUBLIC_FUNCT) && !INFILE(_LT_NOTPUBDOM_1)) {
    INTERESTING(lDebug ? "Pubdom(2)" : LS_PD_CLM);
    ret = 1;
  } else if (INFILE(_LT_PUBDOM_3)) {
    INTERESTING(lDebug ? "Pubdom(3)" : LS_PD_CLM);
    ret = 1;
#ifdef REMOVED_AS_TOO_BROAD
  } else if (INFILE(_LT_PUBDOM_4)) {
    INTERESTING(lDebug ? "Pubdom(4)" : LS_PD_CLM);
    ret = 1;
  } else if (INFILE(_LT_PUBDOM_5)) {
    INTERESTING(lDebug ? "Pubdom(5)" : LS_PD_CLM);
    ret = 1;
  } else if (INFILE(_LT_PUBDOM_6)) {
    INTERESTING(lDebug ? "No-more-copyright" : LS_PD_CLM);
    ret = 1;
#endif // done removing too broad signatures
  } else if (INFILE(_LT_PUBDOM_7)) {
    INTERESTING(lDebug ? "Pubdom(7)" : LS_PD_CLM);
    ret = 1;
  } else if (INFILE(_LT_PUBDOM_8)) {
    INTERESTING(lDebug ? "Pubdom(8)" : LS_PD_CLM);
    ret = 1;
  }else if (HASKW(kwbm, _KW_public_domain) && score < 3) {
    INTERESTING(LS_PD_ONLY);
    ret = 1;
  }
  return(ret);
}


/**
 * \brief If we call this function, we still don't know anything about a license.
 * In fact, there may be NO license.  Look for copyrights, references to
 * the word "trademark", "patent", etc. (and possibly other trivial (or
 * borderline-insignificant) legal stuff in this file.
 */
void checkCornerCases(char *filetext, int size, int score,
    int kwbm, int isML, int isPS, int nw, int force)
{

#ifdef  PROC_TRACE
  traceFunc("== checkCornerCases(%p, %d, %d, %d, %d, %d, %d, %d)\n",
      filetext, size, score, kwbm, isML, isPS, nw, force);
#endif  /* PROC_TRACE */

  if (crCheck++) {        /* only need to check this once */
    return;
  }
  if (INFILE(_LT_NOTATT_NOTBSD)) {
    LOWINTEREST("non-ATT-BSD");
  }

  /*
   * FINAL cases: (close to giving up) -- lowest-importance items
   */
  if (/*force ||*/ !(*licStr)) {
    if (HASTEXT(_TEXT_TRADEMARK, 0)) {      /* a trademark? */
      LOWINTEREST(LS_TDMKONLY);
    }
  }
  if (!(*licStr)) {
    /*
     * We may have matched something but ultimately determined there's nothing
     * significant or of any interest, so empty the list of any matches we may
     * have observed to this point.
     */
    listClear(&whereList, NO);      /* force 'nothing to report' */
  }
  return;
}

int match3(int base, char *buf, int score, int save, int isML, int isPS)
{
  int i;
  int j;
  char *cp;
  /* */
#ifdef PROC_TRACE
#ifdef PROC_TRACE_SWITCH
  if (gl.ptswitch)
#endif /* PROC_TRACE_SWITCH */
    printf("== match3(%d, %p, %d, %d, %d, %d)\n", base, buf, score, save,
        isML, isPS);
#else /* not PROC_TRACE */
#ifdef UNKNOWN_CHECK_DEBUG
  printf("== match3(%d, %p, %d, %d, %d, %d)\n", base, buf, score, save,
      isML, isPS);
#endif /* UNKNOWN_CHECK_DEBUG */
#endif /* not PROC_TRACE */
  /* */
  for (i = 1; i <= 3; i++) {
    if (dbgIdxGrep(base+i, buf, (save && lDiags)) == 0) {
#ifdef UNKNOWN_CHECK_DEBUG
      printf("match3: FAILED regex (%d)!\n", base+i);
#endif /* UNKNOWN_CHECK_DEBUG */
      return(0);
    }
  }
#ifdef UNKNOWN_CHECK_DEBUG
  printf("match3: Success (%s)!\n",
      (save ? "buffer-for-real" : "file-initial-check"));
#endif /* UNKNOWN_CHECK_DEBUG */
  /*
   * Some "possible licenses" are technical descriptions that share some words
   * that typically appear in licenses (distribution, terms, permission(s)).
   *****
   * If we're checking a paragraph (e.g., "save" is non-zero), see if there are
   * other engineering-development-technical terms in the paragraph that tend
   * to NOT be present in licenses...
   */
  if (save) {
    for (j = i = 0, cp = buf; *cp; i++, cp++) {
      if (*cp & 0200) {
        j++;
      }
    }
#ifdef UNKNOWN_CHECK_DEBUG
    printf("DEEBUG: %d bytes, %d 8-bit\n", i, j);
#endif /* UNKNOWN_CHECK_DEBUG */
    if (j >= (i/2)) {
      if (lDiags) {
        printf("... no, >= 50 percent 8-bit characters\n");
      }
      return(0);
    }
    /*
    We need to allocate space for a doctored-up version of the candidate
    unknown-license paragraph, but it's ONLY used in this function.  E.g.,
    make darn sure we free it up before exiting this function...
     */
    cp = copyString(buf, MTAG_TEXTPARA);
    doctorBuffer(cp, isML, isPS, NO);
    /*
    If we detected a no-warraty statement earlier, "checknw" is != 0.
    Look for a no-warrany statement in this candidate paragraph.
    If we find it, report failure for the paragraph and remember
    finding the no--warranty.
     */
    if (checknw && idxGrep(checknw, cp, REG_ICASE|REG_EXTENDED)) {
      if (lDiags) {
        printf("... no, warranty regex %d\n", checknw);
      }
      checknw = 0;
      memFree(cp, MTAG_TEXTPARA);
      return(0);
    }
    /*
    False-positive-check: GNU/FSF template (often see in ".po"
    and ".c" files

    "This file is distributed under the same license as the
    package PACKAGE"
     */
    if (dbgIdxGrep(_LT_BOGUSTMPL, cp, lDiags)) {
      if (lDiags) {
        printf("... no, FSF-GNU template\n");
      }
      memFree(cp, MTAG_TEXTPARA);
      return(0);
    }
    /*
     * False-positive-check: GNU GPL preamble statements; these have been
     * "sprinkled" throughout files seen before, so check ALL of them.
     */
    if (dbgIdxGrep(_PHR_GNU_FREEDOM, cp, lDiags) ||
        dbgIdxGrep(_PHR_GNU_COPYING, cp, lDiags) ||
        dbgIdxGrep(_PHR_GNU_PROTECT, cp, lDiags)) {
      if (lDiags) {
        printf("... no, GNU-GPL preamble\n");
      }
      memFree(cp, MTAG_TEXTPARA);
      return(0);
    }
    if (lDiags) {
      printf("... candidate paragraph analysis:\n");
    }
    for (i = j = 0; i < NKEYWORDS; i++) {
      if (idxGrep(i+_KW_first, cp, REG_EXTENDED|REG_ICASE)) {
        if (lDiags) {
          printf("%s", j ? ", " : "KEYWORDS: ");
          printf("%s", _REGEX(i+_KW_first));
        }
        j++;
      }
    }
    if (lDiags) {
      if (j) {
        printf("\n");
      }
      printf("SCORES: para %d, file %d == %05.2f%% ", j,
          score, 100.0 * (float) j / (float) score);
    }
    /*
    Here, we guess that an UnclassifiedLicense exists in a paragraph
    when:
    + a paragraph has a keyword-score of at least 3 -OR-
    + ... a keyword-score of 2 *AND* is >= 50% of the file's
    total score

    It's likely we'll see a few false-positives with a
    keyword-score of 2 but there are cases where this works.
    We can filter out the 2-scores we see
    with the FILTER checks below...
     */
    if (j == 0) { /* no license-like keywords */
      if (lDiags) {
        printf("(ZERO legal keywords)\n");
      }
      memFree(cp, MTAG_TEXTPARA);
      return(0);
    }
    if (j >= 3 || (j == 2 && j*2 >= score)) {
      if (j >= 3 && lDiags) {
        printf("(LIKELY: para-score >= 2)\n");
      }
      else if (lDiags) {
        printf("(MAYBE: local percentage)\n");
      }
    }
    else {
      if (lDiags) {
        printf("(NOT LIKELY a license)\n");
#if 0
#endif
        printf("[FAILED]\n%s\n[/FAILED]\n", buf);
      }
      memFree(cp, MTAG_TEXTPARA);
      return(0);
    }
    /*
    Sure, there ARE paragraphs with these words that do NOT constitute a
    real license.  Look for key words and phrases of them HERE.  This list
    of filters will likely grow over time.
     */
    for (i = 0; i < NFILTER; i++) {
      if (dbgIdxGrep(_FILTER_first+i, buf, lDiags)) {
        if (lDiags) {
          printf("!! NO-LIC: filter %d\n", ++i);
        }
        memFree(cp, MTAG_TEXTPARA);
        return(0);
      }
    }
    if (cur.licPara == NULL_STR) {
      saveLicenseParagraph(buf, isML, isPS, YES);
    }
    memFree(cp, MTAG_TEXTPARA);
  }
#ifdef UNKNOWN_CHECK_DEBUG
  else {
    printf("match3: Initial-check only (save == %d)\n", save);
  }
#endif /* UNKNOWN_CHECK_DEBUG */
  return(1);
}

void saveLicenseParagraph(char *mtext, int isML, int isPS, int entireBuf)
{
  char *cp;
  char *start = mtext;
  int len;
  /* */
#ifdef PROC_TRACE
#ifdef PROC_TRACE_SWITCH
  if (gl.ptswitch)
#endif /* PROC_TRACE_SWITCH */
    printf("== saveLicenseParagraph(%p, %d, %d, %d)\n", mtext, isML, isPS, entireBuf);
#endif /* PROC_TRACE */
  /* */
  if (entireBuf) {
    cur.licPara = copyString(mtext, MTAG_TEXTPARA);
  } else {
    if (cur.regm.rm_so < 50) {
      len = cur.regm.rm_eo + 80;
    } else {
      len = cur.regm.rm_eo + 130 - cur.regm.rm_so;
      start += cur.regm.rm_so - 50;
    }
    cur.licPara = memAlloc(len + 9, MTAG_TEXTPARA);
    (void) strcpy(cur.licPara, "... ");
    (void) strncpy(cur.licPara + 4, start, len);
    (void) strcpy(cur.licPara + len + 4, " ...");
  }
  /*
   * Convert double-line-feed chars ("\r" and "\n" combos) to a single "\n"
   */
  for (cp = cur.licPara; *cp; cp++) {
    if ((*cp == '\n' || *cp == '\r') &&
        (*(cp+1) == '\r' || *(cp+1) == '\n')) {
      *cp = ' ';
      *(cp+1) = '\n';
    }
  }
  if (lDiags) {
    printf("[PERHAPS] (%p)\n%s\n[/PERHAPS]\n", cur.licPara, cur.licPara);
  }
  return;
}

#ifdef  LTSR_DEBUG
#define LT_TARGET       1299    /* set to -1 OR the string# to track */
void showLTCache(char *msg)
{
  int i = 0;
  int nCached = 0;
  int nMatch = 0;

  printf("%s\n", msg);
  if (LT_TARGET >= 0) {
    printf("... tracking string #%d\n", LT_TARGET);
  }
  while (i < NFOOTPRINTS) {
    if (ltsr[i] < LTSR_SMASK) {
      printf(i == LT_TARGET ? "x" : ".");
    } else if (ltsr[i] == LTSR_YES) {
      printf("%%");
      nMatch++;
      nCached++;
    } else {
      printf(i == LT_TARGET ? "0" : ":");
      nCached++;
    }
    if ((++i % 75) == 0) {
      printf("|%04d\n", i);
    }
  }
  printf("\nLTSR-matches: %d, Cached: %d\n", nMatch, nCached);
  return;
}
#endif  /* LTSR_DEBUG */

#ifdef DOCTOR_DEBUG
/*
 Debugging
 */
void dumpMatch(char *text, char *label)
{
  char *x = text + cur.regm.rm_so;
  char *cp = text + cur.regm.rm_eo;

  if (label) {
    printf("%s ", label);
  }
  printf("@ %d [", cur.regm.rm_so);
  for (; x < cp; x++) {
    if (!isEOL(*x)) {
      printf("%c", *x);
    }
  }
  printf("]\n");
  return;
}
#endif  /* DOCTOR_DEBUG  */
