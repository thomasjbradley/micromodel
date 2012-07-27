# MicroModel

A really basic ORM for working with Silex and Doctrine DBAL;
supports single tables without relationships.

## Example table

Here’s a table we’ll use for the rest of the examples.

	`planets`

	| id | name    | orbital_period | last_updated |
	------------------------------------------------
	| 1  | Mercury | 87.97          | 1982-10-28   |
	| 2  | Venus   | 224.70         | 1980-05-21   |
	| 3  | Earth   | 365.25         | 1981-06-04   |

## How to use

Install with Composer.

```php
{
	"require": {
		"thomasjbradley/micromodel": "1.0.*"
	}
}
```

Create a PHP class in your Silex application that extends MicroModel.
**The class must be named identically to the table.**
Capitalization doesn’t matter, the class name/table name will be converted to lowercase.

	use Symfony\Component\Validator\Constraints as Assert;

	class Planets extends MicroModel
	{
		public function registerFields ()
		{
			// Register all the table's fields
		}
	}

Then make a new instance of your model, passing the Silex\Application.

	$planets = new Planets($app);
	$planetsList = $planets->all();

## Field registration

When registering fields,
the options array inherits everything from [Symfony\Form](http://symfony.com/doc/current/book/forms.html) options arrays.

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
			, 'widget' => 'single_text'
			, 'format' => 'yyyy-MM-dd'
			, 'display' => false
			, 'set' => function ($val) {
				if (is_object($val)) return $val;
				return new DateTime($val);
			}
		));
	}

### Field registration extra options

MicroModel adds a few extra options to the array.

- `set` (function) — A function for handling when the data is set.
	Allows for data type conversion and sanitization.
- `display` (boolean) — Whether the field should be shown in forms or not.

## Methods

- `__construct($app, $pkValue = null)` — the constructor has one dependency: the Silex\Application object.
	You can optionally read a single item immediately by specifying `$pkValue`.

	`$pkValue` (mixed) — the value for the individual item’s primary key.

		$planets = new Planets($app, 1);
		echo $planets->name; // Mercury

- `all($order)` — get all the results from the table, optionally sorting them.

	`$order` (string|array) — the field names & direction for the order clause.

		$planets = new Planets($app);
		$planets->all();
		$planets->all('name ASC');
		$planets->all(array('name ASC', 'discovery_date DESC'));

- `create()` — save the current object into the database, aka `INSERT`.

		$planets = new Planets($app);
		$planets->name = 'Jupiter';
		$planets->orbital_period = 4332.59;
		$planets->last_updated = new DateTime();
		$planets->create();

- `read($pkValue)` — read a single entry from the table.

	$pkValue (mixed) — the value for the individual item’s primary key.

		$planets = new Planets($app);
		$pluto = $planets->read(1);
		echo $pluto->name; // Mercury

- `update()` — update the current object in the table, aka `UPDATE`.

		$planets = new Planets($app, 2);
		$planets->last_updated = new DateTime();
		$planets->update();

- `delete()` — delete the current object from the table, aka `DELETE`.

		$planets = new Planets($app, 3);
		$planets->delete();

- `getForm()` — return a Symfony\Form object for the object.

		$planets = new Planets($app, 1);
		$form = $planets->getForm();
		// $form->bindRequest($request);
		// $form->isValid();
		// $form->createForm();

- `isValid()` — validates the information in the object against the field constraints.

		$planets = new Planets($app);
		$planets->name = 'Saturn';
		$planets->orbital_period = 10759.22;
		$planets->last_updated = new DateTime();
		$planets->isValid(); // true

	The method will return `true` if the form is valid, and a collection of Symfony\Form error messages if invalid.

## License

MicroModel is licensed under the [New BSD license](https://github.com/thomasjbradley/micromodel/blob/master/NEW-BSD-LICENSE.txt).
