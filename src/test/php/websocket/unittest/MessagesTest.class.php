<?php namespace websocket\unittest;

use lang\IllegalStateException;
use unittest\Assert;
use unittest\{Test, TestCase, Values};
use util\{Bytes, URI};
use websocket\logging\Sink;
use websocket\protocol\{Connection, Messages};
use websocket\{Dispatch, Environment, Listeners, Logging};

class MessagesTest {
  const ID = 42;

  private $log;

  /** @return void */
  #[Before]
  public function setUp() {
    $this->log= new Logging(null);
  }

  /**
   * Creates a fixture
   *
   * @param  websocket.unittest.Channel $channel
   * @param  function(web.protocol.Connection, string): var $listener
   * @return websocket.protocol.Messages
   */
  private function fixture($channel, $listener= null) {
    $listeners= newinstance(Listeners::class, [new Environment('test'), null], [
      'serve' => function($events) use($listener) {
        return ['/ws' => $listener ?: function($conn, $message) { }];
      }
    ]);

    // Simulate handshake
    $channel->connect();
    $listeners->connections[self::ID]= new Connection($channel, self::ID, $listener, []);

    return new Messages($listeners, $this->log);
  }

  #[Test, Values(eval: '[["\x81", "Test"], ["\x82", new Bytes("Test")]]')]
  public function handle_message($type, $expected) {
    $invoked= [];
    $c= new Channel($type."\x04Test");
    $p= $this->fixture($c, function($conn, $message) use(&$invoked) {
      $invoked[]= [$conn->id() => $message];
    });
    $p->next($c, self::ID);

    Assert::equals([[self::ID => $expected]], $invoked);
  }

  #[Test]
  public function text_message_with_malformed_utf8() {
    $c= new Channel("\x81\x04\xfcber");
    $this->fixture($c)->next($c, self::ID);

    Assert::equals(new Bytes("\x88\x02\x03\xef"), new Bytes(substr($c->out, -4)));
    Assert::false($c->isConnected());
  }

  #[Test]
  public function incoming_ping_answered_with_pong() {
    $c= new Channel("\x89\x04Test");
    $this->fixture($c)->next($c, self::ID);

    Assert::equals(new Bytes("\x8a\x04Test"), new Bytes(substr($c->out, -6)));
  }

  #[Test]
  public function incoming_pong_ignored() {
    $c= new Channel("\x8a\x04Test");
    $this->fixture($c)->next($c, self::ID);

    Assert::equals('', $c->out);
  }

  #[Test]
  public function close_without_payload() {
    $c= new Channel("\x88\x00");
    $this->fixture($c)->next($c, self::ID);

    Assert::equals(new Bytes("\x88\x02\x03\xe8"), new Bytes(substr($c->out, -4)));
    Assert::false($c->isConnected());
  }

  #[Test]
  public function close_with_code_and_message_echoed() {
    $c= new Channel("\x88\x06\x0b\xb8Test");
    $this->fixture($c)->next($c, self::ID);

    Assert::equals(new Bytes("\x88\x06\x0b\xb8Test"), new Bytes(substr($c->out, -8)));
    Assert::false($c->isConnected());
  }

  #[Test]
  public function close_with_illegal_client_code() {
    $c= new Channel("\x88\x06\x03\xecTest");
    $this->fixture($c)->next($c, self::ID);

    Assert::equals(new Bytes("\x88\x02\x03\xea"), new Bytes(substr($c->out, -4)));
    Assert::false($c->isConnected());
  }

  #[Test]
  public function close_with_malformed_utf8() {
    $c= new Channel("\x88\x06\x03\xec\xfcber");
    $this->fixture($c)->next($c, self::ID);

    Assert::equals(new Bytes("\x88\x02\x03\xef"), new Bytes(substr($c->out, -4)));
    Assert::false($c->isConnected());
  }

  #[Test]
  public function exceptions_are_logged() {
    $logged= [];
    $c= new Channel("\x81\x04Test");
    $this->log= new Logging(newinstance(Sink::class, [], [
      'log' => function($client, $opcode, $result) use(&$logged) {
        $logged[]= [$client, $opcode, typeof($result)->getName()];
      }
    ]));
    $this->fixture($c, function($conn, $message) { throw new IllegalStateException('Test'); })->next($c, self::ID);

    Assert::equals([[self::ID, 'TEXT', 'lang.IllegalStateException']], $logged);
  }

  #[Test]
  public function native_exceptions_are_wrapped() {
    $logged= [];
    $c= new Channel("\x81\x04Test");
    $this->log= new Logging(newinstance(Sink::class, [], [
      'log' => function($client, $opcode, $result) use(&$logged) {
        $logged[]= [$client, $opcode, typeof($result)->getName()];
      }
    ]));
    $this->fixture($c, function($conn, $message) { throw new \Exception('Test'); })->next($c, self::ID);

    Assert::equals([[self::ID, 'TEXT', 'lang.XPException']], $logged);
  }

  #[Test]
  public function end() {
    $c= new Channel();
    $this->fixture($c)->end($c, self::ID);
  }
}