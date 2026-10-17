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
            $q = Db::name('withdrawals')->order('id', 'desc')->limit(100);
            if ($status !== '') {
                $q->where('status', $status);
            }
            $rows = $q->select()->toArray();

            return [
                'items' => array_map(fn ($r) => [
                    'id' => (int) $r['id'],
                    'member_id' => (int) $r['member_id'],
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
                'reviewed_at' => $row['reviewed_at'],
            ];
        });
    }
}
