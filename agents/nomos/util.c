/*
 * (C) Copyright 2006 Hewlett-Packard Development Company, L.P.
 */
#include "nomos.h"
#include "_autodefs.h"
#include <stdarg.h>
#include "md5.h"

#define	MM_CACHESIZE	20

void Msg(const char *fmt, ...), Log(const char *fmt, ...),
    MsgLog(const char *fmt, ...), Fatal(const char *fmt, ...),
    Error(const char *fmt, ...), Warn(const char *fmt, ...),
    Note(const char *fmt, ...), Assert(int fatalFlag, const char *fmt, ...);
int mySystem(const char *fmt, ...),
#ifdef	OLD_STYLE
    isFILE(const char *fmt, ...),
#endif	/* OLD_STYLE */
    addEntry(char *pathname, int forceFlag, const char *fmt, ...);

#ifdef	REUSE_STATIC_MEMORY
static char grepzone[10485760];	/* 10M for now, adjust if needed */
#endif	/* REUSE_STATIC_MEMORY */
static va_list ap;
static char pathname[myBUFSIZ], utilbuf[myBUFSIZ];
static struct mm_cache mmap_data[MM_CACHESIZE];
static uid_t myUID = -1;

extern struct globals gl;
extern struct curPkg cur;
extern lic_t licText[];

static FILE *newPkgsFp, *indexFp, *licenseFp, *licHistFp, *missingFp,
    *xrefFp, *unusedFp;
static void cannotClose();
static int makeRemovePerms();

extern FILE* fopenFile(), *popenProc();
extern void Bail();
extern int lineInFile(), idxGrep();
extern char *mkdtemp(), *findEol();

void openLogFile(), closeLogFile(), openOriginsFile(),
    closeOriginsFile(), copyFile(), dumpFile(), munmapFile(), appendFile(),
    unbufferFile(), makeSymlink(), makeTempDir(), makeBaseDirs(),
    makeCustomDirs(), makeDir(), makePath(), forceRemoveDir(),
#ifdef	MEMSTATS
    memStats(),
#endif	/* MEMSTATS */
    asyncRemoveDir(), fcloseWebpage(), confidentialNote();
int fileLineCount(), bufferLineCount(), compareFiles(), nftwFileFilter(),
    fileTypeIs(), fileIsShar(), isDIR(), isBLOCK(), isCHAR(), isPIPE(),
    isSYMLINK(), isEMPTYFILE(), isINODE(), openFile();
char *fileMD5SUM(), *mmapFile();

void changeDir(), unlinkFile(), renameInode(), suPrivs();
FILE *fopenFile(), *popenProc(), *fopenWebpage();
char *findEol(), *findBol(), *pathBasename(), *curDate(), *copyString(),
    *copySnippet(), *newReloTarget();

#ifdef	MEMORY_TRACING
#define	MEMCACHESIZ	200000
static int memlast = -1;
static struct mm_cache memcache[MEMCACHESIZ];
void memCacheDump();
#endif	/* MEMORY_TRACING */

int isDIR(char *dpath)
{
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== isDIR(%s)\n", dpath);
#endif	/* PROC_TRACE */
/* */
	return(isINODE(dpath, S_IFDIR));
}

void unbufferFile(FILE *fp)
{
	(void) setbuf(fp, NULL_STR);
	return;
}

/* Q: do we need isEMPTYDIR() ?? */
int isEMPTYDIR(char *dpath)
{
	DIR *dirp;
	struct dirent *dent;
	int i = 0;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== isEMPTYDIR(%s)\n", dpath);
#endif	/* PROC_TRACE */
/* */
	if (!isDIR(dpath)) {
		return(0);
	}
	if ((dirp = opendir(dpath)) == (DIR *) NULL) {
		if (errno != ENOTDIR) {
			perror(dpath);
			Bail(1);
		}
		return(0);
	}
/*
 * quick-n-dirty brute force: look for an entry starting with ".." AND
 * an entry starting with "."; other entries == directory NOT empty.
 */
	while ((dent = readdir(dirp)) != (struct dirent *) NULL) {
		if (strcmp(dent->d_name, ".") == 0 ||
		    strcmp(dent->d_name, "..") == 0) {
			continue;
		}
		i++;
		break;
	}
	(void) closedir(dirp);
	return(i == 0);
}

int isEMPTYFILE(char *fpath)
{
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== isEMPTYFILE(%s)\n", fpath);
#endif	/* PROC_TRACE */
/* */
	if (!isFILE(fpath)) {
		return(0);
	}
	return(gl.stbuf.st_size == 0);
}

int isBLOCK(char *bpath)
{
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== isBLOCK(%s)\n", bpath);
#endif	/* PROC_TRACE */
/* */
	return(isINODE(bpath, S_IFBLK));
}

int isCHAR(char *cpath)
{
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== isCHAR(%s)\n", cpath);
#endif	/* PROC_TRACE */
/* */
	return(isINODE(cpath, S_IFCHR));
}

int isPIPE(char *ppath)
{
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== isPIPE(%s)\n", ppath);
#endif	/* PROC_TRACE */
/* */
	return(isINODE(ppath, S_IFIFO));
}

int isSYMLINK(char *spath)
{
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== isSYMLINK(%s)\n", spath);
#endif	/* PROC_TRACE */
/* */
	return(isINODE(spath, S_IFLNK));
}

