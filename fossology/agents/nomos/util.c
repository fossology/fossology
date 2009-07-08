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
#include <stdarg.h>
#include <stdio.h>
#include "nomos.h"
#include "util.h"
#include "list.h"
#include "nomos_regex.h"
/* CDB do we need this?
#include "_autodefs.h"
*/

#define	MM_CACHESIZE	20

#ifdef	REUSE_STATIC_MEMORY
static char grepzone[10485760];	/* 10M for now, adjust if needed */
#endif	/* REUSE_STATIC_MEMORY */

/*
  File local variables
*/
static va_list ap;
static char utilbuf[myBUFSIZ];
static struct mm_cache mmap_data[MM_CACHESIZE];
static char cmdBuf[512];


#ifdef	MEMORY_TRACING
#define	MEMCACHESIZ	200000
static int memlast = -1;
static struct mm_cache memcache[MEMCACHESIZ];
void memCacheDump();
#endif	/* MEMORY_TRACING */


int isDIR(char *dpath)
{
#ifdef	PROC_TRACE
	traceFunc("== isDIR(%s)\n", dpath);
#endif	/* PROC_TRACE */

    return(isINODE(dpath, S_IFDIR));
}


void unbufferFile(FILE *fp)
{
    (void) setbuf(fp, NULL);
    return;
}


int isEMPTYFILE(char *fpath)
{
#ifdef	PROC_TRACE
	traceFunc("== isEMPTYFILE(%s)\n", fpath);
#endif	/* PROC_TRACE */

    if (!isFILE(fpath)) {
	return(0);
    }
    return(cur.stbuf.st_size == 0);
}


int isBLOCK(char *bpath)
{
#ifdef	PROC_TRACE
	traceFunc("== isBLOCK(%s)\n", bpath);
#endif	/* PROC_TRACE */

    return(isINODE(bpath, S_IFBLK));
}


int isCHAR(char *cpath)
{
#ifdef	PROC_TRACE
	traceFunc("== isCHAR(%s)\n", cpath);
#endif	/* PROC_TRACE */

    return(isINODE(cpath, S_IFCHR));
}


int isPIPE(char *ppath)
{
#ifdef	PROC_TRACE
	traceFunc("== isPIPE(%s)\n", ppath);
#endif	/* PROC_TRACE */

    return(isINODE(ppath, S_IFIFO));
}


int isSYMLINK(char *spath)
{
#ifdef	PROC_TRACE
	traceFunc("== isSYMLINK(%s)\n", spath);
#endif	/* PROC_TRACE */

    return(isINODE(spath, S_IFLNK));
}


int isINODE(char *ipath, int typ)
{
    int ret;

#ifdef	PROC_TRACE
	traceFunc("== isINODE(%s, 0x%x)\n", ipath, typ);
#endif	/* PROC_TRACE */

    if ((ret = stat(ipath, &cur.stbuf)) < 0) {
	/*
	  IF we're trying to stat() a file that doesn't exist, 
	  that's no biggie.
	  Any other error, however, is fatal.
	*/
	if (errno == ENOENT) {
	    return 0;
	}
	perror(ipath);
	mySystem("ls -l '%s'", ipath);
	Bail(1);
    }
    if (typ == 0) {
	return(1);
    }
    return((int)(cur.stbuf.st_mode & S_IFMT & typ));
}


char *newReloTarget(char *basename)
{
    static char newpath[myBUFSIZ];
    int i;

#ifdef	PROC_TRACE
	traceFunc("== newReloTarget(%s)\n", basename);
#endif	/* PROC_TRACE */

    for (i = 0; i < MAX_RENAME; i++) {
	(void) sprintf(newpath, "%s_%s-renamed.%03d", basename, gl.progName, i);
	if (access(newpath, F_OK) && errno == ENOENT) {
	    break;
	}
    }
    if (i == MAX_RENAME) {
	Fatal("%s: no suitable relocation target (%d tries)", basename, i);
    }
    return(newpath);
}



#ifdef	MEMORY_TRACING
/*
 * memAlloc is a front-end to calloc() that dies on allocation-failure
 * thus, we don't have to always check the return value from calloc()
 * in the guts of the application code; we die here if alloc fails.
 */
char *memAllocTagged(int size, char *name)
{
    void *ptr;
    /*
     * we don't track memory allocated; we front-end for errors and return
     * the pointer we were given.
     */

#if	defined(PROC_TRACE) || defined(MEM_ACCT)
	traceFunc("== memAllocTagged(%d, \"%s\")\n", size, name);
#endif	/* PROC_TRACE || MEM_ACCT */

    if (size < 1) {
	Fatal("Bombs away, cannot alloc %d bytes!", size);
    }
    if (++memlast == MEMCACHESIZ) {
	Fatal("*** memAllocTagged: out of memcache entries");
    }
#ifdef	USE_CALLOC
    if ((ptr = calloc((size_t) 1, (size_t) size)) == (void *) NULL) {
	perror("calloc");
	Fatal("Calloc error for %s", name);
    }
#else	/* not USE_CALLOC */
    if ((ptr = malloc((size_t) size)) == (void *) NULL) {
	perror("malloc");
	Fatal("Malloc error for %s", name);
    }
    (void) memset(ptr, 0, (size_t) size);
#endif	/* not USE_CALLOC */
#if	DEBUG > 3 || defined(MEM_ACCT)
    printf("+%p:%p=(%d)\n", ptr, ptr+size-1, size);
#endif	/* DEBUG > 3 || MEM_ACCT */
    memcache[memlast].mmPtr = ptr;
    memcache[memlast].size = size;
    (void) strcpy(memcache[memlast].label, name);
#ifdef	MEM_ACCT
    printf("memAllocTagged(%d, \"%s\") == %p [entry %04d]\n", size, name, ptr,
	   memlast);
    /* memCacheDump("post-memAllocTagged:"); */
#endif	/* MEM_ACCT */
    return(ptr);
}


