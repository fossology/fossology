# FOSSology Kubernetes — Local Quickstart Guide

This guide walks you through deploying FOSSology on a local Kubernetes cluster
using [kind](https://kind.sigs.k8s.io/) and Helm.

## Prerequisites

| Tool | Minimum Version | Install |
|------|-----------------|---------|
| Docker | 24.0+ | [docs.docker.com](https://docs.docker.com/get-docker/) |
| kind | 0.20+ | [kind.sigs.k8s.io](https://kind.sigs.k8s.io/docs/user/quick-start/#installation) |
| kubectl | 1.28+ | [kubernetes.io](https://kubernetes.io/docs/tasks/tools/) |
| Helm | 3.12+ | [helm.sh](https://helm.sh/docs/intro/install/) |
| ssh-keygen | any | Included with OpenSSH |

## 1. Clone and Setup

```bash
git clone https://github.com/fossology/fossology.git
cd fossology
```

## 2. Create kind Cluster

```bash
kind create cluster --name fossology --wait 120s
```

Verify the cluster is running:

```bash
kubectl cluster-info --context kind-fossology
```

## 3. Generate SSH Keys

The scheduler uses SSH to dispatch agents to worker pods. Generate a
dedicated key pair:

```bash
ssh-keygen -t ed25519 -f /tmp/fossology-ssh -N "" -q
```

## 4. Build and Load Worker Image

```bash
docker build \
  --build-arg FOSSOLOGY_VERSION=4.4.0 \
  -t fossology-worker:local \
  -f docker/worker/Dockerfile .

kind load docker-image fossology-worker:local --name fossology
```

## 5. Create Kubernetes Namespace and Secrets

```bash
kubectl create namespace fossology

# SSH secret
kubectl create secret generic fossology-ssh \
  --namespace fossology \
  --from-file=id_rsa=/tmp/fossology-ssh \
  --from-file=id_rsa.pub=/tmp/fossology-ssh.pub \
  --from-file=authorized_keys=/tmp/fossology-ssh.pub \
  --from-literal=known_hosts=""

# Database secret (development defaults)
kubectl create secret generic fossology-db \
  --namespace fossology \
  --from-literal=POSTGRES_DB=fossology \
  --from-literal=POSTGRES_USER=fossy \
  --from-literal=POSTGRES_PASSWORD=fossy
```

## 6. Install with Helm

```bash
helm install fossology kubernetes/helm/fossology/ \
  --namespace fossology \
  --set image.worker.repository=fossology-worker \
  --set image.worker.tag=local \
  --set image.worker.pullPolicy=Never \
  --wait \
  --timeout 10m
```

Watch pods come up:

```bash
kubectl get pods -n fossology -w
```

You should see:
- `fossology-db-0` — PostgreSQL
- `fossology-scheduler-*` — Scheduler + config-sync sidecar
- `fossology-web-*` — Web UI
- `fossology-workers-0`, `fossology-workers-1` — Worker pods

## 7. Access the Web UI

```bash
kubectl port-forward svc/fossology-web 8081:80 -n fossology
```

Open **http://localhost:8081/repo** in your browser.

**Default credentials:**
- Username: `fossy`
- Password: `fossy`

⚠️ **Change these immediately in production.**

## 8. Run a Test Scan

### Via the UI:
1. Log in → Upload → From File
2. Select a small tarball or source file
3. Choose agents: nomos, copyright
4. Click Upload and wait for the scan to complete

### Via the REST API:

```bash
# Get a JWT token
TOKEN=$(curl -s -X POST http://localhost:8081/repo/api/v2/tokens \
  -H "Content-Type: application/json" \
  -d '{"username":"fossy","password":"fossy","token_name":"test","token_scope":"write","token_expire":"2099-01-01"}' \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['Authorization'])")

# Upload a file
curl -X POST http://localhost:8081/repo/api/v2/uploads \
  -H "Authorization: Bearer $TOKEN" \
  -H "folderId: 1" \
  -H "uploadDescription: test scan" \
  -F "fileInput=@/path/to/your/file.tar.gz"
```

## 9. Scale Workers

```bash
kubectl scale statefulset fossology-workers --replicas=4 -n fossology
```

The config-sync sidecar will automatically detect new workers, update
`fossology.conf`, and trigger `fo_cli --reload` on the scheduler (with
`SIGHUP` fallback).

For dedicated per-agent pools, define `workers.pools` in a values file
instead of scaling the generic `fossology-workers` StatefulSet. Example:

```yaml
workers:
  pools:
    - name: nomos
      replicaCount: 2
      maxAgentsPerWorker: 12
      agentCaps: ["nomos", "ojo", "monk"]
    - name: copyright
      replicaCount: 1
      maxAgentsPerWorker: 6
      agentCaps: ["copyright", "ecc", "keyword", "ipra"]
```

With that values file, install or upgrade with:

```bash
helm upgrade --install fossology kubernetes/helm/fossology/ \
  --namespace fossology \
  -f values-pools.yaml
```

## 10. Tear Down

```bash
helm uninstall fossology -n fossology
kind delete cluster --name fossology
```

## Troubleshooting

### PVC stuck in Pending
**Cause:** kind uses `standard` storage class which doesn't support `ReadWriteMany`.

**Fix:** For local development, kind's `standard` class works with `ReadWriteOnce`.
If you need true RWX, install the [NFS provisioner](https://github.com/kubernetes-sigs/nfs-subdir-external-provisioner).

### Scheduler can't reach workers
**Diagnose:** `kubectl logs deployment/fossology-scheduler -c config-sync -n fossology`

**Fix:** Verify the SSH secret was created correctly and workers are ready:
```bash
kubectl get pods -l app=fossology-worker -n fossology
kubectl exec -it fossology-workers-0 -n fossology -- ssh-keyscan localhost
```

### fo-postinstall job fails
**Diagnose:** `kubectl logs job/fossology-postinstall -n fossology`

**Fix:** Usually means the database isn't ready. The init container should
wait, but check: `kubectl get pods -l app.kubernetes.io/component=database -n fossology`

### Config-sync sidecar in CrashLoopBackOff
**Diagnose:** `kubectl logs deployment/fossology-scheduler -c config-sync -n fossology`

**Fix:** Check RBAC: `kubectl get rolebinding -n fossology`. The ServiceAccount
needs `get`, `list`, `watch` on pods.

### Web UI returns 502
**Fix:** The web pod connects to the scheduler via `fossology-scheduler:24693`.
Ensure the scheduler pod and service are running:
```bash
kubectl get svc fossology-scheduler -n fossology
kubectl get pods -l app.kubernetes.io/component=scheduler -n fossology
```
