<?php

declare(strict_types=1);

namespace app\controller\api;

use app\controller\ApiController;
use app\service\MaintenanceService;
use app\service\RegistrationConfigService;
use app\service\TradeSettings;
use think\Response;

class ShopStatus extends ApiController
{
    public function index(): Response
    {
        return $this->run(function () {
            $maintenance = (new MaintenanceService())->publicStatus();
            $registration = (new RegistrationConfigService())->publicStatus();

            return array_merge($maintenance, $registration, [
                'cs_whatsapp' => TradeSettings::csWhatsapp(),
                'cs_email' => TradeSettings::csEmail(),
            ]);
        });
    }
}
