<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Reliese\Coders\Model\Config;
use Reliese\Meta\Blueprint;

class ConfigTest extends TestCase
{
    #[DataProvider('provideDataForTestGet')]
    public function testGet($config, $key, $expected)
    {
        $config = new Config($config);

        $baseBlueprint = Mockery::mock(Blueprint::class);
        $baseBlueprint->shouldReceive('schema')->andReturn('test');
        $baseBlueprint->shouldReceive('qualifiedTable')->andReturn('test.my_table');
        $baseBlueprint->shouldReceive('connection')->andReturn('test_connection');
        $baseBlueprint->shouldReceive('table')->andReturn('my_table');

        $this->assertEquals($expected, $config->get($baseBlueprint, $key));
    }

    public static function provideDataForTestGet()
    {
        return [
            'Basic Key' => [
                [
                    '*' => [
                        'Key' => 'Value'
                    ],
                ],
                'Key',
                'Value'
            ],
            'Schema Key' => [
                [
                    'test' => [
                        'schemaKey' => 'Schema Value'
                    ],
                ],
                'schemaKey',
                'Schema Value'
            ],
            'Qualified Table Key' => [
                [
                    'test' => [
                        'qfKey' => 'Qualified Table Value'
                    ],
                ],
                'qfKey',
                'Qualified Table Value'
            ],
            'Connection Basic Key' => [
                [
                    '@connections' => [
                        'test_connection' => [
                            'cKey' => 'Connection Value'
                        ],
                    ]
                ],
                'cKey',
                'Connection Value'
            ],
            'Connection Schema Key' => [
                [
                    '@connections' => [
                        'test_connection' => [
                            'test' => [
                                'csKey' => 'Connection Schema Value'
                            ]
                        ],
                    ]
                ],
                'csKey',
                'Connection Schema Value'
            ],
            'Connection Table Key' => [
                [
                    '@connections' => [
                        'test_connection' => [
                            'my_table' => [
                                'ctKey' => 'Connection Table Value'
                            ]
                        ],
                    ]
                ],
                'ctKey',
                'Connection Table Value'
            ],

            'Connection Database Table Key' => [
                [
                    '@connections' => [
                        'test_connection' => [
                            'test' => [
                                'my_table' => [
                                    'cdtKey' => 'Connection Database Table Value'
                                ]
                            ]
                        ],
                    ]
                ],
                'cdtKey',
                'Connection Database Table Value'
            ],
            'Table Key' => [
                [
                    'my_table' => [
                        'tKey' => 'Table Value'
                    ],
                ],
                'tKey',
                'Table Value'
            ],
            'Test Hierarchy Override for Schema' => [
                [
                    '*' => [
                        'FirstKey' => 'Some Value'
                    ],
                    'test' => [
                        'FirstKey' => 'A Second Value'
                    ]
                ],
                'FirstKey',
                'A Second Value'
            ],
            'Test Hierarchy Override for Qualified Table' => [
                [
                    '*' => [
                        'FirstKey' => 'Some Value'
                    ],
                    'test' => [
                        'FirstKey' => 'A Second Value',
                        'my_table' => [
                            'FirstKey' => 'A Third Value'
                        ]
                    ],
                ],
                'FirstKey',
                'A Third Value'
            ],
            'Test Hierarchy Override for Connection Basic Key' => [
                [
                    '*' => [
                        'FirstKey' => 'Some Value'
                    ],
                    'test' => [
                        'FirstKey' => 'A Second Value',
                        'my_table' => [
                            'FirstKey' => 'A Third Value'
                        ]
                    ],
                    '@connections' => [
                        'test_connection' => [
                            'FirstKey' => 'A Fourth Value',
                        ]
                    ]
                ],
                'FirstKey',
                'A Fourth Value'
            ],
            'Test Hierarchy Override for Connection Schema Key' => [
                [
                    '*' => [
                        'FirstKey' => 'Some Value'
                    ],
                    'test' => [
                        'FirstKey' => 'A Second Value',
                        'my_table' => [
                            'FirstKey' => 'A Third Value'
                        ]
                    ],
                    '@connections' => [
                        'test_connection' => [
                            'FirstKey' => 'A Fourth Value',
                            'test' => [
                                'FirstKey' => 'A Fifth Value'
                            ]
                        ]
                    ]
                ],
                'FirstKey',
                'A Fifth Value'
            ],
            'Test Hierarchy Override for Connection Table Key' => [
                [
                    '*' => [
                        'FirstKey' => 'Some Value'
                    ],
                    'test' => [
                        'FirstKey' => 'A Second Value',
                        'my_table' => [
                            'FirstKey' => 'A Third Value'
                        ]
                    ],
                    '@connections' => [
                        'test_connection' => [
                            'FirstKey' => 'A Fourth Value',
                            'test' => [
                                'FirstKey' => 'A Fifth Value',
                            ],
                            'my_table' => [
                                'FirstKey' => 'A Sixth Value',
                            ]
                        ]
                    ]
                ],
                'FirstKey',
                'A Sixth Value'
            ],

            'Test Hierarchy Override for Connection Database Table Key' => [
                [
                    '*' => [
                        'FirstKey' => 'Some Value'
                    ],
                    'test' => [
                        'FirstKey' => 'A Second Value',
                        'my_table' => [
                            'FirstKey' => 'A Third Value'
                        ]
                    ],
                    '@connections' => [
                        'test_connection' => [
                            'FirstKey' => 'A Fourth Value',
                            'test' => [
                                'FirstKey' => 'A Fifth Value',
                                'my_table' => [
                                    'FirstKey' => 'A Seventh Value',
                                ],
                            ],
                            'my_table' => [
                                'FirstKey' => 'A Sixth Value',
                            ]
                        ]
                    ]
                ],
                'FirstKey',
                'A Seventh Value'
            ],

            // -------------------------------------------------------------------
            // 'casts' is merged across resolution levels instead of first-hit-wins
            // -------------------------------------------------------------------

            'Casts Are Merged Across Global And Table Blocks' => [
                [
                    '*' => [
                        'casts' => ['*_json' => 'json', 'password' => 'hashed', 'token' => 'encrypted']
                    ],
                    'my_table' => [
                        'casts' => ['password' => 'encrypted', 'result' => 'encrypted:array']
                    ],
                ],
                'casts',
                [
                    'password' => 'encrypted',
                    'result' => 'encrypted:array',
                    '*_json' => 'json',
                    'token' => 'encrypted',
                ]
            ],

            'Casts Table Block Wins For A Shared Pattern' => [
                [
                    '*' => [
                        'casts' => ['password' => 'hashed', 'token' => 'encrypted']
                    ],
                    'my_table' => [
                        'casts' => ['token' => 'encrypted:array']
                    ],
                ],
                'casts',
                ['token' => 'encrypted:array', 'password' => 'hashed']
            ],

            'Casts Table Block Only' => [
                [
                    'my_table' => [
                        'casts' => ['result' => 'encrypted:array']
                    ],
                ],
                'casts',
                ['result' => 'encrypted:array']
            ],

            'Casts Global Block Only' => [
                [
                    '*' => [
                        'casts' => ['*_json' => 'json']
                    ],
                ],
                'casts',
                ['*_json' => 'json']
            ],

            'Casts Return Default When Nothing Defined' => [
                ['my_table' => ['FirstKey' => 'Some Value']],
                'casts',
                null
            ],
        ];
    }
}