void memFreeTagged(void *ptr, char *note)
{
    struct mm_cache *mmp;
    int i;

#if	defined(PROC_TRACE) || defined(MEM_ACCT)
	traceFunc("== memFree(%p, \"%s\")\n", ptr, note);
#endif	/* PROC_TRACE || MEM_ACCT */

#ifdef	MEMORY_TRACING
    printf("DEBUG: mprobe(%p)\n", ptr);
    mprobe(ptr);	/* see if glibc still likes this memory */
#endif	/* MEMORY_TRACING */
    for (mmp = memcache, i = 0; i <= memlast; mmp++, i++) {
	if (mmp->mmPtr == ptr) {
#ifdef	MEM_ACCT
	    printf("memFree(%p, \"%s\") is entry %04d (%d bytes)\n", ptr, note, i,
		   mmp->size);
#endif	/* MEM_ACCT */
	    break;
	}
    }
    if (i > memlast) {
	Fatal("Could not locate %p to free!", ptr);
    }
    free(ptr);
#if	DEBUG > 3 || defined(MEM_ACCT)
    printf("-%p=(%d)\n", ptr, mmp->size);
#endif	/* DEBUG > 3 || MEM_ACCT */
    if (i != memlast) {
	(void) memmove(&memcache[i], &memcache[i+1],
		       (memlast-i)*sizeof(struct mm_cache));
    }
    memset(&memcache[memlast], 0, sizeof(struct mm_cache));
    memlast--;
#ifdef	MEM_ACCT
    memCacheDump("post-memFree:");
#endif	/* MEM_ACCT */
    return;
}


void memCacheDump(char *s)
{
    struct mm_cache *m;
    static int first = 1;
    int i, start;
    /* */
    if (s != NULL_STR) {
	printf("%s\n", s);
    }
    if (memlast < 0) {
	printf("%%%%%% mem-cache is EMPTY\n");
	return;
    }
    start = (memlast > 50 ? memlast-50 : 0);
    printf("%%%%%% mem-cache @ %p [last=%d]\n", memcache, memlast);
    for (m = memcache+start, i = start; i <= memlast; m++, i++) {
	printf("mem-entry %04d: %p (%d) - %s\n", i, m->mmPtr,
	       m->size, m->label);
	if (!first) {
	    printf("... \"%s\"\n", m->mmPtr);
	}
    }
    printf("%%%%%% mem-cache END\n");
    if (first) {
	first --;
    }
    return;
}
#endif	/* MEMORY_TRACING */


char *findBol(char *s, char *upperLimit)
{
    char *cp;

#ifdef	PROC_TRACE
	traceFunc("== findBol(%p, %p)\n", s, upperLimit);
#endif	/* PROC_TRACE */

    if (s == NULL_STR || upperLimit == NULL_STR) {
	return(NULL_STR);
    }
    for (cp = s; cp > upperLimit; cp--) {
#ifdef	DEBUG
	printf("DEBUG: cp %p upperLimit %p\n", cp, upperLimit);
#endif	/* DEBUG */
	if (isEOL(*cp)) {
#ifdef	DEBUG
	    printf("DEBUG: Got it!  BOL == %p\n", cp);
#endif	/* DEBUG */
	    return((char*)(cp+1));
	}
    }
    if (cp == upperLimit) {
#ifdef	DEBUG
	printf("DEBUG: AT upperLimit %p\n", upperLimit);
#endif	/* DEBUG */
	return(upperLimit);
    }
    return(NULL_STR);
}


char *findEol(char *s)
{
    char *cp;
    
#ifdef	PROC_TRACE
	traceFunc("== findEol(%p)\n", s);
#endif	/* PROC_TRACE */
    
    if (s == NULL_STR) {
	return(NULL_STR);
    }
    for (cp = s; *cp != NULL_CHAR; cp++) {
	if (isEOL(*cp)) {
	    return(cp);	/* return ptr to EOL or NULL */
	}
    }
    if (*cp == NULL_CHAR) {
	return(cp);
    }
    return(NULL_STR);
}


void changeDir(char *pathname)
{
    /*
     * we die here if the chdir() fails.
     */

#if	defined(PROC_TRACE) || defined(UNPACK_DEBUG) || defined(CHDIR_DEBUG)
	traceFunc("== changeDir(%s)\n", pathname);
#endif	/* PROC_TRACE || UNPACK_DEBUG || CHDIR_DEBUG */

    if (chdir(pathname) < 0) {
	perror(pathname);
	Fatal("chdir(\"%s\") fails", pathname);
    }
    if (getcwd(cur.cwd, sizeof(cur.cwd)) == NULL_STR) {
	perror("getcwd(changeDir)");
	Bail(1);
    }
    cur.cwdLen = (int) strlen(cur.cwd);
#ifdef	CHDIR_DEBUG
    printf("DEBUG: now in \"%s\"\n", cur.cwd);
#endif	/* CHDIR_DEBUG */
    return;
}

void renameInode(char *oldpath, char *newpath)
{
    int err = 0;
    /*
     * we die here if the unlink() fails.
     */

#if defined(PROC_TRACE) || defined(UNPACK_DEBUG)
	traceFunc("== renameInode(%s, %s)\n", oldpath, newpath);
#endif	/* PROC_TRACE || UNPACK_DEBUG */

#ifdef	DEBUG
    (void) mySystem("ls -ldi '%s'", oldpath);
#endif	/* DEBUG */
    if (rename(oldpath, newpath) < 0) {
	if (errno == EXDEV) {
	    err = mySystem("mv '%s' %s", oldpath, newpath);
	}
	else {
	    err = 1;
	}
	if (err) {
	    perror(oldpath);
	    Fatal("rename(%s, %s) fails", oldpath, newpath);
	}
    }
#ifdef	DEBUG
    (void) mySystem("ls -ldi %s", newpath);
#endif	/* DEBUG */
    return;
}


void unlinkFile(char *pathname)
{
    /*
     * we die here if the unlink() fails.
     */

#if defined(PROC_TRACE) || defined(UNPACK_DEBUG)
	traceFunc("== unlinkFile(%s)\n", pathname);
#endif	/* PROC_TRACE || UNPACK_DEBUG */

    if (unlink(pathname) < 0) {
	(void) chmod(pathname, 0777);
	if (unlink(pathname) < 0) {
	    perror(pathname);
	    (void) mySystem("ls -ldi '%s'", pathname);
	    Fatal("unlink(\"%s\") fails", pathname);
	}
    }
    return;
}


void chmodInode(char *pathname, int mode)
{
/*
 * we die here if the chmod() fails.
 */

#if defined(PROC_TRACE) || defined(UNPACK_DEBUG)
	traceFunc("== chmodInode(%s, 0%o)\n", pathname, mode);
#endif	/* PROC_TRACE || UNPACK_DEBUG */

    if (chmod(pathname, mode) < 0) {
	perror(pathname);
	Fatal("chmod(\"%s\", 0%o) fails", pathname, mode);
    }
    return;
}


FILE *fopenFile(char *pathname, char *mode)
{
    FILE *fp;
/*
 * we don't track directories opened; we front-end and return what's
 * given to us.  we die here if the fopen() fails.
 */

#ifdef	PROC_TRACE
	traceFunc("== fopenFile(%s, \"%s\")\n", pathname, mode);
#endif	/* PROC_TRACE */

    if ((fp = fopen(pathname, mode)) == (FILE *) NULL) {
	perror(pathname);
	Fatal("fopen(%s) fails", pathname);
    }
    return(fp);
}


