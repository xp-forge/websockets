WebSockets for the XP Framework
========================================================================

[![Build status on GitHub](https://github.com/xp-forge/websockets/workflows/Tests/badge.svg)](https://github.com/xp-forge/websockets/actions)
[![XP Framework Module](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Requires PHP 7.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-7_0plus.svg)](http://php.net/)
[![Supports PHP 8.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-8_0plus.svg)](http://php.net/)
[![Latest Stable Version](https://poser.pugx.org/xp-forge/websockets/version.svg)](https://packagist.org/packages/xp-forge/websockets)

Example
-------

```php
use websocket\Listeners;

class Imitator extends Listeners {

  public function serve($events) {
    return [
      '/echo' => function($conn, $payload) {
        $conn->send('You said: '.$payload);
      }
    ];
  }
}
```

Run it using:

```bash
$ xp -supervise ws Imitator
@peer.ServerSocket(Resource id #138 -> tcp://0.0.0.0:8081))
Serving Imitator(dev)[] > websocket.logging.ToConsole
# ...
```

On the JavaScript side, open the connection as follows:

```javascript
var ws = new WebSocket('ws://localhost:8081/echo');
ws.onmessage = (event) => console.log(event.data);

ws.send('Hello');  // Will log "You said: Hello" to the console
```

See also
--------

* [WebSocket chat based on Redis queues](https://gist.github.com/thekid/7f11a62e0a57d18588694f058ebcc38a)
