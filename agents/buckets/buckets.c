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

//#define BOBG
#include "buckets.h"

int debug = 0;

/* global mimetype_pk's for Debian source and binary packages */
int DEB_SOURCE;
int DEB_BINARY;

#ifdef SVN_REV
char BuildVersion[]="Build version: " SVN_REV ".\n";
#endif /* SVN_REV */


/****************************************************
 walkTree

 This function does a recursive depth first walk through a file tree (uploadtree).
 
 @param PGconn pgConn   The database connection object.
 @param pbucketdef_t    bucketDefArray  Bucket Definitions
 @param int  agent_pk   The agent_pk
 @param int  uploadtree_pk
 @param int  writeDB    true to write results to db, false writes to stdout
 @param int  skipProcessedCheck true if it is ok to skip the initial 
                        processed() call.  The call is unnecessary during 
                        recursion and it's an DB query, so best to avoid
                        doing an unnecessary call.
 @param int  hasPrules  1=bucketDefArray contains at least one rule that only 
                        apply to packages.  0=No package rules.

 @return 0 on OK, -1 on failure.
 Errors are written to stdout.
****************************************************/
FUNCTION int walkTree(PGconn *pgConn, pbucketdef_t bucketDefArray, int agent_pk, 
                      int  uploadtree_pk, int writeDB, int skipProcessedCheck,
                      int hasPrules)
{
  char *fcnName = "walkTree";
  char sqlbuf[128];
  PGresult *result, *origresult;
  int   numChildren, childIdx;
  int   rv = 0;
  int  bucketpool_pk = bucketDefArray->bucketpool_pk;
  uploadtree_t uploadtree;
  uploadtree_t childuploadtree;

  if (debug) printf("---- START walkTree, uploadtree_pk=%d ----\n",uploadtree_pk);
  /* get uploadtree rec for uploadtree_pk */
  sprintf(sqlbuf, "select pfile_fk, lft, rgt, ufile_mode, ufile_name, upload_fk from uploadtree where uploadtree_pk=%d", uploadtree_pk);
  origresult = PQexec(pgConn, sqlbuf);
  if (checkPQresult(origresult, sqlbuf, fcnName, __LINE__)) return -1;
  if (PQntuples(origresult) == 0) 
  {
    printf("FATAL: %s.%s missing uploadtree_pk %d\n", __FILE__, fcnName, uploadtree_pk);
    return -1;
  }
  uploadtree.uploadtree_pk = uploadtree_pk;
  uploadtree.pfile_fk = atol(PQgetvalue(origresult, 0, 0));
  uploadtree.lft = atol(PQgetvalue(origresult, 0, 1));
  uploadtree.rgt = atol(PQgetvalue(origresult, 0, 2));
  uploadtree.ufile_mode = atol(PQgetvalue(origresult, 0, 3));
  uploadtree.ufile_name = strdup(PQgetvalue(origresult, 0, 4));
  uploadtree.upload_fk = atol(PQgetvalue(origresult, 0, 5));

  /* Skip file if it has already been processed for buckets. */
  if (!skipProcessedCheck)
    if (processed(pgConn, agent_pk, uploadtree.pfile_fk, uploadtree.uploadtree_pk, bucketpool_pk)) return 0;

  /* If this is a leaf node, and not an artifact process it 
     (i.e. determine what bucket it belongs in).
     This should only be executed in the case where the unpacked upload
     is a single file.
   */
  if (uploadtree.rgt == (uploadtree.lft+1))
  {
    return  processFile(pgConn, bucketDefArray, &uploadtree, agent_pk, writeDB, hasPrules);
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
    childuploadtree.uploadtree_pk = atol(PQgetvalue(result, childIdx, 0));
    childuploadtree.pfile_fk = atol(PQgetvalue(result, childIdx, 1));
    if (processed(pgConn, agent_pk, childuploadtree.pfile_fk, childuploadtree.uploadtree_pk, bucketpool_pk)) continue;

    childuploadtree.lft = atoi(PQgetvalue(result, childIdx, 2));
    childuploadtree.rgt = atoi(PQgetvalue(result, childIdx, 3));
    childuploadtree.ufile_mode = atoi(PQgetvalue(result, childIdx, 4));
    childuploadtree.ufile_name = strdup(PQgetvalue(result, childIdx, 5));
    childuploadtree.upload_fk = uploadtree.upload_fk;

    /* if child is a leaf, just process rather than recurse 
    */
    if (childuploadtree.rgt == (childuploadtree.lft+1)) 
    {
      if (childuploadtree.pfile_fk > 0)
        processFile(pgConn, bucketDefArray, &childuploadtree,
                    agent_pk, writeDB, hasPrules);
      continue;
    }

    /* not a leaf so recurse */
    rv = walkTree(pgConn, bucketDefArray, agent_pk, childuploadtree.uploadtree_pk, writeDB, 
                  1, hasPrules);
    if (rv) return rv;

    /* done processing children, now processes (find buckets) for the container */
    processFile(pgConn, bucketDefArray, &childuploadtree, agent_pk, writeDB, 
                hasPrules);
  } // end of child processing

  PQclear(result);
  PQclear(origresult);
  return rv;
} /* walkTree */