FILE *popenProc(char *command, char *mode)
{
    FILE *pp;
/*
 * we don't track directories opened; we front-end and return what's
 * given to us.  we die here if the popen() fails.
 */

#ifdef	PROC_TRACE
	traceFunc("== popenProc(\"%s\", %s)\n", command, mode);
#endif	/* PROC_TRACE */

    if ((pp = popen(command, mode)) == (FILE *) NULL) {
	perror("popen");
#ifdef	MEMORY_TRACING
	memCacheDump("Post-popen-failure:");
#endif	/* MEMORY_TRACING */
	Fatal("popen(\"%s\") fails", command);
    }
    return(pp);
}


/*
 * VERY simple words-and-lines count, does NOT have to be perfect!
 */
char *wordCount(char *textp)
{
    static char wcbuf[64];
    int words;
    int lines;
    int inword;
    char *cp;

#ifdef	PROC_TRACE
	traceFunc("== wordCount(%p)\n", textp);
#endif	/* PROC_TRACE */

    words = 0;
    lines = 0;
    inword = 0;
    for (cp = textp; *cp; cp++) {
	switch (*cp) {
	case '\f':
	    break;
	case '\n':
	case '\r':
	case '\v':
	    lines++;
	    if (inword) {
		words++;
		inword = 0;
	    }
	    break;
	case ' ':
	case '\t':
	    if (inword) {
		words++;
		inword = 0;
	    }
	    break;
	default:
	    inword++;
	    break;
	}
    }
    (void) sprintf(wcbuf, "%d lines, %d words", lines, words);
    /*
     * Save these values for use elsewhere, too.
     */
    cur.nWords = words;
    cur.nLines = lines;
    return(wcbuf);
}


char *copyString(char *s, char *label)
{
    char *cp;
    int len;
    
#ifdef	PROC_TRACE
	traceFunc("== copyString(%p, \"%s\")\n", s, label);
#endif	/* PROC_TRACE */
    
    cp = memAlloc(len=(strlen(s)+1), label);
#ifdef	DEBUG
    printf("+CS: %d @ %p\n", len, cp);
#endif	/* DEBUG */
    (void) strcpy(cp, s);
    return(cp);
}


char *pathBasename(char *path)
{
    char *cp;

#ifdef	PROC_TRACE
	traceFunc("== pathBasename(\"%s\")\n", path);
#endif	/* PROC_TRACE */

    cp = strrchr(path, '/');
    return(cp == NULL_STR ? path : (char *)(cp+1));
}


