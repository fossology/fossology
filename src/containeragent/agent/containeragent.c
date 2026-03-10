/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors

 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file
 * \brief Container Analysis agent — core implementation
 *
 * Extracts metadata from Docker image tarballs and OCI image layouts after
 * they have been unpacked by the ununpack agent.  The agent queries the
 * uploadtree for known metadata filenames (manifest.json, index.json,
 * <config>.json) and reads them from the FOSSology file repository, exactly
 * as pkgagent reads a Debian control file.
 *
 */

#include "containeragent.h"

int    Verbose  = 0;
PGconn *db_conn = NULL;

/**
 * \brief Read the entire contents of a file into a malloc'd buffer.
 * \param path  File to read
 * \return Null-terminated string on success (caller must free), NULL on error.
 */
static char *ReadFileToString(const char *path)
{
  FILE *fp = fopen(path, "r");
  if (!fp) {
    LOG_ERROR("containeragent: cannot open %s\n", path);
    return NULL;
  }
  fseek(fp, 0, SEEK_END);
  long sz = ftell(fp);
  rewind(fp);

  char *buf = malloc(sz + 1);
  if (!buf) { fclose(fp); return NULL; }

  if (fread(buf, 1, sz, fp) != (size_t)sz) {
    free(buf);
    fclose(fp);
    LOG_ERROR("containeragent: short read on %s\n", path);
    return NULL;
  }
  buf[sz] = '\0';
  fclose(fp);
  return buf;
}

/**
 * \brief Safely copy a JSON string value into a fixed-size destination buffer.
 * \param dst    Destination char array
 * \param dstLen Size of dst
 * \param obj    JSON object to read from
 * \param key    Key name inside obj
 */
static void JsonStrCopy(char *dst, size_t dstLen,
                        struct json_object *obj, const char *key)
{
  struct json_object *val = NULL;
  if (json_object_object_get_ex(obj, key, &val) && val)
  {
    const char *s = json_object_get_string(val);
    if (s) strncpy(dst, s, dstLen - 1);
  }
}

/**
 * \brief Append a string to a dynamic char* array, growing the array.
 * \param arr     Pointer-to-pointer of the array
 * \param count   Current element count (incremented on success)
 * \param maxCnt  Hard cap — silently drops entries beyond this
 * \param value   String to append (strdup'd)
 */
static void AppendStrArray(char ***arr, int *count, int maxCnt,
                           const char *value)
{
  if (*count >= maxCnt) return;
  char **tmp = realloc(*arr, (*count + 1) * sizeof(char *));
  if (!tmp) {
    /* realloc failure: original *arr still valid, drop this entry */
    LOG_WARNING("containeragent: AppendStrArray realloc failed (count=%d) — entry dropped\n",
                *count);
    return;
  }
  *arr = tmp;
  (*arr)[*count] = strdup(value ? value : "");
  (*count)++;
}

/* =========================================================================
 * PopulateLayerBlobNames
 * ========================================================================= */
static void PopulateLayerBlobNames(const char *manifestPath,
                                   struct containerpkginfo *pi)
{
  if (!manifestPath || !pi || !pi->layers) return;

  char *raw = ReadFileToString(manifestPath);
  if (!raw) return;

  struct json_object *root = json_tokener_parse(raw);
  free(raw);
  if (!root) return;

  if (json_object_get_type(root) != json_type_array ||
      json_object_array_length(root) == 0) {
    json_object_put(root);
    return;
  }

  struct json_object *elem = json_object_array_get_idx(root, 0);
  if (!elem) { json_object_put(root); return; }

  struct json_object *layersArr = NULL;
  if (!json_object_object_get_ex(elem, "Layers", &layersArr) || !layersArr ||
      json_object_get_type(layersArr) != json_type_array) {
    json_object_put(root);
    return;
  }

  int blobCount = (int)json_object_array_length(layersArr);
  int blobIdx   = 0;  /* index into Layers[] */

  for (int li = 0; li < pi->layerCount && blobIdx < blobCount && li < MAX_LAYERS; li++) {
    if (pi->layers[li].emptyLayer)
      continue;  /* no Layers[] entry for empty_layer history entries */

    struct json_object *le = json_object_array_get_idx(layersArr, blobIdx);
    blobIdx++;
    if (!le) continue;
    const char *lpath = json_object_get_string(le);
    if (!lpath || !*lpath) continue;
    strncpy(pi->layers[li].blobName, lpath,
            sizeof(pi->layers[li].blobName) - 1);
    pi->layers[li].blobName[sizeof(pi->layers[li].blobName) - 1] = '\0';
  }

  json_object_put(root);
}

/* =========================================================================
 * ParseDockerManifest
 * ========================================================================= */
int ParseDockerManifest(const char *manifestPath,
                        struct containerpkginfo *pi,
                        char *configFilename, size_t cfgLen)
{
  char *raw = ReadFileToString(manifestPath);
  if (!raw) return -1;

  struct json_object *root = json_tokener_parse(raw);
  free(raw);
  if (!root) {
    LOG_ERROR("containeragent: failed to parse %s as JSON\n", manifestPath);
    return -1;
  }

  /* manifest.json is a JSON array — json-c asserts on non-array, so guard type */
  if (json_object_get_type(root) != json_type_array) {
    LOG_ERROR("containeragent: manifest.json is not a JSON array (got type %d)\n",
              (int)json_object_get_type(root));
    json_object_put(root);
    return -1;
  }

  if (json_object_array_length(root) == 0) {
    LOG_ERROR("containeragent: manifest.json array is empty\n");
    json_object_put(root);
    return -1;
  }

  struct json_object *elem = json_object_array_get_idx(root, 0);
  if (!elem) {
    LOG_ERROR("containeragent: manifest.json array element[0] is NULL\n");
    json_object_put(root);
    return -1;
  }

  struct json_object *cfgObj = NULL;
  if (json_object_object_get_ex(elem, "Config", &cfgObj) && cfgObj)
    strncpy(configFilename, json_object_get_string(cfgObj), cfgLen - 1);

  struct json_object *tags = NULL;
  if (json_object_object_get_ex(elem, "RepoTags", &tags) && tags &&
      json_object_get_type(tags) == json_type_array &&
      json_object_array_length(tags) > 0)
  {
    const char *tag0 = json_object_get_string(
                         json_object_array_get_idx(tags, 0));
    if (tag0)
    {
      char tmp[MAXCMD];
      snprintf(tmp, sizeof(tmp), "%s", tag0);
      char *colon = strrchr(tmp, ':');
      if (colon) {
        *colon = '\0';
        snprintf(pi->imageName, sizeof(pi->imageName), "%s", tmp);
        snprintf(pi->imageTag,  sizeof(pi->imageTag),  "%s", colon + 1);
      } else {
        snprintf(pi->imageName, sizeof(pi->imageName), "%s", tmp);
        snprintf(pi->imageTag,  sizeof(pi->imageTag),  "%s", "latest");
      }
    }
  }

  /* layer count — blobNames filled later by PopulateLayerBlobNames */
  struct json_object *layersArr = NULL;
  if (json_object_object_get_ex(elem, "Layers", &layersArr) && layersArr &&
      json_object_get_type(layersArr) == json_type_array)
    pi->layerCount = (int)json_object_array_length(layersArr);

  strncpy(pi->format, "docker", sizeof(pi->format) - 1);

  json_object_put(root);
  return 0;
}

