<?php namespace websocket\unittest;

use io\File;
use io\FileUtil;
use io\Folder;
use lang\ElementNotFoundException;
use lang\Environment as System;
use util\CompositeProperties;
use util\Properties;
use util\RegisteredPropertySource;
use websocket\Environment;

class EnvironmentTest extends \unittest\TestCase {

  #[@test]
  public function can_create() {
    new Environment('dev', []);
  }

  #[@test]
  public function profile() {
    $this->assertEquals('dev', (new Environment('dev', []))->profile());
  }

  #[@test, @expect(ElementNotFoundException::class)]
  public function non_existant_properties() {
    (new Environment('dev', []))->properties('inject');
  }

  #[@test]
  public function properties() {
    $prop= new Properties('inject.ini');
    $environment= new Environment('dev', [new RegisteredPropertySource('inject', $prop)]);
    $this->assertEquals($prop, $environment->properties('inject'));
  }

  #[@test]
  public function composite_properties() {
    $prop= [new Properties('inject.ini'), new Properties('global/inject.ini')];
    $environment= new Environment('dev', [new RegisteredPropertySource('inject', $prop[0]), new RegisteredPropertySource('inject', $prop[1])]);
    $this->assertEquals(new CompositeProperties($prop), $environment->properties('inject'));
  }

  #[@test]
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

  #[@test, @values([[[]], [['test', 'value']]])]
  public function arguments($arguments) {
    $this->assertEquals($arguments, (new Environment('dev', [], $arguments))->arguments());
  }
}