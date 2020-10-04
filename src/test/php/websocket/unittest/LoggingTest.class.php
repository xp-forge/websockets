<?php namespace websocket\unittest;

use lang\{IllegalArgumentException, Throwable};
use unittest\{Test, TestCase, Values};
use websocket\Logging;
use websocket\logging\{ToAllOf, ToConsole, ToFunction};

class LoggingTest extends TestCase {
  const ID = 42;

  /** @return iterable */
  private function arguments() {
    yield ['#42 TEXT OK', 'OK'];
    yield ['#42 TEXT lang.IllegalArgumentException', new IllegalArgumentException('Test')];
  }

  #[Test]
  public function can_create() {
    new Logging(null);
  }

  #[Test]
  public function can_create_with_sink() {
    new Logging(new ToFunction(function($client, $opcode, $result) { }));
  }

  #[Test]
  public function target() {
    $sink= new ToFunction(function($client, $opcode, $result) { });
    $this->assertEquals($sink->target(), (new Logging($sink))->target());
  }

  #[Test]
  public function no_logging_target() {
    $this->assertEquals('(no logging)', (new Logging(null))->target());
  }

  #[Test, Values('arguments')]
  public function log($expected, $result) {
    $logged= [];
    $log= new Logging(new ToFunction(function($client, $opcode, $result) use(&$logged) {
      $logged[]= '#'.$client.' '.$opcode.' '.($result instanceof Throwable ? nameof($result) : $result);
    }));
    $log->log(self::ID, 'TEXT', $result);

    $this->assertEquals([$expected], $logged);
  }

  #[Test]
  public function pipe() {
    $a= new ToFunction(function($client, $opcode, $result) { /* a */ });
    $b= new ToFunction(function($client, $opcode, $result) { /* b */ });
    $this->assertEquals($b, (new Logging($a))->pipe($b)->sink());
  }

  #[Test]
  public function tee() {
    $a= new ToFunction(function($client, $opcode, $result) { /* a */ });
    $b= new ToFunction(function($client, $opcode, $result) { /* b */ });
    $this->assertEquals(new ToAllOf($a, $b), (new Logging($a))->tee($b)->sink());
  }

  #[Test]
  public function tee_multiple() {
    $a= new ToFunction(function($client, $opcode, $result) { /* a */ });
    $b= new ToFunction(function($client, $opcode, $result) { /* b */ });
    $c= new ToFunction(function($client, $opcode, $result) { /* c */ });
    $this->assertEquals(new ToAllOf($a, $b, $c), (new Logging($a))->tee($b)->tee($c)->sink());
  }

  #[Test]
  public function pipe_on_no_logging() {
    $sink= new ToFunction(function($client, $opcode, $result) { });
    $this->assertEquals($sink, (new Logging(null))->pipe($sink)->sink());
  }

  #[Test]
  public function tee_on_no_logging() {
    $sink= new ToFunction(function($client, $opcode, $result) { });
    $this->assertEquals($sink, (new Logging(null))->tee($sink)->sink());
  }

  #[Test]
  public function pipe_accepts_strings() {
    $this->assertEquals(new ToConsole(), (new Logging(null))->pipe('-')->sink());
  }
}