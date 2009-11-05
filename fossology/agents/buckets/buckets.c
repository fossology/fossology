/*
 * (C) Copyright 2006 Hewlett-Packard Development Company, L.P.
 */
#include "nomos.h"
#include "_autodefs.h"

#define	EXT_DEFAULT	-1
#define	EXT_STRING	0
#define	EXT_REGEX	1
#define	EXT_STR_FILE	2
#define	EXT_PATT_FILE	3
#define	EXT_COMP_FILE	4
#define	EXT_SCRIPT	5
#define	EXT_EXEC	6
#define	EXT_APPR_MASK	0x30
#define	EXT_APPR_YES	0x20
#define	EXT_APPR_NO	0x10

static int checkLicenseComponents(), isBoring();
static char *nextUncommentedLine();
static char cbuf[myBUFSIZ];
/* */
int parseConfFile(), validateTools(), parseExtensibleBuckets();
void initExtensibleBuckets(), cleanExtensibleBuckets();
list_t *extensibleBucket();
/* */
extern struct globals gl;
extern struct curPkg cur;
extern lic_t licText[];
/* */
extern char *getenv(), *mmapFile(), *findEol(), *copyString();
extern void Bail(), Fatal(), Error(), listInit(), listClear(),
    munmapFile(), highlight(), dumpFile(), changeDir();
extern int strcasecmp(), atoi(), isFILE(), mySystem();
extern FILE *fopenFile();
extern item_t *listAppend(), *listIterate();

int validateTools()
{
	register item_t *dp, *tp;
	register int i, j, rem;
	register char *env, *start, *end, **ap;
	list_t dList, tList;
	FILE *fp;
	char pathBuf[myBUFSIZ];
	char *eTools[] = {"sh", "ar", "tar", "cpio", "md5sum", "cp", "ls",
	    "rm", "mv", "grep", "egrep", "cat", "rpm", "rpm2cpio", "zcat",
	    "bzcat", "file", "unshar", "mount", "umount", "zipinfo", "unzip",
	    "unrar", "jar", "uudecode", "expect", "sed", "strings",
#ifdef	USE_DPKG_SOURCE
	    "dpkg-source",
#endif	/* USE_DPKG_SOURCE */
#ifdef	USE_PAX 
	    "pax",
#endif	/* USE_PAX */
#ifdef	CONVERT_PS 
	    "pstotext",
#endif	/* CONVERT_PS */
	    NULL_STR};
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== validateTools()\n");
#endif	/* PROC_TRACE */
	listInit(&dList, 0, "directory list");
	listInit(&tList, 0, "tools list");
	if ((env = getenv("PATH")) == NULL_STR) {
		Fatal("No $PATH variable set in environment");
	}
#ifdef	DEBUG
	printf("PATH=%s\n", env);
#endif	/* DEBUG */
	for (start = env; start; start = end+1) {
		if ((end = strchr(start, ':')) != NULL_STR) {
			*end = NULL_CHAR;
		}
		(void) listAppend(&dList, start);
		if (end == NULL_STR) {
			break;
		}
		*end = ':';
	}
#ifdef	DEBUG
	listDump(&dList, NO);
#endif	/* DEBUG */
/*
 * For the Hartford, we also validate the tool we're running; we do this
 * so we know the pathname for if/when we need to remove the code (upon
 * the beta-agreement expiring
 */
#ifdef	CUSTOMER_VERSION
	(void) listAppend(&tList, gl.progName);
#endif	/* CUSTOMER_VERSION */
 	for (ap = eTools; *ap; *ap++) {
		(void) listAppend(&tList, *ap);
	}
#ifdef	DEBUG
	listDump(&tList, NO);
#endif	/* DEBUG */
/*
 * Search for each tool in each directory.  The theory here is that it's
 * more efficient for the outer-most loop to be directory names since we
 * will thrash the filesystem buffer cache _less_ with the inner-most loop
 * searching for different tools in each dir.
 */
	rem = tList.used;
#ifdef	USE_UNUNPACK
	gl.hasUnunpack = gl.hasAntiWord = -1;
#else	/* not USE_UNUNPACK */
	gl.hasAntiWord = -1;
#endif	/* not USE_UNUNPACK */
	while ((dp = listIterate(&dList)) != NULL_ITEM) {
		while ((tp = listIterate(&tList)) != NULL_ITEM) {
			if (tp->foundTool) {			/* found? */
				continue;
			}
			(void) sprintf(pathBuf, "%s/%s", dp->str, tp->str);
			if (!isFILE(pathBuf)) { /* exists? */
				continue;
			}
			if (gl.stbuf.st_mode & _EXECUTE_ANY) {	/* executable */
#ifdef	CUSTOMER_VERSION
				if (strcmp(tp->str, gl.progName) == 0) {
					strcpy(gl.fullPath, dp->str);
					strcat(gl.fullPath, "/");
					strcat(gl.fullPath, tp->str);
				}
#endif	/* CUSTOMER_VERSION */
				tp->foundTool++;
				rem--;
				continue;
			}
		
		}
/*
 * Hacks: add "antiword" ands "ununpack" to the list, but it's NOT fatal
 * if they doesn't exist.  Just note the test-result in global variables.
 */
		(void) sprintf(pathBuf, "%s/antiword", dp->str);
 		if (gl.hasAntiWord < 0 && isFILE(pathBuf)) {
			gl.hasAntiWord = (gl.stbuf.st_mode & _EXECUTE_ANY);
		}
#ifdef	USE_UNUNPACK
		(void) sprintf(pathBuf, "%s/ununpack", dp->str);
 		if (gl.hasUnunpack < 0 && isFILE(pathBuf)) {
			gl.hasUnunpack = (gl.stbuf.st_mode & _EXECUTE_ANY);
		}
#endif	/* USE_UNUNPACK */
	}
	if (rem) {
		(void) sprintf(cbuf, "Tools not in $PATH: ");
		for (i = j = 0, tp = tList.items; i < tList.used; i++, tp++) {
			if (tp->foundTool) {
				continue;
			}
			if (j++) {
				(void) strcat(cbuf, ", ");
			}
			(void) strcat(cbuf, tp->str);
		}
		Fatal(cbuf);
	}
	listClear(&tList, YES);
	listClear(&dList, YES);
#ifdef	USE_PAX
	if (!isFILE(PAXSCRIPT)) {
		fp = fopenFile(PAXSCRIPT, "w");
		fprintf(fp, "#!/bin/sh\nexport PAXFILE=$1\nexpect <<@EOF@\n");
		fprintf(fp, "set timeout 0\nspawn pax -rukf $PAXFILE\n");
		fprintf(fp, "expect {\n\t\"End of archive\" {\n");
		fprintf(fp, "\t\texpect \"volume change required\"\n");
		fprintf(fp, "\t\texpect \"> \"\n\t\tsend \".\\r\"\n");
		fprintf(fp, "\t\texp_continue\n\t}\n}\n@EOF@\nexit $?\n");
		(void) fclose(fp);
		chmodInode(PAXSCRIPT, 0755);
	}
#endif	/* USE_PAX */
	return(1);
}