char *getInstances(char *textp, int size, int nBefore, int nAfter, char *regex,
		   int recordOffsets)
{
    int i;
    int notDone; 
    int buflen = 1;
    static char *ibuf = NULL;
    static int bufmax = 0;
    char *sep = _REGEX(_UTIL_XYZZY);
    item_t *p;
    item_t *bp;
    char *fileeof;
    char *start;
    char *end;
    char *curptr;
    char *bufmark;
    char save;
    char *cp;
    int newDataLen;
    int regexFlags = REG_ICASE|REG_EXTENDED;

#if defined(PROC_TRACE) || defined(PHRASE_DEBUG) || defined(DOCTOR_DEBUG)
	traceFunc("== getInstances(%p, %d, %d, %d, \"%s\", %d)\n", textp, size,
	       nBefore, nAfter, regex, recordOffsets);
#endif	/* PROC_TRACE || PHRASE_DEBUG || DOCTOR_DEBUG */

    if ((notDone = strGrep(regex, textp, regexFlags)) == 0) {
#ifdef	PHRASE_DEBUG
	printf("... no match: 1st strGrep()\n");
#endif	/* PHRASE_DEBUG */
	return(NULL_STR);
    }
    /*
     * The global 'offsets list' is indexed by the seed/key (a regex) that we
     * use for doctoring buffers... each entry will contain a list (containing
     * the "paragraphs" that match the key) AND its size (e.g., # of 'chunks'),
     * which also means, if there are N chunks, there are N-1 'xyzzy' separators.
     */
    p = listGetItem(&cur.offList, regex);
    p->seqNo = cur.offList.used;
    p->nMatch = 0;
    if (recordOffsets) {
	p->bList = (list_t *)memAlloc(sizeof(list_t), MTAG_LIST);
	(void) sprintf(utilbuf, "\"%c%c%c%c%c%c%c%c%c%c\" match-list",
		       *regex, *(regex+1), *(regex+2), *(regex+3), *(regex+4),
		       *(regex+5), *(regex+6), *(regex+7), *(regex+8), *(regex+9));
#ifdef	PHRASE_DEBUG
	printf("Creating %s\n", utilbuf);
#endif	/* PHRASE_DEBUG */
	listInit(p->bList, 0, utilbuf);
#ifdef	QA_CHECKS
	p->val3++;	/* sanity-check -- should never be >1 ! */
	if (p->val3 > 1) {
	    Fatal("Called getInstances(%s) more than once",
		  regex);
	}
#endif	/* QA_CHECKS */
    }
#ifdef	REUSE_STATIC_MEMORY
    if (ibuf == NULL_STR) {		/* first time, uninitialized */
	ibuf = grepzone;
	bufmax = sizeof(grepzone);
    }
    else if (ibuf != grepzone) {
	memFree(ibuf, MTAG_DOUBLED);	/* free the memory... */
	ibuf = grepzone;			/* ... and reset */
	bufmax = sizeof(grepzone);
    }
#else	/* not REUSE_STATIC_MEMORY */
    if (ibuf == NULL_STR) {
	ibuf = memAlloc((bufmax = 1024*1024), MTAG_SEARCHBUF);
    }
#endif	/* not REUSE_STATIC_MEMORY */
    *ibuf = NULL_CHAR;
    bufmark = ibuf;
    end = NULL_STR;
    /*
     * At this point, we know the string we're looking for is IN the file.
     */
#ifdef	PHRASE_DEBUG
    printf("getInstances: \"%s\" [#1] in buf [%d-%d]\n", regex,
	   cur.regm.rm_so, cur.regm.rm_eo-1);
    printf("Really in the buffer: [");
    for (cp = textp + cur.regm.rm_so; cp < (textp + cur.regm.rm_eo); cp++) {
	printf("%c", *cp);
    }
    printf("]\n");
#endif	/* PHRASE_DEBUG */
    /*
     * Find the start of the text line containing the "first" match.
     * locate start of "$nBefore lines above pattern match"; go up to the
     * text on the _previous_ line before we 'really start counting'
     */
    curptr = textp;
    fileeof = (char *) (textp+size);
    while (notDone) {	/* curptr is the 'current block' ptr */
	p->nMatch++;
#ifdef	PHRASE_DEBUG
	printf("... found Match #%d\n", p->nMatch);
#endif	/* PHRASE_DEBUG */
	if (recordOffsets) {
	    (void) sprintf(utilbuf, "buf%05d", p->nMatch);
	    bp = listGetItem(p->bList, utilbuf);
	}
	start = findBol(curptr + cur.regm.rm_so, textp);
	/*
	 * Go to the beggining of the current line and, if nBefore > 0, go 'up'
	 * in the text "$nBefore" lines.  Count 2-consecutive EOL-chars as one
	 * line since some text files use <CR><LF> as line-terminators.
	 */
	if ((nBefore > 0) && (start > textp)) {
	    for (i = 0; (i < nBefore) && (start > textp); i++) {
		start -= 2;
		if ((start > textp) && isEOL(*start)) {
		    start--;
		}
		if (start > textp) {
		    start = findBol(start, textp);
		}
#ifdef	PHRASE_DEBUG
		printf("DEBUG: start = %p\n", start);
#endif	/* PHRASE_DEBUG */
	    }
	}
	if (recordOffsets) {
	    bp->bStart = start-textp;
	}
	/*
	 * Now do what "grep -A $nAfter _filename+" does.
	 *****
	 * If nAfter == 0, we want the end of the current line.
	 *****
	 * If nAfter > 0, locate the end of the line of LAST occurrence of the
	 * string within the next $nAfter lines.  Not well-worded, you say?
	 *****
	 * E.g., if we're saving SIX lines below and we see our pattern 4 lines
	 * below the first match then we'll save 10 lines from the first match.
	 * And to continue this example, if we then see our pattern 9 lines from
	 * the start of the buffer (since we're looking up to 10 lines now), we
	 * will save *15* lines.  Repeat until the last 6 lines we save DO NOT
	 * have our pattern.
	 */
	do {
	    curptr += cur.regm.rm_eo;
	    end = findEol(curptr);
	    if (end < fileeof) {
		end++;	/* first char past end-of-line */
	    }
	    if (nAfter > 0) {
		for (i = 0; end < fileeof; end++) {
		    if (isEOL(*end)) {	/* double-EOL */
			end++;		/* <CR><LF>? */
		    }
		    end = findEol(end);
		    if (end == NULL_STR) {
			Fatal("lost the end-of-line");
		    }
		    if (*end == NULL_CHAR) {
			break;	/* EOF == done */
		    }
		    if (++i == nAfter) {
			break;
		    }
		}
		if ((end < fileeof) && *end) {
		    end++;	/* past newline-char */
		}
	    }
#ifdef	PHRASE_DEBUG
	    printf("Snippet, with %d lines below:\n----\n", nAfter);
	    for (cp = start; cp < end; cp++) {
		printf("%c", *cp);
	    }
	    printf("====\n");
#endif	/* PHRASE_DEBUG */
	    notDone = strGrep(regex, curptr, regexFlags);
	    if (notDone) {	/* another match? */
#ifdef	PHRASE_DEBUG
		printf("... next match @ %d:%d (end=%d)\n",
		       curptr - textp + cur.regm.rm_so,
		       curptr - textp + cur.regm.rm_eo - 1, end - textp);
#endif	/* PHRASE_DEBUG */
#ifdef	QA_CHECKS
		if ((curptr + cur.regm.rm_eo) > fileeof) {
		    Assert(YES, "Too far into file!");
		}
#endif	/* QA_CHECKS */
		/* next match OUTSIDE the text we've already saved? */
		if ((curptr + cur.regm.rm_eo) > end) {
		    break;
		}
		/* else, next match IS within the text we're looking at! */
	    }
	} while (notDone);
	/*
	 * Add this block of text to our buffer.  If 'notdone' is true, there's
	 * at least one more block of text that goes in the buffer, so add the
	 * block-o-text-separator, too.  And, make sure we don't overflow our
	 * buffer (BEFORE we modify it); we don't KNOW how much text to expect!
	 */
	save = *end;
	*end = NULL_CHAR;	/* char PAST the newline! */
	if (recordOffsets) {
	    bp->bLen = end-start;
	    bp->buf = copyString(start, MTAG_TEXTPARA);
	    bp->bDocLen = 0;
#ifdef	PHRASE_DEBUG
	    printf("%s starts @%d, len %d ends [%c%c%c%c%c%c%c]\n",
		   utilbuf, bp->bStart, bp->bLen, *(end-8), *(end-7),
		   *(end-6), *(end-5), *(end-4), *(end-3), *(end-2));
#endif	/* PHRASE_DEBUG */
	}
	newDataLen = end-start+(notDone ? strlen(sep)+1 : 0);
	while (buflen+newDataLen > bufmax) {
	    char *new;
#ifdef	QA_CHECKS
	    Assert(NO, "data(%d) > bufmax(%d)", buflen+newDataLen,
		   bufmax);
#endif	/* QA_CHECKS */
	    bufmax *= 2;
#ifdef	MEMSTATS
	    printf("... DOUBLE search-pattern buffer (%d -> %d)\n",
		   bufmax/2, bufmax);
#if	0
	    memStats("before buffer-double");
#endif
#endif	/* MEMSTATS */
	    new = memAlloc(bufmax, MTAG_DOUBLED);
	    (void) memcpy(new, ibuf, buflen);
#if	0
	    printf("REPLACING buf %p(%d) with %p(%d)\n", ibuf, 
		   bufmax/2, new, bufmax);
#endif
#ifdef	REUSE_STATIC_MEMORY
	    if (ibuf != grepzone) {
		memFree(ibuf, MTAG_TOOSMALL);
	    }
#else	/* not REUSE_STATIC_MEMORY */
	    memFree(ibuf, MTAG_TOOSMALL);
#endif	/* not REUSE_STATIC_MEMORY */
	    ibuf = new;
#if	0
#ifdef	MEMSTATS
	    memStats("after buffer-double");
#endif	/* MEMSTATS */
#endif
	}
	cp = bufmark = ibuf+buflen-1;	/* where the NULL is _now_ */
	buflen += newDataLen;		/* new end-of-data ptr */
	bufmark += sprintf(bufmark, "%s", start);
	if (notDone) {
	    bufmark += sprintf(bufmark, "%s\n", sep);
	}
	/*
	 * Some files use ^M as a line-terminator, so we need to convert those
	 * control-M's to 'regular newlines' in case we need to use the regex
	 * stuff on this buffer; the regex library apparently doesn't have a
	 * flag for interpretting ^M as end-of-line character.
	 */
	while (*cp) {
	    if (*cp == '\r') {		/* '\015'? */
		*cp = '\n';		/* '\012'! */
	    }
	    cp++;
	}
	*end = save;
#ifdef	PHRASE_DEBUG
	printf("Loop end, BUF IS NOW: [\"%s\":%d]\n----\n%s====\n",
	       regex, strlen(ibuf), ibuf);
#endif	/* PHRASE_DEBUG */
    }

#if defined(PHRASE_DEBUG) || defined(DOCTOR_DEBUG)
    printf("getInstances(\"%s\"): Found %d bytes of data...\n", regex,
	   buflen-1);
#endif	/* PHRASE_DEBUG || DOCTOR_DEBUG */
#ifdef	PHRASE_DEBUG
    printf("getInstances(\"%s\"): buffer %p --------\n%s\n========\n",
	   regex, ibuf, ibuf);
#endif	/* PHRASE_DEBUG */
    return(ibuf);
}


