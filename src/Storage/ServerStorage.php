<?php

namespace Sterzik\ModStamp\Storage;

use Redis;
use Exception;
use Sterzik\ModStamp\Modstamp;
use SQLite3;

class ServerStorage
{
    private SQLite3 $db;

    public function __construct(string $file)
    {
        $this->db = new SQLite3($file);
        $result = $this->query("SELECT `name` FROM `sqlite_master` WHERE `type` = 'table' AND `name` = 'modstamps'");

        if (empty($result)) {
            $this->db->exec("CREATE TABLE modstamps(modstamp VARCHAR(255) PRIMARY KEY ASC, value VARCHAR(255))");
        }
    }

    public function setModstampValue(Modstamp $modstamp, ?string $value): self
    {
        if ($value === '') {
            $value = null;
        }

        if ($value === null) {
            $stmt = $this->db->prepare("DELETE FROM modstamps WHERE modstamp = :id");
            $stmt->bindValue(':id', $modstamp->getId(), SQLITE3_TEXT);
            $stmt->execute();
        } else {
            $stmt = $this->db->prepare("REPLACE INTO modstamps (modstamp, value) VALUES (:id, :value)");
            $stmt->bindValue(':id', $modstamp->getId(), SQLITE3_TEXT);
            $stmt->bindValue(':value', $value, SQLITE3_TEXT);
            $stmt->execute();
        }
        return $this;
    }

    public function getModstampValue(Modstamp $modstamp): ?string
    {
        $stmt = $this->db->prepare("SELECT value FROM modstamps WHERE modstamp = :id LIMIT 1");
        $stmt->bindValue(':id', $modstamp->getId(), SQLITE3_TEXT);
        $result = $stmt->execute();
        $rows = [];
        while($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        if (empty($rows)) {
            return null;
        }
        $row = array_shift($rows);
        $value = $row['value'];
        if ($value === '') {
            $value = null;
        }

        return $value;
    }

    private function query(string $query): array
    {
        $result = $this->db->query("SELECT `name` FROM `sqlite_master` WHERE `type` = 'table' AND `name` = 'modstamps'");
        
        $rows = [];
        while($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        return $rows;
    }
}
