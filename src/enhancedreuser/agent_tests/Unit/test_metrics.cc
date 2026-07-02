/*
 SPDX-License-Identifier: GPL-2.0-only
 Author: Saksham Mishra <sakshammishra112@gmail.com>
 SPDX-FileCopyrightText: © 2026 Saksham Mishra
*/
#include <cppunit/TestFixture.h>
#include <cppunit/extensions/HelperMacros.h>

#include "EnhancedReuserDatabaseHandler.hpp"
#include "MockEnhancedReuserDatabaseHandler.hpp"

class MetricsTest : public CPPUNIT_NS::TestFixture
{
  CPPUNIT_TEST_SUITE(MetricsTest);
  CPPUNIT_TEST(testInitialValuesAreZero);
  CPPUNIT_TEST(testIncrementCounters);
  CPPUNIT_TEST(testReset);
  CPPUNIT_TEST(testToJsonFormat);
  CPPUNIT_TEST(testToJsonAfterIncrement);
  CPPUNIT_TEST(testResetViaHandlerMethod);
  CPPUNIT_TEST_SUITE_END();

  typedef EnhancedReuserDatabaseHandler::Metrics Metrics;

protected:
  void testInitialValuesAreZero()
  {
    Metrics m;
    CPPUNIT_ASSERT_EQUAL(0, m.kotobaMatched);
    CPPUNIT_ASSERT_EQUAL(0, m.kotobaSkipped);
    CPPUNIT_ASSERT_EQUAL(0, m.licenseSame);
    CPPUNIT_ASSERT_EQUAL(0, m.licenseChanged);
    CPPUNIT_ASSERT_EQUAL(0, m.copyrightChange);
    CPPUNIT_ASSERT_EQUAL(0, m.codeChange);
    CPPUNIT_ASSERT_EQUAL(0, m.diffError);
    CPPUNIT_ASSERT_EQUAL(0, m.diffSkipped);
    CPPUNIT_ASSERT_EQUAL(0, m.copyFailed);
  }

  void testIncrementCounters()
  {
    Metrics m;
    ++m.kotobaMatched;
    ++m.kotobaSkipped;
    ++m.licenseSame;
    ++m.licenseChanged;
    ++m.copyrightChange;
    ++m.codeChange;
    ++m.diffError;
    ++m.diffSkipped;
    ++m.copyFailed;
    CPPUNIT_ASSERT_EQUAL(1, m.kotobaMatched);
    CPPUNIT_ASSERT_EQUAL(1, m.kotobaSkipped);
    CPPUNIT_ASSERT_EQUAL(1, m.licenseSame);
    CPPUNIT_ASSERT_EQUAL(1, m.licenseChanged);
    CPPUNIT_ASSERT_EQUAL(1, m.copyrightChange);
    CPPUNIT_ASSERT_EQUAL(1, m.codeChange);
    CPPUNIT_ASSERT_EQUAL(1, m.diffError);
    CPPUNIT_ASSERT_EQUAL(1, m.diffSkipped);
    CPPUNIT_ASSERT_EQUAL(1, m.copyFailed);
  }

  void testReset()
  {
    Metrics m;
    m.kotobaMatched        = 3;
    m.kotobaSkipped        = 2;
    m.licenseSame          = 1;
    m.licenseChanged       = 4;
    m.copyrightChange  = 5;
    m.codeChange           = 6;
    m.diffError            = 7;
    m.diffSkipped          = 8;
    m.copyFailed           = 1;
    m.reset();
    CPPUNIT_ASSERT_EQUAL(0, m.kotobaMatched);
    CPPUNIT_ASSERT_EQUAL(0, m.kotobaSkipped);
    CPPUNIT_ASSERT_EQUAL(0, m.licenseSame);
    CPPUNIT_ASSERT_EQUAL(0, m.licenseChanged);
    CPPUNIT_ASSERT_EQUAL(0, m.copyrightChange);
    CPPUNIT_ASSERT_EQUAL(0, m.codeChange);
    CPPUNIT_ASSERT_EQUAL(0, m.diffError);
    CPPUNIT_ASSERT_EQUAL(0, m.diffSkipped);
    CPPUNIT_ASSERT_EQUAL(0, m.copyFailed);
  }

  void testToJsonFormat()
  {
    Metrics m;
    std::string json = m.toJson();
    CPPUNIT_ASSERT(json.find("\"kotobaMatched\":0") != std::string::npos);
    CPPUNIT_ASSERT(json.find("\"kotobaSkipped\":0") != std::string::npos);
    CPPUNIT_ASSERT(json.find("\"licenseSame\":0") != std::string::npos);
    CPPUNIT_ASSERT(json.find("\"licenseChanged\":0") != std::string::npos);
    CPPUNIT_ASSERT(json.find("\"copyrightChange\":0") != std::string::npos);
    CPPUNIT_ASSERT(json.find("\"codeChange\":0") != std::string::npos);
    CPPUNIT_ASSERT(json.find("\"diffError\":0") != std::string::npos);
    CPPUNIT_ASSERT(json.find("\"diffSkipped\":0") != std::string::npos);
    CPPUNIT_ASSERT(json.find("\"copyFailed\":0") != std::string::npos);
    CPPUNIT_ASSERT(json[0] == '{');
    CPPUNIT_ASSERT(json[json.size()-1] == '}');
  }

  void testToJsonAfterIncrement()
  {
    Metrics m;
    ++m.kotobaMatched;
    ++m.kotobaSkipped;
    ++m.licenseSame;
    ++m.licenseChanged;
    ++m.copyrightChange;
    ++m.codeChange;
    ++m.diffError;
    ++m.diffSkipped;
    ++m.copyFailed;
    std::string json = m.toJson();
    CPPUNIT_ASSERT(json.find("\"kotobaMatched\":1") != std::string::npos);
    CPPUNIT_ASSERT(json.find("\"kotobaSkipped\":1") != std::string::npos);
    CPPUNIT_ASSERT(json.find("\"licenseSame\":1") != std::string::npos);
    CPPUNIT_ASSERT(json.find("\"licenseChanged\":1") != std::string::npos);
    CPPUNIT_ASSERT(json.find("\"copyrightChange\":1") != std::string::npos);
    CPPUNIT_ASSERT(json.find("\"codeChange\":1") != std::string::npos);
    CPPUNIT_ASSERT(json.find("\"diffError\":1") != std::string::npos);
    CPPUNIT_ASSERT(json.find("\"diffSkipped\":1") != std::string::npos);
    CPPUNIT_ASSERT(json.find("\"copyFailed\":1") != std::string::npos);
  }

  void testResetViaHandlerMethod()
  {
    MockEnhancedReuserDatabaseHandler handler;
    handler.resetMetrics();
    const auto& m = handler.getMetrics();
    CPPUNIT_ASSERT_EQUAL(0, m.kotobaMatched);
    CPPUNIT_ASSERT_EQUAL(0, m.kotobaSkipped);
    CPPUNIT_ASSERT_EQUAL(0, m.licenseSame);
    CPPUNIT_ASSERT_EQUAL(0, m.licenseChanged);
    CPPUNIT_ASSERT_EQUAL(0, m.copyrightChange);
    CPPUNIT_ASSERT_EQUAL(0, m.codeChange);
    CPPUNIT_ASSERT_EQUAL(0, m.diffError);
    CPPUNIT_ASSERT_EQUAL(0, m.diffSkipped);
    CPPUNIT_ASSERT_EQUAL(0, m.copyFailed);
  }
};

CPPUNIT_TEST_SUITE_REGISTRATION(MetricsTest);
