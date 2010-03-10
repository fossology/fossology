/***************************************************************
 Copyright (C) 2010 Hewlett-Packard Development Company, L.P.

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
/*
 \file buckets.c
 \brief Bucket agent

 The bucket agent uses user rules (see bucket table) to classify
 files into user categories
 */

#include "buckets.h"

int debug = 0;

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif /* SVN_REV */


/****************************************************
 walkTree

 This function does a recursive depth first walk through a file tree (uploadtree).
 
 @param PGconn pgConn   The database connection object.
 @param int  agent_pk   The agent_pk
 @param int  uploadtree_pk
 @param int  writeDB    true to write results to db, false writes to stdout
 @param int  skipProcessedCheck true if it is ok to skip the initial 
                        processed() call.  The call is unnecessary during 
                        recursion and it's an DB query, so best to avoid
                        doing an unnecessary call.
 @param char *fileName  uploadtree_pk ufile_name

 @return 0 on OK, -1 on failure.
 Errors are written to stdout.
****************************************************/
FUNCTION int walkTree(PGconn *pgConn, pbucketdef_t bucketDefArray, int agent_pk, 
                      int  uploadtree_pk, int writeDB, int skipProcessedCheck,
                      char *fileName)
{
  char *fcnName = "walkTree";
  char sqlbuf[128];
  PGresult *result;
  int  lft, rgt, pfile_pk, ufile_mode;
  int  child_uploadtree_pk, child_lft, child_rgt, child_pfile_pk, child_ufile_mode;
  char *child_ufile_name;
  int   numChildren, childIdx;
  int   rv = 0;
  int  *bucketList;  // null terminated list of bucket_pk's
  int  bucketpool_pk = bucketDefArray->bucketpool_pk;

  if (debug) printf("---- START walkTree, uploadtree_pk=%d ----\n",uploadtree_pk);
  /* get uploadtree rec for uploadtree_pk */
  sprintf(sqlbuf, "select pfile_fk, lft, rgt, ufile_mode from uploadtree where uploadtree_pk=%d", uploadtree_pk);
  result = PQexec(pgConn, sqlbuf);
  if (checkPQresult(result, sqlbuf, fcnName, __LINE__)) return -1;
  if (PQntuples(result) == 0) 
  {
    printf("FATAL: %s.%s missing uploadtree_pk %d\n", __FILE__, fcnName, uploadtree_pk);
    return -1;
  }
  pfile_pk = atol(PQgetvalue(result, 0, 0));
  lft = atol(PQgetvalue(result, 0, 1));
  rgt = atol(PQgetvalue(result, 0, 2));
  ufile_mode = atol(PQgetvalue(result, 0, 3));
  PQclear(result);

  /* Skip file if it has already been processed for buckets. */
  if (!skipProcessedCheck)
    if (processed(pgConn, agent_pk, pfile_pk, uploadtree_pk, bucketpool_pk)) return rv;

  /* If this is a leaf node, and not an artifact process it 
     (i.e. determine what bucket it belongs in).
     This should only be executed in the case where the unpacked upload
     is a single file.
   */
  if (rgt == (lft+1))
  {
    if (((ufile_mode & 1<<28) == 0) && (pfile_pk > 0))
    {
      return  processLeaf(pgConn, bucketDefArray, pfile_pk, uploadtree_pk, agent_pk, 
                          writeDB, fileName);
    }
    else
      return 0;  /* case of empty directory or artifact */
  }

  /* Since uploadtree_pk isn't a leaf, find its children and process (if child is leaf) 
     or recurse */
  sprintf(sqlbuf, "select uploadtree_pk,pfile_fk, lft, rgt, ufile_mode, ufile_name from uploadtree where parent=%d", 
          uploadtree_pk);
  result = PQexec(pgConn, sqlbuf);
  if (checkPQresult(result, sqlbuf, fcnName, __LINE__)) return -1;
  numChildren = PQntuples(result);
  if (numChildren == 0) 
  {
    printf("FATAL: %s.%s: Inconsistent uploadtree. uploadtree_pk %d should have children based on lft and rgt\n", 
           __FILE__, fcnName, uploadtree_pk);
    return -1;
  }

  /* process (find buckets for) each child */
  for (childIdx = 0; childIdx < numChildren; childIdx++)
  {
    child_uploadtree_pk = atol(PQgetvalue(result, childIdx, 0));
    child_pfile_pk = atol(PQgetvalue(result, childIdx, 1));
    if (processed(pgConn, agent_pk, child_pfile_pk, child_uploadtree_pk, bucketpool_pk)) continue;

    child_lft = atoi(PQgetvalue(result, childIdx, 2));
    child_rgt = atoi(PQgetvalue(result, childIdx, 3));
    child_ufile_mode = atoi(PQgetvalue(result, childIdx, 4));
    child_ufile_name = PQgetvalue(result, childIdx, 5);

    /* if child is a leaf, just process rather than recurse 
    */
    if (child_rgt == (child_lft+1)) 
    {
      //if (((child_ufile_mode & 1<<28) == 0) && (child_pfile_pk > 0))
      if (child_pfile_pk > 0)
        processLeaf(pgConn, bucketDefArray, child_pfile_pk, child_uploadtree_pk, 
                    agent_pk, writeDB, child_ufile_name);
      continue;
    }

    /* not a leaf so recurse */
    rv = walkTree(pgConn, bucketDefArray, agent_pk, child_uploadtree_pk, writeDB, 
                  1, child_ufile_name);

    /* done processing children, now processes (find buckets) for the container
       Need to process artifacts as a regular directory so that buckets cascade
       up without interruption.
     */
    if ((ufile_mode & 1<<29) )
    {
      bucketList = getContainerBuckets(pgConn, bucketDefArray, child_uploadtree_pk);
      rv = writeBuckets(pgConn, child_pfile_pk, child_uploadtree_pk, bucketList, agent_pk, writeDB);
    }
  }

  return rv;
} /* walkTree */


