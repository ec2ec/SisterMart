<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\MaintenanceService;
use app\service\RegistrationConfigService;
use app\service\TokenService;
use app\service\TradeSettings;
use think\facade\Db;
use think\Response;

class ShopStatus extends ApiController
{
    public function index(): Response
    {
        return $this->run(function () {
            $memberId = null;
            $token = TokenService::bearer($this->request);
            if ($token !== '') {
                $id = TokenService::ownerId('member_tokens', 'member_id', $token);
                if ($id !== null) {
                    $ok = Db::name('members')->where('id', $id)->where('status', 1)->value('id');
                    if ($ok) {
                        $memberId = (int) $id;
                    }
                }
            }

            $maintenance = (new MaintenanceService())->publicStatus($memberId);
            $registration = (new RegistrationConfigService())->publicStatus();

            return array_merge($maintenance, $registration, [
                'cs_whatsapp' => TradeSettings::csWhatsapp(),
                'cs_email' => TradeSettings::csEmail(),
            ]);
        });
    }
}
