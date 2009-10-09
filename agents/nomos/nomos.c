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
/**
 \file nomos.c
 \brief Main for the nomos agent

 Nomos detects licenses and copyrights in a file.  Depending on how it is
 invoked, it either stores it's findings in the FOSSology data base or
 reports them to standard out.

 */
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
int schedulerMode = 0; /**< Non-zero when being run from scheduler */

char *files_to_be_scanned[]; /**< The list of files to scan */
int file_count = 0;

#define	_MAXFILESIZE	/* was 512000000-> 800000000 */	1600000000
#define TEMPDIR_TEMPLATE "/tmp/nomos.agent.XXXXXX"

/**
 addNewLicense
 \brief Add a new license name to license_ref table.

 Adds the license name with special text to indicate that the nomos agent added
 it.  This is the current workaround till the names in the table have been vetted.

 Inserts rf_shortname, and rf_text.

 @param  char *licenseName

 @return 1 for success, 0 for failure

 \todo Change this routine to take an array of licenses (array of char pointers)
 and make the loop a commit rollback loop, any error during an insert causes the
 whole transaction to fail (multiple license names).

 \todo track changes in tables and design.  For example, this routine will
 have to compute the md5 of the text and compare against that before adding a
 license.

 \todo this function must also update the reference hash so that the hash and
 table stay in sync.  The table must be updated first so that rf_pk's can be
 put in the hash.
 */
int addNewLicense(char *licenseName) {

	PGresult *result;
	char query[myBUFSIZ];
	char *specialLicenseText;

	specialLicenseText = "New License name inserted by Agent Nomos";

	if (licenseName == NULL_CHAR) {
		return (FALSE);
	}
	sprintf(
			query,
			"insert into license_ref(rf_shortname, rf_text) values('%s', '%s')",
			licenseName, specialLicenseText);

	result = PQexec(gl.pgConn, query);

	if (PQresultStatus(result) != PGRES_COMMAND_OK) {
		printf("   ERROR: Nomos agent got database error adding a new license "
				"to the reference table:\n%s\n", PQresultErrorMessage(result));
		PQclear(result);
		return (FALSE);
	}
	return (TRUE);
}


/**
 checkPQresult

 check the result status of the query with PQresultStatus

 @param PGresult *result

 @return NULL on OK, PgresultErrorMessage on failure

 \todo Add second parameter to indicate either select or insert/update,
 as the returned item is different (PGRES_COMMAND_OK).
 */
char * checkPQresult(PGresult *result) {
	/* PGresult *result; */
	char *failMessage;

	failMessage = NULL_CHAR;

	if (PQresultStatus(result) != PGRES_TUPLES_OK) {
		/*
		 Something went wrong.
		 */
		printf("   DB: Data Base Query Failed!\n");
		sprintf(failMessage, "   ERROR: Nomos agent got database error: %s\n",
				PQresultErrorMessage(result));
		/* printf("   DB: Error message is:%s\n", PQresultErrorMessage(result)); */
		PQclear(result);
		return (failMessage);
	}
	return (NULL_CHAR);
} /* checkPQresult */

/**
 getFieldValue
 \brief Given a string that contains field='value' pairs, save the items.

 @return pointer to start of next field, or NULL at \0.
 */
