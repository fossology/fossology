/*
 SPDX-FileCopyrightText: © 2006-2014 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2014 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \dir
 * \brief Nomos agent source
 * \file
 * \brief Nomos header file
 * \page nomos Nomos agent
 * \tableofcontents
 * \section nomosabout About
 * Nomos does license identification using short phrases (regular expressions)
 * and heuristics (e.g. phrase must be found in (or out of) proximity to
 * another phrase or phrases).
 *
 * Nomos may also identify a "style" type of license if it has similarities
 * with a known license type.
 *
 * The signatures which uniquely identify a license are stored in `STRINGS.in`
 * file.
 *
 * If you wish to contribute to Nomos, please read [How to add a new license
 * signature](https://github.com/fossology/fossology/wiki/Nomos).
 *
 * \subsection nomosdebug Debugging
 * Activating the defines of PROC_TRACE and/or DOCTOR_DEBUG (see line 31 of
 * file parse.c). Nomos generates lot of tracing information that is really
 * helpful to debug it.
 *
 * `PROC_TRACE` will show you, for example, which regex's were tried and which
 * are successful. To see the successful matches, grep the output file for
 * "addRef".
 *
 * `DOCTOR_DEBUG` will show you the before and after versions of the buffer to
 * be processed. Look in the output file or `----- [Dr-BEFORE:] -----` and
 * `+++++ [Dr-AFTER] +++:`
 *
 * \section nomosactions Supported actions
 *
 * | Command line flag | Description |
 * | ---: | :--- |
 * | -i   | Initialize the database, then exit. |
 * | -c   | Specify the directory for the system configuration. |
 * | -l   | Print full file path (command line only). |
 * | -v   | Verbose (-vv = more verbose). |
 * | -J   | Output in JSON. |
 * | -S   | Print Highlightinfo to stdout . |
 * | file | If files are listed, print the licenses detected within them. |
 * | no file | Process data from the scheduler. |
 * | -V   | Print the version info, then exit. |
 * | -d   | Specify a directory to scan. |
 * | -n   | Spaw n - 1 child processes to run, there will be n running
 * processes(the parent and n - 1 children). \n The default n is 2(when n is
 * less than 2 or not setting, will be changed to 2) when -d is specified. |
 * \section nomossource Agent source
 *   - \link src/nomos/agent \endlink
 *   - \link src/nomos/ui \endlink
 *   - Functional test cases \link src/nomos/agent_tests/Functional \endlink
 *   - Unit test cases \link src/nomos/agent_tests/Unit \endlink
 */

#ifndef _NOMOS_H
#define _NOMOS_H 1
#ifndef	_GNU_SOURCE
#define	_GNU_SOURCE
#endif	/* not defined _GNU_SOURCE */
#include <glib.h>
#include <stdio.h>
#include <assert.h>
#include <stdlib.h>
#include <search.h>
#include <unistd.h>
#include <fcntl.h>
#include <string.h>
#include <strings.h>
#include <ctype.h>
#include <dirent.h>
#include <ftw.h>
#include <regex.h>
#include <getopt.h>
#include <time.h>
#include <libgen.h>
#include <errno.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/wait.h>
#include <sys/mman.h>
#include <sys/time.h>
#include "nomos_gap.h"
#include <stdbool.h>
#include <semaphore.h>
#include <stdbool.h>
#include "json_writer.h"

/** Use nomos in standalone mode (no FOSSology DB) */
#ifdef STANDALONE
#include "standalone.h"
#else
#include <libfossology.h>
#include <libfossdbmanager.h>
#endif

/**
 * To use our local version of debug-malloc(), compile -DMEMORY_TRACING
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

#define	myBUFSIZ	4096      ///< Buffer max length
#define	MAX_RENAME	1000    ///< Max rename length
#define TEMP_FILE_LEN 100   ///< Max temp file length

/** MAX_SCANBYTES is the maximum number of bytes that will be scanned
 * in a file.  Historically, we have never found a license more than
 * 64k into a file.
 */
#define MAX_SCANBYTES 1024*1024

/**
 * Program options and flags
 *
 * MD: I think these are used when making nomos
 */
#define	OPTS_DEBUG		0x1
#define	OPTS_TRACE_SWITCH	0x2
#define OPTS_LONG_CMD_OUTPUT 0x4
#define OPTS_HIGHLIGHT_STDOUT 0x8
#define OPTS_NO_HIGHLIGHTINFO 0x10
#define OPTS_JSON_OUTPUT 0x20
#define OPTS_SCANNING_DIRECTORY 0x40

extern char debugStr[myBUFSIZ];        ///< Debug string
extern char dbErrString[myBUFSIZ];     ///< DB error string
extern char saveLics[myBUFSIZ];        ///< License string

extern size_t hashEntries;             ///< Hash entries

/**
  Flags for program control
 */
#define	FL_SAVEBASE	0x20
#define	FL_FRAGMENT	0x40
#define	FL_SHOWMATCH	0x80
#define	FL_NOCOPYRIGHT	0x100

