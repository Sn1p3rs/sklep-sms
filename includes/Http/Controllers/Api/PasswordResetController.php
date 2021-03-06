<?php
namespace App\Http\Controllers\Api;

use App\Exceptions\ValidationException;
use App\Http\Responses\ApiResponse;
use App\Loggers\DatabaseLogger;
use App\Repositories\UserRepository;
use App\System\Settings;
use App\Translation\TranslationManager;
use Symfony\Component\HttpFoundation\Request;

class PasswordResetController
{
    public function post(
        Request $request,
        TranslationManager $translationManager,
        Settings $settings,
        UserRepository $userRepository,
        DatabaseLogger $logger
    ) {
        $lang = $translationManager->user();

        $warnings = [];

        $uid = as_int($request->request->get('uid'));
        $sign = $request->request->get('sign');
        $pass = $request->request->get('pass');
        $passRepeat = $request->request->get('pass_repeat');

        if (!$sign || $sign != md5($uid . $settings->getSecret())) {
            return new ApiResponse("wrong_sign", $lang->t('wrong_sign'), 0);
        }

        if ($warning = check_for_warnings("password", $pass)) {
            $warnings['pass'] = array_merge((array) $warnings['pass'], $warning);
        }
        if ($pass != $passRepeat) {
            $warnings['pass_repeat'][] = $lang->t('different_values');
        }

        if ($warnings) {
            throw new ValidationException($warnings);
        }

        $userRepository->updatePassword($uid, $pass);
        $logger->log('reset_pass', $uid);

        return new ApiResponse("password_changed", $lang->t('password_changed'), 1);
    }
}
