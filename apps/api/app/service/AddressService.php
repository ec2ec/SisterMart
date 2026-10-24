<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

class AddressService
{
    public const REGIONS = ['香港島', '九龍', '新界'];

    /** @var array<string, list<string>> 大區 → 香港十八區 */
    public const DISTRICTS_BY_REGION = [
        '香港島' => ['中西區', '灣仔區', '東區', '南區'],
        '九龍' => ['油尖旺區', '深水埗區', '九龍城區', '黃大仙區', '觀塘區'],
        '新界' => [
            '葵青區', '荃灣區', '屯門區', '元朗區', '北區',
            '大埔區', '沙田區', '西貢區', '離島區',
        ],
    ];

    /** @return list<string> */
    public static function districtsFor(string $region): array
    {
        return self::DISTRICTS_BY_REGION[$region] ?? [];
    }

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
        $allowed = self::districtsFor($region);
        if ($allowed && !in_array($district, $allowed, true)) {
            throw new ApiException('INVALID_DISTRICT', '請選擇對應大區下的分區', 422, 42211);
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
