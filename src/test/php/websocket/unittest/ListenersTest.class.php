<?php namespace websocket\unittest;

use lang\IllegalArgumentException;
use unittest\{Expect, Test, TestCase, Values};
use websocket\{Environment, Listener, Listeners};

class ListenersTest extends TestCase {
  const ID = 42;

  /**
   * Returns a Listeners instance wth a given implementation of `serve()`.
   *
   * @param  function(xp.ws.Events): [:var] $serve
   * @return web.Listeners
   */
  private function fixture($serve= null) {
    return newinstance(Listeners::class, [new Environment('test'), null], [
      'serve' => $serve ?: function($events) { /* Implementation irrelevant for this test */ }
    ]);
  }

  #[Test]
  public function cast_function() {
    $f= function($conn, $message) { };
    $this->assertEquals($f, Listeners::cast($f));
  }

  #[Test]
  public function cast_listener() {
    $l= newinstance(Listener::class, [], [
      'message' =>  function($conn, $message) { }
    ]);
    $this->assertEquals([$l, 'message'], Listeners::cast($l));
  }

  #[Test, Expect(IllegalArgumentException::class)]
  public function cast_non_listener() {
    Listeners::cast($this);
  }

  #[Test, Expect(IllegalArgumentException::class)]
  public function cast_null() {
    Listeners::cast(null);
  }

  #[Test]
  public function can_create() {
    $this->fixture();
  }

  #[Test]
  public function compare_to_self() {
    $fixture= $this->fixture();
    $this->assertEquals(0, $fixture->compareTo($fixture));
  }

  #[Test]
  public function compare_to_another_instance() {
    $fixture= $this->fixture();
    $this->assertEquals(1, $fixture->compareTo($this->fixture()));
  }

  #[Test, Values([['/listen', 'listen'], ['/test', 'test'], ['/test/', 'test'], ['/test/chat', 'test'], ['/testing', 'catch-all'], ['/prod', 'catch-all'],])]
  public function listener($path, $expected) {
    $listeners= $this->fixture(function($events) {
      return [
        '/listen' => newinstance(Listener::class, [], [
          'message' => function($conn, $payload) { return 'listen'; }
        ]),
        '/test'   => function($conn, $payload) { return 'test'; },
        '/'       => function($conn, $payload) { return 'catch-all'; }
      ];
    });
    $listener= $listeners->listener($path);
    $this->assertEquals($expected, $listener(null, 'Test'));
  }

  #[Test]
  public function no_catch_all() {
    $listeners= $this->fixture(function($events) {
      return [
        '/test'   => function($conn, $payload) { return 'test'; },
      ];
    });
    $this->assertNull($listeners->listener('/prod'));
  }
}