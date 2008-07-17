/***************************************************************
 adj2nest: Convert adjacency list to nested sets.

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

 -------------------------------------------
 adj2nest
 Convert an adjacency list to a nested set.
 Ununpack creates an adjacency list: every child knows it's parent.
 For performance: convert this to nested set.
   P1
   /\
 C1  C2

 C1 is placed in set 1.
 C2 is placed in set 2.
 P1 is placed in set 3 -- P1 tree spans sets 1-3.

 All sets are ordered, so every parent knows the range
 of sets that form every child.

 Method:
 - Select all keys and parents from uploadtree where they are in the upload_fk.
 - Build a tree that changes "child knows parent" to "parent knows child".
 - Walk the tree. (depth-first)
   - Create every set number.
     - Track the left by counting down the tree.
     - Track the right by counting each visited node.
   - Update the DB.

 NOTE:
 The first id is "1", not "0".
 Every node is assumed to have a NULL child!
   - If there are n nodes, then the top-most range is [1,2*n]
   - Every left and every right value is unique.
   - The left part of the range is the same as the node's ID number.
 ***************************************************************/
#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>

#include "libfossdb.h"

#define MAXCMD 4096
char SQL[256];

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif

void *DB=NULL;
int Agent_pk=-1;	/* agent table */
int Verbose=0;

struct uploadtree
  {
  long UploadtreePk;
  long Child;
  long Sibling;
  };
typedef struct uploadtree uploadtree;
uploadtree *Tree=NULL;
long TreeSize=0;
long TreeSet=0; /* index for inserting the next child */
long SetNum=0; /* index for tracking set numbers */

/************************************************************/
/************************************************************/
/************************************************************/

/**************************************************
 WalkTree(): Given a tree, recursively walk it.
 **************************************************/
void	WalkTree	(long Index, long Depth)
{
  long LeftSet;
  if (Verbose)
    {
    int i;
    for(i=0; i<Depth; i++) printf(" ");
    printf("%ld\n",Tree[Index].UploadtreePk);
    }

  LeftSet = SetNum;
  SetNum++;

  if (Tree[Index].Child > -1)
    {
    WalkTree(Tree[Index].Child,Depth+1);
    SetNum++;
    }

  snprintf(SQL,sizeof(SQL),"UPDATE uploadtree SET lft='%ld', rgt='%ld' WHERE uploadtree_pk='%ld';",
	LeftSet,SetNum,Tree[Index].UploadtreePk);
  DBaccess(DB,SQL);

  if (Tree[Index].Sibling > -1)
    {
    SetNum++;
    WalkTree(Tree[Index].Sibling,Depth+1);
    }

} /* WalkTree() */

/**************************************************
 SetParent(): Given a parent and a child, add the child
 to the parent's chain.
 NOTE: This is iterative!
 **************************************************/
void	SetParent	(long Parent, long Child)
{
  long P;
  static long LastParentId=-1;
  static long LastParentIndex=-1;

  /* Insert the child */
  Tree[TreeSet].UploadtreePk = Child;
  TreeSet++;

  if (Parent == 0) /* ignore null parent */
    {
    return;
    }

  /* Find the index of the parent */
  if (Parent == LastParentId)
    {
    P = LastParentIndex;
    }
  else
    {
    P=0;
    while((P<TreeSet) && (Tree[P].UploadtreePk != Parent)) { P++; }
    if (P < TreeSet)
      {
      LastParentId = Parent;
      LastParentIndex = P;
      }
    }

  if (P >= TreeSet)
    {
    /* Parent not found, so create it (right after the child). */
    Tree[TreeSet].UploadtreePk = Parent;
    Tree[TreeSet].Child = TreeSet-1;
    LastParentId = Parent;
    LastParentIndex = TreeSet;
    TreeSet++;
    return;
    }

  /* Parent found, so follow the chain and add the child to the
     end of the chain. */
  if (Tree[P].Child < 0)
    {
    Tree[P].Child = TreeSet-1;
    }
  else
    {
    /* Already have a child so follow that child's sibling chain */
    P=Tree[P].Child;
    while(Tree[P].Sibling > -1) P=Tree[P].Sibling; /* find end of the chain */
    Tree[P].Sibling = TreeSet-1;
    }
} /* SetParent() */

/**************************************************
 LoadAdj(): Given an upload_pk, load the adjacency table.
 This is in the format "every child knows its parent".
 Returns the adjacency tree.
 **************************************************/
