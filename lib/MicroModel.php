<?php
/**
 * MicroModel: A really basic ORM-like form and table mapper, for working with Silex, Symfony\Forms, and Doctrine\DBAL
 * Supports single tables without relationships
 * @author Thomas J Bradley <hey@thomasjbradley.ca>
 * @link http://github.com/thomasjbradley/micromodel
 * @copyright 2012 Thomas J Bradley
 * @license BSD-3-Clause <https://github.com/thomasjbradley/micromodel/blob/master/BSD-3-CLAUSE-LICENSE.txt>
 */

// Still support PHP/5.3, will be useless in PHP\5.4
if (!interface_exists('JsonSerializable')) {
	interface JsonSerializable {
		function jsonSerialize ();
	}
}

abstract class MicroModel implements \ArrayAccess, \Iterator, \JsonSerializable {
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
	protected $__fields = array();

	/**
	 * Sets up DB connection; registers table fields; optionally reads single row
	 * @param Silex\Application $app The Silex application with Doctrine DBAL
	 * @param mixed $clauses Passed directly to read(); {@see MicroModel::read()}
	 */
	public function __construct (\Silex\Application $app, $clauses = null) {
		$this->__app = $app;

		if (!isset($app['db']) || get_class($app['db']) != 'Doctrine\DBAL\Connection') {
			throw new Exception('The Silex application must have the DoctrineServiceProvider registered.');
		}

		if (!isset($app['validator']) || get_class($app['validator']) != 'Symfony\Component\Validator\Validator') {
			throw new Exception('The Silex application must have the ValidatorServiceProvider registered.');
		}

		if (!isset($app['form.factory']) || get_class($app['form.factory']) != 'Symfony\Component\Form\FormFactory') {
			throw new Exception('The Silex application must have the FormServiceProvider registered.');
		}

		$this->__db = $this->__app['db'];

		$this->registerFields();

		if (!is_null($clauses))
			$this->read($clauses);
	}

	/**
	 * Generic getter for all the fields
	 * @param string $field The field's name
	 * @return mixed
	 */
	public function __get ($field) {
		return $this->__fields[$field]['data'];
	}

	/**
	 * Generic setter for all the field
	 * @param string $field The field's name
	 * @param mixed $val The new value for the field
	 */
	public function __set ($field, $val) {
		if (isset($this->__fields[$field]['set'])) {
			$this->__fields[$field]['data'] = $this->__fields[$field]['set']($val);
		} else {
			$this->__fields[$field]['data'] = $val;
		}
	}

	/**
	 * ArrayAccess implementation for offsetSet: changes the field's value
	 * @param string $offset The field's name
	 * @param array $value A replacement value for the field
	 * @return void
	 */
	public function offsetSet ($offset, $value) {
		$this->__set($offset, $data);
	}

	/**
	 * ArrayAccess implementation for offsetExists
	 * @param string $offset The field's name
	 * @return bool
	 */
	public function offsetExists ($offset) {
		return isset($this->__fields[$offset]);
	}

	/**
	 * ArrayAccess implementation for offsetUnset: empties the field's value
	 * @param string $offset The field's name
	 * @return void
	 */
	public function offsetUnset ($offset) {
		$this->__fields[$offset]['data'] = null;
	}

	/**
	 * ArrayAccess implementation for offsetGet: returns the field's value
	 * @param string $offset The field's name
	 * @return mixed
	 */
	public function offsetGet ($offset) {
		return $this->__fields[$offset]['data'];
	}

	/**
	 * Iterator implementation of rewind
	 * @return void
	 */
	public function rewind () {
		prev($this->__fields);
	}

	/**
	 * Iterator implementation of current
	 * @return array All the field's details
	 */
	public function current () {
		$opts = current($this->__fields);

		return $opts['data'];
	}

	/**
	 * Iterator implementation of key
	 * @return string The field's name
	 */
	public function key () {
		return key($this->__fields);
	}

	/**
	 * Iterator implementation of next
	 * @return void
	 */
	public function next () {
		next($this->__fields);
	}

