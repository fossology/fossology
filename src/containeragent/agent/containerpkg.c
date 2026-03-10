/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file containerpkg.c
 * \brief Container installed-package extraction — containeragent extension.
 *
 * Two-phase package discovery:
 *
 * Phase 1 — uploadtree scan:
 *   Query uploadtree for known package database files (dpkg/status,
 *   lib/apk/db/installed, var/lib/rpm/rpmdb.sqlite).  Works when ununpack
 *   recursed into the layer tarball.
 *
 * Phase 2 — direct blob extraction (fallback):
 *   Docker/OCI layer blobs are content-hash-named files with no .tar
 *   extension.  Some ununpack versions skip recursion for such files.
 *   When Phase 1 finds nothing, we look up each layer blob in uploadtree,
 *   get its FOSSology repo path, then scan the tar directly in C without
 *   forking an external process (handles plain tar, .tar.gz, .tar.bz2,
 *   .tar.xz transparently via libarchive).
 */

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdint.h>
#include <errno.h>
#include <unistd.h>
#include <limits.h>
#include <sys/stat.h>
#ifdef HAVE_LIBARCHIVE
#  include <archive.h>
#  include <archive_entry.h>
#endif
#include "containerpkg.h"

/* =========================================================================
 * Known package databases
 * ========================================================================= */

static const struct {
  const char     *filename;   /* basename to search for in uploadtree */
  const char     *parentDir;  /* expected parent directory name (or NULL) */
  const char     *tarPath;    /* full path inside the layer tar */
  FossPkgManager  manager;
} KnownDbs[] = {
  { "status",       "dpkg", "var/lib/dpkg/status",      FOSSPKG_MGR_DPKG },
  { "installed",    "db",   "lib/apk/db/installed",     FOSSPKG_MGR_APK  },
  { "rpmdb.sqlite", "rpm",  "var/lib/rpm/rpmdb.sqlite",  FOSSPKG_MGR_RPM  },
  { "Packages",     "rpm",  "var/lib/rpm/Packages",      FOSSPKG_MGR_RPM  },
  { NULL, NULL, NULL, FOSSPKG_MGR_UNKNOWN }
};

/* =========================================================================
 * Phase 1: uploadtree scan
 * ========================================================================= */

static void FindPackageDatabases(long           upload_pk,
                                  const char    *ut,
                                  unsigned long  rootLft,
                                  unsigned long  rootRgt,
                                  PkgDbRef      *refs,
                                  int           *nRefs)
{
  char      SQL[MAXCMD];
  PGresult *result;
  *nRefs = 0;

  for (int i = 0; KnownDbs[i].filename && *nRefs < MAX_PKGDBS; i++) {
    if (KnownDbs[i].parentDir) {
      snprintf(SQL, sizeof(SQL),
        "SELECT child.uploadtree_pk,"
        "       child.pfile_fk,"
        "       pfile_sha1||'.'||pfile_md5||'.'||pfile_size AS hash"
        " FROM %s child"
        " INNER JOIN %s parent ON parent.uploadtree_pk=child.parent"
        " INNER JOIN pfile ON pfile_pk=child.pfile_fk"
        " WHERE child.upload_fk=%ld"
        "   AND child.lft>%lu AND child.rgt<%lu"
        "   AND child.ufile_name='%s'"
        "   AND parent.ufile_name='%s'"
        " LIMIT %d",
        ut, ut, upload_pk, rootLft, rootRgt,
        KnownDbs[i].filename, KnownDbs[i].parentDir,
        MAX_PKGDBS - *nRefs);
    } else {
      snprintf(SQL, sizeof(SQL),
        "SELECT child.uploadtree_pk,"
        "       child.pfile_fk,"
        "       pfile_sha1||'.'||pfile_md5||'.'||pfile_size AS hash"
        " FROM %s child"
        " INNER JOIN pfile ON pfile_pk=child.pfile_fk"
        " WHERE child.upload_fk=%ld"
        "   AND child.lft>%lu AND child.rgt<%lu"
        "   AND child.ufile_name='%s'"
        " LIMIT %d",
        ut, upload_pk, rootLft, rootRgt,
        KnownDbs[i].filename,
        MAX_PKGDBS - *nRefs);
    }

    result = PQexec(db_conn, SQL);
    if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) {
      continue;  /* fo_checkPQresult already called PQclear on error */
    }
    int rows = PQntuples(result);
    for (int r = 0; r < rows && *nRefs < MAX_PKGDBS; r++) {
      char *repoPath = fo_RepMkPath("files", PQgetvalue(result, r, 2));
      if (!repoPath) continue;
      PkgDbRef *ref      = &refs[*nRefs];
      ref->repoPath      = repoPath;  /* take ownership — fo_RepMkPath returns heap */
      ref->uploadtreePk  = atol(PQgetvalue(result, r, 0));
      ref->pfileFk       = atol(PQgetvalue(result, r, 1));
      ref->manager       = KnownDbs[i].manager;
      ref->layerIndex    = -1;
      ref->isTempFile    = 0;
      (*nRefs)++;
    }
    PQclear(result);
  }
}

