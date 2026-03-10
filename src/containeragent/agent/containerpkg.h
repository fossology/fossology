/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file containerpkg.h
 * \brief Container installed-package extraction extension for containeragent.
 *
 * This module adds OSS/dependency extraction to the containeragent by
 * scanning the unpacked container layer filesystem for OS package manager
 * databases and parsing them via libfosspkg.
 *
 * Supported databases:
 *   /var/lib/dpkg/status      — Debian / Ubuntu
 *   /lib/apk/db/installed     — Alpine Linux
 *   /var/lib/rpm/rpmdb.sqlite — RHEL/Fedora/CentOS (modern rpm)
 *   /var/lib/rpm/Packages     — RHEL/CentOS (legacy BerkeleyDB, limited)
 *
 * Database tables written:
 *   pkg_container_installed   — one row per installed package
 *   pkg_container_inst_dep    — one row per dependency of an installed package
 */

#ifndef _CONTAINERPKG_H
#define _CONTAINERPKG_H 1

#include "containeragent.h"
#include "fosspkg.h"

/* Known package database filenames to search for in the uploadtree */
#define PKGDB_DPKG_STATUS    "status"
#define PKGDB_APK_INSTALLED  "installed"
#define PKGDB_RPM_SQLITE     "rpmdb.sqlite"
#define PKGDB_RPM_PACKAGES   "Packages"

/* Maximum number of package databases expected in one upload */
#define MAX_PKGDBS           32

typedef struct {
  char          *repoPath;   ///< Heap-allocated absolute path in FOSSology repo (or temp file).
                             ///<   Assigned by strdup() at all population sites.
                             ///<   Must be freed with PkgDbRefFree() after use.
  long           pfileFk;        ///< pfile_pk in FOSSology (0 for temp files)
  long           uploadtreePk;   ///< uploadtree_pk of this specific node (0 for temp files).
                                 ///<   Required for ResolveLayerIndex to anchor the CTE to
                                 ///<   the exact uploadtree row, not just the pfile content.
                                 ///<   When the same pfile_fk appears in multiple layers
                                 ///<   (same file content, different layers), pfileFk alone
                                 ///<   is ambiguous — uploadtreePk is unique per node.
  FossPkgManager manager;   ///< Detected package manager type
  int            layerIndex; ///< Layer this database came from
  int            isTempFile; ///< 1 = repoPath is a temp file to unlink after parse
} PkgDbRef;

/**
 * \brief Assign a heap-allocated copy of \p path to \p ref->repoPath.
 *
 * Frees any previously held value before duplicating the new path.
 * Returns 0 on success, -1 if strdup fails (ENOMEM).
 */
static inline int PkgDbRefSetPath(PkgDbRef *ref, const char *path)
{
  free(ref->repoPath);
  ref->repoPath = strdup(path);
  return ref->repoPath ? 0 : -1;
}

/**
 * \brief Release the heap memory owned by a PkgDbRef.
 *
 * Safe to call on a zero-initialised struct (repoPath == NULL).
 */
static inline void PkgDbRefFree(PkgDbRef *ref)
{
  free(ref->repoPath);
  ref->repoPath = NULL;
}

/**
 * \brief Scan the uploadtree for package databases and extract packages.
 *
 * Queries the uploadtree for known package database filenames within the
 * upload, retrieves each file from the FOSSology repository, parses it
 * via libfosspkg, and writes the results to pkg_container_installed.
 *
 * \param upload_pk   Upload to scan.
 * \param pkg_fk      pkg_container.pkg_pk for this image (foreign key).
 * \return            Number of packages recorded, or -1 on DB error.
 */
int ScanContainerPackages(long upload_pk, int pkg_fk, struct containerpkginfo *pi);

/**
 * \brief Write one FossPkgList to the database.
 *
 * \param list        Parsed package list.
 * \param upload_pk   Upload context.
 * \param pkg_fk      pkg_container.pkg_pk foreign key.
 * \param layerIndex  Layer the database came from (-1 = unknown).
 * \param manager     Package manager type string (for the column).
 * \return            Number of rows inserted, or -1 on error.
 */
int RecordInstalledPackages(FossPkgList *list,
                             long         upload_pk,
                             int          pkg_fk,
                             int          layerIndex,
                             const char  *manager);

#endif /* _CONTAINERPKG_H */
