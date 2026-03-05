/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file containerpkg.h
 * \brief Container installed-package and language-manifest extraction
 *        for containeragent.
 *
 * Changes from v1
 * ---------------
 * 1. Language ecosystem support
 *    KnownDbs[] now includes all major language-ecosystem manifest files:
 *    package-lock.json, yarn.lock, requirements.txt, Pipfile.lock,
 *    pyproject.toml, pom.xml, go.mod, Cargo.lock, Gemfile.lock,
 *    packages.lock.json, composer.lock.
 *    Results go to pkg_container_lang_installed / pkg_container_lang_dep
 *    (schema kept separate from OS packages for clean querying).
 *
 * 2. RPM BerkeleyDB (RHEL 7/8)
 *    When FossPkg_ParseRpmSqlite() returns an empty list for a file that
 *    is not sqlite3 (i.e. BerkeleyDB), ScanContainerPackages() now calls
 *    FossPkg_ParseRpmBdb() as a fallback, driving the host's rpm(1) CLI.
 *
 * 3. Multiple Docker manifests
 *    All manifest array elements (not just [0]) are processed.  Each entry
 *    may reference a different image; results are tagged with their
 *    manifest index.
 *
 * 4. Dynamic layer cap
 *    MAX_LAYERS is now a soft default.  The layers array in
 *    containerpkginfo is heap-allocated and grows dynamically via
 *    EnsureLayerCap().  MAX_LAYERS_HARD (65536) is the absolute ceiling.
 *
 * 5. Dynamic package database list
 *    MAX_PKGDBS is removed.  PkgDbRef entries are accumulated in a
 *    heap-allocated, dynamically-grown PkgDbVec instead of a fixed-size
 *    stack array.
 *
 * 6. libarchive as a required dependency
 *    The shell-tar fallback is removed.  CMakeLists.txt now requires
 *    libarchive.  This eliminates the unreliable fork/exec path and
 *    avoids shell-injection risk from paths containing special characters.
 *
 * Database tables written:
 *   pkg_container_installed     — OS packages (one row per package)
 *   pkg_container_inst_dep      — OS package dependencies
 *   pkg_container_lang_installed — Language packages (one row per package)
 *   pkg_container_lang_dep      — Language package dependencies
 */

#ifndef _CONTAINERPKG_H
#define _CONTAINERPKG_H 1

#include "containeragent.h"
#include "fosspkg.h"

/* ── soft / hard layer caps ────────────────────────────────────────────── */
#define MAX_LAYERS_INITIAL  256     ///< Initial allocation for layers[]
#define MAX_LAYERS_HARD   65536     ///< Absolute ceiling; images beyond this are truncated

/* ── coverage bitset helpers (now heap-allocated) ──────────────────────── */
/** Number of uint64_t words needed to cover MAX_LAYERS_HARD bits. */
#define COVERED_WORDS  (MAX_LAYERS_HARD / 64)

/* =========================================================================
 * PkgDbRef — reference to one located package database file
 * ========================================================================= */
typedef struct {
  char          *repoPath;     ///< Heap path in FOSSology repo or temp file.
                               ///<   Must be freed with PkgDbRefFree() after use.
  char          *origFilename; ///< Original filename (e.g. "yarn.lock", "Pipfile.lock").
                               ///<   Required for sub-parser dispatch in ParseAndRecord
                               ///<   because repoPath is a hash path with no extension.
                               ///<   Heap-allocated; freed by PkgDbRefFree().
  long           pfileFk;      ///< pfile_pk (0 for temp files)
  long           uploadtreePk; ///< uploadtree_pk (0 for temp files)
  FossPkgManager manager;      ///< Detected package manager type
  int            layerIndex;   ///< Layer this database came from (-1 = unknown)
  int            manifestIdx;  ///< Docker manifest array index (for multi-manifest)
  int            isTempFile;   ///< 1 = unlink repoPath after parse
} PkgDbRef;

