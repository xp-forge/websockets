<?php namespace websocket\protocol;

/**
 * Websocket handshake
 *
 * @see   https://tools.ietf.org/html/rfc6455#section-4 Opening Handshake
 * @test  xp://websocket.unittest.HandshakeTest
 */
class Handshake {
  const GUID= '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

  private $listeners, $logging;

  public function __construct($listeners, $logging) {
    $this->listeners= $listeners;
    $this->logging= $logging;
  }

  public function start($socket, $i) {
    // NOOP
  }

  public function next($socket, $i) {
    sscanf($socket->readLine(), '%s %s HTTP/%s', $method, $path, $version);
    $headers= [];
    while ($line= $socket->readLine()) {
      sscanf($line, "%[^:]: %[^\r]", $header, $value);
      $headers[$header][]= $value;
    }

    $date= gmdate('D, d M Y H:i:s T');
    $host= isset($headers['Host']) ? $headers['Host'][0] : $socket->localEndpoint()->getAddress();
    $version= isset($headers['Sec-WebSocket-Version']) ? $headers['Sec-WebSocket-Version'][0] : -1;
    switch ($version) {
      case 13:
        if (null === ($listener= $this->listeners->listener($path))) {
          $message= 'This service does not listen on '.$path;
          $error= sprintf(
            "HTTP/1.1 400 Bad Request\r\n".
            "Date: %s\r\n".
            "Host: %s\r\n".
            "Connection: close\r\n".
            "Content-Type: text/plain\r\n".
            "Content-Length: %d\r\n".
            "\r\n".
            "%s",
            $date,
            $host,
            strlen($message),
            $message
          );
          break;
        }

        // Hash websocket key and well-known GUID
        $key= $headers['Sec-WebSocket-Key'][0];
        $accept= base64_encode(sha1($key.self::GUID, true));
        $socket->write(sprintf(
          "HTTP/1.1 101 Switching Protocols\r\n".
          "Date: %s\r\n".
          "Host: %s\r\n".
          "Connection: Upgrade\r\n".
          "Upgrade: websocket\r\n".
          "Sec-WebSocket-Accept: %s\r\n".
          "\r\n",
          $date,
          $host,
          $accept
        ));
        $socket->setTimeout(600.0);
        $this->listeners->connections[$i]= new Connection($socket, $i, $listener, $path, $headers);
        $this->logging->log($i, $method.' '.$path, 101);
        return Messages::class;

      case -1:
        $error= sprintf(
          "HTTP/1.1 426 Upgrade Required\r\n".
          "Date: %s\r\n".
          "Host: %s\r\n".
          "Connection: Upgrade\r\n".
          "Upgrade: websocket\r\n".
          "Content-Type: text/plain\r\n".
          "Content-Length: 51\r\n".
          "\r\n".
          "This service requires use of the WebSocket protocol",
          $date,
          $host
        );
        break;

      default:
        $message= 'This service does not support WebSocket version '.$version;
        $error= sprintf(
          "HTTP/1.1 400 Bad Request\r\n".
          "Date: %s\r\n".
          "Host: %s\r\n".
          "Connection: close\r\n".
          "Content-Type: text/plain\r\n".
          "Content-Length: %d\r\n".
          "\r\n".
          "%s",
          $date,
          $host,
          strlen($message),
          $message
        );
        break;
    }

    $socket->write($error);
    $socket->close();
  }

  public function end($socket, $i) {
    // NOOP
  }
}