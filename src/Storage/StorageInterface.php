<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Session\Storage;

use ArrayAccess;
use Countable;
use Serializable;
use Traversable;

/**
 * Session storage interface
 *
 * Defines the minimum requirements for handling userland, in-script session
 * storage (e.g., the $_SESSION superglobal array).
 */
interface StorageInterface extends Traversable, ArrayAccess, Serializable, Countable
{

    /**
     * Retrieve the request access time
     *
     * @return float
     */
    public function getRequestAccessTime();

    /**
     * Lock this storage instance, or a key within it
     *
     * @param  null|int|string $key
     * @return ArrayStorage
     */
    public function lock($key = null);

    /**
     * Is the object or key marked as locked?
     *
     * @param  null|int|string $key
     * @return bool
     */
    public function isLocked($key = null);

    /**
     * Unlock an object or key marked as locked
     *
     * @param  null|int|string $key
     * @return ArrayStorage
     */
    public function unlock($key = null);

    /**
     * Mark the storage container as isImmutable
     *
     * @return ArrayStorage
     */
    public function markImmutable();

    /**
     * Is the storage container marked as isImmutable?
     *
     * @return bool
     */
    public function isImmutable();

    /**
     * Set storage metadata
     *
     * Metadata is used to store information about the data being stored in the
     * object. Some example use cases include:
     * - Setting expiry data
     * - Maintaining access counts
     * - localizing session storage
     * - etc.
     *
     * @param  string                     $key
     * @param  mixed                      $value
     * @param  bool                       $overwriteArray Whether to overwrite or merge array values; by default, merges
     * @return ArrayStorage
     * @throws Exception\RuntimeException
     */
    public function setMetadata($key, $value, $overwriteArray = false);

    /**
     * Retrieve metadata for the storage object or a specific metadata key
     *
     * Returns false if no metadata stored, or no metadata exists for the given
     * key.
     *
     * @param  null|int|string $key
     * @return mixed
     */
    public function getMetadata($key = null);

    /**
     * Clear the storage object or a subkey of the object
     *
     * @param  null|int|string            $key
     * @return ArrayStorage
     * @throws Exception\RuntimeException
     */
    public function clear($key = null);

    /**
     * Load the storage from another array
     *
     * Overwrites any data that was previously set.
     *
     * @param  array        $array
     * @return ArrayStorage
     */
    public function fromArray(array $array);

    /**
     * Cast the object to an array
     *
     * @param  bool $metaData Whether to include metadata
     * @return array
     */
    public function toArray($metaData = false);
}
