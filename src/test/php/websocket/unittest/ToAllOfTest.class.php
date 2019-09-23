<?php namespace websocket\unittest;

use lang\IllegalArgumentException;
use unittest\TestCase;
use util\URI;
use websocket\logging\ToAllOf;
use websocket\logging\ToConsole;
use websocket\logging\ToFunction;

class ToAllOfTest extends TestCase {
  const ID = 42;

  #[@test]
  public function can_create_without_args() {
    new ToAllOf();
  }

  #[@test]
  public function can_create_with_sink() {
    new ToAllOf(new ToFunction(function($client, $opcode, $result) { }));
  }

  #[@test]
  public function can_create_with_string() {
    new ToAllOf('-');
  }

  #[@test]
  public function sinks() {
    $a= new ToConsole();
    $b= new ToFunction(function($client, $opcode, $result) {  });
    $this->assertEquals([$a, $b], (new ToAllOf($a, $b))->sinks());
  }

  #[@test]
  public function sinks_are_merged_when_passed_ToAllOf_instance() {
    $a= new ToConsole();
    $b= new ToFunction(function($client, $opcode, $result) {  });
    $this->assertEquals([$a, $b], (new ToAllOf(new ToAllOf($a, $b)))->sinks());
  }

  #[@test]
  public function sinks_are_empty_when_created_without_arg() {
    $this->assertEquals([], (new ToAllOf())->sinks());
  }

  #[@test]
  public function targets() {
    $a= new ToConsole();
    $b= new ToFunction(function($client, $opcode, $result) { });
    $this->assertEquals('(websocket.logging.ToConsole & websocket.logging.ToFunction)', (new ToAllOf($a, $b))->target());
  }

  #[@test, @values([
  #  [['a' => ['#42 TEXT +OK'], 'b' => ['#42 TEXT +OK']], null],
  #  [['a' => ['#42 TEXT -ERR'], 'b' => ['#42 TEXT -ERR']], new IllegalArgumentException('Test')],
  #])]
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

    $this->assertEquals($expected, $logged);
  }
}