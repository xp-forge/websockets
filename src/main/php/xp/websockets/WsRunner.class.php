<?php namespace xp\websockets;

use lang\ClassLoader;
use peer\{ServerSocket, Socket};
use util\cmd\Console;
use websocket\{Dispatch, Environment, Listeners};
use xp\runtime\Help;

/**
 * WebSockets server
 * =================
 *
 * - Run a websocket listener
 *   ```sh
 *   $ xp ws com.example.web.Chat
 *   ```
 * - Pass arguments
 *   ```sh
 *   $ xp ws com.example.web.Chat redis://localhost
 *   ```
 * The address the server listens to can be supplied via *-a {host}[:{port}]*.
 * The profile can be changed via *-p {profile}* (and can be anything!). One
 * or more configuration sources may be passed via *-c {file.ini|dir}*.
 *
 * The server log is sent to standard output by default. It can be redirected
 * to a file via *-l /path/to/logfile.log*.
 */
class WsRunner {

  private static function server($address, $backlog= 10) {
    $p= strpos($address, ':', '[' === $address[0] ? strpos($address, ']') : 0);
    if (false === $p) {
      $host= $address;
      $port= 8081;
    } else {
      $host= substr($address, 0, $p);
      $port= (int)substr($address, $p + 1);
    }

    $server= new ServerSocket($host, $port);
    $server->listen($backlog);
    return $server;
  }

  /**
   * Entry point
   *
   * @param  string[] $args
   * @return int
   */
  public static function main($args) {
    if (empty($args)) return Help::main([strtr(self::class, '\\', '.')]);

    $events= new Events();

    // Clean shutdown for `xp -supervise`
    if ($signal= getenv('XP_SIGNAL')) {
      $events->add(new Socket('127.0.0.1', $signal), function($events, $socket, $i) {
        Console::writeLine('Terminating...');
        $events->terminate();
      });
    }    

    $config= [];
    $address= '0.0.0.0';
    $profile= 'dev';
    $logging= [];
    while ($arg= array_shift($args)) {
      if ('-c' === $arg) {
        $config[]= array_shift($args);
      } else if ('-a' === $arg) {
        $address= array_shift($args);
      } else if ('-p' === $arg) {
        $profile= array_shift($args);
      } else if ('-l' === $arg) {
        $logging[]= array_shift($args);
      } else if (is_file($arg)) {
        $class= ClassLoader::getDefault()->loadUri($arg);
        break;
      } else {
        $class= ClassLoader::getDefault()->loadClass($arg);
        break;
      }
    }

    if (!$class->isSubclassOf(Listeners::class)) {
      Console::$err->writeLine($class, ' must extend the websocket.Listener class');
      return 1;
    }

    $environment= new Environment($profile, $config, $args, $logging ?: '-');
    $logging= $environment->logging();
    $listener= $class->newInstance($environment, $events);

    $server= self::server($address);
    $events->add($server, function($events, $socket, $i) use($listener, $logging) {
      $events->add($socket->accept(), new Protocol($listener, $logging));
    });

    Console::writeLine("\e[33m@", $server->toString(), ")\e[0m");
    Console::writeLine("\e[1mServing ", $listener, $config, "\e[0m > ", $logging->target());
    Console::writeLine("\e[36m", str_repeat('═', 72), "\e[0m");
    Console::writeLine();

    Console::writeLine("\e[33;1m>\e[0m Server started: \e[35;4mws://localhost:", $server->port, "\e[0m (", date('r'), ')');
    Console::writeLine('  PID ', getmypid(), '; press Ctrl+C to exit');
    Console::writeLine();

    do {
      $events->await();
    } while ($events->running());
    return 0;
  }
}