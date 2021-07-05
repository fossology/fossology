#!/bin/sh
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# version 2 as published by the Free Software Foundation.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
#
# SPDX-FileCopyrightText: 2021 Orange
# SPDX-License-Identifier: GPL-2.0-only
#
# Author: Nicolas Toussaint <nicolas1.toussaint@orange.com>

# Configure SMTP in database, with input from environment variables

. $(dirname $0)/fo-config-common.sh

f_log "Configure SMTP - Hostname: $FOSSOLOGY_CONF_SMTP_HOSTNAME"
# The domain to be used to send emails.
[ -n "$FOSSOLOGY_CONF_SMTP_HOSTNAME" ]   && f_update_db_sysconfig "SMTPHostName" "$FOSSOLOGY_CONF_SMTP_HOSTNAME"
[ -n "$FOSSOLOGY_CONF_SMTP_PORT" ]       && f_update_db_sysconfig "SMTPPort" "$FOSSOLOGY_CONF_SMTP_PORT"
# Sender Email
[ -n "$FOSSOLOGY_CONF_SMTP_FROM" ]       && f_update_db_sysconfig "SMTPFrom" "$FOSSOLOGY_CONF_SMTP_FROM"
# FOSSOLOGY_CONF_SMTP_AUTH: Algorithm to use for login
# Login => Encrypted
# None => No authentication
# Plain => Send as plain text
[ -n "$FOSSOLOGY_CONF_SMTP_AUTH" ]       && f_update_db_sysconfig "SMTPAuth" "$FOSSOLOGY_CONF_SMTP_AUTH"
[ -n "$FOSSOLOGY_CONF_SMTP_AUTH_USER" ]  && f_update_db_sysconfig "SMTPAuthUser" "$FOSSOLOGY_CONF_SMTP_AUTH_USER"
[ -n "$FOSSOLOGY_CONF_SMTP_AUTH_PWD" ]   && f_update_db_sysconfig "SMTPAuthPasswd" "$FOSSOLOGY_CONF_SMTP_AUTH_PWD"
# The SSL verification for connection is required?
# S -> Strict
# I -> Ignore
# W -> Warn
[ -n "$FOSSOLOGY_CONF_SMTP_SSL_VERIFY" ] && f_update_db_sysconfig "SMTPSslVerify" "$FOSSOLOGY_CONF_SMTP_SSL_VERIFY"
# Use TLS connection for SMTP?
# 1 -> Yes
# 2 -> No
[ -n "$FOSSOLOGY_CONF_SMTP_START_TLS" ]  && f_update_db_sysconfig "SMTPStartTls" "$FOSSOLOGY_CONF_SMTP_START_TLS"
echo
