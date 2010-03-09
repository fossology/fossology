# FOSSology Makefile - agents/copyright_analysis
# Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
include $(VARS)
DEPS=$(TOP)/Makefile.deps

all: model.dat copyright

copyright: copyright.py $(PYTHONLIB)
	cat copyright.py > copyright
	echo -n "    sys.path.append('" >> copyright
	echo -n $(DESTDIR)$(LIBDIR) >> copyright
	echo -e "/python_fossology/')\n    import libfosspython\n    import copyright_library as library\n    main()\n" >> copyright
	chmod a+x copyright

model.dat: copyright training_files
	./copyright --model model.dat --training training_files
	
training_files:
	find data -type f > training_files

install: all
	./copyright --setup
	$(INSTALL_PROGRAM) copyright $(DESTDIR)$(AGENTDIR)/copyright
	mkdir -p $(DESTDIR)$(LIBDIR)/python_fossology/
	$(INSTALL_PROGRAM) copyright_library.py $(DESTDIR)$(LIBDIR)/python_fossology/copyright_library.py
	$(INSTALL_PROGRAM) model.dat $(DATADIR)/model.dat
	make -C ui-plugin install

uninstall: 
	rm -rf $(DESTDIR)$(AGENTDIR)/copyright
	rm -rf $(DESTDIR)$(LIBDIR)/python_fossology/copyright_library.py
	rmdir $(DESTDIR)$(LIBDIR)/python_fossology/
	rm -rf $(DATADIR)/model.dat
	make -C ui-plugin uninstall

test: all
	@echo "*** No tests available for agents/copyright_analysis ***"

clean:
	rm -f copyright model.dat training_files

include $(DEPS)

.PHONY: all install uninstall clean test
