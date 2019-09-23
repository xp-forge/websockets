<?php namespace websocket\logging;

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
   * @param  string $kind
   * @param  util.URI $uri
   * @param  string $status
   * @param  ?lang.Throwable $error Optional error
   * @return void
   */
  public function log($kind, $uri, $status, $error= null) {
    $this->writer->writeLinef(
      "  \e[33m[%s %d %.3fkB]\e[0m %s %s %s %s",
      date('Y-m-d H:i:s'),
      getmypid(),
      memory_get_usage() / 1024,
      $status,
      $kind,
      $uri->path().(($query= $uri->query()) ? '?'.$query : ''),
      $error ? $error->toString() : ''
    );
  }
}