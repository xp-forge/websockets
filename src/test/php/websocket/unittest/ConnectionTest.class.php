<?php namespace websocket\unittest;

use test\{Assert, Test, Values};
use util\Bytes;
use websocket\protocol\{Connection, Opcodes};

class ConnectionTest {
  const ID = 0;

  /**
   * Receive all messages from a given input channel
   *
   * @param  websocket.unittest.Channel $channel
   * @return []
   */
  private function receive($channel) {
    $conn= new Connection($channel->connect(), self::ID, function($conn, $message) { }, []);
    $r= [];
    foreach ($conn->receive() as $type => $message) {
      $r[]= [$type => $message];
    }
    return $r;
  }

  #[Test]
  public function can_create() {
    new Connection(new Channel(), self::ID, function($conn, $message) { }, []);
  }

  #[Test]
  public function id() {
    Assert::equals(self::ID, (new Connection(new Channel(), self::ID, function($conn, $message) { }, []))->id());
  }

  #[Test]
  public function listener() {
    $listener= function($conn, $message) { };
    Assert::equals($listener, (new Connection(new Channel(), self::ID, $listener, []))->listener());
  }

  #[Test, Values([[[]], [['User-Agent' => 'Test', 'Accept' => '*/*']]])]
  public function headers($value) {
    Assert::equals($value, (new Connection(new Channel(), self::ID, function($conn, $message) { }, $value))->headers());
  }

  #[Test]
  public function text() {
    $received= $this->receive(new Channel("\x81\x04Test"));
    Assert::equals(
      [[Opcodes::TEXT => 'Test']],
      $received
    );
  }

  #[Test]
  public function masked_text() {
    $received= $this->receive(new Channel("\x81\x86\x01\x02\x03\x04Ugppdf"));
    Assert::equals(
      [[Opcodes::TEXT => 'Tested']],
      $received
    );
  }

  #[Test]
  public function fragmented_text() {
    $received= $this->receive(new Channel("\x01\x05Hello\x80\x06 World"));
    Assert::equals(
      [[Opcodes::TEXT => 'Hello World']],
      $received
    );
  }

  #[Test]
  public function fragmented_text_with_ping_inbetween() {
    $received= $this->receive(new Channel("\x01\x05Hello\x89\x01!\x80\x06 World"));
    Assert::equals(
      [[Opcodes::PING => '!'], [Opcodes::TEXT => 'Hello World']],
      $received
    );
  }

  #[Test, Values(['', "\x81"])]
  public function closes_connection_on_invalid_packet($bytes) {
    $channel= (new Channel($bytes))->connect();
    $this->receive($channel);

    Assert::equals('', $channel->out);
    Assert::false($channel->isConnected(), 'Channel closed');
  }

  #[Test]
  public function closes_connection_on_invalid_opcode() {
    $channel= (new Channel("\x8f\x00"))->connect();
    $this->receive($channel);

    // 0x80 | 0x08 (CLOSE), 2 bytes, pack("n", 1002)
    Assert::equals("\x88\x02\x03\xea", $channel->out);
    Assert::false($channel->isConnected(), 'Channel closed');
  }

  #[Test]
  public function closes_connection_when_exceeding_max_length() {
    $channel= (new Channel("\x81\x7f".pack('J', Connection::MAXLENGTH + 1)))->connect();
    $this->receive($channel);

    // 0x80 | 0x08 (CLOSE), 2 bytes, pack("n", 1003)
    Assert::equals("\x88\x02\x03\xeb", $channel->out);
    Assert::false($channel->isConnected(), 'Channel closed');
  }

  #[Test]
  public function send_string() {
    $channel= (new Channel())->connect();
    (new Connection($channel, self::ID, function($conn, $message) { }, []))->send('Test');

    Assert::equals("\x81\x04Test", $channel->out);
  }

  #[Test]
  public function send_bytes() {
    $channel= (new Channel())->connect();
    (new Connection($channel, self::ID, function($conn, $message) { }, []))->send(new Bytes('Test'));

    Assert::equals("\x82\x04Test", $channel->out);
  }

  #[Test, Values([[0, "\x81\x00"], [1, "\x81\x01"], [125, "\x81\x7d"], [126, "\x81\x7e\x00\x7e"], [65535, "\x81\x7e\xff\xff"], [65536, "\x81\x7f\x00\x00\x00\x00\x00\x01\x00\x00"],])]
  public function send($length, $header) {
    $string= str_repeat('*', $length);
    $channel= (new Channel())->connect();
    (new Connection($channel, self::ID, function($conn, $message) { }, []))->send($string);

    Assert::equals(new Bytes($header), new Bytes(substr($channel->out, 0, strlen($header))));
    Assert::equals(strlen($header) + $length, strlen($channel->out));
  }

  #[Test, Values([[0, "\x81\x00"], [1, "\x81\x01"], [125, "\x81\x7d"], [126, "\x81\x7e\x00\x7e"], [65535, "\x81\x7e\xff\xff"], [65536, "\x81\x7f\x00\x00\x00\x00\x00\x01\x00\x00"],])]
  public function read($length, $header) {
    $string= str_repeat('*', $length);
    $channel= (new Channel($header.$string))->connect();
    $message= (new Connection($channel, self::ID, function($conn, $message) { }, []))->receive()->current();

    Assert::equals($length, strlen($message));
  }
}