char *curDate()
{
    static char datebuf[32];
    char *cp;
    time_t thyme;

    (void) time(&thyme);
    (void) ctime_r(&thyme, datebuf);
    if ((cp = strrchr(datebuf, '\n')) == NULL_STR) {
	Fatal("Unexpected time format from ctime_r()!");
    }
    *cp = NULL_CHAR;
    return(datebuf);
}


#ifdef	MEMSTATS
void memStats(char *s)
{
    static int first = 1;
    static char mbuf[128];

    if (first) {
	first = 0;
	sprintf(mbuf, "grep VmRSS /proc/%d/status", getpid());
    }
    if (s && *s) {
	int i;
	printf("%s: ", s);
	for (i = (int) (strlen(s)+2); i < 50; i++) {
	    printf(" ");
	}
    }
    (void) mySystem(mbuf);
#if	0
    system("grep Vm /proc/self/status");
    system("grep Brk /proc/self/status");
#endif
}
#endif	/* MEMSTATS */


void makeSymlink(char *path)
{
#if defined(PROC_TRACE) || defined(UNPACK_DEBUG)
	traceFunc("== makeSymlink(%s)\n", path);
#endif	/* PROC_TRACE || UNPACK_DEBUG */

    (void) sprintf(cmdBuf, ".%s", strrchr(path, '/'));
    if (symlink(path, cmdBuf) < 0) {
	perror(cmdBuf);
	Fatal("Failed: symlink(%s, %s)", path, cmdBuf);
    }
    return;
}


#ifdef notdef
int fileTypeIs(char *pathname, int index, char *magicData)
{
    if (idxGrep(index, magicData, REG_ICASE|REG_EXTENDED)) {
	return(1);
    }
    return(0);
}


int fileIsShar(char *textp, char *magicData)
{

#if defined(PROC_TRACE) || defined(UNPACK_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== fileIsShar(%p, \"%s\")\n", textp, magicData);
#endif	/* PROC_TRACE || UNPACK_DEBUG */

#ifdef	DEBUG
    if (!idxGrep(_UTIL_SHARTYPE, magicData, REG_ICASE|REG_EXTENDED)) {
	printf("DEBUG: NOT _UTIL_SHARTYPE\n");
	return(0);
    }
    if (!idxGrep(_UTIL_SHAR, textp, REG_ICASE|REG_NEWLINE)) {
	printf("DEBUG: NOT _UTIL_SHAR\n");
	return(0);
    }
    printf("DEBUG: Hey, a shar file!\n");
    return(1);
#else	/* not DEBUG */
    if (idxGrep(_UTIL_SHARTYPE, magicData, REG_ICASE|REG_EXTENDED) &&
	idxGrep(_UTIL_SHAR, textp, REG_ICASE|REG_NEWLINE)) {
	return(1);
    }
    return(0);
#endif	/* not DEBUG */
}
#endif /* notdef */


/*
   CDB -- Need to review this code, particularly for the use of an
   external file (Nomos.strings.txt). Despite the fact that variable 
   is named debugStr, the file appears to be used for more than just
   debugging.

   Although it might be the case that it only gets called from debug
   code. It does not appear to be called during a few test runs of
   normal file scans that I tried.
*/
void printRegexMatch(int n, int cached)
{
    int save_so;
    int save_eo;
    int match;
    static char debugStr[256];
    static char misc[64];
    char *cp;
    char *x = NULL; 
    char *textp;

#ifdef	PROC_TRACE
    traceFunc("== printRegexMatch(%d, %d)\n", n, cached);
#endif	/* PROC_TRACE */

    if (*debugStr == NULL_CHAR) {
	(void) sprintf(debugStr, "%s/Nomos.strings.txt", gl.initwd);
#ifdef	DEBUG
	printf("File: %s\n", debugStr);
#endif	/* DEBUG */
    }
    save_so = cur.regm.rm_so;
    save_eo = cur.regm.rm_eo;
    if (isFILE(debugStr)) {
	if ((match = (gl.flags & FL_SAVEBASE))) { /* assignment is deliberate */
	    gl.flags &= ~FL_SAVEBASE;
	}
#ifdef	DEBUG
	printf("Match [%d:%d]\n", save_so, save_eo);
#endif	/* DEBUG */
	textp = mmapFile(debugStr);
	(void) sprintf(misc, "=#%03d", n);
	if (strGrep(misc, textp, REG_EXTENDED)) {
#ifdef	DEBUG
	    printf("Patt: %s\nMatch: %d:%d\n", misc,
		   cur.regm.rm_so, cur.regm.rm_eo);
#endif	/* DEBUG */
	    x = textp + cur.regm.rm_so;
	    cp = textp + cur.regm.rm_so;
	    *x = NULL_CHAR;
	    while (*--x != '[') {
		if (x == textp) {
		    Fatal("Cannot locate debug symbol");
		}
	    }
	    ++x; /* CDB - Moved from line below. Hope this is what was intended.*/
	    (void) strncpy(misc, x, cp - x); /* CDB - Fix */
	    misc[cp-x] = NULL_CHAR;
	} else {
	    (void) strcpy(misc, "?");
	}
	munmapFile(textp);
	if (match) {
	    gl.flags |= FL_SAVEBASE;
	}
#ifdef	DEBUG
	printf("RESTR [%d:%d]\n", cur.regm.rm_so, cur.regm.rm_eo);
#endif	/* DEBUG */
    }
    cur.regm.rm_so = save_so;
    cur.regm.rm_eo = save_eo;
    printf("%s regex %d ", cached ? "Cached" : "Found", n);
    if (x) {
	printf("(%s) ", misc);
    }
    if (!cached) {
	printf("\"%s\"", _REGEX(n));
    }
    printf("\n");
#ifdef	DEBUG
    printf("Seed: \"%s\"\n", _SEED(n));
#endif	/* DEBUG */
    return;
}


