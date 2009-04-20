#include "tests.h"
#include "../util.h"

START_TEST(test_util_base16_encode)
{
	assert_string_equal_free("68656c6c6f2c20776f726c642100", gaim_base16_encode("hello, world!", 14));
}
END_TEST

START_TEST(test_util_base16_decode)
{
	gsize sz = 0;
	guchar *out = gaim_base16_decode("21646c726f77202c6f6c6c656800", &sz);
	fail_unless(sz == 14, NULL);
	fail_unless(strcmp("!dlrow ,olleh", out) == 0, NULL);
	g_free(out);
}
END_TEST

START_TEST(test_util_base64_encode)
{
	assert_string_equal_free("Zm9ydHktdHdvAA==", gaim_base64_encode("forty-two", 10));
}
END_TEST

START_TEST(test_util_base64_decode)
{
	gsize sz;
	guchar *out = gaim_base64_decode("b3d0LXl0cm9mAA==", &sz);
	fail_unless(sz == 10, NULL);
	fail_unless(strcmp("owt-ytrof", out) == 0, NULL);
	g_free(out);
}
END_TEST

START_TEST(test_util_escape_filename)
{
	assert_string_equal("foo", gaim_escape_filename("foo"));
	assert_string_equal("@oo", gaim_escape_filename("@oo"));
	assert_string_equal("#oo", gaim_escape_filename("#oo"));
	assert_string_equal("-oo", gaim_escape_filename("-oo"));
	assert_string_equal("_oo", gaim_escape_filename("_oo"));
	assert_string_equal(".oo", gaim_escape_filename(".oo"));
	assert_string_equal("%25oo", gaim_escape_filename("%oo"));
	assert_string_equal("%21oo", gaim_escape_filename("!oo"));
}
END_TEST

START_TEST(test_util_unescape_filename)
{
	assert_string_equal("bar", gaim_unescape_filename("bar"));
	assert_string_equal("@ar", gaim_unescape_filename("@ar"));
	assert_string_equal("!ar", gaim_unescape_filename("!ar"));
	assert_string_equal("!ar", gaim_unescape_filename("%21ar"));
	assert_string_equal("%ar", gaim_unescape_filename("%25ar"));
}
END_TEST


START_TEST(test_util_text_strip_mnemonic)
{
	assert_string_equal_free("", gaim_text_strip_mnemonic(""));
	assert_string_equal_free("foo", gaim_text_strip_mnemonic("foo"));
	assert_string_equal_free("foo", gaim_text_strip_mnemonic("_foo"));

}
END_TEST

START_TEST(test_util_email_is_valid)
{
	fail_unless(gaim_email_is_valid("gaim-devel@lists.sf.net"));
}
END_TEST

Suite *
util_suite(void)
{
	Suite *s = suite_create("Utility Functions");

	TCase *tc = tcase_create("Base16");
	tcase_add_test(tc, test_util_base16_encode);
	tcase_add_test(tc, test_util_base16_decode);
	suite_add_tcase(s, tc);

	tc = tcase_create("Base64");
	tcase_add_test(tc, test_util_base64_encode);
	tcase_add_test(tc, test_util_base64_decode);
	suite_add_tcase(s, tc);

	tc = tcase_create("Filenames");
	tcase_add_test(tc, test_util_escape_filename);
	tcase_add_test(tc, test_util_unescape_filename);
	suite_add_tcase(s, tc);

	tc = tcase_create("Strip Mnemonic");
	tcase_add_test(tc, test_util_text_strip_mnemonic);
	suite_add_tcase(s, tc);

	tc = tcase_create("Email");
	tcase_add_test(tc, test_util_email_is_valid);
	suite_add_tcase(s, tc);

	return s;
}
