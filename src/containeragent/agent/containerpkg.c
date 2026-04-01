/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file containerpkg.c
 * \brief Container installed-package and language-manifest extraction.
 *
 * Extraction runs in three phases per upload:
 *
 *   Phase 1 — uploadtree scan: query the FOSSology uploadtree for every
 *             known manifest filename (dpkg status, apk installed, METADATA…).
 *             Fast path; works whenever ununpack recursed into layer tars.
 *
 *   Phase 2 — blob extraction: for each layer whose blob is reachable via
 *             the uploadtree, open the layer tar directly from the repo and
 *             walk its entries.  Handles images where ununpack stored layer
 *             blobs but did not recurse into them.
 *
 *   Phase 3 — root-tar two-stage: open the outer Docker image tar, extract
 *             each layer blob into a temp file, then scan that blob.  Fallback
 *             for layers Phase 2 could not reach.
 *
 * Results are written to:
 *   pkg_container_installed    — OS packages  (dpkg/apk/rpm)
 *   pkg_container_inst_dep     — OS package dependencies
 *   pkg_container_lang_installed — Language packages (pip/npm/go/…)
 *   pkg_container_lang_dep     — Language package dependencies
 */

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdint.h>
#include <errno.h>
#include <unistd.h>
#include <limits.h>
#include <sys/stat.h>

#include <archive.h>
#include <archive_entry.h>

#include "containerpkg.h"

/* =========================================================================
 * EscapeField — NULL-safe PQescapeLiteral wrapper
 * ========================================================================= */
static inline char *EscapeField(PGconn *conn, const char *s)
{
  return PQescapeLiteral(conn, s ? s : "", s ? strlen(s) : 0);
}

/* =========================================================================
 * KnownManifests — registry of every file the agent can extract packages from
 *
 * Fields
 *   filename     basename to search for in uploadtree / archive entries
 *   parentDir    exact parent directory name for uploadtree SQL (OS DBs only)
 *   parentSuffix parent directory must END WITH this suffix (archive scan only)
 *                NULL = no suffix check.  Used for "METADATA" inside
 *                "*.dist-info" to avoid false positives.
 *   tarPath      fixed path inside a layer tar (OS DBs only; NULL for lang)
 *   manager      FossPkgManager enum value
 *   isLang       0 = OS package DB, 1 = language ecosystem manifest
 *   depthAny     1 = file may appear at any depth (all lang manifests)
 * ========================================================================= */
typedef struct {
  const char     *filename;
  const char     *parentDir;
  const char     *parentSuffix;
  const char     *tarPath;
  FossPkgManager  manager;
  int             isLang;
  int             depthAny;
} KnownManifest;

static const KnownManifest KnownManifests[] = {
  /* ── OS package databases ───────────────────────────────────────────── */
  { "status",            "dpkg", NULL,         "var/lib/dpkg/status",      FOSSPKG_MGR_DPKG,         0, 0 },
  { "installed",         "db",   NULL,         "lib/apk/db/installed",     FOSSPKG_MGR_APK,          0, 0 },
  { "rpmdb.sqlite",      "rpm",  NULL,         "var/lib/rpm/rpmdb.sqlite", FOSSPKG_MGR_RPM,          0, 0 },
  { "Packages",          "rpm",  NULL,         "var/lib/rpm/Packages",     FOSSPKG_MGR_RPM_BDB,      0, 0 },
  /* ── Language manifests ─────────────────────────────────────────────── */
  { "package-lock.json", NULL,   NULL,         NULL, FOSSPKG_MGR_NPM,        1, 1 },
  { "yarn.lock",         NULL,   NULL,         NULL, FOSSPKG_MGR_NPM,        1, 1 },
  { "requirements.txt",  NULL,   NULL,         NULL, FOSSPKG_MGR_PIP,        1, 1 },
  { "Pipfile.lock",      NULL,   NULL,         NULL, FOSSPKG_MGR_PIP,        1, 1 },
  { "pyproject.toml",    NULL,   NULL,         NULL, FOSSPKG_MGR_PIP,        1, 1 },
  /* pip installed-package records: METADATA inside *.dist-info directories */
  { "METADATA",          NULL,   ".dist-info", NULL, FOSSPKG_MGR_PIP_DIST_INFO, 1, 1 },
  { "pom.xml",           NULL,   NULL,         NULL, FOSSPKG_MGR_MAVEN,      1, 1 },
  { "go.mod",            NULL,   NULL,         NULL, FOSSPKG_MGR_GO,         1, 1 },
  { "Cargo.lock",        NULL,   NULL,         NULL, FOSSPKG_MGR_CARGO,      1, 1 },
  { "Gemfile.lock",      NULL,   NULL,         NULL, FOSSPKG_MGR_GEM,        1, 1 },
  { "packages.lock.json",NULL,   NULL,         NULL, FOSSPKG_MGR_NUGET,      1, 1 },
  { "composer.lock",     NULL,   NULL,         NULL, FOSSPKG_MGR_COMPOSER,   1, 1 },
  { NULL, NULL, NULL, NULL, FOSSPKG_MGR_UNKNOWN, 0, 0 }
};

/* =========================================================================
 * EnsureLayerCap — grow pi->layers[] to hold at least newCount entries
 * ========================================================================= */
int EnsureLayerCap(struct containerpkginfo *pi, int newCount)
{
  if (newCount <= pi->layerCap) return 0;
  if (newCount > MAX_LAYERS_HARD) {
    LOG_WARNING("containerpkg: layer count %d exceeds hard cap %d — truncating\n",
                newCount, MAX_LAYERS_HARD);
    newCount = MAX_LAYERS_HARD;
  }
  int newCap = pi->layerCap ? pi->layerCap * 2 : MAX_LAYERS_INITIAL;
  while (newCap < newCount) newCap *= 2;
  if (newCap > MAX_LAYERS_HARD) newCap = MAX_LAYERS_HARD;

  struct containerlayerinfo *tmp =
    realloc(pi->layers, (size_t)newCap * sizeof(*tmp));
  if (!tmp) {
    LOG_ERROR("containerpkg: realloc layers failed (cap=%d)\n", newCap);
    return -1;
  }
  memset(tmp + pi->layerCap, 0,
         (size_t)(newCap - pi->layerCap) * sizeof(*tmp));
  pi->layers   = tmp;
  pi->layerCap = newCap;
  return 0;
}

/* =========================================================================
 * LayerIdent — extract the uploadtree-visible identifier for a layer blob.
 *
 * Docker layers are stored as "<sha256>/layer.tar"; the identifier used in
 * uploadtree is the sha256 directory name.  OCI layers are bare hex digests.
 * Writes at most MAXLENGTH-1 bytes into out[MAXLENGTH].
 * ========================================================================= */
