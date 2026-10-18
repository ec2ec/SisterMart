<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\FavoriteService;
use think\Response;

class Favorites extends ApiController
{
    public function index(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');

            return (new FavoriteService())->list((int) $member['id']);
        });
    }

    public function codes(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');

            return (new FavoriteService())->codes((int) $member['id']);
        });
    }

    public function store(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');
            $body = $this->payload();
            $code = (string) ($body['product_code'] ?? $body['product_id'] ?? $body['id'] ?? '');

            return (new FavoriteService())->add((int) $member['id'], $code);
        }, 201);
    }

    public function toggle(): Response
    {
        return $this->run(function () {
            $member = app('sistermart.member');
            $body = $this->payload();
            $code = (string) ($body['product_code'] ?? $body['product_id'] ?? $body['id'] ?? '');

            return (new FavoriteService())->toggle((int) $member['id'], $code);
        });
    }

    public function destroy(string $id): Response
    {
        return $this->run(function () use ($id) {
            $member = app('sistermart.member');

            return (new FavoriteService())->remove((int) $member['id'], $id);
        });
    }
}
