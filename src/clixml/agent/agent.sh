#!/usr/bin/env bash

`dirname $0`/../../clixml/agent/clixml --outputFormat=`basename "${0%.sh}"` $@
