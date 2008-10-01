/*********************************************************************
 Filter_License: Given a file, generate a bSAM cached license file.

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

 This uses the DB and repository.
 All output is written to the repository.
 *********************************************************************/

#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <stdint.h>
#include <ctype.h>
#include <string.h>
#include <signal.h>

#include "libfossdb.h"
#include "libfossrepo.h" /* repository functions */
#include "Filter_License.h"
#include "tokholder.h"
#include "1sl.h"
#include "wordcheck.h"
#include "../../ununpack/checksum.h"

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
char Version[]=SVN_REV;
#endif

int Verbose=0;	/* how verbose? (for debugging) */
int AddToDB=0;  /* should the license be added to the license table? */
int UpdateDB=1;	/* should process results go into the DB/repository? */

/**********************************************************************/
/***** globals for printing *******************************************/
/**********************************************************************/
int FirstOut=0;	/* is this the first data out? */
int SectionCount=1; /* global, always incrementing */
char Line[4096];	/* input buffer */
char Filename[FILENAME_MAX];	/* file name of the raw data source */
char Pfile_fk[256];	/* file name of the raw data source */
FILE *Fout=NULL;	/* file handle for output */
char *RepSrc="files";	/* name of repository type */
char *RepDst="license";	/* name of repository type */

/* for DB */
void	*DB=NULL;
char	SQL[1024];
int	Lic_Id=-1;	/* when adding to DB, this is the Id number */
int	Agent_pk=-1;	/* the agent ID */

/* for Meta information */
FILE	*MetaFile=NULL;

/**************************************************
 ShowHeartbeat(): Given an alarm signal, display a
 heartbeat.
 **************************************************/
void    ShowHeartbeat   (int Sig)
{

  /* IF we are tracking hearbeat values AND it has not changed,
     THEN don't display a heartbeat message.
     This can happen if I/O is hung, but alarms are still being processed.
   */
  printf("Heartbeat\n");
  fflush(stdout);
  alarm(60);
} /* ShowHeartbeat() */

/***********************************************************************/
/** Functions to tokenize words **/
/***********************************************************************/

/*********************************************
 StringToToken(): Given a string, generate
 two-byte token value.
 *********************************************/
tokentype	StringToToken	(char *S, int LenS)
{
  int i;
  tokentype Sum=0;

  for(i=0; i < LenS; i++)
    {
    /* high order byte */
    if ((i & 0x1) == 0) { Sum += (S[i]*256); }
    /* lower order byte */
    else { Sum += S[i]; }
    }
  if (Verbose > 1)
    {
    fprintf(stderr,"StringToToken: %04x '%.*s'\n",Sum,LenS,S);
    }
  return(Sum);
} /* StringToToken() */

/*********************************************
 PrintString(): Print a bsam stringlen + string.
 Ensures output contains even number of bytes.
 String MUST BE null terminated.
 Strings are TRUNCATED to a max of 63334 bytes.
 *********************************************/
void	PrintString	(char *S)
{
  int i,j;
#if 0
  for(i=0; S[i]!='\0'; i++) ;
  i++;
#else
  i = strlen((char *)S)+1;
#endif
  if (i > 65534) i=65534;
  fputc((i>>8)&0xff,Fout);
  fputc(i&0xff,Fout);
  for(j=0; j < i; j++) fputc(S[j],Fout);
  if (i & 0x01) fputc(0xff,Fout); /* pack to 2-byte boundary */
} /* PrintString() */

/*********************************************
 ResetPrinting(): Reset all global print variables.
 *********************************************/
inline	void	ResetPrinting	()
{
  FirstOut=0;
  if (UpdateDB)
    {
    if (Fout) RepFclose(Fout);
    Fout=NULL;
    }
  SectionCount=1;
} /* ResetPrinting() */

/*********************************************
 FileReadLine(): Read a line from a file.
 Ending \n is not saved.
 Skips blank lines.
 Returns line length, or -1 on EOF.
 if length == Bufsize, then line was not completely read.
 *********************************************/
int	FileReadLine	(FILE *Fin, char *Buf, int Bufsize)
{
  int Len;
  int c;
  memset(Buf,'\0',Bufsize);
reread:
  for(Len=0; Len < Bufsize; Len++)
	{
	c=fgetc(Fin);
	if (c<0) { return(-1); }
	if (c=='\n')
	  {
	  /* retry if empty line */
	  if (Len == 0) goto reread;
	  return(Len);
	  }
	Buf[Len] = c;
	}
  /* read a partial line, return the part */
  return(Len);
} /* FileReadLine() */

/*********************************************
 AddMetaToDB(): update a license with meta info.
 *********************************************/
void	AddMetaToDB	(int LicPk)
{
  int Len;

  if (!MetaFile || !DB) return;	/* nothing to process */
  rewind(MetaFile);

  while(FileReadLine(MetaFile,Line,sizeof(Line)) >= 0)
    {
    if (!strncmp(Line,"URL:",4))
	{
	Len = 4;
	while(isspace(Line[Len])) Len++; /* skip space */
	memset(SQL,'\0',sizeof(SQL));
	snprintf(SQL,sizeof(SQL),"UPDATE agent_lic_raw SET lic_url = '%s' WHERE lic_pk = %d;",Line+Len,LicPk);
	if (DBaccess(DB,SQL) < 0)
	  {
	  fprintf(stderr,"FATAL: SQL failed: %s\n",SQL);
	  DBclose(DB);
	  exit(-1);
	  }
	}
#if 0
    else if (!strncmp(Line,"Date:",5))
	{
	/** "Date:" is the oldest date known for this license.
	    However, lic_date is the date added to the DB.
	    Thus, this field is currently ignored.
	 **/
	Len = 5;
	while(isspace(Line[Len])) Len++; /* skip space */
	memset(SQL,'\0',sizeof(SQL));
	snprintf(SQL,sizeof(SQL),"UPDATE agent_lic_raw SET lic_date = '%s' WHERE lic_pk = %d;",Line+Len,LicPk);
	if (DBaccess(DB,SQL) < 0)
	  {
	  fprintf(stderr,"FATAL: SQL failed: %s\n",SQL);
	  DBclose(DB);
	  exit(-1);
	  }
	}
#endif
    }
} /* AddMetaToDB() */

/*********************************************
 AddCanonicalName(): Make sure the name exists in the DB.
 *********************************************/
