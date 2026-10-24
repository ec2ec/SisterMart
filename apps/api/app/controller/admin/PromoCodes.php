<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\AdminAcl;
use app\service\PromoCodeService;
use think\facade\Db;
use think\Response;

/** 一次性把庫內推廣編號補上 HMAC 校驗位（按 member id 重算） */
class PromoCodes extends ApiController
{
    public function rehash(): Response
    {
        return $this->run(function () {
            AdminAcl::assert(app('sistermart.admin'), AdminAcl::PERM_RELEASES);
            $rows = Db::name('members')->field('id,promo_code')->select()->toArray();
            $updated = 0;
            $unchanged = 0;
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $next = PromoCodeService::fromMemberId($id);
                if ((string) $row['promo_code'] === $next) {
                    $unchanged++;
                    continue;
                }
                Db::name('members')->where('id', $id)->update([
                    'promo_code' => $next,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $updated++;
            }

            return [
                'updated' => $updated,
                'unchanged' => $unchanged,
                'total' => count($rows),
                'sample' => $rows ? PromoCodeService::fromMemberId((int) $rows[0]['id']) : null,
            ];
        });
    }
}
