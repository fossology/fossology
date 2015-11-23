#!/usr/bin/env bash

`dirname $0`/../../spdx2/agent/spdx2 --outputFormat=`basename "${0%.sh}"` $@
