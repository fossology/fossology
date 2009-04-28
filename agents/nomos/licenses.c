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
#include <stdio.h>
#include <string.h>
#include <ctype.h>
#include <time.h>
#include <signal.h>

#include "nomos.h"
#include "licenses.h"
#include "util.h"
#include "list.h"
#include "nomos_regex.h"
#include "parse.h"
#include "_autodefs.h"

#define	HASHES		"#####################"
#define	DEBCPYRIGHT	"debian/copyright"

static void decrypt(char *, int);
static void prettyPrint(FILE *, char *, int);
static void makeLicenseSummary(list_t *, int, char *, int);
static void noLicenseFound(int);
static void licenseStringChecks();
static int searchStrategy(int, char *, int);
static void findLines(char *, char *, int, int, list_t *);
static void saveLicenseData(scanres_t *, int, int, int, int);

static int cryptPeriod = (4*5)+7;	/* obfuscated 27 */
static int cryptOffset = 5*7;		/* obfuscated 35 == '#' */
static char miscbuf[myBUFSIZ], any[6], some[7], few[6], year[7];

#ifdef	MEMSTATS
extern void memStats();
#endif	/* MEMSTATS */
#ifdef	STOPWATCH
DECL_TIMER;
int timerBytes;
char timerName[64];
#endif	/* STOPWATCH */

#define	MAX(a, b)	((a) > (b) ? a : b)
#define	MIN(a, b)	((a) < (b) ? a : b)


void licenseInit()
{
	register int i, j, len, same, ssAbove, ssBelow;
	register licText_t *ltp = licText;
	register licSpec_t *lp = licSpec;
	register item_t *p;
	register char *cp;
	char buf[myBUFSIZ], nilname[7];
	extern item_t *listLookupName();
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== licenseInit()\n");
#endif	/* PROC_TRACE */
/* */
	cp = any;	/* don't use strcpy; this will provide... */
	*cp++ = '=';	/* even more obfuscation! */
	*cp++ = 'A';
	*cp++ = 'N';
	*cp++ = 'Y';
	*cp++ = '=';
	*cp = NULL_CHAR;
	cp = some;
	*cp++ = '=';
	*cp++ = 'S';
	*cp++ = 'O';
	*cp++ = 'M';
	*cp++ = 'E';
	*cp++ = '=';
	*cp = NULL_CHAR;
	cp = few;
	*cp++ = '=';
	*cp++ = 'F';
	*cp++ = 'E';
	*cp++ = 'W';
	*cp++ = '=';
	*cp = NULL_CHAR;
	cp = year;
	*cp++ = '=';
	*cp++ = 'Y';
	*cp++ = 'E';
	*cp++ = 'A';
	*cp++ = 'R';
	*cp++ = '=';
	*cp = NULL_CHAR;
	cp = nilname;
	*cp++ = '=';
	*cp++ = 'N';
	*cp++ = 'U';
	*cp++ = 'L';
	*cp++ = 'L';
	*cp++ = '=';
	*cp = NULL_CHAR;
	listInit(&gl.sHash, 0, "search-cache");
/*
 * Unpack the encrypted strings and look for 3 corner-cases to optimize
 * all the regex-searches we'll be making:
 * (a) the seed string is the same as the text-search string
 * (b) the text-search string has length 1 and contents == "."
 * ... with encryption, '.' == decimal 46 => encrypted int 11 (013).
 * (c) the seed string is the 'null-string' indicator
 */
#ifdef	OLD_DECRYPT
#define	SCHAR	'\013'
#else	/* OLD_DECRYPT */
#define	SCHAR	'.'
#endif	/* OLD_DECRYPT */
	for (i = 0; i < NFOOTPRINTS; i++, lp++, ltp++) {
		same = 0;
		len = lp->seed.csLen;
		if (lp->text.csData == NULL_STR) {
			ltp->tseed = "(null)";
		}
		if (lp->text.csLen == 1 && *(lp->text.csData) == SCHAR) {
			same++;
		}
		else if (lp->seed.csLen == lp->text.csLen &&
		    memcmp(lp->seed.csData, lp->text.csData, len) == 0) {
		    	same++;
		}
/*
 * Step 1, copy the tseed "search seed", decrypt it, and munge any wild-
 * cards in the string.  Note that once we eliminate the compile-time
 * string encryption, we could re-use the same exact data.  In fact, some
 * day (in our copious spare time), we could effectively remove licSpec.
 */
#ifdef	OLD_DECRYPT
		memcpy(buf, lp->seed.csData, (size_t) (len+1));
		decrypt(buf, len);
#endif	/* OLD_DECRYPT */
#ifdef	FIX_STRINGS
		fixSearchString(buf, sizeof(buf), i, YES);
#endif	/* FIX_STRINGS */
#ifdef	OLD_DECRYPT
		ltp->tseed = copyString(buf, MTAG_SEEDTEXT);
#else	/* not OLD_DECRYPT */
		ltp->tseed = lp->seed.csData;
#endif	/* not OLD_DECRYPT */
/*
 * Step 2, add the search-seed to the search-cache
 */
		if ((p = listGetItem(&gl.sHash, ltp->tseed)) == NULL_ITEM) {
			Fatal("Cannot enqueue search-cache item \"%s\"",
			    ltp->tseed);
		}
		p->refCount++;
/*
 * Step 3, handle special cases of NULL seeds and (regex == seed)
 */
		if (strcmp(ltp->tseed, nilname) == 0) {	/* null */
#ifdef	OLD_DECRYPT
			memFree(ltp->tseed, MTAG_SEEDTEXT);
#endif	/* OLD_DECRYPT */
			ltp->tseed = NULL_STR;
			ltp->nAbove = ltp->nBelow = -1;
		}
		if (same) {		/* seed == phrase */
			ltp->regex = ltp->tseed;
#if	0
			ssBelow = searchStrategy(i, buf, NO);
			ltp->nBelow = MIN(ssBelow, 2);
#endif
			ltp->nAbove = ltp->nBelow = 0;
		}
/*
 * Step 4, decrypt and fix the regex (since seed != regex here).  Once
 * we have all that, searchStrategy() helps determine how many lines
 * above and below [the seed] to save -- see findPhrase() for details.
 */
		else {				/* seed != phrase */
			len = lp->text.csLen;
			memcpy(buf, lp->text.csData, (size_t) (len+1));
#ifdef	OLD_DECRYPT
			decrypt(buf, len);
#endif	/* OLD_DECRYPT */
			ssAbove = searchStrategy(i, buf, YES);
			ssBelow = searchStrategy(i, buf, NO);
#if	0
			ltp->nAbove = MIN(ssAbove, 3);
			ltp->nBelow = MIN(ssBelow, 6);
#endif
			ltp->nAbove = ltp->nBelow = 1;	/* for now... */
#ifdef	FIX_STRINGS
			fixSearchString(buf, sizeof(buf), i, NO);
#endif	/* FIX_STRINGS */
			ltp->regex = copyString(buf, MTAG_SRCHTEXT);
		}
		if (p->ssComp < (ssAbove*100)+ssBelow) {
			p->ssComp = (ssAbove*100)+ssBelow;
		}
		ltp->compiled = 0;
		ltp->plain = 1;	/* assume plain-text for now */
	}
/*
 * Now that we've computed the above- and below-values for license
 * searches, set each of the appropriate entries with the MAX values
 * determined.  Limit 'above' values to 3 and 'below' values to 6.
 *****
 * QUESTION: the above has worked in the past - is it STILL valid?
 */
 	for (ltp = licText, i = 0; i < NFOOTPRINTS; i++, ltp++) {
		if (ltp->tseed == NULL_STR) {
#ifdef	LICENSE_DEBUG
			Note("License[%d] configured with NULL seed", i);
#endif	/* LICENSE_DEBUG */
			continue;
		}
		if (ltp->tseed == ltp->regex) {
#ifdef	LICENSE_DEBUG
			Note("License[%d] seed == regex", i);
#endif	/* LICENSE_DEBUG */
			continue;
		}
#if	0
		p = listLookupName(&gl.sHash, ltp->tseed);
#endif
		ltp->nAbove = p->ssComp / 100;
		ltp->nBelow = p->ssComp % 100;
	}
#if	((DEBUG > 5) || defined LICENSE_DEBUG)
	listDump(&gl.sHash, NO);
#endif	/* (DEBUG>5 || LICENSE_DEBUG) */
/*
 * Finally (if enabled), compare each of the search strings to see if
 * there are duplicates, and determine if some of the regexes can be
 * searched via strstr() (instead of it's slower-but-more-functional
 * regex brethern).
 */
	for (ltp = licText, i = 0; i < NFOOTPRINTS; i++, ltp++) {
		for (cp = _REGEX(i); ltp->plain && *cp; cp++) {
			switch (*cp) {
				case '.': case '*': case '+': case '|':
				case '[': case ']': case '(': case ')':
				case '^': case '$': case '?': case ',':
				case '<': case '>': case '{': case '}':
				case '\\':
					ltp->plain = 0;
					break;
			}
		}
#if	0
		if (ltp->plain) {
			printf("PLAIN[%03d]: \"%s\"\n", i, ltp->regex);
		}
#endif
		if (i >= _CR_first && i <= _CR_last) {
			continue;
		}
	}
	return;
}

