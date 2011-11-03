/***************************************************************
 Copyright (C) 2006-2011 Hewlett-Packard Development Company, L.P.

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

#include <regex.h>
#include <ctype.h>
#include "nomos.h"
#include "nomos_regex.h"
#include "util.h"


/**
 * \file nomos_regex.c
 * \brief search using regex functions
 *
 * Functions for dealing with the regex clib functions.  Performs the
 * regex searchs on the data.
 *
 */
static char regexErrbuf[myBUFSIZ];

regex_t idx_regc[NFOOTPRINTS];

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

  if (strncasecmp(s + slen-sufflen, suffix, (size_t) sufflen) == 0) {
    return(1);
  }
  return(0);
}


int lineInFile(char *pathname, char *regex)
{
  char buf[myBUFSIZ];

#ifdef  PROC_TRACE
  traceFunc("== lineInFile(%s, \"%s\")\n", pathname, regex);
#endif  /* PROC_TRACE */

  (void) sprintf(buf, "^%s$", regex);
  return(textInFile(pathname, buf, REG_NEWLINE));
}


int textInFile(char *pathname, char *regex, int flags)
{
  char *textp;
  int ret;

#ifdef	PROC_TRACE
  traceFunc("== textInFile(%s, \"%s\", 0x%x)\n", pathname, regex, flags);
#endif	/* PROC_TRACE */

  if ((pathname == NULL_STR) || (regex == NULL_STR)) {
#ifdef	QA_CHECKS
    if (pathname == NULL_STR) {
      Assert(NO, "textInFile: NULL pathname");
    }
    if (regex == NULL_STR) {
      Assert(NO, "textInFile: NULL regex");
    }
#endif	/* QA_CHECKS */
    return(0);
  }
  if ((textp = mmapFile(pathname)) == NULL_STR) {
    return(0);
  }
  ret = strGrep(regex, textp, flags);
  munmapFile(textp);
  return(ret);
}


/**
 * strGrep
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

  if (data == NULL_STR || regex == NULL_STR) {
    return(0);
  }
  /* DO NOT, repeat DO NOT add REG_EXTENDED as a default flag! */
  if ((ret = regcomp(&regc, regex, flags)) != 0) {
    regexError(ret, &regc, regex);
    regfree(&regc);
    return(-1);	/* <0 indicates compile failure */
  }
  /*
   * regexec() returns 1 on failure and 0 on success - make sure we call
   * regfree after the regexec call, else after a million or so regex
   * searches we'll have lost a LOT of memory. :)
   */
  ret = regexec(&regc, data, 1, &cur.regm, 0);
  regfree(&regc);
  if (ret) {
    return(0);	/* >0 indicates search failure */
  }
#ifdef	QA_CHECKS
  if (cur.regm.rm_so == cur.regm.rm_eo) {
    Assert(NO, "start/end offsets are identical in strGrep()");
  }
#endif	/* QA_CHECKS */
#ifdef	PHRASE_DEBUG
  printf("strGrep MATCH(%s) @ %d! = {", regex, cur.regm.rm_so);
  for (i = cur.regm.rm_so; i < cur.regm.rm_eo; i++) {
    printf("%c", data[i]);
  }
  printf("}\n");
#endif	/* PHRASE_DEBUG */
  if (gl.flags & FL_SAVEBASE) {
    cur.matchBase = data;
  }
  return(1);
}


/* idxGrep
 * \brief compile a regex, and perform the search (on data?)
 *
 * @param int index ??
 * @param char* data, the data to search
 * @param int flags regcomp cflags
 *
 * @return -1 on regex-compile failure, 1 if regex search fails, and 0 if
 * regex search is successful.
 */
int idxGrep(int index, char *data, int flags)
{
  int i;
  int ret;
  int show = flags & FL_SHOWMATCH;
  licText_t *ltp = licText + index;
  regex_t *rp = idx_regc + index;

#if defined(PROC_TRACE) || defined(PHRASE_DEBUG)
  traceFunc("== idxGrep(%d, %p, 0x%x)\n... regex \"%s\"\n", index, data,
      flags, _REGEX(index));
#endif	/* PROC_TRACE || PHRASE_DEBUG */

  if (index > NFOOTPRINTS) {
    LOG_FATAL("idxGrep: index %d out of range", index)
              Bail(-__LINE__);
  }
  if (data == NULL_STR) {
#ifdef	PHRASE_DEBUG
    printf("idxGrep: NULL pointer to file data!\n");
#endif	/* PHRASE_DEBUG */
    return(0);
  }
  if (ltp->plain) {
    return(strNbuf(data, ltp->regex));
  }
  if ((ret = regcomp(rp, ltp->regex, flags))) {
    fprintf(stderr, "Compile failed, regex #%d\n", index);
    regexError(ret, rp, ltp->regex);
    regfree(rp);
    return(-1);	/* <0 indicates compile failure */
  }
  ret = regexec(rp, data, 1, &cur.regm, 0);
  regfree(rp);
  if (ret) {
    return(0);
  }
#ifdef	QA_CHECKS
  if (cur.regm.rm_so == cur.regm.rm_eo) {
    Assert(NO, "start/end offsets are identical in idxGrep(%d)",
        index);
  }
#endif	/* QA_CHECKS */
  /* Set up a global match-length variable? */
  if (show) {
#ifdef	DEBUG
    printf("REGEX(%d) \"%s\"\n", index, ltp->regex);
#endif	/* DEBUG */
    printf("MATCH @ %d! = {", cur.regm.rm_so);
    for (i = cur.regm.rm_so; i < cur.regm.rm_eo; i++) {
      printf("%c", data[i]);
    }
    printf("}\n");
  }
  if (gl.flags & FL_SAVEBASE) {
    cur.matchBase = data;
  }
  return(1);
}

