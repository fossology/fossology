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

void freeAndClearScan(struct curScan *);

struct globals gl;
struct curScan cur;
extern licText_t licText[];

char *files_to_be_scanned[];
int file_count = 0;

#define	_MAXFILESIZE	/* was 512000000-> 800000000 */	1600000000
#define TEMPDIR_TEMPLATE "/tmp/nomos.agent.XXXXXX"


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
#if defined(MEMORY_TRACING) && defined(MEM_ACCT)
    if (exitval) {
	memCacheDump("Mem-cache @ Bail() time:");
    }
#endif	/* MEMORY_TRACING && MEM_ACCT */

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


/*
  At the moment, we really don't have any options, so all this is doing
  is setting a variable (filenames) to the beginning of a list of filename
  args passed in.
*/
static void parseOptsAndArgs(int argc, char **argv)
{
    int i;

#ifdef  PROC_TRACE
    traceFunc("== parseOptsAndArgs(%d, **argv)\n", argc);
#endif  /* PROC_TRACE */

    if (argc <= 1) {
	Fatal("Usage: %s <file> ...", gl.progName);
    }
    /*
      Copy filename args into array
    */
    for (i = 1; i < argc; i++) {
	files_to_be_scanned[i-1] = argv[i];
	file_count++;
    }
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


/*
  CDB - Could probably roll this back into the main processing
  loop or just put in a generic init func that initializes *all*
  the lists.

  Initialize regfList and offList.
*/

static void getFileLists(char *dirpath)
{
#ifdef	PROC_TRACE
    traceFunc("== getFileLists(%s)\n", dirpath);
#endif	/* PROC_TRACE */

    /*    listInit(&gl.sarchList, 0, "source-archives list & md5sum map"); */
    listInit(&cur.regfList, 0, "regular-files list");
    listInit(&cur.offList, 0, "buffer-offset list");
#ifdef	FLAG_NO_COPYRIGHT
    listInit(&gl.nocpyrtList, 0, "no-copyright list");
#endif	/* FLAG_NO_COPYRIGHT */

    listGetItem(&cur.regfList, cur.targetFile);
    return;
}


void freeAndClearScan(struct curScan *thisScan) {

    /*
      Remove scratch dir and contents
    */
    (void) mySystem("rm -rf %s", thisScan->targetDir);

    /*
      Change back to original working directory
    */
    changeDir(gl.initwd);

    /*
      Clear lists
    */
    listClear(&thisScan->regfList, DEALLOC_LIST);
    listClear(&thisScan->offList, DEALLOC_LIST);
    listClear(&thisScan->fLicFoundMap, DEALLOC_LIST);
    listClear(&thisScan->parseList, DEALLOC_LIST);
    listClear(&thisScan->lList, DEALLOC_LIST);
    listClear(&thisScan->cList, DEALLOC_LIST);
    listClear(&thisScan->eList, DEALLOC_LIST);

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
      Set up variables global to the agent. Ones that are the
      same for all scans.
    */

    /* Record the progname name */
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

    /* Grab miscellaneous things from the environent */
    if (getcwd(gl.initwd, sizeof(gl.initwd)) == NULL_STR) {
	perror("getcwd");
	Fatal("Cannot obtain starting directory");
    }

    gl.uPsize = 6;
    parseOptsAndArgs(argc, argv);
    licenseInit();
    gl.flags = 0;

    /*
      CDB - Would eventually like to get rid of the file magic stuff
      in the agent and let other parts of FOSSology handle it.
    */
    if ((gl.mcookie = magic_open(MAGIC_NONE)) == (magic_t) NULL) {
	Fatal("magic_open() fails!");
    }
    if (magic_load(gl.mcookie, NULL_STR)) {
	Fatal("magic_load() fails!");
    }

    /* CDB -- Start of perScan stuff */

    /* 
       For each file to be scanned
    */
    for (i = 0; i < file_count; i++) {
	char *scan_file = files_to_be_scanned[i];
	
	/*
	  Initialize. This stuff should probably be broken into a separate
	  function, but for now, I'm leaving it all here.
	*/
	(void) strcpy(cur.cwd, gl.initwd);

#ifdef notdef
	CDB - WTF?
	if ((cp = strrchr(scan_file, '/')) == NULL_STR) {
	    cp = *argv;
	} else {
	    cp++;
	}
#endif /* notdef */

	/*
	  Create temporary directory for scratch space
	  and copy target file to that directory.
	*/
	strcpy(cur.targetDir, TEMPDIR_TEMPLATE);
	if (!mkdtemp(cur.targetDir)) {
	    perror("mkdtemp");
	    Fatal("%s: cannot make temp directory %s", gl.progName);
	}
	chmod(cur.targetDir, 0755);
	if (mySystem("cp '%s' %s", scan_file, cur.targetDir)) {
	    Fatal("Cannot copy %s to temp-directory", scan_file);
	}
	strcpy(cur.targetFile, cur.targetDir);
	strcat(cur.targetFile, "/");
	strcat(cur.targetFile, basename(scan_file));
	cur.targetLen = strlen(cur.targetDir);

	if (!isFILE(scan_file)) {
	    Fatal("\"%s\" is not a plain file", *scan_file);
	}
 
	/*
	  CDB - How much of this is still necessary?

	  chdir to target, call getcwd() to get real pathname; then, chdir back
	 
	  We've saved the specified directory in 'gl.targetDir'; now, normalize
	  the pathname (in case we were passed a symlink to another dir).
	*/
	changeDir(cur.targetDir);	/* see if we can chdir to the target */
	getFileLists(cur.targetDir);
	changeDir(gl.initwd);
	listInit(&cur.fLicFoundMap, 0, "file-license-found map");
	listInit(&cur.parseList, 0, "license-components list");
	listInit(&cur.lList, 0, "license-list");
	listInit(&cur.cList, 0, "copyright-list");
	listInit(&cur.eList, 0, "eula-list");


#ifdef	STOPWATCH
	END_TIMER;
	PRINT_TIMER("init", 0);
#endif	/* STOPWATCH */

	processRawSource();

	freeAndClearScan(&cur);
    }
    Bail(0);
}