static void LayerIdent(const char *blobName, char out[MAXLENGTH])
{
  out[0] = '\0';
  if (!blobName || !blobName[0]) return;

  size_t bnLen = strlen(blobName);
  const char *sfx    = "/layer.tar";
  size_t      sfxLen = strlen(sfx);

  if (bnLen > sfxLen && strcmp(blobName + bnLen - sfxLen, sfx) == 0) {
    /* Docker: strip "/layer.tar" suffix, then take the last path component */
    size_t dirLen = bnLen - sfxLen;
    const char *ident = blobName;
    for (size_t k = 0; k < dirLen; k++)
      if (blobName[k] == '/') ident = blobName + k + 1;
    size_t identLen = dirLen - (size_t)(ident - blobName);
    if (identLen >= MAXLENGTH) identLen = MAXLENGTH - 1;
    memcpy(out, ident, identLen);
    out[identLen] = '\0';
  } else {
    /* OCI / plain: take last path component */
    const char *slash = strrchr(blobName, '/');
    const char *ident = slash ? slash + 1 : blobName;
    strncpy(out, ident, MAXLENGTH - 1);
    out[MAXLENGTH - 1] = '\0';
  }
}

/* =========================================================================
 * Phase 1: uploadtree scan
 * ========================================================================= */

static void FindManifestsInUploadtree(long          upload_pk,
                                      const char   *ut,
                                      unsigned long rootLft,
                                      unsigned long rootRgt,
                                      PkgDbVec     *vec,
                                      int           manifestIdx)
{
  char      SQL[MAXCMD * 2];
  PGresult *result;

  for (int i = 0; KnownManifests[i].filename; i++) {
    const KnownManifest *km = &KnownManifests[i];

    /*
     * All three query variants use DISTINCT ON (child.pfile_fk).
     *
     * Why: FOSSology's uploadtree can contain multiple rows that reference
     * the same pfile_fk for the same uploaded content.  This happens with
     * Docker images because ununpack creates uploadtree rows for both:
     *   (a) the layer.tar blob stored as a pfile, and
     *   (b) every file extracted from that layer.tar (also stored as pfiles).
     *
     * The same METADATA file therefore appears in uploadtree twice — once
     * under the extracted-content subtree and once under the raw-blob subtree
     * — with different uploadtree_pk values but IDENTICAL pfile_fk and hash.
     *
     * Without DISTINCT ON, the query returns two rows for the same file,
     * both become PkgDbRef entries, both are parsed, and every package in
     * that file is inserted into the DB twice.
     *
     * DISTINCT ON (child.pfile_fk) collapses all uploadtree rows that share
     * the same file content to exactly one, picking the row with the lowest
     * uploadtree_pk (deterministic via ORDER BY).
     */
    if (km->parentDir && !km->depthAny) {
      /* OS package DBs: exact parent directory match */
      snprintf(SQL, sizeof(SQL),
        "SELECT DISTINCT ON (child.pfile_fk)"
        "       child.uploadtree_pk, child.pfile_fk,"
        "       pfile_sha1||'.'||pfile_md5||'.'||pfile_size AS hash"
        " FROM %s child"
        " INNER JOIN %s parent ON parent.uploadtree_pk = child.parent"
        " INNER JOIN pfile ON pfile_pk = child.pfile_fk"
        " WHERE child.upload_fk=%ld AND child.lft>%lu AND child.rgt<%lu"
        "   AND child.ufile_name='%s' AND parent.ufile_name='%s'"
        " ORDER BY child.pfile_fk, child.uploadtree_pk",
        ut, ut, upload_pk, rootLft, rootRgt, km->filename, km->parentDir);

    } else if (km->parentSuffix) {
      /* pip dist-info METADATA: parent dir must end with suffix */
      snprintf(SQL, sizeof(SQL),
        "SELECT DISTINCT ON (child.pfile_fk)"
        "       child.uploadtree_pk, child.pfile_fk,"
        "       pfile_sha1||'.'||pfile_md5||'.'||pfile_size AS hash"
        " FROM %s child"
        " INNER JOIN %s parent ON parent.uploadtree_pk = child.parent"
        " INNER JOIN pfile ON pfile_pk = child.pfile_fk"
        " WHERE child.upload_fk=%ld AND child.lft>%lu AND child.rgt<%lu"
        "   AND child.ufile_name='%s'"
        "   AND parent.ufile_name LIKE '%%%s'"
        " ORDER BY child.pfile_fk, child.uploadtree_pk",
        ut, ut, upload_pk, rootLft, rootRgt, km->filename, km->parentSuffix);

    } else {
      /* Language manifests: any depth, no parent constraint */
      snprintf(SQL, sizeof(SQL),
        "SELECT DISTINCT ON (child.pfile_fk)"
        "       child.uploadtree_pk, child.pfile_fk,"
        "       pfile_sha1||'.'||pfile_md5||'.'||pfile_size AS hash"
        " FROM %s child"
        " INNER JOIN pfile ON pfile_pk = child.pfile_fk"
        " WHERE child.upload_fk=%ld AND child.lft>%lu AND child.rgt<%lu"
        "   AND child.ufile_name='%s'"
        " ORDER BY child.pfile_fk, child.uploadtree_pk",
        ut, upload_pk, rootLft, rootRgt, km->filename);
    }

    result = PQexec(db_conn, SQL);
    if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) continue;

    int rows = PQntuples(result);
    for (int r = 0; r < rows; r++) {
      char *repoPath = fo_RepMkPath("files", PQgetvalue(result, r, 2));
      if (!repoPath) continue;

      PkgDbRef *ref = PkgDbVecAlloc(vec);
      if (!ref) { free(repoPath); PQclear(result); return; }

      ref->repoPath     = repoPath;
      ref->origFilename = strdup(km->filename);
      ref->uploadtreePk = atol(PQgetvalue(result, r, 0));
      ref->pfileFk      = atol(PQgetvalue(result, r, 1));
      ref->manager      = km->manager;
      ref->layerIndex   = -1;
      ref->manifestIdx  = manifestIdx;
      ref->isTempFile   = 0;
    }
    PQclear(result);
  }
}

/* =========================================================================
 * ResolveLayerIndex — walk uploadtree ancestors to find which layer a file
 * belongs to, by matching ancestor directory names against blobName idents.
 * ========================================================================= */
static int ResolveLayerIndex(long upload_pk, const char *ut,
                             long uploadtreePk, long pfileFk,
                             struct containerpkginfo *pi)
{
  if (!pi || !pi->layers || pi->layerCount <= 0) return -1;

  int maxCheck = pi->layerCount < 4096 ? pi->layerCount : 4096;

  /* Heap-allocated ident table avoids a ~1 MB stack VLA */
  char *buf = calloc((size_t)maxCheck, MAXLENGTH);
  if (!buf) return -1;

  int nIdents = 0;
  for (int i = 0; i < maxCheck; i++) {
    LayerIdent(pi->layers[i].blobName, buf + i * MAXLENGTH);
    if ((buf + i * MAXLENGTH)[0]) nIdents++;
  }
  if (nIdents == 0) { free(buf); return -1; }

  /* Build escaped SQL IN-list */
  int   inBufSize = maxCheck * (2 * MAXLENGTH + 6) + 8;
  char *inList    = malloc((size_t)inBufSize);
  if (!inList) { free(buf); return -1; }

