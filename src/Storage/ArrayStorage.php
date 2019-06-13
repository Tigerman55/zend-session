<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Session\Storage;

use Zend\Stdlib\ArrayObject;
use Zend\Session\Exception;

/**
 * Array session storage
 *
 * Defines an ArrayObject interface for accessing session storage, with options
 * for setting metadata, locking, and marking as isImmutable.
 */
class ArrayStorage extends ArrayObject implements StorageInterface
{
    /**
     * Is storage marked isImmutable?
     * @var bool
     */
    protected $isImmutable = false;

    /**
     * Constructor
     *
     * Instantiates storage as an ArrayObject, allowing property access.
     * Also sets the initial request access time.
     *
     * @param array  $input
     * @param int    $flags
     * @param string $iteratorClass
     */
    public function __construct(
        $input = [],
        $flags = ArrayObject::ARRAY_AS_PROPS,
        $iteratorClass = '\\ArrayIterator'
    ) {
        parent::__construct($input, $flags, $iteratorClass);
        $this->setRequestAccessTime(microtime(true));
    }

    /**
     * Set the request access time
     *
     * @param  float        $time
     * @return ArrayStorage
     */
    protected function setRequestAccessTime($time)
    {
        $this->setMetadata('_REQUEST_ACCESS_TIME', $time);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getRequestAccessTime()
    {
        return $this->getMetadata('_REQUEST_ACCESS_TIME');
    }

    /**
     * Set a value in the storage object
     *
     * If the object is marked as isImmutable, or the object or key is marked as
     * locked, raises an exception.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return void
     */

    /**
     * @param  mixed                      $key
     * @param  mixed                      $value
     * @throws Exception\RuntimeException
     */
    public function offsetSet($key, $value)
    {
        if ($this->isImmutable()) {
            throw new Exception\RuntimeException(
                sprintf('Cannot set key "%s" as storage is marked isImmutable', $key)
            );
        }
        if ($this->isLocked($key)) {
            throw new Exception\RuntimeException(
                sprintf('Cannot set key "%s" due to locking', $key)
            );
        }

        parent::offsetSet($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function lock($key = null)
    {
        if (null === $key) {
            $this->setMetadata('_READONLY', true);

            return $this;
        }
        if (isset($this[$key])) {
            $this->setMetadata('_LOCKS', [$key => true]);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function isLocked($key = null)
    {
        if ($this->isImmutable()) {
            // isImmutable trumps all
            return true;
        }

        if (null === $key) {
            // testing for global lock
            return $this->getMetadata('_READONLY');
        }

        $locks    = $this->getMetadata('_LOCKS');
        $readOnly = $this->getMetadata('_READONLY');

        if ($readOnly && ! $locks) {
            // global lock in play; all keys are locked
            return true;
        } elseif ($readOnly && $locks) {
            return array_key_exists($key, $locks);
        }

        // test for individual locks
        if (! $locks) {
            return false;
        }

        return array_key_exists($key, $locks);
    }

    /**
     * {@inheritDoc}
     */
    public function unlock($key = null)
    {
        if (null === $key) {
            // Unlock everything
            $this->setMetadata('_READONLY', false);
            $this->setMetadata('_LOCKS', false);

            return $this;
        }

        $locks = $this->getMetadata('_LOCKS');
        if (! $locks) {
            if (! $this->getMetadata('_READONLY')) {
                return $this;
            }
            $array = $this->toArray();
            $keys  = array_keys($array);
            $locks = array_flip($keys);
            unset($array, $keys);
        }

        if (array_key_exists($key, $locks)) {
            unset($locks[$key]);
            $this->setMetadata('_LOCKS', $locks, true);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function markImmutable()
    {
        $this->isImmutable = true;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function isImmutable()
    {
        return $this->isImmutable;
    }

    /**
     * {@inheritDoc}
     */
    public function setMetadata($key, $value, $overwriteArray = false)
    {
        if ($this->isImmutable) {
            throw new Exception\RuntimeException(
                sprintf('Cannot set key "%s" as storage is marked isImmutable', $key)
            );
        }

        if (! isset($this['__ZF'])) {
            $this['__ZF'] = [];
        }

        if (isset($this['__ZF'][$key]) && is_array($value)) {
            if ($overwriteArray) {
                $this['__ZF'][$key] = $value;
            } else {
                $this['__ZF'][$key] = array_replace_recursive($this['__ZF'][$key], $value);
            }
        } else {
            if ((null === $value) && isset($this['__ZF'][$key])) {
                // unset($this['__ZF'][$key]) led to "indirect modification...
                // has no effect" errors, so explicitly pulling array and
                // unsetting key.
                $array = $this['__ZF'];
                unset($array[$key]);
                $this['__ZF'] = $array;
                unset($array);
            } elseif (null !== $value) {
                $this['__ZF'][$key] = $value;
            }
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadata($key = null)
    {
        if (! isset($this['__ZF'])) {
            return false;
        }

        if (null === $key) {
            return $this['__ZF'];
        }

        if (! array_key_exists($key, $this['__ZF'])) {
            return false;
        }

        return $this['__ZF'][$key];
    }

    /**
     * {@inheritDoc}
     */
    public function clear($key = null)
    {
        if ($this->isImmutable()) {
            throw new Exception\RuntimeException('Cannot clear storage as it is marked immutable');
        }
        if (null === $key) {
            $this->fromArray([]);

            return $this;
        }

        if (! isset($this[$key])) {
            return $this;
        }

        // Clear key data
        unset($this[$key]);

        // Clear key metadata
        $this->setMetadata($key, null)
             ->unlock($key);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function fromArray(array $array)
    {
        $ts = $this->getRequestAccessTime();
        $this->exchangeArray($array);
        $this->setRequestAccessTime($ts);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray($metaData = false)
    {
        $values = $this->getArrayCopy();
        if ($metaData) {
            return $values;
        }
        if (isset($values['__ZF'])) {
            unset($values['__ZF']);
        }

        return $values;
    }
}
