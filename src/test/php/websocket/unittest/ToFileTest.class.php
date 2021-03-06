<?php namespace websocket\unittest;

use io\TempFile;
use lang\IllegalArgumentException;
use unittest\{Expect, Test, TestCase};
use util\URI;
use websocket\logging\ToFile;

class ToFileTest extends TestCase {
  private $temp;

  /** @return void */
  public function setUp() {
    $this->temp= new TempFile('sink');
  }

  /** @return void */
  public function tearDown() {
    if ($this->temp->exists()) {
      $this->temp->setPermissions(0600);
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
    $this->assertTrue($this->temp->exists());
  }

  #[Test, Expect(IllegalArgumentException::class)]
  public function raises_error_if_file_cannot_be_written_to() {
    $this->temp->setPermissions(0000);
    new ToFile($this->temp);
  }

  #[Test]
  public function log() {
    (new ToFile($this->temp))->log('TEXT', new URI('/ws'), '+OK');

    $this->assertNotEquals(0, $this->temp->size());
  }

  #[Test]
  public function log_with_error() {
    (new ToFile($this->temp))->log('TEXT', new URI('/ws'), '-ERR', new IllegalArgumentException('Test'));

    $this->assertNotEquals(0, $this->temp->size());
  }
}