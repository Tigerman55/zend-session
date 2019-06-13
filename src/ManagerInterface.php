<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Session;

use Zend\EventManager\EventManagerInterface;
use Zend\Session\Config\ConfigInterface as Config;
use Zend\Session\SaveHandler\SaveHandlerInterface as SaveHandler;
use Zend\Session\Storage\StorageInterface as Storage;

/**
 * Session manager interface
 */
interface ManagerInterface
{
    /**
     * Set configuration object
     *
     * @param  Config $config
     * @return AbstractManager
     */
    public function setConfig(Config $config);

    /**
     * Retrieve configuration object
     *
     * @return Config
     */
    public function getConfig();

    /**
     * Set session storage object
     *
     * @param  Storage $storage
     * @return AbstractManager
     */
    public function setStorage(Storage $storage);

    /**
     * Retrieve storage object
     *
     * @return Storage
     */
    public function getStorage();

    /**
     * Set session save handler object
     *
     * @param  SaveHandler $saveHandler
     * @return AbstractManager
     */
    public function setSaveHandler(SaveHandler $saveHandler);

    /**
     * Get SaveHandler Object
     *
     * @return SaveHandler
     */
    public function getSaveHandler();

    /**
     * Does a session exist and is it currently active?
     *
     * @return bool
     */
    public function sessionExists();

    /**
     * Start session
     *
     * if No session currently exists, attempt to start it. Calls
     * {@link isValid()} once session_start() is called, and raises an
     * exception if validation fails.
     *
     * @param bool $preserveStorage        If set to true, current session storage will not be overwritten by the
     *                                     contents of $_SESSION.
     * @return void
     * @throws Exception\RuntimeException
     */
    public function start();

    /**
     * Destroy/end a session
     *
     * @param  array $options See {@link $defaultDestroyOptions}
     * @return void
     */
    public function destroy();

    /**
     * Write session to save handler and close
     *
     * Once done, the Storage object will be marked as isImmutable.
     *
     * @return void
     */
    public function writeClose();

    /**
     * Attempt to set the session name
     *
     * If the session has already been started, or if the name provided fails
     * validation, an exception will be raised.
     *
     * @param  string $name
     * @return SessionManager
     * @throws Exception\InvalidArgumentException
     */
    public function setName($name);

    /**
     * Get session name
     *
     * Proxies to {@link session_name()}.
     *
     * @return string
     */
    public function getName();

    /**
     * Set session ID
     *
     * Can safely be called in the middle of a session.
     *
     * @param  string $id
     * @return SessionManager
     */
    public function setId($id);

    /**
     * Get session ID
     *
     * Proxies to {@link session_id()}
     *
     * @return string
     */
    public function getId();

    /**
     * Regenerate id
     *
     * Regenerate the session ID, using session save handler's
     * native ID generation Can safely be called in the middle of a session.
     *
     * @param  bool $deleteOldSession
     * @return SessionManager
     */
    public function regenerateId();

    /**
     * Set the TTL (in seconds) for the session cookie expiry
     *
     * Can safely be called in the middle of a session.
     *
     * @param  null|int $ttl
     * @return SessionManager
     */
    public function rememberMe($ttl = null);

    /**
     * Set a 0s TTL for the session cookie
     *
     * Can safely be called in the middle of a session.
     *
     * @return SessionManager
     */
    public function forgetMe();

    /**
     * Expire the session cookie
     *
     * Sends a session cookie with no value, and with an expiry in the past.
     *
     * @return void
     */
    public function expireSessionCookie();

    /**
     * Set the validator chain to use when validating a session
     *
     * In most cases, you should use an instance of {@link ValidatorChain}.
     *
     * @param  EventManagerInterface $chain
     * @return SessionManager
     */
    public function setValidatorChain(EventManagerInterface $chain);

    /**
     * Get the validator chain to use when validating a session
     *
     * By default, uses an instance of {@link ValidatorChain}.
     *
     * @return EventManagerInterface
     */
    public function getValidatorChain();

    /**
     * Is this session valid?
     *
     * Notifies the Validator Chain until either all validators have returned
     * true or one has failed.
     *
     * @return bool
     */
    public function isValid();
}
