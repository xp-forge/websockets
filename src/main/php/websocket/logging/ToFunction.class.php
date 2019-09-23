<?php namespace websocket\logging;

class ToFunction extends Sink {
  private $function;

  /** @param callable $function */
  public function __construct($function) {
    $this->function= cast($function, 'function(int, string, var): void');
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
    $this->function->__invoke($client, $opcode, $result);
  }
}