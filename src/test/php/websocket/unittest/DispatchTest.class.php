<?php namespace websocket\unittest;

use lang\IllegalArgumentException;
use unittest\TestCase;
use util\URI;
use websocket\Dispatch;
use websocket\Listener;
use websocket\protocol\Connection;

class DispatchTest extends TestCase {

  #[@test]
  public function can_create() {
    new Dispatch([]);
  }

  #[@test, @expect(IllegalArgumentException::class)]
  public function cannot_create_with_non_listener_in_map() {
    new Dispatch(['/' => null]);
  }

  #[@test, @values([
  #  ['http://localhost/test', [['/test' => 'Message']]],
  #  ['http://localhost/test/', [['/test' => 'Message']]],
  #  ['http://localhost/test/chat', [['/test/chat' => 'Message']]],
  #  ['http://localhost/testing', [['/**' => 'Message']]],
  #  ['http://localhost/prod', [['/**' => 'Message']]],
  #  ['http://localhost/listen', [['/listen' => 'Message']]],
  #])]
  public function dispatch($uri, $expected) {
    $invoked= [];
    $listeners= new Dispatch([
      '/listen' => newinstance(Listener::class, [], [
        'message' => function($conn, $payload) use(&$invoked) {
          $invoked[]= [rtrim($conn->uri()->path(), '/') => $payload];
        }
      ]),
      '/test'   => function($conn, $payload) use(&$invoked) {
        $invoked[]= [rtrim($conn->uri()->path(), '/') => $payload];
      },
      '/'       => function($conn, $payload) use(&$invoked) {
        $invoked[]= ['/**' => $payload];
      }
    ]);
    $listeners->dispatch(new Connection(new Channel(), 0, new URI($uri)), 'Message');

    $this->assertEquals($expected, $invoked);
  }

  #[@test, @values([
  #  ['http://localhost/test', [['/test' => 'Message']]],
  #  ['http://localhost/prod', []],
  #])]
  public function dispatch_without_catch_all($uri, $expected) {
    $invoked= [];
    $listeners= new Dispatch([
      '/test' => function($conn, $payload) use(&$invoked) {
        $invoked[]= [rtrim($conn->uri()->path(), '/') => $payload];
      }
    ]);
    $listeners->dispatch(new Connection(new Channel(), 0, new URI($uri)), 'Message');

    $this->assertEquals($expected, $invoked);
  }
}