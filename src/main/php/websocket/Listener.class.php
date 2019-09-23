<?php namespace websocket;

interface Listener {

  /**
   * Listens for messages
   *
   * @param  websocket.protocol.Connection
   * @param  string|util.Bytes
   * @return var
   */
  public function message($connection, $message);
}