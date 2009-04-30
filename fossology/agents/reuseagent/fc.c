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
#  include <stdio.h>
#include <unistd.h>
#	include <ctype.h>
#include <string.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/mman.h>
#include <fcntl.h>

/****************************************************
 TBD:
 - Identify functions in ProcessFunctions.
 - Rewind the file (move Mmap pointer) so I can tokenize
   and output the bsam cache file.
 - Build a tokenizer and write to bsam cache file.
 - Make a switch from C to Java.
 - Make a parser for object code.
 ****************************************************/

/*** Globals ***/
int Verbose=0;

/*** Globals for fast file access ***/
unsigned char * Mmap = NULL; /* mmap */
int MmapSize=0; /* size of mmap */

/*** Globals for generic values ***/
char *KeywordGeneric[] = {"COMMENT","KEYWORD","NUMBER","STRING","CHAR","VAR","PARM",NULL};

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

struct multitoken
  {
  char *Value;
  char *TokenValue;
  };
typedef struct multitoken multitoken;

/*** Globals for quick parsing ***/
token *Keyword = NULL;
token *KeywordTokens = NULL;
multitoken *KeywordMultiTokens = NULL;

/*** Globals for parsing C and C++ ***/
token KeywordC[] = {
		{0,"auto"}, {0,"break"}, {0,"case"}, {0,"catch"},
		{0,"char"}, {0,"const"}, {0,"continue"}, {0,"default"},
		{0,"do"}, {0,"double"}, {0,"else"}, {0,"enum"},
		{0,"extern"}, {0,"float"}, {0,"for"}, {0,"goto"},
		{0,"if"}, {0,"int"}, {0,"long"}, {0,"register"},
		{0,"return"}, {0,"short"}, {0,"signed"}, {0,"sizeof"},
		{0,"static"}, {0,"struct"}, {0,"switch"}, {0,"throw"},
		{0,"typedef"}, {0,"union"}, {0,"unsigned"}, {0,"void"},
		{0,"volatile"}, {0,"while"}, {0,"NULL"}, {0,NULL}
		};
  /** Token list identifies non-string special words.  List them by length
      since the first match is used.  Any string of symbols not listed
      here are kept as a string of symbols. **/
token KeywordCTokens[] = {
		{0,"<<="}, {0,">>="}, {0,"..."},
		{0,"&&"}, {0,"||"}, {0,"<<"}, {0,">>"}, {0,"->"},
		{0,"++"}, {0,"--"},
		{0,"=="}, {0,"!="}, {0,"<="}, {0,">="},
		{0,"+="}, {0,"-="}, {0,"*="}, {0,"/="}, {0,"%="},
		{0,"&="}, {0,"|="}, {0,"^="},
		{0,"!"}, {0,"+"}, {0,"-"}, {0,"*"}, {0,"/"}, {0,"%"},
		{0,"|"}, {0,"&"}, {0,"^"}, {0,"~"},
		{0,";"}, {0,"<"}, {0,">"}, {0,"="},
		{0,"?"}, {0,":"}, {0,","}, {0,"."},
		{0,NULL} };
  /** MultiTokens are ones that can be separated by whitespace **/
multitoken KeywordCMultiTokens[] = {
		{"# include","#include"},
		{"# import","#import"},
		{"# pragma","#pragma"},
		{"# define","#define"},
		{"# ifndef","$ifndef"},
		{"# ifdef","#ifdef"},
		{"# undef","#undef"},
		{"# else","#else"},
		{"# elif","#elif"},
		{"# if","#if"},
		{"# endif", "#endif"},
		{"# error","#error"},
		{"# using","#using"},
		{"# line","#line"},
		{NULL,NULL} };

/*** Globals for Java source code ***/
token KeywordJava[] = {
		{0,"abstract"}, {0,"boolean"}, {0,"break"}, {0,"byte"},
		{0,"case"}, {0,"catch"}, {0,"char"}, {0,"class"},
		{0,"const"}, {0,"continue"}, {0,"default"}, {0,"do"},
		{0,"double"}, {0,"else"}, {0,"extends"}, {0,"final"},
		{0,"finally"}, {0,"float"}, {0,"for"}, {0,"goto"},
		{0,"if"}, {0,"implements"}, {0,"import"}, {0,"instanceof"},
		{0,"int"}, {0,"interface"}, {0,"long"}, {0,"native"},
		{0,"new"}, {0,"package"}, {0,"private"}, {0,"protected"},
		{0,"public"}, {0,"return"}, {0,"short"}, {0,"static"},
		{0,"strictfp"}, {0,"super"}, {0,"switch"}, {0,"synchronized"},
		{0,"this"}, {0,"throw"}, {0,"throws"}, {0,"transient"},
		{0,"try"}, {0,"void"}, {0,"volatile"}, {0,"while"},
		{0,NULL}
		};
