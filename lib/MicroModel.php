<?php
/**
 * MicroModel: a really basic ORM for working with Silex and Doctrine DBAL
 * Supports single instance tables with no relationships
 * @author Thomas J Bradley <hey@thomasjbradley.ca>
 * @link http://github.com/thomasjbradley/micromodel
 * @copyright 2012 Thomas J Bradley
 * @license BSD-3-Clause
 */

abstract class MicroModel implements \ArrayAccess, \Iterator {
	/**
	 * Instance of Silex\Application
	 * @var Silex\Application
	 */
	protected $__app;

	/**
	 * Instance of the Doctrine DBAL Connection
	 * @var Doctrine\DBAL\Connection
	 */
	protected $__db;

	/**
	 * Holds all the table's fields, values, and form constraints
	 * @var array
	 */
	protected $__params = array();

	/**
	 * Sets up DB connection; registers table params; optionally reads single row
	 * @param Silex\Application $app The Silex application with Doctrine DBAL
	 * @param mixed $pkValue The value for the primary key item to read an individual row
	 */
	public function __construct (\Silex\Application $app, $pkValue = null) {
		$this->__app = $app;
		$this->__db = $this->__app['db'];

		$this->registerFields();

		if (!is_null($pkValue))
			$this->read($pkValue);
	}

	/**
	 * Generic getter for all the field params
	 * @param string $param The param's name
	 * @return mixed
	 */
	public function __get ($param) {
		return $this->__params[$param]['value'];
	}

	/**
	 * Generic setter for all the field params
	 * @param string $param The param's name
	 * @param mixed $val The new value for the param
	 */
	public function __set ($param, $val) {
		if (isset($this->__params[$param]['set'])) {
			$this->__params[$param]['value'] = $this->__params[$param]['set']($val);
		} else {
			$this->__params[$param]['value'] = $val;
		}
	}

	/**
	 * ArrayAccess implementation for offsetSet: changes the param's value
	 * @param string $offset The param's name
	 * @param array $value A replacement value for the param
	 * @return void
	 */
	public function offsetSet ($offset, $value) {
		$this->__set($offset, $value);
	}

	/**
	 * ArrayAccess implementation for offsetExists
	 * @param string $offset The param's name
	 * @return bool
	 */
	public function offsetExists ($offset) {
		return isset($this->__params[$offset]);
	}

	/**
	 * ArrayAccess implementation for offsetUnset: empties the param's value
	 * @param string $offset The param's name
	 * @return void
	 */
	public function offsetUnset ($offset) {
		$this->__params[$offset]['value'] = null;
	}

	/**
	 * ArrayAccess implementation for offsetGet: returns the param's value
	 * @param string $offset The param's name
	 * @return mixed
	 */
	public function offsetGet ($offset) {
		return $this->__params[$offset]['value'];
	}

	/**
	 * Iterator implementation of rewind
	 * @return void
	 */
	public function rewind () {
		prev($this->__params);
	}

	/**
	 * Iterator implementation of current
	 * @return array All the param's details
	 */
	public function current () {
		return current($this->__params);
	}

	/**
	 * Iterator implementation of key
	 * @return string The param's name
	 */
	public function key () {
		return key($this->__params);
	}

	/**
	 * Iterator implementation of next
	 * @return void
	 */
	public function next () {
		next($this->__params);
	}

	/**
	 * Iterator implementation of valid
	 * @return bool
	 */
	public function valid () {
		return isset($this->__params[key($this->__params)]);
	}

	/**
	 * Registers a table's field as a param
	 * @param string $param The param's name
	 * @param string $type The param's type, matches Symfony\Form types
	 * @param array $options The param's options, matches Symfony\Form options
	 * @return void
	 */
	public function register ($param, $type = 'text', $options = array()) {
		if (!isset($options['type']))
			$options['type'] = $type;

		if (!isset($options['value']))
			$options['value'] = null;

		$this->__params[$param] = $options;

		return $this;
	}

	/**
	 * Abstract function that must be implemented by the model
	 * Used to register all the table field types
	 * @return void
	 */
	abstract public function registerFields ();

	/**
	 * Returns all the items from the table for this type
	 * @param array $order The SQL ORDER BY clause, e.g. name ASC
	 * @return array Each item mapped to the model class
	 */
	public function all ($order = array()) {
		$sql = sprintf('SELECT * FROM %s', strtolower(get_class($this)));

		if (!empty($order)) {
			if (is_string($order))
				$order = array($order);

			$sql .= sprintf(' ORDER BY %s', implode(',', $order));
		}

		$results = $this->__db->fetchAll($sql);
		$items = array();

		// Hack because PDO::FETCH_CLASS doesn't work reliably
		foreach ($results as $item) {
			$class = get_class($this);
			$obj = new $class($this->__app);

			foreach ($item as $k => $v) {
				$obj->$k = $v;
			}

			$items[] = $obj;
		}

		return $items;
	}

