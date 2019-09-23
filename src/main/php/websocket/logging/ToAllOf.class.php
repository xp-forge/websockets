<?php namespace websocket\logging;

/**
 * Log sink which logs to all given sinks
 *
 * @test  xp://websocket.unittest.logging.ToAllOfTest
 */
class ToAllOf extends Sink {
  private $sinks= [];

  /**
   * Creates a sink writing to all given other sinks
   *
   * @param  (websocket.logging.Sink|util.log.LogCategory|function(int, string, var): void)... $arg
   */
  public function __construct(... $args) {
    foreach ($args as $arg) {
      if ($arg instanceof self) {
        $this->sinks= array_merge($this->sinks, $arg->sinks);
      } else if ($arg instanceof parent) {
        $this->sinks[]= $arg;
      } else {
        $this->sinks[]= parent::of($arg);
      }
    }
  }

  /** @return websocket.logging.Sink[] */
  public function sinks() { return $this->sinks; }

  /** @return string */
  public function target() {
    $s= '';
    foreach ($this->sinks as $sink) {
      $s.= ' & '.$sink->target();
    }
    return '('.substr($s, 3).')';
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
    foreach ($this->sinks as $sink) {
      $sink->log($client, $opcode, $result);
    }
  }
}