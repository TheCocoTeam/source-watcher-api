<?php

namespace Coco\SourceWatcherApi\Framework;

/**
 * Class ResponseCodes
 * @package Coco\SourceWatcherApi\Framework
 */
class ResponseCodes
{
    const OK = "HTTP/1.1 200 OK";

    const BAD_REQUEST = "HTTP/1.1 400 Bad Request";

    const UNAUTHORIZED = "HTTP/1.1 401 Unauthorized";

    const NOT_FOUND = "HTTP/1.1 404 Not Found";

    const INTERNAL_SERVER_ERROR = "HTTP/1.1 500 Internal Server Error";
}
