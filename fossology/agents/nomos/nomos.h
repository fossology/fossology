/***************************************************************
 Copyright (C) 2006,2009 Hewlett-Packard Development Company, L.P.
 
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
#ifndef _NOMOS_H
#define _NOMOS_H 1
#ifndef	_GNU_SOURCE
#define	_GNU_SOURCE
#endif	/* not defined _GNU_SOURCE */
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <fcntl.h>
#include <string.h>
#include <strings.h>
#include <dirent.h>
#include <ftw.h>
#include <regex.h>
#include <getopt.h>
#include <time.h>
#include <magic.h>
#include <errno.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/wait.h>
#include <sys/mman.h>
#include <sys/time.h>

/*
 * TO use our local version of debug-malloc(), compile -DMEMORY_TRACING
 */
#ifdef	MEMORY_TRACING
#include "DMalloc.h"
#endif	/* MEMORY_TRACING */

#define	PRECHECK
#define	GPLV2_BEATS_GPLV3
#define	SAVE_UNCLASSIFIED_LICENSES
/*#define	FLAG_NO_COPYRIGHT*/

#ifdef	PROC_TRACE_SWITCH
#define	PROC_TRACE
#endif	/* PROC_TRACE_SWITCH */

#define	myBUFSIZ	2048
#define	MAX_RENAME	1000

/*
 * Program options and flags
 */
#define	OPTS_DEBUG		0x1
#define	OPTS_TRACE_SWITCH	0x2

/*
  Flags for program control
*/
#define	FL_SAVEBASE	0x20
#define	FL_FRAGMENT	0x40
#define	FL_SHOWMATCH	0x80
#define	FL_NOCOPYRIGHT	0x100

/*
 * Names of various files/dirs created/used
 */
#define	FILE_FOUND	"Found.txt"
#define	FILE_SCORES	"_scores"


/* Symbolic Boolean values */
#define	NO	0
#define	YES	1

/* List-sorting flags */
#define	UNSORTED		0
#define	SORT_BY_NAME		1
#define	SORT_BY_NAME_ICASE	2
#define	SORT_BY_COUNT_DSC	3
#define	SORT_BY_COUNT_ASC	4
#define	SORT_BY_ALIAS		5
#define	SORT_BY_BASENAME	6

/* Interest level (licenses) */
#define	IL_HIGH		3
#define	IL_MED		2
#define	IL_LOW		1
#define	IL_NONE		0
#define	IL_INIT		-1

/*
 * license-text search results (ltsr) stuff
 */
#define	LTSR_RMASK	((char) 1)	/* True if it's been matched */
#define	LTSR_SMASK	((char) 2)	/* True if it's been searched for */
#define	LTSR_YES	((char) 3)	/* both searched, and matched */
#define	LTSR_NO		LTSR_SMASK	/* searched but not matched */

/*
 * Miscellaneous strings used in various modules
 */
#define STR_NOTPKG      "None (not an rpm-format package)"

/*
 * License-scanning limits
 */
#define	_scCOMFORT	9	/* >= 9 --> certain it's a license */
#define	_scINVALID	4	/* < 4 --> probably NOT a license */

/*
 * LS_ = License Summaries/Strings
 */
#define	LS_NONE		"None"
#define	LS_UNLIKELY	"LikelyNot"
#define	LS_NOSUM	"NoLicenseFound"
#define	LS_UNCL		"UnclassifiedLicense"
#define	LS_NOT_PD	"NOT-public-domain"
#define	LS_PD_CLM	"Public-domain-claim"
#define	LS_PD_CPRT	"Public-domain(C)"
#define	LS_PD_ONLY	"Public-domain-ref"
#define	LS_CPRTONLY	"Misc-Copyright"
#define	LS_TDMKONLY	"Trademark-ref"
#define	LS_LICRONLY	"License-ref"
#define	LS_PATRONLY	"Patent-ref"

/*
 * NULL values
 */
#define	NULL_ITEM	(item_t *) NULL
#define	NULL_LIST	(list_t *) NULL
#define	NULL_FH		(fh_t *) NULL
#define	NULL_CHAR	'\0'
#define	NULL_STR	(char *) NULL

/*
 * Macros needed across >1 source module
 */
#define	isEOL(x)	(((x == '\n') || (x == '\r') || (x == '\v')))
#define	IS_HUGE(x)	(x >= gl.blkUpperLimit)



#define	NOMOS_TEMP	"/tmp/nomos.tempdir"
#define	NOMOS_TLOCK	"/tmp/nomos.tempdir/.lock.tmp," /* CDB, This goes away. */


/*
  Caches memory-mapped files
*/
struct mm_cache {
    int inUse; 
    int fd; 
    int size;
    void *mmPtr;
    char label[myBUFSIZ];
};


