/*
 SPDX-FileCopyrightText: © 2006-2015 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2017-2019 Bittium Wireless Ltd.

 SPDX-License-Identifier: GPL-2.0-only
*/
/* Equivalent to version 1.83 of Core Nomos code. */
#include <ctype.h>

#include "nomos.h"

#include "parse.h"
#include "list.h"
#include "util.h"
#include "nomos_regex.h"
#include "nomos_utils.h"
#include <_autodefs.h>

/* DEBUG
#define DOCTOR_DEBUG 1
#define PROC_TRACE 1
   DEBUG */

/**
 * \file
 * \brief searches for licenses
 *
 * The main workhorse of nomos. This file contains most of the logic for finding
 * licenses in nomos.
 */

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
#define _mNTP           33 // To avoid W3C-style detection
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
#define _fCITRIX        45
#define _fPURDUE        46
#define _fUNICODE       47
#define _fOFL           48
#define _mAPACHE10      49
#define _mAPACHE11      50
#define _mWORDNET       51
#define _fNCSA          52
#define _fTCL           53
#define _fIJG           54
#define _msize          _fIJG+1
//@}

/**
 * Regex match related data
 */
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
char *oslVersion(char *, int, int, int);
char *aflVersion(char *, int, int, int);
static int match3(int, char *, int, int, int, int);
void spdxReference(char *, int, int, int);
void copyleftExceptions(char *, int, int, int);
//@}

/**
 * \name local variables
 * File local variables
 */
//@{
/**
 * Detected licenses are stored here in a form ',BSD,MIT' etc
 */
static char licStr[myBUFSIZ];

static char ltsr[NFOOTPRINTS]; /**< License Text Search Results,
           a bytemask for each possible match string */
static char name[256];
static char lmem[_msize];
static list_t searchList;
static list_t whereList;
static list_t whCacheList;
static int refOffset;
static int maxInterest;
static int pd; /**< Flag for whether we've checked for a
          public domain "license" */
static int crCheck;
static int checknw;
static int lDebug = 0; /**< set this to non-zero for more debugging */
static int lDiags = 0; /**< set this to non-zero for printing diagnostics */
//@}

/**
 * \name micro function definitions
 * These #define's save a LOT of typing and indentation... :)
 */
//@{
#define PARSE_ARGS      filetext, size, isML, isPS  ///< Arguments to parse
#define LVAL(x)         (ltsr[x] & LTSR_RMASK)      ///< Check LTSR_RMASK on lstr[x]
#define SEEN(x)         (ltsr[x] & LTSR_SMASK)      ///< Check LTSR_SMASK on lstr[x]
#define INFILE(x)       fileHasPatt(x, PARSE_ARGS, 0) ///< Calls fileHasPatt()
#define NOT_INFILE(x)   !( fileHasPatt(x, PARSE_ARGS, 0) && clearLastElementOfLicenceBuffer() ) ///< Calls fileHasPatt()
#define RM_INFILE(x)    fileHasPatt(x, PARSE_ARGS, 1) ///< Calls fileHasPatt() with qType 1
#define GPL_INFILE(x)   fileHasPatt(x, PARSE_ARGS, 2) ///< Calls fileHasPatt() with qType 2
#define PERL_INFILE(x)  fileHasPatt(x, PARSE_ARGS, 3) ///< Calls fileHasPatt() with qType 3
#define NY_INFILE(x)    fileHasPatt(x, PARSE_ARGS, 4) ///< Calls fileHasPatt() with qType 4
#define X_INFILE(x, y)  fileHasPatt(x, PARSE_ARGS, y) ///< Calls fileHasPatt() with qType y
#define DEBUG_INFILE(x) printf(" Regex[%d] = \"%s\"\nINFILE(%d) = %d\n", x, _REGEX(x), x, INFILE(x)); ///< Debug print
#define HASREGEX(x, cp) idxGrep(x, cp, REG_ICASE|REG_EXTENDED)  ///< Calls idxGrep()
#define HASREGEX_RI(x, cp) idxGrep_recordIndex(x, cp, REG_ICASE|REG_EXTENDED) ///< Calls idxGrep_recordIndex()
#define HASTEXT(x, fl)  idxGrep_recordIndex(x, filetext, REG_ICASE|fl)  ///< Calls idxGrep_recordIndex()
#define URL_INFILE(x)   (INFILE(x) || fileHasPatt(x, PARSE_ARGS, -1)) ///< Check in file with qType 0|1
#define CANSKIP(i,x,y,z)        ((i >= y) && (i <= z) && !(kwbm & (1 << (x - _KW_first))))
#define HASKW(x, y)     (x & (1 << (y - _KW_first)))
#define TRYGROUP(x)     x(PARSE_ARGS)
#define LOWINTEREST(x)  addRef(x, IL_LOW)
#define MEDINTEREST(x)  addRef(x, IL_MED)
//#define INTERESTING(x)  printf("INTERESTING: %s, %d, %s\n", __FILE__, __LINE__, x);addRef(x, IL_HIGH)
#define INTERESTING(x)  addRef(x, IL_HIGH)
#define ASLVERS()       aslVersion(PARSE_ARGS)
#define CCVERS()        ccVersion(PARSE_ARGS)
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
#define SPDXREF()       spdxReference(PARSE_ARGS)
#define EXCEPTIONS()    copyleftExceptions(PARSE_ARGS)
//@}

/**
 * \brief Checks for a phrase in a file
 * \param licTextIdx  Index of phrase to look
 * \param filetext    Content of file
 * \param size        File size
 * \param isML        File content is HTML/XML
 * \param isPS        File content is a post script
 * \param qType       <0, look at raw text. >=0 look in doctored buffers
 * \return True if pattern found
 */