  int inLen = 0, first = 1;
  inList[inLen++] = '(';
  for (int i = 0; i < maxCheck; i++) {
    const char *ident = buf + i * MAXLENGTH;
    if (!ident[0]) continue;
    char *esc = PQescapeLiteral(db_conn, ident, strlen(ident));
    if (!esc) continue;
    int escLen = (int)strlen(esc);
    if (inLen + 1 + escLen + 2 > inBufSize) { PQfreemem(esc); break; }
    if (!first) inList[inLen++] = ',';
    first = 0;
    memcpy(inList + inLen, esc, (size_t)escLen);
    inLen += escLen;
    PQfreemem(esc);
  }
  inList[inLen++] = ')';
  inList[inLen]   = '\0';

  char SQL[MAXCMD * 4];
  if (uploadtreePk > 0) {
    snprintf(SQL, sizeof(SQL),
      "WITH RECURSIVE anc AS ("
      "  SELECT t.uploadtree_pk, t.parent, t.ufile_name FROM %s t"
      "  WHERE t.uploadtree_pk = %ld"
      "  UNION ALL"
      "  SELECT p.uploadtree_pk, p.parent, p.ufile_name"
      "  FROM %s p JOIN anc a ON p.uploadtree_pk = a.parent"
      "  WHERE a.parent IS NOT NULL"
      ") SELECT ufile_name FROM anc WHERE ufile_name IN %s LIMIT 1",
      ut, uploadtreePk, ut, inList);
  } else {
    snprintf(SQL, sizeof(SQL),
      "WITH RECURSIVE anc AS ("
      "  SELECT t.uploadtree_pk, t.parent, t.ufile_name FROM %s t"
      "  WHERE t.pfile_fk = %ld AND t.upload_fk = %ld"
      "  UNION ALL"
      "  SELECT p.uploadtree_pk, p.parent, p.ufile_name"
      "  FROM %s p JOIN anc a ON p.uploadtree_pk = a.parent"
      "  WHERE a.parent IS NOT NULL"
      ") SELECT ufile_name FROM anc WHERE ufile_name IN %s LIMIT 1",
      ut, pfileFk, upload_pk, ut, inList);
  }
  free(inList);

  PGresult *result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) {
    free(buf); return -1;
  }
  if (PQntuples(result) == 0) { PQclear(result); free(buf); return -1; }

  char matched[MAXLENGTH];
  strncpy(matched, PQgetvalue(result, 0, 0), sizeof(matched) - 1);
  matched[sizeof(matched) - 1] = '\0';
  PQclear(result);

  int ret = -1;
  for (int i = 0; i < maxCheck; i++) {
    if (strcmp(buf + i * MAXLENGTH, matched) == 0) { ret = i; break; }
  }
  free(buf);
  return ret;
}

/* =========================================================================
 * ExtractFromArchive — extract one named member to a fresh temp file
 * ========================================================================= */
static int ExtractFromArchive(const char *tarPath, const char *member,
                               char *tmpFile)
{
  snprintf(tmpFile, PATH_MAX, "/tmp/containerpkg_XXXXXX");
  int fd = mkstemp(tmpFile);
  if (fd < 0) {
    LOG_WARNING("containerpkg: mkstemp failed: %s\n", strerror(errno));
    return -1;
  }

  struct archive       *a   = archive_read_new();
  struct archive_entry *ent = NULL;
  int rc = -1;

  archive_read_support_filter_all(a);
  archive_read_support_format_all(a);

  if (archive_read_open_filename(a, tarPath, 65536) != ARCHIVE_OK) {
    LOG_WARNING("containerpkg: cannot open archive '%s': %s\n",
                tarPath, archive_error_string(a));
    close(fd); unlink(tmpFile); tmpFile[0] = '\0';
    archive_read_free(a);
    return -1;
  }

  /* Accept paths with or without leading "./" */
  char noDot[PATH_MAX], withDot[PATH_MAX];
  if (member[0] == '.' && member[1] == '/') {
    strncpy(noDot,    member + 2, sizeof(noDot)    - 1); noDot[sizeof(noDot)-1]       = '\0';
    strncpy(withDot,  member,     sizeof(withDot)  - 1); withDot[sizeof(withDot)-1]   = '\0';
  } else {
    strncpy(noDot,    member,     sizeof(noDot)    - 1); noDot[sizeof(noDot)-1]       = '\0';
    snprintf(withDot, sizeof(withDot), "./%s", member);
  }

  while (archive_read_next_header(a, &ent) == ARCHIVE_OK) {
    const char *ep = archive_entry_pathname(ent);
    if (!ep || (strcmp(ep, noDot) != 0 && strcmp(ep, withDot) != 0)) {
      archive_read_data_skip(a);
      continue;
    }
    const void *blk; size_t sz; la_int64_t off;
    int ok = 1;
    while (archive_read_data_block(a, &blk, &sz, &off) == ARCHIVE_OK) {
      if (sz == 0) continue;
      if (pwrite(fd, blk, sz, (off_t)off) != (ssize_t)sz) {
        LOG_WARNING("containerpkg: pwrite failed: %s\n", strerror(errno));
        ok = 0; break;
      }
    }
    rc = ok ? 0 : -1;
    break;
  }

  close(fd);
  archive_read_free(a);

  if (rc != 0) { unlink(tmpFile); tmpFile[0] = '\0'; return -1; }

  struct stat st;
  if (stat(tmpFile, &st) != 0 || st.st_size == 0) {
    unlink(tmpFile); tmpFile[0] = '\0'; return -1;
  }
  return 0;
}

/* =========================================================================
 * FindBlobRepoPath — locate a layer blob in the FOSSology file repository.
 *
 * Two uploadtree layouts are tried in order:
 *   Layout A (split):   ufile_name="layer.tar"          parent="<sha256dir>"
 *   Layout B (flat):    ufile_name="<sha256dir>/layer.tar"  (full path)
 * OCI blobs are bare hex digests (no '/') — plain name lookup only.
 * ========================================================================= */
