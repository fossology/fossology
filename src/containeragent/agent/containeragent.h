/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief containeragent header
 *
 * Defines data structures and function prototypes for the Container Analysis
 * agent. Supports Docker image tarballs (docker save) and OCI image layouts.
 *
 * Metadata is extracted from:
 *   - manifest.json  : image name, tag, layer list
 *   - <config>.json  : OS, arch, entrypoint, cmd, env, ports, labels
 */
#ifndef _CONTAINERAGENT_H
#define _CONTAINERAGENT_H 1

#include <stdlib.h>
#include <stdio.h>
#include <stdarg.h>
#include <string.h>
#include <ctype.h>
#include <signal.h>
#include <libgen.h>
#include <time.h>
#include <sys/wait.h>

#include <json-c/json.h>
#include <libfossology.h>

#define MAXCMD      5000
#define MAXLENGTH   256
#define MAX_LAYERS  256   ///< Maximum number of layers per image
#define MAX_ENV     512   ///< Maximum number of environment variables
#define MAX_PORTS   128   ///< Maximum number of exposed ports
#define MAX_LABELS  256   ///< Maximum number of labels

#define MIME_DOCKER  "application/vnd.docker.image.rootfs.diff.tar"
#define MIME_OCI     "application/vnd.oci.image.manifest.v1+json"

/**
 * \struct containerlayerinfo
 * \brief Holds metadata for a single image layer
 */
struct containerlayerinfo
{
  char layerId[MAXLENGTH];      ///< Layer digest / ID
  char createdBy[MAXCMD];       ///< Command that created this layer
  int  emptyLayer;              ///< 1 if this is an empty (no-op) layer
  char blobName[MAXLENGTH];     ///< Basename of the layer blob in the repo (sha256 hex)
};

/**
 * \struct containerpkginfo
 * \brief Holds all extracted metadata for a container image
 */
struct containerpkginfo
{
  /* Core identity */
  char imageName[MAXCMD];         ///< Image repository name  (e.g. "ubuntu")
  char imageTag[MAXLENGTH];       ///< Image tag              (e.g. "22.04")
  char imageId[MAXLENGTH];        ///< Full image ID / digest
  char os[MAXLENGTH];             ///< OS field from config    (e.g. "linux")
  char architecture[MAXLENGTH];   ///< Arch field from config  (e.g. "amd64")
  char variant[MAXLENGTH];        ///< Arch variant            (e.g. "v8")
  char created[MAXLENGTH];        ///< Creation timestamp (ISO 8601)
  char author[MAXCMD];            ///< Image author / maintainer label
  char description[MAXCMD];       ///< Free-form description (from labels)
  char format[MAXLENGTH];         ///< "docker" or "oci"

  /* Runtime config */
  char entrypoint[MAXCMD];        ///< ENTRYPOINT as JSON array string
  char cmd[MAXCMD];               ///< CMD as JSON array string
  char workingDir[MAXLENGTH];     ///< WORKDIR
  char user[MAXLENGTH];           ///< USER

  /* Counts */
  int layerCount;                 ///< Number of layers

  /* Dynamic arrays */
  char **envVars;                 ///< Environment variables  ("KEY=VALUE")
  int   envCount;                 ///< Number of env vars

  char **ports;                   ///< Exposed ports          ("8080/tcp")
  int   portCount;                ///< Number of exposed ports

  struct containerlabelpair {
    char *key;                    ///< Label key   (heap-allocated)
    char *val;                    ///< Label value (heap-allocated)
  } *labels_kv;                   ///< Label key/value pairs (single allocation)
  int   labelCount;               ///< Number of labels

  struct containerlayerinfo *layers;  ///< Per-layer metadata array
  /* layerCount doubles as the size of this array */

  /* FOSSology internal */
  long pFileFk;                   ///< pfile_pk of the image tarball
  char pFile[MAXCMD];             ///< pfile hash string
  long uploadPk;                  ///< upload_pk — set by ProcessUpload, used by ScanContainerPackages
};

extern int     Verbose;
extern PGconn *db_conn;

/**
 * \brief Process all container images in an upload
 * \param upload_pk  Upload primary key from the scheduler
 * \return 0 on success, -1 on failure
 */
int ProcessUpload(long upload_pk);

/**
 * \brief Extract metadata from a Docker image tarball
 * \param upload_pk  Upload primary key (used to locate manifest/config in uploadtree)
 * \param pi         Output struct to populate
 * \return 0 on success, -1 on failure
 */
int GetMetadataDocker(long upload_pk, struct containerpkginfo *pi);

/**
 * \brief Extract metadata from an OCI image layout
 * \param upload_pk  Upload primary key
 * \param pi         Output struct to populate
 * \return 0 on success, -1 on failure
 */
int GetMetadataOCI(long upload_pk, struct containerpkginfo *pi);

/**
 * \brief Parse a Docker manifest.json file
 * \param manifestPath  Absolute path to the manifest.json file in the repo
 * \param pi            Output struct to populate (imageName, imageTag, layerCount)
 * \param configFilename Output buffer to receive the config JSON filename
 * \param cfgLen        Size of configFilename buffer
 * \return 0 on success, -1 on failure
 */
int ParseDockerManifest(const char *manifestPath,
                        struct containerpkginfo *pi,
                        char *configFilename, size_t cfgLen);

/**
 * \brief Parse an OCI index.json file to get the image manifest digest
 * \param indexPath     Absolute path to the index.json file
 * \param pi            Output struct (receives imageName, imageTag, format)
 * \param manifestDigest Output buffer — receives the manifest blob digest
 *                       (e.g. "sha256:bcc663…").  Caller must then parse the
 *                       manifest blob to obtain the config blob digest.
 * \param digestLen     Size of manifestDigest buffer
 * \return 0 on success, -1 on failure
 */
int ParseOCIIndex(const char *indexPath,
                  struct containerpkginfo *pi,
                  char *manifestDigest, size_t digestLen);

/**
 * \brief Parse a Docker/OCI image config JSON file
 * \param configPath  Absolute path to the config JSON in the repo
 * \param pi          Output struct to populate
 * \return 0 on success, -1 on failure
 */
int ParseImageConfig(const char *configPath, struct containerpkginfo *pi);

/**
 * \brief Store container metadata into the database
 * \param pi  Populated containerpkginfo struct
 * \return 0 on success, -1 on failure
 */
int RecordMetadataContainer(struct containerpkginfo *pi);

/**
 * \brief Free all heap memory inside a containerpkginfo struct
 * \param pi  Struct to clean up (does not free the struct itself)
 */
void FreeContainerInfo(struct containerpkginfo *pi);

/**
 * \brief Print usage/help to stdout
 * \param Name  argv[0]
 */
void Usage(char *Name);

/* Pull in the installed-package extraction extension */
#include "containerpkg.h"

#endif /* _CONTAINERAGENT_H */
