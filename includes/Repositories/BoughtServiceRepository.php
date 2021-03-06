<?php
namespace App\Repositories;

use App\Models\BoughtService;
use App\Support\Database;

class BoughtServiceRepository
{
    /** @var Database */
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function get($id)
    {
        if ($id) {
            $statement = $this->db->statement("SELECT * FROM `ss_bought_services` WHERE `id` = ?");
            $statement->execute([$id]);

            if ($data = $statement->fetch()) {
                return $this->mapToModel($data);
            }
        }

        return null;
    }

    public function create(
        $uid,
        $method,
        $paymentId,
        $service,
        $server,
        $amount,
        $authData,
        $email,
        $extraData = []
    ) {
        $this->db
            ->statement(
                "INSERT INTO `ss_bought_services` " .
                    "SET `uid` = ?, `payment` = ?, `payment_id` = ?, `service` = ?, " .
                    "`server` = ?, `amount` = ?, `auth_data` = ?, `email` = ?, `extra_data` = ?"
            )
            ->execute([
                $uid ?: 0,
                $method,
                $paymentId,
                $service,
                $server ?: 0,
                $amount ?: 0,
                $authData ?: '',
                $email ?: '',
                json_encode($extraData),
            ]);

        return $this->get($this->db->lastId());
    }

    private function mapToModel(array $data)
    {
        return new BoughtService(
            as_int($data['id']),
            as_int($data['uid']),
            $data['payment'],
            $data['payment_id'],
            $data['service'],
            as_int($data['server']),
            $data['amount'],
            $data['auth_data'],
            $data['email'],
            json_decode($data['extra_data'])
        );
    }
}
