<?php namespace websocket\unittest;

use test\{Assert, Before, Test};
use websocket\{Dispatch, Environment, Listeners, Logging};
use xp\websockets\{Events, Protocol};

class ProtocolTest {
  const ID= 42;
  const HANDSHAKE= "GET /ws HTTP/1.1\r\nHost: localhost\r\nSec-WebSocket-Version: 13\r\nSec-WebSocket-Key: VW5pdHRlc\r\n\r\n";

  private $log, $events;

  #[Before]
  public function setUp() {
    $this->log= new Logging(null);
    $this->events= new Events();
  }

  /**
   * Creates a fixture
   *
   * @param  function(web.protocol.Connection, string): var $listener
   * @return web.protocol.Http
   */
  private function fixture($listener= null) {
    $listeners= newinstance(Listeners::class, [new Environment('test'), $this->events], [
      'serve' => function($events) use($listener) {
        return ['/ws' => $listener ?: function($conn, $message) { }];
      }
    ]);

    return new Protocol($listeners, $this->log);
  }

  #[Test]
  public function handshake_only() {
    $invoked= [];
    $p= $this->fixture(function($conn, $message) use(&$invoked) {
      $invoked[]= [$conn->id() => $message];
    });

    $c= (new Channel(self::HANDSHAKE))->connect();
    $p->open($this->events, $c, self::ID);
    $p->data($this->events, $c, self::ID);
    $p->close($this->events, $c, self::ID);

    Assert::equals([], $invoked);
  }

  #[Test]
  public function complete_flow() {
    $invoked= [];
    $p= $this->fixture(function($conn, $message) use(&$invoked) {
      $invoked[]= [$conn->id() => $message];
    });

    $c= (new Channel(self::HANDSHAKE."\x81\x04Test"))->connect();
    $p->open($this->events, $c, self::ID);
    $p->data($this->events, $c, self::ID);
    $p->data($this->events, $c, self::ID);
    $p->close($this->events, $c, self::ID);

    Assert::equals([[self::ID => 'Test']], $invoked);
  }
}