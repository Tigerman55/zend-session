<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Session;

use Zend\Session\Config\ConfigInterface as Config;
use Zend\Session\ManagerInterface as Manager;
use Zend\Session\SaveHandler\SaveHandlerInterface as SaveHandler;
use Zend\Session\Storage\StorageInterface as Storage;

/**
 * Base ManagerInterface implementation
 *
 * Defines common constructor logic and getters for Storage and Configuration
 */
abstract class AbstractManager implements Manager
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * Default configuration class to use when no configuration provided
     * @var string
     */
    protected $defaultConfigClass = 'Zend\Session\Config\SessionConfig';

    /**
     * @var Storage
     */
    protected $storage;

    /**
     * Default storage class to use when no storage provided
     * @var string
     */
    protected $defaultStorageClass = 'Zend\Session\Storage\SessionArrayStorage';

    /**
     * @var SaveHandler
     */
    protected $saveHandler;

    /**
     * @var array
     */
    protected $validators;

    /**
     * Constructor
     *
     * @param  Config|null      $config
     * @param  Storage|null     $storage
     * @param  SaveHandler|null $saveHandler
     * @param  array            $validators
     * @throws Exception\RuntimeException
     */
    public function __construct(
        Config $config = null,
        Storage $storage = null,
        SaveHandler $saveHandler = null,
        array $validators = []
    ) {
        // init config
        if ($config === null) {
            if (! class_exists($this->defaultConfigClass)) {
                throw new Exception\RuntimeException(sprintf(
                    'Unable to locate config class "%s"; class does not exist',
                    $this->defaultConfigClass
                ));
            }

            $config = new $this->defaultConfigClass();

            if (! $config instanceof Config) {
                throw new Exception\RuntimeException(sprintf(
                    'Default config class %s is invalid; must implement %s\Config\ConfigInterface',
                    $this->defaultConfigClass,
                    __NAMESPACE__
                ));
            }
        }

        $this->config = $config;

        // init storage
        if ($storage === null) {
            if (! class_exists($this->defaultStorageClass)) {
                throw new Exception\RuntimeException(sprintf(
                    'Unable to locate storage class "%s"; class does not exist',
                    $this->defaultStorageClass
                ));
            }

            $storage = new $this->defaultStorageClass();

            if (! $storage instanceof Storage) {
                throw new Exception\RuntimeException(sprintf(
                    'Default storage class %s is invalid; must implement %s\Storage\StorageInterface',
                    $this->defaultConfigClass,
                    __NAMESPACE__
                ));
            }
        }

        $this->storage = $storage;

        // save handler
        if ($saveHandler !== null) {
            $this->saveHandler = $saveHandler;
        }

        $this->validators = $validators;
    }

    /**
     * {@inheritDoc}
     */
    public function setConfig(Config $config)
    {
        $this->config = $config;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * {@inheritDoc}
     */
    public function setStorage(Storage $storage)
    {
        $this->storage = $storage;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * {@inheritDoc}
     */
    public function setSaveHandler(SaveHandler $saveHandler)
    {
        $this->saveHandler = $saveHandler;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getSaveHandler()
    {
        return $this->saveHandler;
    }
}
