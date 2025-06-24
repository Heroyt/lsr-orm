<?php
declare(strict_types=1);

namespace TestCases\Models;

use Dibi\Exception;
use Lsr\Caching\Cache;
use Lsr\Db\Connection;
use Lsr\Db\DB;
use Lsr\Orm\ModelRepository;
use Lsr\Serializer\Mapper;
use Lsr\Serializer\Normalizer\DateTimeNormalizer;
use Lsr\Serializer\Normalizer\DibiRowNormalizer;
use Mocks\Models\ModelA;
use Mocks\Models\ModelB;
use Mocks\Models\ModelC;
use Mocks\Models\ModelCA;
use Mocks\Models\ModelCB;
use Mocks\Models\ModelCC;
use Mocks\Models\ModelD;
use Mocks\Models\ModelE;
use Mocks\Models\ModelInvalidInstantiate;
use Mocks\Models\ModelWithRelationFactories;
use Mocks\Models\ModelWithTimestamps;
use Mocks\Models\QueryModel;
use Nette\Caching\Storages\DevNullStorage;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

trait DbHelpers
{
    /** @var array<string,string> */
    private const array TABLES = [
        'modelsA'                     => <<<SQL
            CREATE TABLE modelsA (
                model_a_id INTEGER PRIMARY KEY autoincrement NOT NULL ,
                name CHAR(60) NOT NULL,
                age INT,
                verified INT DEFAULT 0
            );
        SQL,
        'modelsB'                     => <<<SQL
            CREATE TABLE modelsB (
                model_b_id INTEGER PRIMARY KEY autoincrement NOT NULL, 
                description CHAR(200) NOT NULL, 
                model_type CHAR(1) NOT NULL, 
                model_a_id INT 
            );
        SQL,
        'modelsC'                     => <<<SQL
            CREATE TABLE modelsC (
                model_c_id INTEGER PRIMARY KEY autoincrement NOT NULL,
                value0 CHAR(50) NOT NULL, 
                value1 CHAR(50) NOT NULL, 
                value2 CHAR(50) NOT NULL
            );
        SQL,
        'modelsD'                     => <<<SQL
            CREATE TABLE modelsD (
                model_d_id INTEGER PRIMARY KEY autoincrement NOT NULL,
                name CHAR(50) NOT NULL
            );
        SQL,
        'modelsE'                     => <<<SQL
            CREATE TABLE modelsE (
                model_e_id INTEGER PRIMARY KEY autoincrement NOT NULL,
                name CHAR(50) NOT NULL
            );
        SQL,
        'modelsD_modelsE'             => <<<SQL
            CREATE TABLE modelsD_modelsE (
                model_d_id INTEGER NOT NULL,
                model_e_id INTEGER NOT NULL,
                PRIMARY KEY(model_d_id, model_e_id)
            );
        SQL,
        'model_invalid_instantiate'   => <<<SQL
            CREATE TABLE model_invalid_instantiate ( 
                id_model INTEGER PRIMARY KEY autoincrement NOT NULL
            );
            SQL,
        'with_timestamps'             => <<<SQL
            CREATE TABLE with_timestamps (
                id_with_timestamps INTEGER PRIMARY KEY autoincrement NOT NULL,
                name CHAR(50) NOT NULL,
                updated_at TIMESTAMP DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        SQL,
        'models_a'                    => <<<SQL
            CREATE TABLE models_a (
                id_model_a INTEGER PRIMARY KEY autoincrement NOT NULL, 
                name CHAR(60) NOT NULL, 
                id_model_b INT NOT NULL,
                id_model_c INT NOT NULL 
            );
        SQL,
        'models_b'                    => <<<SQL
            CREATE TABLE models_b ( 
                id_model_b INTEGER PRIMARY KEY autoincrement NOT NULL, 
                name CHAR(60) NOT NULL
            );
        SQL,
        'models_c'                    => <<<SQL
            CREATE TABLE models_c ( 
                id_model_c INTEGER PRIMARY KEY autoincrement NOT NULL,
                name CHAR(60) NOT NULL,
                id_model_b INT NOT NULL
            );
        SQL,
        'models'                      => <<<SQL
            CREATE TABLE models ( 
                id_model INTEGER PRIMARY KEY autoincrement NOT NULL, 
                name CHAR(60) NOT NULL, 
                age INT 
            );
        SQL,
        'data'                        => <<<SQL
            CREATE TABLE data ( 
                id INTEGER PRIMARY KEY autoincrement NOT NULL, 
                id_model INTEGER,
                description CHAR(200) NOT NULL, 
                model_type CHAR(1) NOT NULL
            );
        SQL,
        'modelsWithRelationFactories' => <<<SQL
            CREATE TABLE modelsWithRelationFactories ( 
                id INTEGER PRIMARY KEY autoincrement NOT NULL , 
                name CHAR(60) NOT NULL
            );
        SQL,
    ];
    /** @var array<string, array<int, array<string,mixed>>> */
    private const array SEED_DATA = [
        ModelA::TABLE                     => [
            1 => [
                'model_a_id' => 1,
                'name'       => 'model1',
                'age'        => 20,
                'verified'   => false,
            ],
            2 => [
                'model_a_id' => 2,
                'name'       => 'model2',
                'age'        => null,
                'verified'   => true,
            ],
        ],
        ModelB::TABLE                     => [
            1 => [
                'model_b_id'  => 1,
                'description' => 'Lorem ipsum',
                'model_type'  => 'A',
                'model_a_id'  => 1,
            ],
            2 => [
                'model_b_id'  => 2,
                'description' => 'Lorem ipsumaaaaa',
                'model_type'  => 'A',
                'model_a_id'  => 1,
            ],
            3 => [
                'model_b_id'  => 3,
                'description' => 'Lorem ipsumbbbbbb',
                'model_type'  => 'C',
                'model_a_id'  => 2,
            ],
            4 => [
                'model_b_id'  => 4,
                'description' => 'Lorem dasmdlsakdnad',
                'model_type'  => 'D',
                'model_a_id'  => null,
            ],
        ],
        ModelC::TABLE                     => [
            1 => [
                'model_c_id' => 1,
                'value0'     => 'value0',
                'value1'     => 'value1',
                'value2'     => 'value2',
            ],
            2 => [
                'model_c_id' => 2,
                'value0'     => 'a',
                'value1'     => 'b',
                'value2'     => 'c',
            ],
        ],
        ModelE::TABLE                     => [
            1 => [
                'model_e_id' => 1,
                'name'       => 'a',
            ],
            2 => [
                'model_e_id' => 2,
                'name'       => 'b',
            ],
            3 => [
                'model_e_id' => 3,
                'name'       => 'c',
            ],
        ],
        ModelD::TABLE                     => [
            1 => [
                'model_d_id' => 1,
                'name'       => 'a',
            ],
            2 => [
                'model_d_id' => 2,
                'name'       => 'b',
            ],
            3 => [
                'model_d_id' => 3,
                'name'       => 'c',
            ],
        ],
        ModelInvalidInstantiate::TABLE    => [
            1 => [
                'id_model' => 1,
            ],
        ],
        'modelsD_modelsE'                 => [
            1 => [
                'model_d_id' => 1,
                'model_e_id' => 1,
            ],
            2 => [
                'model_d_id' => 1,
                'model_e_id' => 2,
            ],
            3 => [
                'model_d_id' => 1,
                'model_e_id' => 3,
            ],
            4 => [
                'model_d_id' => 2,
                'model_e_id' => 1,
            ],
            5 => [
                'model_d_id' => 2,
                'model_e_id' => 3,
            ],
            6 => [
                'model_d_id' => 3,
                'model_e_id' => 1,
            ],
        ],
        ModelWithTimestamps::TABLE        => [
            1 => [
                'id_with_timestamps' => 1,
                'name'               => 'test timestamp',
            ],
        ],
        ModelCB::TABLE                    => [
            1 => [
                'id_model_b' => 1,
                'name'       => 'Parent1',
            ],
            2 => [
                'id_model_b' => 2,
                'name'       => 'Parent2',
            ],
        ],
        ModelCC::TABLE                    => [
            1 => [
                'id_model_c' => 1,
                'id_model_b' => 1,
                'name'       => 'Group1',
            ],
            2 => [
                'id_model_c' => 2,
                'id_model_b' => 1,
                'name'       => 'Group2',
            ],
            3 => [
                'id_model_c' => 3,
                'id_model_b' => 1,
                'name'       => 'Group3',
            ],
            4 => [
                'id_model_c' => 4,
                'id_model_b' => 2,
                'name'       => 'Group4',
            ],
            5 => [
                'id_model_c' => 5,
                'id_model_b' => 2,
                'name'       => 'Group5',
            ],
        ],
        ModelCA::TABLE                    => [
            1 => [
                'id_model_a' => 1,
                'name'       => 'Model1',
                'id_model_b' => 1,
                'id_model_c' => 1,
            ],
            2 => [
                'id_model_a' => 2,
                'name'       => 'Model2',
                'id_model_b' => 1,
                'id_model_c' => 1,
            ],
            3 => [
                'id_model_a' => 3,
                'name'       => 'Model3',
                'id_model_b' => 1,
                'id_model_c' => 1,
            ],
            4 => [
                'id_model_a' => 4,
                'name'       => 'Model4',
                'id_model_b' => 1,
                'id_model_c' => 2,
            ],
            5 => [
                'id_model_a' => 5,
                'name'       => 'Model5',
                'id_model_b' => 1,
                'id_model_c' => 2,
            ],
            6 => [
                'id_model_a' => 6,
                'name'       => 'Model6',
                'id_model_b' => 1,
                'id_model_c' => 3,
            ],
            7 => [
                'id_model_a' => 7,
                'name'       => 'Model7',
                'id_model_b' => 2,
                'id_model_c' => 4,
            ],
            8 => [
                'id_model_a' => 8,
                'name'       => 'Model8',
                'id_model_b' => 2,
                'id_model_c' => 5,
            ],
            9 => [
                'id_model_a' => 9,
                'name'       => 'Model9',
                'id_model_b' => 2,
                'id_model_c' => 6,
            ],
        ],
        QueryModel::TABLE                 => [
            1 => [
                'id_model' => 1,
                'name'     => 'model1',
                'age'      => 20,
            ],
            2 => [
                'id_model' => 2,
                'name'     => 'model2',
                'age'      => 10,
            ],
            3 => [
                'id_model' => 3,
                'name'     => 'model3',
                'age'      => 99,
            ],
            4 => [
                'id_model' => 4,
                'name'     => 'model4',
                'age'      => null,
            ],
        ],
        'data'                            => [
            1 => [
                'id_model'    => 1,
                'description' => 'aasda',
                'model_type'  => 'A',
            ],
            2 => [
                'id_model'    => 2,
                'description' => 'ahoj',
                'model_type'  => 'B',
            ],
            3 => [
                'id_model'    => 3,
                'description' => 'desc',
                'model_type'  => 'C',
            ],
        ],
        ModelWithRelationFactories::TABLE => [
            1 => [
                'id'   => 1,
                'name' => 'model1',
            ],
            2 => [
                'id'   => 2,
                'name' => 'model2',
            ],
        ],
    ];
    protected Cache $cache {
        get => new Cache(new DevNullStorage());
    }
    protected Mapper $mapper {
        get => new Mapper(
            new Serializer(
                [
                    new ArrayDenormalizer(),
                    new DateTimeNormalizer(),
                    new DibiRowNormalizer(),
                    new BackedEnumNormalizer(),
                    new JsonSerializableNormalizer(),
                    new ObjectNormalizer(propertyTypeExtractor: new ReflectionExtractor(),),
                ]
            )
        );
    }

    protected function initDb(string $name = 'dbModels') : void {
        DB::init(
            new Connection(
                $this->cache,
                $this->mapper,
                [
                    'driver'   => "sqlite",
                    'database' => ROOT."tests/tmp/$name.db",
                    'prefix'   => "",
                ]
            )
        );

        // Create all tables
        foreach (self::TABLES as $sql) {
            try {
                DB::getConnection()->query($sql);
            } catch (Exception) {
            }
        }

        // Clear model configs
        $files = glob(TMP_DIR.'models/*');
        assert($files !== false);
        foreach ($files as $file) {
            unlink($file);
        }

        $this->refreshData();
    }

    protected function refreshData() : void {
        foreach (self::TABLES as $table => $sql) {
            DB::delete($table, ['1 = 1']);
        }

        DB::resetAutoIncrement(QueryModel::TABLE);
        DB::resetAutoIncrement('data');

        foreach (self::SEED_DATA as $table => $data) {
            foreach ($data as $id => $row) {
                DB::insert($table, $row);
            }
        }

        ModelRepository::clearInstances();
        $this->cache->clean([$this->cache::All => true]);
    }

    protected function cleanupDb() : void {
        DB::close();
    }

}