<?php namespace websocket\unittest;

use unittest\{Test, TestCase, Values};
use websocket\protocol\Opcodes;

class OpcodesTest extends TestCase {

  #[Test, Values([[Opcodes::TEXT, 'TEXT'], [Opcodes::BINARY, 'BINARY'], [Opcodes::CLOSE, 'CLOSE'], [Opcodes::PING, 'PING'], [Opcodes::PONG, 'PONG'],])]
  public function name($opcode, $name) {
    $this->assertEquals($name, Opcodes::nameOf($opcode));
  }

  #[Test]
  public function unknown_name() {
    $this->assertEquals('UNKNOWN(0xff)', Opcodes::nameOf("\xff"));
  }
}