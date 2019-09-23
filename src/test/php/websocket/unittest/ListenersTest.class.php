<?php namespace websocket\unittest;

use lang\IllegalArgumentException;
use unittest\TestCase;
use websocket\Environment;
use websocket\Listener;
use websocket\Listeners;

class ListenersTest extends TestCase {
  const ID = 42;

  /**
   * Returns a Listeners instance wth a given implementation of `serve()`.
   *
   * @param  function(xp.ws.Events): [:var] $serve
   * @return web.Listeners
   */
  private function fixture($serve= null) {
    return newinstance(Listeners::class, [new Environment('test')], [
      'serve' => $serve ?: function($events) { /* Implementation irrelevant for this test */ }
    ]);
  }

  #[@test]
  public function cast_function() {
    $f= function($conn, $message) { };
    $this->assertEquals($f, Listeners::cast($f));
  }

  #[@test]
  public function cast_listener() {
    $l= newinstance(Listener::class, [], [
      'message' =>  function($conn, $message) { }
    ]);
    $this->assertEquals([$l, 'message'], Listeners::cast($l));
  }

  #[@test, @expect(IllegalArgumentException::class)]
  public function cast_non_listener() {
    Listeners::cast($this);
  }

  #[@test, @expect(IllegalArgumentException::class)]
  public function cast_null() {
    Listeners::cast(null);
  }

  #[@test]
  public function can_create() {
    $this->fixture();
  }

  #[@test]
  public function compare_to_self() {
    $fixture= $this->fixture();
    $this->assertEquals(0, $fixture->compareTo($fixture));
  }

  #[@test]
  public function compare_to_another_instance() {
    $fixture= $this->fixture();
    $this->assertEquals(1, $fixture->compareTo($this->fixture()));
  }
}
