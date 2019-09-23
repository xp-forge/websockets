<?php namespace websocket;

use lang\IllegalArgumentException;
use lang\Value;

/**
 * Extension point for websocket listeners
 *
 * @test  xp://websocket.unittest.ListenersTest
 */
abstract class Listeners implements Value {
  protected $environment;
  private $paths= [];
  public $connections= [];

  /**
   * Creates a new listeners instance
   *
   * @param  websocket.Environment $environment
   * @param  xp.ws.Events $events
   */
  public function __construct($environment, $events) {
    $this->environment= $environment;
    foreach ($this->serve($events) ?: [] as $path => $listener) {
      if ('/' === $path) {
        $this->paths['#.#']= self::cast($listener);
      } else {
        $this->paths['#^'.$path.'(/?|/.+)$#']= self::cast($listener);
      }
    }
  }

  /** @return websocket.Environment */
  public function environment() { return $this->environment; }

  /**
   * Finds listener for a given path, returning NULL otherwise
   *
   * @param  string $path
   * @return ?callable
   */
  public function listener($path) {
    foreach ($this->paths as $pattern => $listener) {
      if (preg_match($pattern, $path)) return $listener;
    }
    return null;
  }

  /**
   * Cast listeners
   *
   * @param  websocket.Listener|function(websocket.protocol.Connection, string|util.Bytes): var $arg
   * @return callable
   * @throws lang.IllegalArgumentException
   */
  public static function cast($arg) {
    if ($arg instanceof Listener) {
      return [$arg, 'message'];
    } else if (is_callable($arg)) {
      return $arg;
    } else {
      throw new IllegalArgumentException('Expected either a callable or a websocket.Listener instance, have '.typeof($arg));
    }
  }

  public abstract function serve($events);

  /** @return string */
  public function toString() { return nameof($this).'('.$this->environment->profile().')'; }

  /** @return string */
  public function hashCode() { return spl_object_hash($this); }

  /**
   * Comparison
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value === $this ? 0 : 1;
  }
}