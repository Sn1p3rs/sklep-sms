<?php
namespace App\ServiceModules\ChargeWallet;

use App\Models\Purchase;
use App\Models\Service;
use App\Payment\BoughtServiceService;
use App\Repositories\SmsPriceRepository;
use App\ServiceModules\Interfaces\IServicePurchase;
use App\ServiceModules\Interfaces\IServicePurchaseWeb;
use App\ServiceModules\ServiceModule;
use App\Services\PriceTextService;
use App\Services\SmsPriceService;
use App\System\Auth;
use App\System\Heart;
use App\System\Settings;
use App\Translation\TranslationManager;
use App\Translation\Translator;
use App\Verification\Abstracts\SupportSms;
use App\View\Interfaces\IBeLoggedMust;

class ChargeWalletServiceModule extends ServiceModule implements
    IServicePurchase,
    IServicePurchaseWeb,
    IBeLoggedMust
{
    const MODULE_ID = "charge_wallet";

    /** @var Auth */
    private $auth;

    /** @var Heart */
    private $heart;

    /** @var Translator */
    private $lang;

    /** @var Settings */
    private $settings;

    /** @var BoughtServiceService */
    private $boughtServiceService;

    /** @var SmsPriceRepository */
    private $smsPriceRepository;

    /** @var SmsPriceService */
    private $smsPriceService;

    /** @var PriceTextService */
    private $priceTextService;

    public function __construct(Service $service = null)
    {
        parent::__construct($service);

        /** @var TranslationManager $translationManager */
        $translationManager = $this->app->make(TranslationManager::class);
        $this->lang = $translationManager->user();
        $this->auth = $this->app->make(Auth::class);
        $this->heart = $this->app->make(Heart::class);
        $this->settings = $this->app->make(Settings::class);
        $this->boughtServiceService = $this->app->make(BoughtServiceService::class);
        $this->smsPriceRepository = $this->app->make(SmsPriceRepository::class);
        $this->smsPriceService = $this->app->make(SmsPriceService::class);
        $this->priceTextService = $this->app->make(PriceTextService::class);
    }

    public function purchaseFormGet(array $query)
    {
        $optionSms = '';
        $optionTransfer = '';
        $smsBody = '';
        $transferBody = '';

        if ($this->settings->getSmsPlatformId()) {
            $paymentModule = $this->heart->getPaymentModuleByPlatformId(
                $this->settings->getSmsPlatformId()
            );

            if ($paymentModule instanceof SupportSms) {
                $optionSms = $this->template->render("services/charge_wallet/option_sms");

                $smsList = [];
                foreach ($paymentModule::getSmsNumbers() as $smsNumber) {
                    $provision = number_format($smsNumber->getProvision() / 100.0, 2);
                    $smsList[] = create_dom_element(
                        "option",
                        $this->lang->t(
                            'charge_sms_option',
                            $this->priceTextService->getPriceGrossText($smsNumber->getPrice()),
                            $this->settings->getCurrency(),
                            $provision,
                            $this->settings->getCurrency()
                        ),
                        [
                            'value' => $smsNumber->getPrice(),
                        ]
                    );
                }

                $smsBody = $this->template->render("services/charge_wallet/sms_body", [
                    'smsList' => implode("", $smsList),
                ]);
            }
        }

        if ($this->settings->getTransferPlatformId()) {
            $optionTransfer = $this->template->render("services/charge_wallet/option_transfer");
            $transferBody = $this->template->render("services/charge_wallet/transfer_body");
        }

        return $this->template->render(
            "services/charge_wallet/purchase_form",
            compact('optionSms', 'optionTransfer', 'smsBody', 'transferBody') + [
                'serviceId' => $this->service->getId(),
            ]
        );
    }

    public function purchaseFormValidate(Purchase $purchase, array $body)
    {
        $method = array_get($body, 'method');
        $smsPrice = as_int(array_get($body, 'sms_price'));
        $transferPrice = array_get($body, 'transfer_price');

        if (!$this->auth->check()) {
            return [
                'status' => "no_access",
                'text' => $this->lang->t('not_logged_or_no_perm'),
                'positive' => false,
            ];
        }

        // There are only two allowed ways to charge wallet
        if (!in_array($method, [Purchase::METHOD_SMS, Purchase::METHOD_TRANSFER])) {
            return [
                'status' => "wrong_method",
                'text' => $this->lang->t('wrong_charge_method'),
                'positive' => false,
            ];
        }

        $warnings = [];

        if ($method == Purchase::METHOD_SMS) {
            if (!$smsPrice || !$this->smsPriceRepository->exists($smsPrice)) {
                $warnings['sms_price'][] = $this->lang->t('charge_amount_not_chosen');
            }
        } elseif ($method == Purchase::METHOD_TRANSFER) {
            if ($warning = check_for_warnings("number", $transferPrice)) {
                $warnings['transfer_price'] = array_merge(
                    (array) $warnings['transfer_price'],
                    $warning
                );
            }

            if ($transferPrice <= 1) {
                $warnings['transfer_price'][] = $this->lang->t(
                    'charge_amount_too_low',
                    "1.00 " . $this->settings->getCurrency()
                );
            }
        }

        if ($warnings) {
            return [
                'status' => "warnings",
                'text' => $this->lang->t('form_wrong_filled'),
                'positive' => false,
                'data' => ['warnings' => $warnings],
            ];
        }

        $purchase->setService($this->service->getId());
        $purchase->setPayment([
            Purchase::PAYMENT_WALLET_DISABLED => true,
        ]);

        if ($method == Purchase::METHOD_SMS) {
            $smsPaymentModule = $this->heart->getPaymentModuleByPlatformId(
                $purchase->getPayment(Purchase::PAYMENT_SMS_PLATFORM)
            );

            if ($smsPaymentModule instanceof SupportSms) {
                $purchase->setPayment([
                    Purchase::PAYMENT_SMS_PRICE => $smsPrice,
                    Purchase::PAYMENT_TRANSFER_DISABLED => true,
                ]);
                $purchase->setOrder([
                    Purchase::ORDER_QUANTITY => $this->smsPriceService->getProvision(
                        $smsPrice,
                        $smsPaymentModule
                    ),
                ]);
            }
        } elseif ($method == Purchase::METHOD_TRANSFER) {
            $purchase->setPayment([
                Purchase::PAYMENT_TRANSFER_PRICE => $transferPrice * 100,
                Purchase::PAYMENT_SMS_DISABLED => true,
            ]);
            $purchase->setOrder([
                Purchase::ORDER_QUANTITY => $transferPrice * 100,
            ]);
        }

        return [
            'status' => "ok",
            'text' => $this->lang->t('purchase_form_validated'),
            'positive' => true,
        ];
    }

    public function orderDetails(Purchase $purchase)
    {
        $quantity = number_format($purchase->getOrder(Purchase::ORDER_QUANTITY) / 100, 2);

        return $this->template->renderNoComments(
            "services/charge_wallet/order_details",
            compact('quantity')
        );
    }

    public function purchase(Purchase $purchase)
    {
        $this->chargeWallet(
            $purchase->user->getUid(),
            $purchase->getOrder(Purchase::ORDER_QUANTITY)
        );

        return $this->boughtServiceService->create(
            $purchase->user->getUid(),
            $purchase->user->getUsername(),
            $purchase->user->getLastIp(),
            $purchase->getPayment(Purchase::PAYMENT_METHOD),
            $purchase->getPayment(Purchase::PAYMENT_PAYMENT_ID),
            $this->service->getId(),
            0,
            number_format($purchase->getOrder(Purchase::ORDER_QUANTITY) / 100, 2),
            $purchase->user->getUsername(),
            $purchase->getEmail()
        );
    }

    public function purchaseInfo($action, array $data)
    {
        $cost = $data['cost'] ?: 0;
        $data['amount'] .= ' ' . $this->settings->getCurrency();
        $data['cost'] = number_format($cost / 100, 2) . ' ' . $this->settings->getCurrency();

        if ($action == "web") {
            if ($data['payment'] == Purchase::METHOD_SMS) {
                $desc = $this->lang->t('wallet_was_charged', $data['amount']);
                return $this->template->renderNoComments(
                    "services/charge_wallet/web_purchase_info_sms",
                    compact('desc', 'data')
                );
            }

            if ($data['payment'] == Purchase::METHOD_TRANSFER) {
                return $this->template->renderNoComments(
                    "services/charge_wallet/web_purchase_info_transfer",
                    compact('data')
                );
            }

            return '';
        }

        if ($action == "payment_log") {
            return [
                'text' => $this->lang->t('wallet_was_charged', $data['amount']),
                'class' => "income",
            ];
        }

        return '';
    }

    public function descriptionShortGet()
    {
        return $this->service->getDescription();
    }

    /**
     * @param int $uid
     * @param int $quantity
     */
    private function chargeWallet($uid, $quantity)
    {
        $this->db
            ->statement("UPDATE `ss_users` " . "SET `wallet` = `wallet` + ? " . "WHERE `uid` = ?")
            ->execute([$quantity, $uid]);
    }
}
