# http message

Forked from zend-diactoros

Master:
[![Build status][Master image]][Master]
[![Coverage Status][Master coverage image]][Master coverage]

> Diactoros (pronunciation: `/dɪʌktɒrɒs/`): an epithet for Hermes, meaning literally, "the messenger."

This package supercedes and replaces [phly/http](https://github.com/phly/http).

`zend-diactoros` is a PHP package containing implementations of the [accepted PSR-7 HTTP message interfaces](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md), as well as a "server" implementation similar to [node's http.Server](http://nodejs.org/api/http.html).

* File issues at https://github.com/zendframework/zend-diactoros/issues
* Issue patches to https://github.com/zendframework/zend-diactoros/pulls

## Documentation

Documentation is [in the doc tree](doc/), and can be compiled using [bookdown](http://bookdown.io):

```console
$ bookdown doc/bookdown.json
$ php -S 0.0.0.0:8080 -t doc/html/ # then browse to http://localhost:8080/
```

> ### Bookdown
>
> You can install bookdown globally using `composer global require bookdown/bookdown`. If you do
> this, make sure that `$HOME/.composer/vendor/bin` is on your `$PATH`.

  [Master]: https://travis-ci.org/amouhzi/http-message
  [Master image]: https://secure.travis-ci.org/amouhzi/http-message.svg?branch=master
  [Master coverage image]: https://img.shields.io/coveralls/amouhzi/http-message/master.svg
  [Master coverage]: https://coveralls.io/r/amouhzi/http-message?branch=master
