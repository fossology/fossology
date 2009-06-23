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

TEMP := $(shell find ../Training/License_Test/ -type f -not \( -regex ".*meta$$" \)  -not \( -wholename "*.svn*" \) > training.txt)
TEMP := $(shell python ../date_files.py training.txt training.txt)

all: $(CONF) $(CACHE)

$(CONF): $(CACHE)
	@echo db_conf=$(SYSCONFDIR)/$(PROJECT)/Db.conf\; > $(CONF)
	@echo cache_file=$(CACHE)\; >> $(CONF)
	@echo version=$(REV)\; >> $(CONF)

$(CACHE): license_test_model.py license_test.py license_test_agent.py training.txt
	rm -f $(CACHE)
	python license_test.py -f training.txt --cache $(CACHE)

test: all
	python license_test.py --cache $(CACHE) $(shell find /usr/share/doc/ -type f \( -regex ".*[Cc][Oo][Pp][Yy][Rr][Ii][Gg][Hh][Tt]$$" \))

clean:
	rm -f $(CACHE) $(CONF) training.txt

.PHONY: all clean test
