<?php

declare(strict_types=1);

namespace app\service;

use app\support\ApiException;
use think\facade\Db;

class ArticleService
{
    /**
     * @param bool $withBody false 時列表不帶正文（後台列表用，避免大 HTML／富文本卡死）
     */
    public function list(bool $activeOnly = false, bool $withBody = true): array
    {
        $q = Db::name('articles')->order('sort_order', 'asc')->order('id', 'asc');
        if ($activeOnly) {
            $q->where('status', 1);
        }
        $rows = $q->select()->toArray();

        return array_map(fn ($row) => $this->present($row, $withBody), $rows);
    }

    public function byCode(string $code, bool $activeOnly = true): array
    {
        $row = Db::name('articles')->where('code', $code)->find();
        if (!$row || ($activeOnly && (int) $row['status'] !== 1)) {
            throw new ApiException('NOT_FOUND', '文章不存在', 404, 40403);
        }

        return $this->present($row, true);
    }

    public function save(array $body, ?int $id = null): array
    {
        $code = trim((string) ($body['code'] ?? ''));
        if ($code === '' || !preg_match('/^[a-z0-9-]{1,64}$/', $code)) {
            throw new ApiException('INVALID', '文章代碼不正確', 422, 42201);
        }
        $titleZh = trim((string) ($body['title']['zh-HK'] ?? ''));
        $titleEn = trim((string) ($body['title']['en'] ?? ''));
        $bodyZh = (string) ($body['body']['zh-HK'] ?? '');
        $bodyEn = (string) ($body['body']['en'] ?? '');
        if ($titleZh === '' || $titleEn === '') {
            throw new ApiException('INVALID', '請填寫雙語標題', 422, 42201);
        }
        $dup = Db::name('articles')->where('code', $code);
        if ($id) {
            $dup->where('id', '<>', $id);
        }
        if ($dup->find()) {
            throw new ApiException('CODE_TAKEN', '文章代碼已存在', 422, 42202);
        }
        $now = date('Y-m-d H:i:s');
        $data = [
            'code'           => $code,
            'status'         => (int) ($body['status'] ?? 1) === 0 ? 0 : 1,
            'show_in_footer' => (int) ($body['show_in_footer'] ?? 0) ? 1 : 0,
            'sort_order'     => (int) ($body['sort'] ?? 0),
            'updated_at'     => $now,
        ];
        if ($id) {
            if (!Db::name('articles')->where('id', $id)->find()) {
                throw new ApiException('NOT_FOUND', '文章不存在', 404, 40403);
            }
            Db::name('articles')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = $now;
            $id = (int) Db::name('articles')->insertGetId($data);
        }
        foreach (['zh-HK' => [$titleZh, $bodyZh], 'en' => [$titleEn, $bodyEn]] as $locale => [$title, $text]) {
            $exists = Db::name('article_i18n')->where(['article_id' => $id, 'locale' => $locale])->find();
            if ($exists) {
                Db::name('article_i18n')->where(['article_id' => $id, 'locale' => $locale])->update(['title' => $title, 'body' => $text]);
            } else {
                Db::name('article_i18n')->insert(['article_id' => $id, 'locale' => $locale, 'title' => $title, 'body' => $text]);
            }
        }

        return $this->byCode($code, false);
    }

    private function present(array $row, bool $withBody = true): array
    {
        $i18n = Db::name('article_i18n')->where('article_id', $row['id'])->select()->toArray();
        $title = ['zh-HK' => '', 'en' => ''];
        $body = ['zh-HK' => '', 'en' => ''];
        $hasBody = false;
        foreach ($i18n as $n) {
            $locale = (string) $n['locale'];
            $title[$locale] = (string) $n['title'];
            $text = (string) $n['body'];
            if (trim(strip_tags($text)) !== '') {
                $hasBody = true;
            }
            if ($withBody) {
                $body[$locale] = $text;
            }
        }

        return [
            'id'             => (int) $row['id'],
            'code'           => trim((string) $row['code']),
            'status'         => (int) $row['status'],
            'show_in_footer' => (int) $row['show_in_footer'] === 1,
            'sort'           => (int) $row['sort_order'],
            'title'          => $title,
            'body'           => $withBody ? $body : ['zh-HK' => '', 'en' => ''],
            'has_body'       => $hasBody,
            'updated_at'     => !empty($row['updated_at']) ? (string) $row['updated_at'] : null,
            'created_at'     => !empty($row['created_at']) ? (string) $row['created_at'] : null,
        ];
    }
}
