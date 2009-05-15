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
/* CDB - What is this define for??? */
#ifndef	_GNU_SOURCE
#define	_GNU_SOURCE
#endif	/* not defined _GNU_SOURCE */

#include "nomos.h"
#include "util.h"
#include "list.h"
#include "licenses.h"
#include "process.h"
#include "nomos_regex.h"
#include "_autodefs.h"

#ifdef	STOPWATCH
DECL_TIMER;
#endif	/* STOPWATCH */

struct globals gl;
struct curPkg cur;
extern licText_t licText[];

#define	_MAXFILESIZE	/* was 512000000-> 800000000 */	1600000000

void Bail(int exitval)
{
#ifdef	PROC_TRACE
    traceFunc("== Bail(%d)\n", exitval);
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
    if (isDIR(gl.tmpdir)) {
	removeDir(gl.tmpdir);
    }
#if defined(MEMORY_TRACING) && defined(MEM_ACCT)
    if (exitval) {
	memCacheDump("Mem-cache @ Bail() time:");
    }
#endif	/* MEMORY_TRACING && MEM_ACCT */
    /*
     * Question: do we want to wait for any processes that might still
     * be executing?
     */
    if (isDIR(gl.mntpath)) {
	(void) mySystem("%s %s > %s 2>&1", _REGEX(_UMNT_IMG),
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
    traceFunc("== setOption(%x)\n", val);
#endif	/* PROC_TRACE */
    gl.progOpts |= val;
    return;
}

static void unsetOption(int val)
{
#ifdef	PROC_TRACE
    traceFunc("== unsetOption(%x)\n", val);
#endif /* PROC_TRACE */
    gl.progOpts &= ~val;
    return;
}

int optionIsSet(int val)
{
#ifdef	PROC_TRACE
    traceFunc("== optionIsSet(%x)\n", val);
#endif	/* PROC_TRACE */

    return(gl.progOpts & val);
}


static void parseOpts(int argc, char **argv)
{
    char *cp;

#ifdef  PROC_TRACE
    traceFunc("== parseOpts(%d, **argv)\n", argc);
#endif  /* PROC_TRACE */

    gl.uPsize = 6;

    argc--; /* CDB, Lame, but will fix in transition */
    *argv++; /* CDB, Ditto */
	
    if (argc != 1) {
	Fatal("Usage: %s <file>", gl.progName);
    }

    if (isDIR(NOMOS_TEMP)) {
	if (isFILE(NOMOS_TLOCK)) {
	    Fatal("Another %s instance is running!",
		  gl.progName);
	}
	Fatal("Temp directory %s already exists!", NOMOS_TEMP);
    }

    if (!isFILE(*argv)) {
	Fatal("\"%s\" is not a plain file", *argv);
    }
    if ((cp = strrchr(*argv, '/')) == NULL_STR) {
	cp = *argv;
    } else {
	cp++;
    }
    /*
      CDB - Need to change this so that we use a unique temp
      directory for each scan. Means we have to figure out how
      to keep track of them.
    */
    if (mkdir(NOMOS_TEMP, 0755)) {
	perror(NOMOS_TEMP);
	Fatal("%s: cannot make temp directory %s", gl.progName);
    }
    if (mySystem("cp '%s' %s", *argv, NOMOS_TEMP)) {
	Fatal("Cannot copy %s to temp-directory", *argv);
    }
    strcpy(gl.target, NOMOS_TEMP);
    strcpy(gl.targetFile, NOMOS_TEMP);
    strcat(gl.targetFile, "/");
    strcat(gl.targetFile, basename(*argv));

    gl.targetLen = strlen(gl.target);
    return;
}


static void printListToFile(list_t *l, char *filename, char *mode)
{
    FILE *fp;
    item_t *ip;

    fp = fopenFile(filename, mode);
    while ((ip = listIterate(l)) != NULL_ITEM) {
	fprintf(fp, "%s\n", ip->str);
    }
    (void) fclose(fp);
    return;
}


static void getFileLists(char *dirpath)
{
    /*
     * Construct lists of source packages, installable-packages, plain files
     * and source archives, and classify all files in the distribution.
     */
#ifdef	PROC_TRACE
    traceFunc("== getFileLists(%s)\n", dirpath);
#endif	/* PROC_TRACE */

    listInit(&gl.sarchList, 0, "source-archives list & md5sum map");
    listInit(&gl.regfList, 0, "regular-files list");
    listInit(&gl.allLicList, 0, "all-licenses list");
    listInit(&gl.offList, 0, "buffer-offset list");
#ifdef	FLAG_NO_COPYRIGHT
    listInit(&gl.nocpyrtList, 0, "no-copyright list");
#endif	/* FLAG_NO_COPYRIGHT */
#ifdef	SEARCH_CRYPTO
    listInit(&gl.cryptoList, 0, "cryptography list");
#endif	/* SEARCH_CRYPTO */

    listGetItem(&gl.regfList, gl.targetFile);
    return;
}




int main(int argc, char **argv)
{
    char *cp;
    int i;

#ifdef	PROC_TRACE
    traceFunc("== main(%d, %p)\n", argc, argv);
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
     * Record the progname name
     */
    if ((cp = strrchr(*argv, '/')) == NULL_STR) {
	(void) strcpy(gl.progName, *argv);
    } else {
	while (*cp == '.' || *cp == '/') {
	    cp++;
	}
	(void) strcpy(gl.progName, cp);
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
    parseOpts(argc, argv);
    licenseInit();
    gl.fSearch = 0;
    gl.fSave = 0;
    gl.flags = 0;
    gl.totBytes = 0.0;

    i = 0; /* CDB - Added so we don't have a custom Magic file */
    if ((gl.mcookie = magic_open(MAGIC_NONE)) == (magic_t) NULL) {
	Fatal("magic_open() fails!");
    }
    if (magic_load(gl.mcookie, i ? gl.magicFile : NULL_STR)) {
	Fatal("magic_load() fails!");
    }
    /*
     * chdir to target, call getcwd() to get real pathname; then, chdir back
     *
     *
     * We've saved the specified directory in 'gl.target'; now, normalize
     * the pathname (in case we were passed a symlink to another dir).
     */
    changeDir(gl.target);	/* see if we can chdir to the target */
    getFileLists(gl.target);
    changeDir(gl.initwd);
    listInit(&gl.licHistList, 0, "found-licenses list");
    listInit(&gl.fLicFoundMap, 0, "file-license-found map");
    listInit(&gl.parseList, 0, "license-components list");

#ifdef	STOPWATCH
    END_TIMER;
    PRINT_TIMER("init", 0);
#endif	/* STOPWATCH */
    processRawSource();
    changeDir("/tmp");
    (void) mySystem("rm -rf %s", NOMOS_TEMP);
    Bail(0);
}