/* 
 * OPTIMIZE-ME: instead of reading one line a time with stdio, now that
 * we have strGrep() and idxGrep() working, we COULD just look for the
 * string we want and go directly to it.
 */
int parseConfFile(char *patt)
{
	FILE *fp;
	int i = 0, ret = 1;
	size_t len;
	char *cp1, *cp2, target[myBUFSIZ];
	void confFileFormatError();
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== parseConfFile(\"%s\")\n", patt);
#endif	/* PROC_TRACE */
/* */
	if (*patt == NULL_CHAR) {
		Assert(YES, "NULL search pattern for configuration file");
	}
	fp = fopenFile(gl.confPath, "r");
	(void) strcpy(target, patt);
	(void) strcat(target, "\t");
	len = strlen(target);
/*
 * FIX-ME: now that mmapFile() and strGrep() work, the stdio stuff
 * should be replaced with a regex-search in memory.
 */
	for (i = 1; fgets(cbuf, (int) myBUFSIZ, fp) != NULL_STR; i++) {
		if (*cbuf == '#') {
			continue;
		}
		cp2 = cbuf;
		while (*cp2 == ' ' || *cp2 == '\t') {
			cp2++;
		}
		if (strncmp(cp2, target, (size_t) len) == 0) {
			cp1 = cp2+len;
			*(cp1-1) = NULL_CHAR;
			while (*cp1 == '\t') {
				cp1++;
			}
			if ((cp2 = strchr(cp1, '\t')) == NULL_STR) {
				confFileFormatError(i);
			}
			*cp2++ = NULL_CHAR;
			/* gl.vendor = cp1; */
			(void) strcpy(gl.vendor, cp1);
			while (*cp2 == '\t') {
				cp2++;
			}
			if ((cp1 = strchr(cp2, '\t')) == NULL_STR) {
				confFileFormatError(i);
			}
			*cp1++ = NULL_CHAR;
			if (strcasecmp(cp2, "none") == 0 ||
			    strcasecmp(cp2, "null") == 0) {
				*gl.refVendor = NULL_CHAR;
			} 
			else {
				/* gl.refVendor = cp2; */
				(void) strcpy(gl.refVendor, cp2);
			}
			while (*cp1 == '\t') {
				cp1++;
			}
			if ((cp2 = findEol(cp1)) == NULL_STR) {
				confFileFormatError(i);
			}
			*cp2++ = NULL_CHAR;
			if (strlen(cp1) == 0) {
				confFileFormatError(i);
			}
			/* gl.prod = cp1; */
			(void) strcpy(gl.prod, cp1);
			ret = 0;
			break;
		}
	}
	(void) fclose(fp);
	return(ret);
}