/*
 * Blarg.  Files that are EXACTLY a multiple of the system pagesize do
 * not get a NULL on the end of the buffer.  We need something
 * creative, else we'll need to just calloc() the size of the file (plus
 * one) and read() the whole thing into memory.
 */
char *mmapFile(char *pathname)	/* read-only for now */
{
    struct mm_cache *mmp;
    static int first = 1;
    int i;
    int n;
    int rem;
    char *cp;

#ifdef	PROC_TRACE
	traceFunc("== mmapFile(%s)\n", pathname);
#endif	/* PROC_TRACE */

    /* 
     * Static storage is _supposed_ to be initialized @ 0.  Do we need this?
     */
    if (first) {
#if	(DEBUG > 3)
	printf("MMapFile: first call\n");
#endif	/* DEBUG > 3 */
	for (mmp = mmap_data, i = 0; i < MM_CACHESIZE; i++) {
	    if (mmp->inUse) {
		printf("Uninitialized entry %d\n", i);
		mmp->inUse = 0;
	    }
	}
	first = 0;
    }
    /* CDB - Changed this. Could possibly have broken it. */
    i = 0;
    for (mmp = mmap_data; i < MM_CACHESIZE; mmp++) {
	if (mmp->inUse == 0) {
	    break;
	}
	i++;
    }
    if (i == MM_CACHESIZE) {
	fprintf(stderr, "mmap-cache too small [%d]!\n", MM_CACHESIZE);
	mmapOpenListing();
	Bail(1);
    }

    if ((mmp->fd = open(pathname, O_RDONLY)) < 0) {
	if (errno == ENOENT) {
	    mmp->inUse = 0;		/* overkill? */
	    mmp->size = -1;		/* overkill? */
	    mmp->mmPtr = (void *) NULL;
#if	(DEBUG > 3)
	    printf("mmapFile: ENOENT %s\n", pathname);
#endif	/* DEBUG > 3 */
	    return(NULL_STR);
	}
	perror(pathname);
	(void) mySystem("ls -l %s", pathname);
	Fatal("%s: open failure!", pathname);
    }
    if (fstat(mmp->fd, &cur.stbuf) < 0) {
	fprintf(stderr, "fstat failure!\n");
	perror(pathname);
	Bail(1);
    }
    if (S_ISDIR(cur.stbuf.st_mode)) {
	fprintf(stderr, "mmapFile(%s): is a directory\n", pathname);
	Bail(1);
    }
    (void) strcpy(mmp->label, pathname);
    if (cur.stbuf.st_size) {
	mmp->size = cur.stbuf.st_size + 1;
	mmp->mmPtr = memAlloc(mmp->size, MTAG_MMAPFILE);
#ifdef	DEBUG
	printf("+MM: %d @ %p\n", mmp->size, mmp->mmPtr);
#endif	/* DEBUG */
	rem = mmp->size-1;
	cp = mmp->mmPtr;
	while (rem > 0) {
	    if ((n = (int) read(mmp->fd, cp, (size_t) rem)) < 0) {
		perror("read");
		Bail(1);
	    }
	    rem -= n;
	    cp += n;
	}
	mmp->inUse = 1;
	return((char *) mmp->mmPtr);
    }
    /*
     * If we're here, we hit some sort of error.
     */
    (void) close(mmp->fd);
#ifdef	QA_CHECKS
    Assert(NO, "mmapFile: returning NULL");
#endif	/* QA_CHECKS */
    return(NULL_STR);
}


void mmapOpenListing()
{
    struct mm_cache *mmp;
    int i;

    printf("=== mm-cache BEGIN ===\n");
    for (mmp = mmap_data, i = 0; i < MM_CACHESIZE; i++, mmp++) {
	if (mmp->inUse) {
	    printf("mm[%d]: (%d) %s:%d\n", i, mmp->fd,
		   mmp->label, (int) mmp->size);
	}
    }
    printf("--- mm-cache END ---\n");
    return;
}

/*
 * WARNING: do NOT use a string/buffer AFTER calling munmapFile()!!!
 *****
 * We don't explicitly zero out the memory, but apparently glibc DOES.
 */
void munmapFile(void *ptr)
{
    struct mm_cache *mmp;
    int i;
    
#ifdef	PROC_TRACE
	traceFunc("== munmapFile(%p)\n", ptr);
#endif	/* PROC_TRACE */

    if (ptr == (void *) NULL) {
#ifdef	QA_CHECKS
	Assert(NO, "NULL sent to munmapFile()!");
#endif	/* QA_CHECKS */
	return;
    }
    for (mmp = mmap_data, i = 0; i < MM_CACHESIZE; i++, mmp++) {
	if (mmp->inUse == 0) {
	    continue;
	}
	if (mmp->mmPtr == ptr) {
#if	DEBUG > 4
	    printf("munmapFile: clearing entry %d\n", i);
#endif	/* DEBUG > 4 */
#if	0
	    if (mmp->size) {
		(void) munmap((void *) ptr, (size_t) mmp->size);
	    }
#endif
	    if (close(mmp->fd) < 0) {
		perror("close");
		Bail(1);
	    }
#ifdef	PARANOID
	    mmp->buf = (void *) NULL;
#endif	/* PARANOID */
	    mmp->inUse = 0;
#ifdef	DEBUG
	    printf("DEBUG: munmapFile: freeing %d bytes\n",
		   mmp->size);
#endif	/* DEBUG */
	    memFree(mmp->mmPtr, MTAG_MMAPFILE);
	    break;
	}
    }
    return;
}


int fileLineCount(char *pathname)
{
    char *filep;
    int i;
    /*
     * We need the file size to know when we're at the end-of-file.
     *****
     * When we call mmapFile(), we automatically get a stat(2) structure
     * populated for the file we're opening, so we can get the file size
     * from there.  Maybe that's kludgy, and maybe it's 'efficient design'. 
     */

#ifdef	PROC_TRACE
	traceFunc("== fileLineCount(%s)\n", pathname);
#endif	/* PROC_TRACE */

    if ((filep = mmapFile(pathname)) == NULL_STR) {
	return(0);
    }
    i = bufferLineCount(filep, cur.stbuf.st_size);
    munmapFile(filep);
    return(i);
}


