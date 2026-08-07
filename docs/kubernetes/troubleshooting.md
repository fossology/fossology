# FOSSology Kubernetes — Troubleshooting Guide

## 1. SSH Key Format Wrong

**Symptom:** Scheduler logs show `Permission denied (publickey)` when
trying to reach workers.

**Diagnose:**
```bash
kubectl logs deployment/fossology-scheduler -c scheduler -n fossology | grep -i "ssh\|permission"
kubectl exec -it fossology-workers-0 -n fossology -- cat /home/fossy/.ssh/authorized_keys
```

**Fix:**
1. Verify the SSH secret has all three keys:
```bash
kubectl get secret fossology-ssh -n fossology -o jsonpath='{.data}' | python3 -c "import sys,json; [print(k) for k in json.load(sys.stdin)]"
```
Expected keys: `id_rsa`, `id_rsa.pub`, `authorized_keys`

2. Regenerate if needed:
```bash
ssh-keygen -t ed25519 -f /tmp/fossology-ssh -N "" -q
kubectl delete secret fossology-ssh -n fossology
kubectl create secret generic fossology-ssh \
  --namespace fossology \
  --from-file=id_rsa=/tmp/fossology-ssh \
  --from-file=id_rsa.pub=/tmp/fossology-ssh.pub \
  --from-file=authorized_keys=/tmp/fossology-ssh.pub \
  --from-literal=known_hosts=""
kubectl rollout restart deployment/fossology-scheduler -n fossology
kubectl rollout restart statefulset/fossology-workers -n fossology
```

---

## 2. PVC Stuck in Pending

**Symptom:** Pods are in `Pending` state. `kubectl describe pod` shows
`persistentvolumeclaim "fossology-repo" not found` or PVC is `Pending`.

**Diagnose:**
```bash
kubectl get pvc -n fossology
kubectl describe pvc fossology-repo -n fossology
```

**Fix:**
1. Check if the storage class exists and supports `ReadWriteMany`:
```bash
kubectl get storageclass
```
2. If no RWX class exists, install one:
```bash
# For local/kind: use standard class with ReadWriteOnce (single-node only)
# For production: install NFS provisioner or use cloud file storage
helm install nfs-provisioner nfs-subdir-external-provisioner/nfs-subdir-external-provisioner \
  --set nfs.server=<NFS_SERVER_IP> \
  --set nfs.path=<NFS_EXPORT_PATH>
```
3. Update values:
```yaml
repository:
  storage:
    className: nfs-client
```

---

## 3. Scheduler Can't Resolve Worker DNS Names

**Symptom:** Scheduler logs: `Could not resolve hostname
fossology-workers-0.fossology-workers-headless...`

**Diagnose:**
```bash
kubectl exec -it deployment/fossology-scheduler -c scheduler -n fossology -- \
  nslookup fossology-workers-0.fossology-workers-headless.fossology.svc.cluster.local
```

**Fix:**
1. Verify the headless service exists and selects worker pods:
```bash
kubectl get svc fossology-workers-headless -n fossology
kubectl get endpoints fossology-workers-headless -n fossology
```
2. Verify worker pods have the correct label:
```bash
kubectl get pods -l app=fossology-worker -n fossology
```
3. If no endpoints, check the label selector matches between the headless
   service and the worker StatefulSet.

---

## 4. fo-postinstall Job Fails

**Symptom:** Helm install hangs or the `fossology-postinstall` job shows
`Error` or `BackoffLimitExceeded`.

**Diagnose:**
```bash
kubectl get jobs -n fossology
kubectl logs job/fossology-postinstall -n fossology
kubectl logs job/fossology-postinstall -c wait-for-db -n fossology
```

**Fix:**
1. If the init container is stuck, the database isn't ready:
```bash
kubectl get pods -l app.kubernetes.io/component=database -n fossology
kubectl logs fossology-db-0 -n fossology
```
2. Check the database secret has correct credentials:
```bash
kubectl get secret fossology-db -n fossology -o jsonpath='{.data.POSTGRES_PASSWORD}' | base64 -d
```
3. Delete and retry:
```bash
kubectl delete job fossology-postinstall -n fossology
helm upgrade fossology kubernetes/helm/fossology/ -n fossology
```

---

## 5. Config-Sync Sidecar in CrashLoopBackOff

**Symptom:** The `config-sync` container in the scheduler pod keeps restarting.

**Diagnose:**
```bash
kubectl logs deployment/fossology-scheduler -c config-sync -n fossology --previous
```