/* =========================================================================
 * ParseOCIIndex
 * ========================================================================= */
int ParseOCIIndex(const char *indexPath,
                  struct containerpkginfo *pi,
                  char *manifestDigest, size_t digestLen)
{
  char *raw = ReadFileToString(indexPath);
  if (!raw) return -1;

  struct json_object *root = json_tokener_parse(raw);
  free(raw);
  if (!root) {
    LOG_ERROR("containeragent: failed to parse OCI index %s\n", indexPath);
    return -1;
  }

  /* "digest" is the manifest blob — not the config; GetMetadataOCI resolves further */
  struct json_object *manifests = NULL;
  if (!json_object_object_get_ex(root, "manifests", &manifests) ||
      !manifests ||
      json_object_get_type(manifests) != json_type_array ||
      json_object_array_length(manifests) == 0)
  {
    LOG_ERROR("containeragent: OCI index.json has no valid 'manifests' array\n");
    json_object_put(root);
    return -1;
  }

  struct json_object *m0 = json_object_array_get_idx(manifests, 0);

  struct json_object *digestObj = NULL;
  if (json_object_object_get_ex(m0, "digest", &digestObj) && digestObj)
    strncpy(manifestDigest, json_object_get_string(digestObj), digestLen - 1);

  struct json_object *ann = NULL;
  if (json_object_object_get_ex(m0, "annotations", &ann) && ann)
  {
    struct json_object *refName = NULL;
    if (json_object_object_get_ex(ann,
          "org.opencontainers.image.ref.name", &refName) && refName)
    {
      const char *ref = json_object_get_string(refName);
      char tmp[MAXCMD];
      snprintf(tmp, sizeof(tmp), "%s", ref);
      char *colon = strrchr(tmp, ':');
      if (colon) {
        *colon = '\0';
        snprintf(pi->imageName, sizeof(pi->imageName), "%s", tmp);
        snprintf(pi->imageTag,  sizeof(pi->imageTag),  "%s", colon + 1);
      } else {
        snprintf(pi->imageName, sizeof(pi->imageName), "%s", tmp);
        snprintf(pi->imageTag,  sizeof(pi->imageTag),  "%s", "latest");
      }
    }
  }

  strncpy(pi->format, "oci", sizeof(pi->format) - 1);

  json_object_put(root);
  return 0;
}

/* =========================================================================
 * ParseImageConfig
 * ========================================================================= */