	/**
	 * Creates a new entry in the table
	 * @return $this
	 */
	public function create () {
		reset($this->__params);
		$pk = key($this->__params);
		$values = $this->getValues(false);
		$placeholders = array();

		foreach ($values as $k => $v) {
			$placeholders[] = ':' . $k;
		}

		$sql = sprintf(
			'INSERT INTO %s (%s) VALUES (%s)'
			, strtolower(get_class($this))
			, implode(',', array_keys($values))
			, implode(',', $placeholders)
		);

		$stmt = $this->__db->prepare($sql);

		foreach ($values as $k => $v) {
			$stmt->bindValue($k, $v, $this->__params[$k]['type']);
		}

		$stmt->execute();
		$this->$pk = $this->__db->lastInsertId();

		return $this;
	}

	/**
	 * Gets a single item from the table
	 * @param mixed $pkValue The value for the primary key of this table
	 * @return $this
	 */
	public function read ($pkValue) {
		reset($this->__params);
		$pk = key($this->__params);

		$sql = sprintf(
			'SELECT * FROM %s WHERE %s = :%s'
			, strtolower(get_class($this))
			, $pk
			, $pk
		);

		$stmt = $this->__db->prepare($sql);
		$stmt->bindValue($pk, $pkValue);
		$stmt->execute();

		$results = $stmt->fetch();

		foreach ($this->__params as $k => $v) {
			$this->$k = $results[$k];
		}

		return $this;
	}

	/**
	 * Updates this entry in the table
	 * @return $this
	 */
	public function update () {
		reset($this->__params);
		$pk = key($this->__params);
		$values = $this->getValues(false);
		$updates = array();

		foreach ($values as $k => $v) {
			$updates[] = sprintf('%s = :%s', $k, $k);
		}

		$sql = sprintf(
			'UPDATE %s SET %s WHERE %s = :%s'
			, strtolower(get_class($this))
			, implode(',', $updates)
			, $pk
			, $pk
		);

		$stmt = $this->__db->prepare($sql);

		foreach ($this->__params as $k => $v) {
			$stmt->bindValue($k, $this->__get($k), $v['type']);
		}

		$stmt->execute();

		return $this;
	}

	/**
	 * Deletes this entry in the table
	 * @return $this
	 */
	public function delete () {
		reset($this->__params);
		$pk = key($this->__params);

		$this->__db->delete(
			strtolower(get_class($this))
			, array($pk => $this->__get($pk))
		);

		return $this;
	}

	/**
	 * Gets a Symfony\Form object based on registered params
	 * @return Silex\Form
	 */
	public function getForm () {
		$builder = $this->__app['form.factory']->createBuilder('form', $this);

		foreach (array_slice($this->__params, 1, null, true) as $k => $v) {
			if (isset($v['display']) && $v['display'] == false)
				continue;

			$options = $v;
			unset($options['type']);
			unset($options['value']);
			unset($options['set']);
			unset($options['display']);

			$builder->add($k, $v['type'], $options);
		}

		$form = $builder->getForm();

		return $form;
	}

	/**
	 * Confirms that the current model object is valid or not
	 * @return boolean|array True if valid; Error list if invalid
	 */
	public function isValid () {
		$errors = array();
		$validator = $this->__app['validator'];

		foreach ($this->getValues(true) as $k => $v) {
			if (!isset($this->__params[$k]['constraints']))
				continue;

			foreach ($this->__params[$k]['constraints'] as $constraint) {
				$possibleError = $validator->validateValue($v, $constraint);

				if (count($possibleError) != 0) {
					$errors[$k] = array();
					$errors[$k][get_class($constraint)] = $possibleError;
				}
			}
		}

		return (empty($errors)) ? true : $errors;
	}

	/**
	 * Gets all the values for this item and puts them in an array
	 * @param boolean $withPk Whether the primary key item should be included or not
	 * @return array
	 */
	public function getValues ($withPk = true) {
		if (!$withPk) {
			$values = array_slice($this->__params, 1, null, true);
		} else {
			$values = $this->__params;
		}

		foreach ($values as $k => $v) {
			$values[$k] = $this->__get($k);
		}

		return $values;
	}
}