char *getFieldValue(char *inStr, char *field, int fieldMax, char *value,
		int valueMax, char separator) {
	int s;
	int f;
	int v;
	int gotQuote;

#ifdef	PROC_TRACE
	traceFunc("== getFieldValue(inStr= %s fieldMax= %d separator= '%c'\n",
			inStr, fieldMax, separator);
#endif	/* PROC_TRACE */

	memset(field, 0, fieldMax);
	memset(value, 0, valueMax);

	/* Skip initial spaces */
	while (isspace(inStr[0])) {
		inStr++;
	}

	if (inStr[0]=='\0') {
		return (NULL);
	}
	f = 0;
	v = 0;

	/* Skip to end of field name */
	for (s = 0; (inStr[s] != '\0') && !isspace(inStr[s]) && (inStr[s] != '='); s++) {
		field[f++] = inStr[s];
	}

	/* Skip spaces after field name */
	while (isspace(inStr[s])) {
		s++;
	}
	/* If it is not a field, then just return it. */
	if (inStr[s] != separator) {
		return (inStr + s);
	}
	if (inStr[s]=='\0') {
		return (NULL);
	}
	/* Skip '=' */
	s++;

	/* Skip spaces after '=' */
	while (isspace(inStr[s])) {
		s++;
	}
	if (inStr[s]=='\0') {
		return (NULL);
	}

	gotQuote = '\0';
	if ((inStr[s]=='\'') || (inStr[s]=='"')) {
		gotQuote = inStr[s];
		s++; /* skip quote */
		if (inStr[s]=='\0') {
			return (NULL);
		}
	}

	if (gotQuote) {
		for (; (inStr[s] != '\0') && (inStr[s] != gotQuote); s++) {
			if (inStr[s] == '\\') {
				value[v++] = inStr[++s];
			} else {
				value[v++]=inStr[s];
			}
		}
	} else {
		/* if it gets here, then there is no quote */
		for (; (inStr[s] != '\0') && !isspace(inStr[s]); s++) {
			if (inStr[s]=='\\') {
				value[v++]=inStr[++s];
			} else {
				value[v++]=inStr[s];
			}
		}
	}
	/* Skip spaces */
	while (isspace(inStr[s])) {
		s++;
	}

	return (inStr+s);
} /* getFieldValue */

/**
 parseLicenseList
 \brief parse the comma separated list of license names found
 Uses cur.compLic and sets cur.licenseList

 void?
 */

void parseLicenseList() {

	int numNames = 0;
	char *saveptr;

	/* check for a single name */
	if (strstr(cur.compLic, ",") == NULL_CHAR) {
		cur.licenseList[numNames] = &cur.compLic;
	}
	if (cur.compLic == NULL_CHAR) {
		return;
	}

	cur.licenseList[numNames] = strtok_r(cur.compLic, ",", &saveptr);

	/* printf("DB: PLL: after first strtoc_r call, name is:%s\n",cur.licenseList[numNames]); */

	while (cur.licenseList[numNames] != NULL) {
		numNames++;
		cur.licenseList[numNames] = strtok_r(NULL, ",", &saveptr);
	}
	/*
	 printf("DB: name:%s\n",cur.licenseList[i]);
	 for(i=0; i < numNames; i++) {
	 printf("DB: name:%s\n",cur.licenseList[i]);
	 }
	 */
	return;
} /* parseLicenseList */

/**
 parseSchedInput
 \brief Convert input pairs from the scheduler into globals.

 @param char *s a string that should contain foo = bar type substrings

 sets cur.pFileFk and cur.pFile in the curScan structure

 return void

 */
void parseSchedInput(char *s) {
	char field[256];
	char value[1024];
	int gotOther=0;
	char *origS;

#ifdef	PROC_TRACE
	traceFunc("== parseSchedInput(%s)\n", s);
#endif	/* PROC_TRACE */

	cur.pFileFk = -1;
	memset(cur.pFile, '\0', myBUFSIZ);
	if (!s) {
		return;
	}
	origS = s;

	while (s && (s[0] != '\0')) {
		s = getFieldValue(s, field, 256, value, 1024, '=');
		printf("   LOG: nomos got field = value %s = %s\n", field, value); /* DEBUG */
		if (value[0] != '\0') {
			if (!strcasecmp(field, "pfile_pk")) {
				cur.pFileFk = atol(value);
			} else if (!strcasecmp(field, "pfilename")) {
				strncpy(cur.pFile, value, sizeof(cur.pFile));
			} else {
				printf("   LOG: got other:%s\n", value); /* DEBUG */
				gotOther = 1;
			}
		}
	}

	if (gotOther || (cur.pFileFk < 0) || (cur.pFile[0]=='\0')) {
		printf("   FATAL: Data is in an unknown format.\n");
		printf("   LOG: Unknown data: '%s'\n", origS);
		printf("   LOG: Nomos agent is exiting\n");
		fflush(stdout);
		DBclose(gl.DB);
		exit(-1);
	}
} /* parseSchedInput */

void Usage(char *Name) {
	printf("Usage: %s [options] [file [file [...]]\n", Name);
	printf("  -i   :: initialize the database, then exit.\n");
	printf("  -d   :: turn on debugging to a logfile (NomosDebugLog)\n");
	/*    printf("  -v   :: verbose (-vv = more verbose)\n"); */
	printf("  file :: if files are listed, print the licenses detected within them.\n");
	printf("  no file :: process data from the scheduler.\n");
} /* Usage() */