void confFileFormatError(int n)
{
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== confFileFormatError(%d)\n", n);
#endif	/* PROC_TRACE */
	Fatal("Format error, line %d", n);
}

int parseExtensibleBuckets()
{
	register item_t *p, *ip;
	register list_t *l;
	register char *textp, *cur, *cp, *x, *y, save;
	int hasFile, hasString, hasDefault = 0, seq = 0, bType,
	    pOrder, pNext = 1;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== parseExtensibleBuckets()\n");
#endif	/* PROC_TRACE */
/* */
	cur = textp = mmapFile(gl.bSpec);
	if (textp == NULL_STR) {
		return(0);
	}
#if	0
	while (strGrep("^BUCKET:", cur, REG_NEWLINE|REG_ICASE))
#endif
	while (strGrep("^(BUCKET|BUCKET-(GREEN|RED|YELLOW|BROWN|PURPLE|URL)):",
	    cur, REG_NEWLINE|REG_ICASE|REG_EXTENDED)) {
#ifdef	BUCKET_DEBUG
		register char *colorName;
#endif	/* BUCKET_DEBUG */
	    	pOrder = 0;
/*
 * Find the name of the bucket referenced
 */
		hasFile = hasString = 0;
		y = cur+gl.regm.rm_so;
		if (strncasecmp(y, "BUCKET-GREEN", 12) == 0) {
			bType = BT_GREEN;
#ifdef	BUCKET_DEBUG
			colorName = "Green";
#endif	/* BUCKET_DEBUG */
		}
		else if (strncasecmp(y, "BUCKET-YELLOW", 13) == 0) {
			bType = BT_YELLOW;
#ifdef	BUCKET_DEBUG
			colorName = "Yellow";
#endif	/* BUCKET_DEBUG */
		}
		else if (strncasecmp(y, "BUCKET-RED", 10) == 0) {
			bType = BT_RED;
#ifdef	BUCKET_DEBUG
			colorName = "Red";
#endif	/* BUCKET_DEBUG */
		}
		else if (strncasecmp(y, "BUCKET-URL", 10) == 0) {
			bType = BT_URL;
#ifdef	BUCKET_DEBUG
			colorName = "(URL)";
#endif	/* BUCKET_DEBUG */
		}
		else if (strncasecmp(y, "BUCKET-BROWN", 12) == 0) {
			bType = BT_BROWN;
#ifdef	BUCKET_DEBUG
			colorName = "Brown";
#endif	/* BUCKET_DEBUG */
		}
		else if (strncasecmp(y, "BUCKET-PURPLE", 13) == 0) {
			bType = BT_PURPLE;
#ifdef	BUCKET_DEBUG
			colorName = "Purple";
#endif	/* BUCKET_DEBUG */
		}
		else {
			bType = BT_PLAIN;
#ifdef	BUCKET_DEBUG
			colorName = "Plain";
#endif	/* BUCKET_DEBUG */
		}
		cp = cur+gl.regm.rm_eo;
		x = findEol(cp);
		*x = NULL_CHAR;
		while (isspace(*cp)) {
			cp++;
		}
/*
 * FIX-ME: we don't check for duplicately-listed buckets yet.  Should we
 * just insert into a sorted list (e.g., call listGetItem()) instead?
 */
		p = listAppend(&gl.bucketList, cp);
#ifdef	BUCKET_DEBUG
		printf("DEBUG: bucket name \"%s\", color %d (%s)\n", p->str,
		    bType, colorName);
#endif	/* BUCKET_DEBUG */
		*x = '\n';
/*
 * Now find the next non-commented line to see if the bucket is a FILE
 * (containing regexes) or a REGEX
 */
		x = nextUncommentedLine(x);
		if (*x == NULL_CHAR) {
			Fatal("No type/order (bucket \"%s\")", p->str);
		}
/*
 * Specifying the print order comes between the BUCKET- line and
 * the specification of the bucket type
 */
		if (strncasecmp(x, "PRINT:", 6) == 0) {
			cp = x+6;
			if ((pOrder = atoi(cp))) {
				if (pOrder >= pNext) {
					pNext = pOrder+1;
				}
			}
			x = nextUncommentedLine(findEol(cp));
		}
/*
 * Now determine what TYPE of bucket this is.  Options are:
 * DEFAULT (the default destination, can only be specified ONCE)
 * STR-FILE (test entire license against strings in a file, match ENTIRE line)
 * PATT-FILE (test entire license against regex list in a file)
 * COMP-FILE (test license components against lines in a file)
 * SCRIPT (execute shell-script, passed env-vars describing license)
 * EXEC (execute program, passed env-vars describing license)
 * REGEX (test entire license against a regex)
 * STRING (compare entire license against a string)
 */
		if (strncasecmp(x, "DEFAULT", 7) == 0) {
			if (++hasDefault > 1) {
				Fatal("Only 1 \"default\" bucket is allowed");
			}
			p->seqNo = 1024;	/* WAY at bottom of list */
			p->bucketType = EXT_DEFAULT;
			cur = x+7;
			continue;
		}
		else if (strncasecmp(x, "STR-FILE:", 9) == 0) {
			hasFile = 1;
			p->seqNo = seq++;
			p->bucketType = EXT_STR_FILE;
			cp = x+9;
		}
		else if (strncasecmp(x, "PATT-FILE:", 10) == 0) {
			hasFile = 1;
			p->seqNo = seq++;
			p->bucketType = EXT_PATT_FILE;
			cp = x+10;
		}
		else if (strncasecmp(x, "COMP-FILE:", 10) == 0) {
			hasFile = 1;
			p->seqNo = seq++;
			p->bucketType = EXT_COMP_FILE;
			cp = x+10;
		}
		else if (strncasecmp(x, "SCRIPT:", 7) == 0) {
			hasFile = 1;
			p->seqNo = seq++;
			p->bucketType = EXT_SCRIPT;
			cp = x+7;
		}
		else if (strncasecmp(x, "EXEC:", 5) == 0) {
			hasFile = 1;
			p->seqNo = seq++;
			p->bucketType = EXT_EXEC;
			cp = x+5;
		}
		else if (strncasecmp(x, "REGEX:", 6) == 0) {
			hasString = 1;
			p->seqNo = seq++;
			p->bucketType = EXT_REGEX;
			cp = x+6;
		}
		else if (strncasecmp(x, "STRING:", 7) == 0) {
			hasString = 1;
			p->seqNo = seq++;
			p->bucketType = EXT_STRING;	/* already zero! */
			cp = x+7;
		}
		else if (strncasecmp(x, "BUCKET:", 7) == 0) {
			Fatal("Bucket \"%s\" is not defined\n\"", p->str);
		}
/*
 * The line following the BUCKET- marker *must* be a bucket type.  We know
 * the case of DEFAULT was handled above, so we've either got a bucket that
 * needs a file or a string/regex -- unless something funky happened (e.g.,
 * syntax error or something else strange).
 */
		if ((hasString+hasFile) == 0) {
			cp = findEol(x);
			*cp = NULL_CHAR;
			Fatal("Invalid data-source, line follows:\n\"%s\"",
			    p->str, x);
		}
/*
 * Add the file/regex spec to the list-item
 */
 		while (isspace(*cp)) {
			if (isEOL(*cp)) {
				Fatal("Null string invalid for %s (in \"%s\")",
				    hasFile ? "filename" : "regex/string",
				    p->str);
			}
			cp++;
		}
		x = findEol(cp);
		*x = NULL_CHAR;
/*
 * If filename was specified, it's a pathname relative to "$NOMOS_BASE/ext/"
 * and we should check now to see if it exists; furthermore, if it was
 * specified as an executable, it better have an execute bit set...
 */
		if (hasFile) {
			(void) sprintf(cbuf, "%s/%s", gl.extDir, cp);
			if (!isFILE(cbuf)) {
				Fatal("File \"%s\" does not exist", cbuf);
			}
/*
 * Calling access(2) as super-user doesn't necessarily get us the
 * results we're looking for (root can access _ANYTHING_).  Check the
 * permission bits on the file since we may run as a non-super-[l]user.
 */
			if (p->bucketType == EXT_EXEC &&
			    (gl.stbuf.st_mode & _EXECUTE_ANY) == 0) {
				Fatal("File \"%s\": no execute perms", cbuf);
			}
		} 
		p->buf = copyString(hasFile ? cbuf : cp, MTAG_LISTBUF);
/*
 * If it's a regex, try it to see if it compiles correctly - strGrep()
 * will fail on finding a regex-compile error.
 */
		if (p->bucketType == EXT_REGEX) {
			(void) strGrep(p->buf, cp, 0);
		}
		else if (strlen(p->buf) == 0) {
			Error("Provided string cannot be NULL");
			Fatal("Last bucket read: %s", p->str);
		}
		*x = '\n';
		cur = x;
/*
 * Create a list of regexes from a PATT-FILE
 */
		if (p->bucketType == EXT_PATT_FILE ||
		    p->bucketType == EXT_STR_FILE) {
			if ((cp = mmapFile(cbuf)) == NULL_STR) {
				Fatal("File \"%s\" has size of zero!", cbuf);
			}
			x = cp;
			l = (list_t *)memAlloc(sizeof(list_t), MTAG_LIST);
			listInit(l, 0, cbuf);
			while (*x) {
				y = findEol(x);
				*y = NULL_CHAR;
				ip = listAppend(l, x);
				x = y+1;
				while (*x && isspace(*x)) {
					x++;
				}
			}
			munmapFile(cp);
			p->buf = l;
#ifdef	BUCKET_DEBUG
			listDump(l, NO);
#endif	/* BUCKET_DEBUG */
		}
/*
 * Run the shell-script through "sh -n" as a syntax check
 */
 		else if (p->bucketType == EXT_SCRIPT) {
			(void) sprintf(cbuf, "%s/SYNTAX", gl.tmpdir);
			if (mySystem("%s %s > %s 2>&1", _REGEX(_TEST_SH),
			    p->buf, cbuf)) {
			    	dumpFile(stdout, cbuf, NO);
				Fatal("syntax error in %s", p->buf);
			}
		}
/*
 * See if the magic-string of an executable includes "executable"
 */
		else if (p->bucketType == EXT_EXEC) {
#ifdef	QA_CHECKS
			if (gl.mcookie == (magic_t) NULL) {
				Fatal("Magic cookie not initialized!");
			}
#endif	/* QA_CHECKS */
			(void) strcpy(cbuf, magic_file(gl.mcookie, p->buf));
			if (strGrep("executable", cbuf, REG_ICASE) == 0) {
				Fatal("file %s is not executable", p->buf);
			}
		}
/*
 * the contents of a COMP-FILE get placed in the p->buf element
 */
 		else if (p->bucketType == EXT_COMP_FILE) {
			cp = mmapFile(cbuf);
			p->buf = copyString(cp, MTAG_MMAPFILE);
			munmapFile(cp);
		}
		p->bucketType |= (bType << 8);
/*
 * Save both the testing-order and the print order
 */
		p->seqNo <<= 16;	/* testing order */
		p->seqNo += pOrder;	/* printing order */
	}
/*
 * If a DEFAULT was specified, it can occur anywhere and thus we have
 * to sort the bucket-list; else, the list is already 'right' since all 
 * tests are inserted in the order read from the file... HOWEVER, we
 * should listSort() it anyway just to have the right sort-type set in
 * the list_t structure.  It keeps things consistent and it's NOT that
 * expensive.
 */
	listSort(&gl.bucketList, SORT_BY_COUNT_ASC);
	initExtensibleBuckets(YES);
#ifdef	BUCKET_DEBUG
	printf("Address of gl.Bux: %p, sizeof list = %d\n", gl.Bux,
	    sizeof(list_t));
	while ((p = listIterate(&gl.bucketList)) != NULL_ITEM) {
		register int i;
		i = p-(gl.bucketList.items);
		printf("CALC: &Bux[%d] = %p\n", i, &gl.Bux[i]);
		listDump(&gl.Bux[i], -1);
	}
	listDump(&gl.bucketList, NO);
#endif	/* BUCKET_DEBUG */
	while ((p = listIterate(&gl.bucketList)) != NULL_ITEM) {
		ip = listAppend(&gl.printList, p->str);
		seq = p->seqNo & 0xffff;	/* printing-order */
		p->seqNo >>= 16;		/* reset testing-order */
		ip->seqNo = (seq ? seq : pNext++);
		ip->bList = (void *)&gl.Bux[p-gl.bucketList.items];
#ifdef	BUCKET_DEBUG
		printf("APPENDING: %p\n", ip->bList);
#endif	/* BUCKET_DEBUG */
	}
	listSort(&gl.printList, SORT_BY_COUNT_ASC);
#ifdef	BUCKET_DEBUG
	printf("AFTER normalizing print-order:\n");
	listDump(&gl.printList, NO);
#endif	/* BUCKET_DEBUG */
	return(gl.bucketList.used);
}

