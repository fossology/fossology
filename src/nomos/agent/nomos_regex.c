/***************************************************************
 Copyright (C) 2006-2011 Hewlett-Packard Development Company, L.P.
 Copyright (C) 2014, Siemens AG

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
//#define DEBUG_TNG
#ifndef DEBUG_TNG
#define CALL_IF_DEBUG_MODE(x)
#else
#define CALL_IF_DEBUG_MODE(x) x
#endif

#include "nomos_regex.h"
#include "nomos_gap.h"
#include "nomos_utils.h"
/**
 * \file
 * \brief search using regex functions
 *
 * Functions for dealing with the regex clib functions.  Performs the
 * regex searchs on the data.
 */
/** Buffer to hold regex error */
static char regexErrbuf[myBUFSIZ];

regex_t idx_regc[NFOOTPRINTS];

/**
 * \brief Log an error caused by regex
 *
 * Calls regerror()
 * \param ret   Error code
 * \param regc  Compiled regex
 * \param regex Regex string
 */
void regexError(int ret, regex_t *regc, char *regex)
{
#ifdef	PROC_TRACE
  traceFunc("== regexError(%d, %p, %s)\n", ret, regc, regex);
#endif	/* PROC_TRACE */

  (void) regerror(ret, regc, regexErrbuf, sizeof(regexErrbuf));
  Msg("regex = \"%s\"\n", regex);
  LOG_FATAL("regcomp failure: %s", regexErrbuf)
  Bail(-__LINE__);
}

/**
 * \brief Check if a string ends with given suffix
 * \param s       String to check
 * \param suffix  Suffix to find
 * \return 1 if suffix found, 0 otherwise
 */
int endsIn(char *s, char *suffix)
{
  int slen = (int) strlen(s);
  int sufflen = (int) strlen(suffix);
  /*
   * compare trailing chars in a string with a constant (should be faster
   * than calling regcomp() and regexec()!)
   */
#ifdef	PROC_TRACE
  traceFunc("== endsIn(%s, %s)\n", s, suffix);
#endif	/* PROC_TRACE */

  if (strncasecmp(s + slen - sufflen, suffix, (size_t) sufflen) == 0)
  {
    return (1);
  }
  return (0);
}

/**
 * \brief Check if a line exists in a file
 * \param pathname  File location
 * \param regex     Regex to check
 * \return True if regex satisfied, false otherwise
 */
int lineInFile(char *pathname, char *regex)
{
  char buf[myBUFSIZ];

#ifdef  PROC_TRACE
  traceFunc("== lineInFile(%s, \"%s\")\n", pathname, regex);
#endif  /* PROC_TRACE */

  (void) sprintf(buf, "^%s$", regex);
  return (textInFile(pathname, buf, REG_NEWLINE));
}

/**
 * \brief Check if a regex passes in a file
 * \param pathname  File location
 * \param regex     Regex to check
 * \param flags     Additional regex flags
 * \return True if regex satisfied, false otherwise
 */
int textInFile(char *pathname, char *regex, int flags)
{
  char *textp;
  int ret;

#ifdef	PROC_TRACE
  traceFunc("== textInFile(%s, \"%s\", 0x%x)\n", pathname, regex, flags);
#endif	/* PROC_TRACE */

  if ((pathname == NULL_STR ) || (regex == NULL_STR ))
  {
#ifdef	QA_CHECKS
    if (pathname == NULL_STR)
    {
      Assert(NO, "textInFile: NULL pathname");
    }
    if (regex == NULL_STR)
    {
      Assert(NO, "textInFile: NULL regex");
    }
#endif	/* QA_CHECKS */
    return (0);
  }
  if ((textp = mmapFile(pathname)) == NULL_STR)
  {
    return (0);
  }
  ret = strGrep(regex, textp, flags);
  munmapFile(textp);
  return (ret);
}

/**
 * \brief General-purpose grep function, used for one-time-only searches.
 *
 * @return -1 on regex-compile failure, 1 if regex search fails, and 0 if
 * regex search is successful.
 */

