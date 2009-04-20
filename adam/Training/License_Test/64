$MODULE_NAME = "Request Functions Test";

use Gaim;

# All the information Gaim gets about our nifty plugin
%PLUGIN_INFO = (
	perl_api_version => 2,
	name => "Perl: $MODULE_NAME",
	version => "0.1",
	summary => "Test plugin for the Perl interpreter.",
	description => "Implements a set of test proccedures to ensure all functions that work in the C API still work in the Perl plugin interface.  As XSUBs are added, this *should* be updated to test the changes.  Furthermore, this will function as the tutorial perl plugin.",
	author => "John H. Kelm <johnhkelm\@gmail.com>",
	url => "http://sourceforge.net/users/johnhkelm/",

	load => "plugin_load",
	unload => "plugin_unload",
	plugin_action_sub => "plugin_action_names"
);

%plugin_actions = (
	"Plugin Action Test Label" => \&plugin_action_test,
);

sub plugin_action_names {
	foreach $key (keys %plugin_actions) {
		push @array, $key;
	}

	return @array;
}

sub plugin_init {
	return %PLUGIN_INFO;
}

sub ok_cb_test {
	$fields = shift;

	Gaim::Debug::info($MODULE_NAME, "plugin_action_cb_test: BEGIN\n");
	Gaim::Debug::info($MODULE_NAME, "ok_cb_test: BEGIN\n");
	Gaim::Debug::info($MODULE_NAME, "ok_cb_test: Button Click\n");
	Gaim::Debug::info($MODULE_NAME, "ok_cb_test: Field Type:  $fields \n");
	$account = Gaim::Request::Fields::get_account($fields, "acct_test");
	Gaim::Debug::info($MODULE_NAME, "ok_cb_test: Username of selected account: " . Gaim::Account::get_username($account) . "\n");
	$int = Gaim::Request::Fields::get_integer($fields, "int_test");
	Gaim::Debug::info($MODULE_NAME, "ok_cb_test: Integer Value: $int \n");
	$choice = Gaim::Request::Fields::get_choice($fields, "ch_test");
	Gaim::Debug::info($MODULE_NAME, "ok_cb_test: Choice Value: $choice \n");
	Gaim::Debug::info($MODULE_NAME, "ok_cb_test: END\n");
}

sub cancel_cb_test {
	Gaim::Debug::info($MODULE_NAME, "cancel_cb_test: Button Click\n");
}

sub plugin_action_test {
	$plugin = shift;
	Gaim::Debug::info($MODULE_NAME, "plugin_action_cb_test: BEGIN\n");
	plugin_request($plugin);
	Gaim::Debug::info($MODULE_NAME, "plugin_action_cb_test: END\n");
}

sub plugin_load {
	my $plugin = shift;
	#########  TEST CODE HERE  ##########


}

sub plugin_request {
	$group = Gaim::Request::Field::Group::new("Group Name");
	$field = Gaim::Request::Field::account_new("acct_test", "Account Text", undef);
	Gaim::Request::Field::account_set_show_all($field, 0);
	Gaim::Request::Field::Group::add_field($group, $field);

	$field = Gaim::Request::Field::int_new("int_test", "Integer Text", 33);
	Gaim::Request::Field::Group::add_field($group, $field);

	# Test field choice
	$field = Gaim::Request::Field::choice_new("ch_test", "Choice Text", 1);
	Gaim::Request::Field::choice_add($field, "Choice 0");
	Gaim::Request::Field::choice_add($field, "Choice 1");
	Gaim::Request::Field::choice_add($field, "Choice 2");

	Gaim::Request::Field::Group::add_field($group, $field);


	$request = Gaim::Request::Fields::new();
	Gaim::Request::Fields::add_group($request, $group);

	Gaim::Request::fields(
		$plugin,
		"Request Title!",
		"Primary Title",
		"Secondary Title",
		$request,
		"Ok Text", "ok_cb_test",
		"Cancel Text", "cancel_cb_test");
}

sub plugin_unload {
	my $plugin = shift;
	Gaim::Debug::info($MODULE_NAME, "#" x 80 . "\n");
	#########  TEST CODE HERE  ##########


	Gaim::Debug::info($MODULE_NAME, "\n" . "#" x 80 . "\n");
}