/*
 * This function should be called BEFORE the wild-card specifier =ANY=
 * is converted to a REAL regex ".*" (e.g., before fixSearchString())!
 *****
 * ASSUME a "standard line-length" of 50 characters/bytes.  That's
 * likely too small, but err on the side of being too conservative.
 *****
 * determining for the number of text-lines ABOVE involves finding out
 * how far into the 'license footprint' the seed-word resides.  ASSUME
 * a standard line-length of 50 (probably too small, but we'll err on
 * the side of being too conservative.  If the seed isn't IN the regex,
 * assume a generally-bad worst-case and search 2-3 lines above.
 *****
 * determining for the number of text-lines BELOW involves finding out
 * how long the 'license footprint' actually is, plus adding some fudge
 * based on the number of wild-cards in the footprint.
 */

#define	LINE_BYTES	50	/* fudge for punctuation, etc. */
#define	LINE_WORDS	8	/* assume this many words per line */
#define	WC_BYTES	30	/* wild-card counts this many bytes */
#define	WC_WORDS	3	/* wild-card counts this many words */
#define	PUNT_LINES	3	/* if "dunno", guess this line-count */
#define	MIN_LINES	1	/* normal minimum-extra-lines */

static int searchStrategy(int index, char *regex, int aboveCalc)
{
	register char *start, *cp, *s;
	char seed[myBUFSIZ];
	register int words, lines, bytes, minLines, matchWild, matchSeed;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== searchStrategy(%d(%s), \"%s\", %d)\n", index, _SEED(index),
	    regex, aboveCalc);
#endif	/* PROC_TRACE */
/* */
	s = _SEED(index);
	if (s == NULL_STR || strlen(s) == 0) {
#ifdef	LICENSE_DEBUG
		Note("Lic[%d] has NULL seed", index);
#endif	/* LICENSE_DEBUG */
		return(0);
	}
	if (regex == NULL_STR || strlen(regex) == 0) {
#ifdef	LICENSE_DEBUG
		Assert(NO, "searchStrategy(%d) called with NULL data", index);
#endif	/* LICENSE_DEBUG */
		return(0);
	}
	if (strcmp(s, regex) == 0) {
		return(0);
	}
	bytes = words = lines = 0;
	(void) strcpy(seed, s);
	while (seed[strlen(seed)-1] == ' ') {
		seed[strlen(seed)-1] = NULL_CHAR;
	}
/* how far ABOVE to look depends on location of the seed in footprint */
	if (aboveCalc) {
		if (strGrep(seed, regex, REG_ICASE) == 0) {
#ifdef	LICENSE_DEBUG
			printf("DEBUG: seed(%d) no hit in regex!\n", index);
#endif	/* LICENSE_DEBUG */
			return(PUNT_LINES);	/* guess */
		}
		for (minLines = 0, cp = start = regex; cp; start = cp+1) {
			matchWild = matchSeed = 0;
			if ((cp = strchr(start, ' ')) != NULL_STR) {
				*cp = NULL_CHAR;
			}
			matchWild = (strcmp(start, any) == 0 ||
			    strcmp(start, some) == 0 || strcmp(start, few));
			matchSeed = strcmp(start, seed) == 0;
			if (!matchSeed) {
				bytes += (matchWild ? WC_BYTES :
				    strlen(start)+1);
				words += (matchWild ? WC_WORDS : 1);
			}
			if (cp != NULL_STR) {
				*cp = ' ';
			}
			if (matchSeed) {	/* found seed? */
				break;
			}
		}
/* optimization for single-lines: */
		minLines += (words >= LINE_WORDS/2 && words < LINE_WORDS);
		lines = MAX(bytes/LINE_BYTES, words/LINE_WORDS)+minLines;
#ifdef	LICENSE_DEBUG
		printf("ABOVE: .... bytes=%d, words=%d; max(%d,%d)+%d == %d\n",
		    bytes, words, bytes/LINE_BYTES, words/LINE_WORDS,
		    minLines, lines);
#endif	/* LICENSE_DEBUG */
		return(words == 0 ? 0 : lines);
	}
/* calculate how far below to look -- depends on length of footprint */
	for (minLines = MIN_LINES, cp = start = regex; cp; start = cp+1) {
		matchWild = matchSeed = 0;
		if ((cp = strchr(start, ' ')) != NULL_STR) {
			*cp = NULL_CHAR;
		}
		matchWild = (strcmp(start, any) == 0 ||
		    strcmp(start, some) == 0 || strcmp(start, few));
		matchSeed = strcmp(start, seed) == 0;
		if (matchSeed) {
			bytes = words = 0;
			/*minLines = MIN_LINES+1;*/
		}
		else {
			bytes += (matchWild ? WC_BYTES : strlen(start)+1);
			words += (matchWild ? WC_WORDS : 1);
		}
		if (cp != NULL_STR) {
			*cp = ' ';
		}
	}
	lines = MAX(bytes/LINE_BYTES, words/LINE_WORDS)+minLines;
#ifdef	LICENSE_DEBUG
	printf("BELOW: .... bytes=%d, words=%d; max(%d,%d)+%d == %d\n",
	    bytes, words, bytes/LINE_BYTES, words/LINE_WORDS, minLines, lines);
#endif	/* LICENSE_DEBUG */
	return(lines);
}

