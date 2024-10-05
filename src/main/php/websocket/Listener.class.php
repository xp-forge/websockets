<?php namespace websocket;

abstract class Listener {

  /**
   * Opens connection
   *
   * @param  websocket.protocol.Connection $connection
   * @return void
   */
  public function open($connection) { /* NOOP */ }

  /**
   * Listens for messages
   *
   * @param  websocket.protocol.Connection $connection
   * @param  string|util.Bytes $message
   * @return var
   */
  public abstract function message($connection, $message);

  /**
   * Closes connection
   *
   * @param  websocket.protocol.Connection $connection
   * @param  int $code
   * @param  string $reason
   * @return void
   */
  public function close($connection, $code, $reason) { /* NOOP */ }
}