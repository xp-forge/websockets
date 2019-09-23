<?php namespace xp\ws;

use lang\Throwable;
use util\Bytes;
use util\URI;
use websocket\protocol\Connection;
use websocket\protocol\Opcodes;

class Protocol implements Handler {
  private $listener, $dispatch, $logging;

  public function __construct($listener, $dispatch, $logging) {
    $this->listener= $listener;
    $this->dispatch= $dispatch;
    $this->logging= $logging;
  }

  public function open($events, $socket, $i) {
    sscanf($socket->readLine(), '%s %s HTTP/%s', $method, $uri, $version);
    $headers= [];
    while ($line= $socket->readLine()) {
      sscanf($line, "%[^:]: %[^\r]", $header, $value);
      $headers[$header][]= $value;
    }

    $date= gmdate('D, d M Y H:i:s T');
    $host= isset($headers['Host']) ? $headers['Host'][0] : $socket->localEndpoint()->getAddress();
    $version= isset($headers['Sec-WebSocket-Version']) ? $headers['Sec-WebSocket-Version'][0] : null;
    switch ($version) {
      case '13': 

        // Hash websocket key and well-known GUID
        $key= $headers['Sec-WebSocket-Key'][0];
        $accept= base64_encode(sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
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
        $this->listener->connections[$i]= new Connection($socket, $i, new URI($uri), $headers);
        break;
      
      default:
        $message= 'Unsupported websocket version '.$version;
        $socket->write(sprintf(
          "HTTP/1.1 400 Bad Request\r\n".
          "Date: %s\r\n".
          "Host: %s\r\n".
          "Connection: close\r\n".
          "Content-Type: text/plain\r\n".
          "Content-Length: %d\r\n".
          "\r\n%s",
          $date,
          $host,
          strlen($message),
          $message
        ));
        $socket->close();
        break;
    }
  }

  public function data($events, $socket, $i) {
    $conn= $this->listener->connections[$i];
    foreach ($conn->receive() as $opcode => $payload) {
      try {
        switch ($opcode) {
          case Opcodes::TEXT:
            if (!preg_match('//u', $payload)) {
              $conn->transmit(Opcodes::CLOSE, pack('n', 1007));
              $this->logging->log('TEXT', $conn->uri(), '1007');
              $socket->close();
              break;
            }

            $r= $this->dispatch->dispatch($conn, $payload);
            $this->logging->log('TEXT', $conn->uri(), $r ?: 'OK');
            break;

          case Opcodes::BINARY:
            $r= $this->dispatch->dispatch($conn, new Bytes($payload));
            $this->logging->log('BINARY', $conn->uri(), $r ?: 'OK');
            break;

          case Opcodes::PING:  // Answer a PING frame with a PONG
            $conn->transmit(Opcodes::PONG, $payload);
            $this->logging->log('PING', $conn->uri(), 'PONG');
            break;

          case Opcodes::PONG:  // Do not answer PONGs
            break;

          case Opcodes::CLOSE: // Close connection
            if ('' === $payload) {
              $conn->transmit(Opcodes::CLOSE, pack('n', 1000));
              $this->logging->log('CLOSE', $conn->uri(), 1000);
            } else {
              $result= unpack('ncode/a*message', $payload);
              if (!preg_match('//u', $result['message'])) {
                $conn->transmit(Opcodes::CLOSE, pack('n', 1007));
                $this->logging->log('CLOSE', $conn->uri(), 1007);
              } else if ($result['code'] > 2999 || in_array($result['code'], [1000, 1001, 1002, 1003, 1007, 1008, 1009, 1010, 1011])) {
                $conn->transmit(Opcodes::CLOSE, $payload);
                $this->logging->log('CLOSE', $conn->uri(), $result['code']);
              } else {
                $conn->transmit(Opcodes::CLOSE, pack('n', 1002));
                $this->logging->log('CLOSE', $conn->uri(), 1002);
              }
            }
            $socket->close();
            break;
        }
      } catch (Throwable $t) {
        $this->logging->log(Opcodes::nameOf($opcode), $conn->uri(), 'ERR', $t);
      } catch (\Throwable $t) {  // PHP 7
        $this->logging->log(Opcodes::nameOf($opcode), $conn->uri(), 'ERR', Throwable::wrap($t));
      } catch (\Exception $e) {  // PHP 5
        $this->logging->log(Opcodes::nameOf($opcode), $conn->uri(), 'ERR', Throwable::wrap($e));
      }
    }
  }

  public function close($events, $socket, $i) {
    unset($this->listener->connections[$i]);
  }
}