#ifdef	FIX_STRINGS
static void fixSearchString(char *s, int size, int i, int wildcardBad)
{
	register char *cp;
	register int len;
	char wildCard[16];
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== fixSearchString(\"%s\", %d, %d, %d)\n", s, size, i,
	    wildcardBad);
#endif	/* PROC_TRACE */
/* */
/*
 * Decrypt the text-string and then replace all instances of our wild-
 * card string =ANY= to ".*".  This may appear stupid on the surface,
 * but the string =ANY= is *more* noticable when examining the text
 * than an 'embedded' .* wild-card is.  Same for =SOME=...
 *****
 * Make sure the search string does NOT start with a wild-card; it's not
 * necessary and will probably double execution time.  Once we know the
 * first text is 'not wild', walk through an replace our strange =ANY=
 * wildcards with regex(7) wild-cards. The only magic is to ensure the
 * string doesn't END in a wild-card, either (more performance dumb-ness).
 */
	cp = s;
	while (isspace(*cp)) {
		cp++;
	}
	if (strncmp(cp, any, sizeof(any)-1) == 0 ||
	    strncmp(cp, some, sizeof(some)-1) == 0 ||
	    strncmp(cp, few, sizeof(few)-1) == 0) {
		printf("string %d == \"%s\"\n", i, cp);
		Fatal("Text-spec %d begins with a wild-card", i);
	}
/*
 * We'll replace the string " =ANY=" (6 chars) with ".*" (2 chars).
 * The token MUST OCCUR BY ITSELF (e.g., not a substring)!
 */
	(void) sprintf(wildCard, " %s", any);
	len = strlen(wildCard);
	for (cp = s; strGrep(wildCard, cp, 0); ) {
		if (wildcardBad) {
			Fatal("OOPS, regex %d, wild-card not allowed here", i);
		}
		if (*(cp+gl.regm.rm_eo) == NULL_CHAR) {
			Fatal("String %d ends in a wild-card", i);
		}
		else if (*(cp+gl.regm.rm_eo) == ' ') {
#ifdef	DEBUG
			printf("BEFORE(any): %s\n", s);
#endif	/* DEBUG */
			cp += gl.regm.rm_so;
			*cp++ = '.';
			*cp++ = '*';
			memmove(cp, cp+len-1, strlen(cp+len)+2);
#ifdef	DEBUG
			printf("_AFTER(any): %s\n", s);
#endif	/* DEBUG */
		}
		else {
			Note("Wild-card \"%s\" sub-string, phrase %d", wildCard, i);
			cp += gl.regm.rm_eo;
		}
	}
/*
 * Ditto for replacing " =SOME= " (8 chars) with ".{0,60}" (7 chars)
 */
	(void) sprintf(wildCard, " %s", some);
	len = strlen(wildCard);
	for (cp = s; strGrep(wildCard, cp, 0); ) {
		if (wildcardBad) {
			Fatal("OOPS, regex %d, wild-card not allowed here", i);
		}
		if (*(cp+gl.regm.rm_eo) == NULL_CHAR) {
			Fatal("String %d ends in a wild-card", i);
		}
		else if (*(cp+gl.regm.rm_eo) == ' ') {
#ifdef	DEBUG
			printf("BEFORE(some): %s\n", s);
#endif	/* DEBUG */
			cp += gl.regm.rm_so;
			*cp++ = '.';
			*cp++ = '{';
			*cp++ = '0';
			*cp++ = ',';
			*cp++ = '6';
			*cp++ = '0';
			*cp++ = '}';
			memmove(cp, cp+len-6, strlen(cp+len)+7);
#ifdef	DEBUG
			printf("_AFTER(some): %s\n", s);
#endif	/* DEBUG */
		}
		else {
			Note("Wild-card \"%s\" sub-string, phrase %d", wildCard, i);
			cp += gl.regm.rm_eo;
		}
	}
/*
 * And, same for replacing " =FEW= " (7 chars) with ".{0,15}" (7 chars)
 */
	(void) sprintf(wildCard, " %s", few);
	len = strlen(wildCard);
	for (cp = s; strGrep(wildCard, cp, 0); ) {
		if (wildcardBad) {
			Fatal("OOPS, regex %d, wild-card not allowed here", i);
		}
		if (*(cp+gl.regm.rm_eo) == NULL_CHAR) {
			Fatal("String %d ends in a wild-card", i);
		}
		else if (*(cp+gl.regm.rm_eo) == ' ') {
#ifdef	DEBUG
			printf("BEFORE(few): %s\n", s);
#endif	/* DEBUG */
			cp += gl.regm.rm_so;
			*cp++ = '.';
			*cp++ = '{';
			*cp++ = '0';
			*cp++ = ',';
			*cp++ = '3';
			*cp++ = '0';
			*cp++ = '}';
			memmove(cp, cp+len-6, strlen(cp+len)+7);
#ifdef	DEBUG
			printf("_AFTER(few): %s\n", s);
#endif	/* DEBUG */
		}
		else {
			Note("Wild-card \"%s\" sub-string, phrase %d", wildCard, i);
			cp += gl.regm.rm_eo;
		}
	}
/*
 * AND, replace the string "=YEAR=" with "[12][0-9][0-9][0-9][,- ]*".
 * The former is 6 chars in length, the latter is 24.  We must be careful
 * not to overflow the buffer we're passed.
 */
	len = strlen(year);
	while (strGrep(year, s, 0)) {
		if (strlen(s)+25 >= size) {	/* 24 plus 1(NULL) */
			Fatal("buffer overflow, text-spec %d", i);
		}
		cp = (char *)(s+gl.regm.rm_so);
#ifdef	DEBUG
		printf("BEFORE: %s\n", s);
#endif	/* DEBUG */
		memmove(cp+25, cp+6, strlen(cp+len)+1);	/* was 26, 6 */
		memset(cp+6, '_', 19);
#ifdef	DEBUG
		printf("_MOVED: %s\n", s);
#endif	/* DEBUG */
		*cp = *(cp+4) = *(cp+9) = *(cp+14) = *(cp+19) = '[';
		*(cp+1) = '1';
		*(cp+2) = '2';
		*(cp+5) = *(cp+10) = *(cp+15) = '0';
		*(cp+6) = *(cp+11) = *(cp+16) = '-';
		*(cp+7) = *(cp+12) = *(cp+17) = '9';
		*(cp+3) = *(cp+8) = *(cp+13) = *(cp+18) = *(cp+23) = ']';
		*(cp+20) = ' ';
		*(cp+21) = ',';
		*(cp+22) = '-';
		*(cp+24) = '*';
#ifdef	DEBUG
		printf("_AFTER: %s\n", s);
#endif	/* DEBUG */
	}
	return;
}
#endif	/* FIX_STRINGS */


