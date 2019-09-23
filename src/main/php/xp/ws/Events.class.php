<?php namespace xp\ws;

use peer\Sockets;

class Events {
  private $running= true;
  private $socket;
  private $sockets= [], $timeout= [], $handlers= [];

  public function __construct() {
    $this->socket= Sockets::$STREAM;
  }

  /** @return bool */
  public function running() { return $this->running; }

  /** @return void */
  public function terminate() { $this->running= false; }

  public function add($socket, $arg) {
    $socket->isConnected() || $socket->connect();
    $i= (int)$socket->getHandle();

    $handler= $arg instanceof Handler ? $arg : new Data($arg);
    $handler->open($this, $socket, $i);
    if (!$socket->isConnected()) return null;

    $this->select[$i]= $socket;
    $this->handlers[$i]= $handler;
    $this->timeout[$i]= $socket->getTimeout();
    return $i;
  }

  public function remove($i) {
    unset($this->select[$i], $this->handlers[$i], $this->timeout[$i]);
  }

  public function await() {
    $read= $this->select;
    $write= $error= [];
    $this->socket->select($read, $write, $error, min($this->timeout));

    // Check data to be read
    foreach ($read as $i => $select) {
      $this->handlers[$i]->data($this, $select, $i);

      if ($select->eof() || !$select->isConnected()) {
        $this->handlers[$i]->close($this, $select, $i);
        unset($this->select[$i], $this->handlers[$i], $this->timeout[$i]);
      }
    }
  }
}