void initExtensibleBuckets(int initialSetup)
{
	register int i;
	register item_t *ip;
	register list_t *listp;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== initExtensibleBuckets(%d)\n", initialSetup);
#endif	/* PROC_TRACE */
/* */
	if (initialSetup) {
		gl.Bux = (list_t *)memAlloc(gl.bucketList.used*sizeof(list_t),
		    MTAG_BUCKET);
	}
	listp = gl.Bux;
	ip = gl.bucketList.items;
 	for (i = 0; i < gl.bucketList.used; i++, listp++, ip++) {
		if (initialSetup) {
			listp->desc = ip->bucketType >> 8;
			ip->bucketType &= 0xff;	/* type == bottom 8 bits */
			/*continue;*/
		}
		listInit(listp, 0, ip->str);
	}
	return;
}

void cleanExtensibleBuckets()
{
	register int i;
	register item_t *ip;
	register list_t *listp;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== cleanExtensibleBuckets()\n");
#endif	/* PROC_TRACE */
/* */
	listp = gl.Bux;
	ip = gl.bucketList.items;
 	for (i = 0; i < gl.bucketList.used; i++, listp++, ip++) {
		if (listp->used) {
			listClear(listp, YES);
		}
	}
	return;
}

static void putEnviron(char *varname, char *value)
{
	register char *e;
	register int i;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== putEnviron(\"%s\", \"%s\")\n", varname, value);
#endif	/* PROC_TRACE */
/* */
	i = strlen(varname)+2;
	if (value == NULL_STR) {
		value = "";
	}
	else {
		i += strlen(value);
	}
	e = memAlloc(i, MTAG_ENV);
	if (e == NULL_CHAR) {
		perror("memAlloc");
		exit(1);
	}
#ifdef	DEBUG
	printf("@Alloc(%d): %p\n", i, e);
#endif	/* DEBUG */
	if (*value) {
		(void) sprintf(e, "%s=%s", varname, value);
	}
	else {
		(void) sprintf(e, "%s=", varname);
	}
	if (putenv(e)) {
		perror(e);
		exit(1);
	}
#ifdef	DEBUG
	printf("putEnviron: %s\n", e);
#endif	/* DEBUG */
	return;
}

