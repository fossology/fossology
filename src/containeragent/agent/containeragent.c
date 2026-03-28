/*
 SPDX-FileCopyrightText: © 2026 Fossology contributors
 SPDX-License-Identifier: GPL-2.0-only
*/
/**
 * \file containeragent.c
 * \brief Container Analysis agent — core implementation
 *
 * Extracts metadata from Docker image tarballs and OCI image layouts after
 * they have been unpacked by the ununpack agent.
 */

#include "containeragent.h"
#include "containerpkg.h"

int    Verbose  = 0;
PGconn *db_conn = NULL;

/* Forward declaration — EnsureLayerCap is defined in containerpkg.c */
int EnsureLayerCap(struct containerpkginfo *pi, int newCount);

/* =========================================================================
 * ReadFileToString — read a file into a malloc'd, NUL-terminated buffer
 * ========================================================================= */
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

  char *buf = malloc((size_t)sz + 1);
  if (!buf) { fclose(fp); return NULL; }

  if (fread(buf, 1, (size_t)sz, fp) != (size_t)sz) {
    free(buf); fclose(fp);
    LOG_ERROR("containeragent: short read on %s\n", path);
    return NULL;
  }
  buf[sz] = '\0';
  fclose(fp);
  return buf;
}

/* =========================================================================
 * JsonStrCopy — safely copy a JSON string field into a fixed-size buffer
 * ========================================================================= */
static void JsonStrCopy(char *dst, size_t dstLen,
                        struct json_object *obj, const char *key)
{
  struct json_object *val = NULL;
  if (json_object_object_get_ex(obj, key, &val) && val) {
    const char *s = json_object_get_string(val);
    if (s) strncpy(dst, s, dstLen - 1);
  }
}

/* =========================================================================
 * AppendStrArray — append a string to a dynamically-grown char* array
 * ========================================================================= */
static void AppendStrArray(char ***arr, int *count, int maxCnt, const char *value)
{
  if (*count >= maxCnt) return;
  char **tmp = realloc(*arr, (*count + 1) * sizeof(char *));
  if (!tmp) {
    LOG_WARNING("containeragent: AppendStrArray realloc failed — entry dropped\n");
    return;
  }
  *arr = tmp;
  (*arr)[*count] = strdup(value ? value : "");
  (*count)++;
}

/* =========================================================================
 * JoinJsonArray — flatten a JSON string array into a space-separated string.
 *
 * Used for Entrypoint and Cmd fields which are stored as JSON arrays but
 * displayed / recorded as single strings.
 * ========================================================================= */
static void JoinJsonArray(struct json_object *arr, char *dst, size_t dstLen)
{
  if (!arr || json_object_get_type(arr) != json_type_array) return;
  int n = (int)json_object_array_length(arr);
  for (int i = 0; i < n; i++) {
    struct json_object *el = json_object_array_get_idx(arr, i);
    const char *s = el ? json_object_get_string(el) : "";
    if (!s) s = "";
    if (i > 0) strncat(dst, " ", dstLen - strlen(dst) - 1);
    strncat(dst, s, dstLen - strlen(dst) - 1);
  }
}

/* =========================================================================
 * ParseTagString — split "repo:tag" into imageName and imageTag fields.
 * Defaults imageTag to "latest" when no colon is present.
 * ========================================================================= */
static void ParseTagString(const char *tagStr,
                            char *imageName, size_t nameLen,
                            char *imageTag,  size_t tagLen)
{
  char tmp[MAXCMD];
  snprintf(tmp, sizeof(tmp), "%s", tagStr);
  char *colon = strrchr(tmp, ':');
  if (colon) {
    *colon = '\0';
    snprintf(imageName, nameLen, "%s", tmp);
    snprintf(imageTag,  tagLen,  "%s", colon + 1);
  } else {
    snprintf(imageName, nameLen, "%s", tmp);
    snprintf(imageTag,  tagLen,  "%s", "latest");
  }
}

/* =========================================================================
 * PopulateLayerBlobNames
 * ========================================================================= */
