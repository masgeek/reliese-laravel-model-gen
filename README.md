# Reliese Laravel Model Generator
[![Build Status](https://travis-ci.org/masgeek/laravel-model-gen.svg?branch=master)](https://travis-ci.org/masgeek/laravel-model-gen)
[![Latest Stable Version](https://poser.pugx.org/masgeek/laravel-model-gen/v/stable)](https://packagist.org/packages/masgeek/laravel-model-gen)
[![Total Downloads](https://poser.pugx.org/masgeek/laravel-model-gen/downloads)](https://packagist.org/packages/masgeek/laravel-model-gen)
[![Latest Unstable Version](https://poser.pugx.org/masgeek/laravel-model-gen/v/unstable)](https://packagist.org/packages/masgeek/laravel-model-gen)
[![License](https://poser.pugx.org/masgeek/laravel-model-gen/license)](https://packagist.org/packages/masgeek/laravel-model-gen)

Reliese Laravel Model Generator aims to speed up the development process of Laravel applications by 
providing some convenient code-generation capabilities. 
The tool inspects your database structure, including column names and foreign keys, in order 
to automatically generate Models that have correctly typed properties, along with any relationships to other Models.

## How does it work?

This package expects that you are using Laravel 5.1 or above.
You will need to import the `masgeek/laravel-model-gen` package via composer:

### Configuration

It is recommended that this package should only be used on a local environment for security reasons. You should install it via composer using the --dev option like this:

```shell
composer require masgeek/laravel-model-gen --dev
```

Add the `models.php` configuration file to your `config` directory and clear the config cache:

```shell
php artisan vendor:publish --tag=reliese-models

# Let's refresh our config cache just in case
php artisan config:clear
```

## Models

![Generating models with artisan](https://cdn-images-1.medium.com/max/800/1*hOa2QxORE2zyO_-ZqJ40sA.png "Making artisan code my Eloquent models")

### Usage

Assuming you have already configured your database, you are now all set to go.

- Let's scaffold some of your models from your default connection.

```shell
php artisan code:models
```

- You can scaffold a specific table like this:

```shell
php artisan code:models --table=users
```

- You can also specify the connection:

```shell
php artisan code:models --connection=mysql
```

- If you are using a MySQL database, you can specify which schema you want to scaffold:

```shell
php artisan code:models --schema=shop
```

### Customizing Model Scaffolding

To change the scaffolding behaviour you can make `config/models.php` configuration file
fit your database needs. [Check it out](https://github.com/masgeek/laravel-model-gen/blob/master/config/models.php) ;-)

### Tips

#### 1. Keeping model changes

You may want to generate your models as often as you change your database. In order
not to lose your own model changes, you should set `base_files` to `true` in your `config/models.php`.

When you enable this feature your models will inherit their base configurations from
base models. You should avoid adding code to your base models, since you
will lose all changes when they are generated again.

> Note: You will end up with two models for the same table and you may think it is a horrible idea 
to have two classes for the same thing. However, it is up to you
to decide whether this approach gives value to your project :-)

#### Support

For the time being, this package supports MySQL, PostgreSQL and SQLite databases. Support for other databases are encouraged to be added through pull requests.

#### Custom Schema Mappers

If your application uses a custom or third-party database connection class (for example a PgBouncer connection wrapper, a connection decorator from another package, or any driver not natively recognised by this package), you will see an error like:

```
There is no Schema Mapper registered for [Vendor\Package\CustomConnection] connection.
```

You can fix this by registering a custom mapper in `config/models.php`. Add a top-level `custom_mappers` key that maps the fully-qualified connection class name to the schema mapper that should handle it:

```php
// config/models.php

return [

    // ... existing '*' config ...

    'custom_mappers' => [
        // Map a PgBouncer connection wrapper to the built-in Postgres mapper
        \Vermaysha\PgbouncerLaravelExtension\PostgresPGBouncerExtension::class
            => \Reliese\Meta\Postgres\Schema::class,

        // Any other custom connection → mapper pairs
        // \YourVendor\YourPackage\CustomMySqlConnection::class
        //     => \Reliese\Meta\MySql\Schema::class,
    ],

];
```

Built-in mapper classes you can reuse:

| Mapper | Use for |
|---|---|
| `Reliese\Meta\MySql\Schema` | MySQL / MariaDB compatible connections |
| `Reliese\Meta\Postgres\Schema` | PostgreSQL compatible connections |
| `Reliese\Meta\Sqlite\Schema` | SQLite compatible connections |

You can also implement your own mapper by implementing `Reliese\Meta\Schema` if you need custom introspection logic for an unsupported database driver.
