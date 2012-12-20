<?php
App::uses('Object', 'Core');
App::uses('PaymentProcessorException', 'Payments.Error');
App::uses('PaymentApiException', 'Payments.Error');
App::uses('PaymentApiLog', 'Payments.Log');
App::uses('PaymentStatus', 'Payments.Payment');

/**
 * BasePaymentProcessor
 *
 * @author Florian Krämer
 * @copyright 2012 Florian Krämer
 * @license MIT
 */
abstract class BasePaymentProcessor extends Object {

/**
 * Configuration settings for this processor
 *
 * @var array
 */
	public $config = array();

/**
 * Callback Url
 *
 * @var string callback url
 */
	public $callbackUrl = '/';

/**
 * Return Url
 *
 * @var string callback url
 */
	public $returnUrl = '/';

/**
 * Cancel Url
 *
 * @var string callback url
 */
	public $cancelUrl = '/';

/**
 * Finishing page url to display a thank you page or something like that
 *
 * @var string callback url
 */
	public $finishUrl = '/';

/**
 * CakeRequest object instance
 * 
 * @var CakeRequest
 */
	protected $_request;

/**
 * CakeResponse object instance
 *
 * @var CakeResponse
 */
	protected $_response;

/**
 * Internal Payment API Version
 *
 * Can be used for checks to keep a processor compatible for different versions
 *
 * @var string
 */
	protected $_apiVersion = '1.0';

/**
 * Values to be used by the API implementation
 *
 * Structure of the array is:
 * MethodName/VariableName/OptionsArray
 *
 * @var array
 */
	protected $_fields = array(
		'pay' => array(
			'amount' => array(
				'required' => true,
				'type' => array('integer', 'float')
			),
		),
	);

/**
 * Constructor
 *
 * @param array $options
 * @return BasePaymentProcessor
 * @throws PaymentProcessorException
 */
	public function __construct(array $options = array()) {
		$this->config($options);

		if (!$this->_initialize($options)) {
			throw new PaymentProcessorException(__('Failed to initialize %s!', get_class($this)));
		}
	}

/**
 * Empties the fields
 *
 * @return void
 */
	public function flushFields() {
		$this->_fields = array();
	}

/**
 * Sets data for API calls
 *
 * @param string $field
 * @param mixed $value
 * @return void
 */
	public function set(string $field, $value = null) {
		if (is_array($field)) {
			$this->_fields = array_merge($this->_fields, $field);
			return;
		}

		$this->_fields[$field] = $value;
	}

/**
 * Validates if all (required) values are set for an API call
 *
 * You really should validate if all values are set before you do anything in
 * one of your methods to avoid the need to do a lot of manual checks on the
 * set data and to ensure that your API call is going to get all required values
 *
 * @param string $action
 * @throws PaymentProcessorException
 * @return boolean
 */
	public function validateFields(string $action) {
		if (isset($this->_fields[$action])) {
			foreach($this->_fields[$action] as $field => $options) {
				if (!is_array($options['type'])) {
					throw new PaymentProcessorException(__('No data type(s) defined for value %s!', $field));
				}

				if (isset($options['required']) && $options['required'] === true) {
					if (!isset($this->_fields[$field])) {
						throw new PaymentProcessorException(__('Required value %s is not set!', $field));
					}
				}

				if (isset($options['type'])) {
					if (is_string($options['type'])) {
						$field['type'] = array($options['type']);
					}

					foreach ($options['type'] as $type) {
						if (!$this->validateType($type, $this->_fields[$field])) {
							throw new PaymentProcessorException(__('Invalid data type for value %s!', $field));
						}
					}
				}
			}
		}

		return true;
	}

/**
 * Validates values against data types
 *
 * @param string $type
 * @param mixed $value
 * @return bool
 */
	public function validateType($type, $value) {
		switch ($type) :
			case 'string':
				return is_string($value);
			case 'integer':
				return is_int($value);
			case 'float':
				return is_float($value);
			case 'array':
				return is_array($value);
			case 'object':
				return is_object($value);
		endswitch;

		return false;
	}

/**
 * Callback to avoid overloading the constructor if you need to inject app or processor specific changes
 *
 * @param array $options
 * @return void
 */
	protected function _initialize(array $options) {
		if (isset($options['CakeRequest'])) {
			$this->_request = $options['request'];
		}

		if (isset($options['CakeResponse'])) {
			$this->_response = $options['response'];
		}

		return true;
	}

/**
 * Sets configuration data
 *
 * @param array $config
 * @param boolean $merge
 * @return void
 */
	public function config(array $config = array(), $merge = false) {
		if (empty($config)) {
			$config = (array) Configure::read(get_class($this));
		}

		if ($merge === true) {
			$this->config = array_merge($this->config, $config);
		} else {
			$this->config = $config;
		}
	}

/**
 * Redirect
 *
 * @param string $url Url to redirect to
 */
	public function redirect($url) {
		header('Location: ' . $url);
		exit();
	}

/**
 * Log
 *
 * @param string $message
 * @param string $type
 * @return bool|void
 */
	public function log($message, $type = null) {
		if (empty($type)) {
			$type = 'payments_' . Inflector::underscore(__CLASS__);
		}

		parent::log($message, $type);
	}

/**
 * Check of the processor supports a certain interface
 *
 * @param string $interfaceName
 * @return boolean
 */
	public function supports(string $interfaceName) {
		return in_array($interfaceName . 'Interface', class_implements($this));
	}

/**
 *
 */
	abstract public function pay();

/**
 *
 */
	abstract public function callback();

/**
 *
 */
	abstract public function refund();

/**
 *
 */
	abstract public function cancel();

}