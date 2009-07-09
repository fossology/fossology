/***************************************************************
 Copyright (C) 2006-2009 Hewlett-Packard Development Company, L.P.
 
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
#include "nomos.h"
#include "nomos_regex.h"
#include "util.h"

static char regexErrbuf[myBUFSIZ];

/* #ifdef	LATER */
#define	NBMATCH	500
int idxGrepBatch();
/* #endif	LATER */
regex_t regc[NFOOTPRINTS];

void regexError(int ret, regex_t *regc, char *regex)
{
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== regexError(%d, %p, %s)\n", ret, regc, regex);
#endif	/* PROC_TRACE */
	(void) regerror(ret, regc, regexErrbuf, sizeof(regexErrbuf));
	MsgLog("regex = \"%s\"\n", regex);
	Fatal("regcomp failure: %s", regexErrbuf);
}

int endsIn(char *s, char *suffix)
{
	register int slen = (int) strlen(s), sufflen = (int) strlen(suffix);
/*
 * compare trailing chars in a string with a constant (should be faster
 * than calling regcomp() and regexec()!)
 */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== endsIn(%s, %s)\n", s, suffix);
#endif	/* PROC_TRACE */
#if	0
	if (strncmp(s+slen-sufflen, suffix, (size_t) sufflen) == 0)
#endif
	if (strncasecmp(s+slen-sufflen, suffix, (size_t) sufflen) == 0) {
		return(1);
	}
	return(0);
}

int lineInFile(char *pathname, char *regex)
{
	char buf[myBUFSIZ];
/* */
#ifdef  PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== lineInFile(%s, \"%s\")\n", pathname, regex);
#endif  /* PROC_TRACE */
/* */
	(void) sprintf(buf, "^%s$", regex);
	return(textInFile(pathname, buf, REG_NEWLINE));
}

