/*********************************************************************
 Filter_C: Given a C program, create a bSAM cache file.

 Copyright (C) 2007 Hewlett-Packard Development Company, L.P.

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

 --------------------------
 This program parses code.  It assumes the code is valid.
 *********************************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#    include <ctype.h>
#include <string.h>
#include <sys/types.h>
#  include <sys/stat.h>
#include <sys/mman.h>
#include <fcntl.h>


/*** Globals ***/
int Verbose=0;

/*** Globals for fast file access ***/
unsigned char * Mmap = NULL; /* mmap */
int MmapSize=0; /* size of mmap */

/*** Globals for generic values ***/
char *KeywordGeneric[] = {"COMMENT","NUMBER","STRING","CHAR","VAR","PARM",NULL};

/**********************************************************************
 These are ALL keywords for the language (everything else, like
 printf(), read(), and exit()) is a function).
 Languages may also have a keyword before the start of a function.
 For example, every perl subroutines start with "sub".
 **********************************************************************/

struct tokeninfo
  {
  char Type;	/* type of token */
  int Start;	/* offset to start of token */
  int Len;	/* length of token */
  char *Value;	/* pointer to the start of the token */
  char *Generic; /* pointer to generic string, or NULL */
  };
typedef struct tokeninfo tokeninfo;

struct token
  {
  int Len;
  char *Value;
  };
typedef struct token token;

/*** Globals for quick parsing ***/
char **Keyword = NULL;
struct token *KeywordTokens = NULL;
char **KeywordMultiTokens = NULL;

/*** Globals for parsing C and C++ ***/
char *KeywordC[] = {
		"auto", "break", "case", "catch", "char", "const",
		"continue", "default", "do", "double", "else", "enum",
		"extern", "float", "for", "goto", "if", "int", "long",
		"register", "return", "short", "signed", "sizeof", "static",
		"struct", "switch", "throw", "typedef", "union", "unsigned",
		"void", "volatile", "while", NULL
		};
  /** Token list identifies non-string special words.  List them by length
      since the first match is used.  Any string of symbols not listed
      here are kept as a string of symbols. **/
token KeywordCTokens[] = {
		{3,"<<="}, {3,">>="}, {3,"..."},
		{2,"&&"}, {2,"||"}, {2,"<<"}, {2,">>"}, {2,"->"},
		{2,"++"}, {2,"--"},
		{2,"=="}, {2,"!="}, {2,"<="}, {2,">="},
		{2,"+="}, {2,"-="}, {2,"*="}, {2,"/="}, {2,"%="},
		{2,"&="}, {2,"|="}, {2,"^="},
		{1,"!"}, {1,"+"}, {1,"-"}, {1,"*"}, {1,"/"}, {1,"%"},
		{1,"|"}, {1,"&"}, {1,"^"}, {1,"~"},
		{1,";"}, {1,"<"}, {1,">"}, {1,"="},
		{1,"?"}, {1,":"}, {1,","}, {1,"."},
		{0,NULL} };
  /** MultiTokens are ones that can be separated by whitespace **/
char *KeywordCMultiTokens[] = {
		"# include",
		"# import",
		"# pragma",
		"# define", "# ifndef", "# ifdef", "# undef",
		"# else", "# elif", "# if", "# endif", 
		"# error", "# using", "# line",
		NULL };
char KeywordCPreFunction[] = "";

/*** Globals for Java source code ***/
char *KeywordJava[] = {
		"abstract", "boolean", "break", "byte", "case", "catch",
		"char", "class", "const", "continue", "default", "do",
		"double", "else", "extends", "final", "finally", "float",
		"for", "goto", "if", "implements", "import", "instanceof",
		"int", "interface", "long", "native", "new", "package",
		"private", "protected", "public", "return", "short",
		"static", "strictfp", "super", "switch", "synchronized",
		"this", "throw", "throws", "transient", "try", "void",
		"volatile", "while", NULL
		};
