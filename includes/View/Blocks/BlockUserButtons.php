<?php
namespace App\View\Blocks;

use App\Routing\UrlGenerator;
use App\System\Auth;
use App\System\Heart;
use App\Support\Template;
use App\Translation\TranslationManager;

class BlockUserButtons extends Block
{
    public function getContentClass()
    {
        return is_logged() ? "user_buttons" : "loginarea";
    }

    public function getContentId()
    {
        return "user_buttons";
    }

    protected function content(array $query, array $body)
    {
        /** @var Auth $auth */
        $auth = app()->make(Auth::class);
        $user = $auth->user();

        /** @var Template $template */
        $template = app()->make(Template::class);

        /** @var TranslationManager $translationManager */
        $translationManager = app()->make(TranslationManager::class);
        $lang = $translationManager->user();

        /** @var Heart $heart */
        $heart = app()->make(Heart::class);

        /** @var UrlGenerator $url */
        $url = app()->make(UrlGenerator::class);

        if (!$auth->check()) {
            return $template->render("loginarea");
        }

        $acpButton = "";

        // Panel Admina
        if (get_privileges("acp", $user)) {
            $acpButton = create_dom_element(
                "li",
                create_dom_element("a", $lang->t('acp'), [
                    'href' => $url->to("/admin"),
                ])
            );
        }

        // Doładowanie portfela
        if ($heart->userCanUseService($user->getUid(), $heart->getService("charge_wallet"))) {
            $chargeWalletButton = create_dom_element(
                "li",
                create_dom_element("a", $lang->t('charge_wallet'), [
                    'href' => $url->to("/page/purchase?service=charge_wallet"),
                ])
            );
        }

        return $template->render("user_buttons", compact('acpButton', 'chargeWalletButton'));
    }
}
