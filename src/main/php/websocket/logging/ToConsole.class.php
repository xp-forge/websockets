<?php namespace websocket\logging;

use util\Objects;
use util\cmd\Console;

class ToConsole extends Sink {
  private $writer;

  /**
   * Creates a console writer, defaulting to console output.
   *
   * @param  ?io.streams.StringWriter $writer
   */
  public function __construct($writer= null) {
    $this->writer= $writer ?: Console::$out;
  }

  /**
   * Writes a log entry
   *
   * @param  int $client
   * @param  string $opcode
   * @param  var $result
   * @return void
   */
  public function log($client, $opcode, $result) {
    $this->writer->writeLinef(
      "  \e[33m[%s %d %.3fkB]\e[0m #%d %s %s",
      date('Y-m-d H:i:s'),
      getmypid(),
      memory_get_usage() / 1024,
      $client,
      $opcode,
      Objects::stringOf($result)
    );
  }
}