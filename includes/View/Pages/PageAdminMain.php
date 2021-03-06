<?php
namespace App\View\Pages;

use App\Http\Services\IncomeService;
use App\Models\Server;
use App\Requesting\Requester;
use App\Services\PriceTextService;
use App\System\License;
use App\Support\Version;
use App\View\Html\UnescapedSimpleText;

class PageAdminMain extends PageAdmin
{
    const PAGE_ID = "home";
    const LICENSE_EXPIRE_THRESHOLD = 4 * 24 * 60 * 60;

    /** @var Version */
    private $version;

    /** @var License */
    private $license;

    /** @var Requester */
    private $requester;

    /** @var IncomeService */
    private $incomeService;

    /** @var PriceTextService */
    private $priceTextService;

    public function __construct(
        Version $version,
        License $license,
        Requester $requester,
        IncomeService $incomeService,
        PriceTextService $priceTextService
    ) {
        parent::__construct();

        $this->heart->pageTitle = $this->title = $this->lang->t('main_page');
        $this->version = $version;
        $this->license = $license;
        $this->requester = $requester;
        $this->incomeService = $incomeService;
        $this->priceTextService = $priceTextService;
    }

    protected function content(array $query, array $body)
    {
        $bricks = $this->getBricks();
        $notes = $this->getNotes();

        return $this->template->render("admin/home", compact('notes', 'bricks'));
    }

    private function getNotes()
    {
        $notes = [];

        // No license note
        if (!$this->license->isValid()) {
            $settingsUrl = $this->url->to("/admin/settings");
            $notes[] = $this->createNote(
                $this->lang->t('license_error', $settingsUrl),
                "is-danger"
            );
        }

        $expireSeconds = strtotime($this->license->getExpires()) - time();
        if (
            !$this->license->isForever() &&
            $expireSeconds >= 0 &&
            $expireSeconds < self::LICENSE_EXPIRE_THRESHOLD
        ) {
            $notes[] = $this->createNote(
                $this->lang->t(
                    'license_soon_expire',
                    seconds_to_time(strtotime($this->license->getExpires()) - time())
                ),
                "is-danger"
            );
        }

        $newestVersion = $this->version->getNewestWeb();
        $newestAmxxVersion = $this->version->getNewestAmxmodx();
        $newestSmVersion = $this->version->getNewestSourcemod();

        if ($this->app->version() !== $newestVersion) {
            $updateWebLink = $this->url->to("/admin/update_web");

            $notes[] = $this->createNote(
                $this->lang->t(
                    'update_available',
                    htmlspecialchars($newestVersion),
                    $updateWebLink
                ),
                "is-success"
            );
        }

        $serversCount = 0;
        foreach ($this->heart->getServers() as $server) {
            if (!$this->isServerNewest($server, $newestAmxxVersion, $newestSmVersion)) {
                $serversCount += 1;
            }
        }

        if ($serversCount) {
            $updateServersLink = $this->url->to("/admin/update_servers");

            $notes[] = $this->createNote(
                $this->lang->t(
                    'update_available_servers',
                    $serversCount,
                    $this->heart->getServersAmount(),
                    $updateServersLink
                ),
                "is-success"
            );
        }

        return implode("", $notes);
    }

    private function getBricks()
    {
        $bricks = [];

        // Server
        $bricks[] = $this->createBrick(
            $this->lang->t('number_of_servers', $this->heart->getServersAmount())
        );

        // User
        $bricks[] = $this->createBrick(
            $this->lang->t(
                'number_of_users',
                $this->db->query("SELECT COUNT(*) FROM `ss_users`")->fetchColumn()
            )
        );

        // Bought service
        $amount = $this->db
            ->query("SELECT COUNT(*) " . "FROM ({$this->settings['transactions_query']}) AS t")
            ->fetchColumn();
        $bricks[] = $this->createBrick($this->lang->t('number_of_bought_services', $amount));

        // SMS
        $amount = $this->db
            ->query(
                "SELECT COUNT(*) AS `amount` " .
                    "FROM ({$this->settings['transactions_query']}) as t " .
                    "WHERE t.payment = 'sms' AND t.free='0'"
            )
            ->fetchColumn();
        $bricks[] = $this->createBrick($this->lang->t('number_of_sent_smses', $amount));

        // Transfer
        $amount = $this->db
            ->query(
                "SELECT COUNT(*) AS `amount` " .
                    "FROM ({$this->settings['transactions_query']}) as t " .
                    "WHERE t.payment = 'transfer' AND t.free='0'"
            )
            ->fetchColumn();
        $bricks[] = $this->createBrick($this->lang->t('number_of_transfers', $amount));

        // Income
        $incomeData = $this->incomeService->get(date("Y"), date("m"));
        $income = 0;
        foreach ($incomeData as $date) {
            foreach ($date as $value) {
                $income += $value;
            }
        }
        $incomeText = $this->priceTextService->getPriceText($income);
        $bricks[] = $this->createBrick($this->lang->t('note_income', $incomeText));

        // Whole income
        $wholeIncomeText = $this->priceTextService->getPriceText(
            $this->incomeService->getWholeIncome()
        );
        $bricks[] = $this->createBrick($this->lang->t('note_whole_income', $wholeIncomeText));

        return implode("", $bricks);
    }

    /**
     * @param Server $server
     * @param string $newestAmxxVersion
     * @param string $newestSmVersion
     * @return bool
     */
    private function isServerNewest(Server $server, $newestAmxxVersion, $newestSmVersion)
    {
        if (
            $server->getType() === Server::TYPE_AMXMODX &&
            $server->getVersion() !== $newestAmxxVersion
        ) {
            return false;
        }

        if (
            $server->getType() === Server::TYPE_SOURCEMOD &&
            $server->getVersion() !== $newestSmVersion
        ) {
            return false;
        }

        return true;
    }

    private function createNote($text, $class)
    {
        return create_dom_element("div", new UnescapedSimpleText($text), [
            'class' => "notification " . $class,
        ]);
    }

    private function createBrick($content)
    {
        return $this->template->render("admin/brick_card", compact('content'));
    }
}
