#!/usr/bin/env python3
# SPDX-FileCopyrightText: © 2024 FOSSology contributors
# SPDX-License-Identifier: GPL-2.0-only
"""
Config-sync sidecar script for the FOSSology scheduler pod.

Watches the Kubernetes API for ready worker pods and renders
the [HOSTS] section of fossology.conf with their stable DNS names.
Triggers a scheduler reload command when the configuration changes.
"""

import argparse
import json
import os
import signal
import subprocess
import sys
import tempfile
import time
import urllib.parse
import urllib.request
from pathlib import Path
from typing import Optional


def log(message: str) -> None:
    print(f"[config-sync] {message}", flush=True)


def read_service_account_file(path: str) -> str:
    return Path(path).read_text(encoding="utf-8").strip()


def fetch_ready_worker_pods(
    namespace: str, label_selector: str
) -> list[dict]:
    """Fetch running+ready worker pods from the Kubernetes API.

    Returns a list of dicts with keys: name, labels, annotations.
    """
    api_host = os.environ.get(
        "KUBERNETES_SERVICE_HOST", "kubernetes.default.svc"
    )
    api_port = os.environ.get("KUBERNETES_SERVICE_PORT", "443")
    token = read_service_account_file(
        "/var/run/secrets/kubernetes.io/serviceaccount/token"
    )
    ca_cert = "/var/run/secrets/kubernetes.io/serviceaccount/ca.crt"
    encoded_selector = urllib.parse.quote(label_selector, safe="")
    url = (
        f"https://{api_host}:{api_port}/api/v1/namespaces/{namespace}/pods"
        f"?labelSelector={encoded_selector}"
    )

    request = urllib.request.Request(
        url,
        headers={
            "Authorization": f"Bearer {token}",
            "Accept": "application/json",
        },
    )
    context = None
    if Path(ca_cert).exists():
        import ssl

        context = ssl.create_default_context(cafile=ca_cert)

    with urllib.request.urlopen(
        request, context=context, timeout=10
    ) as response:
        payload = json.load(response)

    ready_pods = []
    for item in payload.get("items", []):
        if item.get("status", {}).get("phase") != "Running":
            continue
        conditions = item.get("status", {}).get("conditions", [])
        if not any(
            c.get("type") == "Ready" and c.get("status") == "True"
            for c in conditions
        ):
            continue
        ready_pods.append(
            {
                "name": item["metadata"]["name"],
                "labels": item.get("metadata", {}).get("labels", {}),
                "annotations": item.get("metadata", {}).get(
                    "annotations", {}
                ),
            }
        )
    return sorted(ready_pods, key=lambda p: p["name"])


def render_hosts_block(
    pods: list[dict],
    namespace: str,
    headless_service: str,
    worker_conf_dir: str,
    max_agents: int,
    caps_label: str,
    max_agents_label: str,
) -> str:
    """Render the [HOSTS] section entries for fossology.conf."""
    lines = []
    for pod in pods:
        pod_name = pod["name"]
        fqdn = (
            f"{pod_name}.{headless_service}.{namespace}.svc.cluster.local"
        )
        labels = pod.get("labels", {})
        annotations = pod.get("annotations", {})
        host_max_agents = max_agents
        label_max_agents = labels.get(max_agents_label, "")
        if label_max_agents:
            try:
                host_max_agents = int(label_max_agents)
            except ValueError:
                log(
                    "invalid max-agents label on "
                    f"{pod_name}: {label_max_agents!r}; "
                    f"falling back to {max_agents}"
                )

        line = (
            f"{pod_name} = {fqdn} {worker_conf_dir} {host_max_agents}"
        )

        # Emit agents= field if the pod has an agent-caps annotation.
        agent_caps = annotations.get(caps_label, "")
        if agent_caps:
            line += f" agents={agent_caps}"

        lines.append(line)
        log(f"  host: {line}")
    return "\n".join(lines)