/* 
 * Child sets up environment -- here we allocate different strings for
 * environment variables and explicly DO NOT worry about freeing them.
 * This is due to the way putenv() works, coupled with the fact that
 * we're going to execl() another image anyway.
 */
static void childBucketEnviron(pattr_t *pp, int isPackage)
{
	register char *e;
	void putEnviron();
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== childBucketEnviron(%p, %d)\n", pp, isPackage);
#endif	/* PROC_TRACE */
/* */
#if	0
printf("... pp->paName = %s\n... pp->paLic = %s\n", pp->paName, pp->paLic);
	e = memAlloc(12+strlen(gl.basedir), MTAG_ENV);
#endif
	putEnviron("NOMOS_BASE", gl.basedir);
	putEnviron("NOMOS_NAME", pp->paName);
	putEnviron("NOMOS_LICENSE", pp->paLic);
#if	0
/* It might be desireable (some day) to allow access to the temp-dir */
	putEnviron("NOMOS_TMP", gl.tmpdir);
#endif
/*
 * Some of the environment depends on whether or not we're actually
 * processing a package
 */
	if (isPackage) {
		putEnviron("NOMOS_DIST", gl.prodName);
		putEnviron("NOMOS_VERS", pp->paVers);
		putEnviron("NOMOS_SRCVERS", pp->paSrcVers);
		putEnviron("NOMOS_SRCNAME", pp->paSrcName);
		putEnviron("NOMOS_PACKAGE", pp->paPkg);
		putEnviron("NOMOS_VENDOR", gl.vendor);
		putEnviron("NOMOS_PKGVEND", pp->paVendor);
		putEnviron("NOMOS_ISPKG", "y");
		putEnviron("NOMOS_TYPE", pp->paType);
	}
	else {
		putEnviron("NOMOS_DIST", NULL_STR);
		putEnviron("NOMOS_VERS", NULL_STR);
		putEnviron("NOMOS_PACKAGE", NULL_STR);
		putEnviron("NOMOS_VENDOR", NULL_STR);
		putEnviron("NOMOS_PKGVEND", NULL_STR);
		putEnviron("NOMOS_ISPKG", NULL_STR);
		putEnviron("NOMOS_TYPE", NULL_STR);
	}
	if (isBoring(pp->paLic)) {
		putEnviron("NOMOS_MINOR", "y");
	}
	else {
		putEnviron("NOMOS_MINOR", NULL_STR);
	}
	return;
}

