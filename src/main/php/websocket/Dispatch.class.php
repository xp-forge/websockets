<?php namespace websocket;

/**
 * Dispatches websocket messages to a given listener
 *
 * @test  xp://websocket.unittest.DispatchTest
 */
class Dispatch {
  private $paths= [];

  /**
   * Creates a dispatch instance
   *
   * @param  [:websocket.Listener|function(websocket.protocol.Connection, string|util.Bytes): var] $dispatch
   * @throws lang.IllegalArgumentException
   */
  public function __construct(array $dispatch) {
    $this->paths= [];
    foreach ($dispatch as $path => $listener) {
      if ('/' === $path) {
        $this->paths['#.#']= Listeners::cast($listener);
      } else {
        $this->paths['#^'.$path.'(/?|/.+)$#']= Listeners::cast($listener);
      }
    }
  }

  /**
   * Performs dispatch, returns whatever the listeners return
   *
   * @param  websocket.protocol.Connection $conn
   * @param  string|util.Bytes $payload
   * @return var
   */
  public function dispatch($conn, $payload) {
    $path= $conn->uri()->path();
    foreach ($this->paths as $pattern => $listener) {
      if (preg_match($pattern, $path)) return $listener($conn, $payload);
    }
    return null;
  }
}