void	AddCanonicalName	(char *Filename, int LicId)
{
  char SQL[1024]; /* SQL */
  char Name[1024]; /* the canonical name */
  char *S;
  int i,j;
  long LicTermId;

  S = strrchr(Filename,'/');
  if (S) { S++; /* pass the slash */ }
  else S=Filename;

  /* copy over the name, and taint the string as we go */
  memset(Name,'\0',sizeof(Name));
  for(i=0,j=0; (j<sizeof(Name)-2) && S[i]; i++)
    {
    if (S[i]=='\'') { Name[j++]='\''; Name[j++]='\''; }
    else if (isprint(S[i])) { Name[j++] = S[i]; }
    }

  /* remove unnecessary strings */
  S=strstr(Name," part"); if (S) S[0]='\0';
  S=strstr(Name," short"); if (S) S[0]='\0';
  S=strstr(Name," variant"); if (S) S[0]='\0';
  S=strstr(Name," reference"); if (S) S[0]='\0';
  S=strstr(Name," ("); if (S) S[0]='\0';

  /* Check if it exists in the DB */
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"SELECT licterm_pk FROM licterm WHERE licterm_name = '%s';",Name);
  DBaccess(DB,SQL);
  if (DBdatasize(DB) < 1)
	{
	// fprintf(stderr,"Not in DB: '%s'\n",Name);
	memset(SQL,'\0',sizeof(SQL));
	snprintf(SQL,sizeof(SQL),"INSERT INTO licterm (licterm_name) VALUES ('%s');",Name);
	DBaccess(DB,SQL);
	memset(SQL,'\0',sizeof(SQL));
	snprintf(SQL,sizeof(SQL),"SELECT licterm_pk FROM licterm WHERE licterm_name = '%s';",Name);
	DBaccess(DB,SQL);
	}
  LicTermId = atol(DBgetvalue(DB,0,0));

  /* Now associate the license if there is no current association */
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"SELECT * FROM licterm_maplic WHERE lic_fk = '%d';",LicId);
  DBaccess(DB,SQL);
  if (DBdatasize(DB) < 1)
	{
	// fprintf(stderr,"Not linked in DB: '%s' (link it to %ld)\n",Name,LicTermId);
	memset(SQL,'\0',sizeof(SQL));
	snprintf(SQL,sizeof(SQL),"INSERT INTO licterm_maplic (licterm_fk,lic_fk) VALUES ('%ld','%d');",LicTermId,LicId);
	DBaccess(DB,SQL);
	}

  /* Make sure all records are updated */
  memset(SQL,'\0',sizeof(SQL));
  snprintf(SQL,sizeof(SQL),"UPDATE licterm_name SET licterm_fk = %ld WHERE licterm_fk IS NULL AND agent_lic_meta_fk IN (SELECT DISTINCT agent_lic_meta_pk FROM agent_lic_meta INNER JOIN agent_lic_raw ON lic_id = %d AND lic_fk = lic_pk);",LicTermId,LicId);
  DBaccess(DB,SQL);
  // fprintf(stderr,"%s\n",SQL);
} /* AddCanonicalName() */

/*********************************************
 AddPhrase(): Phrase is a static license.
 *********************************************/
void	AddPhrase	()
{
  int rc;
  long TermId;
  char SQL[1024]; /* SQL */

  rc = DBaccess(DB,"SELECT lic_pk FROM agent_lic_raw WHERE lic_name = 'Phrase';");
  if ((rc < 0) || (DBdatasize(DB) <= 0))
    {
    DBaccess(DB,"INSERT INTO agent_lic_raw (lic_name,lic_unique,lic_text,lic_version,lic_section,lic_id) VALUES ('Phrase','1','Phrase','1',1,1);");
    }

  rc = DBaccess(DB,"SELECT licterm_pk FROM licterm WHERE licterm_name = 'Phrase';");
  if ((rc < 0) || (DBdatasize(DB) <= 0))
    {
    DBaccess(DB,"INSERT INTO licterm (licterm_name) VALUES ('Phrase');");
    DBaccess(DB,"SELECT licterm_pk FROM licterm WHERE licterm_name = 'Phrase';");
    }
  TermId = atol(DBgetvalue(DB,0,0));

  /* Make sure Phrase exists in the licterm_maplic table */
  rc = DBaccess(DB,"SELECT licterm_maplic_pk FROM licterm_maplic WHERE lic_fk = 1;");
  if ((rc < 0) || (DBdatasize(DB) <= 0))
    {
    memset(SQL,'\0',sizeof(SQL));
    snprintf(SQL,sizeof(SQL),"INSERT INTO licterm_maplic (licterm_fk,lic_fk) VALUES (%ld,1);",TermId);
    DBaccess(DB,SQL);
    }

  /* Update any records that need migration */
  DBaccess(DB,"UPDATE licterm_name SET licterm_fk = (SELECT licterm_pk FROM licterm WHERE licterm_name = 'Phrase') WHERE licterm_fk IS NULL AND agent_lic_meta_fk IN (SELECT DISTINCT agent_lic_meta_pk FROM agent_lic_meta INNER JOIN agent_lic_raw ON lic_name = 'Phrase' AND lic_fk = lic_pk);");
} /* AddPhrase() */

/*********************************************
 AddLicenseToDB(): Given a license, add it to
 the database.
 This requires the unique value, license name,
 and full license text.
 Returns the license Id.
 *********************************************/
int	AddLicenseToDB	(int Lic_Id, char *Unique, char *Filename,
			 char *Section, fileoffset Start, fileoffset Length,
			 int TokCount)
{
  char LicSQL[1024]; /* the license-insert SQL */
  fileoffset MStart; /* start location into mmap */
  int LastAddPk;

  MStart = Start - TH.Start;

  /* Check before adding */
  memset(LicSQL,'\0',sizeof(LicSQL));
  sprintf(LicSQL,"SELECT lic_pk FROM agent_lic_raw WHERE lic_unique = '%s';",Unique);
  DBaccess(DB,LicSQL);
  if (DBdatasize(DB) <= 0) /* if it did not find the data, then INSERT */
    {
    /* Create the SQL */
    memset(LicSQL,'\0',sizeof(LicSQL));
    sprintf(LicSQL,"INSERT INTO agent_lic_raw (lic_id,lic_name,lic_section,lic_unique,lic_tokens) VALUES ('-1','%s','%s','%s','%d');",Filename,Section,Unique,TokCount);

    /* Ok, we have the SQL query */
    switch(DBaccess(DB,LicSQL))
      {
      case 0: /* good SELECT */
      case 1: /* good INSERT */
	break;
      default:
	{
	fprintf(stderr,"ERROR pfile %s Bad database access.\n",Pfile_fk);
	fprintf(stderr,"LOG pfile %s SQL error: %s\n",Pfile_fk,LicSQL);
	return(-1);
	}
      }
    } /* if INSERT */
  else /* UPDATE record */
    {
    memset(LicSQL,'\0',sizeof(LicSQL));
    sprintf(LicSQL,"UPDATE agent_lic_raw SET lic_name='%s',lic_section='%s',lic_tokens='%d' WHERE lic_unique='%s' AND lic_version='1';",Filename,Section,TokCount,Unique);
    DBaccess(DB,LicSQL);
    }

  /* We inserted!  Find the new primary key. */
  memset(LicSQL,'\0',sizeof(LicSQL));
  sprintf(LicSQL,"SELECT lic_pk FROM agent_lic_raw WHERE lic_name='%s' AND lic_section='%s' AND lic_unique='%s' AND lic_version='1';",Filename,Section,Unique);
  if (DBaccess(DB,LicSQL) < 0)
	{
	fprintf(stderr,"ERROR pfile %s Bad database access.\n",Pfile_fk);
	fprintf(stderr,"LOG pfile %s SQL error: %s\n",Pfile_fk,LicSQL);
	return(-1);
	}
  LastAddPk = atoi(DBgetvalue(DB,0,0));
  if (Lic_Id == -1) Lic_Id = LastAddPk;

  /* Re-add the key as the DB item */
  memset(LicSQL,0,1024);
  sprintf(LicSQL,"UPDATE agent_lic_raw SET lic_id='%d' WHERE lic_pk='%d';",Lic_Id,LastAddPk);
  if (DBaccess(DB,LicSQL) < 0)
	{
	fprintf(stderr,"FATAL: SQL failed: %s\n",LicSQL);
	DBclose(DB);
	exit(-1);
	}

  /* Add in meta info */
  AddMetaToDB(LastAddPk);

  /* Add canonical name to the DB */
  AddCanonicalName(Filename,Lic_Id);

#if 0
  /** Disabled: Database will take care of this **/
  /* Analyze table (for performance) */
  DBaccess(DB,"ANALYZE agent_lic_raw;");
#endif
  return(Lic_Id);
} /* AddLicenseToDB() */

