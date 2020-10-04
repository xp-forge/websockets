<?php namespace websocket\unittest;

use lang\IllegalArgumentException;
use unittest\{Test, TestCase};
use util\URI;
use util\log\{BufferedAppender, LogCategory};
use websocket\logging\ToCategory;

class ToCategoryTest extends TestCase {
  const ID = 42;

  #[Test]
  public function can_create() {
    new ToCategory(new LogCategory());
  }

  #[Test]
  public function log() {
    $buf= new BufferedAppender();
    (new ToCategory((new LogCategory())->withAppender($buf)))->log(self::ID, 'TEXT', '+OK');

    $this->assertNotEquals('', $buf->getBuffer());
  }

  #[Test]
  public function log_with_error() {
    $buf= new BufferedAppender();
    (new ToCategory((new LogCategory())->withAppender($buf)))->log(self::ID, 'TEXT', new IllegalArgumentException('Test'));

    $this->assertNotEquals('', $buf->getBuffer());
  }
}