	/**
	 * Iterator implementation of valid
	 * @return bool
	 */
	public function valid () {
		return isset($this->__fields[key($this->__fields)]);
	}

	/**
	 * Registers a table's field as a field
	 * @param string $field The field's name
	 * @param string $type The field's type, matches Symfony\Form types
	 * @param array $options The field's options, matches Symfony\Form options
	 * @return void
	 */
	public function register ($field, $type = 'text', $options = array()) {
		if (!isset($options['type']))
			$options['type'] = $type;

		if (!isset($options['data']))
			$options['data'] = null;

		$this->__fields[$field] = $options;

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
	 * @param array $order The ORDER BY clause, e.g. name ASC
	 * @param array $where An array of arrays comprising the WHERE clause
	 * @return array Each item mapped to the model class
	 */
	public function all ($order = null, $where = array()) {
		$sql = sprintf('SELECT * FROM %s', $this->getTableName());
		$whereClauses = array();

		if (!empty($where)) {
			foreach ($where as $clause) {
				$whereClauses[] = sprintf('%s %s :%s', $clause[0], $clause[1], $clause[0]);
			}

			$sql .= sprintf(' WHERE %s', implode(' AND ', $whereClauses));
		}

		if (!empty($order)) {
			if (is_string($order))
				$order = array($order);

			$sql .= sprintf(' ORDER BY %s', implode(',', $order));
		}

		$stmt = $this->__db->prepare($sql);

		if (!empty($where)) {
			foreach ($where as $clause) {
				$stmt->bindValue($clause[0], $clause[2]);
			}
		}

		$stmt->execute();
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$items = array();

		// Hack because PDO::FETCH_CLASS doesn't work reliably
		foreach ($results as $item) {
			$class = get_class($this);
			$obj = new $class($this->__app);

			foreach ($item as $k => $v) {
				$obj->__set($k, $v);
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
		reset($this->__fields);
		$pk = key($this->__fields);
		$form = $this->getForm();
		$placeholders = array();

		foreach ($form as $k => $v) {
			$placeholders[$k] = ':' . $k;
		}

		$sql = sprintf(
			'INSERT INTO %s (%s) VALUES (%s)'
			, $this->getTableName()
			, implode(',', array_keys($placeholders))
			, implode(',', $placeholders)
		);

		$stmt = $this->__db->prepare($sql);

		foreach ($form as $k => $v) {
			if (is_object($v->getData()) && get_class($v->getData()) == 'DateTime') {
				$stmt->bindValue($k, $v->getData(), $this->__fields[$k]['type']);
			} else {
				$stmt->bindValue($k, $v->getData());
			}
		}

		$stmt->execute();
		$this->$pk = $this->__db->lastInsertId();

		return $this;
	}

	/**
	 * Gets a single item from the table
	 * @param scalar|array $clauses Scalar: the value for the primary key; Array: arrays comprising the WHERE clause
	 * @return $this
	 */
	public function read ($clauses) {
		$whereClauses = array();

		if (!is_array($clauses)) {
			reset($this->__fields);
			$pk = key($this->__fields);
			$clauses = array(array($pk, '=', $clauses));
		}

		foreach ($clauses as $clause) {
			$whereClauses[] = sprintf('%s %s :%s', $clause[0], $clause[1], $clause[0]);
		}

		$sql = sprintf(
			'SELECT * FROM %s WHERE %s'
			, $this->getTableName()
			, implode(' AND ', $whereClauses)
		);

		$stmt = $this->__db->prepare($sql);

		foreach ($clauses as $clause) {
			$stmt->bindValue($clause[0], $clause[2]);
		}

		$stmt->execute();
		$results = $stmt->fetch(PDO::FETCH_ASSOC);

		foreach ($this->__fields as $k => $v) {
			$this->$k = $results[$k];
		}

		return $this;
	}

	/**
	 * Updates this entry in the table
	 * @return $this
	 */
	public function update () {
		reset($this->__fields);
		$pk = key($this->__fields);
		$form = $this->getForm();
		$updates = array();

		foreach ($form as $k => $v) {
			$updates[] = sprintf('%s = :%s', $k, $k);
		}

		$sql = sprintf(
			'UPDATE %s SET %s WHERE %s = :%s'
			, $this->getTableName()
			, implode(',', $updates)
			, $pk
			, $pk
		);

		$stmt = $this->__db->prepare($sql);
		$stmt->bindValue($pk, $this->__get($pk));

		foreach ($form as $k => $v) {
			if (is_object($v->getData()) && get_class($v->getData()) == 'DateTime') {
				$stmt->bindValue($k, $v->getData(), $this->__fields[$k]['type']);
			} else {
				$stmt->bindValue($k, $v->getData());
			}
		}

		$stmt->execute();

		return $this;
	}

	/**
	 * Deletes this entry in the table
	 * @return $this
	 */
	public function delete () {
		reset($this->__fields);
		$pk = key($this->__fields);

		$this->__db->delete(
			$this->getTableName()
			, array($pk => $this->__get($pk))
		);

		return $this;
	}

	/**
	 * Gets a Symfony\Form object based on registered fields
	 * @param boolean $csrf_protection Whether to use CSRF protection or not
	 * @return Silex\Form
	 */
	public function getForm ($csrf_protection = true) {
		$builder = $this->__app['form.factory']->createBuilder('form', $this, array(
			'csrf_protection' => $csrf_protection
		));

		foreach (array_slice($this->__fields, 1, null, true) as $k => $v) {
			if (isset($v['display']) && $v['display'] == false)
				continue;

			$options = $v;
			unset($options['type']);
			unset($options['set']);
			unset($options['display']);

			$builder->add($k, $v['type'], $options);
		}

		$form = $builder->getForm();

		return $form;
	}

	/**
	 * Confirms that the current model object is valid or not
	 * @return boolean
	 */
	public function isValid () {
		$validator = $this->__app['validator'];

		foreach ($this->getValues(true) as $k => $v) {
			if (!isset($this->__fields[$k]['constraints']))
				continue;

			foreach ($this->__fields[$k]['constraints'] as $constraint) {
				$possibleError = $validator->validateValue($v, $constraint);

				if (count($possibleError) != 0) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Returns a list of all the validation error messages
	 * @return array Error list if invalid; empty array otherwise
	 */
	public function getErrors () {
		$errors = array();
		$validator = $this->__app['validator'];

		foreach ($this->getValues(true) as $k => $v) {
			if (!isset($this->__fields[$k]['constraints']))
				continue;

			foreach ($this->__fields[$k]['constraints'] as $constraint) {
				$possibleError = $validator->validateValue($v, $constraint);

				if (count($possibleError) != 0) {
					$errors[$k] = array();
					$errors[$k][get_class($constraint)] = $possibleError;
				}
			}
		}

		return $errors;
	}

	/**
	 * Converts this item to a JSON object
	 * In PHP/5.3 this method must be called directly before json_encode()
	 *   json_encode($myModel->jsonSerialize())
	 * @return array The simplified fields and values
	 */
	public function jsonSerialize () {
		reset($this->__fields);
		$pk = key($this->__fields);
		$data = array(
			$pk => $this->__get($pk)
		);

		$form = $this->getForm();

		foreach ($form as $k => $v) {
			if (is_object($v->getData()) && get_class($v->getData()) == 'DateTime') {
				$data[$k] = $v->getViewData();
			} else {
				$data[$k] = $v->getData();
			}
		}

		return $data;
	}

	/**
	 * Converts the class's name into the table name
	 * @return string
	 */
	protected function getTableName () {
		$class = explode('\\', get_class($this));

		return strtolower(end($class));
	}

	/**
	 * Gets all the values for this item and puts them in an array
	 * @param boolean $withPk Whether the primary key item should be included or not
	 * @return array
	 */
	protected function getValues ($withPk = true) {
		if (!$withPk) {
			$values = array_slice($this->__fields, 1, null, true);
		} else {
			$values = $this->__fields;
		}

		foreach ($values as $k => $v) {
			$values[$k] = $this->__get($k);
		}

		return $values;
	}
}