int ParseImageConfig(const char *configPath, struct containerpkginfo *pi)
{
  char *raw = ReadFileToString(configPath);
  if (!raw) return -1;

  struct json_object *root = json_tokener_parse(raw);
  free(raw);
  if (!root) {
    LOG_ERROR("containeragent: failed to parse config JSON %s\n", configPath);
    return -1;
  }

  JsonStrCopy(pi->os,           sizeof(pi->os),           root, "os");
  JsonStrCopy(pi->architecture, sizeof(pi->architecture), root, "architecture");
  JsonStrCopy(pi->variant,      sizeof(pi->variant),      root, "variant");
  JsonStrCopy(pi->created,      sizeof(pi->created),      root, "created");
  JsonStrCopy(pi->author,       sizeof(pi->author),       root, "author");

  JsonStrCopy(pi->imageId,      sizeof(pi->imageId),      root, "id");

  struct json_object *cfg = NULL;
  if (!json_object_object_get_ex(root, "config", &cfg) || !cfg)
  {
    /* OCI puts config at top level */
    cfg = root;
  }

  JsonStrCopy(pi->workingDir, sizeof(pi->workingDir), cfg, "WorkingDir");
  JsonStrCopy(pi->user,       sizeof(pi->user),       cfg, "User");

  /* Entrypoint: JSON array -> flattened string */
  struct json_object *ep = NULL;
  if (json_object_object_get_ex(cfg, "Entrypoint", &ep) && ep &&
      json_object_get_type(ep) == json_type_array)
  {
    char buf[MAXCMD] = "";
    int n = (int)json_object_array_length(ep);
    for (int i = 0; i < n; i++) {
      struct json_object *el = json_object_array_get_idx(ep, i);
      const char *s = el ? json_object_get_string(el) : NULL;
      if (!s) s = "";
      if (i > 0) strncat(buf, " ", sizeof(buf) - strlen(buf) - 1);
      strncat(buf, s, sizeof(buf) - strlen(buf) - 1);
    }
    snprintf(pi->entrypoint, sizeof(pi->entrypoint), "%s", buf);
  }

  /* Cmd: JSON array -> flattened string */
  struct json_object *cmd = NULL;
  if (json_object_object_get_ex(cfg, "Cmd", &cmd) && cmd &&
      json_object_get_type(cmd) == json_type_array)
  {
    char buf[MAXCMD] = "";
    int n = (int)json_object_array_length(cmd);
    for (int i = 0; i < n; i++) {
      struct json_object *el = json_object_array_get_idx(cmd, i);
      const char *s = el ? json_object_get_string(el) : NULL;
      if (!s) s = "";
      if (i > 0) strncat(buf, " ", sizeof(buf) - strlen(buf) - 1);
      strncat(buf, s, sizeof(buf) - strlen(buf) - 1);
    }
    snprintf(pi->cmd, sizeof(pi->cmd), "%s", buf);
  }

  /* Env: array of "KEY=VALUE" strings */
  struct json_object *env = NULL;
  if (json_object_object_get_ex(cfg, "Env", &env) && env &&
      json_object_get_type(env) == json_type_array)
  {
    int n = (int)json_object_array_length(env);
    for (int i = 0; i < n; i++) {
      struct json_object *el = json_object_array_get_idx(env, i);
      const char *s = el ? json_object_get_string(el) : NULL;
      if (s)
        AppendStrArray(&pi->envVars, &pi->envCount, MAX_ENV, s);
    }
  }

  /* ExposedPorts: object keys are "port/proto" */
  struct json_object *ports = NULL;
  if (json_object_object_get_ex(cfg, "ExposedPorts", &ports) && ports)
  {
    json_object_object_foreach(ports, portKey, portVal)
    {
      (void)portVal;
      AppendStrArray(&pi->ports, &pi->portCount, MAX_PORTS, portKey);
    }
  }

  /* Labels */
  struct json_object *labels = NULL;
  if (json_object_object_get_ex(cfg, "Labels", &labels) && labels)
  {
    json_object_object_foreach(labels, lKey, lVal)
    {
      if (strcmp(lKey, "org.opencontainers.image.description") == 0 &&
          pi->description[0] == '\0')
        strncpy(pi->description,
                json_object_get_string(lVal), sizeof(pi->description) - 1);

      if (strcmp(lKey, "maintainer") == 0 && pi->author[0] == '\0')
        strncpy(pi->author,
                json_object_get_string(lVal), sizeof(pi->author) - 1);

      if (pi->labelCount < MAX_LABELS) {
        /* single realloc for key+val pair avoids double-free if two reallocs alias */
        struct containerlabelpair *tmp =
          realloc(pi->labels_kv,
                  (pi->labelCount + 1) * sizeof(struct containerlabelpair));
        if (!tmp) {
          LOG_WARNING("containeragent: label realloc OOM — label entry dropped\n");
        } else {
          pi->labels_kv = tmp;
          const char *safeKey = lKey ? lKey : "";
          const char *safeVal = lVal ? json_object_get_string(lVal) : "";
          char *dupKey = strdup(safeKey);
          char *dupVal = strdup(safeVal ? safeVal : "");
          if (!dupKey || !dupVal) {
            free(dupKey);
            free(dupVal);
            LOG_WARNING("containeragent: label strdup OOM — label entry dropped\n");
          } else {
            pi->labels_kv[pi->labelCount].key = dupKey;
            pi->labels_kv[pi->labelCount].val = dupVal;
            pi->labelCount++;
          }
        }
      }
    }
  }

  /* history: allocate layers, record created_by and empty_layer per entry */
  struct json_object *history = NULL;
  if (json_object_object_get_ex(root, "history", &history) && history &&
      json_object_get_type(history) == json_type_array)
  {
    int n = (int)json_object_array_length(history);
    /* always MAX_LAYERS: loop skips (empty_layer) can make layerIdx < i, causing OOB if sized by n */
    int allocCount = MAX_LAYERS;
    pi->layers = calloc(allocCount, sizeof(struct containerlayerinfo));

    int layerIdx = 0;
    for (int i = 0; i < n && layerIdx < allocCount; i++)
    {
      struct json_object *h = json_object_array_get_idx(history, i);
      if (!h) continue;   /* skip null elements — malformed history array */

      struct json_object *emptyObj = NULL;
      int isEmpty = 0;
      if (json_object_object_get_ex(h, "empty_layer", &emptyObj) && emptyObj)
        isEmpty = json_object_get_boolean(emptyObj);

      pi->layers[layerIdx].emptyLayer = isEmpty;

      struct json_object *createdBy = NULL;
      if (json_object_object_get_ex(h, "created_by", &createdBy) && createdBy)
        strncpy(pi->layers[layerIdx].createdBy,
                json_object_get_string(createdBy),
                sizeof(pi->layers[layerIdx].createdBy) - 1);

      /* Layer ID comes from the Layers array in manifest; here we use index */
      snprintf(pi->layers[layerIdx].layerId,
               sizeof(pi->layers[layerIdx].layerId), "layer-%d", i);

      layerIdx++;
    }
    /* sync layerCount to actual written entries — manifest count may differ due to empty layers */
    pi->layerCount = layerIdx;
  }

  json_object_put(root);
  return 0;
}

/* =========================================================================
 * GetMetadataDocker
 * ========================================================================= */
