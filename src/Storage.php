<?php

namespace Weibohit;

use Medoo\Medoo;

class Storage
{

    private static $_instance = null;

    private $_database;

    private function __construct()
    {
        $this->_database = new Medoo([
            'database_type' => 'sqlite',
            'database_file' => __DIR__ . '/db/db.db'
        ]);
    }

    /**
     * @return Storage
     */
    public static function getInstance()
    {
        if (self::$_instance == null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function set($tbl, $key, $data) 
    {
        // 表不存在 
        $count = $this->_database->count($tbl, ['key' => $key]);
        if ($count === false) {
            $this->_database->create($tbl, ['key' => 'text', 'data' => 'text']);
            
        } 
        if ($count > 0) {
            $this->_database->update($tbl, ['key' => $key, 'data' => $data], ['key' => $key]);
        } else {
            $this->_database->insert($tbl, ['key' => $key, 'data' => $data]);
        }
    }

    public function get($tbl, $key)
    {
        $data = $this->_database->select($tbl, 'data', ['key' => $key]);
        if ($data) {
            return unserialize($data[0]);
        }
        return '';
    }
}