**Fix:**
1. **RBAC missing**: Check if the ServiceAccount has the required Role:
```bash
kubectl get rolebinding -n fossology
kubectl get role fossology-config-sync -n fossology -o yaml
```
The role needs: `get`, `list`, `watch` on `pods`.

2. **Wrong namespace**: Check the `POD_NAMESPACE` env var:
```bash
kubectl get deployment fossology-scheduler -n fossology -o jsonpath='{.spec.template.spec.containers[?(@.name=="config-sync")].env}'
```

3. **Template file missing**: Verify the configmap is mounted:
```bash
kubectl exec deployment/fossology-scheduler -c config-sync -n fossology -- ls /config-source/
```

---

## 6. Worker Pods Ready But Not in [HOSTS]

**Symptom:** Workers are running but the scheduler doesn't dispatch
jobs to them. Scheduler logs show no hosts available.

**Diagnose:**
```bash
kubectl exec deployment/fossology-scheduler -c scheduler -n fossology -- \
  cat /usr/local/etc/fossology/fossology.conf | grep -A 20 "\[HOSTS\]"
```

**Fix:**
1. Check if worker pods have the correct label:
```bash
kubectl get pods -l app=fossology-worker -n fossology --show-labels
```
2. The label selector in config-sync must match. Check:
```bash
kubectl get deployment fossology-scheduler -n fossology -o yaml | grep label-selector
```
3. Force a config refresh:
```bash
kubectl exec deployment/fossology-scheduler -c config-sync -n fossology -- pkill -HUP python3
```

---

## 7. Scans Queue But Never Run

**Symptom:** Jobs appear in the queue (UI shows "queued") but never start.
Scheduler is running.

**Diagnose:**
```bash
kubectl logs deployment/fossology-scheduler -c scheduler -n fossology | grep -i "blacklist\|invalid\|scheduler_start"
```

**Fix:**
This is likely the **duplicate `--scheduler_start` bug** (fixed in PR1).
If running a version without the fix:
1. Update to a version with PR1 merged
2. Or apply the [agent-wrapper workaround](https://github.com/mandar1045/fossology-k8s-poc/blob/main/manifests/images/worker/agent-wrapper.sh)

---

## 8. KEDA Not Scaling

**Symptom:** Jobs are pending but worker count doesn't increase.

**Diagnose:**
```bash
kubectl get scaledobject -n fossology
kubectl describe scaledobject fossology-workers -n fossology
kubectl get hpa -n fossology
```

**Fix:**
1. **Connection string wrong**: Verify the database secret:
```bash
kubectl get secret fossology-db -n fossology -o jsonpath='{.data.KEDA_DB_CONNECTION}' | base64 -d
```
2. **Query returns NULL**: Test the query manually:
```bash
kubectl exec -it fossology-db-0 -n fossology -- \
  psql -U fossy -d fossology -c "SELECT COUNT(*) FROM jobqueue WHERE jq_endtime IS NULL AND jq_starttime IS NULL"
```
3. **KEDA not installed**: Verify KEDA operator is running:
```bash
kubectl get pods -n keda
```

---

## 9. Web UI Returns 502

**Symptom:** Browser shows 502 Bad Gateway or "Service Unavailable".

**Diagnose:**
```bash
kubectl get pods -l app.kubernetes.io/component=web -n fossology
kubectl logs deployment/fossology-web -n fossology --tail=20
```

**Fix:**
1. The web pod needs to connect to the scheduler service. Check:
```bash
kubectl get svc fossology-scheduler -n fossology
kubectl exec deployment/fossology-web -n fossology -- \
  curl -s http://fossology-scheduler:24693/ || echo "Connection failed"
```
2. If the scheduler pod isn't running, check:
```bash
kubectl get pods -l app.kubernetes.io/component=scheduler -n fossology
kubectl describe pod -l app.kubernetes.io/component=scheduler -n fossology
```

---

## 10. Repository PVC Full

**Symptom:** Uploads fail. Worker or web logs show "No space left on device".

**Diagnose:**
```bash
kubectl exec fossology-workers-0 -n fossology -- df -h /srv/fossology/repository
kubectl get pvc fossology-repo -n fossology
```

**Fix:**
1. **Expand the PVC** (if storage class supports it):
```bash
kubectl patch pvc fossology-repo -n fossology -p '{"spec":{"resources":{"requests":{"storage":"200Gi"}}}}'
```
2. **Clean old uploads** via the FOSSology UI (Admin → Maintenance)
3. **Migrate to larger storage**: Create a new PVC, copy data with a job,
   update the Helm release