int GetMetadataDocker(long upload_pk, struct containerpkginfo *pi)
{
  char SQL[MAXCMD];
  PGresult *result;
  unsigned long lft, rgt;
  char *uploadtree_tablename;
  char configFilename[MAXLENGTH] = "";

  if (!upload_pk) return -1;

  uploadtree_tablename = GetUploadtreeTableName(db_conn, upload_pk);
  if (!uploadtree_tablename)
    uploadtree_tablename = strdup("uploadtree_a");

  /* Get the upload root bounds — search all descendants of the upload */
  snprintf(SQL, sizeof(SQL),
    "SELECT lft, rgt FROM %s "
    "WHERE upload_fk = %ld AND parent IS NULL LIMIT 1",
    uploadtree_tablename, upload_pk);

  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) {
    free(uploadtree_tablename);
    return -1;
  }
  if (PQntuples(result) == 0) {
    LOG_ERROR("containeragent: no root uploadtree row for upload %ld\n",
              upload_pk);
    PQclear(result);
    free(uploadtree_tablename);
    return -1;
  }
  lft = strtoul(PQgetvalue(result, 0, 0), NULL, 10);
  rgt = strtoul(PQgetvalue(result, 0, 1), NULL, 10);
  PQclear(result);

  /* Locate manifest.json inside the unpacked image */
  snprintf(SQL, sizeof(SQL),
    "SELECT pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size "
    "FROM pfile "
    "INNER JOIN %s ON pfile_pk = pfile_fk "
    "WHERE upload_fk = %ld AND lft > %ld AND rgt < %ld "
    "AND ufile_name = 'manifest.json' LIMIT 1",
    uploadtree_tablename, upload_pk, lft, rgt);

  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) {
    free(uploadtree_tablename);
    return -1;
  }
  if (PQntuples(result) == 0) {
    LOG_ERROR("containeragent: manifest.json not found for upload %ld\n",
              upload_pk);
    PQclear(result);
    free(uploadtree_tablename);
    return -1;
  }

  char *manifestPfile = PQgetvalue(result, 0, 0);
  char *manifestRepo  = fo_RepMkPath("files", manifestPfile);
  PQclear(result);

  if (!manifestRepo) {
    LOG_FATAL("containeragent: fo_RepMkPath failed for manifest\n");
    free(uploadtree_tablename);
    return -1;
  }

  /* keep copy for PopulateLayerBlobNames — manifestRepo is freed below */
  char manifestRepo2[MAXCMD] = "";
  strncpy(manifestRepo2, manifestRepo, sizeof(manifestRepo2) - 1);

  /* Parse manifest.json */
  if (ParseDockerManifest(manifestRepo, pi, configFilename,
                          sizeof(configFilename)) != 0)
  {
    free(manifestRepo);
    free(uploadtree_tablename);
    return -1;
  }
  free(manifestRepo);

  if (configFilename[0] == '\0') {
    LOG_ERROR("containeragent: manifest.json has no Config entry\n");
    free(uploadtree_tablename);
    return -1;
  }

  /* basename() may modify its arg in-place — use a copy */
  char configFilenameCopy[MAXLENGTH];
  strncpy(configFilenameCopy, configFilename, sizeof(configFilenameCopy) - 1);
  configFilenameCopy[sizeof(configFilenameCopy) - 1] = '\0';
  char *cfgBase = basename(configFilenameCopy);

  /* Locate the config JSON — ufile_name is the basename of configFilename */
  snprintf(SQL, sizeof(SQL),
    "SELECT pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size "
    "FROM pfile "
    "INNER JOIN %s ON pfile_pk = pfile_fk "
    "WHERE upload_fk = %ld AND lft > %ld AND rgt < %ld "
    "AND ufile_name = '%s' LIMIT 1",
    uploadtree_tablename, upload_pk, lft, rgt, cfgBase);

  free(uploadtree_tablename);

  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) return -1;
  if (PQntuples(result) == 0) {
    LOG_ERROR("containeragent: config JSON '%s' not found\n", cfgBase);
    PQclear(result);
    return -1;
  }

  char *cfgPfile = PQgetvalue(result, 0, 0);
  char *cfgRepo  = fo_RepMkPath("files", cfgPfile);
  PQclear(result);

  if (!cfgRepo) {
    LOG_FATAL("containeragent: fo_RepMkPath failed for config JSON\n");
    return -1;
  }

  int rc = ParseImageConfig(cfgRepo, pi);
  free(cfgRepo);

  /* back-fill blobNames from manifest Layers[] after ParseImageConfig allocated layers */
  if (rc == 0 && pi->layers && manifestRepo2[0] != '\0') {
    PopulateLayerBlobNames(manifestRepo2, pi);
  }

  return rc;
}

/* =========================================================================
 * GetMetadataOCI
 * ========================================================================= */
