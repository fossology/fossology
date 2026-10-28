/*
 SPDX-FileCopyrightText: © 2026 Siemens Healthineers AG

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * @file
 * @brief Nested-set parity and per-node heartbeat regression tests.
 *
 * Exercise the production traversal without a database or scheduler process.
 * Database updates are captured as (uploadtree_pk, lft, rgt) tuples; heartbeat
 * calls are checked and forwarded to the real scheduler library. This tests
 * progress/aliveness updates, not wall-clock heartbeat delivery or DB latency.
 */
#include <libfossology.h>
#include <libfocunit.h>
#include <sys/resource.h>

static PGresult *capture_update(PGconn *connection, const char *query);
static void capture_clear(PGresult *result);
static void capture_heart(int items);

/* Keep the real WalkTree(), replacing only its I/O and the agent entry point.
 * Include library headers first so these macros cannot rename library APIs.
 */
#define main adj2nest_main
#define PQexec capture_update
#define PQclear capture_clear
#define fo_scheduler_heart capture_heart
#include "../../agent/adj2nest.c"
#undef fo_scheduler_heart
#undef PQclear
#undef PQexec
#undef main

extern volatile gint items_processed;
extern volatile int alive;

typedef struct
{
  long pk;
  long lft;
  long rgt;
} nested_set;

static nested_set *actual;
static nested_set *expected;
static unsigned char *endpoints;
static PGresult *command_result;
static long update_count;
static long clear_count;
static long heart_count;
static long expected_count;
static struct rlimit saved_stack_limit;
static int stack_limited;

static void set_up(void)
{
  TreeSize = 0;
  TreeSet = 0;
  SetNum = 1;
  agent_verbose = 0;
  pgConn = NULL;
  uploadtree_tablename = "uploadtree_a";
  update_count = clear_count = heart_count = expected_count = 0;
  g_atomic_int_set(&items_processed, 0);
  alive = FALSE;
}

static void tear_down(void)
{
  if (stack_limited)
  {
    FO_ASSERT(setrlimit(RLIMIT_STACK, &saved_stack_limit) == 0);
    stack_limited = 0;
  }
  PQclear(command_result);
  command_result = NULL;
  free(Tree);
  Tree = NULL;
  free(actual);
  actual = NULL;
  free(expected);
  expected = NULL;
  free(endpoints);
  endpoints = NULL;
}

static void make_tree(long count)
{
  TreeSize = count;
  Tree = calloc(count, sizeof(*Tree));
  actual = calloc(count, sizeof(*actual));
  expected = calloc(count, sizeof(*expected));
  endpoints = calloc(2 * count + 1, sizeof(*endpoints));
  /* A real successful PGresult lets fo_checkPQcommand() run unchanged. */
  command_result = PQmakeEmptyPGresult(NULL, PGRES_COMMAND_OK);
  FO_ASSERT_PTR_NOT_NULL_FATAL(Tree);
  FO_ASSERT_PTR_NOT_NULL_FATAL(actual);
  FO_ASSERT_PTR_NOT_NULL_FATAL(expected);
  FO_ASSERT_PTR_NOT_NULL_FATAL(endpoints);
  FO_ASSERT_PTR_NOT_NULL_FATAL(command_result);
  for (long i = 0; i < count; ++i)
  {
    /* IDs are deliberately nonconsecutive and unrelated to traversal order. */
    Tree[i].UploadtreePk = 1001 + 7 * (count - i);
    Tree[i].Child = -1;
    Tree[i].Sibling = -1;
  }
}

static PGresult *capture_update(PGconn *connection, const char *query)
{
  char table[128];
  int end = 0;
  FO_ASSERT_PTR_EQUAL(connection, pgConn);
  FO_ASSERT_FATAL(update_count < TreeSize);
  /* The previous update must be cleared and heartbeated before the next one. */
  FO_ASSERT(clear_count == update_count);
  FO_ASSERT(heart_count == update_count);
  nested_set *row = &actual[update_count];
  int fields = sscanf(query,
      "UPDATE %127s SET lft='%ld', rgt='%ld' WHERE uploadtree_pk='%ld'%n",
      table, &row->lft, &row->rgt, &row->pk, &end);
  FO_ASSERT_EQUAL_FATAL(fields, 4);
  FO_ASSERT(query[end] == '\0');
  FO_ASSERT_STRING_EQUAL(table, uploadtree_tablename);
  ++update_count;
  return command_result;
}