static int ResolveLayerIndex(long upload_pk, const char *ut,
                              long uploadtreePk, long pfileFk,
                              struct containerpkginfo *pi)
{
  /* anchor CTE by uploadtreePk (not pfileFk) — same content can appear in multiple layers */
  if (!pi || !pi->layers || pi->layerCount <= 0)
    return -1;

  char SQL[MAXCMD * 2];  /* larger — IN clause can be wide */

  /* for each layer: derive uploadtree identifier — legacy Docker uses uuid dir, OCI uses hash basename */
  char layerIdent[MAX_LAYERS][MAXLENGTH];
  int  nIdents = 0;
  for (int i = 0; i < pi->layerCount && i < MAX_LAYERS; i++) {
    layerIdent[i][0] = '\0';
    const char *bn = pi->layers[i].blobName;
    if (!bn || !bn[0]) continue;

    const char *ident;
    size_t bnLen  = strlen(bn);
    const char *sfx = "/layer.tar";
    size_t sfxLen = strlen(sfx);
    if (bnLen > sfxLen && strcmp(bn + bnLen - sfxLen, sfx) == 0) {
      /* legacy Docker: use uuid dir component, not "layer.tar" (same for all layers) */
      size_t dirLen = bnLen - sfxLen;
      const char *lastSlash = NULL;
      for (size_t k = 0; k < dirLen; k++)
        if (bn[k] == '/') lastSlash = bn + k;
      if (lastSlash)
        ident = lastSlash + 1;
      else
        ident = bn;
      size_t identLen = (bnLen - sfxLen) - (size_t)(ident - bn);
      if (identLen >= sizeof(layerIdent[i]))
        identLen = sizeof(layerIdent[i]) - 1;
      memcpy(layerIdent[i], ident, identLen);
      layerIdent[i][identLen] = '\0';
    } else {
      /* OCI: use last path component (the hash) */
      const char *slash = strrchr(bn, '/');
      ident = slash ? slash + 1 : bn;
      strncpy(layerIdent[i], ident, sizeof(layerIdent[i]) - 1);
      layerIdent[i][sizeof(layerIdent[i]) - 1] = '\0';
    }
    if (layerIdent[i][0]) nIdents++;
  }

  if (nIdents == 0) return -1;

  char inList[MAX_LAYERS * (MAXLENGTH + 4) + 8];
  int  inLen = 0;
  inList[inLen++] = '(';
  int first = 1;
  for (int i = 0; i < pi->layerCount && i < MAX_LAYERS; i++) {
    if (!layerIdent[i][0]) continue;
    if (!first) { inList[inLen++] = ','; }
    first = 0;
    inLen += snprintf(inList + inLen, sizeof(inList) - inLen - 2,
                      "'%s'", layerIdent[i]);
  }
  inList[inLen++] = ')';
  inList[inLen]   = '\0';