list_t *extensibleBucket(pattr_t *pp, char *realName, int isPackage)
{
	register int i, j;
	register char *cp, *lic = pp->paLic;
	register item_t *p, *ip;
	register list_t *listp, *l;
	void childBucketEnviron();
	int ret;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== extensibleBucket(%p, \"%s\", %d)\n", pp, realName,
	    isPackage);
#endif	/* PROC_TRACE */
/* */
	listp = gl.Bux;
	p = gl.bucketList.items;
 	for (i = 0; i < gl.bucketList.used; i++, listp++, p++) {
/*
 * PATT-FILE -- file containing "grep" strings (test each regex in file)
 * This is implemented as a list of regex-strings to check
 */
		if (p->bucketType == EXT_PATT_FILE) {
			l = (list_t *)p->bList;
			ip = l->items;
			for (j = 0; j < l->used; j++, ip++) {
				if (strGrep(ip->str, lic,
				    REG_ICASE|REG_EXTENDED)) {
#ifdef	BUCKET_DEBUG
					printf("DEBUG: match(PATT) \"%s\"\n",
					    ip->str);
#endif	/* BUCKET_DEBUG */
					(void) highlight(pp, lic, ip->str);
					return(listp);
				}
			}
		}
/*
 * COMP-FILE -- file containing strings, used to determine if all license
 * componets match (individually or as an aggreate)
 */
		else if (p->bucketType == EXT_COMP_FILE) {
/*
 * Simplest case (anticipated for use mostly when checking rpm-headers):
 * see if the license-string is a single line in the friendly list.
 */
#ifdef	BUCKET_DEBUG
			printf("DEBUG: COMP-FILE: License/line: \"%s\"\n", lic);
#endif	/* BUCKET_DEBUG */
			(void) sprintf(cbuf, "^%s$", lic);
			if (strGrep(cbuf, p->buf, REG_NEWLINE)) {
#ifdef	BUCKET_DEBUG
				printf("DEBUG: COMP-FILE: whole-line match!\n");
#endif	/* BUCKET_DEBUG */
				return(listp);
			}
			if (checkLicenseComponents(lic, p->buf)) {
				return(listp);
			}
		}
/*
 * STR-FILE -- file containing "fgrep" strings (just match one line)
 */
		else if (p->bucketType == EXT_STR_FILE) {
			(void) sprintf(cbuf, "^%s$", lic);
			if (strGrep(cbuf, p->buf, REG_NEWLINE)) {
#ifdef	BUCKET_DEBUG
				printf("DEBUG: match(STR) \"%s\"\n", p->buf);
#endif	/* BUCKET_DEBUG */
				return(listp);
			}
		}
/*
 * SCRIPT - set up the environment and run the script.  Exit-value of ZERO
 * is a "match" -- e.g., the license goes IN the bucket
 */
 		else if (p->bucketType == EXT_SCRIPT ||
		    p->bucketType == EXT_EXEC) {
			cp = (p->bucketType == EXT_EXEC ? p->buf : gl.shell);
			if ((j = fork()) < 0) {		/* see ya! */
				Fatal("fork failure");
			}
			else if (j == 0) {	/* child runs script */
				childBucketEnviron(pp, isPackage);
				changeDir(gl.tmpdir);
#ifdef	BUCKET_DEBUG
				printf("DEBUG: Child-script \"%s %s\"\n",
				    gl.shell, p->buf);
#endif	/* BUCKET_DEBUG */
				/*mySystem("./autogdb %s %s", cp, p->buf);*/
				execl(cp, cp, p->buf, NULL_STR);
				perror("execl");
#if	0
				Fatal("execl error");
#endif
			}
/*
 * Parent waits for child and checks exit status here.
 */
			if (waitpid(j, &ret, 0) < 0) {
				perror("waitpid");
				Fatal("waitpid fails (pid=%d)", j);
			}
			if (WIFSIGNALED(ret)) {
				Error("child %d died from signal %d", j,
				    WTERMSIG(ret));
			}
			else if (WIFSTOPPED(ret)) {
				Error("child %d stopped, signal %d", j,
				    WSTOPSIG(ret));
			}
			else if (WIFEXITED(ret)) {
				if (WEXITSTATUS(ret) == 0) {
#ifdef	BUCKET_DEBUG
					printf("DEBUG: match(SCRIPT) \"%s\"\n",
					    p->buf);
#endif	/* BUCKET_DEBUG */
					return(listp);
				}
			}
#ifdef	QA_CHECKS
			else {
				Fatal("Unexpected process condition");
			}
#endif	/* QA_CHECKS */
		}
/*
 * REGEX - compare the license string to the regex specified
 */
		else if (p->bucketType == EXT_REGEX) {
			if (strGrep(p->buf, lic, REG_ICASE|REG_EXTENDED)) {
#ifdef	BUCKET_DEBUG
				printf("DEBUG: match(REGEX) \"%s\"\n", p->buf);
#endif	/* BUCKET_DEBUG */
				(void) highlight(pp, lic, p->buf);
				return(listp);
			}
		}
/*
 * STRING - perform a case-insensitive string compare
 */
		else if (p->bucketType == EXT_STRING) {
			if (strcasecmp(p->buf, lic) == 0) {
#ifdef	BUCKET_DEBUG
				printf("DEBUG: match(STRING) \"%s\"\n", p->buf);
#endif	/* BUCKET_DEBUG */
				return(listp);
			}
		}
/*
 * DEFAULT - chosen if nothing else matches
 */
		else if (p->bucketType == EXT_DEFAULT) {
#ifdef	BUCKET_DEBUG
			printf("DEBUG: default \"%s\"\n", lic);
#endif	/* BUCKET_DEBUG */
			return(listp);
		}
	}
	return(NULL_LIST);
}

