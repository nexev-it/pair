# Upgrade from Pair v1 to Pair v2

This document describes the changes you need to make to your application code to upgrade from Pair 1.x to Pair 2.0.

### Installation

To install Pair 2.0, you can use Composer. Run the following commands to install the latest version of Pair from the branch 2.0.0-alpha:

```bash
composer config minimum-stability dev
composer require viames/pair:dev-2.0.0-alpha
```

### Configuration

The expected configuration file has been changed from `config.php` to `.env`, as usual in the root of the project. The configuration file is in the `.env` format, which is a simple key-value pair format that will be typized by Pair for integers, floats, and booleans.

 Quotes are not required for values, but they are allowed. The configuration file is loaded automatically by the Pair framework, so you don't need to load it manually.

The configuration file is used to store sensitive information, such as database credentials, and other configuration options that should not be stored in the code. The configuration file is not included in the repository, so you need to create it manually.

## Run the upgrade script

To help you upgrade your code to Pair 2.0, we have created a script that will automatically update your code to the new version. To run the script, open the command line terminal, go to your application root folder and execute the following command:

```bash
cd vendor/viames/pair
composer run upgrade-to-v2
```

The script will automatically update your code to the new version. The script will make the necessary changes to your code to make it compatible with Pair 2.0.

## List of changes

Some classes and methods have been moved or renamed in Pair 2.0. Apply renaming as shown below to update your code.

#### Namespace changes
```php
<?php

/**
 * The old code valid for Pair v1.9 is commented, rename for Pair v2.0 is below.
 */

// Core classes
use Pair\Core\Application;      	// renamed from Pair\Application
use Pair\Core\Controller;       	// renamed from Pair\Controller
use Pair\Core\Model;            	// renamed from Pair\Model
use Pair\Core\Router;           	// renamed from Pair\Router
use Pair\Core\View;             	// renamed from Pair\View

// Helpers classes
use Pair\Helpers\Plugin;        	// renamed from Pair\Plugin
use Pair\Helpers\PluginInterface;	// renamed from Pair\PluginInterface
use Pair\Helpers\Post; 	        	// renamed from Pair\Input
use Pair\Helpers\Options;       	// renamed from Pair\Options
use Pair\Helpers\Schedule;      	// renamed from Pair\Schedule
use Pair\Helpers\Translator;    	// renamed from Pair\Translator
use Pair\Helpers\Upload;        	// renamed from Pair\Upload
use Pair\Helpers\Utilities;     	// renamed from Pair\Utilities

// Html classes
use Pair\Html\BootstrapMenu;    	// renamed from Pair\BootstrapMenu
use Pair\Html\Breadcrumb;       	// renamed from Pair\Breadcrumb
use Pair\Html\Form;             	// renamed from Pair\Form
use Pair\Html\Menu;             	// renamed from Pair\Menu
use Pair\Html\Pagination;       	// renamed from Pair\Pagination
use Pair\Html\Widget;           	// renamed from Pair\Widget

// Models classes
use Pair\Models\Acl;               // renamed from Pair\Acl
use Pair\Models\Audit;             // renamed from Pair\Audit
use Pair\Models\Country;           // renamed from Pair\Country
use Pair\Models\ErrorLog;          // renamed from Pair\ErrorLog
use Pair\Models\Group;             // renamed from Pair\Group
use Pair\Models\Language;          // renamed from Pair\Language
use Pair\Models\Locale;            // renamed from Pair\Locale
use Pair\Models\Module;            // renamed from Pair\Module
use Pair\Models\Oauth2Client;		// renamed from Pair\Oauth\Oauth2Client
use Pair\Models\Oauth2Token;       // renamed from Pair\Oauth\Oauth2Token
use Pair\Models\Rule;			// renamed from Pair\Rule
use Pair\Models\Session;           // renamed from Pair\Session
use Pair\Models\Template;          // renamed from Pair\Template
use Pair\Models\Token;             // renamed from Pair\Token
use Pair\Models\User;              // renamed from Pair\User
use Pair\Models\UserRemember;      // renamed from Pair\UserRemember

// Orm classes
use Pair\Orm\ActiveRecord;      	// renamed from Pair\ActiveRecord
use Pair\Orm\Database;          	// renamed from Pair\Database

// Services classes
use Pair\Services\AmazonS3;      	// renamed from Pair\AmazonS3
use Pair\Services\Report;        	// renamed from Pair\Report
```

