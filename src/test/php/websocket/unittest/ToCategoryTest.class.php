<?php namespace websocket\unittest;

use lang\IllegalArgumentException;
use unittest\TestCase;
use util\URI;
use util\log\BufferedAppender;
use util\log\LogCategory;
use websocket\logging\ToCategory;

class ToCategoryTest extends TestCase {
  const ID = 42;

  #[@test]
  public function can_create() {
    new ToCategory(new LogCategory());
  }

  #[@test]
  public function log() {
    $buf= new BufferedAppender();
    (new ToCategory((new LogCategory())->withAppender($buf)))->log(self::ID, 'TEXT', '+OK');

    $this->assertNotEquals('', $buf->getBuffer());
  }

  #[@test]
  public function log_with_error() {
    $buf= new BufferedAppender();
    (new ToCategory((new LogCategory())->withAppender($buf)))->log(self::ID, 'TEXT', new IllegalArgumentException('Test'));

    $this->assertNotEquals('', $buf->getBuffer());
  }
}