static int checkLicenseComponents(char *lic, char *attrText)
{
	register char *start, *end, save;
	register int delim = 0;
/*
 * Brute-force (mostly for checking against computed license summaries):
 * Split the license into distinct strings delimited by [+,] and then
 * search for each string in the friendly-list.  Only a complete set of
 * 'friendly substrings' constitutes a friendly license in this case.
 *****
 * FIX-ME-NOT: makeLicenseSummary() [in licenses.c] only uses a comma
 * to separate license components anymore.   This COULD be adjusted to
 * reflect that, but for now let's leave '+' as another delimeter.
 */
	for (start = lic; *start; start = end+1) {
		save = NULL_CHAR;
		for (end = start; *end; end++) {
			if (/**end == '+' || */*end == ',') {
				save = *end;
				*end = NULL_CHAR;
				delim++;
				break;
			}
		}
/*
 * If we haven't found a delimiter, we'd only search for the entire
 * license string -- and we already DID that!
 */
		if (!delim) {
#ifdef	BUCKET_DEBUG
			printf("DEBUG: COMP-FILE: No delimters found, FAIL\n");
#endif	/* BUCKET_DEBUG */
			return(0);
		}
		(void) sprintf(cbuf, "^%s$", start);
		if (save != NULL_CHAR) {
			*end = save;		/* un-terminate it */
		}
#if	defined(REPORT_DEBUG) || defined(FRIENDLY_DEBUG)
		printf("DEBUG: COMP-FILE: Pedantic-check: \"%s\"\n", cbuf);
#endif	/* REPORT_DEBUG || FRIENDLY_DEBUG */
		if (!strGrep(cbuf, attrText, REG_NEWLINE)) {
#if	defined(REPORT_DEBUG) || defined(FRIENDLY_DEBUG)
			printf("DEBUG: COMP-FILE: Pedantic NO MATCH\n");
#endif	/* REPORT_DEBUG || FRIENDLY_DEBUG */
			return(0);		/* non-match == fail */
		}
	}
	return(1);
}

