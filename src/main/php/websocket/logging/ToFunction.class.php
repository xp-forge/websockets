<?php namespace websocket\logging;

class ToFunction extends Sink {
  private $function;

  /** @param callable $function */
  public function __construct($function) {
    $this->function= cast($function, 'function(string, util.URI, string, ?lang.Throwable): void');
  }

  /**
   * Writes a log entry
   *
   * @param  string $kind
   * @param  util.URI $uri
   * @param  string $status
   * @param  ?lang.Throwable $error Optional error
   * @return void
   */
  public function log($kind, $uri, $status, $error= null) {
    $this->function->__invoke($kind, $uri, $status, $error);
  }
}