int GetMetadataOCI(long upload_pk, struct containerpkginfo *pi)
{
  char SQL[MAXCMD];
  PGresult *result;
  unsigned long lft, rgt;
  char *uploadtree_tablename;
  char manifestDigest[MAXLENGTH] = "";

  if (!upload_pk) return -1;

  uploadtree_tablename = GetUploadtreeTableName(db_conn, upload_pk);
  if (!uploadtree_tablename)
    uploadtree_tablename = strdup("uploadtree_a");

  /* Get the upload root bounds */
  snprintf(SQL, sizeof(SQL),
    "SELECT lft, rgt FROM %s "
    "WHERE upload_fk = %ld AND parent IS NULL LIMIT 1",
    uploadtree_tablename, upload_pk);

  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) {
    free(uploadtree_tablename);
    return -1;
  }
  if (PQntuples(result) == 0) {
    PQclear(result);
    free(uploadtree_tablename);
    return -1;
  }

  lft = strtoul(PQgetvalue(result, 0, 0), NULL, 10);
  rgt = strtoul(PQgetvalue(result, 0, 1), NULL, 10);
  PQclear(result);

  /* Locate index.json */
  snprintf(SQL, sizeof(SQL),
    "SELECT pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size "
    "FROM pfile "
    "INNER JOIN %s ON pfile_pk = pfile_fk "
    "WHERE upload_fk = %ld AND lft > %ld AND rgt < %ld "
    "AND ufile_name = 'index.json' LIMIT 1",
    uploadtree_tablename, upload_pk, lft, rgt);

  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) {
    free(uploadtree_tablename);
    return -1;
  }
  if (PQntuples(result) == 0) {
    LOG_ERROR("containeragent: index.json not found for upload %ld\n",
              upload_pk);
    PQclear(result);
    free(uploadtree_tablename);
    return -1;
  }

  char *idxPfile = PQgetvalue(result, 0, 0);
  char *idxRepo  = fo_RepMkPath("files", idxPfile);
  PQclear(result);
  if (!idxRepo) { free(uploadtree_tablename); return -1; }

  if (ParseOCIIndex(idxRepo, pi, manifestDigest, sizeof(manifestDigest)) != 0) {
    free(idxRepo);
    free(uploadtree_tablename);
    return -1;
  }
  free(idxRepo);

  if (manifestDigest[0] == '\0') { free(uploadtree_tablename); return -1; }

  /* Level 1: strip "sha256:" prefix and look up manifest blob */
  const char *manifestHex = strstr(manifestDigest, ":");
  if (manifestHex) manifestHex++;
  else             manifestHex = manifestDigest;

  snprintf(SQL, sizeof(SQL),
    "SELECT pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size "
    "FROM pfile "
    "INNER JOIN %s ON pfile_pk = pfile_fk "
    "WHERE upload_fk = %ld AND lft > %ld AND rgt < %ld "
    "AND ufile_name = '%s' LIMIT 1",
    uploadtree_tablename, upload_pk, lft, rgt, manifestHex);

  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) {
    free(uploadtree_tablename);
    return -1;
  }
  if (PQntuples(result) == 0) {
    LOG_ERROR("containeragent: OCI manifest blob '%s' not found\n", manifestHex);
    PQclear(result);
    free(uploadtree_tablename);
    return -1;
  }

  char *mfPfile = PQgetvalue(result, 0, 0);
  char *mfRepo  = fo_RepMkPath("files", mfPfile);
  PQclear(result);
  if (!mfRepo) { free(uploadtree_tablename); return -1; }

  /* Level 2: parse manifest blob — extract config digest and layer digests */
  char configDigest[MAXLENGTH] = "";
  char ociLayerDigests[MAX_LAYERS][MAXLENGTH];
  int  ociLayerCount = 0;
  memset(ociLayerDigests, 0, sizeof(ociLayerDigests));
  {
    char *raw = ReadFileToString(mfRepo);
    free(mfRepo);
    if (!raw) { free(uploadtree_tablename); return -1; }

    struct json_object *mfRoot = json_tokener_parse(raw);
    free(raw);
    if (!mfRoot) {
      LOG_ERROR("containeragent: failed to parse OCI manifest blob '%s'\n",
                manifestHex);
      free(uploadtree_tablename);
      return -1;
    }

    /* Extract config digest */
    struct json_object *cfgObj = NULL;
    if (json_object_object_get_ex(mfRoot, "config", &cfgObj) && cfgObj) {
      struct json_object *dgst = NULL;
      if (json_object_object_get_ex(cfgObj, "digest", &dgst) && dgst)
        strncpy(configDigest, json_object_get_string(dgst),
                sizeof(configDigest) - 1);
    }

    /* Extract layer digests and count */
    struct json_object *layersArr = NULL;
    if (json_object_object_get_ex(mfRoot, "layers", &layersArr) && layersArr &&
        json_object_get_type(layersArr) == json_type_array) {
      int nlayers = (int)json_object_array_length(layersArr);
      if (pi->layerCount == 0)
        pi->layerCount = nlayers;

      /* collect layer hex digests; written into blobName after ParseImageConfig below */
      for (int _li = 0; _li < nlayers && _li < MAX_LAYERS; _li++) {
        struct json_object *le = json_object_array_get_idx(layersArr, _li);
        if (!le) continue;
        struct json_object *dgstObj = NULL;
        if (!json_object_object_get_ex(le, "digest", &dgstObj) || !dgstObj)
          continue;
        const char *dstr = json_object_get_string(dgstObj);
        if (!dstr) continue;
        /* strip "sha256:" prefix */
        const char *colon = strchr(dstr, ':');
        const char *hex   = colon ? colon + 1 : dstr;
        strncpy(ociLayerDigests[ociLayerCount], hex,
                sizeof(ociLayerDigests[ociLayerCount]) - 1);
        ociLayerCount++;
      }
    }

    json_object_put(mfRoot);
  }

  if (configDigest[0] == '\0') {
    LOG_ERROR("containeragent: OCI manifest has no config.digest\n");
    free(uploadtree_tablename);
    return -1;
  }

  /* Level 3: look up and parse config blob */
  const char *cfgHex = strstr(configDigest, ":");
  if (cfgHex) cfgHex++;
  else        cfgHex = configDigest;

  snprintf(SQL, sizeof(SQL),
    "SELECT pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size "
    "FROM pfile "
    "INNER JOIN %s ON pfile_pk = pfile_fk "
    "WHERE upload_fk = %ld AND lft > %ld AND rgt < %ld "
    "AND ufile_name = '%s' LIMIT 1",
    uploadtree_tablename, upload_pk, lft, rgt, cfgHex);

  free(uploadtree_tablename);

  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) return -1;
  if (PQntuples(result) == 0) {
    LOG_ERROR("containeragent: OCI config blob '%s' not found\n", cfgHex);
    PQclear(result);
    return -1;
  }

  char *cfgPfile = PQgetvalue(result, 0, 0);
  char *cfgRepo  = fo_RepMkPath("files", cfgPfile);
  PQclear(result);
  if (!cfgRepo) return -1;

  int rc = ParseImageConfig(cfgRepo, pi);
  free(cfgRepo);

  /* back-fill OCI layer digests into blobName, skipping empty_layer entries */
  if (rc == 0 && pi->layers && ociLayerCount > 0) {
    int ociIdx = 0;  /* index into ociLayerDigests[] */
    for (int _li = 0; _li < pi->layerCount && ociIdx < ociLayerCount; _li++) {
      if (pi->layers[_li].emptyLayer)
        continue;  /* skip empty layers — they have no blob */
      strncpy(pi->layers[_li].blobName,
              ociLayerDigests[ociIdx],
              sizeof(pi->layers[_li].blobName) - 1);
      pi->layers[_li].blobName[sizeof(pi->layers[_li].blobName) - 1] = '\0';
      ociIdx++;
    }
  }

  return rc;
}

/* =========================================================================
 * RecordMetadataContainer
 * ========================================================================= */

/**
 * \brief Helper: escape a C string for safe embedding in a PostgreSQL query.
 *
 * Uses PQescapeLiteral(), which produces a fully-quoted, escaped string
 * (e.g. 'foo''bar') that is safe against any input including single quotes,
 * backslashes, and NUL bytes.  The returned pointer must be freed with
 * PQfreemem() after use.
 *
 * On failure (OOM or invalid connection) returns a strdup of the string "''"
 * so callers always get a non-NULL value and the INSERT degrades gracefully
 * to an empty string rather than crashing.
 *
 * \param conn  Active PostgreSQL connection
 * \param str   Input C string (may contain any characters)
 * \return      Heap-allocated escaped literal; caller must PQfreemem() it.
 */