void Bail(int exitval) {
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

	printf("   LOG: Nomos agent is exiting\n");
	fflush(stdout);
	DBclose(gl.DB);

	exit(exitval);
}

void alreadyDone(char *pathname) {
	fprintf(stderr, "%s: %s already processed\n", gl.progName, pathname);
	Bail(0);
}

static void setOption(int val) {
#ifdef	PROC_TRACE
	traceFunc("== setOption(%x)\n", val);
#endif	/* PROC_TRACE */
	gl.progOpts |= val;
	return;
} /* alreadyDone */

/**
 checkRefLicense
 \brief check the reference license data for a license match.

 @param char *licenseNames[]

 @return rf_pk of the matched license or -1

 \todo may need to compute the md5 of the text found instead of using the
 rf_shortname

 */

int checkRefLicense(char *licenseName) {

	PGresult *result;

	char query[myBUFSIZ];
	char *pqCkResult;

	int rfFk = -1;
	int numRows = 0;

	if(strlen(licenseName) == 0) {
		printf("ERROR! checkRefLicense, empty name: %s\n", licenseName);
		return(-1);
	}

	/* will use the hash, for now just look in the db. */

	sprintf(
			query,
			"SELECT rf_pk, rf_shortname FROM license_ref WHERE rf_shortname = '%s';",
			licenseName);
	printf("   DB: CKRefLic: query is:\n%s\n", query);

	result = PQexec(gl.pgConn, query);
	pqCkResult = checkPQresult(result);
	if (pqCkResult != NULL_CHAR) {
		printf(
				"   ERROR: Nomos agent got database error getting ref license name: %s\n",
				pqCkResult);
		return (-1);
	}
	numRows = PQntuples(result);
	/* no match */
	printf("   DB: rows returned by query were: %d\n", numRows);
	if(numRows == 0) {
		printf("   LOG: NOTICE! License name: %s not found in Reference Table\n",
				licenseName);
		return(-1);
	}
	/* found one, return key */
	else {
		/* printf("DB: CKREFLIC: converting rfFk\n"); */
		rfFk = atoi(PQgetvalue(result, 0, 0));
		/* printf("DB: CKREFLIC: returning rfFk: %d\n", rfFk); */
		return(rfFk);
	}
} /* checkRefLicense */

static void unsetOption(int val) {
#ifdef	PROC_TRACE
	traceFunc("== unsetOption(%x)\n", val);
#endif /* PROC_TRACE */
	gl.progOpts &= ~val;
	return;
} /* unsetOption */

int optionIsSet(int val) {
#ifdef	PROC_TRACE
	traceFunc("== optionIsSet(%x)\n", val);
#endif	/* PROC_TRACE */

	return (gl.progOpts & val);
} /* optionIsSet */

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

static void printListToFile(list_t *l, char *filename, char *mode) {
	FILE *fp;
	item_t *ip;

	fp = fopenFile(filename, mode);
	while ((ip = listIterate(l)) != NULL_ITEM) {
		fprintf(fp, "%s\n", ip->str);
	}
	(void) fclose(fp);
	return;
}

/**
 getFileLists
 \brief Initialize the lists: regular-files list cur.regfList and buffer-offset
 list cur.offList.

 \todo CDB - Could probably roll this back into the main processing
 loop or just put in a generic init func that initializes *all*
 the lists.

 */

static void getFileLists(char *dirpath) {
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
} /* getFileLists */

/**
 * getReferenceLicenses
 * \brief Get the reference licenses out of the database table license_ref.
 *
 * Stores the reference licenese in ?? (define data structure).
 */
int getReferenceLicenses() {
	return (TRUE);
}

/**
 * updateLicenseFile
 * \brief, insert rf_fk, agent_fk and pfile_fk into license_file table
 *
 * @param long rfPK the reference file foreign key
 *
 * returns boolean (True or False)
 */