/*
  CDB - This is kind of tricky, the way it uses the same fields for
  different meanings. If we had objects, we could subclass. It works
  okay, but is just a PITA for debugging.
*/
struct listitem {
    /* 
       Meanings of val fields are dependent on the particular list --
       See #defines below for examples.
    */
    int val; 
    int val2;
    int val3;
    char *str;		/* primary key for list-element */
    void *buf;		/* alias, extra data, whatever */
};
typedef	struct listitem item_t;


/* 
   Defines for the list val fields 
*/
#define	seqNo		val
#define	foundTool	val
#define	refCount	val
#define num		val
#define bStart		val
#define iFlag		val
#define	bucketType	val2
#define	ssComp		val2
#define	isProcessed	val2
#define iLevel		val2
#define nMatch		val2
#define bLen		val2
#define bDocLen		val3
#define bIndex		val3
#define bList		buf

struct list {
    char name[64];
    int used; 
    int size; 
    int ix; 
    int sorted; 
    int desc;
    item_t *items;
};
typedef	struct list list_t;


struct searchString {
    int csLen;
    char *csData;
};
typedef struct searchString searchString_t;


struct licenseSpec {
    searchString_t seed; 
    searchString_t text;
};
typedef struct licenseSpec licSpec_t;


/*
  CDB - Commenting to note purpose of the various fields so we can tell
  which are true globals and which we will need on
  a per-file basis. 
*/
struct globals {
    char initwd[myBUFSIZ]; /* Global, plan to eliminate  */
    char cwd[myBUFSIZ]; /* Global, will be unneeded */
    char target[myBUFSIZ]; /* Global, can probably get rid of  */
    char targetFile[myBUFSIZ]; /* Should be moved to perScan */
    char progName[64]; /* Global, plan to eliminate */
    char *licPara; /* Used, move to perScan */
    char *matchBase; /* Used, move to perScan */
    int progOpts; /* Global */
    int flags; /* Global, merge with progOpts? */
    int uPsize; /* Global */
#ifdef	GLOBAL_DEBUG
    int DEEBUG; 
    int MEM_DEEBUG;
#endif	/* GLOBAL_DEBUG */
#ifdef	PROC_TRACE_SWITCH
    int ptswitch;
#endif	/* PROC_TRACE_SWITCH */
    size_t targetLen; /* Used, will probably be eliminated, move to perScan */
    size_t cwdLen; /* Used, will probably be eliminated, move to perScan */
    struct stat stbuf; /* Horrible side-effect trap. move to perScan */
    regmatch_t regm; /* Used, move to perScan */
    magic_t mcookie; /* Global */
    list_t regfList; /* perScan */
    list_t fLicFoundMap; /* perScan */
    list_t parseList; /* perScan */
    list_t sHash; 
    list_t offList;
};


/*
  CDB - We don't really have a current package anymore.
  Would like to change to curFile for the sake of consistency.
*/
struct curPkg {
    char pathname[myBUFSIZ];
    char claimlic[myBUFSIZ];
    char cachelic[myBUFSIZ];
    char compLic[myBUFSIZ];
    char briefLic[myBUFSIZ];
    char url[256];
    char dosname[256];
    char basename[128];
    char name[128];
    char version[128];
    char source[128];
    char srcname[128];
    char srcvers[128];
    char reportname[128];
    char vendor[32];
    char arch[16];
    char format[16];
    char ptype[8];
    char debEpoch[8];
    char md5sum[33];
    int isSource;
    int isRpm;
    int noSrc;
    int nLines;
    int nWords;
    int bucketType;
};

struct license {
    int len;
    char *patt;
};

struct licensetext {
    char *regex; 
    char *tseed;	/* unencrypted license text */
    int nAbove; 
    int nBelow; 
    int compiled; 
    int plain;
};
typedef struct licensetext licText_t;

#define	_REGEX(x)	licText[x].regex
#define	_SEED(x)	licText[x].tseed

struct scanResults {
    int score; 
    int kwbm; 
    int size; 
    int flag;
    char fullpath[myBUFSIZ]; 
    char ftype[256]; 
    char linkname[16]; 
    char *licenses; 
    char *relpath;
    size_t nameOffset;
};
typedef	struct scanResults scanres_t;

/*
 * List-based memory tags
 */
