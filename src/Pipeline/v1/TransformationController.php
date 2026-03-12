<?php declare(strict_types=1);

namespace Coco\SourceWatcherApi\Pipeline\v1;

use Coco\SourceWatcherApi\Framework\ApiResponse;
use Coco\SourceWatcherApi\Framework\Controller;
use Coco\SourceWatcherApi\Framework\ResponseCodes;

/**
 * Save a transformation definition (.swt file) given a list of steps.
 *
 * This API does not execute the pipeline; it only persists the array
 * representation similar to SourceWatcher::save() in Core.
 *
 * Expected JSON payload:
 * {
 *   "name": "optional-name",          // optional; if omitted, a random name is generated
 *   "steps": [                        // required; non-empty array of step definitions
 *     { ... }, { ... }
 *   ]
 * }
 */
class TransformationController extends Controller
{
    use ApiResponse;

    public function processRequest(string $requestMethod, array $extraOptions): void
    {
        if ($requestMethod !== 'POST') {
            $response = $this->notFoundResponse();
            header($response['status_code_header']);
            if ($response['body']) {
                echo $response['body'];
            }
            return;
        }

        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody ?? '', true);

        if (!is_array($data)) {
            $response = $this->makeResponse(ResponseCodes::BAD_REQUEST, 'Invalid JSON payload.');
            header($response['status_code_header']);
            if ($response['body']) {
                echo $response['body'];
            }
            return;
        }

        $steps = $data['steps'] ?? null;
        if (!is_array($steps) || $steps === []) {
            $response = $this->makeResponse(ResponseCodes::BAD_REQUEST, 'Field "steps" must be a non-empty array.');
            header($response['status_code_header']);
            if ($response['body']) {
                echo $response['body'];
            }
            return;
        }

        $name = $data['name'] ?? null;
        if ($name !== null && !is_string($name)) {
            $response = $this->makeResponse(ResponseCodes::BAD_REQUEST, 'Field "name" must be a string when provided.');
            header($response['status_code_header']);
            if ($response['body']) {
                echo $response['body'];
            }
            return;
        }

        $name = is_string($name) ? trim($name) : null;

        // Persist the steps array as JSON, following the same directory structure
        // as Coco\SourceWatcher\Core\Pipeline\SourceWatcher::save().
        $jsonRepresentation = json_encode($steps, JSON_PRETTY_PRINT);

        $home = $_SERVER['HOME'] ?? getenv('HOME') ?: null;
        if (empty($home)) {
            // Fallback for environments (like Apache in the container) where HOME is not set.
            // Use the web root as a base; this keeps behavior similar to Core, which expects
            // a writable HOME-based directory, but avoids hard failures when HOME is missing.
            $home = dirname(__DIR__, 3);
        }

        $mainDirectory = $home . DIRECTORY_SEPARATOR . '.source-watcher';
        if (!file_exists($mainDirectory) && !@mkdir($mainDirectory, 0777, true) && !is_dir($mainDirectory)) {
            $response = $this->makeResponse(ResponseCodes::INTERNAL_SERVER_ERROR, 'Unable to create main transformation directory.');
            header($response['status_code_header']);
            if ($response['body']) {
                echo $response['body'];
            }
            return;
        }

        $transformationsDirectory = $mainDirectory . DIRECTORY_SEPARATOR . 'transformations';
        if (!file_exists($transformationsDirectory) && !@mkdir($transformationsDirectory, 0777, true) && !is_dir($transformationsDirectory)) {
            $response = $this->makeResponse(ResponseCodes::INTERNAL_SERVER_ERROR, 'Unable to create transformations directory.');
            header($response['status_code_header']);
            if ($response['body']) {
                echo $response['body'];
            }
            return;
        }

        if (empty($name)) {
            // Simple random name; avoids coupling to any specific UUID implementation.
            $name = 'transformation_' . bin2hex(random_bytes(8));
        }

        $filePath = $transformationsDirectory . DIRECTORY_SEPARATOR . $name . '.swt';

        if (@file_put_contents($filePath, $jsonRepresentation) === false) {
            $response = $this->makeResponse(ResponseCodes::INTERNAL_SERVER_ERROR, 'Unable to write transformation file.');
            header($response['status_code_header']);
            if ($response['body']) {
                echo $response['body'];
            }
            return;
        }

        $payload = [
            'name' => $name,
            'path' => $filePath,
        ];

        $response = $this->makeArrayResponse(ResponseCodes::OK, $payload);
        header($response['status_code_header']);
        if ($response['body']) {
            echo $response['body'];
        }
    }
}

