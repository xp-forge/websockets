<?php namespace websocket;

use lang\ElementNotFoundException;
use util\CompositeProperties;
use util\FilesystemPropertySource;
use util\Objects;
use util\PropertySource;

/**
 * Environment wraps profile, configuration, arguments and logging and
 * provides accessors for them.
 *
 * @test  xp://websocket.unittest.EnvironmentTest
 */
class Environment {
  private $profile, $arguments, $logging;
  private $sources= [];

  /**
   * Creates a new environment
   *
   * @param  string $profile
   * @param  (string|util.PropertySource)[] $config
   * @param  string[] $arguments
   * @param  string|string[]|web.Logging $logging Defaults to logging to console
   */
  public function __construct($profile, $config= [], $arguments= [], $logging= '-') {
    $this->profile= $profile;
    foreach ($config as $source) {
      if ($source instanceof PropertySource) {
        $this->sources[]= $source;
      } else {
        $this->sources[]= new FilesystemPropertySource($source);
      }
    }
    $this->logging= $logging instanceof Logging ? $logging : Logging::of($logging);
    $this->arguments= $arguments;
  }

  /** @return string */
  public function profile() { return $this->profile; }

  /**
   * Gets properties
   *
   * @param  string $name
   * @return util.PropertyAccess
   * @throws lang.ElementNotFoundException
   */
  public function properties($name) {
    $found= [];
    foreach ($this->sources as $source) {
      if ($source->provides($name)) {
        $found[]= $source->fetch($name);
      }
    }

    switch (sizeof($found)) {
      case 1: return $found[0];
      case 0: throw new ElementNotFoundException(sprintf(
        'Cannot find properties "%s" in any of %s',
        $name,
        Objects::stringOf($this->sources)
      ));
      default: return new CompositeProperties($found);
    }
  }

  /** @return string[] */
  public function arguments() { return $this->arguments; }

  /** @return web.Logging */
  public function logging() { return $this->logging; }
}