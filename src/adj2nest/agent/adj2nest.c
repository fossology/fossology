/*
 adj2nest: Convert adjacency list to nested sets.

 SPDX-FileCopyrightText: © 2007-2015 Hewlett-Packard Development Company, L.P.
 SPDX-FileCopyrightText: © 2015 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 \file adj2nest.c
 \page adj2nest adj2nest
 \tableofcontents
 \section adj2nestbrief Working of adj2nest
 Convert an adjacency list to a nested set and update user permissions to the upload.
 Ununpack creates an adjacency list: every child knows it's parent.

 For performance: convert this to nested set.

 <PRE>
   P1
   /\
 C1  C2
 </PRE>
 C1 is placed in set 1.
 C2 is placed in set 2.
 P1 is placed in set 3 -- P1 tree spans sets 1-3.

 All sets are ordered, so every parent knows the range
 of sets that form every child.

 \section adj2nestmethod Method:
 - Select all keys and parents from uploadtree where they are in the upload_fk.
 - Build a tree that changes "child knows parent" to "parent knows child".
 - Walk the tree. (depth-first)
   - Create every set number.
     - Track the left by counting down the tree.
     - Track the right by counting each visited node.
   - Update the DB.

 \section adj2nestactions Supported actions
 Command line flag|Description|
 ---:|:---|
  -h|Help (print this message), then exit|
  -i|Initialize the database, then exit|
  -a|Run on ALL uploads that have no nested set records|
  -c SYSCONFDIR|Specify the directory for the system configuration|
  -v|Verbose (-vv = more verbose)|
  -u|list all upload ids, then exit|
  no file|Process upload ids from the scheduler|
  id|Process upload ids from the command-line|
  -V|Print the version info, then exit|

 \section adj2nestnote NOTE:
 The first id is "1", not "0".
 Every node is assumed to have a NULL child!
   - If there are n nodes, then the top-most range is [1,2*n]
   - Every left and every right value is unique.
   - The left part of the range is the same as the node's ID number.

 \section sadj2nestource Agent source
   - \link src/adj2nest/agent \endlink
   - \link src/adj2nest/ui \endlink
 */

#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>
#include <libgen.h>

#include "libfossology.h"

#define MAXCMD 4096
#define myBUFSIZ 2048
char SQL[256];

#ifdef COMMIT_HASH_S
char BuildVersion[]="adj2nest build version: " VERSION_S " r(" COMMIT_HASH_S ").\n";
#else
char BuildVersion[]="adj2nest build version: NULL.\n";
#endif

PGconn *pgConn = NULL;  ///< Database connection

/**
 * \struct uploadtree
 * \brief Contains information required by uploadtree elements
 */
struct uploadtree
{
  long UploadtreePk;  /**< uploadtree element's ID */
  long Child;         /**< uploadtree element's child ID */
  long Sibling;       /**< uploadtree element's sibling ID */
};
typedef struct uploadtree uploadtree;
uploadtree *Tree=NULL;
char *uploadtree_tablename; /**< Name of DB table (uploadtree, uploadtree_a,...) */
long TreeSize=0;
long TreeSet=0; /**< index for inserting the next child */
long SetNum=0;  /**< index for tracking set numbers */
int isBigUpload=0;
/************************************************************/
/************************************************************/
/************************************************************/

/**
 * Given a tree, recursively walk it.
 * \param Index ID of the uploadtree element
 * \param Depth Maximum depth for the recursion
 */
void	WalkTree	(long Index, long Depth)
{
  long LeftSet;
  PGresult* pgResult;

  if (agent_verbose)
    {
    int i;
    for(i=0; i<Depth; i++) printf(" ");
    LOG_VERBOSE("%ld\n",Tree[Index].UploadtreePk);
    }

  LeftSet = SetNum;
  SetNum++;

  if (Tree[Index].Child > -1)
    {
    WalkTree(Tree[Index].Child,Depth+1);
    SetNum++;
    }

  snprintf(SQL,sizeof(SQL),"UPDATE %s SET lft='%ld', rgt='%ld' WHERE uploadtree_pk='%ld'",
	uploadtree_tablename,LeftSet,SetNum,Tree[Index].UploadtreePk);
  pgResult = PQexec(pgConn, SQL);
  fo_checkPQcommand(pgConn, pgResult, SQL, __FILE__, __LINE__);
  PQclear(pgResult);
  fo_scheduler_heart(1);

  if (Tree[Index].Sibling > -1)
    {
    SetNum++;
    WalkTree(Tree[Index].Sibling,Depth+1);
    }

} /* WalkTree() */