#### Database

Costants that defined the return type of the `load()` method have been moved as constants inside the `Database` class. Update your code accordingly.
```php
<?php

Database::OBJECT_LIST;	// renamed from PAIR_DB_OBJECT_LIST
Database::OBJECT;        // renamed from PAIR_DB_OBJECT
Database::RESULT_LIST;   // renamed from PAIR_DB_RESULT_LIST
Database::RESULT;        // renamed from PAIR_DB_RESULT
Database::COUNT;         // renamed from PAIR_DB_COUNT
Database::DICTIONARY;    // renamed from PAIR_DB_DICTIONARY
Database::COLLECTION;    // renamed from PAIR_DB_COLLECTION
```

#### Menu widget changes

```php
<?php

// Menu widget
$menu = new BootstrapMenu();
$menu->item();           // renamed from $menu->addItem()
$menu->separator();      // renamed from $menu->addSeparator()
$menu->title();          // renamed from $menu->addTitle()
$menu->multiItem();      // faster creation of a list of menu sub-items
$menu->addMulti();       // removed
$menu->getItemObject();  // removed
```

#### User class

```php
<?php

User::current();         // renamed from User::getCurrent()
```

#### View class

```php
<?php

$view->sortable();       // renamed from $view->printSortableColumn()
```

#### JS utilities changes

```php
<?php

// Toast notifications
Application::toast();                               // renamed from Application::enqueueMessage()
Application::toastError();                          // renamed from Application::enqueueError()
Application::makeToastNotificationsPersistent();    // renamed from Application::makeQueuedMessagesPersistent()

// JS methods that return JSON to the client
Utilities::pairJsonMessage(); // renamed from Utilities::printJsonMessage()
Utilities::pairJsonError();   // renamed from Utilities::printJsonError()
Utilities::pairJsonData();    // renamed from Utilities::printJsonData()
Utilities::getJsMessage();    // removed
Utilities::printJsMessage();  // removed
```

### Input class renamed to Post

Input class has been renamed to Post. Update your code accordingly.

```php
<?php

Post::get();		// renamed from Input::get()
Post::int();		// renamed from Input::getInt()
Post::bool();		// renamed from Input::getBool()
Post::date();		// renamed from Input::getDate()
Post::datetime();	// renamed from Input::getDatetime()
Post::trim();		// renamed from Input::getTrim()
Post::sent();		// renamed from Input::isSent()
Post::submitted();	// renamed from Input::formPostSubmitted()
```

### Form

Methods `setListByAssociativeArray()` and `setListByObjectArray()` of the `FormControlSelect` class have been replaced by `options()`.

The `options()` method populates select control options using a `Pair\Collection` or an object array. Each object must have properties for value and text. If property text includes a couple of round parenthesys, will invoke a function without parameters. It’s a chainable method.

```php
<?php

$form = new Form();

$form->select('controlName')    // renamed from $form->addSelect()
     ->options($collection)     // renamed from $form->setListByObjectArray()
     ->value()                  // renamed from $form->setValue()
     ->multiple();              // renamed from $form->setMultiple()
     ->empty();                 // renamed from $form->prependEmpty()
```

#### Methods for creating form controls

Molti metodi per la creazione dei FormControl sono stati rinominati o estesi per essere più specifici del tipo di controllo che creano. Si prega di rinominare i metodi come indicato di seguito.

