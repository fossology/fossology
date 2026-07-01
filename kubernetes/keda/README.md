# KEDA Autoscaling for FOSSology Workers

This directory contains [KEDA](https://keda.sh/) ScaledObject manifests for
auto-scaling FOSSology worker pods based on PostgreSQL job queue pressure.

If you are deploying through the Helm chart, you can also enable chart-managed
KEDA resources with:

```yaml
keda:
  enabled: true
```

When `workers.pools` is configured in chart values, the chart renders one
`ScaledObject` per pool automatically for pools that advertise `agentCaps`
or provide an explicit `keda.query`.

If you keep a generic catch-all pool alongside dedicated pools, set an
explicit `keda.query` for that generic pool. The chart intentionally avoids
guessing a catch-all SQL query in multi-pool mode because it would overlap
the dedicated pool queries.

## Prerequisites

Install KEDA in your cluster:

```bash
helm repo add kedacore https://kedacore.github.io/charts
helm repo update
helm install keda kedacore/keda --namespace keda --create-namespace
```

## Setup

1. **Create the database connection secret** with a `KEDA_DB_CONNECTION` key:

```bash
kubectl create secret generic fossology-db \
  --namespace fossology \
  --from-literal=KEDA_DB_CONNECTION="host=fossology-db port=5432 dbname=fossology user=fossy password=fossy sslmode=disable"
```

2. **Apply the ScaledObjects:**

```bash
# Generic worker scaling (works without PR3)
kubectl apply -f kubernetes/keda/scaledobject-workers.yaml

# Per-agent-type scaling (requires PR3 for routing to work)
kubectl apply -f kubernetes/keda/scaledobject-workers-nomos.yaml
kubectl apply -f kubernetes/keda/scaledobject-workers-copyright.yaml
```

## Files

| File | Target | Scales On |
|------|--------|-----------|
| `scaledobject-workers.yaml` | `fossology-workers` StatefulSet | All pending jobs in jobqueue |
| `scaledobject-workers-nomos.yaml` | `fossology-workers-nomos` StatefulSet | Pending nomos jobs |
| `scaledobject-workers-copyright.yaml` | `fossology-workers-copyright` StatefulSet | Pending copyright/ecc/keyword/ipra jobs |

## Verify Scaling

```bash
# Check if KEDA created the HPA
kubectl get hpa -n fossology

# Watch worker pods scale
kubectl get pods -l app.kubernetes.io/component=worker -n fossology -w

# Check KEDA ScaledObject status
kubectl get scaledobject -n fossology
kubectl describe scaledobject fossology-workers -n fossology
```

## Tuning

- **`targetQueryValue`**: Lower = more aggressive scaling. Default `"5"` means
  scale up for every 5 pending jobs.
- **`pollingInterval`**: How often KEDA checks the metric (seconds).
- **`cooldownPeriod`**: How long to wait after last trigger before scaling down.
- **`maxReplicaCount`**: Upper bound on pod count.

## Important Notes

- The per-agent-type ScaledObjects (`nomos`, `copyright`) require **PR3**
  (scheduler host capability extension) for proper routing. Without PR3, all
  workers receive all job types regardless of which ScaledObject triggered scaling.
- KEDA does **not** manage the generic `fossology-workers` StatefulSet if
  per-agent-type StatefulSets are used instead. Choose one approach.
