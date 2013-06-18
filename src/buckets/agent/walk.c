/***************************************************************
 Copyright (C) 2010-2012 Hewlett-Packard Development Company, L.P.

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

/**
 * \brief This function does a recursive depth first walk through a file tree (uploadtree).
 *
 * \param PGconn $pgConn   The database connection object.
 * \param pbucketdef_t    $bucketDefArray  Bucket Definitions
 * \param int  $agent_pk   The agent_pk
 * \param int  $uploadtree_pk
 * \param int  $skipProcessedCheck true if it is ok to skip the initial 
 *                       processed() call.  The call is unnecessary during 
 *                       recursion and it's an DB query, so best to avoid
 *                       doing an unnecessary call.
 * \param int  $hasPrules  1=bucketDefArray contains at least one rule that only 
 *                       apply to packages.  0=No package rules.
 *
 * \return 0 on OK, -1 on failure.
 *
 * Errors are written to stdout.
 */
FUNCTION int walkTree(PGconn *pgConn, pbucketdef_t bucketDefArray, int agent_pk, 
                      int  uploadtree_pk, int skipProcessedCheck,
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
  if (fo_checkPQresult(pgConn, origresult, sqlbuf, fcnName, __LINE__)) return -1;
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

  if (!skipProcessedCheck)
  //  if (processed(pgConn, agent_pk, uploadtree.pfile_fk, uploadtree.uploadtree_pk, bucketpool_pk)) return 0;

  /* If this is a leaf node, process it
     (i.e. determine what bucket it belongs in).
     This will only be executed in the case where the unpacked upload
     is itself a single file.
   */
  if (uploadtree.rgt == (uploadtree.lft+1))
  {
    return  processFile(pgConn, bucketDefArray, &uploadtree, agent_pk, hasPrules);
  }

  /* Since uploadtree_pk isn't a leaf, find its immediate children and 
     process (if child is leaf) or recurse on container.
     Packages need both processing (check bucket_def rules) and recursion.
   */
  sprintf(sqlbuf, "select uploadtree_pk,pfile_fk, lft, rgt, ufile_mode, ufile_name from uploadtree where parent=%d", 
          uploadtree_pk);
  result = PQexec(pgConn, sqlbuf);
  if (fo_checkPQresult(pgConn, result, sqlbuf, fcnName, __LINE__)) return -1;
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
    if (processed(pgConn, agent_pk, childuploadtree.pfile_fk, childuploadtree.uploadtree_pk, bucketpool_pk, 0)) continue;

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
                    agent_pk, hasPrules);
      free(childuploadtree.ufile_name);
      continue;
    }

    /* not a leaf so recurse */
    rv = walkTree(pgConn, bucketDefArray, agent_pk, childuploadtree.uploadtree_pk, 
                  1, hasPrules);
    if (rv) 
    {
      free(childuploadtree.ufile_name);
      return rv;
    }

    /* done processing children, now processes (find buckets) for the container */
    processFile(pgConn, bucketDefArray, &childuploadtree, agent_pk, 
                hasPrules);

    free(childuploadtree.ufile_name);
  } // end of child processing
  

  PQclear(result);
  PQclear(origresult);
  return rv;
} /* walkTree */


/**
 * \brief Process a file.  
 *
 * The file might be a single file, a container,
 * an artifact, a package, ..., in other words, an uploadtree record. \n
 * Need to process container artifacts as a regular directory so that buckets cascade
 * up without interruption. \n
 * There is one small caveat.  If the container is a package AND
 * the bucketDefArray has rules that apply to packages (applies_to='p')
 * THEN process the package as both a leaf since the bucket pool has its own 
 * rules for packages, and as a container (the pkg is in each of its childrens
 * buckets).
 *
 * \param PGconn $pgConn   The database connection object.
 * \param pbucketdef_t    $bucketDefArray  Bucket Definitions
 * \param pupuploadtree_t $puoloadtree Uploadtree record
 * \param int  $agent_pk   The agent_pk
 * \param int  $hasPrules  1=bucketDefArray contains at least one rule that only 
 *                       apply to packages.  0=No package rules.
 *
 * \return 0 on OK, -1 on failure.
 *
 * Errors are written to stdout.
 */
FUNCTION int processFile(PGconn *pgConn, pbucketdef_t bucketDefArray, 
                      puploadtree_t puploadtree, int agent_pk, int hasPrules)
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
     then get the package record (if it is a package).
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
    if (fo_checkPQresult(pgConn, result, sql, fcnName, __LINE__)) return 0;
    isPkg = PQntuples(result);

    /* is the file a package?  
       Then replace any terminal newline with a null in the package name.
       If not, continue on to the next bucket def. 
     */
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

  if (debug) printf("\nFile name: %s\n", puploadtree->ufile_name);

   /* getContainerBuckets() handles:
      1) items with no pfile
      2) artifacts (both with pfile and without)
      3) all containers
   */
  if ((puploadtree->pfile_fk == 0) || (IsArtifact(puploadtree->ufile_mode))
      || (IsContainer(puploadtree->ufile_mode)))
  {
    bucketList = getContainerBuckets(pgConn, bucketDefArray, puploadtree->uploadtree_pk);
    rv = writeBuckets(pgConn, puploadtree->pfile_fk, puploadtree->uploadtree_pk, bucketList, 
                      agent_pk, bucketDefArray->nomos_agent_pk, bucketDefArray->bucketpool_pk);
    if (bucketList) free(bucketList);

    /* process packages because they are treated as leafs and as containers */
    rv = processLeaf(pgConn, bucketDefArray, puploadtree, &package,
                     agent_pk, hasPrules);
  }
  else /* processLeaf handles everything else.  */
  {
    rv = processLeaf(pgConn, bucketDefArray, puploadtree, &package,
                     agent_pk, hasPrules);
  }

  return rv;
}
