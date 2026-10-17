<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

class AddressService
{
    public const REGIONS = ['香港島', '九龍', '新界'];

    public function list(int $memberId): array
    {
        $rows = Db::name('addresses')->where('member_id', $memberId)
            ->order('is_default', 'desc')->order('id', 'desc')->select()->toArray();

        return array_map([$this, 'present'], $rows);
    }

    public function save(int $memberId, array $body, ?int $id = null): array
    {
        $receiver = trim((string) ($body['receiver'] ?? ''));
        $phone = trim((string) ($body['phone'] ?? ''));
        $region = trim((string) ($body['region'] ?? ''));
        $district = trim((string) ($body['district'] ?? ''));
        $detail = trim((string) ($body['detail'] ?? ''));
        if ($receiver === '' || $phone === '' || $district === '' || $detail === '') {
            throw new ApiException('INVALID', '請填寫完整收貨資訊', 422, 42201);
        }
        if (!in_array($region, self::REGIONS, true)) {
            throw new ApiException('INVALID_REGION', '只支持香港地址（香港島 / 九龍 / 新界）', 422, 42210);
        }
        $isDefault = !empty($body['is_default']) ? 1 : 0;
        $now = date('Y-m-d H:i:s');
        if ($isDefault) {
            Db::name('addresses')->where('member_id', $memberId)->update(['is_default' => 0, 'updated_at' => $now]);
        }
        $data = compact('receiver', 'phone', 'region', 'district', 'detail') + [
            'is_default' => $isDefault,
            'updated_at' => $now,
        ];
        if ($id) {
            $row = Db::name('addresses')->where(['id' => $id, 'member_id' => $memberId])->find();
            if (!$row) {
                throw new ApiException('NOT_FOUND', '地址不存在', 404, 40410);
            }
            Db::name('addresses')->where('id', $id)->update($data);
        } else {
            if (!$isDefault && !Db::name('addresses')->where('member_id', $memberId)->find()) {
                $data['is_default'] = 1;
            }
            $data['member_id'] = $memberId;
            $data['created_at'] = $now;
            $id = (int) Db::name('addresses')->insertGetId($data);
        }

        return $this->present(Db::name('addresses')->where('id', $id)->find());
    }

    public function delete(int $memberId, int $id): array
    {
        $row = Db::name('addresses')->where(['id' => $id, 'member_id' => $memberId])->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '地址不存在', 404, 40410);
        }
        Db::name('addresses')->where('id', $id)->delete();
        if ((int) $row['is_default'] === 1) {
            $next = Db::name('addresses')->where('member_id', $memberId)->order('id', 'desc')->find();
            if ($next) {
                Db::name('addresses')->where('id', $next['id'])->update(['is_default' => 1]);
            }
        }

        return ['deleted' => true];
    }

    public function getOwned(int $memberId, int $id): array
    {
        $row = Db::name('addresses')->where(['id' => $id, 'member_id' => $memberId])->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '地址不存在', 404, 40410);
        }

        return $row;
    }

    private function present(array $row): array
    {
        return [
            'id'         => (int) $row['id'],
            'receiver'   => $row['receiver'],
            'phone'      => $row['phone'],
            'region'     => $row['region'],
            'district'   => $row['district'],
            'detail'     => $row['detail'],
            'is_default' => (int) $row['is_default'] === 1,
        ];
    }
}