/**
 * Names of various files/dirs created/used
 */
#define	FILE_FOUND	"Found.txt"
#define	FILE_SCORES	"_scores"
#define DEBUGLOG    "/tmp/NomosDebugLog"


/**
 *  Symbolic Boolean value for NO
 */
#define	NO	0
/**
 *  Symbolic Boolean value for YES
 */
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

/**
 * license-text search results (ltsr) stuff
 */
#define	LTSR_RMASK	((char) 1)	/**< True if it's been matched */
#define	LTSR_SMASK	((char) 2)	/**< True if it's been searched for */
#define	LTSR_YES	((char) 3)	  /**< Both searched, and matched */
#define	LTSR_NO		LTSR_SMASK	  /**< Searched but not matched */

/**
 * Miscellaneous strings used in various modules
 */
#define STR_NOTPKG      "None (not an rpm-format package)"

/*
 * License-scanning limits
 */
#define	_scCOMFORT	9	/**< >= 9 --> certain it's a license */
#define	_scINVALID	4	/**< < 4 --> probably NOT a license */

/**
 * LS_ = License Summaries/Strings
 */
#define	LS_NONE		"None"
#define	LS_UNLIKELY	"LikelyNot"
#define	LS_NOSUM	"No_license_found"
#define	LS_UNCL		"LicenseRef-UnclassifiedLicense"
#define	LS_NOT_PD	"LicenseRef-NOT-public-domain"
#define	LS_PD_CLM	"LicenseRef-Public-domain"
#define	LS_PD_CPRT	"LicenseRef-Public-domain-C"
#define	LS_PD_ONLY	"LicenseRef-Public-domain-ref"
#define	LS_CPRTONLY	"LicenseRef-Misc-Copyright"
#define	LS_TDMKONLY	"LicenseRef-Trademark-ref"
#define	LS_LICRONLY	"LicenseRef-License-ref"
#define	LS_PATRONLY	"LicenseRef-Patent-ref"

/*
 * NULL values
 */
#define	NULL_ITEM	(item_t *) NULL ///< NULL item
#define	NULL_LIST	(list_t *) NULL ///< NULL list
#define	NULL_FH		(fh_t *) NULL   ///< NULL fh
#define	NULL_CHAR	'\0'            ///< NULL character
#define	NULL_STR	(char *) NULL   ///< NULL string

/**
 * Macros needed across >1 source module
 */
#define	isEOL(x)	(((x == '\n') || (x == '\r') || (x == '\v'))) ///< Check if x points to a EOL character
#define	IS_HUGE(x)	(x >= gl.blkUpperLimit)



#define	NOMOS_TEMP	"/tmp/nomos.tempdir"
#define	NOMOS_TLOCK	"/tmp/nomos.tempdir/.lock.tmp," /**< CDB, This goes away. */


/**
 * Caches memory-mapped files
 */
struct mm_cache {
    int inUse;            ///< Cache in use
    int fd;               ///< File descriptor
    unsigned long size;   ///< Size
    void *mmPtr;          ///< Memory pointer
    char label[myBUFSIZ]; ///< Label
};


/*
  CDB - This is kind of tricky, the way it uses the same fields for
  different meanings. If we had objects, we could subclass. It works
  okay, but is just a PITA for debugging.
 */

/**
   listitem item_t
   \brief tricky data structure used for a list of 'items'

   Meanings of val fields are dependent on the particular list --
   See #defines below for examples.
 */
struct listitem {
  int val;
  int val2;
  int val3;
  char *str;		/**< primary key for list-element */
  void *buf;		/**< alias, extra data, whatever */
};
typedef	struct listitem item_t;


/**
   Defines for the list val fields
 */
#define	seqNo		val
#define	foundTool	val
#define	refCount	val
#define num		val
#define bStart		val
#define iFlag		val
#define	ssComp		val2
#define	isProcessed	val2
#define iLevel		val2
#define nMatch		val2
#define bLen		val2
#define bDocLen		val3
#define bIndex		val3
#define bList		buf

/**
 list
 \brief list_t type structure used to keep various lists. (e.g. there are
 multiple lists).

 */
struct list {
    char name[64]; /**< Name of the list */
    int used; /**< Number of items found, 0 is empty list */
    int size; /**< What size is this? (MD) */
    int ix; /**< The index for the items below */
    int sorted; /**< Flag to indicate how ?? (the list or the items in the
                         list?) things are sorted: SORT_BY_NAME or
                         SORT_BY_NAME_ICASE */
    int desc; /**< Description */
    item_t *items; /**< List items */
};
typedef	struct list list_t;

/**
 * Search string
 */
struct searchString {
    int csLen;      ///< String length
    char *csData;   ///< String data
};
typedef struct searchString searchString_t;

/**
 * License specification
 */
struct licenseSpec {
    searchString_t seed;  ///< License seed
    searchString_t text;  ///< License text
};
typedef struct licenseSpec licSpec_t;


