<?php
declare(strict_types=1);

namespace TestCases\Models;

use Lsr\Orm\ModelRepository;
use Mocks\Models\ModelFromBase;
use PHPUnit\Framework\TestCase;

class ModelConfigTest extends TestCase
{

    public static function setUpBeforeClass() : void {
        // Clear mode configs
        ModelRepository::$modelConfig = [];
        $files = glob(TMP_DIR.'models/*.php');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    public function testConfig() : void {
        $config = ModelFromBase::getModelConfig();

        $this->assertSame('id_model_from_base', $config->primaryKey);

        // Properties
        $this->assertArrayHasKey('id', $config->properties);
        $this->assertArrayHasKey('name', $config->properties);
        $this->assertArrayHasKey('noDB', $config->properties);
        $this->assertArrayHasKey('virtual', $config->properties);
        $this->assertArrayHasKey('virtualNoDb', $config->properties);

        $this->assertEquals('id', $config->properties['id']['name']);
        $this->assertEquals('name', $config->properties['name']['name']);
        $this->assertEquals('noDB', $config->properties['noDB']['name']);
        $this->assertEquals('virtual', $config->properties['virtual']['name']);
        $this->assertEquals('virtualNoDb', $config->properties['virtualNoDb']['name']);

        $this->assertTrue($config->properties['id']['isPrimaryKey']);
        $this->assertFalse($config->properties['name']['isPrimaryKey']);
        $this->assertFalse($config->properties['noDB']['isPrimaryKey']);
        $this->assertFalse($config->properties['virtual']['isPrimaryKey']);
        $this->assertFalse($config->properties['virtualNoDb']['isPrimaryKey']);

        $this->assertTrue($config->properties['id']['isBuiltin']);
        $this->assertTrue($config->properties['name']['isBuiltin']);
        $this->assertTrue($config->properties['noDB']['isBuiltin']);
        $this->assertTrue($config->properties['virtual']['isBuiltin']);
        $this->assertTrue($config->properties['virtualNoDb']['isBuiltin']);

        $this->assertTrue($config->properties['id']['allowsNull']);
        $this->assertFalse($config->properties['name']['allowsNull']);
        $this->assertFalse($config->properties['noDB']['allowsNull']);
        $this->assertFalse($config->properties['virtual']['allowsNull']);
        $this->assertFalse($config->properties['virtualNoDb']['allowsNull']);

        $this->assertFalse($config->properties['id']['isExtend']);
        $this->assertFalse($config->properties['name']['isExtend']);
        $this->assertFalse($config->properties['noDB']['isExtend']);
        $this->assertFalse($config->properties['virtual']['isExtend']);
        $this->assertFalse($config->properties['virtualNoDb']['isExtend']);

        $this->assertFalse($config->properties['id']['isEnum']);
        $this->assertFalse($config->properties['name']['isEnum']);
        $this->assertFalse($config->properties['noDB']['isEnum']);
        $this->assertFalse($config->properties['virtual']['isEnum']);
        $this->assertFalse($config->properties['virtualNoDb']['isEnum']);

        $this->assertFalse($config->properties['id']['isDateTime']);
        $this->assertFalse($config->properties['name']['isDateTime']);
        $this->assertFalse($config->properties['noDB']['isDateTime']);
        $this->assertFalse($config->properties['virtual']['isDateTime']);
        $this->assertFalse($config->properties['virtualNoDb']['isDateTime']);

        $this->assertFalse($config->properties['id']['instantiate']);
        $this->assertFalse($config->properties['name']['instantiate']);
        $this->assertFalse($config->properties['noDB']['instantiate']);
        $this->assertFalse($config->properties['virtual']['instantiate']);
        $this->assertFalse($config->properties['virtualNoDb']['instantiate']);

        $this->assertTrue($config->properties['id']['noDb']);
        $this->assertFalse($config->properties['name']['noDb']);
        $this->assertTrue($config->properties['noDB']['noDb']);
        $this->assertFalse($config->properties['virtual']['noDb']);
        $this->assertTrue($config->properties['virtualNoDb']['noDb']);

        $this->assertEquals('int', $config->properties['id']['type']);
        $this->assertEquals('string', $config->properties['name']['type']);
        $this->assertEquals('string', $config->properties['noDB']['type']);
        $this->assertEquals('string', $config->properties['virtual']['type']);
        $this->assertEquals('array', $config->properties['virtualNoDb']['type']);

        $this->assertNull($config->properties['id']['relation']);
        $this->assertNull($config->properties['name']['relation']);
        $this->assertNull($config->properties['noDB']['relation']);
        $this->assertNull($config->properties['virtual']['relation']);
        $this->assertNull($config->properties['virtualNoDb']['relation']);

        // Hooks
        $this->assertNotEmpty($config->beforeUpdate);
        $this->assertNotEmpty($config->afterUpdate);
        $this->assertNotEmpty($config->beforeInsert);
        $this->assertNotEmpty($config->afterInsert);
        $this->assertNotEmpty($config->beforeDelete);
        $this->assertNotEmpty($config->afterDelete);
    }

}