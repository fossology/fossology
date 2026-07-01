# FOSSology Kubernetes — Production Deployment Guide

This guide covers production-grade deployment considerations for
FOSSology on Kubernetes.

## 1. Storage

### ReadWriteMany (RWX) Storage

FOSSology requires a `ReadWriteMany` PVC for the repository volume, shared
between the scheduler, web, and all worker pods.

| Provider | Solution | Storage Class | Notes |
|----------|----------|---------------|-------|
| AWS | EFS CSI Driver | `efs-sc` | Best for AWS; elastic, pay-per-use |
| GCP | Filestore CSI | `filestore-sc` | Min 1TB for standard tier |
| Azure | Azure Files Premium | `azurefile-premium` | SMB-based, good performance |
| On-prem | NFS Subdir External Provisioner | `nfs-client` | Requires existing NFS server |
| On-prem | Rook-Ceph (CephFS) | `cephfs` | Self-managed, high performance |

**Configure in `values-production.yaml`:**

```yaml
repository:
  storage:
    size: 500Gi
    className: efs-sc  # Replace with your RWX storage class
  accessMode: ReadWriteMany
```

### When to Choose What

- **Cloud + simplicity**: Use managed file storage (EFS, Filestore, Azure Files)
- **Cloud + performance**: Use EBS/PD with a single-writer architecture (not recommended for FOSSology)
- **On-prem + existing NFS**: Use nfs-subdir-external-provisioner
- **On-prem + new deployment**: Use Rook-Ceph for integrated storage

## 2. Database

### Should You Use the Bundled PostgreSQL?

**No, for production.** Use a managed PostgreSQL service:
- AWS RDS PostgreSQL
- GCP Cloud SQL
- Azure Database for PostgreSQL
- Self-managed PostgreSQL with streaming replication

### External Database Configuration

1. Disable internal PostgreSQL:
```yaml
postgres:
  internal:
    enabled: false
```

2. Update the database secret:
```bash
kubectl create secret generic fossology-db \
  --namespace fossology \
  --from-literal=POSTGRES_DB=fossology \
  --from-literal=POSTGRES_USER=fossy \
  --from-literal=POSTGRES_PASSWORD='<strong-password>' \
  --from-literal=KEDA_DB_CONNECTION='host=your-rds.amazonaws.com port=5432 dbname=fossology user=fossy password=<password> sslmode=require'
```

### Connection Pooling

For high-concurrency deployments (>100 scans/day), add PgBouncer between
FOSSology and PostgreSQL:

```bash
helm install pgbouncer enbuild/pgbouncer \
  --set databases.fossology.host=your-rds.amazonaws.com \
  --set databases.fossology.port=5432
```

## 3. TLS and Ingress

### cert-manager + Let's Encrypt

```bash
# Install cert-manager
kubectl apply -f https://github.com/cert-manager/cert-manager/releases/download/v1.14.0/cert-manager.yaml

# Create ClusterIssuer
cat <<EOF | kubectl apply -f -
apiVersion: cert-manager.io/v1
kind: ClusterIssuer
metadata:
  name: letsencrypt-prod
spec:
  acme:
    server: https://acme-v02.api.letsencrypt.org/directory
    email: admin@example.com
    privateKeySecretRef:
      name: letsencrypt-prod
    solvers:
      - http01:
          ingress:
            class: nginx
EOF
```

### Ingress Values

```yaml
ingress:
  enabled: true
  className: nginx
  host: fossology.example.com
  path: /
  pathType: Prefix
  tls: true
  tlsSecretName: fossology-tls
  annotations:
    cert-manager.io/cluster-issuer: letsencrypt-prod
```

## 4. Resource Sizing

| Component | Min CPU | Min Memory | Recommended (100 scans/day) | Notes |
|-----------|---------|------------|----------------------------|-------|
| Web | 250m | 512Mi | 2 CPU / 4Gi × 3 replicas | PHP + Apache |
| Scheduler | 100m | 128Mi | 1 CPU / 2Gi × 1 replica | Single instance only |
| Worker | 500m | 1Gi | 4 CPU / 8Gi × 5 replicas | nomos needs ~2GB per instance |
| PostgreSQL | 250m | 512Mi | 4 CPU / 8Gi | Use managed service |

**nomos** is the most memory-hungry agent. If scans are failing with OOM,
increase worker memory limits first.

## 5. KEDA Autoscaling

### Install KEDA

```bash
helm repo add kedacore https://kedacore.github.io/charts
helm repo update
helm install keda kedacore/keda --namespace keda --create-namespace
```

### Apply ScaledObjects

```bash
kubectl apply -f kubernetes/keda/scaledobject-workers.yaml
```

### Tuning

- **`targetQueryValue: "3"`** — Scale up for every 3 pending jobs (aggressive)
- **`targetQueryValue: "10"`** — Scale up for every 10 pending jobs (conservative)
- **`cooldownPeriod: 600`** — Wait 10 minutes before scaling down
- **`pollingInterval: 15`** — Check every 15 seconds

## 6. Secrets Management

**Never store secrets in `values.yaml` for production.**

### Option 1: External Secrets Operator + AWS Secrets Manager

```bash
helm install external-secrets external-secrets/external-secrets \
  --namespace external-secrets --create-namespace
```

```yaml
apiVersion: external-secrets.io/v1beta1
kind: ExternalSecret
metadata:
  name: fossology-db
  namespace: fossology
spec:
  refreshInterval: 1h
  secretStoreRef:
    name: aws-secrets-manager
    kind: ClusterSecretStore
  target:
    name: fossology-db
  data:
    - secretKey: POSTGRES_PASSWORD
      remoteRef:
        key: fossology/production/db
        property: password
```

### Option 2: Sealed Secrets

```bash
kubeseal --format=yaml < secret.yaml > sealed-secret.yaml
kubectl apply -f sealed-secret.yaml
```

## 7. Backup

### What Needs Backing Up

1. **PostgreSQL data** — Scan metadata, user accounts, job history
2. **Repository PVC** — Uploaded files and extracted content

### Velero Backup

```bash
# Install Velero
velero install --provider aws --bucket fossology-backups \
  --secret-file ./credentials-velero

# Schedule daily backups
velero schedule create fossology-daily \
  --schedule="0 2 * * *" \
  --include-namespaces fossology \
  --ttl 720h

# Manual backup
velero backup create fossology-manual --include-namespaces fossology

# Restore
velero restore create --from-backup fossology-manual
```

### PostgreSQL-specific Backup

If using the bundled PostgreSQL:

```bash
kubectl exec -it fossology-db-0 -n fossology -- \
  pg_dump -U fossy fossology | gzip > fossology-$(date +%Y%m%d).sql.gz
```