/*
  We get a list passed in, but it'll always be just a single file
*/
void licenseScan(list_t *l)
{
	register int i, c, lowWater, lowest, nCand, nSkip;
	register char *textp, *cp;
	int counts[NKEYWORDS+1];
	scanres_t *scores, *scp;
	int scoreCompare();
	register item_t *p;
#ifdef	SCORE_DEBUG
	FILE *scoreFp;
#endif	/* SCORE_DEBUG */
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== licenseScan(%p, %d)\n", l);
#endif	/* PROC_TRACE */
/* */
#ifdef	MEMSTATS
	printf("... allocating %d bytes for scanres_t[] array\n",
	    sizeof(*scp)*l->used);
#endif	/* MEMSTATS */
	scores = (scanres_t *) memAlloc(sizeof(*scp) * l->used, MTAG_SCANRES);
	memset((void *)counts, 0, (size_t)((NKEYWORDS+1)*sizeof(int)));
/*
 * For EACH file, determine if we want to scan it, and if so, scan
 * the candidate files for keywords (to obtain a "score" -- the higher
 * the score, the more likely it has a real open source license in it).
 *****
 * There are lots of things that will 'disintest' us in a file (below).
 *****
 * PERFORMANCE NOTE: this loop is called 400,000 to 500,000 times
 * when parsing a distribution.  Little slow-downs ADD UP quickly!
 */
#ifdef	STOPWATCH
	timerBytes = 0;
	START_TIMER;
#endif	/* STOPWATCH */
	lowest = nSkip = 0;
	for (scp = scores; (p = listIterate(l)) != NULL_ITEM; scp++) {
#ifdef	QA_CHECKS
		if (p->iFlag) {
			Assert(NO, "Non-zero iFlag for %s", p->str);
		}
#endif	/* QA_CHECKS */
/*
 * Use *relative* pathnames wherever possible -- we'll spend less time in
 * the kernel looking up inodes and pathname components that way.
 */
		if (*(p->str) == '/') {
			(void) strcpy(scp->fullpath, p->str);
			scp->nameOffset = (size_t) (gl.targetLen+1);
			cp = scp->fullpath;	/* full pathname */
		}
		else {
			(void) sprintf(scp->fullpath, "%s/%s", gl.cwd, p->str);
			scp->nameOffset = (size_t) (gl.cwdLen+1);
			cp = p->str;	/* relative path == faster open() */
		}
#ifdef	DEBUG
		printf("licenseScan: scan %s\n",
		    (char *)(scp->fullpath+scp->nameOffset));
#endif	/* DEBUG */
/*
 * Zero-length files are of no interest; there's nothing in them!
 *  CDB - We need to report this error somehow...
 */
		if ((textp = mmapFile(cp)) == NULL_STR) {
			perror(cp);
#ifdef	QA_CHECKS
			Assert(NO, "zero-length: %s", cp);
			mySystem("ls -l '%s'", cp);
#endif	/* QA_CHECKS */
			continue;
		}
		scp->size = gl.stbuf.st_size; 
		gl.totBytes += (double) scp->size;
		(void) strcpy(scp->ftype, magic_buffer(gl.mcookie, textp,
		    (size_t) scp->size));
#ifdef	DEBUG
		printf("Magic: %s\n", scp->ftype);
#endif	/* DEBUG */
/*
 * Disinterest #3 (discriminate-by-file-content):
 * Files not of a known-good type (as reported by file(1)/magic(3)) should
 * also be skipped (some are quite large!).  _UTIL_MAGIC (see _autodata.c)
 * contains a regex for MOST of the files we're interested in, but there
 * ARE some exceptions (logged below).
 *****
 * exception (A): patch/diff files are sometimes identified as "data".
 *****
 * FIX-ME: we don't currently use _UTIL_FILTER, which is set up to
 * exclude some files by filename.
 */
		if (idxGrep(_UTIL_MAGIC, scp->ftype, REG_ICASE|REG_EXTENDED)) {
			for (scp->kwbm = c = 0; c < NKEYWORDS; c++) {
				if (idxGrep(c+_KW_first, textp,
				    REG_EXTENDED|REG_ICASE)) {
					scp->kwbm |= (1 << c);
					scp->score++;
#if	(DEBUG > 5)
		printf("Keyword %d (\"%s\"): YES\n", c, _REGEX(c+_KW_first));
#endif	/* DEBUG > 5 */
				}
			}
		}
		else {
			scp->score = 0;
		}
		munmapFile(textp);
#if	(DEBUG > 5)
		printf("%s = %d\n", (char *)(scp->fullpath+scp->nameOffset),
		    scp->score);
#endif	/* DEBUG > 5 */
	}
	c = l->used;
/*
 * If we were invoked with a single-file-only option, just over-ride the
 * score calculation -- give the file any greater-than-zero score so it
 * appears as a valid candidate.  This is important when the file to be
 * evaluated has no keywords, yet might contain authorship inferences.
 */
	if (scores->score == 0) {
		scores->score = 1;	/* force to be a valid candidate  */
	}
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("=> invoking qsort(): callback == scoreCompare()\n");
#endif	/* PROC_TRACE */
	qsort(scores, (size_t) c, sizeof(*scp), scoreCompare);
/*
 * record the highest score of license candidates; then, determine the
 * minimum score we'll care about (which is based on high-score)
 */
	gl.fSearch += c;
#ifdef	DEBUG
	printf("Score = %d (%d %s searched)\n", scores->score, l->used,
	    pluralName("file", l->used));
	printf("... total files = %d\n", gl.fSearch);
#endif	/* DEBUG */
/*
 * Set up defaults for the minimum-scores for which we'll save files.
 * Try to ensure a minimum # of license files will be recorded for this
 * source/package (try, don't force it too hard); see if lower scores
 * yield a better fit, but recognize the of a non-license file increases
 * as we lower the bar.
 */
	lowWater = 1;
#ifdef	SCORE_DEBUG
/*
 * Sanity-check the high-scores versus availability of thesaurus or
 * dictionary files.
 */
	if (scores->score < abs(lowest)) {
		Warn("%s: thesaurus is best (tot %d)!\n", cur.basename, nSkip);
		printf("scores->score %d, lowest %d\n", lowest);
	}
#endif	/* SCORE_DEBUG */
/*
 * Run through the list once more; this time we record and count the
 * license candidates to process.  License candidates are determined
 * by either (score >= low) *OR* matching a set of filename patterns.
 *****
 * Per Scott Peterson, if we see a pathname "debian/copyright", it's
 * of significant interest -- even if it's a blank/empty file!
 */
#ifdef	SCORE_DEBUG
	scoreFp = fopenFile("../_scores.ALL", "w");
#endif	/* SCORE_DEBUG */
	for (scp = scores, i = nCand = 0; i < c; i++, scp++) {
		scp->relpath = (char *)(scp->fullpath + scp->nameOffset);
#if	0
		if (idxGrep(_FN_DEBCPYRT, scp->relpath, REG_ICASE)) {
			scp->flag = 2;
		}
		else if (scp->flag == 0 && idxGrep(_FN_LICENSEPATT,
		    pathBasename(scp->relpath), REG_ICASE|REG_EXTENDED)) {
			scp->flag = 1;
		}
		else if (scp->flag == 0 && scp->score >= lowWater) {
			scp->flag |= 1;
		}
/*
 * If we want to save files based on their contents (e.g., specific words
 * such as "license" or "copyright", include this.
 */
		if (idxGrep(_KW_license, textp, 0) ||
		    idxGrep(_KW_copyright, textp, 0)) {
			scp->flag = 1;
		}
#endif
		if (idxGrep(_FN_LICENSEPATT, pathBasename(scp->relpath),
		    REG_ICASE|REG_EXTENDED)) {
			scp->flag = 1;
			if (idxGrep(_FN_DEBCPYRT, scp->relpath, REG_ICASE)) {
				scp->flag = 2;
			}
		}
		else if (scp->score >= lowWater) {
			scp->flag |= 1;
		}
/*
 * So now, save any license candidate EITHER named "debian/copyright*"
 * OR having a score > 0
 */
		if (scp->flag == 2 || (scp->score && scp->flag)) {
#if	(DEBUG > 3)
			printf("%s [score: %d], %07o\n", scp->fullpath,
			    scp->score, scp->kwbm);
#endif	/* DEBUG > 3 */
			nCand++;
		}
#ifdef	SCORE_DEBUG
		fprintf(scoreFp, "%7d %s\n", scp->score, scp->relpath);
#endif	/* SCORE_DEBUG */
	}
#ifdef	SCORE_DEBUG
	(void) fclose(scoreFp);
#endif	/* SCORE_DEBUG */
#ifdef	STOPWATCH
	END_TIMER;
	(void) sprintf(timerName, "scan+sort+select (%d candidates)", nCand);
	PRINT_TIMER(timerName, 0);
	START_TIMER;	/* stops in saveLicenseData! */
#endif	/* STOPWATCH */
/*
 * OF SPECIAL INTEREST: saveLicenseData() changes directory (to "..")!!!
 */
	saveLicenseData(scores, nCand, c, lowWater, NO);
/*
 * At this point, we don't need either the raw-source directory or the
 * unpacked results anymore, so get rid of 'em.
 */
	removeDir(RAW_DIR);
	memFree((char *) scores, "scores table");
	return;
}