/**
  \brief Structure holding data truly global in that it remains consistent
  for each file scanned.
 */
struct globals {
    char initwd[myBUFSIZ];  ///< CDB, would like to workaround/eliminate.
    char progName[64];      ///< Program name
    int progOpts;           ///< CLI options
    int flags;              ///< Flags
    int uPsize;             ///< Size
#ifdef	GLOBAL_DEBUG
  int DEEBUG;
  int MEM_DEEBUG;
#endif	/* GLOBAL_DEBUG */
#ifdef	PROC_TRACE_SWITCH
  int ptswitch;
#endif	/* PROC_TRACE_SWITCH */
    list_t sHash;           ///< Hashes
    /* Agent-specific Things */
    int agentPk;            ///< Agent id
    long uploadFk;          ///< Upload id
    int arsPk;              ///< Agent ars id
    PGconn *pgConn;         ///< DB Connection
    fo_dbManager *dbManager;  ///< FOSSology DB manager
};

/**
 * License match positions and license type
 */
typedef struct  {
    int start;    ///< Start position of match
    int end;      ///< End position of match
    int index;    ///< Enums from index (Entrynumber) in STRINGS.in
} MatchPositionAndType;

/**
 * License matches
 */
typedef struct  {
    GArray* matchPositions;   ///< Match positions
    GArray* indexList;        ///< License indexes
    char* licenceName;        ///< License names
    int licenseFileId;        ///< PFile id
} LicenceAndMatchPositions;



/**
  curScan
  \brief Struct that tracks state related to current file being scanned.
 */
struct curScan {
  char cwd[myBUFSIZ];      /**< CDB, Would like to workaround and eliminate. */
  char targetDir[myBUFSIZ]; 	/**< Directory where file is */ /* check */
  char targetFile[myBUFSIZ]; 	/**< File we're scanning (tmp file)*/ /* check */
  char filePath[myBUFSIZ];    /**< the original file path passed in */
  long pFileFk;            /**< [in] pfile_fk from scheduler */
  char pFile[myBUFSIZ];       /**< [in] pfilename from scheduler */
  char *licPara;
  char *matchBase;
  size_t targetLen;
  size_t cwdLen;
  struct stat stbuf;
  regmatch_t regm;
  list_t regfList;
  list_t fLicFoundMap;
  list_t parseList;
  list_t offList;
  list_t lList;
  char compLic[myBUFSIZ];  	/**< the license(s) found, None or NotLikely.
                                comma separated if multiple names are found. */
  int nLines;
  int cliMode;                /**< boolean to indicate running from command line */
  char *tmpLics;              /**< pointer to storage for parsed names */
  char *licenseList[512];     /**< list of license names found, can be a single name */

  GArray* indexList; /**< List of license indexes */
  GArray* theMatches; /**< List of matches */
  GArray* keywordPositions; /**< List of matche positions */
  GArray* docBufferPositionsAndOffsets;
  int currentLicenceIndex;
};

/**
 * License pattern
 */
struct license {
    int len;      ///< Length of pattern
    char *patt;   ///< License pattern to use
};

/**
 * License text to information
 */
struct licensetext {
    char *regex;  ///< License regex
    char *tseed;	///< unencrypted license text
  int nAbove;
  int nBelow;
  int compiled;
  int plain;
};
typedef struct licensetext licText_t;

/**
 * Get regex of a license text
 */
#define	_REGEX(x)	licText[x].regex
/**
 * Get seed of a license text
 */
#define	_SEED(x)	licText[x].tseed

/**
 * License scan result
 */
struct scanResults {
  int score;        ///< License match score
  int kwbm;
  int size;
  int flag;         ///< Flags
  int dataOffset;
  char fullpath[myBUFSIZ];
  char linkname[16];
  char *licenses;
  char *relpath;
  size_t nameOffset;
};
typedef	struct scanResults scanres_t;

/**
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
/**
 * Miscellaneous memory tags
 */
#define	MTAG_SEEDTEXT	"search-seed text"
#define	MTAG_SRCHTEXT	"license-search text"
#define	MTAG_MMAPFILE	"mmap-file data"
#define	MTAG_MAGICDATA	"file magic description"
#define	MTAG_PATTRS	"pkg-attr buffer"
#define	MTAG_DOUBLED	"doubled (reallocated) data"
#define	MTAG_SEARCHBUF	"initial search-data buffer"
#define	MTAG_TOOSMALL	"too-small half-size buffer"
#define	MTAG_TEXTPARA	"paragraph text"
#define	MTAG_LIST	"dynamically-allocated list"
#define	MTAG_ENV	"environment variable"
#define	MTAG_SCANRES	"scan-results list"


/*
   Functions defined in nomos.c, used in other files
 */
void Bail(int exitval);
int optionIsSet(int val);

/*
  Global Declarations
 */
extern struct globals gl;
extern struct curScan cur;
extern licText_t licText[];
extern licSpec_t licSpec[];
extern int schedulerMode; /* Non-zero if being run by scheduler */

/**
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
