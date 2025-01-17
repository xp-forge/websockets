<?php namespace websocket\unittest;

use lang\IllegalArgumentException;
use test\{Assert, Expect, Test, Values};
use websocket\{Environment, Listener, Listeners};

class ListenersTest {
  const ID= 42;

  /**
   * Returns a Listeners instance wth a given implementation of `serve()`.
   *
   * @param  function(xp.websockets.Events): [:var] $serve
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
    Assert::instance(Listener::class, Listeners::cast($f));
  }

  #[Test]
  public function cast_listener() {
    $l= newinstance(Listener::class, [], [
      'message' =>  function($conn, $message) { }
    ]);
    Assert::instance(Listener::class, Listeners::cast($l));
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
    Assert::equals(0, $fixture->compareTo($fixture));
  }

  #[Test]
  public function compare_to_another_instance() {
    $fixture= $this->fixture();
    Assert::equals(1, $fixture->compareTo($this->fixture()));
  }

  #[Test, Values([['/listen', 'listen'], ['/test', 'test'], ['/test/', 'test'], ['/test/chat', 'test'], ['/testing', 'catch-all'], ['/prod', 'catch-all'],])]
  public function listener($path, $expected) {
    $listeners= $this->fixture(function($events) {
      return [
        '/listen' => newinstance(Listener::class, [], [
          'message' => fn($conn, $payload) => 'listen',
        ]),
        '/test'   => fn($conn, $payload) => 'test',
        '/'       => fn($conn, $payload) => 'catch-all',
      ];
    });

    Assert::equals($expected, $listeners->listener($path)->message(null, 'Test'));
  }

  #[Test, Values(['/', '/test'])]
  public function catch_all_when_returning_listener($path) {
    $listener= function($conn, $payload) { return 'test'; };
    $listeners= $this->fixture(fn($events) => $listener);

    Assert::equals('test', $listeners->listener($path)->message(null, 'Test'));
  }

  #[Test]
  public function no_catch_all() {
    $listeners= $this->fixture(function($events) {
      return [
        '/test' => fn($conn, $payload) => 'test',
      ];
    });
    Assert::null($listeners->listener('/prod'));
  }
}