/**
 * Given a parent and a child, add the child
 * to the parent's chain. NOTE: This is iterative!
 * \param Parent ID of the parent to add child.
 * \param Child  ID of the child to be added.
 */
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

/**
 * Given an upload_pk, load the adjacency table.
 * This is in the format "every child knows its parent".
 * Returns the adjacency tree.
 * \param UploadPk Upload ID to be aj2nested
 */
void	LoadAdj	(long UploadPk)
{
  long i;
  long Parent,Child;
  long RootRows, NonRootRows;
  PGresult* pgNonRootResult;
  PGresult* pgRootResult;
  PGresult* pgResult;
  char LastChar;

  uploadtree_tablename = GetUploadtreeTableName(pgConn, UploadPk);

  /* If the last character of the uploadtree_tablename is a digit, run analyze */
  LastChar = uploadtree_tablename[strlen(uploadtree_tablename)-1];
  if (LastChar >= '0' && LastChar <= '9')
  {
    isBigUpload=1;
    snprintf(SQL,sizeof(SQL),"ANALYZE %s",uploadtree_tablename);
    pgResult =  PQexec(pgConn, SQL);
    fo_checkPQcommand(pgConn, pgResult, SQL, __FILE__ ,__LINE__);
    PQclear(pgResult);
  }

  snprintf(SQL,sizeof(SQL),"SELECT uploadtree_pk,parent FROM %s WHERE upload_fk = %ld AND parent IS NOT NULL ORDER BY parent, ufile_mode&(1<<29) DESC, ufile_name",uploadtree_tablename,UploadPk);
  pgNonRootResult = PQexec(pgConn, SQL);
  fo_checkPQresult(pgConn, pgNonRootResult, SQL, __FILE__, __LINE__);

  NonRootRows = PQntuples(pgNonRootResult);
  TreeSize = NonRootRows;
  LOG_VERBOSE("# Upload %ld: %ld items\n",UploadPk,TreeSize);

  snprintf(SQL,sizeof(SQL),"SELECT uploadtree_pk,parent FROM %s WHERE upload_fk = %ld AND parent IS NULL",uploadtree_tablename,UploadPk);
  pgRootResult = PQexec(pgConn, SQL);
  fo_checkPQresult(pgConn, pgRootResult, SQL, __FILE__, __LINE__);

  RootRows = PQntuples(pgRootResult);
  TreeSize += RootRows;

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
  for(i=0; i<RootRows; i++)
  {
    Child = atol(PQgetvalue(pgRootResult, i, 0));
    Tree[TreeSet].UploadtreePk = Child;
    TreeSet++;

    /* dummy heart to make sure the scheduler knows we are still alive */
    if ((i % 100000) == 0) fo_scheduler_heart(0);
  }

  /* Load all non-roots */
  for(i=0; i<NonRootRows; i++)
  {
    Child = atol(PQgetvalue(pgNonRootResult,i,0));
    Parent = atol(PQgetvalue(pgNonRootResult,i,1));
    SetParent(Parent,Child);

    /* dummy heart to make sure the scheduler knows we are still alive */
    if ((i % 100000) == 0) fo_scheduler_heart(0);
  }

  /* Free up DB memory */
  PQclear(pgNonRootResult);
  PQclear(pgRootResult);
  return;
} /* LoadAdj() */

/*********************************************
 Run on all uploads WHERE the upload
 has no nested set numbers.
 This displays each upload as it runs!
 *********************************************/
void	RunAllNew	()
{
  int Row,MaxRow;
  long UploadPk;
  PGresult *pgResult;

  snprintf(SQL,sizeof(SQL), "SELECT DISTINCT upload_pk,upload_desc,upload_filename FROM upload WHERE upload_pk IN ( SELECT DISTINCT upload_fk FROM uploadtree WHERE lft IS NULL )");
  pgResult = PQexec(pgConn, SQL);
  fo_checkPQresult(pgConn, pgResult, SQL, __FILE__, __LINE__);

  MaxRow = PQntuples(pgResult);
  for(Row=0; Row < MaxRow; Row++)
  {
    UploadPk = atol(PQgetvalue(pgResult,Row,0));
    if (UploadPk >= 0)
	  {
      char *S;
      printf("Processing %ld :: %s",UploadPk,PQgetvalue(pgResult,Row,2));
      S = PQgetvalue(pgResult,Row,1);
      if (S && S[0]) printf(" (%s)",S);
      printf("\n");
      LoadAdj(UploadPk);
      if (Tree) WalkTree(0,0);
      if (Tree) free(Tree);
      Tree=NULL;
      TreeSize=0;
    }
  }
  PQclear(pgResult);
} /* RunAllNew() */

