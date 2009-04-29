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

#include "nomos.h"
#include "_autodefs.h"
#include "util.h"
#include "licenses.h"
#include "list.h"
#include "process.h"

#define	DEF_SHELL	"/bin/sh"
#define	_MAXFILESIZE	/* was 512000000-> 800000000 */	1600000000

#ifdef	STOPWATCH
DECL_TIMER;
#endif	/* STOPWATCH */

#ifdef notdef
/* 
   Extern Functions
*/
/*
  CDB - Go back and make sure all of these are used in this file.
        May also want to add args for type checking, or put them
	in a header file.
*/
extern void makeDir(); 
extern void makeBaseDirs(); 
extern void makeTempDir(); 
extern void makePath();
extern void munmapFile(); 
extern void changeDir(); 
extern void decryptAll(); 
extern void licenseInit(); 
extern void dumpLicenses();
extern void processRawSource(); 
extern void listInit(); 
extern void processDistribution(); 
extern void chmodInode();
extern void Fatal(); 
extern void MsgLog(); 
extern void forceRemoveDir(); 
extern void Warn(); 
extern void ntfwFileFilter();
extern void unbufferFile(); 
extern void openLogFile(); 
extern void closeLogFile();
extern void listClear();
extern void makeCustomDirs();
extern char *mmapFile(); 
extern char *copyString(); 
extern char *pathBasename(); 
extern char *findEol();
extern char *pluralName(); 
extern char *findBol();
extern int parseExtensibleBuckets(); 
extern int parseConfFile(); 
extern int putenv(); 
extern int isDIR();
extern int isFILE(); 
extern int endsIn(); 
extern int textInFile(); 
extern int mySystem();
extern int strGrep(); 
extern int idxGrep(); 
extern int validateTools();
extern FILE *fopenFile();
extern item_t *listIterate(); 
extern item_t *listGetItem(); 
extern item_t *listAppend();
extern fileHandler_t *unpackHandler();
extern int fileIsShar();
extern int isDebianDSC();
#endif /* notdef */


/*
   External Variable References
*/
struct globals gl;
extern licText_t licText[];


#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
char Version[]=SVN_REV;
#endif




void Bail(int exitval)
{
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== Bail(%d)\n", exitval);
#endif	/* PROC_TRACE */
#ifdef	DEBUG
    printf("Bail(%d)\n", exitval);
    if (exitval) {
	printf("Bailing in dir \"%s\"\n", gl.cwd);
	(void) mySystem("ls -lR");
    }
#endif	/* DEBUG */
    (void) chdir(gl.initwd);
    if (gl.mcookie != (magic_t) NULL) {
	magic_close(gl.mcookie);
    }
    if (isDIR(gl.tmpdir)) { /* CDB ? Are we using this tmpdir? */
	forceRemoveDir(gl.tmpdir, NO);
    }
#if	defined(MEMORY_TRACING) && defined(MEM_ACCT)
    if (exitval) {
	memCacheDump("Mem-cache @ Bail() time:");
    }
#endif	/* MEMORY_TRACING && MEM_ACCT */
    /*
     * Question: do we want to wait for any processes that might still
     * be executing?
     */
    if (isDIR(gl.mntpath)) {
	(void) mySystem("%s %s > %s 2>&1", licText[_UMNT_IMG].regex,
			gl.mntpath, DEVNULL);
	if (rmdir(gl.mntpath)) {
	    perror(gl.mntpath);
	}
    }
    exit(exitval);
}

void alreadyDone(char *pathname)
{
	fprintf(stderr, "%s: %s already processed\n", gl.progName, pathname);
	Bail(0);
}

static void setOption(int val)
{
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== setOption(%x)\n", val);
#endif	/* PROC_TRACE */
    gl.progOpts |= val;
    return;
}


static void unsetOption(int val)
{
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== unsetOption(%x)\n", val);
#endif	/* PROC_TRACE */
    gl.progOpts &= ~val;
    return;
}


int optionIsSet(int val)
{
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== optionIsSet(%x)\n", val);
#endif	/* PROC_TRACE */
    return(gl.progOpts & val);
}