static char *EscapeLit(PGconn *conn, const char *str)
{
  if (!str) str = "";
  char *esc = PQescapeLiteral(conn, str, strlen(str));
  if (!esc) {
    /* PQescapeLiteral failed (extremely unlikely); fall back to empty string */
    LOG_WARNING("containeragent: PQescapeLiteral failed for string (len=%zu) — "
                "storing empty string\n", strlen(str));
    esc = strdup("''");
  }
  return esc;
}

/**
 * \brief Build a heap-allocated SQL string from a printf-style template.
 *
 * This is the dynamic replacement for the old fixed-size stack buffer
 * (previously `char SQL[300 + 14 * MAXCMD * 2]`).  Instead of guessing a
 * worst-case size at compile time, BuildSQL() measures the exact space needed
 * at runtime using vsnprintf's dry-run mode (passing NULL / size 0 returns the
 * number of bytes that *would* have been written), then allocates precisely
 * that much on the heap.
 *
 * Benefits over a fixed stack buffer:
 *  - Adding or removing fields in any INSERT never requires updating a magic
 *    number — the size is always derived from the actual content.
 *  - No silent truncation: if snprintf would have truncated, we catch it.
 *  - Stack usage is constant and small regardless of field count or content.
 *
 * \param fmt   printf-style format string (the SQL template)
 * \param ...   Arguments matching the format string
 * \return      Heap-allocated, NUL-terminated SQL string on success.
 *              NULL on OOM — caller must treat this as a fatal build error.
 *              Caller must free() the returned pointer.
 */
static char *BuildSQL(const char *fmt, ...) __attribute__((format(printf, 1, 2)));
static char *BuildSQL(const char *fmt, ...)
{
  va_list ap;

  /* --- Pass 1: dry-run to measure the exact required length --- */
  va_start(ap, fmt);
  int needed = vsnprintf(NULL, 0, fmt, ap);
  va_end(ap);

  if (needed < 0) {
    LOG_ERROR("containeragent: BuildSQL vsnprintf measurement failed\n");
    return NULL;
  }

  /* --- Allocate exactly what we need (+1 for the NUL terminator) --- */
  char *buf = malloc((size_t)needed + 1);
  if (!buf) {
    LOG_ERROR("containeragent: BuildSQL malloc(%d) OOM\n", needed + 1);
    return NULL;
  }

  /* --- Pass 2: actually format into the buffer --- */
  va_start(ap, fmt);
  int written = vsnprintf(buf, (size_t)needed + 1, fmt, ap);
  va_end(ap);

  if (written != needed) {
    /* Should never happen — same format + same args must produce same length */
    LOG_ERROR("containeragent: BuildSQL length mismatch (pass1=%d pass2=%d)\n",
              needed, written);
    free(buf);
    return NULL;
  }

  return buf;
}

