<?php namespace websocket;

use lang\{Closeable, Throwable};
use peer\{Socket, CryptoSocket, ProtocolException};
use util\Bytes;
use websocket\protocol\{Connection, Handshake, Opcodes};

/**
 * WebSocket implementation
 *
 * @test  websocket.unittest.WebSocketTest
 */
class WebSocket implements Closeable {
  private $socket, $path;
  private $conn= null;
  private $listener= null;
  private $random= 'random_bytes';

  /**
   * Creates a new instance
   *
   * @param  peer.Socket|string $endpoint, e.g. "wss://example.com"
   * @param  string $path
   */
  public function __construct($endpoint, $path= '/') {
    if ($endpoint instanceof Socket) {
      $this->socket= $endpoint;
      $this->path= $path;
    } else {
      $url= parse_url($endpoint);
      if ('wss' === $url['scheme']) {
        $this->socket= new CryptoSocket($url['host'], $url['port'] ?? 443);
        $this->socket->cryptoImpl= STREAM_CRYPTO_METHOD_ANY_CLIENT;
      } else {
        $this->socket= new Socket($url['host'], $url['port'] ?? 80);
      }
      $this->path= $url['path'] ?? $path;
      isset($url['query']) && $this->path.= '?'.$url['query'];
    }
  }

  /** @return peer.Socket */
  public function socket() { return $this->socket; }

  /** @return string */
  public function path() { return $this->path; }

  /** @return bool */
  public function connected() { return null !== $this->conn; }

  /** @param function(int): string */
  public function random($function) {
    $this->random= $function;
  }

  /**
   * Attach listener
   *
   * @param  websocket.Listener $listener
   * @return self
   */
  public function listening(Listener $listener) {
    $this->listener= $listener;
    return $this;
  }

  /**
   * Connects to websocket endpoint and performs handshake
   *
   * @see    https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Sec-WebSocket-Accept
   * @param  [:string|string[]] $headers
   * @throws peer.ProtocolException
   * @return void
   */
  public function connect($headers= []) {
    if ($this->conn) return;

    $key= base64_encode(($this->random)(16));
    $headers+= ['Host' => $this->socket->host];
    $this->socket->isConnected() || $this->socket->connect();
    $this->socket->write(
      "GET {$this->path} HTTP/1.1\r\n".
      "Upgrade: websocket\r\n".
      "Sec-WebSocket-Key: {$key}\r\n".
      "Sec-WebSocket-Version: 13\r\n".
      "Connection: Upgrade\r\n"
    );
    foreach ($headers as $name => $values) {
      foreach ((array)$values as $value) {
        $this->socket->write("{$name}: {$value}\r\n");
      }
    }
    $this->socket->write("\r\n");

    sscanf($this->socket->readLine(), "HTTP/%s %d %[^\r]", $version, $status, $message);
    $headers= [];
    while ($line= $this->socket->readLine()) {
      sscanf($line, "%[^:]: %[^\r]", $header, $value);
      $headers[strtolower($header)][]= $value;
    }

    if (101 !== $status) {
      $body= ($length= $headers['content-length'][0] ?? 0) ? $this->socket->readBinary($length) : '';
      $this->socket->close();
      throw new ProtocolException('Unexpected response '.$status.' '.$message.($body ? ': '.$body : ''));
    }

    $accept= $headers['sec-websocket-accept'][0] ?? '';
    $expect= base64_encode(sha1($key.Handshake::GUID, true));
    if ($accept !== $expect) {
      $this->socket->close();
      throw new ProtocolException('Accept key mismatch, have '.$accept.', expect '.$expect);
    }

    $this->socket->setTimeout(600.0);
    $this->conn= new Connection(
      $this->socket,
      (int)$this->socket->getHandle(),
      $this->listener,
      $this->path,
      $headers
    );
    $this->conn->open();
  }

  /**
   * Sends a ping
   *
   * @param  string $payload
   * @return void
   * @throws peer.ProtocolException
   */
  public function ping($payload= '') {
    if (!$this->conn) throw new ProtocolException('Not connected');

    $this->conn->message(Opcodes::PING, $payload, ($this->random)(4));
  }

  /**
   * Sends a message
   *
   * @param  util.Bytes|string $message
   * @return void
   * @throws peer.ProtocolException
   */
  public function send($message) {
    if (!$this->conn) throw new ProtocolException('Not connected');

    if ($message instanceof Bytes) {
      $this->conn->message(Opcodes::BINARY, $message, ($this->random)(4));
    } else {
      $this->conn->message(Opcodes::TEXT, $message, ($this->random)(4));
    }
  }

  /**
   * Receive message, handling PING and CLOSE
   *
   * @param  ?int|float $timeout
   * @return ?string|util.Bytes
   * @throws peer.ProtocolException
   */
  public function receive($timeout= null) {
    if (!$this->conn) throw new ProtocolException('Not connected');

    if (null !== $timeout && !$this->socket->canRead($timeout)) return null;

    $result= null;
    foreach ($this->conn->receive() as $opcode => $packet) {
      // echo "<<< ", Opcodes::nameOf($opcode), " -> ", addcslashes($packet, "\0..\37!\177..\377"), "\n";
      switch ($opcode) {
        case Opcodes::TEXT:
          $result= $packet;
          $this->conn->on($result);
          break;

        case Opcodes::BINARY:
          $result= new Bytes($packet);
          $this->conn->on($result);
          break;

        case Opcodes::PING:
          $this->conn->message(Opcodes::PONG, $packet, ($this->random)(4));
          break;

        case Opcodes::PONG:  // Do not answer PONGs
          break;

        case Opcodes::CLOSE:
          $close= unpack('ncode/a*reason', $packet);
          $this->conn->close($close['code'], $close['reason']);
          $this->conn= null;
          $this->socket->close();

          // 1000 is a normal close, all others indicate an error
          if (1000 === $close['code']) return null;
          throw new ProtocolException('Connection closed (#'.$close['code'].'): '.$close['reason']);
      }
    }
    return $result;
  }

  /**
   * Closes connection
   *
   * @param  int $code
   * @param  string $reason
   * @return void
   */
  public function close($code= 1000, $reason= '') {
    if (!$this->conn) return;

    try {
      $this->conn->message(Opcodes::CLOSE, pack('na*', $code, $reason), ($this->random)(4));
    } catch (Throwable $ignored) {
      // ...
    }

    $this->conn->close($code, $reason);
    $this->conn= null;
    $this->socket->close();
  }

  /** Destructor - ensures connection is closed */
  public function __destruct() {
    $this->close();
  }
}