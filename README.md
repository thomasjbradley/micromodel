# MicroModel

A really basic ORM-like form and table mapper, for working with Silex, Symfony Forms, and Doctrine DBAL;
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
			"thomasjbradley/micromodel": "<2.0.0"
		}
	}
	```

2. Create a PHP class in your Silex application that extends MicroModel.
	**The class must be named identically to the table.**
	Capitalization doesn’t matter, the class name/table name will be converted to lowercase.

	```php
	<?php

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

use Symfony\Component\Validator\Constraints as Assert;

class Planets extends MicroModel
{
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
}
```

### Field registration extra options

MicroModel adds a few extra options to the array.

- `set` (function) — An optional setter function.
	Allows for data type conversion and sanitization.
- `display` (boolean) — Optional; whether the field should be shown in forms or not.

## Methods

### ☛ __construct( Silex\Application *$app* [, mixed *$clauses* = null ] )

Set up the model and optionally read a single item immediately by specifying `$clauses`.

- `$app` — the Silex\Application object.
- `$clauses` — passed directly to `read()`. See `read()` for more details.

```php
<?php
$planets = new Planets($app, 1);
echo $planets->name; // Mercury
```

### ☛ register( *$name* [, string *$type* = 'text' [, array *$options* = array() ]] )

Register a new field on the model to match a field in the table. *Usually called from within the `registerFields()` method.* [Refer to field registration](#field-registration).

- `$name` — The name of the field, spelled identically to the table’s field name.
- `$type` — One of the [Symfony\Form](http://symfony.com/doc/current/book/forms.html) field types. They match very well to the [Doctrine\DBAL](http://www.doctrine-project.org/projects/dbal.html) field types.
- `$options` — Any of the Symfony\Form field options. Refer to [field registration extra options](#field-registration-extra-options) for more options.

**@return** — `$this`

### ☛ all( [ string|array *$order* = null [, array *$where* = array() ]] )

Get a bunch of results from the table, optionally sorting them.
Without any arguments `all()` will return every entry in the database.
*Will return an array of the model objects.*

- `$order` — the field names & direction for the order clause.
- `$where` — arrays of WHERE clause conditions, following this format: `array('field', 'comparison', 'value')`.

**@return** — an array of objects, each object is an instance of your model.

```php
<?php
$planets = new Planets($app);
$planetsList = $planets->all();

foreach ($planetsList as $planet) {
	echo $planet->name;
}

// Since each item in the array is your model object, you could do this
$planetsList[0]->name = 'Neptune';
$planetsList[0]->update();

// Using the $order argument
$planets->all('name ASC');
$planets->all(array('name ASC', 'orbital_period DESC'));

// Using the $where argument
$planets->all(null, array(
	array('orbital_period', '>', 200)
	, array('name', 'LIKE', '%e%')
));

// Using $order and $where
$planets->all('name ASC', array(
	array('orbital_period', '>', 200)
));
```

### ☛ create()

Save the current object into the table, using the property values; aka `INSERT`.
After insertion, the primary key field is populated with `lastInsertId`.
**The data is not validated before creation is attempted.**

**@return** — `$this`

```php
<?php
$planets = new Planets($app);
$planets->name = 'Jupiter';
$planets->orbital_period = 4332.59;
$planets->last_updated = new DateTime();
$planets->create();
echo $planets->id; // 4
```

### ☛ read( mixed *$clauses* )

Read a single entry from the table, converting all the fields to properties of the object.

- `$clauses` — conditions for reading a single entry in the table.
	- `scalar` — the value for the individual item’s primary key.
	- `array` — arrays of WHERE clause conditions, following this format: `array('field', 'comparison', 'value')`.

**@return** — `$this`

```php
<?php
$planets = new Planets($app);

// Use the primary key to select an item
// Equates to WHERE id = 1
$planet = $planets->read(1);
echo $planet->name; // Mercury

// Set up a WHERE clause with arrays
// Equates to WHERE name = 'Earth'
$planet = $planets->read(array(
	array('name', '=', 'Earth')
));
echo $planet->name; // Earth
```

### ☛ update()

Update the current object in the table, using the property values; aka `UPDATE`.
Uses the field marked as primary key for the `WHERE` clause.
**The data is not validated before updating is attempted.**

**@return** — `$this`

```php
<?php
$planets = new Planets($app, 2);
$planets->last_updated = new DateTime();
$planets->update();
```

### ☛ delete()

Delete the current object from the table; aka `DELETE`.
Uses the field marked as primary key for the `WHERE` clause.

**@return** — `$this`

```php
<?php
$planets = new Planets($app, 3);
$planets->delete();
```

### ☛ getForm( [ boolean *$csrf_protection* = true ] )

Returns a [Symfony\Form](http://symfony.com/doc/current/book/forms.html) object for the model.
All constraints and options from the field registration are used.

- `$csrf_protection` — flag for enabling/disabling CSRF proection; helpful primarily for APIs

**@return** — Symfony\Form

```php
<?php
$planets = new Planets($app, 1);
$form = $planets->getForm();
// $form->bindRequest($request);
// $form->isValid();
// $form->createForm();

// When writing a JSON API
$form = $planets->getForm(false);
// Symfony's form bind() method expects an array, so force json_decode to use associative arrays
$form->bind(json_decode($request->getContent(), true));

if ($form->isValid()) {
	$movie->create();
	$app->abort(204);
}
```

### ☛ jsonSerialize()

Double duty: returns a simplified version of all the fields’ values in an associative array,
and in PHP/5.4 it is the JsonSerializer implementation.

**@return** — an `array` containing all the fields and their values

```php
<?php
$planets = new Planets($app, 2);

// PHP/5.4
return $app->json($planets);

// PHP/5.3
return $app->json($planets->jsonSerialize());
// Because PHP/5.3 doesn't have the JsonSerializer interface
// Will work equally as well in PHP/5.4
```

### ☛ isValid()

Validates the information in the object against the field constraints.

**@return** — `boolean`

```php
<?php
$planets = new Planets($app);
$planets->name = 'Saturn';
$planets->orbital_period = 10759.22;
$planets->last_updated = new DateTime();
$planets->isValid(); // true
```

### ☛ getErrors()

Return an array of all the validation error messages produced by Symfony\Form.
If there are no error messages the array is empty.

**@return** — `array`

***

## License

MicroModel is licensed under the [BSD 3-Clause license](https://github.com/thomasjbradley/micromodel/blob/master/BSD-3-CLAUSE-LICENSE.txt).