void	LoadAdj	(long UploadPk)
{
  long i;
  long Parent,Child;
  void *UDB;

  snprintf(SQL,sizeof(SQL),"SELECT uploadtree_pk,parent FROM uploadtree WHERE upload_fk = %ld AND parent IS NOT NULL ORDER BY parent;",UploadPk);
  DBaccess(DB,SQL);
  TreeSize = DBdatasize(DB);
  if (Verbose) printf("# Upload %ld: %ld items\n",UploadPk,TreeSize);

  UDB=DBmove(DB);
  snprintf(SQL,sizeof(SQL),"SELECT uploadtree_pk,parent FROM uploadtree WHERE upload_fk = %ld AND parent IS NULL;",UploadPk);
  DBaccess(DB,SQL);
  TreeSize += DBdatasize(DB);

  /* Got data! Populate the tree! */
  if (Tree) { free(Tree); }
  if (TreeSize <= 0) { Tree=NULL; return; }
  Tree = (uploadtree *)calloc(TreeSize+1,sizeof(uploadtree));
  for(i=0; i<TreeSize+1; i++)
    {
    Tree[i].UploadtreePk=-1;
    Tree[i].Child=-1;
    Tree[i].Sibling=-1;
    }

  TreeSet=0;
  SetNum=1;

  /* Load the roots */
  for(i=0; i<DBdatasize(DB); i++)
    {
    Child = atol(DBgetvalue(DB,i,0));
    Tree[TreeSet].UploadtreePk = Child;
    TreeSet++;
    }

  /* Load all non-roots */
  for(i=0; i<DBdatasize(UDB); i++)
    {
    Child = atol(DBgetvalue(UDB,i,0));
    Parent = atol(DBgetvalue(UDB,i,1));
    SetParent(Parent,Child);
    }

  /* Free up DB memory */
  DBclose(UDB);
  return;
} /* LoadAdj() */

/*********************************************
 RunAllNew(): Run on all uploads WHERE the upload
 has no nested set numbers.
 This displays each upload as it runs!
 *********************************************/
void	RunAllNew	()
{
  int Row,MaxRow;
  long UploadPk;
  void *UDB;
  DBaccess(DB,"SELECT DISTINCT upload_pk,upload_desc,upload_filename FROM upload WHERE upload_pk IN ( SELECT DISTINCT upload_fk FROM uploadtree WHERE lft IS NULL );");
  UDB=DBmove(DB);
  MaxRow = DBdatasize(UDB);
  for(Row=0; Row < MaxRow; Row++)
      {
      UploadPk = atol(DBgetvalue(UDB,Row,0));
      if (UploadPk >= 0)
	{
	char *S;
	printf("Processing %ld :: %s",UploadPk,DBgetvalue(UDB,Row,2));
	S = DBgetvalue(UDB,Row,1);
	if (S && S[0]) printf(" (%s)",S);
	printf("\n");
	LoadAdj(UploadPk);
	if (Tree) WalkTree(0,0);
	if (Tree) free(Tree);
	Tree=NULL;
	TreeSize=0;
	}
      }
  DBclose(UDB);
} /* RunAllNew() */

/*********************************************
 ListUploads(): List every upload ID.
 *********************************************/
void    ListUploads     ()
{
  int Row,MaxRow;
  long NewPid;

  printf("# Uploads\n");
  DBaccess(DB,"SELECT upload_pk,upload_desc,upload_filename FROM upload ORDER BY upload_pk;");

  /* list each value */
  MaxRow = DBdatasize(DB);
  for(Row=0; Row < MaxRow; Row++)
      {
      NewPid = atol(DBgetvalue(DB,Row,0));
      if (NewPid >= 0)
	{
	char *S;
	printf("%ld :: %s",NewPid,DBgetvalue(DB,Row,2));
	S = DBgetvalue(DB,Row,1);
	if (S && S[0]) printf(" (%s)",S);
	printf("\n");
	}
      }
} /* ListUploads() */


/************************************************************/
/************************************************************/
/************************************************************/

/**************************************************
 ShowHeartbeat(): Given an alarm signal, display a
 heartbeat.
 **************************************************/
void    ShowHeartbeat   (int Sig)
{
  printf("Heartbeat\n");
  fflush(stdout);
  /* re-schedule itself */
  alarm(60);
} /* ShowHeartbeat() */

