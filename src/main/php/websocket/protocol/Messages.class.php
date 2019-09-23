<?php namespace websocket\protocol;

use lang\Throwable;
use util\Bytes;

class Messages {
  private $listener, $dispatch, $logging;

  public function __construct($listener, $dispatch, $logging) {
    $this->listener= $listener;
    $this->dispatch= $dispatch;
    $this->logging= $logging;
  }

  public function next($socket, $i) {
    $conn= $this->listener->connections[$i];
    foreach ($conn->receive() as $opcode => $payload) {
      try {
        switch ($opcode) {
          case Opcodes::TEXT:
            if (!preg_match('//u', $payload)) {
              $conn->transmit(Opcodes::CLOSE, pack('n', 1007));
              $this->logging->log('TEXT', $conn->uri(), '@#'.$i.':1007');
              $socket->close();
              break;
            }

            $this->dispatch->dispatch($conn, $payload);
            $this->logging->log('TEXT', $conn->uri(), $i);
            break;

          case Opcodes::BINARY:
            $this->dispatch->dispatch($conn, new Bytes($payload));
            $this->logging->log('BINARY', $conn->uri(), $i);
            break;

          case Opcodes::PING:  // Answer a PING frame with a PONG
            $conn->transmit(Opcodes::PONG, $payload);
            $this->logging->log('PING', $conn->uri(), $i);
            break;

          case Opcodes::PONG:  // Do not answer PONGs
            break;

          case Opcodes::CLOSE: // Close connection
            if ('' === $payload) {
              $conn->transmit(Opcodes::CLOSE, pack('n', 1000));
              $this->logging->log('CLOSE(1000)', $conn->uri(), $i);
            } else {
              $result= unpack('ncode/a*message', $payload);
              if (!preg_match('//u', $result['message'])) {
                $conn->transmit(Opcodes::CLOSE, pack('n', 1007));
                $this->logging->log('CLOSE(1007)', $conn->uri(), $i);
              } else if ($result['code'] > 2999 || in_array($result['code'], [1000, 1001, 1002, 1003, 1007, 1008, 1009, 1010, 1011])) {
                $conn->transmit(Opcodes::CLOSE, $payload);
                $this->logging->log('CLOSE('.$result['code'].')', $conn->uri(), $i);
              } else {
                $conn->transmit(Opcodes::CLOSE, pack('n', 1002));
                $this->logging->log('CLOSE(1002)', $conn->uri(), $i);
              }
            }
            $socket->close();
            break;
        }
      } catch (Throwable $t) {
        $this->logging->log(Opcodes::nameOf($opcode), $conn->uri(), $i, $t);
      } catch (\Throwable $t) {  // PHP 7
        $this->logging->log(Opcodes::nameOf($opcode), $conn->uri(), $i, Throwable::wrap($t));
      } catch (\Exception $e) {  // PHP 5
        $this->logging->log(Opcodes::nameOf($opcode), $conn->uri(), $i, Throwable::wrap($e));
      }
    }
  }

  public function end($socket, $i) {
    unset($this->listener->connections[$i]);
  }
}