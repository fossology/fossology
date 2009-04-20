/* untar.c */

/*#define VERSION "1.4"*/

/* DESCRIPTION:
 *	Untar extracts files from an uncompressed tar archive, or one which
 *	has been compressed with gzip. Usually such archives will have file
 *	names that end with ".tar" or ".tgz" respectively, although untar
 *	doesn't depend on any naming conventions.  For a summary of the
 *	command-line options, run untar with no arguments.
 *
 * HOW TO COMPILE:
 *	Untar doesn't require any special libraries or compile-time flags.
 *	A simple "cc untar.c -o untar" (or the local equivalent) is
 *	sufficient.  Even "make untar" works, without needing a Makefile.
 *	For Microsoft Visual C++, the command is "cl /D_WEAK_POSIX untar.c"
 *	(for 32 bit compilers) or "cl /F 1400 untar.c" (for 16-bit).
 *
 *	IF YOU SEE COMPILER WARNINGS, THAT'S NORMAL; you can ignore them.
 *	Most of the warnings could be eliminated by adding #include <string.h>
 *	but that isn't portable -- some systems require <strings.h> and
 *	<malloc.h>, for example.  Because <string.h> isn't quite portable,
 *	and isn't really necessary in the context of this program, it isn't
 *	included.
 *
 * PORTABILITY:
 *	Untar only requires the <stdio.h> header.  It uses old-style function
 *	definitions.  It opens all files in binary mode.  Taken together,
 *	this means that untar should compile & run on just about anything.
 *
 *	If your system supports the POSIX chmod(2), utime(2), link(2), and
 *	symlink(2) calls, then you may wish to compile with -D_POSIX_SOURCE,
 *	which will enable untar to use those system calls to restore the
 *	timestamp and permissions of the extracted files, and restore links.
 *	(For Linux, _POSIX_SOURCE is always defined.)
 *
 *	For systems which support some POSIX features but not enough to support
 *	-D_POSIX_SOURCE, you might be able to use -D_WEAK_POSIX.  This allows
 *	untar to restore time stamps and file permissions, but not links.
 *	This should work for Microsoft systems, and hopefully others as well.
 *
 * AUTHOR & COPYRIGHT INFO:
 *	Written by Steve Kirkendall, kirkenda@cs.pdx.edu
 *	Placed in public domain, 6 October 1995
 *
 *	Portions derived from inflate.c -- Not copyrighted 1992 by Mark Adler
 *	version c10p1, 10 January 1993
 *
 *      Altered by Herman Bloggs <hermanator12002@yahoo.com>
 *      April 4, 2003
 *      Changes: Stripped out gz compression code, added better interface for
 *      untar.
 */
#include <windows.h>
#include <stdio.h>
#include <io.h>
#include <string.h>
#include <stdlib.h>
#ifndef SEEK_SET
# define SEEK_SET 0
#endif

#ifdef _WEAK_POSIX
# ifndef _POSIX_SOURCE
#  define _POSIX_SOURCE
# endif
#endif

#ifdef _POSIX_SOURCE
# include <sys/types.h>
# include <sys/stat.h>
# include <sys/utime.h>
# ifdef _WEAK_POSIX
#  define mode_t int
# else
#  include <unistd.h>
# endif
#endif
#include "debug.h"
#include "untar.h"
#include <glib.h>

#if GLIB_CHECK_VERSION(2,6,0)
#	include <glib/gstdio.h>
#else
#define mkdir(a,b) _mkdir((a))
#define g_mkdir mkdir
#define g_fopen fopen
#define g_unlink unlink
#endif

#define untar_error( error, args... )      gaim_debug(GAIM_DEBUG_ERROR, "untar", error, ## args )
#define untar_warning( warning, args... )  gaim_debug(GAIM_DEBUG_WARNING, "untar", warning, ## args )
#define untar_verbose( args... )           gaim_debug(GAIM_DEBUG_INFO, "untar", ## args )
 
#define WSIZE	32768	/* size of decompression buffer */
#define TSIZE	512	/* size of a "tape" block */
#define CR	13	/* carriage-return character */
#define LF	10	/* line-feed character */

typedef unsigned char	Uchar_t;
typedef unsigned short	Ushort_t;
typedef unsigned long	Ulong_t;