static inline int PkgDbRefSetPath(PkgDbRef *ref, const char *path)
{
  free(ref->repoPath);
  ref->repoPath = strdup(path);
  return ref->repoPath ? 0 : -1;
}

static inline void PkgDbRefFree(PkgDbRef *ref)
{
  free(ref->repoPath);
  ref->repoPath = NULL;
  free(ref->origFilename);
  ref->origFilename = NULL;
}

/* =========================================================================
 * PkgDbVec — dynamically-grown vector of PkgDbRef
 * ========================================================================= */
typedef struct {
  PkgDbRef *refs;   ///< Heap array
  int        count; ///< Number of valid entries
  int        cap;   ///< Allocated capacity
} PkgDbVec;

/** Initialise an empty PkgDbVec (stack-allocated wrapper, heap data). */
static inline void PkgDbVecInit(PkgDbVec *v)
{
  v->refs  = NULL;
  v->count = 0;
  v->cap   = 0;
}

/**
 * \brief Append a zero-initialised PkgDbRef to v and return a pointer to it.
 * Returns NULL on OOM.
 */
static inline PkgDbRef *PkgDbVecAlloc(PkgDbVec *v)
{
  if (v->count >= v->cap) {
    int newCap = v->cap ? v->cap * 2 : 32;
    PkgDbRef *tmp = realloc(v->refs, (size_t)newCap * sizeof(PkgDbRef));
    if (!tmp) {
      LOG_ERROR("PkgDbVec: realloc failed\n");
      return NULL;
    }
    v->refs = tmp;
    v->cap  = newCap;
  }
  PkgDbRef *ref = &v->refs[v->count++];
  memset(ref, 0, sizeof(*ref));
  ref->layerIndex  = -1;
  ref->manifestIdx =  0;
  return ref;
}

/** Free the heap data owned by a PkgDbVec (does NOT free the struct itself). */
static inline void PkgDbVecFree(PkgDbVec *v)
{
  for (int i = 0; i < v->count; i++) PkgDbRefFree(&v->refs[i]);
  free(v->refs);
  v->refs  = NULL;
  v->count = 0;
  v->cap   = 0;
}

/* =========================================================================
 * Public API
 * ========================================================================= */

/**
 * \brief Scan the container upload for all known package manifests.
 *
 * Runs three phases (uploadtree scan → blob extraction → root-tar
 * two-stage) to locate OS package databases and language ecosystem
 * manifests, then parses them and writes results to the database.
 *
 * \param upload_pk  Upload to scan.
 * \param pkg_fk     pkg_container.pkg_pk foreign key.
 * \param pi         Container metadata (layers, format, etc.).
 * \return           Total packages recorded across all managers, or -1 on DB error.
 */
int ScanContainerPackages(long upload_pk, int pkg_fk,
                           struct containerpkginfo *pi);

/**
 * \brief Write one FossPkgList of OS packages to pkg_container_installed.
 *
 * \param list        Parsed package list.
 * \param upload_pk   Upload context.
 * \param pkg_fk      pkg_container.pkg_pk foreign key.
 * \param layerIndex  Layer the database came from (-1 = unknown).
 * \param manager     Package manager name string.
 * \return            Rows inserted, or -1 on error.
 */
int RecordInstalledPackages(FossPkgList *list,
                             long         upload_pk,
                             int          pkg_fk,
                             int          layerIndex,
                             const char  *manager);

/**
 * \brief Write one FossPkgList of language packages to
 *        pkg_container_lang_installed.
 *
 * \param list        Parsed package list.
 * \param upload_pk   Upload context.
 * \param pkg_fk      pkg_container.pkg_pk foreign key.
 * \param layerIndex  Layer index (-1 = unknown).
 * \param manager     Package manager name string (e.g. "npm").
 * \return            Rows inserted, or -1 on error.
 */
int RecordLangPackages(FossPkgList *list,
                        long         upload_pk,
                        int          pkg_fk,
                        int          layerIndex,
                        const char  *manager);

#endif /* _CONTAINERPKG_H */
