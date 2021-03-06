<?php
namespace App\View\Pages;

use App\View\Html\BodyRow;
use App\View\Html\Cell;
use App\View\Html\Div;
use App\View\Html\HeadCell;
use App\View\Html\Structure;
use App\View\Html\Wrapper;

class PageAdminLogs extends PageAdmin
{
    const PAGE_ID = 'logs';
    protected $privilege = 'view_logs';

    public function __construct()
    {
        parent::__construct();

        $this->heart->pageTitle = $this->title = $this->lang->t('logs');
    }

    protected function content(array $query, array $body)
    {
        $wrapper = new Wrapper();
        $wrapper->setTitle($this->title);
        $wrapper->setSearch();

        $table = new Structure();
        $table->addHeadCell(new HeadCell($this->lang->t('id'), "id"));
        $table->addHeadCell(new HeadCell($this->lang->t('text')));
        $table->addHeadCell(new HeadCell($this->lang->t('date')));

        // Wyszukujemy dane ktore spelniaja kryteria
        $where = '';
        if (isset($query['search'])) {
            searchWhere(["`id`", "`text`", "CAST(`timestamp` as CHAR)"], $query['search'], $where);
        }

        // Jezeli jest jakis where, to dodajemy WHERE
        if (strlen($where)) {
            $where = "WHERE " . $where . " ";
        }

        $result = $this->db->query(
            "SELECT SQL_CALC_FOUND_ROWS * FROM `ss_logs` " .
                $where .
                "ORDER BY `id` DESC " .
                "LIMIT " .
                get_row_limit($this->currentPage->getPageNumber())
        );

        $table->setDbRowsCount($this->db->query("SELECT FOUND_ROWS()")->fetchColumn());

        foreach ($result as $row) {
            $bodyRow = new BodyRow();

            $bodyRow->setDbId($row['id']);

            $cell = new Cell();
            $div = new Div($row['text']);
            $div->addClass('one_line');
            $cell->addContent($div);
            $bodyRow->addCell($cell);

            $cell = new Cell(convert_date($row['timestamp']));
            $cell->setParam('headers', 'date');
            $bodyRow->addCell($cell);

            if (get_privileges("manage_logs")) {
                $bodyRow->setDeleteAction(true);
            }

            $table->addBodyRow($bodyRow);
        }

        $wrapper->setTable($table);

        return $wrapper->toHtml();
    }
}
