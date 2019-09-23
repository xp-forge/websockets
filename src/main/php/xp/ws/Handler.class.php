<?php namespace xp\ws;

interface Handler {

  public function open($events, $socket, $i);

  public function data($events, $socket, $i);

  public function close($events, $socket, $i);
}