static void __attribute__((unused))
PopulateLayerBlobNames(const char *manifestPath,
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
    json_object_put(root); return;
  }

  struct json_object *elem = json_object_array_get_idx(root, 0);
  if (!elem) { json_object_put(root); return; }

  struct json_object *layersArr = NULL;
  if (!json_object_object_get_ex(elem, "Layers", &layersArr) || !layersArr ||
      json_object_get_type(layersArr) != json_type_array) {
    json_object_put(root); return;
  }

  int blobCount = (int)json_object_array_length(layersArr);
  int blobIdx   = 0;

  for (int li = 0; li < pi->layerCount && blobIdx < blobCount; li++) {
    if (pi->layers[li].emptyLayer) continue;
    struct json_object *le = json_object_array_get_idx(layersArr, blobIdx++);
    if (!le) continue;
    const char *lpath = json_object_get_string(le);
    if (!lpath || !*lpath) continue;
    strncpy(pi->layers[li].blobName, lpath, sizeof(pi->layers[li].blobName) - 1);
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
  if (json_object_get_type(root) != json_type_array) {
    LOG_ERROR("containeragent: manifest.json is not a JSON array\n");
    json_object_put(root); return -1;
  }
  if (json_object_array_length(root) == 0) {
    LOG_ERROR("containeragent: manifest.json array is empty\n");
    json_object_put(root); return -1;
  }

  struct json_object *elem = json_object_array_get_idx(root, 0);
  if (!elem) {
    LOG_ERROR("containeragent: manifest.json array element[0] is NULL\n");
    json_object_put(root); return -1;
  }

  struct json_object *cfgObj = NULL;
  if (json_object_object_get_ex(elem, "Config", &cfgObj) && cfgObj)
    strncpy(configFilename, json_object_get_string(cfgObj), cfgLen - 1);

  struct json_object *tags = NULL;
  if (json_object_object_get_ex(elem, "RepoTags", &tags) && tags &&
      json_object_get_type(tags) == json_type_array &&
      json_object_array_length(tags) > 0) {
    const char *tag0 = json_object_get_string(json_object_array_get_idx(tags, 0));
    if (tag0)
      ParseTagString(tag0, pi->imageName, sizeof(pi->imageName),
                         pi->imageTag,   sizeof(pi->imageTag));
  }

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

  struct json_object *manifests = NULL;
  if (!json_object_object_get_ex(root, "manifests", &manifests) || !manifests ||
      json_object_get_type(manifests) != json_type_array ||
      json_object_array_length(manifests) == 0) {
    LOG_ERROR("containeragent: OCI index.json has no valid 'manifests' array\n");
    json_object_put(root); return -1;
  }

  struct json_object *m0 = json_object_array_get_idx(manifests, 0);

  struct json_object *digestObj = NULL;
  if (json_object_object_get_ex(m0, "digest", &digestObj) && digestObj)
    strncpy(manifestDigest, json_object_get_string(digestObj), digestLen - 1);

  struct json_object *ann = NULL;
  if (json_object_object_get_ex(m0, "annotations", &ann) && ann) {
    struct json_object *refName = NULL;
    if (json_object_object_get_ex(ann,
          "org.opencontainers.image.ref.name", &refName) && refName) {
      const char *ref = json_object_get_string(refName);
      ParseTagString(ref, pi->imageName, sizeof(pi->imageName),
                          pi->imageTag,  sizeof(pi->imageTag));
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
    cfg = root;   /* OCI puts config fields at the top level */

  JsonStrCopy(pi->workingDir, sizeof(pi->workingDir), cfg, "WorkingDir");
  JsonStrCopy(pi->user,       sizeof(pi->user),       cfg, "User");

  /* Entrypoint and Cmd are JSON arrays; flatten to space-separated strings */
  struct json_object *ep = NULL;
  if (json_object_object_get_ex(cfg, "Entrypoint", &ep))
    JoinJsonArray(ep, pi->entrypoint, sizeof(pi->entrypoint));

  struct json_object *cmd = NULL;
  if (json_object_object_get_ex(cfg, "Cmd", &cmd))
    JoinJsonArray(cmd, pi->cmd, sizeof(pi->cmd));

  /* Env vars */
  struct json_object *env = NULL;
  if (json_object_object_get_ex(cfg, "Env", &env) && env &&
      json_object_get_type(env) == json_type_array) {
    int n = (int)json_object_array_length(env);
    for (int i = 0; i < n; i++) {
      struct json_object *el = json_object_array_get_idx(env, i);
      const char *s = el ? json_object_get_string(el) : NULL;
      if (s) AppendStrArray(&pi->envVars, &pi->envCount, MAX_ENV, s);
    }
  }

  /* ExposedPorts: keys are "port/proto" strings */
  struct json_object *ports = NULL;
  if (json_object_object_get_ex(cfg, "ExposedPorts", &ports) && ports) {
    json_object_object_foreach(ports, portKey, portVal) {
      (void)portVal;
      AppendStrArray(&pi->ports, &pi->portCount, MAX_PORTS, portKey);
    }
  }

  /* Labels */
  struct json_object *labels = NULL;
  if (json_object_object_get_ex(cfg, "Labels", &labels) && labels) {
    json_object_object_foreach(labels, lKey, lVal) {
      if (strcmp(lKey, "org.opencontainers.image.description") == 0 &&
          pi->description[0] == '\0')
        strncpy(pi->description,
                json_object_get_string(lVal), sizeof(pi->description) - 1);

      if (strcmp(lKey, "maintainer") == 0 && pi->author[0] == '\0')
        strncpy(pi->author,
                json_object_get_string(lVal), sizeof(pi->author) - 1);

      if (pi->labelCount < MAX_LABELS) {
        struct containerlabelpair *tmp =
          realloc(pi->labels_kv,
                  (pi->labelCount + 1) * sizeof(struct containerlabelpair));
        if (!tmp) {
          LOG_WARNING("containeragent: label realloc OOM — entry dropped\n");
        } else {
          pi->labels_kv = tmp;
          char *dupKey = strdup(lKey ? lKey : "");
          const char *rawVal = lVal ? json_object_get_string(lVal) : "";
          char *dupVal = strdup(rawVal ? rawVal : "");
          if (!dupKey || !dupVal) {
            free(dupKey); free(dupVal);
            LOG_WARNING("containeragent: label strdup OOM — entry dropped\n");
          } else {
            pi->labels_kv[pi->labelCount].key = dupKey;
            pi->labels_kv[pi->labelCount].val = dupVal;
            pi->labelCount++;
          }
        }
      }
    }
  }

  /* History — one entry per layer (including empty_layer ones) */
  struct json_object *history = NULL;
  if (json_object_object_get_ex(root, "history", &history) && history &&
      json_object_get_type(history) == json_type_array) {
    int n = (int)json_object_array_length(history);
    int layerIdx = 0;
    for (int i = 0; i < n; i++) {
      struct json_object *h = json_object_array_get_idx(history, i);
      if (!h) continue;
      if (EnsureLayerCap(pi, layerIdx + 1) != 0) break;

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

      snprintf(pi->layers[layerIdx].layerId,
               sizeof(pi->layers[layerIdx].layerId), "layer-%d", i);
      layerIdx++;
    }
    pi->layerCount = layerIdx;
  }

  json_object_put(root);
  return 0;
}

/* =========================================================================
 * GetMetadataDocker — read manifest.json; parse every element
 * ========================================================================= */
int GetMetadataDocker(long upload_pk, struct containerpkginfo *pi)
{
  char SQL[MAXCMD];
  PGresult *result;
  unsigned long lft, rgt;

  if (!upload_pk) return -1;

  char *ut = GetUploadtreeTableName(db_conn, upload_pk);
  if (!ut) ut = strdup("uploadtree_a");

  snprintf(SQL, sizeof(SQL),
    "SELECT lft,rgt FROM %s WHERE upload_fk=%ld AND parent IS NULL LIMIT 1",
    ut, upload_pk);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) {
    free(ut); return -1;
  }
  if (PQntuples(result) == 0) {
    LOG_ERROR("containeragent: no root uploadtree row for upload %ld\n", upload_pk);
    PQclear(result); free(ut); return -1;
  }
  lft = strtoul(PQgetvalue(result, 0, 0), NULL, 10);
  rgt = strtoul(PQgetvalue(result, 0, 1), NULL, 10);
  PQclear(result);

  /* Locate manifest.json */
  snprintf(SQL, sizeof(SQL),
    "SELECT pfile_sha1||'.'||pfile_md5||'.'||pfile_size"
    " FROM pfile INNER JOIN %s ON pfile_pk=pfile_fk"
    " WHERE upload_fk=%ld AND lft>%ld AND rgt<%ld"
    "   AND ufile_name='manifest.json' LIMIT 1",
    ut, upload_pk, lft, rgt);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) {
    free(ut); return -1;
  }
  if (PQntuples(result) == 0) {
    LOG_ERROR("containeragent: manifest.json not found for upload %ld\n", upload_pk);
    PQclear(result); free(ut); return -1;
  }
  char *manifestRepo = fo_RepMkPath("files", PQgetvalue(result, 0, 0));
  PQclear(result);
  if (!manifestRepo) { free(ut); return -1; }

  char *raw = ReadFileToString(manifestRepo);
  free(manifestRepo);
  if (!raw) { LOG_ERROR("containeragent: cannot read manifest.json\n"); free(ut); return -1; }

  struct json_object *manifestArr = json_tokener_parse(raw);
  free(raw);
  if (!manifestArr || json_object_get_type(manifestArr) != json_type_array ||
      json_object_array_length(manifestArr) == 0) {
    LOG_ERROR("containeragent: manifest.json is not a non-empty JSON array\n");
    json_object_put(manifestArr); free(ut); return -1;
  }

  int nElems = (int)json_object_array_length(manifestArr);
  pi->manifestCount = nElems;
  LOG_NOTICE("containeragent: manifest.json has %d element(s)\n", nElems);

  int rc = -1;
  for (int mi = 0; mi < nElems; mi++) {
    fo_scheduler_heart(0);   /* heartbeat per manifest element */

    struct json_object *elem = json_object_array_get_idx(manifestArr, mi);
    if (!elem) continue;

    char configFilename[MAXLENGTH] = "";
    struct json_object *cfgObj = NULL;
    if (json_object_object_get_ex(elem, "Config", &cfgObj) && cfgObj)
      strncpy(configFilename, json_object_get_string(cfgObj),
              sizeof(configFilename) - 1);

    if (pi->imageName[0] == '\0') {
      struct json_object *tags = NULL;
      if (json_object_object_get_ex(elem, "RepoTags", &tags) && tags &&
          json_object_get_type(tags) == json_type_array &&
          json_object_array_length(tags) > 0) {
        const char *tag0 = json_object_get_string(
                             json_object_array_get_idx(tags, 0));
        if (tag0)
          ParseTagString(tag0, pi->imageName, sizeof(pi->imageName),
                              pi->imageTag,  sizeof(pi->imageTag));
      }
    }

    if (configFilename[0] == '\0') {
      LOG_WARNING("containeragent: manifest element [%d] has no Config\n", mi);
      continue;
    }

    char cfgCopy[MAXLENGTH];
    strncpy(cfgCopy, configFilename, sizeof(cfgCopy) - 1);
    cfgCopy[sizeof(cfgCopy) - 1] = '\0';
    char *cfgBase = basename(cfgCopy);

    snprintf(SQL, sizeof(SQL),
      "SELECT pfile_sha1||'.'||pfile_md5||'.'||pfile_size"
      " FROM pfile INNER JOIN %s ON pfile_pk=pfile_fk"
      " WHERE upload_fk=%ld AND lft>%ld AND rgt<%ld"
      "   AND ufile_name='%s' LIMIT 1",
      ut, upload_pk, lft, rgt, cfgBase);
    result = PQexec(db_conn, SQL);
    if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) continue;
    if (PQntuples(result) == 0) {
      LOG_WARNING("containeragent: config JSON '%s' not found for manifest[%d]\n",
                  cfgBase, mi);
      PQclear(result); continue;
    }
    char *cfgRepo = fo_RepMkPath("files", PQgetvalue(result, 0, 0));
    PQclear(result);
    if (!cfgRepo) continue;

    if (ParseImageConfig(cfgRepo, pi) == 0) {
      struct json_object *layersArr = NULL;
      if (json_object_object_get_ex(elem, "Layers", &layersArr) && layersArr &&
          json_object_get_type(layersArr) == json_type_array) {
        int blobCount = (int)json_object_array_length(layersArr);
        int blobIdx   = 0;
        for (int li = 0; li < pi->layerCount && blobIdx < blobCount; li++) {
          if (pi->layers[li].emptyLayer) continue;
          struct json_object *le = json_object_array_get_idx(layersArr, blobIdx++);
          if (!le) continue;
          const char *lpath = json_object_get_string(le);
          if (!lpath || !*lpath) continue;
          strncpy(pi->layers[li].blobName, lpath,
                  sizeof(pi->layers[li].blobName) - 1);
        }
      }
      rc = 0;
    }
    free(cfgRepo);
  }

  json_object_put(manifestArr);
  free(ut);
  strncpy(pi->format, "docker", sizeof(pi->format) - 1);
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
  char manifestDigest[MAXLENGTH] = "";

  if (!upload_pk) return -1;

  char *ut = GetUploadtreeTableName(db_conn, upload_pk);
  if (!ut) ut = strdup("uploadtree_a");

  snprintf(SQL, sizeof(SQL),
    "SELECT lft,rgt FROM %s WHERE upload_fk=%ld AND parent IS NULL LIMIT 1",
    ut, upload_pk);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) {
    free(ut); return -1;
  }
  if (PQntuples(result) == 0) { PQclear(result); free(ut); return -1; }
  lft = strtoul(PQgetvalue(result, 0, 0), NULL, 10);
  rgt = strtoul(PQgetvalue(result, 0, 1), NULL, 10);
  PQclear(result);

  /* Locate index.json */
  snprintf(SQL, sizeof(SQL),
    "SELECT pfile_sha1||'.'||pfile_md5||'.'||pfile_size"
    " FROM pfile INNER JOIN %s ON pfile_pk=pfile_fk"
    " WHERE upload_fk=%ld AND lft>%ld AND rgt<%ld"
    "   AND ufile_name='index.json' LIMIT 1",
    ut, upload_pk, lft, rgt);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) {
    free(ut); return -1;
  }
  if (PQntuples(result) == 0) {
    LOG_ERROR("containeragent: index.json not found for upload %ld\n", upload_pk);
    PQclear(result); free(ut); return -1;
  }
  char *idxRepo = fo_RepMkPath("files", PQgetvalue(result, 0, 0));
  PQclear(result);
  if (!idxRepo) { free(ut); return -1; }

  if (ParseOCIIndex(idxRepo, pi, manifestDigest, sizeof(manifestDigest)) != 0) {
    free(idxRepo); free(ut); return -1;
  }
  free(idxRepo);
  if (manifestDigest[0] == '\0') { free(ut); return -1; }

  /* Strip "sha256:" prefix; find manifest blob */
  const char *manifestHex = strstr(manifestDigest, ":");
  manifestHex = manifestHex ? manifestHex + 1 : manifestDigest;

  snprintf(SQL, sizeof(SQL),
    "SELECT pfile_sha1||'.'||pfile_md5||'.'||pfile_size"
    " FROM pfile INNER JOIN %s ON pfile_pk=pfile_fk"
    " WHERE upload_fk=%ld AND lft>%ld AND rgt<%ld"
    "   AND ufile_name='%s' LIMIT 1",
    ut, upload_pk, lft, rgt, manifestHex);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) {
    free(ut); return -1;
  }
  if (PQntuples(result) == 0) {
    LOG_ERROR("containeragent: OCI manifest blob '%s' not found\n", manifestHex);
    PQclear(result); free(ut); return -1;
  }
  char *mfRepo = fo_RepMkPath("files", PQgetvalue(result, 0, 0));
  PQclear(result);
  if (!mfRepo) { free(ut); return -1; }

  /* Parse manifest blob — extract config digest and layer digests */
  char configDigest[MAXLENGTH] = "";
  char ociLayerDigests[MAX_LAYERS][MAXLENGTH];
  int  ociLayerCount = 0;
  memset(ociLayerDigests, 0, sizeof(ociLayerDigests));

  {
    char *raw = ReadFileToString(mfRepo);
    free(mfRepo);
    if (!raw) { free(ut); return -1; }

    struct json_object *mfRoot = json_tokener_parse(raw);
    free(raw);
    if (!mfRoot) {
      LOG_ERROR("containeragent: failed to parse OCI manifest blob '%s'\n", manifestHex);
      free(ut); return -1;
    }

    struct json_object *cfgObj = NULL;
    if (json_object_object_get_ex(mfRoot, "config", &cfgObj) && cfgObj) {
      struct json_object *dgst = NULL;
      if (json_object_object_get_ex(cfgObj, "digest", &dgst) && dgst)
        strncpy(configDigest, json_object_get_string(dgst),
                sizeof(configDigest) - 1);
    }

    struct json_object *layersArr = NULL;
    if (json_object_object_get_ex(mfRoot, "layers", &layersArr) && layersArr &&
        json_object_get_type(layersArr) == json_type_array) {
      int nlayers = (int)json_object_array_length(layersArr);
      if (pi->layerCount == 0) pi->layerCount = nlayers;
      for (int li = 0; li < nlayers && li < MAX_LAYERS; li++) {
        fo_scheduler_heart(0);   /* heartbeat per layer digest */
        struct json_object *le = json_object_array_get_idx(layersArr, li);
        if (!le) continue;
        struct json_object *dgstObj = NULL;
        if (!json_object_object_get_ex(le, "digest", &dgstObj) || !dgstObj) continue;
        const char *dstr  = json_object_get_string(dgstObj);
        if (!dstr) continue;
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
    free(ut); return -1;
  }

  /* Find and parse config blob */
  const char *cfgHex = strstr(configDigest, ":");
  cfgHex = cfgHex ? cfgHex + 1 : configDigest;

  snprintf(SQL, sizeof(SQL),
    "SELECT pfile_sha1||'.'||pfile_md5||'.'||pfile_size"
    " FROM pfile INNER JOIN %s ON pfile_pk=pfile_fk"
    " WHERE upload_fk=%ld AND lft>%ld AND rgt<%ld"
    "   AND ufile_name='%s' LIMIT 1",
    ut, upload_pk, lft, rgt, cfgHex);
  free(ut);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__)) return -1;
  if (PQntuples(result) == 0) {
    LOG_ERROR("containeragent: OCI config blob '%s' not found\n", cfgHex);
    PQclear(result); return -1;
  }
  char *cfgRepo = fo_RepMkPath("files", PQgetvalue(result, 0, 0));
  PQclear(result);
  if (!cfgRepo) return -1;

  int rc = ParseImageConfig(cfgRepo, pi);
  free(cfgRepo);

  if (rc == 0 && pi->layers && ociLayerCount > 0) {
    int ociIdx = 0;
    for (int li = 0; li < pi->layerCount && ociIdx < ociLayerCount; li++) {
      if (pi->layers[li].emptyLayer) continue;
      strncpy(pi->layers[li].blobName, ociLayerDigests[ociIdx],
              sizeof(pi->layers[li].blobName) - 1);
      pi->layers[li].blobName[sizeof(pi->layers[li].blobName) - 1] = '\0';
      ociIdx++;
    }
  }

  return rc;
}

