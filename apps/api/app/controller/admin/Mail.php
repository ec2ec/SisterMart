<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\controller\ApiController;
use app\service\MailService;
use app\support\ApiException;
use think\Response;

/** 郵件出站診斷／測試（超管）。 */
class Mail extends ApiController
{
    public function status(): Response
    {
        return $this->run(function () {
            $this->assertSuper();

            return (new MailService())->diagnostics();
        });
    }

    public function test(): Response
    {
        return $this->run(function () {
            $this->assertSuper();
            $to = strtolower(trim((string) ($this->payload()['to'] ?? '')));
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new ApiException('INVALID', '請提供有效收件郵箱 to', 422, 42201);
            }
            $result = (new MailService())->enqueue(
                $to,
                'SisterMart 郵件測試',
                '<p>這是一封 SisterMart SES/SMTP 測試信。若你收到此信，真實發信已接通。</p>',
                'mail_test',
                null,
            );

            return [
                'result' => $result,
                'diagnostics' => (new MailService())->diagnostics(),
            ];
        });
    }

    private function assertSuper(): void
    {
        $admin = app('sistermart.admin');
        $role = (string) ($admin['role'] ?? '');
        if ($role !== 'super') {
            throw new ApiException('FORBIDDEN', '僅超管可操作郵件診斷', 403, 40301);
        }
    }
}