/*********************************************
 PrintFirstOut(): At the first sign of output,
 display the data information.
 Returns: 0 on success, 1 on error.
 *********************************************/
inline int	PrintFirstOut	()
{
  if (FirstOut) return(0);

  /* Display file information */
  FirstOut=1; /* set flag so this is not called again */

  /* open repository for writing */
  if (UpdateDB)
    {
    if (Fout) RepFclose(Fout);
    Fout=RepFwrite(RepDst,Filename);
    if (!Fout) { return(1); }
    stderr = stdout; /* send errors to stdout */
    }
  else
    {
    Fout=stdout;
    /* errors go to stderr */
    }

  /* save file name */
  fputc(0x00,Fout); fputc(0x01,Fout); /* tag 0x0001 = file name */
  if (Filename[0]=='\0') PrintString("-");
  else	PrintString(Filename);

  /* save file type */
  fputc(0x00,Fout); fputc(0x04,Fout); /* tag 0x0004 = file type */
  PrintString("text");

  return(0);
} /* PrintFirstOut() */

/**************************************************
 PrintTokens(): Generate the bSAM file.
 Determine all tokens within GOODWORDDIST of a key word.
 Only displays those tokens.
 NOTE: This is recursive.
    Token = list of tokens
    TokenOffsets = fileoffsets to Token (this is 1 token longer for end)
 It processes the first bytes and recurses on the rest.
 Returns: 1 if tokens printed, 0 if none printed, -1 on error.
 **************************************************/
int	PrintTokens	(tokentype *Token, fileoffset *TokenOffsets,
			 long TokenCount)
{
  int i;
  int OrListSize;
  fileoffset Start,End,Length;
  int rc;
  char SectionString[256];
  char *Unique=NULL;

  if (TokenCount < 3) return(0); /* too few tokens */
  if (!GetGoodWordRange(Token,TokenCount,&Start,&End)) return(0);
  Length = End - Start;
  if (Length < 12) return(0); /* too short; min = "this is free" */
  if (Length > TokenCount) return(0); /* no strings of one-characters */

  /* Range is set! */
  if (Verbose)
    {
    fprintf(stderr,"  PrintTokens Range: %08x - %08x :: %d - %d\n",TokenOffsets[Start],TokenOffsets[End],Start,End);
    }

  OrListSize = GetOrList(Token+Start,Length);
  if (OrListSize > 0)
    {
    /* Only create Fout if there is data to print */
    /** This prevents hundreds of zero-length files */
    if (PrintFirstOut())	return(-1);

    rc=1;

    /* it's good!  Sent it to Fout! */
    if (SectionCount > 1)
      {
      fputc(0x01,Fout); fputc(0xff,Fout); /* tag 0x01ff = End of function */
      fputc(0x00,Fout); fputc(0x00,Fout); /* length = 0 */
      }
    memset(SectionString,'\0',sizeof(SectionString));
    if (Verbose) fprintf(stderr,"*** Section_%d ***\n",SectionCount);
    snprintf(SectionString,sizeof(SectionString),"Section_%d",SectionCount);
    fputc(0x01,Fout); fputc(0x01,Fout); /* tag 0x0101 = name */
    PrintString(SectionString);
    if (DB && AddToDB)
	{
	Cksum *Sum;
	CksumFile CFile;
	CFile.Mmap = TH.Raw;
	CFile.MmapSize = TokenOffsets[End]-TokenOffsets[Start];
	Sum = SumComputeBuff(&CFile);
	if (Sum) { Unique = SumToString(Sum); free(Sum); }
	fputc(0x01,Fout); fputc(0x10,Fout); /* tag 0x0110 = unique value */
	PrintString(Unique);
	Lic_Id = AddLicenseToDB(Lic_Id,Unique,Filename,SectionString,TokenOffsets[Start],TokenOffsets[End]-TokenOffsets[Start],End-Start);
	free(Unique);
	Unique=NULL;
	}
#if 0
    /*** DEBUG CODE: displays section text ***/
    else
	{
	Cksum *Sum;
	CksumFile CFile;
	CFile.Mmap = TH.Raw;
	CFile.MmapSize = TokenOffsets[End]-TokenOffsets[Start];
	  {
	  int i;
	  fprintf(stderr,">--- %ld bytes [ %d - %d ] -----------------------\n",(long)(CFile.MmapSize),TokenOffsets[Start],TokenOffsets[End]);
	  for(i=0; i<CFile.MmapSize; i++)  fputc(CFile.Mmap[i],stderr);
	  fprintf(stderr,"\n");
	  fprintf(stderr,"<--------------------------\n");
	  }
	Sum = SumComputeBuff(&CFile);
	if (Sum) { Unique = SumToString(Sum); free(Sum); }
	fputc(0x01,Fout); fputc(0x10,Fout); /* tag 0x0110 = unique value */
	PrintString(Unique);
	free(Unique);
	Unique=NULL;
	}
#endif
    SectionCount++;

    /* print OR list */
    fputc(0x01,Fout); fputc(0x18,Fout); /* tag 0x0118 = OR list*/
    /* save OR size */
    fputc(((OrListSize*2)>>8)&0xff,Fout);
    fputc((OrListSize*2)&0xff,Fout);
    /* save each OR tag */
    for(i=0; GoodWords[i] != NULL; i++)
	{
	if (GoodWordListOR[i])
	      {
	      fputc((GoodWordList[i] >> 8)&0xff,Fout);
	      fputc((GoodWordList[i])&0xff,Fout);
	      OrListSize--;
	      }
	}
    if (OrListSize != 0)
	{
	fprintf(stderr,"ERROR pfile %s Bad file processing. Contact your administrator.\n",Pfile_fk);
	fprintf(stderr,"LOG pfile ERROR: %s Bad OR list size (off by %d)\n",Pfile_fk,OrListSize);
	}

    /********/
    /* save start and end offsets into real data file */
    /* Range is for(i=Start; i<End; i++) */
    fputc(0x01,Fout); fputc(0x31,Fout); /* tag 0x0131 = start */
    fputc(0x00,Fout); fputc(0x04,Fout); /* 4 bytes */
    fputc((TokenOffsets[Start] >> 24) & 0xff,Fout);
    fputc((TokenOffsets[Start] >> 16) & 0xff,Fout);
    fputc((TokenOffsets[Start] >> 8) & 0xff,Fout);
    fputc((TokenOffsets[Start]) & 0xff,Fout);

    fputc(0x01,Fout); fputc(0x32,Fout); /* tag 0x0132 = end */
    fputc(0x00,Fout); fputc(0x04,Fout); /* 4 bytes */
    fputc((TokenOffsets[End] >> 24) & 0xff,Fout);
    fputc((TokenOffsets[End] >> 16) & 0xff,Fout);
    fputc((TokenOffsets[End] >> 8) & 0xff,Fout);
    fputc((TokenOffsets[End]) & 0xff,Fout);
    if (Verbose) fprintf(stderr,"  File range: %x - %x\n",TokenOffsets[Start],TokenOffsets[End]);

    /********/
    /* save token offsets */
    fputc(0x01,Fout); fputc(0x38,Fout); /* tag 0x0138 = token offset list */
    /* save size */
    fputc(((Length)>>8)&0xff,Fout);
    fputc( (Length)    &0xff,Fout);
    /* save length of each token offset */
    for(i=Start; i < End; i++)
	{
	fputc( (TokenOffsets[i+1]-TokenOffsets[i]) &0xff,Fout);
	}
    /* make sure we end on a boundary */
    if (((Length)%2)==1) fputc(0xff,Fout);

    /********/
    /* save tokens */
    fputc(0x01,Fout); fputc(0x08,Fout); /* tag 0x0108 = token list */
    /* save size */
    fputc(((Length*2)>>8)&0xff,Fout);
    fputc( (Length*2)    &0xff,Fout);
    /* save each token */
    for(i=Start; i < End; i++)
	{
	fputc((Token[i] >> 8)&0xff,Fout);
	fputc((Token[i])     &0xff,Fout);
	}

    Check1SL(TokRevOffset(TokenOffsets[Start]),TokRevOffset(TokenOffsets[End]),Fout);
    } /* if has OrList */
  else rc=0;

  /* recurse on remainder */
  if (End < TokenCount)
    {
    rc |= PrintTokens(Token+End,TokenOffsets+End,TokenCount-End);
    }
  return(rc);
} /* PrintTokens() */