int strGrep(char *regex, char *data, int flags)
{
  regex_t regc;
  int ret;

#ifdef	PHRASE_DEBUG
  int i;
#endif	/* PHRASE_DEBUG */

#if defined(PROC_TRACE) || defined(PHRASE_DEBUG)
  traceFunc("== strGrep(\"%s\", %p, 0x%x)\n", regex, data, flags);
#endif	/* PROC_TRACE || PHRASE_DEBUG */

  if (data == NULL_STR || regex == NULL_STR)
  {
    return (0);
  }
  /* DO NOT, repeat DO NOT add REG_EXTENDED as a default flag! */
  if ((ret = regcomp(&regc, regex, flags)) != 0)
  {
    regexError(ret, &regc, regex);
    regfree(&regc);
    return (-1); /* <0 indicates compile failure */
  }
  /*
   * regexec() returns 1 on failure and 0 on success - make sure we call
   * regfree after the regexec call, else after a million or so regex
   * searches we'll have lost a LOT of memory. :)
   */
  ret = regexec(&regc, data, 1, &cur.regm, 0);
  regfree(&regc);
  if (ret)
  {
    return (0); /* >0 indicates search failure */
  }
#ifdef	QA_CHECKS
  if (cur.regm.rm_so == cur.regm.rm_eo)
  {
    Assert(NO, "start/end offsets are identical in strGrep()");
  }
#endif	/* QA_CHECKS */
#ifdef	PHRASE_DEBUG
  printf("strGrep MATCH(%s) @ %d! = {", regex, cur.regm.rm_so);
  for (i = cur.regm.rm_so; i < cur.regm.rm_eo; i++)
  {
    printf("%c", data[i]);
  }
  printf("}\n");
#endif	/* PHRASE_DEBUG */
  if (gl.flags & FL_SAVEBASE)
  {
    cur.matchBase = data;
  }
  return (1);
}

/**
 * \brief compile a regex, and perform the search (on data?)
 *
 * @param index number of licence/regex we are looking for (given in STRINGS.in)
 * @param data  the data to search
 * @param flags regcomp cflags
 *
 * @return -1 on regex-compile failure, 1 if regex search fails, and 0 if
 * regex search is successful.
 */
int idxGrep(int index, char *data, int flags)
{
  return idxGrep_base(index, data, flags, 0);
}

/**
 * \brief compile a regex, perform the search and record findings
 *
 * If OPTS_NO_HIGHLIGHTINFO is set, do not record. If not set, record the findings
 * \param index number of licence/regex we are looking for (given in STRINGS.in)
 * \param data  the data to search
 * \param flags regcomp cflags
 * \return -1 on regex-compile failure, 1 if regex search fails, and 0 if
 * regex search is successful.
 */
int idxGrep_recordPosition(int index, char *data, int flags)
{
  if( optionIsSet(OPTS_NO_HIGHLIGHTINFO) ) {
    return idxGrep_base(index, data, flags, 0);
  }
  else {
    return idxGrep_base(index, data, flags, 1);
  }
}

/**
 * \brief compile a regex, perform the search and record findings
 *
 * If OPTS_NO_HIGHLIGHTINFO is set, do not record. If not set, record the
 * doctored findings
 * \param index number of licence/regex we are looking for (given in STRINGS.in)
 * \param data  the data to search
 * \param flags regcomp cflags
 * \return -1 on regex-compile failure, 1 if regex search fails, and 0 if
 * regex search is successful.
 */
int idxGrep_recordPositionDoctored(int index, char *data, int flags)
{

  if( optionIsSet(OPTS_NO_HIGHLIGHTINFO) ) {
    return idxGrep_base(index, data, flags, 0);
  }
  else {
    return idxGrep_base(index, data, flags, 2);
  }
}

/**
 * \brief compile a regex, perform the search and record index
 *
 * If OPTS_NO_HIGHLIGHTINFO is set, do not record. If not set, record the
 * finding index
 * \param index number of licence/regex we are looking for (given in STRINGS.in)
 * \param data  the data to search
 * \param flags regcomp cflags
 * \return -1 on regex-compile failure, 1 if regex search fails, and 0 if
 * regex search is successful.
 */
int idxGrep_recordIndex(int index, char *data, int flags)
{
  if( optionIsSet(OPTS_NO_HIGHLIGHTINFO) ) {
    return idxGrep_base(index, data, flags, 0);
  }
  else {
    return idxGrep_base(index, data, flags, 3);
  }
}

/**
 * \brief Perform a regex match on a given data and return only first match
 * \param isPlain   Do a plain match?
 * \param data      The string to perform search on
 * \param regex     The regex string
 * \param rp        Regex buffer
 * \param[out] regmatch  Regex matches
 * \return
 */
