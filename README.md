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

After installation, you need to switch out the implementation of the SqsQueue class
in your main composer file. Yes this is hacky, but it is a proof of concept at this stage only.

Adding the `Illuminate` namespace and excluding the original from the classmap will ensure that our
SqsQueue class is used whenever it is instantiated throughout the framework.

```json
"autoload": {
    "exclude-from-classmap": [
        "vendor/laravel/framework/src/Illuminate/Queue/SqsQueue.php"
    ],
    "psr-4": {
        "App\\": "app/",
        "Illuminate\\": "vendor/booni3/vapor-queue-manager/src/Illuminate"
    }
}
```

## Usage

- Install the package
- Publish the config and migration
- Run the migration
- Add queues to your config

In your configuration file, ensure to set the default queue up to match your SQS main queue for
the vapor environment. This is usually "your-app-name-environment"

In the limits section, you can set up both time based throttles and funnel based throttles. 

The funnel implementation uses your standard cache driver (Redis/Dynamo).

For now, we are using redis for the `Redis::throttle` implementation. If you do not have redis installed 
then you can disable this by leaving the allow/every keys as null blank.

```php
return [
    'default_queue' => 'vapor-app-staging',

    'limits' => [
        'vapor-app-staging' => ['allow' => 1, 'every' => 60, 'funnel' => 1],
        'virtual-queue-1' => ['allow' => 5, 'every' => 60, 'funnel' => 5],
        'virtual-queue-2' => ['allow' => null, 'every' => null, 'funnel' => 5], // funnel only
    ]
];
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