/*********************************************
 Prep2bSAM(): Take a line of preprocessed data
 and output SAM data.
 Returns: 0 on success, -1 on error.
 Assumes global TH holds the line's data.
 *********************************************/
int	Prep2bSAM	()
{
  tokentype Token[MAX_TOKEN_LOAD];
  fileoffset TokenOffsets[MAX_TOKEN_LOAD];
  long TokenOffsetStart;
  long TokenCounter=0; /* how many tokens created? */
  char Word[256];
  int WordLen=0;
  int Start=0;
  int C;
  int v;

  /* idiot checking */
  if (TH.PreLineLen < 1) return(0);  /* nothing to process */

  /* initializing */
  WordLen=0;
  TokenCounter=0;
  TokenOffsetStart=-1;

  /* find start of the string */
  v=0;
  while(isspace(TH.PreLine[v])) v++;
  Start = v;

  for( ; v < TH.PreLineLen; v++)
    {
    C=TH.PreLine[v];
    if (C==' ')
	{
	/* increment to the next token */
	Word[WordLen]='\0';
	Token[TokenCounter] = StringToToken(Word,WordLen);
	TokenOffsets[TokenCounter] = TokenOffsetStart;
	TokenOffsetStart=-1;
	if (Verbose > 1)
	  {
	  fprintf(stderr,"Token %ld: %04x len=%d @ %0x = \"%.*s\"\n",
	    TokenCounter,Token[TokenCounter],WordLen,TokenOffsets[TokenCounter],WordLen,Word);
	  }
	WordLen=0;
	TokenCounter++;
	if (TokenCounter >= MAX_TOKEN_LOAD-1)
	  {
	  TokenOffsets[TokenCounter] = TokOffset(v); /* save end */
	  if (PrintTokens(Token,TokenOffsets,TokenCounter) < 0)
		{
		return(-1);
		}
	  TokenCounter=0;
	  Start=v+1;
	  }
	}
    else /* character is a token */
	{
	if (WordLen < sizeof(Word))
	  {
	  Word[WordLen] = C;
	  WordLen++;
	  }
	if (TokenOffsetStart < 0) TokenOffsetStart = TokOffset(v);
	}
    } /* foreach v in TH.PreLine */

  /* any final tokens... */
  if (WordLen > 0)
    {
    Token[TokenCounter] = StringToToken(Word,WordLen);
    TokenOffsets[TokenCounter] = TokenOffsetStart;
    TokenOffsetStart=-1;
    if (Verbose > 1)
	  {
	  fprintf(stderr,"Token %ld: %04x len=%d @ %0x = \"%.*s\"\n",
	    TokenCounter,Token[TokenCounter],WordLen,WordLen,TokenOffsets[TokenCounter],Word);
	  }
    TokenCounter++;
    WordLen=0;
    }
  if (TokenCounter >= 3) /* minimum = 3: copyright date owner */
    {
    TokenOffsets[TokenCounter] = TokOffset(v); /* save end */
    if (PrintTokens(Token,TokenOffsets,TokenCounter) < 0)
		{
		return(-1);
		}
    }
  return(0);
} /* Prep2bSAM() */

/***********************************************************************/
/** Functions to read original file and generate pre-processed temp file **/
/***********************************************************************/

/*********************************************
 WriteMarker(): Store a marker in a text file.
 The marker followed by 4 bytes for the
 byte offset of the source.
 *********************************************/
void	WriteMarker	(int Marker, FILE *Fout, long Offset)
{
  if (Verbose > 1) fprintf(stderr,"Marked: %02x %08lx\n",Marker,Offset);
  fputc(Marker & 0xff,Fout);
  fputc((Offset >> 24) & 0xff,Fout);
  fputc((Offset >> 16) & 0xff,Fout);
  fputc((Offset >> 8) & 0xff,Fout);
  fputc(Offset & 0xff,Fout);
} /* WriteMarker() */

/*********************************************
 CopyrightYearCheck(): A copyright may be associated
 with a series of years.  This function finds the
 end of the year list (stopping at 250 characters).
 The list must be in the form:
   4-digit year
   followed by a comma or hyphen
   followed by any amount of whitespace (but not newline) and !isalnum
   followed by another 4-digit year.
 NOTE: Year lists cannot span lines!
 I'm hard-coding this: all years MUST begin with a 19 or 20.
 (The year "0123" and "2103" are not valid.)
 Returns number of bytes matched.
 *********************************************/