int matchOnce(int isPlain, char *data, char* regex, regex_t *rp,
    regmatch_t* regmatch)
{
  if(isPlain) {
    return !strNbuf_noGlobals(data, regex, regmatch , 0 , cur.matchBase );
  }

  return regexec(rp, data, 1, regmatch, 0);
}

/**
 * \brief Store a single regex match to array
 * \param[in] currentRegMatch Match to store
 * \param[in] lastmatch       Index of last match
 * \param[in,out] allmatches  Array of all matches
 * \param[in,out] tmpData
 * \param[in] data
 * \return New lastmatch index
 */
int storeOneMatch(regmatch_t currentRegMatch, int lastmatch, GArray* allmatches,
    char** tmpData, char* data)
{
  regmatch_t storeRegMatch;
  storeRegMatch.rm_so = currentRegMatch.rm_so + lastmatch;
  storeRegMatch.rm_eo = currentRegMatch.rm_eo + lastmatch;
  g_array_append_val(allmatches, storeRegMatch);
  lastmatch += currentRegMatch.rm_eo;
  *tmpData = data + lastmatch;
  return lastmatch;
}

/**
 * \brief compile a regex, and perform the search (on data?)
 *
 * @param index number of licence/regex we are looking for (given in STRINGS.in)
 * @param data the data to search
 * @param flags regcomp cflags
 * @param mode Flag to control recording of findings (0:No, 1: Yes, 2:Yes doctored buffer)
 *
 * @return -1 on regex-compile failure, 1 if regex search fails, and 0 if
 * regex search is successful.
 */
int idxGrep_base(int index, char *data, int flags, int mode)
{
  int i;
  int ret;

  int show = flags & FL_SHOWMATCH;
  licText_t *ltp = licText + index;
  /**
   * \todo is idx_regc needed? Here we set the pointer to our array and later
   * we fill it, but we never reuse the regex_t
   */
  regex_t *rp = idx_regc + index;

  CALL_IF_DEBUG_MODE(printf(" %i %i \"", index, ltp->plain);)

#if defined(PROC_TRACE) || defined(PHRASE_DEBUG)
  traceFunc("== idxGrep(%d, %p, 0x%x)\n... regex \"%s\"\n", index, data,
      flags, _REGEX(index));
#endif  /* PROC_TRACE || PHRASE_DEBUG */

  if (index > NFOOTPRINTS)
  {
    LOG_FATAL("idxGrep: index %d out of range", index)
    Bail(-__LINE__);
  }
  if (data == NULL_STR)
  {
#ifdef  PHRASE_DEBUG
    printf("idxGrep: NULL pointer to file data!\n");
#endif  /* PHRASE_DEBUG */
    return (0);
  }

  if (ltp->plain )
  {
    ret = strNbuf(data, ltp->regex);
    if(ret == 0) return (ret);
  }
  else {
    if ((ret = regcomp(rp, ltp->regex, flags)))
    {
      fprintf(stderr, "Compile failed, regex #%d\n", index);
      regexError(ret, rp, ltp->regex);
      regfree(rp);
      printf("Compile error \n");
      return (-1); /* <0 indicates compile failure */
    }

    if (regexec(rp, data, 1, &cur.regm, 0))
    {
      regfree(rp);
      return (0);
    }
    else ret  =1;

  #ifdef  QA_CHECKS
    if (cur.regm.rm_so == cur.regm.rm_eo)
    {
      regfree(rp);
      Assert(NO, "start/end offsets are identical in idxGrep(%d)",
          index);
    }
  #endif  /* QA_CHECKS */
    /* Set up a global match-length variable? */
    if (show)
    {
  #ifdef  DEBUG
      printf("REGEX(%d) \"%s\"\n", index, ltp->regex);
  #endif  /* DEBUG */
      printf("MATCH @ %d! = {", cur.regm.rm_so);
      for (i = cur.regm.rm_so; i < cur.regm.rm_eo; i++)
      {
        printf("%c", data[i]);
      }
      printf("}\n");
    }
    if (gl.flags & FL_SAVEBASE)
    {
      cur.matchBase = data;
    }
  }

  //! Now we have a match

  if (mode == 3 ) {
      recordIndex(cur.indexList, index);
    }
  else if (mode==1 || mode == 2)
  {
    CALL_IF_DEBUG_MODE(printf("MATCH!\n");)
    //! All sanity checks have passed and we have at least one match

    CALL_IF_DEBUG_MODE(printf("%s", data);)


    GArray* allmatches = g_array_new(FALSE, FALSE, sizeof(regmatch_t));
    regmatch_t currentRegMatch;
    int lastmatch = 0;

    char* tmpData = data;

    lastmatch = storeOneMatch(cur.regm, lastmatch, allmatches, &tmpData, data);

    while (!matchOnce(ltp->plain,tmpData, ltp->regex, rp, &currentRegMatch )  )
    {
      lastmatch = storeOneMatch(currentRegMatch, lastmatch, allmatches, &tmpData, data);
    }


    if(index >= _KW_first  && index <= _KW_last ) {
      rememberWhatWeFound(cur.keywordPositions, allmatches, index, mode);
    }
    else if (cur.currentLicenceIndex > -1 ) {
       rememberWhatWeFound( getLicenceAndMatchPositions(cur.theMatches, cur.currentLicenceIndex )->matchPositions , allmatches, index, mode);
    }

    CALL_IF_DEBUG_MODE(printf("Bye!\n");)
 }

  if (!ltp->plain ) regfree(rp);
return (1);
}