/**********************************************
 ReadLine(): Read a command from stdin.
 If the line is empty, then try again.
 Returns line length, or -1 of EOF.
 **********************************************/
int	ReadLine	(FILE *Fin, char *Line, int MaxLine)
{
  int C;
  int i;

  memset(Line,'\0',MaxLine);
  if (feof(Fin)) return(-1);
  i=0;
  C=fgetc(Fin);
  if (C<0) return(-1);
  while(!feof(Fin) && (C>=0) && (i<MaxLine))
    {
    if (C=='\n')
        {
        if (i > 0) return(i);
        /* if it is a blank line, then ignore it. */
        }
    else
        {
        Line[i]=C;
        i++;
        }
    C=fgetc(Fin);
    }
  return(i);
} /* ReadLine() */

/**********************************************
 MatchField(): Given a string that contains
 field='value' pairs, check if the field name
 matches.
 Returns: 1 on match, 0 on miss, -1 on no data.
 **********************************************/
int	MatchField	(char *Field, char *S)
{
  int Len;
  if (!S || (S[0]=='\0')) return(-1);
  while(isspace(S[0])) S++;
  Len = strlen(Field);
  if (!strncmp(Field,S,Len))
	{
	/* Matched string, now make sure it is a real match */
	while(isspace(S[Len])) Len++;
	if (S[Len]=='=') return(1);
	}
  return(0);
} /* MatchField() */

/**********************************************
 SkipFieldValue(): Given a string that contains
 field='value' pairs, skip the first pair and
 return the pointer to the next pair (or NULL if
 end of string).
 **********************************************/
char *	SkipFieldValue	(char *S)
{
  char Quote;

  if (!S || (S[0]=='\0')) return(NULL);

  /* Skip the field */
  while((S[0] != '\0') && (S[0]!='=')) S++; /* skip until the '=' is found */
  if (S[0]=='\0') return(NULL);
  S++; /* skip the '=' */
  while(isspace(S[0])) S++; /* Skip any spaces */
  if (S[0]=='\0') return(NULL);

  /* Now for the fun part... Skip the Value.  This may be quoted. */
  switch(S[0])
    {
    case '\"': case '\'':
	Quote=S[0];
	S++;
	break;
    default:
	Quote=' ';
	break;
    }
  while((S[0]!='\0') && (S[0]!=Quote))
	{
	if (S[0]=='\\') { S+=2; }
	else S++;
	}
  if (S[0]==Quote) S++;
  while(isspace(S[0])) S++; /* Skip any spaces */
  return(S);
} /* SkipFieldValue() */

/**********************************************
 UntaintValue(): The scheduler taints field=value
 pairs.  Given a pair, return the untainted value.
 NOTE: In string and out string CAN be the same string!
 NOTE: strlen(Sout) is ALWAYS < strlen(Sin).
 Returns Sout, or NULL if there is an error.
 **********************************************/
char *	UntaintValue	(char *Sin, char *Sout)
{
  char Quote;

  /* Skip the field */
  while((Sin[0] != '\0') && (Sin[0]!='=')) Sin++; /* skip until the '=' is found */
  if (Sin[0]=='\0') return(NULL);
  Sin++; /* skip the '=' */
  while(isspace(Sin[0])) Sin++; /* Skip any spaces */
  if (Sin[0]=='\0') { Sout[0]='\0'; return(NULL); }

  /* The value may be inside quotes */
  switch(Sin[0])
    {
    case '\"': case '\'':
	Quote=Sin[0];
	Sin++;
	break;
    default:
	Quote=' ';
	break;
    }

  /* Now we're ready to untaint the value */
  while((Sin[0]!='\0') && (Sin[0]!=Quote))
	{
	if (Sin[0]=='\\')
	  {
	  Sin++; /* skip quote char */
	  if (Sin[0]=='n') { Sout[0]='\n'; }
	  else if (Sin[0]=='r') { Sout[0]='\r'; }
	  else if (Sin[0]=='a') { Sout[0]='\a'; }
	  else { Sout[0]=Sin[0]; }
	  Sout++;
	  Sin++; /* skip processed char */
	  }
	else
	  {
	  Sout[0] = Sin[0];
	  Sin++;
	  Sout++;
	  };
	}
  Sout[0]='\0'; /* terminate string */
  return(Sout);
} /* UntaintValue() */

/**********************************************
 SetParm(): Convert field=value pairs into parameter.
 This overwrites the parameter string!
 The parameter is untainted from the scheduler.
 Returns 1 if Parm is set, 0 if not.
 **********************************************/