int	CopyrightYearCheck	(char *Mmap, fileoffset MaxLen)
{
  fileoffset Len=0;
  fileoffset Spaces=0;

  if (MaxLen > 250) MaxLen=250;
  while(Len < MaxLen)
    {
    /* Check if enough room for a year (minimum: 4 digits + \n) */
    if (Len + Spaces + 5 > MaxLen) return(Len);
    /* Check for a year... */
    if (!strncmp(Mmap+Len+Spaces,"19",2) + !strncmp(Mmap+Len+Spaces,"20",2) != 1)
	{
	return(Len);
	}
    if (!isdigit(Mmap[Len+2+Spaces]) || !isdigit(Mmap[Len+3+Spaces]))
	{
	return(Len);
	}
    if (isalnum(Mmap[Len+4+Spaces]))
	{
	return(Len);
	}
    /* Got a year! */
    Len += 4 + Spaces;

    /* Comma or hyphen is permitted */
    if ((Len < MaxLen) && strchr(",-",Mmap[Len]))
	{
	Len++;
	}

    /* Skip spaces */
    Spaces=0;
    while((Len+Spaces < MaxLen) && isspace(Mmap[Len+Spaces]))
	{
	Spaces++;
	}
    /* Loop back and check for another date */
    }
  return(Len);
} /* CopyrightYearCheck() */

/*********************************************
 PreprocessFile(): Load raw datafile and convert
 it to a pre-processed format.
 This takes care of case, language conversion, and
 everything else.
 Loads global TH to contain the token-ready string.
 Tokens are generated each time TokClear is called.
 Returns: 1 on success, 0 on error

 NOTE: In this code are a number of "C='char'; C=' ';"
 This is intentional.
 Originally, Filter_License kept non-letter symbols.
 In September 2008, the decision was made to remove
 non-alphanumeric characters.  Setting C=' ' removes it.
 But, I didn't want to lose the old character.
 *********************************************/