/*
 * NOTE: this procedure is a qsort callback that provides a REVERSE
 * integer sort (highest to lowest)
 */
int scoreCompare(scanres_t *sc1, scanres_t *sc2)
{
	if (sc1->score > sc2->score) {
		return(-1);
	}
	else if (sc1->score < sc2->score) {
		return(1);
	}
	else if (sc1->fullpath != NULL_STR &&
	    sc2->fullpath == NULL_STR) {
		return(-1);
	}
	else if (sc2->fullpath != NULL_STR &&
	    sc1->fullpath == NULL_STR) {
		return(1);
	}
	else {
		return(-strcmp(sc1->fullpath, sc2->fullpath));
	}
}

static void noLicenseFound(int isPackage)
{
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== noLicenseFound(%d)\n", isPackage);
#endif	/* PROC_TRACE */
/* */
	if (gl.flags & FL_TESTPKG) {
		(void) sprintf(cur.compLic, "%s", WHO_KNOWS);
	}
	else if (isPackage && cur.isRpm) {
		(void) sprintf(cur.compLic, "%s (RPM PkgHdr: %s)", LS_NOSUM,
		    cur.claimlic);
	}
	else if (isPackage) {
		(void) sprintf(cur.compLic, "%s (no DEB PkgHdr)", LS_NOSUM);
	}
	else {
		(void) strcpy(cur.compLic, LS_NOSUM);
	}
	return;
}

/*
 * This function creates all the license-data in a specific directory.
 *****
 * OF SPECIAL INTEREST: this function changes directory!
 */
static void saveLicenseData(scanres_t *scores, int nCand, int nElem,
    int lowWater, int isPackage)
{
	scanres_t *scp;
	register int i, c, base, size, highScore = scores->score, fCnt, adj,
	    isML = 0, isPS = 0;
#ifdef	HP_INTERNAL
	register int offset;
#endif	/* HP_INTERNAL */
	register char *cp;
	char result[16], *odds, *textp, *date;
	FILE *fp, *webFp, *linkFp, *altFp, *scoreFp;
	register item_t *p;
	static list_t lList, cList, eList;
	static int firstFlag = 1;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== saveLicenseData(%p, %d, %d, %d, %d)\n", scores, nCand,
	    nElem, lowWater, isPackage);
#endif	/* PROC_TRACE */
/* */
/*
 * Save the necessary licensing information in a list of files...
 */
#ifdef	DEBUG
	printf("saveLicenseData: %d candidates\n", nCand);
#endif	/* DEBUG */
	adj = strlen(RAW_DIR)+1;

	changeDir("..");
/*
 * Question: should these 'static' lists move somewhere else?
 * 
 * CDB - Are they even used ?
 */
	if (firstFlag) {
		listInit(&lList, 0, "license-list");
		listInit(&cList, 0, "copyright-list");
		listInit(&eList, 0, "eula-list");
		firstFlag = 0;
	}