/*
  At the moment, we have options for debugging and tracing.

  Tracing only works if program is compiled with PROC_TRACE and
  PROC_TRACE_SWITCH.
*/
static void parseOpts(int argc, char **argv)
{
    int optc;
    char *file_to_scan;
#if defined (PROC_TRACE) && defined(PROC_TRACE_SWITCH)
    char *optChoices = "DT";
#else
    char *optChoices = "D";
#endif
    static struct option const longopts[] = {
	{"debug", no_argument, NULL, 'D'},
#if defined (PROC_TRACE) && defined(PROC_TRACE_SWITCH)
	{"trace", no_argument, NULL, 'T'},
#endif /* PROC_TRACE && PROC_TRACE_SWITCH */
	{NULL, no_argument, NULL, 0}
    };

#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch) /* Oh! The Irony */
#endif	/* PROC_TRACE_SWITCH */
	printf("== parseOpts(%d, **argv)\n", argc);
#endif	/* PROC_TRACE */
    /* */
    gl.progOpts = 0;
    gl.uPsize = 6; /* "Paragraph" size */
    while ((optc = getopt_long(argc, argv, optChoices, longopts,
			       (int *) 0)) != -1) {
	switch (optc) {
	case 'D':
	    setOption(OPTS_DEBUG);
	    break;
#ifdef PROC_TRACE_SWITCH
	case 'T':
	    gl.ptswitch = 1;
	    break;
#endif
	default:
	    /* need usage() function! */
	    Fatal("Usage: %s [-D] [-T] file_to_scan\n", gl.progName);
	}
    }

    /*
      Need to have a remaining arg that identifies file to
      be scanned.
    */
    if (optind >= argc) {
	Fatal("Usage: %s [-D] [-T] file_to_scan\n", gl.progName);
    }

    file_to_scan = argv[optind];
    printf("%s: scanning file %s\n.", gl.progName, file_to_scan); /* CDB */

    if (isDIR(NOMOS_TEMP)) {
	if (isFILE(NOMOS_TLOCK)) {
	    Fatal("Another %s instance is running!",
		  gl.progName);
	}
	/* 
	   CDB - Can't I just go ahead and clear out the directory
	   and start over if the lockfile isn't there? 
	*/
	Fatal("Temp directory %s already exists!", NOMOS_TEMP);
    }

    if (!isFILE(file_to_scan)) {
	Fatal("\"%s\" is not a plain file", file_to_scan);
    }

    if (mkdir(NOMOS_TEMP, 0755)) {
	perror(NOMOS_TEMP);
	Fatal("%s: cannot make temp directory %s", gl.progName, NOMOS_TEMP);
    }

    if (mySystem("cp '%s' %s", file_to_scan, NOMOS_TEMP)) {
	Fatal("Cannot copy %s to temp-directory", file_to_scan);
    }

    sprintf(gl.target, "%s/%s", NOMOS_TEMP, basename(file_to_scan));
    printf("%s: full path of file to scan is %s\n.", gl.progName, gl.target); /* CDB */

    gl.targetLen = strlen(gl.target);
    return;
}


#ifdef notdef
CDB -- I dont think I need this....
static int fileDirScan(char *p, struct stat *st, int flag, struct FTW *s)
{
    char *cp; 
    char *textp; 
    char *md; 
    char *what;
    item_t *ip; 
    item_t *xp;
    int saveBasename = 1;
    int isPackage = 0;
    int *bufp;
    int notPkgFmt = 0;
    list_t *l;
    /* */
#ifdef	QA_CHECKS
    if (flag != FTW_F) {
	Warn("%s is NOT a file!", p);
	return 0;
    }
#endif	/* QA_CHECKS */
    /*
     * no PROC_TRACE support for this routine, but the nftwFileFilter()
     * call WILL log something!
     */
    if (nftwFileFilter(p, st, NO)) {
	return(0);
    }
    if ((st->st_mode & S_IRUSR) == 0) {
	chmodInode(p, (st->st_mode | S_IRUSR));
#ifdef	QA_CHECKS
	Warn("%s not readable (corrected)", p);
#endif	/* QA_CHECKS */
    }
    if ((cp = pathBasename(p)) == NULL_STR) {
	Fatal("%s: No '/' in %s!", gl.progName, p);
    }
    /*
     * Experience shows we really cannot trust the name of a file.  Even if
     * a file ends in deb or rpm, it's possible they're *corrupt* and later
     * on, parsing a corrupt package leads to errors.
     */
    md = copyString(magic_file(gl.mcookie, p), MTAG_MAGICDATA);
    if (idxGrep(_UTIL_FILESUFFIX, cp, REG_ICASE|REG_NEWLINE|REG_EXTENDED)) {
	if (endsIn(cp, ".rpm") || endsIn(cp, ".mvl")) {
	    if (idxGrep(_FTYP_RPM, md, REG_ICASE)) {
		if (endsIn(cp, "src.rpm")) {
		    l = &gl.srcpList;
		    isPackage = 1;
		}
		else {
		    l = &gl.instpList;
		    isPackage = 1;
		}
		gl.nRpm++;
	    }
	    else {
		notPkgFmt++;
		what = "RPM-format package";
	    }
	}
#if	0
	else if (endsIn(cp, ".mvl")) {
	    l = &gl.instpList;
	    isPackage = 1;
	    gl.nRpm++;
	}
#endif
	else if (endsIn(cp, ".deb") || endsIn(cp, ".udeb")) {
	    if (idxGrep(_FTYP_DEB, md, REG_ICASE)) {
		l = &gl.instpList;
		isPackage = 1;
		gl.nDeb++;
	    }
	    else {
		notPkgFmt++;
		what = "Debian binary package";
	    }
	}
	else if (endsIn(cp, ".dsc")) {
	    register char *textp;
	    textp = mmapFile(p);
	    if (isDebianDSC(textp)) {
		l = &gl.srcpList;
		isPackage = 1;
		gl.nDeb++;
	    }
	    else {
		notPkgFmt++;
		what = "Debian-source spec";
	    }
	    munmapFile(textp);
	}
	else {
	    l = &gl.sarchList;
	}
	if (notPkgFmt) {
	    Note("%s: NOT %s", p, what);
	    l = &gl.regfList;
	    saveBasename--;
	}
    }
    else {			/* ASSUMPTION: we won't find a package here. */
	if (unpackHandler(p, md, NO) != NULL_FH) {
	    l = &gl.sarchList;
	}
	else {
	    textp = mmapFile(p);
	    if (fileIsShar(textp, md)) {
		l = &gl.sarchList;
	    }
	    else {		/* regular: DON'T save the basename */
		l = &gl.regfList;
		saveBasename--;
	    }
	    munmapFile(textp);
	}
    }
    memFree(md, MTAG_MAGICDATA);
    /*
     * Save the filename and it's basename in the appropriate list.
     */
    ip = listGetItem(l, p);
    if (saveBasename) {
	ip->buf = copyString(cp, MTAG_PATHBASE);
    }
    /*
     * IF this is a package, count the # of instances/occurrences of 
     * the reference to this file (e.g., an instance of the source package
     * OR a binary package that's derived from the source).
     */
    if (isPackage) {
	xp = listGetItem(&gl.uniqList, cp);
	xp->val++;
	isPackage = 0;
    }
    return(0);
}
#endif /* notdef */