int	PreprocessFile	(int UseRep)
{
  RepMmapStruct *Rep;
  fileoffset i;
  fileoffset ii;
  fileoffset BytesLeft=0;
  fileoffset FileLocation=0;
  fileoffset YearLength; /* used for copyright processing */
  int C;
  int LastC=' ';	/* last C value */
  char S[256];	/* used when C==-2 */
  /* for finding copyright blocks */
  int NewLine;
  int LinesFromCopyright; /* how many lines away was the last copyright? */
  int GotTextFlag=0; /* am I outputting a text block? */
  float Percent=0, PercentInc=0; /* used for debugging */

  if (Verbose)
	fprintf(stderr,"Child[%d] Preprocessing: %s %s\n",
	getpid(),RepSrc,Filename);

  if (UseRep)
	{
	/* Check if file exists, then use it */
	if (RepExist(RepSrc,Filename))
	  {
	  Rep = RepMmap(RepSrc,Filename);
	  }
	else
	  {
	  /* nothing to process */
	  printf("LOG pfile %s File does not exist in repository: '%s' '%s'\n",Pfile_fk,RepSrc,Filename);
	  /* Returning 1 will mark it in the DB as processed and not in repo */
	  return(1);
	  }
	}
  else Rep = RepMmapFile(Filename);
  if (!Rep)
    {
    if (Verbose) fprintf(stderr,"FAIL: Unable to mmap '%s'\n",Filename);
    return(0);
    }
  NewLine=1;
  LinesFromCopyright=1000;  /* big number = not part of same block */
  TokClear(Rep->Mmap,0,GotTextFlag);

  Lic_Id = -1; /* no license Id yet */
  FileLocation=0;
  PercentInc = Rep->MmapSize/100.0; /* 1% (rough estimate due to rounding) */
  Percent = 5;

  /* the BIG parsing loop! */
  /** This is slow for massive files.  It could take more than a minute.
      But we want to make sure it is alive. **/
  for(i=0; i < Rep->MmapSize; i++)
    {
    if (Verbose)
      {
      if (i >= PercentInc*Percent)
	{
	fprintf(stderr,"Processed %.0f%% @ %08x\n",Percent,i);
        Percent += 5;
	}
      }

    /* Now process the data as regular data */
    BytesLeft = Rep->MmapSize-i-1;
    C = Rep->Mmap[i];
    /* idiot checking for loops */
    if (i < FileLocation) return(1); /* we looped a uint32_t -- good enough */
    FileLocation=i; /* save location since i can change */

    /* If it is a newline, check for copyright statements */
    if (NewLine)
      {
      int LineLength,j;
      int HasCopyright;
      int HasYear;

      LinesFromCopyright++;
      NewLine=0;

      /* how long is the line? */
      LineLength=0;
      while((LineLength < BytesLeft) && (Rep->Mmap[i+LineLength] != '\n')
        && (Rep->Mmap[i+LineLength] != '\r'))
	{
	LineLength++;
	}

      /* check for a copyright (word + year) */
      HasCopyright=0;
      HasYear=0;
      for(j=0; (j<LineLength) && !(HasCopyright && HasYear); j++)
        {
	/** if match, move j.  Remember: for will also do a j++ **/
	if (!HasCopyright && (LineLength-j >= 9) &&
	    !strncasecmp((char *)(Rep->Mmap+i+j),"copyright",9))
		{ HasCopyright=1; j=j+8; }
	if (!HasCopyright && (LineLength-j >= 3) &&
	    !strncasecmp((char *)(Rep->Mmap+i+j),"(c)",3))
		{ HasCopyright=1; j=j+2; }
	if (!HasCopyright && (LineLength-j >= 6) &&
	    !strncasecmp((char *)(Rep->Mmap+i+j),"&copy;",6))
		{ HasCopyright=1; j=j+5; }
	/** UTF-8 **/
	if (!HasCopyright && (LineLength-j >= 2) &&
	    (Rep->Mmap[i+j] == 0xC2) && (Rep->Mmap[i+j+1] == 0xAE))
		{ HasCopyright=1; j=j+1; }
	/** ISO8859 **/
	if (!HasCopyright && (Rep->Mmap[i+j] == 0xAE))
		{ HasCopyright=1; }

	/* try to ignore generic numbers, but accept years */
	if (!HasYear)
	  {
	  YearLength = CopyrightYearCheck((char *)(Rep->Mmap+i+j),LineLength-j);
	  if (YearLength > 0)
	    {
#if 0
	    fprintf(stderr,"YEAR: [%d] '%.*s'\n",YearLength,YearLength,Rep->Mmap+i+j);
#endif
	    HasYear = 1;
	    j = j + YearLength;
	    }
	  else /* skip numbers, +/-, dots */
	    {
	    int MovedIndex=0;
	    while((j<LineLength) &&
	          (isdigit(Rep->Mmap[i+j]) || strchr("+-.",Rep->Mmap[i+j])))
		  {
		  MovedIndex=1;
		  j++;
		  }
	    if (MovedIndex) j--; /* let the for-loop move it */
	    }
	  }
	} /* for j */
      if (HasCopyright && HasYear)
        {
	if (LinesFromCopyright > 5)
	  {
	  /* only mark off the section if the copyright lines are far enough
	     apart. */
	  TokClear((Rep->Mmap)+FileLocation,FileLocation,GotTextFlag);
	  GotTextFlag=1;
	  }
	LinesFromCopyright=0;
	}
      }

    /* convert characters */
    if (C=='\n') { NewLine=1; C=' '; }
    else if (C=='\r') { NewLine=1; C=' '; }
    else if (isupper(C)) C=tolower(C); /* make it lowercase */
    else if (isspace(C)) C=' ';

    /* convert UTF-8, 2-byte words */
    if ((BytesLeft >= 1) && (C >= 0xC0) && (C <= 0xDF) &&
        (Rep->Mmap[i+1] >= 0x80))
      {
      i++;
      if (C==0xC2) switch(Rep->Mmap[i])
        {
	case 0xA1:	C='!'; C=' '; break; /* inverted ! */
	case 0xA9:	C=-2; strcpy(S,"copyright"); break; /* copyright */
	case 0xAE:	C=-3; strcpy(S,"Registered"); break; /* Registered */
	case 0xB1:	C=-4; strcpy(S,"+-"); break; /* +/- */
	case 0xB4:	C='\''; C=' '; break; /* accent */
	case 0xBF:	C='?'; C=' '; break; /* inverted ? */
	default:	C=' ';	break;
	}
      else C=' ';
      } /* UTF-8, 2 bytes */

    /* convert UTF-8, 3-byte words */
    else if ((BytesLeft >= 2) && (C >= 0xE0) && (C <= 0xEF) &&
             (Rep->Mmap[i+1] >= 0x80) && (Rep->Mmap[i+2] >= 0x80))
      {
      i+=2;
      if ((C==0xE2) && (Rep->Mmap[i-1] == 0x80))
        {
        switch(Rep->Mmap[i])
          {
	  case 0x95:	C='-'; break; /* horizonal bar */
	  case 0x98: case 0x99: case 0x9A: case 0x9B:
	  	C='\''; C=' '; break;	/* different types of quotes */
	  case 0x9C: case 0x9D: case 0x9E: case 0x9F:
	  	C='"'; C=' '; break;	/* different types of quotes */
	  default:	C=' ';	break;
	  }
	}
      else C=' ';
      } /* UTF-8, 3 bytes */

    /* convert UTF-8, 4-byte words */
    else if ((BytesLeft >= 3) && (C >= 0xF0) &&
             (Rep->Mmap[i+1] >= 0x80) && (Rep->Mmap[i+2] >= 0x80) &&
	     (Rep->Mmap[i+1] >= 0x80))
      {
      C=' ';
      i+=3;
      } /* UTF-8, 4 bytes */

    /* Not UTF-8? Try ISO8859! */
    else if ((C & 0xf0) >= 0x80)
      {
      switch(C)
        {
	case 0x91:	C='\''; C=' '; break; /* quote in */
	case 0x92:	C='\''; C=' '; break; /* quote out */
	case 0x93:	C='"'; C=' '; break; /* quote in */
	case 0x99:	C=-5; strcpy(S,"TM"); C=' '; break; /* trademark */
	case 0xA0:	C=' '; break; /* alternate space */
	case 0xA9:	C=-2; strcpy(S,"copyright"); break; /* Copyright */
	case 0xAE:	C=-3; strcpy(S,"Registered"); C=' '; break; /* Registered */
	case 0xD5:	C='\''; C=' '; break; /* single quote */
	case 0xA7:	C=-6; strcpy(S,"SS"); C=' '; break; /* legal notation */
	case 0xC2:	C=-6; strcpy(S,"SS"); C=' '; break; /* legal notation */
	case 0xE9:	C='e'; break; /* e with accent */
	default:	C=' ';
	}
      } /* ISO8859 */

    /* Try HTML! */
    else if ((BytesLeft >= 5) && !strncasecmp((char *)(Rep->Mmap+i),"&copy;",6))
	{ C=-2; strcpy(S,"copyright"); i+=5; }
    else if ((BytesLeft >= 5) && !strncasecmp((char *)(Rep->Mmap+i),"&quot;",6))
	{ C='"'; C=' '; i+=5; }
    else if ((BytesLeft >= 4) && !strncasecmp((char *)(Rep->Mmap+i),"&amp;",5))
	{ C='&'; C=' '; i+=4; }
    else if ((BytesLeft >= 5) && !strncasecmp((char *)(Rep->Mmap+i),"&nbsp;",6))
	{ C=' '; i+=5; }
    else if ((BytesLeft >= 3) && !strncasecmp((char *)(Rep->Mmap+i),"&lt;",4))
	{ C='<'; C=' '; i+=3; }
    else if ((BytesLeft >= 3) && !strncasecmp((char *)(Rep->Mmap+i),"&gt;",4))
	{ C='>'; C=' '; i+=3; }
    else if ((BytesLeft >= 2) && !strncasecmp((char *)(Rep->Mmap+i),"<p>",3))
	{ C='\n'; C=' '; i+=2; }
    else if ((BytesLeft >= 3) && !strncasecmp((char *)(Rep->Mmap+i),"<br>",4))
	{ C='\n'; C=' '; i+=3; }
    else if (C=='<')
	{
	/* remove all tags */
	for(ii=i+1; (ii < Rep->MmapSize) &&
	  (isspace(Rep->Mmap[ii]) || isalnum(Rep->Mmap[ii]) ||
	   strchr("\"'&/\\!@#$%^&*()-+_=.,?:;",Rep->Mmap[ii])) ; ii++)
		{
		/* skip likely tag characters */
		}
	if ((ii < Rep->MmapSize) && (Rep->Mmap[ii] == '>'))
		{
		i=ii;
		C=' ';
		}
	}

    /* check for poor-man's quote characters */
    else if ((BytesLeft >2) && (Rep->Mmap[i]=='`') && (Rep->Mmap[i+1]=='`'))
	{ C='"'; C=' '; i+=1; }
    else if ((BytesLeft >2) && (Rep->Mmap[i]=='\'') && (Rep->Mmap[i+1]=='\''))
	{ C='"'; C=' '; i+=1; }

    else if (!isprint(C))
	{
	if (GotTextFlag)
	  {
	  TokClear((Rep->Mmap)+FileLocation,FileLocation,GotTextFlag);
	  }
	GotTextFlag=0;
	C=-1;	/* not a printable character */
	LastC=-1;
	}

    /* NEXT STEP!  Clean up text */
    else if ((BytesLeft >= 3) && !strncasecmp((char *)(Rep->Mmap+i),"(c)",3))
	{
	i=i+2;
	C=-2;
	strcpy(S,"copyright");
	}
    else if (isdigit(Rep->Mmap[i]) && ((YearLength = CopyrightYearCheck((char *)(Rep->Mmap+i),BytesLeft)) > 0))
	{
	/* found a date */
	i+=YearLength-1;
	C=-7;
	strcpy(S,"Year");
	}

    /* massive duplication */
    else if ((BytesLeft >= 3) && (C != ' ') && isprint(C) && !isalnum(C) &&
             (C == Rep->Mmap[i+1]) && (C == Rep->Mmap[i+2]))
	{
	while((i < BytesLeft) && (Rep->Mmap[i] == C))
		{
		i++;
		}
	i=i-1;
	C=-8;
	strcpy(S,"...");
	}

    /* Remove non-license-like printable characters */
    else if (!isalnum(C) && !strchr(".,;?!-",C)) C=' ';

    /* NEXT STEP!  Final clean-up */
    if (!isprint(C) && (C >= 0))
    	{
	C=-1;
	LastC=-1;
	continue;
	}

    /* too many in a row should be viewed as a section break */
    if ((FileLocation-TH.Curr > 250) && GotTextFlag)
	{
	TokClear(Rep->Mmap+FileLocation,FileLocation,GotTextFlag);
	}

    /* NEXT STEP!  Save character */
    if ((LastC!=' ') && (LastC != -1))
      {
      if (!GotTextFlag)
        {
	GotTextFlag=1;
	TokClear(Rep->Mmap+FileLocation,FileLocation,GotTextFlag);
	}
      if (C < -1)
        {
	if (C != LastC)
	  {
	  TokAddChr(' ',FileLocation);
	  TH.TokCount++;
	  }
	}
      else if (!(isalnum(LastC) && isalnum(C)))
	{
	if (ispunct(LastC) && ispunct(C)) ;
	else
	  {
	  TokAddChr(' ',FileLocation);
	  TH.TokCount++;
	  }
	}
      }
    if ((C != ' ') && (C != -1))
	{
	if (!GotTextFlag)
	  {
	  TokClear(Rep->Mmap+FileLocation,FileLocation,0);
	  GotTextFlag=1;
	  }
	if (C < -1)
	  {
	  if (LastC != C)
	    {
	    TokAddStr(S,strlen((char *)S),FileLocation);
	    }
	  }
	else
	  {
	  TokAddChr(C,FileLocation);
	  }
	}

    /* if the block is too long, then clear it. */
    if (TH.TokCount >= 0x7FFE)
	{
	TokClear(Rep->Mmap+FileLocation,FileLocation,GotTextFlag);
	}

    LastC=C;
    } /* foreach byte in original file */
  TokClear(NULL,0,GotTextFlag);
  if (FirstOut)
    {
    fputc(0x01,Fout); fputc(0xff,Fout); /* tag 0x01ff = End of function */
    fputc(0x00,Fout); fputc(0x00,Fout); /* length = 0 */
    }
  RepMunmap(Rep);
  if (Verbose) fprintf(stderr,"Done processing '%s'\n",Filename);
  return(1);
} /* PreprocessFile() */