/* =========================================================================
 * EscapeLit / BuildSQL — SQL string helpers for RecordMetadataContainer
 * ========================================================================= */
static char *EscapeLit(PGconn *conn, const char *str)
{
  if (!str) str = "";
  char *esc = PQescapeLiteral(conn, str, strlen(str));
  if (!esc) {
    LOG_WARNING("containeragent: PQescapeLiteral failed — storing empty string\n");
    esc = strdup("''");
  }
  return esc;
}

static char *BuildSQL(const char *fmt, ...) __attribute__((format(printf, 1, 2)));
static char *BuildSQL(const char *fmt, ...)
{
  va_list ap;
  va_start(ap, fmt);
  int needed = vsnprintf(NULL, 0, fmt, ap);
  va_end(ap);
  if (needed < 0) { LOG_ERROR("containeragent: BuildSQL measure failed\n"); return NULL; }

  char *buf = malloc((size_t)needed + 1);
  if (!buf) { LOG_ERROR("containeragent: BuildSQL OOM\n"); return NULL; }

  va_start(ap, fmt);
  int written = vsnprintf(buf, (size_t)needed + 1, fmt, ap);
  va_end(ap);
  if (written != needed) {
    LOG_ERROR("containeragent: BuildSQL length mismatch\n");
    free(buf); return NULL;
  }
  return buf;
}

