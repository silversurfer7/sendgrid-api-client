<?php
/**
 *
 * @author: sschulze@silversurfer7.de
 */

namespace Silversurfer7\Sendgrid\Api\Client\Exception;


use Exception;

abstract class AbstractRequestException extends \Exception{
    public function __construct($message = "", $httpStatusCode = 0, Exception $previous = null)
    {
        parent::__construct($message, $httpStatusCode, $previous);
    }

    public function getHttpStatusCode() {
        return $this->getCode();
    }
}