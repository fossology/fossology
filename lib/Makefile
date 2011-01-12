# FOSSology Makefile - lib/
# Copyright (C) 2009 Hewlett-Packard Development Company, L.P.
TOP = ..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL = $(CFLAGS_DB) $(CFLAGS_REPO) $(ALL_CFLAGS) -I.
LIB = libfossscheduler.a

all: $(LIB) $(VARS)

$(LIB): %.a: %.c %.h $(VARS)
	$(CC) -c $< $(CFLAGS_LOCAL)
	$(AR) cr $@ libfossscheduler.o

install: $(LIB)
	$(INSTALL_DATA) libfossscheduler.a $(DESTDIR)$(LIBDIR)/libfossscheduler.a
	$(INSTALL_DATA) libfossscheduler.h $(DESTDIR)$(INCLUDEDIR)/libfossscheduler.h

uninstall-dev:
	rm -f $(DESTDIR)$(LIBDIR)/libfossscheduler.a
	rm -f $(DESTDIR)$(INCLUDEDIR)/libfossscheduler.h

clean:
	rm -f $(LIB) *.o

include $(DEPS)
	