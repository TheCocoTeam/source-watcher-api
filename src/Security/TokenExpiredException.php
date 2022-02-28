<?php declare(strict_types=1);

namespace Coco\SourceWatcherApi\Security;

use Coco\SourceWatcherApi\Framework\Exception as FrameworkException;
use Throwable;

class TokenExpiredException extends FrameworkException
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
