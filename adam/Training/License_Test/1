SUBDIRS = api loader

mono_sources = GetBuddyBack.cs \
			   MPlugin.cs

EXTRA_DIST = $(mono_sources)

monodir = $(libdir)/gaim
mono_SCRIPTS = MPlugin.dll GetBuddyBack.dll
mono_build_sources = $(addprefix $(srcdir)/, $(mono_sources))

all: $(mono_SCRIPTS)

SUFFIXES = .cs .dll
.cs.dll: api/GaimAPI.dll $(mono_build_sources)
	mcs -t:library -lib:./api -out:$@ -r:GaimAPI.dll $<

clean-local:
	rm -f $(mono_SCRIPTS)