#ifdef notdef
CDB - Don't think it's needed
static int nestedPkgScan(char *p, struct stat *st, int flag, struct FTW *s)
{
 	register char *cp, *textp, *md;
	register item_t *ip, *xp;
	register int isSource = 0, *bufp;
	list_t *l;
	extern fh_t *unpackHandler();
	extern int fileIsShar();
/* */
#ifdef	QA_CHECKS
	if (flag != FTW_F) {
		Warn("%s is NOT a file!", p);
		return 0;
	}
#endif	/* QA_CHECKS */
	if (nftwFileFilter(p, st, NO)) {
		return(0);
	}
	if ((cp = pathBasename(p)) == NULL_STR) {
		Fatal("%s: No '/' in %s!", gl.progName, p);
	}
	if (idxGrep(_UTIL_FILESUFFIX, cp, REG_ICASE|REG_NEWLINE|REG_EXTENDED)) {
		if (endsIn(cp, ".rpm")) {
			l = &gl.defPkgList;
			if (endsIn(cp, "src.rpm")) {
				isSource = 1;
			}
			gl.nRpm++;
		}
		else if (endsIn(cp, ".mvl")) {
			l = &gl.defPkgList;
			gl.nRpm++;
		}
		else if (endsIn(cp, ".deb") || endsIn(cp, ".udeb")) {
			l = &gl.defPkgList;
			gl.nDeb++;
		}
		else if (endsIn(cp, ".dsc")) {
			l = &gl.defPkgList;
			isSource = 1;
			gl.nDeb++;
		}
		else {
			l = &gl.defArchList;
		}
	}
/*
 * Save the filename and its basename in the appropriate list.
 */
	ip = listGetItem(l, p);
	ip->val = isSource;
	ip->buf = copyString(cp, MTAG_PATHBASE);
	return(0);
}
#endif /* notdef */

#ifdef notdef
/* CDB -- Don't think we need this */
  
static void printListToFile(list_t *l, char *filename, char *mode)
{
	FILE *fp;
	register int i;
	register item_t *ip;
/* */
	fp = fopenFile(filename, mode);
#if	0
	for (i = 0, ip = l->items; i < l->used; i++, ip++)
#endif
	while ((ip = listIterate(l)) != NULL_ITEM) {
		fprintf(fp, "%s\n", ip->str);
	}
	(void) fclose(fp);
	return;
}

static void getFileLists(char *dirpath)
{
	register item_t *p;
/*
 * Construct lists of source packages, installable-packages, plain files
 * and source archives, and classify all files in the distribution.
 */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== getFileLists(%s)\n", dirpath);
#endif	/* PROC_TRACE */
	gl.nRpm = gl.nDeb = gl.isDebian = 0;
	listInit(&gl.srcpList, 0, "source-package list");
	listInit(&gl.instpList, 0, "installable-package list");
	listInit(&gl.testpList, 0, "known-bad-format-test-pkgs list");
	listInit(&gl.sarchList, 0, "source-archives list & md5sum map");
	listInit(&gl.regfList, 0, "regular-files list");
	listInit(&gl.uniqList, 0, "unique-package-name list");
	listInit(&gl.nestedNameList, 0, "nested-package-location(s) list");
	listInit(&gl.corrList, 0, "corrupt-sources list");
	listInit(&gl.hugeOkList, 0, "huge-files-OK list");
	listInit(&gl.allLicList, 0, "all-licenses list");
#ifdef	FLAG_NO_COPYRIGHT
	listInit(&gl.nocpyrtList, 0, "no-copyright list");
#endif	/* FLAG_NO_COPYRIGHT */
/*
 * ftw() callback will process all files found in the repository and
 * populate files containing lists of each (installable packages, source
 * packages, spare source archives, and plain-ol' files)
 */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch) {
#endif	/* PROC_TRACE_SWITCH */
	printf("=> invoking nftw:fileDirScan(%s)\n", dirpath);
#ifdef	PROC_TRACE_SWITCH
    }