/**
 * \brief Add a given index to index list
 * \param[in,out] indexList List to add index to
 * \param[in] index         Index to be appended
 */
void recordIndex(GArray* indexList, int index){
  g_array_append_val(indexList, index);
}

/**
 * \brief Get offset from doctored buffer
 * \param posInDoctoredBuffer
 * \return new offset
 * \sa uncollapsePosition()
 */
static int getOffset(int posInDoctoredBuffer)
{
  return uncollapsePosition(posInDoctoredBuffer, cur.docBufferPositionsAndOffsets);
}

/**
 * \brief From a given array, get regex match from a given index
 * \param in    Array to get regex match from
 * \param index The index in array required
 * \return  Regex match @index
 */
regmatch_t* getRegmatch_t(GArray* in, int index)
{
 return & g_array_index(in, regmatch_t, index);
}

/**
 * \brief Store regex matches in highlight array
 * \param[in,out] highlight   The array holding the regex matches
 * \param[in] regmatch_tArray Array of regex matches to store
 * \param[in] index           Index of license (from STRINGS.in)
 * \param[in] mode            Mode to store (1=>get the byte position|2=>get the offset)
 */
void rememberWhatWeFound(GArray* highlight, GArray* regmatch_tArray, int index,
  int mode)
{

  if (mode != 1 && mode != 2)
  {
    FOSSY_EXIT("This mode is not supported\n", 8);
    return;
  }

  int i = 0;
  int nmatches = regmatch_tArray->len;
  int alreadyFound = highlight->len;
  g_array_set_size(highlight, alreadyFound + nmatches);

  for (i = 0; i < nmatches; ++i)
  {
    regmatch_t* theRegmatch = getRegmatch_t(regmatch_tArray, i);
    if (theRegmatch->rm_eo == -1 || theRegmatch->rm_so == -1)
    {
      FOSSY_EXIT("Found match at negative position... this should not happen\n", 9);
      return;
    }

    MatchPositionAndType* ourMatchv = getMatchfromHighlightInfo(highlight, i + alreadyFound);
    ourMatchv->start = (mode == 1) ? theRegmatch->rm_so : getOffset(theRegmatch->rm_so);
    ourMatchv->end = (mode == 1) ? theRegmatch->rm_eo : getOffset(theRegmatch->rm_eo);
    ourMatchv->index = index;

  CALL_IF_DEBUG_MODE(printf("here: %i - %i \n", ourMatchv->start, ourMatchv->end);)
  }
  CALL_IF_DEBUG_MODE(printf(" We go and now we know  %d ", highlight->len);)
}

#define	_XC(q)	((char) xascii[q])

/**
 * \brief Check if a string exists in buffer (case insensitive)
 * \param data  Haystack
 * \param str   Needle
 * \return 1 on success, 0 otherwise
 * \sa strNbuf_noGlobals()
 */
int strNbuf(char *data, char *str){

  return strNbuf_noGlobals(data, str, &(cur.regm), gl.flags & FL_SAVEBASE , cur.matchBase );
}

/**
 * \brief This is our own internal, case-insensitive version of strstr().
 *
 * No open-source code was consulted/used in the construction of this function.
 */
