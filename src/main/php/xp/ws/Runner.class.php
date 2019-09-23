<?php namespace xp\ws;

use lang\ClassLoader;
use peer\ServerSocket;
use peer\Socket;
use util\cmd\Console;
use websocket\Dispatch;
use websocket\Environment;
use websocket\Listeners;

class Runner {

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
    if (empty($args)) {
      Console::writeLine('Usage: xp ws [-c {file.ini|dir}] [-a {host}[:{port}] [-p {profile}] {package.Listener}');
      return 2;
    }

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
    $listener= $class->newInstance($environment);
    $dispatch= new Dispatch($listener->serve($events));

    $server= self::server($address);
    $events->add($server, function($events, $socket, $i) use($listener, $dispatch, $logging) {
      $events->add($socket->accept(), new Protocol($listener, $dispatch, $logging));
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