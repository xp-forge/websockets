<?php namespace websocket;

use lang\Closeable;
use peer\{Socket, CryptoSocket, ProtocolException};
use websocket\protocol\{Connection, Handshake, Opcodes};

class WebSocket implements Closeable {
  private $socket, $path, $origin;
  private $listener= null;

  public function __construct($arg, $origin= 'localhost') {
    if ($arg instanceof Socket) {
      $this->socket= $arg;
      $this->path= '/';
    } else {
      $url= parse_url($arg);
      if ('wss' === $url['scheme']) {
        $this->socket= new CryptoSocket($url['host'], $url['port'] ?? 443);
        $this->socket->cryptoImpl= STREAM_CRYPTO_METHOD_ANY_CLIENT;
      } else {
        $this->socket= new Socket($url['host'], $url['port'] ?? 80);
      }
      $this->path= $url['path'] ?? '/';
    }
    $this->origin= $origin;
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
    if ($this->socket->isConnected()) return;

    $key= base64_encode(random_bytes(16));
    $headers+= ['Host' => $this->socket->host, 'Origin' => $this->origin];
    $this->socket->connect();
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
    if (101 !== $status) {
      $this->socket->close();
      throw new ProtocolException('Unexpected response '.$status.' '.$message);
    }

    $headers= [];
    while ($line= $this->socket->readLine()) {
      sscanf($line, "%[^:]: %[^\r]", $header, $value);
      $headers[$header][]= $value;
    }

    $accept= $headers['Sec-Websocket-Accept'][0] ?? '';
    $expect= base64_encode(sha1($key.Handshake::GUID, true));
    if ($accept !== $expect) {
      $this->socket->close();
      throw new ProtocolException('Accept key mismatch, have '.$accept.', expect '.$expect);
    }

    $this->socket->setTimeout(600.0);
    $this->conn= new Connection(
      $this->socket,
      (int)$this->socket->getHandle(),
      $this->listener ?? new class() extends Listener {
        public function open($connection) { }
        public function message($connection, $message) { }
        public function close($connection) { }
      },
      $this->path,
      $headers
    );
    $this->conn->open();
  }

  public function send($arg) {
    if (!$this->socket->isConnected()) throw new ProtocolException('Not connected');

    $this->conn->send($arg);
  }

  public function receive($timeout= null) {
    if (!$this->socket->isConnected()) throw new ProtocolException('Not connected');

    if (null !== $timeout && !$this->socket->canRead($timeout)) return;
    foreach ($this->conn->receive() as $opcode => $message) {
      switch ($opcode) {
        case Opcodes::BINARY: $this->conn->on(new Bytes($message)); break;
        case Opcodes::TEXT: $this->conn->on($message); break;
      }
      yield $opcode => $message;
    }
  }

  public function close() {
    if (!$this->socket->isConnected()) return;

    $this->conn->close();
    $this->socket->close();
  }
}