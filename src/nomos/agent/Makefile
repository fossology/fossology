# FOSSology Makefile - agent/nomos
# Copyright (C) 2006-2014 Hewlett-Packard Development Company, L.P.
TOP=../../..
VARS=$(TOP)/Makefile.conf
include $(VARS)

EXE = nomos

SPEC=STRINGS.in
CHECK=CHECKSTR
PRE=PRECHECK
PDATA=_split_words
LICFIX=GENSEARCHDATA

OBJS=licenses.o list.o parse.o process.o nomos_regex.o util.o nomos_gap.o nomos_utils.o doctorBuffer_utils.o # sources.o DMalloc.o
GENOBJS=_precheck.o _autodata.o
HDRS=nomos.h $(OBJS:.o=.h) _autodefs.h
COVERAGE=$(OBJS:%.o=%_cov.o)
GENOBJS_COV=$(GENOBJS:%.o=%_cov.o)

LOCAL_CFLAGS= $(DB_CFLAGS) $(REPO_CFLAGS) $(AGENT_CFLAGS) -lpq $(ALL_CFLAGS)
LOCAL_CFLAGSO= $(DB_CFLAGSO) $(REPO_CFLAGSO) $(AGENT_CFLAGSO) $(ALL_CFLAGS)

all: encode nomos libnomos.a

debug: nomos-gl

nomos: nomos.o $(OBJS) $(GENOBJS)
	$(CC) nomos.o $(OBJS) $(GENOBJS) $(LOCAL_CFLAGS) $(FO_LDFLAGS) -o $@

nomos.o: nomos.c $(HDRS) $(DB) $(REPO) $(AGENTLIB) $(VARS)
	$(CC) -c $< $(LOCAL_CFLAGSO) $(DEFS)

nomos-gl: nomos-g.o $(OBJS) $(GENOBJS)
	$(CC) nomos.o $(OBJS) $(GENOBJS) $(LOCAL_CFLAGS) -o $@

nomos-g.o: nomos.c $(HDRS) $(DB) $(REPO) $(AGENTLIB) $(VARS)
	$(CC) -c  -l /usr/lib/libefence.a $< $(LOCAL_CFLAGSO)

$(OBJS) $(GENOBJS): %.o: %.c $(HDRS) $(DB) $(VARS)
	$(CC) -c $< $(DB_CFLAGSO) $(ALL_CFLAGS)

#
# Non "standard" preprocessing stuff starts here...
#

encode: encode.o
	$(CC) $(CFLAGS) -o $@ $@.c

_precheck.c:	_autodata.c $(PRE) $(CHECK)
#	@echo "NOTE: _autodata.c has changed --> regenerate _precheck.c"
	./$(PRE)
	./$(CHECK)
#	@$(MAKE) $(STRINGS) $(KEYS)

_autodefs.h _autodata.c:	$(SPEC) $(LICFIX)
	@echo "NOTE: GENSEARCHDATA takes 1-2 minutes to run"
	./$(LICFIX)

#
# Non "standard" preprocessing stuff ends here...
#

install: all
	@echo "make nomos agent install"
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)
#	$(INSTALL_PROGRAM) encode  $(DESTDIR)$(AGENTDIR)/encode

uninstall:
	@echo "make nomos agent uninstall"
#	rm -f $(DESTDIR)$(AGENTDIR)/encode
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

test: all
	@echo "*** No tests available for agent/$(EXE) ***"

coverage:
	@echo "NOTICE: no coverage available for nomos"

clean:
	rm -f encode nomos  core *.a *.gc* \
           _autodata.c _autodefs.c _autodefs.h _precheck.c \
           _strings.data _STRFILTER strings.HISTOGRAM words.HISTOGRAM \
           split.OTHER checkstr.OK

libnomos.a: $(OBJS) $(GENOBJS)
	ar cvr $@ $(OBJS) $(GENOBJS)

libnomos_cov.a: $(COVERAGE) $(GENOBJS)
	ar cvr $@ $(COVERAGE) $(GENOBJS)

include $(DEPS)

.PHONY: all install uninstall clean test
