![Stackwatch](docs/assets/img/stackwatch-logo.png?raw=true)

# Stackwatch

[![Author](http://img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](https://phpc.social/@nyamsprodd)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/stackwatch/workflows/build/badge.svg)](https://github.com/bakame-php/stackwatch/actions?query=workflow%3A%22build%22)
[![Latest Version](https://img.shields.io/github/release/bakame-php/stackwatch.svg?style=flat-square)](https://github.com/bakame-php/stackwatch/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/bakame/stackwatch.svg?style=flat-square)](https://packagist.org/packages/bakame/stackwatch)
[![Sponsor development of this project](https://img.shields.io/badge/sponsor%20this%20package-%E2%9D%A4-ff69b4.svg?style=flat-square)](https://github.com/sponsors/nyamsprod)

**Stackwatch** is a lightweight profiler for PHP 8.1+.  It helps you measure performance with precision—without
unnecessary complexity. 

**Stackwatch**  bridges the gap between basic timers and heavy profiling tools like [PHPBench](https://phpbench.readthedocs.io/en/latest/), [Xdebug](https://xdebug.org/) or [Blackfire](https://www.blackfire.io/).
It is perfect for:

- Isolated performance testing
- Annotated profiling of large codebases
- Lightweight integration into dev workflows

> Zero-dependency core. Optional CLI with familiar Symfony Console integration.

## Installation

~~~
composer require bakame/stackwatch
~~~

You need:

- **PHP >= 8.1** but the latest stable version of PHP is recommended
- the `psr/log` package or any package implementing the PHP-FIG log contract

To use the CLI command you will also need:

- `symfony/console` and `symfony/process`


## Documentation

Full documentation can be found at https://bakame-php.github.io/stackwatch/

## Testing

The library has:

- a [PHPUnit](https://phpunit.de) test suite.
- a coding style compliance test suite using [PHP CS Fixer](https://cs.symfony.com/).
- a code analysis compliance test suite using [PHPStan](https://github.com/phpstan/phpstan).

To run the tests, run the following command from the project folder.

```bash
composer test
```

## Contributing

Contributions are welcome and will be fully credited. Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CONDUCT](.github/CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email nyamsprod@gmail.com instead of using the issue tracker.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [ignace nyamagana butera](https://github.com/nyamsprod)
- [All Contributors](https://github.com/bakame-php/aide/graphs/contributors)
