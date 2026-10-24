<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

class AddressService
{
    public const REGIONS = ['香港島', '九龍', '新界'];

    /** @var array<string, list<string>> 大區 → 分區（常用商業／社區名） */
    public const DISTRICTS_BY_REGION = [
        '香港島' => [
            '中環', '上環', '西環', '堅尼地城', '石塘咀',
            '灣仔', '銅鑼灣', '跑馬地', '大坑', '天后',
            '北角', '鰂魚涌', '太古', '西灣河', '筲箕灣', '柴灣',
            '香港仔', '鴨脷洲', '黃竹坑', '赤柱', '淺水灣', '薄扶林',
        ],
        '九龍' => [
            '尖沙咀', '佐敦', '油麻地', '旺角', '大角咀', '太子',
            '深水埗', '長沙灣', '荔枝角', '美孚',
            '九龍城', '土瓜灣', '紅磡', '何文田',
            '黃大仙', '鑽石山', '新蒲崗', '慈雲山',
            '觀塘', '九龍灣', '牛頭角', '藍田', '油塘', '彩虹',
        ],
        '新界' => [
            '荃灣', '葵涌', '青衣', '荃灣西',
            '屯門', '元朗', '天水圍', '上水', '粉嶺', '大埔',
            '沙田', '大圍', '馬鞍山', '火炭',
            '西貢', '將軍澳', '坑口', '調景嶺',
            '東涌', '愉景灣', '長洲', '南丫島',
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
