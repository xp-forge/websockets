<?php namespace websocket;

use websocket\logging\{Sink, ToAllOf};

/**
 * Logging takes care of logging to various outputs.
 *
 * @see  websocket.logging.Sink
 * @test websocket.unittest.LoggingTest
 */
class Logging {
  private $sink;

  /**
   * Create an instance with a given sink
   *
   * @param  ?websocket.logging.Sink $sink
   */
  public function __construct(Sink $sink= null) {
    $this->sink= $sink;
  }

  /** @return ?websocket.logging.Sink */
  public function sink() { return $this->sink; }

  /**
   * Create an instance from a given command line argument
   *
   * @param  string $arg
   * @return self
   */
  public static function of($arg) {
    return new self(Sink::of($arg));
  }

  /**
   * Pipe to a given sink
   *
   * @param  var $sink
   * @return self
   */
  public function pipe($sink) {
    if (null === $sink || $sink instanceof Sink) {
      $this->sink= $sink;
    } else {
      $this->sink= Sink::of($sink);
    }
    return $this;
  }

  /**
   * Tee to a given sink
   *
   * @param  var $sink
   * @return self
   */
  public function tee($sink) {
    if (null === $this->sink) {
      $this->pipe($sink);
    } else {
      $this->sink= new ToAllOf($this->sink, $sink);
    }
    return $this;
  }

  /**
   * Writes a log entry
   *
   * @param  int $client
   * @param  string $opcode
   * @param  var $result
   * @return void
   */
  public function log($client, $opcode, $result) {
    $this->sink && $this->sink->log($client, $opcode, $result);
  }

  /**
   * Returns logging target
   *
   * @return string
   */
  public function target() {
    return $this->sink ? $this->sink->target() : '(no logging)';
  }
}