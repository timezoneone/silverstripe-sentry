<?php

namespace SilverStripeSentry\Adaptor;

use SilverStripeSentry\Adaptor\SentryClientAdaptor;
use SilverStripeSentry\Exception\SentryLogWriterException;

/**
 * The Sentry class simply acts as a bridge between the Raven PHP SDK and
 * SilverStripe itself.
 * 
 * @author  Russell Michell 2017 <russ@theruss.com>
 * @package silverstripe/sentry
 */

class RavenClient extends SentryClientAdaptor
{
    
    /**
     * It's an ERROR unless proven otherwise!
     * 
     * @var    string
     * @config
     */
    private static $default_error_level = 'ERROR';
    
    /**
     *
     * @var Raven_Client
     */
    protected $client;
    
    /**
     * A mapping of log-level values between Zend_Log => Raven_Client
     * 
     * @var array
     */
    protected $logLevels = [
        'NOTICE'    => \Raven_Client::INFO,
        'WARN'      => \Raven_Client::WARNING,
        'ERR'       => \Raven_Client::ERROR,
        'EMERG'     => \Raven_Client::FATAL
    ];
    
    /**
     * @param string $e Environment
     * @param array  $u User data
     * @param array  $t Tags
     * @param array  $x eXtra
     * @return \Raven_Client
     * @throws SentryLogWriterException
     */
    public function __construct($e = null, $u = [], $t = [], $x = [], $r = null)
    {        
        if (!$dsn = $this->getOpts('dsn')) {
            $msg = sprintf("%s requires a DSN string to be set in config.", __CLASS__);
            throw new SentryLogWriterException($msg);
        }
        
        $this->client = new \Raven_Client($dsn);
        
        // Use the xxx_context() methods
        $this->client->setEnvironment($e);
        $this->client->user_context($u);
        $this->client->tags_context($t);
        $this->client->extra_context($x);
        // Wonky API much?
        $this->client->setRelease($r);
        
        // Installs all available PHP error handlers when set
        if ($this->config()->install === true) {
            $this->client->install();
        }
        
        return $this->client;
    }

    /**
     * @inheritdoc
     */
    public function setData($field, $data)
    {
        switch($field) {
            case 'env':
                $this->client->setEnvironment($data);
                break;
            case 'tags':
                $this->client->tags_context($data);
                break;
            case 'user':
                $this->client->user_context($data);
                break;
            case 'extra':
                $this->client->extra_context($data);
                break;
            default:
                $msg = sprintf('Unknown field %s passed to %s.', $field, __FUNCTION__);
                throw new SentryLogWriterException($msg);
        }
    }
    
    /**
     * @inheritdoc
     */
    public function getLevel($level)
    {
        return isset($this->client->logLevels[$level]) ?
            $this->client->logLevels[$level] : 
            $this->client->logLevels[self::$default_error_level];
    }
    
    /**
     * @inheritdoc
     */
    public function send($message, $extras = [], $data, $trace)
    {
        // Raven_Client::captureMessage() returns an ID to identify each message
        $eventId = $this->client->captureMessage($message, $extras, $data, $trace);
        
        return $eventId ?: false;
    }

}