  /* walk ancestor chain — anchor by uploadtreePk if available, fall back to pfileFk */
  if (uploadtreePk > 0) {
    snprintf(SQL, sizeof(SQL),
      "WITH RECURSIVE anc AS ("
      "  SELECT t.uploadtree_pk, t.parent, t.ufile_name"
      "  FROM %s t"
      "  WHERE t.uploadtree_pk = %ld"
      "  UNION ALL"
      "  SELECT p.uploadtree_pk, p.parent, p.ufile_name"
      "  FROM %s p JOIN anc a ON p.uploadtree_pk = a.parent"
      "  WHERE a.parent IS NOT NULL"
      ")"
      "SELECT ufile_name FROM anc"
      " WHERE ufile_name IN %s"
      " LIMIT 1",
      ut, uploadtreePk, ut, inList);
  } else {
    snprintf(SQL, sizeof(SQL),
      "WITH RECURSIVE anc AS ("
      "  SELECT t.uploadtree_pk, t.parent, t.ufile_name"
      "  FROM %s t"
      "  WHERE t.pfile_fk = %ld AND t.upload_fk = %ld"
      "  UNION ALL"
      "  SELECT p.uploadtree_pk, p.parent, p.ufile_name"
      "  FROM %s p JOIN anc a ON p.uploadtree_pk = a.parent"
      "  WHERE a.parent IS NOT NULL"
      ")"
      "SELECT ufile_name FROM anc"
      " WHERE ufile_name IN %s"
      " LIMIT 1",
      ut, pfileFk, upload_pk, ut, inList);
  }

  PGresult *result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) return -1;
  if (PQntuples(result) == 0) { PQclear(result); return -1; }

  char matchedName[MAXLENGTH];
  strncpy(matchedName, PQgetvalue(result, 0, 0), sizeof(matchedName) - 1);
  matchedName[sizeof(matchedName) - 1] = '\0';
  PQclear(result);

  for (int i = 0; i < pi->layerCount && i < MAX_LAYERS; i++) {
    if (strcmp(layerIdent[i], matchedName) == 0)
      return i;
  }

  return -1;  /* should never reach here */
}

/* =========================================================================
 * Phase 2: direct blob extraction via libarchive
 * ========================================================================= */

/**
 * \brief Look up blobName in uploadtree and return its FOSSology repo path.
 * Caller must free the returned string.
 */
static char *FindBlobRepoPath(long upload_pk, const char *ut,
                               const char *blobName)
{
  if (!blobName || !blobName[0]) return NULL;

  char *esafe = PQescapeLiteral(db_conn, blobName, strlen(blobName));
  if (!esafe) return NULL;

  char SQL[MAXCMD];
  snprintf(SQL, sizeof(SQL),
    "SELECT pfile_sha1||'.'||pfile_md5||'.'||pfile_size AS hash"
    " FROM %s"
    " INNER JOIN pfile ON pfile_pk=pfile_fk"
    " WHERE upload_fk=%ld AND ufile_name=%s"
    " AND pfile_fk IS NOT NULL"
    " LIMIT 1",
    ut, upload_pk, esafe);
  PQfreemem(esafe);

  PGresult *result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) { return NULL; }
  if (PQntuples(result) == 0) { PQclear(result); return NULL; }

  char *repoPath = fo_RepMkPath("files", PQgetvalue(result, 0, 0));
  PQclear(result);
  return repoPath;
}

/**
 * \brief Extract one member from a tar archive to a temp file.
 *
 * When built with libarchive (HAVE_LIBARCHIVE defined), uses the library
 * directly in-process — handles plain tar, tar.gz, tar.bz2, tar.xz, and
 * matches paths with or without a leading "./" prefix.
 *
 * When libarchive headers are not available at build time, falls back to
 * spawning `tar -xOf` via system().  This is less reliable in restricted
 * agent environments but avoids a hard build dependency.
 *
 * \param tarPath  Absolute path to the archive in the FOSSology repo.
 * \param member   Path of the member to extract (e.g. "lib/apk/db/installed").
 * \param tmpFile  Output buffer (PATH_MAX bytes). Filled with temp file path.
 *                 Caller must unlink() on success.
 * \return 0 on success, -1 on failure.
 */
