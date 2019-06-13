<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Session;

use Traversable;
use Zend\EventManager\Event;
use Zend\EventManager\EventManagerInterface;
use Zend\Stdlib\ArrayUtils;

/**
 * Session ManagerInterface implementation utilizing ext/session
 */
class SessionManager extends AbstractManager
{
    /**
     * Default options when a call to {@link destroy()} is made
     * - send_expire_cookie: whether or not to send a cookie expiring the current session cookie
     * - clear_storage: whether or not to empty the storage object of any stored values
     * @var array
     */
    protected $defaultDestroyOptions = [
        'send_expire_cookie' => true,
        'clear_storage'      => false,
    ];

    /**
     * @var array Default session manager options
     */
    protected $defaultOptions = [
        'attach_default_validators' => true,
    ];

    /**
     * @var array Default validators
     */
    protected $defaultValidators = [
        Validator\Id::class,
    ];

    /**
     * @var string value returned by session_name()
     */
    protected $name;

    /**
     * @var EventManagerInterface Validation chain to determine if session is valid
     */
    protected $validatorChain;

    /**
     * Constructor
     *
     * @param  Config\ConfigInterface|null           $config
     * @param  Storage\StorageInterface|null         $storage
     * @param  SaveHandler\SaveHandlerInterface|null $saveHandler
     * @param  array                                 $validators
     * @param  array                                 $options
     * @throws Exception\RuntimeException
     */
    public function __construct(
        Config\ConfigInterface $config = null,
        Storage\StorageInterface $storage = null,
        SaveHandler\SaveHandlerInterface $saveHandler = null,
        array $validators = [],
        array $options = []
    ) {
        $options = array_merge($this->defaultOptions, $options);
        if ($options['attach_default_validators']) {
            $validators = array_merge($this->defaultValidators, $validators);
        }

        parent::__construct($config, $storage, $saveHandler, $validators);
        register_shutdown_function([$this, 'writeClose']);
    }