token KeywordJavaTokens[] = {
		{3,"<<="}, {3,">>="}, {3,"..."},
		{2,"&&"}, {2,"||"}, {2,"<<"}, {2,">>"}, {2,"->"},
		{2,"++"}, {2,"--"},
		{2,"=="}, {2,"!="}, {2,"<="}, {2,">="},
		{2,"+="}, {2,"-="}, {2,"*="}, {2,"/="}, {2,"%="},
		{2,"&="}, {2,"|="}, {2,"^="},
		{1,"!"}, {1,"+"}, {1,"-"}, {1,"*"}, {1,"/"}, {1,"%"},
		{1,"|"}, {1,"&"}, {1,"^"}, {1,"~"},
		{1,";"}, {1,"<"}, {1,">"}, {1,"="},
		{1,"?"}, {1,":"}, {1,","}, {1,"."},
		{0,NULL} };
char KeywordJavaPreFunction[] = "";


/********************************************************
 GetTokenStart(): Given an input string, identify the
 offset to the next start of token.
 Returns -1 when no more tokens.
 ********************************************************/
int	GetTokenStart	(char *S, long MaxS)
{
  int i=0;

  if (!S) return(-1);
  while((i < MaxS) && S[i] && isspace(S[i])) i++;
  if (i==MaxS) return(-1);
  return(i);
} /* GetTokenStart() */

/********************************************************
 GetTokenType(): Given the first character in a token,
 return the type:
  " = double-quoted string
  ' = single-quoted string
  ( ) { } or [ ] = bracket () {} or []
  d = number (number)
  x = hex number (number)
  n = variable name (letter, number, or underscore)
  s = symbol
  ' ' = white space
  c = C comment (the '/' + '*')
  C = C++ comment (the '/' + '/')
  \0 = bad token
 This function works with GetTokenEnd().
 NOTES:
   Strings like "-1" are two symbols: '-' (symbol) and '1' (number).
   String ".5" is two symbols: '.' and '5'.
   String "a.b" is ONE symbol since most languages permit subelements
     in variable names.
   Similarly, "a->b" is ONE symbol.
 ********************************************************/
char	GetTokenType	(char *S, long MaxS)
{
  if (!S || !S[0]) return(0);
  if (isspace(S[0])) return(' ');
  if (S[0]=='"') return('"');
  if (S[0]=='\'') return('\'');
  if ((MaxS > 1) && (S[0]=='0') && (S[1]=='x')) return('x');
  if (isdigit(S[0])) return('d');
  if (isalnum(S[0]) || (S[0]=='_')) return('n');
  if ((MaxS > 1) && (S[0]=='/') && (S[1]=='*')) return('c');
  if ((MaxS > 1) && (S[0]=='/') && (S[1]=='/')) return('C');
  if (strchr("{}[]()",S[0])) return(S[0]);
  return('s'); /* default: assume symbol */
} /* GetTokenType() */

/********************************************************
 GetNameLen(): Given a string, determine the length of the
 variable name.
 Variables can be "name" or "name.name" or "name->name"
 (or name.name->name.name->name) but "name..name" is not permitted.
 Valid names are letters, numbers, or underscores.
 This is RECURSIVE.
 ********************************************************/
int	GetNameLen	(char *S, long MaxS)
{
  int i=0;
  if (!S || !S[0]) return(0);
  if (MaxS <= 0) return(0);
  while(isalnum(S[i]) || (S[i]=='_')) i++;
  if ((S[i]=='.') && (isalnum(S[i]) || (S[i]=='_')))
    {
    i++;
    i += GetNameLen(S+i,MaxS-1);
    }
  else if ((S[i]=='-') && (S[i]=='>'))
    {
    i+=2;
    i += GetNameLen(S+i,MaxS-2);
    }
  return(i);
} /* GetNameLen() */

/********************************************************
 GetStringLen(): Given a string, determine the length.
 QuoteFlag treats "\" as a quotable character.
 ********************************************************/
int	GetStringLen	(char *S, long MaxS, char StringType, int QuoteFlag)
{
  int i;
  if (!S) return(-1);
  if (S[0] != StringType) return(0);
  i=1;
  while((i < MaxS) && S[i] != StringType)
    {
    if (QuoteFlag && (S[i]=='\\')) i+=2;
    else i++;
    }
  if (S[i] == StringType) i++;
  return(i);
} /* GetStringLen() */