static int ExtractFromArchive(const char *tarPath, const char *member,
                               char *tmpFile)
{
  snprintf(tmpFile, PATH_MAX, "/tmp/containerpkg_XXXXXX");
  int fd = mkstemp(tmpFile);
  if (fd < 0) {
    LOG_WARNING("containerpkg: mkstemp failed: %s\n", strerror(errno));
    return -1;
  }

#ifdef HAVE_LIBARCHIVE
  struct archive       *a   = archive_read_new();
  struct archive_entry *ent = NULL;
  int rc = -1;

  archive_read_support_filter_all(a);
  archive_read_support_format_tar(a);
  archive_read_support_format_gnutar(a);

  if (archive_read_open_filename(a, tarPath, 65536) != ARCHIVE_OK) {
    LOG_WARNING("containerpkg: cannot open archive '%s': %s\n",
                tarPath, archive_error_string(a));
    struct stat _st;
    if (stat(tarPath, &_st) != 0) {
      LOG_WARNING("containerpkg:   file does not exist: %s\n", strerror(errno));
    } else {
      LOG_NOTICE("containerpkg:   file exists, size=%ld, but libarchive cannot open it\n",
                 (long)_st.st_size);
    }
    close(fd); unlink(tmpFile); tmpFile[0] = '\0';
    archive_read_free(a);
    return -1;
  }

  /* match paths with or without leading "./" */
  char memberNoDot[PATH_MAX], memberDot[PATH_MAX];
  if (member[0] == '.' && member[1] == '/') {
    strncpy(memberNoDot, member + 2, sizeof(memberNoDot) - 1);
    memberNoDot[sizeof(memberNoDot)-1] = '\0';
    strncpy(memberDot, member, sizeof(memberDot) - 1);
    memberDot[sizeof(memberDot)-1] = '\0';
  } else {
    strncpy(memberNoDot, member, sizeof(memberNoDot) - 1);
    memberNoDot[sizeof(memberNoDot)-1] = '\0';
    snprintf(memberDot, sizeof(memberDot), "./%s", member);
  }

  while (archive_read_next_header(a, &ent) == ARCHIVE_OK) {
    const char *entPath = archive_entry_pathname(ent);
    if (!entPath ||
        (strcmp(entPath, memberNoDot) != 0 &&
         strcmp(entPath, memberDot)   != 0)) {
      archive_read_data_skip(a);
      continue;
    }

    const void *buf; size_t size; la_int64_t offset;
    int wr_ok = 1;
    while (archive_read_data_block(a, &buf, &size, &offset) == ARCHIVE_OK) {
      if (size == 0) continue;
      if (write(fd, buf, size) != (ssize_t)size) {
        LOG_WARNING("containerpkg: write to temp file failed: %s\n",
                    strerror(errno));
        wr_ok = 0; break;
      }
    }
    rc = wr_ok ? 0 : -1;
    break;
  }

  if (rc != 0) {
    close(fd);
    unlink(tmpFile);
    archive_read_free(a);
    tmpFile[0] = '\0';
    return rc;
  }

  close(fd);
  archive_read_free(a);

#else
  /* system(tar) fallback */
  close(fd);
  char cmd[MAXCMD * 2];
  const char *tarBins[] = { "tar", "/bin/tar", "/usr/bin/tar", NULL };
  int rc = -1;
  for (int _ti = 0; tarBins[_ti] && rc != 0; _ti++) {
    snprintf(cmd, sizeof(cmd),
      "%s -xOf '%s' '%s' > '%s' 2>/dev/null",
      tarBins[_ti], tarPath, member, tmpFile);
    int sysrc = system(cmd);
    if (sysrc == 0) { rc = 0; break; }
    LOG_NOTICE("containerpkg: %s failed (rc=%d) for '%s'\n",
               tarBins[_ti], sysrc, member);
  }
  if (rc != 0)
    LOG_WARNING("containerpkg: all tar attempts failed for member '%s' in '%s'\n",
                member, tarPath);
#endif /* HAVE_LIBARCHIVE */

  if (rc != 0) {
    unlink(tmpFile); tmpFile[0] = '\0';
    return -1;
  }

  /* verify non-empty */
  struct stat st;
  if (stat(tmpFile, &st) != 0 || st.st_size == 0) {
    unlink(tmpFile); tmpFile[0] = '\0';
    return -1;
  }
  return 0;
}

/**
 * \brief Scan each layer blob directly for package databases (Phase 2 fallback).
 */
