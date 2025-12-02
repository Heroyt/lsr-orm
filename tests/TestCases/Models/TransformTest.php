<?php
declare(strict_types=1);

namespace TestCases\Models;

use Lsr\Db\DB;
use Mocks\Models\ModelWithTransforms;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TransformTest extends TestCase
{
    use DbHelpers;

    /**
     * @return iterable<array{0:int,1:int}>
     */
    public static function clampProvider(): iterable
    {
        yield [15, 10];
        yield [-5, 0];
        yield [5, 5];
    }

    /**
     * @return iterable<array{0:string,1:string}>
     */
    public static function padProvider(): iterable
    {
        yield ['', '00000000'];
        yield ['test', '0000test'];
        yield ['longerstring', 'longerstring'];
    }

    /**
     * @return iterable<array{0:string,1:string}>
     */
    public static function truncateProvider(): iterable
    {
        yield ['short', 'short'];
        yield ['this is a very long string', 'this is...'];
        yield ['verylongsinglewordstringthatgetstruncatedtoonly10', 'verylon...'];
    }

    /**
     * @return iterable<array{0:string,1:string}>
     */
    public static function trimProvider(): iterable
    {
        yield ['   trimmed   ', 'trimmed'];
        yield ["\n\ttrimmed\n", 'trimmed'];
        yield ['no_trim', 'no_trim'];
    }

    public function setUp(): void
    {
        $this->initDb();

        parent::setUp();
    }

    public function tearDown(): void
    {
        $this->cleanupDb();
        parent::tearDown();
    }

    public function testTransformSave(): void
    {
        $model = new ModelWithTransforms();
        $model->lowercaseName = 'TESTNAME';
        $this->assertTrue($model->save());

        // Check that the value in the model is not changed yet
        $this->assertSame('TESTNAME', $model->lowercaseName);

        // Check that the value in the database is lowercase uppercase
        $dbValue = DB::select($model::TABLE, 'lowercase_name')
            ->where('id_model = %i', $model->id)
            ->fetchSingle(false);
        $this->assertSame('testname', $dbValue);

        $model->fetch(true);
        $this->assertSame('testname', $model->lowercaseName);
    }

    public function testTransformLoad(): void
    {
        $model = new ModelWithTransforms();
        $model->lowercaseLoadedName = 'TESTNAME';
        $this->assertTrue($model->save());

        // Check that the value in the model is not changed yet
        $this->assertSame('TESTNAME', $model->lowercaseLoadedName);

        // Check that the value in the database is still uppercase
        $dbValue = DB::select($model::TABLE, 'lowercase_loaded_name')
            ->where('id_model = %i', $model->id)
            ->fetchSingle(false);
        $this->assertSame('TESTNAME', $dbValue);

        $model->fetch(true);
        $this->assertSame('testname', $model->lowercaseLoadedName);
    }

    #[DataProvider('clampProvider')]
    public function testClampTransform(int $setValue, int $clampedValue): void
    {
        $model = new ModelWithTransforms();
        $model->clampedValue = $setValue;
        $model->clampedValueOnSave = $setValue;
        $model->clampedValueOnLoad = $setValue;
        $this->assertTrue($model->save());

        // Check the values in the model didn't change yet
        $this->assertSame($setValue, $model->clampedValue);
        $this->assertSame($setValue, $model->clampedValueOnSave);
        $this->assertSame($setValue, $model->clampedValueOnLoad);

        // Check values in the database
        $dbValues = DB::select($model::TABLE, 'clamped_value, clamped_value_on_save, clamped_value_on_load')
            ->where('id_model = %i', $model->id)
            ->fetch(false);

        $this->assertSame($clampedValue, (int)$dbValues->clamped_value);
        $this->assertSame($clampedValue, (int)$dbValues->clamped_value_on_save);
        $this->assertSame($setValue, (int)$dbValues->clamped_value_on_load);

        // Fetch again to test onLoad clamp
        $model->fetch(true);
        $this->assertSame($clampedValue, $model->clampedValue);
        $this->assertSame($clampedValue, $model->clampedValueOnSave);
        $this->assertSame($clampedValue, $model->clampedValueOnLoad);
    }

    #[DataProvider('padProvider')]
    public function testPadTransform(string $setValue, string $paddedValue): void
    {
        $model = new ModelWithTransforms();
        $model->paddedValue = $setValue;
        $model->paddedValueOnSave = $setValue;
        $model->paddedValueOnLoad = $setValue;
        $this->assertTrue($model->save());

        // Check the values in the model didn't change yet
        $this->assertSame($setValue, $model->paddedValue);
        $this->assertSame($setValue, $model->paddedValueOnSave);
        $this->assertSame($setValue, $model->paddedValueOnLoad);

        // Check values in the database
        $dbValues = DB::select($model::TABLE, 'padded_value, padded_value_on_save, padded_value_on_load')
            ->where('id_model = %i', $model->id)
            ->fetch(false);

        $this->assertSame($paddedValue, $dbValues->padded_value);
        $this->assertSame($paddedValue, $dbValues->padded_value_on_save);
        $this->assertSame($setValue, $dbValues->padded_value_on_load);

        // Fetch again to test onLoad pad
        $model->fetch(true);
        $this->assertSame($paddedValue, $model->paddedValue);
        $this->assertSame($paddedValue, $model->paddedValueOnSave);
        $this->assertSame($paddedValue, $model->paddedValueOnLoad);
    }

    #[DataProvider('truncateProvider')]
    public function testTruncateTransform(string $setValue, string $truncatedValue): void
    {
        $model = new ModelWithTransforms();
        $model->truncatedValue = $setValue;
        $model->truncatedValueOnSave = $setValue;
        $model->truncatedValueOnLoad = $setValue;
        $this->assertTrue($model->save());

        // Check the values in the model didn't change yet
        $this->assertSame($setValue, $model->truncatedValue);
        $this->assertSame($setValue, $model->truncatedValueOnSave);
        $this->assertSame($setValue, $model->truncatedValueOnLoad);

        // Check values in the database
        $dbValues = DB::select($model::TABLE, 'truncated_value, truncated_value_on_save, truncated_value_on_load')
            ->where('id_model = %i', $model->id)
            ->fetch(false);

        $this->assertSame($truncatedValue, $dbValues->truncated_value);
        $this->assertSame($truncatedValue, $dbValues->truncated_value_on_save);
        $this->assertSame($setValue, $dbValues->truncated_value_on_load);

        // Fetch again to test onLoad truncate
        $model->fetch(true);
        $this->assertSame($truncatedValue, $model->truncatedValue);
        $this->assertSame($truncatedValue, $model->truncatedValueOnSave);
        $this->assertSame($truncatedValue, $model->truncatedValueOnLoad);
    }

    #[DataProvider('trimProvider')]
    public function testTrimTransform(string $setValue, string $trimmedValue): void
    {
        $model = new ModelWithTransforms();
        $model->trimmedValue = $setValue;
        $model->trimmedValueOnSave = $setValue;
        $model->trimmedValueOnLoad = $setValue;
        $this->assertTrue($model->save());

        // Check the values in the model didn't change yet
        $this->assertSame($setValue, $model->trimmedValue);
        $this->assertSame($setValue, $model->trimmedValueOnSave);
        $this->assertSame($setValue, $model->trimmedValueOnLoad);

        // Check values in the database
        $dbValues = DB::select($model::TABLE, 'trimmed_value, trimmed_value_on_save, trimmed_value_on_load')
            ->where('id_model = %i', $model->id)
            ->fetch(false);
        $this->assertSame($trimmedValue, $dbValues->trimmed_value);
        $this->assertSame($trimmedValue, $dbValues->trimmed_value_on_save);
        $this->assertSame($setValue, $dbValues->trimmed_value_on_load);

        // Fetch again to test onLoad trim
        $model->fetch(true);
        $this->assertSame($trimmedValue, $model->trimmedValue);
        $this->assertSame($trimmedValue, $model->trimmedValueOnSave);
        $this->assertSame($trimmedValue, $model->trimmedValueOnLoad);
    }

}