#define	MTAG_UNSORTKEY	"list/str (initially-UNsorted key)"
#define	MTAG_SORTKEY	"list/str (initially-sorted key)"
#define	MTAG_LISTKEY	"list/str (sorted/unsorted key)"
#define	MTAG_REPLKEY	"list/str (replaced primary key)"
#define	MTAG_LISTBUF	"list/buf (any data)"
#define	MTAG_PATHBASE	"list/buf (path basename)"
#define	MTAG_PKGINFO	"list/buf (pkg rname/type/name/vers/lic)"
#define	MTAG_PKG_NV	"list/buf (pkg name/vers)"
#define	MTAG_MD5SUM	"list/buf (distro-arch MD5SUM)"
#define	MTAG_COUNTER	"list/buf integer (counter)"
#define	MTAG_PKGNAME	"list/buf (package-name)"
#define	MTAG_PKGVERS	"list/buf (package-vers)"
#define	MTAG_CLAIMLIC	"list/buf (claimlic copy)"
#define	MTAG_COMPLIC	"list/buf (pkg compLic copy)"
#define	MTAG_URLCOPY	"list/buf (pkg URL copy)"
#define	MTAG_FILELIC	"list/buf (file-license copy)"
#define	MTAG_FIXNAME	"list/buf (fixed-package name)"
/*
 * Miscellaneous memory tags
 */
#define	MTAG_SEEDTEXT	"search-seed text"
#define	MTAG_SRCHTEXT	"license-search text"
#define	MTAG_MMAPFILE	"mmap-file data"
#define	MTAG_MAGICDATA	"file magic description"
#define	MTAG_BUCKETDATA	"bucket-data(copy)"
#define	MTAG_PATTRS	"pkg-attr buffer"
#define	MTAG_DOUBLED	"doubled (reallocated) data"
#define	MTAG_SEARCHBUF	"initial search-data buffer"
#define	MTAG_TOOSMALL	"too-small half-size buffer"
#define	MTAG_TEXTPARA	"paragraph text"
#define	MTAG_BUCKET	"extensible license bucket array"
#define	MTAG_LIST	"dynamically-allocated list"
#define	MTAG_ENV	"environment variable"
#define	MTAG_SCANRES	"scan-results list"

#define	LABEL_ATTR	"==== Attributes ===="
#define	LABEL_TEXT	"==== File Text ===="
#define	LABEL_PATH	"Path:"
#define	LABEL_CNTS	"Counts:"
#define LABEL_HOTW	"Hotword: "

/*
   Functions defined in nomos.c, used in other files
*/
void Bail(int exitval);
int optionIsSet(int val);

/*
  Global Declarations
*/
extern struct globals gl;
extern struct curPkg cur;
extern licText_t licText[];
extern licSpec_t licSpec[];

/*
  Declarations for using the memory debug stuff
*/
#ifdef	MEMORY_TRACING 
char *memAllocTagged();
void memFreeTagged();
#define	memFree(x,y)		memFreeTagged(x, y)
#define	memAlloc(x,y)		memAllocTagged(x, y)
#else	/* NOT MEMORY_TRACING */
#define	memFree(x,/*notused*/y)	free(x)
#define	memAlloc(x,y)		calloc(x, 1)
#endif	/* NOT MEMORY_TRACING */

/*
 * Macros for timing - refer to findPhrase() for usage examples
 */
/* need TIMING_DECL in the declarations section of function */
#define	DECL_TIMER	struct timeval bTV, eTV; float proctime
#define	ZERO_TIMER	memcpy((void *) &bTV, (void *) &eTV, sizeof(eTV))
#define	RESET_TIMER	END_TIMER; ZERO_TIMER
#define	START_TIMER	RECORD_TIMER(bTV)
#define	END_TIMER 	RECORD_TIMER(eTV) ; \
			proctime = (float) (eTV.tv_sec - bTV.tv_sec) + \
			    ((float) (eTV.tv_usec - bTV.tv_usec) * 0.000001) 
#define	RECORD_TIMER(x)	(void) gettimeofday(&x, (struct timezone *) NULL)
#define	PRINT_TIMER(x,y)	printf("%11.6f seconds: %s\n", proctime, x); \
			if (y) { DUMP_TIMERS; }
#define	DUMP_TIMERS	printf("[1]: %d.%06d\n", bTV.tv_sec, bTV.tv_usec); \
			printf("[2]: %d.%06d\n", eTV.tv_sec, eTV.tv_usec)


/*
 * Cut-and-paste this stuff to turn on timing
 */
#if	0
#ifdef	TIMING
	DECL_TIMER;	/* timer declaration */
#endif
/* */
#ifdef	TIMING
	START_TIMER;	/* turn on the timer */
#endif	/* TIMING */
/* */
#ifdef	TIMING
	END_TIMER;	/* stop the timer */
	PRINT_TIMER("unpack", 0);	/* ... and report */
	START_TIMER;	/* optionally re-start timer */
#endif	/* TIMING */
#endif

#endif /* _NOMOS_H */