static void FindPackageDatabasesInBlobs(long                     upload_pk,
                                         const char              *ut,
                                         struct containerpkginfo *pi,
                                         PkgDbRef                *refs,
                                         int                     *nRefs)
{
  /* do NOT reset *nRefs — appends to Phase 1 results */
  if (!pi || !pi->layers) return;

  for (int li = 0; li < pi->layerCount && *nRefs < MAX_PKGDBS; li++) {
    const char *blobName = pi->layers[li].blobName;
    if (!blobName || !blobName[0]) continue;

    char *blobRepo = FindBlobRepoPath(upload_pk, ut, blobName);
    if (!blobRepo) {
      LOG_NOTICE("containerpkg: layer[%d] blob '%s' NOT FOUND in uploadtree\n",
                 li, blobName);
      continue;
    }

    LOG_NOTICE("containerpkg: layer[%d] blob repo path: %s\n", li, blobRepo);

    for (int di = 0; KnownDbs[di].filename && *nRefs < MAX_PKGDBS; di++) {
      char tmpFile[PATH_MAX] = "";
      if (ExtractFromArchive(blobRepo, KnownDbs[di].tarPath, tmpFile) != 0)
        continue;  /* not found in this layer — normal for non-base layers */

      LOG_NOTICE("containerpkg: layer[%d] found '%s' (manager=%d)\n",
                 li, KnownDbs[di].tarPath, (int)KnownDbs[di].manager);

      PkgDbRef *ref = &refs[*nRefs];
      ref->repoPath   = strdup(tmpFile);
      if (!ref->repoPath) {
        LOG_WARNING("containerpkg: strdup failed for tmpFile path\n");
        unlink(tmpFile);
        continue;
      }
      ref->pfileFk    = 0;
      ref->manager    = KnownDbs[di].manager;
      ref->layerIndex = li;
      ref->isTempFile = 1;
      (*nRefs)++;
    }
    free(blobRepo);
  }
}

/* =========================================================================
 * RecordInstalledPackages
 * ========================================================================= */
int RecordInstalledPackages(FossPkgList *list, long upload_pk,
                             int pkg_fk, int layerIndex, const char *manager)
{
  if (!list || list->count == 0) return 0;

  PGresult *result;
  int totalInserted = 0;

  result = PQexec(db_conn, "BEGIN;");
  if (fo_checkPQcommand(db_conn, result, "BEGIN", __FILE__, __LINE__)) return -1;
  PQclear(result);

  for (int i = 0; i < list->count; i++) {
    FossPkgInfo *pkg = list->pkgs[i];
    if (!pkg || !pkg->name[0]) continue;

#define ESC(s) PQescapeLiteral(db_conn, (s), strlen(s))
    char *eName  = ESC(pkg->name);
    char *eVer   = ESC(pkg->version);
    char *eArch  = ESC(pkg->arch);
    char *eSrc   = ESC(pkg->source);
    char *eSum   = ESC(pkg->summary);
    char *eMaint = ESC(pkg->maintainer);
    char *eLic   = ESC(pkg->license);
    char *eMgr   = ESC(manager);
#undef ESC

    /* two-pass heap alloc: measure then format */
    static const char *insertTmpl =
      "INSERT INTO pkg_container_installed"
      " (upload_fk,pkg_fk,pkg_manager,pkg_name,pkg_version,"
      "  pkg_arch,pkg_source,pkg_summary,pkg_maintainer,"
      "  pkg_license,layer_index)"
      " VALUES (%ld,%d,%s,%s,%s,%s,%s,%s,%s,%s,%d)"
      " RETURNING inst_pk;";

    int needed = snprintf(NULL, 0, insertTmpl,
                          upload_pk, pkg_fk,
                          eMgr, eName, eVer, eArch, eSrc, eSum, eMaint, eLic,
                          layerIndex);
    char *dynSQL = (needed > 0) ? malloc((size_t)needed + 1) : NULL;
    if (!dynSQL) {
      LOG_ERROR("containerpkg: malloc failed for INSERT SQL (%d bytes)\n",
                needed + 1);
      PQfreemem(eName);  PQfreemem(eVer);  PQfreemem(eArch);
      PQfreemem(eSrc);   PQfreemem(eSum);  PQfreemem(eMaint);
      PQfreemem(eLic);   PQfreemem(eMgr);
      { PGresult *rb = PQexec(db_conn, "ROLLBACK;"); PQclear(rb); }
      return -1;
    }
    snprintf(dynSQL, (size_t)needed + 1, insertTmpl,
             upload_pk, pkg_fk,
             eMgr, eName, eVer, eArch, eSrc, eSum, eMaint, eLic,
             layerIndex);

    PQfreemem(eName);  PQfreemem(eVer);  PQfreemem(eArch);
    PQfreemem(eSrc);   PQfreemem(eSum);  PQfreemem(eMaint);
    PQfreemem(eLic);   PQfreemem(eMgr);

    result = PQexec(db_conn, dynSQL);
    free(dynSQL);
    /* INSERT...RETURNING -> PGRES_TUPLES_OK: use fo_checkPQresult */
    if (fo_checkPQresult(db_conn, result, "(pkg insert)", __FILE__, __LINE__)) {
      { PGresult *rb = PQexec(db_conn, "ROLLBACK;"); PQclear(rb); }
      return -1;
    }
    int instPk = (PQntuples(result) > 0) ? atoi(PQgetvalue(result, 0, 0)) : 0;
    PQclear(result);
    totalInserted++;

    for (int d = 0; d < pkg->requireCount; d++) {
      if (!pkg->requires[d] || !pkg->requires[d][0]) continue;
      char *eDep = PQescapeLiteral(db_conn,
                                    pkg->requires[d],
                                    strlen(pkg->requires[d]));
      static const char *depTmpl =
        "INSERT INTO pkg_container_inst_dep (inst_fk,dep_name) VALUES (%d,%s);";
      int depNeeded = snprintf(NULL, 0, depTmpl, instPk, eDep);
      char *depSQL  = (depNeeded > 0) ? malloc((size_t)depNeeded + 1) : NULL;
      if (!depSQL) {
        PQfreemem(eDep);
        { PGresult *rb = PQexec(db_conn, "ROLLBACK;"); PQclear(rb); }
        return -1;
      }
      snprintf(depSQL, (size_t)depNeeded + 1, depTmpl, instPk, eDep);
      PQfreemem(eDep);

      result = PQexec(db_conn, depSQL);
      free(depSQL);
      if (fo_checkPQcommand(db_conn, result, "(dep insert)", __FILE__, __LINE__)) {
        { PGresult *rb = PQexec(db_conn, "ROLLBACK;"); PQclear(rb); }
        return -1;
      }
      PQclear(result);
    }
  }

  result = PQexec(db_conn, "COMMIT;");
  if (fo_checkPQcommand(db_conn, result, "COMMIT", __FILE__, __LINE__)) {
    return -1;
  }
  PQclear(result);
  return totalInserted;
}

