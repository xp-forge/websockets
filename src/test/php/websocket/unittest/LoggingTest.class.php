<?php namespace websocket\unittest;

use lang\IllegalArgumentException;
use lang\Throwable;
use unittest\TestCase;
use websocket\Logging;
use websocket\logging\ToAllOf;
use websocket\logging\ToConsole;
use websocket\logging\ToFunction;

class LoggingTest extends TestCase {
  const ID = 42;

  #[@test]
  public function can_create() {
    new Logging(null);
  }

  #[@test]
  public function can_create_with_sink() {
    new Logging(new ToFunction(function($client, $opcode, $result) { }));
  }

  #[@test]
  public function target() {
    $sink= new ToFunction(function($client, $opcode, $result) { });
    $this->assertEquals($sink->target(), (new Logging($sink))->target());
  }

  #[@test]
  public function no_logging_target() {
    $this->assertEquals('(no logging)', (new Logging(null))->target());
  }

  #[@test, @values([
  #  ['#42 TEXT OK', 'OK'],
  #  ['#42 TEXT lang.IllegalArgumentException', new IllegalArgumentException('Test')],
  #])]
  public function log($expected, $result) {
    $logged= [];
    $log= new Logging(new ToFunction(function($client, $opcode, $result) use(&$logged) {
      $logged[]= '#'.$client.' '.$opcode.' '.($result instanceof Throwable ? nameof($result) : $result);
    }));
    $log->log(self::ID, 'TEXT', $result);

    $this->assertEquals([$expected], $logged);
  }

  #[@test]
  public function pipe() {
    $a= new ToFunction(function($client, $opcode, $result) { /* a */ });
    $b= new ToFunction(function($client, $opcode, $result) { /* b */ });
    $this->assertEquals($b, (new Logging($a))->pipe($b)->sink());
  }

  #[@test]
  public function tee() {
    $a= new ToFunction(function($client, $opcode, $result) { /* a */ });
    $b= new ToFunction(function($client, $opcode, $result) { /* b */ });
    $this->assertEquals(new ToAllOf($a, $b), (new Logging($a))->tee($b)->sink());
  }

  #[@test]
  public function tee_multiple() {
    $a= new ToFunction(function($client, $opcode, $result) { /* a */ });
    $b= new ToFunction(function($client, $opcode, $result) { /* b */ });
    $c= new ToFunction(function($client, $opcode, $result) { /* c */ });
    $this->assertEquals(new ToAllOf($a, $b, $c), (new Logging($a))->tee($b)->tee($c)->sink());
  }

  #[@test]
  public function pipe_on_no_logging() {
    $sink= new ToFunction(function($client, $opcode, $result) { });
    $this->assertEquals($sink, (new Logging(null))->pipe($sink)->sink());
  }

  #[@test]
  public function tee_on_no_logging() {
    $sink= new ToFunction(function($client, $opcode, $result) { });
    $this->assertEquals($sink, (new Logging(null))->tee($sink)->sink());
  }

  #[@test]
  public function pipe_accepts_strings() {
    $this->assertEquals(new ToConsole(), (new Logging(null))->pipe('-')->sink());
  }
}