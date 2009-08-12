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

#include "libfossrepo.h"
#include "libfossdb.h"
#include "libfossagent.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif /* SVN_REV */

void freeAndClearScan(struct curScan *);

extern licText_t licText[]; /* Defined in _autodata.c */
struct globals gl;
struct curScan cur;
void *DB = NULL;
int schedulerMode = 0; /* Non-zero when being run from scheduler */

char *files_to_be_scanned[];
int file_count = 0;

#define	_MAXFILESIZE	/* was 512000000-> 800000000 */	1600000000
#define TEMPDIR_TEMPLATE "/tmp/nomos.agent.XXXXXX"


/*
  getFieldValue(): Given a string that contains
  field='value' pairs, save the items.
  Returns: pointer to start of next field, or NULL at \0.
*/
char *getFieldValue(char *inStr, char *field, int fieldMax,
			 char *value, int valueMax, char separator)
{
    int s;
    int f;
    int v;
    int gotQuote;

#ifdef	PROC_TRACE
    traceFunc("== getFieldValue(inStr= %s fieldMax= %d separator= '%c'\n",
	      inStr, fieldMax, separator);
#endif	/* PROC_TRACE */

    memset(field,0,fieldMax);
    memset(value,0,valueMax);

    /* Skip initial spaces */
    while(isspace(inStr[0])) {
	inStr++; 
    }

    if (inStr[0]=='\0') {
	return(NULL);
    }
    f = 0; 
    v = 0;

    /* Skip to end of field name */
    for(s = 0; (inStr[s] != '\0') && !isspace(inStr[s]) && (inStr[s] != '='); s++) {
	field[f++] = inStr[s];
    }

    /* Skip spaces after field name */
    while(isspace(inStr[s])) {
	s++; 
    }
    /* If it is not a field, then just return it. */
    if (inStr[s] != separator) { 
	return(inStr + s);
    }
    if (inStr[s]=='\0') {
	return(NULL);
    }
    /* Skip '=' */
    s++; 

    /* Skip spaces after '=' */
    while(isspace(inStr[s])) {
	s++; 
    }
    if (inStr[s]=='\0') {
	return(NULL);
    }

    gotQuote = '\0';
    if ((inStr[s]=='\'') || (inStr[s]=='"')) {
	gotQuote = inStr[s];
	s++; /* skip quote */
	if (inStr[s]=='\0') {
	    return(NULL);
	}
    }

    if (gotQuote) {
	for( ; (inStr[s] != '\0') && (inStr[s] != gotQuote); s++) {
	    if (inStr[s] == '\\') {
		value[v++] = inStr[++s];
	    } else {
		value[v++]=inStr[s];
	    }
	}
    } else {
	/* if it gets here, then there is no quote */
	for( ; (inStr[s] != '\0') && !isspace(inStr[s]); s++) {
	    if (inStr[s]=='\\') {
		value[v++]=inStr[++s];
	    } else {
		value[v++]=inStr[s];
	    }
	}
    }
    /* Skip spaces */
    while(isspace(inStr[s])) {
	s++;
    }

    return(inStr+s);
}


/*
  parseSchedInput(): Convert input pairs from the scheduler
  into globals.
*/
void parseSchedInput(char *s)
{
    char field[256];
    char value[1024];
    int gotOther=0;
    char *origS;

#ifdef	PROC_TRACE
    traceFunc("== parseSchedInput(%s)\n", s);
#endif	/* PROC_TRACE */

    cur.pFileFk = -1;
    memset(cur.pFile,'\0',myBUFSIZ);
    if (!s) {
	return;
    }
    origS = s;

    while(s && (s[0] != '\0')) {
	s = getFieldValue(s, field, 256, value, 1024, '=');
	if (value[0] != '\0') {
	    if (!strcasecmp(field,"pfile_fk")) {
		cur.pFileFk = atol(value);
	    } else if (!strcasecmp(field,"pfile")) {
		strncpy(cur.pFile, value, sizeof(cur.pFile));
	    } else {
		gotOther = 1;
	    }
        }
    }

    if (gotOther || (cur.pFileFk < 0) || (cur.pFile[0]=='\0')) {
	printf("FATAL: Data is in an unknown format.\n");
	printf("LOG: Unknown data: '%s'\n",origS);
	printf("LOG: Nomos agent is exiting\n");
	fflush(stdout);
	DBclose(DB);
	exit(-1);
    }
} 


void  Usage (char *Name)
{ 
    printf("Usage: %s [options] [file [file [...]]\n",Name);
    printf("  -i   :: initialize the database, then exit.\n");
    /*    printf("  -v   :: verbose (-vv = more verbose)\n"); */
    printf("  file :: if files are listed, print the licenses detected within them.\n");
    printf("  no file :: process data from the scheduler.\n");
} /* Usage() */



