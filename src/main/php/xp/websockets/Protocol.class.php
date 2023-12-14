<?php namespace xp\websockets;

use websocket\protocol\{Handshake, Messages};

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
    $this->flow[$i]->start($socket, $i);
  }

  public function data($events, $socket, $i) {
    if ($next= $this->flow[$i]->next($socket, $i)) {
      $this->flow[$i]= $this->flow[$next];
      $this->flow[$i]->start($socket, $i);
    }
  }

  public function close($events, $socket, $i) {
    $this->flow[$i]->end($socket, $i);
    unset($this->flow[$i]);
  }
}