/****************************************************
 processLeaf

 determine which bucket(s) a leaf node is in and write results

 @param PGconn      *pgConn          postgresql connection
 @param pbucketdef_t bucketDefArray  Bucket Definitions
 @param int          pfile_pk  
 @param int          uploadtree_pk  
 @param int          agent_pk
 @param int          writeDB         True writes to DB, False writes results to stdout
 @param char        *fileName        uploadtree_pk ufile_name

 @return 0=success, else error
****************************************************/
FUNCTION int processLeaf(PGconn *pgConn, pbucketdef_t bucketDefArray, 
                         int pfile_pk, int uploadtree_pk, int agent_pk, int writeDB,
                         char *fileName)
{
  int rv = 0;
  int *bucketList;

  if (debug) printf("processLeaf() pfile: %d\n", pfile_pk);
  bucketList = getLeafBuckets(pgConn, bucketDefArray, pfile_pk, fileName);
  if (bucketList) 
  {
    if (debug)
    {
      printf("  buckets for pfile %d:",pfile_pk);
      for (rv=0;bucketList[rv];rv++) printf("%d ",bucketList[rv]);
      printf("\n");
    }
    rv = writeBuckets(pgConn, pfile_pk, uploadtree_pk, bucketList, agent_pk, writeDB);
  }
  return rv;
}


