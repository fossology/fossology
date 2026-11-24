<?php
/*
 SPDX-FileCopyrightText: © 2026 Siemens AG

 SPDX-License-Identifier: GPL-2.0-only
*/

namespace Fossology\Lib\UI;

/**
 * @class MenuHookTest
 * @brief Test for class MenuHook
 */
class MenuHookTest extends \PHPUnit\Framework\TestCase
{
  /**
   * @test
   * -# decider before scancode/reuser in menu order (the real, alphabetically
   *    sorted case: "Automatic..." < "Scancode...").
   * -# Verify both are moved to just before decider, in their original
   *    relative order, with nothing else disturbed.
   */
  public function testMovesReuserAndScancodeBeforeDecider(): void
  {
    $parmList = ['agent_decider', 'agent_scancode', 'agent_reuser', 'agent_compatibility'];

    MenuHook::rearrangeParmAgentsBeforeDecider($parmList);

    $deciderKey = array_search('agent_decider', $parmList);
    $scancodeKey = array_search('agent_scancode', $parmList);
    $reuserKey = array_search('agent_reuser', $parmList);

    $this->assertLessThan($deciderKey, $scancodeKey);
    $this->assertLessThan($deciderKey, $reuserKey);
    $this->assertSame(['agent_reuser', 'agent_scancode', 'agent_decider', 'agent_compatibility'], $parmList);
  }

  /**
   * @test
   * -# scancode already before decider, reuser after.
   * -# Verify only reuser moves; scancode's position is left alone.
   */
  public function testOnlyMovesAgentsThatAreAfterDecider(): void
  {
    $parmList = ['agent_scancode', 'agent_decider', 'agent_reuser'];

    MenuHook::rearrangeParmAgentsBeforeDecider($parmList);

    $this->assertSame(['agent_scancode', 'agent_reuser', 'agent_decider'], $parmList);
  }

  /**
   * @test
   * -# Both already before decider.
   * -# Verify the list is untouched (idempotent).
   */
  public function testNoOpWhenAlreadyOrdered(): void
  {
    $parmList = ['agent_reuser', 'agent_scancode', 'agent_decider'];

    MenuHook::rearrangeParmAgentsBeforeDecider($parmList);

    $this->assertSame(['agent_reuser', 'agent_scancode', 'agent_decider'], $parmList);
  }

  /**
   * @test
   * -# decider absent from the list (e.g. not installed/selected).
   * -# Verify the list is untouched.
   */
  public function testNoOpWhenDeciderAbsent(): void
  {
    $parmList = ['agent_scancode', 'agent_reuser'];

    MenuHook::rearrangeParmAgentsBeforeDecider($parmList);

    $this->assertSame(['agent_scancode', 'agent_reuser'], $parmList);
  }

  /**
   * @test
   * -# Neither reuser nor scancode present.
   * -# Verify the list is untouched.
   */
  public function testNoOpWhenNeitherAgentPresent(): void
  {
    $parmList = ['agent_decider', 'agent_compatibility'];

    MenuHook::rearrangeParmAgentsBeforeDecider($parmList);

    $this->assertSame(['agent_decider', 'agent_compatibility'], $parmList);
  }

  /**
   * @test
   * -# Empty list.
   * -# Verify no error and the list stays empty.
   */
  public function testEmptyListIsNoOp(): void
  {
    $parmList = [];

    MenuHook::rearrangeParmAgentsBeforeDecider($parmList);

    $this->assertSame([], $parmList);
  }

  /**
   * @test
   * -# decider is the only entry.
   * -# Verify no error and the list is unchanged.
   */
  public function testDeciderOnlyIsNoOp(): void
  {
    $parmList = ['agent_decider'];

    MenuHook::rearrangeParmAgentsBeforeDecider($parmList);

    $this->assertSame(['agent_decider'], $parmList);
  }

  /**
   * @test
   * -# No entry is lost or duplicated across every combination this
   *    function can plausibly see, regardless of starting order.
   */
  public function testNeverLosesOrDuplicatesEntries(): void
  {
    $base = ['agent_decider', 'agent_scancode', 'agent_reuser', 'agent_compatibility'];
    $permutations = [
      $base,
      ['agent_scancode', 'agent_reuser', 'agent_decider', 'agent_compatibility'],
      ['agent_reuser', 'agent_decider', 'agent_scancode', 'agent_compatibility'],
      ['agent_compatibility', 'agent_decider'],
    ];

    foreach ($permutations as $parmList) {
      $expectedCount = count($parmList);
      $expectedSet = $parmList;
      sort($expectedSet);

      MenuHook::rearrangeParmAgentsBeforeDecider($parmList);

      $actualSet = $parmList;
      sort($actualSet);

      $this->assertCount($expectedCount, $parmList);
      $this->assertSame($expectedSet, $actualSet);
    }
  }
}