/****************************************************
 processFile

 Process a file.  The file might be a single file, a container,
 an artifact, a package, ...
 Need to process container artifacts as a regular directory so that buckets cascade
 up without interruption.
 There is one small caveat.  If the container is a package AND
 the bucketDefArray has rules that apply to packages (applies_to='p')
 THEN process the package as a leaf since the bucket pool has its own 
 rules for packages.
 
 @param PGconn pgConn   The database connection object.
 @param pbucketdef_t    bucketDefArray  Bucket Definitions
 @param pupuploadtree_t Uploadtree record
 @param int  agent_pk   The agent_pk
 @param int  writeDB    true to write results to db, false writes to stdout
 @param int  hasPrules  1=bucketDefArray contains at least one rule that only 
                        apply to packages.  0=No package rules.

 @return 0 on OK, -1 on failure.
 Errors are written to stdout.
****************************************************/
FUNCTION int processFile(PGconn *pgConn, pbucketdef_t bucketDefArray, 
                      puploadtree_t puploadtree, int agent_pk, 
                      int writeDB, int hasPrules)
{
  int  *bucketList;  // null terminated list of bucket_pk's
  int  rv = 0;
  int  isPkg = 0;
  char *fcnName = "processFile";
  char  sql[1024];
  PGresult *result;
  package_t package;

  /* Can skip processing for terminal artifacts (e.g. empty artifact container,
     and "artifact.meta" files.
  */
  if (IsArtifact(puploadtree->ufile_mode) 
      && (puploadtree->rgt == puploadtree->lft+1)) return 0;

  package.pkgname[0] = 0;
  package.pkgvers[0] = 0;
  package.vendor[0] = 0;
  package.srcpkgname[0] = 0;

  /* If is a container and hasPrules and pfile_pk != 0, 
     then get the package record if it is a package
   */
  if ((puploadtree->pfile_fk && (IsContainer(puploadtree->ufile_mode))) && hasPrules)
  {
    /* note: for binary packages, srcpkg is the name of the source package.
             srcpkg is null if the pkg is a source package.
             For debian, srcpkg may also be null if the source and binary packages
             have the same name and version.
    */
    snprintf(sql, sizeof(sql), 
           "select pkg_name, version, '' as vendor, source as srcpkg  from pkg_deb where pfile_fk='%d' \
            union all \
            select pkg_name, version, vendor, source_rpm as srcpkg from pkg_rpm where pfile_fk='%d' ",
            puploadtree->pfile_fk, puploadtree->pfile_fk);
    result = PQexec(pgConn, sql);
    if (checkPQresult(result, sql, fcnName, __LINE__)) return 0;
    isPkg = PQntuples(result);

    /* is the file a package?  If not, continue on to the next bucket def. */
    if (isPkg)
    {
      strncpy(package.pkgname, PQgetvalue(result, 0, 0), sizeof(package.pkgname));
      if (package.pkgname[strlen(package.pkgname)-1] == '\n')
        package.pkgname[strlen(package.pkgname)-1] = 0;

      strncpy(package.pkgvers, PQgetvalue(result, 0, 1), sizeof(package.pkgvers));
      if (package.pkgvers[strlen(package.pkgvers)-1] == '\n')
        package.pkgvers[strlen(package.pkgvers)-1] = 0;

      strncpy(package.vendor, PQgetvalue(result, 0, 2), sizeof(package.vendor));
      if (package.vendor[strlen(package.vendor)-1] == '\n')
        package.vendor[strlen(package.vendor)-1] = 0;

      strncpy(package.srcpkgname, PQgetvalue(result, 0, 3), sizeof(package.srcpkgname));
      if (package.srcpkgname[strlen(package.srcpkgname)-1] == '\n')
        package.srcpkgname[strlen(package.srcpkgname)-1] = 0;
    }
    PQclear(result);
  }

   /* getContainerBuckets handles:
      1) items with no pfile (both artifact and non-artifact)
      2) artifacts (both with pfile and without)
      3) containers except packages
   */
  if ((puploadtree->pfile_fk == 0) || (IsArtifact(puploadtree->ufile_mode))
      || (IsContainer(puploadtree->ufile_mode) && !isPkg))
  {
    bucketList = getContainerBuckets(pgConn, bucketDefArray, puploadtree->uploadtree_pk);
    rv = writeBuckets(pgConn, puploadtree->pfile_fk, puploadtree->uploadtree_pk, bucketList, 
                      agent_pk, writeDB, bucketDefArray->nomos_agent_pk);
    if (bucketList) free(bucketList);
  }
  else /* processLeaf handles everything else.  */
  {
    rv = processLeaf(pgConn, bucketDefArray, puploadtree, &package,
                     agent_pk, writeDB, hasPrules);
  }

  return rv;
}