def write_if_changed(path: Path, content: str) -> bool:
    """Atomically write content if it differs from existing file."""
    if path.exists() and path.read_text(encoding="utf-8") == content:
        path.chmod(0o644)
        return False

    path.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.NamedTemporaryFile(
        "w", delete=False, dir=str(path.parent), encoding="utf-8"
    ) as handle:
        handle.write(content)
        temp_name = handle.name

    os.replace(temp_name, path)
    path.chmod(0o644)
    return True


def render_config(
    template_path: Path, hosts_block: str, scheduler_host: str
) -> str:
    """Replace placeholders in the fossology.conf template."""
    template = template_path.read_text(encoding="utf-8")
    return template.replace("__HOSTS__", hosts_block).replace(
        "__SCHEDULER_HOST__", scheduler_host
    )


def maybe_signal_scheduler(command: Optional[str]) -> None:
    """Run the configured scheduler reload command, if any."""
    if not command:
        return
    result = subprocess.run(
        command, shell=True, text=True, capture_output=True, check=False
    )
    if result.returncode == 0:
        log(f"reloaded scheduler with: {command}")
    else:
        log(
            "scheduler reload command failed "
            f"(exit={result.returncode}): "
            f"{result.stderr.strip() or result.stdout.strip()}"
        )


def run_once(args: argparse.Namespace) -> int:
    """Fetch worker pods, render config, optionally signal scheduler."""
    deadline = time.time() + args.timeout_seconds
    while True:
        pods = fetch_ready_worker_pods(
            args.namespace, args.label_selector
        )
        if len(pods) >= args.min_ready_workers:
            break
        if args.mode == "once" and time.time() >= deadline:
            log(
                f"timed out waiting for {args.min_ready_workers} "
                f"ready workers; currently ready: "
                f"{[p['name'] for p in pods]}"
            )
            return 1
        log(
            f"waiting for ready workers: need "
            f"{args.min_ready_workers}, currently have "
            f"{len(pods)} "
            f"({', '.join(p['name'] for p in pods) or 'none'})"
        )
        time.sleep(args.poll_interval_seconds)

    hosts_block = render_hosts_block(
        pods,
        args.namespace,
        args.headless_service,
        args.worker_conf_dir,
        args.max_agents_per_worker,
        args.caps_label,
        args.max_agents_label,
    )
    content = render_config(
        args.template, hosts_block, args.scheduler_host
    )
    changed = write_if_changed(args.output, content)
    rendered_hosts = ", ".join(p["name"] for p in pods)
    if changed:
        log(
            f"rendered {args.output} with {len(pods)} "
            f"ready worker(s): {rendered_hosts}"
        )
    else:
        log(
            f"configuration up to date for {len(pods)} "
            f"ready worker(s): {rendered_hosts}"
        )
    if changed:
        maybe_signal_scheduler(args.signal_command)
    return 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="FOSSology config-sync sidecar"
    )
    parser.add_argument("--template", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--namespace", required=True)
    parser.add_argument(
        "--label-selector", default="app=fossology-worker"
    )
    parser.add_argument(
        "--headless-service", default="fossology-workers-headless"
    )
    parser.add_argument(
        "--worker-conf-dir", default="/usr/local/etc/fossology"
    )
    parser.add_argument("--max-agents-per-worker", type=int, default=2)
    parser.add_argument("--min-ready-workers", type=int, default=1)
    parser.add_argument("--poll-interval-seconds", type=int, default=5)
    parser.add_argument("--timeout-seconds", type=int, default=300)
    parser.add_argument("--scheduler-host", required=True)
    parser.add_argument("--signal-command")
    parser.add_argument(
        "--mode", choices=("once", "loop"), default="once"
    )
    parser.add_argument(
        "--caps-label",
        default="fossology.org/agent-caps",
        help="Pod label key for per-host agent capability lists",
    )
    parser.add_argument(
        "--max-agents-label",
        default="fossology.org/max-agents",
        help="Pod label key for per-host max-agent capacity overrides",
    )
    return parser


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()
    if args.mode == "once":
        return run_once(args)

    while True:
        status = run_once(args)
        if status != 0:
            return status
        time.sleep(args.poll_interval_seconds)


if __name__ == "__main__":
    sys.exit(main())
