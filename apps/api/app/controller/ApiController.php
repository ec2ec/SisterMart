<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\support\ApiException;
use app\support\ApiResponse;
use think\Response;

abstract class ApiController extends BaseController
{
    protected function payload(): array
    {
        $post = $this->request->post();
        if (is_array($post) && $post !== []) {
            return $post;
        }
        $raw = (string) $this->request->getContent();
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function run(callable $fn): Response
    {
        try {
            $data = $fn();

            return ApiResponse::ok(is_array($data) ? $data : []);
        } catch (ApiException $e) {
            return ApiResponse::fail($e->errorCode, $e->getMessage(), $e->status);
        } catch (\Throwable $e) {
            $message = env('APP_DEBUG') ? $e->getMessage() : '服務暫時不可用';

            return ApiResponse::fail('SERVER', $message, 500);
        }
    }
}