#define	_XC(q)	((char) xascii[q])
/**
 * \brief This is our own internal, case-insensitive version of strstr().  No
 * open-source code was consulted/used in the construction of this function.
 */
int strNbuf(char *data, char *str)
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

  if (firstFlag) {
    firstFlag = 0;
    /*
     * 32 characters separate 'A' (65) and 'a' (97), contiguous up to 'Z'.
     * Therefore, 'Z' == 90, 'a' == 97, and 'z' == 122
     */
    for (i = 0; i < sizeof(xascii); i++) {
      if ((i >= 65) && (i <= 90)) {	/* isupper */
        xascii[i] = i + 32;	/* -> tolower */
      } else if ((i >= 97) && (i <= 122)) {	/* islower */
        xascii[i] = i - 32;	/* -> toupper */
      } else {
        /* *foo = tolower((char)i); */
        xascii[i] = (char) /*i*/ 0;
      }
    }
#ifdef	STRSTR_DEBUG
    /*
     * Dump the table (debugging purposes only)
     */
    for (i = 0; i < sizeof(xascii); i++) {
      if (xascii[i]) {
        printf(" %c%c  ", (unsigned) i, xascii[i]);
      }
      else {
        printf("\\%03d ", (int) xascii[i]);
      }
      if (i & 16 == 15) {
        printf("\n");
      }
    }
#endif	/* STRSTR_DEBUG */
  }
#ifdef	STRSTR_DEBUG
  printf("DATA \"%s\"\nPATT \"%s\"\n", data, str);
#endif	/* STRSTR_DEBUG */
  if (data == NULL_STR || str == NULL_STR) {
    return(0);
  }
  alph = isalpha(*str);
  if (alph) {
    firstx = xascii[(int)*str];
#ifdef	STRSTR_DEBUG
    printf("NOTE: first char (%c) is Alphabetic - alternate is (%c)\n",
        *str, firstx);
#endif	/* STRSTR_DEBUG */
#ifdef	QA_CHECKS
    if (firstx == NULL_CHAR) {
      LOG_FATAL("Unexpected initialization")
                  Bail(-__LINE__);
    }
#endif	/* QA_CHECKS */
  }
  for (bufp = data; /* *pattp && */ *bufp; bufp = mark) {
#ifdef	STRSTR_DEBUG
    printf("\nDEBUG: start, buffer = \"%s\"\n", bufp);
#endif	/* STRSTR_DEBUG */
    pattp = str;
    /*
     * Locate the first character of our target-pattern in the buffer...
     */
    while (*bufp) {
#ifdef	STRSTR_DEBUG
      printf("... findfirst, *bufp is '%c' == [%c%c]?\n",
          *bufp, *str, alph?firstx:*str);
#endif	/* STRSTR_DEBUG */
      if (*bufp == *pattp) {
        break;
      }
      if (alph && (*bufp == firstx)) {
        break;
      }
      bufp++;
    }
    if (*bufp == NULL_CHAR) {
      return(0);
    }
    save = bufp-data;
    mark = ++bufp;	/* could optimize this in loop below */
#ifdef	STRSTR_DEBUG
    printf("GOT IT, at offset %d (*mark now is '%c')\n",
        bufp-data-1, *mark);
#endif	/* STRSTR_DEBUG */
    /* optimizeMark = 1; */
    for (++pattp; *bufp && *pattp; bufp++, pattp++) {
#ifdef	STRSTR_DEBUG
      printf("STRING-COMPARE: %c == %c ??\n", *bufp, *pattp);
#endif	/* STRSTR_DEBUG */
      if (*bufp == *pattp) {
        continue;
      }
#ifdef	STRSTR_DEBUG
      printf("... or perhaps: %c == %c ??\n", *bufp,
          xascii[*pattp]);
#endif	/* STRSTR_DEBUG */
      if (((x = xascii[(int)*pattp])) && (*bufp == x)) {
        continue;
      }
      break;
    }
    if (*pattp == NULL_CHAR) {
      cur.regm.rm_so = save;
      cur.regm.rm_eo = save + strlen(str);
      if (gl.flags & FL_SAVEBASE) {
        cur.matchBase = data;
      }
      return(1);	/* end of pattern == success */
    }
    if (*bufp == NULL_CHAR) {
      return(0);	/* end of buffer == success */
    }
  }
  return(0);
}