/********************************************************
 GetTokenEnd(): Given an input string that starts a token,
 identify the offset to the end of the token.
 Returns the length of the token.
 This function is quote-smart!
 Returns -1 when no more tokens.
 ********************************************************/
int	GetTokenEnd	(char Type, char *S, int MaxS)
{
  int i=0;
  int s,si,sj;

  if (!S) return(-1);
  switch(Type)
    {
    case ' ': /* white-space */
	while((i < MaxS) && isspace(S[i])) i++;
	break;

    case 'd': /* digit */
	while(isdigit(S[i])) i++;
	/* check for decimals */
	if (S[i]=='.')
	  {
	  i++;
	  while((i < MaxS) && isdigit(S[i])) i++;
	  }
	/* check for exponent */
	if ((S[i]=='e') || (S[i]=='E'))
	  {
	  i++;
	  if (strchr("+-",S[i])) i++;
	  while((i < MaxS) && isdigit(S[i])) i++;
	  }
	/* check for suffix for data type */
	while((i < MaxS) && strchr("fFuUlL",S[i])) i++;
	break;

    case 'x': /* hex digit */
	while(isxdigit(S[i])) i++;
	/* check for suffix for data type */
	while((i < MaxS) && strchr("fFuUlL",S[i])) i++;
	break;

    case '"':	/* quoted string */
    case '\'':	/* quoted string */
	i=GetStringLen(S,MaxS,Type,1);
	break;

    case 'n':	/* variable name (name or name.name or name->name) */
	i=GetNameLen(S,MaxS);
	break;

    case 'C':	/* C++ comment: comment to end of line */
	while((i < MaxS) && S[i] && (S[i]!='\n')) i++;
	break;

    case 'c':	/* C comment: comment to '*'+'/' */
	i+=2;	/* can only enter this case is there is a slash-star */
	while((i < MaxS-1) && S[i] && strncmp("*/",S+i,2))
		{
		i++;
		}
	if (S[i]) i += 2; /* skip the final match */
	break;

    case '{':	/* it's a bracket */
    case '}':	/* it's a bracket */
    case '(':	/* it's a bracket */
    case ')':	/* it's a bracket */
    case '[':	/* it's a bracket */
    case ']':	/* it's a bracket */
	i++;
	break;

    case 's':	/* it's a symbol (one or more characters) */
    default:	/* default: assume it is a symbol */

	/* check for multitokens */
	if (KeywordMultiTokens)
	  {
	  for(s=0; KeywordMultiTokens[s] != NULL; s++)
	    {
	    si = i-1; /* temp holder */
	    sj = -1;
	    do
	      {
	      si++;
	      sj++;
	      /* skip spaces */
	      if (isspace(KeywordMultiTokens[s][sj]))
	        {
		/* only skip spaces if the token says to skip spaces */
	        while((si < MaxS) && isspace(S[si])) si++;
	        while(isspace(KeywordMultiTokens[s][sj])) sj++;
		}
	      } while((si < MaxS) && KeywordMultiTokens[s][sj] &&
		  (S[si]==KeywordMultiTokens[s][sj]));
	    /* see if it matched */
	    if (!KeywordMultiTokens[s][sj]) return(si);
	    }
	  }

	/* check for known tokens */
	for(s=0; KeywordTokens[s].Len > 0; s++)
	  {
	  if ((KeywordTokens[s].Len < MaxS) &&
	      !memcmp(KeywordTokens[s].Value,S,KeywordTokens[s].Len))
		{
		return(KeywordTokens[s].Len);
		}
	  }
	/* alright, not a know token... return adjacent symbols */
	i++;
	while((i < MaxS) && GetTokenType(S+i,MaxS-i) == 's') i++;
	break;
    }
  return(i);
} /* GetTokenEnd() */

/********************************************************
 GetToken(): Given an input string, return the next token.
 Returns tokeninfo.Start == -1 when no more tokens.
 ********************************************************/
