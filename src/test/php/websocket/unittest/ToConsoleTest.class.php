<?php namespace websocket\unittest;

use io\streams\{MemoryOutputStream, StringWriter};
use lang\IllegalArgumentException;
use test\Assert;
use test\{Test, TestCase};
use util\URI;
use websocket\logging\ToConsole;

class ToConsoleTest {

  #[Test]
  public function can_create() {
    new ToConsole();
  }

  #[Test]
  public function log() {
    $out= new MemoryOutputStream();
    (new ToConsole(new StringWriter($out)))->log('TEXT', new URI('/ws'), '+OK');

    Assert::notEquals('', $out->bytes());
  }

  #[Test]
  public function log_with_error() {
    $out= new MemoryOutputStream();
    (new ToConsole(new StringWriter($out)))->log('TEXT', new URI('/ws'), '-ERR', new IllegalArgumentException('Test'));

    Assert::notEquals('', $out->bytes());
  }
}