int RecordMetadataContainer(struct containerpkginfo *pi)
{
  PGresult *result;
  char     *SQL = NULL;   /* always heap-allocated by BuildSQL(); free() after use */
  int       pkg_pk;

  /* idempotency: skip if already recorded */
  {
    char idSQL[128];
    snprintf(idSQL, sizeof(idSQL),
      "SELECT pfile_fk FROM pkg_container WHERE pfile_fk = %ld;",
      pi->pFileFk);
    result = PQexec(db_conn, idSQL);
    if (fo_checkPQresult(db_conn, result, idSQL, __FILE__, __LINE__)) exit(-1);
    if (PQntuples(result) > 0) { PQclear(result); return 0; }
    PQclear(result);
  }

  result = PQexec(db_conn, "BEGIN;");
  if (fo_checkPQcommand(db_conn, result, "BEGIN", __FILE__, __LINE__)) exit(-1);
  PQclear(result);

/* execute heap SQL, rollback+return -1 on error, always frees the buffer */
#define EXEC_DYN_SQL(sql_ptr)                                                 \
  do {                                                                        \
    if (!(sql_ptr)) {                                                         \
      PGresult *rb = PQexec(db_conn, "ROLLBACK;"); PQclear(rb);              \
      return -1;                                                              \
    }                                                                         \
    result = PQexec(db_conn, (sql_ptr));                                      \
    free(sql_ptr); (sql_ptr) = NULL;                                          \
    if (fo_checkPQcommand(db_conn, result, "(dynamic)", __FILE__, __LINE__)) {\
      PGresult *rb = PQexec(db_conn, "ROLLBACK;"); PQclear(rb);              \
      PQclear(result);                                                        \
      return -1;                                                              \
    }                                                                         \
    PQclear(result);                                                          \
  } while (0)

  /* pkg_container */
  {
    char *e_imageName    = EscapeLit(db_conn, pi->imageName);
    char *e_imageTag     = EscapeLit(db_conn, pi->imageTag);
    char *e_imageId      = EscapeLit(db_conn, pi->imageId);
    char *e_os           = EscapeLit(db_conn, pi->os);
    char *e_architecture = EscapeLit(db_conn, pi->architecture);
    char *e_variant      = EscapeLit(db_conn, pi->variant);
    char *e_created      = EscapeLit(db_conn, pi->created);
    char *e_author       = EscapeLit(db_conn, pi->author);
    char *e_description  = EscapeLit(db_conn, pi->description);
    char *e_format       = EscapeLit(db_conn, pi->format);
    char *e_entrypoint   = EscapeLit(db_conn, pi->entrypoint);
    char *e_cmd          = EscapeLit(db_conn, pi->cmd);
    char *e_workingDir   = EscapeLit(db_conn, pi->workingDir);
    char *e_user         = EscapeLit(db_conn, pi->user);

    SQL = BuildSQL(
      "INSERT INTO pkg_container "
      "(image_name, image_tag, image_id, os, architecture, variant, "
      " created, author, description, format, "
      " entrypoint, cmd, working_dir, user_field, layer_count, pfile_fk) "
      "VALUES "
      "(%s, %s, %s, %s, %s, %s, "
      " %s, %s, %s, %s, "
      " %s, %s, %s, %s, %d, %ld);",
      e_imageName, e_imageTag, e_imageId,
      e_os, e_architecture, e_variant,
      e_created, e_author, e_description, e_format,
      e_entrypoint, e_cmd, e_workingDir, e_user,
      pi->layerCount, pi->pFileFk);

    PQfreemem(e_imageName);    PQfreemem(e_imageTag);   PQfreemem(e_imageId);
    PQfreemem(e_os);           PQfreemem(e_architecture); PQfreemem(e_variant);
    PQfreemem(e_created);      PQfreemem(e_author);     PQfreemem(e_description);
    PQfreemem(e_format);       PQfreemem(e_entrypoint); PQfreemem(e_cmd);
    PQfreemem(e_workingDir);   PQfreemem(e_user);
  }
  EXEC_DYN_SQL(SQL);

  result = PQexec(db_conn,
    "SELECT currval('pkg_container_pkg_pk_seq'::regclass);");
  if (fo_checkPQresult(db_conn, result, "currval", __FILE__, __LINE__)) exit(-1);
  pkg_pk = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);

  if (Verbose) printf("containeragent: pkg_pk=%d\n", pkg_pk);

  /* pkg_container_env */
  for (int i = 0; i < pi->envCount; i++)
  {
    char keyBuf[MAXLENGTH] = "";
    char valBuf[MAXCMD]    = "";
    char *eq = strchr(pi->envVars[i], '=');
    if (eq) {
      int klen = (int)(eq - pi->envVars[i]);
      if (klen >= (int)sizeof(keyBuf)) klen = sizeof(keyBuf) - 1;
      strncpy(keyBuf, pi->envVars[i], klen);
      strncpy(valBuf, eq + 1, sizeof(valBuf) - 1);
    } else {
      strncpy(keyBuf, pi->envVars[i], sizeof(keyBuf) - 1);
    }

    char *e_key = EscapeLit(db_conn, keyBuf);
    char *e_val = EscapeLit(db_conn, valBuf);
    SQL = BuildSQL(
      "INSERT INTO pkg_container_env (pkg_fk, env_key, env_val) "
      "VALUES (%d, %s, %s);",
      pkg_pk, e_key, e_val);
    PQfreemem(e_key);
    PQfreemem(e_val);
    EXEC_DYN_SQL(SQL);
  }

  /* pkg_container_port */
  for (int i = 0; i < pi->portCount; i++)
  {
    char *e_port = EscapeLit(db_conn, pi->ports[i]);
    SQL = BuildSQL(
      "INSERT INTO pkg_container_port (pkg_fk, port) VALUES (%d, %s);",
      pkg_pk, e_port);
    PQfreemem(e_port);
    EXEC_DYN_SQL(SQL);
  }

  /* pkg_container_label */
  for (int i = 0; i < pi->labelCount; i++)
  {
    char *e_lkey = EscapeLit(db_conn,
                              pi->labels_kv[i].key ? pi->labels_kv[i].key : "");
    char *e_lval = EscapeLit(db_conn,
                              pi->labels_kv[i].val ? pi->labels_kv[i].val : "");
    SQL = BuildSQL(
      "INSERT INTO pkg_container_label (pkg_fk, lbl_key, lbl_val) "
      "VALUES (%d, %s, %s);",
      pkg_pk, e_lkey, e_lval);
    PQfreemem(e_lkey);
    PQfreemem(e_lval);
    EXEC_DYN_SQL(SQL);
  }

  /* pkg_container_layer — EscapeLit prevents SQL injection from createdBy */
  for (int i = 0; i < pi->layerCount && pi->layers; i++)
  {
    char *e_layerId   = EscapeLit(db_conn, pi->layers[i].layerId);
    char *e_createdBy = EscapeLit(db_conn, pi->layers[i].createdBy);
    SQL = BuildSQL(
      "INSERT INTO pkg_container_layer "
      "(pkg_fk, layer_index, layer_id, created_by, empty_layer) "
      "VALUES (%d, %d, %s, %s, %s);",
      pkg_pk, i,
      e_layerId,
      e_createdBy,
      pi->layers[i].emptyLayer ? "TRUE" : "FALSE");
    PQfreemem(e_layerId);
    PQfreemem(e_createdBy);
    EXEC_DYN_SQL(SQL);
  }

#undef EXEC_DYN_SQL

  result = PQexec(db_conn, "COMMIT;");
  if (fo_checkPQcommand(db_conn, result, "COMMIT", __FILE__, __LINE__)) exit(-1);
  PQclear(result);

  /* scan installed OS packages via libfosspkg — failure is non-fatal */
  if (pi->uploadPk > 0) {
    int nPkgs = ScanContainerPackages(pi->uploadPk, pkg_pk, pi);
    if (nPkgs < 0) {
      LOG_WARNING("containeragent: installed package scan failed for upload %ld\n",
                  pi->uploadPk);
    } else if (Verbose) {
      printf("containeragent: recorded %d installed packages\n", nPkgs);
    }
  }

  return 0;
}

/* =========================================================================
 * ProcessUpload
 *
 * Detection strategy
 * ------------------
 * libmagic sees the uploaded file as application/x-tar because it cannot
 * inspect the tar contents before ununpack expands them.  By the time
 * containeragent runs, ununpack has already unpacked the tar and every
 * inner file has its own uploadtree row.
 *
 * We therefore detect container images by looking for their well-known
 * marker files inside the upload tree:
 *
 *   Docker : manifest.json  (Docker image tar produced by docker save)
 *   OCI    : index.json     (OCI image layout)
 *
 * For each top-level tar in the upload we check whether manifest.json or
 * index.json exists as a direct child, then dispatch accordingly.
 * ========================================================================= */
