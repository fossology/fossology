#include "gtkmodule.h"

MODULE = Gaim::GtkUI::Themes  PACKAGE = Gaim::GtkUI::Themes  PREFIX = gaim_gtkthemes_
PROTOTYPES: ENABLE

void
gaim_gtkthemes_init()

gboolean
gaim_gtkthemes_smileys_disabled()

void
gaim_gtkthemes_smiley_theme_probe()

void
gaim_gtkthemes_load_smiley_theme(file, load)
	const char * file
	gboolean load

void
gaim_gtkthemes_get_proto_smileys(id)
	const char * id
PREINIT:
	GSList *l;
PPCODE:
	for (l = gaim_gtkthemes_get_proto_smileys(id); l != NULL; l = l->next) {
		XPUSHs(sv_2mortal(gaim_perl_bless_object(l->data, "Gaim::GtkUI::IMHtml::Smiley")));
	}
