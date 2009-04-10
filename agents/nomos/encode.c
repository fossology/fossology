/***************************************************************
 encode.c: Simple utility program for escaping C strings for
       inclusion in code. Reads the first line of a given file
       and then outputs (to stdout) text suitable for initializing
       an licSpec_t structure. Used by GENSEARCHDATA script for
       processing STRINGS.in.

 Copyright (C) 2006, 2009 Hewlett-Packard Development Company, L.P.
 
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
#include <stdio.h>
#include <string.h>

#define	myBUFSIZ	BUFSIZ

extern void exit();
#ifdef notdef
char *encode(), *decode();

char *encode(char *p, int len)
{
    static char cr_buf[myBUFSIZ];
    register char *cp;
    register int i;

#ifdef	PROC_TRACE
    printf("encode(%s, %d)\n", p, len);
#endif	/* PROC_TRACE */
    (void) memset(cr_buf, 0, sizeof(cr_buf));
    for (i = 0;  i < len; i++) {
	cr_buf[i] = p[i];
    }
    cr_buf[i] = '\0';
    return(cr_buf);
}
#endif /* notdef */



main(int argc, char **argv)
{
    char str[myBUFSIZ], *encoded_str, *new, *cp;
    int i, len = 0;
    FILE *fp;

#ifdef	PROC_TRACE
    printf("main(%d, **argv)\n", argc);
#endif	/* PROC_TRACE */
    if (argc == 1) {
	fprintf(stderr, "Usage: %s file\n", *argv);
	exit(1);
    }

    /*
      Open the file (or stdin)
    */
    if (strcmp(*++argv, "-") == 0) {
	fp = stdin;
    }
    else if ((fp = fopen(*argv, "r")) == (FILE *) NULL) {
	perror(*argv);
	exit(1);
    }

    /* 
       Read the first line and remove any trailing newline.
    */
    if (fgets(str, sizeof(str), fp) == (char *) EOF) {
	perror(*argv);
	exit(1);
    }
    if ((cp = strrchr(str, '\n')) != (char *) NULL) {
	*cp = '\0';
    }
    len = strlen(str);

    printf("{%d, \"", len);
    for (i = 0; i < len; i++) {
	printf("\\%o", str[i] & 0xff);
    }
    printf("\\0\"}\n");
}
