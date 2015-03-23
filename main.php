<?php

use Elasticsearch\ClientBuilder;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Main
{
    /**
     * initialize elastic search
     * @return \Elasticsearch\Client
     */
    const ONE_MONTH = 2678400;

    private function initialize()
    {
        # monolog
        $logger = ClientBuilder::defaultLogger('log/error.log', Logger::WARNING);

        # elasticsearch
        $client = ClientBuilder::create()
            ->setLogger($logger)
            ->build();
        return $client;
    }

    /**
     * initialize log
     * @param string $name
     * @param string $address
     * @return object elastic
     */
    private function log($name, $address)
    {
        $logging = new Logger($name);
        $log = $logging->pushHandler(new StreamHandler($address));
        return $log;
    }

    /**
     * verify http method and get body
     * @param string $httpMethod
     * @return mixed
     */
    private function getBody($httpMethod)
    {
        # validate http method
        if ($_SERVER['REQUEST_METHOD'] != $httpMethod)
            $this->result(false, null, 700, 'http method must be ' . $httpMethod);

        # get request body
        $body = file_get_contents("php://input");

        # return parsed data
        return json_decode($body, true);
    }

    /**
     * insert to elastic search db
     * @return mixed
     */
    public function insert()
    {
        $body = $this->getBody('PUT');
        $client = $this->initialize();

        # set elastic query
        $params = [
            'index' => $body['database'],
            'type' => $body['table'],
            'id' => $body['key'],
            'body' => $body['fields']
        ];

        # insert
        $response = $client->index($params);

        # set version (version more than 1, means duplicated)
        $result = ['version' => $response['_version']];

        # return result
        $this->result(true, $result, null, null);
    }

    /**
     * update document in elastic search
     * @return mixed
     */
    public function update()
    {
        $body = $this->getBody('PUT');
        $client = $this->initialize();
        # set elastic query
        $params = [
            'index' => $body['database'],
            'type' => $body['table'],
            'id' => $body['key'],
            'body' => [
                'doc' => $body['fields']
            ]
        ];

        # update
        $response = $client->update($params);

        # set version
        $result = ['version' => $response['_version']];

        # return result
        $this->result(true, $result, null, null);
    }

    /**
     * select just with key
     * @param string $database
     * @param string $table
     * @param string $Key
     * @return mixed
     */
    public function select($database, $table, $Key)
    {
        $this->getBody('GET'); # just verify http method
        $client = $this->initialize();

        # set elastic query
        $params = [
            'index' => $database,
            'type' => $table,
            'id' => $Key,
            'client' => ['ignore' => 404] # for custom exception
        ];

        # select
        $response = $client->get($params);

        # check found
        if (!$response['found']) {
            $this->result(false, null, 701, 'not found');
        }

        # return result
        $this->result(true, $response['_source'], null, null);
    }

    /**
     * delete a document by key
     * @param string $database
     * @param string $table
     * @param string $Key
     * @return mixed
     */
    public function deleteDocument($database, $table, $Key)
    {
        $this->getBody('DELETE'); # just verify http method
        $client = $this->initialize();

        # set elastic query
        $params = [
            'index' => $database,
            'type' => $table,
            'id' => $Key,
            'client' => ['ignore' => 404] # for custom exception
        ];

        # check existence
        $get = $client->get($params);
        if (!$get['found']) {
            $this->result(false, null, 702, 'not found');
        }

        # delete
        $client->delete($params);

        # return result
        $this->result(true, null, null, null);
    }

    /**
     * search in elastic db
     * @param string $database
     * @param string $table
     * @param integer $offset
     * @param integer $limit
     * @param string $word
     * @return mixed
     */
    public function search($database, $table, $offset, $limit, $word)
    {
        $this->getBody('GET'); # just verify http method
        $client = $this->initialize();

        #correction word
        $word = substr($word, 1);

        # for logging
        $this->log('search', 'log/search.log')->info($word);

        # get query params and search
        $params = $this->searchParams($database, $table, $word, $offset, $limit);
        $response = $client->search($params);

        # check found
        if (!$response['hits']['hits'])
            $this->result(false, null, 703, 'not found');

        # refactor result
        foreach ($response['hits']['hits'] as $key => $value)
        {
            $result[$key]['username'] = $value['_source']['username'];
            $result[$key]['displayName'] = $value['_source']['displayName'];
            $result[$key]['age'] = $value['_source']['age'];
            $result[$key]['city'] = $value['_source']['city'];
            $result[$key]['website'] = $value['_source']['website'];
            $result[$key]['email'] = $value['_source']['email'];
            $result[$key]['cover'] = $value['_source']['cover'];
        }

        # return result
        $this->result(true, $result, null, null);
    }

    /**
     * create search parameters
     * @param string $database
     * @param string $table
     * @param string $word
     * @param integer $offset
     * @param integer $limit
     * @return array
     */
    private function searchParams($database, $table, $word, $offset, $limit)
    {
        $params = [
            'index' => $database,
            'type' => $table,
            'from' => $offset,
            'size' => $limit,
            'body' => [
                "query" => [
                    'function_score' => [
                        'query' => [
                            'bool' => [
                                'should' => [
                                    [
                                        'bool' => [
                                            'should' => [
                                                [
                                                    "multi_match" => [
                                                        "query" => $word,
                                                        'fields' => ['name', 'name_fa'],
                                                        "fuzziness" => 'AUTO',
                                                        'boost' => 2,
                                                    ],
                                                ],
                                                [
                                                    "multi_match" => [
                                                        "query" => str_replace(' ', '', $word),
                                                        'fields' => ['name', 'name_fa'],
                                                        "fuzziness" => 'AUTO',
                                                        'boost' => 2,
                                                    ],
                                                ],
                                            ]
                                        ]
                                    ],
                                    [
                                        'multi_match' => [
                                            'query' => $word,
                                            'fields' => ['category_name', 'category_name_fa'],
                                            'type' => 'phrase',
                                            "minimum_should_match" => "100%",
                                            "operator" => "and",
                                            'boost' => 50
                                        ]
                                    ],
                                    [
                                        'match' => [
                                            'website_developer' => [
                                                'query' => $word,
                                                "minimum_should_match" => "100%",
                                                "operator" => "and",
                                                'boost' => 1
                                            ]
                                        ]
                                    ],
                                ],
                            ],
                        ],
                        "field_value_factor" => [
                            "field" => "site_views",
                            "modifier" => "log1p",
                            "factor" => 0.1,
                        ],
                        "boost_mode" => "multiply",
                    ],
                ]
            ],
            'client' => ['ignore' => 404] # for custom exception
        ];

        return $params;
    }

    /**
     * import from websites mysql database to elastic search database
     * @return mixed
     */
    public function import()
    {
        $body = $this->getBody('PUT');
        $client = $this->initialize();

        # set session for current database name and start import flag
        ini_set('session.gc_maxlifetime', self::ONE_MONTH);
        session_id('database');
        session_start();

        # for logging
        $this->log('import', 'log/import.log')->info('before', $_SESSION);

        # set variable
        $newDatabase = (string)$body['database'];
        $table = $body['table'];
        $fields = $body['fields'];
        $oldDatabase = isset($_SESSION['old']) ? $_SESSION['old'] : null; # get old database name
        $startDatabase = isset($_SESSION['start']) ? $_SESSION['start'] : null; # get start import process
        $finish = $body['finish'];

        # if this is first request, create database schema
        if (!$startDatabase) {
            $this->createDatabase($client, $newDatabase, $table);
        }

        # insert data (batch)
        $this->insertBatch($newDatabase, $table, $fields, $client, $finish, $oldDatabase);

        # for logging
        $this->log('import', 'log/import.log')->info('after', $_SESSION);

        $this->result(true, null, null, null);
    }

    /**
     * create elastic search database with schema
     * @param object $client
     * @param string $newDatabase
     * @param string $table
     */
    public function createDatabase($client, $newDatabase, $table)
    {
        $params = [
            'index' => $newDatabase,
            'body' => [
                'settings' => [
                    'analysis' => [
                        'filter' => [
                            'filter_websitename' => [
                                'type' => 'synonym',
                                'synonyms_path' => 'synonyms_websitename.txt'
                            ],
                            'filter_websitenamefa' => [
                                'type' => 'synonym',
                                'synonyms_path' => 'synonyms_websitenamefa.txt'
                            ],
                        ],
                        'analyzer' => [
                            'analyzer_websitename' => [
                                'tokenizer' => 'standard',
                                'filter' => ['filter_name', 'lowercase']
                            ],
                            'analyzer_websitenamefa' => [
                                'tokenizer' => 'standard',
                                'filter' => ['filter_namefa']
                            ],
                        ]
                    ]
                ],
                'mwebsiteings' => [
                    $table => [
                        'properties' => [
                            'website_name' => [
                                'type' => 'string',
                                'analyzer' => 'analyzer_name',
                            ],
                            'website_name_fa' => [
                                'type' => 'string',
                                'analyzer' => 'analyzer_namefa',
                            ],
                        ]
                    ],
                ]
            ]
        ];

        $client->indices()->create($params);
    }

    /**
     * @param $newDatabase
     * @param $table
     * @param $fields
     * @param $client
     * @param $finish
     * @param $oldDatabase
     */
    private function insertBatch($newDatabase, $table, $fields, $client, $finish, $oldDatabase)
    {
        $ESQuery = $this->reformElasticQuery($newDatabase, $table, $fields);
        $client->bulk($ESQuery);
        $_SESSION['start'] = true;

        # if this is last request, remove old db and change alias
        if ($finish) {
            if ($oldDatabase) # old db name on session is existence ===> this is first time
                $this->afterImport($client, $oldDatabase, $newDatabase);
            else # old db name on session is not existence ===> this is not first time
                $this->afterFirstImport($client, $newDatabase);
        }
    }

    /**
     * do operations after first import from zero state
     * @param object $client
     * @param string $newDatabase
     */
    private function afterFirstImport($client, $newDatabase)
    {
        # step 1 : add alias
        $params['body'] = [
            'actions' => [
                [
                    'add' => [
                        'index' => $newDatabase,
                        'alias' => 'websites'
                    ]
                ]
            ]
        ];
        $client->indices()->updateAliases($params);

        # step 2 : update session
        $_SESSION['old'] = $newDatabase;
        $_SESSION['start'] = false;
    }

    /**
     * do operations after import
     * @param object $client
     * @param string $oldDatabase
     * @param string $newDatabase
     */
    private function afterImport($client, $oldDatabase, $newDatabase)
    {
        # step 1 : remove alias
        $params['body'] = [
            'actions' => [
                [
                    'remove' => [
                        'index' => $oldDatabase,
                        'alias' => 'websites'
                    ]
                ]
            ]
        ];
        $client->indices()->updateAliases($params);

        # step 2 : add alias
        $params['body'] = [
            'actions' => [
                [
                    'add' => [
                        'index' => $newDatabase,
                        'alias' => 'websites'
                    ]
                ]
            ]
        ];
        $client->indices()->updateAliases($params);

        # step 3 : remove index
        $deleteParams = [
            'index' => $oldDatabase
        ];
        $client->indices()->delete($deleteParams);

        # step 4 : update session
        $_SESSION['old'] = $newDatabase;
        $_SESSION['start'] = false;
    }

    /**
     * reform mysql query to elastic query
     * @param string $newDB
     * @param string $table
     * @param array $fields
     * @return array
     */
    private function reformElasticQuery($newDB, $table, $fields)
    {
        for ($i = 0; $i < count($fields); $i++) {
            $result['body'][] = [
                'index' => [
                    '_index' => $newDB,
                    '_type' => $table,
                    '_id' => $fields[$i]['website_id']
                ],
            ];
            $result['body'][] = [
                'email' => $fields[$i]['website_developer_email'],
                'website' => $fields[$i]['website_developer_website'],
                'video' => (int)$fields[$i]['website_video'],
                'video_delete' => (int)$fields[$i]['video_delete'],
                'user_display_name' => $fields[$i]['user_display_name'],
                'user_email' => $fields[$i]['user_email'],
                'user_website' => $fields[$i]['user_website']
            ];
        }
        
        return $result;
    }

    /**
     * delete database with name
     * @param string $database
     * @return mixed
     */
    public function deleteDatabase($database)
    {
        $this->getBody('DELETE');
        $client = $this->initialize();
        $deleteParams = [
            'index' => $database
        ];
        $dbExist = $client->indices()->exists($deleteParams);

        # check database exist
        if ( !$dbExist ) {
            $this->result(false, null, 704, 'database don\'t exist');
        }

        # delete database
        $response = $client->indices()->delete($deleteParams);

        #check database deleted
        if ( $response['acknowledged'] == 1 ) {
            $this->result('true', null, null, null);
        }
    }

    /**
     * delete all session
     * @return string
     */
    public function reset()
    {
        session_id('database');
        session_start();
        session_unset();
        if (session_destroy())
            echo 'success';
        else
            echo 'failed';
    }

    /**
     * get current database name on session
     * @return array
     */
    public function currentDB()
    {
        session_id('database');
        session_start();
        var_dump($_SESSION);
    }

    /**
     * create result for all method
     * @param string $success
     * @param array, string $result
     * @param integer $errorCode
     * @param string $errorDescription
     * @return mixed
     */
    private function result($success, $result, $errorCode, $errorDescription)
    {
        header('Content-Type: websitelication/json');
        $message = [
            'success' => $success,
            'result' => $result,
            'error_code' => $errorCode,
            'error_description' => $errorDescription,
        ];

        echo json_encode($message);
    }
}