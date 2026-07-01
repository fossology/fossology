{{/*
FOSSology Helm chart template helpers.
*/}}

{{- define "fossology.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{- define "fossology.fullname" -}}
{{- if .Values.fullnameOverride -}}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" -}}
{{- else -}}
{{- include "fossology.name" . -}}
{{- end -}}
{{- end -}}

{{- define "fossology.labels" -}}
helm.sh/chart: {{ .Chart.Name }}-{{ .Chart.Version | replace "+" "_" }}
app.kubernetes.io/name: {{ include "fossology.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end -}}

{{- define "fossology.selectorLabels" -}}
app.kubernetes.io/name: {{ include "fossology.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end -}}

{{- define "fossology.serviceAccountName" -}}
{{- if .Values.serviceAccount.create -}}
{{- default (printf "%s-scheduler" (include "fossology.fullname" .)) .Values.serviceAccount.name -}}
{{- else -}}
{{- default "default" .Values.serviceAccount.name -}}
{{- end -}}
{{- end -}}

{{/* Database host — internal StatefulSet or external */}}
{{- define "fossology.dbHost" -}}
{{- printf "%s-db" (include "fossology.fullname" .) -}}
{{- end -}}

{{/* Image references */}}
{{- define "fossology.webImage" -}}
{{- printf "%s:%s" .Values.image.web.repository (toString .Values.image.web.tag) -}}
{{- end -}}

{{- define "fossology.schedulerImage" -}}
{{- printf "%s:%s" .Values.image.scheduler.repository (toString .Values.image.scheduler.tag) -}}
{{- end -}}

{{- define "fossology.workerImage" -}}
{{- printf "%s:%s" .Values.image.worker.repository (toString .Values.image.worker.tag) -}}
{{- end -}}

{{- define "fossology.postgresImage" -}}
{{- printf "%s:%s" .Values.image.postgres.repository (toString .Values.image.postgres.tag) -}}
{{- end -}}

{{/* Resource names */}}
{{- define "fossology.repoPvcName" -}}
{{- printf "%s-repo" (include "fossology.fullname" .) -}}
{{- end -}}

{{- define "fossology.dbPvcName" -}}
{{- printf "%s-db-data" (include "fossology.fullname" .) -}}
{{- end -}}

{{- define "fossology.workersHeadless" -}}
{{- printf "%s-workers-headless" (include "fossology.fullname" .) -}}
{{- end -}}

{{- define "fossology.workerLabelSelector" -}}
app.kubernetes.io/name={{ include "fossology.name" . }},app.kubernetes.io/instance={{ .Release.Name }},fossology.org/role=worker
{{- end -}}

{{- define "fossology.workerStatefulSetName" -}}
{{- $root := .root -}}
{{- $pool := .pool | default dict -}}
{{- if $pool.name -}}
{{- printf "%s-workers-%s" (include "fossology.fullname" $root) $pool.name | trunc 63 | trimSuffix "-" -}}
{{- else -}}
{{- printf "%s-workers" (include "fossology.fullname" $root) -}}
{{- end -}}
{{- end -}}

{{- define "fossology.workerPoolName" -}}
{{- $pool := .pool | default dict -}}
{{- default "generic" $pool.name -}}
{{- end -}}

{{/* Common environment variables */}}
{{- define "fossology.envDatabase" -}}
- name: FOSSOLOGY_DB_HOST
  value: {{ include "fossology.dbHost" . | quote }}
- name: FOSSOLOGY_DB_NAME
  value: {{ .Values.db.name | quote }}
- name: FOSSOLOGY_DB_USER
  value: {{ .Values.db.user | quote }}
- name: FOSSOLOGY_DB_PASSWORD
  valueFrom:
    secretKeyRef:
      name: {{ .Values.db.secretName }}
      key: POSTGRES_PASSWORD
{{- end -}}

{{- define "fossology.envPodNamespace" -}}
- name: POD_NAMESPACE
  valueFrom:
    fieldRef:
      fieldPath: metadata.namespace
{{- end -}}
