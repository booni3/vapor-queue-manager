# Vapor Queue Manager

This is only a proof of concept at this stage. 

Vapor allows incredible scale, but in terms of a queue system that is not always what we want. In most cases,
other parts of the system (RDS) will not be able to handle everything that is thrown at it.

Added to this, vapor currently does not have any fine grain control over the queue system. Some issues faced:

- We throw 1000 jobs into the queue with a concurrency of 10 but then our 1001th job will have to wait, even if its the most critical.
- We need jobs to process in order. Unless we setup new environments and FIFO queues, this is not possible.

This solution fixes these issues and while it may not be best practice, it seems to work with some good scale. I have tested it
processing a few hundred thousand jobs in under and hour. Only one process deals with the database queue so deadlocks should not be possible.

What it allows:

- Multiple queues. These can be either real SQS queues as defined in vapor.yml, the default lambda queue or virtual queues.
- Each queue can be limited by throttle (time) or funnel (concurrency). Note: currently throttle requires redis installed. Funnel uses the standard cache driver.

How it works:

- We have copied the database queue driver implementation from Laravel. When you push a job to the queue, it now pushes to your jobs table instead.
- A command (job:push) runs every minute in a while-loop, and picks up eligible jobs from the table to dispatch to SQS.
- As a job finises or fails the throttle/funnel parameters are updated.
- All throttle/funnel values are stored in cache

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
- Add queues to your config file

In your configuration file, ensure to set the default queue up to match your SQS main queue for
the vapor environment. This is usually "your-app-name-environment"

In the limits section, you can set up both time based throttles and funnel based throttles. 

The funnel implementation uses your standard cache driver (Redis/Dynamo).

For now, we are using redis for the `Redis::throttle` implementation. If you do not have redis installed 
then you can disable this by leaving the allow/every keys as null blank.

```php
return [
    'enabled' => 'true',

    'default_queue' => 'vapor-app-staging',

    'limits' => [
        'vapor-app-staging' => ['allow' => 1, 'every' => 60, 'funnel' => 1],
        'virtual-queue-1' => ['allow' => 5, 'every' => 60, 'funnel' => 5],
        'virtual-queue-2' => ['allow' => null, 'every' => null, 'funnel' => 5],
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