/****************************************************
 processLeaf

 determine which bucket(s) a leaf node is in and write results

 @param PGconn      *pgConn          postgresql connection
 @param pbucketdef_t bucketDefArray  Bucket Definitions
 @param puploadtree_t puploadtree    uploadtree record
 @param ppackage_t    ppackage       package record
 @param int          agent_pk
 @param int          writeDB         True writes to DB, False writes results to stdout
 @param int          hasPrules       

 @return 0=success, else error
****************************************************/
FUNCTION int processLeaf(PGconn *pgConn, pbucketdef_t bucketDefArray, 
                         puploadtree_t puploadtree, ppackage_t ppackage,
                         int agent_pk, int writeDB, int hasPrules)
{
  int rv = 0;
  int *bucketList;

  bucketList = getLeafBuckets(pgConn, bucketDefArray, puploadtree, ppackage, hasPrules);
  if (bucketList) 
  {
    if (debug)
    {
      printf("  buckets for pfile %d:",puploadtree->pfile_fk);
      for (rv=0;bucketList[rv];rv++) printf("%d ",bucketList[rv]);
      printf("\n");
    }
    rv = writeBuckets(pgConn, puploadtree->pfile_fk, puploadtree->uploadtree_pk, 
                      bucketList, agent_pk, 
                      writeDB, bucketDefArray->nomos_agent_pk);
  }
  else
    rv = -1;

  free(bucketList);
  return rv;
}