tokeninfo	GetToken	(char *S, int MaxS)
{
  tokeninfo TI;

  memset(&TI,0,sizeof(tokeninfo));

  TI.Start = GetTokenStart(S,MaxS);
  if (TI.Start == -1) return(TI);

  TI.Type = GetTokenType(S+TI.Start,MaxS-TI.Start);
  switch(TI.Type)
    {
    case 'c': case 'C': /* comments */
	TI.Generic = KeywordGeneric[0];
	break;
    case 'd': case 'x': /* digit (decimal or hex) */
	TI.Generic = KeywordGeneric[1];
	break;
    case '"': /* string */
	TI.Generic = KeywordGeneric[2];
	break;
    case '\'': /* character */
	TI.Generic = KeywordGeneric[3];
	break;
    case 'n': /* variable name */
	TI.Generic = KeywordGeneric[4];
	break;
    default:
	TI.Generic = NULL;
    }

  TI.Len = GetTokenEnd(TI.Type,S+TI.Start,MaxS-TI.Start);
  return(TI);
} /* GetToken() */

/********************************************************
 MatchKeyword(): Given an input string, does it match a
 keyword?
 Returns non-zero for match, 0 for miss.
 ********************************************************/
int	MatchKeyword	(char *S, int SLen, char *KeywordList[])
{
  int k;
  for(k=0; KeywordList[k] != NULL; k++)
    {
    /* S must match KeywordList and not be a substring. */
    if (!strncmp(S,KeywordList[k],SLen) && (KeywordList[k][SLen] == '\0'))
	{
	return(k+1); /* returns 1+keyword_index */
	}
    }
  return(0);
} /* MatchKeyword() */

/**********************************************
 CloseFile(): Close a filename.
 **********************************************/
void	CloseFile	(int FileHandle)
{
  if (MmapSize > 0) munmap(Mmap,MmapSize);
  MmapSize = 0;
  Mmap = NULL;
  close(FileHandle);
} /* CloseFile() */

/**********************************************
 OpenFile(): Open and mmap a file.
 Returns file handle, or exits on failure.
 **********************************************/
int	OpenFile	(char *Fname)
{
  int F;
  struct stat Stat;

  /* open the file (memory map) */
  if (Verbose > 1) fprintf(stderr,"Debug: opening %s\n",Fname);
  F = open(Fname,O_RDONLY);
  if (F == -1)
        {
        fprintf(stderr,"ERROR: Unable to open file (%s)\n",Fname);
        exit(-1);
        }
  if (fstat(F,&Stat) == -1)
        {
        fprintf(stderr,"ERROR: Unable to stat file (%s)\n",Fname);
        exit(-1);
        }
  MmapSize = Stat.st_size;
  Mmap = mmap(0,MmapSize,PROT_READ,MAP_PRIVATE,F,0);
  if (Mmap == MAP_FAILED)
        {
        fprintf(stderr,"ERROR: Unable to mmap file (%s)\n",Fname);
        exit(-1);
        }
  return(F);
} /* OpenFile() */


/**************************************************************************/
/**************************************************************************/
/**************************************************************************/
int	main	(int argc, char *argv[])
{
  int F;
  long i,j;
  tokeninfo TI;

  /*****
   Next step:
     Load the file.
     Print each token and length.  Identify if it is a key word.
     Replace basic token objects (comments, etc.).
   Then:
     Keep track of "var ( parm ) {" -- denotes start of a function.
     Keep track of the { ... } with nesting.
   *****/
  F = OpenFile(argv[1]);

  i=0;
  Keyword = KeywordC;
  KeywordTokens = KeywordCTokens;
  KeywordMultiTokens = KeywordCMultiTokens;
  while(i < MmapSize)
    {
    TI = GetToken(Mmap+i,MmapSize-i);
    if (TI.Start < 0)
      {
      i = MmapSize;
      }
    else
	{
	i += TI.Start;
	printf("%ld len: %d  value: '",i,TI.Len);
	for(j=i; j < i+TI.Len; j++) fputc(Mmap[j],stdout);
	printf("'");
	if (TI.Generic) printf(" (%s)",TI.Generic);
	printf("\n");
	i += TI.Len;
	}
    }

  CloseFile(F);
  return(0);
} /* main() */

