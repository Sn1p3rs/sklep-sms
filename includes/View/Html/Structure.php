<?php
namespace App\View\Html;

use App\View\CurrentPage;
use App\Translation\TranslationManager;
use App\View\Pagination;
use Symfony\Component\HttpFoundation\Request;

class Structure extends DOMElement
{
    protected $name = 'table';

    protected $params = [
        "class" => "table is-fullwidth is-hoverable",
    ];

    /** @var DOMElement[] */
    private $headCells = [];

    /** @var BodyRow[] */
    private $bodyRows = [];

    /**
     * Ilość elementów w bazie danych
     * potrzebne do stworzenia paginacji
     *
     * @var int
     */
    private $dbRowsCount;

    /** @var DOMElement */
    public $foot = null;

    public function toHtml()
    {
        /** @var TranslationManager $translationManager */
        $translationManager = app()->make(TranslationManager::class);
        $lang = $translationManager->user();

        // Tworzymy thead
        $head = new DOMElement();
        $head->setName('thead');

        $headRow = new Row();
        foreach ($this->headCells as $cell) {
            $headRow->addContent($cell);
        }
        $actions = new HeadCell($lang->t('actions'));
        $actions->setStyle('width', '4%');
        $headRow->addContent($actions);

        $head->addContent($headRow);

        // Tworzymy tbody
        $body = new DOMElement();
        $body->setName('tbody');
        foreach ($this->bodyRows as $row) {
            $body->addContent($row);
        }

        if ($body->isEmpty()) {
            $row = new Row();
            $cell = new Cell($lang->t('no_data'));
            $cell->setParam('colspan', '30');
            $cell->addClass("has-text-centered");
            $cell->setStyle('padding', '40px');
            $row->addContent($cell);
            $body->addContent($row);
        }

        $this->contents = [];
        $this->addContent($head);
        $this->addContent($body);
        if ($this->foot !== null) {
            $this->addContent($this->foot);
        }

        return parent::toHtml();
    }

    /**
     * @param DOMElement $headCell
     */
    public function addHeadCell($headCell)
    {
        $this->headCells[] = $headCell;
    }

    /**
     * @param string     $key
     * @param DOMElement $headCell
     */
    public function setHeadCell($key, $headCell)
    {
        $this->headCells[$key] = $headCell;
    }

    /**
     * @param BodyRow $bodyRow
     */
    public function addBodyRow($bodyRow)
    {
        $this->bodyRows[] = $bodyRow;
    }

    /**
     * @return int
     */
    public function getDbRowsCount()
    {
        return $this->dbRowsCount;
    }

    /**
     * @param int $count
     */
    public function setDbRowsCount($count)
    {
        /** @var CurrentPage $currentPage */
        $currentPage = app()->make(CurrentPage::class);

        /** @var Request $request */
        $request = app()->make(Request::class);

        /** @var Pagination $pagination */
        $pagination = app()->make(Pagination::class);

        $pageNumber = $currentPage->getPageNumber();
        $this->dbRowsCount = (int) $count;

        $paginationContent = $pagination->getPagination(
            $this->dbRowsCount,
            $pageNumber,
            $request->getPathInfo(),
            $request->query->all()
        );

        if ($paginationContent) {
            $this->foot = new DOMElement();
            $this->foot->setName('tfoot');
            $this->foot->addClass('display_tfoot');

            $row = new Row();

            $cell = new Cell($paginationContent);
            $cell->setParam('colspan', '31');

            $row->addContent($cell);
            $this->foot->addContent($row);
        }
    }
}
