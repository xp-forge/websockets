<?php namespace xp\ws;

use websocket\protocol\Handshake;
use websocket\protocol\Messages;

class Protocol implements Handler {
  private $flow;

  public function __construct($listener, $logging) {
    $this->flow= [
      Handshake::class => new Handshake($listener, $logging),
      Messages::class  => new Messages($listener, $logging)
    ];
  }

  public function open($events, $socket, $i) {
    $this->flow[$i]= $this->flow[Handshake::class];
  }

  public function data($events, $socket, $i) {
    if ($next= $this->flow[$i]->next($socket, $i)) {
      $this->flow[$i]= $this->flow[$next];
    }
  }

  public function close($events, $socket, $i) {
    $this->flow[$i]->end($socket, $i);
    unset($this->flow[$i]);
  }
}