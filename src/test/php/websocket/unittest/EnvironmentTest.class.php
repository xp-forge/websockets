<?php namespace websocket\unittest;

use io\{File, FileUtil, Folder};
use lang\{ElementNotFoundException, Environment as System};
use unittest\{Expect, Test, Values};
use util\{CompositeProperties, Properties, RegisteredPropertySource};
use websocket\Environment;

class EnvironmentTest extends \unittest\TestCase {

  #[Test]
  public function can_create() {
    new Environment('dev', []);
  }

  #[Test]
  public function profile() {
    $this->assertEquals('dev', (new Environment('dev', []))->profile());
  }

  #[Test, Expect(ElementNotFoundException::class)]
  public function non_existant_properties() {
    (new Environment('dev', []))->properties('inject');
  }

  #[Test]
  public function properties() {
    $prop= new Properties('inject.ini');
    $environment= new Environment('dev', [new RegisteredPropertySource('inject', $prop)]);
    $this->assertEquals($prop, $environment->properties('inject'));
  }

  #[Test]
  public function composite_properties() {
    $prop= [new Properties('inject.ini'), new Properties('global/inject.ini')];
    $environment= new Environment('dev', [new RegisteredPropertySource('inject', $prop[0]), new RegisteredPropertySource('inject', $prop[1])]);

    $composite= $environment->properties('inject');
    $this->assertInstanceOf(CompositeProperties::class, $composite);
    $this->assertEquals(2, $composite->length());
  }

  #[Test]
  public function properties_from_directory() {
    $dir= new Folder(System::tempDir(), $this->name);
    $dir->create();

    try {
      $prop= new File($dir, 'inject.ini');
      FileUtil::setContents($prop, "[test]\nresult=success\n");
      $environment= new Environment('dev', [$dir->getURI()]);
      $this->assertEquals('success', $environment->properties('inject')->readString('test', 'result'));
    } finally {
      $dir->unlink();
    }
  }

  #[Test, Values([[[]], [['test', 'value']]])]
  public function arguments($arguments) {
    $this->assertEquals($arguments, (new Environment('dev', [], $arguments))->arguments());
  }
}