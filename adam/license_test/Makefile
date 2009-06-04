# FOSSology Makefile - projects/adam/licens_test
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

PWD = $(shell pwd)
PYTHONPATH := ${PYTHONPATH}:../utils
export PYTHONPATH

REV=`svnversion -nc | sed -e 's/^[^:]*://;s/[A-Za-z]//'`

TOP=../../../fossology
VARS=$(TOP)/Makefile.conf
include $(VARS)

CONF=license_test.conf
CACHE=license_test.cache

TEMP := $(shell find ../Licenses/ -type f -not \( -regex ".*meta$$" \)  -not \( -wholename "*.svn*" \) > pos.txt)
TEMP := $(shell find ../Training/License_Test/ -type f -not \( -regex ".*meta$$" \)  -not \( -wholename "*.svn*" \) > neg.txt)
TEMP := $(shell python ../date_files.py pos.txt pos.txt)
TEMP := $(shell python ../date_files.py neg.txt neg.txt)

all: $(CONF) $(CACHE)

$(CONF): $(CACHE)
	@echo db_conf=$(SYSCONFDIR)/$(PROJECT)/Db.conf\; > $(CONF)
	@echo cache_file=$(CACHE)\; >> $(CONF)
	@echo version=$(REV)\; >> $(CONF)

$(CACHE): license_test_model.py license_test.py license_test_agent.py pos.txt neg.txt
	rm -f $(CACHE)
	python license_test.py -p pos.txt -n neg.txt --cache $(CACHE)

pos.txt:
	find ../Licenses/ -type f -not \( -regex ".*meta$$" \)  -not \( -wholename "*.svn*" \) > pos.txt
	
neg.txt:
	find ../Training/License_Test/ -type f -not \( -regex ".*meta$$" \)  -not \( -wholename "*.svn*" \) > neg.txt
	
test: all
	@echo "*** No tests available for license_test ***"

clean:
	rm -f $(CACHE) $(CONF) pos.txt neg.txt

.PHONY: all clean test