static char *FindBlobRepoPath(long upload_pk, const char *ut,
                               const char *blobName)
{
  if (!blobName || !blobName[0]) return NULL;

  const char *lastSlash = strrchr(blobName, '/');
  PGresult   *result;
  char        SQL[MAXCMD * 2];

  if (lastSlash) {
    const char *base     = lastSlash + 1;
    const char *dirStart = blobName;
    for (const char *p = blobName; p < lastSlash; p++)
      if (*p == '/') dirStart = p + 1;
    size_t dirLen = (size_t)(lastSlash - dirStart);

    char *eBase = PQescapeLiteral(db_conn, base,     strlen(base));
    char *eDir  = PQescapeLiteral(db_conn, dirStart, dirLen);
    char *eFull = PQescapeLiteral(db_conn, blobName, strlen(blobName));
    if (!eBase || !eDir || !eFull) {
      PQfreemem(eBase); PQfreemem(eDir); PQfreemem(eFull); return NULL;
    }

    /* Layout A */
    snprintf(SQL, sizeof(SQL),
      "SELECT pfile_sha1||'.'||pfile_md5||'.'||pfile_size"
      " FROM %s child"
      " INNER JOIN %s parent ON parent.uploadtree_pk = child.parent"
      " INNER JOIN pfile ON pfile_pk = child.pfile_fk"
      " WHERE child.upload_fk=%ld AND parent.upload_fk=%ld"
      "   AND child.ufile_name=%s AND parent.ufile_name=%s"
      "   AND child.pfile_fk IS NOT NULL LIMIT 1",
      ut, ut, upload_pk, upload_pk, eBase, eDir);

    result = PQexec(db_conn, SQL);
    if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) {
      PQfreemem(eBase); PQfreemem(eDir); PQfreemem(eFull); return NULL;
    }
    if (PQntuples(result) > 0) {
      char *path = fo_RepMkPath("files", PQgetvalue(result, 0, 0));
      PQclear(result);
      PQfreemem(eBase); PQfreemem(eDir); PQfreemem(eFull);
      return path;
    }
    PQclear(result);

    /* Layout B fallback */
    snprintf(SQL, sizeof(SQL),
      "SELECT pfile_sha1||'.'||pfile_md5||'.'||pfile_size"
      " FROM %s INNER JOIN pfile ON pfile_pk=pfile_fk"
      " WHERE upload_fk=%ld AND ufile_name=%s"
      "   AND pfile_fk IS NOT NULL LIMIT 1",
      ut, upload_pk, eFull);

    PQfreemem(eBase); PQfreemem(eDir); PQfreemem(eFull);

    result = PQexec(db_conn, SQL);
    if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) return NULL;
    if (PQntuples(result) == 0) { PQclear(result); return NULL; }

  } else {
    /* OCI bare digest */
    char *esc = PQescapeLiteral(db_conn, blobName, strlen(blobName));
    if (!esc) return NULL;

    snprintf(SQL, sizeof(SQL),
      "SELECT pfile_sha1||'.'||pfile_md5||'.'||pfile_size"
      " FROM %s INNER JOIN pfile ON pfile_pk=pfile_fk"
      " WHERE upload_fk=%ld AND ufile_name=%s"
      "   AND pfile_fk IS NOT NULL LIMIT 1",
      ut, upload_pk, esc);

    PQfreemem(esc);

    result = PQexec(db_conn, SQL);
    if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) return NULL;
    if (PQntuples(result) == 0) { PQclear(result); return NULL; }
  }

  char *path = fo_RepMkPath("files", PQgetvalue(result, 0, 0));
  PQclear(result);
  return path;
}

/* =========================================================================
 * ParentDirOf — return the immediate parent directory name of an archive path.
 *
 * "a/b/c/foo.txt" → "c"   |   "foo.txt" → NULL (top-level)
 *
 * Uses a file-scoped static buffer; safe for the single-threaded agent.
 * Do not call from multiple threads without adding a mutex.
 * ========================================================================= */
static const char *ParentDirOf(const char *entPath)
{
  if (!entPath) return NULL;
  const char *last = strrchr(entPath, '/');
  if (!last || last == entPath) return NULL;

  const char *end   = last;
  const char *start = last - 1;
  while (start > entPath && *start != '/') start--;
  if (*start == '/') start++;

  static char parentBuf[PATH_MAX];
  size_t len = (size_t)(end - start);
  if (len >= sizeof(parentBuf)) len = sizeof(parentBuf) - 1;
  memcpy(parentBuf, start, len);
  parentBuf[len] = '\0';
  return parentBuf;
}

/* =========================================================================
 * ScanArchiveForLangManifests — full archive walk, matching by basename
 *
 * Entries whose basename appears in KnownManifests[] with depthAny=1 are
 * extracted to temp files and appended to vec.  An optional parentSuffix
 * constraint prevents false positives (e.g. "METADATA" only inside ".dist-info").
 * ========================================================================= */
static void ScanArchiveForLangManifests(const char *tarPath,
                                        int         layerIndex,
                                        int         manifestIdx,
                                        PkgDbVec   *vec)
{
  struct archive       *a   = archive_read_new();
  struct archive_entry *ent = NULL;

  archive_read_support_filter_all(a);
  archive_read_support_format_all(a);

  if (archive_read_open_filename(a, tarPath, 65536) != ARCHIVE_OK) {
    LOG_WARNING("containerpkg: ScanArchiveLang: cannot open '%s': %s\n",
                tarPath, archive_error_string(a));
    archive_read_free(a);
    return;
  }

  int processed = 0;

  while (archive_read_next_header(a, &ent) == ARCHIVE_OK) {
    /* Heartbeat every 100 entries to prevent scheduler timeout */
    if (++processed % 100 == 0)
      fo_scheduler_heart(0);

    if (archive_entry_filetype(ent) != AE_IFREG) {
      archive_read_data_skip(a); continue;
    }

    const char *entPath = archive_entry_pathname(ent);
    if (!entPath) { archive_read_data_skip(a); continue; }

    const char *bname = strrchr(entPath, '/');
    bname = bname ? bname + 1 : entPath;
    if (!bname || !bname[0]) { archive_read_data_skip(a); continue; }

    /* Find a matching depthAny entry */
    int matchIdx = -1;
    for (int di = 0; KnownManifests[di].filename; di++) {
      if (!KnownManifests[di].depthAny) continue;
      if (strcmp(bname, KnownManifests[di].filename) != 0) continue;

      if (KnownManifests[di].parentSuffix) {
        const char *pdir = ParentDirOf(entPath);
        if (!pdir || !pdir[0]) continue;
        size_t plen = strlen(pdir);
        size_t slen = strlen(KnownManifests[di].parentSuffix);
        if (plen < slen) continue;
        if (strcmp(pdir + plen - slen, KnownManifests[di].parentSuffix) != 0) continue;
      }

      matchIdx = di;
      break;
    }
    if (matchIdx < 0) { archive_read_data_skip(a); continue; }

    /* Extract to temp file */
    char tmpFile[PATH_MAX];
    snprintf(tmpFile, sizeof(tmpFile), "/tmp/containerpkg_lang_XXXXXX");
    int fd = mkstemp(tmpFile);
    if (fd < 0) {
      LOG_WARNING("containerpkg: ScanArchiveLang: mkstemp failed: %s\n",
                  strerror(errno));
      archive_read_data_skip(a); continue;
    }

    const void *blk; size_t sz; la_int64_t off;
    int ok = 1;
    while (archive_read_data_block(a, &blk, &sz, &off) == ARCHIVE_OK) {
      if (sz == 0) continue;
      if (pwrite(fd, blk, sz, (off_t)off) != (ssize_t)sz) {
        LOG_WARNING("containerpkg: ScanArchiveLang: pwrite failed: %s\n",
                    strerror(errno));
        ok = 0; break;
      }
    }
    close(fd);

    if (!ok) { unlink(tmpFile); continue; }

    struct stat st;
    if (stat(tmpFile, &st) != 0 || st.st_size == 0) { unlink(tmpFile); continue; }

    PkgDbRef *ref = PkgDbVecAlloc(vec);
    if (!ref) { unlink(tmpFile); archive_read_free(a); return; }

    ref->repoPath     = strdup(tmpFile);
    ref->origFilename = strdup(KnownManifests[matchIdx].filename);
    ref->manager      = KnownManifests[matchIdx].manager;
    ref->layerIndex   = layerIndex;
    ref->manifestIdx  = manifestIdx;
    ref->isTempFile   = 1;

    LOG_NOTICE("containerpkg: found lang manifest '%s' at '%s' in layer %d\n",
               bname, entPath, layerIndex);
  }

  archive_read_free(a);
}