typedef struct
{
	char	filename[100];	/*   0  name of next file */
	char	mode[8];	/* 100  Permissions and type (octal digits) */
	char	owner[8];	/* 108  Owner ID (ignored) */
	char	group[8];	/* 116  Group ID (ignored) */
	char	size[12];	/* 124  Bytes in file (octal digits) */
	char	mtime[12];	/* 136  Modification time stamp (octal digits)*/
	char	checksum[8];	/* 148  Header checksum (ignored) */
	char	type;		/* 156  File type (see below) */
	char	linkto[100];	/* 157  Linked-to name */
	char	brand[8];	/* 257  Identifies tar version (ignored) */
	char	ownername[32];	/* 265  Name of owner (ignored) */
	char	groupname[32];	/* 297  Name of group (ignored) */
	char	devmajor[8];	/* 329  Device major number (ignored) */
	char	defminor[8];	/* 337  Device minor number (ignored) */
	char	prefix[155];	/* 345  Prefix of name (optional) */
	char	RESERVED[12];	/* 500  Pad header size to 512 bytes */
} tar_t;
#define ISREGULAR(hdr)	((hdr).type < '1' || (hdr).type > '6')

Uchar_t slide[WSIZE];

static const char *inname  = NULL;      /* name of input archive */
static FILE	  *infp    = NULL;      /* input byte stream */
static FILE	  *outfp   = NULL;      /* output stream, for file currently being extracted */
static Ulong_t	  outsize  = 0;         /* number of bytes remainin in file currently being extracted */
static char	  **only   = NULL;      /* array of filenames to extract/list */
static int	  nonlys   = 0;	        /* number of filenames in "only" array; 0=extract all */
static int	  didabs   = 0;	        /* were any filenames affected by the absence of -p? */

static untar_opt untarops = 0;          /* Untar options */

/* Options checked during untar process */
#define LISTING (untarops & UNTAR_LISTING)  /* 1 if listing, 0 if extracting */
#define QUIET   (untarops & UNTAR_QUIET)    /* 1 to write nothing to stdout, 0 for normal chatter */
#define VERBOSE (untarops & UNTAR_VERBOSE)  /* 1 to write extra information to stdout */
#define FORCE   (untarops & UNTAR_FORCE)    /* 1 to overwrite existing files, 0 to skip them */
#define ABSPATH (untarops & UNTAR_ABSPATH)  /* 1 to allow leading '/', 0 to strip leading '/' */
#define CONVERT (untarops & UNTAR_CONVERT)  /* 1 to convert newlines, 0 to leave unchanged */

/*----------------------------------------------------------------------------*/

/* create a file for writing.  If necessary, create the directories leading up
 * to that file as well.
 */
static FILE *createpath(name)
	char	*name;	/* pathname of file to create */
{
	FILE	*fp;
	int	i;

	/* if we aren't allowed to overwrite and this file exists, return NULL */
	if (!FORCE && access(name, 0) == 0)
	{
		untar_warning("%s: exists, will not overwrite without \"FORCE option\"\n", name);
		return NULL;
	}

	/* first try creating it the easy way */
	fp = g_fopen(name, CONVERT ? "w" : "wb");
	if (fp)
		return fp;

	/* Else try making all of its directories, and then try creating
	 * the file again.
	 */
	for (i = 0; name[i]; i++)
	{
		/* If this is a slash, then temporarily replace the '/'
		 * with a '\0' and do a mkdir() on the resulting string.
		 * Ignore errors for now.
		 */
		if (name[i] == '/')
		{
			name[i] = '\0';
			(void)g_mkdir(name, 0777);
			name[i] = '/';
		}
	}
	fp = g_fopen(name, CONVERT ? "w" : "wb");
	if (!fp)
		untar_error("Error opening: %s\n", name);
	return fp;
}

/* Create a link, or copy a file.  If the file is copied (not linked) then
 * give a warning.
 */