/* =========================================================================
 * RecordMetadataContainer
 * ========================================================================= */
int RecordMetadataContainer(struct containerpkginfo *pi)
{
  PGresult *result;
  char     *SQL = NULL;
  int       pkg_pk;

  /* Idempotency guard */
  {
    char idSQL[128];
    snprintf(idSQL, sizeof(idSQL),
      "SELECT pfile_fk FROM pkg_container WHERE pfile_fk=%ld;", pi->pFileFk);
    result = PQexec(db_conn, idSQL);
    if (fo_checkPQresult(db_conn, result, idSQL, __FILE__, __LINE__)) exit(-1);
    if (PQntuples(result) > 0) { PQclear(result); return 0; }
    PQclear(result);
  }

  result = PQexec(db_conn, "BEGIN;");
  if (fo_checkPQcommand(db_conn, result, "BEGIN", __FILE__, __LINE__)) exit(-1);
  PQclear(result);

/* Execute heap-allocated SQL; ROLLBACK+return -1 on error */
#define EXEC_SQL(ptr)                                                        \
  do {                                                                       \
    if (!(ptr)) {                                                            \
      PGresult *rb = PQexec(db_conn, "ROLLBACK;"); PQclear(rb);             \
      return -1;                                                             \
    }                                                                        \
    result = PQexec(db_conn, (ptr));                                         \
    free(ptr); (ptr) = NULL;                                                 \
    if (fo_checkPQcommand(db_conn, result, "(dynamic)", __FILE__, __LINE__)) {\
      PGresult *rb = PQexec(db_conn, "ROLLBACK;"); PQclear(rb);             \
      PQclear(result); return -1;                                            \
    }                                                                        \
    PQclear(result);                                                         \
  } while (0)

  /* pkg_container row */
  {
    char *e_name = EscapeLit(db_conn, pi->imageName);
    char *e_tag  = EscapeLit(db_conn, pi->imageTag);
    char *e_id   = EscapeLit(db_conn, pi->imageId);
    char *e_os   = EscapeLit(db_conn, pi->os);
    char *e_arch = EscapeLit(db_conn, pi->architecture);
    char *e_var  = EscapeLit(db_conn, pi->variant);
    char *e_cre  = EscapeLit(db_conn, pi->created);
    char *e_auth = EscapeLit(db_conn, pi->author);
    char *e_desc = EscapeLit(db_conn, pi->description);
    char *e_fmt  = EscapeLit(db_conn, pi->format);
    char *e_ep   = EscapeLit(db_conn, pi->entrypoint);
    char *e_cmd  = EscapeLit(db_conn, pi->cmd);
    char *e_wd   = EscapeLit(db_conn, pi->workingDir);
    char *e_usr  = EscapeLit(db_conn, pi->user);

    SQL = BuildSQL(
      "INSERT INTO pkg_container"
      " (image_name,image_tag,image_id,os,architecture,variant,"
      "  created,author,description,format,"
      "  entrypoint,cmd,working_dir,user_field,layer_count,pfile_fk)"
      " VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%d,%ld);",
      e_name, e_tag, e_id, e_os, e_arch, e_var,
      e_cre, e_auth, e_desc, e_fmt,
      e_ep, e_cmd, e_wd, e_usr,
      pi->layerCount, pi->pFileFk);

    PQfreemem(e_name); PQfreemem(e_tag);  PQfreemem(e_id);
    PQfreemem(e_os);   PQfreemem(e_arch); PQfreemem(e_var);
    PQfreemem(e_cre);  PQfreemem(e_auth); PQfreemem(e_desc);
    PQfreemem(e_fmt);  PQfreemem(e_ep);   PQfreemem(e_cmd);
    PQfreemem(e_wd);   PQfreemem(e_usr);
  }
  EXEC_SQL(SQL);

  result = PQexec(db_conn,
    "SELECT currval('pkg_container_pkg_pk_seq'::regclass);");
  if (fo_checkPQresult(db_conn, result, "currval", __FILE__, __LINE__)) exit(-1);
  pkg_pk = atoi(PQgetvalue(result, 0, 0));
  PQclear(result);

  LOG_NOTICE("containeragent: pkg_pk=%d\n", pkg_pk);

  /* Env vars */
  for (int i = 0; i < pi->envCount; i++) {
    char keyBuf[MAXLENGTH] = "", valBuf[MAXCMD] = "";
    char *eq = strchr(pi->envVars[i], '=');
    if (eq) {
      int klen = (int)(eq - pi->envVars[i]);
      if (klen >= (int)sizeof(keyBuf)) klen = (int)sizeof(keyBuf) - 1;
      strncpy(keyBuf, pi->envVars[i], klen);
      strncpy(valBuf, eq + 1, sizeof(valBuf) - 1);
    } else {
      strncpy(keyBuf, pi->envVars[i], sizeof(keyBuf) - 1);
    }
    char *e_k = EscapeLit(db_conn, keyBuf);
    char *e_v = EscapeLit(db_conn, valBuf);
    SQL = BuildSQL("INSERT INTO pkg_container_env (pkg_fk,env_key,env_val)"
                   " VALUES (%d,%s,%s);", pkg_pk, e_k, e_v);
    PQfreemem(e_k); PQfreemem(e_v);
    EXEC_SQL(SQL);
    if ((i + 1) % 20 == 0) fo_scheduler_heart(0);
  }

  /* Ports */
  for (int i = 0; i < pi->portCount; i++) {
    char *e_p = EscapeLit(db_conn, pi->ports[i]);
    SQL = BuildSQL("INSERT INTO pkg_container_port (pkg_fk,port) VALUES (%d,%s);",
                   pkg_pk, e_p);
    PQfreemem(e_p);
    EXEC_SQL(SQL);
  }

  /* Labels */
  for (int i = 0; i < pi->labelCount; i++) {
    char *e_k = EscapeLit(db_conn, pi->labels_kv[i].key ? pi->labels_kv[i].key : "");
    char *e_v = EscapeLit(db_conn, pi->labels_kv[i].val ? pi->labels_kv[i].val : "");
    SQL = BuildSQL("INSERT INTO pkg_container_label (pkg_fk,lbl_key,lbl_val)"
                   " VALUES (%d,%s,%s);", pkg_pk, e_k, e_v);
    PQfreemem(e_k); PQfreemem(e_v);
    EXEC_SQL(SQL);
  }

  /* Layer history */
  for (int i = 0; i < pi->layerCount && pi->layers; i++) {
    char *e_lid = EscapeLit(db_conn, pi->layers[i].layerId);
    char *e_cby = EscapeLit(db_conn, pi->layers[i].createdBy);
    SQL = BuildSQL(
      "INSERT INTO pkg_container_layer"
      " (pkg_fk,layer_index,layer_id,created_by,empty_layer)"
      " VALUES (%d,%d,%s,%s,%s);",
      pkg_pk, i, e_lid, e_cby,
      pi->layers[i].emptyLayer ? "TRUE" : "FALSE");
    PQfreemem(e_lid); PQfreemem(e_cby);
    EXEC_SQL(SQL);
    if ((i + 1) % 20 == 0) fo_scheduler_heart(0);
  }

#undef EXEC_SQL

  result = PQexec(db_conn, "COMMIT;");
  if (fo_checkPQcommand(db_conn, result, "COMMIT", __FILE__, __LINE__)) exit(-1);
  PQclear(result);

  if (pi->uploadPk > 0) {
    int nPkgs = ScanContainerPackages(pi->uploadPk, pkg_pk, pi);
    if (nPkgs < 0) {
      LOG_WARNING("containeragent: package scan failed for upload %ld\n",
                  pi->uploadPk);
    } else {
      LOG_NOTICE("containeragent: recorded %d packages for upload %ld\n",
                 nPkgs, pi->uploadPk);
    }
  }

  return 0;
}

