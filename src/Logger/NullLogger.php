<?php
/**
 *
 * @author: sschulze@silversurfer7.de
 */

namespace Silversurfer7\Sendgrid\Api\Client\Logger;


use Psr\Log\LoggerInterface;

class NullLogger implements LoggerInterface{

    /**
     * @inheritdoc
     */
    public function emergency($message, array $context = array())
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function alert($message, array $context = array())
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function critical($message, array $context = array())
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function error($message, array $context = array())
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function warning($message, array $context = array())
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function notice($message, array $context = array())
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function info($message, array $context = array())
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function debug($message, array $context = array())
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function log($level, $message, array $context = array())
    {
        return null;
    }
}