int textInFile(char *pathname, char *regex, int flags)
{
	char *textp;
	int ret;

#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== textInFile(%s, \"%s\", 0x%x)\n", pathname, regex, flags);
#endif	/* PROC_TRACE */
	if (pathname == NULL_STR || regex == NULL_STR) {
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

int strGrep(char *regex, char *data, int flags)
{
	int ret;
#ifdef	PHRASE_DEBUG
	int i;
#endif	/* PHRASE_DEBUG */
/*
 * General-purpose grep function, used for one-time-only searches.
 * Return -1 on regex-compile failure, 1 if regex search fails, and 0 if
 * regex search is successful.
 */
#if defined(PROC_TRACE) || defined(PHRASE_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== strGrep(\"%s\", %p, 0x%x)\n", regex, data, flags);
#endif	/* PROC_TRACE || PHRASE_DEBUG */
	if (data == NULL_STR || regex == NULL_STR) {
		return(0);
	}
/* DO NOT, repeat DO NOT add REG_EXTENDED as a default flag! */
	if ((ret = regcomp(&gl.regc, regex, flags)) != 0) {
		regexError(ret, &gl.regc, regex);
		regfree(&gl.regc);
		return(-1);	/* <0 indicates compile failure */
	}
/*
 * regexec() returns 1 on failure and 0 on success - make sure we call
 * regfree after the regexec call, else after a million or so regex
 * searches we'll have lost a LOT of memory. :)
 */
	ret = regexec(&gl.regc, data, 1, &gl.regm, 0);
	regfree(&gl.regc);
	if (ret) {
		return(0);	/* >0 indicates search failure */
	}
#ifdef	QA_CHECKS
	if (gl.regm.rm_so == gl.regm.rm_eo) {
		Assert(NO, "start/end offsets are identical in strGrep()");
	}
#endif	/* QA_CHECKS */
#ifdef	PHRASE_DEBUG
	printf("strGrep MATCH(%s) @ %d! = {", regex, gl.regm.rm_so);
	for (i = gl.regm.rm_so; i < gl.regm.rm_eo; i++) {
		printf("%c", data[i]);
	}
	printf("}\n");
#endif	/* PHRASE_DEBUG */
	if (gl.flags & FL_SAVEBASE) {
		gl.matchBase = data;
	}
	return(1);
}

/*
 * Return -1 on regex-compile failure, 1 if regex search fails, and 0 if
 * regex search is successful.
 */
int idxGrep(int index, char *data, int flags)
{
	register int i, ret, show = flags & FL_SHOWMATCH;
	register licText_t *ltp = licText+index;
	register regex_t *rp = regc+index;
/* */
#if defined(PROC_TRACE) || defined(PHRASE_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== idxGrep(%d, %p, 0x%x)\n... regex \"%s\"\n", index, data,
	    flags, _REGEX(index));
#endif	/* PROC_TRACE || PHRASE_DEBUG */
#if	0
	switch (index) {	/* per-string debugging! */
		case _LT_PUBDOM_4:
			show = 1; break;
		case _TEXT_GFDL_NOT_GPL:
			printf("p %p s %s\n", data, data);
	}
#endif
#if	0
#ifdef	PHRASE_DEBUG
	printf("idxGrep: ");
	if (flags) {
		printf("flags == ");
		if (flags & REG_ICASE) {
			printf("ICASE ");
		}
		if (flags & REG_EXTENDED) {
			printf("EXTENDED ");
		}
		if (flags & REG_NEWLINE) {
			printf("NEWLINE ");
		}
		if (show) {
			printf("SHOWMATCH ");
		}
		printf("\n");
		flags |= FL_SHOWMATCH;	/* always show matches! */
	}
#endif	/* PHRASE_DEBUG */
#endif
	if (index > NFOOTPRINTS) {
		Fatal("idxGrep: index %d out of range", index);
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
	ret = regexec(rp, data, 1, &gl.regm, 0);
	regfree(rp);
	if (ret) {
		return(0);
	}
#ifdef	QA_CHECKS
	if (gl.regm.rm_so == gl.regm.rm_eo) {
		Assert(NO, "start/end offsets are identical in idxGrep(%d)",
		    index);
	}
#endif	/* QA_CHECKS */
/* Set up a global match-length variable? */
	if (show) {
#ifdef	DEBUG
		printf("REGEX(%d) \"%s\"\n", index, ltp->regex);
#endif	/* DEBUG */
		printf("MATCH @ %d! = {", gl.regm.rm_so);
		for (i = gl.regm.rm_so; i < gl.regm.rm_eo; i++) {
			printf("%c", data[i]);
		}
		printf("}\n");
	}
	if (gl.flags & FL_SAVEBASE) {
		gl.matchBase = data;
	}
	return(1);
}

#ifdef	LATER
/*
 * Grep for an encrypted string in batch-mode (multiple regm structs)
 */
int idxGrepBatch(int index, char *data, int flags)
{
	register int ret;
	register licText_t *ltp = licText+index;
	register regex_t *rp = regc+index;
	static regmatch_t batchMatch[NBMATCH], *mp;
	static int nextMatch = NBMATCH, lastIndex = -1;
#ifdef	PHRASE_DEBUG
	int i;
#endif	/* PHRASE_DEBUG */
register int i, j;
char *res;
/* */
#if defined(PROC_TRACE) || defined(PHRASE_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== idxGrepBatch(%d, %p, 0x%x)\n... regex \"%s\"\n", index,
	    data, flags, _REGEX(index));
#endif	/* PROC_TRACE || PHRASE_DEBUG */
#if	0
/*
 * Passing an index of -1 says "reset everything"
 */
	if (index < 0) {
		(void) memset(batchMatch, (int) -1, sizeof(batchMatch));
		nextMatch = NBMATCH/*0*/;
		lastIndex = -1;
		return(0);
	}
#endif
	if (index > NFOOTPRINTS) {
		Fatal("idxGrepBatch: index %d out of range", index);
	}
#ifdef	BATCH_DEBUG
printf("== idxGrepBatch(%d, %p, 0x%x) lastIdx %d nextMatch %d",
    index, data, flags, lastIndex, nextMatch);
#endif	/* BATCH_DEBUG */
#ifdef	DEBUG
	printf("DEBUG: idx %d lastIdx %d nextmatch %d regex \"%s\"\n",
	    index, lastIndex, nextMatch, ltp->regex);
#endif
	if (lastIndex == index) {
		if (nextMatch < NBMATCH) {
			mp = batchMatch+nextMatch;
			res = mp->rm_so < 0? "no" : "yes!";
/*
 * if the next "start-offset" field == -1 then there's NOT another match
 */
#ifdef	BATCH_DEBUG
printf("\n***** Cached results(%d), entry %d: %s\n", index, nextMatch, res);
#endif	/* BATCH_DEBUG */
			if (mp->rm_so < 0) {
				nextMatch = NBMATCH;
				return(0);	/* no more matches */
			}
			/* memcpy(&gl.regm, mp, sizeof(*mp)); */
			gl.regm.rm_so = mp->rm_so;
			gl.regm.rm_eo = mp->rm_eo;
			nextMatch++;
			return(1);
		}
	}
	else {
		lastIndex = index;
	}
/*
 * If we're at the end of our list of matches, we need to get more.
 */
	if (data == NULL_STR) {
		return(0);
	}
	if ((ret = regcomp(rp, ltp->regex, flags))) {
		fprintf(stderr, "Compile failed, regex #%d\n", index);
		regexError(ret, rp, ltp->regex);
		regfree(rp);
		return(-1);	/* <0 indicates compile failure */
	}
	(void) memset(batchMatch, (int) -1, sizeof(batchMatch));
	ret = regexec(rp, data, NBMATCH, batchMatch, 0);
	regfree(rp);
	if (ret) {
#ifdef	BATCH_DEBUG
printf(" no\n");
#endif	/* BATCH_DEBUG */
		nextMatch = NBMATCH;
		return(0);
	}
#ifdef	BATCH_DEBUG
printf("\n>>---> regexec(%d) match: \"%s\"\n", index, ltp->regex);
#endif	/* BATCH_DEBUG */
	mp = batchMatch;
#ifdef	BATCH_DEBUG
	for (i = 0; mp->rm_so >= 0; mp++, i++) {
printf("MATCH#%d[%d]: {%d,%d} [", index, i, mp->rm_so, mp->rm_eo);
		for (j = mp->rm_so; j < mp->rm_eo; j++) {
			printf("%c", *(data+j));
		}
		printf("]\n");
	}
#endif	/* BATCH_DEBUG */
	gl.regm.rm_so = mp->rm_so;
	gl.regm.rm_eo = mp->rm_eo;
	nextMatch = 1;	/* we just used entry #0, so #1 is next */
	return(1);
}
#endif	/* LATER */

/*
 * This is our own internal, case-insensitive version of strstr().  No
 * open-source code was consulted/used in the construction of this function.
 */
#define	_XC(q)	((char) xascii[q])
int strNbuf(char *data, char *str)
{
	static int firstFlag = 1;
	register int i, alph = 0, optimizeMark, save = 0;
	register char *bufp, *pattp, *mark, x, firstx;
	static /*unsigned*/ char xascii[128];
#if	0
	register unsigned char *foo;
#endif
/* */
#if defined(PROC_TRACE) || defined(PHRASE_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== strNbuf(%p, %p)\n", data, str);
#endif	/* PROC_TRACE || PHRASE_DEBUG */
/* */
	if (firstFlag) {
		firstFlag = 0;
/*
 * 32 characters separate 'A' (65) and 'a' (97), contiguous up to 'Z'.
 * Therefore, 'Z' == 90, 'a' == 97, and 'z' == 122
 */
		for (i = 0; i < sizeof(xascii); i++) {
			if (i >= 65 && i <= 90) {	/* isupper */
				xascii[i] = i+32;	/* -> tolower */
			}
			else if (i >= 97 && i <= 122) {	/* islower */
				xascii[i] = i-32;	/* -> toupper */
			}
			else {
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
	if (alph = isalpha(*str)) {
		firstx = xascii[*str];
#ifdef	STRSTR_DEBUG
	printf("NOTE: first char (%c) is Alphabetic - alternate is (%c)\n",
	    *str, firstx);
#endif	/* STRSTR_DEBUG */
#ifdef	QA_CHECKS
		if (firstx == NULL_CHAR) {
			Fatal("Unexpected initialization");
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
			if (alph && *bufp == firstx) {
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
#if	0
			if (optimizeMark) {
				if (*bufp == *str || *bufp == firstx) {
					optimizeMark = 0;
					mark = bufp;
				}
			}
#endif
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
			if ((x = xascii[*pattp]) && *bufp == x) {
				continue;
			}
			break;
		}
		if (*pattp == NULL_CHAR) {
			gl.regm.rm_so = save;
			gl.regm.rm_eo = save+strlen(str);
			if (gl.flags & FL_SAVEBASE) {
				gl.matchBase = data;
			}
			return(1);	/* end of pattern == success */
		}
		if (*bufp == NULL_CHAR) {
			return(0);	/* end of buffer == success */
		}
	}
	return(0);
}