static void linkorcopy(src, dst, sym)
	char	*src;	/* name of existing source file */
	char	*dst;	/* name of new destination file */
	int	sym;	/* use symlink instead of link */
{
	FILE	*fpsrc;
	FILE	*fpdst;
	int	c;

	/* Open the source file.  We do this first to make sure it exists */
	fpsrc = g_fopen(src, "rb");
	if (!fpsrc)
	{
		untar_error("Error opening: %s\n", src);
		return;
	}

	/* Create the destination file.  On POSIX systems, this is just to
	 * make sure the directory path exists.
	 */
	fpdst = createpath(dst);
	if (!fpdst)
		/* error message already given */
		return;

#ifdef _POSIX_SOURCE
# ifndef _WEAK_POSIX
	/* first try to link it over, instead of copying */
	fclose(fpdst);
	g_unlink(dst);
	if (sym)
	{
		if (symlink(src, dst))
		{
			perror(dst);
		}
		fclose(fpsrc);
		return;
	}
	if (!link(src, dst))
	{
		/* This story had a happy ending */
		fclose(fpsrc);
		return;
	}

	/* Dang.  Reopen the destination again */
	fpdst = g_fopen(dst, "wb");
	/* This *can't* fail */

# endif /* _WEAK_POSIX */
#endif /* _POSIX_SOURCE */

	/* Copy characters */
	while ((c = getc(fpsrc)) != EOF)
		putc(c, fpdst);

	/* Close the files */
	fclose(fpsrc);
	fclose(fpdst);

	/* Give a warning */
	untar_warning("%s: copy instead of link\n", dst);
}

/* This calls fwrite(), possibly after converting CR-LF to LF */
static void cvtwrite(blk, size, fp)
	Uchar_t	*blk;	/* the block to be written */
	Ulong_t	size;	/* number of characters to be written */
	FILE	*fp;	/* file to write to */
{
	int	i, j;
	static Uchar_t mod[TSIZE];

	if (CONVERT)
	{
		for (i = j = 0; i < size; i++)
		{
			/* convert LF to local newline convention */
			if (blk[i] == LF)
				mod[j++] = '\n';
			/* If CR-LF pair, then delete the CR */
			else if (blk[i] == CR && (i+1 >= size || blk[i+1] == LF))
				;
			/* other characters copied literally */
			else
				mod[j++] = blk[i];
		}
		size = j;
		blk = mod;
	}

	fwrite(blk, (size_t)size, sizeof(Uchar_t), fp);
}


/* Compute the checksum of a tar header block, and return it as a long int.
 * The checksum can be computed using either POSIX rules (unsigned bytes)
 * or Sun rules (signed bytes).
 */
static long checksum(tblk, sunny)
	tar_t	*tblk;	/* buffer containing the tar header block */
	int	sunny;	/* Boolean: Sun-style checksums? (else POSIX) */
{
	long	sum;
	char	*scan;

	/* compute the sum of the first 148 bytes -- everything up to but not
	 * including the checksum field itself.
	 */
	sum = 0L;
	for (scan = (char *)tblk; scan < tblk->checksum; scan++)
	{
		sum += (*scan) & 0xff;
		if (sunny && (*scan & 0x80) != 0)
			sum -= 256;
	}

	/* for the 8 bytes of the checksum field, add blanks to the sum */
	sum += ' ' * sizeof tblk->checksum;
	scan += sizeof tblk->checksum;

	/* finish counting the sum of the rest of the block */
	for (; scan < (char *)tblk + sizeof *tblk; scan++)
	{
		sum += (*scan) & 0xff;
		if (sunny && (*scan & 0x80) != 0)
			sum -= 256;
	}

	return sum;
}



