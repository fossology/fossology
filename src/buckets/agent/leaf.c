/***************************************************************
 Copyright (C) 2010-2013 Hewlett-Packard Development Company, L.P.

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
#include "buckets.h"

extern int debug;
extern int DEB_SOURCE;
extern int DEB_BINARY;


/**
 * \brief determine which bucket(s) a leaf node is in and write results
 * 
 * \param PGconn      $pgConn          postgresql connection
 * \param pbucketdef_t $bucketDefArray  Bucket Definitions
 * \param puploadtree_t $puploadtree    uploadtree record
 * \param ppackage_t    $ppackage       package record
 * \param int          $agent_pk
 * \param int          $hasPrules       
 *
 * \return 0=success, else error
 */
FUNCTION int processLeaf(PGconn *pgConn, pbucketdef_t bucketDefArray, 
                         puploadtree_t puploadtree, ppackage_t ppackage,
                         int agent_pk, int hasPrules)
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
                      bucketDefArray->nomos_agent_pk, bucketDefArray->bucketpool_pk);
  }
  else
    rv = -1;

  free(bucketList);
  return rv;
}


/**
 * \brief Determine what buckets the pfile is in
 *
 * \param PGconn $pgConn  postgresql connection
 * \param pbucketdef_t $bucketDefArray
 * \param puploadtree_t $puploadtree
 * \param int $hasPrules  
 *
 * \return array of bucket_pk's, or 0 if error
 */
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
  if (fo_checkPQresult(pgConn, result, sql, fcnName, __LINE__)) return 0;
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
                 PROJECTSTATEDIR, bucketDefArray->bucketpool_pk, bucketDefArray->dataFilename);
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
            if (fo_checkPQresult(pgConn, resultmime, sql, fcnName, __LINE__)) return 0;
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
printf("bobg match: %d\n", match);
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
