<?php namespace xp\ws;

class Data implements Handler {
  private $func;

  public function __construct($func) {
    $this->func= $func;
  }

  public function open($events, $socket, $i) {
    // NOOP
  }

  public function data($events, $socket, $i) {
    $this->func->__invoke($events, $socket, $i);
  }

  public function close($events, $socket, $i) {
    // NOOP
  }
}