int updateLicenseFile(long rfPk) {

	PGresult *result;
	char query[myBUFSIZ];

	if (rfPk <= 0) {
		return (FALSE);
	}
	printf("UPLicFile: updating license_file\n");
	sprintf(query,
			"INSERT INTO license_file(rf_fk, agent_fk, pfile_fk) VALUES(%ld, %d, %ld)",
			rfPk, gl.agentPk, cur.pFileFk);

	result = PQexec(gl.pgConn, query);

	if (PQresultStatus(result) != PGRES_COMMAND_OK) {
		printf("   ERROR: Nomos agent got database error, insert of license_file: %s\n",
				PQresultErrorMessage(result));
		PQclear(result);
		return (FALSE);
	}
	printf("UPLicFile: returning true\n");
	return (TRUE);
} /* updateLicenseFile */

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

	printf("   LOG: nomos scanning file %s.\n", fileToScan); /* DEBUG */

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
	changeDir(cur.targetDir); /* see if we can chdir to the target */
	getFileLists(cur.targetDir);
	changeDir(gl.initwd);
	listInit(&cur.fLicFoundMap, 0, "file-license-found map");
	listInit(&cur.parseList, 0, "license-components list");
	listInit(&cur.lList, 0, "license-list");
	listInit(&cur.cList, 0, "copyright-list");
	listInit(&cur.eList, 0, "eula-list");

	processRawSource();

	freeAndClearScan(&cur);
} /* Process File */

/**
 recordScanToDb

 Write out the information about the scan to the FOSSology database.

 curScan is passed as an arg even though it's available as a global,
 in order to facilitate subsequent modularization of the code.

 Returns: 0 if successful, -1 if not.

 \todo need to insert results of the analysis.
 */
int recordScanToDB(struct curScan *scanRecord) {

	PGresult *result;

	char query[myBUFSIZ];
	char *pqCkResult;
	char *noneFound;
	char *tname;

	long numrows;
	long numcols;
	long rfFk;
	long rf_pk;


	/*
	 * need to check for None and then add the appropriate items to license_file
	 * (e.g. rf_pk, agent_fk, and pfile_fk).
	 */
	noneFound = strstr(scanRecord->compLic, LS_NONE);
	if (noneFound != NULL_CHAR) {
		/* no license found */
		printf("   DB: No license found\n");
		sprintf(query, "SELECT rf_pk, rf_shortname FROM license_ref WHERE "
			"rf_shortname = 'No License Found';");
		/* printf("   DB: query was:%s\n", query); */

		result = PQexec(gl.pgConn, query);
		pqCkResult = checkPQresult(result);
		if (pqCkResult != NULL_CHAR) {
			printf(
					"   ERROR: Nomos agent got database error getting No License Found: %s\n",
					pqCkResult);
			return (-1);
		}
		numrows = PQntuples(result);
		printf(
				"   LOG: nomos:number of row from query for no lice found is:%ld\n",
				numrows);
		numcols = PQnfields(result);
		printf(
				"   LOG: nomos:number of columns from query for no lice found is:%ld\n",
				numcols);

		rf_pk = PQgetvalue(result, 0, 0);
		printf("   LOG: value of tup0, field0 (rf_pk) is:%ld\n", rf_pk);
		tname = PQgetvalue(result, 0, 1);
		printf("   LOG: value of tup0, field1 (rf_sn) is:%s\n", tname);

		if (updateLicenseFile(rf_pk)) {
			return (0);
		} else {
			return (-1);
		}
	} /* No license found */

	/* Do we match a reference license?
	 * for now just query the table.  Need to create a routine that gets the table
	 * and stores it in a either a hash for faster lookup.
	 */

	/* we have one or more license names, parse them */

	printf("   DB: parsing license names\n");
	parseLicenseList();

	int numLicenses;
	for (numLicenses = 0; cur.licenseList[numLicenses] != NULL; numLicenses++) {
		printf("processing cur.licenseList[%d]:%s\n",numLicenses, cur.licenseList[numLicenses]);

		int nameLen;
		nameLen = strlen(cur.licenseList[numLicenses]);
		printf("   DB: length of license name found is:%d",nameLen);

		rfFk = checkRefLicense(cur.licenseList[numLicenses]);
		printf("rfFk returned from checkRefLic is:%ld\n", rfFk);

		if (rfFk == -1) {
			printf("   DB: adding %s license to the reference table.\n",
					cur.licenseList[numLicenses]);
			if (!addNewLicense(cur.licenseList[numLicenses])) {
				printf("   LOG: FAILURE! could not add new license %s to ref table\n",
						cur.licenseList[numLicenses]);
				return (-1);
			}
			/* get ref lic pk, better be there! */
			rfFk = checkRefLicense(cur.licenseList[numLicenses]);
			if (rfFk == -1) {
				printf("FATAL: could not get rf_fk from just added license %s\n",
					cur.licenseList[numLicenses]);
				return(-1);
			}
			 /* updateLicenseFile */
			if(updateLicenseFile(rfFk) == FALSE){
				return(-1);
			}
		}
		else {

			/*
			 * use rfFk set above and updateLicenseFile
			 */
			/*
			 NOTE: this WILL NOT WORK in cli mode, as written!  There is no pfile_fk
			 when run from the cli.  Need to special case it and go get the pfile_pk
			 associated with the ?? wait, if run from the command line, it's not in
			 the repo... I will propose for 1.2 that we don't update DB in cli mode
			 that matches the current behavior today.  Next release we fix it.
			 */
			printf(" RS2DB: updating LicenseFile with fk and text\n");
			if(updateLicenseFile(rfFk) == FALSE) {
				printf(" RS2DB: updateLicenseFile failed on the found license (last)\n");
				return (-1);
			}
		}

	} /* for */
} /* recordScanToDb */

