<?php
namespace App\Verification\PaymentModules;

use App\Loggers\FileLogger;
use App\Models\PaymentPlatform;
use App\Models\SmsNumber;
use App\Requesting\Requester;
use App\Support\Database;
use App\Verification\Abstracts\PaymentModule;
use App\Verification\Abstracts\SupportSms;
use App\Verification\DataField;
use App\Verification\Exceptions\BadCodeException;
use App\Verification\Exceptions\BadNumberException;
use App\Verification\Exceptions\ExternalErrorException;
use App\Verification\Exceptions\NoConnectionException;
use App\Verification\Exceptions\ServerErrorException;
use App\Verification\Exceptions\UnknownErrorException;
use App\Verification\Exceptions\WrongCredentialsException;
use App\Verification\Results\SmsSuccessResult;

class OneShotOneKill extends PaymentModule implements SupportSms
{
    const MODULE_ID = "1s1k";

    /** @var FileLogger */
    private $fileLogger;

    public function __construct(
        Database $database,
        Requester $requester,
        PaymentPlatform $paymentPlatform,
        FileLogger $fileLogger
    ) {
        parent::__construct($database, $requester, $paymentPlatform);
        $this->fileLogger = $fileLogger;
    }

    public static function getDataFields()
    {
        return [new DataField("api")];
    }

    public static function getSmsNumbers()
    {
        return [
            new SmsNumber("7136", 65),
            new SmsNumber("7255", 130),
            new SmsNumber("7355", 195),
            new SmsNumber("7455", 260),
            new SmsNumber("7555", 325),
            new SmsNumber("7636", 390),
            new SmsNumber("77464", 455),
            new SmsNumber("78464", 520),
            new SmsNumber("7936", 585),
            new SmsNumber("91055", 650),
            new SmsNumber("91155", 715),
            new SmsNumber("91455", 910),
            new SmsNumber("91664", 1040),
            new SmsNumber("91955", 1235),
            new SmsNumber("92055", 1300),
            new SmsNumber("92555", 1625),
        ];
    }

    public function verifySms($returnCode, $number)
    {
        $response = $this->requester->get('http://www.1shot1kill.pl/api', [
            'type' => 'sms',
            'key' => $this->getApi(),
            'sms_code' => $returnCode,
            'comment' => '',
        ]);

        if ($response === false) {
            throw new NoConnectionException();
        }

        $content = $response->json();
        if (!is_array($content)) {
            throw new ServerErrorException();
        }

        $responseNumber = $this->getSmsNumberByPrice((int) ($content['amount'] * 100));

        if ($responseNumber === null) {
            $this->fileLogger->error("1s1k invalid amount [{$content['amount']}]");
            throw new ServerErrorException();
        }

        switch ($content['status']) {
            case 'ok':
                if ($responseNumber == $number) {
                    return new SmsSuccessResult();
                }

                throw new BadNumberException(get_sms_cost($responseNumber));

            case 'fail':
                throw new BadCodeException();

            case 'error':
                switch ($content['desc']) {
                    case 'internal api error':
                        throw new ExternalErrorException();

                    case 'wrong api type':
                    case 'wrong api key':
                        throw new WrongCredentialsException();
                }

                throw new UnknownErrorException($content['desc']);

            default:
                throw new UnknownErrorException();
        }
    }

    public function getSmsCode()
    {
        return 'SHOT';
    }

    private function getApi()
    {
        return $this->getData('api');
    }

    private function getSmsNumberByPrice($price)
    {
        foreach (OneShotOneKill::getSmsNumbers() as $smsNumber) {
            if ($smsNumber->getPrice() === $price) {
                return $smsNumber->getNumber();
            }
        }

        return null;
    }
}
