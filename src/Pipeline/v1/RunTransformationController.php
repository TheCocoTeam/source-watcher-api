<?php declare(strict_types=1);

namespace Coco\SourceWatcherApi\Pipeline\v1;

use Coco\SourceWatcher\Core\Database\Connections\MySqlConnector;
use Coco\SourceWatcher\Core\Database\Connections\PostgreSqlConnector;
use Coco\SourceWatcher\Core\Database\Connections\SqliteConnector;
use Coco\SourceWatcher\Core\Exception\SourceWatcherException;
use Coco\SourceWatcher\Core\IO\Inputs\FileInput;
use Coco\SourceWatcher\Core\IO\Outputs\DatabaseOutput;
use Coco\SourceWatcher\Core\Pipeline\SourceWatcher;
use Coco\SourceWatcherApi\Framework\ApiResponse;
use Coco\SourceWatcherApi\Framework\Controller;
use Coco\SourceWatcherApi\Framework\ResponseCodes;

/**
 * Run a transformation: either by name (saved .swt file) or by inline steps (current canvas).
 *
 * Expected JSON payload (one of):
 *   { "name": "transformation-name" }   — run saved transformation
 *   { "steps": [ { "type", "name", "options" }, ... ] }   — run inline steps (current canvas)
 */
class RunTransformationController extends Controller
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
        $name = isset($data['name']) && is_string($data['name']) ? trim($data['name']) : null;

        if (is_array($steps) && $steps !== []) {
            $this->executeRun($steps, null);
            return;
        }

        if ($name !== null && $name !== '') {
            if (preg_match('/[^a-zA-Z0-9_\-]/', $name)) {
                $response = $this->makeResponse(ResponseCodes::BAD_REQUEST, 'Transformation name may only contain letters, numbers, underscores, and hyphens.');
                header($response['status_code_header']);
                if ($response['body']) {
                    echo $response['body'];
                }
                return;
            }

            $home = $_SERVER['HOME'] ?? getenv('HOME') ?: null;
            if (empty($home)) {
                $home = dirname(__DIR__, 3);
            }

            $transformationsDirectory = $home . DIRECTORY_SEPARATOR . '.source-watcher' . DIRECTORY_SEPARATOR . 'transformations';
            $filePath = $transformationsDirectory . DIRECTORY_SEPARATOR . $name . '.swt';

            if (!is_file($filePath) || !is_readable($filePath)) {
                $response = $this->makeResponse(ResponseCodes::NOT_FOUND, 'Transformation not found: ' . $name);
                header($response['status_code_header']);
                if ($response['body']) {
                    echo $response['body'];
                }
                return;
            }

            $json = file_get_contents($filePath);
            $steps = $json !== false ? json_decode($json, true) : null;

            if (!is_array($steps) || $steps === []) {
                $response = $this->makeResponse(ResponseCodes::BAD_REQUEST, 'Transformation file is empty or invalid.');
                header($response['status_code_header']);
                if ($response['body']) {
                    echo $response['body'];
                }
                return;
            }

            $this->executeRun($steps, $name);
            return;
        }

        $response = $this->makeResponse(ResponseCodes::BAD_REQUEST, 'Either "name" (saved transformation) or "steps" (inline) is required.');
        header($response['status_code_header']);
        if ($response['body']) {
            echo $response['body'];
        }
    }

    private function executeRun(array $steps, ?string $name): void
    {
        try {
            $this->runPipeline($steps);
        } catch (StepFailureException $e) {
            $response = $this->makeArrayResponse(ResponseCodes::INTERNAL_SERVER_ERROR, [
                'message' => 'Pipeline execution failed.',
                'error' => $e->getMessage(),
                'stepIndex' => $e->getStepIndex(),
                'stepName' => $e->getStepName(),
            ]);
            header($response['status_code_header']);
            if ($response['body']) {
                echo $response['body'];
            }
            return;
        } catch (SourceWatcherException $e) {
            $response = $this->makeArrayResponse(ResponseCodes::INTERNAL_SERVER_ERROR, [
                'message' => 'Pipeline execution failed.',
                'error' => $e->getMessage(),
            ]);
            header($response['status_code_header']);
            if ($response['body']) {
                echo $response['body'];
            }
            return;
        } catch (\Throwable $e) {
            $response = $this->makeArrayResponse(ResponseCodes::INTERNAL_SERVER_ERROR, [
                'message' => 'Pipeline execution failed.',
                'error' => $e->getMessage(),
            ]);
            header($response['status_code_header']);
            if ($response['body']) {
                echo $response['body'];
            }
            return;
        }

        $payload = ['message' => 'Transformation ran successfully.'];
        if ($name !== null) {
            $payload['name'] = $name;
        }
        $response = $this->makeArrayResponse(ResponseCodes::OK, $payload);
        header($response['status_code_header']);
        if ($response['body']) {
            echo $response['body'];
        }
    }

    /**
     * Build and execute a SourceWatcher pipeline from a steps array (same shape as .swt).
     *
     * @param array<int, array{type: string, name: string, options?: array}> $steps
     * @throws SourceWatcherException
     * @throws StepFailureException when a step throws (carries step index and name for error reporting)
     */
    private function runPipeline(array $steps): void
    {
        $sourceWatcher = new SourceWatcher();

        foreach ($steps as $index => $step) {
            $type = $step['type'] ?? '';
            $name = (string) ($step['name'] ?? '');
            $options = $step['options'] ?? [];
            if (isset($step['description']) && is_string($step['description'])) {
                $options['description'] = $step['description'];
            }

            try {
                if ($type === 'extractor') {
                    if ($name === 'Csv') {
                        $filePath = $options['filePath'] ?? '';
                        if ($filePath === '') {
                            throw new SourceWatcherException('Csv extractor requires options.filePath.');
                        }
                        $input = new FileInput($filePath);
                        $extractorOptions = array_filter([
                            'columns' => $options['columns'] ?? null,
                            'delimiter' => $options['delimiter'] ?? null,
                            'enclosure' => $options['enclosure'] ?? null,
                        ], fn($v) => $v !== null && $v !== '');
                        $sourceWatcher->extract('Csv', $input, $extractorOptions);
                    } elseif ($name === 'Json') {
                        $filePath = $options['filePath'] ?? '';
                        if ($filePath === '') {
                            throw new SourceWatcherException('Json extractor requires options.filePath.');
                        }
                        $input = new FileInput($filePath);
                        $extractorOptions = [];
                        if (isset($options['columns']) && is_array($options['columns']) && $options['columns'] !== []) {
                            $extractorOptions['columns'] = $options['columns'];
                        }
                        $sourceWatcher->extract('Json', $input, $extractorOptions);
                    } elseif ($name === 'Txt') {
                        $filePath = $options['filePath'] ?? '';
                        if ($filePath === '') {
                            throw new SourceWatcherException('Txt extractor requires options.filePath.');
                        }
                        $input = new FileInput($filePath);
                        $extractorOptions = [];
                        $column = isset($options['column']) && is_string($options['column']) ? trim($options['column']) : '';
                        if ($column !== '') {
                            $extractorOptions['column'] = $column;
                        }
                        $sourceWatcher->extract('Txt', $input, $extractorOptions);
                    } else {
                        throw new SourceWatcherException('Unsupported extractor: ' . $name);
                    }
                    continue;
                }

                if ($type === 'transformer') {
                    if (strtolower($name) === 'convertcase') {
                        $options = $this->normalizeConvertCaseMode($options);
                    }
                    $sourceWatcher->transform($name, $options);
                    continue;
                }

                if ($type === 'loader') {
                    if ($name === 'Database') {
                        $output = $this->buildDatabaseOutput($options);
                        $sourceWatcher->load('Database', $output, []);
                    } else {
                        throw new SourceWatcherException('Unsupported loader: ' . $name);
                    }
                    continue;
                }

                throw new SourceWatcherException('Unknown step type: ' . $type);
            } catch (\Throwable $e) {
                throw new StepFailureException($e->getMessage(), $index, $name, $e);
            }
        }

        $sourceWatcher->run();
    }

    /**
     * Normalize ConvertCase mode: accept string keys (upper, lower, title) and pass integer to Core (MB_CASE_*).
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function normalizeConvertCaseMode(array $options): array
    {
        $mode = $options['mode'] ?? MB_CASE_TITLE;
        $options['mode'] = is_string($mode)
            ? match (strtolower($mode)) {
                'upper' => MB_CASE_UPPER,
                'lower' => MB_CASE_LOWER,
                'title' => MB_CASE_TITLE,
                default => MB_CASE_TITLE,
            }
            : (int) $mode;
        return $options;
    }

    /**
     * Build a DatabaseOutput from saved options (driver, tableName, and driver-specific connection params).
     *
     * @param array{driver?: string, tableName?: string, path?: string, memory?: bool, host?: string, port?: int, database?: string, user?: string, password?: string} $options
     */
    private function buildDatabaseOutput(array $options): DatabaseOutput
    {
        $driver = $options['driver'] ?? 'pdo_sqlite';
        $tableName = (string) ($options['tableName'] ?? 'data');

        if ($driver === 'pdo_sqlite') {
            $connector = new SqliteConnector();
            $connector->setTableName($tableName);
            if (!empty($options['memory'])) {
                $connector->setMemory(true);
            } else {
                $path = $options['path'] ?? ':memory:';
                $connector->setPath($path);
                $connector->setMemory(false);
            }
            return new DatabaseOutput($connector);
        }

        if ($driver === 'pdo_mysql') {
            $connector = new MySqlConnector();
            $connector->setTableName($tableName);
            $connector->setUser((string) ($options['user'] ?? ''));
            $connector->setPassword((string) ($options['password'] ?? ''));
            $connector->setHost((string) ($options['host'] ?? 'localhost'));
            $connector->setPort((int) ($options['port'] ?? 3306));
            $connector->setDbName((string) ($options['database'] ?? $options['dbName'] ?? ''));
            return new DatabaseOutput($connector);
        }

        if ($driver === 'pdo_pgsql') {
            $connector = new PostgreSqlConnector();
            $connector->setTableName($tableName);
            $connector->setUser((string) ($options['user'] ?? ''));
            $connector->setPassword((string) ($options['password'] ?? ''));
            $connector->setHost((string) ($options['host'] ?? 'localhost'));
            $connector->setPort((int) ($options['port'] ?? 5432));
            $connector->setDbName((string) ($options['database'] ?? $options['dbName'] ?? ''));
            return new DatabaseOutput($connector);
        }

        throw new SourceWatcherException('Unsupported database driver: ' . $driver);
    }
}