static void capture_clear(PGresult *result)
{
  FO_ASSERT_PTR_EQUAL(result, command_result);
  FO_ASSERT(clear_count + 1 == update_count);
  FO_ASSERT(heart_count + 1 == update_count);
  ++clear_count;
  /* Reuse the successful result; tear_down() frees it with the real PQclear. */
}

static void capture_heart(int items)
{
  FO_ASSERT_EQUAL(items, 1);
  FO_ASSERT(clear_count == update_count);
  FO_ASSERT(heart_count + 1 == update_count);
  ++heart_count;

  /* Simulate the alive flag being reset between updates by the alarm handler.
   * Each call must refresh it, not just increment the processed-item count.
   */
  alive = FALSE;
  fo_scheduler_heart(items);
  FO_ASSERT_TRUE(alive);
  FO_ASSERT(g_atomic_int_get(&items_processed) == heart_count);
}

static int compare_pk(const void *a, const void *b)
{
  long left = ((const nested_set *)a)->pk;
  long right = ((const nested_set *)b)->pk;
  return (left > right) - (left < right);
}

static const nested_set *find_row(long index)
{
  nested_set key = {Tree[index].UploadtreePk, 0, 0};
  return bsearch(&key, actual, TreeSize, sizeof(*actual), compare_pk);
}

static void assert_range(long index, long lft, long rgt)
{
  const nested_set *row = find_row(index);
  FO_ASSERT_PTR_NOT_NULL_FATAL(row);
  FO_ASSERT(row->lft == lft);
  FO_ASSERT(row->rgt == rgt);
}

static void run_walk(void)
{
  WalkTree(0, 0);
  FO_ASSERT_FATAL(update_count == TreeSize);
  FO_ASSERT(clear_count == TreeSize);
  FO_ASSERT(heart_count == TreeSize);
  FO_ASSERT(g_atomic_int_get(&items_processed) == TreeSize);
  FO_ASSERT(SetNum == 2 * TreeSize);
  qsort(actual, TreeSize, sizeof(*actual), compare_pk);

  for (long i = 0; i < TreeSize; ++i)
  {
    const nested_set *row = &actual[i];
    FO_ASSERT_FATAL(row->lft >= 1 && row->rgt <= 2 * TreeSize);
    FO_ASSERT_FATAL(row->lft < row->rgt);
    FO_ASSERT_FALSE(endpoints[row->lft]);
    FO_ASSERT_FALSE(endpoints[row->rgt]);
    endpoints[row->lft] = endpoints[row->rgt] = 1;
  }
  for (long i = 1; i <= 2 * TreeSize; ++i)
  {
    FO_ASSERT_TRUE(endpoints[i]);
  }
  for (long i = 0; i < TreeSize; ++i)
  {
    const nested_set *parent = find_row(i);
    FO_ASSERT_PTR_NOT_NULL_FATAL(parent);
    for (long child = Tree[i].Child; child >= 0; child = Tree[child].Sibling)
    {
      const nested_set *row = find_row(child);
      FO_ASSERT_PTR_NOT_NULL_FATAL(row);
      FO_ASSERT(parent->lft < row->lft);
      FO_ASSERT(row->rgt < parent->rgt);
    }
  }
  assert_range(0, 1, 2 * TreeSize);
}

/** Frozen numbering logic from WalkTree before commit 1766d5b1c17.
 * Both children and siblings recurse. Keep independent of the production
 * SetNum and DB mocks; never use this oracle on the stack-overflow fixture.
 */
static void walk_recursive(long index, long *number)
{
  long left = (*number)++;
  if (Tree[index].Child > -1)
  {
    walk_recursive(Tree[index].Child, number);
    ++*number;
  }
  expected[expected_count++] =
      (nested_set){Tree[index].UploadtreePk, left, *number};
  if (Tree[index].Sibling > -1)
  {
    ++*number;
    walk_recursive(Tree[index].Sibling, number);
  }
}

