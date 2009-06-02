# FOSSology Makefile - projects/adam/licens_test
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

REV=`svnversion -nc ${PROJECT_DIR} | sed -e 's/^[^:]*://;s/[A-Za-z]//'`

TOP=../../../fossology
VARS=$(TOP)/Makefile.conf
include $(VARS)

CONF=license_test.conf
CACHE=license_test.cache

all: $(CONF) $(CACHE)

$(CONF): $(CACHE)
	echo $(SYSCONFDIR)/$(PROJECT)/Db.conf > $(CONF)
	echo $(CACHE) >> $(CONF)
	echo $(REV) >> $(CONF)

$(CACHE): license_test_model.py pos.txt neg.txt license_test.py license_test_agent.py
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
