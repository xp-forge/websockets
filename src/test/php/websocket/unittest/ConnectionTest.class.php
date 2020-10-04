<?php namespace websocket\unittest;

use unittest\{Test, TestCase, Values};
use util\Bytes;
use websocket\protocol\{Connection, Opcodes};

class ConnectionTest extends TestCase {
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
    $this->assertEquals(self::ID, (new Connection(new Channel(), self::ID, function($conn, $message) { }, []))->id());
  }

  #[Test]
  public function listener() {
    $listener= function($conn, $message) { };
    $this->assertEquals($listener, (new Connection(new Channel(), self::ID, $listener, []))->listener());
  }

  #[Test, Values([[[]], [['User-Agent' => 'Test', 'Accept' => '*/*']]])]
  public function headers($value) {
    $this->assertEquals($value, (new Connection(new Channel(), self::ID, function($conn, $message) { }, $value))->headers());
  }

  #[Test]
  public function text() {
    $received= $this->receive(new Channel("\x81\x04Test"));
    $this->assertEquals(
      [[Opcodes::TEXT => 'Test']],
      $received
    );
  }

  #[Test]
  public function masked_text() {
    $received= $this->receive(new Channel("\x81\x86\x01\x02\x03\x04Ugppdf"));
    $this->assertEquals(
      [[Opcodes::TEXT => 'Tested']],
      $received
    );
  }

  #[Test]
  public function fragmented_text() {
    $received= $this->receive(new Channel("\x01\x05Hello\x80\x06 World"));
    $this->assertEquals(
      [[Opcodes::TEXT => 'Hello World']],
      $received
    );
  }

  #[Test]
  public function fragmented_text_with_ping_inbetween() {
    $received= $this->receive(new Channel("\x01\x05Hello\x89\x01!\x80\x06 World"));
    $this->assertEquals(
      [[Opcodes::PING => '!'], [Opcodes::TEXT => 'Hello World']],
      $received
    );
  }

  #[Test, Values(['', "\x81"])]
  public function closes_connection_on_invalid_packet($bytes) {
    $channel= (new Channel($bytes))->connect();
    $this->receive($channel);

    $this->assertEquals('', $channel->out);
    $this->assertFalse($channel->isConnected(), 'Channel closed');
  }

  #[Test]
  public function closes_connection_on_invalid_opcode() {
    $channel= (new Channel("\x8f\x00"))->connect();
    $this->receive($channel);

    // 0x80 | 0x08 (CLOSE), 2 bytes, pack("n", 1002)
    $this->assertEquals("\x88\x02\x03\xea", $channel->out);
    $this->assertFalse($channel->isConnected(), 'Channel closed');
  }

  #[Test]
  public function closes_connection_when_exceeding_max_length() {
    $channel= (new Channel("\x81\x7f".pack('J', Connection::MAXLENGTH + 1)))->connect();
    $this->receive($channel);

    // 0x80 | 0x08 (CLOSE), 2 bytes, pack("n", 1003)
    $this->assertEquals("\x88\x02\x03\xeb", $channel->out);
    $this->assertFalse($channel->isConnected(), 'Channel closed');
  }

  #[Test]
  public function send_string() {
    $channel= (new Channel())->connect();
    (new Connection($channel, self::ID, function($conn, $message) { }, []))->send('Test');

    $this->assertEquals("\x81\x04Test", $channel->out);
  }

  #[Test]
  public function send_bytes() {
    $channel= (new Channel())->connect();
    (new Connection($channel, self::ID, function($conn, $message) { }, []))->send(new Bytes('Test'));

    $this->assertEquals("\x82\x04Test", $channel->out);
  }

  #[Test, Values([[0, "\x81\x00"], [1, "\x81\x01"], [125, "\x81\x7d"], [126, "\x81\x7e\x00\x7e"], [65535, "\x81\x7e\xff\xff"], [65536, "\x81\x7f\x00\x00\x00\x00\x00\x01\x00\x00"],])]
  public function send($length, $header) {
    $string= str_repeat('*', $length);
    $channel= (new Channel())->connect();
    (new Connection($channel, self::ID, function($conn, $message) { }, []))->send($string);

    $this->assertEquals(new Bytes($header), new Bytes(substr($channel->out, 0, strlen($header))));
    $this->assertEquals(strlen($header) + $length, strlen($channel->out));
  }

  #[Test, Values([[0, "\x81\x00"], [1, "\x81\x01"], [125, "\x81\x7d"], [126, "\x81\x7e\x00\x7e"], [65535, "\x81\x7e\xff\xff"], [65536, "\x81\x7f\x00\x00\x00\x00\x00\x01\x00\x00"],])]
  public function read($length, $header) {
    $string= str_repeat('*', $length);
    $channel= (new Channel($header.$string))->connect();
    $message= (new Connection($channel, self::ID, function($conn, $message) { }, []))->receive()->current();

    $this->assertEquals($length, strlen($message));
  }
}