/*********************************************
 ListUploads(): List every upload ID.
 *********************************************/
void    ListUploads     ()
{
  int Row,MaxRow;
  long NewPid;
  PGresult *pgResult;

  printf("# Uploads\n");
  snprintf(SQL,sizeof(SQL), "SELECT upload_pk,upload_desc,upload_filename FROM upload ORDER BY upload_pk");
  pgResult = PQexec(pgConn, SQL);
  fo_checkPQresult(pgConn, pgResult, SQL, __FILE__, __LINE__);

  /* list each value */
  MaxRow = PQntuples(pgResult);
  for(Row=0; Row < MaxRow; Row++)
  {
    NewPid = atol(PQgetvalue(pgResult,Row,0));
    if (NewPid >= 0)
    {
      char *S;
      printf("%ld :: %s",NewPid,PQgetvalue(pgResult,Row,2));
      S = PQgetvalue(pgResult,Row,1);
      if (S && S[0]) printf(" (%s)",S);
      printf("\n");
    }
  }
  PQclear(pgResult);
} /* ListUploads() */


/************************************************************/
/************************************************************/
/************************************************************/

/**********************************************
 Given a string that contains
 field='value' pairs, check if the field name
 matches.
 \param Field Haystack
 \param S     Needle
 \return 1 on match, 0 on miss, -1 on no data.
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
    while (isspace(S[Len])) Len++;
    if (S[Len] == '=') return (1);
  }
  return(0);
} /* MatchField() */

/**********************************************
 Given a string that contains
 field='value' pairs, skip the first pair and
 return the pointer to the next pair (or NULL if
 end of string).
 \param S field='value' pairs
 \return Pointer to next pair
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
    if (S[0] == '\\') {
      S += 2;
    }
    else S++;
  }
  if (S[0]==Quote) S++;
  while(isspace(S[0])) S++; /* Skip any spaces */
  return(S);
} /* SkipFieldValue() */

/**********************************************
 The scheduler taints field=value
 pairs.  Given a pair, return the untainted value.
 NOTE: In string and out string CAN be the same string!
 NOTE: strlen(Sout) is ALWAYS < strlen(Sin).
 \param[in]  Sin  Tainted string
 \param[out] Sout Untainted string
 \return Untainted string or NULL if there is an error.
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
    if (Sin[0] == '\\') {
      Sin++; /* skip quote char */
      if (Sin[0] == 'n') {
        Sout[0] = '\n';
      }
      else if (Sin[0] == 'r') {
        Sout[0] = '\r';
      }
      else if (Sin[0] == 'a') {
        Sout[0] = '\a';
      }
      else {
        Sout[0] = Sin[0];
      }
      Sout++;
      Sin++; /* skip processed char */
    }
    else {
      Sout[0] = Sin[0];
      Sin++;
      Sout++;
    };
  }
  Sout[0]='\0'; /* terminate string */
  return(Sout);
} /* UntaintValue() */

/**********************************************
 Convert field=value pairs into parameter.
 This overwrites the parameter string!
 The parameter is untainted from the scheduler.
 \param ParmName field='value' pairs.
 \param Parm     Parameter to be set.
 \returns 1 if Parm is set, 0 if not.
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


/**
 * \brief Finish updating the upload record and permissions data
 *
 * \param UploadPk Upload ID to be updated
 *
 * \return int -1 failure, 0 success
 */
int UpdateUpload(long UploadPk)
{
  PGresult *pgResult;

  /* update upload.upload_mode to say that adj2nest was successful */
  snprintf(SQL, sizeof(SQL), "UPDATE upload SET upload_mode = upload_mode | (1<<6) WHERE upload_pk='%ld'",
           UploadPk);
  pgResult =  PQexec(pgConn, SQL); /* UPDATE upload */
  if (fo_checkPQcommand(pgConn, pgResult, SQL, __FILE__ ,__LINE__)) return -1;
  PQclear(pgResult);

  if(isBigUpload)
  {
    snprintf(SQL,sizeof(SQL),"VACUUM ANALYZE %s",uploadtree_tablename);
    pgResult =  PQexec(pgConn, SQL);
    if (fo_checkPQcommand(pgConn, pgResult, SQL, __FILE__ ,__LINE__)) return -1;
    PQclear(pgResult);
  }
  return(0);
}