#endif	/* PROC_TRACE_SWITCH */
#endif	/* PROC_TRACE */
/*
 * Red Hat has introduced the need to use FTW_PHYS (e.g., don't follow
 * symlinks in the nftw() call -- there are symlinks in the distro trees
 * (RHEL2.1 update 4 and beyond) that have symlinks that point to /tmp.
 *****
 * FIX-ME: this means we cannot use symlinks for targets; we should try
 * to detect this case and work around it somehow -- like check the
 * target and see if it falls outside the distro?
 */
	(void) nftw(dirpath, (__nftw_func_t) fileDirScan, 100, FTW_PHYS);
#ifdef	DEBUG
	listDump(&gl.srcpList, NO);
	listDump(&gl.instpList, NO);
	listDump(&gl.sarchList, NO);
	listDump(&gl.regfList, NO);
	if (gl.srcpList.used) {		/* dump source packages to file */
		printListToFile(&gl.srcpList, SRCS_LIST, "w");
	}
	if (gl.instpList.used) {	/* ... installable packages */
		printListToFile(&gl.instpList, INST_LIST, "w");
	}
/*
 * For the source-archive list, sort the list by basename AFTER dumping
 * the contents to a file.
 */
	if (gl.sarchList.used) {	/* ... source archives */
		printListToFile(&gl.sarchList, ARCH_LIST, "w");
	}
	if (gl.regfList.used) {		/* ... regular files */
		printListToFile(&gl.regfList, FILE_LIST, "w");
	}
	(void) mySystem("cp %s %s", SRCS_LIST, gl.initwd);
	(void) mySystem("cp %s %s", INST_LIST, gl.initwd);
	(void) mySystem("cp %s %s", FILE_LIST, gl.initwd);
	(void) mySystem("cp %s %s", ARCH_LIST, gl.initwd);
#endif	/* DEBUG */
/*
 * We use a text-file to search for packages found and the source-archives
 * list needs to be sorted-by-alias
 */
	if (gl.srcpList.used || gl.instpList.used) {	/* ... all packages */
		printListToFile(&gl.srcpList, PKGS_LIST, "w");
		printListToFile(&gl.instpList, PKGS_LIST, "a");
	}
	listSort(&gl.sarchList, SORT_BY_ALIAS);
	return;
}

/*
 * Construct lists of nested source and binary packages as well as any
 * necessary source archives found in a PREVIOUS run -- these packages
 * and files need to appear on the same lists as they would if this were
 * the first time we parsed the distribution.
 *****
 * This special-purpose function ASSUMES pen/$VENDOR/$NAME.$ARCH/nested
 * directory has not been modified.  This function further assumes it is
 * ONLY called for a re-scan of a distro (e.g., NOT the first time a
 * distribution is parsed with this tool)!
 */
static void getNestedPackages()
{
	char dirpath[myBUFSIZ], *cp;
	register item_t *p, *xp;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== getNestedPackages()\n");
#endif	/* PROC_TRACE */
/* */
	(void) sprintf(dirpath, "%s/nested", gl.penDir);
	if (!isDIR(dirpath)) {	/* assume this directory exists! */
		Fatal("Nested-packages directory structure missing");
	}
/*
 * ftw() callback will process all files found in the nested-packages dir
 * and populate the correct lists so processDistro() can function properly.
 */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch) {
#endif	/* PROC_TRACE_SWITCH */
	printf("=> invoking nftw:nestedPkgScan(%s)\n", dirpath);
#ifdef	PROC_TRACE_SWITCH
    }
#endif	/* PROC_TRACE_SWITCH */
#endif	/* PROC_TRACE */
/* */
	(void) nftw(dirpath, (__nftw_func_t) nestedPkgScan, 100, FTW_PHYS);
#ifdef	DEBUG
	listDump(&gl.defPkgList, NO);
	listDump(&gl.defArchList, NO);
#endif	/* DEBUG */
	if (gl.defPkgList.used) {
		Note("Adding %d nested packages found in initial scan",
		    gl.defPkgList.used);
/*
 * Need to be careful here. At this point, both the source-packages and
 * binary-packages lists are sorted BY_NAME and the source-archives list
 * is sorted BY_ALIAS -- so after we add the nested stuff to them we
 * need to make sure they're sorted the same way (and validly) before
 * we consider the job done here.
 */
		while ((p = listIterate(&gl.defPkgList)) != NULL_ITEM) {
			cp = pathBasename(p->str);
			xp = listAppend(p->val ? &gl.srcpList : &gl.instpList,
			    p->str);
			xp->buf = copyString(cp, MTAG_PATHBASE);
			xp = listGetItem(&gl.uniqList, cp);
			xp->val++;
		}
		while ((p = listIterate(&gl.defArchList)) != NULL_ITEM) {
			xp = listAppend(&gl.sarchList, p->str);
			xp->buf = copyString(pathBasename(p->str),
			    MTAG_PATHBASE);
			xp->val = 1;	/* consider archive 'processed' */
		}
		listSort(&gl.srcpList, SORT_BY_NAME);
		listSort(&gl.instpList, SORT_BY_NAME);
		listSort(&gl.sarchList, SORT_BY_ALIAS);
	}
	return;
}
#endif /* notdef */

