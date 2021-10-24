<?php namespace websocket\unittest;

use lang\IllegalArgumentException;
use unittest\Assert;
use unittest\{Test, TestCase};
use util\URI;
use util\log\{BufferedAppender, LogCategory};
use websocket\logging\ToCategory;

class ToCategoryTest {
  const ID = 42;

  #[Test]
  public function can_create() {
    new ToCategory(new LogCategory());
  }

  #[Test]
  public function log() {
    $buf= new BufferedAppender();
    (new ToCategory((new LogCategory())->withAppender($buf)))->log(self::ID, 'TEXT', '+OK');

    Assert::notEquals('', $buf->getBuffer());
  }

  #[Test]
  public function log_with_error() {
    $buf= new BufferedAppender();
    (new ToCategory((new LogCategory())->withAppender($buf)))->log(self::ID, 'TEXT', new IllegalArgumentException('Test'));

    Assert::notEquals('', $buf->getBuffer());
  }
}