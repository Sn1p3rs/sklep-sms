<?php
namespace App\Http\Controllers\Api\Server;

use App\View\Html\UnescapedSimpleText;
use App\Http\Responses\HtmlResponse;
use App\Routing\UrlGenerator;
use App\View\CurrentPage;
use App\System\Heart;
use App\System\License;
use App\Support\Template;
use App\Translation\TranslationManager;
use Symfony\Component\HttpFoundation\Request;

class ServiceLongDescriptionController
{
    public function get(
        $serviceId,
        Request $request,
        Template $template,
        Heart $heart,
        CurrentPage $currentPage,
        TranslationManager $translationManager,
        UrlGenerator $url,
        License $license
    ) {
        $lang = $translationManager->user();

        if ($request->query->get("popup")) {
            $link = $url->to("/api/server/services/{$serviceId}/long_description");
            $safeLink = str_replace('"', '\"', $link);
            $output = create_dom_element(
                "script",
                new UnescapedSimpleText(
                    'window.open("' . $safeLink . '", "", "height=720,width=1280");'
                ),
                [
                    'type' => "text/javascript",
                ]
            );

            return new HtmlResponse($output);
        }

        $body = "";
        $heart->pageTitle = $lang->t('description') . ": ";

        $serviceModule = $heart->getServiceModule($serviceId);
        if ($serviceModule) {
            $body = $serviceModule->descriptionLongGet();
            $heart->pageTitle .= $serviceModule->service->getName();
        }

        $heart->styleAdd($url->versioned("build/css/static/extra_stuff/long_desc.css"));
        $pageTitle = $heart->pageTitle;
        $header = $template->render(
            "header",
            compact('currentPage', 'heart', 'license', 'pageTitle')
        );

        $output = create_dom_element("html", [
            create_dom_element("head", new UnescapedSimpleText($header)),
            create_dom_element("body", new UnescapedSimpleText($body)),
        ]);

        return new HtmlResponse($output);
    }
}