/* list files in an archive, and optionally extract them as well */
static int untar_block(Uchar_t *blk) {
	static char	nbuf[256];/* storage space for prefix+name, combined */
	static char	*name,*n2;/* prefix and name, combined */
	static int	first = 1;/* Boolean: first block of archive? */
	long		sum;	  /* checksum for this block */
	int		i;
	tar_t		tblk[1];

#ifdef _POSIX_SOURCE
	static mode_t		mode;		/* file permissions */
	static struct utimbuf	timestamp;	/* file timestamp */
#endif

	/* make a local copy of the block, and treat it as a tar header */
	tblk[0] = *(tar_t *)blk;

	/* process each type of tape block differently */
	if (outsize > TSIZE)
	{
		/* data block, but not the last one */
		if (outfp)
			cvtwrite(blk, (Ulong_t)TSIZE, outfp);
		outsize -= TSIZE;
	}
	else if (outsize > 0)
	{
		/* last data block of current file */
		if (outfp)
		{
			cvtwrite(blk, outsize, outfp);
			fclose(outfp);
			outfp = NULL;
#ifdef _POSIX_SOURCE
			utime(nbuf, &timestamp);
			chmod(nbuf, mode);
#endif
		}
		outsize = 0;
	}
	else if ((tblk)->filename[0] == '\0')
	{
		/* end-of-archive marker */
		if (didabs)
			untar_warning("Removed leading slashes because \"ABSPATH option\" wasn't given.\n");
		return 1;
	}
	else
	{
		/* file header */
	
		/* half-assed verification -- does it look like header? */
		if ((tblk)->filename[99] != '\0'
		 || ((tblk)->size[0] < '0'
			&& (tblk)->size[0] != ' ')
		 || (tblk)->size[0] > '9')
		{
			if (first)
			{
				untar_error("%s: not a valid tar file\n", inname);
				return 0;
			}
			else
			{
				untar_error("Garbage detected; preceding file may be damaged\n");
				return 0;
			}
		}

		/* combine prefix and filename */
		memset(nbuf, 0, sizeof nbuf);
		name = nbuf;
		if ((tblk)->prefix[0])
		{
			strncpy(name, (tblk)->prefix, sizeof (tblk)->prefix);
			strcat(name, "/");
			strncat(name + strlen(name), (tblk)->filename,
				sizeof (tblk)->filename);
		}
		else
		{
			strncpy(name, (tblk)->filename,
				sizeof (tblk)->filename);
		}

		/* Convert any backslashes to forward slashes, and guard
		 * against doubled-up slashes. (Some DOS versions of "tar"
		 * get this wrong.)  Also strip off leading slashes.
		 */
		if (!ABSPATH && (*name == '/' || *name == '\\'))
			didabs = 1;
		for (n2 = nbuf; *name; name++)
		{
			if (*name == '\\')
				*name = '/';
			if (*name != '/'
			 || (ABSPATH && n2 == nbuf)
			 || (n2 != nbuf && n2[-1] != '/'))
				*n2++ = *name;
		}
		if (n2 == nbuf)
			*n2++ = '/';
		*n2 = '\0';

		/* verify the checksum */
		for (sum = 0L, i = 0; i < sizeof((tblk)->checksum); i++)
		{
			if ((tblk)->checksum[i] >= '0'
						&& (tblk)->checksum[i] <= '7')
				sum = sum * 8 + (tblk)->checksum[i] - '0';
		}
		if (sum != checksum(tblk, 0) && sum != checksum(tblk, 1))
		{
			if (!first)
				untar_error("Garbage detected; preceding file may be damaged\n");
			untar_error("%s: header has bad checksum for %s\n", inname, nbuf);
			return 0;
		}

		/* From this point on, we don't care whether this is the first
		 * block or not.  Might as well reset the "first" flag now.
		 */
		first = 0;

		/* if last character of name is '/' then assume directory */
		if (*nbuf && nbuf[strlen(nbuf) - 1] == '/')
			(tblk)->type = '5';

		/* convert file size */
		for (outsize = 0L, i = 0; i < sizeof((tblk)->size); i++)
		{
			if ((tblk)->size[i] >= '0' && (tblk)->size[i] <= '7')
				outsize = outsize * 8 + (tblk)->size[i] - '0';
		}

#ifdef _POSIX_SOURCE
		/* convert file timestamp */
		for (timestamp.modtime=0L, i=0; i < sizeof((tblk)->mtime); i++)
		{
			if ((tblk)->mtime[i] >= '0' && (tblk)->mtime[i] <= '7')
				timestamp.modtime = timestamp.modtime * 8
						+ (tblk)->mtime[i] - '0';
		}
		timestamp.actime = timestamp.modtime;

		/* convert file permissions */
		for (mode = i = 0; i < sizeof((tblk)->mode); i++)
		{
			if ((tblk)->mode[i] >= '0' && (tblk)->mode[i] <= '7')
				mode = mode * 8 + (tblk)->mode[i] - '0';
		}
#endif

		/* If we have an "only" list, and this file isn't in it,
		 * then skip it.
		 */
		if (nonlys > 0)
		{
			for (i = 0;
			     i < nonlys
				&& strcmp(only[i], nbuf)
				&& (strncmp(only[i], nbuf, strlen(only[i]))
					|| nbuf[strlen(only[i])] != '/');
				i++)
			{
			}
			if (i >= nonlys)
			{
				outfp = NULL;
				return 1;
			}
		}

		/* list the file */
		if (VERBOSE)
			untar_verbose("%c %s",
				ISREGULAR(*tblk) ? '-' : ("hlcbdp"[(tblk)->type - '1']),
				nbuf);
		else if (!QUIET)
			untar_verbose("%s\n", nbuf);

		/* if link, then do the link-or-copy thing */
		if (tblk->type == '1' || tblk->type == '2')
		{
			if (VERBOSE)
				untar_verbose(" -> %s\n", tblk->linkto);
			if (!LISTING)
				linkorcopy(tblk->linkto, nbuf, tblk->type == '2');
			outsize = 0L;
			return 1;
		}

		/* If directory, then make a weak attempt to create it.
		 * Ideally we would do the "create path" thing, but that
		 * seems like more trouble than it's worth since traditional
		 * tar archives don't contain directories anyway.
		 */
		if (tblk->type == '5')
		{
			if (LISTING)
				n2 = " directory";
#ifdef _POSIX_SOURCE
			else if (mkdir(nbuf, mode) == 0)
#else
			else if (g_mkdir(nbuf, 0755) == 0)
#endif
				n2 = " created";
			else
				n2 = " ignored";
			if (VERBOSE)
				untar_verbose("%s\n", n2);
			return 1;
		}

		/* if not a regular file, then skip it */
		if (!ISREGULAR(*tblk))
		{
			if (VERBOSE)
				untar_verbose(" ignored\n");
			outsize = 0L;
			return 1;
		}

		/* print file statistics */
		if (VERBOSE)
		{
			untar_verbose(" (%ld byte%s, %ld tape block%s)\n",
				outsize,
				outsize == 1 ? "" : "s",
				(outsize + TSIZE - 1) / TSIZE,
				(outsize > 0  && outsize <= TSIZE) ? "" : "s");
		}

		/* if extracting, then try to create the file */
		if (!LISTING)
			outfp = createpath(nbuf);
		else
			outfp = NULL;

		/* if file is 0 bytes long, then we're done already! */
		if (outsize == 0 && outfp)
		{
			fclose(outfp);
#ifdef _POSIX_SOURCE
			utime(nbuf, &timestamp);
			chmod(nbuf, mode);
#endif
		}
	}
	return 1;
}