/* BE PERFORMANCE-CONSCIOUS WITHIN THIS LOOP (it runs a LOT!) */
/*
 * OPTIMIZE-ME: should we store local variables and use lots of
 * registers instead of accessing everything through the scanres
 * array?  We've got to be doing some serious address calculations.
 */
	scoreFp = fopenFile(FILE_SCORES, "w");
	for (fCnt = 0, i = 1, scp = scores; i <= nCand; scp++) {
/*
 * If we didn't flag this file as needing to be saved, ignore it.
 */
 		if (scp->flag == 0) {
			continue;
		}
		if (scp->flag > 1) {
			fCnt++;
		}
		fprintf(scoreFp, "%7d %s\n", scp->score, scp->relpath);
		(void) sprintf(scp->linkname, "Link%03d.txt", i++);
		linkFp = fopenFile(scp->linkname, "w+");
		fprintf(linkFp, "[%s]\n%s %s\n", LABEL_ATTR, LABEL_PATH,
		    scp->relpath);
#if	DEBUG > 5
		printf("name: %s\n[%s]\n", scp->relpath, scp->fullpath);
#endif	/* DEBUG > 5 */
/*
 * Kludge up the pointer to the relative-path in scp->fullpath so we don't
 * have to as many directory entries to open each file... this works for
 * anything EXCEPT 'distribution files'.
 */

/* "cp" is the filename of the file we're scanning. */
		cp = (gl.flags & FL_DISTFILES) ? scp->fullpath :
		    scp->relpath-adj;
		if (optionIsSet(OPTS_DEBUG)) {
			printf("File name: %s\n", cp);
		}
 		if ((textp = mmapFile(cp)) == NULL_STR) {
			Fatal("Null mmapFile(), path=%s", cp);
		}
		size = (int) gl.stbuf.st_size;
		fprintf(linkFp, "Type: %s\n%s %s, %d bytes\n",
		    scp->ftype, LABEL_CNTS, wordCount(textp), scp->size);
/*
 * Report which package (if any) this file came from
 */
		fprintf(linkFp, "Package: %s\n",
		    isPackage ? cur.basename : STR_NOTPKG);
#ifdef	HP_INTERNAL
/*
 * construct the list of keywords that matched in licenseScan()
 */
		fprintf(linkFp, "Score: %d\n", scp->score);
		(void) strcpy(miscbuf, "Matches: ");
		offset = 9;	/* e.g., strlen("Matches: ") */
		for (base = c = 0; c < NKEYWORDS; c++) {
			if (scp->kwbm & (1 << c)) {
				if (base++) {
					miscbuf[offset++] = ',';
					miscbuf[offset++] = ' ';
				}
#if	0
				sprintf(miscbuf+offset, "%s",
				    _REGEX(c+_KW_first));
				offset += strlen(_REGEX(c+_KW_first));
#endif
				offset += sprintf(miscbuf+offset, "%s",
				    _REGEX(c+_KW_first));
			}
		}
/*
 * Since we hard-wire the score of every file (invoked as --file), a score
 * of 1 could be either 0 or 1, so scp->kwbm tells the real story...
 */
		if (optionIsSet(OPTS_DEBUG)) {
			printf("File type: %s\n", scp->ftype);
			printf("File score: %d (0x%06x)\n",
			    scp->kwbm ? scp->score : scp->kwbm, scp->kwbm);
			if (scp->kwbm) {
				printf("%s\n", miscbuf);
			}
		}
		prettyPrint(linkFp, miscbuf, 3);
#endif	/* HP_INTERNAL */
/*
 * Print the license claim (e.g., what's listed in the package)
 */
		fprintf(linkFp, "License Claim: %s\n",
		    isPackage ? cur.claimlic : STR_NOTPKG);
/*
 * determine licenses in the file, and record 'em; wrap up by including
 * the file contents
 *****
 * FIX-ME: we should filter some names out like the shellscript does.
 * For instance, word-spell-dictionary files will score high but will
 * likely NOT contain a license.  But the shellscript filters these
 * names AFTER they're already scanned.  Think about it.
 *****
 FILTERPATTERNS="(/man|\.[0-9]|\.[0-9][a-z]|rfc[0-9].*|.po|.pot"
 FILTERPATTERNS="$FILTERPATTERNS|words.*|.*spelling.*|spell)$"
 */
#if	defined(DEBUG) || defined(DOCTOR_DEBUG) || defined(LTSR_DEBUG) \
    || defined(BATCH_DEBUG) || defined(PARSE_STOPWATCH) || defined(MEMSTATS)
		printf("*** PROCESS File: %s\n", scp->relpath);
		printf("... %d bytes, score %d, %s\n", size, scp->score,
		    scp->ftype);
#endif /* DEBUG || DOCTOR_DEBUG || LTSR_DEBUG || BATCH_DEBUG || PARSE_STOPWATCH || MEMSTATS */
		isML = idxGrep(_UTIL_MARKUP, textp, REG_ICASE|REG_EXTENDED);
#ifdef	DOCTOR_DEBUG
		printf("idxGrep(ML) returns %d\n", isML);
		if (isML) {
			int i;
			printf("isMarkUp@%d: [", gl.regm.rm_so);
			for (i = gl.regm.rm_so; i <= gl.regm.rm_eo; i++) {
				printf("%c", *(textp+i));
			}
			printf("]\n");
		}
#endif	/* DOCTOR_DEBUG */
/*
 * BUG: When _FTYP_POSTSCR is "(postscript|utf-8 unicode)", the resulting
 * license-parse yields 'NoLicenseFound' but when both "postscript" and
 * "utf-8 unicode" are searched independently, parsing definitely finds
 * quantifiable licenses. WHY?
 */
		if (idxGrep(_FTYP_POSTSCR, scp->ftype, REG_ICASE) ||
		    idxGrep(_FTYP_UTF8, scp->ftype, REG_ICASE) ||
		    idxGrep(_FTYP_RTF, scp->ftype, REG_ICASE)) {
		    	isPS = 1;
		}
#ifdef	DOCTOR_DEBUG
		printf("idxGrep(PS) returns %d\n", isPS);
		if (isPS) {
			int i;
			printf("isPostScript@%d: [", gl.regm.rm_so);
			for (i = gl.regm.rm_so; i <= gl.regm.rm_eo; i++) {
				printf("%c", *(scp->ftype+i));
			}
			printf("]\n");
		}
#endif	/* DOCTOR_DEBUG */
/* 
 * Interesting - copyString(parseLicenses(args), MTAG_FILELIC)...
 * will randomly segfault on 32-bit Debian releases.  Split the calls.
 */
		cp = parseLicenses(textp, size, scp, isML, isPS);
		scp->licenses = copyString(cp, MTAG_FILELIC);
#ifdef	QA_CHECKS
		if (cp == NULL_STR) {
			Assert(NO, "Expected non-null parseLicenses return!");
		}
		if (scp->licenses == NULL_STR) {
			Assert(NO, "Expected non-null license summary!");
		}
#endif	/* QA_CHECKS */
#ifdef	STOPWATCH
		timerBytes += size;
#endif	/* STOPWATCH */
#ifdef	FLAG_NO_COPYRIGHT
		if (gl.flags & FL_NOCOPYRIGHT) {
			p = listGetItem(&gl.nocpyrtList, scp->relpath);
			p->buf = copyString(scp->linkname, MTAG_PATHBASE);
			p->num = scp->score;
		}
#endif	/* FLAG_NO_COPYRIGHT */
#ifdef	SEARCH_CRYPTO
		if (gl.flags & FL_HASCRYPTO) {
			p = listGetItem(&gl.cryptoList, scp->relpath);
			p->buf = copyString(scp->linkname, MTAG_PATHBASE);
			p->num = scp->score;
		}
#endif	/* SEARCH_CRYPTO */
		fprintf(linkFp, "License(s) Found: %s\n", scp->licenses);
#ifdef	SAVE_UNCLASSIFIED_LICENSES
		if (gl.licPara != NULL_STR) {
			if (gl.flags & FL_FRAGMENT) {
				fprintf(linkFp,
				    "[- Pattern(fragment) text -]\n\"%s\"\n",
				    gl.licPara);
			}
			else {
				fprintf(linkFp,
				    "[- Possible license text/snippet -]\n%s\n",
				    gl.licPara);
			}
			memFree(gl.licPara, MTAG_TEXTPARA);	/* be free! */
			gl.licPara = NULL_STR;			/* remember */
		}
#endif	/* SAVE_UNCLASSIFIED_LICENSES */
		fprintf(linkFp, "[%s]\n%s", LABEL_TEXT, textp);
		munmapFile(textp);
		(void) fclose(linkFp);
/*
 * Remember this license in this file... we add it to 2 lists:
 * (1) for this instance of saving license data
 * (2) for a global (e.g., distro-wide) list of licenses
 */
		p = listGetItem(&lList, scp->licenses);
		p->refCount++;
/*
 * Clear out the buffer-offsets list
 */
#ifdef	SHOW_LOCATION
#ifdef	PHRASE_DEBUG
		listDump(&gl.offList, NO);
#endif	/* PHRASE_DEBUG */
		while (p = listIterate(&gl.offList)) {
			listClear(p->buf, YES);
		}
		listClear(&gl.offList, NO);
#endif	/* SHOW_LOCATION */
	}
#ifdef	MEMSTATS
	memStats("saveLicenseData: out of loop");
#endif	/* MEMSTATS */
	(void) fclose(scoreFp);
	gl.fSave += nCand;
	listSort(&lList, SORT_BY_COUNT_DSC);
#ifdef	QA_CHECKS
	if (lList.used == 0) {
		Assert(NO, "No entries in license-list");
	}
#endif	/* QA_CHECKS */
/*
 * Construct a 'computed license'.  Wherever possible, leave off the
 * entries for None and LikelyNot; those are individual-file results
 * and we're making an 'aggregate summary' here.
 */
	if (gl.parseList.used == 0) {
		noLicenseFound(isPackage);
	}
	else {
		makeLicenseSummary(&gl.parseList, highScore, cur.compLic,
		    sizeof(cur.compLic));
	}
	if (optionIsSet(OPTS_DEBUG)) {
	    printf("==> ");
	}
	printf("%s\n", cur.compLic);
	return;
