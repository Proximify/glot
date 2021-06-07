<?php

/**
 * File for Airtable plugin.
 * 
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 * @version   1.0.0 GLOT Run Time Library
 */

namespace Proximify\Glot\Plugin\DataSource;

/**
 * A class to manage airtable data source
 */
class Airtable
{
    function __construct($config)
    {
        $this->validateConfig($config);
        $this->doc = $config['doc'];
        $this->table = $config['tableName'];
        $this->apiKey = $config['apiKey'];
    }

    function validateConfig($config)
    {
        if (empty($config['doc']))
            throw new \Exception('Airtable instance doc name is missing.');

        if (empty($config['tableName']))
            throw new \Exception('Table name is missing.');

        if (empty($config['apiKey']))
            throw new \Exception('API key is missing.');
    }

    function fetchRecords($params, $columnName)
    {
        $doc = $this->doc;
        $tableName = $this->table;
        $apiKey = $this->apiKey;

        $query = "https://api.airtable.com/v0/$doc/$tableName?api_key=$apiKey";

        $queryParams = $params['queryParams'];
        $fields = $params['fields'];

        if ($queryParams)
            $query .= '&' . $queryParams;

        if ($columnName) {
            foreach ($columnName as $value) {
                $str = str_replace(' ', '%20', trim($value));
                $query .= '&fields%5B%5D=' . $str;
            }
        } else {
            if ($fields) {
                $a = explode(',', $fields);

                foreach ($a as $value) {
                    $query .= '&fields%5B%5D=' . trim($value);
                }
            }
        }

        $result = file_get_contents($query);

        $result = json_decode($result, true);

        if (isset($result['records'])) {
            $records = $result['records'];

            $result = $this->parseAirtableData($records);
            return $result;
        } else
            return 'Invalid request from airtable.';
    }

    function parseAirtableData($data)
    {
        $result = [];

        foreach ($data as $value) {
            $fields = isset($value['fields']) ? $value['fields'] : [];

            $result[] = $fields;
        }

        return $result;
    }
}