int isINODE(char *ipath, int typ)
{
	int ret;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== isINODE(%s, 0x%x)\n", ipath, typ);
#endif	/* PROC_TRACE */
/* */
	if ((ret = stat(ipath, &gl.stbuf)) < 0) {
/*
 * IF we're trying to stat() a file that doesn't exist, that's no biggie.
 * Any other error, however, is fatal.
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
#if	0
	return (gl.stbuf.st_mode & typ);
	return((int)(gl.stbuf.st_mode & S_IFMT) == typ);
#endif
	return((int)(gl.stbuf.st_mode & S_IFMT & typ));
}

char *newReloTarget(char *basename)
{
	static char newpath[myBUFSIZ];
	register int i;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== newReloTarget(%s)\n", basename);
#endif	/* PROC_TRACE */
/* */
	for (i = 0; i < MAX_RENAME; i++) {
		(void) sprintf(newpath, "%s_%s-renamed.%03d", basename,
		    gl.progName, i);
		if (access(newpath, F_OK) && errno == ENOENT) {
			break;
		}
	}
	if (i == MAX_RENAME) {
		Fatal("%s: no suitable relocation target (%d tries)",
		    basename, i);
	}
	return(newpath);
}

char *pluralName(char *s, int count)
{
	static char name[128];
	register char *cp = name;
/* */
#if	0
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== pluralName(\"%s\", %d)\n", s, count);
#endif	/* PROC_TRACE */
#endif
/* */
#if	0
	(void) strcpy(name, s);
#endif
	while (*s) {
		*cp++ = *s++;
	}
	if (count != 1) {
#if	0
		(void) strcat(name, "s");
#endif
		*cp++ = 's';
	}
	*cp = NULL_CHAR;
	return(name);
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
/* */
#if	defined(PROC_TRACE) || defined(MEM_ACCT)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== memAlloc(%d, \"%s\")\n", size, name);
#endif	/* PROC_TRACE || MEM_ACCT */
/* */
	if (size < 1) {
		Fatal("Bombs away, cannot alloc %d bytes!", size);
	}
	if (++memlast == MEMCACHESIZ) {
		Fatal("*** memAlloc: out of memcache entries");
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
	printf("memAlloc(%d, \"%s\") == %p [entry %04d]\n", size, name, ptr,
	    memlast);
	/* memCacheDump("post-memAlloc:"); */
#endif	/* MEM_ACCT */
	return(ptr);
}

void memFreeTagged(void *ptr, char *note)
{
	struct mm_cache *mmp;
	int i;
/* */
#if	defined(PROC_TRACE) || defined(MEM_ACCT)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== memFree(%p, \"%s\")\n", ptr, note);
#endif	/* PROC_TRACE || MEM_ACCT */
/* */
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
#if	0
/*
 * THESE TWO FUNCTIONS ARE FOR DEBUGGING ONLY
 */
char *memAlloc(int size, char *name)
{
	register char *cp;
	extern char *calloc();
/* */
	if ((cp = calloc(size, 1)) == NULL_STR) {
		Fatal("Cannot allocate %d bytes (%s)", size, name);
	}
#if	DEBUG > 3 || defined(MEM_ACCT)
	printf("+%p (%s)\n", cp, name);
#endif	/* DEBUG > 3 || MEM_ACCT */
	return(cp);
}

void memFree(void *ptr, char *note)
{
	(void) free(ptr);
#if	DEBUG > 3 || defined(MEM_ACCT)
	printf("-%p (%s)\n", ptr, note);
#endif	/* DEBUG > 3 || MEM_ACCT */
	return;
}
#endif

char *findBol(char *s, char *upperLimit)
{
	register char *cp;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== findBol(%p, %p)\n", s, upperLimit);
#endif	/* PROC_TRACE */
/* */
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
	register char *cp;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== findEol(%p)\n", s);
#endif	/* PROC_TRACE */
/* */
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
/* */
#if	defined(PROC_TRACE) || defined(UNPACK_DEBUG) || defined(CHDIR_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== changeDir(%s)\n", pathname);
#endif	/* PROC_TRACE || UNPACK_DEBUG || CHDIR_DEBUG */
/* */
	if (chdir(pathname) < 0) {
		perror(pathname);
		Fatal("chdir(\"%s\") fails", pathname);
	}
	if (getcwd(gl.cwd, sizeof(gl.cwd)) == NULL_STR) {
		perror("getcwd(changeDir)");
		Bail(1);
	}
	gl.cwdLen = (int) strlen(gl.cwd);
#ifdef	CHDIR_DEBUG
	printf("DEBUG: now in \"%s\"\n", gl.cwd);
#endif	/* CHDIR_DEBUG */
	return;
}

void renameInode(char *oldpath, char *newpath)
{
	int err = 0;
/*
 * we die here if the unlink() fails.
 */
/* */
#if defined(PROC_TRACE) || defined(UNPACK_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== renameInode(%s, %s)\n", oldpath, newpath);
#endif	/* PROC_TRACE || UNPACK_DEBUG */
/* */
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
/* */
#if defined(PROC_TRACE) || defined(UNPACK_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== unlinkFile(%s)\n", pathname);
#endif	/* PROC_TRACE || UNPACK_DEBUG */
/* */
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
/* */
#if defined(PROC_TRACE) || defined(UNPACK_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== chmodInode(%s, 0%o)\n", pathname, mode);
#endif	/* PROC_TRACE || UNPACK_DEBUG */
/* */
	if (chmod(pathname, mode) < 0) {
		perror(pathname);
		Fatal("chmod(\"%s\", 0%o) fails", pathname, mode);
	}
	return;
}

FILE *fopenWebpage(char *pathname, char *mode, char *title)
{
	FILE *fp;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== fopenWebpage(%s, \"%s\", %s)\n", pathname, mode, title);
#endif	/* PROC_TRACE */
/* */
	fp = fopenFile(pathname, mode);
	fprintf(fp, "<HTML>\n<HEAD>\n<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=iso-8859-1\">\n");
	fprintf(fp, "<META NAME=\"Description\" CONTENT=\"Confidential data, generated from %s\">\n",
	    gl.progName);
	fprintf(fp, "<LINK rel=\"stylesheet\" href=\"../../ext/styles.css\" type=\"text/css\">\n");
/* */
	fprintf(fp, "<TITLE>%s</TITLE>\n", title ? title : "Un-named page");
/* */
	fprintf(fp, "</HEAD>\n<BODY>\n<H3>** %s **</H3>\n", CONFIDENTIAL);
	fprintf(fp, "<p class=\"confidential\">\n<table border=2>\n<tr><td><B>\n");
	confidentialNote(fp, NO);
	fprintf(fp, "</B></td></tr></table>\n</p><br>");
	return(fp);
}

void confidentialNote(FILE *fp, int textonly)
{
	if (textonly) {
		fprintf(fp, "** %s **\n\n", CONFIDENTIAL);
		fprintf(fp, "\t--- CONFIDENTIALITY NOTE ---\n");
	}
#if	0
/*
 * At The Hartford, this was modified to be specific to customer agreements:
 */
	fprintf(fp, "The contents of this %s are considered %s\n",
	    textonly ? "file" : "web page", CONFIDENTIAL);
	fprintf(fp, "and are subject to the terms of the Non-Disclosure\n");
	fprintf(fp, "Agreements (NDAs) and Software Licenses in effect\n");
	fprintf(fp, "between HP and The Hartford\n");
#endif
	fprintf(fp, "The contents of this %s may not be shared with\n",
	    textonly ? "file" : "web page");
	fprintf(fp, "a non-HP employee or company without the express\n");
	fprintf(fp, "written approval of the HP Open Source Review\n");
	fprintf(fp, "Board (OSRB)\n");
	if (textonly) {
		fprintf(fp, "\t\t--- END ---\n\n");
	}
	return;
}

void fcloseWebpage(FILE *fp)
{
	fprintf(fp, "</BODY>\n</HTML>\n");
	(void) fclose(fp);
	return;
}

FILE *fopenFile(char *pathname, char *mode)
{
	FILE *fp;
	extern FILE *fopen();
/*
 * we don't track directories opened; we front-end and return what's
 * given to us.  we die here if the fopen() fails.
 */
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== fopenFile(%s, \"%s\")\n", pathname, mode);
#endif	/* PROC_TRACE */
/* */
 	if ((fp = fopen(pathname, mode)) == (FILE *) NULL) {
		perror(pathname);
		Fatal("fopen(%s) fails", pathname);
	}
	return(fp);
}

FILE *popenProc(char *command, char *mode)
{
	FILE *pp;
	extern FILE *popen();
/*
 * we don't track directories opened; we front-end and return what's
 * given to us.  we die here if the popen() fails.
 */
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== popenProc(\"%s\", %s)\n", command, mode);
#endif	/* PROC_TRACE */
/* */
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
	register int words, lines, inword;
	register char *cp;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== wordCount(%p)\n", textp);
#endif	/* PROC_TRACE */
/* */
	for (words = lines = inword = 0, cp = textp; /*(int)*/ *cp; cp++) {
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

#if	0
char *copySnippet(char *s, char *start, char *end, char *label)
{
	register char *cp;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== copySnippet(%p, %p, %p, \"%s\")\n", s, start, end, label);
#endif	/* PROC_TRACE */
/* */
	cp = memAlloc((int)(end-start+1), label);
	(void) strncpy(cp, s, (size_t)(end-start));
	return(cp);
}
#endif

char *copyString(char *s, char *label)
{
	register char *cp;
	register int len;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== copyString(%p, \"%s\")\n", s, label);
#endif	/* PROC_TRACE */
/* */
	cp = memAlloc(len=(strlen(s)+1), label);
#ifdef	DEBUG
	printf("+CS: %d @ %p\n", len, cp);
#endif	/* DEBUG */
	(void) strcpy(cp, s);
	return(cp);
}

char *pathBasename(char *path)
{
	register char *cp;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== pathBasename(\"%s\")\n", path);
#endif	/* PROC_TRACE */
/* */
	cp = strrchr(path, '/');
	return(cp == NULL_STR ? path : (char *)(cp+1));
}

char *getInstances(char *textp, int size, int nBefore, int nAfter, char *regex,
    int recordOffsets)
{
	int i, notDone, buflen = 1;
	static char *ibuf = NULL_STR;
	static int bufmax = 0;
	char *sep = _REGEX(_UTIL_XYZZY);
#ifdef	SHOW_LOCATION
	register item_t *p, *bp;
	extern item_t *listIterate();
#endif	/* SHOW_LOCATION */
	register char *fileeof, *start, *end, *curptr, *bufmark, save, *cp;
	register int newDataLen, regexFlags = REG_ICASE|REG_EXTENDED;
	extern item_t *listGetItem();
/* */
#if defined(PROC_TRACE) || defined(PHRASE_DEBUG) || defined(DOCTOR_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== getInstances(%p, %d, %d, %d, \"%s\", %d)\n", textp, size,
	    nBefore, nAfter, regex, recordOffsets);
#endif	/* PROC_TRACE || PHRASE_DEBUG || DOCTOR_DEBUG */
/* */
	if ((notDone = strGrep(regex, textp, regexFlags)) == 0) {
#ifdef	PHRASE_DEBUG
		printf("... no match: 1st strGrep()\n");
#endif	/* PHRASE_DEBUG */
		return(NULL_STR);
	}
#ifdef	SHOW_LOCATION
/*
 * The global 'offsets list' is indexed by the seed/key (a regex) that we
 * use for doctoring buffers... each entry will contain a list (containing
 * the "paragraphs" that match the key) AND its size (e.g., # of 'chunks'),
 * which also means, if there are N chunks, there are N-1 'xyzzy' separators.
 */
	p = listGetItem(&gl.offList, regex);
	p->seqNo = gl.offList.used;
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
#endif	/* SHOW_LOCATION */
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
	    gl.regm.rm_so, gl.regm.rm_eo-1);
	printf("Really in the buffer: [");
	for (cp = textp+gl.regm.rm_so; cp < textp+gl.regm.rm_eo; cp++) {
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
#ifdef	SHOW_LOCATION
		p->nMatch++;
#ifdef	PHRASE_DEBUG
		printf("... found Match #%d\n", p->nMatch);
#endif	/* PHRASE_DEBUG */
		if (recordOffsets) {
			(void) sprintf(utilbuf, "buf%05d", p->nMatch);
			bp = listGetItem(p->bList, utilbuf);
		}
#endif	/* SHOW_LOCATION */
		start = findBol(curptr+gl.regm.rm_so, textp);
/*
 * Go to the beggining of the current line and, if nBefore > 0, go 'up'
 * in the text "$nBefore" lines.  Count 2-consecutive EOL-chars as one
 * line since some text files use <CR><LF> as line-terminators.
 */
		if (nBefore > 0 && start > textp) {
			for (i = 0; i < nBefore && start > textp; i++) {
				start -= 2;
				if (start > textp && isEOL(*start)) {
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
#ifdef	SHOW_LOCATION
		if (recordOffsets) {
			bp->bStart = start-textp;
		}
#endif	/* SHOW_LOCATION */
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
			curptr += gl.regm.rm_eo;
			if ((end = findEol(curptr)) < fileeof) {
				end++;	/* first char past end-of-line */
			}
			if (nAfter > 0) {
				for (i = 0; end < fileeof; end++) {
					if (isEOL(*end)) {	/* double-EOL */
						end++;		/* <CR><LF>? */
					}
					if ((end = findEol(end)) == NULL_STR) {
						Fatal("lost the end-of-line");
					}
					if (*end == NULL_CHAR) {
						break;	/* EOF == done */
					}
					if (++i == nAfter) {
						break;
					}
				}
				if (end < fileeof && *end) {
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
				    curptr-textp+gl.regm.rm_so,
				    curptr-textp+gl.regm.rm_eo-1, end-textp);
#endif	/* PHRASE_DEBUG */
#ifdef	QA_CHECKS
				if (curptr+gl.regm.rm_eo > fileeof) {
					Assert(YES, "Too far into file!");
				}
#endif	/* QA_CHECKS */
/* next match OUTSIDE the text we've already saved? */
				if (curptr+gl.regm.rm_eo > end) {
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
#ifdef	SHOW_LOCATION
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
#endif	/* SHOW_LOCATION */
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
#if	0
#ifdef	SHOW_LOCATION
	printf("\"%s\": matches == %d\n", p->str, p->nMatch);
	/*listDump(&gl.offList, YES);*/
#endif	/* SHOW_LOCATION */
#endif
#if	defined(PHRASE_DEBUG) || defined(DOCTOR_DEBUG)
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
	register char *cp;
	time_t thyme;

	(void) time(&thyme);
	(void) ctime_r(&thyme, datebuf);
	if ((cp = strrchr(datebuf, '\n')) == NULL_STR) {
		Fatal("Unexpected time format from ctime_r()!");
	}
	*cp = NULL_CHAR;
	return(datebuf);
}

void suPrivs(int on)
{
/* */
#if defined(PROC_TRACE) || defined(UNPACK_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== suPrivs(%d)\n", on);
#endif	/* PROC_TRACE || UNPACK_DEBUG */
/* */
	if (getresuid(&gl.uid, &gl.euid, &gl.suid)) {
		Fatal("%s: Cannot re-determine UIDs", gl.progName);
	}
	if (on) {	/* turn privs on */
		if (gl.euid == 0) {
#ifdef	UNPACK_DEBUG
			Assert(NO, "suPrivs(%d): already root!", on);
#endif	/* UNPACK_DEBUG */
			return;
		}
		myUID = gl.uid;
		if (setresuid(gl.suid, gl.suid, -1)) {
			Fatal("Cannot obtain root-priledges");
		}
	}
	else {		/* turn privs off */
		if (myUID) {
			(void) setresuid(myUID, myUID, -1);
		}
		myUID = -1;
	}
#ifdef	UNPACK_DEBUG
	printf("DEBUG: suPrivs(%d): NOW - uid %d euid %d\n", on,
	    getuid(), geteuid());
#endif	/* UNPACK_DEBUG */
	return;
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

static void cannotClose(char *descr)
{
	fprintf(stderr, "OOPS: cannot close %s file - not open\n", descr);
	return;
}

void openLogFile()
{
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== openLogFile()\n");
#endif	/* PROC_TRACE */
	gl.logFp = fopenFile(gl.logfile, "w+");
	confidentialNote(gl.logFp, YES);
	unbufferFile(gl.logFp);
	return;
}

void closeLogFile()
{
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== closeLogFile()\n");
#endif	/* PROC_TRACE */
	if (gl.logFp == (FILE *) NULL) {
		cannotClose("log");
	}
	else {
		(void) fclose(gl.logFp);
	}
	return;
}

void makeSymlink(char *path)
{
#if defined(PROC_TRACE) || defined(UNPACK_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== makeSymlink(%s)\n", path);
#endif	/* PROC_TRACE || UNPACK_DEBUG */
	(void) sprintf(gl.cmdBuf, ".%s", strrchr(path, '/'));
	if (symlink(path, gl.cmdBuf) < 0) {
		perror(gl.cmdBuf);
		Fatal("Failed: symlink(%s, %s)", path, gl.cmdBuf);
	}
	return;
}

int fileTypeIs(char *pathname, int index, char *magicData)
{
/* */
#if defined(PROC_TRACE) || defined(UNPACK_DEBUG)
	extern lic_t licText[];
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch) {
#endif	/* PROC_TRACE_SWITCH */
	printf("== fileTypeIs(%s, %d, \"%s\")\n", pathname, index,
	    magicData);
#ifndef	UNPACK_DEBUG
	printf("... regex is \"%s\"\n", _REGEX(index));
#endif	/* not UNPACK_DEBUG */
#ifdef	PROC_TRACE_SWITCH
    }
#endif	/* PROC_TRACE_SWITCH */
#endif	/* PROC_TRACE || UNPACK_DEBUG */
/* */
	if (idxGrep(index, magicData, REG_ICASE|REG_EXTENDED)) {
		return(1);
	}
	return(0);
}

int fileIsShar(char *textp, char *magicData)
{
/* */
#if defined(PROC_TRACE) || defined(UNPACK_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== fileIsShar(%p, \"%s\")\n", textp, magicData);
#endif	/* PROC_TRACE || UNPACK_DEBUG */
/* */
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

void printRegexMatch(int n, int cached)
{
	register int i, save_so, save_eo, match;
	static char debugStr[256];
	static char misc[64];
	register char *cp, *x = NULL_STR, *textp;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== printRegexMatch(%d, %d)\n", n, cached);
#endif	/* PROC_TRACE */
/* */
	if (*debugStr == NULL_CHAR) {
		(void) sprintf(debugStr, "%s/Nomos.strings.txt", gl.initwd);
#ifdef	DEBUG
		printf("File: %s\n", debugStr);
#endif	/* DEBUG */
	}
	save_so = gl.regm.rm_so;
	save_eo = gl.regm.rm_eo;
	if (isFILE(debugStr)) {
		if (match = (gl.flags & FL_SAVEBASE)) {
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
			    gl.regm.rm_so, gl.regm.rm_eo);
#endif	/* DEBUG */
			x = cp = textp+gl.regm.rm_so;
			*x = NULL_CHAR;
			while (*--x != '[') {
				if (x == textp) {
					Fatal("Cannot locate debug symbol");
				}
			}
			(void) strncpy(misc, ++x, cp-x);
			misc[cp-x] = NULL_CHAR;
		}
		else {
			(void) strcpy(misc, "?");
		}
		munmapFile(textp);
		if (match) {
			gl.flags |= FL_SAVEBASE;
		}
#ifdef	DEBUG
		printf("RESTR [%d:%d]\n", gl.regm.rm_so, gl.regm.rm_eo);
#endif	/* DEBUG */
	}
	gl.regm.rm_so = save_so;
	gl.regm.rm_eo = save_eo;
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
/*@null@*/ char *mmapFile(char *pathname)	/* read-only for now */
{
	register struct mm_cache *mmp;
	static int first = 1;
	register int i, n, rem;
	register char *cp;
	void mmapOpenListing();
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== mmapFile(%s)\n", pathname);
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
	for (mmp = mmap_data, i = 0; i < MM_CACHESIZE; i++, mmp++) {
		if (mmp->inUse == 0) {
			break;
		}
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
	if (fstat(mmp->fd, &gl.stbuf) < 0) {
		fprintf(stderr, "fstat failure!\n");
		perror(pathname);
		Bail(1);
	}
	if (S_ISDIR(gl.stbuf.st_mode)) {
		fprintf(stderr, "mmapFile(%s): is a directory\n", pathname);
		Bail(1);
	}
	(void) strcpy(mmp->label, pathname);
	if (gl.stbuf.st_size) {
#ifdef	USE_MMAP
/*
 * mmap() is known to return NULL if the size of a MAP_PRIVATE region is
 * initially specified as zero
 */
		mmp->size = gl.stbuf.st_size;
#if	0
		if ((mmp->size % gl.pagesize) == 0) {
			printf("*** %d is an exact multiple of %d\n",
			    mmp->size, gl.pagesize);
			if (lseek(mmp->fd, SEEK_END, 1) < 0) {
				perror("pathname");
				Bail(1);
			}
			if (write(mmp->fd, " ", 1) < 0) {
				perror("pathname");
				Bail(1);
			}
			mmp->size++;
			gl.stbuf.st_size++;
		}
#endif
		mmp->mmPtr = mmap((void *)0, (size_t) mmp->size, PROT_READ,
		    MAP_PRIVATE, mmp->fd, (off_t) 0);
		if (mmp->mmPtr != MAP_FAILED) {
			mmp->inUse = 1;
			return((char *) mmp->mmPtr);
		}
#else	/* not USE_MMAP */
		mmp->size = gl.stbuf.st_size+1;
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
#endif	/* not USE_MMAP */
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
	register struct mm_cache *mmp;
	register int i;

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
	register struct mm_cache *mmp;
	register int i;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== munmapFile(%p)\n", ptr);
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

#if	0
int MmapPtr2Fd(void *ptr)
{
	struct mm_cache *mmp;
	int i;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== MmapPtr2Fd(%p)\n", ptr);
#endif	/* PROC_TRACE */
	for (mmp = mmap_data, i = 0; i < MM_CACHESIZE; i++) {
		if (mmp->mmPtr == ptr) {
			return(mmp->fd);
		}
	}
	return(-1);
}
#endif

int fileLineCount(char *pathname)
{
	register char *filep;
	register int i;
/*
 * We need the file size to know when we're at the end-of-file.
 *****
 * When we call mmapFile(), we automatically get a stat(2) structure
 * populated for the file we're opening, so we can get the file size
 * from there.  Maybe that's kludgy, and maybe it's 'efficient design'. 
 */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== fileLineCount(%s)\n", pathname);
#endif	/* PROC_TRACE */
	if ((filep = mmapFile(pathname)) == NULL_STR) {
		return(0);
	}
	i = bufferLineCount(filep, gl.stbuf.st_size);
	munmapFile(filep);
	return(i);
}

char *fileMD5SUM(char *pathname)
{
	static char md5buf[32], *hexDigits = "0123456789abcdef";
	static unsigned char MD5digest[16];
	register char *textp, *cp;
	register int i;
	MD5_CTX md5;	/* thanks, Neal */
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== fileMD5SUM(%s)\n", pathname);
#endif	/* PROC_TRACE */
	textp = mmapFile(pathname);
	/* gl.stbuf.st_size is the # of bytes after mmapFile() */
	/* md5sum goes into buf */
	MD5_Init(&md5);
	MD5_Update(&md5, textp, gl.stbuf.st_size);
	MD5_Final(MD5digest,&md5);
	for (i = 0, cp = md5buf; i < 16; i++) {
		*cp++ = hexDigits[(MD5digest[i] & 0xf0) >> 4];
		*cp++ = hexDigits[MD5digest[i] & 0x0f];
	}
	*cp = NULL_CHAR;
	munmapFile(textp);
#ifdef	DEBUG
	Assert(NO, "Md5(%s): %s\n", pathname, md5buf);
#endif	/* DEBUG */
	return(md5buf);
}

int bufferLineCount(char *p, int len)
{
	register char *cp, *eofaddr;
	register int i;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== bufferLineCount(%p, %d)\n", p, len);
#endif	/* PROC_TRACE */
/* */
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
 * Currently we don't use this (anymore).  If we do and we're NOT using
 * real mmap() support, we should just mmapFile() the 'frompath' file,
 * fopenFile() the 'topath' file, do a printf("%s") and be done.
 */
#ifdef	USE_MMAP
void copyFile(char *frompath, char *topath)
{
	register char *readp, *rp, *writep, *wp;
	register int fd, size, i;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== copyFile(%s, %s)\n", frompath, topath);
#endif	/* PROC_TRACE */
	if ((readp = mmapFile(frompath)) == NULL_STR) {
		if (errno != ENOENT) {	/* zero length file */
			(void) close(creat(topath, gl.stbuf.st_mode));
			return;
		}
		perror(frompath);
		fprintf(stderr, "Cannot copy %s to %s\n", frompath, topath);
		Bail(1);
	}
	size = (int) gl.stbuf.st_size;
	if ((fd = open(topath, O_RDWR|O_CREAT|O_TRUNC, 0644)) < 0) {
		perror(topath);
		Bail(1);
	}
/*
 * lseek to $size-1 and then write 1 byte; resulting length is $size
 */
	if (lseek(fd, size-1, SEEK_SET) < 0) {
		perror("lseek: copy-target");
		Bail(1);
	}
	if (write(fd, readp, 1) < 0) {
		perror("write: copy-target");
		Bail(1);
	}
/*
 * Now write the new file with mmap
 */
	writep = (char *) mmap(0, (size_t) size, PROT_WRITE, MAP_SHARED, fd, 0);
	if (writep == MAP_FAILED) {
	    	perror("mmap: open copy-target");
		Bail(1);
	}
	for (i = 0, rp = readp, wp = writep; i < size; i++) {
		*wp++ = *rp++;
	}
/*
 * close what we opened 
 */
	munmapFile(readp);
	if (munmap(writep, (size_t) size) < 0) {
		fprintf(stderr, "Error in munmap()\n");
		perror(topath);
		Bail(1);
	}
	(void) close(fd);
	return;
}
#endif	/* USE_MMAP */

/*
 * like strcmp(), but operate on contents of files
 */
int fileCompare(char *f1, char *f2)
{
	register int size1, size2, ret;
	register char *base1, *base2;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== fileCompare(%s, %s)\n", f1, f2);
#endif	/* PROC_TRACE */
/* */
	base1 = mmapFile(f1);
	size1 = (int) gl.stbuf.st_size;
	base2 = mmapFile(f2);
	size2 = (int) gl.stbuf.st_size;
	if (size1 == 0 && size2 == 0) {
		ret = 0;
	}
	else if (size1 < size2) {
		ret = -1;
	}
	else if (size1 > size2) {
		ret = 1;
	}
	else {
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
	register FILE *fp;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== appendFile(%s, \"%s\")\n", pathname, str);
#endif	/* PROC_TRACE */
/* */
	fp = fopenFile(pathname, "a+");
	fprintf(fp, "%s\n", str);
	(void) fclose(fp);
	return;
}

void dumpFile(FILE *fp, char *pathname, int logFlag)
{
	register char *filep;
/*
 * Dump the contents of a file -- this utility MAY go away later!
 */
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== dumpFile(%p, %s, %d)\n", fp, pathname, logFlag);
#endif	/* PROC_TRACE */
/* */
	if (!isFILE(pathname)) {
#ifdef	DEBUG
		printf("%s: NO SUCH FILE\n", pathname);
#endif	/* DEBUG */
		perror(pathname);
		return;
	}
	if (gl.stbuf.st_size == 0) {
		printf("%s: EMPTY\n", pathname);
		return;
	}
	if ((filep = mmapFile(pathname)) == NULL_STR) {
		return;
	}
	fprintf(fp, "%s", filep);
	if (logFlag) {
		Log("%s", filep);
	}
	munmapFile(filep);
	return;
}

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
	register int ret = 0;
/* */
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
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== nftwFileFilter(\"%s\", %p)\n", pathname, st);
#endif	/* PROC_TRACE || UNPACK_DEBUG */
/* */
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
	if (optionIsNotSet(OPTS_SRCONLY) && IS_HUGE(st->st_blocks)) {
#ifdef	UNPACK_DEBUG
		printf("*** huge file %s (%d blocks)\n", pathname,
		    st->st_blocks);
#endif	/* UNPACK_DEBUG */
		ret = 1;
	}
	if (ret && *pathname != '/') {
		unlinkFile(pathname);
	}
	return(ret);
}

void makeTempDir()
{
#if	defined(PROC_TRACE) || defined(UNPACK_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== makeTempDir()\n");
#endif	/* PROC_TRACE || UNPACK_DEBUG */
	(void) strcpy(gl.tmpdir, "/tmp/pkgXXXXXX");
	if (mkdtemp(gl.tmpdir) != gl.tmpdir) {
		perror("mkdtemp");
		Bail(1);
	}
#ifdef	UNPACK_DEBUG
	printf("** mkdtemp() set dirname to \"%s\"\n", gl.tmpdir);
#endif	/* UNPACK_DEBUG */
}

void makeBaseDirs(char *base)
{
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== makeBaseDirs(%s)\n", base);
#endif	/* PROC_TRACE */
	makePath(base);
/*
 * While at The Hartford, it became apparent that parsing distributions is
 * likely one thing we don't want to support, so there's really no need to
 * create the directories for those reports -- is there?
 */
#ifdef	HP_INTERNAL
	(void) sprintf(pathname, "%s/reports/dist", base);
	makePath(pathname);
	(void) sprintf(pathname, "%s/reports/lists", base);
	makeDir(pathname);
	(void) sprintf(pathname, "%s/reports/recur", base);
	makeDir(pathname);
#endif	/* HP_INTERNAL */
	(void) sprintf(pathname, "%s/reports/src", base);
	makePath(pathname);
	(void) sprintf(pathname, "%s/reports/web", base);
	makeDir(pathname);
	(void) sprintf(pathname, "%s/db/Good", base);
	makePath(pathname);
	(void) sprintf(pathname, "%s/db/XREF", base);
	makeDir(pathname);
	(void) sprintf(pathname, "%s/db/PATHS", base);
	makeDir(pathname);
	(void) sprintf(pathname, "%s/db/INDEX", base);
	makeDir(pathname);
	(void) sprintf(pathname, "%s/db/MISSING", base);
	makeDir(pathname);
	(void) sprintf(pathname, "%s/db/CORRUPT", base);
	makeDir(pathname);
	(void) sprintf(pathname, "%s/db/UNUSED", base);
	makeDir(pathname);
	(void) sprintf(pathname, "%s/db/DUPLICATE", base);
	makeDir(pathname);
	(void) sprintf(pathname, "%s/db/NESTED", base);
	makeDir(pathname);
	(void) sprintf(pathname, "%s/db/ORIGINS", base);
	makeDir(pathname);
	(void) sprintf(pathname, "%s/db/Packages", base);
	makeDir(pathname);
	(void) sprintf(pathname, "%s/db/Archives", base);
	makeDir(pathname);
	(void) sprintf(pathname, "%s/db/LICENSES/CLAIM", base);
	makePath(pathname);
	(void) sprintf(pathname, "%s/db/LICENSES/FOUND", base);
	makeDir(pathname);
	(void) sprintf(pathname, "%s/db/LICENSES/HIST/CLAIM", base);
	makePath(pathname);
	(void) sprintf(pathname, "%s/db/LICENSES/HIST/FOUND", base);
	makeDir(pathname);
	(void) sprintf(pathname, "%s/db/LICENSES/HIST/INVENTORY", base);
	makeDir(pathname);
	(void) sprintf(pathname, "%s/licenses/LOG", base);
	makePath(pathname);
#ifdef  SAVE_REFLICENSES
	(void) sprintf(gl.refLicDir, "%s/licenses/REFERENCE", base);
	makeDir(gl.refLicDir);
#endif  /* SAVE_REFLICENSES */
	(void) sprintf(pathname, "%s/pen", base);
	makeDir(pathname);
	(void) sprintf(pathname, "%s/ext", base);
	makePath(pathname);
#ifdef	HP_INTERNAL
	if (optionIsNotSet(OPTS_NOREPORT) && !isFILE(gl.bSpec)) {
		Fatal("Missing %s", gl.bSpec);
	}
#endif	/* HP_INTERNAL */
	return;
}

void makeCustomDirs(char *base)
{
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== makeCustomDirs(%s)\n", base);
#endif	/* PROC_TRACE */
/* 
 * If we're only processing a single source, it's a much smaller list of
 * directories we have to make.
 */
	makeDir(gl.mntpath);	/* i.e., NOT makePath() */
	if (optionIsSet(OPTS_SRCONLY)) {
		(void) sprintf(pathname, "%s/licenses/%s", base, MISC_SRCDIR);
		makeDir(pathname);
		return;
	}
	if (gl.prodName[0] == NULL_CHAR || gl.prod[0] == NULL_CHAR) {
		Fatal("makeCustomDirs() cannot determine product!");
	}
	(void) sprintf(pathname, "%s/db/%s/%s/%s", base, gl.vendor, gl.prod,
	    gl.arch);
	makePath(pathname);
	(void) sprintf(pathname, "%s/licenses/%s", base, gl.vendor);
	makeDir(pathname);
	(void) sprintf(pathname, "%s/licenses/%s/%s", base, MISC_FDIR, gl.prod);
	makePath(pathname);
	(void) sprintf(gl.penDir, "%s/pen/%s/%s", base, gl.vendor, gl.prodName);
	makePath(gl.penDir);
	gl.pendirLen = strlen(gl.penDir);
	(void) sprintf(pathname, "%s/huge", gl.penDir);
	makeDir(pathname);
	(void) sprintf(pathname, "%s/nested", gl.penDir);
	makeDir(pathname);
	return;
}

void makePath(char *dirpath)	/* e.g., the command "mkdir -p" */
{
	char *cp = dirpath, *last = NULL_STR;
	static int baseLen, first = 1;
	static char base[256];
#if	defined(PROC_TRACE) || defined(UNPACK_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== makePath(%s)\n", dirpath);
#endif	/* PROC_TRACE || UNPACK_DEBUG */
	if (isDIR(dirpath)) {
#if	(DEBUG > 1)
		printf("makePath: \"%s\" exists\n", cp);
#endif	/* DEBUG > 1 */
		return;
	}
/*
 * Local optimization: if the pathname STARTS with the value of
 * NOMOS_BASE *plus* an appended '/', we can skip that.  We know it
 * exists, because we create it the FIRST time we're called here.
 */
	if (first) {
		if ((baseLen = strlen(gl.basedir)+1) > sizeof(base)) {
			Fatal("makePath: base storage too small");
		}
		(void) sprintf(base, "%s/", gl.basedir);
		first = 0;
	}
	while (*cp && *cp == '/') {
		last = cp++;
	}
	if (last != NULL_STR && strncmp(last, base, baseLen) == 0) {
		cp += baseLen;
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
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== makeDir(%s)\n", dirpath);
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

/*
 * Based on the design of how sources are unpacked (e.g., into the local
 * subdirectory "raw/" AND the desire to have to NOT wait for a recursive
 * directory removed, there's a race condition that can be fixed by using
 * the following rule:
 ***** (Rule) *****
 * Temp-directories CREATED and REMOVED must have different names (e.g.,
 * move the temp-dir before you delete it) IF deleting temp-dirs is to be
 * done asyncrhonously.
 *****
 * FIX-ME: there are at least 2 routines that look for 'unused inode names'
 * now (here and in sources.c) -- we should write a general-purpose function
 * to search for an 'available' name.
 */
void asyncRemoveDir(char *dir)
{
	char *new;
	register int i;
	extern char *newReloTarget();
/* */
#if	defined(PROC_TRACE) || defined(UNPACK_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== asyncRemoveDir(%s)\n", dir);
#endif	/* PROC_TRACE || UNPACK_DEBUG */
/* */
	new = newReloTarget("xyzzy");
	renameInode(dir, new);
	forceRemoveDir(new, YES);
	return;
}

void forceRemoveDir(char *dir, int async)
{
/* */
#if	defined(PROC_TRACE) || defined(UNPACK_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== forceRemoveDir(%s, %d)\n", dir, async);
#endif	/* PROC_TRACE || UNPACK_DEBUG */
/* */
	if (getuid()) {
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
	    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
		printf("=> invoking nftw:makeRemovePerms(%s)\n", dir);
#endif	/* PROC_TRACE */
		(void) nftw(dir, (__nftw_func_t) makeRemovePerms, 100,
		    FTW_PHYS);
	}
	if (mySystem("rm -rf %s %c", dir, async ? '&' : NULL_CHAR)) {
		Fatal("Cannot clean %s", dir);
	}
	return;
}

static int makeRemovePerms(char *pathname, struct stat *st, int flag,
    struct FTW *s)
{
	char *cp, magicData[myBUFSIZ];
/* */
	if (st->st_mode & S_IFDIR) {
		chmodInode(pathname, 0755);
	}
	return(0);
}

int mySystem(const char *fmt, ...)
{
	int ret;
	va_start(ap, fmt);
	(void) vsprintf(gl.cmdBuf, fmt, ap);
	va_end(ap);
/* */
#if defined(PROC_TRACE) || defined(UNPACK_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== mySystem('%s')\n", gl.cmdBuf);
#endif  /* PROC_TRACE || UNPACK_DEBUG */
/* */
	ret = system(gl.cmdBuf);
	if (WIFEXITED(ret)) {
		ret = WEXITSTATUS(ret);
#ifdef	DEBUG
		if (ret) {
			Error("system(%s) returns %d", gl.cmdBuf, ret);
		}
#endif	/* DEBUG */
	}
	else if (WIFSIGNALED(ret)) {
		ret = WTERMSIG(ret);
		Error("system(%s) died from signal %d", gl.cmdBuf, ret);
	}
	else if (WIFSTOPPED(ret)) {
		ret = WSTOPSIG(ret);
		Error("system(%s) stopped, signal %d", gl.cmdBuf, ret);
	}
#if	0
	if (ret && isFILE(UNPACK_STDERR) && (int) gl.stbuf.st_size > 0) {
		dumpFile(stderr, UNPACK_STDERR, YES);
	}
#endif
	return(ret);
}

int isFILE(char *pathname)
{
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== isFILE(%s)\n", pathname);
#endif	/* PROC_TRACE */
/* */
	return(isINODE(pathname, S_IFREG));
}

#ifdef	OLD_STYLE
int isFILE(const char *fmt, ...)
{
	va_start(ap, fmt);
	(void) vsprintf(utilbuf, fmt, ap);
	va_end(ap);
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== isFILE(%s)\n", utilbuf);
#endif	/* PROC_TRACE */
/* */
	return(isINODE(utilbuf, S_IFREG));
}
#endif	/* OLD_STYLE */

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
/* */
#ifdef  PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== addEntry(%s, %d, \"%s\")\n", pathname, forceFlag, utilbuf);
#endif  /* PROC_TRACE */
/* */
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

/*
 * DO NOT automatically add \n to a string passed to Log(); in
 * parseDistro, we sometimes want to dump a partial line.
 */
void Log(const char *fmt, ...)
{
	if (gl.logFp != (FILE *) NULL) {
		va_start(ap, fmt);
		(void) vfprintf(gl.logFp, fmt, ap);
		va_end(ap);
	}
	return;
}

/*
 * DO NOT automatically add \n to a string passed to MsgLog(); in
 * parseDistro, we sometimes want to dump a partial line.
 */
void MsgLog(const char *fmt, ...)
{
	va_start(ap, fmt);
	(void) vsprintf(utilbuf, fmt, ap);
	va_end(ap);
	Msg("%s", utilbuf);
	Log("%s", utilbuf);
	return;
}

/*
 * NOTE statements get logged to stdout and to the logfile
 */
void Note(const char *fmt, ...)
{
	va_start(ap, fmt);
	(void) sprintf(utilbuf, "NOTE: ");
	(void) vsprintf(utilbuf+strlen(utilbuf), fmt, ap);
	va_end(ap);
/* */
#ifdef  PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== Warn(\"%s\")\n", utilbuf);
#endif  /* PROC_TRACE */
/* */
	(void) strcat(utilbuf, "\n");
	MsgLog("%s", utilbuf);
	return;
}

/*
 * WARNING statements get logged to stdout and to the logfile
 */
void Warn(const char *fmt, ...)
{
	va_start(ap, fmt);
	(void) sprintf(utilbuf, "WARNING: ");
	(void) vsprintf(utilbuf+strlen(utilbuf), fmt, ap);
	va_end(ap);
/* */
#ifdef  PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== Warn(\"%s\")\n", utilbuf);
#endif  /* PROC_TRACE */
/* */
	(void) strcat(utilbuf, "\n");
	MsgLog("%s", utilbuf);
	return;
}

/*
 * ASSERT statements get logged to stdout and to the logfile
 */
void Assert(int fatalFlag, const char *fmt, ...)
{
	va_start(ap, fmt);
	(void) sprintf(utilbuf, "ASSERT: ");
	(void) vsprintf(utilbuf+strlen(utilbuf), fmt, ap);
	va_end(ap);
/* */
#ifdef  PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("!! Assert(\"%s\")\n", utilbuf+strlen(gl.progName)+3);
#endif  /* PROC_TRACE */
/* */
	(void) strcat(utilbuf, "\n");
	MsgLog("%s", utilbuf);
	if (fatalFlag) {
		Bail(1);
	}
	return;
}

/*
 * ERROR statements get logged to stdout and to the logfile
 */
void Error(const char *fmt, ...)
{
	va_start(ap, fmt);
	(void) sprintf(utilbuf, "ERROR: ");
	(void) vsprintf(utilbuf+strlen(utilbuf), fmt, ap);
	va_end(ap);
/* */
#ifdef  PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== Error(\"%s\")\n");
#endif  /* PROC_TRACE */
/* */
	(void) strcat(utilbuf, "\n");
	MsgLog("%s", utilbuf);
	return;
}

/*
 * FATAL statements get logged to stdout and to the logfile
 */
void Fatal(const char *fmt, ...)
{
	va_start(ap, fmt);
	(void) sprintf(utilbuf, "%s: FATAL: ", gl.progName);
	(void) vsprintf(utilbuf+strlen(utilbuf), fmt, ap);
	va_end(ap);
/* */
#ifdef  PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("!! Fatal(\"%s\")\n", utilbuf+strlen(gl.progName)+9);
#endif  /* PROC_TRACE */
/* */
	(void) strcat(utilbuf, "\n");
	MsgLog("%s", utilbuf);
	Bail(1);
}
