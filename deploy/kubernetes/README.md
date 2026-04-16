<!--
SPDX-FileCopyrightText: © 2026 FOSSology contributors

SPDX-License-Identifier: GPL-2.0-only
-->
# Kubernetes Deployment

This directory contains a small Kubernetes baseline for running FOSSology with
an in-cluster PostgreSQL database and persistent storage for both PostgreSQL
data and the FOSSology repository.

The example is intentionally conservative:

- It uses the published `fossology/fossology` container image.
- It runs FOSSology as a single replica so the scheduler and web UI share the
  same repository volume.
- It exposes the web UI with a `ClusterIP` service so clusters can choose
  port-forwarding, an ingress controller, or a platform-specific load balancer.

## Included resources

- `namespace.yaml`: dedicated namespace for the deployment.
- `configmap.yaml`: non-sensitive database configuration.
- `secret.yaml`: example credentials for PostgreSQL and FOSSology.
- `postgres-pvc.yaml`: persistent volume claim for PostgreSQL data.
- `postgres-deployment.yaml`: PostgreSQL deployment.
- `postgres-service.yaml`: PostgreSQL service for the application.
- `repository-pvc.yaml`: persistent volume claim for the FOSSology repository.
- `fossology-deployment.yaml`: FOSSology application deployment.
- `fossology-service.yaml`: internal web service.
- `kustomization.yaml`: lets you deploy the full stack with one command.

## Deploy

Review and update the default credentials in `secret.yaml` before deploying.

```sh
kubectl apply -k deploy/kubernetes
kubectl -n fossology rollout status deployment/postgres
kubectl -n fossology rollout status deployment/fossology
```

To access the web UI locally:

```sh
kubectl -n fossology port-forward service/fossology-web 8081:80
```

Then open `http://127.0.0.1:8081/repo`.

Default login:

- Username: `fossy`
- Password: `fossy`

## Customization

- Change storage requests in `postgres-pvc.yaml` and `repository-pvc.yaml`.
- Set a `storageClassName` in the PVCs if your cluster does not provide a
  default storage class.
- Replace `fossology/fossology:latest` with a pinned release tag in
  `fossology-deployment.yaml` for reproducible environments.
- Add an ingress or load balancer if you want external cluster access.
- Use an external PostgreSQL service if your cluster already provides one.

## Operational notes

- The deployment uses one FOSSology replica because the scheduler and web UI
  share a filesystem repository. Scaling this deployment horizontally requires
  shared storage with an access mode that fits your cluster and workload.
- The bundled PostgreSQL deployment is a convenient starting point for
  development and evaluation. For production, a managed or separately operated
  PostgreSQL instance is usually the better fit.
- The health probes target `/repo/api/v1/health`, which matches the existing
  container healthcheck used in Docker Compose.

## Remove

```sh
kubectl delete -k deploy/kubernetes
```
