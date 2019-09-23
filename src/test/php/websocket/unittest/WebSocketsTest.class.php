<?php namespace websocket\unittest;

use lang\IllegalStateException;
use peer\SocketTimeoutException;
use unittest\TestCase;
use util\Bytes;
use websocket\Dispatch;
use websocket\Environment;
use websocket\Listeners;
use websocket\Logging;
use websocket\logging\Sink;
use xp\ws\Events;
use xp\ws\Protocol;

class WebSocketsTest extends TestCase {
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
    $listeners= newinstance(Listeners::class, [new Environment('test')], [
      'serve' => function($events) use($listener) {
        return ['/ws' => $listener ?: function($conn, $message) { }];
      }
    ]);

    return new Protocol($listeners, new Dispatch($listeners->serve($this->events)), $this->log);
  }

  /**
   * Assertion helper
   *
   * @param  string $expected Regular expression without delimiters
   * @param  string[] $out
   * @throws unittest.AssertionFailedError
   */
  private function assertHttp($expected, $out) {
    if (!preg_match('#^'.$expected.'$#m', $out)) {
      $this->fail('=~', $out, $expected);
    }
  }

  #[@test]
  public function can_create() {
    $this->fixture();
  }

  #[@test]
  public function handle_connect_reads_handshake() {
    $c= (new Channel(self::HANDSHAKE))->connect();
    $p= $this->fixture();
    $p->open($this->events, $c, 0);
    $p->data($this->events, $c, 0);

    $this->assertHttp(
      "HTTP/1.1 101 Switching Protocols\r\n".
      "Date: [A-Za-z]+, [0-9]+ [A-Za-z]+ [0-9]+ [0-9]+:[0-9]+:[0-9]+ GMT\r\n".
      "Host: localhost\r\n".
      "Connection: Upgrade\r\n".
      "Upgrade: websocket\r\n".
      "Sec-WebSocket-Accept: burhE5E1BXOFMByjTtUeclRFR9w=\r\n".
      "\r\n",
      $c->out
    );
  }

  #[@test]
  public function handle_connect_sets_timeout() {
    $c= (new Channel(self::HANDSHAKE))->connect();
    $p= $this->fixture();
    $p->open($this->events, $c, 0);
    $p->data($this->events, $c, 0);

    $this->assertEquals(600.0, $c->getTimeout());
  }

  #[@test]
  public function unsupported_ws_version() {
    $c= new Channel(
      "GET /ws HTTP/1.1\r\n".
      "Host: localhost\r\n".
      "Connection: Upgrade\r\n".
      "Upgrade: websocket\r\n".
      "Sec-WebSocket-Version: 99\r\n".
      "\r\n"
    );
    $p= $this->fixture();
    $p->open($this->events, $c->connect(), 0);
    $p->data($this->events, $c, 0);

    $this->assertHttp(
      "HTTP/1.1 400 Bad Request\r\n".
      "Date: [A-Za-z]+, [0-9]+ [A-Za-z]+ [0-9]+ [0-9]+:[0-9]+:[0-9]+ GMT\r\n".
      "Host: localhost\r\n".
      "Connection: close\r\n".
      "Content-Type: text/plain\r\n".
      "Content-Length: 32\r\n".
      "\r\n".
      "Unsupported websocket version 99",
      $c->out
    );
  }

  #[@test, @values([["\x81", 'Test'], ["\x82", new Bytes('Test')]])]
  public function handle_message($type, $expected) {
    $invoked= [];
    $p= $this->fixture(function($conn, $message) use(&$invoked) {
      $invoked[]= [$conn->uri()->path() => $message];
    });

    $c= (new Channel(self::HANDSHAKE.$type."\x04Test"))->connect();
    $p->open($this->events, $c, 0);
    $p->data($this->events, $c, 0);
    $p->data($this->events, $c, 0);

    $this->assertEquals([['/ws' => $expected]], $invoked);
  }

  #[@test]
  public function text_message_with_malformed_utf8() {
    $p= $this->fixture(function($conn, $message) { });

    $c= (new Channel(self::HANDSHAKE."\x81\x04\xfcber"))->connect();
    $p->open($this->events, $c, 0);
    $p->data($this->events, $c, 0);
    $p->data($this->events, $c, 0);

    $this->assertEquals(new Bytes("\x88\x02\x03\xef"), new Bytes(substr($c->out, -4)));
    $this->assertFalse($c->isConnected());
  }

  #[@test]
  public function incoming_ping_answered_with_pong() {
    $p= $this->fixture(function($conn, $message) { });

    $c= (new Channel(self::HANDSHAKE."\x89\x04Test"))->connect();
    $p->open($this->events, $c, 0);
    $p->data($this->events, $c, 0);
    $p->data($this->events, $c, 0);

    $this->assertEquals(new Bytes("\x8a\x04Test"), new Bytes(substr($c->out, -6)));
  }

  #[@test]
  public function incoming_pong_ignored() {
    $p= $this->fixture();

    $c= (new Channel(self::HANDSHAKE."\x8a\x04Test"))->connect();
    $p->open($this->events, $c, 0);
    $p->data($this->events, $c, 0);
    $out= $c->out;
    $p->data($this->events, $c, 0);

    $this->assertEquals($out, $c->out);
  }

  #[@test]
  public function close_without_payload() {
    $p= $this->fixture();

    $c= (new Channel(self::HANDSHAKE."\x88\x00"))->connect();
    $p->open($this->events, $c, 0);
    $p->data($this->events, $c, 0);
    $p->data($this->events, $c, 0);

    $this->assertEquals(new Bytes("\x88\x02\x03\xe8"), new Bytes(substr($c->out, -4)));
    $this->assertFalse($c->isConnected());
  }

  #[@test]
  public function close_with_code_and_message_echoed() {
    $p= $this->fixture();

    $c= (new Channel(self::HANDSHAKE."\x88\x06\x0b\xb8Test"))->connect();
    $p->open($this->events, $c, 0);
    $p->data($this->events, $c, 0);
    $p->data($this->events, $c, 0);

    $this->assertEquals(new Bytes("\x88\x06\x0b\xb8Test"), new Bytes(substr($c->out, -8)));
    $this->assertFalse($c->isConnected());
  }

  #[@test]
  public function close_with_illegal_client_code() {
    $p= $this->fixture();

    $c= (new Channel(self::HANDSHAKE."\x88\x06\x03\xecTest"))->connect();
    $p->open($this->events, $c, 0);
    $p->data($this->events, $c, 0);
    $p->data($this->events, $c, 0);

    $this->assertEquals(new Bytes("\x88\x02\x03\xea"), new Bytes(substr($c->out, -4)));
    $this->assertFalse($c->isConnected());
  }

  #[@test]
  public function close_with_malformed_utf8() {
    $p= $this->fixture();

    $c= (new Channel(self::HANDSHAKE."\x88\x06\x03\xec\xfcber"))->connect();
    $p->open($this->events, $c, 0);
    $p->data($this->events, $c, 0);
    $p->data($this->events, $c, 0);

    $this->assertEquals(new Bytes("\x88\x02\x03\xef"), new Bytes(substr($c->out, -4)));
    $this->assertFalse($c->isConnected());
  }

  #[@test]
  public function exceptions_are_logged() {
    $logged= [];
    $this->log= new Logging(newinstance(Sink::class, [], [
      'log' => function($kind, $uri, $status, $error= null) use(&$logged) {
        $logged[]= [$kind, $uri->path(), $status, $error ? nameof($error).':'.$error->getMessage() : null];
      }
    ]));
    $p= $this->fixture(function($conn, $message) { throw new IllegalStateException('Test'); });

    $c= (new Channel(self::HANDSHAKE."\x81\x04Test"))->connect();
    $p->open($this->events, $c, 0);
    $p->data($this->events, $c, 0);
    $p->data($this->events, $c, 0);

    $this->assertEquals(
      [['OPEN(GET)', '/ws', 0, null], ['TEXT', '/ws', 0, 'lang.IllegalStateException:Test']],
      $logged
    );
  }

  #[@test]
  public function native_exceptions_are_wrapped() {
    $logged= [];
    $this->log= new Logging(newinstance(Sink::class, [], [
      'log' => function($kind, $uri, $status, $error= null) use(&$logged) {
        $logged[]= [$kind, $uri->path(), $status, $error ? nameof($error).':'.$error->getMessage() : null];
      }
    ]));
    $p= $this->fixture(function($conn, $message) { throw new \Exception('Test'); });

    $c= (new Channel(self::HANDSHAKE."\x81\x04Test"))->connect();
    $p->open($this->events, $c, 0);
    $p->data($this->events, $c, 0);
    $p->data($this->events, $c, 0);

    $this->assertEquals(
      [['OPEN(GET)', '/ws', 0, null], ['TEXT', '/ws', 0, 'lang.XPException:Test']],
      $logged
    );
  }
}