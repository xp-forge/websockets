<?php namespace websocket\unittest;

use io\{File, FileUtil, Folder};
use lang\{ElementNotFoundException, Environment as System};
use unittest\Assert;
use unittest\{Expect, Test, Values};
use util\{CompositeProperties, Properties, RegisteredPropertySource};
use websocket\Environment;

class EnvironmentTest {

  #[Test]
  public function can_create() {
    new Environment('dev', []);
  }

  #[Test]
  public function profile() {
    Assert::equals('dev', (new Environment('dev', []))->profile());
  }

  #[Test, Expect(ElementNotFoundException::class)]
  public function non_existant_properties() {
    (new Environment('dev', []))->properties('inject');
  }

  #[Test]
  public function properties() {
    $prop= new Properties('inject.ini');
    $environment= new Environment('dev', [new RegisteredPropertySource('inject', $prop)]);
    Assert::equals($prop, $environment->properties('inject'));
  }

  #[Test]
  public function composite_properties() {
    $prop= [new Properties('inject.ini'), new Properties('global/inject.ini')];
    $environment= new Environment('dev', [new RegisteredPropertySource('inject', $prop[0]), new RegisteredPropertySource('inject', $prop[1])]);

    $composite= $environment->properties('inject');
    Assert::instance(CompositeProperties::class, $composite);
    Assert::equals(2, $composite->length());
  }

  #[Test]
  public function properties_from_directory() {
    $dir= new Folder(System::tempDir(), 'environment');
    $dir->create();

    try {
      $prop= new File($dir, 'inject.ini');
      FileUtil::setContents($prop, "[test]\nresult=success\n");
      $environment= new Environment('dev', [$dir->getURI()]);
      Assert::equals('success', $environment->properties('inject')->readString('test', 'result'));
    } finally {
      $dir->unlink();
    }
  }

  #[Test, Values([[[]], [['test', 'value']]])]
  public function arguments($arguments) {
    Assert::equals($arguments, (new Environment('dev', [], $arguments))->arguments());
  }
}