#ifdef	DEBUG
	printf("DEBUG: cur.compLic == \"%s\"\n", cur.compLic);
#endif	/* DEBUG */
	altFp = fopenFile(FILE_FOUND, "w+");
	fprintf(altFp, "License Claim: %s\nLicense Component(s) Found:\n",
	    isPackage ? cur.claimlic : STR_NOTPKG);
	while ((p = listIterate(&lList)) != NULL_ITEM) {
		fprintf(altFp, "%7d\t%s\n", p->refCount, p->str);
	}
	(void) fclose(altFp);
#ifdef	STOPWATCH
	END_TIMER;
	(void) sprintf(timerName, "parse+save (%d KB, %.2f KB/sec)",
	    timerBytes/1024, (double)timerBytes/1024.0/proctime);
	PRINT_TIMER(timerName, 0);
#endif	/* STOPWATCH */
#ifdef	MEMSTATS
	printf("DEBUG: static lists in saveLicenseData():\n");
	listDump(&lList, -1);
	listDump(&cList, -1);
	listDump(&eList, -1);
	listDump(&gl.parseList, -1);
#endif	/* MEMSTATS */
	listClear(&lList, NO);
	listClear(&cList, NO);
	listClear(&eList, NO);
	listClear(&gl.parseList, NO);
	return;
}


/*
 * pretty-print the keywords into possibly _several_ formatted lines
 * e.g., 'fmt -t'
 */
#define	_PP_LINESIZE	72	/* formatting limit */
static void prettyPrint(FILE *fp, char *s, int indentLen)
{
	static char pbuf[myBUFSIZ*2];
	register char *cp1 = pbuf, *cp2;
	register int len;
/* */
	if ((len = strlen(s)) > sizeof(pbuf)) {
		Warn("buffer contents \"%s\"", s);
		Fatal("Pretty-print data too long (%d)!", len);
	}
	strcpy(pbuf, s);
	if (indentLen > 10) {
		indentLen = 10;
	}
	else if (indentLen < 0) {
		indentLen = 0;
	}
	while (isspace(*cp1)) {		/* skip leading white-space */
		cp1++;
	}
	while (strlen(cp1) > _PP_LINESIZE) {
		cp2 = (char *) (cp1+_PP_LINESIZE-1);
		while (*cp2 != ' ' && cp2 > cp1) {
			cp2--;
		}
		*cp2 = NULL_CHAR;		/* end line */
		fprintf(fp, "%s\n", cp1);	/* dump line */
		*cp2 = ' ';			/* reset it */
		cp1 = cp2+1;			/* intented line */
		if (indentLen) {
			cp1 -= indentLen;
			strncpy(cp1, "          ", indentLen);
		}
	}
	fprintf(fp, "%s\n", cp1);
	return;
}


/*
 * Construct a 'computed license'.  Wherever possible, leave off the
 * entries for None and LikelyNot; those are individual-file results
 * and we're making an 'aggregate summary' here.
 *****
 * parseLicenses() added license components found, as long as they were
 * considered "interesting" to some extent.  Components of significant
 * interest had their iFlag set to 1; those of lower-interest were set to
 * 0.  In this way we can tier license components into 4 distinct levels:
 * 'interesting', 'medium interest', 'nothing significant', and 'Zero'.
 * ==> If the list is EMPTY, there's nothing, period.
 * ==> If listCount() returns non-zero, "interesting" stuff is in it and
 *     we can safely ignore things of 'significantly less interest'.
 * ==> If neither of these is the case, only the licenses of the above
 *     'significantly less interest' category exist (don't ignore them).
 ******
 * We need to be VERY careful in this routine about the length of the
 * license-summary created; they COULD be indefinitely long!  For now,
 * just check to see if we're going to overrun the buffer...
 */
static void makeLicenseSummary(list_t *l, int highScore,
    char *target, int size)
{
	register item_t *p, *ip;
	register int printCount = 0, len = 0, new, goodStuff;
	register char *start, *end, save = NULL_CHAR;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== makeLicenseSummary(%p, %d, %p, %d)\n", l, highScore,
	    target, size);
#endif	/* PROC_TRACE */
/* */
	if (l->used == 0) {		/* zero/nothing */
		(void) strcpy(target, LS_NOSUM);
		return;
	}
/*
 * Now we know there's something in the list of AT LEAST marginal interest
 * in the component-list.  If listCount() is zero, ALL data is 'marginal';
 * else we have 'good stuff'.  For the latter, we only summarize items with
 * a 'val' > 0 (the really intersting stuff).
 *****
 * This is how the attorneys would prefer the summaries to be constructed.
 */
	listSort(l, SORT_BY_COUNT_DSC);	/* sort components */
#if	0
	listDump(l, YES);
#endif
 	size--;		/* maximum strlen, adjust to allow *1* NULL */
	for (goodStuff = 0; (p = listIterate(l)) != NULL_ITEM; /*nada */) {
		if (p->iLevel) {
			goodStuff = 1;		/* interesting license */
			l->ix = -1;		/* reset saved index */
			break;
		}
	}
	while ((p = listIterate(l)) != NULL_ITEM) {
		if (goodStuff && p->iLevel <= IL_LOW) {	/* uninteresting */
			continue;
		}
		if (printCount) {
			target[len++] = ',';
		}
		printCount++;
		new = sprintf(target+len, "%s", p->str);
		if ((len += new) > size) {
			Fatal("Buffer-overwrite, marginal license components");
		}
		new = 0;
	}
	return;
}

#ifdef	LICENSE_DEBUG
dumpLicenses()
{
	int i;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== dumpLicenses()\n");
#endif	/* PROC_TRACE */
/* */
	for (i = 0; i < NFOOTPRINTS; i++) {
		printf("License[%d]: seedlen=%d, regexlen=%d\n", i,
		    licSpec[i].seed.csLen, licSpec[i].text.csLen);
	}
	printf("[NFOOTPRINTS = %d\n", NFOOTPRINTS);
}
#endif	/* LICENSE_DEBUG */



#define	EXCERPT_MAXLEN	128