int strNbuf_noGlobals(char *data, char *str, regmatch_t* matchPos, int doSave,
char* saveData)
{
  static int firstFlag = 1;
  static char xascii[128];
  int i;
  int alph = 0;
  int save = 0;
  char *bufp;
  char *pattp;
  char *mark;
  char x;
  char firstx = 0;

#if defined(PROC_TRACE) || defined(PHRASE_DEBUG)
  traceFunc("== strNbuf(%p, %p)\n", data, str);
#endif	/* PROC_TRACE || PHRASE_DEBUG */

  if (firstFlag)
  {
    firstFlag = 0;
    /*
     * 32 characters separate 'A' (65) and 'a' (97), contiguous up to 'Z'.
     * Therefore, 'Z' == 90, 'a' == 97, and 'z' == 122
     */
    for (i = 0; i < sizeof(xascii); i++)
    {
      if ((i >= 65) && (i <= 90))
      { /* isupper */
        xascii[i] = i + 32; /* -> tolower */
      }
      else if ((i >= 97) && (i <= 122))
      { /* islower */
        xascii[i] = i - 32; /* -> toupper */
      }
      else
      {
        /* *foo = tolower((char)i); */
        xascii[i] = (char) /*i*/0;
      }
    }
#ifdef	STRSTR_DEBUG
    /*
     * Dump the table (debugging purposes only)
     */
    for (i = 0; i < sizeof (xascii); i++)
    {
      if (xascii[i])
      {
        printf(" %c%c  ", (unsigned) i, xascii[i]);
      }
      else
      {
        printf("\\%03d ", (int) xascii[i]);
      }
      if (i & 16 == 15)
      {
        printf("\n");
      }
    }
#endif	/* STRSTR_DEBUG */
  }
#ifdef	STRSTR_DEBUG
  printf("DATA \"%s\"\nPATT \"%s\"\n", data, str);
#endif	/* STRSTR_DEBUG */
  if (data == NULL_STR || str == NULL_STR)
  {
    return (0);
  }
  alph = isalpha(*str);
  if (alph)
  {
    firstx = xascii[(int) *str];
#ifdef	STRSTR_DEBUG
    printf("NOTE: first char (%c) is Alphabetic - alternate is (%c)\n",
                *str, firstx);
#endif	/* STRSTR_DEBUG */
#ifdef	QA_CHECKS
    if (firstx == NULL_CHAR)
    {
      LOG_FATAL("Unexpected initialization")
              Bail(-__LINE__);
    }
#endif	/* QA_CHECKS */
  }
  for (bufp = data; /* *pattp && */*bufp; bufp = mark)
  {
#ifdef	STRSTR_DEBUG
    printf("\nDEBUG: start, buffer = \"%s\"\n", bufp);
#endif	/* STRSTR_DEBUG */
    pattp = str;
    /*
     * Locate the first character of our target-pattern in the buffer...
     */
    while (*bufp)
    {
#ifdef	STRSTR_DEBUG
      printf("... findfirst, *bufp is '%c' == [%c%c]?\n",
                  *bufp, *str, alph ? firstx : *str);
#endif	/* STRSTR_DEBUG */
      if (*bufp == *pattp)
      {
        break;
      }
      if (alph && (*bufp == firstx))
      {
        break;
      }
      bufp++;
    }
    if (*bufp == NULL_CHAR)
    {
      return (0);
    }
    save = bufp - data;
    mark = ++bufp; /* could optimize this in loop below */
#ifdef	STRSTR_DEBUG
    printf("GOT IT, at offset %d (*mark now is '%c')\n",
                bufp - data - 1, *mark);
#endif	/* STRSTR_DEBUG */
    /* optimizeMark = 1; */
    for (++pattp; *bufp && *pattp; bufp++, pattp++)
    {
#ifdef	STRSTR_DEBUG
      printf("STRING-COMPARE: %c == %c ??\n", *bufp, *pattp);
#endif	/* STRSTR_DEBUG */
      if (*bufp == *pattp)
      {
        continue;
      }
#ifdef	STRSTR_DEBUG
      printf("... or perhaps: %c == %c ??\n", *bufp,
                  xascii[*pattp]);
#endif	/* STRSTR_DEBUG */
      if (((x = xascii[(int) *pattp])) && (*bufp == x))
      {
        continue;
      }
      break;
    }
    if (*pattp == NULL_CHAR)
    {
      matchPos->rm_so = save;
      matchPos->rm_eo = save + strlen(str);
      if (doSave)
      {
         saveData = data;
      }
      return (1); /* end of pattern == success */
    }
    if (*bufp == NULL_CHAR)
    {
      return (0); /* end of buffer == success */
    }
  }
  return (0);
}