int bufferLineCount(char *p, int len)
{
    char *cp; 
    char *eofaddr;
    int i;
    
#ifdef	PROC_TRACE
	traceFunc("== bufferLineCount(%p, %d)\n", p, len);
#endif	/* PROC_TRACE */

    if (eofaddr == p) {
	return(0);
    }
    eofaddr = (char *) (p+len);
    for (i = 0, cp = p; cp <= eofaddr; cp++, i++) {
	if ((cp = findEol(cp)) == NULL_STR || *cp == NULL_CHAR) {
	    break;
	}
    }
#if	(DEBUG > 3)
    printf("bufferLineCount == %d\n", i);
#endif	/* DEBUG > 3 */
    return(i ? i : 1);
}


/*
 * like strcmp(), but operate on contents of files
 */
int fileCompare(char *f1, char *f2)
{
    int size1;
    int size2;
    int ret;
    char *base1;
    char *base2;

#ifdef	PROC_TRACE
	traceFunc("== fileCompare(%s, %s)\n", f1, f2);
#endif	/* PROC_TRACE */

    base1 = mmapFile(f1);
    size1 = (int) cur.stbuf.st_size;
    base2 = mmapFile(f2);
    size2 = (int) cur.stbuf.st_size;
    if (size1 == 0 && size2 == 0) {
	ret = 0;
    } else if (size1 < size2) {
	ret = -1;
    } else if (size1 > size2) {
	ret = 1;
    } else {
	ret = strcmp(base1, base2);
    }
    if (base1) {
	munmapFile(base1);
    }
    if (base2) {
	munmapFile(base2);
    }
    return(ret);
}


void appendFile(char *pathname, char *str)
{
    FILE *fp;

#ifdef	PROC_TRACE
	traceFunc("== appendFile(%s, \"%s\")\n", pathname, str);
#endif	/* PROC_TRACE */

    fp = fopenFile(pathname, "a+");
    fprintf(fp, "%s\n", str);
    (void) fclose(fp);
    return;
}

/*
  Dump the contents of a file -- this utility MAY go away later!
*/
void dumpFile(FILE *fp, char *pathname, int logFlag)
{
    char *filep;

#ifdef	PROC_TRACE
	traceFunc("== dumpFile(%p, %s, %d)\n", fp, pathname, logFlag);
#endif	/* PROC_TRACE */

    if (!isFILE(pathname)) {
#ifdef	DEBUG
	printf("%s: NO SUCH FILE\n", pathname);
#endif	/* DEBUG */
	perror(pathname);
	return;
    }
    if (cur.stbuf.st_size == 0) {
	printf("%s: EMPTY\n", pathname);
	return;
    }
    if ((filep = mmapFile(pathname)) == NULL_STR) {
	return;
    }
    fprintf(fp, "%s", filep);
    munmapFile(filep);
    return;
}


/* CDB ?? Really needed ?? Can we get rid of it easily */
/*
 * This is the filter routine called in nftw() callback functions.  The 
 * general rule is to return 1 if we DON'T want to unpack an inode.
 * One flag we implement here is whether or not we want to remove any
 * files whose link count is >1.  We DON'T want to do this in the first
 * pass through a distribution, as it's reasonable for RPMs to have
 * links to other identical RPMs in other distros.
 */
int nftwFileFilter(char *pathname, struct stat *st, int onlySingleLink)
{
    int ret = 0;
    
    if (S_ISDIR(st->st_mode)) {	/* no dirs, please */
	if (access(pathname, X_OK) != 0) {
	    chmodInode(pathname, (st->st_mode | S_IXUSR));
#ifdef	QA_CHECKS
	    Warn("corrected bad dir (mode) \"%s\"", pathname);
#endif	/* QA_CHECKS */
	}
	return(1);
    }
#if	defined(PROC_TRACE) || defined(UNPACK_DEBUG)
	traceFunc("== nftwFileFilter(\"%s\", %p)\n", pathname, st);
#endif	/* PROC_TRACE || UNPACK_DEBUG */

    if (!S_ISREG(st->st_mode)) {
#ifdef	UNPACK_DEBUG
	if (S_ISLNK(st->st_mode)) {
	    printf("*** symlink %s (stat)\n", pathname);
	}
	else if (S_ISBLK(st->st_mode)) {
	    printf("*** block-mode %s (stat)\n", pathname);
	}
	else if (S_ISCHR(st->st_mode)) {
	    printf("*** char-mode %s (stat)\n", pathname);
	}
	else if (S_ISSOCK(st->st_mode)) {
	    printf("*** socket %s (stat)\n", pathname);
	}
	else if (S_ISFIFO(st->st_mode)) {
	    printf("*** fifo/pipe %s (stat)\n", pathname);
	}
	else {
	    printf("*** UNKNOWN? 0%o %s (stat)\n", st->st_mode,
		   pathname);
	    mySystem("ls -lid \"%s\"", pathname);
	    mySystem("file \"%s\"", pathname);
	}
#endif	/* UNPACK_DEBUG */
	ret = 1;
    }
    if (onlySingleLink && st->st_nlink != 1) {
#ifdef	UNPACK_DEBUG
	printf("+++ %s nlink == %d\n", pathname, st->st_nlink);
#endif	/* UNPACK_DEBUG */
	ret = 1;
    }
    if (st->st_size == 0) {
#ifdef	UNPACK_DEBUG
	printf("--- %s empty\n", pathname);
#endif	/* UNPACK_DEBUG */
	ret = 1;
    }
#ifdef notdef
    if (!optionIsSet(OPTS_SRCONLY) && IS_HUGE(st->st_blocks)) {
#ifdef	UNPACK_DEBUG
	printf("*** huge file %s (%d blocks)\n", pathname,
	       st->st_blocks);
#endif	/* UNPACK_DEBUG */
	ret = 1;
    }
#endif /* notdef */
    if (ret && *pathname != '/') {
	unlinkFile(pathname);
    }
    return(ret);
}



void makePath(char *dirpath)	/* e.g., the command "mkdir -p" */
{
    char *cp = dirpath;

#if	defined(PROC_TRACE) || defined(UNPACK_DEBUG)
	traceFunc("== makePath(%s)\n", dirpath);
#endif	/* PROC_TRACE || UNPACK_DEBUG */

    if (isDIR(dirpath)) {
	Warn("makePath: \"%s\" exists\n", cp);
	return;
    }

    while ((cp = strchr(cp, '/')) != NULL_STR) {
	*cp = NULL_CHAR;
	makeDir(dirpath);
	*cp = '/';
	cp++;
    }
    makeDir(dirpath);
    return;
}


