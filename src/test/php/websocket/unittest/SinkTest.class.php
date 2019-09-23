<?php namespace websocket\unittest;

use io\TempFile;
use unittest\TestCase;
use util\log\LogCategory;
use websocket\logging\Sink;
use websocket\logging\ToAllOf;
use websocket\logging\ToCategory;
use websocket\logging\ToConsole;
use websocket\logging\ToFile;
use websocket\logging\ToFunction;

class SinkTest extends TestCase {

  #[@test, @values([[null], [[]]])]
  public function no_logging($arg) {
    $this->assertNull(Sink::of($arg));
  }

  #[@test]
  public function logging_to_console() {
    $this->assertInstanceOf(ToConsole::class, Sink::of('-'));
  }

  #[@test]
  public function logging_to_function() {
    $this->assertInstanceOf(ToFunction::class, Sink::of(function($kind, $uri, $status, $error= null) { }));
  }

  #[@test]
  public function logging_to_file() {
    $t= new TempFile('log');
    try {
      $this->assertInstanceOf(ToFile::class, Sink::of($t));
    } finally {
      $t->unlink();
    }
  }

  #[@test]
  public function logging_to_file_by_name() {
    $t= new TempFile('log');
    try {
      $this->assertInstanceOf(ToFile::class, Sink::of($t->getURI()));
    } finally {
      $t->unlink();
    }
  }

  #[@test]
  public function logging_to_all_of() {
    $t= new TempFile('log');
    try {
      $this->assertInstanceOf(ToAllOf::class, Sink::of(['-', $t]));
    } finally {
      $t->unlink();
    }
  }

  #[@test]
  public function logging_to_all_of_flattened_when_only_one_argument_passed() {
    $this->assertInstanceOf(ToConsole::class, Sink::of(['-']));
  }

  #[@test]
  public function logging_to_category() {
    $this->assertInstanceOf(ToCategory::class, Sink::of(new LogCategory()));
  }
}