<?php
namespace App\Http\Controllers\Api;

use App\Http\Responses\HtmlResponse;
use App\ServiceModules\Interfaces\IServiceUserOwnServicesEdit;
use App\Services\UserServiceService;
use App\System\Auth;
use App\System\Heart;
use App\System\Settings;
use App\Support\Template;
use App\Translation\TranslationManager;

class UserServiceEditFormController
{
    public function get(
        $userServiceId,
        TranslationManager $translationManager,
        Settings $settings,
        Auth $auth,
        Heart $heart,
        Template $template,
        UserServiceService $userServiceService
    ) {
        $lang = $translationManager->user();
        $user = $auth->user();

        // Użytkownik nie może edytować usługi
        if (!$settings['user_edit_service']) {
            return new HtmlResponse($lang->t('not_logged'));
        }

        $userService = $userServiceService->findOne($userServiceId);

        if (!$userService) {
            return new HtmlResponse($lang->t('dont_play_games'));
        }

        // Dany użytkownik nie jest właścicielem usługi o danym id
        if ($userService->getUid() !== $user->getUid()) {
            return new HtmlResponse($lang->t('dont_play_games'));
        }

        $serviceModule = $heart->getServiceModule($userService->getServiceId());
        if (!($serviceModule instanceof IServiceUserOwnServicesEdit)) {
            return new HtmlResponse($lang->t('service_cant_be_modified'));
        }

        $buttons = $template->render("services/my_services_savencancel");

        return new HtmlResponse($buttons . $serviceModule->userOwnServiceEditFormGet($userService));
    }
}
