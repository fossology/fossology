# FOSSology Makefile - agent/nomos
# SPDX-FileCopyrightText: © 2006-2014 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

EXE = nomos

SPEC = STRINGS.in
CHECK = CHECKSTR
PRE = PRECHECK
PDATA =_split_words
LICFIX = GENSEARCHDATA

OBJS = licenses.o list.o parse.o process.o nomos_regex.o util.o nomos_gap.o nomos_utils.o doctorBuffer_utils.o json_writer.o # sources.o DMalloc.o
GENOBJS = _precheck.o _autodata.o
HDRS = nomos.h $(OBJS:.o=.h) _autodefs.h
COVERAGE = $(OBJS:%.o=%_cov.o)
GENOBJS_COV = $(GENOBJS:%.o=%_cov.o)

CFLAGS_LOCAL = $(FO_CFLAGS) -Werror $(shell pkg-config --cflags json-c)
CFLAGS_LOCALO = $(FO_CFLAGS)

FO_LDFLAGS += $(shell pkg-config --libs json-c) -lpthread -lrt

all: encode nomos libnomos.a

coverage: encode nomos_cov libnomos_cov.a

debug: nomos-gl

nomos: nomos.o $(OBJS) $(GENOBJS) $(FOLIB)
	$(CC) nomos.o $(OBJS) $(GENOBJS) $(CFLAGS_LOCAL) $(FO_LDFLAGS) -o $@

nomos_cov: nomos_cov.o $(COVERAGE) $(GENOBJS_COV)
	$(CC) nomos_cov.o $(COVERAGE) $(GENOBJS_COV) $(CFLAGS_LOCAL) $(FO_LDFLAGS) $(FLAG_COV) -o nomos

nomos.o: nomos.c $(HDRS) $(DB) $(REPO) $(AGENTLIB) $(VARS)
	$(CC) -c $< $(CFLAGS_LOCALO) $(DEFS)

nomos_cov.o: nomos.c $(HDRS) $(DB) $(REPO) $(AGENTLIB) $(VARS)
	$(CC) -c $< $(CFLAGS_LOCALO) $(FLAG_COV) $(DEFS) -o $@

nomos-gl: nomos-g.o $(OBJS) $(GENOBJS)
	$(CC) nomos.o $(OBJS) $(GENOBJS) $(CFLAGS_LOCAL) -o $@

nomos-g.o: nomos.c $(HDRS) $(DB) $(REPO) $(AGENTLIB) $(VARS)
	$(CC) -c -l /usr/lib/libefence.a $< $(CFLAGS_LOCALO)

$(OBJS) $(GENOBJS): %.o: %.c $(HDRS) $(DB) $(VARS)
	$(CC) -c $< $(CFLAGS_DBO) $(FO_CFLAGS)

$(COVERAGE) $(GENOBJS_COV): %_cov.o: %.c $(HDRS) $(DB) $(VARS)
	$(CC) -c $< $(CFLAGS_DBO) $(FO_CFLAGS) $(FLAG_COV) -o $@

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
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)
#	$(INSTALL_PROGRAM) encode $(DESTDIR)$(AGENTDIR)/encode

uninstall:
#	rm -f $(DESTDIR)$(AGENTDIR)/encode
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

clean:
	rm -f encode nomos core *.a *.gc* *.o \
           _autodata.c _autodefs.c _autodefs.h _precheck.c \
           _strings.data _STRFILTER strings.HISTOGRAM words.HISTOGRAM \
           split.OTHER checkstr.OK

libnomos.a: $(OBJS) $(GENOBJS)
	ar cvr $@ $(OBJS) $(GENOBJS)

libnomos_cov.a: $(COVERAGE) $(GENOBJS)
	ar cvr $@ $(COVERAGE) $(GENOBJS)

.PHONY: all install uninstall clean

include $(DEPS)
