<?php namespace websocket\unittest;

use lang\IllegalArgumentException;
use unittest\TestCase;
use util\URI;
use websocket\logging\ToAllOf;
use websocket\logging\ToConsole;
use websocket\logging\ToFunction;

class ToAllOfTest extends TestCase {

  #[@test]
  public function can_create_without_args() {
    new ToAllOf();
  }

  #[@test]
  public function can_create_with_sink() {
    new ToAllOf(new ToFunction(function($kind, $uri, $status, $error= null) { }));
  }

  #[@test]
  public function can_create_with_string() {
    new ToAllOf('-');
  }

  #[@test]
  public function sinks() {
    $a= new ToConsole();
    $b= new ToFunction(function($kind, $uri, $status, $error= null) {  });
    $this->assertEquals([$a, $b], (new ToAllOf($a, $b))->sinks());
  }

  #[@test]
  public function sinks_are_merged_when_passed_ToAllOf_instance() {
    $a= new ToConsole();
    $b= new ToFunction(function($kind, $uri, $status, $error= null) {  });
    $this->assertEquals([$a, $b], (new ToAllOf(new ToAllOf($a, $b)))->sinks());
  }

  #[@test]
  public function sinks_are_empty_when_created_without_arg() {
    $this->assertEquals([], (new ToAllOf())->sinks());
  }

  #[@test]
  public function targets() {
    $a= new ToConsole();
    $b= new ToFunction(function($kind, $uri, $status, $error= null) { });
    $this->assertEquals('(websocket.logging.ToConsole & websocket.logging.ToFunction)', (new ToAllOf($a, $b))->target());
  }

  #[@test, @values([
  #  [['a' => ['TEXT /ws'], 'b' => ['TEXT /ws']], null],
  #  [['a' => ['TEXT /ws Test'], 'b' => ['TEXT /ws Test']], new IllegalArgumentException('Test')],
  #])]
  public function logs_to_all($expected, $error) {
    $logged= ['a' => [], 'b' => []];
    $sink= new ToAllOf(
      new ToFunction(function($kind, $uri, $status, $error= null) use(&$logged) {
        $logged['a'][]= $kind.' '.$uri->path().($error ? ' '.$error->getMessage() : '');
      }),
      new ToFunction(function($kind, $uri, $status, $error= null) use(&$logged) {
        $logged['b'][]= $kind.' '.$uri->path().($error ? ' '.$error->getMessage() : '');
      })
    );
    $sink->log('TEXT', new URI('/ws'), '+OK', $error);

    $this->assertEquals($expected, $logged);
  }
}