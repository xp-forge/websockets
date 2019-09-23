<?php namespace websocket\unittest;

use unittest\TestCase;
use websocket\Dispatch;
use websocket\Environment;
use websocket\Listeners;
use websocket\Logging;
use xp\ws\Events;
use xp\ws\Protocol;

class ProtocolTest extends TestCase {
  const HANDSHAKE = "GET /ws HTTP/1.1\r\nHost: localhost\r\nSec-WebSocket-Version: 13\r\nSec-WebSocket-Key: VW5pdHRlc\r\n\r\n";

  private $log, $events;

  /** @return void */
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

  #[@test]
  public function handshake_only() {
    $invoked= [];
    $p= $this->fixture(function($conn, $message) use(&$invoked) {
      $invoked[]= [$conn->uri()->path() => $message];
    });

    $c= (new Channel(self::HANDSHAKE))->connect();
    $p->open($this->events, $c, 0);
    $p->data($this->events, $c, 0);
    $p->close($this->events, $c, 0);

    $this->assertEquals([], $invoked);
  }

  #[@test]
  public function complete_flow() {
    $invoked= [];
    $p= $this->fixture(function($conn, $message) use(&$invoked) {
      $invoked[]= [$conn->uri()->path() => $message];
    });

    $c= (new Channel(self::HANDSHAKE."\x81\x04Test"))->connect();
    $p->open($this->events, $c, 0);
    $p->data($this->events, $c, 0);
    $p->data($this->events, $c, 0);
    $p->close($this->events, $c, 0);

    $this->assertEquals([['/ws' => 'Test']], $invoked);
  }
}