void Bail(int exitval)
{
#ifdef	PROC_TRACE
    traceFunc("== Bail(%d)\n", exitval);
#endif	/* PROC_TRACE */

    (void) chdir(gl.initwd);
    if (gl.mcookie != (magic_t) NULL) {
	magic_close(gl.mcookie);
    }
#if defined(MEMORY_TRACING) && defined(MEM_ACCT)
    if (exitval) {
	memCacheDump("Mem-cache @ Bail() time:");
    }
#endif	/* MEMORY_TRACING && MEM_ACCT */

    printf("LOG: Nomos agent is exiting\n");
    fflush(stdout);
    DBclose(DB);

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
#ifdef notdef
static void parseOptsAndArgs(int argc, char **argv)
{
    int i;

#ifdef  PROC_TRACE
    traceFunc("== parseOptsAndArgs(%d, **argv)\n", argc);
#endif  /* PROC_TRACE */

    /*
      Copy filename args into array
    */
    for (i = 1; i < argc; i++) {
	files_to_be_scanned[i-1] = argv[i];
	file_count++;
    }
    return;
}
#endif /* notdef */


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


/*
  Clean-up all the per scan data structures, freeing any
  old data.
*/
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



void processFile(char *fileToScan) {

#ifdef	PROC_TRACE
    traceFunc("== processFile(%s)\n", fileToScan);
#endif	/* PROC_TRACE */

    /*
      Initialize. This stuff should probably be broken into a separate
      function, but for now, I'm leaving it all here.
    */
    (void) strcpy(cur.cwd, gl.initwd);

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
    if (mySystem("cp '%s' %s", fileToScan, cur.targetDir)) {
	Fatal("Cannot copy %s to temp-directory", fileToScan);
    }
    strcpy(cur.targetFile, cur.targetDir);
    strcat(cur.targetFile, "/");
    strcat(cur.targetFile, basename(fileToScan));
    cur.targetLen = strlen(cur.targetDir);

    if (!isFILE(fileToScan)) {
	Fatal("\"%s\" is not a plain file", *fileToScan);
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

    processRawSource();

    freeAndClearScan(&cur);
}



int main(int argc, char **argv)
{
    char *cp;
    int i;
    int c;
    char *agent_desc = "Nomos License Detection Agency";
    char parm[myBUFSIZ];


#ifdef	PROC_TRACE
    traceFunc("== main(%d, %p)\n", argc, argv);
#endif	/* PROC_TRACE */

#ifdef	MEMORY_TRACING
    mcheck(0);
#endif	/* MEMORY_TRACING */
#ifdef	GLOBAL_DEBUG
    gl.DEEBUG = gl.MEM_DEEBUG = 0;
#endif	/* GLOBAL_DEBUG */

    printf("LOG: fo_nomos agent starting up from the beginning....");
    /*
      Set up variables global to the agent. Ones that are the
      same for all scans.
    */
    DB = DBopen();
    if (!DB) {
	printf("FATAL: Nomos agent unable to connect to database, exiting...\n");
	fflush(stdout);
	exit(-1);
    }
    gl.agentPk = GetAgentKey(DB, basename(argv[0]), 0, SVN_REV, agent_desc);


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

    /*
      Deal with command line options
    */
    while((c = getopt(argc,argv,"i")) != -1) {
    switch(c)
	{
	case 'i':
	    /* "Initialize" */
	    DBclose(DB);  /* DB was opened above, now close it and exit */
	    exit(0);
	default:
	    Usage(argv[0]);
	    DBclose(DB);
	    exit(-1);
	}
    }

    /*
      Copy filename args (if any) into array
    */
    for (i = 1; i < argc; i++) {
	files_to_be_scanned[i-1] = argv[i];
	file_count++;
    }

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


    if (file_count == 0) {
	char *repFile;

	/* 
	   We're being run from the scheduler
	*/
	printf("LOG: fo_nomos agent starting up in scheduler mode....");
	schedulerMode = 1;
	signal(SIGALRM, ShowHeartbeat);
	printf("OK\n");
	fflush(stdout);
	alarm(60);

	while (ReadLine(stdin, parm, myBUFSIZ) >= 0) {
	    if (parm[0] != '\0') {
		/*
		  Get the file arg and go ahead and process it
		*/
		parseSchedInput(parm);
		repFile = RepMkPath("files", cur.pFile);
		if (!repFile) {
		    printf("FATAL: pfile %ld Nomos unable to open file %s\n",
			   cur.pFileFk, cur.pFile);
		    fflush(stdout);
		    DBclose(DB);
		    exit(-1);
		}
		processFile(cur.pFile); 
		freeAndClearScan(&cur);
		printf("OK\n");
		fflush(stdout);
	    }
	}

	/*
	  On EOF we fall through to the Bail() call at the end.
	*/

    } else {
	/*
	  Files on the command line
	*/
	/* 
	   For each file to be scanned
	*/
	for (i = 0; i < file_count; i++) {
	    processFile(files_to_be_scanned[i]);
	}
    }
    Bail(0);
}