int	SetParm	(char *ParmName, char *Parm)
{
  int rc;
  char *OldParm;
  OldParm=Parm;
  if (!ParmName || (ParmName[0]=='\0')) return(1); /* no change */
  if (!Parm || (Parm[0]=='\0')) return(1); /* no change */

  /* Find the parameter */
  while(!(rc=MatchField(ParmName,Parm)))
    {
    Parm = SkipFieldValue(Parm);
    }
  if (rc != 1) return(0); /* no match */

  /* Found it!  Set the value */
  UntaintValue(Parm,OldParm);
  return(1);
} /* SetParm() */

/*********************************************************
 GetAgentKey(): Get the Agent Key from the database.
 *********************************************************/
void	GetAgentKey	()
{
  int rc;

  rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='adj2nest' ORDER BY agent_id DESC;");
  if (rc < 0)
	{
	printf("ERROR: unable to access the database\n");
	printf("LOG: unable to select 'adj2nest' from the database table 'agent'\n");
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
  if (DBdatasize(DB) <= 0)
      {
      /* Not found? Add it! */
      rc = DBaccess(DB,"INSERT INTO agent (agent_name,agent_rev,agent_desc) VALUES ('adj2nest','unknown','Analyze source rpm .spec files');");
      if (rc < 0)
	{
	printf("ERROR: unable to write to the database\n");
	printf("LOG: unable to write 'adj2nest' to the database table 'agent'\n");
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
      rc = DBaccess(DB,"SELECT agent_id FROM agent WHERE agent_name ='adj2nest' ORDER BY agent_id DESC;");
      if (rc < 0)
	{
	printf("ERROR: unable to access the database\n");
	printf("LOG: unable to select 'adj2nest' from the database table 'agent'\n");
	fflush(stdout);
	DBclose(DB);
	exit(-1);
	}
      }
  Agent_pk = atoi(DBgetvalue(DB,0,0));
} /* GetAgentKey() */

/*********************************************************
 Usage():
 *********************************************************/
void    Usage   (char *Name)
{
  printf("Usage: %s [options] [id [id ...]]\n",Name);
  printf("  -i        :: initialize the database, then exit.\n");
  printf("  -a        :: run on ALL uploads that have no nested set records.\n");
  printf("  -u        :: list all upload ids, then exit.\n");
  printf("  no file   :: process upload ids from the scheduler.\n");
  printf("  id        :: process upload ids from the command-line.\n");
} /* Usage() */

/*********************************************************/
int	main	(int argc, char *argv[])
{
  char Parm[MAXCMD];
  int c;
  int arg;
  long UploadPk=-1;

  DB = DBopen();
  if (!DB)
	{
	printf("FATAL: Unable to connect to database\n");
	fflush(stdout);
	exit(-1);
	}
  GetAgentKey();

  /* Process command-line */
  while((c = getopt(argc,argv,"aiuv")) != -1)
    {
    switch(c)
	{
	case 'a': /* run on ALL */
		RunAllNew();
		break;
	case 'i':
		/* GetAgentKey() already processed */
		DBclose(DB);
		return(0);
	case 'v':	Verbose++; break;
	case 'u':
		/* list ids */
		ListUploads();
		DBclose(DB);
		return(0);
	default:
		Usage(argv[0]);
		fflush(stdout);
		DBclose(DB);
		exit(-1);
	}
    }

  /* Process each file */
  for(arg=optind; arg < argc; arg++)
    {
    UploadPk = atol(argv[arg]);
    LoadAdj(UploadPk);
    if (Tree) WalkTree(0,0);
    if (Tree) free(Tree);
    Tree=NULL;
    TreeSize=0;
    }

  /* No args?  Run from schedule! */
  if (argc == 1)
    {
    signal(SIGALRM,ShowHeartbeat);
    alarm(60);
    printf("OK\n"); /* inform scheduler that we are ready */
    fflush(stdout);
    while(ReadLine(stdin,Parm,MAXCMD) >= 0)
      {
      UploadPk = atol(Parm);
      LoadAdj(UploadPk);
      if (Tree) WalkTree(0,0);
      if (Tree) free(Tree);
      Tree=NULL;
      TreeSize=0;
      printf("OK\n"); /* inform scheduler that we are ready */
      fflush(stdout);
      } /* while() */
    }

  DBclose(DB);
  return(0);
} /* main() */