/****************************************************
 getLeafBuckets

 given a pfile and bucketdef, determine what buckets the pfile is in

 @param PGconn *pgConn  postgresql connection
 @param pbucketdef_t bucketDefArray
 @param int uploadtree_pk  
 @param char *fileName   uploadtree_pk ufile_name

 @return array of bucket_pk's, or 0 if error
****************************************************/
FUNCTION int *getLeafBuckets(PGconn *pgConn, pbucketdef_t in_bucketDefArray, int pfile_pk,
                             char *fileName)
{
  char *fcnName = "getLeafBuckets";
  int  *bucket_pk_list = 0;
  int  *bucket_pk_list_start;
  char  filepath[256];
  char  sql[256];
  PGresult *result;
  PGresult *resultpkg = 0;
  int   numLics, licNumb;
  int   numBucketDefs = 0;
  int   match = 0;   // bucket match
  int   foundmatch, foundmatch2; 
  int   *pmatch_array;
  int  **ppmatch_array;
  int  *pfile_rfpks;
  int   rv;
  pbucketdef_t bucketDefArray;
  regex_file_t *regex_row;
  char *argv[2];
  char *envp[5];
  char  envbuf[256];
  char *pkgvers=0, *vendor=0;
  pid_t pid;

  if (debug) printf("debug: %s  pfile: %d\n", fcnName, pfile_pk);
  /*** count how many elements are in in_bucketDefArray   ***/
  for (bucketDefArray = in_bucketDefArray; bucketDefArray->bucket_pk; bucketDefArray++)
    numBucketDefs++;

  /* allocate return array to hold max number of bucket_pk's + 1 for null terminator */
  bucket_pk_list_start = calloc(numBucketDefs+1, sizeof(int));
  if (bucket_pk_list_start == 0)
  {
    printf("FATAL: out of memory allocating int array of %d elements\n", numBucketDefs+1);
    return 0;
  }
  bucket_pk_list = bucket_pk_list_start;
  
  /*** select all the licenses for pfile_pk and agent_pk ***/
  bucketDefArray = in_bucketDefArray;
  snprintf(sql, sizeof(sql), 
           "select rf_shortname, rf_pk from license_file, license_ref where agent_fk=%d and pfile_fk=%d and rf_fk=rf_pk",
           bucketDefArray->nomos_agent_pk, pfile_pk);
  result = PQexec(pgConn, sql);
  if (checkPQresult(result, sql, fcnName, __LINE__)) return 0;
  numLics = PQntuples(result);
  
  /* make int array of rf_pk's for this pfile */
  pfile_rfpks = calloc(numLics+1, sizeof(int));
  if (pfile_rfpks == 0)
  {
    printf("FATAL: out of memory allocating int array of %d rf_pk elements\n", numLics+1);
    return 0;
  }
  for (licNumb=0; licNumb < numLics; licNumb++) 
    pfile_rfpks[licNumb] = atoi(PQgetvalue(result, licNumb, 1));
  
  /* loop through all the bucket defs in this pool */
  while (bucketDefArray->bucket_pk != 0)
  {
    /* if this def is restricted to package (applies_to=2), 
       then skip if this is not a package.
       NOTE DEPENDENCY ON PKG ANALYSIS!
    */
    if (bucketDefArray->applies_to == 2)
    {
      snprintf(sql, sizeof(sql), 
           "select pkg_name, version, "" as vendor  from pkg_deb where pfile_fk='%d' \
            union all \
            select pkg_name, version, vendor from pkg_rpm where pfile_fk='%d' ",
            pfile_pk, pfile_pk);
      resultpkg = PQexec(pgConn, sql);
      if (checkPQresult(resultpkg, sql, fcnName, __LINE__)) return 0;
      numLics = PQntuples(resultpkg);
      if (numLics < 1) continue;
      pkgvers = PQgetvalue(result, 0, 1);
      vendor = PQgetvalue(result, 0, 2);
    }

    switch (bucketDefArray->bucket_type)
    {
      /***  1  MATCH_EVERY  ***/
      case 1:
        ppmatch_array = bucketDefArray->match_every;
        if (!ppmatch_array) break;  
        while (*ppmatch_array)
        {
          /* is match_array contained in pfile_rfpks?  */
          if (arrayAinB(*ppmatch_array, pfile_rfpks))
          {
            *bucket_pk_list = bucketDefArray->bucket_pk;
            bucket_pk_list++;
            match++;
            break;
          }
          ++ppmatch_array;
        }
        break;
        
      /***  2  MATCH_ONLY  ***/
      case 2: 
        foundmatch = 1;  
        /* loop through pfile licenses to see if they are all found in the match_only list  */
        for (licNumb=0; licNumb < numLics; licNumb++) 
        {
          /* if rf_pk doesn't match any value in match_only, 
             then pfile is not in this bucket              */
          pmatch_array = bucketDefArray->match_only;
          while (*pmatch_array)
          {
            if (pfile_rfpks[licNumb] == *pmatch_array) break;
            pmatch_array++;
          }
          if (!*pmatch_array) 
          {
            /* no match, so pfile is not in this bucket */
            foundmatch = 0;
            break;  /* break out of for loop */
          }
        }
        if (foundmatch)
        {
          *bucket_pk_list = bucketDefArray->bucket_pk;
          bucket_pk_list++;
          match++;
        }
        break;

      /***  3  REGEX  ***/
      case 3:  /* does this regex match any license names for this pfile */
        if (matchAnyLic(result, numLics, &bucketDefArray->compRegex))
        {
          /* regex matched!  */
          *bucket_pk_list = bucketDefArray->bucket_pk;
          bucket_pk_list++;
          match++;
        }
        break;

      /***  4  EXEC  ***/
      case 4:  
        /* file to exec bucketDefArray->dataFilename
         * Exec'd file returns 0 on true (file is in bucket)
         * environment: FILENAME, LICENSES, PKGVERS, VENDOR
         * FILENAME: name of file being checked
         * LICENSES: pipe seperated list of licenses for this file.
         * PKGVERS: Package version from pkg header
         * VENDOR: Vendor from pkg header
         */
        /* put together complete file path to file */
        snprintf(filepath, sizeof(filepath), "%s/bucketpools/%d/%s", 
                 DATADIR, bucketDefArray->bucketpool_pk, bucketDefArray->dataFilename);
			if ((pid = fork()) < 0)
      {
        printf("FATAL: fork failure, %s\n", strerror(errno));
			}
			else 
      if (pid == 0)  /* in child */
      {
				/* set up environment variables */
        argv[0] = strdup(bucketDefArray->dataFilename);
        argv[1] = 0;
        sprintf(envbuf, "FILENAME=%s", fileName);
        envp[0] = strdup(envbuf);
        /* create pipe seperated list of licenses */
        strcpy(envbuf, "LICENSES=");
        for (licNumb=0; licNumb < numLics; licNumb++) 
        {
          if (envbuf[9]) strcat(envbuf, "|");
          strcat(envbuf, PQgetvalue(result, licNumb, 0));
        }
        envp[1] = strdup(envbuf);
        sprintf(envbuf, "PKGVERS=%s", pkgvers);
        envp[2] = strdup(envbuf); 
        sprintf(envbuf, "VENDOR=%s", vendor);
        envp[3] =strdup(envbuf); 
        envp[4] = 0;
        execve(filepath, argv, envp);
        printf("FATAL: buckets execve (%s) failed, %s\n", filepath, strerror(errno));
        exit(1);
			}

      /* wait for exit */
			if (waitpid(pid, &rv, 0) < 0) 
      {
        printf("FATAL: waitpid, %s\n", strerror(errno));
			}
			if (WIFSIGNALED(rv)) 
      {
        printf("FATAL: child %d died from signal %d", pid, WTERMSIG(rv));
      }
			else 
      if (WIFSTOPPED(rv)) 
      {
        printf("FATAL: child %d stopped, signal %d", pid, WSTOPSIG(rv));
      }
			else 
      if (WIFEXITED(rv)) 
      {
				if (WEXITSTATUS(rv) == 0) 
        {
          *bucket_pk_list = bucketDefArray->bucket_pk;
          bucket_pk_list++;
          match++;
				}
			}
      break;

      /***  5  REGEX-FILE  ***/
      /* File format is:
         {filetype1} {regex1} {op} {filetype2} {regex2}
         filetype == 1 is filename
         filetype == 2 is license
         op to end of line is optional.
         e.g. filename COPYRIGHT and license BSD.*clause
      */
      case 5:  
        regex_row = bucketDefArray->regex_row;
        foundmatch = 0;
        foundmatch2 = 0;
        /* loop through each regex_row */
        while (regex_row->ftype1)
        {
          /* switches do not have a default since values have already been validated
             see init.c
          */
          switch (regex_row->ftype1)
          {
            case 1: // check regex against filename
              foundmatch = !regexec(&regex_row->compRegex1, fileName, 0, 0, 0);
              break;
            case 2: // check regex against licenses
              foundmatch = matchAnyLic(result, numLics, &regex_row->compRegex1);
              break;
          }

          /* no sense in evaluating last half if first have is a match and
             op is an OR
          */
          if ((regex_row->op == 2) || !foundmatch)
            if (regex_row->op)
            {
              switch (regex_row->ftype2)
              {
                case 1: // check regex against filename
                  foundmatch2 = !regexec(&regex_row->compRegex2, fileName, 0, 0, 0);
                  break;
                case 2: // check regex against licenses
                  foundmatch2 = matchAnyLic(result, numLics, &regex_row->compRegex2);
                  break;
              }
            }

          switch (regex_row->op)
          {
            case 1: // AND
              foundmatch = (foundmatch && foundmatch2) ? 1 : 0;
              break;
            case 2: // OR
              foundmatch = (foundmatch || foundmatch2) ? 1 : 0;
              break;
            case 3: // Not
              foundmatch = (foundmatch && !foundmatch2) ? 1 : 0;
              break;
          }

          if (foundmatch)
          {
            *bucket_pk_list = bucketDefArray->bucket_pk;
            bucket_pk_list++;
            match++;
          }
          regex_row++;
        }
        break;

      /*** 99 DEFAULT bucket. aka not in any other bucket ***/
      case 99:
        if (!match) 
        {
          *bucket_pk_list = bucketDefArray->bucket_pk;
          bucket_pk_list++;
          match++;
        }
        break;

      /*** UNKNOWN BUCKET TYPE  ***/
      default:  
        printf("FATAL: Unknown bucket type %d, exiting...\n",
                bucketDefArray->bucket_type);
        exit(-1);
    }
    if (match && bucketDefArray->stopon == 'Y') break;
    bucketDefArray++;
  }

  PQclear(result);
  if (resultpkg) PQclear(resultpkg);
  return bucket_pk_list_start;
}


