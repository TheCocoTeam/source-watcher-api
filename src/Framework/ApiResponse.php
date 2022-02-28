<?php

namespace Coco\SourceWatcherApi\Framework;

/**
 * Trait ApiResponse
 * @package Coco\SourceWatcherApi\Framework
 */
trait ApiResponse
{
    /**
     * @param string $httpCode
     * @param string|null $body
     * @return array
     */
    public function makeResponse( string $httpCode, string $body = null ): array {
        if ( empty( $body ) ) {
            return ['status_code_header' => $httpCode];
        }

        return ['status_code_header' => $httpCode, 'body' => json_encode( $body )];
    }

    /**
     * @param string $httpCode
     * @param array|null $body
     * @return array
     */
    public function makeArrayResponse( string $httpCode, array $body = null ): array {
        if ( empty( $body ) ) {
            return ['status_code_header' => $httpCode];
        }

        return ['status_code_header' => $httpCode, 'body' => json_encode( $body )];
    }
}
