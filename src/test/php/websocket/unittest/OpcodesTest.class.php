<?php namespace websocket\unittest;

use unittest\Assert;
use unittest\{Test, TestCase, Values};
use websocket\protocol\Opcodes;

class OpcodesTest {

  #[Test, Values([[Opcodes::TEXT, 'TEXT'], [Opcodes::BINARY, 'BINARY'], [Opcodes::CLOSE, 'CLOSE'], [Opcodes::PING, 'PING'], [Opcodes::PONG, 'PONG'],])]
  public function name($opcode, $name) {
    Assert::equals($name, Opcodes::nameOf($opcode));
  }

  #[Test]
  public function unknown_name() {
    Assert::equals('UNKNOWN(0xff)', Opcodes::nameOf("\xff"));
  }
}