/*********************************************************
 Usage of the agent
 \param Name absolute path of the agent
 *********************************************************/
void    Usage   (char *Name)
{
  printf("Usage: %s [options] [id [id ...]]\n",Name);
  printf("  -h            :: help (print this message), then exit.\n");
  printf("  -i            :: initialize the database, then exit.\n");
  printf("  -a            :: run on ALL uploads that have no nested set records.\n");
  printf("  -c SYSCONFDIR :: Specify the directory for the system configuration.\n");
  printf("  -v            :: verbose (-vv = more verbose).\n");
  printf("  -u            :: list all upload ids, then exit.\n");
  printf("  no file       :: process upload ids from the scheduler.\n");
  printf("  id            :: process upload ids from the command-line.\n");
  printf("  -V            :: print the version info, then exit.\n");
} /* Usage() */

/*********************************************************/
int	main	(int argc, char *argv[])
{
  int c, i, rv;
  long UploadPk=-1;
  long *uploads_to_scan;
  int  upload_count = 0;
  int  user_pk;
  char *agent_desc = "Adj2nest Agent";
  char *COMMIT_HASH;
  char *VERSION;
  char agent_rev[myBUFSIZ];

  /* connect to scheduler.  Noop if not run from scheduler.  */
  fo_scheduler_connect(&argc, argv, &pgConn);

  COMMIT_HASH = fo_sysconfig("adj2nest", "COMMIT_HASH");
  VERSION = fo_sysconfig("adj2nest", "VERSION");
  sprintf(agent_rev, "%s.%s", VERSION, COMMIT_HASH);
  /* Get the Agent Key from the DB */
  fo_GetAgentKey(pgConn, basename(argv[0]), 0, agent_rev, agent_desc);

  /* for list of upload_pk's from the command line */
  uploads_to_scan = calloc(argc, sizeof(long));

  /* Process command-line */
  while((c = getopt(argc,argv,"aciuvVh")) != -1)
  {
    switch(c)
    {
      case 'a': /* run on ALL */
      RunAllNew();
      break;
    case 'c':
      break;  /* handled by fo_scheduler_connect()  */
    case 'i':
      PQfinish(pgConn);
      return(0);
    case 'v':	agent_verbose++; break;
    case 'u':
      /* list ids */
      ListUploads();
      PQfinish(pgConn);
      return(0);
    case 'V':
      printf("%s", BuildVersion);
      PQfinish(pgConn);
      return(0);
    default:
      Usage(argv[0]);
      fflush(stdout);
      PQfinish(pgConn);
      exit(-1);
    }
  }

  /* Copy filename args (if any) into array */
  for (i = optind; i < argc; i++)
  {
    uploads_to_scan[upload_count] = atol(argv[i]);
    upload_count++;
  }

  if (upload_count == 0)
  {
    user_pk = fo_scheduler_userID(); /* get user_pk for user who queued the agent */
    while(fo_scheduler_next())
    {
      UploadPk = atol(fo_scheduler_current());

      /* Check Permissions */
      if (GetUploadPerm(pgConn, UploadPk, user_pk) < PERM_WRITE)
      {
        LOG_ERROR("You have no update permissions on upload %ld", UploadPk);
        continue;
      }

      LoadAdj(UploadPk);
      if (Tree) WalkTree(0,0);
      if (Tree) free(Tree);
      Tree=NULL;
      TreeSize=0;
      /* Update Upload */
      rv = UpdateUpload(UploadPk);
      if (rv == -1) LOG_ERROR("Unable to update mode on upload %ld", UploadPk);
    } /* while() */
  }
  else
  {
    for (i = 0; i < upload_count; i++)
    {
      UploadPk = uploads_to_scan[i];
      LoadAdj(UploadPk);
      if (Tree) WalkTree(0,0);
      if (Tree) free(Tree);
      Tree=NULL;
      TreeSize=0;
      /* Update Upload */
      rv = UpdateUpload(UploadPk);
      if (rv == -1) LOG_ERROR("Unable to update mode on upload %ld", UploadPk);
    }
    free(uploads_to_scan);
  }

  PQfinish(pgConn);
  fo_scheduler_disconnect(0);
  return 0;
} /* main() */