/* =========================================================================
 * ScanContainerPackages — main entry point
 * ========================================================================= */
int ScanContainerPackages(long upload_pk, int pkg_fk, struct containerpkginfo *pi)
{
  LOG_NOTICE("containerpkg: ScanContainerPackages called: upload_pk=%ld pkg_fk=%d "
             "pi=%s layerCount=%d\n",
             upload_pk, pkg_fk, pi ? "ok" : "NULL",
             (pi && pi->layers) ? pi->layerCount : -1);

  char *ut = GetUploadtreeTableName(db_conn, upload_pk);
  if (!ut) ut = g_strdup("uploadtree_a");
  LOG_NOTICE("containerpkg: using uploadtree table: %s\n", ut);

  char SQL[MAXCMD];
  snprintf(SQL, sizeof(SQL),
    "SELECT lft,rgt FROM %s WHERE upload_fk=%ld AND parent IS NULL LIMIT 1",
    ut, upload_pk);
  PGresult *result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) {
    g_free(ut); return -1;  /* fo_checkPQresult already called PQclear on error */
  }
  if (PQntuples(result) == 0) {
    PQclear(result); g_free(ut); return -1;
  }
  unsigned long rootLft = strtoul(PQgetvalue(result, 0, 0), NULL, 10);
  unsigned long rootRgt = strtoul(PQgetvalue(result, 0, 1), NULL, 10);
  PQclear(result);

  PkgDbRef refs[MAX_PKGDBS];
  memset(refs, 0, sizeof(refs)); /* zero-init so all repoPath pointers start NULL */
  int      nRefs = 0;

  /* Phase 1: uploadtree scan */
  FindPackageDatabases(upload_pk, ut, rootLft, rootRgt, refs, &nRefs);

  LOG_NOTICE("containerpkg: Phase 1 (uploadtree scan) found %d package db(s) "
             "for upload %ld\n", nRefs, upload_pk);

  /* Phase 2: direct blob extraction — fallback when ununpack skipped extensionless blobs */
  if (nRefs == 0) {
    if (pi && pi->layers && pi->layerCount > 0) {
      LOG_NOTICE("containerpkg: Phase 1 empty — trying direct blob extraction "
                 "(layerCount=%d)\n", pi->layerCount);
      for (int _li = 0; _li < pi->layerCount; _li++)
        LOG_NOTICE("containerpkg:   layer[%d].blobName='%s'\n",
                   _li, pi->layers[_li].blobName[0] ? pi->layers[_li].blobName : "(empty)");
      FindPackageDatabasesInBlobs(upload_pk, ut, pi, refs, &nRefs);
      LOG_NOTICE("containerpkg: Phase 2 (blob extraction) found %d package db(s)\n",
                 nRefs);
    } else {
      LOG_NOTICE("containerpkg: Phase 1 empty and pi/layers unavailable "
                 "(pi=%s, layers=%s, layerCount=%d)\n",
                 pi ? "ok" : "NULL",
                 (pi && pi->layers) ? "ok" : "NULL",
                 pi ? pi->layerCount : -1);
    }
  }

  /* resolve layer indices now — needed for Phase 3 coverage bitset */
  for (int r = 0; r < nRefs; r++) {
    if (!refs[r].isTempFile && refs[r].pfileFk > 0 && refs[r].layerIndex < 0)
      refs[r].layerIndex = ResolveLayerIndex(upload_pk, ut,
                                              refs[r].uploadtreePk,
                                              refs[r].pfileFk, pi);
  }

  /* Phase 3: two-stage extraction from root tar for layers not covered by Phase 1/2 */
  if (pi && pi->layers && nRefs < MAX_PKGDBS) {
    /* coverage bitset of already-found layer indices */
    uint64_t covered[4] = {0, 0, 0, 0};
    for (int r = 0; r < nRefs; r++) {
      int li = refs[r].layerIndex;
      if (li >= 0 && li < 256)
        covered[li / 64] |= (uint64_t)1 << (li % 64);
    }

    char sqlP3[MAXCMD];
    snprintf(sqlP3, sizeof(sqlP3),
      "SELECT pfile_sha1||'.'||pfile_md5||'.'||pfile_size"
      " FROM %s INNER JOIN pfile ON pfile_pk=pfile_fk"
      " WHERE upload_fk=%ld AND parent IS NULL AND pfile_fk IS NOT NULL"
      " LIMIT 1",
      ut, upload_pk);
    PGresult *rP3 = PQexec(db_conn, sqlP3);
    int p3Err = fo_checkPQresult(db_conn, rP3, sqlP3, __FILE__, __LINE__);

    if (!p3Err && PQntuples(rP3) > 0) {
      char *rootRepo = fo_RepMkPath("files", PQgetvalue(rP3, 0, 0));
      PQclear(rP3);

      if (rootRepo) {
        int  p3RefsStart = nRefs;
        LOG_NOTICE("containerpkg: Phase 3 — image format='%s' root tar='%s'\n",
                   pi->format[0] ? pi->format : "(unknown)", rootRepo);

        for (int li = 0; li < pi->layerCount && nRefs < MAX_PKGDBS; li++) {
          /* skip layers already covered */
          if (li < 256 && (covered[li / 64] & ((uint64_t)1 << (li % 64))))
            continue;

          const char *bn = pi->layers[li].blobName;
          if (!bn || !bn[0]) continue;

          char layerMember[PATH_MAX];
          snprintf(layerMember, sizeof(layerMember), "%s", bn);

          /* Stage 1: extract layer blob from outer image tar */
          char layerTarTmp[PATH_MAX] = "";
          if (ExtractFromArchive(rootRepo, layerMember, layerTarTmp) != 0) {
            LOG_NOTICE("containerpkg: Phase 3 layer[%d] cannot extract "
                       "layer blob '%s' from image tar\n", li, layerMember);
            continue;  /* this layer truly has no extractable tar in the image */
          }

          LOG_NOTICE("containerpkg: Phase 3 layer[%d] extracted layer blob "
                     "'%s' to '%s'\n", li, layerMember, layerTarTmp);

          /* Stage 2: search for pkg db inside extracted layer tar */
          int foundDbForLayer = 0;
          for (int di = 0; KnownDbs[di].filename && nRefs < MAX_PKGDBS; di++) {
            char dbTmpFile[PATH_MAX] = "";
            if (ExtractFromArchive(layerTarTmp, KnownDbs[di].tarPath,
                                   dbTmpFile) != 0) {
              /* not every db exists in every layer */
              continue;
            }

            LOG_NOTICE("containerpkg: Phase 3 layer[%d] found '%s' (manager=%d)\n",
                       li, KnownDbs[di].tarPath, (int)KnownDbs[di].manager);

            PkgDbRef *ref = &refs[nRefs];
            ref->repoPath = strdup(dbTmpFile);
            if (!ref->repoPath) {
              LOG_WARNING("containerpkg: Phase 3 strdup OOM\n");
              unlink(dbTmpFile);
              continue;
            }
            ref->pfileFk    = 0;
            ref->manager    = KnownDbs[di].manager;
            ref->layerIndex = li;
            ref->isTempFile = 1;
            nRefs++;
            foundDbForLayer = 1;
          }

          unlink(layerTarTmp);  /* clean up stage-1 temp */

          if (!foundDbForLayer)
            LOG_NOTICE("containerpkg: Phase 3 layer[%d] no pkg db found "
                       "in layer tar (empty/non-base layer)\n", li);
        }

        free(rootRepo);
        LOG_NOTICE("containerpkg: Phase 3 added %d package db(s)\n",
                   nRefs - p3RefsStart);
      }
    } else if (!p3Err) {
      PQclear(rP3);
    }
  }

  if (nRefs == 0) {
    LOG_NOTICE("containerpkg: all phases found no package databases "
               "for upload %ld\n", upload_pk);
    g_free(ut);
    return 0;
  }

  int totalPackages = 0;

  for (int i = 0; i < nRefs; i++) {
    PkgDbRef *ref = &refs[i];

    /* resolve any remaining unresolved layer indices (no-op for Phase-1 refs) */
    if (!ref->isTempFile && ref->pfileFk > 0 && ref->layerIndex < 0)
      ref->layerIndex = ResolveLayerIndex(upload_pk, ut,
                                          ref->uploadtreePk,
                                          ref->pfileFk, pi);

    /* snapshot before PkgDbRefFree() nulls repoPath */
    int            layerIndex = ref->layerIndex;
    FossPkgManager manager    = ref->manager;
    int            isTempFile = ref->isTempFile;
    char          *savedPath  = ref->repoPath ? strdup(ref->repoPath) : NULL;

    if (Verbose)
      printf("containerpkg: parsing %s db (layer=%d, tempfile=%d, path=%s)\n",
             FossPkg_ManagerName(manager), layerIndex,
             isTempFile, savedPath ? savedPath : "(null)");

    FossPkgList *list = NULL;
    switch (manager) {
      case FOSSPKG_MGR_DPKG: list = FossPkg_ParseDpkgStatus(ref->repoPath);   break;
      case FOSSPKG_MGR_APK:  list = FossPkg_ParseApkInstalled(ref->repoPath); break;
      case FOSSPKG_MGR_RPM:  list = FossPkg_ParseRpmSqlite(ref->repoPath);    break;
      default:               list = FossPkg_ParseAuto(ref->repoPath);          break;
    }

    /* unlink temp file immediately after parsing */
    if (isTempFile && ref->repoPath && ref->repoPath[0])
      unlink(ref->repoPath);
    PkgDbRefFree(ref);   /* nulls ref->repoPath — use savedPath for diagnostics */

    if (!list) {
      LOG_WARNING("containerpkg: failed to parse db (manager=%d, path=%s)\n",
                  manager, savedPath ? savedPath : "(unknown)");
      free(savedPath);
      continue;
    }
    free(savedPath);

    LOG_NOTICE("containerpkg: parsed %d packages from layer %d (manager=%d)\n",
               list->count, layerIndex, manager);

    int inserted = RecordInstalledPackages(list, upload_pk, pkg_fk,
                                           layerIndex,
                                           FossPkg_ManagerName(manager));
    FossPkgListFree(list);

    if (inserted < 0) {
      LOG_ERROR("containerpkg: DB insert failed for layer %d\n", layerIndex);
      /* clean up remaining temp files on error */
      for (int j = i + 1; j < nRefs; j++) {
        if (refs[j].isTempFile && refs[j].repoPath && refs[j].repoPath[0])
          unlink(refs[j].repoPath);
        PkgDbRefFree(&refs[j]);
      }
      g_free(ut);
      return -1;
    }
    totalPackages += inserted;
  }

  if (Verbose)
    printf("containerpkg: recorded %d installed packages for upload %ld\n",
           totalPackages, upload_pk);

  g_free(ut);
  return totalPackages;
}