    /**
     * {@inheritDoc}
     */
    public function sessionExists()
    {
        if (session_status() == PHP_SESSION_ACTIVE) {
            return true;
        }
        $sid = defined('SID') ? constant('SID') : false;
        if ($sid !== false && $this->getId()) {
            return true;
        }
        if (headers_sent()) {
            return true;
        }
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function start($preserveStorage = false)
    {
        if ($this->sessionExists()) {
            return;
        }

        $saveHandler = $this->getSaveHandler();
        if ($saveHandler instanceof SaveHandler\SaveHandlerInterface) {
            // register the session handler with ext/session
            $this->registerSaveHandler($saveHandler);
        }

        $oldSessionData = [];
        if (isset($_SESSION)) {
            $oldSessionData = $_SESSION;

            // convert session data to plain array that’ll be acceptable as
            // ArrayUtils::merge parameter
            if ($oldSessionData instanceof Storage\StorageInterface) {
                $oldSessionData = $oldSessionData->toArray();
            } elseif ($oldSessionData instanceof Traversable) {
                $oldSessionData = iterator_to_array($oldSessionData);
            }
        }

        session_start();

        if (! empty($oldSessionData) && is_array($oldSessionData)) {
            $_SESSION = ArrayUtils::merge($oldSessionData, $_SESSION, true);
        }

        $storage = $this->getStorage();

        // Since session is starting, we need to potentially repopulate our
        // session storage
        if ($storage instanceof Storage\SessionStorage && $_SESSION !== $storage) {
            if (! $preserveStorage) {
                $storage->fromArray($_SESSION);
            }
            $_SESSION = $storage;
        } elseif ($storage instanceof Storage\StorageInitializationInterface) {
            $storage->init($_SESSION);
        }

        $this->initializeValidatorChain();

        if (! $this->isValid()) {
            throw new Exception\RuntimeException('Session validation failed');
        }
    }

    /**
     * Create validators, insert reference value and add them to the validator chain
     */
    protected function initializeValidatorChain()
    {
        $validatorChain  = $this->getValidatorChain();
        $validatorValues = $this->getStorage()->getMetadata('_VALID');

        foreach ($this->validators as $validator) {
            // Ignore validators which are already present in Storage
            if (is_array($validatorValues) && array_key_exists($validator, $validatorValues)) {
                continue;
            }

            $validator = new $validator(null);
            $validatorChain->attach('session.validate', [$validator, 'isValid']);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function destroy(array $options = null)
    {
        if (! $this->sessionExists()) {
            return;
        }

        if (null === $options) {
            $options = $this->defaultDestroyOptions;
        } else {
            $options = array_merge($this->defaultDestroyOptions, $options);
        }

        session_destroy();
        if ($options['send_expire_cookie']) {
            $this->expireSessionCookie();
        }

        if ($options['clear_storage']) {
            $this->getStorage()->clear();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function writeClose()
    {
        // The assumption is that we're using PHP's ext/session.
        // session_write_close() will actually overwrite $_SESSION with an
        // empty array on completion -- which leads to a mismatch between what
        // is in the storage object and $_SESSION. To get around this, we
        // temporarily reset $_SESSION to an array, and then re-link it to
        // the storage object.
        //
        // Additionally, while you _can_ write to $_SESSION following a
        // session_write_close() operation, no changes made to it will be
        // flushed to the session handler. As such, we now mark the storage
        // object isImmutable.
        $storage  = $this->getStorage();
        if (! $storage->isImmutable()) {
            $_SESSION = $storage->toArray(true);
            session_write_close();
            $storage->fromArray($_SESSION);
            $storage->markImmutable();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setName($name)
    {
        if ($this->sessionExists()) {
            throw new Exception\InvalidArgumentException(
                'Cannot set session name after a session has already started'
            );
        }

        if (! preg_match('/^[a-zA-Z0-9]+$/', $name)) {
            throw new Exception\InvalidArgumentException(
                'Name provided contains invalid characters; must be alphanumeric only'
            );
        }

        $this->name = $name;
        session_name($name);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        if (null === $this->name) {
            // If we're grabbing via session_name(), we don't need our
            // validation routine; additionally, calling setName() after
            // session_start() can lead to issues, and often we just need the name
            // in order to do things such as setting cookies.
            $this->name = session_name();
        }
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function setId($id)
    {
        if ($this->sessionExists()) {
            throw new Exception\RuntimeException(
                'Session has already been started, to change the session ID call regenerateId()'
            );
        }
        session_id($id);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getId()
    {
        return session_id();
    }

    /**
     * {@inheritDoc}
     */
    public function regenerateId($deleteOldSession = true)
    {
        if ($this->sessionExists()) {
            session_regenerate_id((bool) $deleteOldSession);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function rememberMe($ttl = null)
    {
        if (null === $ttl) {
            $ttl = $this->getConfig()->getRememberMeSeconds();
        }
        $this->setSessionCookieLifetime($ttl);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function forgetMe()
    {
        $this->setSessionCookieLifetime(0);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setValidatorChain(EventManagerInterface $chain)
    {
        $this->validatorChain = $chain;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getValidatorChain()
    {
        if (null === $this->validatorChain) {
            $this->setValidatorChain(new ValidatorChain($this->getStorage()));
        }
        return $this->validatorChain;
    }

    /**
     * {@inheritDoc}
     */
    public function isValid()
    {
        $validator = $this->getValidatorChain();

        $event = new Event();
        $event->setName('session.validate');
        $event->setTarget($this);
        $event->setParams($this);

        $falseResult = function ($test) {
            return false === $test;
        };

        $responses = $validator->triggerEventUntil($falseResult, $event);

        if ($responses->stopped()) {
            // If execution was halted, validation failed
            return false;
        }

        // Otherwise, we're good to go
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function expireSessionCookie()
    {
        $config = $this->getConfig();
        if (! $config->getUseCookies()) {
            return;
        }
        setcookie(
            $this->getName(), // session name
            '', // value
            $_SERVER['REQUEST_TIME'] - 42000, // TTL for cookie
            $config->getCookiePath(),
            $config->getCookieDomain(),
            $config->getCookieSecure(),
            $config->getCookieHttpOnly()
        );
    }

    /**
     * Set the session cookie lifetime
     *
     * If a session already exists, destroys it (without sending an expiration
     * cookie), regenerates the session ID, and restarts the session.
     *
     * @param  int $ttl
     * @return void
     */
    protected function setSessionCookieLifetime($ttl)
    {
        $config = $this->getConfig();
        if (! $config->getUseCookies()) {
            return;
        }

        // Set new cookie TTL
        $config->setCookieLifetime($ttl);

        if ($this->sessionExists()) {
            // There is a running session so we'll regenerate id to send a new cookie
            $this->regenerateId();
        }
    }

    /**
     * Register Save Handler with ext/session
     *
     * Since ext/session is coupled to this particular session manager
     * register the save handler with ext/session.
     *
     * @param SaveHandler\SaveHandlerInterface $saveHandler
     * @return bool
     */
    protected function registerSaveHandler(SaveHandler\SaveHandlerInterface $saveHandler)
    {
        return session_set_save_handler($saveHandler);
    }
}
