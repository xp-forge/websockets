<?php namespace websocket\unittest;

use lang\IllegalArgumentException;
use unittest\TestCase;
use util\URI;
use util\log\BufferedAppender;
use util\log\LogCategory;
use websocket\logging\ToCategory;

class ToCategoryTest extends TestCase {

  #[@test]
  public function can_create() {
    new ToCategory(new LogCategory());
  }

  #[@test]
  public function log() {
    $buf= new BufferedAppender();
    (new ToCategory((new LogCategory())->withAppender($buf)))->log('TEXT', new URI('/ws'), '+OK');

    $this->assertNotEquals('', $buf->getBuffer());
  }

  #[@test]
  public function log_with_error() {
    $buf= new BufferedAppender();
    (new ToCategory((new LogCategory())->withAppender($buf)))->log('TEXT', new URI('/ws'), '-ERR', new IllegalArgumentException('Test'));

    $this->assertNotEquals('', $buf->getBuffer());
  }
}