#ifdef notdef
static void setupEnviron(char *dirpath)
{
	char buf[myBUFSIZ];
	register char *cp, *textp, *x;
	register item_t *p;
	int i;
/*
 * default setup, expect to be a raw source archive (not a distro).
 */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== setupEnviron(%s)\n", dirpath);
#endif	/* PROC_TRACE */
	if (validateTools() == 0) {
		Warn("Some expected tools are missing");
	}
	*gl.refVendor = NULL_CHAR;			/* default */
	if ((cp = strrchr(dirpath, '/')) == NULL) {
		Fatal("%s: directory has no slashes", gl.progName);
	}
	cp++;
	if (optionIsSet(OPTS_SRCONLY)) {
		strcpy(gl.arch, "");
		(void) strcpy(gl.vendor, "source archive for");	/* default */
		(void) strcpy(gl.prod, cp);
		(void) strcpy(gl.prodName, cp);
		(void) strcpy(gl.dist, dirpath);
		return;
	}
	(void) sprintf(buf, "%s/%s", gl.tmpdir, PKGS_LIST);
	if ((textp = mmapFile(buf)) == NULL_STR) {
		Fatal("No packages, didn't expect that!");
	}
/*
 * Determine distribution architecture (if any)
 */
	(void) strcpy(buf, "^.*");
	if (strcmp(cp, "i386") == 0 || strcmp(cp, "ia32") == 0) {
	    	(void) strcat(buf, "(i[3-6]86|ia32|noarch|all)");
		(void) strcpy(gl.arch, "i386");
	}
	else if (strcmp(cp, "ia64") == 0) {
		(void) strcat(buf, "ia64|noarch|all");
		(void) strcpy(gl.arch, "ia64");
	}
	else if (strcmp(cp, "x86_64") == 0 || strcmp(cp, "x86-64") == 0 ||
	    strcmp(cp, "amd64") == 0 || strcmp(cp, "emt64") == 0) {
	    	(void) strcat(buf, "(x86[-_]64|amd64|emt64|i[3456]86|noarch|all)");
		(void) strcpy(gl.arch, "x86_64");
	}
	else if (strcmp(cp, "lpia") == 0) {
	    	(void) strcat(buf, "lpia.*|noarch|all");
		(void) strcpy(gl.arch, "lpia");
	}
	else if (strcmp(cp, "arm") == 0) {
	    	(void) strcat(buf, "arm.*|noarch|all");
		(void) strcpy(gl.arch, "arm");
	}
	else if (strcmp(cp, "ppc") == 0) {
	    	(void) strcat(buf, "ppc.*|noarch|all");
		(void) strcpy(gl.arch, "arm");
	}
	else if (strcmp(cp, "mips") == 0) {
	    	(void) strcat(buf, "mips.*|noarch|all");
		(void) strcpy(gl.arch, "arm");
	}
	else if (strcmp(cp, "alpha") == 0) {
	    	(void) strcat(buf, "alpha|noarch|all");
		(void) strcpy(gl.arch, "alpha");
	}
/*
 * The architecture-types 'noarch' and 'all' are special cases...
 * Distros that are source-only are allowed here.
 */
	else if (strcmp(cp, "all") == 0 || strcmp(cp, "noarch") == 0) {
	    	(void) strcat(buf, "(all|noarch|src)");
		(void) strcpy(gl.arch, "all-arch");
	}
	else {	/* unknown --> punt */
		Fatal("%s: Unknown architecture", dirpath);
	}
/*
 * validate that some package of the specified architecture exists 
 */
	*(cp-1) = NULL_CHAR;	/* eliminate arch component of path */
	(void) strcat(buf, ".(rpm|mvl|deb)$");
	if (strGrep(buf, (char *) textp, REG_NEWLINE|REG_EXTENDED) == 0) {
		Fatal("No \"%s\" packages found", buf);
	}
/* 
 * OK, so a package with the right architecture has been found in the
 * hierarchy (somwhere); save pattern-match as a pathname template
 */
	i = gl.regm.rm_eo-gl.regm.rm_so;
	strncpy(buf, (char *) (textp+gl.regm.rm_so), (size_t) i);
	buf[i] = NULL_CHAR;
	if (optionIsSet(OPTS_DEBUG)) {
		printf("[>] \"%s\"\n", buf);
	}
/* 
 * determine distribution vendor w/ regex to locate the start-position
 * of the pathname that includes the vendor -- we'll want that next.
 *****
 * Note that we check the predominant vendors LAST -- this is because
 * (for the sake of Red Hat, as an example) some distributions just copy
 * the "RedHat/RPMS" directory structure from Red Hat -- and that will
 * match -- leading to confusion.
 */
	if (!strGrep("/hp-ref/", buf, REG_ICASE) &&
	    !strGrep("/caldera/", buf, REG_ICASE) &&
	    !strGrep("/centos/", buf, REG_ICASE) &&
	    !strGrep("/everest/", buf, REG_ICASE) &&
	    !strGrep("/debian/", buf, REG_ICASE) &&
	    !strGrep("/mandrake/", buf, REG_ICASE) &&
	    !strGrep("/mandriva/", buf, REG_ICASE) &&
	    !strGrep("/montavista/", buf, REG_ICASE) &&
	    !strGrep("/turbolinux/", buf, REG_ICASE) &&
	    !strGrep("/yellowdog/", buf, REG_ICASE) &&
	    !strGrep("/pigeonpoint/", buf, REG_ICASE) &&
	    !strGrep("/suse/", buf, REG_ICASE) &&
	    !strGrep("/test/", buf, REG_ICASE) &&
	    !strGrep("/asianux/", buf, REG_ICASE) &&
	    !strGrep("/oracle/", buf, REG_ICASE) &&
	    !strGrep("/redhat/", buf, REG_ICASE) &&
	    !strGrep("/hp/", buf, REG_ICASE)) {
		Fatal("Unknown vendor");
	}
	if (parseConfFile((x = dirpath+gl.regm.rm_so+1))) {
		Fatal("Entry for %s not found", gl.target);
	}
/*
 * Reset the entire pathname (we truncated it earlier)
 */
	*(cp-1) = '/';	/* restore arch component to path */
	(void) strcpy(gl.dist, x);
	(void) sprintf(gl.prodName, "%s.%s", gl.prod, gl.arch);
	munmapFile(textp);
/*
 * Set up the global list of known-bad-format-test-packages
 */
	(void) sprintf(buf, "%s/%s", gl.tmpdir, KNOWN_TESTPKGS);
	if ((textp = mmapFile(buf)) != NULL_STR) {
		(void) sprintf(buf, "^.*\t.*$");
		cp = textp;
		while (strGrep(buf, cp, REG_NEWLINE)) {
			cp += gl.regm.rm_so;
			x = strchr(cp, '\t');
			*x = '/';
			x = findEol(x);
			*x = NULL_CHAR;
			p = listGetItem(&gl.testpList, cp);
			cp = x+1;
		}
		munmapFile(textp);
#ifdef	DEBUG
		listDump(&gl.testpList, NO);
#endif	/* DEBUG */
	}
/*
 * Now set up the global list of OK-packages-known-to-create-huge-files
 */
	(void) sprintf(buf, "%s/%s", gl.tmpdir, KNOWN_HUGEOK);
	if ((textp = mmapFile(buf)) != NULL_STR) {
		for (cp = textp; *cp; cp = x+1) {
			x = findEol(cp);
			*x = NULL_CHAR;
#if	0
printf("DEBUG: hugeOK line:\n\t%s\n", cp);
#endif
			if (*cp == '#') {	/* comment? */
				continue;	/* yes, ignore it */
			}
			p = listGetItem(&gl.hugeOkList, cp);
		}
		munmapFile(textp);
#ifdef	DEBUG
		listDump(&gl.hugeOkList, NO);
#endif	/* DEBUG */
	}
	return;
}
#endif /* notdef */


