<?php namespace websocket\protocol;

use Throwable as Any;
use lang\Throwable;
use util\Bytes;

class Messages {
  private $listeners, $logging;

  public function __construct($listeners, $logging) {
    $this->listeners= $listeners;
    $this->logging= $logging;
  }

  public function start($socket, $i) {
    $this->listeners->connections[$i]->open();
  }

  public function next($socket, $i) {
    $conn= $this->listeners->connections[$i];
    foreach ($conn->receive() as $opcode => $payload) {
      try {
        switch ($opcode) {
          case Opcodes::TEXT:
            if (!preg_match('//u', $payload)) {
              $conn->answer(Opcodes::CLOSE, pack('n', 1007));
              $this->logging->log($i, 'TEXT', 1007);
              $socket->close();
              break;
            }

            $r= $conn->on($payload);
            $this->logging->log($i, 'TEXT', $r);
            break;

          case Opcodes::BINARY:
            $r= $conn->on(new Bytes($payload));
            $this->logging->log($i, 'BINARY', $r);
            break;

          case Opcodes::PING:  // Answer a PING frame with a PONG
            $conn->answer(Opcodes::PONG, $payload);
            $this->logging->log($i, 'PING', true);
            break;

          case Opcodes::PONG:  // Do not answer PONGs
            break;

          case Opcodes::CLOSE: // Close connection
            if ('' === $payload) {
              $close= ['code' => 1000, 'reason' => ''];
            } else {
              $close= unpack('ncode/a*reason', $payload);
              if (!preg_match('//u', $close['reason'])) {
                $close= ['code' => 1007, 'reason' => ''];
              } else if ($close['code'] > 2999 || in_array($close['code'], [1000, 1001, 1002, 1003, 1007, 1008, 1009, 1010, 1011])) {
                // Answer with client code and reason
              } else {
                $close= ['code' => 1002, 'reason' => ''];
              }
            }

            $conn->answer(Opcodes::CLOSE, pack('na*', $close['code'], $close['reason']));
            $this->logging->log($i, 'CLOSE', $close['code'].($close['reason'] ? ': '.$close['reason'] : ''));
            $this->listeners->connections[$i]->close($close['code'], $close['reason']);
            break;
        }
      } catch (Throwable $t) {
        $this->logging->log($i, Opcodes::nameOf($opcode), $t);
      } catch (Any $e) {
        $this->logging->log($i, Opcodes::nameOf($opcode), Throwable::wrap($e));
      }
    }
  }

  public function end($socket, $i) {
    $this->listeners->connections[$i]->close();
    unset($this->listeners->connections[$i]);
  }
}