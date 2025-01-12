<?php namespace websocket\unittest;

use peer\{Socket, ProtocolException};
use test\{Assert, Expect, Test, Values};
use util\Bytes;
use websocket\{WebSocket, Listener};

class WebSocketTest {

  /** Returns a fixture with the handshake and the given messages */
  private function fixture($payload= '') {
    $fixture= new WebSocket(new Channel(
      "HTTP/1.1 101 Switching Protocols\r\n".
      "Connection: Upgrade\r\n".
      "Upgrade: websocket\r\n".
      "Sec-WebSocket-Accept: pT25h6EVFbWDyyinkmTBvzUVxQo=\r\n".
      "\r\n".
      $payload
    ));
    $fixture->random(fn($bytes) => str_repeat('*', $bytes));
    return $fixture;
  }

  #[Test, Values([['ws://example.com', '/'], ['ws://example.com/', '/'], ['ws://example.com/sub', '/sub']])]
  public function path($url, $expected) {
    Assert::equals($expected, (new WebSocket($url))->path());
  }

  #[Test, Values([['ws://example.com', '/'], ['ws://example.com/?test=1&l=de', '/?test=1&l=de']])]
  public function query($url, $expected) {
    Assert::equals($expected, (new WebSocket($url))->path());
  }

  /** @deprecated */
  #[Test]
  public function default_origin() {
    Assert::null((new WebSocket('ws://example.com'))->origin());
  }

  /** @deprecated */
  #[Test]
  public function origin_via_constructor() {
    Assert::equals('example.com', (new WebSocket('ws://example.com', 'example.com'))->origin());
  }

  #[Test]
  public function socket_argument() {
    $s= new Socket('example.com', 8443);
    Assert::equals($s, (new WebSocket($s))->socket());
  }

  #[Test, Values([[null, '/'], ['/', '/'], ['/sub', '/sub'], ['/?test=1&l=de', '/?test=1&l=de']])]
  public function socket_path($path, $expected) {
    $s= new Socket('example.com', 8443);
    Assert::equals($expected, (new WebSocket($s, $path))->path());
  }

  #[Test, Values([['ws://example.com', 80], ['wss://example.com', 443]])]
  public function default_port($url, $expected) {
    Assert::equals($expected, (new WebSocket($url))->socket()->port);
  }

  #[Test, Values([['ws://example.com:8080', 8080], ['wss://example.com:8443', 8443]])]
  public function port($url, $expected) {
    Assert::equals($expected, (new WebSocket($url))->socket()->port);
  }

  #[Test, Expect(class: ProtocolException::class, message: 'Unexpected response 400 Bad Request: No websocket here!')]
  public function no_websocket_to_connect_to() {
    $fixture= new WebSocket(new Channel(
      "HTTP/1.1 400 Bad Request\r\n".
      "Content-Length: 18\r\n".
      "\r\n".
      "No websocket here!"
    ));
    $fixture->connect();
  }

  #[Test, Expect(class: ProtocolException::class, message: '/Accept key mismatch, have .+, expect .+/')]
  public function handshake_mismatch() {
    $fixture= new WebSocket(new Channel(
      "HTTP/1.1 101 Switching Protocols\r\n".
      "Connection: Upgrade\r\n".
      "Upgrade: websocket\r\n".
      "Sec-WebSocket-Accept: EGUNIQA7j7p+kiqxH/TKPdu8A4g=\r\n".
      "\r\n"
    ));
    $fixture->connect();
  }

  #[Test]
  public function lowercase_headers() {
    $fixture= new WebSocket(new Channel(
      "HTTP/1.1 101 Switching Protocols\r\n".
      "connection: Upgrade\r\n".
      "upgrade: websocket\r\n".
      "sec-websocket-accept: pT25h6EVFbWDyyinkmTBvzUVxQo=\r\n".
      "\r\n"
    ));
    $fixture->random(fn($bytes) => str_repeat('*', $bytes));
    $fixture->connect();
  }

  #[Test]
  public function connect() {
    $fixture= $this->fixture();
    $fixture->connect();

    Assert::true($fixture->connected());
  }

  #[Test]
  public function connect_connected() {
    $fixture= $this->fixture();
    $fixture->socket()->connect();
    $fixture->connect();

    Assert::true($fixture->connected());
  }

  #[Test]
  public function close() {
    $fixture= $this->fixture();
    $fixture->connect();
    $fixture->close();

    Assert::false($fixture->connected());
  }

