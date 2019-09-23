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
  public $connections= [];

  /**
   * Creates a new listeners instance
   *
   * @param  websocket.Environment $environment
   */
  public function __construct($environment) {
    $this->environment= $environment;
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