static void assert_recursive_parity(void)
{
  long number = 1;
  walk_recursive(0, &number);
  FO_ASSERT_FATAL(expected_count == TreeSize);
  qsort(expected, TreeSize, sizeof(*expected), compare_pk);
  run_walk();
  FO_ASSERT(SetNum == number);
  for (long i = 0; i < TreeSize; ++i)
  {
    FO_ASSERT(actual[i].pk == expected[i].pk);
    FO_ASSERT(actual[i].lft == expected[i].lft);
    FO_ASSERT(actual[i].rgt == expected[i].rgt);
  }
}

static void test_single_node(void)
{
  make_tree(1);
  uploadtree_tablename = "uploadtree";
  assert_recursive_parity();
  assert_range(0, 1, 2);
}

static void test_siblings(void)
{
  make_tree(4);
  Tree[0].Child = 1;
  Tree[1].Sibling = 2;
  Tree[2].Sibling = 3;
  assert_recursive_parity();
  assert_range(0, 1, 8);
  assert_range(1, 2, 3);
  assert_range(2, 4, 5);
  assert_range(3, 6, 7);
}

static void test_mixed_tree(void)
{
  /* 0 -> [1, 2], 1 -> [3, 4], 2 -> [5], 4 -> [6, 7]. */
  make_tree(8);
  Tree[0].Child = 1;
  Tree[1].Sibling = 2;
  Tree[1].Child = 3;
  Tree[3].Sibling = 4;
  Tree[2].Child = 5;
  Tree[4].Child = 6;
  Tree[6].Sibling = 7;
  assert_recursive_parity();
  /* Hand-calculated values also guard against errors shared with the oracle. */
  assert_range(0, 1, 16);
  assert_range(1, 2, 11);
  assert_range(2, 12, 15);
  assert_range(3, 3, 4);
  assert_range(4, 5, 10);
  assert_range(5, 13, 14);
  assert_range(6, 6, 7);
  assert_range(7, 8, 9);
}

static void test_deep_tree(void)
{
  make_tree(256);
  for (long i = 0; i + 1 < TreeSize; ++i)
  {
    Tree[i].Child = i + 1;
  }
  assert_recursive_parity();
  for (long i = 0; i < TreeSize; ++i)
  {
    assert_range(i, i + 1, 2 * TreeSize - i);
  }
}

static void test_generated_tree(void)
{
  guint32 seed = 0xc0ffee;
  make_tree(1024);
  for (long i = 1; i < TreeSize; ++i)
  {
    /* Fixed seed makes this irregular tree reproducible on every platform. */
    seed = seed * 1664525U + 1013904223U;
    long parent = seed % i;
    long *link = &Tree[parent].Child;
    while (*link >= 0)
    {
      link = &Tree[*link].Sibling;
    }
    *link = i;
  }
  assert_recursive_parity();
}

static void test_wide_tree(void)
{
  make_tree(100001); /* One root and 100,000 siblings. */
  uploadtree_tablename = "uploadtree_123";
  Tree[0].Child = 1;
  for (long i = 1; i + 1 < TreeSize; ++i)
  {
    Tree[i].Sibling = i + 1;
  }

  /* Width must not require a large call stack, even in unoptimized builds.
   * Save/restore the soft limit; never change the process's hard limit.
   */
  FO_ASSERT_FATAL(getrlimit(RLIMIT_STACK, &saved_stack_limit) == 0);
  struct rlimit limit = saved_stack_limit;
  if (limit.rlim_cur == RLIM_INFINITY || limit.rlim_cur > 256 * 1024)
  {
    limit.rlim_cur = 256 * 1024;
    FO_ASSERT_FATAL(setrlimit(RLIMIT_STACK, &limit) == 0);
    stack_limited = 1;
  }
  run_walk();
  for (long i = 1; i < TreeSize; ++i)
  {
    assert_range(i, 2 * i, 2 * i + 1);
  }
}

static CU_TestInfo walk_tree_tests[] = {
  {"single node", test_single_node},
  {"siblings", test_siblings},
  {"mixed tree", test_mixed_tree},
  {"deep tree", test_deep_tree},
  {"generated tree", test_generated_tree},
  {"100000 siblings with bounded stack", test_wide_tree},
  CU_TEST_INFO_NULL
};

static CU_SuiteInfo suites[] = {
  {"WalkTree", NULL, NULL, set_up, tear_down, walk_tree_tests},
  CU_SUITE_INFO_NULL
};

int main(int argc, char **argv)
{
  return focunit_main(argc, argv, "adj2nest", suites);
}