/* Process an archive file.  This involves reading the blocks one at a time
 * and passing them to a untar() function.
 */
int untar(const char *filename, const char* destdir, untar_opt options) {
	int ret=1;
	char curdir[_MAX_PATH];
	untarops = options;
	/* open the archive */
	inname = filename;
	infp = g_fopen(filename, "rb");
	if (!infp)
	{
		untar_error("Error opening: %s\n", filename);
		return 0;
	}
	
	/* Set current directory */
	if(!GetCurrentDirectory(_MAX_PATH, curdir)) {
		untar_error("Could not get current directory (error %d).\n", GetLastError());
		fclose(infp);
		return 0;
	}
	if(!SetCurrentDirectory(destdir)) {
		untar_error("Could not set current directory to (error %d): %s\n", GetLastError(), destdir);
		fclose(infp);
		return 0;
	} else {
		/* UNCOMPRESSED */
		/* send each block to the untar_block() function */
		while (fread(slide, 1, TSIZE, infp) == TSIZE) {
			if(!untar_block(slide)) {
				untar_error("untar failure: %s\n", filename);
				fclose(infp);
				ret=0;
			}
		}
		if (outsize > 0 && ret) {
			untar_warning("Last file might be truncated!\n");
			fclose(outfp);
			outfp = NULL;
		}
		if(!SetCurrentDirectory(curdir)) {
			untar_error("Could not set current dir back to original (error %d).\n", GetLastError());
			ret=0;
		}
	}

	/* close the archive file. */
	fclose(infp);

	return ret;
}

