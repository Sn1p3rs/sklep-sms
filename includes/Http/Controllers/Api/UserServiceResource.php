<?php
namespace App\Http\Controllers\Api;

use App\Http\Responses\ApiResponse;
use App\ServiceModules\Interfaces\IServiceUserOwnServicesEdit;
use App\Services\UserServiceService;
use App\System\Auth;
use App\System\Heart;
use App\System\Settings;
use App\Translation\TranslationManager;
use Symfony\Component\HttpFoundation\Request;

class UserServiceResource
{
    public function put(
        $userServiceId,
        Request $request,
        TranslationManager $translationManager,
        Heart $heart,
        Auth $auth,
        Settings $settings,
        UserServiceService $userServiceService
    ) {
        $lang = $translationManager->user();
        $user = $auth->user();

        $userService = $userServiceService->findOne($userServiceId);

        if (!$userService) {
            return new ApiResponse("dont_play_games", $lang->t('dont_play_games'), 0);
        }

        if ($userService->getUid() !== $user->getUid()) {
            return new ApiResponse("dont_play_games", $lang->t('dont_play_games'), 0);
        }

        $serviceModule = $heart->getServiceModule($userService->getServiceId());
        if (!$serviceModule) {
            return new ApiResponse("wrong_module", $lang->t('bad_module'), 0);
        }

        if (
            !$settings['user_edit_service'] ||
            !($serviceModule instanceof IServiceUserOwnServicesEdit)
        ) {
            return new ApiResponse(
                "service_cant_be_modified",
                $lang->t('service_cant_be_modified'),
                0
            );
        }

        $returnData = $serviceModule->userOwnServiceEdit(
            array_merge($request->request->all(), [
                "id" => $userServiceId,
            ]),
            $userService
        );

        if ($returnData['status'] == "warnings") {
            $returnData["data"]["warnings"] = format_warnings($returnData["data"]["warnings"]);
        }

        return new ApiResponse(
            $returnData['status'],
            $returnData['text'],
            $returnData['positive'],
            $returnData['data']
        );
    }
}
