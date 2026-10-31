<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

/**
 * 供應商主檔（一品一供；停用後不可新綁，已綁商品保留）。
 */
class SupplierService
{
    public const TYPES = [
        'factory' => '工廠',
        'wholesaler' => '批發商',
        'dropship' => '代發貨',
        'bulk' => '散貨',
        'local_stall' => '本地檔口',
    ];

    public const MAX_CODE_LEN = 16;

    /** @return list<array<string, mixed>> */
    public function list(bool $activeOnly = false): array
    {
        $q = Db::name('suppliers')->order('id', 'asc');
        if ($activeOnly) {
            $q->where('status', 1);
        }
        $rows = $q->select()->toArray();

        return array_map(fn ($r) => $this->present($r), $rows);
    }

    public function get(int $id): array
    {
        $row = Db::name('suppliers')->where('id', $id)->find();
        if (!$row) {
            throw new ApiException('NOT_FOUND', '供應商不存在', 404, 40402);
        }

        return $this->present($row);
    }

    /** @param array<string, mixed> $body */
    public function save(array $body, ?int $id = null): array
    {
        $code = strtoupper(trim((string) ($body['code'] ?? '')));
        if ($code === '' || !preg_match('/^[A-Z0-9_-]{1,' . self::MAX_CODE_LEN . '}$/', $code)) {
            throw new ApiException('INVALID', '供應商簡碼須為 1–' . self::MAX_CODE_LEN . ' 位大寫字母/數字/_/-', 422, 42201);
        }
        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            throw new ApiException('INVALID', '請填寫供應商全名', 422, 42201);
        }
        $type = trim((string) ($body['type'] ?? 'factory'));
        if (!isset(self::TYPES[$type])) {
            throw new ApiException('INVALID', '供應商類型不正確', 422, 42201);
        }
        $status = (int) ($body['status'] ?? 1) === 0 ? 0 : 1;
        $remark = $this->nullableTrim($body['remark'] ?? null, 500);
        $contact = $this->nullableTrim($body['contact_person'] ?? null, 80);
        $phone = $this->nullableTrim($body['phone'] ?? null, 40);
        $whatsapp = $this->nullableTrim($body['whatsapp'] ?? null, 40);
        $email = $this->nullableTrim($body['email'] ?? null, 120);
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('INVALID', '聯絡郵箱格式不正確', 422, 42201);
        }
        $address = $this->nullableTrim($body['address'] ?? null, 255);

        $dup = Db::name('suppliers')->where('code', $code);
        if ($id) {
            $dup->where('id', '<>', $id);
        }
        if ($dup->find()) {
            throw new ApiException('CODE_TAKEN', '供應商簡碼已存在', 422, 42202);
        }

        $now = date('Y-m-d H:i:s');
        $data = [
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'status' => $status,
            'remark' => $remark,
            'contact_person' => $contact,
            'phone' => $phone,
            'whatsapp' => $whatsapp,
            'email' => $email,
            'address' => $address,
            'updated_at' => $now,
        ];

        if ($id) {
            if (!Db::name('suppliers')->where('id', $id)->find()) {
                throw new ApiException('NOT_FOUND', '供應商不存在', 404, 40402);
            }
            Db::name('suppliers')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = $now;
            $id = (int) Db::name('suppliers')->insertGetId($data);
        }

        return $this->get($id);
    }

    /**
     * 商品綁定校驗：一品一供永久綁定。
     * 新建必須選啟用供應商；編輯不可更換（換供應商＝新建商品）。
     * 供應商停用後已綁商品保留，不要求換綁。
     *
     * @return array{id: int, code: string, name: string, type: string, status: int}
     */
    public function assertBindable(int $supplierId, ?int $currentSupplierId = null): array
    {
        if ($supplierId <= 0) {
            throw new ApiException('INVALID', '請選擇供應商', 422, 42201);
        }
        if ($currentSupplierId !== null && $currentSupplierId > 0 && $currentSupplierId !== $supplierId) {
            throw new ApiException('INVALID', '商品供應商不可更換；換供請新建商品', 422, 42201);
        }
        $row = Db::name('suppliers')->where('id', $supplierId)->find();
        if (!$row) {
            throw new ApiException('INVALID', '供應商不存在', 422, 42201);
        }
        $isNewBind = $currentSupplierId === null || $currentSupplierId <= 0;
        if ($isNewBind && (int) $row['status'] !== 1) {
            throw new ApiException('INVALID', '停用供應商不可綁定商品', 422, 42201);
        }

        return [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'type' => (string) $row['type'],
            'status' => (int) $row['status'],
        ];
    }

    /** @return array<string, string> */
    public function typeLabels(): array
    {
        return self::TYPES;
    }

    /** @param array<string, mixed> $row */
    private function present(array $row): array
    {
        $type = (string) ($row['type'] ?? 'factory');

        return [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'type' => $type,
            'type_label' => self::TYPES[$type] ?? $type,
            'status' => (int) $row['status'],
            'remark' => $row['remark'] !== null && $row['remark'] !== '' ? (string) $row['remark'] : null,
            'contact_person' => $row['contact_person'] !== null && $row['contact_person'] !== ''
                ? (string) $row['contact_person'] : null,
            'phone' => $row['phone'] !== null && $row['phone'] !== '' ? (string) $row['phone'] : null,
            'whatsapp' => $row['whatsapp'] !== null && $row['whatsapp'] !== '' ? (string) $row['whatsapp'] : null,
            'email' => $row['email'] !== null && $row['email'] !== '' ? (string) $row['email'] : null,
            'address' => $row['address'] !== null && $row['address'] !== '' ? (string) $row['address'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function nullableTrim(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        if (mb_strlen($s) > $max) {
            throw new ApiException('INVALID', '欄位長度不可超過 ' . $max, 422, 42201);
        }

        return $s;
    }
}