int ProcessUpload(long upload_pk)
{
  char SQL[MAXCMD];
  PGresult *result;
  char *uploadtree_tablename;

  struct containerpkginfo *pi =
    calloc(1, sizeof(struct containerpkginfo));

  if (!upload_pk) { free(pi); return -1; }

  uploadtree_tablename = GetUploadtreeTableName(db_conn, upload_pk);
  if (!uploadtree_tablename)
    uploadtree_tablename = strdup("uploadtree_a");

  /* get root uploadtree node */
  snprintf(SQL, sizeof(SQL),
    "SELECT uploadtree_pk, lft, rgt, pfile_fk "
    "FROM %s "
    "WHERE upload_fk = %ld AND parent IS NULL LIMIT 1",
    uploadtree_tablename, upload_pk);

  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
    { free(uploadtree_tablename); free(pi); exit(-1); }

  if (PQntuples(result) == 0) {
    LOG_ERROR("containeragent: no root uploadtree row for upload %ld\n",
              upload_pk);
    PQclear(result);
    free(uploadtree_tablename);
    free(pi);
    return -1;
  }

  long          rootUploadtreePk = atol(PQgetvalue(result, 0, 0));
  long          rootPfileFk      = atol(PQgetvalue(result, 0, 3));
  PQclear(result);


  /* skip if already processed */
  snprintf(SQL, sizeof(SQL),
    "SELECT 1 FROM pkg_container WHERE pfile_fk = %ld LIMIT 1;",
    rootPfileFk);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
    { free(uploadtree_tablename); free(pi); exit(-1); }
  if (PQntuples(result) > 0) {
    PQclear(result);
    if (Verbose) printf("containeragent: upload %ld already processed\n",
                        upload_pk);
    free(uploadtree_tablename);
    free(pi);
    return 0;
  }
  PQclear(result);

  /* detect Docker: search depth 1+2 only — depth 1 misses ununpack wrapper, lft/rgt range causes false positives */
  snprintf(SQL, sizeof(SQL),
    "SELECT 1 FROM %s "
    "WHERE upload_fk = %ld "
    "AND ufile_name = 'manifest.json' "
    "AND (parent = %ld "
    "     OR parent IN ("
    "         SELECT uploadtree_pk FROM %s "
    "         WHERE upload_fk = %ld AND parent = %ld"
    "     )) "
    "LIMIT 1",
    uploadtree_tablename, upload_pk,
    rootUploadtreePk,
    uploadtree_tablename, upload_pk, rootUploadtreePk);

  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
    { free(uploadtree_tablename); free(pi); exit(-1); }
  int hasManifest = (PQntuples(result) > 0);
  PQclear(result);

  /* detect OCI: same depth 1+2 strategy */
  snprintf(SQL, sizeof(SQL),
    "SELECT 1 FROM %s "
    "WHERE upload_fk = %ld "
    "AND ufile_name = 'index.json' "
    "AND (parent = %ld "
    "     OR parent IN ("
    "         SELECT uploadtree_pk FROM %s "
    "         WHERE upload_fk = %ld AND parent = %ld"
    "     )) "
    "LIMIT 1",
    uploadtree_tablename, upload_pk,
    rootUploadtreePk,
    uploadtree_tablename, upload_pk, rootUploadtreePk);

  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
    { free(uploadtree_tablename); free(pi); exit(-1); }
  int hasIndex = (PQntuples(result) > 0);
  PQclear(result);

  free(uploadtree_tablename);   /* done with table name */

  if (!hasManifest && !hasIndex) {
    if (Verbose)
      printf("containeragent: upload %ld is not a container image "
             "(no manifest.json or index.json found)\n", upload_pk);
    free(pi);
    return 0;   /* not a container image — exit cleanly, no error */
  }

  memset(pi, 0, sizeof(struct containerpkginfo));
  pi->pFileFk = rootPfileFk;
  pi->uploadPk = upload_pk;   /* needed by ScanContainerPackages */

  snprintf(SQL, sizeof(SQL),
    "SELECT pfile_sha1 || '.' || pfile_md5 || '.' || pfile_size "
    "FROM pfile WHERE pfile_pk = %ld",
    rootPfileFk);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
    { free(pi); exit(-1); }
  if (PQntuples(result) > 0)
    strncpy(pi->pFile, PQgetvalue(result, 0, 0), sizeof(pi->pFile) - 1);
  PQclear(result);

  int rc = -1;

  /* prefer Docker when both markers present */
  if (hasManifest) {
    if (Verbose)
      printf("containeragent: upload %ld detected as Docker image\n",
             upload_pk);
    rc = GetMetadataDocker(upload_pk, pi);
  } else {
    if (Verbose)
      printf("containeragent: upload %ld detected as OCI image\n",
             upload_pk);
    rc = GetMetadataOCI(upload_pk, pi);
  }

  if (rc == 0)
    RecordMetadataContainer(pi);
  else
    LOG_WARNING("containeragent: metadata extraction failed for "
                "upload %ld\n", upload_pk);

  FreeContainerInfo(pi);
  fo_scheduler_heart(1);
  free(pi);
  return 0;
}

/* =========================================================================
 * FreeContainerInfo
 * ========================================================================= */
void FreeContainerInfo(struct containerpkginfo *pi)
{
  int i;
  if (!pi) return;
  for (i = 0; i < pi->envCount;   i++) { free(pi->envVars[i]);  pi->envVars[i]  = NULL; }
  for (i = 0; i < pi->portCount;  i++) { free(pi->ports[i]);    pi->ports[i]    = NULL; }
  for (i = 0; i < pi->labelCount; i++) {
    free(pi->labels_kv[i].key); pi->labels_kv[i].key = NULL;
    free(pi->labels_kv[i].val); pi->labels_kv[i].val = NULL;
  }
  free(pi->envVars);   pi->envVars   = NULL; pi->envCount   = 0;
  free(pi->ports);     pi->ports     = NULL; pi->portCount  = 0;
  free(pi->labels_kv); pi->labels_kv = NULL; pi->labelCount = 0;
  free(pi->layers);    pi->layers    = NULL; pi->layerCount = 0;
}

/* =========================================================================
 * Usage
 * ========================================================================= */
void Usage(char *Name)
{
  printf("Usage: %s [options]\n", Name);
  printf("  -i   :: initialize the database, then exit.\n");
  printf("  -v   :: verbose (-vv = more verbose)\n");
  printf("  -c   :: specify the directory for the system configuration.\n");
  printf("  -C   :: run from command line (not scheduler).\n");
  printf("  -V   :: print the version info, then exit.\n");
}