void makeDir(char *dirpath)
{
#if	defined(PROC_TRACE) || defined(UNPACK_DEBUG)
	traceFunc("== makeDir(%s)\n", dirpath);
#endif	/* PROC_TRACE || UNPACK_DEBUG */

    if (isDIR(dirpath)) {
#if	(DEBUG > 1)
	printf("makeDir: \"%s\" exists\n", dirpath);
#endif	/* DEBUG > 1 */
	return;
    }
    if (mkdir(dirpath, 0755) < 0) {
	perror(dirpath);
	Fatal("Failure in makeDir");
    }
    return;
}


void removeDir(char *dir)
{

#if	defined(PROC_TRACE) || defined(UNPACK_DEBUG)
	traceFunc("== removeDir(%s)\n", dir);
#endif	/* PROC_TRACE || UNPACK_DEBUG */

    if (mySystem("rm -rf %s", dir)) {
	Fatal("Cannot clean %s", dir);
    }
    return;
}


int mySystem(const char *fmt, ...)
{
    int ret;
    va_start(ap, fmt);
    (void) vsprintf(cmdBuf, fmt, ap);
    va_end(ap);

#if defined(PROC_TRACE) || defined(UNPACK_DEBUG)
	traceFunc("== mySystem('%s')\n", cmdBuf);
#endif  /* PROC_TRACE || UNPACK_DEBUG */

    ret = system(cmdBuf);
    if (WIFEXITED(ret)) {
	ret = WEXITSTATUS(ret);
#ifdef	DEBUG
	if (ret) {
	    Error("system(%s) returns %d", cmdBuf, ret);
	}
#endif	/* DEBUG */
    }
    else if (WIFSIGNALED(ret)) {
	ret = WTERMSIG(ret);
	Error("system(%s) died from signal %d", cmdBuf, ret);
    }
    else if (WIFSTOPPED(ret)) {
	ret = WSTOPSIG(ret);
	Error("system(%s) stopped, signal %d", cmdBuf, ret);
    }
#if	0
    if (ret && isFILE(UNPACK_STDERR) && (int) cur.stbuf.st_size > 0) {
	dumpFile(stderr, UNPACK_STDERR, YES);
    }
#endif
    return(ret);
}


int isFILE(char *pathname)
{

#ifdef	PROC_TRACE
	traceFunc("== isFILE(%s)\n", pathname);
#endif	/* PROC_TRACE */

    return(isINODE(pathname, S_IFREG));
}


/*
 * addEntry() adds a line to the specified pathname if either:
 *	(a) the line does NOT already exist in the line, or
 *	(b) the variable 'forceFlag' is set to non-zero
 */
int addEntry(char *pathname, int forceFlag, const char *fmt, ...)
{
    va_start(ap, fmt);
    vsprintf(utilbuf, fmt, ap);
    va_end(ap);

#ifdef  PROC_TRACE
	traceFunc("== addEntry(%s, %d, \"%s\")\n", pathname, forceFlag, utilbuf);
#endif  /* PROC_TRACE */

    if (pathname == NULL_STR) {
	Assert(YES, "addEntry - NULL pathname");
    }
    if (forceFlag || !lineInFile(pathname, utilbuf)) {
	appendFile(pathname, utilbuf);
	return(1);
    }
    return(0);
}

/*
 * DO NOT automatically add \n to a string passed to Msg(); in
 * parseDistro, we sometimes want to dump a partial line.
 */
void Msg(const char *fmt, ...)
{
    va_start(ap, fmt);
    (void) vprintf(fmt, ap);
    va_end(ap);
    return;
}


void Note(const char *fmt, ...)
{
    va_start(ap, fmt);
    (void) sprintf(utilbuf, "NOTE: ");
    (void) vsprintf(utilbuf+strlen(utilbuf), fmt, ap);
    va_end(ap);

#ifdef  PROC_TRACE
	traceFunc("== Warn(\"%s\")\n", utilbuf);
#endif  /* PROC_TRACE */

    (void) strcat(utilbuf, "\n");
    Msg("%s", utilbuf);
    return;
}


void Warn(const char *fmt, ...)
{
    va_start(ap, fmt);
    (void) sprintf(utilbuf, "WARNING: ");
    (void) vsprintf(utilbuf+strlen(utilbuf), fmt, ap);
    va_end(ap);

#ifdef  PROC_TRACE
	traceFunc("== Warn(\"%s\")\n", utilbuf);
#endif  /* PROC_TRACE */

    (void) strcat(utilbuf, "\n");
    Msg("%s", utilbuf);
    return;
}


void Assert(int fatalFlag, const char *fmt, ...)
{
    va_start(ap, fmt);
    (void) sprintf(utilbuf, "ASSERT: ");
    (void) vsprintf(utilbuf+strlen(utilbuf), fmt, ap);
    va_end(ap);

#ifdef  PROC_TRACE
	traceFunc("!! Assert(\"%s\")\n", utilbuf+strlen(gl.progName)+3);
#endif  /* PROC_TRACE */

    (void) strcat(utilbuf, "\n");
    Msg("%s", utilbuf);
    if (fatalFlag) {
	Bail(1);
    }
    return;
}


void Error(const char *fmt, ...)
{
    va_start(ap, fmt);
    (void) sprintf(utilbuf, "ERROR: ");
    (void) vsprintf(utilbuf+strlen(utilbuf), fmt, ap);
    va_end(ap);

#ifdef  PROC_TRACE
	traceFunc("== Error(\"%s\")\n");
#endif  /* PROC_TRACE */

    (void) strcat(utilbuf, "\n");
    Msg("%s", utilbuf);
    return;
}


void Fatal(const char *fmt, ...)
{
    va_start(ap, fmt);
    (void) sprintf(utilbuf, "%s: FATAL: ", gl.progName);
    (void) vsprintf(utilbuf+strlen(utilbuf), fmt, ap);
    va_end(ap);

#ifdef  PROC_TRACE
	traceFunc("!! Fatal(\"%s\")\n", utilbuf + strlen(gl.progName) + 9);
#endif  /* PROC_TRACE */

    (void) strcat(utilbuf, "\n");
    Msg("%s", utilbuf);
    Bail(1);
}

void traceFunc(char *fmtStr, ...)
{
    va_list args;

#ifdef PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif /* PROC_TRACE_SWITCH */
    va_start(args, fmtStr);

    vprintf(fmtStr, args);
    va_end(args);
#ifdef PROC_TRACE_SWITCH
}
#endif /* PROC_TRACE_SWITCH */
}