static void findLines(char *path, char *textp, int tsize, int index,
    list_t *list)
{
	char *para, *cp, *xp, *start, *end, buf[myBUFSIZ];
	item_t *p;
#ifdef	PHRASE_DEBUG
	register int i;
#endif	/* PHRASE_DEBUG */
/* */
#if defined PROC_TRACE || defined PHRASE_DEBUG
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== findLines(%p, %d, %d, %p)\n", textp, tsize, index, list);
#endif	/* PROC_TRACE || PHRASE_DEBUG */
/* */
	if (_SEED(index) == NULL_STR) {
		Assert(NO, "Empty (NULL) key for search-regex %d", index);
	}
	para = getInstances(textp, tsize, 0, 0, _SEED(index), NO);
	if (para == NULL_STR) {
		return;
	}
	cp = para;
/*
 * Search for the line; remember, we might get/find >1 !!
 */
	while (idxGrep(index, cp, REG_ICASE|REG_NEWLINE|REG_EXTENDED)) {
#ifdef	PHRASE_DEBUG
		printf("DEBUG: STR[");
		for (i = gl.regm.rm_so; i < gl.regm.rm_eo; i++) {
			printf("%c", *(cp+i));
		}
		printf("]...\n");
#endif	/* PHRASE_DEBUG */
		cp += gl.regm.rm_so;
		start = findBol(cp, para);
		if ((end = findEol(cp)) == NULL_STR) {
			Fatal("Searching for EOL is all for naught!");
		}
		*buf = *end = NULL_CHAR;
#ifdef	PHRASE_DEBUG
		printf("DEBUG: CONTEXT[");
		for (i = 0; i < EXCERPT_MAXLEN && *(cp+i) != NULL_CHAR; i++) {
			printf("%c", *(cp+i));
		}
		if (*(cp+i) == NULL_CHAR) {
			printf("<NULL!>");
		}
		printf("]...\n");
		printf("DEBUG: start %p end %p (diff %d)\n", start, end,
		    end-start);
#endif	/* PHRASE_DEBUG */
/*
 * If the line we found is 'long', pretty-print an excerpt of the text
 * surrounding our target string; else, record the whole line.
 */
		if (end-start > EXCERPT_MAXLEN) {
#ifdef	QA_CHECKS
			Assert(NO, "findLines: LONG line in %s", path);
#endif	/* QA_CHECKS */
			(void) strcat(buf, "[long-line-excerpt follows]\n");
			if (cp+EXCERPT_MAXLEN <= end) {
				*(cp+EXCERPT_MAXLEN) = NULL_CHAR;
			}
			(void) strcat(buf, cp);
			(void) strcat(buf, "\n[end-of-excerpt]");
#ifdef	PHRASE_DEBUG
			Msg("%d-byte line >> %d (index %d)\n%s\n",
			    end-cp, EXCERPT_MAXLEN, index, buf);
#endif /* PHRASE_DEBUG */
		}
		else {
			(void) strcpy(buf, start);
		}
#ifdef	CACHE_DEBUG
		printf("findLines: enqueue \"%s\"\n", buf);
#endif	/* CACHE_DEBUG */
		*end = '\n';	/* restore newline */
		cp = end;
/*
 * Enqueue this text
 */
		doctorBuffer(buf, 0, 0, YES);
		p = listGetItem(list, buf);
		p->refCount++;
	}
	return;
}

int findParagraph(char *textp, int size, scanres_t *scp,
    char *pathname, int index, char *label, int exists)
{
	char *buf, header[22];
	FILE *refFp;
	int isNew = 0;
/* */
#if defined(PROC_TRACE) || defined(PHRASE_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== findParagraph(%p, %d, %p, %s, %d, %s, %d)\n", textp,
	    size, scp, pathname, index, label, exists);
#endif	/* PROC_TRACE || PHRASE_DEBUG */
/* */
#ifdef	PHRASE_DEBUG
	printf("findParagraph: looking for \"%s\" --> %s\n",
	    _REGEX(index), pathname);
#endif	/* PHRASE_DEBUG */
	buf = getInstances(textp, size, 6, 6, _REGEX(index), YES);
	if (buf == NULL_STR) {
		return(0);
	}
	isNew = !isFILE(pathname);
	refFp = fopen(pathname, "a+");
	if (isNew) {
		fprintf(refFp, "Hotword: %s\n", label);
	}
	(void) snprintf(header, 9+strlen(scp->linkname), "%s", HASHES);
	fprintf(refFp, "%s\n### %s ### %s\n%s\n%s\n", header, scp->linkname,
	    scp->relpath, header, buf);
	(void) fclose(refFp);
	return(1);
}

/*
 * These checks used to be performed on every run, but it makes better
 * sense to run this at compile-time.
 */
static void licenseStringChecks()
{
	register int i, j;
	register char *cp;
	register licText_t *ltp = licText;
	char buf[myBUFSIZ];
	extern regex_t regc[NFOOTPRINTS];
/* */
#if defined(PROC_TRACE) || defined(PHRASE_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== licenseStringChecks()\n");
#endif	/* PROC_TRACE || PHRASE_DEBUG */
/* */
/*
 * Look to see if the key (tseed) is a sub-string of the regex
 */
	for (i = 0; i < NFOOTPRINTS; i++) {
		if (_SEED(i) == NULL_STR) {
			continue;
		}
#if	((DEBUG > 5) || defined LICENSE_DEBUG)
		if (_SEED(i) == _REGEX(i)) {
			Assert(NO, "Lic %d tseed == regex", i);
		}
#endif	/* (DEBUG>5 || LICENSE_DEBUG) */
		if (!strGrep(_SEED(i), _REGEX(i), REG_ICASE) &&
		    !strNbuf(_REGEX(i), _SEED(i))) {
			Assert(NO, "Seed %d [\"%s\"] NOT a regex substring",
			    i, _SEED(i));
		}
	}
/*
 * Check for duplicate regex-strings (duplicate keys are allowed/encouraged)
 */
	for (ltp = licText, i = 0; i < NFOOTPRINTS; i++, ltp++) {
		for (cp = _REGEX(i); *cp; cp++) {
			if (*cp == ':' && cp != _REGEX(i) &&
			    strncmp(cp-2, "[^:]", 4)) {
				Warn("Regex %d: embedded '%c'", i, *cp);
				break;
			}
		}
#if	0
		memcpy(buf, licSpec[i].text.csData, licSpec[i].text.csLen);
		decrypt(buf, licSpec[i].text.csLen);
		printf("%s\n", buf);
		for (cp = licSpec[i].text.csData; *cp; cp++) {
			switch (*cp) {
				case '(': case ')': case ',': case '.':
					Warn("Regex %d: punctuation '%c'",
					    i, *cp);
					printf("%s\n", licSpec[i].text.csData);
					break;
			}
		}
#endif
		for (j = i+1; j < NFOOTPRINTS; j++) {
			if (licSpec[i].text.csLen != licSpec[j].text.csLen) {
				continue;
			}
			if (strcmp(_REGEX(i), _REGEX(j)) == 0) {
			    	Assert(NO, "Regex %d and %d are identical",
				    i, j);
			}
		}
		if ((j = regcomp(&gl.regc, _REGEX(i),
		    REG_EXTENDED|REG_NEWLINE|REG_ICASE)) != 0) {
			Assert(NO, "Regex-compile failed(%d), regex %d", j, i);
			regexError(j, &gl.regc, _REGEX(i));
			printf("\"%s\"\n", _REGEX(i));
		}
		regfree(&gl.regc);
	}
	if (optionIsNotSet(OPTS_DEBUG)) {
		return;
	}
	for (i = _LT_first; i <= _LT_last; i++) {
		for (j = _LT_first; j <= _LT_last; j++) {
			if (i == j) {
				continue;
			}
			if (idxGrep(i, _REGEX(j), REG_EXTENDED)) {
				printf("=+=+=\n");
				Warn("Regex %d matches regex/text #%d", i, j);
				(void) mySystem("egrep \"	%d$\" _autodefs.h", i);
				printf("  REGEX: %s\n", _REGEX(i));
				(void) mySystem("egrep \"	%d$\" _autodefs.h", j);
				printf("MATCHES: %s\n", _REGEX(j));
			}
		}
	}
	return;
}