/* =========================================================================
 * Phase 2: direct blob extraction
 * ========================================================================= */

static void FindManifestsInBlobs(long                     upload_pk,
                                  const char              *ut,
                                  struct containerpkginfo *pi,
                                  PkgDbVec                *vec,
                                  int                      manifestIdx,
                                  uint64_t                *blobScanned,
                                  const uint64_t          *p1OsCovered,
                                  const uint64_t          *p1LangCovered)
{
  if (!pi || !pi->layers) return;

  int attempted = 0, found = 0;

  for (int li = 0; li < pi->layerCount; li++) {
    const char *blobName = pi->layers[li].blobName;
    if (!blobName || !blobName[0]) continue;

    /* Determine what Phase 1 already provided for this layer */
    int osAlreadyFound   = (p1OsCovered   && li < MAX_LAYERS_HARD &&
                            (p1OsCovered  [li / 64] & ((uint64_t)1 << (li % 64))));
    int langAlreadyFound = (p1LangCovered && li < MAX_LAYERS_HARD &&
                            (p1LangCovered[li / 64] & ((uint64_t)1 << (li % 64))));

    /* If Phase 1 covered both OS and lang for this layer, nothing left to do */
    if (osAlreadyFound && langAlreadyFound) continue;

    attempted++;
    char *blobRepo = FindBlobRepoPath(upload_pk, ut, blobName);
    if (!blobRepo) {
      if (Verbose)
        LOG_NOTICE("containerpkg: Phase2 layer[%d] blobName='%s' not found\n",
                   li, blobName);
      continue;
    }

    found++;
    LOG_NOTICE("containerpkg: Phase2 layer[%d] scanning blob '%s' "
               "(skipOS=%d skipLang=%d)\n",
               li, blobName, osAlreadyFound, langAlreadyFound);

    /* Mark scanned so Phase 3 skips this layer */
    if (blobScanned && li < MAX_LAYERS_HARD)
      blobScanned[li / 64] |= (uint64_t)1 << (li % 64);

    /* OS databases: extract by fixed tarPath — only if Phase 1 missed them */
    if (!osAlreadyFound) {
      for (int di = 0; KnownManifests[di].filename; di++) {
        if (!KnownManifests[di].tarPath) continue;
        char tmpFile[PATH_MAX] = "";
        if (ExtractFromArchive(blobRepo, KnownManifests[di].tarPath, tmpFile) != 0)
          continue;
        PkgDbRef *ref = PkgDbVecAlloc(vec);
        if (!ref) { unlink(tmpFile); free(blobRepo); return; }
        ref->repoPath     = strdup(tmpFile);
        ref->origFilename = strdup(KnownManifests[di].filename);
        ref->manager      = KnownManifests[di].manager;
        ref->layerIndex   = li;
        ref->manifestIdx  = manifestIdx;
        ref->isTempFile   = 1;
        LOG_NOTICE("containerpkg: Phase2 layer[%d] found OS db '%s'\n",
                   li, KnownManifests[di].filename);
      }
    }

    /* Language manifests: full archive walk — only if Phase 1 missed them */
    if (!langAlreadyFound) {
      int before = vec->count;
      ScanArchiveForLangManifests(blobRepo, li, manifestIdx, vec);
      LOG_NOTICE("containerpkg: Phase2 layer[%d] lang scan: %d manifest(s)\n",
                 li, vec->count - before);
    }

    free(blobRepo);

    /* Heartbeat after each layer blob to prevent scheduler timeout */
    fo_scheduler_heart(0);
  }

  LOG_NOTICE("containerpkg: Phase2: %d/%d blobs opened\n", found, attempted);
}

/* =========================================================================
 * Phase 3: two-stage root-tar extraction
 *
 * Three attempts to locate the outer Docker image tar, robust to both
 * Layout 1 (root row has pfile_fk set) and Layout 2 (virtual root wrapper):
 *   1. pi->pFile — set by ProcessUpload when root has pfile_fk
 *   2. lft = rootLft+1 — first child of the virtual root
 *   3. Lowest-lft non-JSON pfile in the upload
 * ========================================================================= */

static void FindManifestsInRootTar(long                     upload_pk,
                                    const char              *ut,
                                    struct containerpkginfo *pi,
                                    PkgDbVec                *vec,
                                    int                      manifestIdx,
                                    uint64_t                *covered,
                                    unsigned long            rootLft)
{
  char *rootRepo = NULL;

  /* Attempt 1 */
  if (pi->pFile[0] != '\0') {
    rootRepo = fo_RepMkPath("files", pi->pFile);
    LOG_NOTICE("containerpkg: Phase3 root via pi->pFile\n");
  }

  /* Attempt 2 */
  if (!rootRepo) {
    char sql[MAXCMD];
    snprintf(sql, sizeof(sql),
      "SELECT pfile_sha1||'.'||pfile_md5||'.'||pfile_size"
      " FROM %s INNER JOIN pfile ON pfile_pk=pfile_fk"
      " WHERE upload_fk=%ld AND lft=%lu AND pfile_fk IS NOT NULL LIMIT 1",
      ut, upload_pk, rootLft + 1);
    PGresult *r = PQexec(db_conn, sql);
    if (!fo_checkPQresult(db_conn, r, sql, __FILE__, __LINE__) && PQntuples(r) > 0) {
      rootRepo = fo_RepMkPath("files", PQgetvalue(r, 0, 0));
      LOG_NOTICE("containerpkg: Phase3 root via lft=%lu child\n", rootLft + 1);
    }
    PQclear(r);
  }

  /* Attempt 3 */
  if (!rootRepo) {
    char sql[MAXCMD];
    snprintf(sql, sizeof(sql),
      "SELECT pfile_sha1||'.'||pfile_md5||'.'||pfile_size"
      " FROM %s INNER JOIN pfile ON pfile_pk=pfile_fk"
      " WHERE upload_fk=%ld AND pfile_fk IS NOT NULL"
      "   AND ufile_name NOT LIKE '%%.json'"
      " ORDER BY lft ASC LIMIT 1",
      ut, upload_pk);
    PGresult *r = PQexec(db_conn, sql);
    if (!fo_checkPQresult(db_conn, r, sql, __FILE__, __LINE__) && PQntuples(r) > 0) {
      rootRepo = fo_RepMkPath("files", PQgetvalue(r, 0, 0));
      LOG_NOTICE("containerpkg: Phase3 root via lowest-lft non-JSON fallback\n");
    }
    PQclear(r);
  }

  if (!rootRepo) {
    LOG_WARNING("containerpkg: Phase3: cannot locate outer image tar for upload %ld\n",
                upload_pk);
    return;
  }

  for (int li = 0; li < pi->layerCount; li++) {
    if (li < MAX_LAYERS_HARD &&
        (covered[li / 64] & ((uint64_t)1 << (li % 64)))) continue;

    const char *bn = pi->layers[li].blobName;
    if (!bn || !bn[0]) continue;

    char layerTmp[PATH_MAX] = "";
    if (ExtractFromArchive(rootRepo, bn, layerTmp) != 0) continue;

    /* OS databases */
    for (int di = 0; KnownManifests[di].filename; di++) {
      if (!KnownManifests[di].tarPath) continue;
      char dbTmp[PATH_MAX] = "";
      if (ExtractFromArchive(layerTmp, KnownManifests[di].tarPath, dbTmp) != 0) continue;
      PkgDbRef *ref = PkgDbVecAlloc(vec);
      if (!ref) { unlink(dbTmp); unlink(layerTmp); free(rootRepo); return; }
      ref->repoPath     = strdup(dbTmp);
      ref->origFilename = strdup(KnownManifests[di].filename);
      ref->manager      = KnownManifests[di].manager;
      ref->layerIndex   = li;
      ref->manifestIdx  = manifestIdx;
      ref->isTempFile   = 1;
    }

    /* Language manifests */
    ScanArchiveForLangManifests(layerTmp, li, manifestIdx, vec);
    unlink(layerTmp);

    /* Heartbeat after each layer to prevent scheduler timeout */
    fo_scheduler_heart(0);
  }

  free(rootRepo);
}