/**********************************************
 GetFieldValue(): Given a string that contains
 field='value' pairs, save the items.
 Returns: pointer to start of next field, or
 NULL at \0.
 **********************************************/
char *  GetFieldValue   (char *Sin, char *Field, int FieldMax,
                         char *Value, int ValueMax)
{
  int s,f,v;
  int GotQuote;

  memset(Field,0,FieldMax);
  memset(Value,0,ValueMax);

  while(isspace(Sin[0])) Sin++; /* skip initial spaces */
  if (Sin[0]=='\0') return(NULL);
  f=0; v=0;

  for(s=0; (Sin[s] != '\0') && !isspace(Sin[s]) && (Sin[s] != '='); s++)
    {
    Field[f++] = Sin[s];
    }
  while(isspace(Sin[s])) s++; /* skip spaces after field name */
  if (Sin[s] != '=') /* if it is not a field, then just return it. */
    {
    return(Sin+s);
    }
  if (Sin[s]=='\0') return(NULL);
  s++; /* skip '=' */
  while(isspace(Sin[s])) s++; /* skip spaces after '=' */
  if (Sin[s]=='\0') return(NULL);

  GotQuote='\0';
  if ((Sin[s]=='\'') || (Sin[s]=='"'))
    {
    GotQuote = Sin[s];
    s++; /* skip quote */
    if (Sin[s]=='\0') return(NULL);
    }
  if (GotQuote)
    {
    for( ; (Sin[s] != '\0') && (Sin[s] != GotQuote); s++)
      {
      if (Sin[s]=='\\') Value[v++]=Sin[++s];
      else Value[v++]=Sin[s];
      }
    }
  else
    {
    /* if it gets here, then there is no quote */
    for( ; (Sin[s] != '\0') && !isspace(Sin[s]); s++)
      {
      if (Sin[s]=='\\') Value[v++]=Sin[++s];
      else Value[v++]=Sin[s];
      }
    }
  while(isspace(Sin[s])) s++; /* skip spaces */
  return(Sin+s);
} /* GetFieldValue() */

/****************************************
 SetFilename(): Process global Line[] and set Filename.
 Line should contain field=value pairs.
 Filename should be the field named "file".
 ****************************************/
void	SetFilename	()
{
  static char Field[256];
  static char Value[1024];
  char *F;

  memset(Filename,0,sizeof(Filename));
  F = Line;
  do
    {
    F = GetFieldValue(F,Field,256,Value,1024);
    if (!strcasecmp(Field,"A"))
      {
      strcpy(Filename,Value);
      }
    if (!strcasecmp(Field,"Akey"))
      {
      strcpy(Pfile_fk,Value);
      }
    } while(F);
  /* if it gets here, then it failed. */
} /* SetFilename() */

/****************************************
 EngineReadLine(): Inform my parent that
 I am ready for more information, and read
 the next line.
 Result value: 0= no error, >0 = error, <0 = not set
 Returns line length.
 ****************************************/
int	EngineReadLine	(char *Buf, int Bufsize)
{
  int Len, c=0;
ReRead:
  if (Verbose > 1) fprintf(stderr,"Child[%d] Ready!\n",getpid());
  printf("OK\n"); /* tells parent to send more data! */
  fflush(stdout); /* make sure parent gets the message */

  memset(Buf,'\0',Bufsize);
Reread:
  for(Len=0; Len < Bufsize; Len++)
	{
	c=fgetc(stdin);
	if (c<0)
	  {
	  if (Verbose > 1) printf("Child[%d] Stdin closed. Exiting.\n",getpid());
	  fflush(stdout);
	  DBclose(DB);
	  exit(0);	/* end when stdin ends */
	  }
	if (c=='\n')
	  {
	  if (Verbose > 1) fprintf(stderr,"Child[%d] Read command: %s\n",getpid(),Buf);
	  /* retry if empty line */
	  if (Len == 0) goto Reread;
	  return(Len);
	  }
	Buf[Len] = c;
	}

  /* got a problem...  string too long for the buffer */
  /* flush the rest of the string */
  while((c >= 0) && (c!='\n')) c=fgetc(stdin);
  if (c < 0)
    {
    if (Verbose > 1) printf("Child[%d] Stdin closed. Exiting.\n",getpid());
    fflush(stdout);
    DBclose(DB);
    exit(-1);	/* abort! */
    }
  /* set the error and try again */
  goto ReRead;
} /* EngineReadLine() */

/*********************************************************
 GetAgentKey(): Get the Agent Key from the database.
 *********************************************************/