/****************************************************
 matchAnyLic

 Does this regex match any license name for this pfile?

 @param PGresult result    results from select of lic names for this pfile
 @param int      numLics   number of lics in result
 @param regex_t *compRegex ptr to compiled regex to check

 @return 1=true, 0=false
****************************************************/
FUNCTION int matchAnyLic(PGresult *result, int numLics, regex_t *compRegex)
{
  int   licNumb;
  char *licName;

  for (licNumb=0; licNumb < numLics; licNumb++)
  {
    licName = PQgetvalue(result, licNumb, 0);
    if (0 == regexec(compRegex, licName, 0, 0, 0)) return 1;
  }
  return 0;
}


/****************************************************
 getContainerBuckets

 given a container uploadtree_pk and bucketdef, determine what buckets 
 the container is in (based on the buckets of its children).
 
 This function is also called for artifacts to simplify the
 recursion in walkTree().

 Unlike licenses, where we can report a license hierarchy at runtime
 from a single select, buckets need to be evaluated in order.  Because
 of this extra processing, this agent computes and stores
 buckets for containers (this function).

 @param PGconn      *pgConn  postgresql connection
 @param pbucketdef_t bucketDefArray  
 @param int          uploadtree_pk

 @return array of bucket_pk's for this uploadtree_pk

 Note: You can't just pass in a list of child buckets from walkTree()
       since, due to pfile reuse, walkTree() may not have processed
       parts of the tree.
****************************************************/
FUNCTION int *getContainerBuckets(PGconn *pgConn, pbucketdef_t in_bucketDefArray,
                                  int uploadtree_pk)
{
  char *fcnName = "getContainerBuckets";
  char  sql[512];
  int  *bucket_pk_list = 0;
  int  *bucket_pk_list_start = 0;
  int   numBucketDefs = 0;
  int  *children_bucket_pk_list = 0;
  int   childParent_pk;  /* uploadtree_pk */
  int   numLics;
  int   bucketNumb;
  int   match;
  PGresult *result;
  pbucketdef_t bucketDefArray;

  if (debug) printf("%s: for uploadtree_pk %d\n",fcnName,uploadtree_pk);

  /* Find the parent of this uploadtree_pk's children.  */
//  childParent_pk = childParent(pgConn, uploadtree_pk);
//printf("childParent_pk %d\n", childParent_pk);
  childParent_pk = uploadtree_pk;

  /* If this is a container without a pfile, save the bucket info in
     bucket_container table */
  

  /* Get all the bucket_fk's from the immediate children  
     That is, what buckets are the children in */
  snprintf(sql, sizeof(sql), 
           "select distinct(bucket_fk) from uploadtree,bucket_container, bucket_def \
             where parent='%d' and bucket_container.uploadtree_fk=uploadtree_pk \
                   and bucket_fk=bucket_pk and agent_fk='%d' and bucketpool_fk='%d'\
            union\
            select distinct(bucket_fk) from uploadtree, bucket_file, bucket_def \
             where parent='%d' and bucket_file.pfile_fk=uploadtree.pfile_fk \
                   and bucket_fk=bucket_pk and agent_fk='%d' and bucketpool_fk='%d'",
           childParent_pk, in_bucketDefArray->bucket_agent_pk, 
           in_bucketDefArray->bucketpool_pk,
           childParent_pk, in_bucketDefArray->bucket_agent_pk, 
           in_bucketDefArray->bucketpool_pk);
  result = PQexec(pgConn, sql);
  if (checkPQresult(result, sql, fcnName, __LINE__)) return 0;
  numLics = PQntuples(result);

  /*** save the bucket list in a null terminated easy access int array ***/
  children_bucket_pk_list = calloc(numLics+1, sizeof(int));
  if (children_bucket_pk_list == 0)
  {
    printf("FATAL: out of memory allocating int array of %d ints\n", numLics+1);
    return 0;
  }
  for (bucketNumb=0; bucketNumb < numLics; bucketNumb++)
  {
    children_bucket_pk_list[bucketNumb] = atoi(PQgetvalue(result, bucketNumb, 0));
  }
  PQclear(result);

  /*** count how many elements are in in_bucketDefArray   ***/
  /* move this out when implement pbucketpool_t */
  for (bucketDefArray = in_bucketDefArray; bucketDefArray->bucket_pk; bucketDefArray++)
    numBucketDefs++;

  /* allocate return array to hold max number of bucket_pk's + 1 for null terminator */
  bucket_pk_list_start = calloc(numBucketDefs+1, sizeof(int));
  if (bucket_pk_list_start == 0)
  {
    printf("FATAL: out of memory allocating int array of %d ints\n", numBucketDefs+1);
    return 0;
  }
  bucket_pk_list = bucket_pk_list_start;

  if (debug) printf("debug found %d buckets under parent %d, childParent %d\n",numLics, uploadtree_pk, childParent_pk);

  /* loop through each bucket definition */
  bucketDefArray = in_bucketDefArray;
  match = 0;
  while (bucketDefArray->bucket_pk != 0)
  {
    /* if children_bucket_pk_list contains this bucket_pk 
       then this is a match */
    if (intAinB(bucketDefArray->bucket_pk, children_bucket_pk_list))
    {
      if (debug) printf(">>>   found bucket_pk: %d\n", bucketDefArray->bucket_pk);
      *bucket_pk_list = bucketDefArray->bucket_pk;
      bucket_pk_list++;
      match++;
      break;
    }

    if (match && bucketDefArray->stopon == 'Y') break;
    bucketDefArray++;
  }
  return bucket_pk_list_start;
}