token KeywordJavaTokens[] = {
		{0,"<<="}, {0,">>="}, {0,"..."},
		{0,"&&"}, {0,"||"}, {0,"<<"}, {0,">>"}, {0,"->"},
		{0,"++"}, {0,"--"},
		{0,"=="}, {0,"!="}, {0,"<="}, {0,">="},
		{0,"+="}, {0,"-="}, {0,"*="}, {0,"/="}, {0,"%="},
		{0,"&="}, {0,"|="}, {0,"^="},
		{0,"!"}, {0,"+"}, {0,"-"}, {0,"*"}, {0,"/"}, {0,"%"},
		{0,"|"}, {0,"&"}, {0,"^"}, {0,"~"},
		{0,";"}, {0,"<"}, {0,">"}, {0,"="},
		{0,"?"}, {0,":"}, {0,","}, {0,"."},
		{0,NULL} };
multitoken KeywordJavaMultiTokens[] = {
		{NULL,NULL} };


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
  m = multi-token (set by GetTokenEnd)
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
int	GetTokenEnd	(tokeninfo *TI, char *S, int MaxS)
{
  int i=0;
  int s,si,sj;

  if (!S) return(-1);
  switch(TI->Type)
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
	i=GetStringLen(S,MaxS,TI->Type,1);
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
	  for(s=0; KeywordMultiTokens[s].Value != NULL; s++)
	    {
	    si = i-1; /* temp holder */
	    sj = -1;
	    do
	      {
	      si++;
	      sj++;
	      /* skip spaces */
	      if (isspace(KeywordMultiTokens[s].Value[sj]))
	        {
		/* only skip spaces if the token says to skip spaces */
	        while((si < MaxS) && isspace(S[si])) si++;
	        while(isspace(KeywordMultiTokens[s].Value[sj])) sj++;
		}
	      } while((si < MaxS) && KeywordMultiTokens[s].Value[sj] &&
		  (S[si]==KeywordMultiTokens[s].Value[sj]));
	    /* see if it matched */
	    if (!KeywordMultiTokens[s].Value[sj])
		{
		TI->Type = 'm';
		TI->Generic = KeywordMultiTokens[s].TokenValue;
		return(si);
		}
	    }
	  } /* if KeywordMultiTokens */

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
 MatchKeyword(): Given an input string, does it match a
 keyword?
 Returns index of match, or -1 for miss.
 ********************************************************/
int	MatchKeyword	(S,KeywordList)
  char *S;
  token KeywordList[];
{
  int k;
  for(k=0; KeywordList[k].Value != NULL; k++)
    {
    /* S must match KeywordList and not be a substring. */
    if (!strncmp(S,KeywordList[k].Value,KeywordList[k].Len))
	{
	return(k); /* returns 1+keyword_index */
	}
    }
  return(-1);
} /* MatchKeyword() */

/********************************************************
 GetToken(): Given an input string, return the next token.
 Returns tokeninfo.Start == -1 when no more tokens.
 ********************************************************/
tokeninfo	GetToken	(char *S, int MaxS)
{
  tokeninfo TI;
  int rc;

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
	TI.Generic = KeywordGeneric[2];
	break;
    case '"': /* string */
	TI.Generic = KeywordGeneric[3];
	break;
    case '\'': /* character */
	TI.Generic = KeywordGeneric[4];
	break;
    case 'n': /* variable name */
	TI.Generic = KeywordGeneric[5];
	rc = MatchKeyword(S,Keyword);
	if (rc >= 0)
		{
		TI.Generic = KeywordGeneric[1];
		TI.Value = Keyword[rc].Value;
		TI.Len = Keyword[rc].Len;
		return(TI);
		}
	break;
    default:
	TI.Generic = NULL;
	rc = MatchKeyword(S,Keyword);
	if (rc >= 0)
		{
		TI.Generic = KeywordGeneric[1];
		TI.Value = Keyword[rc].Value;
		TI.Len = Keyword[rc].Len;
		return(TI);
		}
	break;
    }

  TI.Len = GetTokenEnd(&TI,S+TI.Start,MaxS-TI.Start);
  return(TI);
} /* GetToken() */

/**********************************************
 FunctionToBsam(): given a function create the
 bSAM cache output!
 **********************************************/
void	FunctionToBsam	(char *Fname, int FnameLen,
			 long Fstart, long Fend)
{
  printf("Function %.*s : %ld - %ld, size=%ld\n",
	FnameLen,Fname,
	Fstart,Fend,
	Fend-Fstart+1);
} /* FunctionToBsam() */

/**********************************************
 ProcessFunctions(): Identify functions and only
 output them.
 **********************************************/