  #[Test]
  public function receive_text() {
    $fixture= $this->fixture("\x81\x04Test");
    $fixture->connect();

    Assert::equals('Test', $fixture->receive());
  }

  #[Test]
  public function receive_binary() {
    $fixture= $this->fixture("\x82\x08GIF89...");
    $fixture->connect();

    Assert::equals(new Bytes('GIF89...'), $fixture->receive());
  }

  #[Test]
  public function handle_graceful_server_close() {
    $fixture= $this->fixture("\x88\x02\x03\xe8");
    $fixture->connect();

    Assert::null($fixture->receive());
    Assert::false($fixture->connected());
  }

  #[Test]
  public function handle_server_error() {
    $fixture= $this->fixture("\x88\x02\x03\xea");
    $fixture->connect();

    Assert::throws(ProtocolException::class, fn() => $fixture->receive());
    Assert::false($fixture->connected());
  }

  #[Test]
  public function handshake() {
    $fixture= $this->fixture();
    $fixture->connect();

    Assert::equals(
      "GET / HTTP/1.1\r\n".
      "Upgrade: websocket\r\n".
      "Sec-WebSocket-Key: KioqKioqKioqKioqKioqKg==\r\n".
      "Sec-WebSocket-Version: 13\r\n".
      "Connection: Upgrade\r\n".
      "Host: test\r\n\r\n",
      $fixture->socket()->out
    );
  }

  #[Test]
  public function sends_headers() {
    $fixture= $this->fixture();
    $fixture->connect(['Origin' => 'example.com', 'Sec-WebSocket-Protocol' => ['wamp', 'soap']]);

    Assert::equals(
      "GET / HTTP/1.1\r\n".
      "Upgrade: websocket\r\n".
      "Sec-WebSocket-Key: KioqKioqKioqKioqKioqKg==\r\n".
      "Sec-WebSocket-Version: 13\r\n".
      "Connection: Upgrade\r\n".
      "Origin: example.com\r\n".
      "Sec-WebSocket-Protocol: wamp\r\n".
      "Sec-WebSocket-Protocol: soap\r\n".
      "Host: test\r\n\r\n",
      $fixture->socket()->out
    );
  }

  #[Test]
  public function send_text() {
    $fixture= $this->fixture();
    $fixture->connect();
    $fixture->send('Test');

    Assert::equals(
      "GET / HTTP/1.1\r\n".
      "Upgrade: websocket\r\n".
      "Sec-WebSocket-Key: KioqKioqKioqKioqKioqKg==\r\n".
      "Sec-WebSocket-Version: 13\r\n".
      "Connection: Upgrade\r\n".
      "Host: test\r\n\r\n".
      "\x81\x84****\176\117\131\136",
      $fixture->socket()->out
    );
  }

  #[Test]
  public function send_bytes() {
    $fixture= $this->fixture();
    $fixture->connect();
    $fixture->send(new Bytes('GIF89...'));

    Assert::equals(
      "GET / HTTP/1.1\r\n".
      "Upgrade: websocket\r\n".
      "Sec-WebSocket-Key: KioqKioqKioqKioqKioqKg==\r\n".
      "Sec-WebSocket-Version: 13\r\n".
      "Connection: Upgrade\r\n".
      "Host: test\r\n\r\n".
      "\x82\x88****\155\143\154\022\023\004\004\004",
      $fixture->socket()->out
    );
  }

  #[Test]
  public function pings_are_answered() {
    $fixture= $this->fixture("\x89\x01!");
    $fixture->connect();

    Assert::null($fixture->receive());
    Assert::equals(
      "GET / HTTP/1.1\r\n".
      "Upgrade: websocket\r\n".
      "Sec-WebSocket-Key: KioqKioqKioqKioqKioqKg==\r\n".
      "Sec-WebSocket-Version: 13\r\n".
      "Connection: Upgrade\r\n".
      "Host: test\r\n\r\n".
      "\x8a\x81****\013",
      $fixture->socket()->out
    );
  }

  #[Test]
  public function listening() {
    $listener= new class() extends Listener {
      public $events= [];
      public function open($conn) { $this->events[]= 'open'; }
      public function message($conn, $message) { $this->events[]= "message<{$message}>"; }
      public function close($conn, $code, $reason) { $this->events[]= "close<{$code}>"; }
    };

    $fixture= $this->fixture("\x81\x04Test")->listening($listener);
    $fixture->connect();
    $fixture->receive();
    $fixture->close();

    Assert::equals(['open', 'message<Test>', 'close<1000>'], $listener->events);
  }
}