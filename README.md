<img src="https://github.com/nexev-it/pair/wiki/files/pair-logo.png" width="240">

[![Latest Stable Version](https://poser.pugx.org/nexev-it/pair/v/stable)](https://packagist.org/packages/nexev-it/pair)
[![Total Downloads](https://poser.pugx.org/nexev-it/pair/downloads)](https://packagist.org/packages/nexev-it/pair)
[![Latest Unstable Version](https://poser.pugx.org/nexev-it/pair/v/unstable)](https://packagist.org/packages/nexev-it/pair)
[![License](https://poser.pugx.org/nexev-it/pair/license)](https://packagist.org/packages/nexev-it/pair)

## Features

Pair is simple and fast, few frills, maybe none. It was written with simplicity in mind, while trying to achieve the most frequent needs of web applications. It implements [Model-View-Controller](https://en.wikipedia.org/wiki/Model-View-Controller) pattern and a search friendly [route logic](https://github.com/nexev-it/pair/wiki/Router) by default.

Everyone knows that you do not need a truck to go shopping. You do not even need the car to go and buy the newspaper at the nearby newsstand. You need the right medium for everything.

If you’re already tired of starting a new web project due to the complexity of the famous frameworks used around, you should take a look at Pair. For a small or medium web project, it fits really well.

#### ActiveRecord

Pair allows the creation of objects related to each respective database table using the [ActiveRecord class](https://github.com/nexev-it/pair/wiki/ActiveRecord). Objects retrieved from the DB are cast in both directions to the required type (int, bool, DateTime, float, csv). See [Automatic properties cast](https://github.com/nexev-it/pair/wiki/ActiveRecord#automatic-properties-cast) page in the wiki.

In addition, each class inherited from ActiveRecord supports many convenient methods including those for caching data that save queries.

The Pair base tables are InnoDB utf-8mb4.

#### Plugins

Pair supports modules and templates as installable plugins, but can easily be extended to other types of custom plugins. The Pair’s Plugin class allows you to create the manifest file, the ZIP package with the contents of the plugin and the installation of the plugin of your Pair’s application.

#### Time zone

The automatic time zone management allows to store the data on UTC and to obtain it already converted according to the connected user’s time zone automatically.

#### Log bar

A nice log bar shows all the details of the loaded objects, the system memory load, the time taken for each step and for the queries, the SQL code of the executed queries and the backtrace of the detected errors. Custom messages can be added for each step of the code.

## Installation

### Composer

```sh
composer require nexev-it/pair
```
After having installed Pair framework you can get singleton object `$app` and the just start MVC. You can check any session before MVC, like in the following example.

```php
use Pair\Application;

// initialize the framework
require 'vendor/autoload.php';

// intialize the Application
$app = Application::getInstance();

// any session
$app->manageSession();

// start controller and then display
$app->startMvc();
```

If you want to test code that is in the master branch, which hasn’t been pushed as a release, you can use master.

```
composer require nexev-it/pair dev-master
```
If you don’t have Composer, you can [download it](https://getcomposer.org/download/).

## Documentation

Please consult the [Wiki](https://github.com/nexev-it/pair/wiki) of this project. Below are its most interesting pages that illustrate some features of Pair.

* [ActiveRecord](https://github.com/nexev-it/pair/wiki/ActiveRecord)
* [Application](https://github.com/nexev-it/pair/wiki/Application)
* [Controller](https://github.com/nexev-it/pair/wiki/Controller)
* [Form](https://github.com/nexev-it/pair/wiki/Form)
* [Router](https://github.com/nexev-it/pair/wiki/Router)
* [index.php](https://github.com/nexev-it/pair/wiki/index)
* [.htaccess](https://github.com/nexev-it/pair/wiki/htaccess)
* [config.php](https://github.com/nexev-it/pair/wiki/Configuration-file)
* [classes](https://github.com/nexev-it/pair/wiki/Classes-folder)

## Requirements

| Software | Recommended | Minimum | Configuration          |
| ---      |    :---:    |  :---:  | ---                    |
| Apache   | 2.4+        | 2.2     | `modules:` mod_rewrite |
| MySQL    | 8.0         | 5.6     | `character_set:` utf8mb4 <br> `collation:` utf8mb4\_unicode_ci <br> `storage_engine:` InnoDB |
| PHP      | 7.3+        | 7.0     | `extensions:` fileinfo, json, pcre, PDO, pdo_mysql, Reflection |

## Examples

The [Pair_example](https://github.com/viames/Pair_example) is a good starting point to build your new web project in a breeze with Pair PHP framework using the installer wizard.

## Contributing

If you would like to contribute to this project, please feel free to submit a pull request.

# License

MIT
