<?php namespace websocket\protocol;

use util\Bytes;
use websocket\Listener;

/**
 * Websocket connection
 *
 * @see   https://tools.ietf.org/html/rfc6455
 * @test  xp://websocket.unittest.ConnectionTest
 */
class Connection {
  const MAXLENGTH= 0x8000000;

  private $socket, $id, $listener, $path, $headers;

  /**
   * Creates a new connection
   *
   * @param  peer.Socket $socket
   * @param  int $id
   * @param  ?websocket.Listener $listener
   * @param  string $path
   * @param  [:var] $headers
   */
  public function __construct($socket, $id, $listener, $path= '/', $headers= []) {
    $this->socket= $socket;
    $this->id= $id;
    $this->listener= $listener;
    $this->path= $path;
    $this->headers= $headers;
  }

  /** @return int */
  public function id() { return $this->id; }

  /** @return websocket.Listener */
  public function listener() { return $this->listener; }

  /** @return string */
  public function path() { return $this->path; }

  /** @return [:var] */
  public function headers() { return $this->headers; }

  /**
   * Opens connection
   * 
   * @return void
   */
  public function open() {
    $this->listener && $this->listener->open($this);
  }

  /**
   * Invokes listener and returns its result
   *
   * @param  string|util.Bytes $payload
   * @return var
   */
  public function on($payload) {
    return $this->listener ? $this->listener->message($this, $payload) : null;
  }

  /**
   * Closes connection
   * 
   * @param  int $code
   * @param  string $reason
   * @return void
   */
  public function close($code= 1000, $reason= '') {
    if ($this->socket->isConnected()) {
      $this->listener && $this->listener->close($this, $code, $reason);
      $this->socket->close();
    }
  }

  /**
   * Reads a certain number of bytes
   *
   * @param  int $length
   * @return string
   */
  private function read($length) {
    $r= '';
    do {
      $r.= $this->socket->readBinary($length - strlen($r));
    } while (strlen($r) < $length && !$this->socket->eof());
    return $r;
  }

  /**
   * Receive messages, handling fragmentation
   *
   * @return iterable
   */
  public function receive() {
    $packets= [
      Opcodes::TEXT    => '',
      Opcodes::BINARY  => '',
      Opcodes::CLOSE   => '',
      Opcodes::PING    => '',
      Opcodes::PONG    => '',
    ];

    $continue= [];
    do {
      $packet= $this->read(2);
      if (strlen($packet) < 2) return;

      $final= $packet[0] & "\x80";
      $opcode= $packet[0] & "\x0f";
      $length= $packet[1] & "\x7f";
      $masked= $packet[1] & "\x80";

      if ("\x00" === $opcode) {
        $opcode= array_pop($continue);
      }

      // Verify opcode, send protocol error if unkown
      if (!isset($packets[$opcode])) {
        yield Opcodes::CLOSE => pack('n', 1002);
        return;
      }

      if ("\x7e" === $length) {
        $read= unpack('n', $this->read(2))[1];
      } else if ("\x7f" === $length) {
        $read= unpack('J', $this->read(8))[1];
      } else {
        $read= ord($length);
      }

      // Verify length
      if ($read > self::MAXLENGTH) {
        yield Opcodes::CLOSE => pack('n', 1003);
        return;
      }

      // Read data
      if ("\x00" === $masked) {
        $packets[$opcode].= $read > 0 ? $this->read($read) : '';
      } else {
        $mask= $this->read(4);
        $data= $read > 0 ? $this->read($read) : '';

        for ($i = 0; $i < strlen($data); $i+= 4) {
          $packets[$opcode].= $mask ^ substr($data, $i, 4);
        }
      }

      if ("\x00" === $final) {
        $continue[]= $opcode;
        continue;
      }

      yield $opcode => $packets[$opcode];
      $packets[$opcode]= '';
    } while ($continue);
  }

  /**
   * Sends an message
   *
   * @param  string $type One of the class constants TEXT | BINARY | CLOSE | PING | PONG
   * @param  string $payload
   * @param  string $mask 4 bytes
   * @return void
   */
  public function message($type, $payload, $mask) {
    $length= strlen($payload);
    $data= '';
    for ($i = 0; $i < $length; $i+= 4) {
      $data.= $mask ^ substr($payload, $i, 4);
    }

    if ($length < 126) {
      $this->socket->write(("\x80" | $type).("\x80" | chr($length)).$mask.$data);
    } else if ($length < 65536) {
      $this->socket->write(("\x80" | $type)."\xfe".pack('n', $length).$mask.$data);
    } else {
      $this->socket->write(("\x80" | $type)."\xff".pack('J', $length).$mask.$data);
    }
  }

  /**
   * Transmits an answer
   *
   * @param  string $type One of the class constants TEXT | BINARY | CLOSE | PING | PONG
   * @param  string $payload
   * @return void
   */
  public function answer($type, $payload) {
    $length= strlen($payload);
    if ($length < 126) {
      $this->socket->write(("\x80" | $type).chr($length).$payload);
    } else if ($length < 65536) {
      $this->socket->write(("\x80" | $type)."\x7e".pack('n', $length).$payload);
    } else {
      $this->socket->write(("\x80" | $type)."\x7f".pack('J', $length).$payload);
    }
  }

  /**
   * Sends an answer
   *
   * @param  util.Bytes|string $arg
   * @return void
   */
  public function send($arg) {
    if ($arg instanceof Bytes) {
      $this->answer(Opcodes::BINARY, $arg);
    } else {
      $this->answer(Opcodes::TEXT, $arg);
    }
  }
}