/****************************************************
 getLeafBuckets

 given a pfile and bucketdef, determine what buckets the pfile is in

 @param PGconn *pgConn  postgresql connection
 @param pbucketdef_t bucketDefArray
 @param puploadtree_t puploadtree
 @param int hasPrules  

 @return array of bucket_pk's, or 0 if error
****************************************************/
FUNCTION int *getLeafBuckets(PGconn *pgConn, pbucketdef_t in_bucketDefArray, 
                             puploadtree_t puploadtree, ppackage_t ppackage,
                             int hasPrules)
{
  char *fcnName = "getLeafBuckets";
  int  *bucket_pk_list = 0;
  int  *bucket_pk_list_start;
  char  filepath[512];
  char  sql[1024];
  PGresult *result;
  PGresult *resultmime;
  int   mimetype;
  int   numLics, licNumb;
  int   numBucketDefs = 0;
  int   match = 0;   // bucket match
  int   foundmatch, foundmatch2; 
  int   *pmatch_array;
  int  **ppmatch_array;
  int  *pfile_rfpks;
  int   rv;
  int   isPkg = 0;
  int   envnum;
  pbucketdef_t bucketDefArray;
  regex_file_t *regex_row;
  char *argv[2];
  char *envp[11];
  char  envbuf[4096];
  char  pkgtype=0;
  pid_t pid;

  if (debug) printf("debug: %s  pfile: %d\n", fcnName, puploadtree->pfile_fk);
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
  
  /*** select all the licenses for uploadtree_pk and children and agent_pk ***/
  bucketDefArray = in_bucketDefArray;
//  snprintf(sql, sizeof(sql), 
//           "select rf_shortname, rf_pk from license_file, license_ref where agent_fk=%d and pfile_fk=%d and rf_fk=rf_pk",
//           bucketDefArray->nomos_agent_pk, puploadtree->pfile_fk);
  snprintf(sql, sizeof(sql), 
      "SELECT distinct(rf_shortname) as rf_shortname, rf_pk \
        from license_ref,license_file,\
             (SELECT distinct(pfile_fk) as PF from uploadtree \
             where upload_fk=%d \
             and uploadtree.lft BETWEEN %d and %d) as SS \
             where PF=pfile_fk and agent_fk=%d and rf_fk=rf_pk",
       puploadtree->upload_fk, puploadtree->lft, puploadtree->rgt,
       bucketDefArray->nomos_agent_pk);

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
  

#ifdef BOBG
printf("bobg: fileName: %s\n", puploadtree->ufile_name);
#endif
  isPkg = (ppackage->pkgname[0]) ? 1 : 0;
  /* loop through all the bucket defs in this pool */
  for (bucketDefArray = in_bucketDefArray; bucketDefArray->bucket_pk; bucketDefArray++)
  {
    /* if this def is restricted to package (applies_to='p'), 
       then skip if this is not a package.
       NOTE DEPENDENCY ON PKG ANALYSIS!
    */
    if (bucketDefArray->applies_to == 'p')
    {
      if (!isPkg) continue;
    }
    else
    {
      /* If this is a container, see if any of its children are in
         this bucket.  If so, then the container is in this bucket.
      */
      if ((!isPkg) && (IsContainer(puploadtree->ufile_mode)))
      {
        rv = childInBucket(pgConn, bucketDefArray, puploadtree);
        if (rv == 1)
        {
          *bucket_pk_list = bucketDefArray->bucket_pk;
          bucket_pk_list++;
          match++;
        }
        else if (rv == -1) return 0; //error
        continue;
      }
    }

#ifdef BOBG
printf("bobg: check bucket_pk: %d\n", bucketDefArray->bucket_pk);
#endif
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
        if (numLics == 0) break;
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
         * Exec'd file returns 0 on true (file is in bucket).
         * When a file is exec'd it can expect the following
         * environment variables:
         * FILENAME: name of file being checked
         * LICENSES: pipe seperated list of licenses for this file.
         * PKGVERS: Package version from pkg header
         * VENDOR: Vendor from pkg header
         * PKGNAME:  simple package name (e.g. "cup", "mozilla-mail", ...) 
                     of file being checked.  Only applies to packages.
         * SRCPKGNAME:  Source package name 
         * UPLOADTREE_PK: uploadtree_pk
         * PFILE_PK: pfile_pk
         * PKGTYPE: 's' if source, 'b' if binary package, '' if not a package
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
        /* use TMPDIR for working directory
         */
        if ((rv = chdir("/tmp")))
        {
          printf("FATAL: exec bucket couldn't cd to /tmp\n");
          exit(1);
        }

				/* set up environment variables */
        envnum = 0;
        argv[0] = strdup(bucketDefArray->dataFilename);
        argv[1] = 0;
        sprintf(envbuf, "FILENAME=%s", puploadtree->ufile_name);
        envp[envnum++] = strdup(envbuf);
        /* create pipe seperated list of licenses */
        strcpy(envbuf, "LICENSES=");
        for (licNumb=0; licNumb < numLics; licNumb++) 
        {
          if (envbuf[9]) strcat(envbuf, "|");
          strcat(envbuf, PQgetvalue(result, licNumb, 0));
        }
        envp[envnum++] = strdup(envbuf);
        sprintf(envbuf, "PKGVERS=%s", ppackage->pkgvers);
        envp[envnum++] = strdup(envbuf); 
        sprintf(envbuf, "VENDOR=%s", ppackage->vendor);
        envp[envnum++] =strdup(envbuf); 
        sprintf(envbuf, "PKGNAME=%s", ppackage->pkgname);
        envp[envnum++] =strdup(envbuf); 
        sprintf(envbuf, "SRCPKGNAME=%s", ppackage->srcpkgname);
        envp[envnum++] =strdup(envbuf); 
        sprintf(envbuf, "UPLOADTREE_PK=%d", puploadtree->uploadtree_pk);
        envp[envnum++] =strdup(envbuf); 
        sprintf(envbuf, "PFILE_PK=%d", puploadtree->pfile_fk);
        envp[envnum++] =strdup(envbuf); 

        /* Only figure out PKGTYPE if this is a pkg
           For Debian packages, check mimetype:
             application/x-debian-package --  binary
             application/x-debian-source  --  source
           For RPM's, 
             if srcpkgname is not null, 
             then this is a binary package
             else this is a source package
         */
        pkgtype = 0;
        if (isPkg)
        {
          if ((strstr(ppackage->srcpkgname,"none")==0)
              || (ppackage->srcpkgname[0]==0)) pkgtype='b';
          else
          {
            snprintf(sql, sizeof(sql), 
                     "select pfile_mimetypefk from pfile where pfile_pk=%d",
                     puploadtree->pfile_fk);
            resultmime = PQexec(pgConn, sql);
            if (checkPQresult(resultmime, sql, fcnName, __LINE__)) return 0;
            mimetype = *(PQgetvalue(resultmime, 0, 0));
            PQclear(resultmime);
            if (mimetype == DEB_SOURCE) pkgtype = 's';
            else if (mimetype == DEB_BINARY) pkgtype = 'b';
            else pkgtype = 's';
          }
        }
        sprintf(envbuf, "PKGTYPE=%c", pkgtype);
        envp[envnum++] =strdup(envbuf); 

        envp[envnum++] = 0;
        execve(filepath, argv, envp);
        printf("FATAL: buckets execve (%s) failed, %s\n", filepath, strerror(errno));
        exit(1);
			}

      /* wait for exit */
			if (waitpid(pid, &rv, 0) < 0) 
      {
        printf("FATAL: waitpid, %s\n", strerror(errno));
        return 0;
			}
			if (WIFSIGNALED(rv)) 
      {
        printf("FATAL: child %d died from signal %d", pid, WTERMSIG(rv));
        return 0;
      }
			else 
      if (WIFSTOPPED(rv)) 
      {
        printf("FATAL: child %d stopped, signal %d", pid, WSTOPSIG(rv));
        return 0;
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
              foundmatch = !regexec(&regex_row->compRegex1, puploadtree->ufile_name, 0, 0, 0);
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
                  foundmatch2 = !regexec(&regex_row->compRegex2, puploadtree->ufile_name, 0, 0, 0);
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
#ifdef BOBG
if (match)
printf("bobg found MATCH\n");
else
printf("bobg found NO Match\n");
#endif
    if (match && bucketDefArray->stopon == 'Y') break;
  }

#ifdef BOBG
  printf("bobg exit GetLeafBuckets()\n");
#endif
  free(pfile_rfpks);
  PQclear(result);
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
  char  sql[1024];
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
  free(children_bucket_pk_list);
  return bucket_pk_list_start;
}