int main(int argc, char **argv) {
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

	/*
	 Set up variables global to the agent. Ones that are the
	 same for all scans.
	 */
	gl.DB = DBopen();
	if (!gl.DB) {
		printf("   FATAL: Nomos agent unable to connect to database, exiting...\n");
		fflush(stdout);
		exit(-1);
	}

	/* MD: move the call the GetAgentKey to the -i code? does that cause other
	 * issues?
	 */

	gl.agentPk = GetAgentKey(gl.DB, basename(argv[0]), 0, SVN_REV, agent_desc);
	gl.pgConn = DBgetconn(gl.DB);

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

	/* MD: if you keep -d, then this code needs fixing as it doesn't leave argc
	 * in the correct state (number of args).
	 */
	while ((c = getopt(argc, argv, "id")) != -1) {

		printf("start of while; argc is:%d\n",argc);
		 /* for(i=0; i<argc; i++){
			printf("args passed in:%s\n",argv[i]);
		}*/
		switch (c) {
		case 'i':
			/* "Initialize" */
			DBclose(gl.DB); /* DB was opened above, now close it and exit */
			exit(0);
		case 'd':
			/* turn on the debug log and set debug flag, useful for debugging
			 * with the scheduler
			 */
			mdDebug = 1;
			argc--;
			++argv;
            break;
		default:
			Usage(argv[0]);
			DBclose(gl.DB);
			exit(-1);
		}
	}


	/*
	 Copy filename args (if any) into array
	 */
	/*
	printf("after parse args, argc is:%d\n",argc);
	for (i = 1; i < argc; i++) {
		printf("argv's are:%s",argv[i]);
		files_to_be_scanned[i-1] = argv[i];
		file_count++;
	}
	*/

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

		 \todo need to add:
		 1. insert into agent_runstatus that we have started: agent_pk and
		 upload_fk, ars_ts? (ask bob)
		 2. need to complete recordScanToDb
		 3. need to insert into agent_runstatus:
		 - if complete (no error) set ars_complete to true
		 - if setting ars_complete, set ars_ts
		 - Error? set ars_status with the error text.
		 */
		printf("   LOG: nomos agent starting up in scheduler mode....\n"); /* DEBUG */
		schedulerMode = 1;
		signal(SIGALRM, ShowHeartbeat);
		printf("OK\n");
		fflush(stdout);
		alarm(60);

		while (ReadLine(stdin, parm, myBUFSIZ) >= 0) {
			printf("    LOG: nomos read %s\n", parm);
			if (parm[0] != '\0') {
				/*
				 Get the file arg and go ahead and process it
				 */
				parseSchedInput(parm);
				repFile = RepMkPath("files", cur.pFile);
				if (!repFile) {
					printf("   FATAL: pfile %ld Nomos unable to open file %s\n",
							cur.pFileFk, cur.pFile);
					fflush(stdout);
					DBclose(gl.DB);
					exit(-1);
				}
				/* createAgentStatus(); */
				processFile(repFile);
				recordScanToDB(&cur);
				freeAndClearScan(&cur);
				/* recordAgentStatus(); */
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
		 Copy filename args (if any) into array
		 */


		/*
		 For each file to be scanned
		 */
		for (i = 0; i < file_count; i++) {
			processFile(files_to_be_scanned[i]);
			/** \todo remove the call to recordScanToDb */
			/* recordScanToDB(&cur); */
			freeAndClearScan(&cur);
		}
	}
	Bail(0);
}