/* =========================================================================
 * ExecInsertWithDeps — generic helper shared by RecordInstalledPackages and
 * RecordLangPackages.
 *
 * Executes one INSERT for a package row and one INSERT per dependency row
 * inside the caller's open transaction.  Returns 1 on success, -1 on DB
 * error (caller must ROLLBACK).
 *
 * Parameters
 *   insertSQL   — fully-formatted INSERT … RETURNING inst_pk statement
 *   depTmpl     — printf template for dep INSERT: "INSERT … VALUES (%d,%s);"
 *   pkg         — package whose requires[] to iterate
 * ========================================================================= */
static int ExecInsertWithDeps(const char  *insertSQL,
                               const char  *depTmpl,
                               FossPkgInfo *pkg,
                               int         *instPkOut)
{
  PGresult *result = PQexec(db_conn, insertSQL);
  if (fo_checkPQresult(db_conn, result, (char *)insertSQL, __FILE__, __LINE__)) {
    PQclear(result); return -1;
  }

  int instPk = (PQntuples(result) > 0) ? atoi(PQgetvalue(result, 0, 0)) : 0;
  PQclear(result);
  if (instPkOut) *instPkOut = instPk;

  if (instPk <= 0) return 1;   /* inserted but no inst_pk — skip deps */

  for (int d = 0; d < pkg->requireCount; d++) {
    if (!pkg->requires[d] || !pkg->requires[d][0]) continue;

    char *eDep = PQescapeLiteral(db_conn, pkg->requires[d], strlen(pkg->requires[d]));
    int   need = snprintf(NULL, 0, depTmpl, instPk, eDep);
    char *depSQL = (need > 0) ? malloc((size_t)need + 1) : NULL;
    if (!depSQL) { PQfreemem(eDep); return -1; }
    snprintf(depSQL, (size_t)need + 1, depTmpl, instPk, eDep);
    PQfreemem(eDep);

    result = PQexec(db_conn, depSQL);
    free(depSQL);
    if (fo_checkPQcommand(db_conn, result, "(dep insert)", __FILE__, __LINE__)) {
      PQclear(result); return -1;
    }
    PQclear(result);
  }

  return 1;
}

/* =========================================================================
 * RecordInstalledPackages — OS packages → pkg_container_installed
 * ========================================================================= */
int RecordInstalledPackages(FossPkgList *list, long upload_pk,
                             int pkg_fk, int layerIndex, const char *manager)
{
  if (!list || list->count == 0) return 0;

  PGresult *result = PQexec(db_conn, "BEGIN;");
  if (fo_checkPQcommand(db_conn, result, "BEGIN", __FILE__, __LINE__)) return -1;
  PQclear(result);

  int total = 0;

  static const char *insTmpl =
    "INSERT INTO pkg_container_installed"
    " (upload_fk,pkg_fk,pkg_manager,pkg_name,pkg_version,"
    "  pkg_arch,pkg_source,pkg_summary,pkg_maintainer,pkg_license,layer_index)"
    " VALUES (%ld,%d,%s,%s,%s,%s,%s,%s,%s,%s,%d) RETURNING inst_pk;";

  static const char *depTmpl =
    "INSERT INTO pkg_container_inst_dep (inst_fk,dep_name) VALUES (%d,%s);";

  for (int i = 0; i < list->count; i++) {
    FossPkgInfo *pkg = list->pkgs[i];
    if (!pkg || !pkg->name) continue;

#define ESC(s) EscapeField(db_conn, (s))
    char *eName  = ESC(pkg->name);   char *eVer   = ESC(pkg->version);
    char *eArch  = ESC(pkg->arch);   char *eSrc   = ESC(pkg->source);
    char *eSum   = ESC(pkg->summary);char *eMaint = ESC(pkg->maintainer);
    char *eLic   = ESC(pkg->license);char *eMgr   = ESC(manager);
#undef ESC

    int need = snprintf(NULL, 0, insTmpl,
                        upload_pk, pkg_fk,
                        eMgr, eName, eVer, eArch, eSrc, eSum, eMaint, eLic, layerIndex);
    char *sql = (need > 0) ? malloc((size_t)need + 1) : NULL;
    if (!sql) {
      PQfreemem(eName); PQfreemem(eVer); PQfreemem(eArch); PQfreemem(eSrc);
      PQfreemem(eSum);  PQfreemem(eMaint);PQfreemem(eLic);  PQfreemem(eMgr);
      PGresult *rb = PQexec(db_conn, "ROLLBACK;"); PQclear(rb);
      return -1;
    }
    snprintf(sql, (size_t)need + 1, insTmpl,
             upload_pk, pkg_fk,
             eMgr, eName, eVer, eArch, eSrc, eSum, eMaint, eLic, layerIndex);
    PQfreemem(eName); PQfreemem(eVer); PQfreemem(eArch); PQfreemem(eSrc);
    PQfreemem(eSum);  PQfreemem(eMaint);PQfreemem(eLic);  PQfreemem(eMgr);

    int rc = ExecInsertWithDeps(sql, depTmpl, pkg, NULL);
    free(sql);
    if (rc < 0) {
      PGresult *rb = PQexec(db_conn, "ROLLBACK;"); PQclear(rb);
      return -1;
    }
    total++;

    /* Heartbeat every 50 packages to prevent scheduler timeout */
    if (total % 50 == 0) fo_scheduler_heart(0);
  }

  result = PQexec(db_conn, "COMMIT;");
  if (fo_checkPQcommand(db_conn, result, "COMMIT", __FILE__, __LINE__)) return -1;
  PQclear(result);
  return total;
}

/* =========================================================================
 * RecordLangPackages — language packages → pkg_container_lang_installed
 * ========================================================================= */