```php
<?php

$form = new Form();

$form->text('controlName')         // renamed from $form->addInput()
     ->readonly();			     // renamed from $form->setReadonly()
     ->disabled();			     // renamed from $form->setDisabled()
     ->required();			     // renamed from $form->setRequired()
     ->placeholder();              // renamed from $form->setPlaceholder()
     ->label('CONTROL_LABEL');     // renamed from $form->setLabel()

$form->accept();                   // renamed from $form->setAccept()
$form->button('controlName');      // renamed from $form->addButton()
$form->classForControls();		// renamed from $form->addControlClass()
$form->control();       		     // renamed from $form->getControl()
$form->controls();                 // renamed from $form->getAllControls()
$form->dateFormat();               // renamed from $form->setDateFormat()
$form->datetimeFormat();           // renamed from $form->setDatetimeFormat()
$form->grouped();                  // renamed from $form->setGroupedList()
$form->allReadonly();              // renamed from $form->setAllReadonly()
$form->textarea('controlName');	// renamed from $form->addTextarea()
$form->values($activeRecordObj);   // renamed from $form->setValuesByObject()
```

New methods for creating form controls have been added to the `Form` class. These methods are more specific to the type of control they create. Update your application code accordingly to use these new methods because `FormControl` class no longer has `setType()` method.

```php
<?php

$form->address();	// changed from $form->addInput()->setType('address')
$form->checkbox();	// changed from $form->addInput()->setType('bool')
$form->color();	// changed from $form->addInput()->setType('color')
$form->date();		// changed from $form->addInput()->setType('date')
$form->datetime();	// changed from $form->addInput()->setType('datetime')
$form->email();	// changed from $form->addInput()->setType('email')
$form->file();		// changed from $form->addInput()->setType('file')
$form->hidden();	// changed from $form->addInput()->setType('hidden')
$form->image();	// changed from $form->addInput()->setType('image')
$form->number();	// changed from $form->addInput()->setType('number')
$form->password();	// changed from $form->addInput()->setType('password')
$form->tel();		// changed from $form->addInput()->setType('tel')
$form->url();		// changed from $form->addInput()->setType('url')
```

#### Labels

The behavior of the `Form::printLabel()` method has changed; this method now also generates the `<label>` tag around the label text itself. Update your application code accordingly to remove the `<label>` tag from the HTML, to avoid having a duplicate tag.

The CSS class of the label can be specified individually, for each label, with `FormControl::labelClass()` method or massively, with the `Form::classForLabels('form-label')` method.
See the example below.

```php
<?php

$form = new Form();

$form->text('serialNumber')			// return a FormControl subclass
     ->label('Serial number')			// set the label text and return the FormControl object
     ->labelClass('my-label-class');    // set the class for the label of this FormControl

// or apply to all labels in the Form as follows

$form->classForLabels('form-label');	// set the same class for all labels in all HTML controls in the Form
```

Example of the old code and the new code in template file:

```php
<?php

// old code
?><label class="col-md-3 col-form-label"><?php $this->form->printLabel('serialNumber') ?></label><?php

// new code
?><div class="col-md-3"><?php $this->form->printLabel('serialNumber') ?></div><?php

```

#### Logger

The new Logger class respects the `PSR-3` (Logger Interface of PHP Standard Recommendation) log levels. The default level for error stored without indications is `DEBUG`. The levels, from most critical to least serious, are:

1. EMERGENCY
2. ALERT
3. CRITICAL
4. ERROR
5. WARNING
6. NOTICE
7. INFO
8. DEBUG

The Logger class now takes care of logging status information when errors occur or when explicitly requested by application code. Note the changes in its behavior:

```php
<?php

Logger::error($msg, Logger::ERROR);  // changed from ErrorLog::keepSnapshot($msg)
```
Via `.env` configuration file, or via code calls, you can set up automatic notifications to one or more `Telegram` recipients, for instant notifications, or e-mail (supported `SMTP`, `sendmail`, `AmazonSES`) for asynchronous notifications.