/****************************************************
 writeBuckets

 Write bucket results to either db (bucket_file, bucket_container) or stdout.

 @param PGconn *pgConn  postgresql connection
 @param int pfile_pk  
 @param int uploadtree_pk  
 @param int *bucketList   null terminated array of bucket_pks 
                          that match this pfile
 @param int agent_pk  

 @return 0=success, errors are FATAL and will exit process.
****************************************************/
FUNCTION int writeBuckets(PGconn *pgConn, int pfile_pk, int uploadtree_pk, 
                          int *bucketList, int agent_pk, int writeDB)
{
  char     *fcnName = "writeBuckets";
  char      sql[256];
  PGresult *result;
  int rv = 0;
  if (debug) printf("debug: %s pfile: %d, uploadtree_pk: %d\n", fcnName, pfile_pk, uploadtree_pk);

  if (!writeDB) printf("write buckets for pfile=%d, uploadtree_pk=%d: ", pfile_pk, uploadtree_pk);

  if (bucketList)
  {
    while(*bucketList)
    {
      if (writeDB)
      {
        if (pfile_pk)
        {
          snprintf(sql, sizeof(sql), 
                 "insert into bucket_file (bucket_fk, pfile_fk, agent_fk) \
                  values(%d,%d,%d)", *bucketList, pfile_pk, agent_pk);
          result = PQexec(pgConn, sql);
          if (PQresultStatus(result) != PGRES_COMMAND_OK) 
          {
            printf("ERROR: %s.%s.%d:  Failed to add bucket to bucket_file. %s:%s\n: %s\n",
                    __FILE__,fcnName, __LINE__, PQresultErrorField(result, PG_DIAG_SQLSTATE),
                    PQresultErrorMessage(result), sql);
            PQclear(result);
          }
          if (debug) printf("%s sql: %s\n",fcnName, sql);
        }
        else
        {
          snprintf(sql, sizeof(sql), 
                 "insert into bucket_container (bucket_fk, uploadtree_fk, agent_fk) \
                  values(%d,%d,%d)", *bucketList, uploadtree_pk, agent_pk);
          result = PQexec(pgConn, sql);
          if (PQresultStatus(result) != PGRES_COMMAND_OK) 
          {
            printf("ERROR: %s.%s.%d:  Failed to add bucket to bucket_container. %s:%s\n: %s\n",
                    __FILE__,fcnName, __LINE__, PQresultErrorField(result, PG_DIAG_SQLSTATE),
                    PQresultErrorMessage(result), sql);
            PQclear(result);
          }
          if (debug) printf("%s sql: %s\n",fcnName, sql);
        }
      }
      else
        printf(" %d", *bucketList);
      bucketList++;
    }
  }

  if (!writeDB) printf("\n");
  return rv;
}


