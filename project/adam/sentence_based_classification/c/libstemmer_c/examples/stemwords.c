/* This is a simple program which uses libstemmer to provide a command
 * line interface for stemming using any of the algorithms provided.
 */

#include <stdio.h>
#include <stdlib.h> /* for malloc, free */
#include <string.h> /* for memmove */
#include <ctype.h>  /* for isupper, tolower */

#include "libstemmer.h"

const char * progname;
static int pretty = 1;

static void
stem_file(struct sb_stemmer * stemmer, FILE * f_in, FILE * f_out)
{
#define INC 10
    int lim = INC;
    sb_symbol * b = (sb_symbol *) malloc(lim * sizeof(sb_symbol));

    while(1) {
        int ch = getc(f_in);
        if (ch == EOF) {
            free(b); return;
        }
        {
            int i = 0;
            while(1) {
                if (ch == '\n' || ch == EOF) break;
                if (i == lim) {
                    sb_symbol * newb;
		    newb = (sb_symbol *)
			    realloc(b, (lim + INC) * sizeof(sb_symbol));
		    if (newb == 0) goto error;
		    b = newb;
                    lim = lim + INC;
                }
                /* force lower case: */
                if (isupper(ch)) ch = tolower(ch);

                b[i] = ch;
		i++;
                ch = getc(f_in);
            }

            if (pretty) {
                int j;
                for (j = 0; j < i; j++) fprintf(f_out, "%c", b[j]);
                fprintf(f_out, "%s", " -> ");
            }
	    {
		const sb_symbol * stemmed = sb_stemmer_stem(stemmer, b, i);
                if (stemmed == NULL)
                {
                    fprintf(stderr, "Out of memory");
                    exit(1);
                }
                else
                {
                    int j;
                    /*for (j = 0; j < z->l; j++) */
                    for (j = 0; stemmed[j] != 0; j++)
                        fprintf(f_out, "%c", stemmed[j]);
                    fprintf(f_out, "\n");
                }
            }
        }
    }
error:
    if (b != 0) free(b);
    return;
}

/** Display the command line syntax, and then exit.
 *  @param n The value to exit with.
 */
static void
usage(int n)
{
    printf("usage: %s [-l <language>] [-i <input file>] [-o <output file>] [-c <character encoding>] [-p] [-h]\n"
	  "\n"
	  "The input file consists of a list of words to be stemmed, one per\n"
	  "line. Words should be in lower case, but (for English) A-Z letters\n"
	  "are mapped to their a-z equivalents anyway. If omitted, stdin is\n"
	  "used.\n"
	  "\n"
	  "If -c is given, the argument is the character encoding of the input\n"
          "and output files.  If it is omitted, the UTF-8 encoding is used.\n"
	  "\n"
	  "If -p is given the output file consists of each word of the input\n"
	  "file followed by \"->\" followed by its stemmed equivalent.\n"
	  "Otherwise, the output file consists of the stemmed words, one per\n"
	  "line.\n"
	  "\n"
	  "-h displays this help\n",
	  progname);
    exit(n);
}

int
main(int argc, char * argv[])
{
    char * in = 0;
    char * out = 0;
    FILE * f_in;
    FILE * f_out;
    struct sb_stemmer * stemmer;

    char * language = "english";
    char * charenc = NULL;

    char * s;
    int i = 1;
    pretty = 0;

    progname = argv[0];

    while(i < argc) {
	s = argv[i++];
	if (s[0] == '-') {
	    if (strcmp(s, "-o") == 0) {
		if (i >= argc) {
		    fprintf(stderr, "%s requires an argument\n", s);
		    exit(1);
		}
		out = argv[i++];
	    } else if (strcmp(s, "-i") == 0) {
		if (i >= argc) {
		    fprintf(stderr, "%s requires an argument\n", s);
		    exit(1);
		}
		in = argv[i++];
	    } else if (strcmp(s, "-l") == 0) {
		if (i >= argc) {
		    fprintf(stderr, "%s requires an argument\n", s);
		    exit(1);
		}
		language = argv[i++];
	    } else if (strcmp(s, "-c") == 0) {
		if (i >= argc) {
		    fprintf(stderr, "%s requires an argument\n", s);
		    exit(1);
		}
		charenc = argv[i++];
	    } else if (strcmp(s, "-p") == 0) {
		pretty = 1;
	    } else if (strcmp(s, "-h") == 0) {
		usage(0);
	    } else {
		fprintf(stderr, "option %s unknown\n", s);
		usage(1);
	    }
	} else {
	    fprintf(stderr, "unexpected parameter %s\n", s);
	    usage(1);
	}
    }

    /* prepare the files */
    f_in = (in == 0) ? stdin : fopen(in, "r");
    if (f_in == 0) {
	fprintf(stderr, "file %s not found\n", in);
	exit(1);
    }
    f_out = (out == 0) ? stdout : fopen(out, "w");
    if (f_out == 0) {
	fprintf(stderr, "file %s cannot be opened\n", out);
	exit(1);
    }

    /* do the stemming process: */
    stemmer = sb_stemmer_new(language, charenc);
    if (stemmer == 0) {
        if (charenc == NULL) {
            fprintf(stderr, "language `%s' not available for stemming\n", language);
            exit(1);
        } else {
            fprintf(stderr, "language `%s' not available for stemming in encoding `%s'\n", language, charenc);
            exit(1);
        }
    }
    stem_file(stemmer, f_in, f_out);
    sb_stemmer_delete(stemmer);

    if (in != 0) (void) fclose(f_in);
    if (out != 0) (void) fclose(f_out);

    return 0;
}

