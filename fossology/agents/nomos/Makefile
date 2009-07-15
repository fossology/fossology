# FOSSology Makefile - agent/fo_nomos
# Copyright (C) 2006-2009 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

SPEC=STRINGS.in
CHECK=CHECKSTR
PRE=PRECHECK
PDATA=_split_words
LICFIX=GENSEARCHDATA


OBJS=_precheck.o _autodata.o licenses.o list.o nomos.o parse.o process.o regex.o util.o # sources.o DMalloc.o
SRCS=$(OBJS:.o=.c)
HDRS=_autodefs.h licenses.h list.h nomos.h nomos_regex.h parse.h process.h util.h

DEF=-D_FILE_OFFSET_BITS=64 -D__USE_LARGEFILE64
CFLAGS_LOCAL= $(DEF) $(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq -lmagic $(ALL_CFLAGS)

all: encode fo_nomos

fo_nomos: $(OBJS) $(DB) $(REPO) $(VARS)
	$(CC) $< $(CFLAGS_LOCAL) -o $@

#$(OBJS): %.o: %.c %.h $(DB) $(VARS)
$(OBJS): $(SRCS) $(HDRS) $(DB) $(VARS)
	$(CC) -c $< $(DEF) $(CFLAGS_DBO) $(ALL_CFLAGS)

#
# Non "standard" preprocessing stuff starts here...
#
encode: encode.c
	$(CC) $(CFLAGS) -o $@ $@.c

_autodefs.h _autodata.c:	$(SPEC) $(LICFIX)
	./$(LICFIX)

_precheck.c:	_autodata.c # $(PRE)
#	@echo "NOTE: _autodata.c has changed --> regenerate _precheck.c"
	./$(PRE)
	./$(CHECK)
#	@$(MAKE) $(STRINGS) $(KEYS)

#
# Non "standard" preprocessing stuff ends here...
#

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(AGENTDIR)/$(EXE)

uninstall:
	rm -f $(DESTDIR)$(AGENTDIR)/$(EXE)

test: all
	@echo "*** No tests available for agent/$(EXE) ***"

clean:
	rm -f $(EXE) *.o core

include $(DEPS)

.PHONY: all install uninstall clean test