int RecordLangPackages(FossPkgList *list, long upload_pk,
                        int pkg_fk, int layerIndex, const char *manager)
{
  if (!list || list->count == 0) return 0;

  PGresult *result = PQexec(db_conn, "BEGIN;");
  if (fo_checkPQcommand(db_conn, result, "BEGIN", __FILE__, __LINE__)) return -1;
  PQclear(result);

  int total = 0;

  static const char *insTmpl =
    "INSERT INTO pkg_container_lang_installed"
    " (upload_fk,pkg_fk,pkg_manager,pkg_name,pkg_version,"
    "  pkg_source,pkg_license,pkg_url,layer_index)"
    " VALUES (%ld,%d,%s,%s,%s,%s,%s,%s,%d) RETURNING inst_pk;";

  static const char *depTmpl =
    "INSERT INTO pkg_container_lang_dep (inst_fk,dep_name) VALUES (%d,%s);";

  for (int i = 0; i < list->count; i++) {
    FossPkgInfo *pkg = list->pkgs[i];
    if (!pkg || !pkg->name) continue;

#define ESC(s) EscapeField(db_conn, (s))
    char *eName = ESC(pkg->name);  char *eVer = ESC(pkg->version);
    char *eSrc  = ESC(pkg->source);char *eLic = ESC(pkg->license);
    char *eUrl  = ESC(pkg->url);   char *eMgr = ESC(manager);
#undef ESC

    int need = snprintf(NULL, 0, insTmpl,
                        upload_pk, pkg_fk,
                        eMgr, eName, eVer, eSrc, eLic, eUrl, layerIndex);
    char *sql = (need > 0) ? malloc((size_t)need + 1) : NULL;
    if (!sql) {
      PQfreemem(eName); PQfreemem(eVer); PQfreemem(eSrc);
      PQfreemem(eLic);  PQfreemem(eUrl); PQfreemem(eMgr);
      PGresult *rb = PQexec(db_conn, "ROLLBACK;"); PQclear(rb);
      return -1;
    }
    snprintf(sql, (size_t)need + 1, insTmpl,
             upload_pk, pkg_fk,
             eMgr, eName, eVer, eSrc, eLic, eUrl, layerIndex);
    PQfreemem(eName); PQfreemem(eVer); PQfreemem(eSrc);
    PQfreemem(eLic);  PQfreemem(eUrl); PQfreemem(eMgr);

    int rc = ExecInsertWithDeps(sql, depTmpl, pkg, NULL);
    free(sql);
    if (rc < 0) {
      PGresult *rb = PQexec(db_conn, "ROLLBACK;"); PQclear(rb);
      return -1;
    }
    total++;

    /* Heartbeat every 50 packages to prevent scheduler timeout */
    if (total % 50 == 0) fo_scheduler_heart(0);
  }

  result = PQexec(db_conn, "COMMIT;");
  if (fo_checkPQcommand(db_conn, result, "COMMIT", __FILE__, __LINE__)) return -1;
  PQclear(result);
  return total;
}

/* =========================================================================
 * ParseAndRecord — dispatch to the correct parser, then record results
 * ========================================================================= */
static int ParseAndRecord(PkgDbRef *ref, long upload_pk, int pkg_fk)
{
  /* origFilename drives sub-parser selection for managers with multiple file
   * formats (NPM: yarn.lock vs package-lock.json; PIP: three formats). */
  const char *fname = ref->origFilename ? ref->origFilename : "";

  FossPkgList *list = NULL;
  switch (ref->manager) {
    case FOSSPKG_MGR_DPKG:         list = FossPkg_ParseDpkgStatus(ref->repoPath);   break;
    case FOSSPKG_MGR_APK:          list = FossPkg_ParseApkInstalled(ref->repoPath); break;
    case FOSSPKG_MGR_RPM:
      list = FossPkg_ParseRpmSqlite(ref->repoPath);
      if (list && list->count == 0) {          /* empty → likely BerkeleyDB */
        FossPkgListFree(list);
        list = FossPkg_ParseRpmBdb(ref->repoPath);
        if (list)
          for (int i = 0; i < list->count; i++)
            if (list->pkgs[i]) list->pkgs[i]->manager = FOSSPKG_MGR_RPM_BDB;
      }
      break;
    case FOSSPKG_MGR_RPM_BDB:      list = FossPkg_ParseRpmBdb(ref->repoPath);       break;
    case FOSSPKG_MGR_NPM:
      list = strcmp(fname, "yarn.lock") == 0
               ? FossPkg_ParseYarnLock(ref->repoPath)
               : FossPkg_ParseNpmLock(ref->repoPath);
      break;
    case FOSSPKG_MGR_PIP:
      if      (strcmp(fname, "Pipfile.lock")   == 0) list = FossPkg_ParsePipfileLock(ref->repoPath);
      else if (strcmp(fname, "pyproject.toml") == 0) list = FossPkg_ParsePyprojectToml(ref->repoPath);
      else                                           list = FossPkg_ParsePipRequirements(ref->repoPath);
      break;
    case FOSSPKG_MGR_PIP_DIST_INFO: list = FossPkg_ParsePipDistInfo(ref->repoPath); break;
    case FOSSPKG_MGR_MAVEN:         list = FossPkg_ParseMavenPom(ref->repoPath);    break;
    case FOSSPKG_MGR_GO:            list = FossPkg_ParseGoMod(ref->repoPath);       break;
    case FOSSPKG_MGR_CARGO:         list = FossPkg_ParseCargoLock(ref->repoPath);   break;
    case FOSSPKG_MGR_GEM:           list = FossPkg_ParseGemfileLock(ref->repoPath); break;
    case FOSSPKG_MGR_NUGET:         list = FossPkg_ParseNugetLock(ref->repoPath);   break;
    case FOSSPKG_MGR_COMPOSER:      list = FossPkg_ParseComposerLock(ref->repoPath);break;
    default:                        list = FossPkg_ParseAuto(ref->repoPath);         break;
  }

  if (ref->isTempFile && ref->repoPath && ref->repoPath[0])
    unlink(ref->repoPath);

  if (!list) {
    LOG_WARNING("containerpkg: parse failed (manager=%s, file=%s)\n",
                FossPkg_ManagerName(ref->manager), fname);
    return 0;
  }

  LOG_NOTICE("containerpkg: parsed %d packages from layer %d (manager=%s)\n",
             list->count, ref->layerIndex, FossPkg_ManagerName(ref->manager));

  int inserted = FossPkg_IsLangManager(ref->manager)
    ? RecordLangPackages(list, upload_pk, pkg_fk,
                         ref->layerIndex, FossPkg_ManagerName(ref->manager))
    : RecordInstalledPackages(list, upload_pk, pkg_fk,
                              ref->layerIndex, FossPkg_ManagerName(ref->manager));

  FossPkgListFree(list);
  return inserted;
}

/* =========================================================================
 * ScanContainerPackages — main entry point called by RecordMetadataContainer
 * ========================================================================= */
int ScanContainerPackages(long upload_pk, int pkg_fk,
                           struct containerpkginfo *pi)
{
  LOG_NOTICE("containerpkg: ScanContainerPackages upload_pk=%ld pkg_fk=%d "
             "layerCount=%d\n",
             upload_pk, pkg_fk, (pi && pi->layers) ? pi->layerCount : -1);

