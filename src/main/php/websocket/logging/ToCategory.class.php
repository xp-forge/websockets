<?php namespace websocket\logging;

use lang\Throwable;

class ToCategory extends Sink {
  private $cat;

  /** @param util.log.LogCategory $cat */
  public function __construct($cat) {
    $this->cat= $cat;
  }

  /** @return string */
  public function target() { return nameof($this).'('.$this->cat->toString().')'; }

  /**
   * Writes a log entry
   *
   * @param  int $client
   * @param  string $opcode
   * @param  var $result
   * @return void
   */
  public function log($client, $opcode, $result) {
    if ($result instanceof Throwable) {
      $this->cat->warn('#'.$client, $opcode, $result);
    } else {
      $this->cat->info('#'.$client, $opcode, $result);
    }
  }
}