static char *nextUncommentedLine(char *s)
{
	register char *x = s;
/* */
	while (isspace(*x)) {
		x++;
	}
	while (*x && *x == '#') {
		x = findEol(x);
		while (isspace(*x)) {
			x++;
		}
	}
	return(x);
} 

/*
 * Return 1 if the entire license summary consists of only those items
 * considered 'of very minor importance' to the IP/Commercial attorneys.
 *****
 * License summaries will ONLY contain things classified as LOWINTEREST
 * (see parseLicenses() in parse.c) when nothing of more interest is in
 * the set of licenses found.  In other words, we can look at the first
 * component and tell -- if it's a LOWINTEREST component, EVERYTHING in
 * this summary is LOWINTEREST
 *****
 * "Punt" (LikelyNot and NoLicenseFound) cases are generated separately.
 */
static int isBoring(char *lic)
{
	register char *cp;
	register int val = 0;
/* */
#ifdef	PROC_TRACE
#ifdef	PROC_TRACE_SWITCH
    if (gl.ptswitch)
#endif	/* PROC_TRACE_SWITCH */
	printf("== isBoring(\"%s\")\n", lic);
#endif	/* PROC_TRACE */
/* */
#ifdef	QA_CHECKS
	if (lic == NULL_STR) {
		Assert(NO, "isBoring: Empty text on license");
	}
#endif	/* QA_CHECKS */
	if ((cp = strchr(lic, ',')) != NULL_STR) {
		*cp = NULL_CHAR;
	}
	val = strcmp(lic, LS_NOT_PD) && strcmp(lic, LS_PD_CLM) &&
	    strcmp(lic, LS_PD_CPRT) && strcmp(lic, LS_CPRTONLY) &&
	    strcmp(lic, LS_TDMKONLY) && strcmp(lic, LS_LICRONLY) &&
	    strcmp(lic, LS_PATRONLY) && strncmp(lic, "(C)", 3);
#if	0
	    strcmp(lic, LS_PATRONLY) && !endsIn(lic, "(C)");
#endif
	if (cp != NULL_STR) {
		*cp = ',';
	}
	return(!val);
}