void	ProcessFunctions	()
{
  /* How to find a function:
     Functions are in the form:
       VAR ( "anything except ; ( ) and { }" )
       "anything except ( ) and { }"
       {
       "anything"
       }
     The "anything except ; and { }" are the parameters.
     Keep track of nested ( ) and { } and [ ] in the
     parameters and in the function.
   */
  /* All items are offsets in the Mmap. */
  char *FunctionName=NULL;
  int FunctionNameLen=0;
  long ParmStart=0;
  long ParmEnd=0;
  long FunctionStart=0;
  long FunctionEnd=0;
  int CountC=0;	/* count "{...}" -- curly bracket nesting */
  tokeninfo TI;
  long i;

  /* Process the file! */
  i=0;
  while(i < MmapSize)
    {
    /* Skip spaces */
    while((i < MmapSize) && isspace(Mmap[i])) i++;
    if (i >= MmapSize) continue; /* skip the rest */

    /* Get the token */
    TI = GetToken((char *)(Mmap+i),MmapSize-i);
    if (TI.Start < 0)
	{
	/* No more tokens */
	i = MmapSize;
	continue; /* skip the rest */
	}

    /* Got a token */
    /* Get function name first */
    if (!FunctionName)
	{
	if (TI.Generic == KeywordGeneric[5])
		{
		FunctionName=(char *)(Mmap+i);
		FunctionNameLen=TI.Len;
		ParmStart=0;
		ParmEnd=0;
		FunctionStart=0;
		FunctionEnd=0;
		}
	}
    else if (ParmStart == 0)
	{
	if (!TI.Generic && (TI.Len==1) && (Mmap[i]=='('))
		{
		ParmStart=i;
		}
	else if (TI.Generic == KeywordGeneric[5]) /* if VAR */
		{
		/* reset function start */
		FunctionName=(char *)(Mmap+i);
		FunctionNameLen=TI.Len;
		ParmStart=0;
		ParmEnd=0;
		FunctionStart=0;
		FunctionEnd=0;
		}
	else if (!TI.Generic && (TI.Len==1) && strchr("{}()",Mmap[i]))
		{
		/* Not a valid start */
		FunctionName=NULL;
		FunctionNameLen=0;
		}
	else if ((TI.Generic == KeywordGeneric[2]) || /* if number */
		 (TI.Generic == KeywordGeneric[3]) || /* if string */
		 (TI.Generic == KeywordGeneric[4])) /* if character */
		{
		/* Not a valid start */
		FunctionName=NULL;
		FunctionNameLen=0;
		}
	/* skip everything else */
	}
    else if (ParmEnd == 0)
	{
	if (!TI.Generic && (TI.Len==1) && (Mmap[i]==')'))
		{
		ParmEnd=i;
		}
	else if (!TI.Generic && (TI.Len==1) && strchr("{}()",Mmap[i]))
		{
		/* Not a valid start */
		FunctionName=NULL;
		FunctionNameLen=0;
		}
	else if ((TI.Generic == KeywordGeneric[2]) || /* if number */
		 (TI.Generic == KeywordGeneric[3]) || /* if string */
		 (TI.Generic == KeywordGeneric[4])) /* if character */
		{
		/* Not a valid start */
		FunctionName=NULL;
		FunctionNameLen=0;
		}
	/* skip everything else */
	}
    else if (FunctionStart == 0)
	{
	if (!TI.Generic && (TI.Len==1) && (Mmap[i]=='{'))
		{
		FunctionStart=i;
		CountC=1;
		}
	else if (!TI.Generic && (TI.Len==1) && strchr("{}()",Mmap[i]))
		{
		/* Not a valid start */
		FunctionName=NULL;
		FunctionNameLen=0;
		}
	else if ((TI.Generic == KeywordGeneric[2]) || /* if number */
		 (TI.Generic == KeywordGeneric[3]) || /* if string */
		 (TI.Generic == KeywordGeneric[4])) /* if character */
		{
		/* Not a valid start */
		FunctionName=NULL;
		FunctionNameLen=0;
		}
	/* skip everything else */
	}
    else /* Got FunctionName, Parms, and FunctionStart */
	{
	if (!TI.Generic && (TI.Len==1) && (Mmap[i]=='{'))
		{
		CountC++;
		}
	else if (!TI.Generic && (TI.Len==1) && (Mmap[i]=='}'))
		{
		CountC--;
		if (CountC==0)
		  {
		  FunctionEnd=i;
		  /* Print the function! */
		  FunctionToBsam(FunctionName,FunctionNameLen,FunctionStart,FunctionEnd);
		  /* Reset and get ready to go again! */
		  FunctionName=NULL;
		  FunctionNameLen=0;
		  }
		}
	}

    i += TI.Len;
#if 0
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
#endif
    }
} /* ProcessFunctions() */


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
  long i;

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

  /* Compute string lengths (for speed) */
  for(i=0; Keyword[i].Value != NULL; i++)
    {
    Keyword[i].Len = strlen(Keyword[i].Value);
    }
  for(i=0; KeywordTokens[i].Value != NULL; i++)
    {
    KeywordTokens[i].Len = strlen(KeywordTokens[i].Value);
    }

  /* Process the file! */
  ProcessFunctions();
  CloseFile(F);
  return(0);
} /* main() */

