<?php namespace Starmoozie\IdGenerator;

class IdGenerator
{
    private function getFieldType($table, $field)
    {
        $connection = config('database.default'); // Ambil default koneksi database
        $driver     = \DB::connection($connection)->getDriverName(); // Ambil driver koneksi database
        $database   = \DB::connection($connection)->getDatabaseName(); // Ambil nama database

        if ($driver == 'mysql') {
            // Ambil nama kolom dari schema
            $sql  = 'SELECT column_name AS "column_name", data_type AS "data_type", column_type AS "column_type" FROM information_schema.columns ';
            // Dimana nama database xx dan table xx
            $sql .= 'WHERE table_schema=:database AND table_name=:table';
        }
        else { // pada postgresql column_type tidak ada, diganti table_catalog
            $sql  = 'SELECT column_name as "column_name", data_type as "data_type" FROM information_schema.columns ';
            $sql .= 'WHERE table_catalog=:database AND table_name=:table';
        }

        $rows        = \DB::select($sql, ['database' => $database, 'table' => $table]);
        $fieldType   = null;
        $fieldLength = null;

        foreach ($rows as $col) {
            if ($field == $col->column_name) {
                $fieldType = $col->data_type;

                if ($driver == 'mysql') {
                    // column_type int(11) to 11 pada mysql
                    preg_match("/(?<=\().+?(?=\))/", $col->column_type, $tblFieldLength);
                    $fieldLength = $tblFieldLength[0];
                }
                else {
                    //column_type tidak ada pada postgres SQL nila 32
                    $fieldLength = 32;
                }

                break;
            }
        }

        if ($fieldType == null) throw new \Exception("kolom $field tidak ada pada tabel $table");

        return ['type' => $fieldType, 'length' => $fieldLength];
    }

    public static function generate($configArr)
    {
        if (!array_key_exists('table', $configArr) || $configArr['table'] == '') {
            throw new \Exception('index table harus ada');
        }

        if (!array_key_exists('length', $configArr) || $configArr['length'] == '') {
            throw new \Exception('Harus mengidentifikasi panjang karakter pada id');
        }

        if (!array_key_exists('prefix', $configArr) || $configArr['prefix'] == '') {
            throw new \Exception('Harus menyertakan kata awalan pada id');
        }

        if (array_key_exists('where', $configArr)) {
            if (is_string($configArr['where'])) {
                throw new \Exception('klausa where harus array, yang kamu masukkan adalah string');
            }
            if (!count($configArr['where'])) {
                throw new \Exception('klausa where harus array');
            }
        }

        $table               = $configArr['table'];
        $field               = array_key_exists('field', $configArr) ? $configArr['field'] : 'id';
        $prefix              = $configArr['prefix'];
        $resetOnPrefixChange = array_key_exists('reset_on_prefix_change', $configArr) ? $configArr['reset_on_prefix_change'] : false;
        $length              = $configArr['length'];

        $fieldInfo        = (new self)->getFieldType($table, $field);
        $tableFieldType   = $fieldInfo['type'];
        $tableFieldLength = $fieldInfo['length'];

        if (in_array($tableFieldType, ['int', 'integer', 'bigint', 'numeric']) && !is_numeric($prefix)) {
            throw new \Exception("$field field type is $tableFieldType but prefix is string");
        }

        if ($length > $tableFieldLength) {
            throw new \Exception('ID yang digenerate meleibihi panjang karakter pada kolom');
        }

        $prefixLength = strlen($configArr['prefix']);
        $idLength     = $length - $prefixLength;
        $whereString  = '';

        if (array_key_exists('where', $configArr)) {
            $whereString .= " WHERE ";
            foreach ($configArr['where'] as $row) {
                $whereString .= $row[0] . "=" . $row[1] . " AND ";
            }
        }
        $whereString = rtrim($whereString, 'AND ');

        $totalQuery  = sprintf("SELECT count(%s) total FROM %s %s", $field, $configArr['table'], $whereString);
        $total       = \DB::select($totalQuery);

        if ($total[0]->total) {
            if ($resetOnPrefixChange) {
                $maxQuery = sprintf("SELECT MAX(%s) maxId from %s WHERE %s like %s", $field, $table, $field, "'" . $prefix . "%'");
            }
            else {
                $maxQuery = sprintf("SELECT MAX(%s) maxId from %s", $field, $table);
            }

            $queryResult = \DB::select($maxQuery);
            $maxFullId   = $queryResult[0]->maxId;
            $maxId       = substr($maxFullId, $prefixLength, $idLength);

            return $prefix . str_pad($maxId + 1, $idLength, '0', STR_PAD_LEFT);
        }
        else {

            return $prefix . str_pad(1, $idLength, '0', STR_PAD_LEFT);
        }
    }
}