/****************************************************
 processed

 Has this pfile or uploadtree_pk already been bucket processed?
 This only works if the bucket has been recorded in table 
 bucket_file, or bucket_container.

 @param PGconn *pgConn  postgresql connection
 @param int *agent_pk   agent ID
 @param int pfile_pk  
 @param int uploadtree_pk  
 @param int bucketpool_pk  

 @return 1=yes, 0=no
****************************************************/
FUNCTION int processed(PGconn *pgConn, int agent_pk, int pfile_pk, int uploadtree_pk,
                       int bucketpool_pk)
{
  char *fcnName = "processed";
  int numRecs=0;
  char sqlbuf[512];
  PGresult *result;

  /* Skip file if it has already been processed for buckets. 
     See if this pfile or uploadtree_pk has any buckets. */
  sprintf(sqlbuf,
    "select bf_pk from bucket_file, bucket_def \
      where pfile_fk=%d and agent_fk=%d and bucketpool_fk=%d \
            and bucket_fk=bucket_pk \
     union \
     select bf_pk from bucket_container, bucket_def \
      where uploadtree_fk=%d and agent_fk=%d and bucketpool_fk=%d \
            and bucket_fk=bucket_pk limit 1",
    pfile_pk, agent_pk, bucketpool_pk, uploadtree_pk, agent_pk, bucketpool_pk);
  result = PQexec(pgConn, sqlbuf);
  if (checkPQresult(result, sqlbuf, fcnName, __LINE__)) return -1;
  numRecs = PQntuples(result);
  PQclear(result);

  if (debug) printf("%s: returning %d, for pfile_pk %d, uploadtree_pk %d\n",fcnName,numRecs,pfile_pk, uploadtree_pk);
  return numRecs;
}