/* =========================================================================
 * ProcessUpload
 * ========================================================================= */
int ProcessUpload(long upload_pk)
{
  char SQL[MAXCMD];
  PGresult *result;

  struct containerpkginfo *pi = calloc(1, sizeof(*pi));
  if (!upload_pk) { free(pi); return -1; }

  char *ut = GetUploadtreeTableName(db_conn, upload_pk);
  if (!ut) ut = strdup("uploadtree_a");

  /* Get root uploadtree node */
  snprintf(SQL, sizeof(SQL),
    "SELECT uploadtree_pk,lft,rgt,pfile_fk FROM %s"
    " WHERE upload_fk=%ld AND parent IS NULL LIMIT 1",
    ut, upload_pk);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
    { free(ut); free(pi); exit(-1); }
  if (PQntuples(result) == 0) {
    LOG_ERROR("containeragent: no root uploadtree row for upload %ld\n", upload_pk);
    PQclear(result); free(ut); free(pi); return -1;
  }
  long rootUploadtreePk = atol(PQgetvalue(result, 0, 0));
  long rootPfileFk      = atol(PQgetvalue(result, 0, 3));
  PQclear(result);

  /* Already processed? */
  snprintf(SQL, sizeof(SQL),
    "SELECT 1 FROM pkg_container WHERE pfile_fk=%ld LIMIT 1;", rootPfileFk);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
    { free(ut); free(pi); exit(-1); }
  if (PQntuples(result) > 0) {
    PQclear(result);
    LOG_NOTICE("containeragent: upload %ld already processed\n", upload_pk);
    free(ut); free(pi); return 0;
  }
  PQclear(result);

  /* Detect Docker (manifest.json) at depth 1 or 2 */
  snprintf(SQL, sizeof(SQL),
    "SELECT 1 FROM %s"
    " WHERE upload_fk=%ld AND ufile_name='manifest.json'"
    "   AND (parent=%ld OR parent IN ("
    "     SELECT uploadtree_pk FROM %s WHERE upload_fk=%ld AND parent=%ld"
    "   )) LIMIT 1",
    ut, upload_pk, rootUploadtreePk,
    ut, upload_pk, rootUploadtreePk);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
    { free(ut); free(pi); exit(-1); }
  int hasManifest = (PQntuples(result) > 0);
  PQclear(result);

  /* Detect OCI (index.json) at depth 1 or 2 */
  snprintf(SQL, sizeof(SQL),
    "SELECT 1 FROM %s"
    " WHERE upload_fk=%ld AND ufile_name='index.json'"
    "   AND (parent=%ld OR parent IN ("
    "     SELECT uploadtree_pk FROM %s WHERE upload_fk=%ld AND parent=%ld"
    "   )) LIMIT 1",
    ut, upload_pk, rootUploadtreePk,
    ut, upload_pk, rootUploadtreePk);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
    { free(ut); free(pi); exit(-1); }
  int hasIndex = (PQntuples(result) > 0);
  PQclear(result);

  free(ut);

  if (!hasManifest && !hasIndex) {
    LOG_NOTICE("containeragent: upload %ld is not a container image\n", upload_pk);
    free(pi);
    return 0;
  }

  memset(pi, 0, sizeof(*pi));
  pi->pFileFk  = rootPfileFk;
  pi->uploadPk = upload_pk;

  snprintf(SQL, sizeof(SQL),
    "SELECT pfile_sha1||'.'||pfile_md5||'.'||pfile_size"
    " FROM pfile WHERE pfile_pk=%ld", rootPfileFk);
  result = PQexec(db_conn, SQL);
  if (fo_checkPQresult(db_conn, result, SQL, __FILE__, __LINE__))
    { free(pi); exit(-1); }
  if (PQntuples(result) > 0)
    strncpy(pi->pFile, PQgetvalue(result, 0, 0), sizeof(pi->pFile) - 1);
  PQclear(result);

  int rc;
  if (hasManifest) {
    LOG_NOTICE("containeragent: upload %ld detected as Docker image\n", upload_pk);
    rc = GetMetadataDocker(upload_pk, pi);
  } else {
    LOG_NOTICE("containeragent: upload %ld detected as OCI image\n", upload_pk);
    rc = GetMetadataOCI(upload_pk, pi);
  }

  if (rc == 0)
    RecordMetadataContainer(pi);
  else
    LOG_WARNING("containeragent: metadata extraction failed for upload %ld\n",
                upload_pk);

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
  if (!pi) return;
  for (int i = 0; i < pi->envCount;   i++) free(pi->envVars[i]);
  for (int i = 0; i < pi->portCount;  i++) free(pi->ports[i]);
  for (int i = 0; i < pi->labelCount; i++) {
    free(pi->labels_kv[i].key);
    free(pi->labels_kv[i].val);
  }
  free(pi->envVars);   pi->envVars   = NULL; pi->envCount   = 0;
  free(pi->ports);     pi->ports     = NULL; pi->portCount  = 0;
  free(pi->labels_kv); pi->labels_kv = NULL; pi->labelCount = 0;
  free(pi->layers);    pi->layers    = NULL; pi->layerCount = 0;
  pi->layerCap      = 0;
  pi->manifestCount = 0;
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
