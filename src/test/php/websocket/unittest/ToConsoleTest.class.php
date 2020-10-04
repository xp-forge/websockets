<?php namespace websocket\unittest;

use io\streams\{MemoryOutputStream, StringWriter};
use lang\IllegalArgumentException;
use unittest\{Test, TestCase, _test};
use util\URI;
use websocket\logging\ToConsole;

class ToConsoleTest extends TestCase {

  #[Test]
  public function can_create() {
    new ToConsole();
  }

  #[Test]
  public function log() {
    $out= new MemoryOutputStream();
    (new ToConsole(new StringWriter($out)))->log('TEXT', new URI('/ws'), '+OK');

    $this->assertNotEquals('', $out->getBytes());
  }

  #[_test]
  public function log_with_error() {
    $out= new MemoryOutputStream();
    (new ToConsole(new StringWriter($out)))->log('TEXT', new URI('/ws'), '-ERR', new IllegalArgumentException('Test'));

    $this->assertNotEquals('', $out->getBytes());
  }
}