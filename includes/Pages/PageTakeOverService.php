<?php
namespace App\Pages;

use App\Interfaces\IBeLoggedMust;
use App\Services\Interfaces\IServiceTakeOver;

class PageTakeOverService extends Page implements IBeLoggedMust
{
    const PAGE_ID = 'service_take_over';

    public function __construct()
    {
        parent::__construct();

        $this->heart->pageTitle = $this->title = $this->lang->translate('take_over_service');
    }

    protected function content(array $query, array $body)
    {
        $servicesOptions = "";
        $services = $this->heart->getServices();
        foreach ($services as $service) {
            if (($serviceModule = $this->heart->getServiceModule($service['id'])) === null) {
                continue;
            }

            // Moduł danej usługi nie zezwala na jej przejmowanie
            if (!($serviceModule instanceof IServiceTakeOver)) {
                continue;
            }

            $servicesOptions .= create_dom_element("option", $service['name'], [
                'value' => $service['id'],
            ]);
        }

        return $this->template->render("service_take_over", compact('servicesOptions'));
    }
}