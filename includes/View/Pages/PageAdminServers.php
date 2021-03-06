<?php
namespace App\View\Pages;

use App\Exceptions\UnauthorizedException;
use App\View\Html\BodyRow;
use App\View\Html\Cell;
use App\View\Html\HeadCell;
use App\View\Html\Input;
use App\View\Html\Structure;
use App\View\Html\Wrapper;
use App\View\Pages\Interfaces\IPageAdminActionBox;
use App\Repositories\PaymentPlatformRepository;
use App\ServiceModules\Interfaces\IServiceAvailableOnServers;
use App\Verification\Abstracts\SupportSms;

class PageAdminServers extends PageAdmin implements IPageAdminActionBox
{
    const PAGE_ID = 'servers';
    protected $privilege = 'manage_servers';

    /** @var PaymentPlatformRepository */
    private $paymentPlatformRepository;

    public function __construct(PaymentPlatformRepository $paymentPlatformRepository)
    {
        parent::__construct();

        $this->heart->pageTitle = $this->title = $this->lang->t('servers');
        $this->paymentPlatformRepository = $paymentPlatformRepository;
    }

    protected function content(array $query, array $body)
    {
        $wrapper = new Wrapper();
        $wrapper->setTitle($this->title);

        $table = new Structure();
        $table->addHeadCell(new HeadCell($this->lang->t('id'), "id"));
        $table->addHeadCell(new HeadCell($this->lang->t('name')));
        $table->addHeadCell(new HeadCell($this->lang->t('ip') . ':' . $this->lang->t('port')));
        $table->addHeadCell(new HeadCell($this->lang->t('platform')));
        $table->addHeadCell(new HeadCell($this->lang->t('version')));
        $table->addHeadCell(new HeadCell($this->lang->t('last_active_at')));

        foreach ($this->heart->getServers() as $server) {
            $bodyRow = new BodyRow();

            $bodyRow->setDbId($server->getId());
            $bodyRow->addCell(new Cell($server->getName()));
            $bodyRow->addCell(new Cell($server->getIp() . ':' . $server->getPort()));
            $bodyRow->addCell(new Cell($server->getType() ?: 'n/a'));
            $bodyRow->addCell(new Cell($server->getVersion() ?: 'n/a'));
            $bodyRow->addCell(new Cell($server->getLastActiveAt() ?: 'n/a'));

            if (get_privileges("manage_servers")) {
                $bodyRow->setDeleteAction(true);
                $bodyRow->setEditAction(true);
            }

            $table->addBodyRow($bodyRow);
        }

        $wrapper->setTable($table);

        if (get_privileges("manage_servers")) {
            $button = new Input();
            $button->setParam('id', 'server_button_add');
            $button->setParam('type', 'button');
            $button->addClass('button');
            $button->setParam('value', $this->lang->t('add_server'));
            $wrapper->addButton($button);
        }

        return $wrapper->toHtml();
    }

    public function getActionBox($boxId, array $query)
    {
        if (!get_privileges("manage_servers")) {
            throw new UnauthorizedException();
        }

        if ($boxId == "server_edit") {
            $server = $this->heart->getServer($query['id']);
        }

        $smsPlatforms = "";
        foreach ($this->paymentPlatformRepository->all() as $paymentPlatform) {
            $paymentModule = $this->heart->getPaymentModule($paymentPlatform);
            if ($paymentModule instanceof SupportSms) {
                $smsPlatforms .= create_dom_element("option", $paymentPlatform->getName(), [
                    'value' => $paymentPlatform->getId(),
                    'selected' =>
                        isset($server) && $paymentPlatform->getId() == $server->getSmsPlatformId()
                            ? "selected"
                            : "",
                ]);
            }
        }

        $services = "";
        foreach ($this->heart->getServices() as $service) {
            // Dana usługa nie może być kupiona na serwerze
            $serviceModule = $this->heart->getServiceModule($service->getId());
            if (!($serviceModule instanceof IServiceAvailableOnServers)) {
                continue;
            }

            $values = create_dom_element("option", $this->lang->strtoupper($this->lang->t('no')), [
                'value' => 0,
                'selected' =>
                    isset($server) &&
                    $this->heart->serverServiceLinked($server->getId(), $service->getId())
                        ? ""
                        : "selected",
            ]);

            $values .= create_dom_element(
                "option",
                $this->lang->strtoupper($this->lang->t('yes')),
                [
                    'value' => 1,
                    'selected' =>
                        isset($server) &&
                        $this->heart->serverServiceLinked($server->getId(), $service->getId())
                            ? "selected"
                            : "",
                ]
            );

            $name = $service->getId();
            $text = "{$service->getName()} ( {$service->getId()} )";

            $services .= $this->template->render(
                "tr_text_select",
                compact('name', 'text', 'values')
            );
        }

        switch ($boxId) {
            case "server_add":
                $output = $this->template->render(
                    "admin/action_boxes/server_add",
                    compact('smsPlatforms', 'services')
                );
                break;

            case "server_edit":
                $output = $this->template->render(
                    "admin/action_boxes/server_edit",
                    compact('server', 'smsPlatforms', 'services')
                );
                break;

            default:
                $output = '';
        }

        return [
            'status' => 'ok',
            'template' => $output,
        ];
    }
}