static int fileHasPatt(int licTextIdx, char *filetext, int size,
    int isML, int isPS, int qType)
{
  int ret = 0;
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
    ret = idxGrep_recordPosition(licTextIdx, filetext, REG_ICASE | REG_EXTENDED | show);
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

/**
 * \brief Debugging call for idxGrep()
 *
 * Function calls idxGrep() and print the regex match using printRegexMatch()
 * \param licTextIdx  license index
 * \param buf
 * \param show
 * \return -1 on regex-compile failure, 1 if regex search fails, and 0 if
 * regex search is successful.
 */
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

/**
 * \brief Parse a file to check all the possible licenses and add them to
 * matches
 *
 * The function calls fileHasPatt() if the file contains a pattern defined in
 * STRINGS.in. If a match is found, then it can call idxGrep_recordIndex() to
 * check if the file has some additional text and finally adds the license
 * using addRef(). The results found are also stored in licStr as a comma
 * separated list.
 *
 * The function first check if a file contains an interesting string which can
 * denote a license. If it is found then the heuristics are done in detail to
 * find the exact license match. For more info please refer to
 * [nomos wiki](https://github.com/fossology/fossology/wiki/Nomos#step-2-change-the-scanner---parsec)
 * \param filetext  File content
 * \param size      File size
 * \param[out] scp  Scan results
 * \param isML      Source is HTML/XML
 * \param isPS      Source is PostScript
 * \return  Next index in licStr
 */
char *parseLicenses(char *filetext, int size, scanres_t *scp,
    int isML, int isPS)
{
  static int first = 1;
  char *cp;
  int i;
  int j;
  int nw = 0;
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
  if (!isPS && (strncasecmp(filetext, "%%page:", 7) == 0 || strncasecmp(filetext, "{\\rtf", 5) == 0)) {
#if defined(DEBUG) || defined(DOCTOR_DEBUG)
    printf("File is really postscript, %s filetext !\n", filetext);
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
   * MySQL.FLOSS exception
   */
  if (INFILE(_LT_MYSQL_EXCEPT) || INFILE(_PHR_FREE_LIBRE)) {
    if (INFILE(_TITLE_ALFRESCO)) {
      INTERESTING("Alfresco-FLOSS");
    }
    else if (HASTEXT(_TEXT_ALFRESCO, 0)) {
      INTERESTING("Alfresco");
    }
    else if (INFILE(_CR_MYSQL) || INFILE(_TITLE_mysql_floss_exception)) {
      if (INFILE(_TITLE_MYSQL_V03)) {
        INTERESTING("MySQL-0.3");
      }
      else {
        INTERESTING("mysql-floss-exception");
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
    /*
     * List of other licenses should be excluded only if full license text is found
     */
    if (INFILE(_LT_RPSL_COMPATIBLE)) {
      lmem[_fREAL] = 1;
    }
    if (INFILE(_LT_REAL_RPSL)) {
      cp = REALVERS(_TITLE_RPSL);
      INTERESTING(lDebug ? "RPSL" : cp);
    }
    else if (INFILE(_LT_REAL_RPSLref)) {
      cp = REALVERS(_TITLE_RPSL);
      INTERESTING(lDebug ? "Real-RPSL(ref)" : cp);
    }
    if (INFILE(_LT_REAL_RCSL)) {
      cp = REALVERS(_TITLE_RCSL);
      INTERESTING(lDebug ? "RCSL" : cp);
    }
    else if (INFILE(_LT_REAL_RCSLref)) {
      cp = REALVERS(_TITLE_RCSL);
      INTERESTING(lDebug ? "Real-RCSL(ref)" : cp);
    }
    if (INFILE(_TITLE_REAL_EULA)) {
      INTERESTING("RealNetworks-EULA");
    }
    else if (INFILE(_LT_HELIX_TITLE)) {
      INTERESTING("Helix.RealNetworks-EULA");
    }
  }
  cleanLicenceBuffer();
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
    else if (INFILE(_SPDX_ZPL_11)) {
      INTERESTING("ZPL-1.1");
      lmem[_fZPL] = 1;
    }
    else if (INFILE(_SPDX_ZPL_20)) {
      INTERESTING("ZPL-2.0");
      lmem[_fZPL] = 1;
    }
    else if (INFILE(_SPDX_ZPL_21)) {
      INTERESTING("ZPL-2.1");
      lmem[_fZPL] = 1;
    }
    else if (INFILE(_TITLE_ZIMBRA_13)) {
      INTERESTING("Zimbra-1.3");
    }
    else if (INFILE(_TITLE_ZIMBRA_12)) {
      INTERESTING("Zimbra-1.2");
    }
    else {
      INTERESTING(lDebug ? "Zope(ref)" : "ZPL");
      lmem[_fZPL] = 1;
    }
  }
  cleanLicenceBuffer();
  /*
   * Check Apache licenses before BSD
   */
  if (HASTEXT(_PHR_Apache_ref0, REG_EXTENDED) || INFILE(_PHR_Apache_ref7) || INFILE(_TITLE_Apache)) {
    cp = ASLVERS();
    INTERESTING(cp);
  }
  cleanLicenceBuffer();
  /*
   * BSD and all the variant 'flavors'.  BSD licenses are kind of like
   * the cooking concept of 'the mother sauces' -- MANY things are derived
   * from the wordings of these licenses.  There are still many more, for
   * certain, but LOTS of licenses are based on ~10 originally-BSD-phrases.
   */
  if (INFILE(_LT_BSD_1)) {
    if (INFILE(_TITLE_PHP301)) {
      INTERESTING(lDebug ? "PHP(v3.01#1)" : "PHP-3.01");
      lmem[_mPHP] = 1;
    }
    else if (INFILE(_TITLE_PHP30)) {
      INTERESTING(lDebug ? "PHP(v3.0#1)" : "PHP-3.0");
      lmem[_mPHP] = 1;
    }
    else if (INFILE(_TITLE_PHP202)) {
      INTERESTING(lDebug ? "PHP(v2.02#1)" : "PHP-2.02");
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
    else if (INFILE(_LT_Oracle_Berkeley_DB)) {
      INTERESTING("Oracle-Berkeley-DB");
    }
    else if (INFILE(_CR_SLEEPYCAT) || INFILE(_LT_SLEEPYCAT_1)) {
      MEDINTEREST(lDebug ? "Sleepycat(1)" : "Sleepycat");
    }
    else if (INFILE(_TITLE_ZEND_V20)) {
      INTERESTING("Zend-2.0");
    }
    else if (!lmem[_fOPENLDAP] && !TRYGROUP(famOPENLDAP)) {
      if (HASTEXT(_LT_OPENSSLref5, REG_EXTENDED)) {
        INTERESTING(lDebug ? "OpenSSL(ref)" : "OpenSSL");
      }
      else if (INFILE(_LT_BSD_CLAUSE_0) && INFILE(_LT_BSD_CLAUSE_1) && INFILE(_LT_BSD_CLAUSE_2)) {
        if (INFILE(_LT_BSD_CLAUSE_3) && (INFILE(_LT_BSD_CLAUSE_4) || INFILE(_LT_BSD_CLAUSE_4_LONG)) && INFILE(_LT_UC)) {
          INTERESTING("BSD-4-Clause-UC");
        }
        else if (INFILE(_LT_BSD_CLAUSE_3) && (INFILE(_LT_BSD_CLAUSE_4) || INFILE(_LT_BSD_CLAUSE_4_LONG))) {
        INTERESTING("BSD-4-Clause");
        }
        else if (INFILE(_LT_BSD_CLAUSE_4) && INFILE(_LT_BSD_CLAUSE_CLEAR)) {
          INTERESTING("BSD-3-Clause-Clear");
        }
        else if (INFILE(_LT_BSD_CLAUSE_4) && INFILE(_LT_BSD_CLAUSE_OPEN_MPI)) {
          INTERESTING("BSD-3-Clause-Open-MPI");
        }
        else if (INFILE(_LT_BSD_CLAUSE_4) && HASTEXT(_KW_severability, REG_EXTENDED)) {
          INTERESTING("BSD-3-Clause-Severability");
        }
        else if (INFILE(_LT_XML_DB_V10)) {
          INTERESTING("XMLDB-1.0");
        }
        else if (INFILE(_LT_BSD_CLAUSE_4) && INFILE(_LT_ANT_BSD_RESTRICTION)) {
          INTERESTING("ANT+SharedSource");
        }
        else if (!lmem[_mAPACHE11] && INFILE(_LT_Apache_11_CLAUSE_3) && INFILE(_LT_Apache_11_CLAUSE_4) && INFILE(_LT_Apache_11_CLAUSE_5)) {
          INTERESTING(lDebug ? "BSD(Apache-1.1)" : "Apache-1.1-style");
        }
        else if(HASTEXT(_LT_Sendmail_823_title, 0)) {
           INTERESTING("Sendmail-8.23");
        }
        else if (!lmem[_mAPACHE10] && !lmem[_mAPACHE11] && INFILE(_LT_BSD_CLAUSE_ATTRIBUTION)) {
          INTERESTING("BSD-3-Clause-Attribution");
        }
        else if (!lmem[_mAPACHE10] && !lmem[_mAPACHE11] && INFILE(_LT_BSD_CLAUSE_4)) {
          if (INFILE(_LT_DARPA_COUGAAR_2)) {
            INTERESTING("DARPA-Cougaar");
          }
          else {
            INTERESTING("BSD-3-Clause");
          }
        }
        else if (INFILE(_LT_SSLEAY)) {
          INTERESTING("SSLeay");
        }
        else if (INFILE(_LT_TMATE)) {
          INTERESTING("TMate");
        }
        else if (INFILE(_LT_MUP)) {
          INTERESTING("Mup");
        }
        else if (INFILE(_LT_FREE_BSD)) {
          INTERESTING("BSD-2-Clause-FreeBSD");
        }
        else if (INFILE(_LT_BSD_CLAUSE_PATENT)) {
          INTERESTING("BSD-2-Clause-Patent");
        }
        else if (INFILE(_CR_NETBSD)) {
          INTERESTING("BSD-2-Clause-NetBSD");
        }
        else if (INFILE(_LT_MIT_0)) {
          lmem[_mMIT] = 1;
          INTERESTING("Linux-OpenIB");
        }
        else if (!lmem[_mAPACHE10] && !lmem[_mAPACHE11]) {
          INTERESTING("BSD-2-Clause");
        }
      }
      else if (INFILE(_CR_CRYPTOGAMS)) {
        INTERESTING("Cryptogams");
      }
      else if (INFILE(_LT_BSD_SHORTENED_CLAUSE_0) && INFILE(_LT_BSD_SHORTENED_CLAUSE_1) && INFILE(_LT_BSD_SHORTENED_CLAUSE_2) && INFILE(_LT_BSD_CLAUSE_3)) {
        INTERESTING("BSD-4-Clause-Shortened");
      }
      else if (INFILE(_CR_BSDCAL)) {
        INTERESTING(lDebug ? "BSD(1)" : "BSD");
      }
      else if (HASTEXT(_TEXT_ALTERED_SOURCE, REG_EXTENDED) && HASTEXT(_TEXT_ORIGIN, 0)) {
        if (INFILE(_PHR_BZIP2_3)) {
          INTERESTING("bzip2-1.0.5");
        }
        else if (HASTEXT(_PHR_BZIP2_4, REG_EXTENDED)) {
          INTERESTING("bzip2-1.0.6");
        }
        else {
          INTERESTING("bzip2");
        }
      }
      else if (mCR_CMU()) {
        INTERESTING(lDebug ? "CMU(BSD-ish)" : "CMU");
      }
      else if (INFILE(_LT_MTLL)) {
        INTERESTING("MTLL");
      }
      else if (INFILE(_LT_BSD_CLAUSE_1_DISCLAIMER)) {
        INTERESTING("BSD-1-Clause");
      }
      else if (INFILE(_LT_Spencer_99) && INFILE(_CR_Spencer)) {
        INTERESTING("Spencer-99");
      }
      else if (!lmem[_fZPL]) {
        INTERESTING(lDebug ? "BSD-style(1)" : "BSD-style");
      }
    }
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSD_CLEAR_CLAUSE_0) && INFILE(_LT_BSD_CLAUSE_1) && INFILE(_LT_BSD_CLAUSE_2)) {
    INTERESTING("BSD-3-Clause-Clear");
  }
  else if (INFILE(_PHR_Linux_OpenIB)) {
    INTERESTING("Linux-OpenIB");
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
    else if (INFILE(_LT_CMU_7)) {
      if (INFILE(_CR_CMU_1) || INFILE(_CR_CMU_2) || INFILE(_CR_BSDCAL)) {
        INTERESTING("MIT-CMU");
      }
      else {
        INTERESTING("MIT-CMU-style");
      }
      lmem[_mCMU] = 1;
    }
    else if (INFILE(_CR_BSDCAL)) {
      INTERESTING(lDebug ? "BSD(2)" : "BSD");
    }
    else if (INFILE(_LT_NTP)) {
      INTERESTING("NTP");
    }
    else if (INFILE(_LT_WORDNET))
    {
      INTERESTING("WordNet-3.0");
      lmem[_mMIT] = 1;
    }
    else if (INFILE(_LT_HPND_1) && INFILE(_LT_HPND_2)) {
      INTERESTING("HPND");
      lmem[_mMIT] = 1;
    }
    else if (INFILE(_LT_NOT_ADVERTISING)) {
      INTERESTING(lDebug ? "BSD-style(2)" : "BSD-style");
    }
    else if (INFILE(_PHR_NO_WARRANTY_12)) {
      INTERESTING(lDebug ? "ISC(BSD-style)" : "ISC-style");
    }
    else {
      INTERESTING(lDebug ? "BSD-style(2)-MIT" : "MIT-style");
    }
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSD_3) && NOT_INFILE(_TITLE_OPENLDAP)) {
    if (INFILE(_LT_AMPAS)) {
      INTERESTING("AMPAS");
    }
    else if (INFILE(_CR_BSDCAL)) {
      INTERESTING(lDebug ? "BSD(3)" : "BSD");
    }
    else if (INFILE(_TITLE_OZPLB_10)) {
      INTERESTING("OZPLB-1.0");
    }
    /*
     * JPNIC
     */
    else if (HASTEXT(_TEXT_JPNIC, 0) && INFILE(_LT_JPNIC)) {
      INTERESTING("JPNIC");
    }
    else if (NOT_INFILE(_CR_XFREE86) && NOT_INFILE(_TITLE_NCSA) && NOT_INFILE(_TITLE_INNERNET200)) {
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
    if (HASTEXT(_LT_MAKEINDEX_1, 0) && HASTEXT(_LT_MAKEINDEX_2, 0)) {
      INTERESTING("MakeIndex");
    }
    else if (INFILE(_CR_BSDCAL)) {
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
      INTERESTING(lDebug ? "BSD(NonC)" : "BSD.non-commercial");
    }
    else {
      INTERESTING(lDebug ? "BSD-style(NonC)" : "Non-commercial");
    }
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_SPDX_BSD_3_Clause_Clear)) {
    INTERESTING("BSD-3-Clause-Clear");
  }
  else if (INFILE(_SPDX_BSD_3_Clause_No_Nuclear_License_2014)) {
    INTERESTING("BSD-3-Clause-No-Nuclear-License-2014");
  }
  else if (INFILE(_SPDX_BSD_3_Clause_No_Nuclear_License)) {
    INTERESTING("BSD-3-Clause-No-Nuclear-License");
  }
  else if (INFILE(_SPDX_BSD_3_Clause_No_Nuclear_Warranty)) {
    INTERESTING("BSD-3-Clause-No-Nuclear-Warranty");
  }
  else if (INFILE(_SPDX_BSD_3_Clause_Attribution)) {
    INTERESTING("BSD-3-Clause-Attribution");
  }
  else if (INFILE(_SPDX_BSD_3_Clause_LBNL)) {
    INTERESTING("BSD-3-Clause-LBNL");
  }
  else if (INFILE(_SPDX_BSD_3_Clause_Open_MPI)) {
    INTERESTING("BSD-3-Clause-Open-MPI");
  }
  else if (INFILE(_SPDX_BSD_3_Clause)) {
    INTERESTING("BSD-3-Clause");
  }
  else if (INFILE(_PHR_BSD_3_CLAUSE_1) || INFILE(_PHR_BSD_3_CLAUSE_2) || INFILE(_PHR_BSD_3_CLAUSE_3) || INFILE(_PHR_BSD_3_CLAUSE_4)) {
    INTERESTING(lDebug ? "BSD(phr1/2)" : "BSD-3-Clause");
  }
  else if (INFILE(_SPDX_BSD_2_Clause_FreeBSD)) {
    INTERESTING("BSD-2-Clause-FreeBSD");
  }
  else if (INFILE(_SPDX_BSD_2_Clause_NetBSD)) {
    INTERESTING("BSD-2-Clause-NetBSD");
  }
  else if (INFILE(_SPDX_BSD_2_Clause_Patent)) {
    INTERESTING("BSD-2-Clause-Patent");
  }
  else if (INFILE(_SPDX_BSD_2_Clause_Views)) {
    INTERESTING("BSD-2-Clause-Views");
  }
  else if (INFILE(_SPDX_BSD_2_Clause_1)) {
    INTERESTING("BSD-2-Clause");
  }
  else if (INFILE(_SPDX_BSD_2_Clause_2)) {
    INTERESTING("BSD-2-Clause");
  }
  else if (INFILE(_PHR_BSD_2_CLAUSE_1)
        || INFILE(_PHR_BSD_2_CLAUSE_2)
        || INFILE(_PHR_BSD_2_CLAUSE_3)
        || INFILE(_PHR_BSD_2_CLAUSE_4)
        || INFILE(_PHR_BSD_2_CLAUSE_5)
        || INFILE(_PHR_BSD_2_CLAUSE_6)
        || INFILE(_PHR_BSD_2_CLAUSE_7)) {
    INTERESTING(lDebug ? "BSD(phr1/2/3/4/5/6)" : "BSD-2-Clause");
  }
  else if (INFILE(_SPDX_BSD_4_Clause_UC)) {
    INTERESTING("BSD-4-Clause-UC");
  }
  else if (INFILE(_SPDX_BSD_4_Clause)) {
    INTERESTING("BSD-4-Clause");
  }
  else if (INFILE(_PHR_BSD_4_CLAUSE_1)) {
    INTERESTING(lDebug ? "BSD-4-Clause(phr1)" : "BSD-4-Clause");
  }
  else if (INFILE(_PHR_BSD_CLEAR_1)) {
    INTERESTING(lDebug ? "BSD-Clear(phr1)" : "BSD-3-Clause-Clear");
  }
  else if (INFILE(_PHR_BSD_3_CLAUSE_LBNL)) {
    INTERESTING("BSD-3-Clause-LBNL");
  }
  else if (INFILE(_SPDX_BSD_Protection)) {
    INTERESTING("BSD-Protection");
  }
  else if (INFILE(_SPDX_BSD_Source_Code)) {
    INTERESTING("BSD-Source-Code");
  }
  else if (INFILE(_SPDX_BSD_1_Clause)) {
    INTERESTING("BSD-1-Clause");
  }
  else if (INFILE(_PHR_0BSD)) {
    INTERESTING("0BSD");
  }
  else if (INFILE(_LT_BSDref1)) {
    INTERESTING(lDebug ? "BSD(ref1)" : "BSD");
  }
  else if (INFILE(_LT_BSDref2)) {
    INTERESTING(lDebug ? "BSD(ref2)" : "BSD");
  }
  else if (INFILE(_LT_BSDref3)) {
    INTERESTING(lDebug ? "BSD(ref3)" : "BSD");
  }
  else if (INFILE(_LT_BSDref4)) {
    INTERESTING(lDebug ? "BSD(ref4)" : "BSD");
  }
  else if (INFILE(_LT_BSDref5)) {
    INTERESTING(lDebug ? "BSD(ref5)" : "BSD");
  }
  else if (INFILE(_LT_BSDref6)) {
    INTERESTING(lDebug ? "BSD(ref6)" : "BSD");
  }
  else if (INFILE(_LT_BSDref7)) {
    INTERESTING(lDebug ? "BSD(ref7)" : "BSD");
  }
  else if (INFILE(_LT_BSDref8)) {
    INTERESTING(lDebug ? "BSD(ref8)" : "BSD");
  }
  else if (INFILE(_LT_BSDref9)) {
    INTERESTING(lDebug ? "BSD(ref9)" : "BSD");
  }
  else if (INFILE(_LT_BSDref10)) {
    INTERESTING(lDebug ? "BSD(ref10)" : "BSD");
  }
  else if (INFILE(_LT_BSDref11)) {
    INTERESTING(lDebug ? "BSD(ref11)" : "BSD");
  }
  else if (INFILE(_LT_BSDref12) || HASTEXT(_LT_BSDref13, REG_EXTENDED)) {
    INTERESTING(lDebug ? "BSD(ref12)" : "BSD-3-Clause");
  }
  else if (URL_INFILE(_URL_BSD_1) || URL_INFILE(_URL_BSD_2)) {
    INTERESTING(lDebug ? "BSD(url)" : "BSD");
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSDref14)) {
    INTERESTING(lDebug ? "BSD(ref14)" : "BSD");
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSDref15)) {
    INTERESTING(lDebug ? "BSD(ref15)" : "BSD");
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
  else if (INFILE(_LT_BSDSTYLEref4)) {
    INTERESTING(lDebug ? "BSD-st(4)" : "BSD-style");
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSDSTYLEref3)) {
    INTERESTING(lDebug ? "BSD-st(3)" : "BSD-style");
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_LT_BSD_HTMLAREA_1) || INFILE(_LT_BSD_HTMLAREA_2)) {
    INTERESTING(lDebug ? "BSD-htmlArea" : "BSD-3-Clause");
    lmem[_fBSD] = 1;
  }
  else if (INFILE(_FILE_BSD1) || INFILE(_FILE_BSD2)) {
    INTERESTING(lDebug ? "BSD(deb)" : "BSD");
  }
  cleanLicenceBuffer();
  /*
   * Aptana public license (based on MPL)
   */
  if (INFILE(_LT_APTANA)) {
    if (INFILE(_TITLE_APTANA_V10)) {
      INTERESTING("Aptana-1.0");
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
      INTERESTING(lDebug ? "PHP(v2.02#3)" : "PHP-2.02");
    }
    else if (INFILE(_CR_PHP)) {
      INTERESTING(lDebug ? "PHP(1)" : "PHP");
    }
    else {
      INTERESTING("PHP-style");
    }
    lmem[_mPHP] = 1;
  }
  else if (!lmem[_mPHP] && INFILE(_LT_PHP_V301_1)) {
    INTERESTING("PHP-3.01");
    lmem[_mPHP] = 1;
  }
  else if (!lmem[_mPHP] && INFILE(_LT_PHP_V30_1)) {
    INTERESTING("PHP-3.0");
    lmem[_mPHP] = 1;
  }
  else if (!lmem[_mPHP] && INFILE(_LT_PHP_V30_2)) {
    INTERESTING("PHP-3.0");
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
  else if (INFILE(_LT_PHP_ref)) {
    INTERESTING(lDebug ? "PHP(2)" : "PHP");
    lmem[_mPHP] = 1;
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  if (INFILE(_LT_HACKTIVISMO)) {
    INTERESTING("Hacktivismo");
    lmem[_mGPL] = 1;        /* don't look for GPL references */
  }
  cleanLicenceBuffer();
  if (INFILE(_LT_NESSUS) && INFILE(_TITLE_NESSUS)) {
    INTERESTING("NESSUS-EULA");
    lmem[_mLGPL] = 1;       /* don't look for LGPL references */
    lmem[_mGPL] = 1;
  }
  cleanLicenceBuffer();
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
  else if (URL_INFILE(_URL_ORACLE_BERKELEY_DB)) {
    INTERESTING(lDebug ? "URL_ORACLE_BERKELEY_DB" : "Oracle-Berkeley-DB");
  }
  cleanLicenceBuffer();
  /*
   * CeCILL
   * According to digikam-0.9.4/digikam/libs/greycstoration/CImg.h:
   * The CeCILL-C (C_V1) license is close to the GNU LGPL
   * The CeCILL (V2.0) license is compatible with the GNU GPL
   */
  if (INFILE(_TITLE_CECILL_V11_2) || INFILE(_SPDX_CECILL_11)) {
    INTERESTING(lDebug ? "CeCILL_v1.1(#2)" : "CECILL-1.1");
    lmem[_mGPL] = lmem[_mLGPL] = 1;
  }
  else if (INFILE(_TITLE_CECILL_B) || INFILE(_TITLE_CECILL_B1) || INFILE(_SPDX_CECILL_B)) {
    INTERESTING("CECILL-B");
  }
  else if (INFILE(_TITLE_CECILL_C) || INFILE(_TITLE_CECILL_C1) || INFILE(_SPDX_CECILL_C)) {
    INTERESTING("CECILL-C");
  }
  else if (INFILE(_LT_CECILL_DUALref)) {
    INTERESTING("CECILL(dual)");
    lmem[_mGPL] = lmem[_mLGPL] = 1;
  }
  else if (INFILE(_SPDX_CECILL_10)) {
    INTERESTING("CECILL-1.0");
  }
  else if (INFILE(_SPDX_CECILL_21)) {
    INTERESTING("CECILL-2.1");
  }
  else if (INFILE(_LT_CECILL_2_ref) || INFILE(_SPDX_CECILL_20)) {
    INTERESTING("CECILL-2.0");
  }
  else if (INFILE(_LT_CECILL_ref2)) {
    INTERESTING("CECILL");
  }
  else if (INFILE(_LT_CECILL_B_ref)) {
    INTERESTING("CECILL-B");
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
  cleanLicenceBuffer();
  /*
   * Monash University
   */
  if (INFILE(_CR_UMONASH) && INFILE(_LT_UMONASH)) {
    INTERESTING("U-Monash");
    if (INFILE(_PHR_GPL_NO_MORE)) {
      lmem[_mGPL] = 1;
    }
  }
  cleanLicenceBuffer();
  /* Open Font License   */
  if (INFILE(_LT_OPEN_FONT_V10) || INFILE(_LT_OPEN_FONT_V10_1))
  {
    INTERESTING("OFL-1.0");
    lmem[_fOFL] = 1;
  }
  else if (INFILE(_SPDX_OFL_10_no_RFN)) {
    INTERESTING("OFL-1.0-no-RFN");
  }
  else if (INFILE(_SPDX_OFL_10_RFN)) {
    INTERESTING("OFL-1.0-RFN");
  }
  else if (INFILE(_SPDX_OFL_10)) {
    INTERESTING("OFL-1.0");
  }
  else if (INFILE(_PHR_OPEN_FONT_V10_1) || INFILE(_PHR_OPEN_FONT_V10_2))
  {
    INTERESTING("OFL-1.0");
    lmem[_fOFL] = 1;
  }
  else if (INFILE(_LT_OPEN_FONT_V11) || INFILE(_LT_OPEN_FONT_V11_1))
  {
    INTERESTING("OFL-1.1");
    lmem[_fOFL] = 1;
  }
  else if (INFILE(_SPDX_OFL_11_no_RFN)) {
    INTERESTING("OFL-1.1-no-RFN");
  }
  else if (INFILE(_SPDX_OFL_11_RFN)) {
    INTERESTING("OFL-1.1-RFN");
  }
  else if (INFILE(_SPDX_OFL_11)) {
    INTERESTING("OFL-1.1");
  }
  else if (INFILE(_PHR_OPEN_FONT_V11_1) || INFILE(_PHR_OPEN_FONT_V11_2))
  {
    INTERESTING("OFL-1.1");
    lmem[_fOFL] = 1;
  }
  cleanLicenceBuffer();
  /* Simple Public License 2.0 */
  if (INFILE(_TITLE_SimPL_V2)) {
    INTERESTING("SimPL-2.0");
    lmem[_mGPL] = 1;
  }
  cleanLicenceBuffer();
  /* Leptonica license */
  if (INFILE(_TITLE_LEPTONICA) && INFILE(_LT_GNU_3)) {
    INTERESTING("Leptonica");
  }
  cleanLicenceBuffer();
  /* copyleft-next license
   * It has to be checked before GPL because the license has the reference
   * to GPL license which gives a false positive GPL finding.
   */
  if (INFILE(_TITLE_copyleft_next_030) && INFILE(_PHR_copyleft_next_PARA1) && INFILE(_PHR_copyleft_next_PARA3)) {
    INTERESTING("copyleft-next-0.3.0");
    lmem[_mGPL] = 1;
  }
  else if (INFILE(_TITLE_copyleft_next_031) && INFILE(_PHR_copyleft_next_PARA1) && INFILE(_PHR_copyleft_next_PARA3)) {
    INTERESTING("copyleft-next-0.3.1");
    lmem[_mGPL] = 1;
  }
  else if (INFILE(_PHR_copyleft_next_030) || INFILE(_SPDX_copyleft_next_030)) {
    INTERESTING("copyleft-next-0.3.0");
  }
  else if (INFILE(_PHR_copyleft_next_031) || INFILE(_SPDX_copyleft_next_031)) {
    INTERESTING("copyleft-next-0.3.1");
  }
  cleanLicenceBuffer();
  /*
   * GPL, LGPL, GFDL
   * QUESTION: do we need to check for the FSF copyright since we also
   * check for "GNU" or "free"?
   */
  if ((NOT_INFILE(_LT_FORMER_GNU) && (mCR_FSF() ||
      HASTEXT(_TEXT_GNUTERMS, REG_EXTENDED)))) {
    /*
     * Affero
     */
    if (INFILE(_PHR_AGPL) && NOT_INFILE(_LT_GPL3ref4)) {
      if (INFILE(_LT_AGPL1) || INFILE(_LT_AGPL2) ||
          INFILE(_LT_AGPL3)) {
        cp = AGPLVERS();
        INTERESTING(lDebug ? "Affero(#1)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_LT_AGPLref1)) {
        cp = AGPLVERS();
        INTERESTING(lDebug ? "Affero(#2)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_LT_AGPLref2) && NOT_INFILE(_LT_NOT_AGPLref1)) {
        cp = AGPLVERS();
        INTERESTING(lDebug ? "Affero(#3)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (mCR_FSF() && NOT_INFILE(_LT_GPL3_NOT_AGPL)) {
        cp = AGPLVERS();
        INTERESTING(lDebug ? "Affero(CR)" : cp);
        lmem[_mGPL] = 1;
      }
    }
    else if (INFILE(_LT_AGPL_NAMED)) {
      cp = AGPLVERS();
      INTERESTING(lDebug ? "AGPL(named)" : cp);
      lmem[_mGPL] = 1;
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
      INTERESTING("GPL-1.0-only");
      lmem[_mGPL] = 1;
    }
    else if (INFILE(_LT_GPL_1) && !HASTEXT(_LT_GPL_EXCEPT_0, REG_EXTENDED)) {
      if (INFILE(_PHR_GPL2_OR_LATER_1) && !HASTEXT(_LT_IGNORE_CLAUSE, REG_EXTENDED))
      {
        INTERESTING(lDebug ? "PHR(GPL2_OR_LATER#1)" : "GPL-2.0-or-later");
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_TITLE_GPL2)) {
        INTERESTING(lDebug ? "Title(GPL-2.0-only)" : "GPL-2.0-only");
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_PHR_GPL1_OR_LATER) && !HASTEXT(_LT_IGNORE_CLAUSE, REG_EXTENDED))
      {
        INTERESTING("GPL-1.0-or-later");
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_TITLE_GPL1)) {
        INTERESTING("GPL-1.0-only");
        lmem[_mGPL] = 1;
      }
      else {
        INTERESTING("GPL");
        lmem[_mGPL] = 1;
      }
    }
    else if ((INFILE(_LT_GPL_V2) || INFILE(_LT_GPL_V2_ref) || INFILE(_LT_GPL_V2_ref1) || INFILE(_LT_GPL_V2_ref2)) && !HASTEXT(_LT_GPL_EXCEPT_0, REG_EXTENDED)) {
      if (INFILE(_PHR_GPL2_OR_LATER_1) && !HASTEXT(_LT_IGNORE_CLAUSE, REG_EXTENDED))
      {
        INTERESTING(lDebug ? "PHR(GPL2_OR_LATER#2)" : "GPL-2.0-or-later");
        lmem[_mGPL] = 1;
      }
      else {
        INTERESTING(lDebug ? "LT(GPL-V2)" : "GPL-2.0-only");
        lmem[_mGPL] = 1;
      }
    }
    else if (INFILE(_PHR_GPL2_OR_LATER_2))
    {
      INTERESTING(lDebug ? "PHR(GPL2_OR_LATER#2)" : "GPL-2.0-or-later");
      lmem[_mGPL] = 1;
    }
    else if (INFILE(_LT_GPL3_PATENTS)) {
      if (INFILE(_TITLE_GPL3)) {
        INTERESTING(lDebug ? "GPL_v3(#1)" : "GPL-3.0-only");
        lmem[_mGPL] = 1;
      }
      else {
        INTERESTING("GPL-3.0-only-possibility");
        lmem[_mGPL] = 1;
      }
    }
    else if (INFILE(_TITLE_GPL3_ref3_later)) {
      INTERESTING("GPL-3.0-or-later");
      lmem[_mGPL] = 1;
    }
    else if (INFILE(_TITLE_GPL3_ref4_later)) {
      INTERESTING("GPL-3.0-or-later");
      lmem[_mGPL] = 1;
    }
    else if (INFILE(_TITLE_GPL3_ref3)) {
      INTERESTING("GPL-3.0-only");
      lmem[_mGPL] = 1;
    }
    if (INFILE(_LT_LGPL_1) || INFILE(_LT_LGPL_2) || INFILE(_LT_LGPL21_OR_LATER_GENERAL)) {
      if (INFILE(_PHR_LGPL21_OR_LATER_1) && !HASTEXT(_LT_IGNORE_CLAUSE, REG_EXTENDED))
      {
        INTERESTING("LGPL-2.1-or-later");
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_PHR_LGPL2_OR_LATER) && !HASTEXT(_LT_IGNORE_CLAUSE, REG_EXTENDED))
      {
        INTERESTING("LGPL-2.0-or-later");
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_TITLE_LGPLV21)) {
        INTERESTING("LGPL-2.1-only");
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_TITLE_LGPLV2)) {
        INTERESTING("LGPL-2.0-only");
        lmem[_mLGPL] = 1;
      }
      else {
        INTERESTING("LGPL");
        lmem[_mLGPL] = 1;
      }
    }
    else if (INFILE(_LT_LGPL_3)) {
      if ((INFILE(_PHR_LGPL3_OR_LATER)
          || INFILE(_PHR_LGPL3_OR_LATER_ref1)
          || INFILE(_PHR_LGPL3_OR_LATER_ref2))
          && !HASTEXT(_LT_IGNORE_CLAUSE, REG_EXTENDED))
      {
        INTERESTING("LGPL-3.0-or-later");
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_TITLE_LGPL3)) {
        INTERESTING("LGPL-3.0-only");
        lmem[_mLGPL] = 1;
      }
      else {
        INTERESTING("LGPL-3.0-only-possibility");
        lmem[_mLGPL] = 1;
      }
    }
    if (INFILE(_LT_GFDL)) {
      cp = GFDLVERS();
      INTERESTING(lDebug ? "GFDL(#1)" : cp);
      lmem[_mGFDL] = 1;
    }
    if (!lmem[_mLGPL] && NOT_INFILE(_LT_MPL_SECONDARY)) {  /* no FSF/GPL-like match yet */
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
      else if (INFILE(_LT_LGPL3_ref_later)) {
        INTERESTING("LGPL-3.0-or-later");
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_LT_LGPLref1)) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(ref1)" : cp);
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_LT_LGPLref2) &&
          NOT_INFILE(_PHR_NOT_UNDER_LGPL)) {
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
          NOT_INFILE(_PHR_NOT_UNDER_LGPL)) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(ref7)" : cp);
        lmem[_mLGPL] = 1;
      }
      else if (!lmem[_fREAL] && !lmem[_mAPTANA] &&
          !LVAL(_TEXT_GNU_LIC_INFO) && INFILE(_LT_LGPLref8) &&
          NOT_INFILE(_PHR_NOT_UNDER_LGPL)) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(ref8)" : cp);
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_LT_LGPLref9) &&
          NOT_INFILE(_PHR_NOT_UNDER_LGPL)) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(ref9)" : cp);
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_LT_LGPLref10) &&
          NOT_INFILE(_PHR_NOT_UNDER_LGPL)) {
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
      else if (lmem[_fREAL] && !LVAL(_TEXT_GNU_LIC_INFO) &&
          GPL_INFILE(_LT_LGPL_OR)) {
        cp = LGPLVERS();
        INTERESTING(lDebug ? "LGPL(or)" : cp);
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_PHR_LGPL21_OR_LATER_2)) {
        INTERESTING(lDebug ? "LGPL-2.1-or-later(_LATER_2)" : "LGPL-2.1-or-later");
        lmem[_mLGPL] = 1;
      }
      else if (INFILE(_PHR_LGPL21_ONLY_ref) || INFILE(_TITLE_LGPLV21_2)) {
        INTERESTING("LGPL-2.1-only");
        lmem[_mLGPL] = 1;
      }
    }
    if (!lmem[_mGPL] && !HASTEXT(_TEXT_GCC, REG_EXTENDED)) {
      if (GPL_INFILE(_LT_GPL_ALT) && !INFILE(_LT_LGPL_ALT)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(alternate)" : cp);
        lmem[_mGPL] = 1;
      }
      else if ((GPL_INFILE(_LT_GPL3ref2) || GPL_INFILE(_PHR_GPL3_OR_LATER)
            || GPL_INFILE(_PHR_GPL3_OR_LATER_ref1) || GPL_INFILE(_PHR_GPL3_OR_LATER_ref2))
            && !HASTEXT(_LT_IGNORE_CLAUSE, REG_EXTENDED))
      {
        INTERESTING("GPL-3.0-or-later");
        lmem[_mGPL] = 1;
      }
      else if (GPL_INFILE(_LT_GPL3ref)) {
        INTERESTING(lDebug ? "GPL_v3(#2)" : "GPL-3.0-only");
        lmem[_mGPL] = 1;
      }
      else if (GPL_INFILE(_LT_GPL3ref3) && NOT_INFILE(_TITLE_LGPL3)) {
        INTERESTING("GPL-3.0-only");
        lmem[_mGPL] = 1;
      }
      else if (!lmem[_mLIBRE] && GPL_INFILE(_LT_GPLref1)
          && NOT_INFILE(_PHR_NOT_UNDER_GPL)
          && NOT_INFILE(_LT_LGPLref2)
          && NOT_INFILE(_PHR_GPL_COMPAT_3)) {
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
        else if (!HASTEXT(_TEXT_GCC, REG_EXTENDED)
            && NOT_INFILE(_TITLE_D_FSL_10)){
          cp = GPLVERS();
          INTERESTING(cp);
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
          NOT_INFILE(_LT_LGPLref2)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(ref14)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_LT_GPLref16)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(ref16)" : cp);
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_LT_GPLref18)) {
        cp = GPLVERS();
        INTERESTING(lDebug ? "GPL(ref18)" : cp);
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
      else if (!LVAL(_TEXT_GNU_LIC_INFO) && NOT_INFILE(_LT_INTEL_7) &&
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
    if (!lmem[_mGPL] && INFILE(_PHR_GPL_DESCRIPTIONS)) {
      INTERESTING(lDebug ? "GPL-kinda" : "GPL");
      lmem[_mGPL] = 1;
    }
    /* checking for FSF */
    if (INFILE(_LT_FSF_1)) {
      INTERESTING(lDebug ? "FSF(1)" : "FSFULLR");
      lmem[_mLGPL] = 1;
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
    else if (!lmem[_mGPL] && mCR_FSF() && INFILE(_LT_FSF_5)) {
      INTERESTING(lDebug ? "FSF(5)" : "FSF");
    }
    else if (!lmem[_mGPL] && INFILE(_LT_FSFUL)) {
      INTERESTING("FSFUL");
    }
    else if (!lmem[_mGPL] && NOT_INFILE(_LT_Autoconf_exception_20) && INFILE(_LT_FSFref1)) {
      INTERESTING(lDebug ? "FSF(ref1)" : "FSF");
    }
    else if (INFILE(_LT_FSFref2)) {
      INTERESTING(lDebug ? "FSF(ref2)" : "FSF");
    }
    else if (INFILE(_LT_LGPLrefFSF) &&
        NOT_INFILE(_PHR_NOT_UNDER_LGPL)) {
      INTERESTING(lDebug ? "LGPL(FSF)" : "LGPL");
      lmem[_mLGPL] = 1;
    }
    if (!lmem[_mGPL] && !lmem[_mLGPL] && !lmem[_mGFDL]) {
      /*
       * Check these patterns AFTER checking for FSF and GFDL, and only if the
       * CUPS license isn't present.
       */
      if (!lmem[_mCUPS] ) {
        if (GPL_INFILE(_LT_GPLpatt1) &&
            NOT_INFILE(_PHR_NOT_UNDER_LGPL)) {
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
          INTERESTING(lDebug ? "GPLISH" : "GPL-possibility");
          INTERESTING(lDebug ? "GPLISH" : "LGPL-possibility");
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
  cleanLicenceBuffer();
  if (!lmem[_mGPL] && INFILE(_LT_GNU_PROJECTS)) {
    cp = GPLVERS();
    INTERESTING(lDebug ? "GPL(proj)" : cp);
    lmem[_mGPL] = 1;
  }
  cleanLicenceBuffer();
  if (HASTEXT(_LT_GPL_V2_NAMED_later, REG_EXTENDED) || HASTEXT(_TITLE_GPL2_ref1_later, REG_EXTENDED))
  {
    INTERESTING(lDebug ? "GPLV2+(named)" : "GPL-2.0-or-later");
    lmem[_mGPL] = 1;
  }
  else if (INFILE(_LT_TAPJOY) || INFILE(_LT_TAPJOY_ref1)) {
    INTERESTING("Tapjoy");
    lmem[_fGPL] = 1;
    lmem[_mMIT] = 1;
  }
  else if (!lmem[_mGPL] && !lmem[_mGFDL] && !lmem[_mLGPL] && !lmem[_fZPL]
      && (INFILE(_LT_GPL_NAMED)
        || INFILE(_LT_GPL_NAMED2)
        || HASTEXT(_LT_GPL_NAMED3, REG_EXTENDED))
      && !HASTEXT(_PHR_GPL_GHOSTSCRIPT, REG_EXTENDED)
      && NOT_INFILE(_LT_MPL_SECONDARY)
      && NOT_INFILE(_TEXT_NOT_GPL)
      && NOT_INFILE(_TEXT_NOT_GPL2)
      && NOT_INFILE(_LT_CNRI_PYTHON_GPL)
      && NOT_INFILE(_LT_W3Cref4)
      && NOT_INFILE(_LT_GPL_NAMED3_EXHIBIT)
      && NOT_INFILE(_LT_GPL_NAMED_COMPATIBLE)
      && !HASTEXT(_LT_GPL_NAMED_COMPATIBLE_1, REG_EXTENDED)
      && NOT_INFILE(_LT_GPL_NAMED_EXHIBIT)
      && NOT_INFILE(_TITLE_D_FSL_10)
      && NOT_INFILE(_LT_INTEL_7)
      && NOT_INFILE(_PHR_GPL_COMPAT_3)) {
    cp = GPLVERS();
    INTERESTING(lDebug ? "GPL(named)" : cp);
    lmem[_mGPL] = 1;
  }
  else if ( !lmem[_mGPL] && !INFILE(_TITLE_MIROS) && (INFILE(_LT_GPL_V2_NAMED) || INFILE(_LT_GPL_V2_NAMED_ref1)))
  {
    INTERESTING(lDebug ? "GPLV2(named)" : "GPL-2.0-only");
    lmem[_mGPL] = 1;
  }
  else if (!lmem[_mGPL] && INFILE(_LT_GPL_V3_NAMED_later))
  {
    INTERESTING(lDebug ? "GPLV3(named_later)" : "GPL-3.0-or-later");
  }
  else if (!lmem[_mGPL] && INFILE(_LT_GPL_V3_NAMED))
  {
    INTERESTING(lDebug ? "GPLV3(named)" : "GPL-3.0-only");
  }
  cleanLicenceBuffer();
  if (!lmem[_mLGPL] && (INFILE(_LT_LGPL_NAMED)
        || INFILE(_LT_LGPL_NAMED2)) && NOT_INFILE(_LT_GPL_NAMED_EXHIBIT)
      && NOT_INFILE(_LT_PHP_V30_2)) {
    cp = LGPLVERS();
    INTERESTING(lDebug ? "LGPL(named)" : cp);
  }

  cleanLicenceBuffer();
  /*
   * MIT, X11, Open Group, NEC -- text is very long, search in 2 parts
   */
  if (INFILE(_LT_JSON) && INFILE(_LT_MIT_NO_EVIL)) { // JSON license
    INTERESTING("JSON");
    lmem[_mMIT] = 1;
  }
  cleanLicenceBuffer();
  if (!lmem[_mWORDNET] && INFILE(_TITLE_WORDNET))
  {
    INTERESTING("WordNet-3.0");
  }
  cleanLicenceBuffer();
  if (INFILE(_CR_XFREE86_V10) || INFILE(_LT_XFREE86_V10)) {
    INTERESTING("XFree86-1.0");
    lmem[_mMIT] = 1;
  }
  else if (INFILE(_CR_XFREE86_V11) || INFILE(_LT_XFREE86_V11)) {
    INTERESTING("XFree86-1.1");
    lmem[_mMIT] = 1;
  }
  else if (INFILE(_LT_XFREE86)) {
    INTERESTING(lDebug ? "XFree86(1)" : "XFree86");
    lmem[_mMIT] = 1;
  }
  else if (HASTEXT(_LT_BSD_OR_MIT, REG_EXTENDED)) {
    INTERESTING("MIT");
    INTERESTING("BSD");
    lmem[_mMIT] = 1;
  }
  else if (HASTEXT(_LT_BSD_AND_MIT, REG_EXTENDED)) {
    INTERESTING("BSD");
    INTERESTING("MIT");
    lmem[_mMIT] = 1;
  }
  /*
   * MIT search order changed. First MIT license explicit phrases and references are checked .
   */
  else if (!lmem[_mMIT] && NOT_INFILE(_TITLE_MIT_EXHIBIT) && NOT_INFILE(_TITLE_SGI) &&
        (INFILE(_LT_MIT_1) || INFILE(_TITLE_MIT))) {
    if(INFILE(_LT_MIT_NO_EVIL)) {
      INTERESTING(lDebug ? "MIT-style(no evil)" : "JSON");
      lmem[_mMIT] = 1;
    }
    else if (INFILE(_LT_MIT_2)) {
      if (mCR_MIT() || INFILE(_TITLE_MIT)) {
        INTERESTING(lDebug ? "MIT(1)" : "MIT");
        lmem[_mMIT] = 1;
      }
      else if (INFILE(_TITLE_XNET)) {
        INTERESTING("Xnet");
        lmem[_mMIT] = 1;
      }
      else if (INFILE(_CR_X11) || INFILE(_TITLE_X11)) {
        INTERESTING(lDebug ? "X11(1)" : "X11");
        lmem[_mMIT] = 1;
      }
      else if (INFILE(_CR_XFREE86)) {
        INTERESTING(lDebug ? "XFree86(2)" : "XFree86");
        lmem[_mMIT] = 1;
      }
      /* MIT-advertising License */
      else if (INFILE(_LT_MIT_ADVERTISING)) {
        INTERESTING("MIT-advertising");
        lmem[_mMIT] = 1;
      }
      /* MIT-enna License */
      else if (INFILE(_LT_MIT_ENNA)) {
        INTERESTING("MIT-enna");
        lmem[_mMIT] = 1;
      }
      /* MIT-feh License */
      else if (INFILE(_LT_MIT_FEH)) {
        INTERESTING("MIT-feh");
        lmem[_mMIT] = 1;
      }
      /* MITNFA License */
      else if (HASTEXT(_LT_MITNFA, 0)) {
        INTERESTING("MITNFA");
        lmem[_mMIT] = 1;
      }
      /* Imlib2 License */
      else if (INFILE(_LT_Imlib2)) {
        INTERESTING("Imlib2");
        lmem[_mMIT] = 1;
      }
      else if (INFILE(_LT_MIT_13)) {
        INTERESTING(lDebug ? "MIT(14)" : "MIT");
        lmem[_mMIT] = 1;
      }
      /* NCSA */
      else if (INFILE(_TITLE_NCSA) && NOT_INFILE(_TITLE_NCSA_EXHIBIT)) {
        INTERESTING(lDebug ? "NCSA(1)" : "NCSA");
        lmem[_mMIT] = 1;
        lmem[_fNCSA] = 1;
      }
      else if (INFILE(_LT_MIT_0)) {
        INTERESTING("MIT-0");
        lmem[_mMIT] = 1;
      }
      else if (NOT_INFILE(_LT_INTEL_7)) {
        INTERESTING(lDebug ? "MIT-style(1)" : "MIT-style");
        lmem[_mMIT] = 1;
      }
    }
    else if (INFILE(_LT_BITSTREAM_1)) {
      INTERESTING(lDebug ? "Bitstream(1)" : "Bitstream");
      lmem[_mMIT] = 1;
    }
    else if (mCR_X11()) {
      INTERESTING(lDebug ? "X11(2)" : "X11");
    }
    else if (!lmem[_mMPL] && INFILE(_LT_MPL_1)) {
      cp = MPLVERS(); /* NPL, too */
      INTERESTING(lDebug ? "MPL/NPL#5" : cp);
      lmem[_mMPL] = 1;
    }
    else if (!lmem[_mMIT] && (mCR_MIT() || INFILE(_TITLE_MIT)) && NOT_INFILE(_TITLE_MIT_EXHIBIT)) {
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
    else if (mCR_MIT()) {
      INTERESTING(lDebug ? "MIT(3)" : "MIT");
      lmem[_mMIT] = 1;
    }
    else if (INFILE(_TITLE_ICU) || HASTEXT(_URL_ICU, REG_EXTENDED)) {
      INTERESTING(lDebug ? "MIT-style(ICU)" : "ICU");
      lmem[_mMIT] = 1;
    }
    else if (INFILE(_LT_ICU_2) && (INFILE(_CR_IBM_1) || INFILE(_CR_IBM_1))) {
      INTERESTING(lDebug ? "MIT-style(ICU)" : "ICU");
      lmem[_mMIT] = 1;
    }
    else if (INFILE(_TITLE_JasPer_20)) {
      INTERESTING(lDebug ? "JasPer(title)" : "JasPer-2.0");
      lmem[_mMIT] = 1;
    }
    else if (INFILE(_LT_UNICODE_4)) {
      INTERESTING(lDebug ? "MIT-style(Unicode)" : "Unicode");
      lmem[_fUNICODE] = 1;
      lmem[_mMIT] = 1;
    }
    /*
     * Adobe-Glyph
     */
    else if (HASTEXT(_LT_ADOBE_GLYPH_1, REG_EXTENDED) && INFILE(_LT_ADOBE_GLYPH_2)) {
      INTERESTING("Adobe-Glyph");
    }
    /*
     * Ubuntu Font
     */
    else if (INFILE(_LT_UBUNTU_FONT)) {
      INTERESTING("ubuntu-font-1.0");
    }
    /*
     * OFL license text has MIT license warranty claims which is identifed as MIT-style
    */
    else if (!lmem[_fOFL]) {
      INTERESTING(lDebug ? "MIT-style(2)" : "MIT-style");
      lmem[_mMIT] = 1;
    }
  }
  /*
   * Secondly X11 and MIT style phrases are checked.
   */
  else if (INFILE(_LT_MIT_0) && (INFILE(_LT_MIT_2) || INFILE(_LT_MIT_3) || INFILE(_LT_MIT_4) ||
        INFILE(_LT_MIT_5) || INFILE(_LT_MIT_6) || INFILE(_LT_MIT_7))) {
    if(INFILE(_LT_MIT_NO_EVIL)) {
      INTERESTING(lDebug ? "MIT-style(no evil)" : "JSON");
      lmem[_mMIT] = 1;
    }
    else if (INFILE(_LT_X11_5)) {
      if (INFILE(_CR_XFREE86)) {
        INTERESTING(lDebug ? "XFree86(3)" : "XFree86");
      }
      else {
        INTERESTING(lDebug ? "X11(3)" : "X11");
      }
      lmem[_mMIT] = 1;
    }
    else if (INFILE(_LT_Xnet_STYLE)) {
      INTERESTING("Xnet");
      lmem[_mMIT] = 1;
    }
    else if (INFILE(_LT_TAPJOY)) {
      INTERESTING("Tapjoy");
      lmem[_mMIT] = 1;
    }
    else {
      /*
       * SPDX defines curl license. It has very typical MIT-style text. The only way to
       * identify it is to use copyright or copyright reference.
       */
      if (INFILE(_CR_CURL)) {
        INTERESTING("curl");
      }
      else {
        INTERESTING(lDebug ? "MIT-style(7)" : "MIT-style");
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
  cleanLicenceBuffer();
  /*
   * Open Group, NEC, MIT use the same text in licenses
   */
  if (INFILE(_LT_MIT_6)) {
    if (!lmem[_mMIT] && INFILE(_CR_OpenGroup)) {
      INTERESTING(lDebug ? "OpenGroup(1)" : "OpenGroup");
      lmem[_mMIT] = 1;
    }
    else if (!lmem[_mCMU] && mCR_CMU()) {
      INTERESTING(lDebug ? "CMU(2)" : "CMU");
      lmem[_mCMU] = 1;
    }
    else if (!lmem[_mMIT] && mCR_MIT()) {
      INTERESTING(lDebug ? "MIT(6)" : "MIT");
      lmem[_mMIT] = 1;
    }
    else if (!lmem[_mMIT] && INFILE(_LT_HPND_1) && INFILE(_LT_HPND_2)) {
      INTERESTING("HPND-sell-variant");
      lmem[_mMIT] = 1;
    }
    /*
     * _LT_MIT_6 is very similar to _LT_BSD_2 where MIT-CMU licenses
     * are also detected. Following else if is the copy from there.
     */
    else if (!lmem[_mMIT] && INFILE(_LT_CMU_7)) {
      if (INFILE(_CR_CMU_1) || INFILE(_CR_CMU_2) || INFILE(_CR_BSDCAL)) {
        INTERESTING("MIT-CMU");
      }
      else {
        INTERESTING("MIT-CMU-style");
      }
      lmem[_mCMU] = 1;
    }
    else if (!lmem[_mMIT]) {
      INTERESTING(lDebug ? "MIT-style(4)" : "MIT-style");
      lmem[_mMIT] = 1;
    }
  }
  else if (INFILE(_SPDX_MIT_0)) {
    INTERESTING("MIT-0");
  }
  else if (INFILE(_SPDX_MIT_advertising)) {
    INTERESTING("MIT-advertising");
  }
  else if (INFILE(_SPDX_MIT_enna)) {
    INTERESTING("MIT-enna");
  }
  else if (INFILE(_SPDX_MIT_feh)) {
    INTERESTING("MIT-feh");
  }
  else if (!lmem[_mMIT] && INFILE(_LT_MIT_7)) {
    if (INFILE(_CR_OpenGroup)) {
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
  else if ((!lmem[_mMIT] || mCR_MIT()) && INFILE(_LT_MITref1)) {
    INTERESTING(lDebug ? "MIT(ref1)" : "MIT");
    lmem[_mMIT] = 1;
  }
  else if (!lmem[_mMIT] && INFILE(_LT_MITref2)) {
    INTERESTING(lDebug ? "MIT(ref2)" : "MIT");
    lmem[_mMIT] = 1;
  }
  else if (!lmem[_mMIT] && INFILE(_LT_MITref6)) {
    INTERESTING(lDebug ? "MIT(ref6)" : "MIT");
    lmem[_mMIT] = 1;
  }
  else if (!lmem[_mMIT] && INFILE(_LT_MITref3)) {
    INTERESTING(lDebug ? "MIT(ref3)" : "MIT-style");
    lmem[_mMIT] = 1;
  }
  else if (!lmem[_mMIT] && INFILE(_LT_MITref4)) {
    INTERESTING(lDebug ? "MIT(ref4)" : "MIT");
    lmem[_mMIT] = 1;
  }
  else if (!lmem[_mMIT] && (INFILE(_LT_MITref5) || INFILE(_LT_MITref9))) {
    INTERESTING(lDebug ? "MIT(ref5)" : "MIT");
    lmem[_mMIT] = 1;
  }
  else if (INFILE(_SPDX_MIT_CMU)) {
    INTERESTING("MIT-CMU");
  }
  else if (!lmem[_fREAL] && (INFILE(_SPDX_MIT))) {
    INTERESTING(lDebug ? "MIT(SPDX)" : "MIT");
  }
  else if (!lmem[_mMIT] && !lmem[_fREAL] && INFILE(_LT_MITref7)) {
    INTERESTING(lDebug ? "MIT(ref7)" : "MIT");
    lmem[_mMIT] = 1;
  }
  else if (!lmem[_mMIT] && INFILE(_LT_MITref8)) {
    INTERESTING(lDebug ? "MIT(ref8/9)" : "MIT");
    lmem[_mMIT] = 1;
  }
  else if (INFILE(_LT_OpenGroup_1)) {
    if (INFILE(_CR_OpenGroup)) {
      INTERESTING(lDebug ? "OpenGroup(3)" : "OpenGroup");
    }
    else {
      INTERESTING(lDebug ? "OG-style(3)" : "OpenGroup-style");
    }
  }
  else if (INFILE(_LT_OpenGroup_3)) {
    if (INFILE(_CR_OpenGroup)) {
      INTERESTING(lDebug ? "OpenGroup(5)" : "OpenGroup");
    }
    else {
      INTERESTING(lDebug ? "OG-style(5)" : "OpenGroup-style");
    }
  }
  else if (INFILE(_LT_OpenGroup_PROP)) {
    if (!lmem[_mXOPEN] && INFILE(_CR_XOPEN)) {
      INTERESTING("XOPEN-EULA");
      lmem[_mXOPEN] = 1;
    }
    else if (INFILE(_CR_OpenGroup)) {
      INTERESTING("OpenGroup-Proprietary");
    }
    else {
      INTERESTING("Proprietary");
    }
  }
  else if (INFILE(_LT_X11_1)) {
    if (mCR_X11()) {
      INTERESTING(lDebug ? "X11(4)" : "X11");
    }
    else {
      INTERESTING(lDebug ? "X11-style(2)" : "X11-style");
    }
  }
  else if (INFILE(_LT_X11_2)) {
    if (mCR_X11()) {
      INTERESTING(lDebug ? "X11(5)" : "X11");
    }
    else {
      INTERESTING(lDebug ? "X11-style(3)" : "X11-style");
    }
  }
  else if (INFILE(_LT_X11_3)) {
    if (mCR_X11()) {
      INTERESTING(lDebug ? "X11(6)" : "X11");
    }
    /*
    * Unix System Laboratories
    */
    else if (INFILE(_CR_USL_EUR)) {
      INTERESTING(lDebug ? "USLE(2)" : "USL-Europe");
    }
    else {
      INTERESTING(lDebug ? "X11-style(4)" : "X11-style");
    }
  }
  else if (INFILE(_LT_X11_4)) {
    if (mCR_X11()) {
      INTERESTING(lDebug ? "X11(7)" : "X11");
    }
    else {
      INTERESTING(lDebug ? "X11-style(5)" : "X11-style");
    }
  }
  else if (INFILE(_PHR_X11_1) || INFILE(_PHR_X11_2)) {
    INTERESTING(lDebug ? "PHR(X11)" : "X11");
  }
  else if (INFILE(_LT_X11_STYLE)) {
    INTERESTING(lDebug ? "X11-style(6)" : "X11-style");
  }
  /*
   * ISC License
   */
  if (INFILE(_PHR_ISC_1) || INFILE(_PHR_ISC_2) || HASTEXT(_URL_ISC, REG_EXTENDED)) {
    INTERESTING(lDebug ? "PHR(ISC)" : "ISC");
    lmem[_mISC] = 1;
  }
  else if (INFILE(_LT_MIT_4) && INFILE(_PHR_NO_WARRANTY_12)) {
    INTERESTING(lDebug ? "ISC(MIT-style(4))" : "ISC");
    lmem[_mISC] = 1;
  }
  else if (INFILE(_LT_MIT_8) && INFILE(_CR_ISC)) {
    INTERESTING(lDebug ? "ISC(MIT-style(8))" : "ISC");
    lmem[_mISC] = 1;
  }
  cleanLicenceBuffer();
  /*
   * NTP License, note that NTP license text is detected with _LT_BSD_2
   */
  if (INFILE(_TITLE_NTP)) {
    INTERESTING("NTP");
    lmem[_mNTP] = 1;
  }
  cleanLicenceBuffer();
  /* MirOS License (MirOS) */
  if (INFILE(_TITLE_MIROS)) {
    INTERESTING("MirOS");
    lmem[_mMIT] = 1;
  }
  cleanLicenceBuffer();
  /* Libpng license */
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
    if (INFILE(_LT_W3C_19980720)) {
      INTERESTING("W3C-19980720");
    }
    else if (INFILE(_CR_W3C)) {
      INTERESTING(lDebug ? "W3C(2)" : "W3C");
    }
    else {
      INTERESTING(lDebug ? "W3C-style(2)" : "W3C-style");
    }
    lmem[_fW3C] = 1;
  }
  else if (INFILE(_LT_W3C_3)) {
    if (INFILE(_LT_W3C_8)) {
      if (INFILE(_LT_W3C_20021231)) {
        INTERESTING("W3C");
      }
      else if (INFILE(_LT_W3C_20150513)) {
        INTERESTING("W3C-20150513");
      }
    }
    else if (INFILE(_CR_W3C)) {
      INTERESTING(lDebug ? "W3C(3)" : "W3C");
    }
    else if (INFILE(_LT_W3Cref4)) {
      INTERESTING(lDebug ? "W3C(ref4)" : "W3C");
    }
    else {
      INTERESTING(lDebug ? "W3C-style(3)" : "W3C-style");
    }
    lmem[_fW3C] = 1;
  }
  else if (INFILE(_LT_W3C_4) && NOT_INFILE(_LT_PNG_ZLIB_2)) {
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
    else if (INFILE(_LT_OGC)) {
      INTERESTING("OGC");
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
  else if (URL_INFILE(_URL_W3C_20021231)) {
    INTERESTING(lDebug ? "W3C-20021231(url)" : "W3C");
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
    if (INFILE(_LT_NTP_0)) {
      INTERESTING("NTP-0");
    }
    else if (mCR_MIT()) {
      INTERESTING(lDebug ? "MIT(8)" : "MIT");
    }
    else {
      INTERESTING(lDebug ? "MIT-style(6)" : "MIT-style");
    }
    lmem[_mMIT] = 1;
  }
  else if (!lmem[_mMIT] && INFILE(_LT_MIT_4)) {
    if (mCR_MIT()) {
      INTERESTING(lDebug ? "MIT(9)" : "MIT");
    }
    else if (!lmem[_mISC] && mCR_FSF()) {
      INTERESTING(lDebug ? "FSF(7)" : "FSF");
    }
    else if (!lmem[_mISC]) {
      INTERESTING(lDebug ? "MIT-style(13)" : "MIT-style");
    }
    lmem[_mMIT] = 1;
  }
  else if (!lmem[_mMIT] && INFILE(_LT_MIT_8)) {
    if (INFILE(_CR_VIXIE)) {
      INTERESTING("Vixie");
    }
    else if (INFILE(_LT_0BSD)) {
      INTERESTING("0BSD");
    }
    else if (mCR_MIT()) {
      INTERESTING(lDebug ? "MIT(10)" : "MIT");
    }
    else if (HASTEXT(_TEXT_TU_BERLIN, 0) && HASTEXT(_LT_FREE_87,0)) {
      INTERESTING("TU-Berlin-2.0");
    }
    else {
      INTERESTING(lDebug ? "MIT-style(8)" : "MIT-style");
    }
    lmem[_mMIT] = 1;
  }
  else if (INFILE(_LT_MIT_9)) {
    if (INFILE(_CR_SLEEPYCAT)) {
      MEDINTEREST(lDebug ? "Sleepycat(2)" : "Sleepycat");
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
      /*
       * Tcl/Tk license has MIT phrase
       */
      if (INFILE(_LT_TCL)) {
        INTERESTING("TCL");
        lmem[_fTCL] = 1;
      }
      else {
        INTERESTING(lDebug ? "MIT-style(9)" : "MIT-style");
        lmem[_mMIT] = 1;
      }
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
  else if ((INFILE(_LT_MIROS_PREAMBLE) || INFILE(_TITLE_MIROS)) && INFILE(_LT_MIT_11)) {
    INTERESTING(lDebug ? "MIT-style(MirOS)" : "MirOS");
    lmem[_mMIT] = 1;
  }
  else if (INFILE(_LT_MIT_11)) {
    INTERESTING(lDebug ? "MIT-style(11)" : "MIT-style");
    lmem[_mMIT] = 1;
  }
  else if (!lmem[_mMIT] && INFILE(_LT_MITDOC)) {
    if (mCR_MIT()) {
      INTERESTING(lDebug ? "MIT(13)" : "MIT");
    }
    else {
      INTERESTING(lDebug ? "MIT-style(12)" : "MIT-style");
    }
    lmem[_mMIT] = 1;
  }
  else if (!lmem[_mMIT] && INFILE(_LT_MIT_0) && NOT_INFILE(_LT_ECL)) {
    INTERESTING(lDebug ? "MIT(0)" : "MIT-style");
    lmem[_mMIT] = 1;
  }
  else if (INFILE(_LT_MIT_14)) {
    INTERESTING(lDebug ? "MIT-style(14)" : "MIT-style");
    lmem[_mMIT] = 1;
  }
  else if (INFILE(_LT_MIT_15)) {
    INTERESTING(lDebug ? "MIT-style(15)" : "ISC-style");
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
  else if (!lmem[_mMIT] && (URL_INFILE(_URL_MIT) || URL_INFILE(_URL_MIT_ROCK))) {
    INTERESTING(lDebug ? "MIT(url)" : "MIT");
    lmem[_mMIT] = 1;
  }
  else if (HASTEXT(_TEXT_TU_BERLIN, 0) && HASTEXT(_LT_FREE_87,0)) {
    INTERESTING("TU-Berlin-1.0");
  }
  else if (INFILE(_LT_NIST_1) && INFILE(_LT_NIST_2)) {
    INTERESTING(lDebug ? "NIST" : "MIT-style");
  }
  else if (INFILE(_LT_FSFAP)) {
    INTERESTING("FSFAP");
  }
  cleanLicenceBuffer();
  /*
   * Generic CopyLeft licenses
   */
  if (INFILE(_LT_COPYLEFT_1)) {
    INTERESTING("CopyLeft[1]");
  }
  else if (INFILE(_LT_COPYLEFT_2)) {
    INTERESTING("CopyLeft[2]");
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * jpeg/netpbm and png/zlib and others...
   */
  if (INFILE(_TITLE_ZLIB)) {
    INTERESTING("Zlib");
  }
  else if (INFILE (_LT_TRUECRYPT_30)) {
    INTERESTING("TrueCrypt-3.0");
  }
  else if (INFILE(_TITLE_LIBPNG)) {
    INTERESTING("Libpng");
  }
  /* IJG */
  else if (INFILE(_LT_JPEG_1)) {
    INTERESTING(lDebug ? "JPEG(1)" : "IJG");
    lmem[_fIJG] = 1;
  }
  else if (INFILE(_LT_JPEG_2) && HASTEXT(_TITLE_IJG_2, 0)) {
    INTERESTING(lDebug ? "JPEG(2)" : "IJG");
    lmem[_fIJG] = 1;
  }
  /* Zlib */
  else if (INFILE(_SPDX_zlib_acknowledgement)) {
    INTERESTING("zlib-acknowledgement");
  }
  else if (!lmem[_fREAL] && (INFILE(_SPDX_Zlib))) {
    INTERESTING("Zlib");
  }
  else if (INFILE(_LT_PNG_ZLIB_1) && HASTEXT(_LT_PNG_ZLIB_CLAUSE_1, 0) && HASTEXT(_LT_PNG_ZLIB_CLAUSE_2, REG_EXTENDED) && HASTEXT(_LT_PNG_ZLIB_CLAUSE_3, 0)) {
    INTERESTING(lDebug ? "ZLIB(1)" : "Zlib");
  }
  else if (INFILE(_LT_PNG_ZLIBref4) && NOT_INFILE(_LT_PNG_ZLIBref4_EXHIBIT)) {
    INTERESTING(lDebug ? "ZLIB(6)" : "Zlib");
  }
  else if (!lmem[_fW3C] && INFILE(_LT_PNG_ZLIB_2)) {
    if (INFILE(_LT_libmng_2007_Clause_1)) {
      if (HASTEXT(_LT_libmng_2007_1, 0)) {
        INTERESTING("libmng-2007");
      }
      else if (INFILE(_LT_libpng_20_Clause_1)) {
        INTERESTING("libpng-2.0");
      }
    }
    else {
      INTERESTING(lDebug ? "PNG/ZLIB(2)" : "Libpng");
    }
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
  else if (!LVAL(_TEXT_GNU_LIC_INFO) && (URL_INFILE(_URL_ZLIB_1) || URL_INFILE(_URL_ZLIB_2))) {
    INTERESTING(lDebug ? "ZLIB(url)" : "Zlib");
  }

  if (INFILE(_LT_INFO_ZIP) || URL_INFILE(_URL_INFO_ZIP)) {
    INTERESTING("Info-ZIP");
  }
  cleanLicenceBuffer();
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
    else if (INFILE(_LT_IETF_5)) {
      INTERESTING("IETF");
    }
    else if (HASTEXT(_LT_IETF_7, 0)) {
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
   * IETF Trust's Legal Provisions
   */
  else if (INFILE(_LT_IETF_6)) {
    INTERESTING("IETF");
  }
  /*
   * Contributions to IETF Standard Process
   */
  else if (INFILE(_LT_IETF_7)) {
    INTERESTING("IETF-contribution");
  }
  cleanLicenceBuffer();
  /*
   * CDDL
   */
  if (INFILE(_PHR_CDDL_1) || HASTEXT(_PHR_CDDL_2, REG_EXTENDED)) {
      cp = CDDLVERS();
      INTERESTING(cp);
      lmem[_mCDDL] = 1;
  }
  cleanLicenceBuffer();
  /*
   * MPL (Mozilla)
   * ... Sun SISSL and one Mozilla licensing derivative share wording
   */
  if (!lmem[_fREAL] && NOT_INFILE(_LT_CPALref) && NOT_INFILE(_TITLE_GSOAP) &&
      (INFILE(_LT_MPL_OR) || INFILE(_TITLE_MPL_ref))) {
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
  else if (INFILE(_LT_CPAL_V1_ref)) {
    INTERESTING(lDebug ? "CPAL_v1.0(#3)" : "CPAL-1.0");
    lmem[_mMPL] = 1;
    lmem[_fATTRIB] = 1;
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
      INTERESTING("Interbase-1.0");
      lmem[_mMPL] = 1;
    }
    else if (INFILE(_TITLE_NETIZEN_V10)) {
      INTERESTING("NOSL-1.0");
      lmem[_mMPL] = 1;
    }
    else if (INFILE(_TITLE_NETIZEN)) {
      INTERESTING(lDebug ? "NOSL(#1)" : "NOSL");
      lmem[_mMPL] = 1;
    }
    else if (INFILE(_TITLE_TPL10)) {
      INTERESTING(lDebug ? "TPL(v1.0#1)" : "MPL.TPL-1.0");
      lmem[_mMPL] = 1;
    }
    else if (INFILE(_TITLE_TPL)) {
      INTERESTING(lDebug ? "TPL(#1)" : "MPL.TPL");
      lmem[_mMPL] = 1;
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
    else if (INFILE(_TITLE_OPENPL10)) {
      INTERESTING("OPL-1.0");
    }
    else if (INFILE(_TITLE_SNIA_V11)) {
      INTERESTING("SNIA-1.1");
    }
    else if (INFILE(_TITLE_SNIA_V10)) {
      INTERESTING("SNIA-1.0");
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
      else if (!lmem[_mMPL] && !lmem[_fREAL] && INFILE(_TITLE_MOZNET_PL)) {
        cp = MPLVERS(); /* NPL, too */
        INTERESTING(lDebug ? "MPL/NPL#1" : cp);
        lmem[_mMPL] = 1;
      }
    }
    else if (INFILE(_TITLE_RHeCos_V11)) {
      INTERESTING("RHeCos-1.1");
    }
    else if (INFILE(_TITLE_CYGNUS_ECOS_V10)) {
      INTERESTING("Cygnus-eCos-1.0");
    }
    else if (INFILE(_TITLE_H2_V10)) {
      INTERESTING("H2-1.0");
    }
    else {
      if (!lmem[_mCDDL]) {
        INTERESTING("MPL-style");
        lmem[_mMPL] = 1;
      }
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
  else if (!lmem[_mMPL] && !lmem[_mLIBRE] && !lmem[_fREAL] &&
      !LVAL(_TEXT_GNU_LIC_INFO) && INFILE(_LT_MPLref2)) {
    cp = MPLVERS(); /* NPL, too */
    INTERESTING(lDebug ? "MPL/NPL-ref#2" : cp);
    lmem[_mMPL] = 1;
  }
  else if (!lmem[_mMPL] && !lmem[_mLIBRE] && !lmem[_fREAL] &&
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
  else if (INFILE(_TITLE_OPENPL)) {
    INTERESTING(lDebug ? "OPL(title)" : "OPL-style");
  }
  cleanLicenceBuffer();
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
  else if (INFILE(_LT_MSCORP_LIMITEDref1)) {
    INTERESTING("MS-LPL");
    lmem[_fMSCORP] = 1;
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
    else if (INFILE(_LT_MSCORP_PLref3)) {
      INTERESTING(lDebug ? "MS-PL(ref3)" : "MS-PL");
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
    else if (INFILE(_LT_MSCORP_EULA_6)) {
      INTERESTING(lDebug ? "MS-EULA(6)" : "MS-EULA");
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
  cleanLicenceBuffer();
  /*
   * Santa Cruz Operation (SCO)
   */
  if (INFILE(_LT_SCO_COMM) && INFILE(_CR_SCO)) {
    INTERESTING("SCO.commercial");
  }
  cleanLicenceBuffer();
  /*
   * Zonealarm
   */
  if (INFILE(_LT_ZONEALARM) && INFILE(_TITLE_ZONEALARM_EULA)) {
    INTERESTING("ZoneAlarm-EULA");
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * Artifex Software
   */
  if (INFILE(_LT_ARTIFEX) && INFILE(_CR_ARTIFEX)) {
    INTERESTING("Artifex");
  }
  cleanLicenceBuffer();
  /*
   * AGE logic
   */
  if (INFILE(_LT_AGE) && INFILE(_CR_AGE)) {
    INTERESTING("AGE-Logic");
  }
  cleanLicenceBuffer();
  /*
   * OpenSSL
   */
  if (INFILE(_LT_OPENSSLref1) || INFILE(_LT_OPENSSLref2) ||
      INFILE(_LT_OPENSSLref3) || INFILE(_LT_OPENSSLref4) ||
      INFILE(_LT_OPENSSLref6) || INFILE(_LT_OPENSSLref7) ||
      INFILE(_LT_OPENSSLref8) ) {
    INTERESTING(lDebug ? "OpenSSL(ref)" : "OpenSSL");
  }
  else if (INFILE(_SPDX_OpenSSL) || INFILE(_URL_OPENSSL)) {
    INTERESTING(lDebug ? "OpenSSL(phr)" : "OpenSSL");
  }
  cleanLicenceBuffer();
  /*
   * Dual OpenSSL SSLeay
   */
  if (INFILE(_LT_COMBINED_OPENSSL_SSLEAY)) {
    INTERESTING("OpenSSL");
    INTERESTING("SSLeay");
  }
  cleanLicenceBuffer();
  /*
   * Ruby. Ruby is a dual license which allows distribution also under GPL.
   * GPL was earlier recognized beside Ruby here but GPL was not identified
   * in all Ruby cases. Therefore GPL statements have bee removed.
   */
  if (INFILE(_LT_RUBY)) {
    INTERESTING("Ruby");
    lmem[_fRUBY] = 1;
  }
  else if (INFILE(_LT_RUBYref1)) {
    INTERESTING(lDebug ? "Ruby(ref1)" : "Ruby");
  }
  else if (INFILE(_LT_RUBYref2)) {
    INTERESTING(lDebug ? "Ruby(ref2)" : "Ruby");
  }
  else if (INFILE(_LT_RUBYref3)) {
    INTERESTING(lDebug ? "Ruby(ref3)" : "Ruby");
  }
  else if (INFILE(_LT_RUBYref4)) {
    INTERESTING(lDebug ? "Ruby(ref4)" : "Ruby");
  }
  else if (INFILE(_LT_RUBYref5)) {
    INTERESTING(lDebug ? "Ruby(ref5)" : "Ruby");
  }
  else if (INFILE(_LT_RUBYref6)) {
    INTERESTING(lDebug ? "Ruby(ref6)" : "Ruby");
  }
  cleanLicenceBuffer();
  /*
   * Python and EGenix.com look a bit alike
   * Q: should all these Python rhecks be a family-check like OpenLDAP?
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
        lmem[_mGPL] = 1;
      }
      else if (INFILE(_CR_PYTHON) || INFILE(_TITLE_PYTHON)) {
        cp = PYTHVERS();
        INTERESTING(lDebug ? "Python(1)" : cp);
      }
      else if (INFILE(_LT_CNRI_PYTHON_1)) {
        INTERESTING("CNRI-Python");
      }
      else if (INFILE(_LT_CNRI_JYTHON)) {
        INTERESTING("CNRI-Jython");
      }
      else {
        INTERESTING("Python-style");
      }
      lmem[_mPYTHON] = 1;
    }
    else if (INFILE(_SPDX_CNRI_Python_GPL_Compatible)) {
      INTERESTING("CNRI-Python-GPL-Compatible");
    }
    else if (INFILE(_SPDX_CNRI_Python)) {
      INTERESTING("CNRI-Python");
    }
    else if (INFILE(_LT_CNRI_PYTHON_2)) {
      INTERESTING("CNRI-Python");
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
    else if (!lmem[_mPYTHON] && INFILE(_LT_PYTHON22ref)) {
      INTERESTING(lDebug ? "Python(22ref)" : "Python-2.2");
      lmem[_mPYTHON] = 1;
    }
  }
  cleanLicenceBuffer();
  /*
   * Intel
   */
  if (INFILE(_LT_INTEL) && NOT_INFILE(_LT_REAL_RPSL)) {
    INTERESTING(lDebug ? "Intel(8)" : "Intel");
  }
  else if (INFILE(_LT_INTEL_6)) {
    INTERESTING(lDebug ? "Intel(9)" : "Intel-other");
  }
  else if (INFILE(_LT_INTEL_7)) {
    INTERESTING(lDebug ? "Intel(10)" : "Intel-other");
  }
  else if (INFILE(_LT_INTEL_WLAN)) {
    INTERESTING("Intel-WLAN");
  }
  else if (INFILE(_LT_INTEL_ACPI)) {
    INTERESTING("Intel-ACPI");
  }
  else if (INFILE(_SPDX_Intel_ACPI)) {
    INTERESTING("Intel-ACPI");
  }
  else if (INFILE(_LT_ISSL_1) && INFILE(_LT_ISSL_2)) {
    INTERESTING("ISSL");
  }
  else if (!lmem[_fREAL] && INFILE(_SPDX_Intel)) {
    INTERESTING("Intel");
  }
  else if (HASTEXT(_TEXT_INTELCORP, 0)) {
    if (INFILE(_LT_INTEL_1)) {
      if (INFILE(_LT_INTEL_FW)) {
        INTERESTING(lDebug ? "Intel(2)" : "Intel-only-FW");
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
      INTERESTING("Intel.RESTRICTED");
    }
    else if (INFILE(_LT_INTEL_BINARY) && HASTEXT(_TEXT_INTELCORPBINARY, 0) && HASTEXT(_TEXT_NOMODIFICATION, 0)) {
      INTERESTING("Intel-Binary");
    }
  }
  else if (INFILE(_LT_INTEL_5)) {
    INTERESTING(lDebug ? "CPL(Intel)" : "CPL");
    INTERESTING(lDebug ? "Intel(7)" : "Intel");
  }
  else if (INFILE(_LT_INTEL_EULA)) {
    INTERESTING("Intel-EULA");
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * Purdue
   */
  if (INFILE(_LT_PURDUE) && INFILE(_CR_PURDUE) && HASTEXT(_TEXT_ALTERED_SOURCE, REG_EXTENDED) && HASTEXT(_TEXT_ORIGIN, 0)) {
    INTERESTING("Purdue");
    /* flag is set to avoid Cisco-style detection */
    lmem[_fPURDUE] = 1;
  }
  cleanLicenceBuffer();
  /*
   * Cisco systems
   */
  if (!lmem[_fPURDUE] && INFILE(_LT_CISCO)) {
    if (HASTEXT(_LT_PNG_ZLIB_CLAUSE_1, 0) &&
        HASTEXT(_LT_PNG_ZLIB_CLAUSE_2, REG_EXTENDED) &&
        HASTEXT(_LT_PNG_ZLIB_CLAUSE_3, 0)) {
      if (INFILE(_LT_Spencer_86_94_CLAUSE_1) && INFILE(_LT_Spencer_94_CLAUSE_2)) {
        INTERESTING("Spencer-94");
      }
      else {
        INTERESTING("Zlib-style");
      }
    }
    else if (INFILE(_CR_CISCO)) {
      INTERESTING("Cisco");
    }
    else {
      INTERESTING("Cisco-style");
    }
  }
  cleanLicenceBuffer();
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
    else if (INFILE(_LT_GNUPLOT_1) && INFILE(_LT_GNUPLOT_2)) {
      INTERESTING("gnuplot");
    }
    else {
      INTERESTING(lDebug ? "HP-DEC-style(1)" : "HP-DEC-style");
    }
  }
  else if (HASTEXT(_TEXT_HP, REG_EXTENDED)) {
    if (INFILE(_LT_HP_1)) {
      INTERESTING(lDebug ? "HP(2)" : "HP");
    }
    else if (INFILE(_LT_HP_3) && INFILE(_LT_HP_snmp_pp)) {
      INTERESTING(lDebug ? "HP(3)" : "hp-snmp-pp");
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
  cleanLicenceBuffer();
  /*
   * SUN Microsystems
   */
  if (!lmem[_mSUN] && (INFILE(_CR_SUN) || INFILE(_TEXT_MICROSYSTEMS))) {
    if (INFILE(_LT_SUN_PROPRIETARY) || INFILE(_LT_SUN_PROPRIETARY_2) || INFILE(_LT_SUN_PROPRIETARY_3)) {
      INTERESTING(lDebug ? "Sun(Prop)" : "Sun-Proprietary");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_RPC)) {
      INTERESTING("Sun-RPC");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_1)) {
      if (INFILE(_LT_SUN_FREE)) {
        INTERESTING(lDebug ? "Sun(Freeware)" : "Freeware");
      }
      else {
        INTERESTING(lDebug ? "Sun(3)" : "Sun");
      }
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_2)) {
      INTERESTING(lDebug ? "Sun(4)" : "Sun-Proprietary");
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
      INTERESTING(lDebug ? "Sun(7)" : "Freeware");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_6)) {
      INTERESTING(lDebug ? "Sun(8)" : "BSD-style");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUN_NC)) {
      INTERESTING("Sun.Non-commercial");
      lmem[_mSUN] = 1;
    }
    else if (INFILE(_LT_SUNrestrict)) {
      INTERESTING("Sun.RESTRICTED");
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
  }
  else if (!lmem[_fREAL] && INFILE(_LT_SUN_PLref)) {
    INTERESTING(lDebug ? "Sun-PL(ref)" : "SPL");
    lmem[_mSUN] = 1;
  }
  else if (!lmem[_mSUN] && URL_INFILE(_URL_SUN_BINARY_V150)) {
    INTERESTING("Sun-BCLA-1.5.0");
    lmem[_mSUN] = 1;
  }
  else if (!lmem[_mSUN] && URL_INFILE(_URL_SUN_BINARY)) {
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
  cleanLicenceBuffer();
  if (INFILE(_LT_SUN_PRO)) {
    INTERESTING(lDebug ? "SunPro" : "Freeware");
  }
  cleanLicenceBuffer();
  /*
   * IBM
   */
  if (INFILE(_TEXT_IBM)) {
    if (INFILE(_LT_IBM_RECIP)) {
      INTERESTING("IBM-reciprocal");
    }
    else if (INFILE(_LT_IBM_4)) {
      INTERESTING(lDebug ? "IBM(4)" : "IBM-dhcp");
    }
    else if (INFILE(_LT_IBM_1)) {
      INTERESTING(lDebug ? "IBM(1)" : "MIT-style");
    }
    else if (INFILE(_LT_IBM_3)) {
      INTERESTING(lDebug ? "IBM(3)" : "MIT-style");
    }
    else if (INFILE(_LT_IBM_2)) {
      INTERESTING(lDebug ? "IBM(2)" : "IBM");
    }
    else if (INFILE(_LT_IBM_OWNER)) {
      INTERESTING(lDebug ? "IBM(4)" : "IBM");
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
    else if (INFILE(_LT_IBM_PIBS)) {
      INTERESTING("IBM-pibs");
    }
    else if (INFILE(_LT_IBM_AS_IS)) {
      INTERESTING("IBM-as-is");
    }
  }
  cleanLicenceBuffer();
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
  if (HASTEXT(_TITLE_MOTOROLA_MOBILE, 0)) {
    INTERESTING("Motorola-Mobile-SLA");
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * Comtrol Corp
   */
  if (INFILE(_CR_COMTROL) && INFILE(_LT_COMTROL)) {
    INTERESTING("Comtrol");
  }
  cleanLicenceBuffer();
  /*
   * TrollTech
   */
  if (INFILE(_LT_TROLLTECH) && INFILE(_CR_TROLLTECH)) {
    INTERESTING("Trolltech");
  }
  else if (INFILE(_LT_QT_COMMref)) {
    INTERESTING("Qt.Commercial");
  }
  else if (INFILE(_LT_QT_PROPRIETARY) || INFILE(_TITLE_QT_PROPRIETARY)) {
    INTERESTING("Qt.Commercial");
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * ADOBE/FRAME
   */
  if (HASTEXT(_TEXT_ADOBE_FRAME, REG_EXTENDED)) {
    if (INFILE(_LT_ADOBE_1)) {
      INTERESTING(lDebug ? "Adobe(1)" : "Adobe");
    }
    else if (!lmem[_mMIT] && INFILE(_LT_ADOBE_2)) {
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
      INTERESTING(lDebug ? "Adobe(src)" : "Adobe-2006");
    }
    else if (INFILE(_LT_ADOBE_DATA)) {
      INTERESTING(lDebug ? "Adobe(data)" : "Adobe");
    }
    else if (INFILE(_LT_ADOBE_EULA)) {
      INTERESTING("Adobe-EULA");
    }
    else if (INFILE(_LT_ADOBE_AFM)) {
      INTERESTING("APAFML");
    }
    else if (HASTEXT(_TITLE_ADOBE_DNG, 0)) {
      INTERESTING("Adobe-DNG");
    }
    else if (INFILE(_LT_ADOBE_AFMPARSE_1) && INFILE(_LT_ADOBE_AFMPARSE_2)) {
      INTERESTING("Afmparse");
    }
    else if (INFILE(_LT_ADOBE_OTHER)) {
      INTERESTING(lDebug ? "Adobe(other)" : "Adobe");
    }
    else if (INFILE(_LT_ADOBE_SUB)) {
      INTERESTING(lDebug ? "Adobe(sub)" : "Adobe");
    }
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * MP3 decoder
   */
  if (INFILE(_LT_MPEG3)) {
    INTERESTING("MPEG3-decoder");
  }
  cleanLicenceBuffer();
  /*
   * Google
   */
  if (INFILE(_LT_GOOGLE_1)) {
    INTERESTING(lDebug ? "Google(1)" : "Google");
  }
  else if (INFILE(_LT_GOOGLE_2)) {
    INTERESTING(lDebug ? "Google(2)" : "Google");
  }
  cleanLicenceBuffer();
  /*
   * Mandriva
   */
  if (INFILE(_LT_MANDRIVA)) {
    INTERESTING("Mandriva");
  }
  cleanLicenceBuffer();
  /*
   * Irondoc
   */
  if (INFILE(_LT_IRONDOC)) {
    INTERESTING("IronDoc");
  }
  cleanLicenceBuffer();
  /*
   * Quarterdeck Office Systems
   */
  if (INFILE(_LT_QUARTERDECK) && INFILE(_CR_QUARTERDECK)) {
    INTERESTING("QuarterDeck");
  }
  cleanLicenceBuffer();
  /*
   * Electronic Book Technologies
   */
  if (INFILE(_LT_EBT)) {
    if(INFILE(_CR_EBT)) {
      INTERESTING("EBT");
    }    else {
      INTERESTING("EBT-style");
    }
  }
  cleanLicenceBuffer();
  /*
   * SGML
   */
  if (HASTEXT(_TEXT_SGMLUG, 0) && INFILE(_LT_SGML)) {
    INTERESTING("SGML");
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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
          INTERESTING(lDebug ? "Aladdin(Closed-Source!)" : "Aladdin");
        if (INFILE(_PHR_NOT_OPEN)) {
          INTERESTING(lDebug ? "Aladdin(Closed-Source!)" : "Aladdin");
          lmem[_mALADDIN] = 1;
        }
        else {
          INTERESTING(lDebug ? "Aladdin-Ghostscript" : "Aladdin");
        }
      }
      else if (INFILE(_LT_ALADDIN_RESTRICT)) {
        INTERESTING(lDebug ? "Aladdin(RESTRICTED)": "Aladdin");
      }
    }
    else if (INFILE(_LT_AFPL)) {
      INTERESTING("AFPL-Ghostscript");
    }
  }
  else if (INFILE(_LT_FREEPLref_1)) {
    INTERESTING("Aladdin");
  }
  else if (INFILE(_LT_FREEPL) || INFILE(_LT_FREEPLref)) {
    INTERESTING("Free-PL");
  }
  /*
   * IPTC (International Press Telecommunications Council)
   */
  else if (INFILE(_TITLE_IPTC) || INFILE(_LT_IPTC_2)) {
    INTERESTING("IPTC");
  }
  else if (INFILE(_LT_IPTC_1) && mCR_IPTC()) {
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
  cleanLicenceBuffer();
  /*
   * Ascender
   */
  if (INFILE(_LT_ASCENDER_EULA) && INFILE(_TITLE_ASCENDER_EULA)) {
    INTERESTING("Ascender-EULA");
  }
  cleanLicenceBuffer();
  /*
   * ADAPTEC
   */
  if (INFILE(_LT_ADAPTEC_OBJ)) {
    INTERESTING("Adaptec.RESTRICTED");
  }
  else if (INFILE(_CR_ADAPTEC) && INFILE(_LT_ADAPTEC_GPL)) {
    INTERESTING("Adaptec-GPL");
  }
  cleanLicenceBuffer();
  /*
   * Artistic and Perl
   */
  if (INFILE(_LT_PERL_1)) {
    INTERESTING(lDebug ? "Artistic(Perl#1)" : "Artistic-1.0-Perl");
    if (!lmem[_mGPL]) {
      INTERESTING("Dual-license");
      INTERESTING(lDebug ? "GPL(Perl#1)" : "GPL-1.0-or-later");
    }
  }
  else if (INFILE(_LT_PERL_2)) {
    INTERESTING(lDebug ? "Artistic(Perl#2)" : "Artistic-1.0-Perl");
    if (!lmem[_mGPL]) {
      INTERESTING("Dual-license");
      INTERESTING(lDebug ? "GPL(Perl#2)" : "GPL-1.0-or-later");
    }
  }
  else if (INFILE(_LT_PERL_3)) {
    if (INFILE(_LT_Spencer_86_94_CLAUSE_1) &&
        HASTEXT(_LT_PNG_ZLIB_CLAUSE_1, 0) &&
        HASTEXT(_LT_PNG_ZLIB_CLAUSE_2, REG_EXTENDED)) {
      INTERESTING("Spencer-86");
    }
    else if (!lmem[_fOPENLDAP] && !TRYGROUP(famOPENLDAP)) {
      INTERESTING(lDebug ? "Artistic(Perl#3)" : "Artistic-1.0");
    }
  }
  /*
   * Licensed "same as perl itself" will actually be Artistic AND GPL, per
   * Larry Wall and the documented licensing terms of "perl"
   */
  else if (INFILE(_LT_PERLref1)) {
    INTERESTING(lDebug ? "Artistic(Perl-ref1)" : "Artistic-1.0-Perl");
    if (!lmem[_mGPL]) {
      INTERESTING("Dual-license");
      INTERESTING(lDebug ? "GPL(Perl-ref1)" : "GPL-1.0-or-later");
    }
  }
  else if (PERL_INFILE(_LT_PERLref2)) {
    INTERESTING(lDebug ? "Artistic(Perl-ref2)" : "Artistic-1.0-Perl");
    if (!lmem[_mGPL]) {
      INTERESTING("Dual-license");
      INTERESTING(lDebug ? "GPL(Perl-ref2)" : "GPL-1.0-or-later");
    }
  }
  else if (INFILE(_LT_PERLref3)) {
    INTERESTING(lDebug ? "Artistic(Perl-ref3)" : "Artistic-1.0-Perl");
    if (!lmem[_mGPL]) {
      INTERESTING("Dual-license");
      INTERESTING(lDebug ? "GPL(Perl-ref3)" : "GPL-1.0-or-later");
    }
  }
  else if (INFILE(_LT_PERLref4)) {
    INTERESTING(lDebug ? "Artistic(Perl-ref4)" : "Artistic-1.0-Perl");
    if (!lmem[_mGPL]) {
      INTERESTING("Dual-license");
      INTERESTING(lDebug ? "GPL(Perl-ref4)" : "GPL-1.0-or-later");
    }
  }
  else if (INFILE(_LT_PERLref5)) {
    INTERESTING(lDebug ? "Artistic(Perl-ref5)" : "Artistic-1.0-Perl");
    if (!lmem[_mGPL]) {
      INTERESTING("Dual-license");
      INTERESTING(lDebug ? "GPL(Perl-ref5)" : "GPL-1.0-or-later");
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
      else if (INFILE(_PHR_Artistic_20)) {
        INTERESTING("Artistic-2.0");
      }
      else if (INFILE(_TITLE_NBPL_V10)) {
        INTERESTING("NBPL-1.0");
        lmem[_fARTISTIC] = 1;
      }
      else if(HASTEXT(_PHR_ARTISTIC_PERL, 0)) {
        INTERESTING("Artistic-1.0-Perl");
        lmem[_fARTISTIC] = 1;
      }
      else if (HASTEXT(_PHR_ARTISTIC_CLAUSE8, 0))
      {
        INTERESTING("Artistic-1.0-cl8");
        lmem[_fARTISTIC] = 1;
      }
      else {
        INTERESTING(lDebug ? "Artistic(v1.0#other)" : "Artistic-1.0");
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
  cleanLicenceBuffer();
  /*
   * LDP, Manpages, OASIS, GPDL, Linux-HOWTO and Linux-doc
   */
  if (INFILE(_TITLE_LDPL20)) {
    INTERESTING("LDPL-2.0");
  }
  else if (INFILE(_TITLE_LDPL1A)) {
    INTERESTING("LDPL-1A");
  }
  else if (INFILE(_LT_LDPL)) {
    INTERESTING(lDebug ? "LDPL(1)" : "LDPL");
  }
  else if (INFILE(_LT_LDPLref1)) {
    INTERESTING(lDebug ? "LDPL(ref1)" : "LDPL");
  }
  else if (INFILE(_LT_LDPLref2)) {
    INTERESTING(lDebug ? "LDPL(ref2)" : "LDPL");
  }
  else if (INFILE(_LT_LDPLref3)) {
    INTERESTING(lDebug ? "LDPL(ref3)" : "LDPL");
  }
  /*
   * GNU-Manpages, Software in the Public Interest (Debian), aka SPI
   */
  else if (INFILE(_LT_SPI)) {
    if (INFILE(_LT_MANPAGE)) {
      INTERESTING("GNU-Manpages");
    }
    else  if (!lmem[_fGPL]) {
      if (INFILE(_CR_SPI)) {
        INTERESTING("Debian-SPI");
      }
      else {
        INTERESTING("Debian-SPI-style");
      }
    }
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
  cleanLicenceBuffer();
  /*
   * U-Washington
   */
  if (INFILE(_LT_UW1)) {
    if (INFILE(_CR_UWASHINGTON)) {
      INTERESTING("U-Wash.Free-Fork");
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
  cleanLicenceBuffer();
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
  else if (INFILE(_LT_PRINCETON) && NOT_INFILE(_TITLE_WORDNET)) {
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
      INTERESTING("USC.Non-commercial");
    }
    else {
      INTERESTING(lDebug ? "NonC(5)" : "Non-commercial");
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
  else if (INFILE(_LT_UCAR_1) && INFILE(_LT_UCAR_2)) {
    INTERESTING("UCAR");
  }
  else if (INFILE(_LT_UCAR_3) && INFILE(_CR_UCAR)) {
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
   * U-Cambridge
   */
  else if (INFILE(_LT_CAMBRIDGE)) {
    if (HASTEXT(_LT_MSNTP, 0)) {
      INTERESTING("MSNTP");
    }
    else if (INFILE(_CR_CAMBRIDGE_1) || INFILE(_CR_CAMBRIDGE_2)) {
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
  cleanLicenceBuffer();
  /*
   * Boost references
   */
  if (!lmem[_mMIT] && INFILE(_LT_BOOST_2)) {
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
    INTERESTING("Sleepycat.Non-commercial");
  }
  cleanLicenceBuffer();
  /*
   * Vim license
   */
  if ((INFILE(_LT_Vim_1) || INFILE(_LT_Vim_2)) && INFILE(_TITLE_Vim)) {
    INTERESTING("Vim");
  }
  else if (INFILE(_PHR_Vim)) {
    INTERESTING("Vim");
  }
  cleanLicenceBuffer();
  /*
   * Vixie license
   */
  if (INFILE(_LT_VIXIE)) {
    INTERESTING("Vixie-license");
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * Apple
   */
  if (INFILE(_TEXT_APPLE)) {
    if (INFILE(_LT_MIT_12)) {
      INTERESTING(lDebug ? "Apple MIT License" : "AML");
    }
    else if (INFILE(_LT_APPLE_1)) {
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
      INTERESTING("Apple.FontForge");
    }
    else if (INFILE(_LT_APPLE_SAMPLE)) {
      INTERESTING("Apple.Sample");
    }
    else if (INFILE(_LT_APSLref1) || INFILE(_LT_APSLref2) ||
        INFILE(_TITLE_APSL)) {
      if (INFILE(_TITLE_APSL20)) {
        INTERESTING("APSL-2.0");
      }
      else if (INFILE(_TITLE_Ferris)) {
        INTERESTING(lDebug ? "Ferris-1.2" : "APSL-style");
      }
      else if (INFILE(_TITLE_APSL_style)) {
        INTERESTING("APSL-style");
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
  cleanLicenceBuffer();
  /*
   * Redland
   */
  if (INFILE(_LT_REDLAND)) {
    INTERESTING("Redland");
  }
  cleanLicenceBuffer();
  /*
   * Red Hat and Fedora
   */
  if (INFILE(_LT_RH_PKGS)) {
    if (INFILE(_LT_RH_NONCOMMERCIAL)) {
      INTERESTING(lDebug ? "RH(NC)" : "RedHat.Non-commercial");
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
    INTERESTING("FedoraCLA");
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
  cleanLicenceBuffer();
  /*
   * SUSE/Novell/UnitedLinux
   */
  if (INFILE(_CR_SUSE) && INFILE(_PHR_YAST_CR)) {
    INTERESTING("YaST.SuSE");
  }
  else if (INFILE(_TITLE_NOVELL_EULA)) {
    INTERESTING("Novell-EULA");
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
  cleanLicenceBuffer();
  /*
   * Epson Public license
   */
  if (INFILE(_LT_EPSON_PL) && INFILE(_TITLE_EPSON_PL)) {
    INTERESTING("Epson-PL");
  }
  else if (INFILE(_LT_EPSON_EULA) && INFILE(_TITLE_EPSON_EULA)) {
    INTERESTING("Epson-EULA");
  }
  cleanLicenceBuffer();
  /*
   * Open Publication license
   */
  if (INFILE(_LT_OPENPUBL_1) || INFILE(_LT_OPENPUBL_2)) {
    if (INFILE(_TITLE_OPENPUBL04)) {
      INTERESTING("Open-PL-0.4");
    }
    else if (INFILE(_TITLE_OPENPUBL10) || URL_INFILE(_URL_OPEN_PL_V10)) {
      INTERESTING("Open-PL-1.0");
    }
    else if (INFILE(_TITLE_OPENPUBL)) {
      INTERESTING("Open-PL");
    }
    else {
      INTERESTING("Open-PL-style");
    }
  }
  else if (INFILE(_LT_OPENPUBLref)) {
    INTERESTING(lDebug ? "Open-PL(ref)" : "Open-PL");
  }
  cleanLicenceBuffer();
  /*
   * Free Art License
   */
  if (INFILE(_LT_FREEART_V10)) {
    INTERESTING("FAL-1.0");
  }
  else if (INFILE(_LT_FREEART_V13)) {
    INTERESTING("FAL-1.3");
  }
  cleanLicenceBuffer();
  /*
   * RSA Security, Inc.
   */
  if (INFILE(_LT_RSA_4)) {
    INTERESTING(lDebug ? "RSA(4)" : "RSA-MD");
  }
  else if (INFILE(_LT_RSA_5)) {
    INTERESTING(lDebug ? "RSA(5)" : "RSA-DNS");
  }
  else if (INFILE (_LT_RSA_CRYPTOKI_1) && INFILE(_LT_RSA_CRYPTOKI_2)){
    INTERESTING("RSA-Cryptoki");
  }
  else if (INFILE(_LT_RSA_3)) {
    INTERESTING(lDebug ? "RSA(3)" : "RSA-Security");
  }
  else if (INFILE(_CR_RSA)) {
    if (INFILE(_LT_RSA_1)) {
      INTERESTING(lDebug ? "RSA(1)" : "RSA-Security");
    }
    else if (INFILE(_LT_RSA_2)) {
      INTERESTING(lDebug ? "RSA(2)" : "RSA-Security");
    }
  }
  cleanLicenceBuffer();
  /* Some licenses only deal with fonts */
  if (HASTEXT(_TEXT_FONT, 0)) {
    /*
     * AGFA Monotype
     */
    if (INFILE(_LT_AGFA)) {
      INTERESTING("AGFA.RESTRICTED");
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
    cleanLicenceBuffer();
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
    cleanLicenceBuffer();
    /*
     * BITSTREAM
     */
    if (INFILE(_LT_BITSTREAM_1)) {
      INTERESTING(lDebug ? "Bitstream(2)" : "Bitstream");
    }
    else if (INFILE(_LT_BITSTREAM_2)) {
      INTERESTING(lDebug ? "Bitstream(3)" : "Bitstream");
    }
    cleanLicenceBuffer();
    /*
     * Larabie Fonts
     */
    if (INFILE(_LT_LARABIE_EULA) && INFILE(_TITLE_LARABIE_EULA)) {
      INTERESTING("Larabie-EULA");
    }
    cleanLicenceBuffer();
    /*
     * Baekmuk Fonts and Hwan Design
     */
    if (INFILE(_LT_BAEKMUK_1)) {
      INTERESTING("Baekmuk-Font");
    }
    else if (INFILE(_LT_BAEKMUK_2)) {
      INTERESTING("Baekmuk.Hwan");
    }
    cleanLicenceBuffer();
    /*
     * Information-Technology Promotion Agency (IPA)
     */
    if (INFILE(_LT_IPA_EULA)) {
      INTERESTING("IPA-Font-EULA");
    }
    cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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
    else if (INFILE(_CR_ATT) || INFILE(_CR_LUCENT)) {
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
    INTERESTING("ATT.Non-commercial");
  }
  cleanLicenceBuffer();
  /*
   * Silicon Graphics
   */
  if (INFILE(_TITLE_SGI_V10)) {
    INTERESTING("SGI-B-1.0");
  }
  else if (INFILE(_TITLE_SGI_V11)) {
    INTERESTING("SGI-B-1.1");
  }
  else if (INFILE(_TITLE_SGI_V20)) {
    INTERESTING("SGI-B-2.0");
  }
  else if (INFILE(_LT_SGI_1)) {
    if (HASTEXT(_PHR_SGI_LIBTIFF, REG_EXTENDED)) {
      INTERESTING("libtiff");
    }
    else if (HASTEXT(_PHR_LIBTIFF_STYLE, REG_EXTENDED)) {
      INTERESTING("libtiff-style");
    }
  }
  else if (INFILE(_LT_SGI_2)) {
    if (INFILE(_LT_SGI_V10)) {
      INTERESTING("SGI-B-1.0");
    }
    else if (INFILE(_LT_SGI_V11)) {
      INTERESTING("SGI-B-1.1");
    }
    else if (INFILE(_LT_SGI_V20)) {
      INTERESTING("SGI-B-2.0");
    }
    else if (INFILE(_CR_SGI) || URL_INFILE(_URL_SGI)) {
      INTERESTING("SGI");
    }
  }
  else if (INFILE(_LT_SGI_1)) {
    if (INFILE(_CR_SGI) || URL_INFILE(_URL_SGI)) {
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
  cleanLicenceBuffer();
  /*
   * 3DFX (Glide)
   */
  if (INFILE(_CR_3DFX_1) || INFILE(_CR_3DFX_2)) {
    if (INFILE(_LT_GLIDE_3DFX)) {
      INTERESTING("Glide");
    }
    else if (INFILE(_LT_GLIDE_GPL)) {
      INTERESTING("3DFX-PL");
    }
  }
  /*
   * Nvidia Corp
   */
  else if (INFILE(_LT_NVIDIA_EULA_3)) {
    INTERESTING(lDebug ? "Nvidia(1)" : "Nvidia-EULA-a");
  }
  else if (INFILE(_CR_NVIDIA) && INFILE(_LT_NVIDIA)) {
    INTERESTING(lDebug ? "Nvidia(2)" : "Nvidia");
  }
  else if (INFILE(_LT_NVIDIA_EULA_2)) {
    INTERESTING(lDebug ? "Nvidia(3)" : "Nvidia-EULA-b");
  }
  else if (INFILE(_LT_NVIDIA_EULA_1) || INFILE(_TITLE_NVIDIA)) {
    INTERESTING(lDebug ? "Nvidia(4)" : "Nvidia-EULA-b");
  }
  else if (INFILE(_LT_NVIDIA_1)) {
    INTERESTING(lDebug ? "Nvidia(5)" : "Nvidia");
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
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * KDE
   */
  if (INFILE(_PHR_KDE_FILE) && INFILE(_LT_KDE)) {
    INTERESTING("KDE");
  }
  cleanLicenceBuffer();
  /*
   * Broadcom
   */
  if (INFILE(_LT_BROADCOM_EULA) && INFILE(_CR_BROADCOM)) {
    INTERESTING("Broadcom-EULA");
  }
  cleanLicenceBuffer();
  /*
   * DARPA (Defense Advanved Research Projects Agency)
   */
  if (INFILE(_LT_DARPA_COUGAAR_1)) {
    INTERESTING("DARPA-Cougaar");
  }
  else if (INFILE(_LT_DARPA)) {
    INTERESTING("DARPA");
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * Alliance for Open Media Patent License
   */
  if (INFILE(_LT_AOM_Patent)) {
    INTERESTING("Alliance for Open Media Patent License 1.0");
  }
  cleanLicenceBuffer();
  /*
   * Open Market, Inc
   */
  if (INFILE(_LT_CADENCE) && INFILE(_CR_CADENCE)) {
    INTERESTING("Cadence");
  }
  cleanLicenceBuffer();
  /*
   * Open Market, Inc
   */
  if (INFILE(_LT_OPENMKT)) {
    INTERESTING("OpenMarket");
  }
  cleanLicenceBuffer();
  /*
   * Unicode
   */
  if (!lmem[_fUNICODE]) {
    if (INFILE(_TITLE_UNICODE)) {
      INTERESTING(lDebug ? "Unicode(4)" : "Unicode");
    }
    else if (INFILE(_LT_UNICODE_1) && INFILE(_CR_UNICODE)) {
      INTERESTING(lDebug ? "Unicode(1)" : "Unicode");
    }
    else if (INFILE(_LT_UNICODE_2)) {
      INTERESTING(lDebug ? "Unicode(2)" : "Unicode");
    }
    else if (INFILE(_LT_UNICODE_3)) {
      INTERESTING(lDebug ? "Unicode(3)" : "Unicode");
    }
    else if (INFILE(_LT_UNICODE_4)) {
      INTERESTING(lDebug ? "Unicode(4)" : "Unicode-TOU");
    }
    else if (URL_INFILE(_URL_UNICODE)) {
      INTERESTING(lDebug ? "Unicode(5)" : "Unicode-TOU");
    }
    else if (INFILE(_TITLE_UNICODE_TOU) && INFILE(_LT_UNICODE_TOU)) {
      INTERESTING("Unicode-TOU");
    }
    cleanLicenceBuffer();
  }
  /*
   * Software Research Assoc
   */
  if (INFILE(_LT_SRA) && INFILE(_CR_SRA)) {
    INTERESTING("SW-Research");
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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
  else if (URL_INFILE(_URL_JABBER)) {
    INTERESTING(lDebug ? "Jabber(url)" : "Jabber");
  }
  cleanLicenceBuffer();
  /*
   * CPL, Lucent Public License, Eclipse PL
   */
  int _epl = 0;
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
    else if (INFILE(_TITLE_OpenGroup)) {
      INTERESTING("OpenGroup");
    }
    else if (INFILE(_TITLE_EPL10)) {
      INTERESTING(lDebug ? "Eclipse(v.0#1)" : "EPL-1.0");
      _epl = 1;
    }
    else if (INFILE(_TITLE_EPL20)) {
      INTERESTING(lDebug ? "Eclipse(v.2#1)" : "EPL-2.0");
      _epl = 1;
    }
    else if (INFILE(_TITLE_EPL) && NOT_INFILE(_TITLE_EPL_IGNORE)) {
      INTERESTING(lDebug ? "Eclipse(#1)" : "EPL");
      _epl = 1;
    }
    else if (INFILE(_TITLE_LUCENT102)) {
      INTERESTING("LPL-1.02");
    }
    else if (INFILE(_TITLE_LUCENT10)) {
      INTERESTING("LPL-1.0");
    }
    else if (NOT_INFILE(_LT_CA)) {
      cp = CPLVERS();
      INTERESTING(lDebug ? "CPL(#1)" : cp);
    }
  }
  else if (INFILE(_LT_LPL_102)) {
    INTERESTING("LPL-1.02");
  }
  else if (!lmem[_fREAL] && INFILE(_LT_CPLref1) && NOT_INFILE(_TITLE_EPL10)) {
    cp = CPLVERS();
    INTERESTING(lDebug ? "CPL(ref)" : cp);
  }
  else if (URL_INFILE(_URL_CPL)) {
    cp = CPLVERS();
    INTERESTING(lDebug ? "CPL(url)" : cp);
  }
  else if (INFILE(_PHR_CPL_05)) {
    INTERESTING(lDebug ? "CPL(0.5)" : "CPL-0.5");
  }
  else if (INFILE(_PHR_CPL_10)) {
    INTERESTING(lDebug ? "CPL(0.5)" : "CPL-1.0");
  }
  else if (INFILE(_LT_IBM_PLref1)) {
    INTERESTING(lDebug ? "IBM-PL(ref)" : "IPL");
  }
  else if (URL_INFILE(_URL_IBM_PL)) {
    INTERESTING(lDebug ? "IBM-PL(url)" : "IPL");
  }
  cleanLicenceBuffer();
  /* More EPL cases */
  if (!_epl) {
	  if (INFILE(_LT_EPL20ref_1)) {
	    INTERESTING(lDebug ? "Eclipse(ref#2)" : "EPL-2.0");
	  }
	  else if (INFILE(_LT_EPL10ref_1) || INFILE(_LT_EPL10ref_2) || HASTEXT(_LT_EPL10ref_3, REG_EXTENDED)) {
	    INTERESTING(lDebug ? "Eclipse(ref#2)" : "EPL-1.0");
	  }
    else if (INFILE(_LT_EPLref)) {
      if (INFILE(_TITLE_EPL10)) {
        INTERESTING(lDebug ? "Eclipse(v.0#2)" : "EPL-1.0");
      }
      else if (INFILE(_TITLE_EPL20)) {
        INTERESTING(lDebug ? "Eclipse(v.2#2)" : "EPL-2.0");
      }
      else {
        INTERESTING(lDebug ? "Eclipse(#2)" : "EPL");
      }
    }
    else if (INFILE(_LT_EPL10ref_1)) {
      INTERESTING(lDebug ? "Eclipse(ref#2)" : "EPL-1.0");
    }
    else if (INFILE(_LT_EPL) && NOT_INFILE(_TITLE_EPL_IGNORE)) {
      if (INFILE(_TITLE_EPL10ref_1)) {
        INTERESTING(lDebug ? "Eclipse(v1.0#2)" : "EPL-1.0");
      }
      if (INFILE(_TITLE_EPL20ref_1)) {
        INTERESTING(lDebug ? "Eclipse(v1.0#2)" : "EPL-2.0");
      }
    }
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * Ricoh
   */
  if (INFILE(_LT_RICOH)) {
    if (INFILE(_TITLE_RICOH10)) {
      INTERESTING("RSCPL");
    }
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * Educational Community License
   */
  if (INFILE(_LT_ECL1)) {
    INTERESTING("ECL-1.0");
    lmem[_mMIT] = 1;
  }
  else if (INFILE(_LT_ECL2)) {
    INTERESTING("ECL-2.0");
    lmem[_mMIT] = 1;
  }
  else if (INFILE(_LT_ECL)) {
    INTERESTING(lDebug ? "ECL(1)" : "ECL-1.0");
    lmem[_mMIT] = 1;
  }
  cleanLicenceBuffer();
  /*
   * EU DataGrid and Condor PL
   */
  if (INFILE(_LT_EU)) {
    if (INFILE(_TITLE_CONDOR_V10)) {
      INTERESTING("Condor-1.0");
    } else if (INFILE(_TITLE_CONDOR_V11)) {
      INTERESTING("Condor-1.1");
    }
    else {
      INTERESTING("EUDatagrid");
    }
  }
  else if (URL_INFILE(_URL_EUDatagrid)) {
    INTERESTING("EUDatagrid");
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * gSOAP Public License
   */
  if (!lmem[_mGSOAP] && INFILE(_LT_GSOAPref13)) {
    INTERESTING("gSOAP-1.3b");
  }
  else if (!lmem[_mGSOAP] && INFILE(_LT_GSOAPref)) {
    INTERESTING("gSOAP");
  }
  cleanLicenceBuffer();
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
  else if (INFILE(_TITLE_CA)) {
    INTERESTING("CATOSL");
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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
      INTERESTING("FTL");
    }
    else {
      INTERESTING("FTL-style");
    }
  }
  else if (INFILE(_LT_FTL)) {
      INTERESTING("FTL");
  }
  else if (INFILE(_TITLE_FTL)|| INFILE(_SPDX_FTL)) {
    INTERESTING("FTL");
  }
  else if (INFILE(_LT_CATHARON)) {
    INTERESTING(lDebug ? "Catharon(3)" : "Catharon");
  }
  else if (INFILE(_LT_FREETYPEref)) {
    INTERESTING(lDebug ? "FTL(ref)" : "FTL");
  }
  cleanLicenceBuffer();
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
  else if (INFILE(_LT_EIFFEL_20)) {
    INTERESTING("EFL-2.0");
  }
  else if (INFILE(_LT_EIFFEL_1)) {
    INTERESTING("EFL");
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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
  else if (INFILE(_TITLE_OSL21) && NOT_INFILE(_TITLE_OSL21_EXHIBIT)) {
    cp = OSLVERS();
    INTERESTING(lDebug? "OSL(T2.1)" : cp);
  }
  else if (INFILE(_TITLE_AFL21)) {
    cp = AFLVERS();
    INTERESTING(lDebug? "AFL(T2.1)" : cp);
  }
  else if (INFILE(_TITLE_OSL30) && NOT_INFILE(_TITLE_OSL30_EXHIBIT)) {
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
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * Open Government Licence
   */
  if (INFILE(_TITLE_OGL_UK)) {
    if (INFILE(_TITLE_OGL_UK_10)) {
      INTERESTING("OGL-UK-1.0");
    }
    else if (INFILE(_TITLE_OGL_UK_20)) {
      INTERESTING("OGL-UK-2.0");
    }
    else if (INFILE(_TITLE_OGL_UK_30)) {
      INTERESTING("OGL-UK-3.0");
    }
    /* Full OGL license texts have reference to Creative Commons */
    if (HASTEXT(_LT_CC_ref, REG_EXTENDED)) {
      lmem[_fCCBY] = 1;
    }
  }
  cleanLicenceBuffer();
  /*
   * Creative Commons Public License, Mindterm, and the Reciprocal PL
   */
  if (!lmem[_fCCBY] && HASTEXT(_LT_CC_ref, REG_EXTENDED)) {
    cp = CCVERS();
    INTERESTING(lDebug ? "CC(ref)" : cp);
  }
  else if (INFILE(_LT_CCPL)) {
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
  }
  else if (INFILE(_LT_RECIP15)) {
    INTERESTING("RPL-1.5");
  }
  else if (INFILE(_TITLE_MINDTERM)) {
    INTERESTING("MindTerm");
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
  /*
   * For some reason license text strings were defined for few
   * Creative Commons licenses.
   */
  else if (INFILE(_LT_CC_BY_SA_30)) {
    INTERESTING("CC-BY-SA-3.0");
  }
  else if (INFILE(_LT_CC_BY_SA_25)) {
    INTERESTING("CC-BY-SA-2.5");
  }
  else if (INFILE(_LT_CC_BY_NC_30)) {
    INTERESTING("CC-BY-NC-3.0");
  }
  else if (INFILE(_LT_CC_BY_ND_30)) {
    INTERESTING("CC-BY-ND-3.0");
  }
  cleanLicenceBuffer();
  if (URL_INFILE(_URL_RPL)) {
    INTERESTING(lDebug ? "RPL(url)" : "RPL");
  }
  else if (URL_INFILE(_URL_CCLGPL)) {
    cp = LGPLVERS();
    INTERESTING(lDebug ? "CC-LGPL(url)" : cp);
  }
  else if (URL_INFILE(_URL_CCGPL)) {
    cp = GPLVERS();
    INTERESTING(lDebug ? "CC-GPL(url)" : cp);
  }
  cleanLicenceBuffer();
  /*
   * SpikeSource
   */
  if (INFILE(_CR_SPIKESOURCE) && INFILE(_LT_SPIKESOURCE)) {
    INTERESTING("SpikeSource");
  }
  cleanLicenceBuffer();
  /*
   * Legato systems
   */
  if (INFILE(_LT_LEGATO_1) || INFILE(_LT_LEGATO_2)) {
    INTERESTING("Legato");
  }
  cleanLicenceBuffer();
  /*
   * Paradigm associates
   */
  if (INFILE(_LT_PARADIGM) && INFILE(_CR_PARADIGM)) {
    INTERESTING("Paradigm");
  }
  cleanLicenceBuffer();
  /*
   * Wintertree Software
   */
  if (INFILE(_LT_WINTERTREE)) {
    INTERESTING("Wintertree");
  }
  cleanLicenceBuffer();
  /*
   * Genivia
   */
  if (INFILE(_LT_GENIVIAref)) {
    INTERESTING("Genivia.Commercial");
  }
  cleanLicenceBuffer();
  /*
   * Open Directory License
   */
  if (INFILE(_LT_ODL)) {
    INTERESTING("ODL");
  }
  cleanLicenceBuffer();
  /*
   * Open Directory License
   */
  if (INFILE(_LT_OSD)) {
    INTERESTING("OSD");
  }
  cleanLicenceBuffer();
  /*
   * Zveno
   */
  if (INFILE(_LT_ZVENO) && INFILE(_CR_ZVENO)) {
    INTERESTING("Zveno");
  }
  cleanLicenceBuffer();
  /*
   * Brainstorm
   */
  if (INFILE(_LT_BRAINSTORM_EULA) && INFILE(_TITLE_BRAINSTORM_EULA)) {
    INTERESTING("BrainStorm-EULA");
  }
  cleanLicenceBuffer();
  /*
   * AOL
   */
  if (INFILE(_LT_AOL_EULA)) {
    INTERESTING("AOL-EULA");
  }
  cleanLicenceBuffer();
  /*
   * Algorithmics
   */
  if (INFILE(_LT_ALGORITHMICS)) {
    INTERESTING("Algorithmics");
  }
  cleanLicenceBuffer();
  /*
   * Pixware
   */
  if (INFILE(_LT_PIXWARE_EULA)) {
    INTERESTING("Pixware-EULA");
  }
  cleanLicenceBuffer();
  /*
   * Compuserve
   */
  if (HASTEXT(_TEXT_COMPUSERVE, 0) && INFILE(_LT_COMPUSERVE)) {
    INTERESTING("CompuServe");
  }
  cleanLicenceBuffer();
  /*
   * Advanved Micro Devices (AMD)
   */
  if (INFILE(_CR_AMD) && INFILE(_LT_AMD)) {
    INTERESTING("AMD");
  }
  else if (INFILE(_LT_AMD_EULA) && INFILE(_TITLE_AMD_EULA)) {
    INTERESTING("AMD-EULA");
  }
  cleanLicenceBuffer();
  /*
   * OMRON Corp
   */
  if ((INFILE(_CR_OMRON_1) || INFILE(_CR_OMRON_2)) &&
      (INFILE(_LT_OMRON1) || INFILE(_LT_OMRON2))) {
    INTERESTING(lDebug ? "OMRON(2)" : "OMRON");
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * Older CMU (including the weird "fnord" text) licenses.
   * Note that SPDX listed MIT-CMU license is detected earlier.
   */
  if (!lmem[_mCMU] && INFILE(_LT_CMU_1)) {
    if (!lmem[_mREDHAT] && INFILE(_CR_REDHAT)) {
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
      INTERESTING("CMU-style");
      lmem[_mCMU] = 1;
    }
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
  cleanLicenceBuffer();
  /*
   * University of Chicago
   */
  if (INFILE(_CR_UCHICAGO) && INFILE(_LT_UCHICAGO)) {
    INTERESTING("U-Chicago");
  }
  cleanLicenceBuffer();
  /*
   * University of Utah
   */
  if (INFILE(_CR_UUTAH) && INFILE(_LT_UUTAH)) {
    INTERESTING("U-Utah");
  }
  cleanLicenceBuffer();
  /*
   * University of British Columbia
   */
  if (INFILE(_CR_UBC) && INFILE(_LT_UBC)) {
    INTERESTING("U-BC");
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * Riverbank
   */
  if (INFILE(_LT_RIVERBANK) && INFILE(_TITLE_RIVERBANK_EULA)) {
    INTERESTING("Riverbank-EULA");
  }
  cleanLicenceBuffer();
  /*
   * Polyserve
   */
  if (INFILE(_CR_POLYSERVE) && INFILE(_LT_POLYSERVE)) {
    INTERESTING("Polyserve-CONFIDENTIAL");
  }
  cleanLicenceBuffer();
  /*
   * Fujitsu Limited
   */
  if (INFILE(_CR_FUJITSU) && INFILE(_LT_FUJITSU)) {
    INTERESTING("Fujitsu");
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * Quest Software
   */
  if (INFILE(_LT_QUEST_EULA) && INFILE(_TITLE_QUEST_EULA)) {
    INTERESTING("Quest-EULA");
  }
  cleanLicenceBuffer();
  /*
   * International Organization for Standarization
   */
  if (INFILE(_LT_IOS) && INFILE(_CR_IOS)) {
    INTERESTING("IOS");
  }
  cleanLicenceBuffer();
  /*
   * Garmin Ltd.
   */
  if (INFILE(_LT_GARMIN_EULA) && INFILE(_TITLE_GARMIN_EULA)) {
    INTERESTING("Garmin-EULA");
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * Zeus Technology -- this one is kind of a corner-case since the wording
   * is VERY general.  If there's a Zeus copyright with the license text,
   * spell it out; else, look for the same text in the "generic" section.
   */
  if (INFILE(_CR_ZEUS) && INFILE(_LT_ZEUS)) {
    INTERESTING("Zeus");
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * Interlink networks EULA (seen in HP proprietary code)
   */
  if (INFILE(_LT_INTERLINK_EULA) && INFILE(_TITLE_INTERLINK_EULA)) {
    INTERESTING("Interlink-EULA");
  }
  cleanLicenceBuffer();
  /*
   * Mellanox Technologies
   */
  if (INFILE(_LT_MELLANOX) && INFILE(_CR_MELLANOX)) {
    INTERESTING("Mellanox");
  }
  cleanLicenceBuffer();
  /*
   * nCipher Corp
   */
  if (INFILE(_LT_NCIPHER) && INFILE(_CR_NCIPHER)) {
    INTERESTING("nCipher");
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * DSC Technologies Corp
   */
  if (INFILE(_CR_DSCT) && INFILE(_LT_DSCT)) {
    INTERESTING("DSCT");
  }
  cleanLicenceBuffer();
  /*
   * Epinions, Inc.
   */
  if (INFILE(_CR_EPINIONS) && INFILE(_LT_EPINIONS)) {
    INTERESTING("Epinions");
  }
  cleanLicenceBuffer();
  /*
   * MITEM, Ltd
   */
  if (INFILE(_CR_MITEM) && INFILE(_LT_MITEM)) {
    INTERESTING("MITEM");
  }
  cleanLicenceBuffer();
  /*
   * Cylink corp
   */
  if ((INFILE(_LT_CYLINK_ISC_1) || INFILE(_LT_CYLINK_ISC_2))) {
    INTERESTING("Cylink-ISC");
  }
  cleanLicenceBuffer();
  /*
   * SciTech software
   */
  if (INFILE(_CR_SCITECH) && INFILE(_LT_SCITECH)) {
    INTERESTING("SciTech");
  }
  cleanLicenceBuffer();
  /*
   * OReilly and Associates
   */
  if (INFILE(_LT_OREILLY_1)) {
    if (INFILE(_CR_OREILLY)) {
      INTERESTING("OReilly");
    }
    else {
      INTERESTING("OReilly-style");
    }
  }
  else if (INFILE(_LT_OREILLY_2)) {
    if (INFILE(_CR_OREILLY)) {
      INTERESTING(lDebug ? "OReilly-2" : "OReilly");
    }
    else {
      INTERESTING(lDebug ? "OReilly-st-2" : "OReilly-style");
    }
  }
  cleanLicenceBuffer();
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
  else if (INFILE(_LT_BITTORRENT_V11)) {
    INTERESTING("BitTorrent-1.1");
  }
  else if (INFILE(_LT_BITTORRENT_V10)) {
    INTERESTING("BitTorrent-1.0");
  }
  else if (INFILE(_LT_BITTORRENTref)) {
    INTERESTING(lDebug ? "BitTorrent(ref)" : "BitTorrent");
  }
  cleanLicenceBuffer();
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
    else if (INFILE(_LT_CMU_8)) {
      INTERESTING(lDebug ? "CMU(11)" : "CMU");
    }
    else {
      INTERESTING(lDebug ? "OSF-style(2)" : "OSF-style");
    }
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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
    INTERESTING("IoSoft.COMMERCIAL");
  }
  cleanLicenceBuffer();
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
      INTERESTING("WTI.Not-free");
    }
    else {
      INTERESTING("Not-Free");
    }
  }
  cleanLicenceBuffer();
  /*
   * Code Project Open License
   */
  if (INFILE(_LT_CPOL)) {
    if (HASTEXT(_LT_CPOL_V102, REG_EXTENDED)) {
      INTERESTING("CPOL-1.02");
    } else {
      INTERESTING("CPOL");
    }
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * VMware
   */
  if (INFILE(_LT_VMWARE) && INFILE(_TITLE_VMWARE)) {
    INTERESTING("VMware-EULA");
  }
  cleanLicenceBuffer();
  /*
   * UCWARE.com
   */
  if (INFILE(_LT_UCWARE_EULA_1) || INFILE(_LT_UCWARE_EULA_2)) {
    INTERESTING("UCWare-EULA");
  }
  cleanLicenceBuffer();
  /*
   * InfoSeek Corp
   */
  if (INFILE(_LT_INFOSEEK) && INFILE(_CR_INFOSEEK)) {
    INTERESTING("InfoSeek");
  }
  cleanLicenceBuffer();
  /*
   * Trident Microsystems
   */
  if (INFILE(_LT_TRIDENT_EULA) && INFILE(_CR_TRIDENT)) {
    INTERESTING("Trident-EULA");
  }
  cleanLicenceBuffer();
  /*
   * ARJ Software Inc
   */
  if (INFILE(_LT_ARJ) && INFILE(_CR_ARJ)) {
    INTERESTING("ARJ");
  }
  cleanLicenceBuffer();
  /*
   * Piriform Ltd
   */
  if (INFILE(_LT_PIRIFORM) && INFILE(_CR_PIRIFORM)) {
    INTERESTING("Piriform");
  }
  cleanLicenceBuffer();
  /*
   * Design Science License (DSL)
   */
  if (INFILE(_LT_DSL)) {
    INTERESTING("DSL");
  }
  cleanLicenceBuffer();
  /*
   * Skype
   */
  if (INFILE(_TITLE_SKYPE) && INFILE(_LT_SKYPE)) {
    INTERESTING("Skype-EULA");
  }
  cleanLicenceBuffer();
  /*
   * Hauppauge
   */
  if (INFILE(_LT_HAUPPAUGE)) {
    INTERESTING("Hauppauge");
  }
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
  /*
   * Curl
   */
  if (URL_INFILE(_URL_CURL)) {
    INTERESTING(lDebug ? "Curl(URL)" : "curl");
  }
  cleanLicenceBuffer();
  /*
   * ID Software
   */
  if (INFILE(_LT_ID_EULA)) {
    INTERESTING("ID-EULA");
  }
  cleanLicenceBuffer();
  /*
   * M+ Fonts Project
   */
  if (INFILE(_LT_MPLUS_FONT) && INFILE(_CR_MPLUS)) {
    INTERESTING("M-Plus-Project");
  }
  cleanLicenceBuffer();
  /*
   * Powder Development
   */
  if (INFILE(_LT_POWDER)) {
    INTERESTING("Powder-Proprietary");
  }
  cleanLicenceBuffer();
  /*
   * Against DRM
   */
  if (INFILE(_LT_AGAINST_DRM)) {
    INTERESTING("AgainstDRM");
  }
  cleanLicenceBuffer();
  /*
   * The TeXinfo exception clause
   */
  if (INFILE(_LT_TEX_EXCEPT)) {
    INTERESTING(lDebug ? "TeX-except" : "TeX-exception");
  }
  cleanLicenceBuffer();
  /*
   * The U.S. Gummint
   */
  if (INFILE(_LT_USGOVT_1)) {
    if (INFILE(_CR_URA)) {
      MEDINTEREST("URA.govt");
    }
    else {
      MEDINTEREST(lDebug ? "Govt-Wk(1)" : "Govt-work");
    }
  }
  else if (INFILE(_LT_USGOVT_2)) {
    /*
     * mpich2
     */
    if (INFILE(_LT_MPICH2)) {
       INTERESTING("mpich2");
    }
    else {
      MEDINTEREST(lDebug ? "Govt-Wk(2)" : "Govt-work");
    }
  }
  else if (INFILE(_LT_USGOVT_RIGHTS1) && INFILE(_LT_PUBLIC)) {
    MEDINTEREST(lDebug ? "US-Govt(1)" : "Govt-rights");
  }
  else if (INFILE(_LT_USGOVT_RIGHTS2)) {
    MEDINTEREST(lDebug ? "US-Govt(2)" : "Govt-rights");
  }
  cleanLicenceBuffer();
  /*
   * AACA (Ada Conformity Assessment Authority)
   */
  if (INFILE(_LT_ACAA_RIGHTS) && INFILE(_LT_PUBLIC)) {
    INTERESTING("ACAA");
  }
  cleanLicenceBuffer();
  /*
   * Zend Engine License
   */
  if (INFILE(_LT_ZEND_1) || URL_INFILE(_URL_ZEND)) {
    INTERESTING("Zend-2.0");
  }
  else if (INFILE(_LT_ZEND_2)) {
    INTERESTING("Zend-1.0");
  }
  cleanLicenceBuffer();
  /*
   * WebM
   * Changed to BSD-3-Clause, WebM is not OSI nor SPDX recognized license
   */
  if (URL_INFILE(_URL_WEBM)) {
    INTERESTING(lDebug ? "WebM" : "BSD-3-Clause");
  }
  cleanLicenceBuffer();
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
  else if (INFILE(_TITLE_ZIMBRA_12)) {
    INTERESTING("Zimbra-1.2");
  }
  else if (INFILE(_TITLE_ZIMBRA)) {
     INTERESTING("Zimbra");
  }
  cleanLicenceBuffer();
  /*
   * Open Database
   */
  if (INFILE(_TITLE_ODBL)) {
     INTERESTING("ODbL-1.0");
     lmem[_fODBL] = 1;
  }
  cleanLicenceBuffer();
  /*
   * Multics
   */
  if (INFILE(_LT_MULTICS)) {
     INTERESTING("Multics");
  }
  cleanLicenceBuffer();
  /*
   * H2
   * Note, H2 title is also checked in MPL section
   */
  if (INFILE(_TITLE_H2_V10)) {
    INTERESTING("H2-1.0");
  }
  cleanLicenceBuffer();
  /*
   * CRYPTOGAMS
   */
  if (INFILE(_LT_CRYPTOGAMS)) {
    INTERESTING("Cryptogams");
  }
  cleanLicenceBuffer();
  /*
   * Cygnus-eCos-1.0
   * Note, Cygnus-eCos title is also checked in MPL section
   */
  if (INFILE(_TITLE_CYGNUS_ECOS_V10)) {
    INTERESTING("Cygnus-eCos-1.0");
  }
  cleanLicenceBuffer();
  /*
   * RHeCos-1.1
   * Note, RHeCos-1.1 title is also checked in MPL section
   */
  if (INFILE(_TITLE_RHeCos_V11)) {
    INTERESTING("RHeCos-1.1");
  }
  cleanLicenceBuffer();
  /*
   * TMate
   * Note, TMate is also recognized with BSD-2-Clause detection
   */
  if (INFILE(_TITLE_TMATE)) {
    INTERESTING("TMate");
  }
  cleanLicenceBuffer();
  /*
   * Abstyles
   */
  if (INFILE(_LT_ABSTYLES_1) && INFILE(_LT_ABSTYLES_2)) {
    INTERESTING("Abstyles");
  }
  cleanLicenceBuffer();
  /*
   * Amazon Digital Services License
   */
  if (INFILE(_LT_ADSL) && INFILE(_CR_AMAZON)) {
    INTERESTING("ADSL");
  }
  cleanLicenceBuffer();
  /*
   * CrystalStacker License
   */
  if (HASTEXT(_LT_CRYSTALSTACKER, REG_EXTENDED)) {
    INTERESTING("CrystalStacker");
  }
  cleanLicenceBuffer();
  /*
   * 3GPP
   */
  if (INFILE(_LT_3GPP)) {
    INTERESTING("3GPP");
  }
  cleanLicenceBuffer();
  /*
   * ITU-T
   */
  if (INFILE(_LT_ITU_T_1) || INFILE(_LT_ITU_T_2) || HASTEXT(_TITLE_ITU_T, 0)) {
    INTERESTING("ITU-T");
  }
  cleanLicenceBuffer();
  /*
   * Sun Public License
   */
  if (!lmem[_mSUN] && !lmem[_mMPL]) {
    if (INFILE(_TITLE_SUN_PL10)) {
      INTERESTING("SPL-1.0");
    }
    else if (!lmem[_fREAL] && INFILE(_TITLE_SUN_PL)) {
      INTERESTING("SPL");
    }
  }
  cleanLicenceBuffer();
  /*
   * libtiff, note that license text is detected earlier
   */
  if (INFILE(_PHR_LIBTIFF)) {
     INTERESTING("libtiff");
  }
  cleanLicenceBuffer();
  /*
   * Imlib2
   */
  if (INFILE(_PHR_Imlib2)) {
    INTERESTING("Imlib2");
  }
  cleanLicenceBuffer();
  /*
   * Wide Open License (WOL)
   */
  if (INFILE(_TITLE_WOL) || INFILE(_URL_WOL)) {
    INTERESTING("WOL");
  }
  cleanLicenceBuffer();
  /*
   * naist-2003
   */
  if (INFILE(_LT_NAIST_2003) && HASTEXT(_TEXT_NAIST, 0)) {
    INTERESTING("naist-2003");
  }
  cleanLicenceBuffer();
  /*
   * EDL-1.0
   */
  if (INFILE(_TITLE_EDL_V10)) {
    INTERESTING("EDL-1.0");
  }
  cleanLicenceBuffer();
  /*
   * HSQLDB
   */
  if (INFILE(_LT_HSQLDB_1) || INFILE(_LT_HSQLDB_2) || INFILE(_LT_HSQLDB_3)) {
    INTERESTING("HSQLDB");
  }
  cleanLicenceBuffer();
  /*
   * Sony Computer Entertainment (SCEA) Shared Source License
  */
  if (INFILE(_TITLE_SCEA)) {
    INTERESTING("SCEA");
  }
  cleanLicenceBuffer();
  /*
   * OpenMap
   */
  if (INFILE(_TITLE_OPENMAP)) {
    INTERESTING("OpenMap");
    lmem[_fPDDL] = 1;
  }
  cleanLicenceBuffer();
  /*
   * ICU 1.8.1
   */
  if (INFILE(_LT_ICU_1) || INFILE(_TITLE_ICU) || INFILE(_SPDX_ICU)) {
    INTERESTING("ICU");
  }
  else if (INFILE(_PHR_ICU_1)) {
    INTERESTING("ICU");
  }
  cleanLicenceBuffer();
  /*
   * Ubuntu Font License
   */
  if (INFILE(_TITLE_UBUNTU_FONT)) {
    INTERESTING("ubuntu-font-1.0");
    lmem[_fPDDL] = 1;
  }
  cleanLicenceBuffer();
  /*
   * ODC Attribution License
   */
  if (INFILE(_LT_ODC)) {
    INTERESTING("ODC-By-1.0");
    pd = 0;
  }
  cleanLicenceBuffer();
  /*
   * Community Data License Agreement
   */
  if (INFILE(_TITLE_CDLA_Permissive_10)) {
    INTERESTING("CDLA-Permissive-1.0");
  }
  else if (INFILE(_TITLE_CDLA_Sharing_10)) {
    INTERESTING("CDLA-Sharing-1.0");
  }
  cleanLicenceBuffer();
  /*
   * Toolbar2000
   */
  if (INFILE(_TITLE_Toolbar2000) || INFILE(_URL_Toolbar2000)) {
    INTERESTING("Toolbar2000");
  }
  cleanLicenceBuffer();
  /*
   * unboundID-ldap-sdk
   */
  if (INFILE(_TITLE_unboundID_ldap_sdk) || INFILE(_LT_unboundID_ldap_sdk)) {
    INTERESTING("unboundID-ldap-sdk");
  }
  cleanLicenceBuffer();
  /*
   * BlueOak-1.0.0
   */
  if (INFILE(_TITLE_BlueOak_100) || URL_INFILE(_URL_BlueOak_100)) {
    INTERESTING("BlueOak-1.0.0");
  }
  else if (INFILE(_LT_BlueOak_100_Purpose) && INFILE(_LT_BlueOak_100_Acceptance)) {
    INTERESTING("BlueOak-1.0.0");
  }
  cleanLicenceBuffer();
  /*
   * CERN-OHL
   */
  if (INFILE(_TITLE_CERN_OHL_11)) {
    INTERESTING("CERN-OHL-1.1");
  }
  else if (INFILE(_TITLE_CERN_OHL_12)) {
    INTERESTING("CERN-OHL-1.2");
  }
  cleanLicenceBuffer();
  /*
   * MulanPSL
   */
  if (INFILE(_TITLE_MulanPSL_10) || URL_INFILE(_URL_MulanPSL) || INFILE(_LT_MulanPSL_10)) {
    INTERESTING("MulanPSL-1.0");
  }
  cleanLicenceBuffer();
  /*
   * SSH
   */
  if (INFILE(_LT_FREE_72) && HASTEXT(_LT_SSH, REG_EXTENDED)) {
    if (INFILE(_LT_SSH_OpenSSH)) {
      INTERESTING("SSH-OpenSSH");
    }
    else {
      INTERESTING("SSH-short");
    }
  }
  cleanLicenceBuffer();

  SPDXREF();
  cleanLicenceBuffer();
  EXCEPTIONS();
  cleanLicenceBuffer();

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
  cleanLicenceBuffer();
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
  }
  addLicence(cur.theMatches,"NoWarranty");

  /*
   * Statements about IP (Intellectual Property) rights
   */
  if (!lmem[_fIP] && INFILE(_LT_GEN_IP_1)) {
    INTERESTING(lDebug ? "IP(1)" : "IP-claim");
  }
  else if (!lmem[_fIP] && INFILE(_LT_GEN_IP_2) && NOT_INFILE(_TITLE_MIROS)) {
    INTERESTING(lDebug ? "IP(2)" : "IP-claim");
  }
  else if (!lmem[_fIP] && INFILE(_LT_GEN_IP_3)) {
    INTERESTING(lDebug ? "IP(3)" : "IP-claim");
  }
  cleanLicenceBuffer();
  /*
   * Dual licenses
   */
  if (INFILE(_LT_DUAL_LICENSE_0) && NOT_INFILE(_TITLE_NOSL)) {
    MEDINTEREST(lDebug ? "Dual-license(0)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_22)) {
    MEDINTEREST(lDebug ? "Dual-license(22)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_1) && NOT_INFILE(_TITLE_NOSL)) {
    MEDINTEREST(lDebug ? "Dual-license(1)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_2)) {
    MEDINTEREST(lDebug ? "Dual-license(2)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_3) && NOT_INFILE(_LT_DUAL_LICENSE_3_EXHIBIT)) {
    MEDINTEREST(lDebug ? "Dual-license(3)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_4)) {
    MEDINTEREST(lDebug ? "Dual-license(4)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_5)) {
    MEDINTEREST(lDebug ? "Dual-license(5)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_6)) {
    MEDINTEREST(lDebug ? "Dual-license(6)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_7)) {
    MEDINTEREST(lDebug ? "Dual-license(7)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_8)) {
    MEDINTEREST(lDebug ? "Dual-license(8)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_9)) {
    MEDINTEREST(lDebug ? "Dual-license(9)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_10)) {
    MEDINTEREST(lDebug ? "Dual-license(10)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_11)) {
    MEDINTEREST(lDebug ? "Dual-license(11)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_12)) {
    MEDINTEREST(lDebug ? "Dual-license(12)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_13)) {
    INTERESTING(lDebug ? "Dual-license(13)" : "Dual-license");
    INTERESTING("BSD");
    INTERESTING("MIT");
    /*
     * A special case for NomosTestfiles/Dual-license/respond.js
     * that has two dual-license statements.
     */
    if (!lmem[_mGPL]) {
      if (INFILE(_LT_DUAL_LICENSE_16)) {
        INTERESTING(lDebug ? "GPLV2(Dual-license(16))" : "GPL-2.0-only");
      }
    }
  }
  else if (INFILE(_LT_DUAL_LICENSE_14)) {
    INTERESTING(lDebug ? "Dual-license(14)" : "Dual-license");
    INTERESTING("BSD");
    if (!lmem[_mGPL]) {
      INTERESTING("GPL");
    }
  }
  else if (INFILE(_LT_DUAL_LICENSE_15)) {
    MEDINTEREST(lDebug ? "Dual-license(15)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_16)) {
    INTERESTING(lDebug ? "Dual-license(16)" : "Dual-license");
    INTERESTING("MIT");
    INTERESTING(lDebug ? "GPLV2(Dual-license(16))" : "GPL-2.0-only");
  }
  else if (INFILE(_LT_DUAL_LICENSE_17)) {
    MEDINTEREST(lDebug ? "Dual-license(17)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_18)) {
    MEDINTEREST(lDebug ? "Dual-license(18)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_19)) {
    MEDINTEREST(lDebug ? "Dual-license(19)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_20)) {
    MEDINTEREST(lDebug ? "Dual-license(20)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_21)) {
    MEDINTEREST(lDebug ? "Dual-license(21)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_23)) {
    MEDINTEREST(lDebug ? "Dual-license(23)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_24)) {
    MEDINTEREST(lDebug ? "Dual-license(24)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_25)) {
    MEDINTEREST(lDebug ? "Dual-license(25)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_26)) {
    MEDINTEREST(lDebug ? "Dual-license(26)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_27)) {
    MEDINTEREST(lDebug ? "Dual-license(27)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_28)) {
    MEDINTEREST(lDebug ? "Dual-license(28)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_29) && NOT_INFILE(_LT_MPL_SECONDARY_LICENSE)) {
    MEDINTEREST(lDebug ? "Dual-license(29)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_30)) {
    MEDINTEREST(lDebug ? "Dual-license(30)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_31)) {
    MEDINTEREST(lDebug ? "Dual-license(31)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_32)) {
    MEDINTEREST(lDebug ? "Dual-license(32)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_33)) {
    MEDINTEREST(lDebug ? "Dual-license(33)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_34)) {
    MEDINTEREST(lDebug ? "Dual-license(34)" : "Dual-license");
  }
  else if (HASTEXT(_LT_DUAL_LICENSE_35, 0)) {
    MEDINTEREST(lDebug ? "Dual-license(35)" : "Dual-license");
    /*
     * GPL is not detected correctly in this case, therefore it is set here.
     */
    INTERESTING("GPL");
  }
  else if (INFILE(_LT_DUAL_LICENSE_36)) {
    MEDINTEREST(lDebug ? "Dual-license(36)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_37)) {
    MEDINTEREST(lDebug ? "Dual-license(37)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_38)) {
    MEDINTEREST(lDebug ? "Dual-license(38)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_39)) {
    MEDINTEREST(lDebug ? "Dual-license(39)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_40)) {
    MEDINTEREST(lDebug ? "Dual-license(40)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_41)) {
    MEDINTEREST(lDebug ? "Dual-license(41)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_42)) {
    MEDINTEREST(lDebug ? "Dual-license(42)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_43)) {
    MEDINTEREST(lDebug ? "Dual-license(43)" : "Dual-license");
  }
  else if (HASTEXT(_LT_DUAL_LICENSE_44, 0)) {
    MEDINTEREST(lDebug ? "Dual-license(44)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_45)) {
    MEDINTEREST(lDebug ? "Dual-license(45)" : "Dual-license");
    INTERESTING("MIT");
  }
  else if (HASTEXT(_LT_DUAL_LICENSE_46, REG_EXTENDED)) {
    MEDINTEREST(lDebug ? "Dual-license(46)" : "Dual-license");
  }
  else if (HASTEXT(_LT_DUAL_LICENSE_47, REG_EXTENDED) && NOT_INFILE(_LT_MPL_SECONDARY_LICENSE)) {
    MEDINTEREST(lDebug ? "Dual-license(47)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_48)) {
    MEDINTEREST(lDebug ? "Dual-license(48)" : "Dual-license");
  }
  else if (HASTEXT(_LT_DUAL_LICENSE_49, REG_EXTENDED)) {
    MEDINTEREST(lDebug ? "Dual-license(49)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_50)) {
    INTERESTING(lDebug ? "Dual-license(50)" : "Dual-license");
  }
  else if (INFILE(_LT_DUAL_LICENSE_50_BSD_MIT)) {
    INTERESTING("BSD");
    INTERESTING(lDebug ? "Dual-license(50)" : "Dual-license");
    INTERESTING("MIT");
  }
  else if (INFILE(_LT_DUAL_LICENSE_51)) {
    INTERESTING(lDebug ? "Dual-license(51)" : "Dual-license");
  }
  cleanLicenceBuffer();
  /*
   * The Beer-ware license(!)
   */
  if (INFILE(_LT_BEERWARE)) {
    INTERESTING("Beerware");
  }
  cleanLicenceBuffer();
  /*
   * CMake license
   */
  if (URL_INFILE(_URL_CMAKE)) {
    INTERESTING("CMake");
  }
  cleanLicenceBuffer();
  /*
   * unRAR restriction
   */
  if (INFILE(_LT_UNRARref1) || INFILE(_LT_UNRARref2)) {
    INTERESTING("unRAR restriction");
  }
  cleanLicenceBuffer();
  /*
   * ANTLR Software Rights Notice
   */
  if (INFILE(_LT_ANTLR)) {
    INTERESTING("ANTLR-PD");
    lmem[_fANTLR] = 1;
  }
  cleanLicenceBuffer();
  /*
   * Creative Commons Zero v1.0 Universal
   */
  if (INFILE(_SPDX_CC0_10)) {
    INTERESTING("CC0-1.0");
  }
  else if (INFILE(_TITLE_CC0_10_1) || INFILE(_PHR_CC0_1) || INFILE(_PHR_CC0_2)) {
    INTERESTING("CC0-1.0");
  }
  else if (INFILE(_SPDX_CC0)) {
    INTERESTING("CC0");
  }
  cleanLicenceBuffer();
  /*
   * PA Font License (IPA)
   */
  if (INFILE(_TITLE_IPA)) {
    INTERESTING("IPA");
  }
  cleanLicenceBuffer();
  /*
   * European Union Public Licence
   */
  if (INFILE(_PHR_EUPL_10) || INFILE(_TITLE_EUPL_10)) {
    INTERESTING("EUPL-1.0");
  }
  else if (INFILE(_PHR_EUPL_11) || INFILE(_TITLE_EUPL_11)) {
    INTERESTING("EUPL-1.1");
  }
  else if (INFILE(_TITLE_EUPL_12)) {
    INTERESTING("EUPL-1.2");
  }
  else if (INFILE(_PHR_EUPL)) {
	INTERESTING("EUPL");
  }
  cleanLicenceBuffer();
  /* University of Illinois/NCSA Open Source License */
  if (!lmem[_fNCSA] && INFILE(_TITLE_NCSA) && NOT_INFILE(_TITLE_NCSA_EXHIBIT)) {
    INTERESTING(lDebug ? "NCSA(2)" : "NCSA");
    /* OZPLB-1.1 refers both to NCSA and OZPLB-1.1 licenses */
    if (INFILE(_TITLE_OZPLB_11)) {
      INTERESTING("OZPLB-1.1");
    }
    lmem[_fBSD] = 1;
    lmem[_mMIT] = 1;
  }
  cleanLicenceBuffer();
  /* ODC Public Domain Dedication & License 1.0 */
  if (INFILE(_TITLE_PDDL)) {
    INTERESTING("PDDL-1.0");
    lmem[_fPDDL] = 1;
  }
  cleanLicenceBuffer();
  /* PostgreSQL License */
  if (INFILE(_TITLE_POSTGRES) || INFILE(_TITLE_POSTGRES_1)) {
    INTERESTING("PostgreSQL");
    lmem[_fBSD] = 1;
  }
  cleanLicenceBuffer();
  /* Sax Public Domain Notice */
  if (INFILE(_LT_SAX_PD)) {
    INTERESTING("SAX-PD");
    lmem[_fSAX] = 1;
  }
  cleanLicenceBuffer();
  /*
   * WTF Public "license"
   */
  if (INFILE(_LT_WTFPL)) {
    INTERESTING("WTFPL");
  }
  else if (INFILE(_LT_WTFPLref)) {
    INTERESTING(lDebug ? "WTFPL(ref)" : "WTFPL");
  }
  else if (INFILE(_LT_WTFPLref_1)) {
    INTERESTING(lDebug ? "WTFPL(ref#1)" : "WTFPL");
  }
  else if (INFILE(_PHR_WTFPL)) {
    INTERESTING(lDebug ? "WTFPL(phr)" : "WTFPL");
  }
  cleanLicenceBuffer();
  /* Independent JPEG Group License */
  if (!lmem[_fIJG]) {
    if (HASTEXT(_PHR_IJG_1, REG_EXTENDED)) {
      INTERESTING("IJG");
    }
    else if (HASTEXT(_PHR_IJG_2, 0)) {
      INTERESTING("IJG");
    }
    else if (HASTEXT(_TITLE_IJG_1, 0) && !HASTEXT(_PHR_IJG_INTERFACE_1, 0) && !HASTEXT(_PHR_IJG_INTERFACE_2, 0)) {
      INTERESTING("IJG");
    }
    /* Independent JPEG Group can be referenced without IJG license*/
    else if (HASTEXT(_TITLE_IJG_2, 0) && !HASTEXT(_PHR_IJG_INTERFACE_1, 0) && !HASTEXT(_PHR_IJG_INTERFACE_2, 0)) {
      INTERESTING("IJG-possibility");
    }
    cleanLicenceBuffer();
  }
  /* Netizen Open Source License  */
  if (!lmem[_mMPL] && INFILE(_TITLE_NOSL)) {
    INTERESTING(lDebug ? "NOSL(#2)" : "NOSL");
  }
  cleanLicenceBuffer();
  /* Net Boolean Public License v1 */
  if (INFILE(_TITLE_NBPL_V10)) {
    INTERESTING("NBPL-1.0");
  }
  cleanLicenceBuffer();
  /* Flora License */
  if (INFILE(_TITLE_Flora_V10)) {
    INTERESTING("Flora-1.0");
  }
  else if (INFILE(_TITLE_Flora_V11)) {
    INTERESTING("Flora-1.1");
  }
  else if (URL_INFILE(_URL_Flora)) {
    INTERESTING("Flora");
  }
  cleanLicenceBuffer();
  /* Standard ML of New Jersey License */
  if (INFILE(_TITLE_SMLNJ)) {
    INTERESTING("SMLNJ");
  }
  cleanLicenceBuffer();
  /* Mozilla Public License possibility */
  if (!lmem[_mMPL] && INFILE(_TEXT_MPLV2) && INFILE(_URL_MPL20)) {
      INTERESTING("MPL-2.0");
      lmem[_mMPL] = 1;
  }
  else if (!lmem[_mMPL] && URL_INFILE(_URL_MPL_LATEST)) {
    INTERESTING(lDebug ? "MPL(latest)" : "MPL");
  }
  cleanLicenceBuffer();
  /* Citrix License */
  if (INFILE(_TITLE_CITRIX)) {
    INTERESTING("Citrix");
    lmem[_fCITRIX] = 1;
  }
  cleanLicenceBuffer();
  /* CUA office public license */
  if (INFILE(_TITLE_CUA10)) {
    INTERESTING("CUA-OPL-1.0");
  }
  cleanLicenceBuffer();
  /*  the Erlang Public License */
  if (INFILE(_TITLE_ERLPL_ref)) {
    INTERESTING("ErlPL-1.1");
  }
  cleanLicenceBuffer();
  /* German Free Software License */
  if (INFILE(_TITLE_D_FSL_10) || INFILE(_TITLE_D_FSL_DE1_10) || INFILE(_TITLE_D_FSL_DE2_10) || INFILE(_TITLE_D_FSL_DE3_10) || INFILE(_TITLE_D_FSL_DE4_10))
  {
    INTERESTING("D-FSL-1.0");
    lmem[_mGPL] = 1;
  }
  cleanLicenceBuffer();
  /*  CCLRC License */
  if (INFILE(_TITLE_CCLRC)) {
    INTERESTING("CCLRC");
  }
  cleanLicenceBuffer();

  /* Some GPL cases are still missing */
  if (!lmem[_mGPL] && (INFILE(_LT_GPL_V2_ref) || INFILE(_LT_GPL_V2_ref1) || INFILE(_LT_GPL_V2_ref2) || INFILE(_LT_GPL_V2_ref3) || INFILE(_LT_GPL_V2_ref4)))
  {
    INTERESTING(lDebug ? "GPL_V2_ref" : "GPL-2.0-only");
    lmem[_mGPL] = 1;
  }
  else if (!lmem[_mGPL] && INFILE(_LT_GPL_V3_ref))
  {
    INTERESTING(lDebug ? "GPL_V3_ref" : "GPL-3.0-only");
    lmem[_mGPL] = 1;
  }
  else if (!lmem[_mGPL] && INFILE(_LT_GPLref22))
  {
    INTERESTING(lDebug ? "GPLref22" : "GPL");
    lmem[_mGPL] = 1;
  }
  else if (!lmem[_mGPL] && NOT_INFILE(_LT_IGNORE_CLAUSE_2) && INFILE(_LT_GPLref21))
  {
    INTERESTING(lDebug ? "GPLref21" : "GPL");
    lmem[_mGPL] = 1;
  }
  cleanLicenceBuffer();

  /* MX4J License version 1.0 */
  if (INFILE(_LT_MX4J_V10))
  {
    INTERESTING("MX4J-1.0");
  }
  else if (INFILE(_LT_MX4J))
  {
    INTERESTING("MX4J");
  }
  cleanLicenceBuffer();
  /* postfix license */
  if (INFILE(_TITLE_POSTFIX))
  {
    INTERESTING("Postfix");
  }
  cleanLicenceBuffer();
  /* not public domain */
  if (HASTEXT(_LT_PUBDOM_NOTclaim, REG_EXTENDED)) {
    if (INFILE(_LT_PUBDOM_CC)) {
      INTERESTING(lDebug ? "Pubdom(CC)" : "CC-PDDC");
      pd = 1;
    }
    else {
      INTERESTING(LS_NOT_PD);
      pd = 0;
    }
  }
  cleanLicenceBuffer();
  /* LIBGCJ license */
  if (INFILE(_LT_LIBGCJ))
  {
    INTERESTING("LIBGCJ");
  }
  cleanLicenceBuffer();
  /* open cascade technology public license */
  if (INFILE(_TITLE_OPEN_CASCADE))
  {
    INTERESTING("OpenCASCADE-PL");
  }
  cleanLicenceBuffer();
  /*  KnowledgeTree Public License */
  if (INFILE(_LT_KnowledgeTree_V11))
  {
    INTERESTING("KnowledgeTree-1.1");
  }
  cleanLicenceBuffer();
  /* Interbase Public License */
  if (INFILE(_LT_Interbase_V10))
  {
    INTERESTING("Interbase-1.0");
  }
  cleanLicenceBuffer();
  /* ClearSilver license */
  if (INFILE(_LT_ClearSilver))
  {
    INTERESTING("ClearSilver");
  }
  cleanLicenceBuffer();
  /* ACE, TAO, CIAO */
  if(INFILE(_LT_ACE)) {
    INTERESTING("ACE");
  }
  else if(INFILE(_LT_FACE)) {
    INTERESTING("FaCE");
  }
  cleanLicenceBuffer();
  /* JISP */
  if(INFILE(_LT_JISP)) {
    INTERESTING("JISP");
  }
  cleanLicenceBuffer();
  /* Qmail */
  if(INFILE(_LT_QMAIL)) {
    INTERESTING("Qmail");
  }
  cleanLicenceBuffer();
  /* Migemo */
  if(INFILE(_LT_MIGEMO)) {
    INTERESTING("Migemo");
  }
  cleanLicenceBuffer();
  /* Sendmail */
  if(INFILE(_LT_Sendmail_title) ) {
     INTERESTING("Sendmail");
  }
  cleanLicenceBuffer();
  /* Giftware */
  if(INFILE(_LT_GIFTWARE)) {
    INTERESTING("Giftware");
  }
  cleanLicenceBuffer();
  /* Logica opensource */
  if(INFILE(_LT_LOGICA)) {
    INTERESTING("Logica-OSL-1.0");
  }
  cleanLicenceBuffer();
  /* Unidex */
  if(INFILE(_LT_UNIDEX)) {
    INTERESTING("Unidex");
  }
  cleanLicenceBuffer();
  /*  TCL License */
  if (!lmem[_fTCL]) {
    if (INFILE(_TITLE_TCL)) {
      INTERESTING("TCL");
    }
    else if (INFILE(_SPDX_TCL)) {
      INTERESTING("TCL");
    }
    else if (INFILE(_LT_TCL)) {
      INTERESTING("TCL");
    }
  }
  cleanLicenceBuffer();
  /* AndroidSDK-Commercial license */
  if (INFILE(_LT_GOOGLE_SDK)) {
    INTERESTING("AndroidSDK.Commercial");
  }
  cleanLicenceBuffer();
  /* Qhull license */
  if (INFILE(_PHR_Qhull)) {
    INTERESTING("Qhull");
  }
  cleanLicenceBuffer();
  /* OZPLB-1.0 license */
  if (INFILE(_PHR_OZPLB_10)) {
    INTERESTING("OZPLB-1.0");
  }
  cleanLicenceBuffer();
  /* Texas Instruments license */
  if (INFILE(_LT_TI_BASE)) {
    if (INFILE(_LT_TI_TSPA)) {
      INTERESTING("TI-TSPA");
    } else if (INFILE(_LT_TI_TFL)) {
      INTERESTING("TI-TFL");
    }
  }
  cleanLicenceBuffer();

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
  cleanLicenceBuffer();
  gl.flags |= ~FL_SAVEBASE; /* turn off, regardless */
  /*
   * ... and, there are several generic claims that "you are free to use this
   * software".
   * We call these claims "Freeware", because you can use the software free of charge,
   * but some other copyright holder exclusive rights are not granted in some cases.
   */
  if (*licStr == NULL_CHAR || pd >= 0) {
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
        (void) strcpy(name, "Freeware");
        if (lDebug) {
          (void) sprintf(name+8, "(%d)", i+1);
        }
        INTERESTING(name);
        break;
      }
    }
  }
  cleanLicenceBuffer();
  /* Check for Public Domain */
  if (!lmem[_fANTLR] && !lmem[_fCLA] && !lmem[_mPYTHON] && !lmem[_mGFDL] &&
      !lmem[_fODBL] && !lmem[_fPDDL] && !lmem[_fRUBY] && !lmem[_fSAX] && !lmem[_fAPL] && !lmem[_mAPACHE] && !lmem[_mAPACHE10] && !lmem[_mAPACHE11] &&
      !lmem[_fARTISTIC] && !lmem[_fCITRIX] && !lmem[_mLGPL] && !lmem[_fBSD]&& NOT_INFILE(_TITLE_D_FSL_10)
      && !INFILE(_LT_CPOL)) {
    pd = checkPublicDomain(filetext, size, score, kwbm, isML, isPS);
  }
  cleanLicenceBuffer();
  /*
   * Some licenses point you to files/URLs...
   */
  if (*licStr == NULL_CHAR) {
    checkFileReferences(filetext, size, score, kwbm, isML, isPS);
  }
  cleanLicenceBuffer();
  /*
   * And, If no other licenses are present but there's a reference to
   * something being non-commercial, better note it now.
   */
#if 0
  if (*licStr == NULL_CHAR && !HASKW(kwbm, _KW_public_domain))
#endif
    if (maxInterest != IL_HIGH && !HASKW(kwbm, _KW_public_domain) &&
        NOT_INFILE(_PHR_COMMERC_NONCOMM)) {
      if (INFILE(_LT_NONCOMMERCIAL_1)) {
        INTERESTING(lDebug ? "NonC(1)" : "Non-commercial");
      }
      else if (INFILE(_LT_ZZNON_COMMERC1)) {
        INTERESTING(lDebug ? "NonC(2)" : "Non-commercial");
      }
      else if (INFILE(_LT_ZZNON_COMMERC2)) {
        INTERESTING(lDebug ? "NonC(3)" : "Non-commercial");
      }
      else if (HASTEXT(_TEXT_COMMERC, 0) &&
          INFILE(_PHR_NONCOMMERCIAL)) {
        INTERESTING(lDebug ? "NonC(4)" : "Non-commercial");
      }
    }
  if (INFILE(_LT_NOT_OPENSOURCE)) {
    INTERESTING("Not-OpenSource");
  }
  listClear(&whereList, NO);      /* again, clear "unused" matches */
  cleanLicenceBuffer();
  /*
   * Look for footprints that declare something as proprietary... if we such
   * a statement, we care about the Copyright owner, too.
   */
  if (maxInterest != IL_HIGH) { /* if (*licStr == NULL_CHAR) { */
    j = 0;  /* just a flag */
    if (INFILE(_LT_GEN_PROPRIETARY_1)) {
      INTERESTING(lDebug ? "Prop(1)" : "COMMERCIAL");
      j++;
    }
    else if (INFILE(_LT_GEN_PROPRIETARY_2)) {
      INTERESTING(lDebug ? "Prop(2)" : "COMMERCIAL");
      j++;
    }
    else if (INFILE(_LT_GEN_PROPRIETARY_3)) {
      INTERESTING(lDebug ? "Prop(3)" : "COMMERCIAL");
      j++;
    }
    else if (INFILE(_LT_GEN_PROPRIETARY_4)) {
      INTERESTING(lDebug ? "Prop(4)" : "COMMERCIAL");
      j++;
    }
    else if (INFILE(_LT_GEN_PROPRIETARY_5)) {
      INTERESTING(lDebug ? "Prop(5)" : "COMMERCIAL");
      j++;
    }
    else if (INFILE(_LT_GEN_PROPRIETARY_6)) {
      INTERESTING(lDebug ? "Prop(6)" : "COMMERCIAL");
      j++;
    }
    else if (INFILE(_LT_GEN_PROPRIETARY_7)) {
      INTERESTING(lDebug ? "Prop(7)" : "COMMERCIAL");
      j++;
    }
    else if (INFILE(_LT_GEN_PROPRIETARY_8)) {
      INTERESTING(lDebug ? "Prop(8)" : "COMMERCIAL");
      j++;
    }
    if (j) {
      checkCornerCases(filetext, size, score, kwbm, isML,
          isPS, nw, YES);
    }
  }
  listClear(&whereList, NO);      /* again, clear "unused" matches */
  cleanLicenceBuffer();
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
      INTERESTING(lDebug ? "COMM(1)" : "COMMERCIAL");
    }
    else if (INFILE(_LT_COMMERCIAL_2)) {
      INTERESTING(lDebug ? "COMM(2)" : "COMMERCIAL");
    }
    else if (HASTEXT(_LT_COMMERCIAL_3, REG_EXTENDED)) {
      if (HASTEXT(_LT_COMMERCIAL_Intel, REG_EXTENDED)) {
        INTERESTING("Intel.Commercial");
      } else if (HASTEXT(_LT_COMMERCIAL_Broadcom, REG_EXTENDED)) {
        INTERESTING("Broadcom.Commercial");
      } else {
        INTERESTING(lDebug ? "COMM(3)" : "COMMERCIAL");
      }
    }
    else if (INFILE(_LT_COMMERCIAL_4)) {
      if (HASTEXT(_LT_COMMERCIAL_Android_Fraunhofer, 0)) {
        INTERESTING("AndroidFraunhofer.Commercial");
      } else {
        INTERESTING(lDebug ? "COMM(4)" : "COMMERCIAL");
      }
    }
    else if (HASTEXT(_TEXT_BOOK, 0) && INFILE(_LT_BOOKPURCHASE)) {
      INTERESTING(lDebug ? "PurchBook" : "COMMERCIAL");
    }
    else if (INFILE(_LT_COMMERCIAL_5)) {
      INTERESTING(lDebug ? "COMM(5)" : "COMMERCIAL");
    }
    else if (INFILE(_LT_COMMERCIAL_6)) {
      INTERESTING(lDebug ? "COMM(6)" : "COMMERCIAL");
    }
    else if (INFILE(_LT_COMMERCIAL_7)) {
      INTERESTING(lDebug ? "COMM(7)" : "COMMERCIAL");
    }
    if (INFILE(_LT_NONPROFIT_1)) {
      MEDINTEREST(lDebug ? "NonP(1)" : "Non-profit");
    }
    else if (!lmem[_mPYTH_TEXT] && HASTEXT(_TEXT_PROFIT, 0) &&
        INFILE(_PHR_NONPROFIT)) {
      if (!(lmem[_fIETF] + lmem[_fDOC])) {
        MEDINTEREST(lDebug ? "NonP(2)" : "Non-profit");
      }
    }
    if (INFILE(_PHR_NO_SALE)) {
      MEDINTEREST("Not-for-sale");
    }
    if (!lmem[_mALADDIN] && INFILE(_PHR_NOT_OPEN)) {
      MEDINTEREST("NOT-Open-Source");
    }
    if (HASKW(kwbm, _KW_patent) && INFILE(_PHR_PATENT) && NOT_INFILE(_PHR_PATENT_NOT)) {
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
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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
  cleanLicenceBuffer();
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

/**
 * \brief Check for SISSL versions
 * \param filetext  File content
 * \param size      File size
 * \param isML      File is HTML/XML
 * \param isPS      File is PostScript
 * \return Return SISSL license shortname
 */
char *sisslVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;

#ifdef  PROC_TRACE
  traceFunc("== sisslVersion()\n");
#endif  /* PROC_TRACE */

  if (INFILE(_TITLE_SISSL_V11)) {
    lstr = "SISSL-1.1";
  }
  else if (INFILE(_TITLE_SISSL_V12)) {
    lstr = "SISSL-1.2";
  } else {
    lstr = "SISSL";
  }
  return lstr;
}

/**
 * \brief Check for ASL Apache versions
 * \param filetext  File content
 * \param size      File size
 * \param isML      File is HTML/XML
 * \param isPS      File is PostScript
 * \return Return ASL Apache license shortname
 */
char *aslVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;

#ifdef  PROC_TRACE
  traceFunc("== aslVersion()\n");
#endif  /* PROC_TRACE */

  /*
   * Exclude first non-Apache licenses
   */
  if (INFILE(_TITLE_PHORUM) || INFILE(_CR_PHORUM)) {
    lstr = "Phorum";
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_CR_IMAGEMAGICK)) {
    lstr = "ImageMagick(Apache)";
    lmem[_mAPACHE] = 1;
  }
  /*
   * Apache-2.0 cases
   */
  else if (INFILE(_SPDX_Apache_20)) {
    lstr = (lDebug ? "Apache-2.0(SPDX)" : "Apache-2.0");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_TITLE_Apache_20)) {
    lstr = (lDebug ? "Apache-2(f)" : "Apache-2.0");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_PHR_Apache_20_ref1) || INFILE(_PHR_Apache_20_ref2) || INFILE(_PHR_Apache_20_ref3))
  {
    lstr = (lDebug ? "PHR_Apache_20_ref(1-5)" : "Apache-2.0");
    lmem[_mAPACHE] = 1;
  }
  else if (URL_INFILE(_URL_Apache_20)) {
    lstr = (lDebug ? "Apache-2.0(u)" : "Apache-2.0");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_LT_Apache_20) && NOT_INFILE(_TITLE_Flora_V10) && NOT_INFILE(_TITLE_Flora_V11) && !URL_INFILE(_URL_Flora))
  {
    lstr = (lDebug ? "Apache(2.0#2)" : "Apache-2.0");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_PHR_Apache_20_ref4) || INFILE(_PHR_Apache_20_ref5) || INFILE(_PHR_Apache_20_ref6) || INFILE(_PHR_Apache_20_ref7)) {
    lstr = (lDebug ? "Apache(2.0#3)" : "Apache-2.0");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_TITLE_Apache_20)) {
    lstr = (lDebug ? "Apache(2.0#4)" : "Apache-2.0");
    lmem[_mAPACHE] = 1;
  }
  else if (URL_INFILE(_URL_Apache_20_1)) {
    lstr = (lDebug ? "Apache2(url#1)" : "Apache-2.0");
    lmem[_mAPACHE] = 1;
  }
  else if (URL_INFILE(_URL_Apache_20_2)) {
    lstr = (lDebug ? "Apache2(url#2)" : "Apache-2.0");
    lmem[_mAPACHE] = 1;
  }
  /*
   * Apache-1.1 cases
   */
  else if (INFILE(_SPDX_Apache_11)) {
    lstr = (lDebug ? "Apache-1.1(SPDX)" : "Apache-1.1");
    lmem[_mAPACHE11] = 1;
  }
  else if (INFILE(_TITLE_Apache_11)) {
    lstr = (lDebug ? "Apache-1.1(f)" : "Apache-1.1");
    lmem[_mAPACHE11] = 1;
  }
  else if (URL_INFILE(_URL_Apache_11)) {
    lstr = (lDebug ? "Apache-1.1(u)" : "Apache-1.1");
    lmem[_mAPACHE11] = 1;
  }
  else if (INFILE(_LT_Apache_11_CLAUSE_3) && INFILE(_LT_Apache_11_CLAUSE_4) && INFILE(_LT_Apache_11_CLAUSE_5)) {
    lstr = (lDebug ? "Apache-1.1(clauses)" : "Apache-1.1");
    lmem[_mAPACHE11] = 1;
  }
  else if (INFILE(_PHR_Apache_11_ref1)) {
    lstr = (lDebug ? "Apache(1.1#phr)" : "Apache-1.1");
    lmem[_mAPACHE11] = 1;
  }
  /*
   * Apache-1.0 cases
   */
  else if (INFILE(_SPDX_Apache_10)) {
    lstr = (lDebug ? "Apache-1.0(SPDX)" : "Apache-1.0");
    lmem[_mAPACHE10] = 1;
  }
  else if (INFILE(_PHR_Apache_ref2)) {
    lstr = (lDebug ? "Apache-1.0(f)" : "Apache-1.0");
    lmem[_mAPACHE10] = 1;
  }
  else if (INFILE(_LT_Apache_10_CLAUSE_4)) {
    lstr = (lDebug ? "Apache-1.0(g)" : "Apache-1.0");
    lmem[_mAPACHE10] = 1;
  }
  else if (URL_INFILE(_URL_Apache_10)) {
    lstr = (lDebug ? "Apache-1.0(u)" : "Apache-v1.0");
    lmem[_mAPACHE10] = 1;
  }
  /*
   * BSD-style cases
   */
  else if (INFILE(_LT_BSD_1)) {
    if (INFILE(_CR_APACHE) || INFILE(_TITLE_Apache)) {
      if (INFILE(_PHR_Apache_20_ref6)) {
        lstr = (lDebug ? "Apache-20_ref6" : "Apache-2.0");
        lmem[_mAPACHE] = 1;
      }
      else if (INFILE(_PHR_Apache_11_ref2)) {
        lstr = (lDebug ? "Apache(1.1#2)" : "Apache-1.1");
        lmem[_mAPACHE11] = 1;
      }
      else if ((INFILE(_PHR_Apache_ref2) || INFILE(_LT_Apache_10_CLAUSE_4))) {
        lstr = (lDebug ? "Apache(1.0#2)" : "Apache-1.0");
        lmem[_mAPACHE10] = 1;
      }
      else {
        lstr = (lDebug ? "Apache(title)" : "Apache");
        lmem[_mAPACHE] = 1;
      }
    }
  }
  /*
   * Apache without versions
   */
  else if (!lmem[_fREAL] && INFILE(_SPDX_Apache)) {
    lstr = (lDebug ? "Apache(SPDX)" : "Apache");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_PHR_Apache_ref1)) {
    INTERESTING(lDebug ? "Apache(ref#1)" : "Apache");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_PHR_Apache_ref4)) {
    lstr = (lDebug ? "Apache(ref#3)" : "Apache");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_PHR_Apache_ref3)) {
    lstr = (lDebug ? "Apache(ref#4)" : "Apache");
    lmem[_mAPACHE] = 1;
  }
  else if (URL_INFILE(_URL_Apache_1)) {
    lstr = (lDebug ? "Apache(url#1)" : "Apache");
    lmem[_mAPACHE] = 1;
  }
  else if (URL_INFILE(_URL_Apache_2)) {
    lstr = (lDebug ? "Apache(url#2)" : "Apache");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_PHR_Apache_ref6)) {
    lstr = (lDebug ? "Apache(ref#6)" : "Apache");
    lmem[_mAPACHE] = 1;
  }
  /*
   * _LT_Apache_1 and _2 cannot be identified in any Apache license
   * versions. They have been defined in very early nomos versions. They
   * are kept here, although, there are no special test cases for them.
   */
  else if (INFILE(_LT_Apache_1)) {
    lstr = (lDebug ? "Apache(1)" : "Apache");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_LT_Apache_2)) {
    lstr = (lDebug ? "Apache(2)" : "Apache");
    lmem[_mAPACHE] = 1;
  }
  else if (INFILE(_LT_APACHESTYLEref)) {
    lstr = ("Apache-style");
    lmem[_mAPACHE] = 1;
  }
  return lstr;
}

/**
 * \brief Check for MPL|NPL versions
 * \param filetext  File content
 * \param size      File size
 * \param isML      File is HTML/XML
 * \param isPS      File is PostScript
 * \return Return license shortname
 */
char *mplNplVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;

#ifdef  PROC_TRACE
  traceFunc("== mplNplVersion()\n");
#endif  /* PROC_TRACE */

  if (INFILE(_TITLE_MPL11_OR_LATER)) {
    lstr = "MPL-1.1+";
  }
  else if (INFILE(_LT_MPL11_ref)) {
    lstr = "MPL-1.1";
  }
  else if (INFILE(_TITLE_NPL11_MPL)) {
    lstr = "NPL-1.1";
  }
  else if (INFILE(_TITLE_MPL11) && INFILE(_TITLE_MPL_style)) {
    lstr = "MPL-1.1-style";
  }
  else if (INFILE(_TITLE_SUGARCRM_PL)) {
    lstr = "SugarCRM-1.1.3";
    lmem[_mMPL] = 1;
    lmem[_fATTRIB] = 1;
  }
  else if (INFILE(_TITLE_MPL11) && !HASTEXT(_LT_NP_AME, REG_EXTENDED)) {
    lstr = "MPL-1.1";
  }
  else if (INFILE(_TITLE_MPL20_EXCEPTION)) {
    lstr = "MPL-2.0-no-copyleft-exception";
  }
  else if (INFILE(_TITLE_MPL20) || URL_INFILE(_URL_MPL20) || HASTEXT(_LT_MPL20_ref, REG_EXTENDED)) {
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
  else if (INFILE(_TITLE_NPL11_OR_LATER)) {
    lstr = "NPL-1.1+";
  }
  else if (INFILE(_TITLE_NPL11)) {
    lstr = "NPL-1.1";
  }
  else if (INFILE(_TITLE_NPL10)) {
    lstr = "NPL-1.0";
  }
  else if (URL_INFILE(_URL_NPL)) {
    lstr = (lDebug ? "NPL(url)" : "NPL");
  }
  else if (INFILE(_SPDX_MPL_10)) {
    lstr = "MPL-1.0";
  }
  else if (INFILE(_SPDX_MPL_11)) {
    lstr = "MPL-1.1";
  }
  else if (INFILE(_SPDX_MPL_20_no_copyleft_exception)) {
    lstr = "MPL-2.0-no-copyleft-exception";
  }
  else if (INFILE(_SPDX_MPL_20)) {
    lstr = "MPL-2.0";
  }
  else if (URL_INFILE(_URL_MPL_LATEST)) {
    lstr = (lDebug ? "MPL(latest)" : "MPL");
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

/**
 * \brief Check for RPSL versions
 * \param filetext  File content
 * \param size      File size
 * \param isML      File is HTML/XML
 * \param isPS      File is PostScript
 * \return Return license shortname
 */
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

/**
 * \brief Check for python versions
 * \param filetext  File content
 * \param size      File size
 * \param isML      File is HTML/XML
 * \param isPS      File is PostScript
 * \return Return license shortname
 */
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
  else if (INFILE(_TITLE_PYTHON_20_1) || INFILE(_TITLE_PYTHON_20_2)) {
    lstr = "Python-2.0";
  }
  else {
    lstr = "Python";
  }
  return lstr;
}

/**
 * \brief Check for AFL versions
 * \param filetext  File content
 * \param size      File size
 * \param isML      File is HTML/XML
 * \param isPS      File is PostScript
 * \return Return license shortname
 */
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

/**
 * \brief Check for OSL versions
 * \param filetext  File content
 * \param size      File size
 * \param isML      File is HTML/XML
 * \param isPS      File is PostScript
 * \return Return license shortname
 */
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

/**
 * \brief Check for CDDL versions
 * \param filetext  File content
 * \param size      File size
 * \param isML      File is HTML/XML
 * \param isPS      File is PostScript
 * \return Return license shortname
 */
char *cddlVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;
  /* */
#ifdef  PROC_TRACE
  traceFunc("== cddlVersion()\n");
#endif  /* PROC_TRACE */
  /* */
  if (INFILE(_TITLE_CDDL_10)) {
    lstr = "CDDL-1.0";
  }
  else if (INFILE(_SPDX_CDDL_10)) {
    INTERESTING("CDDL-1.0");
  }
  else if (URL_INFILE(_URL_CDDL_10)) {
    lstr = "CDDL-1.0";
  }
  else if (INFILE(_TITLE_CDDL_11)) {
    lstr = "CDDL-1.1";
  }
  else if (INFILE(_SPDX_CDDL_11)) {
    INTERESTING("CDDL-1.1");
  }
  else if (URL_INFILE(_URL_CDDL)) {
    lstr = "CDDL";
  }
  else {
    lstr = "CDDL";
  }
  return lstr;
}

/**
 * \brief Check for LPPL versions
 * \param filetext  File content
 * \param size      File size
 * \param isML      File is HTML/XML
 * \param isPS      File is PostScript
 * \return Return license shortname
 */
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

/**
 * \brief Check for AGPL versions
 * \param filetext  File content
 * \param size      File size
 * \param isML      File is HTML/XML
 * \param isPS      File is PostScript
 * \return Return license shortname
 */
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
  if (INFILE(_PHR_AGPL_10_or_later)
      || INFILE(_TITLE_AGPL_10_or_later)
      || INFILE(_SPDX_AGPL_10_or_later)
      || HASTEXT(_SPDX_AGPL_10plus, REG_EXTENDED)
      || HASTEXT(_PHR_AGPL_10plus, REG_EXTENDED))
  {
    lstr = "AGPL-1.0-or-later";
  }
  else if (INFILE(_PHR_FSF_V1_ONLY)
      || INFILE(_TITLE_AGPL_10_only)
      || INFILE(_SPDX_AGPL_10))
  {
    lstr = "AGPL-1.0-only";
  }
  else if (INFILE(_PHR_AGPL_30_or_later)
      || INFILE(_TITLE_AGPL_30_or_later_ref1)
      || INFILE(_TITLE_AGPL_30_or_later)
      || INFILE(_SPDX_AGPL_30_or_later)
      || HASTEXT(_SPDX_AGPL_30plus, REG_EXTENDED)
      || HASTEXT(_PHR_AGPL_30plus, REG_EXTENDED))
  {
    if (INFILE(_LT_AGPL_30)) {
      lstr = lDebug ? "Affero-v3(#1)" : "AGPL-3.0-only";
    }
    else {
      lstr = "AGPL-3.0-or-later";
    }
  }
  else if (HASTEXT(_PHR_AGPL_30_1, REG_EXTENDED) || INFILE(_SPDX_AGPL_30)) {
    lstr = "AGPL-3.0-only";
  }
  else if (GPL_INFILE(_PHR_FSF_V3_ONLY)) {
    if (INFILE(_TITLE_GPL3)) {
      lstr = lDebug ? "GPLv3(Affero#1)" : "GPL-3.0-only";
    }
    else if (INFILE(_LT_GPL3ref3)){
      lstr = lDebug ? "GPLv3(special)" : "GPL-3.0-only";
    }
    else {
      lstr = lDebug ? "Affero-v3(#2)" : "AGPL-3.0-only";
    }
  }
  else if (INFILE(_TITLE_AGPL_30_only)) {
    lstr = lDebug ? "Affero-v3(#3)" : "AGPL-3.0-only";
  }
  else if (INFILE(_TITLE_GPL3)) {
    lstr = lDebug ? "GPLv3(Affero#2)" : "GPL-3.0-only";
  }
  else if (URL_INFILE(_URL_AGPL3)) {
    lstr = lDebug ? "Affero-v3(url)" : "AGPL-3.0-only";
  }
  else {
    lstr = "AGPL";
  }
  return lstr;
}

/**
 * \brief Check for GFDL versions
 * \param filetext  File content
 * \param size      File size
 * \param isML      File is HTML/XML
 * \param isPS      File is PostScript
 * \return Return license shortname
 */
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
  if (INFILE(_TITLE_GFDL_V13_FULL_LICENSE)) {
    lstr = "GFDL-1.3";
    /* Full GFDL-1.3 license text has a reference to Creative Commons */
    if (HASTEXT(_LT_CC_ref, REG_EXTENDED)) {
      lmem[_fCCBY] = 1;
    }
  }
  else if (INFILE(_TITLE_GFDL_V13_OR_LATER)) {
    lstr = "GFDL-1.3-or-later";
  }
  else if (INFILE(_TITLE_GFDL_V13_ONLY)) {
    lstr = lDebug ? "GFDL-1.3(#1)" : "GFDL-1.3";
  }
  else if (INFILE(_TITLE_GFDL_V12_FULL_LICENSE)) {
    lstr = lDebug ? "GFDL-1.2-only(#1)" : "GFDL-1.2-only";
  }
  else if (INFILE(_PHR_FSF_V12_OR_LATER) ||
      INFILE(_TITLE_GFDL_V12_OR_LATER)) {
    lstr = "GFDL-1.2-or-later";
  }
  else if (INFILE(_TITLE_GFDL_V12_ONLY)) {
    lstr = lDebug ? "GFDL-1.2-only(#1)" : "GFDL-1.2-only";
  }
  else if (INFILE(_TITLE_GFDL_V11_FULL_LICENSE)) {
    lstr = lDebug ? "GFDL-1.1-only(#1)" : "GFDL-1.1-only";
  }
  else if (INFILE(_PHR_FSF_V11_OR_LATER) ||
      INFILE(_TITLE_GFDL_V11_OR_LATER)) {
    lstr = "GFDL-1.1-or-later";
  }
  else if (INFILE(_TITLE_GFDL_V11_ONLY)) {
    lstr = lDebug ? "GFDL-1.1-only(#1)" : "GFDL-1.1-only";
  }
  else if (INFILE(_PHR_FSF_V12_ONLY)) {
    lstr = lDebug ? "GFDL-1.2-only(#2)" : "GFDL-1.2-only";
  }
  else if (INFILE(_PHR_FSF_V11_ONLY)) {
    lstr = lDebug ? "GFDL-1.1-only(#2)" : "GFDL-1.1-only";
  }
  else {
    lstr = "GFDL";
  }
  return lstr;
}

/**
 * \brief Check for LGPL versions
 * \param filetext  File content
 * \param size      File size
 * \param isML      File is HTML/XML
 * \param isPS      File is PostScript
 * \return Return license shortname
 */
char *lgplVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;
  /* */
#ifdef  PROC_TRACE
  traceFunc("== lgplVersion()\n");
#endif  /* PROC_TRACE */
  /* */
  if ((INFILE(_PHR_LGPL21_OR_LATER_1)
      || INFILE(_PHR_LGPL21_OR_LATER_2)
      || HASTEXT(_PHR_LGPL21_OR_LATER_3, REG_EXTENDED)
      || HASTEXT(_PHR_LGPL21_OR_LATER_4, REG_EXTENDED))
      && !HASTEXT(_LT_IGNORE_CLAUSE, REG_EXTENDED))
  {
    if (INFILE(_TITLE_LGPL_KDE)) {
      lstr = "LGPL-2.1-or-later-KDE-exception";
    }
    else {
      lstr = "LGPL-2.1-or-later";
    }
  }
  else if ((INFILE(_PHR_LGPL3_OR_LATER)
      || INFILE(_PHR_LGPL3_OR_LATER_ref1)
      || INFILE(_PHR_LGPL3_OR_LATER_ref2)
      || HASTEXT(_PHR_LGPL3_OR_LATER_ref3, REG_EXTENDED)
      || HASTEXT(_SPDX_LGPL_30plus, REG_EXTENDED)
      || HASTEXT(_PHR_LGPL_30plus, REG_EXTENDED))
      && !HASTEXT(_LT_IGNORE_CLAUSE, REG_EXTENDED))
  {
    lstr = "LGPL-3.0-or-later";
  }
  else if (INFILE(_LT_LGPL3ref) && NOT_INFILE(_PHR_NOT_UNDER_LGPL)) {
    lstr = "LGPL-3.0-only";
    lmem[_mLGPL] = 1;
  }
  else if (GPL_INFILE(_PHR_LGPL3_ONLY)
      || INFILE(_FILE_LGPLv3)
      || GPL_INFILE(_PHR_LGPL3_ONLY_ref1)
      || GPL_INFILE(_PHR_LGPL3_ONLY_ref2))
  {
    lstr = "LGPL-3.0-only";
  }
  else if (INFILE(_PHR_LGPL21_ONLY)
      || INFILE(_FILE_LGPLv21)
      || URL_INFILE(_URL_LGPL_V21)
      || INFILE(_PHR_LGPL21_ONLY_ref)
      || INFILE(_PHR_LGPL21_ONLY_ref2)
      || INFILE(_PHR_LGPL21_ONLY_ref3)
      || INFILE(_PHR_LGPL21_ONLY_ref4))
  {
    lstr = "LGPL-2.1-only";
  }
  else if ((INFILE(_PHR_LGPL2_OR_LATER)
      || HASTEXT(_PHR_LGPL2_OR_LATER_2, REG_EXTENDED)
      || HASTEXT(_PHR_LGPL2_OR_LATER_3, REG_EXTENDED))
      && !HASTEXT(_LT_IGNORE_CLAUSE, REG_EXTENDED))
  {
    lstr = "LGPL-2.0-or-later";
  }
  else if (RM_INFILE(_PHR_LGPL2_ONLY) || RM_INFILE(_PHR_LGPL2_ONLY_ref1) || INFILE(_FILE_LGPLv2)) {
    lstr = "LGPL-2.0-only";
  }
  else if (INFILE(_PHR_LGPL1_OR_LATER) && !HASTEXT(_LT_IGNORE_CLAUSE, REG_EXTENDED)) {
    lstr = "LGPL-1.0-or-later";
  }
  else if (INFILE(_PHR_LGPL1_ONLY) || INFILE(_PHR_FSF_V1_ONLY)) {
    lstr = "LGPL-1.0-only";
  }
  else if (URL_INFILE(_URL_CCLGPL_V21)) {
    lstr = lDebug ? "CC-LGPL-2.1" : "LGPL-2.1-only";
  }
  else if (INFILE(_LT_CC_GPL) || INFILE(_TITLE_CC_LGPL)) {
    lstr = "CC-LGPL";
  }
  else if (NY_INFILE(_TEXT_LGPLV3) && NOT_INFILE(_TEXT_LGPLV3_FOOTNOTE) &&
      HASREGEX(_TEXT_LGPLV3, filetext)) {
    lstr = lDebug ? "LGPL-v3(#2)" : "LGPL-3.0-only";
  }
  else if (INFILE(_TEXT_LGPLV21) &&
      HASREGEX(_TEXT_LGPLV21, filetext)) {
    lstr = lDebug ? "LGPL-v2.1(#2)" : "LGPL-2.1-only";
  }
  else if (INFILE(_TEXT_LGPLV2) &&
      HASREGEX(_TEXT_LGPLV2, filetext)) {
    lstr = lDebug ? "LGPL-v2(#2)" : "LGPL-2.0-only";
  }
  else if (INFILE(_SPDX_LGPL_20)) {
    lstr = "LGPL-2.0-only";
  }
  else if (INFILE(_SPDX_LGPL_21)) {
    lstr = "LGPL-2.1-only";
  }
  else if (INFILE(_SPDX_LGPL_30)) {
    lstr = "LGPL-3.0-only";
  }
  else {
    lstr = "LGPL";
  }
  return lstr;
}

/**
 * \brief Check for GPL versions
 * \param filetext  File content
 * \param size      File size
 * \param isML      File is HTML/XML
 * \param isPS      File is PostScript
 * \return Return license shortname
 */
char *gplVersion(char *filetext, int size, int isML, int isPS)
{
  char *cp, *lstr = NULL_STR;
  /* */
#ifdef  PROC_TRACE
  traceFunc("== gplVersion()\n");
#endif  /* PROC_TRACE */
  /* */

  /*
   * GPL-3.0-only cases
   */
  if (GPL_INFILE(_PHR_GPL3_OR_LATER_ref2)
      || GPL_INFILE(_PHR_GPL3_OR_LATER_ref3)
      || GPL_INFILE(_PHR_GPL3_OR_LATER)
      || GPL_INFILE(_PHR_GPL3_OR_LATER_ref1)
      || HASTEXT(_SPDX_GPL_30plus, REG_EXTENDED)
      || HASTEXT(_PHR_GPL_30plus, REG_EXTENDED))
  {
    if (!HASTEXT(_LT_IGNORE_CLAUSE, REG_EXTENDED)) {
      lstr = "GPL-3.0-or-later";
      if (INFILE(_PHR_GPL2_OR_LATER_1)) {
        lstr = "GPL-2.0-or-later,GPL-3.0-or-later";
      }
    }
  }
  else if (GPL_INFILE(_PHR_FSF_V3_ONLY)
      || GPL_INFILE(_PHR_GPL3_ONLY)
      || INFILE(_FILE_GPLv3)
      || GPL_INFILE(_PHR_GPL3_ONLY_ref1)
      || GPL_INFILE(_PHR_GPL3_ONLY_ref2)) {
    lstr = lDebug ? "GPL-v3(#2)" : "GPL-3.0-only";
    if (INFILE(_PHR_GPL2_OR_LATER_1))
    {
      lstr = "GPL-2.0-or-later,GPL-3.0-only";
    }
  }
  else if (NY_INFILE(_TEXT_GPLV3) && NOT_INFILE(_TEXT_GPLV3_FOOTNOTE) &&
      HASREGEX(_TEXT_GPLV3, filetext)) {
    lstr = lDebug ? "GPL-v3(#3)" : "GPL-3.0-only";
  }
  /*
   * GPL-2.0-only cases
   */
  else if (HASTEXT(_LT_GPL_V2_NAMED_later, REG_EXTENDED) || INFILE(_TITLE_GPL2_ref1_later)) {
    lstr = lDebug ? "GPLV2+(named)" : "GPL-2.0-or-later";
  }
  else if (HASTEXT(_SPDX_GPL_20_or_later, REG_EXTENDED)) {
    lstr = lDebug ? "GPL-2.0-or-later(SPDX)" : "GPL-2.0-or-later";
  }
  else if (INFILE(_PHR_GPL2_OR_LATER_1)) {
    if (INFILE(_TITLE_GPL_KDE)) {
      lstr = "GPL-2.0-or-laterKDEupgradeClause";
    }
    else if (INFILE(_PHR_GPL2_ONLY_2) || INFILE(_PHR_GPL2_ONLY_3)) {
      lstr = "GPL-2.0-only";
    }
    else if (!HASTEXT(_LT_IGNORE_CLAUSE, REG_EXTENDED)) {
      lstr = lDebug ? "PHR(GPL2_OR_LATER#3)" : "GPL-2.0-or-later";
    }
    else if (INFILE(_TITLE_GPL2_ref1) || INFILE(_TITLE_GPL2_ref2)) {
      lstr = lDebug ? "GPL-2.0-only(title)" : "GPL-2.0-only";
    }
  }
  else if (HASTEXT(_SPDX_GPL_20, REG_EXTENDED)) {
    lstr = lDebug ? "GPL-2.0-only(SPDX)" : "GPL-2.0-only";
  }
  else if (INFILE(_PHR_GPL2_ONLY_4)) {
    lstr = "GPL-2.0-only";
  }
  else if (INFILE(_PHR_GPL2_ONLY_5)) {
    lstr = "GPL-2.0-only";
  }
  else if (GPL_INFILE(_PHR_GPL2_OR_GPL3)) {
    lstr = "GPL-2.0-only,GPL-3.0-only";
  }
  else if (INFILE(_PHR_FSF_V2_ONLY) || INFILE(_PHR_GPL2_ONLY) || INFILE(_PHR_GPL2_ONLY_1) ||
      INFILE(_FILE_GPLv2) || INFILE(_LT_GPL_V2_NAMED)) {
    lstr = lDebug ? "GPL-v2(#2)" : "GPL-2.0-only";
  }
  else if (INFILE(_LT_GPL_V2_ref5)) {
    lstr = lDebug ? "GPL-2.0-only(ref5)" : "GPL-2.0-only";
  }
  else if (NY_INFILE(_TEXT_GPLV2)) {
    lstr = lDebug ? "GPL-v2(#3)" : "GPL-2.0-only";
  }
  /*
   * GPL-1.0-only cases
   */
  else if (GPL_INFILE(_PHR_FSF_V1_OR_LATER)
      || INFILE(_PHR_GPL1_OR_LATER)
      || HASTEXT(_SPDX_GPL_10plus, REG_EXTENDED)
      || HASTEXT(_PHR_GPL_10plus, REG_EXTENDED))
  {
    if (INFILE(_TITLE_GPL1)) {
      lstr = lDebug ? "GPL-v1(#1)" : "GPL-1.0-only";
    }
    else if (!HASTEXT(_LT_IGNORE_CLAUSE, REG_EXTENDED)) {
      lstr = "GPL-1.0-or-later";
    }
  }
  else if (INFILE(_PHR_FSF_V1_ONLY) || INFILE(_PHR_GPL1_ONLY)) {
    lstr = lDebug ? "GPL-v1(#2)" : "GPL-1.0-only";
  }
  else if (URL_INFILE(_URL_CCGPL_V2)) {
    lstr = "GPL-2.0-only";
  }
  else if (INFILE(_LT_CC_GPL) || INFILE(_TITLE_CC_GPL)) {
    lstr = lDebug ? "GPL(CC_GPL)" : "GPL";
  }
  else if (NY_INFILE(_TEXT_GPLV1) &&
      HASREGEX(_TEXT_GPLV1, filetext)) {
    lstr = lDebug ? "GPL-v1(#3)" : "GPL-1.0-only";
  }
  else if (HASTEXT(_SPDX_GPL_10, REG_EXTENDED)) {
    lstr = lDebug ? "GPL-1.0-only(SPDX)" : "GPL-1.0-only";
  }
  else if (HASTEXT(_SPDX_GPL_30, REG_EXTENDED)) {
    lstr = lDebug ? "GPL-3.0-only(SPDX)" : "GPL-3.0-only";
  }
  /* special case for Debian copyright files
     Moved from the beginning here under else if ... is this anymore needed
   */
  else if (INFILE(_TEXT_GPLV3_CR) && !HASTEXT(_LT_IGNORE_CLAUSE, REG_EXTENDED)) {
    lstr = "GPL-3.0-only";
  }
  else if (INFILE(_FILE_GPL1) || INFILE(_FILE_GPL2)) {
    lstr = lDebug ? "GPL(deb)" : "GPL";
  }
  /*
   * MODULE("GPL") cannot be unambiguously interpreted as GPL-2.0-only
   * license. Same statement is used also outside Linux kernel.
   * Furthermore, many of the files which have this MODULE statement,
   * have explicit GPL license statement. Therefore this is changed
   * to GPL.
   */
  else if (INFILE(_TITLE_MODULE_LICENSE_GPL)) {
    lstr = lDebug ? "GPL(linux-kernel)" : "GPL";
  }
  /*
   * Finally let's see if there is a type error in license version
   */
  else if (INFILE(_PHR_GPL21_OR_LATER) && !HASTEXT(_LT_IGNORE_CLAUSE, REG_EXTENDED)) {
    lstr = "GPL-2.1+[sic]";
  }
  else if (INFILE(_PHR_FSF_V21_ONLY) || INFILE(_PHR_GPL21_ONLY)) {
    lstr = lDebug ? "GPL-v2.1[sic]" : "GPL-2.1[sic]";
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
  if (lstr == NULL_STR && NOT_INFILE(_PHR_JYTHON_NOTGPL) && !HASTEXT(_TITLE_QT_GPL_EXCEPTION_10, 0) && !HASTEXT(_LT_OPENBSD_GPL_EXCEPTION, 0)) {
    lstr = lDebug ? "GPL(NULL)" : "GPL";
  }
  return lstr;
}

/**
 * \brief Check for CPL versions
 * \param filetext  File content
 * \param size      File size
 * \param isML      File is HTML/XML
 * \param isPS      File is PostScript
 * \return Return license shortname
 */
char *cplVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;
  /* */
#ifdef  PROC_TRACE
  traceFunc("== cplVersion()\n");
#endif  /* PROC_TRACE */
  /* */
  if (INFILE(_TITLE_CPL_10)) {
    lstr = "CPL-1.0";
  }
  else if (URL_INFILE(_URL_CPL_10)) {
    lstr = "CPL-1.0";
  }
  else if (INFILE(_TITLE_CPL_05)) {
    lstr = "CPL-0.5";
  }
  else {
    lstr = "CPL";
  }
  return lstr;
}

/**
 * \brief Check for CC_BY-X versions
 * \param filetext  File content
 * \param size      File size
 * \param isML      File is HTML/XML
 * \param isPS      File is PostScript
 * \return Return license shortname
 */
char *ccVersion(char *filetext, int size, int isML, int isPS)
{
  char *lstr = NULL_STR;
  /* */
#ifdef  PROC_TRACE
  traceFunc("== ccVersion()\n");
#endif  /* PROC_TRACE */
  /*
   * Creative Commons Attribution-ShareAlike
   */
  if (INFILE(_TITLE_CC_BY_SA_10) || URL_INFILE(_URL_CC_BY_SA_10)) {
    lstr = "CC-BY-SA-1.0";
  }
  else if (INFILE(_TITLE_CC_BY_SA_20) || URL_INFILE(_URL_CC_BY_SA_20)) {
    lstr = "CC-BY-SA-2.0";
  }
  else if (INFILE(_TITLE_CC_BY_SA_25) || URL_INFILE(_URL_CC_BY_SA_25)) {
    lstr = "CC-BY-SA-2.5";
  }
  else if (INFILE(_TITLE_CC_BY_SA_30) || URL_INFILE(_URL_CC_BY_SA_30)) {
    lstr = "CC-BY-SA-3.0";
  }
  else if (INFILE(_PHR_CC_BY_SA_30)) {
    lstr = "CC-BY-SA-3.0";
  }
  else if (INFILE(_TITLE_CC_BY_SA_40) || URL_INFILE(_URL_CC_BY_SA_40)) {
    lstr = "CC-BY-SA-4.0";
  }
  else if (URL_INFILE(_URL_CC_BY_SA_20)) {
    lstr = "CC-BY-SA-2.0";
  }
  else if (INFILE(_TITLE_CC_BY_SA) || URL_INFILE(_URL_CC_BY_SA)) {
    lstr = lDebug ? "CCA-SA(1)" : "CC-BY-SA";
  }
  /*
   * Creative Commons Attribution-NonCommercial-ShareAlike
   */
  else if (INFILE(_TITLE_CC_BY_NC_SA_40) || URL_INFILE(_URL_CC_BY_NC_SA_40)) {
    lstr = "CC-BY-NC-SA-4.0";
  }
  else if (INFILE(_TITLE_CC_BY_NC_SA_30) || URL_INFILE(_URL_CC_BY_NC_SA_30)) {
    lstr = "CC-BY-NC-SA-3.0";
  }
  else if (INFILE(_TITLE_CC_BY_NC_SA_25) || URL_INFILE(_URL_CC_BY_NC_SA_25)) {
    lstr = "CC-BY-NC-SA-2.5";
  }
  else if (INFILE(_TITLE_CC_BY_NC_SA_20) || URL_INFILE(_URL_CC_BY_NC_SA_20)) {
    lstr = "CC-BY-NC-SA-2.0";
  }
  else if (INFILE(_TITLE_CC_BY_NC_SA_10) || URL_INFILE(_URL_CC_BY_NC_SA_10)) {
    lstr = "CC-BY-NC-SA-1.0";
  }
  /*
   * Creative Commons NonCommercial NoDerivs
   */
  else if (INFILE(_TITLE_CC_BY_NC_ND_40) || URL_INFILE(_URL_CC_BY_NC_ND_40)) {
    lstr = "CC-BY-NC-ND-4.0";
  }
  else if (INFILE(_TITLE_CC_BY_NC_ND_30) || URL_INFILE(_URL_CC_BY_NC_ND_30)) {
    lstr = "CC-BY-NC-ND-3.0";
  }
  else if (INFILE(_TITLE_CC_BY_NC_ND_25) || URL_INFILE(_URL_CC_BY_NC_ND_25)) {
    lstr = "CC-BY-NC-ND-2.5";
  }
  else if (INFILE(_TITLE_CC_BY_NC_ND_20) || URL_INFILE(_URL_CC_BY_NC_ND_20)) {
    lstr = "CC-BY-NC-ND-2.0";
  }
  else if (INFILE(_TITLE_CC_BY_NC_ND_10) || INFILE(_TITLE_CC_BY_NC_ND_10_1) || URL_INFILE(_URL_CC_BY_NC_ND_10)) {
    lstr = "CC-BY-NC-ND-1.0";
  }
  /*
   * Creative Commons NonCommercial
   */
  else if (INFILE(_TITLE_CC_BY_NC_40) || URL_INFILE(_URL_CC_BY_NC_40)) {
      lstr = "CC-BY-NC-4.0";
  }
  else if (INFILE(_TITLE_CC_BY_NC_30) || URL_INFILE(_URL_CC_BY_NC_30)) {
      lstr = "CC-BY-NC-3.0";
  }
  else if (INFILE(_TITLE_CC_BY_NC_25) || URL_INFILE(_URL_CC_BY_NC_25)) {
      lstr = "CC-BY-NC-2.5";
  }
  else if (INFILE(_TITLE_CC_BY_NC_20) || URL_INFILE(_URL_CC_BY_NC_20)) {
      lstr = "CC-BY-NC-2.0";
  }
  else if (INFILE(_TITLE_CC_BY_NC_10) || URL_INFILE(_URL_CC_BY_NC_10)) {
      lstr = "CC-BY-NC-1.0";
  }
  /*
   * Creative Commons Attribution-NoDerivatives
   */
  else if (INFILE(_TITLE_CC_BY_ND_40) || URL_INFILE(_URL_CC_BY_ND_40)) {
      lstr = "CC-BY-ND-4.0";
  }
  else if (INFILE(_TITLE_CC_BY_ND_30) || URL_INFILE(_URL_CC_BY_ND_30)) {
      lstr = "CC-BY-ND-3.0";
  }
  else if (INFILE(_TITLE_CC_BY_ND_25) || URL_INFILE(_URL_CC_BY_ND_25)) {
      lstr = "CC-BY-ND-2.5";
  }
  else if (INFILE(_TITLE_CC_BY_ND_20) || URL_INFILE(_URL_CC_BY_ND_20)) {
      lstr = "CC-BY-ND-2.0";
  }
  else if (INFILE(_TITLE_CC_BY_ND_10) || URL_INFILE(_URL_CC_BY_ND_10)) {
    lstr = "CC-BY-ND-1.0";
  }
  /*
   * Creative Commons Attribution
   */
  else if (INFILE(_TITLE_CC_BY_10) || URL_INFILE(_URL_CC_BY_10)) {
    lstr = "CC-BY-1.0";
  }
  else if (INFILE(_TITLE_CC_BY_20) || URL_INFILE(_URL_CC_BY_20)) {
    lstr = "CC-BY-2.0";
  }
  else if (INFILE(_TITLE_CC_BY_25) || URL_INFILE(_URL_CC_BY_25)) {
    lstr = "CC-BY-2.5";
  }
  else if (INFILE(_TITLE_CC_BY_30) || URL_INFILE(_URL_CC_BY_30)) {
    lstr = "CC-BY-3.0";
  }
  else if (INFILE(_TITLE_CC_BY_40) || URL_INFILE(_URL_CC_BY_40)) {
    lstr = "CC-BY-4.0";
  }
  /*
   * Creative Commons CC0
   */
  else if (INFILE(_TITLE_CC0_10_2)) {
    lstr = lDebug ? "CC0(2)" : "CC0-1.0";
  }
  else if (INFILE(_PHR_CC0_2)) {
    lstr = lDebug ? "CC0(2)" : "CC0-1.0";
  }
  else if (URL_INFILE(_URL_CC0)) {
    lstr = lDebug ? "CC0(URL)" : "CC0-1.0";
  }
  else if (URL_INFILE(_URL_CC_PDDC)) {
    lstr = lDebug ? "CC(PD)" : "CC-PDDC";
    pd = 1;
  }
  else if (INFILE(_TITLE_CCPL)) {
    INTERESTING("CCPL");
  }
  else if (INFILE(_TITLE_CC_BY)) {
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
 * @param index index of the phrase to be searched for
 * @param filetext the text to search
 * @param size the size of file
 * @param isML Is HTML/XML file?
 * @param isPS Is postscript file?
 * @param qtype ??
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
    ret = HASREGEX_RI(index, sp->buf);
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
          NOT_INFILE(_TEXT_LGPL_DETAILS) &&
          NOT_INFILE(_TEXT_LICSET)) {
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
    /* locateRegex(filetext, op, index, size, sso, seo); */
  }
  return(ret);
}

/**
 * \brief Locate a regex in a given file
 *
 * Function first looks in raw text, then goes for doctored buffer if not found
 * in the file.
 *
 * Save location using saveRegexLocation()
 * \param text  Raw file to check
 * \param op    List
 * \param index Index of regex
 * \param size  Size of file
 * \param sso   Match start
 * \param seo   Match end
 */
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
      LOG_NOTICE("Nothing to trim from the front (*cp == NULL)");
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

/**
 * \brief Save a regex in whereList
 * \param index     Index of the regex
 * \param offset    Regex match start
 * \param length    Regex match length
 * \param saveCache Set YES to save in whChacheList
 */
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
  if(!(str && str[0]))
    return;

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

  addLicence(cur.theMatches,str);

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
    else if (INFILE(_TITLE_OPENLDAP201)) {
      INTERESTING("OLDAP-2.0.1");
      ret = 1;
    }
    else if (INFILE(_TITLE_OPENLDAP20)) {
      INTERESTING("OLDAP-2.0");
      ret = 1;
    }
    else if (INFILE(_TITLE_OPENLDAP21)) {
      INTERESTING("OLDAP-2.1");
      ret = 1;
    }
    else if (INFILE(_TITLE_OPENLDAP221) || INFILE(_SPDX_OLDAP_221)) {
      INTERESTING("OLDAP-2.2.1");
      ret = 1;
    }
    else if (INFILE(_TITLE_OPENLDAP222) || INFILE(_SPDX_OLDAP_222)) {
      INTERESTING("OLDAP-2.2.2");
      ret = 1;
    }
    else if (INFILE(_TITLE_OPENLDAP22)) {
      INTERESTING("OLDAP-2.2");
      ret = 1;
    }
    else if (INFILE(_TITLE_OPENLDAP11)) {
      INTERESTING("OLDAP-1.1");
      ret = 1;
    }
    else if (INFILE(_TITLE_OPENLDAP23)) {
      INTERESTING("OLDAP-2.3");
      ret = 1;
    }
    else if (INFILE(_TITLE_OPENLDAP24)) {
      INTERESTING("OLDAP-2.4");
      ret = 1;
    }
    else if (INFILE(_TITLE_OPENLDAP12)) {
      INTERESTING("OLDAP-1.2");
      ret = 1;
    }
    else if (INFILE(_TITLE_OPENLDAP13)) {
      INTERESTING("OLDAP-1.3");
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
  if (INFILE(_LT_TROLLTECH)) {
    return(1);
  }

  /*
   * A Generic EULA 'qualifies' as an UnclassifiedLicense, or the clause 'License agreement' as an UnclassifiedLicense, check this
   * one before trying the word-matching magic checks (below).
   */
  gl.flags |= FL_SAVEBASE; /* save match buffer (if any) */
  m = INFILE(_LT_GEN_EULA) || INFILE(_LT_LG) || INFILE(_LT_GENERIC_UNCLASSIFIED);
  /* gl.flags & ~FL_SAVEBASE;  CDB -- This makes no sense, given line above */
  if (m) {
    if (cur.licPara == NULL_STR  && cur.matchBase) {
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
        INTERESTING("See-file.COPYING");
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
        INTERESTING("See-file.LICENSE");
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
        INTERESTING("See-file.README");
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
        INTERESTING("See-doc.OTHER");
      }
      return;
    }
  }
  /* */
  if (INFILE(_LT_SEE_OUTPUT_1)) {
    INTERESTING(lDebug ? "Gen-EXC-1" : "GNU-style.EXECUTE");
  }
#if 0
  else if (INFILE(_LT_SEE_OUTPUT_2)) {
    INTERESTING(lDebug ? "Gen-EXC-2" : "Free-SW.run-COMMAND");
  } else if (INFILE(_LT_SEE_OUTPUT_3)) {
    INTERESTING(lDebug ? "Gen-EXC-3" : "Free-SW.run-COMMAND");
  }
#endif
  if(HASTEXT(_LT_SEE_COPYING_LICENSE_1, REG_EXTENDED) || HASTEXT(_LT_SEE_COPYING_LICENSE_2, REG_EXTENDED)) {
    INTERESTING("See-file");
  }
  else if (HASTEXT(_LT_SEE_URL, REG_EXTENDED) || HASTEXT(_LT_SEE_URL_ref1, REG_EXTENDED) || HASTEXT(_LT_SEE_URL_ref2, REG_EXTENDED) || HASTEXT(_LT_SEE_URL_ref3, REG_EXTENDED)) {
    INTERESTING("See-URL");
  }
  return;

#ifdef OLD_VERSION
  if (INFILE(_LT_SEE_COPYING_1)) {
    INTERESTING(lDebug ? "Gen-CPY-1" : "See-file.COPYING");
  }
  else if (INFILE(_LT_SEE_COPYING_2)) {
    INTERESTING(lDebug ? "Gen-CPY-2" : "See-file.COPYING");
  }
  else if (INFILE(_LT_SEE_COPYING_3)) {
    INTERESTING(lDebug ? "Gen-CPY-3" : "See-file.COPYING");
  }
  else if (INFILE(_LT_SEE_COPYING_4)) {
    INTERESTING(lDebug ? "Gen-CPY-4" : "See-file.COPYING");
  }
  else if (INFILE(_LT_SEE_COPYING_5)) {
    INTERESTING(lDebug ? "Gen-CPY-5" : "See-file.COPYING");
  }
  else if (INFILE(_LT_SEE_COPYING_6)) {
    INTERESTING(lDebug ? "Gen-CPY-6" : "See-file.COPYING");
  }
  else if (INFILE(_LT_SEE_COPYING_7)) {
    INTERESTING(lDebug ? "Gen-CPY-7" : "See-file.COPYING");
  }
  else if (INFILE(_LT_SEE_COPYING_8)) {
    INTERESTING(lDebug ? "Gen-CPY-8" : "See-file.COPYING");
  }
  else if (INFILE(_LT_SEE_COPYING_9)) {
    INTERESTING(lDebug ? "Gen-CPY-9" : "See-file.COPYING");
  }
  else if (INFILE(_LT_SEE_COPYING_10)) {
    INTERESTING(lDebug ? "Gen-CPY-10" : "See-file.COPYING");
  }
  else if (INFILE(_LT_SEE_COPYING_LAST1)) {
    INTERESTING(lDebug ? "Gen-CPY-L1" : "See-file.COPYING");
  }
  else if (INFILE(_LT_SEE_COPYING_LAST2)) {
    INTERESTING(lDebug ? "Gen-CPY-L2" : "See-file.COPYING");
  }
  else if (INFILE(_LT_SEE_LICENSE_1)) {
    INTERESTING(lDebug ? "Gen-LIC-1" : "See-file.LICENSE");
  }
  else if (INFILE(_LT_SEE_LICENSE_2)) {
    INTERESTING(lDebug ? "Gen-LIC-2" : "See-file.LICENSE");
  }
  else if (INFILE(_LT_SEE_LICENSE_3)) {
    INTERESTING(lDebug ? "Gen-LIC-3" : "See-file.LICENSE");
  }
  else if (INFILE(_LT_SEE_LICENSE_4)) {
    INTERESTING(lDebug ? "Gen-LIC-4" : "See-file.LICENSE");
  }
  else if (INFILE(_LT_SEE_LICENSE_5)) {
    INTERESTING(lDebug ? "Gen-LIC-5" : "See-file.LICENSE");
  }
  else if (INFILE(_LT_SEE_LICENSE_6)) {
    INTERESTING(lDebug ? "Gen-LIC-6" : "See-file.LICENSE");
  }
  else if (INFILE(_LT_SEE_LICENSE_7)) {
    INTERESTING(lDebug ? "Gen-LIC-7" : "See-file.LICENSE");
  }
  else if (INFILE(_LT_SEE_LICENSE_8)) {
    INTERESTING(lDebug ? "Gen-LIC-8" : "See-file.LICENSE");
  }
  else if (INFILE(_LT_SEE_LICENSE_9)) {
    INTERESTING(lDebug ? "Gen-LIC-9" : "See-file.LICENSE");
  }
  else if (INFILE(_LT_SEE_LICENSE_10)) {
    INTERESTING(lDebug ? "Gen-LIC-10" : "See-file.LICENSE");
  }
  else if (INFILE(_LT_SEE_LICENSE_LAST1)) {
    INTERESTING(lDebug ? "Gen-LIC-L1" : "See-file.LICENSE");
  }
  else if (INFILE(_LT_SEE_LICENSE_LAST2)) {
    INTERESTING(lDebug ? "Gen-LIC-L2" : "See-file.LICENSE");
  }
  else if (INFILE(_LT_SEE_README_1)) {
    INTERESTING(lDebug ? "Gen-RDM-1" : "See-file.README");
  }
  else if (INFILE(_LT_SEE_README_2)) {
    INTERESTING(lDebug ? "Gen-RDM-2" : "See-file.README");
  }
  else if (INFILE(_LT_SEE_README_3)) {
    INTERESTING(lDebug ? "Gen-RDM-3" : "See-file.README");
  }
  else if (INFILE(_LT_SEE_README_4)) {
    INTERESTING(lDebug ? "Gen-RDM-4" : "See-file.README");
  }
  else if (INFILE(_LT_SEE_README_5)) {
    INTERESTING(lDebug ? "Gen-RDM-5" : "See-file.README");
  }
  else if (INFILE(_LT_SEE_README_6)) {
    INTERESTING(lDebug ? "Gen-RDM-6" : "See-file.README");
  }
  else if (INFILE(_LT_SEE_README_7)) {
    INTERESTING(lDebug ? "Gen-RDM-7" : "See-file.README");
  }
  else if (INFILE(_LT_SEE_README_LAST1)) {
    INTERESTING(lDebug ? "Gen-RDM-L1" : "See-file.README");
  }
  else if (INFILE(_LT_SEE_README_LAST2)) {
    INTERESTING(lDebug ? "Gen-RDM-L2" : "See-file.README");
  }
  else if (INFILE(_LT_SEE_OTHER_1)) {
    INTERESTING(lDebug ? "Gen-OTH-1" : "See-doc.OTHER");
  }
  else if (INFILE(_LT_SEE_OTHER_2)) {
    INTERESTING(lDebug ? "Gen-OTH-2" : "See-doc.OTHER");
  }
  else if (INFILE(_LT_SEE_OTHER_3)) {
    INTERESTING(lDebug ? "Gen-OTH-3" : "See-doc.OTHER");
  }
  else if (INFILE(_LT_SEE_OTHER_4)) {
    INTERESTING(lDebug ? "Gen-OTH-4" : "See-doc.OTHER");
  }
  else if (INFILE(_LT_SEE_OTHER_5)) {
    INTERESTING(lDebug ? "Gen-OTH-5" : "See-doc.OTHER");
  }
  else if (INFILE(_LT_SEE_OTHER_6)) {
    INTERESTING(lDebug ? "Gen-OTH-6" : "See-doc.OTHER");
  }
  else if (INFILE(_LT_SEE_OTHER_7)) {
    INTERESTING(lDebug ? "Gen-OTH-7" : "See-doc.OTHER");
  }
  else if (INFILE(_LT_SEE_OTHER_8)) {
    INTERESTING(lDebug ? "Gen-OTH-8" : "See-doc.OTHER");
  }
  else if (INFILE(_LT_SEE_OTHER_9)) {
    INTERESTING(lDebug ? "Gen-OTH-9" : "See-doc.OTHER");
  }
  else if (INFILE(_LT_SEE_OTHER_10)) {
    INTERESTING(lDebug ? "Gen-OTH-10" : "See-doc.OTHER");
  }
  else if (INFILE(_LT_SEE_OTHER_11)) {
    INTERESTING(lDebug ? "Gen-OTH-11" : "See-doc.OTHER");
  }
  else if (INFILE(_LT_SEE_OTHER_12)) {
    INTERESTING(lDebug ? "Gen-OTH-12" : "See-doc.OTHER");
  }
  else if (INFILE(_LT_SEE_OTHER_13)) {
    INTERESTING(lDebug ? "Gen-OTH-13" : "See-doc.OTHER");
  }
  else if (INFILE(_LT_SEE_OTHER_14)) {
    INTERESTING(lDebug ? "Gen-OTH-14" : "See-doc.OTHER");
  }
  else if (INFILE(_LT_SEE_OTHER_15)) {
    INTERESTING(lDebug ? "Gen-OTH-15" : "See-doc.OTHER");
  }
  else if (INFILE(_LT_SEE_OTHER_LAST1)) {
    INTERESTING(lDebug ? "Gen-OTH-L1" : "See-doc.OTHER");
  }
  else if (INFILE(_LT_SEE_OTHER_LAST2)) {
    INTERESTING(lDebug ? "Gen-OTH-L2" : "See-doc.OTHER");
  }
  else if (INFILE(_LT_SEE_OTHER_LAST3)) {
    INTERESTING(lDebug ? "Gen-OTH-L3" : "See-doc.OTHER");
  }
  else if (INFILE(_LT_SEE_OUTPUT_1)) {
    INTERESTING(lDebug ? "Gen-EXC-1" : "GNU-style.interactive");
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
    INTERESTING(lDebug ? "Pubdom(CC)" : "CC-PDDC");
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
  } else if (HASTEXT(_LT_PUBDOM_NOTclaim, REG_EXTENDED)) {
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
  } else if (INFILE(_LT_UNLIN) || URL_INFILE(_URL_UNLINref) || URL_INFILE(_URL_UNLIN)) {
    INTERESTING("Unlicense");
    ret = 1;
  } else if (INFILE(_LT_PUBDOM_1)) {
    INTERESTING(lDebug ? "Pubdom(1)" : LS_PD_CLM);
    ret = 1;
  } else if (INFILE(_LT_PUBDOM_2) && NOT_INFILE(_PHR_PUBLIC_FUNCT) && NOT_INFILE(_LT_NOTPUBDOM_1)) {
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
  } else if (INFILE(_LT_PUBDOM_9)) {
    INTERESTING(lDebug ? "Pubdom(9)" : LS_PD_CLM);
    ret = 1;
  } else if (INFILE(_LT_PUBDOM_10)) {
      if (INFILE(_LT_blessing)) {
        INTERESTING(lDebug ? "Pubdom(10)" : "blessing");
      }
      else {
        INTERESTING(lDebug ? "Pubdom(10)" : LS_PD_CLM);
      }
    ret = 1;
  } else if (INFILE(_URL_PUBDOM)) {
    INTERESTING(lDebug ? "Pubdom(URL)" : LS_PD_CLM);
    ret = 1;
  } else if (HASKW(kwbm, _KW_public_domain) && score <= 3) {
    INTERESTING(LS_PD_ONLY);
    ret = 1;
  }
  return(ret);
}


/**
 * \brief If we call this function, we still don't know anything about a license.
 *
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
  /**
   * @todo Remove this code block and respective phrase from STRINGS.in later
   *
   * Trademark detection removed. It gave too many false positives.Code left
   * because more experiences are needed about the consequences.
   */
  /*
  if (!(*licStr)) {
    if (HASTEXT(_TEXT_TRADEMARK, 0)) {
      LOWINTEREST(LS_TDMKONLY);
    }
  }
  */
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
    if (checknw && !idxGrep(checknw, cp, REG_ICASE|REG_EXTENDED)) {
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
      if (idxGrep_recordPositionDoctored(i + _KW_first, cp, REG_EXTENDED | REG_ICASE)) {
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
  char *start;
  int index=0;
  int len;
  start =  copyString(mtext, MTAG_TEXTPARA);
  if(!start)
  {
    LOG_FATAL("called saveLicenseParagraph without text")
    Bail(-__LINE__);
  }
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
      index = cur.regm.rm_so - 50;
    }
    cur.licPara = memAlloc(len + 9, MTAG_TEXTPARA);
    (void) strcpy(cur.licPara, "... ");
    (void) strncpy(cur.licPara + 4, start + index, len);
    (void) strcpy(cur.licPara + len + 4, " ...");
    memFree(start, MTAG_TEXTPARA);
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

/**
 * SPDX license references
 *
 * Note that many license references have been detected earlier:
 * - BSD, CNRI-Python, Intel and MIT variants,
 * - CC_BY_SA, CECILL, MPL, ZPL, AGPL, GPL and LGPL versions
 * - ICU, TCL
 */
void spdxReference(char *filetext, int size, int isML, int isPS)
{
  if (INFILE(_SPDX_Glide)) {
    INTERESTING("Glide");
  }
  if (INFILE(_SPDX_Abstyles)) {
    INTERESTING("Abstyles");
  }
  if (INFILE(_SPDX_AFL_11)) {
    INTERESTING("AFL-1.1");
  }
  if (INFILE(_SPDX_AFL_12)) {
    INTERESTING("AFL-1.2");
  }
  if (INFILE(_SPDX_AFL_20)) {
    INTERESTING("AFL-2.0");
  }
  if (INFILE(_SPDX_AFL_21)) {
    INTERESTING("AFL-2.1");
  }
  if (INFILE(_SPDX_AFL_30)) {
    INTERESTING("AFL-3.0");
  }
  if (INFILE(_SPDX_AMPAS)) {
    INTERESTING("AMPAS");
  }
  if (INFILE(_SPDX_APL_10)) {
    INTERESTING("APL-1.0");
  }
  if (INFILE(_SPDX_Adobe_Glyph)) {
    INTERESTING("Adobe-Glyph");
  }
  if (INFILE(_SPDX_APAFML)) {
    INTERESTING("APAFML");
  }
  if (INFILE(_SPDX_Adobe_2006)) {
    INTERESTING("Adobe-2006");
  }
  if (INFILE(_SPDX_Afmparse)) {
    INTERESTING("Afmparse");
  }
  if (INFILE(_SPDX_Aladdin)) {
    INTERESTING("Aladdin");
  }
  if (INFILE(_SPDX_ADSL)) {
    INTERESTING("ADSL");
  }
  if (INFILE(_SPDX_AMDPLPA)) {
    INTERESTING("AMDPLPA");
  }
  if (INFILE(_SPDX_ANTLR_PD)) {
    INTERESTING("ANTLR-PD");
  }
  if (INFILE(_SPDX_AML)) {
    INTERESTING("AML");
  }
  if (INFILE(_SPDX_APSL_10)) {
    INTERESTING("APSL-1.0");
  }
  if (INFILE(_SPDX_APSL_11)) {
    INTERESTING("APSL-1.1");
  }
  if (INFILE(_SPDX_APSL_12)) {
    INTERESTING("APSL-1.2");
  }
  if (INFILE(_SPDX_APSL_20)) {
    INTERESTING("APSL-2.0");
  }
  if (INFILE(_SPDX_Artistic_10_Perl)) {
    INTERESTING("Artistic-1.0-Perl");
  }
  else if (INFILE(_SPDX_Artistic_10_cl8)) {
    INTERESTING("Artistic-1.0-cl8");
  }
  else if (INFILE(_SPDX_Artistic_10)) {
    INTERESTING("Artistic-1.0");
  }
  if (INFILE(_SPDX_Artistic_20)) {
    INTERESTING("Artistic-2.0");
  }
  if (INFILE(_SPDX_AAL)) {
    INTERESTING("AAL");
  }
  if (INFILE(_SPDX_Bahyph)) {
    INTERESTING("Bahyph");
  }
  if (INFILE(_SPDX_Barr)) {
    INTERESTING("Barr");
  }
  if (INFILE(_SPDX_Beerware)) {
    INTERESTING("Beerware");
  }
  if (INFILE(_SPDX_BitTorrent_10)) {
    INTERESTING("BitTorrent-1.0");
  }
  else if (INFILE(_SPDX_BitTorrent_11)) {
    INTERESTING("BitTorrent-1.1");
  }
  if (INFILE(_SPDX_blessing)) {
    INTERESTING("blessing");
  }
  if (INFILE(_SPDX_BlueOak_100)) {
    INTERESTING("BlueOak-1.0.0");
  }
  if (INFILE(_SPDX_BSL_10)) {
    INTERESTING("BSL-1.0");
  }
  if (INFILE(_SPDX_Borceux)) {
    INTERESTING("Borceux");
  }
  if (INFILE(_SPDX_0BSD)) {
    INTERESTING("0BSD");
  }
  if (INFILE(_SPDX_bzip2_105)) {
    INTERESTING("bzip2-1.0.5");
  }
  else if (INFILE(_SPDX_bzip2_106)) {
    INTERESTING("bzip2-1.0.6");
  }
  if (INFILE(_SPDX_Caldera)) {
    INTERESTING("Caldera");
  }
  if (INFILE(_SPDX_CC_PDDC)) {
    INTERESTING("CC-PDDC");
  }
  if (INFILE(_SPDX_CERN_OHL_P_20)) {
    INTERESTING("CERN-OHL-P-2.0");
  }
  else if (INFILE(_SPDX_CERN_OHL_S_20)) {
    INTERESTING("CERN-OHL-S-2.0");
  }
  else if (INFILE(_SPDX_CERN_OHL_W_20)) {
    INTERESTING("CERN-OHL-W-2.0");
  }
  else if (INFILE(_SPDX_CERN_OHL_12)) {
    INTERESTING("CERN-OHL-1.2");
  }
  else if (INFILE(_SPDX_CERN_OHL_11)) {
    INTERESTING("CERN-OHL-1.1");
  }
  if (INFILE(_SPDX_ClArtistic)) {
    INTERESTING("ClArtistic");
  }
  if (INFILE(_SPDX_CNRI_Jython)) {
    INTERESTING("CNRI-Jython");
  }
  if (INFILE(_SPDX_CPOL_102)) {
    INTERESTING("CPOL-1.02");
  }
  if (INFILE(_SPDX_CPAL_10)) {
    INTERESTING("CPAL-1.0");
  }
  if (INFILE(_SPDX_CPL_10)) {
    INTERESTING("CPL-1.0");
  }
  if (INFILE(_SPDX_CAL_10_Combined_Work_Exception)) {
    INTERESTING("CAL-1.0-Combined-Work-Exception");
  }
  else if (INFILE(_SPDX_CAL_10)) {
    INTERESTING("CAL-1.0");
  }
  if (INFILE(_SPDX_CATOSL_11)) {
    INTERESTING("CATOSL-1.1");
  }
  if (INFILE(_SPDX_Condor_11)) {
    INTERESTING("Condor-1.1");
  }
  if (INFILE(_SPDX_CC_BY_10)) {
    INTERESTING("CC-BY-1.0");
  }
  else if (INFILE(_SPDX_CC_BY_20)) {
    INTERESTING("CC-BY-2.0");
  }
  else if (INFILE(_SPDX_CC_BY_25)) {
    INTERESTING("CC-BY-2.5");
  }
  else if (INFILE(_SPDX_CC_BY_30_AT)) {
    INTERESTING("CC-BY-3.0-AT");
  }
  else if (INFILE(_SPDX_CC_BY_30)) {
    INTERESTING("CC-BY-3.0");
  }
  else if (INFILE(_SPDX_CC_BY_40)) {
    INTERESTING("CC-BY-4.0");
  }
  if (INFILE(_SPDX_CC_BY_ND_10)) {
    INTERESTING("CC-BY-ND-1.0");
  }
  else if (INFILE(_SPDX_CC_BY_ND_20)) {
    INTERESTING("CC-BY-ND-2.0");
  }
  else if (INFILE(_SPDX_CC_BY_ND_25)) {
    INTERESTING("CC-BY-ND-2.5");
  }
  else if (INFILE(_SPDX_CC_BY_ND_30)) {
    INTERESTING("CC-BY-ND-3.0");
  }
  else if (INFILE(_SPDX_CC_BY_ND_40)) {
    INTERESTING("CC-BY-ND-4.0");
  }
  if (INFILE(_SPDX_CC_BY_NC_10)) {
    INTERESTING("CC-BY-NC-1.0");
  }
  else if (INFILE(_SPDX_CC_BY_NC_20)) {
    INTERESTING("CC-BY-NC-2.0");
  }
  else if (INFILE(_SPDX_CC_BY_NC_25)) {
    INTERESTING("CC-BY-NC-2.5");
  }
  else if (INFILE(_SPDX_CC_BY_NC_30)) {
    INTERESTING("CC-BY-NC-3.0");
  }
  else if (INFILE(_SPDX_CC_BY_NC_40)) {
    INTERESTING("CC-BY-NC-4.0");
  }
  if (INFILE(_SPDX_CC_BY_NC_ND_10)) {
    INTERESTING("CC-BY-NC-ND-1.0");
  }
  else if (INFILE(_SPDX_CC_BY_NC_ND_20)) {
    INTERESTING("CC-BY-NC-ND-2.0");
  }
  else if (INFILE(_SPDX_CC_BY_NC_ND_25)) {
    INTERESTING("CC-BY-NC-ND-2.5");
  }
  else if (INFILE(_SPDX_CC_BY_NC_ND_30_IGO)) {
    INTERESTING("CC-BY-NC-ND-3.0-IGO");
  }
  else if (INFILE(_SPDX_CC_BY_NC_ND_30)) {
    INTERESTING("CC-BY-NC-ND-3.0");
  }
  else if (INFILE(_SPDX_CC_BY_NC_ND_40)) {
    INTERESTING("CC-BY-NC-ND-4.0");
  }
  if (INFILE(_SPDX_CC_BY_NC_SA_10)) {
    INTERESTING("CC-BY-NC-SA-1.0");
  }
  else if (INFILE(_SPDX_CC_BY_NC_SA_20)) {
    INTERESTING("CC-BY-NC-SA-2.0");
  }
  else if (INFILE(_SPDX_CC_BY_NC_SA_25)) {
    INTERESTING("CC-BY-NC-SA-2.5");
  }
  else if (INFILE(_SPDX_CC_BY_NC_SA_30)) {
    INTERESTING("CC-BY-NC-SA-3.0");
  }
  else if (INFILE(_SPDX_CC_BY_NC_SA_40)) {
    INTERESTING("CC-BY-NC-SA-4.0");
  }
  if (INFILE(_SPDX_CC_BY_SA_10)) {
    INTERESTING("CC-BY-SA-1.0");
  }
  else if (INFILE(_SPDX_CC_BY_SA_20)) {
    INTERESTING("CC-BY-SA-2.0");
  }
  else if (INFILE(_SPDX_CC_BY_SA_25)) {
    INTERESTING("CC-BY-SA-2.5");
  }
  else if (INFILE(_SPDX_CC_BY_SA_30_AT)) {
    INTERESTING("CC-BY-SA-3.0-AT");
  }
  else if (INFILE(_SPDX_CC_BY_SA_30)) {
    INTERESTING("CC-BY-SA-3.0");
  }
  else if (INFILE(_SPDX_CC_BY_SA_40)) {
    INTERESTING("CC-BY-SA-4.0");
  }
  if (INFILE(_SPDX_CDLA_Permissive_10)) {
    INTERESTING("CDLA-Permissive-1.0");
  }
  if (INFILE(_SPDX_CDLA_Sharing_10)) {
    INTERESTING("CDLA-Sharing-1.0");
  }
  if (INFILE(_SPDX_Crossword)) {
    INTERESTING("Crossword");
  }
  if (INFILE(_SPDX_CrystalStacker)) {
    INTERESTING("CrystalStacker");
  }
  if (INFILE(_SPDX_CUA_OPL_10)) {
    INTERESTING("CUA-OPL-1.0");
  }
  if (INFILE(_SPDX_Cube)) {
    INTERESTING("Cube");
  }
  if (INFILE(_SPDX_curl)) {
    INTERESTING("curl");
  }
  if (INFILE(_SPDX_D_FSL_10)) {
    INTERESTING("D-FSL-1.0");
  }
  if (INFILE(_SPDX_diffmark)) {
    INTERESTING("diffmark");
  }
  if (INFILE(_SPDX_WTFPL)) {
    INTERESTING("WTFPL");
  }
  if (HASTEXT(_SPDX_DOC, REG_EXTENDED) || HASTEXT(_PHR_DOC, REG_EXTENDED)) {
    INTERESTING("DOC");
  }
  if (INFILE(_SPDX_Dotseqn)) {
    INTERESTING("Dotseqn");
  }
  if (INFILE(_SPDX_DSDP)) {
    INTERESTING("DSDP");
  }
  if (INFILE(_SPDX_dvipdfm)) {
    INTERESTING("dvipdfm");
  }
  if (INFILE(_SPDX_EPL_10)) {
    INTERESTING("EPL-1.0");
  }
  else if (INFILE(_SPDX_EPL_20)) {
    INTERESTING("EPL-2.0");
  }
  if (INFILE(_SPDX_ECL_10)) {
    INTERESTING("ECL-1.0");
  }
  if (INFILE(_SPDX_ECL_20)) {
    INTERESTING("ECL-2.0");
  }
  if (INFILE(_SPDX_eGenix)) {
    INTERESTING("eGenix");
  }
  if (INFILE(_SPDX_EFL_10)) {
    INTERESTING("EFL-1.0");
  }
  if (INFILE(_SPDX_EFL_20)) {
    INTERESTING("EFL-2.0");
  }
  if (INFILE(_SPDX_Entessa)) {
    INTERESTING("Entessa");
  }
  if (INFILE(_SPDX_EPICS)) {
    INTERESTING("EPICS");
  }
  if (INFILE(_SPDX_ErlPL_11)) {
    INTERESTING("ErlPL-1.1");
  }
  if (INFILE(_SPDX_etalab_20)) {
    INTERESTING("etalab-2.0");
  }
  if (INFILE(_SPDX_EUDatagrid)) {
    INTERESTING("EUDatagrid");
  }
  if (INFILE(_SPDX_EUPL_10)) {
    INTERESTING("EUPL-1.0");
  }
  else if (INFILE(_SPDX_EUPL_11)) {
    INTERESTING("EUPL-1.1");
  }
  else if (INFILE(_SPDX_EUPL_12)) {
    INTERESTING("EUPL-1.2");
  }
  if (INFILE(_SPDX_Eurosym)) {
    INTERESTING("Eurosym");
  }
  if (HASTEXT(_SPDX_Fair, REG_EXTENDED) || HASTEXT(_PHR_Fair, REG_EXTENDED)) {
    INTERESTING("Fair");
  }
  if (INFILE(_SPDX_Frameworx_10)) {
    INTERESTING("Frameworx-1.0");
  }
  if (INFILE(_SPDX_FreeImage)) {
    INTERESTING("FreeImage");
  }
  if (INFILE(_SPDX_FSFAP)) {
    INTERESTING("FSFAP");
  }
  if (INFILE(_SPDX_FSFULLR)) {
    INTERESTING("FSFULLR");
  }
  else if (INFILE(_SPDX_FSFUL)) {
    INTERESTING("FSFUL");
  }
  if (INFILE(_SPDX_Giftware)) {
    INTERESTING("Giftware");
  }
  if (INFILE(_SPDX_GL2PS)) {
    INTERESTING("GL2PS");
  }
  if (INFILE(_SPDX_Glulxe)) {
    INTERESTING("Glulxe");
  }
  if (INFILE(_SPDX_GFDL_11_invariants_or_later)) {
    INTERESTING("GFDL-1.1-invariants-or-later");
  }
  else if (INFILE(_SPDX_GFDL_11_no_invariants_or_later)) {
    INTERESTING("GFDL-1.1-no-invariants-or-later");
  }
  else if (INFILE(_SPDX_GFDL_11_invariants_only)) {
    INTERESTING("GFDL-1.1-invariants-only");
  }
  else if (INFILE(_SPDX_GFDL_11_no_invariants_only)) {
    INTERESTING("GFDL-1.1-no-invariants-only");
  }
  else if (INFILE(_SPDX_GFDL_11_or_later)
      || HASTEXT(_SPDX_GFDL_11plus, REG_EXTENDED)
      || HASTEXT(_PHR_GFDL_11plus, REG_EXTENDED))
  {
    INTERESTING("GFDL-1.1-or-later");
  }
  else if (INFILE(_SPDX_GFDL_11)) {
    INTERESTING("GFDL-1.1-only");
  }
  else if (INFILE(_SPDX_GFDL_12_invariants_or_later)) {
    INTERESTING("GFDL-1.2-invariants-or-later");
  }
  else if (INFILE(_SPDX_GFDL_12_no_invariants_or_later)) {
    INTERESTING("GFDL-1.2-no-invariants-or-later");
  }
  else if (INFILE(_SPDX_GFDL_12_invariants_only)) {
    INTERESTING("GFDL-1.2-invariants-only");
  }
  else if (INFILE(_SPDX_GFDL_12_no_invariants_only)) {
    INTERESTING("GFDL-1.2-no-invariants-only");
  }
  else if (INFILE(_SPDX_GFDL_12_or_later)
      || HASTEXT(_SPDX_GFDL_12plus, REG_EXTENDED)
      || HASTEXT(_PHR_GFDL_12plus, REG_EXTENDED))
  {
    INTERESTING("GFDL-1.2-or-later");
  }
  else if (INFILE(_SPDX_GFDL_12)) {
    INTERESTING("GFDL-1.2-only");
  }
  else if (INFILE(_SPDX_GFDL_13_invariants_or_later)) {
    INTERESTING("GFDL-1.3-invariants-or-later");
  }
  else if (INFILE(_SPDX_GFDL_13_no_invariants_or_later)) {
    INTERESTING("GFDL-1.3-no-invariants-or-later");
  }
  else if (INFILE(_SPDX_GFDL_13_invariants_only)) {
    INTERESTING("GFDL-1.3-invariants-only");
  }
  else if (INFILE(_SPDX_GFDL_13_no_invariants_only)) {
    INTERESTING("GFDL-1.3-no-invariants-only");
  }
  else if (INFILE(_SPDX_GFDL_13_or_later)
      || HASTEXT(_SPDX_GFDL_13plus, REG_EXTENDED)
      || HASTEXT(_PHR_GFDL_13plus, REG_EXTENDED))
  {
    INTERESTING("GFDL-1.3-or-later");
  }
  else if (INFILE(_SPDX_GFDL_13)) {
    INTERESTING("GFDL-1.3");
  }
  if (INFILE(_SPDX_GLWTPL)) {
    INTERESTING("GLWTPL");
  }
  if (INFILE(_SPDX_gnuplot)) {
    INTERESTING("gnuplot");
  }
  if (INFILE(_SPDX_gSOAP_13b)) {
    INTERESTING("gSOAP-1.3b");
  }
  if (INFILE(_SPDX_HaskellReport)) {
    INTERESTING("HaskellReport");
  }
  if (INFILE(_SPDX_Hippocratic_21)) {
    INTERESTING("Hippocratic-2.1");
  }
  if (INFILE(_SPDX_HPND_sell_variant)) {
    INTERESTING("HPND-sell-variant");
  }
  else if (INFILE(_SPDX_HPND)) {
    INTERESTING("HPND");
  }
  if (INFILE(_SPDX_IBM_pibs)) {
    INTERESTING("IBM-pibs");
  }
  if (INFILE(_SPDX_IPL_10)) {
    INTERESTING("IPL-1.0");
  }
  if (INFILE(_SPDX_ImageMagick)) {
    INTERESTING("ImageMagick");
  }
  if (INFILE(_SPDX_iMatix)) {
    INTERESTING("iMatix");
  }
  if (INFILE(_SPDX_Imlib2)) {
    INTERESTING("Imlib2");
  }
  if (INFILE(_SPDX_IJG)) {
    INTERESTING("IJG");
  }
  if (INFILE(_SPDX_Info_ZIP)) {
    INTERESTING("Info-ZIP");
  }
  if (INFILE(_SPDX_Interbase_10)) {
    INTERESTING("Interbase-1.0");
  }
  if (INFILE(_SPDX_IPA)) {
    INTERESTING("IPA");
  }
  if (INFILE(_SPDX_ISC)) {
    INTERESTING("ISC");
  }
  if (INFILE(_SPDX_JasPer_20)) {
    INTERESTING("JasPer-2.0");
  }
  if (INFILE(_SPDX_JPNIC)) {
    INTERESTING("JPNIC");
  }
  if (INFILE(_SPDX_JSON)) {
    INTERESTING("JSON");
  }
  if (INFILE(_SPDX_Latex2e)) {
    INTERESTING("Latex2e");
  }
  if (INFILE(_SPDX_Leptonica)) {
    INTERESTING("Leptonica");
  }
  if (INFILE(_SPDX_LGPLLR)) {
    INTERESTING("LGPLLR");
  }
  if (INFILE(_SPDX_libpng_20)) {
    INTERESTING("libpng-2.0");
  }
  else if (INFILE(_SPDX_Libpng)) {
    INTERESTING("Libpng");
  }
  if (INFILE(_SPDX_libselinux_10)) {
    INTERESTING("libselinux-1.0");
  }
  if (INFILE(_SPDX_libtiff)) {
    INTERESTING("libtiff");
  }
  if (INFILE(_SPDX_LAL_12)) {
    INTERESTING("LAL-1.2");
  }
  if (INFILE(_SPDX_LAL_13)) {
    INTERESTING("LAL-1.3");
  }
  if (INFILE(_SPDX_LiLiQ_P_11)) {
    INTERESTING("LiLiQ-P-1.1");
  }
  if (INFILE(_SPDX_LiLiQ_Rplus_11)) {
    INTERESTING("LiLiQ-Rplus-1.1");
  }
  if (INFILE(_SPDX_LiLiQ_R_11)) {
    INTERESTING("LiLiQ-R-1.1");
  }
  if (INFILE(_SPDX_Linux_OpenIB)) {
    INTERESTING("Linux-OpenIB");
  }
  if (INFILE(_SPDX_LPL_102)) {
    INTERESTING("LPL-1.02");
  }
  else if (INFILE(_SPDX_LPL_10)) {
    INTERESTING("LPL-1.0");
  }
  if (INFILE(_SPDX_LPPL_10)) {
    INTERESTING("LPPL-1.0");
  }
  if (INFILE(_SPDX_LPPL_11)) {
    INTERESTING("LPPL-1.1");
  }
  if (INFILE(_SPDX_LPPL_12)) {
    INTERESTING("LPPL-1.2");
  }
  if (INFILE(_SPDX_LPPL_13a)) {
    INTERESTING("LPPL-1.3a");
  }
  if (INFILE(_SPDX_LPPL_13c)) {
    INTERESTING("LPPL-1.3c");
  }
  if (INFILE(_SPDX_MakeIndex)) {
    INTERESTING("MakeIndex");
  }
  if (INFILE(_SPDX_MTLL)) {
    INTERESTING("MTLL");
  }
  if (INFILE(_SPDX_MS_PL)) {
    INTERESTING("MS-PL");
  }
  if (INFILE(_SPDX_MS_RL)) {
    INTERESTING("MS-RL");
  }
  if (INFILE(_SPDX_MirOS)) {
    INTERESTING("MirOS");
  }
  if (INFILE(_SPDX_MITNFA)) {
    INTERESTING("MITNFA");
  }
  if (!lmem[_fREAL] && INFILE(_SPDX_Motosoto)) {
    INTERESTING("Motosoto");
  }
  if (INFILE(_SPDX_mpich2)) {
    INTERESTING("mpich2");
  }
  if (INFILE(_SPDX_MulanPSL_20)) {
    INTERESTING("MulanPSL-2.0");
  }
  else if (INFILE(_SPDX_MulanPSL_10)) {
    INTERESTING("MulanPSL-1.0");
  }
  if (INFILE(_SPDX_Multics)) {
    INTERESTING("Multics");
  }
  if (INFILE(_SPDX_Mup)) {
    INTERESTING("Mup");
  }
  if (INFILE(_SPDX_NASA_13)) {
    INTERESTING("NASA-1.3");
  }
  if (INFILE(_SPDX_Naumen)) {
    INTERESTING("Naumen");
  }
  if (INFILE(_SPDX_NBPL_10)) {
    INTERESTING("NBPL-1.0");
  }
  if (INFILE(_SPDX_NCGL_UK_20)) {
    INTERESTING("NCGL-UK-2.0");
  }
  if (INFILE(_SPDX_Net_SNMP)) {
    INTERESTING("Net-SNMP");
  }
  if (INFILE(_SPDX_NetCDF)) {
    INTERESTING("NetCDF");
  }
  if (INFILE(_SPDX_NGPL)) {
    INTERESTING("NGPL");
  }
  if (INFILE(_SPDX_NIST_PD_fallback)) {
    INTERESTING("NIST-PD-fallback");
  }
  else if (INFILE(_SPDX_NIST_PD)) {
    INTERESTING("NIST-PD");
  }
  if (INFILE(_SPDX_NOSL)) {
    INTERESTING("NOSL");
  }
  if (INFILE(_SPDX_NPL_10)) {
    INTERESTING("NPL-1.0");
  }
  if (INFILE(_SPDX_NPL_11)) {
    INTERESTING("NPL-1.1");
  }
  if (INFILE(_SPDX_Newsletr)) {
    INTERESTING("Newsletr");
  }
  if (INFILE(_SPDX_NLPL)) {
    INTERESTING("NLPL");
  }
  if (INFILE(_SPDX_Nokia) && NOT_INFILE(_LT_OPENSSL_NOKIA)) {
    INTERESTING("Nokia");
  }
  if (INFILE(_SPDX_NPOSL_30)) {
    INTERESTING("NPOSL-3.0");
  }
  if (INFILE(_SPDX_NLOD_10)) {
    INTERESTING("NLOD-1.0");
  }
  if (INFILE(_SPDX_Noweb)) {
    INTERESTING("Noweb");
  }
  if (INFILE(_SPDX_NRL)) {
    INTERESTING("NRL");
  }
  if (INFILE(_SPDX_NTP_0)) {
    INTERESTING("NTP-0");
  }
  else if (INFILE(_SPDX_NTP)) {
    INTERESTING("NTP");
  }
  if (INFILE(_SPDX_Nunit)) {
    INTERESTING("Nunit");
  }
  if (INFILE(_SPDX_O_UDA_10)) {
    INTERESTING("O-UDA-1.0");
  }
  if (INFILE(_SPDX_OCLC_20)) {
    INTERESTING("OCLC-2.0");
  }
  if (INFILE(_SPDX_ODbL_10)) {
    INTERESTING("ODbL-1.0");
  }
  if (INFILE(_SPDX_OGC_10)) {
    INTERESTING("OGC-1.0");
  }
  if (INFILE(_SPDX_PDDL_10)) {
    INTERESTING("PDDL-1.0");
  }
  if (INFILE(_SPDX_OCCT_PL)) {
    INTERESTING("OCCT-PL");
  }
  if (INFILE(_SPDX_ODC_By_10)) {
    INTERESTING("ODC-By-1.0");
  }
  if (INFILE(_SPDX_OGL_Canada_20)) {
    INTERESTING("OGL-Canada-2.0");
  }
  if (INFILE(_SPDX_OGL_UK_10)) {
    INTERESTING("OGL-UK-1.0");
  }
  else if (INFILE(_SPDX_OGL_UK_20)) {
    INTERESTING("OGL-UK-2.0");
  }
  else if (INFILE(_SPDX_OGL_UK_30)) {
    INTERESTING("OGL-UK-3.0");
  }
  if (INFILE(_SPDX_OGTSL)) {
    INTERESTING("OGTSL");
  }
  if (INFILE(_SPDX_OLDAP_11)) {
    INTERESTING("OLDAP-1.1");
  }
  else if (INFILE(_SPDX_OLDAP_12)) {
    INTERESTING("OLDAP-1.2");
  }
  else if (INFILE(_SPDX_OLDAP_13)) {
    INTERESTING("OLDAP-1.3");
  }
  else if (INFILE(_SPDX_OLDAP_14)) {
    INTERESTING("OLDAP-1.4");
  }
  else if (INFILE(_SPDX_OLDAP_201)) {
    INTERESTING("OLDAP-2.0.1");
  }
  else if (INFILE(_SPDX_OLDAP_20)) {
    INTERESTING("OLDAP-2.0");
  }
  else if (INFILE(_SPDX_OLDAP_21)) {
    INTERESTING("OLDAP-2.1");
  }
  else if (INFILE(_SPDX_OLDAP_221)) {
    INTERESTING("OLDAP-2.2.1");
  }
  else if (INFILE(_SPDX_OLDAP_222)) {
    INTERESTING("OLDAP-2.2.2");
  }
  else if (INFILE(_SPDX_OLDAP_22)) {
    INTERESTING("OLDAP-2.2");
  }
  else if (INFILE(_SPDX_OLDAP_23)) {
    INTERESTING("OLDAP-2.3");
  }
  else if (INFILE(_SPDX_OLDAP_24)) {
    INTERESTING("OLDAP-2.4");
  }
  else if (INFILE(_SPDX_OLDAP_25)) {
    INTERESTING("OLDAP-2.5");
  }
  else if (INFILE(_SPDX_OLDAP_26)) {
    INTERESTING("OLDAP-2.6");
  }
  else if (INFILE(_SPDX_OLDAP_27)) {
    INTERESTING("OLDAP-2.7");
  }
  else if (INFILE(_SPDX_OLDAP_28)) {
    INTERESTING("OLDAP-2.8");
  }
  if (INFILE(_SPDX_OML)) {
    INTERESTING("OML");
  }
  if (INFILE(_SPDX_OPL_10)) {
    INTERESTING("OPL-1.0");
  }
  if (INFILE(_SPDX_OSL_10)) {
    INTERESTING("OSL-1.0");
  }
  if (INFILE(_SPDX_OSL_11)) {
    INTERESTING("OSL-1.1");
  }
  if (INFILE(_SPDX_OSL_20)) {
    INTERESTING("OSL-2.0");
  }
  if (INFILE(_SPDX_OSL_21)) {
    INTERESTING("OSL-2.1");
  }
  if (INFILE(_SPDX_OSL_30)) {
    INTERESTING("OSL-3.0");
  }
  if (INFILE(_SPDX_OSET_PL_21)) {
    INTERESTING("OSET-PL-2.1");
  }
  if (INFILE(_SPDX_Parity_700)) {
    INTERESTING("Parity-7.0.0");
  }
  else if (INFILE(_SPDX_Parity_600)) {
    INTERESTING("Parity-6.0.0");
  }
  if (INFILE(_SPDX_PHP_301)) {
    INTERESTING("PHP-3.01");
  }
  else if (INFILE(_SPDX_PHP_30)) {
    INTERESTING("PHP-3.0");
  }
  if (INFILE(_SPDX_Plexus)) {
    INTERESTING("Plexus");
  }
  if (INFILE(_SPDX_PolyForm_Noncommercial_100)) {
    INTERESTING("PolyForm-Noncommercial-1.0.0");
  }
  else if (INFILE(_SPDX_PolyForm_Small_Business_100)) {
    INTERESTING("PolyForm-Small-Business-1.0.0");
  }
  if (INFILE(_SPDX_PostgreSQL)) {
    INTERESTING("PostgreSQL");
  }
  if (INFILE(_SPDX_PSF_20)) {
    INTERESTING("PSF-2.0");
  }
  if (INFILE(_SPDX_psfrag)) {
    INTERESTING("psfrag");
  }
  if (INFILE(_SPDX_psutils)) {
    INTERESTING("psutils");
  }
  if (INFILE(_SPDX_Python_20)) {
    INTERESTING("Python-2.0");
  }
  if (INFILE(_SPDX_QPL_10)) {
    INTERESTING("QPL-1.0");
  }
  if (INFILE(_SPDX_Qhull)) {
    INTERESTING("Qhull");
  }
  if (INFILE(_SPDX_Rdisc)) {
    INTERESTING("Rdisc");
  }
  if (INFILE(_SPDX_RPSL_10)) {
    INTERESTING("RPSL-1.0");
  }
  if (INFILE(_SPDX_RPL_11)) {
    INTERESTING("RPL-1.1");
  }
  if (INFILE(_SPDX_RPL_15)) {
    INTERESTING("RPL-1.5");
  }
  if (INFILE(_SPDX_RHeCos_11)) {
    INTERESTING("RHeCos-1.1");
  }
  if (INFILE(_SPDX_RSCPL)) {
    INTERESTING("RSCPL");
  }
  if (INFILE(_SPDX_RSA_MD)) {
    INTERESTING("RSA-MD");
  }
  if (INFILE(_SPDX_Ruby)) {
    INTERESTING("Ruby");
  }
  if (INFILE(_SPDX_SAX_PD)) {
    INTERESTING("SAX-PD");
  }
  if (INFILE(_SPDX_Saxpath)) {
    INTERESTING("Saxpath");
  }
  if (INFILE(_SPDX_SHL_051)) {
    INTERESTING("SHL-0.51");
  }
  else if (INFILE(_SPDX_SHL_05)) {
    INTERESTING("SHL-0.5");
  }
  if (INFILE(_SPDX_SCEA)) {
    INTERESTING("SCEA");
  }
  if (INFILE(_SPDX_SWL)) {
    INTERESTING("SWL");
  }
  if (INFILE(_SPDX_SMPPL)) {
    INTERESTING("SMPPL");
  }
  if (INFILE(_SPDX_Sendmail_823)) {
    INTERESTING("Sendmail-8.23");
  }
  else if (INFILE(_SPDX_Sendmail)) {
    INTERESTING("Sendmail");
  }
  if (INFILE(_SPDX_SGI_B_10)) {
    INTERESTING("SGI-B-1.0");
  }
  if (INFILE(_SPDX_SGI_B_11)) {
    INTERESTING("SGI-B-1.1");
  }
  if (INFILE(_SPDX_SGI_B_20)) {
    INTERESTING("SGI-B-2.0");
  }
  if (INFILE(_SPDX_SimPL_20)) {
    INTERESTING("SimPL-2.0");
  }
  if (INFILE(_SPDX_Sleepycat)) {
    INTERESTING("Sleepycat");
  }
  if (INFILE(_SPDX_SNIA)) {
    INTERESTING("SNIA");
  }
  if (INFILE(_SPDX_Spencer_86)) {
    INTERESTING("Spencer-86");
  }
  if (INFILE(_SPDX_Spencer_94)) {
    INTERESTING("Spencer-94");
  }
  if (INFILE(_SPDX_Spencer_99)) {
    INTERESTING("Spencer-99");
  }
  if (INFILE(_SPDX_SMLNJ)) {
    INTERESTING("SMLNJ");
  }
  if (INFILE(_SPDX_SSH_OpenSSH)) {
    INTERESTING("SSH-OpenSSH");
  }
  if (INFILE(_SPDX_SSH_short)) {
    INTERESTING("SSH-short");
  }
  if (INFILE(_SPDX_SSPL_10)) {
    INTERESTING("SSPL-1.0");
  }
  if (INFILE(_SPDX_SugarCRM_113)) {
    INTERESTING("SugarCRM-1.1.3");
  }
  if (INFILE(_SPDX_SISSL_12)) {
    INTERESTING("SISSL-1.2");
  }
  else if (!lmem[_fREAL] && INFILE(_SPDX_SISSL)) {
    INTERESTING("SISSL");
  }
  if (INFILE(_SPDX_SPL_10)) {
    INTERESTING("SPL-1.0");
  }
  if (INFILE(_SPDX_Watcom_10)) {
    INTERESTING("Watcom-1.0");
  }
  if (INFILE(_SPDX_TAPR_OHL_10)) {
    INTERESTING("TAPR-OHL-1.0");
  }
  if (INFILE(_SPDX_TCP_wrappers)) {
    INTERESTING("TCP-wrappers");
  }
  if (INFILE(_SPDX_Unlicense)) {
    INTERESTING("Unlicense");
  }
  if (INFILE(_SPDX_TMate)) {
    INTERESTING("TMate");
  }
  if (INFILE(_SPDX_TORQUE_11)) {
    INTERESTING("TORQUE-1.1");
  }
  if (INFILE(_SPDX_TOSL)) {
    INTERESTING("TOSL");
  }
  if (INFILE(_SPDX_TU_Berlin_10)) {
    INTERESTING("TU-Berlin-1.0");
  }
  else if (INFILE(_SPDX_TU_Berlin_20)) {
    INTERESTING("TU-Berlin-2.0");
  }
  if (INFILE(_SPDX_UCL_10)) {
    INTERESTING("UCL-1.0");
  }
  if (INFILE(_SPDX_Unicode_DFS_2015)) {
    INTERESTING("Unicode-DFS-2015");
  }
  if (INFILE(_SPDX_Unicode_DFS_2016)) {
    INTERESTING("Unicode-DFS-2016");
  }
  if (INFILE(_SPDX_Unicode_TOU)) {
    INTERESTING("Unicode-TOU");
  }
  if (INFILE(_SPDX_UPL_10)) {
    INTERESTING("UPL-1.0");
  }
  if (INFILE(_SPDX_NCSA)) {
    INTERESTING("NCSA");
  }
  if (INFILE(_SPDX_Vim)) {
    INTERESTING("Vim");
  }
  if (INFILE(_SPDX_VOSTROM)) {
    INTERESTING("VOSTROM");
  }
  if (INFILE(_SPDX_VSL_10)) {
    INTERESTING("VSL-1.0");
  }
  if (INFILE(_SPDX_W3C_20150513)) {
    INTERESTING("W3C-20150513");
  }
  else if (INFILE(_SPDX_W3C_19980720)) {
    INTERESTING("W3C-19980720");
  }
  else if (INFILE(_SPDX_W3C)) {
    INTERESTING("W3C");
  }
  if (INFILE(_SPDX_Wsuipa)) {
    INTERESTING("Wsuipa");
  }
  if (!lmem[_fREAL] && INFILE(_SPDX_Xnet)) {
    INTERESTING("Xnet");
  }
  if (INFILE(_SPDX_X11)) {
    INTERESTING("X11");
  }
  if (INFILE(_SPDX_Xerox)) {
    INTERESTING("Xerox");
  }
  if (INFILE(_SPDX_XFree86_11)) {
    INTERESTING("XFree86-1.1");
  }
  if (INFILE(_SPDX_xinetd)) {
    INTERESTING("xinetd");
  }
  if (INFILE(_SPDX_xpp)) {
    INTERESTING("xpp");
  }
  if (INFILE(_SPDX_XSkat)) {
    INTERESTING("XSkat");
  }
  if (INFILE(_SPDX_YPL_10)) {
    INTERESTING("YPL-1.0");
  }
  if (INFILE(_SPDX_YPL_11)) {
    INTERESTING("YPL-1.1");
  }
  if (INFILE(_SPDX_Zed)) {
    INTERESTING("Zed");
  }
  if (INFILE(_SPDX_Zend_20)) {
    INTERESTING("Zend-2.0");
  }
  if (INFILE(_SPDX_Zimbra_13)) {
    INTERESTING("Zimbra-1.3");
  }
  if (INFILE(_SPDX_Zimbra_14)) {
    INTERESTING("Zimbra-1.4");
  }
  return;
}

/**
 * Find copyleft exceptions
 *
 */
void copyleftExceptions(char *filetext, int size, int isML, int isPS)
{
  if (INFILE(_SPDX_389_exception)) {
    INTERESTING("389-exception");
  }
  if (INFILE(_SPDX_Autoconf_exception_20)) {
    INTERESTING("Autoconf-exception-2.0");
  }
  if (INFILE(_SPDX_Autoconf_exception_30)) {
    INTERESTING("Autoconf-exception-3.0");
  }
  if (INFILE(_SPDX_Bison_exception_22)) {
    INTERESTING("Bison-exception-2.2");
  }
  if (INFILE(_SPDX_Bootloader_exception)) {
    INTERESTING("Bootloader-exception");
  }
  if (INFILE(_SPDX_Classpath_exception_20)) {
    INTERESTING("Classpath-exception-2.0");
  }
  if (INFILE(_SPDX_CLISP_exception_20)) {
    INTERESTING("CLISP-exception-2.0");
  }
  if (INFILE(_SPDX_DigiRule_FOSS_exception)) {
    INTERESTING("DigiRule-FOSS-exception");
  }
  if (INFILE(_SPDX_eCos_exception_20)) {
    INTERESTING("eCos-exception-2.0");
  }
  if (INFILE(_SPDX_Fawkes_Runtime_exception)) {
    INTERESTING("Fawkes-Runtime-exception");
  }
  if (INFILE(_SPDX_FLTK_exception)) {
    INTERESTING("FLTK-exception");
  }
  if (INFILE(_SPDX_Font_exception_20)) {
    INTERESTING("Font-exception-2.0");
  }
  if (INFILE(_SPDX_freertos_exception_20)) {
    INTERESTING("freertos-exception-2.0");
  }
  if (INFILE(_SPDX_GCC_exception_20)) {
    INTERESTING("GCC-exception-2.0");
  }
  if (INFILE(_SPDX_GCC_exception_31)) {
    INTERESTING("GCC-exception-3.1");
  }
  if (INFILE(_SPDX_gnu_javamail_exception)) {
    INTERESTING("gnu-javamail-exception");
  }
  if (INFILE(_SPDX_i2p_gpl_java_exception)) {
    INTERESTING("i2p-gpl-java-exception");
  }
  if (INFILE(_SPDX_Libtool_exception)) {
    INTERESTING("Libtool-exception");
  }
  if (INFILE(_SPDX_Linux_syscall_note)) {
    INTERESTING("Linux-syscall-note");
  }
  if (INFILE(_SPDX_LLVM_exception)) {
    INTERESTING("LLVM-exception");
  }
  if (INFILE(_SPDX_LZMA_exception)) {
    INTERESTING("LZMA-exception");
  }
  if (INFILE(_SPDX_mif_exception)) {
    INTERESTING("mif-exception");
  }
  if (INFILE(_SPDX_Nokia_Qt_exception_11)) {
    INTERESTING("Nokia-Qt-exception-1.1");
  }
  if (INFILE(_SPDX_OCCT_exception_10)) {
    INTERESTING("OCCT-exception-1.0");
  }
  if (INFILE(_SPDX_OpenJDK_assembly_exception_10)) {
    INTERESTING("OpenJDK-assembly-exception-1.0");
  }
  if (INFILE(_SPDX_openvpn_openssl_exception)) {
    INTERESTING("openvpn-openssl-exception");
  }
  if (INFILE(_SPDX_Qwt_exception_10)) {
    INTERESTING("Qwt-exception-1.0");
  }
  if (INFILE(_SPDX_u_boot_exception_20)) {
    INTERESTING("u-boot-exception-2.0");
  }
  if (INFILE(_SPDX_WxWindows_exception_31)) {
    INTERESTING("WxWindows-exception-3.1");
  }
  /*
   * Find exception phrases. There are similar phrases
   * in exception clauses. Therefore 'else if' structure
   * has to be used to get correct detections.
   */
  if (INFILE(_LT_389_exception)) {
    INTERESTING("389-exception");
  }
  else if (INFILE(_LT_Autoconf_exception_20)) {
    INTERESTING("Autoconf-exception-2.0");
  }
  else if (INFILE(_LT_GPL_EXCEPT_5) && INFILE(_LT_Autoconf_exception_30)) {
    INTERESTING("Autoconf-exception-3.0");
  }
  else if (INFILE(_PHR_Autoconf_exception_30)) {
    INTERESTING("Autoconf-exception-3.0");
  }
  else if (INFILE(_LT_Autoconf_exception_3)) {
    INTERESTING("Autoconf-exception");
  }
  else if (INFILE(_LT_Bison_exception_22)) {
    INTERESTING("Bison-exception-2.2");
  }
  else if (INFILE(_LT_Bison_exception_1) || INFILE(_LT_Bison_exception_2)) {
    INTERESTING("Bison-exception");
  }
  else if (INFILE(_LT_Bootloader_exception)) {
    INTERESTING("Bootloader-exception");
  }
  /* Contains similar text to classpath-exception */
  else if (INFILE(_LT_OpenJDK_assembly_exception_10_1) || HASTEXT(_LT_OpenJDK_assembly_exception_10_2, 0)) {
    INTERESTING("OpenJDK-assembly-exception-1.0");
  }
  else if (INFILE(_LT_GPL_EXCEPT_6)) {
    if (INFILE(_LT_mif_exception)) {
      INTERESTING("Fawkes-Runtime-exception");
    }
    else {
      INTERESTING("Classpath-exception-2.0");
    }
  }
  else if (INFILE(_LT_classpath_exception_1)) {
    INTERESTING("Classpath-exception-2.0");
  }
  else if (HASTEXT(_LT_CLISP_exception_20_1, 0) && INFILE(_LT_CLISP_exception_20_2)) {
    INTERESTING("CLISP-exception-2.0");
  }
  else if (HASTEXT(_TITLE_DigiRule_FOSS_exception, 0) || INFILE(_LT_DigiRule_FOSS_exception)) {
    INTERESTING("DigiRule-FOSS-exception");
  }
  else if (INFILE(_LT_eCos_exception) && INFILE(_LT_GPL_EXCEPT_7)) {
    INTERESTING("eCos-exception-2.0");
  }
  else if (HASTEXT(_LT_FLTK_exception, 0)) {
    INTERESTING("FLTK-exception");
  }
  else if (HASTEXT(_TEXT_FONT, REG_EXTENDED) || INFILE(_LT_FONT_EXCEPTION_20)) {
    INTERESTING("Font-exception-2.0");
  }
  else if (HASTEXT(_LT_freertos_exception_20, 0)) {
    INTERESTING("freertos-exception-2.0");
  }
  else if (INFILE(_LT_freertos_exception_1) || INFILE(_LT_freertos_exception_2)) {
    INTERESTING("freertos-exception");
  }
  else if (INFILE(_LT_GCC_exception_31_1) || INFILE(_LT_GCC_exception_31_2)) {
    INTERESTING("GCC-exception-3.1");
  }
  else if (INFILE(_LT_GCC_exception_20)) {
    INTERESTING("GCC-exception-2.0");
  }
  /* This wording is very similar to GCC_exception_20 */
  else if (INFILE(_LT_linking_exception_1)) {
    INTERESTING("linking-exception");
  }
  else if (HASTEXT(_TEXT_GCC, REG_EXTENDED)) {
    INTERESTING("GCC-exception");
  }
  else if (INFILE(_LT_gnu_javamail_exception_1) || INFILE(_LT_gnu_javamail_exception_2)) {
    INTERESTING("gnu-javamail-exception");
  }
  else if (INFILE(_LT_i2p_gpl_java_exception)) {
    INTERESTING("i2p-gpl-java-exception");
  }
  else if (INFILE(_LT_GPL_EXCEPT_1) || INFILE(_LT_GPL_EXCEPT_2)) {
    if (HASTEXT(_LT_Libtool_exception, 0)) {
      INTERESTING("Libtool-exception");
    }
    if (HASTEXT(_LT_Autoconf_exception_2, REG_EXTENDED) || INFILE(_LT_Autoconf_exception_1)) {
      INTERESTING("Autoconf-exception");
    }
  }
  else if (INFILE(_LT_Linux_syscall_note)) {
    INTERESTING("Linux-syscall-note");
  }
  else if (HASTEXT(_LT_LLVM_exception_1, 0) || HASTEXT(_LT_LLVM_exception_2, 0)) {
    INTERESTING("LLVM-exception");
  }
  else if (INFILE(_LT_LZMA_exception)) {
    INTERESTING("LZMA-exception");
  }
  else if (INFILE(_LT_mif_exception)) {
    INTERESTING("mif-exception");
  }
  else if (HASTEXT(_LT_OCCT_exception_10_1, REG_EXTENDED) || INFILE(_LT_OCCT_exception_10_2)) {
    INTERESTING("OCCT-exception-1.0");
  }
  else if (INFILE(_LT_openvpn_openssl_exception)) {
    INTERESTING("openvpn-openssl-exception");
  }
  else if (HASTEXT(_TITLE_QT_GPL_EXCEPTION_10, 0)) {
    INTERESTING("Qt-GPL-exception-1.0");
  }
  else if (HASTEXT(_LT_QT_GPL_EXCEPTION_10_1, 0) && INFILE(_LT_QT_GPL_EXCEPTION_10_2)) {
    INTERESTING("Qt-GPL-exception-1.0");
  }
  else if (HASTEXT(_LT_QT_GPL_EXCEPTION, 0) && HASTEXT(_LT_QT_GPL_EXCEPTION_10_3, 0)) {
    INTERESTING("Qt-GPL-exception-1.0");
  }
  else if (INFILE(_TITLE_Nokia_Qt_LGPL_exception_11)) {
    INTERESTING("Nokia-Qt-exception-1.1");
  }
  else if (INFILE(_TITLE_QT_LGPL_EXCEPTION_11)) {
    INTERESTING("Qt-LGPL-exception-1.1");
  }
  else if (INFILE(_LT_Qwt_exception_10_1)) {
    INTERESTING("Qwt-exception-1.0");
  }
  else if (HASTEXT(_LT_Qwt_exception_10_2, 0)) {
    INTERESTING("Qwt-exception-1.0");
  }
  else if (INFILE(_LT_WxWindows_exception_31)) {
    INTERESTING("WxWindows-exception-3.1");
  }
  /*
   * Full license text includes reference to LGPL-2.0-only
   * exception clause.
   */
  else if (INFILE(_PHR_WXWINDOWS_31)) {
    INTERESTING("WxWindows-exception-3.1");
    INTERESTING("LGPL-2.0-or-later");
  }
  /*
   * This is a vague reference to WxWindows license without
   * an exception reference.
   */
  else if (INFILE(_PHR_WXWINDOWS)) {
    INTERESTING("WxWindows");
  }
  else if (HASTEXT(_LT_u_boot_exception_20, REG_EXTENDED)) {
    INTERESTING("u-boot-exception-2.0");
  }
  else if (HASTEXT(_LT_GPL_EXCEPT_Trolltech, REG_EXTENDED)) {
    INTERESTING("trolltech-exception");
  }
  else if (INFILE(_LT_OpenSSL_exception_1) || INFILE(_LT_OpenSSL_exception_2)) {
    INTERESTING("OpenSSL-exception");
  }
  else if (INFILE(_LT_GPL_UPX_EXCEPT) && !HASTEXT(_LT_IGNORE_CLAUSE, REG_EXTENDED)) {
    INTERESTING("UPX-exception");
  }
  else if (INFILE(_URL_mysql_floss_exception) || HASTEXT(_TITLE_mysql_floss_exception, 0)) {
    INTERESTING(lDebug ? "mysql-floss-exception(URL)" : "mysql-floss-exception");
  }
  else if (INFILE(_TITLE_Oracle_foss_exception) || INFILE(_LT_Oracle_foss_exception)) {
    INTERESTING("Oracle-foss-exception");
  }
  else if (INFILE(_LT_linking_exception_2) || (INFILE(_LT_linking_exception_3) && INFILE(_LT_GPL_EXCEPT_7))) {
    INTERESTING("linking-exception");
  }
  else if (HASTEXT(_TITLE_universal_foss_exception_10, 0)
      || URL_INFILE(_URL_universal_foss_exception_10)
      || INFILE(_LT_universal_foss_exception_10)) {
    INTERESTING("universal-foss-exception-1.0");
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
  else if (INFILE(_LT_GPL_EXCEPT_4)) {
    INTERESTING(lDebug ? "GPL-except-4" : "GPL-exception");
  }
  else if (INFILE(_LT_GPL_EXCEPT_7)) {
    INTERESTING("linking-exception");
  }
  else if (INFILE(_LT_GPL_SWI_PROLOG_EXCEPT)) {
    INTERESTING(lDebug ? "GPL-swi-prolog" : "GPL-exception");
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
