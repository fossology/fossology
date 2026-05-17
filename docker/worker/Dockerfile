ARG FOSSOLOGY_VERSION=4.4.0
FROM fossology/fossology:${FOSSOLOGY_VERSION}

# SPDX-FileCopyrightText: © 2024 FOSSology contributors
# SPDX-License-Identifier: GPL-2.0-only

# Install and configure OpenSSH server for scheduler-to-worker dispatch.
# The fo_scheduler dispatches scan agents to workers over SSH rather than
# kubectl exec — see docs/kubernetes/architecture.md for rationale.
RUN apt-get update && \
    apt-get install -y --no-install-recommends openssh-server && \
    rm -rf /var/lib/apt/lists/* && \
    mkdir -p /run/sshd /root/.ssh /home/fossy/.ssh && \
    chmod 700 /root/.ssh /home/fossy/.ssh && \
    # Harden sshd configuration
    sed -i 's/#PermitRootLogin prohibit-password/PermitRootLogin yes/' /etc/ssh/sshd_config && \
    sed -i 's/#PubkeyAuthentication yes/PubkeyAuthentication yes/' /etc/ssh/sshd_config && \
    sed -i 's/#PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config && \
    sed -i 's/PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config && \
    sed -i 's/#AllowTcpForwarding yes/AllowTcpForwarding no/' /etc/ssh/sshd_config && \
    sed -i 's/#X11Forwarding yes/X11Forwarding no/' /etc/ssh/sshd_config && \
    sed -i 's/X11Forwarding yes/X11Forwarding no/' /etc/ssh/sshd_config && \
    sed -i 's/#MaxStartups 10:30:100/MaxStartups 100:30:200/' /etc/ssh/sshd_config && \
    sed -i 's/#MaxSessions 10/MaxSessions 50/' /etc/ssh/sshd_config && \
    sed -i 's/#LogLevel INFO/LogLevel VERBOSE/' /etc/ssh/sshd_config && \
    # Generate host keys at build time (can be regenerated at runtime)
    ssh-keygen -A

# Install agent-wrapper shim and install-wrappers helper script
COPY docker/worker/agent-wrapper.sh /opt/fossology/agent-wrapper.sh
COPY docker/worker/install-wrappers.sh /opt/fossology/install-wrappers.sh
RUN chmod +x /opt/fossology/agent-wrapper.sh /opt/fossology/install-wrappers.sh && \
    /opt/fossology/install-wrappers.sh

COPY docker/worker/entrypoint.sh /entrypoint-worker.sh
RUN chmod +x /entrypoint-worker.sh

EXPOSE 22

ENTRYPOINT ["/bin/sh", "/entrypoint-worker.sh"]
