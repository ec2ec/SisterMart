<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\AddressService;
use think\Response;

class Addresses extends ApiController
{
    private function memberId(): int
    {
        return (int) app('sistermart.member')['id'];
    }

    public function index(): Response
    {
        return $this->run(fn () => [
            'items' => (new AddressService())->list($this->memberId()),
            'regions' => AddressService::REGIONS,
            'districts_by_region' => AddressService::DISTRICTS_BY_REGION,
            'areas_by_district' => AddressService::AREAS_BY_DISTRICT,
        ]);
    }

    public function store(): Response
    {
        return $this->run(fn () => (new AddressService())->save($this->memberId(), $this->payload()), 201);
    }

    public function update(int $id): Response
    {
        return $this->run(fn () => (new AddressService())->save($this->memberId(), $this->payload(), $id));
    }

    public function destroy(int $id): Response
    {
        return $this->run(fn () => (new AddressService())->delete($this->memberId(), $id));
    }
}
