<?php namespace websocket\unittest;

use io\TempFile;
use lang\IllegalArgumentException;
use test\{After, Assert, Before, Expect, Test};
use util\URI;
use websocket\logging\ToFile;

class ToFileTest {
  private $temp;

  #[Before]
  public function createTempFile() {
    $this->temp= new TempFile('sink');
  }

  #[After]
  public function removeTempFile() {
    if ($this->temp->exists()) {
      $this->temp->unlink();
    }
  }

  #[Test]
  public function can_create() {
    new ToFile($this->temp);
  }

  #[Test]
  public function file_created_during_constructor_call() {
    new ToFile($this->temp);
    Assert::true($this->temp->exists());
  }

  #[Test, Expect(IllegalArgumentException::class)]
  public function raises_error_if_file_cannot_be_written_to() {
    $this->temp->setPermissions(0000);
    try {
      new ToFile($this->temp);
    } finally {
      $this->temp->setPermissions(0600);
    }
  }

  #[Test]
  public function log() {
    (new ToFile($this->temp))->log('TEXT', new URI('/ws'), '+OK');

    Assert::notEquals(0, $this->temp->size());
  }

  #[Test]
  public function log_with_error() {
    (new ToFile($this->temp))->log('TEXT', new URI('/ws'), '-ERR', new IllegalArgumentException('Test'));

    Assert::notEquals(0, $this->temp->size());
  }
}