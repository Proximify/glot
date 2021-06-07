# Generate the source code documentation

Get the PHPDoc [phar file](http://phpdoc.org/phpDocumentor.phar) and copy it to `/usr/local/bin/phpdoc.phar` (or anywhere else in the system's PATH). Then run

```zsh
phpdoc.phar -d ./src -t ./docs/api
```

to generate the HTML guides under `docs/api`.
