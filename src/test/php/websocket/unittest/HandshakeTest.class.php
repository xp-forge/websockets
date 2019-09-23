<?php namespace websocket\unittest;

use unittest\TestCase;
use websocket\Dispatch;
use websocket\Environment;
use websocket\Listeners;
use websocket\Logging;
use websocket\protocol\Handshake;

class HandshakeTest extends TestCase {
  private $log;

  /** @return void */
  public function setUp() {
    $this->log= new Logging(null);
  }

  /**
   * Creates a fixture
   *
   * @param  function(web.protocol.Connection, string): var $listener
   * @return websocket.protocol.Handshake
   */
  private function fixture($listener= null) {
    $listeners= newinstance(Listeners::class, [new Environment('test'), null], [
      'serve' => function($events) use($listener) {
        return ['/ws' => $listener ?: function($conn, $message) { }];
      }
    ]);

    return new Handshake($listeners, $this->log);
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
  public function successful_handshake() {
    $c= new Channel(
      "GET /ws HTTP/1.1\r\n".
      "Host: localhost\r\n".
      "Sec-WebSocket-Version: 13\r\n".
      "Sec-WebSocket-Key: VW5pdHRlc\r\n".
      "\r\n"
    );
    $this->fixture()->next($c->connect(), 0);

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
    $this->assertEquals(600.0, $c->getTimeout());
    $this->assertTrue($c->isConnected());
  }

  #[@test]
  public function cannot_dispatch() {
    $c= new Channel(
      "GET /chat HTTP/1.1\r\n".
      "Host: localhost\r\n".
      "Sec-WebSocket-Version: 13\r\n".
      "Sec-WebSocket-Key: VW5pdHRlc\r\n".
      "\r\n"
    );
    $this->fixture()->next($c->connect(), 0);

    $this->assertHttp(
      "HTTP/1.1 400 Bad Request\r\n".
      "Date: [A-Za-z]+, [0-9]+ [A-Za-z]+ [0-9]+ [0-9]+:[0-9]+:[0-9]+ GMT\r\n".
      "Host: localhost\r\n".
      "Connection: close\r\n".
      "Content-Type: text/plain\r\n".
      "Content-Length: 37\r\n".
      "\r\n".
      "This service does not listen on /chat",
      $c->out
    );
    $this->assertFalse($c->isConnected());
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
    $this->fixture()->next($c->connect(), 0);

    $this->assertHttp(
      "HTTP/1.1 400 Bad Request\r\n".
      "Date: [A-Za-z]+, [0-9]+ [A-Za-z]+ [0-9]+ [0-9]+:[0-9]+:[0-9]+ GMT\r\n".
      "Host: localhost\r\n".
      "Connection: close\r\n".
      "Content-Type: text/plain\r\n".
      "Content-Length: 50\r\n".
      "\r\n".
      "This service does not support WebSocket version 99",
      $c->out
    );
    $this->assertFalse($c->isConnected());
  }

  #[@test]
  public function normal_http_request() {
    $c= new Channel(
      "GET /ws HTTP/1.1\r\n".
      "Host: localhost\r\n".
      "Connection: close\r\n".
      "\r\n"
    );
    $this->fixture()->next($c->connect(), 0);

    $this->assertHttp(
      "HTTP/1.1 426 Upgrade Required\r\n".
      "Date: [A-Za-z]+, [0-9]+ [A-Za-z]+ [0-9]+ [0-9]+:[0-9]+:[0-9]+ GMT\r\n".
      "Host: localhost\r\n".
      "Connection: Upgrade\r\n".
      "Upgrade: websocket\r\n".
      "Content-Type: text/plain\r\n".
      "Content-Length: 51\r\n".
      "\r\n".
      "This service requires use of the WebSocket protocol",
      $c->out
    );
    $this->assertFalse($c->isConnected());
  }

  #[@test]
  public function end_is_noop() {
    $c= new Channel();
    $this->fixture()->end($c, 0);
  }
}