int main(int argc, char *argv[])
{
    char *tempstr;
    int i;
    list_t *list_ptr;
    item_t *item_ptr;

#ifdef	PROC_TRACE
    printf("== main(%d, %p)\n", argc, argv);
#endif	/* PROC_TRACE */

#ifdef	MEMORY_TRACING
    mcheck(0);
#endif	/* MEMORY_TRACING */
#ifdef	GLOBAL_DEBUG
    gl.DEEBUG = gl.MEM_DEEBUG = 0;
#endif	/* GLOBAL_DEBUG */
#ifdef	STOPWATCH
    START_TIMER;
#endif	/* STOPWATCH */

    /*
      Record our name
    */
    if ((tempstr = strrchr(argv[0], '/')) == NULL_STR) {
	(void) strcpy(gl.progName, argv[0]);
    } else {
	while (*tempstr == '.' || *tempstr == '/') {
	    tempstr++;
	}
	(void) strcpy(gl.progName, tempstr);
    }
    

    if (putenv("LANG=C") < 0) {
	perror("putenv");
	Fatal("Cannot set LANG=C in environment");
    }
    unbufferFile(stdout);
    (void) umask(022);

    /*
     * Grab miscellaneous things from the environent
     */
    if (getcwd(gl.initwd, sizeof(gl.initwd)) == NULL_STR) {
	perror("getcwd");
	Fatal("Cannot obtain starting directory");
    }
    (void) strcpy(gl.cwd, gl.initwd);
    tempstr = getenv("SHELL");
    (void) strcpy(gl.shell, tempstr ? tempstr : DEF_SHELL);

#ifdef notdef
    tempstr = getenv("NOMOS_BASE");
    if (tempstr == NULL) {
	Fatal("NOMOS_BASE environment variable not set.");
    }
    if ((tempstr = getenv("NOMOS_EXT")) == NULL_STR) {
	(void) sprintf(gl.extDir, "%s/ext", gl.basedir);
    }
    else {
	(void) strcpy(gl.extDir, tempstr);
	if (!isDIR(gl.extDir)) {
	    Fatal("%s: not a directory", gl.extDir);
	}
    }
#endif /* notdef */

#ifdef	USE_MMAP
    gl.pagesize = getpagesize();
    printf("main: pagesize = %d\n", gl.pagesize);
#endif	/* USE_MMAP */

    (void) sprintf(gl.mntpath, "/tmp/%s.mnt%d", gl.progName, getpid());
    parseOpts(argc, argv);

    makeTempDir(); /* CDB - Improve by having func return name */
    /*    makeBaseDirs(gl.basedir); CDB */

    licenseInit();
    gl.fSearch = 0;
    gl.fSave = 0;
    gl.eSave = 0;
    gl.flags = 0;
    gl.totBytes = 0.0;
    gl.blkUpperLimit = _MAXFILESIZE/512;

    /*
     * We've copied the specified file to scan to 'gl.target'; now, normalize
     * the pathname (in case we were passed a symlink to another dir).
     */
    changeDir(gl.target);	/* see if we can chdir to the target */
#ifdef notdef
    changeDir(gl.tmpdir);
    getMasterLists();
#endif

    printf("%s: Examining %s ...\n", gl.progName, gl.target);
    /* getFileLists(gl.target); */
    changeDir(gl.initwd);
    /* setupEnviron(gl.target); */
    /* makeCustomDirs(gl.basedir); */
#ifdef notdef
    sprintf(pathname, "%s/licenses/%s", gl.basedir, DIST_LIST);
    if (optionIsSet(OPTS_GENLIC) && !optionIsSet(OPTS_TIMESTAMP) &&
	textInFile(pathname, gl.dist, 0)) {
	alreadyDone(gl.target);
    }
    listInit(&gl.defPkgList, 0, "deferred/nested-pkgs list");
    listInit(&gl.defArchList, 0, "deferred/nested-archives list");
    if (!optionIsSet(OPTS_SRCONLY)) {
	getNestedPackages();
	MsgLog("%s: %d %s (%d non-source + %d source",
	       gl.progName, (gl.srcpList.used+gl.instpList.used),
	       pluralName("package", gl.srcpList.used+gl.instpList.used),
	       gl.instpList.used, gl.srcpList.used);
	if (gl.nRpm && gl.nDeb) {
	    MsgLog(", %d %s", gl.nRpm, pluralName("RPM", gl.nRpm));
	    MsgLog(" + %d %s", gl.nDeb, pluralName("DEB", gl.nDeb));
	}
	MsgLog(")\n");
	if ((i = listCount(&gl.uniqList)) != gl.uniqList.used) {
	    MsgLog("%s: %d duplicate %s, %d to process\n",
		   gl.progName, i-gl.uniqList.used,
		   pluralName("package", i-gl.uniqList.used),
		   gl.uniqList.used);
	}
	if (gl.srcpList.used == 0) {
	    Warn("No source packages found!");
	}
    }
#endif /* notdef */
    if (gettimeofday(&gl.startTime, (struct timezone *) NULL) < 0) {
	perror("startTime: gettimeofday()");
	Bail(1);
    }
#ifdef notdef
    i = sprintf(gl.report, "%s/reports/%s/%s", gl.basedir, 
		optionIsSet(OPTS_TIMESTAMP) ? "recur" : "dist", gl.prodName);
    if (optionIsSet(OPTS_TIMESTAMP)) {
	thyme = localtime((time_t *)&gl.startTime.tv_sec);
	i += sprintf(gl.report+i, "-%04d%02d%02d.%02d%02d%02d",
		     thyme->tm_year+1900, thyme->tm_mon+1, thyme->tm_mday,
		     thyme->tm_hour, thyme->tm_min, thyme->tm_sec);
    }
    (void) strcpy(gl.report+i, ".html");
    listInit(&gl.unpFileList, 0, "unpacked-files list");
#endif /* notdef */
    /* CDB -- Not sure I need all four of these */
    listInit(&gl.licHistList, 0, "found-licenses list");
    listInit(&gl.licFoundMap, 0, "pkg-license-found map");
    listInit(&gl.fLicFoundMap, 0, "file-license-found map");
    listInit(&gl.parseList, 0, "license-components list");

#ifdef	STOPWATCH
    END_TIMER;
    PRINT_TIMER("init", 0);
#endif	/* STOPWATCH */

#ifdef notdef
    if (optionIsSet(OPTS_SRCONLY)) {
	processRawSource();
    } else {
	listInit(&gl.allpList, 0, "all-packages list");
	listSort(&gl.srcpList, SORT_BY_ALIAS);
	listSort(&gl.instpList, SORT_BY_ALIAS);
	while ((p = listIterate(&gl.srcpList)) != NULL_ITEM) {
	    ip = listAppend(&gl.allpList, p->str);
	    ip->pkgIsSource = 1;
	}
	while ((p = listIterate(&gl.instpList)) != NULL_ITEM) {
	    ip = listAppend(&gl.allpList, p->str);
	}
	listSort(&gl.allpList, SORT_BY_BASENAME);
	listSort(&gl.allpList, SORT_BY_COUNT_DSC);	/* src-pkgs first */
#endif /* notdef */
	processDistribution();
#ifdef notdef
	listClear(&gl.allpList, YES);
    }
#endif /* notdef */

    /* 
       CDB. In the original, this was only executed if the option 
       OPTS_NOREPORT was set. We're not generating a report, so....
    */
    changeDir("/tmp"); /* CDB - Unnecessary ? */
    (void) mySystem("rm -rf %s", NOMOS_TEMP);
    Bail(0);
    return 0; /* Keeping the compiler happy */

#ifdef notdef
    /*
     * Report execution time, etc.
     */
#ifdef	STOPWATCH
    START_TIMER;
#endif	/* STOPWATCH */
    if (gettimeofday(&gl.endTime, (struct timezone *) NULL) < 0) {
	perror("endTime: gettimeofday()");
    }
    fTime = (double)gl.endTime.tv_sec+((double)gl.endTime.tv_usec*0.000001)-
	(double)gl.startTime.tv_sec-((double)gl.startTime.tv_usec*0.000001);
    i = (int) (fTime+0.5);
    MsgLog("%s: %.2f seconds elapsed", gl.progName, fTime);
    if (fTime >= 60.0) {
	MsgLog(" (");
	if (i > 3600) {
	    MsgLog("%d:", i/3600);
	    i %= 3600;
	}
	MsgLog("%02d:%02d)", i/60, i%60);
    }
    MsgLog("\n");
    if (gl.fSearch && gl.fSave) {
	MsgLog("%s: %d %s searched (%.2f file/sec)\n",
	       gl.progName, gl.fSearch, pluralName("file", gl.fSearch),
	       (double)(gl.fSearch)/fTime);
	MsgLog("%s: %d %s saved (%.2f license/sec)\n",
	       gl.progName, gl.fSave, pluralName("license", gl.fSave),
	       (double)(gl.fSave)/fTime);
    }
    if (optionIsSet(OPTS_SRCONLY)) {
	i = gl.nSpare;
	cp = "source";
    }
    else {
	i = gl.nRpm+gl.nDeb+gl.nSpare;
    }
    if ((i = gl.nRpm+gl.nDeb)) {
	MsgLog("%s: %d %s processed (%.2f package/min)\n",
	       gl.progName, i, pluralName("package", i),
	       (double)(i*60.0)/fTime);
    }
    MBcount = gl.totBytes/1048576.0;
    MsgLog("%s: %.3f MB text/data processed (%.2f MB/sec)\n",
	   gl.progName, MBcount, MBcount/fTime);
    if (gl.logFp != (FILE *) NULL) {
	closeLogFile();
    }
    if (optionIsSet(OPTS_SUMMARY)) {
	if (gl.allLicList.used == 0) {
	    printf("@ no licenses found!\n");
	}
	else {
	    listSort(&gl.allLicList, SORT_BY_COUNT_DSC);
	    while ((p = listIterate(&gl.allLicList)) != NULL_ITEM) {
		printf("@ %7d %s\n", p->val, p->str);
	    }
	}
    }
    listClear(&gl.srcpList, YES);
    listClear(&gl.instpList, YES);
    listClear(&gl.testpList, YES);
    listClear(&gl.sarchList, YES);
    listClear(&gl.regfList, YES);
    listClear(&gl.uniqList, YES);
    listClear(&gl.unpFileList, YES);
    listClear(&gl.licHistList, YES);
    listClear(&gl.licFoundMap, YES);
    listClear(&gl.fLicFoundMap, YES);
    listClear(&gl.nestedNameList, YES);
    listClear(&gl.parseList, YES);
    listClear(&gl.corrList, YES);
    listClear(&gl.hugeOkList, YES);
    listClear(&gl.allLicList, YES);
    listClear(&gl.defPkgList, YES);
    listClear(&gl.defArchList, YES);
#ifdef	STOPWATCH
    END_TIMER;
    PRINT_TIMER("cleanup", 0);
#endif	/* STOPWATCH */
    Bail(0);
#endif /* notdef */
}


