<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\DistributionService;
use think\facade\Db;
use think\Response;

class Withdrawals extends ApiController
{
    public function index(): Response
    {
        return $this->run(function () {
            $status = (string) $this->request->get('status', '');
            $channel = (string) $this->request->get('channel', '');
            $q = (string) $this->request->get('q', '');
            $limit = max(1, min(500, (int) $this->request->get('limit', 200)));

            $query = Db::name('withdrawals')->alias('w')
                ->leftJoin('members m', 'm.id = w.member_id')
                ->field('w.*, m.nickname AS member_nickname, m.phone AS member_phone, m.email AS member_email, m.commission_available')
                ->order('w.id', 'desc')
                ->limit($limit);
            if ($status !== '') {
                $query->where('w.status', $status);
            }
            if ($channel !== '' && in_array($channel, ['fps', 'balance'], true)) {
                $query->where('w.channel', $channel);
            }
            if ($q !== '') {
                $like = '%' . addcslashes($q, '%_\\') . '%';
                $query->where(function ($w) use ($like, $q) {
                    $w->whereLike('w.fps_id', $like)
                        ->whereOr('w.fps_name', 'like', $like)
                        ->whereOr('m.nickname', 'like', $like)
                        ->whereOr('m.phone', 'like', $like)
                        ->whereOr('m.email', 'like', $like);
                    if (ctype_digit($q)) {
                        $w->whereOr('w.member_id', (int) $q)->whereOr('w.id', (int) $q);
                    }
                });
            }
            $rows = $query->select()->toArray();
            $pendingCount = (int) Db::name('withdrawals')->where('status', 'pending_review')->count();

            return [
                'pending_count' => $pendingCount,
                'items' => array_map(fn ($r) => [
                    'id' => (int) $r['id'],
                    'member_id' => (int) $r['member_id'],
                    'member_nickname' => (string) ($r['member_nickname'] ?? ''),
                    'member_phone' => (string) ($r['member_phone'] ?? ''),
                    'member_email' => (string) ($r['member_email'] ?? ''),
                    'commission_available' => (float) ($r['commission_available'] ?? 0),
                    'channel' => $r['channel'],
                    'amount' => (float) $r['amount'],
                    'status' => $r['status'],
                    'fps_name' => $r['fps_name'],
                    'fps_id' => $r['fps_id'],
                    'review_note' => $r['review_note'],
                    'created_at' => $r['created_at'],
                    'reviewed_at' => $r['reviewed_at'],
                ], $rows),
            ];
        });
    }

    public function review(int $id): Response
    {
        return $this->run(function () use ($id) {
            $body = $this->payload();
            $action = (string) ($body['action'] ?? '');
            $note = trim((string) ($body['note'] ?? ''));
            $row = (new DistributionService())->reviewWithdrawal($id, $action, $note);

            return [
                'id' => (int) $row['id'],
                'status' => $row['status'],
                'amount' => (float) $row['amount'],
                'channel' => $row['channel'],
                'review_note' => $row['review_note'] ?? null,
                'reviewed_at' => $row['reviewed_at'],
            ];
        });
    }
}