void	GetAgentKey	()
{
  int rc;

  rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='filter_license' ORDER BY agent_id DESC;");
  if (rc < 0)
	{
	fprintf(stderr,"ERROR: unable to access the database\n");
	fprintf(stderr,"LOG: unable to select 'filter_license' from the database table 'agent'\n");
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
  if (DBdatasize(DB) <= 0)
      {
      /* Not found? Add it! */
      rc = DBaccess(DB,"INSERT INTO agent (agent_name,agent_rev,agent_desc) VALUES ('filter_license','unknown','Convert files to license cache files for bsam');");
      if (rc < 0)
	{
	fprintf(stderr,"ERROR: unable to write to the database\n");
	fprintf(stderr,"LOG: unable to write 'filter_license' to the database table 'agent'\n");
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
      rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='filter_license' ORDER BY agent_id DESC;");
      if (rc < 0)
	{
	fprintf(stderr,"ERROR: unable to access the database\n");
	fprintf(stderr,"LOG: unable to select 'filter_license' from the database table 'agent'\n");
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
      }
  Agent_pk = atoi(DBgetvalue(DB,0,0));
} /* GetAgentKey() */

/****************************************
 Usage(): Display usage.
 ****************************************/
void	Usage	(char *Name)
{
  fprintf(stderr,"Usage: %s [options] [file [file ...]]\n",Name);
  fprintf(stderr,"  -i = Initialize the database, then exit.\n");
  fprintf(stderr,"  -O = Send results to stdout instead of the repository (turns off DB updates)\n");
  fprintf(stderr,"  -Q = Add licenses to the DB and store the license_pk in the file\n");
  fprintf(stderr,"  -M file = Along with -Q, -M specifies a file with license meta information.\n");
  fprintf(stderr,"  -v = verbose (-v -v = more verbose)\n");
  fprintf(stderr,"  -s SrcRep = source repository type (default: -s files)\n");
  fprintf(stderr,"  -d DstRep = output repository type (default: -d license)\n");
  fprintf(stderr,"  stdin = filename in repository to process, one per line.\n");
  fprintf(stderr,"  The destination repository is created if it is non-zero.\n");
  fprintf(stderr,"  Engine sends SIG_USR1 to parent when ready for an input line and\n");
  fprintf(stderr,"  to denote a successful completion of the operation.\n");
  fprintf(stderr,"  (The queue manager can use this to denote a queue item as processed.\n");
  fprintf(stderr,"  Engine sends SIG_USR2 to parent when ready for an input line and\n");
  fprintf(stderr,"  to denote an UNSUCCESSFUL operation.\n");
} /* Usage() */

/***********************************************************************/
/***********************************************************************/
int	main	(int argc, char *argv[])
{
  int c;

  while((c = getopt(argc,argv,"iOQM:s:d:v")) != -1)
    {
    switch(c)
      {
      case 'i':
	DB=DBopen();
	if (!DB)
	  {
	  fprintf(stderr,"ERROR: s Unable to open database connection\n");
	  exit(-1);
	  }
	GetAgentKey();
	DBclose(DB);
	return(0);
      case 'O': UpdateDB=0; break;
      case 'Q': AddToDB=1; break;
      case 'M':
	MetaFile = fopen(optarg,"rb");
	if (!MetaFile)
	  {
	  fprintf(stderr,"ERROR: Unable to open meta file '%s'\n",optarg);
	  exit(-1);
	  }
	break;
      case 'v': Verbose++; break;
      case 's': RepSrc=optarg; break;
      case 'd': RepDst=optarg; break;
      default:
	Usage(argv[0]);
	if (Verbose) fprintf(stderr,"Bad usage.\n");
	exit(-1);
      }
    }

  if ((optind == argc) && (!RepSrc || !RepDst))
	{
	Usage(argv[0]);
	if (Verbose) fprintf(stderr,"Bad usage.\n");
	exit(-1);
	}

  memset(Pfile_fk,0,sizeof(Pfile_fk));
  strcpy(Pfile_fk,"-1");

  memset(&TH,0,sizeof(TokHolder));
  if (Verbose) { fprintf(stderr,"WordCheckInit\n"); }
  WordCheckInit();

  signal(SIGALRM,ShowHeartbeat);
  alarm(60);
  DB=DBopen();
  if (!DB)
	{
	fprintf(stderr,"ERROR pfile %s Unable to open database connection\n",Pfile_fk);
	exit(-1);
	}
  GetAgentKey();

  if (DB && AddToDB) AddPhrase();

  /* process each file from command-line */
  for( ; optind < argc; optind++)
    {
    memset(Line,'\0',sizeof(Line));
    snprintf(Line,sizeof(Line),"A=\"%s\"",argv[optind]);
    if (Verbose) fprintf(stderr,"Processing %s\n",Line);
    SetFilename();	/* process line and find filename */
    if (Filename[0] == '\0') continue;
    if (Verbose) { fprintf(stderr,"Processing file '%s'\n",Filename); }
    if (!PreprocessFile(0))
	{
	if (Verbose) fprintf(stderr,"Child[%d] Something FAILED\n",getpid());
	continue;
	}
    if (Verbose) fprintf(stderr,"Child[%d] Something worked\n",getpid());
    /* update the DB */
    /** If no data, then mark it in the DB as being processed **/
    if (UpdateDB)
      {
      memset(SQL,'\0',sizeof(SQL));
      snprintf(SQL,sizeof(SQL),"INSERT INTO agent_lic_status (pfile_fk,processed,inrepository) VALUES ('%s',%s,%s);",Pfile_fk, FirstOut ? "false" : "true",FirstOut ? "true" : "false");
      if (DBaccess(DB,SQL) < -1) /* -1 is a duplicate constraint */
	{
	fprintf(stderr,"ERROR pfile %s Database update failed. Contact your administrator.\n",Pfile_fk);
	fprintf(stderr,"LOG pfile %s SQL ERROR: '%s'\n",Pfile_fk,SQL);
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
      }
    ResetPrinting();
    }

  /* process each file from stdin */
  if (UpdateDB)
    {
    ResetPrinting();
    while(EngineReadLine(Line,sizeof(Line)) > 0)
      {
      SetFilename();	/* process line and find filename */
      if (Filename[0] == '\0') continue;
      if (!PreprocessFile(1))
	  {
	  if (Verbose) fprintf(stderr,"Child[%d] Something FAILED\n",getpid());
	  continue;
	  }
      if (Verbose) fprintf(stderr,"Child[%d] Something worked\n",getpid());
      /* update the DB */
      /** If no data, then mark it in the DB as being processed **/
      memset(SQL,'\0',sizeof(SQL));
      snprintf(SQL,sizeof(SQL),"INSERT INTO agent_lic_status (pfile_fk,processed,inrepository) VALUES ('%s',%s,%s);",Pfile_fk, FirstOut ? "false" : "true",FirstOut ? "true" : "false");
      if (DBaccess(DB,SQL) < -1) /* -1 is a duplicate constraint */
	{
	fprintf(stderr,"ERROR pfile %s Database update failed. Contact your administrator.\n",Pfile_fk);
	fprintf(stderr,"LOG pfile %s SQL ERROR: '%s'\n",Pfile_fk,SQL);
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
      ResetPrinting();
      } /* while file to read */
    }
  DBclose(DB);
  if (MetaFile) fclose(MetaFile);
  if (Verbose) fprintf(stderr,"Completed.\n");
  return(0);
} /* main() */