#ifdef notdef
int getMasterLists()
{
    char buf[myBUFSIZ];
    /*
     * This routine expects to be called when the current-working-dir is the
     * temporary directory
     */
    (void) sprintf(buf, "%s/db/%s", gl.basedir, MASTER_LISTS);
    if (!isFILE(buf)) {
	perror(buf);
	Bail(1);
    }
    if (mySystem("tar zxf %s", buf) < 0) {
	Fatal("cannot get Master Lists, aborting");
    }
#if	0
    /* Tired of seeing this knowing it's a MAYBE to get done */
    printf("*** STILL need to coordinate the master-lists on remote-server\n");
#endif
    return(gl.stbuf.st_mtime);
}
#endif /* notdef */

#ifdef notdef
/*
 * We have 2 different package-lists to merge:
 *    (1) a list of packages to be automatically merged
 *    (2) a list of packages to be individually asked-about
 * ... process the second one first.
 */
void mergeMasterLists()
{
	char target[myBUFSIZ], data[myBUFSIZ];
	extern void renameInode(), chmodInode();
/*
 * Add the latest new set of packages to the master-approved list
 */
#if	0
#ifdef	PROC_TRACE_SWITCH
	gl.ptswitch = 1;
#endif	/* PROC_TRACE_SWITCH */
#endif
	(void) sprintf(target, "%s/db/%s", gl.basedir, MASTER_PKGS);
	(void) sprintf(data, "%s/db/%s", gl.basedir, NEW_PKGS);
#ifdef	DEBUG
	printf("DEBUG: create new %s\n", target);
#endif	/* DEBUG */
	(void) mySystem("cat %s >> %s", data, target);
	(void) mySystem("sort %s | uniq > %s", target, data);
	renameInode(data, target);
	chmodInode(target, 0444);
	return;
}
#endif /* notdef */
