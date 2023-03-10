<?php namespace websocket\unittest;

use lang\IllegalArgumentException;
use test\Assert;
use test\{Test, TestCase, Values};
use util\URI;
use websocket\logging\{ToAllOf, ToConsole, ToFunction};

class ToAllOfTest {
  const ID = 42;

  /** @return iterable */
  private function arguments() {
    yield [['a' => ['#42 TEXT +OK'], 'b' => ['#42 TEXT +OK']], null];
    yield [['a' => ['#42 TEXT -ERR'], 'b' => ['#42 TEXT -ERR']], new IllegalArgumentException('Test')];
  }

  #[Test]
  public function can_create_without_args() {
    new ToAllOf();
  }

  #[Test]
  public function can_create_with_sink() {
    new ToAllOf(new ToFunction(function($client, $opcode, $result) { }));
  }

  #[Test]
  public function can_create_with_string() {
    new ToAllOf('-');
  }

  #[Test]
  public function sinks() {
    $a= new ToConsole();
    $b= new ToFunction(function($client, $opcode, $result) {  });
    Assert::equals([$a, $b], (new ToAllOf($a, $b))->sinks());
  }

  #[Test]
  public function sinks_are_merged_when_passed_ToAllOf_instance() {
    $a= new ToConsole();
    $b= new ToFunction(function($client, $opcode, $result) {  });
    Assert::equals([$a, $b], (new ToAllOf(new ToAllOf($a, $b)))->sinks());
  }

  #[Test]
  public function sinks_are_empty_when_created_without_arg() {
    Assert::equals([], (new ToAllOf())->sinks());
  }

  #[Test]
  public function targets() {
    $a= new ToConsole();
    $b= new ToFunction(function($client, $opcode, $result) { });
    Assert::equals('(websocket.logging.ToConsole & websocket.logging.ToFunction)', (new ToAllOf($a, $b))->target());
  }

  #[Test, Values(from: 'arguments')]
  public function logs_to_all($expected, $error) {
    $logged= ['a' => [], 'b' => []];
    $sink= new ToAllOf(
      new ToFunction(function($client, $opcode, $result) use(&$logged) {
        $logged['a'][]= '#'.$client.' '.$opcode.' '.($result ? '-ERR' : '+OK');
      }),
      new ToFunction(function($client, $opcode, $result) use(&$logged) {
        $logged['b'][]= '#'.$client.' '.$opcode.' '.($result ? '-ERR' : '+OK');
      })
    );
    $sink->log(self::ID, 'TEXT', $error);

    Assert::equals($expected, $logged);
  }
}