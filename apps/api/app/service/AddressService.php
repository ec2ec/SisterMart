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
            '荃灣區', '屯門區', '元朗區', '北區', '大埔區',
            '沙田區', '西貢區', '葵青區', '離島區',
        ],
    ];

    /** @var array<string, list<string>> 十八區 → 社區地點 */
    public const AREAS_BY_DISTRICT = [
        '中西區' => ['中環', '上環', '西環', '堅尼地城', '半山'],
        '灣仔區' => ['灣仔', '銅鑼灣', '跑馬地', '大坑'],
        '東區' => ['北角', '鰂魚涌', '西灣河', '柴灣', '小西灣', '筲箕灣'],
        '南區' => ['香港仔', '黃竹坑', '鴨脷洲', '淺水灣', '赤柱'],
        '油尖旺區' => ['尖沙咀', '佐敦', '油麻地', '旺角', '大角咀'],
        '深水埗區' => ['深水埗', '長沙灣', '荔枝角', '石硤尾'],
        '九龍城區' => ['九龍城', '土瓜灣', '紅磡', '何文田', '啟德'],
        '黃大仙區' => ['黃大仙', '鑽石山', '慈雲山', '彩虹'],
        '觀塘區' => ['觀塘', '藍田', '牛頭角', '秀茂坪', '油塘'],
        '荃灣區' => ['荃灣', '汀九', '深井'],
        '屯門區' => ['屯門', '藍地', '掃管笏'],
        '元朗區' => ['元朗', '天水圍', '錦田', '屏山'],
        '北區' => ['上水', '粉嶺', '沙頭角', '打鼓嶺'],
        '大埔區' => ['大埔', '太和', '船灣'],
        '沙田區' => ['沙田', '馬鞍山', '大圍', '火炭'],
        '西貢區' => ['西貢', '將軍澳', '坑口', '清水灣'],
        '葵青區' => ['葵涌', '青衣', '荔景'],
        '離島區' => ['大嶼山', '長洲', '梅窩', '坪洲', '南丫島', '東涌'],
    ];

    /** @return list<string> */
    public static function districtsFor(string $region): array
    {
        return self::DISTRICTS_BY_REGION[$region] ?? [];
    }

    /** @return list<string> */
    public static function areasFor(string $district): array
    {
        return self::AREAS_BY_DISTRICT[$district] ?? [];
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
        $area = trim((string) ($body['area'] ?? ''));
        $detail = trim((string) ($body['detail'] ?? ''));
        if ($receiver === '' || $phone === '' || $district === '' || $area === '' || $detail === '') {
            throw new ApiException('INVALID', '請填寫完整收貨資訊', 422, 42201);
        }
        if (!in_array($region, self::REGIONS, true)) {
            throw new ApiException('INVALID_REGION', '只支持香港地址（香港島 / 九龍 / 新界）', 422, 42210);
        }
        $allowed = self::districtsFor($region);
        if ($allowed && !in_array($district, $allowed, true)) {
            throw new ApiException('INVALID_DISTRICT', '請選擇對應大區下的分區', 422, 42211);
        }
        $areas = self::areasFor($district);
        if ($areas && !in_array($area, $areas, true)) {
            throw new ApiException('INVALID_AREA', '請選擇對應分區下的地點', 422, 42212);
        }
        $isDefault = !empty($body['is_default']) ? 1 : 0;
        $now = date('Y-m-d H:i:s');
        if ($isDefault) {
            Db::name('addresses')->where('member_id', $memberId)->update(['is_default' => 0, 'updated_at' => $now]);
        }
        $data = compact('receiver', 'phone', 'region', 'district', 'area', 'detail') + [
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
            'area'       => (string) ($row['area'] ?? ''),
            'detail'     => $row['detail'],
            'is_default' => (int) $row['is_default'] === 1,
        ];
    }
}
