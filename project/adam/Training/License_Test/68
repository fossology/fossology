#include "internal.h"

#include "blist.h"
#include "conversation.h"
#include "debug.h"
#include "signals.h"
#include "version.h"

#include "plugin.h"
#include "pluginpref.h"
#include "prefs.h"

#define STATENOTIFY_PLUGIN_ID "core-statenotify"

static void
write_status(GaimBuddy *buddy, const char *message)
{
	GaimConversation *conv;
	const char *who;
	char buf[256];
	char *escaped;

	conv = gaim_find_conversation_with_account(GAIM_CONV_TYPE_IM,
											   buddy->name, buddy->account);

	if (conv == NULL)
		return;
	g_return_if_fail(conv->type == GAIM_CONV_TYPE_IM);

	who = gaim_buddy_get_alias(buddy);
	escaped = g_markup_escape_text(who, -1);

	g_snprintf(buf, sizeof(buf), message, escaped);
	g_free(escaped);

	gaim_conv_im_write(conv->u.im, NULL, buf, GAIM_MESSAGE_SYSTEM | GAIM_MESSAGE_ACTIVE_ONLY, time(NULL));
}

static void
buddy_status_changed_cb(GaimBuddy *buddy, GaimStatus *old_status,
                        GaimStatus *status, void *data)
{
	gboolean available, old_available;

	available = gaim_status_is_available(status);
	old_available = gaim_status_is_available(old_status);

	if (gaim_prefs_get_bool("/plugins/core/statenotify/notify_away")) {
		if (available && !old_available)
			write_status(buddy, _("%s is no longer away."));
		else if (!available && old_available)
			write_status(buddy, _("%s has gone away."));
	}
}

static void
buddy_idle_changed_cb(GaimBuddy *buddy, gboolean old_idle, gboolean idle,
                      void *data)
{
	if (gaim_prefs_get_bool("/plugins/core/statenotify/notify_idle")) {
		if (idle) {
			write_status(buddy, _("%s has become idle."));
		} else {
			write_status(buddy, _("%s is no longer idle."));
		}
	}
}

static void
buddy_signon_cb(GaimBuddy *buddy, void *data)
{
	if (gaim_prefs_get_bool("/plugins/core/statenotify/notify_signon"))
		write_status(buddy, _("%s has signed on."));
}

static void
buddy_signoff_cb(GaimBuddy *buddy, void *data)
{
	if (gaim_prefs_get_bool("/plugins/core/statenotify/notify_signon"))
		write_status(buddy, _("%s has signed off."));
}

static GaimPluginPrefFrame *
get_plugin_pref_frame(GaimPlugin *plugin)
{
	GaimPluginPrefFrame *frame;
	GaimPluginPref *ppref;

	frame = gaim_plugin_pref_frame_new();

	ppref = gaim_plugin_pref_new_with_label(_("Notify When"));
	gaim_plugin_pref_frame_add(frame, ppref);

	ppref = gaim_plugin_pref_new_with_name_and_label("/plugins/core/statenotify/notify_away", _("Buddy Goes _Away"));
	gaim_plugin_pref_frame_add(frame, ppref);

	ppref = gaim_plugin_pref_new_with_name_and_label("/plugins/core/statenotify/notify_idle", _("Buddy Goes _Idle"));
	gaim_plugin_pref_frame_add(frame, ppref);

	ppref = gaim_plugin_pref_new_with_name_and_label("/plugins/core/statenotify/notify_signon", _("Buddy _Signs On/Off"));
	gaim_plugin_pref_frame_add(frame, ppref);

	return frame;
}

static gboolean
plugin_load(GaimPlugin *plugin)
{
	void *blist_handle = gaim_blist_get_handle();

	gaim_signal_connect(blist_handle, "buddy-status-changed", plugin,
	                    GAIM_CALLBACK(buddy_status_changed_cb), NULL);
	gaim_signal_connect(blist_handle, "buddy-idle-changed", plugin,
	                    GAIM_CALLBACK(buddy_idle_changed_cb), NULL);
	gaim_signal_connect(blist_handle, "buddy-signed-on", plugin,
	                    GAIM_CALLBACK(buddy_signon_cb), NULL);
	gaim_signal_connect(blist_handle, "buddy-signed-off", plugin,
	                    GAIM_CALLBACK(buddy_signoff_cb), NULL);

	return TRUE;
}

static GaimPluginUiInfo prefs_info =
{
	get_plugin_pref_frame,
	0,   /* page_num (Reserved) */
	NULL /* frame (Reserved) */
};

static GaimPluginInfo info =
{
	GAIM_PLUGIN_MAGIC,
	GAIM_MAJOR_VERSION,
	GAIM_MINOR_VERSION,
	GAIM_PLUGIN_STANDARD,                             /**< type           */
	NULL,                                             /**< ui_requirement */
	0,                                                /**< flags          */
	NULL,                                             /**< dependencies   */
	GAIM_PRIORITY_DEFAULT,                            /**< priority       */

	STATENOTIFY_PLUGIN_ID,                            /**< id             */
	N_("Buddy State Notification"),                   /**< name           */
	VERSION,                                          /**< version        */
	                                                  /**  summary        */
	N_("Notifies in a conversation window when a buddy goes or returns from "
	   "away or idle."),
	                                                  /**  description    */
	N_("Notifies in a conversation window when a buddy goes or returns from "
	   "away or idle."),
	"Christian Hammond <chipx86@gnupdate.org>",       /**< author         */
	GAIM_WEBSITE,                                     /**< homepage       */

	plugin_load,                                      /**< load           */
	NULL,                                             /**< unload         */
	NULL,                                             /**< destroy        */

	NULL,                                             /**< ui_info        */
	NULL,                                             /**< extra_info     */
	&prefs_info,                                      /**< prefs_info     */
	NULL
};

static void
init_plugin(GaimPlugin *plugin)
{
	gaim_prefs_add_none("/plugins/core/statenotify");
	gaim_prefs_add_bool("/plugins/core/statenotify/notify_away", TRUE);
	gaim_prefs_add_bool("/plugins/core/statenotify/notify_idle", TRUE);
	gaim_prefs_add_bool("/plugins/core/statenotify/notify_signon", TRUE);
}

GAIM_INIT_PLUGIN(statenotify, init_plugin, info)
