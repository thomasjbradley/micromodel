# MicroModel

A really basic ORM for working with Silex and Doctrine DBAL;
supports single tables without relationships.

***

## Example table

Here’s a table we’ll use for the rest of the code samples.

	`planets`

	| id       | name    | orbital_period | last_updated |
	| (PK, AI) | (text)  | (number)       | (text)       |
	------------------------------------------------------
	| 1        | Mercury | 87.97          | 1982-10-28   |
	| 2        | Venus   | 224.70         | 1980-05-21   |
	| 3        | Earth   | 365.25         | 1981-06-04   |

## How to use

1. Install with Composer.

	```js
	{
		"require": {
			"thomasjbradley/micromodel": "1.0.*"
		}
	}
	```

2. Create a PHP class in your Silex application that extends MicroModel.
	**The class must be named identically to the table.**
	Capitalization doesn’t matter, the class name/table name will be converted to lowercase.

	```php
	<?php

	use Symfony\Component\Validator\Constraints as Assert;

	class Planets extends MicroModel
	{
		public function registerFields ()
		{
			// Register all the table's fields
		}
	}
	```

3. Then make a new instance of your model, passing the Silex\Application.

	```php
	<?php
	$app = new Silex\Application();
	$planets = new Planets($app);
	$planetsList = $planets->all();
	```

## Field registration

When registering fields,
the options array inherits everything from [Symfony\Form](http://symfony.com/doc/current/book/forms.html) options arrays.

**Always register the primary key field first.**

```php
<?php

public function registerFields ()
{
	// The primary key MUST always come first
	$this->register('id', 'integer');

	$this->register('name', 'text', array(
		'constraints' => array(new Assert\NotBlank())
		, 'set' => function ($val) {
			return filter_var($val, FILTER_SANITIZE_STRING);
		}
	));

	$this->register('orbital_period', 'number', array(
		'constraints' => array(new Assert\Number())
		, 'precision' => 2
		, 'set' => function ($val) {
			return filter_var($val, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
		}
	));

	$this->register('last_updated', 'date', array(
		'constraints' => array(new Assert\NotBlank(), new Assert\Date())
		, 'format' => 'yyyy-MM-dd'
		, 'display' => false
		, 'set' => function ($val) {
			if (is_object($val)) return $val;
			return new DateTime($val);
		}
	));
}
```

### Field registration extra options

MicroModel adds a few extra options to the array.

- `set` (function) — An optional setter function.
	Allows for data type conversion and sanitization.
- `display` (boolean) — Optional; whether the field should be shown in forms or not.

## Methods

### ☛ __construct( Silex\Application *$app* [, mixed *$pkValue* = *null* ] )

Set up the model and optionally read a single item immediately by specifying `$pkValue`.

- `$app` — the Silex\Application object.
- `$pkValue` — the value for the individual item’s primary key.

```php
<?php
$planets = new Planets($app, 1);
echo $planets->name; // Mercury
```

### ☛ register( *$name* [, string *$type* = 'text' [, array *$options* = *array()* ]] )

Register a new field on the model to match a field in the table. *Usually called from within the `registerFields()` method.* [Refer to field registration](#field-registration).

- `$name` — The name of the field, spelled identically to the table’s field name.
- `$type` — One of the [Symfony\Form](http://symfony.com/doc/current/book/forms.html) field types. They match very well to the [Doctrine\DBAL](http://www.doctrine-project.org/projects/dbal.html) field types.
- `$options` — Any of the Symfony\Form field options. Refer to [field registration extra options](#field-registration-extra-options) for more options.

**@return** — `$this`

### ☛ all( [ string|array *$order* = *null* ] )

Get all the results from the table, optionally sorting them.
*Will return an array of the model objects.*

- `$order` — the field names & direction for the order clause.

**@return** — an array of objects, each object is an instance of your model.

```php
<?php
$planets = new Planets($app);
$planets->all();
$planets->all('name ASC');
$planets->all(array('name ASC', 'orbital_period DESC'));
```

### ☛ create()

Save the current object, using the property values, into the database, aka `INSERT`.
After insertion, the primary key field is populated with `lastInsertId`.

**@return** — `$this`

```php
<?php
$planets = new Planets($app);
$planets->name = 'Jupiter';
$planets->orbital_period = 4332.59;
$planets->last_updated = new DateTime();
$planets->create();
```

### ☛ read( mixed *$pkValue* )

Read a single entry from the table, converting all the fields to properties of the object.

- `$pkValue` — the value for the individual item’s primary key.

**@return** — `$this`

```php
<?php
$planets = new Planets($app);
$pluto = $planets->read(1);
echo $pluto->name; // Mercury
```

### ☛ update()

Update the current object, using the property values, in the table, aka `UPDATE`.
Uses the field marked as primary key for the `WHERE` clause.

**@return** — `$this`

```php
<?php
$planets = new Planets($app, 2);
$planets->last_updated = new DateTime();
$planets->update();
```

### ☛ delete()

Delete the current object from the table, aka `DELETE`.
Uses the field marked as primary key for the `WHERE` clause.

**@return** — `$this`

```php
<?php
$planets = new Planets($app, 3);
$planets->delete();
```

### ☛ getForm()

Return a Symfony\Form object for the model.
All constraints and options from the field registeration are used.

**@return** — Symfony\Form

```php
<?php
$planets = new Planets($app, 1);
$form = $planets->getForm();
// $form->bindRequest($request);
// $form->isValid();
// $form->createForm();
```

### ☛ isValid()

Validates the information in the object against the field constraints.

**@return** — `true` if the form is valid; a collection of Symfony\Form error messages if invalid.

```php
<?php
$planets = new Planets($app);
$planets->name = 'Saturn';
$planets->orbital_period = 10759.22;
$planets->last_updated = new DateTime();
$planets->isValid(); // true
```

***

## License

MicroModel is licensed under the [BSD 3-Clause license](https://github.com/thomasjbradley/micromodel/blob/master/BSD-3-CLAUSE-LICENSE.txt).
