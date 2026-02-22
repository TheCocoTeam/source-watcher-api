<?php declare(strict_types=1);

namespace Coco\SourceWatcherApi\Core\Item;

use Coco\SourceWatcherApi\Framework\ApiResponse;
use Coco\SourceWatcherApi\Framework\Controller;
use Coco\SourceWatcherApi\Framework\Exception as FrameworkException;
use Coco\SourceWatcherApi\Framework\ResponseCodes;

class ItemController extends Controller
{
    use ApiResponse;

    public function processRequest(string $requestMethod, array $extraOptions): void
    {
        $id = isset($extraOptions[0]) && $extraOptions[0] !== null ? (int) $extraOptions[0] : null;

        try {
            switch ($requestMethod) {
                case 'GET':
                    $response = $id !== null ? $this->getOne($id) : $this->getList();
                    break;
                case 'POST':
                    $response = $this->create();
                    break;
                case 'PUT':
                    $response = $id !== null ? $this->update($id) : $this->notFoundResponse();
                    break;
                case 'DELETE':
                    $response = $id !== null ? $this->delete($id) : $this->notFoundResponse();
                    break;
                default:
                    $response = $this->notFoundResponse();
            }
        } catch (FrameworkException $e) {
            $response = [
                'status_code_header' => ResponseCodes::INTERNAL_SERVER_ERROR,
                'body' => json_encode($e->getMessage()),
            ];
        }

        header($response['status_code_header']);
        if (!empty($response['body'])) {
            echo $response['body'];
        }
    }

    private function getList(): array
    {
        $dao = new ItemDAO();
        $items = $dao->getList();
        $data = array_map(static fn(Item $item) => $item->jsonSerialize(), $items);
        return $this->makeArrayResponse(ResponseCodes::OK, $data);
    }

    private function getOne(int $id): array
    {
        $dao = new ItemDAO();
        $item = $dao->getById($id);
        if ($item === null) {
            return $this->notFoundResponse();
        }
        return $this->makeArrayResponse(ResponseCodes::OK, $item->jsonSerialize());
    }

    private function create(): array
    {
        $requestData = $this->getRequestData();
        $name = trim((string) ($requestData['name'] ?? ''));
        $description = array_key_exists('description', $requestData) ? trim((string) $requestData['description']) : null;
        if ($name === '') {
            return $this->makeResponse(ResponseCodes::BAD_REQUEST, 'Missing or empty name');
        }
        $dao = new ItemDAO();
        $item = $dao->create($name, $description === '' ? null : $description);
        return $this->makeArrayResponse(ResponseCodes::OK, $item->jsonSerialize());
    }

    private function update(int $id): array
    {
        $dao = new ItemDAO();
        if ($dao->getById($id) === null) {
            return $this->notFoundResponse();
        }
        $requestData = $this->getRequestData();
        $name = isset($requestData['name']) ? trim((string) $requestData['name']) : null;
        $description = array_key_exists('description', $requestData) ? (string) $requestData['description'] : null;
        if ($name === null || $name === '') {
            return $this->makeResponse(ResponseCodes::BAD_REQUEST, 'Missing or empty name');
        }
        $dao->update($id, $name, $description);
        $item = $dao->getById($id);
        return $this->makeArrayResponse(ResponseCodes::OK, $item->jsonSerialize());
    }

    private function delete(int $id): array
    {
        $dao = new ItemDAO();
        if ($dao->getById($id) === null) {
            return $this->notFoundResponse();
        }
        $dao->delete($id);
        return ['status_code_header' => ResponseCodes::OK, 'body' => null];
    }
}
