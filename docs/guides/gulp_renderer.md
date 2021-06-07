# GLOT Renderer in Node JS

## Using the built-in PHP server

Use the [PHP Server Manager](https://github.com/oscarotero/php-server-manager) package to run the Renderer.

```js
const PHPServer = require('php-server-manager');

const server = new PHPServer({
    port: 8000,
    directives: {
        directory: 'www',
        display_errors: 0,
        expose_php: 0
    }
});

server.run();
```

## Use with gulp

Create a [gulp](https://gulpjs.com/) task (e.g. `render`) and then use the static function PHPServer::start() to create and run a PHPServer.

```js
const PHPServer = require('php-server-manager');

gulp.task('render', () =>
    PHPServer.start({
        directory: 'www',
        script: 'www/index.php'
    })
);
```