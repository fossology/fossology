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

#include "nomos.h"
#include "process.h"
#include "licenses.h"
#include "util.h"
#include "list.h"

#ifdef	MEMSTATS
extern void memStats();
#endif	/* MEMSTATS */

#define	BOGUS_MD5	"wwww0001xxxx0002yyyy0004zzzz0008"


static void processNonPackagedFiles()
{
    item_t *p;

#if	defined(PROC_TRACE) || defined(UNPACK_DEBUG)
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== processNonPackagedFiles()\n");
#endif  /* PROC_TRACE || UNPACK_DEBUG */

    /*
     * If there are unused/unreferenced source archives, they need to be
     * processed indivudually.  Create the global 'unused archives' list
     * and hand it off to be processed.
     */
#ifdef	UNPACK_DEBUG
    listDump(&gl.sarchList, NO);
    listDump(&gl.regfList, NO);
#endif	/* UNPACK_DEBUG */
	/* CDB
	   if (!isDIR(pathname)) {
	   makePath(pathname);
	   }
	*/
    /*
      CDB - I think we want to try and get rid of gl.sarchlist cause
      I believe it is not used.
    */
    if (gl.sarchList.used + gl.regfList.used == 0) {
	printf("No-data!\n");
	return;
    }
    if (gl.regfList.used) {
	*cur.basename = NULL_CHAR;
	processRegularFiles();
	p = listGetItem(&gl.fLicFoundMap, BOGUS_MD5);
	p->buf = copyString(cur.compLic, MTAG_COMPLIC);
	p = listGetItem(&gl.licHistList, cur.compLic);
	p->refCount++;
	p = listAppend(&gl.sarchList, cur.basename);
	p->buf = copyString(BOGUS_MD5, MTAG_MD5SUM);
	listSort(&gl.sarchList, SORT_BY_ALIAS);
    }
    return;
}



#ifdef DEAD_CODE
/*
 * Remove the line at textp+offset from the buffer
 */
void stripLine(char *textp, int offset, int size)
{
    char *start, *end;
    extern char *findBol();

#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== stripLine(%s, %d, %d)\n", textp, offset, size);
#endif	/* PROC_TRACE */
    /* */
    if ((end = findEol((char *)(textp+offset))) == NULL_STR) {
	Assert(NO, "No EOL found!");
    }
    if ((start = findBol((char *)(textp+offset), textp)) == NULL_STR) {
	Assert(NO, "No BOL found!");
    }
#ifdef	DEBUG
    printf("Textp %p start %p end %p\n", textp, start, end);
    printf("@START(%d): %s", strlen(start), start);
    printf("@END+1(%d): %s", strlen(end+1), end+1);
#endif	/* DEBUG */
    if (*(end+1) == NULL_CHAR) {	/* EOF */
	*start = NULL_CHAR;
    }
    else {
#ifdef	DEBUG
	printf("MOVE %d bytes\n", size-(end-textp));
#endif	/* DEBUG */
	(void) memmove(start, (char *)(end+1),
		       (size_t)((size)-(end-textp)));
    }
#ifdef	DEBUG
    printf("FINISH: @START(%d): %s", strlen(start), start);
#endif	/* DEBUG */
    return;
}
#endif /* DEAD_CODE */


void processRawSource()
{
    /*    char targetdir[myBUFSIZ] = "."; CDB ?? Trying this out */

#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== processRawSource()\n");
#endif	/* PROC_TRACE */

    changeDir(gl.target);

#ifdef	PACKAGE_DEBUG
    listDump(&gl.sarchList, NO);
    listDump(&gl.regfList, NO);
#endif	/* PACKAGE_DEBUG */

    (void) strcpy(cur.name, "(no package name)");
    /* CDB	processNonPackagedFiles(targetdir, NO); */
    processNonPackagedFiles();
    return;
}

/*
  Process a list of regular files.

  CDB - This really isn't a list, there should only be a single file in
  regfList. 
*/
void processRegularFiles()
{
#ifdef  PROC_TRACE
    traceFunc("== processRegularFiles()\n");
#endif  /* PROC_TRACE */
    
#ifdef notdef
    if (isDIR(RAW_DIR)) {	/* temp-unpack dir exists? */
	removeDir(RAW_DIR);	/* clean up */
    }
    makeDir(RAW_DIR);
    changeDir(RAW_DIR);
#endif notdef
    (void) strcpy(cur.ptype, "***");
    (void) sprintf(cur.basename, "%s-misc-files", gl.prodName);
    (void) sprintf(cur.pathname, "%s/(Various)", gl.target);
    /* loop through the list here -- and delete files with link-count >1? */
    licenseScan(&gl.regfList);
    return;
}

