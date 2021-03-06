<?php
namespace App\Http\Middlewares;

use App\Requesting\Response as CustomResponse;
use App\Routing\UrlGenerator;
use App\System\Application;
use App\System\License;
use App\Support\Template;
use App\Translation\TranslationManager;
use App\Translation\Translator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockOnInvalidLicense implements MiddlewareContract
{
    /** @var Template */
    private $template;

    /** @var Translator */
    private $lang;

    /** @var UrlGenerator */
    private $url;

    public function __construct(
        Template $template,
        TranslationManager $translationManager,
        UrlGenerator $url
    ) {
        $this->template = $template;
        $this->lang = $translationManager->user();
        $this->url = $url;
    }

    public function handle(Request $request, Application $app, $args = null)
    {
        /** @var License $license */
        $license = $app->make(License::class);

        if (!$license->isValid()) {
            $e = $license->getLoadingException();
            $message = $this->getMessageFromInvalidResponse($e->response);

            if (starts_with($request->getPathInfo(), "/api")) {
                return new JsonResponse(compact('message'));
            }

            return $this->renderErrorPage($message);
        }

        return null;
    }

    private function getMessageFromInvalidResponse(CustomResponse $response = null)
    {
        if ($response) {
            if ($response->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
                return "Nieprawidłowy token licencji.";
            }

            if ($response->getStatusCode() === Response::HTTP_PAYMENT_REQUIRED) {
                return "Przekroczono limit stron WWW korzystających z licencji. Odczekaj 60 minut.";
            }
        }

        return $this->lang->t('verification_error');
    }

    private function renderErrorPage($message)
    {
        return new Response(
            $this->template->render("license/error", [
                'lang' => $this->lang,
                'message' => $message,
                'url' => $this->url,
            ])
        );
    }
}
