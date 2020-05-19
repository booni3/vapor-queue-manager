# Very short description of the package

This is only a proof of concept. 

A way to manage queues on Laravel Vapor. Its main features include:

- Throttle queue by time and/or concurrency
- Have multiple queues (SQS named or Virtual) 

## Installation

You can install the package via composer:

```bash
composer require booni3/vapor-queue-manager
```

You also need to exclude the SqsQueue in your main composer file.
This is a quick way to switch out the implementation for this demo.

```json
"autoload": {
    "exclude-from-classmap": [
        "vendor/laravel/framework/src/Illuminate/Queue/SqsQueue.php"
    ]
}
```

## Usage

``` php
// Usage description here
```

### Testing

``` bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email adam@profilestudio.com instead of using the issue tracker.

## Credits

- [Adam Lambert](https://github.com/booni3)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Laravel Package Boilerplate

This package was generated using the [Laravel Package Boilerplate](https://laravelpackageboilerplate.com).