  char *ut = GetUploadtreeTableName(db_conn, upload_pk);
  if (!ut) ut = strdup("uploadtree_a");

  /* Get root lft/rgt for Phase 1 tree bounds and Phase 3 root lookup */
  char SQL[MAXCMD];
  snprintf(SQL, sizeof(SQL),
    "SELECT lft,rgt FROM %s WHERE upload_fk=%ld AND parent IS NULL LIMIT 1",
    ut, upload_pk);
  PGresult *result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) {
    free(ut); return -1;
  }
  if (PQntuples(result) == 0) { PQclear(result); free(ut); return -1; }
  unsigned long rootLft = strtoul(PQgetvalue(result, 0, 0), NULL, 10);
  unsigned long rootRgt = strtoul(PQgetvalue(result, 0, 1), NULL, 10);
  PQclear(result);

  PkgDbVec vec;
  PkgDbVecInit(&vec);

  int nManifests = (pi && pi->manifestCount > 0) ? pi->manifestCount : 1;

  /* ── Phase 1: uploadtree scan ── */
  for (int mi = 0; mi < nManifests; mi++)
    FindManifestsInUploadtree(upload_pk, ut, rootLft, rootRgt, &vec, mi);
  LOG_NOTICE("containerpkg: Phase 1 found %d ref(s)\n", vec.count);

  if (pi && pi->layers && pi->layerCount > 0) {
    /*
     * Build per-layer coverage bitsets from Phase 1 results.
     * These prevent Phase 2 from re-extracting files that Phase 1 already
     * found via the uploadtree, which would produce duplicate DB rows.
     *
     *   p1OsCovered[layer]   — Phase 1 found ≥1 OS database ref for this layer
     *   p1LangCovered[layer] — Phase 1 found ≥1 lang manifest ref for this layer
     *
     * Phase 2 skips OS extraction for layers in p1OsCovered, and skips the
     * archive lang-scan for layers in p1LangCovered.  If Phase 1 only found
     * OS (but not lang) for a layer, Phase 2 still runs the lang scan for it.
     *
     * Note: Phase 1 refs have layerIndex=-1 at this point (ResolveLayerIndex
     * runs later).  We cannot use layerIndex to build the bitset yet.
     * Instead we track coverage by pfile_fk: after Phase 1 we resolve each
     * ref's layer index immediately so the bitsets are accurate.
     */
    size_t bsWords = (size_t)((pi->layerCount + 63) / 64);

    /* Resolve Phase 1 layer indices NOW so we can build coverage bitsets */
    for (int r = 0; r < vec.count; r++) {
      PkgDbRef *ref = &vec.refs[r];
      if (!ref->isTempFile && ref->pfileFk > 0 && ref->layerIndex < 0)
        ref->layerIndex = ResolveLayerIndex(upload_pk, ut,
                                            ref->uploadtreePk, ref->pfileFk, pi);
    }

    uint64_t *p1OsCovered   = calloc(bsWords, sizeof(uint64_t));
    uint64_t *p1LangCovered = calloc(bsWords, sizeof(uint64_t));
    for (int r = 0; r < vec.count; r++) {
      int li = vec.refs[r].layerIndex;
      if (li < 0 || li >= pi->layerCount) continue;
      if (FossPkg_IsLangManager(vec.refs[r].manager))
        p1LangCovered[li / 64] |= (uint64_t)1 << (li % 64);
      else
        p1OsCovered[li / 64]   |= (uint64_t)1 << (li % 64);
    }

    int osInP1 = 0;
    for (size_t w = 0; w < bsWords; w++) osInP1 += __builtin_popcountll(p1OsCovered[w]);
    if (osInP1 == 0)
      LOG_NOTICE("containerpkg: Phase 1 found no OS databases\n");

    /* ── Phase 2: blob extraction ── */
    uint64_t *blobScanned = calloc(bsWords, sizeof(uint64_t));

    for (int mi = 0; mi < nManifests; mi++)
      FindManifestsInBlobs(upload_pk, ut, pi, &vec, mi, blobScanned,
                           p1OsCovered, p1LangCovered);
    LOG_NOTICE("containerpkg: Phase 2 total: %d ref(s)\n", vec.count);

    /* Resolve layer indices for any new Phase 2 refs */
    for (int r = 0; r < vec.count; r++) {
      PkgDbRef *ref = &vec.refs[r];
      if (!ref->isTempFile && ref->pfileFk > 0 && ref->layerIndex < 0)
        ref->layerIndex = ResolveLayerIndex(upload_pk, ut,
                                            ref->uploadtreePk, ref->pfileFk, pi);
    }

    /* ── Phase 3: root-tar two-stage for layers Phase 2 could not reach ── */
    for (int mi = 0; mi < nManifests; mi++)
      FindManifestsInRootTar(upload_pk, ut, pi, &vec, mi, blobScanned, rootLft);
    LOG_NOTICE("containerpkg: Phase 3 total: %d ref(s)\n", vec.count);

    free(p1OsCovered);
    free(p1LangCovered);
    free(blobScanned);
  }

  /* Resolve any remaining unresolved layer indices */
  for (int r = 0; r < vec.count; r++) {
    PkgDbRef *ref = &vec.refs[r];
    if (!ref->isTempFile && ref->pfileFk > 0 && ref->layerIndex < 0)
      ref->layerIndex = ResolveLayerIndex(upload_pk, ut,
                                          ref->uploadtreePk, ref->pfileFk, pi);
  }

  if (vec.count == 0) {
    LOG_NOTICE("containerpkg: no package manifests found for upload %ld\n",
               upload_pk);
    PkgDbVecFree(&vec);
    free(ut);
    return 0;
  }

  int total = 0;
  for (int i = 0; i < vec.count; i++) {
    PkgDbRef *ref = &vec.refs[i];

    if (!ref->isTempFile && ref->pfileFk > 0 && ref->layerIndex < 0)
      ref->layerIndex = ResolveLayerIndex(upload_pk, ut,
                                          ref->uploadtreePk, ref->pfileFk, pi);

    int inserted = ParseAndRecord(ref, upload_pk, pkg_fk);
    PkgDbRefFree(ref);

    if (inserted < 0) {
      LOG_ERROR("containerpkg: DB insert failed at ref %d (manager=%s)\n",
                i, FossPkg_ManagerName(ref->manager));
      /* Clean up remaining temp files before returning */
      for (int j = i + 1; j < vec.count; j++) {
        if (vec.refs[j].isTempFile && vec.refs[j].repoPath)
          unlink(vec.refs[j].repoPath);
        PkgDbRefFree(&vec.refs[j]);
      }
      free(vec.refs);
      free(ut);
      return -1;
    }
    total += inserted;

    /* Heartbeat after each ref to prevent scheduler timeout on large images */
    fo_scheduler_heart(0);
  }

  free(vec.refs);
  free(ut);

  LOG_NOTICE("containerpkg: recorded %d packages total for upload %ld\n",
             total, upload_pk);
  return total;
}
