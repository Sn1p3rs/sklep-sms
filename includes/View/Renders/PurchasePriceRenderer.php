<?php
namespace App\View\Renders;

use App\Models\Service;
use App\Services\PriceTextService;
use App\System\Settings;
use App\Support\Template;
use App\Translation\TranslationManager;
use App\Translation\Translator;

class PurchasePriceRenderer
{
    /** @var Settings */
    private $settings;

    /** @var Template */
    private $template;

    /** @var Translator */
    private $lang;

    /** @var PriceTextService */
    private $priceTextService;

    public function __construct(
        Settings $settings,
        TranslationManager $translationManager,
        PriceTextService $priceTextService,
        Template $template
    ) {
        $this->settings = $settings;
        $this->template = $template;
        $this->lang = $translationManager->user();
        $this->priceTextService = $priceTextService;
    }

    public function render(array $price, Service $service)
    {
        $priceId = $price['id'];
        $smsPrice = $this->priceTextService->getPriceGrossText($price['sms_price']);
        $transferPrice = $this->priceTextService->getPriceText($price['transfer_price']);
        $quantity = $this->priceTextService->getQuantityText($price['quantity'], $service);

        return $this->template->renderNoComments(
            "purchase/purchase_price",
            compact('priceId', 'quantity', 'smsPrice', 'transferPrice')
        );
    }
}