/****************************************************/
int main(int argc, char **argv) 
{
  char *agentDesc = "Bucket agent";
  int cmdopt;
  int verbose = 0;
  int writeDB = 0;
  int head_uploadtree_pk = 0;
  void *DB;   // DB object from agent
  PGconn *pgConn;
  PGresult *result;
  char sqlbuf[128];
  int agent_pk = 0;
  int nomos_agent_pk = 0;
  int bucketpool_pk = 0;
  int upload_pk = 0;
  char *fileName;  // head_uploadtree_pk ufile_name
  char *bucketpool_name;
  int pfile_pk = 0;
  int *bucketList;
  pbucketdef_t bucketDefArray = 0;
  cacheroot_t  cacheroot;

  extern int AlarmSecs;
//  extern long HBItemsProcessed;

  /* Connect to the database */
  DB = DBopen();
  if (!DB) 
  {
    printf("FATAL: Bucket agent unable to connect to database, exiting...\n");
    exit(-1);
  }
  pgConn = DBgetconn(DB);

  /* command line options */
  while ((cmdopt = getopt(argc, argv, "din:p:t:u:v")) != -1) 
  {
    switch (cmdopt) 
    {
      case 'd': /* write results to db instead of stdout
                   Note: license_ref may get written to even if writeDB=0
                   Note: ALWAYS use -d unless you are debugging and know
                         what you are doing.  Several functions
                         depend on db updates (like determining bucket
                         of container).
                 */
            writeDB = 1;
            break;
      case 'i': /* "Initialize" */
            DBclose(DB); /* DB was opened above, now close it and exit */
            exit(0);
      case 'n': /* bucketpool_name  */
            bucketpool_name = optarg;
            /* find the highest rev active bucketpool_pk */
            if (!bucketpool_pk)
            {
              bucketpool_pk = getBucketpool_pk(pgConn, bucketpool_name);
              if (!bucketpool_pk)
                printf("%s is not an active bucketpool name.\n", bucketpool_name);
            }
            break;
      case 'p': /* bucketpool_pk */
            bucketpool_pk = atoi(optarg);
            /* validate bucketpool_pk */
            sprintf(sqlbuf, "select bucketpool_pk from bucketpool where bucketpool_pk=%d and active='Y'", bucketpool_pk);
            bucketpool_pk = validate_pk(pgConn, sqlbuf);
            if (!bucketpool_pk)
              printf("%d is not an active bucketpool_pk.\n", atoi(optarg));
            break;
      case 't': /* uploadtree_pk */
            head_uploadtree_pk = atoi(optarg);
            /* validate bucketpool_pk */
            sprintf(sqlbuf, "select uploadtree_pk from uploadtree where uploadtree_pk=%d", head_uploadtree_pk);
            head_uploadtree_pk = validate_pk(pgConn, sqlbuf);
            if (!head_uploadtree_pk)
              printf("%d is not an active uploadtree_pk.\n", atoi(optarg));
            break;
      case 'u': /* upload_pk */
            if (!head_uploadtree_pk)
            {
              upload_pk = atoi(optarg);
              /* validate upload_pk  and get uploadtree_pk  */
              sprintf(sqlbuf, "select upload_pk from upload where upload_pk=%d", upload_pk);
              upload_pk = validate_pk(pgConn, sqlbuf);
              if (!upload_pk)
                printf("%d is not an valid upload_pk.\n", atoi(optarg));
              else
              {
                sprintf(sqlbuf, "select uploadtree_pk from uploadtree where upload_fk=%d and parent is null", upload_pk);
                head_uploadtree_pk = validate_pk(pgConn, sqlbuf);
              }
            }
            break;
      case 'v': /* verbose output for debugging  */
            /* FOR NOW this also means debug */
            verbose++;
            break;
      default:
            Usage(argv[0]);
            DBclose(DB);
            exit(-1);
    }
  }
  debug = verbose;

  /*** validate command line ***/
  if (!bucketpool_pk)
  {
    printf("You must specify an active bucketpool.\n");
    Usage(argv[0]);
    exit(-1);
  }
  if (!head_uploadtree_pk)
  {
    printf("You must specify a valid uploadtree_pk or upload_pk.\n");
    Usage(argv[0]);
    exit(-1);
  }

  /* get agent pk 
   * Note, if GetAgentKey fails, this process will exit.
   */
  agent_pk = GetAgentKey(DB, basename(argv[0]), 0, SVN_REV, agentDesc);

  /*** Get the pfile for head_uploadtree_pk so we can
     check if its already been processed ***/
  sprintf(sqlbuf, "select pfile_fk, ufile_name from uploadtree where uploadtree_pk=%d", head_uploadtree_pk);
  result = PQexec(pgConn, sqlbuf);
  if (checkPQresult(result, sqlbuf, agentDesc, __LINE__)) return -1;
  if (PQntuples(result) == 0) 
  {
    printf("FATAL: %s.%s missing root uploadtree_pk %d\n", 
           __FILE__, agentDesc, head_uploadtree_pk);
    return -1;
  }
  pfile_pk = atol(PQgetvalue(result, 0, 0));
  fileName = PQgetvalue(result, 0, 1);
  PQclear(result);

  /* Has it already been processed?  If so, we are done */
  if (processed(pgConn, agent_pk, pfile_pk, head_uploadtree_pk, bucketpool_pk)) return 0;

  /*** Make sure there is  license data available from the latest nomos agent ***/
  nomos_agent_pk = licDataAvailable(pgConn, head_uploadtree_pk);
  if (nomos_agent_pk == 0)
  {
    printf("WARNING: Bucket agent called on treeitem (%d), but the latest nomos agent hasn't created any license data for this tree.\n",
          head_uploadtree_pk);
    return -1;
  }

  /*** Initialize the license_ref table cache ***/
  /* Build the license ref cache to hold 2**11 (2048) licenses.
     This MUST be a power of 2.
   */
  cacheroot.maxnodes = 2<<11;
  cacheroot.nodes = calloc(cacheroot.maxnodes, sizeof(cachenode_t));
  if (!lrcache_init(pgConn, &cacheroot))
  {
    printf("FATAL: Bucket agent could not allocate license_ref table cache.\n");
    exit(1);
  }


  if (writeDB)
  {
    signal(SIGALRM, ShowHeartbeat);
    alarm(AlarmSecs);
    printf("OK\n");
    fflush(stdout);
  }

  // Heartbeat(++HBItemsProcessed);
  // printf("OK\n"); /* tell scheduler ready for more data */
  // fflush(stdout);

  /* Initialize the Bucket Definition List bucketDefArray  */
  bucketDefArray = initBuckets(pgConn, bucketpool_pk, &cacheroot);
  if (bucketDefArray == 0)
  {
    printf("FATAL: %s.%d Bucket definition for pool %d could not be initialized.\n",
           __FILE__, __LINE__, bucketpool_pk);
    return -1;
  }
  bucketDefArray->nomos_agent_pk = nomos_agent_pk;
  bucketDefArray->bucket_agent_pk = agent_pk;

  /* process the tree for buckets */
  /* all or nothing database update */
  result = PQexec(pgConn, "begin");
  if (checkPQcommand(result, "begin", __FILE__, __LINE__)) return -1;

  walkTree(pgConn, bucketDefArray, agent_pk, head_uploadtree_pk, writeDB, 0, fileName);
  bucketList = getContainerBuckets(pgConn, bucketDefArray, head_uploadtree_pk);
  writeBuckets(pgConn, pfile_pk, head_uploadtree_pk, bucketList, agent_pk, writeDB);

  result = PQexec(pgConn, "commit");
  if (checkPQcommand(result, "commit", __FILE__, __LINE__)) return -1;

  PQfinish(pgConn);
  return (0);
}