/****************************************************
 childInBucket

 given a container uploadtree_pk and bucketdef, determine 
 if any child is in this bucket.
 
 @param PGconn      *pgConn  postgresql connection
 @param pbucketdef_t bucketDef
 @param puploadtree_t puploadtree

 @return 1 if child is in this bucket
         0 not in bucket
        -1 error
****************************************************/
FUNCTION int childInBucket(PGconn *pgConn, pbucketdef_t bucketDef, puploadtree_t puploadtree)
{
  char *fcnName = "childInBucket";
  char  sql[1024];
  int   lft, rgt, upload_pk, rv;
  PGresult *result;

  if (debug) printf("%s: for uploadtree_pk %d\n",fcnName,puploadtree->uploadtree_pk);

  lft = puploadtree->lft;
  rgt = puploadtree->rgt;
  upload_pk = puploadtree->upload_fk;

  /* Are any children in this bucket? 
     First check bucket_container.  
     If none found, then look in bucket_file.
  */
  snprintf(sql, sizeof(sql), 
           "select uploadtree_pk from uploadtree \
              inner join bucket_container \
                on uploadtree_fk=uploadtree_pk and bucket_fk=%d \
                   and agent_fk=%d and nomosagent_fk=%d \
            where upload_fk=%d and uploadtree.lft BETWEEN %d and %d limit 1",
           bucketDef->bucket_pk, bucketDef->bucket_agent_pk, 
           bucketDef->nomos_agent_pk, upload_pk, lft, rgt);
  result = PQexec(pgConn, sql);
  if (checkPQresult(result, sql, fcnName, __LINE__)) return -1;
  rv = PQntuples(result);
  PQclear(result);
  if (rv) return 1;
  
  /* none found so look in bucket_file for any child in this bucket */
  snprintf(sql, sizeof(sql), 
           "select uploadtree_pk from uploadtree \
              inner join bucket_file \
                on uploadtree.pfile_fk=bucket_file.pfile_fk and bucket_fk=%d \
                   and agent_fk=%d and nomosagent_fk=%d \
            where upload_fk=%d and uploadtree.lft BETWEEN %d and %d limit 1",
           bucketDef->bucket_pk, bucketDef->bucket_agent_pk, 
           bucketDef->nomos_agent_pk, upload_pk, lft, rgt);
  result = PQexec(pgConn, sql);
  if (checkPQresult(result, sql, fcnName, __LINE__)) return -1;
  rv = PQntuples(result);
  PQclear(result);
  if (rv) return 1;

  return 0;
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

 @return 0=success, -1 failure
****************************************************/
FUNCTION int writeBuckets(PGconn *pgConn, int pfile_pk, int uploadtree_pk, 
                          int *bucketList, int agent_pk, int writeDB, int nomosagent_pk)
{
  extern long HBItemsProcessed;
  char     *fcnName = "writeBuckets";
  char      sql[1024];
  PGresult *result;
  int rv = 0;
  if (debug) printf("debug: %s pfile: %d, uploadtree_pk: %d\n", fcnName, pfile_pk, uploadtree_pk);

  if (!writeDB) printf("NOTE: writeDB is FALSE, write buckets for pfile=%d, uploadtree_pk=%d: ", pfile_pk, uploadtree_pk);

  if (bucketList)
  {
    while(*bucketList)
    {
      if (writeDB)
      {
        Heartbeat(++HBItemsProcessed);
        if (pfile_pk)
        {
          snprintf(sql, sizeof(sql), 
                 "insert into bucket_file (bucket_fk, pfile_fk, agent_fk, nomosagent_fk) values(%d,%d,%d,%d)", *bucketList, pfile_pk, agent_pk, nomosagent_pk);
          result = PQexec(pgConn, sql);
          if ((PQresultStatus(result) != PGRES_COMMAND_OK) &&
              (strncmp("23505", PQresultErrorField(result, PG_DIAG_SQLSTATE),5)))
          {
            // ignore duplicate constraint failure (23505)
            printf("ERROR: %s.%s().%d:  Failed to add bucket to bucket_file. %s\n: %s\n",
                    __FILE__,fcnName, __LINE__, 
                    PQresultErrorMessage(result), sql);
            PQclear(result);
            rv = -1;
            break;
          }
          if (debug) printf("%s sql: %s\n",fcnName, sql);
        }
        else
        {
          snprintf(sql, sizeof(sql), 
                 "insert into bucket_container (bucket_fk, uploadtree_fk, agent_fk, nomosagent_fk) \
                  values(%d,%d,%d,%d)", *bucketList, uploadtree_pk, agent_pk, nomosagent_pk);
          result = PQexec(pgConn, sql);
          if ((PQresultStatus(result) != PGRES_COMMAND_OK) &&
              (strncmp("23505", PQresultErrorField(result, PG_DIAG_SQLSTATE),5)))
          {
            // ignore duplicate constraint failure (23505)
            printf("ERROR: %s.%s().%d:  Failed to add bucket to bucket_file. %s\n: %s\n",
                    __FILE__,fcnName, __LINE__, 
                    PQresultErrorMessage(result), sql);
            PQclear(result);
            rv = -1;
            break;
          }
          if (debug) printf("%s sql: %s\n",fcnName, sql);
        }
        PQclear(result);
      }
      else
        printf(" %d", *bucketList);
      bucketList++;
    }
  }

  if (!writeDB) printf("\n");
  return rv;
}


/****************************************************/
int main(int argc, char **argv) 
{
  char *agentDesc = "Bucket agent";
  int cmdopt;
  int verbose = 0;
  int writeDB = 0;
  int ReadFromStdin = 1;
  int head_uploadtree_pk = 0;
  void *DB;   // DB object from agent
  PGconn *pgConn;
  PGresult *topresult;
  PGresult *result;
  char sqlbuf[512];
  char inbuf[64];
  char *inbufp;
  char *Delims = ",= \t\n\r";
  char *token, *saveptr;
  int agent_pk = 0;
  int nomos_agent_pk = 0;
  int bucketpool_pk = 0;
  int ars_pk = 0;
  int readnum = 0;
  int rv;
  int hasPrules;
  char *bucketpool_name;
//  int *bucketList;
  pbucketdef_t bucketDefArray = 0;
  pbucketdef_t tmpbucketDefArray = 0;
  cacheroot_t  cacheroot;
  uploadtree_t  uploadtree;

  extern int AlarmSecs;

  /* Connect to the database */
  DB = DBopen();
  if (!DB) 
  {
    printf("FATAL: Bucket agent unable to connect to database, exiting...\n");
    exit(-1);
  }
  pgConn = DBgetconn(DB);
  writeDB = 1;  /* default is to write to the db */

  /* command line options */
  while ((cmdopt = getopt(argc, argv, "din:p:t:u:v")) != -1) 
  {
    switch (cmdopt) 
    {
      case 'd': /* Debug.  Do not write results to db.
                   Note: license_ref may get written to even if writeDB=0
                   Note: Never use -d unless you are debugging and know
                         what you are doing.  Several functions
                         depend on db updates (like determining bucket
                         of container).
                 */
            writeDB = 0;
            verbose++;
            break;
      case 'i': /* "Initialize" */
            DBclose(DB); /* DB was opened above, now close it and exit */
            exit(0);
      case 'n': /* bucketpool_name  */
            ReadFromStdin = 0;
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
            ReadFromStdin = 0;
            bucketpool_pk = atoi(optarg);
            /* validate bucketpool_pk */
            sprintf(sqlbuf, "select bucketpool_pk from bucketpool where bucketpool_pk=%d and active='Y'", bucketpool_pk);
            bucketpool_pk = validate_pk(pgConn, sqlbuf);
            if (!bucketpool_pk)
              printf("%d is not an active bucketpool_pk.\n", atoi(optarg));
            break;
      case 't': /* uploadtree_pk */
            ReadFromStdin = 0;
            if (uploadtree.upload_fk) break;
            head_uploadtree_pk = atoi(optarg);
            /* validate bucketpool_pk */
            sprintf(sqlbuf, "select uploadtree_pk from uploadtree where uploadtree_pk=%d", head_uploadtree_pk);
            head_uploadtree_pk = validate_pk(pgConn, sqlbuf);
            if (!head_uploadtree_pk)
              printf("%d is not an active uploadtree_pk.\n", atoi(optarg));
            break;
      case 'u': /* upload_pk */
            ReadFromStdin = 0;
            if (!head_uploadtree_pk)
            {
              uploadtree.upload_fk = atoi(optarg);
              /* validate upload_pk  and get uploadtree_pk  */
              sprintf(sqlbuf, "select upload_pk from upload where upload_pk=%d", uploadtree.upload_fk);
              uploadtree.upload_fk = validate_pk(pgConn, sqlbuf);
              if (!uploadtree.upload_fk)
                printf("%d is not an valid upload_pk.\n", atoi(optarg));
              else
              {
                sprintf(sqlbuf, "select uploadtree_pk from uploadtree where upload_fk=%d and parent is null", uploadtree.upload_fk);
                head_uploadtree_pk = validate_pk(pgConn, sqlbuf);
              }
            }
            break;
      case 'v': /* verbose output for debugging  */
            /* FOR NOW this also means debug but does write to db */
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
  if (!bucketpool_pk && !ReadFromStdin)
  {
    printf("FATAL: You must specify an active bucketpool.\n");
    Usage(argv[0]);
    exit(-1);
  }
  if (!head_uploadtree_pk && !ReadFromStdin)
  {
    printf("FATAL: You must specify a valid uploadtree_pk or upload_pk.\n");
    Usage(argv[0]);
    exit(-1);
  }

  /* get agent pk 
   * Note, if GetAgentKey fails, this process will exit.
   */
  agent_pk = GetAgentKey(DB, basename(argv[0]), 0, SVN_REV, agentDesc);

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

  /* set the heartbeat alarm signal */
  if (writeDB)
  {
    signal(SIGALRM, ShowHeartbeat);
    alarm(AlarmSecs);
  }

  /* main processing loop */
  while(++readnum)
  {
    uploadtree.upload_fk = 0;
    if (ReadFromStdin) 
    {
      bucketpool_pk = 0;
      printf("OK\n");
      fflush(stdout);

      /* Read the bucketpool_pk and upload_pk from stdin.
       * Format looks like 'bppk=123, upk=987'
       */
      if (ReadLine(stdin, inbuf, sizeof(inbuf)) < 0) break;
      inbufp = inbuf;
      if (!inbufp) break;

      token = strtok_r(inbufp, Delims, &saveptr);
      while (token && (!uploadtree.upload_fk || !bucketpool_pk))
      {
        if (strcmp(token, "bppk") == 0)
        {
          bucketpool_pk = atoi(strtok_r(NULL, Delims, &saveptr));
        }
        else
        if (strcmp(token, "upk") == 0)
        {
          uploadtree.upload_fk = atoi(strtok_r(NULL, Delims, &saveptr));
        }
        token = strtok_r(NULL, Delims, &saveptr);
      }

      /* From the upload_pk, get the head of the uploadtree, pfile_pk and ufile_name  */
      sprintf(sqlbuf, "select uploadtree_pk, pfile_fk, ufile_name, ufile_mode,lft,rgt from uploadtree \
             where upload_fk='%d' and parent is null limit 1", uploadtree.upload_fk);
      topresult = PQexec(pgConn, sqlbuf);
      if (checkPQresult(topresult, sqlbuf, agentDesc, __LINE__)) return -1;
      if (PQntuples(topresult) == 0) 
      {
        printf("ERROR: %s.%s missing upload_pk %d.\nsql: %s", 
               __FILE__, agentDesc, uploadtree.upload_fk, sqlbuf);
        PQclear(topresult);
        continue;
      }
      head_uploadtree_pk = atol(PQgetvalue(topresult, 0, 0));
      uploadtree.uploadtree_pk = head_uploadtree_pk;
      uploadtree.upload_fk = uploadtree.upload_fk;
      uploadtree.pfile_fk = atol(PQgetvalue(topresult, 0, 1));
      uploadtree.ufile_name = strdup(PQgetvalue(topresult, 0, 2));
      uploadtree.ufile_mode = atoi(PQgetvalue(topresult, 0, 3));
      uploadtree.lft = atoi(PQgetvalue(topresult, 0, 4));
      uploadtree.rgt = atoi(PQgetvalue(topresult, 0, 5));
      PQclear(topresult);
    } /* end ReadFromStdin */
    else
    {
      /* Only one input to process if from command line, so terminate if it's been done */
      if (readnum > 1) break;

      /* not reading from stdin 
       * Get the pfile, and ufile_name for head_uploadtree_pk
       */
      sprintf(sqlbuf, "select pfile_fk, ufile_name, ufile_mode,lft,rgt, upload_fk from uploadtree where uploadtree_pk=%d", head_uploadtree_pk);
      topresult = PQexec(pgConn, sqlbuf);
      if (checkPQresult(topresult, sqlbuf, agentDesc, __LINE__)) return -1;
      if (PQntuples(topresult) == 0) 
      {
        printf("FATAL: %s.%s missing root uploadtree_pk %d\n", 
               __FILE__, agentDesc, head_uploadtree_pk);
        PQclear(topresult);
        continue;
      }
      uploadtree.uploadtree_pk = head_uploadtree_pk;
      uploadtree.pfile_fk = atol(PQgetvalue(topresult, 0, 0));
      uploadtree.ufile_name = strdup(PQgetvalue(topresult, 0, 1));
      uploadtree.ufile_mode = atoi(PQgetvalue(topresult, 0, 2));
      uploadtree.lft = atoi(PQgetvalue(topresult, 0, 3));
      uploadtree.rgt = atoi(PQgetvalue(topresult, 0, 4));
      uploadtree.upload_fk = atoi(PQgetvalue(topresult, 0, 5));
      PQclear(topresult);
    }

    /* Find the most recent nomos data for this upload.  That's what we want to use
         to process the buckets.
     */
    nomos_agent_pk = LatestNomosAgent(pgConn, uploadtree.upload_fk);
    if (nomos_agent_pk == 0)
    {
      printf("WARNING: Bucket agent called on treeitem (%d), but the latest nomos agent hasn't created any license data for this tree.\n",
            head_uploadtree_pk);
      continue;
    }

    /* at this point we know:
     * bucketpool_pk, bucket agent_pk, nomos agent_pk, upload_pk, 
     * pfile_pk, and head_uploadtree_pk (the uploadtree_pk of the head tree to scan)
     */

    /* Has the upload already been processed?  If so, we are done.
       Don't even bother to create a bucket_ars entry.
     */ 
    switch (UploadProcessed(pgConn, agent_pk, nomos_agent_pk, uploadtree.pfile_fk, head_uploadtree_pk, uploadtree.upload_fk, bucketpool_pk)) 
    {
      case 1:  /* upload has already been processed */
        printf("LOG: Duplicate request for bucket agent to process upload_pk: %d, uploadtree_pk: %d, bucketpool_pk: %d, bucket agent_pk: %d, nomos agent_pk: %d, pfile_pk: %d ignored.\n",
             uploadtree.upload_fk, head_uploadtree_pk, bucketpool_pk, agent_pk, nomos_agent_pk, uploadtree.pfile_fk);
        continue;
      case -1: /* SQL error, UploadProcessed() wrote error message */
        continue; 
      case 0:  /* upload has not been processed */
        break;
    }

    /*** Initialize the Bucket Definition List bucketDefArray  ***/
    bucketDefArray = initBuckets(pgConn, bucketpool_pk, &cacheroot);
    if (bucketDefArray == 0)
    {
      printf("FATAL: %s.%d Bucket definition for pool %d could not be initialized.\n",
             __FILE__, __LINE__, bucketpool_pk);
      continue;
    }
    bucketDefArray->nomos_agent_pk = nomos_agent_pk;
    bucketDefArray->bucket_agent_pk = agent_pk;

    /* loop through rules (bucket defs) to see if there are any package only rules */
    hasPrules = 0;
    for (tmpbucketDefArray = bucketDefArray; tmpbucketDefArray; tmpbucketDefArray++)
      if (tmpbucketDefArray->applies_to == 'p')
      {
        hasPrules = 1;
        break;
      }

    /*** END initializing bucketDefArray  ***/

    /*** Initialize DEB_SOURCE and DEB_BINARY  ***/
    sprintf(sqlbuf, "select mimetype_pk from mimetype where mimetype_name='application/x-debian-package'");
    result = PQexec(pgConn, sqlbuf);
    if (checkPQresult(result, sqlbuf, __FILE__, __LINE__)) return -1;
    if (PQntuples(result) == 0)
    {
      printf("FATAL: (%s.%d) Missing application/x-debian-package mimetype.\n",__FILE__,__LINE__);
      return -1;
    }
    DEB_BINARY = atoi(PQgetvalue(result, 0, 0));
    PQclear(result);

    sprintf(sqlbuf, "select mimetype_pk from mimetype where mimetype_name='application/x-debian-source'");
    result = PQexec(pgConn, sqlbuf);
    if (checkPQresult(result, sqlbuf, __FILE__, __LINE__)) return -1;
    if (PQntuples(result) == 0)
    {
      printf("FATAL: (%s.%d) Missing application/x-debian-source mimetype.\n",__FILE__,__LINE__);
      return -1;
    }
    DEB_SOURCE = atoi(PQgetvalue(result, 0, 0));
    PQclear(result);
    /*** END Initialize DEB_SOURCE and DEB_BINARY  ***/

    /*** Record analysis start in bucket_ars, the bucket audit trail. ***/
    snprintf(sqlbuf, sizeof(sqlbuf), 
                "insert into bucket_ars (agent_fk, upload_fk, ars_success, nomosagent_fk, bucketpool_fk) values(%d,%d,'%s',%d,%d)",
                 agent_pk, uploadtree.upload_fk, "false", nomos_agent_pk, bucketpool_pk);
    result = PQexec(pgConn, sqlbuf);
    if (checkPQcommand(result, sqlbuf, __FILE__ ,__LINE__)) return -1;
    PQclear(result);

    /* retrieve the ars_pk of the newly inserted record */
    sprintf(sqlbuf, "select ars_pk from bucket_ars where agent_fk='%d' and upload_fk='%d' and ars_success='%s' and nomosagent_fk='%d' \
                  and bucketpool_fk='%d' and ars_endtime is null \
            order by ars_starttime desc limit 1",
            agent_pk, uploadtree.upload_fk, "false", nomos_agent_pk, bucketpool_pk);
    result = PQexec(pgConn, sqlbuf);
    if (checkPQresult(result, sqlbuf, __FILE__, __LINE__)) return -1;
    if (PQntuples(result) == 0)
    {
      printf("FATAL: (%s.%d) Missing bucket_ars record.\n%s\n",__FILE__,__LINE__,sqlbuf);
      return -1;
    }
    ars_pk = atol(PQgetvalue(result, 0, 0));
    PQclear(result);
    /*** END bucket_ars insert  ***/

    if (debug) printf("%s sql: %s\n",__FILE__, sqlbuf);
  
    /* process the tree for buckets 
       Do this as a single transaction, therefore this agent must be 
       run as a single thread.  This will prevent the scheduler from
       consuming excess time (this is a fast agent), and allow this
       process to update bucket_ars.
     */
    rv = walkTree(pgConn, bucketDefArray, agent_pk, head_uploadtree_pk, writeDB, 0, 
             hasPrules);
    /* if no errors and top level is a container, process the container */
    if ((!rv) && (IsContainer(uploadtree.ufile_mode)))
    {
      rv = processFile(pgConn, bucketDefArray, &uploadtree, agent_pk, writeDB, hasPrules);
    }

    /* Record analysis end in bucket_ars, the bucket audit trail. */
    if (ars_pk)
    {
      if (rv)
        snprintf(sqlbuf, sizeof(sqlbuf), 
                "update bucket_ars set ars_endtime=now(), ars_success=false where ars_pk='%d'",
                ars_pk);
      else
        snprintf(sqlbuf, sizeof(sqlbuf), 
                "update bucket_ars set ars_endtime=now(), ars_success=true where ars_pk='%d'",
                ars_pk);

      result = PQexec(pgConn, sqlbuf);
      if (checkPQcommand(result, sqlbuf, __FILE__ ,__LINE__)) return -1;
      PQclear(result);
      if (debug) printf("%s sqlbuf: %s\n",__FILE__, sqlbuf);
    }
  }  /* end of main processing loop */

  lrcache_free(&cacheroot);
  DBclose(DB);
  return (0);
}
