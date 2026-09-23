<?php

namespace Tests\Unit\Modules\DataIngestion;

use App\Modules\DataIngestion\Infrastructure\Parsers\CsvImportParser;
use App\Modules\DataIngestion\Infrastructure\Parsers\ImportParserInterface;
use App\Modules\DataIngestion\Infrastructure\Parsers\JsonImportParser;
use App\Modules\DataIngestion\Infrastructure\Validators\InventoryRowValidator;
use App\Modules\DataIngestion\Infrastructure\Validators\RowValidatorInterface;
use App\Modules\DataIngestion\Infrastructure\Validators\SalesRowValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImportParserAndValidationTest extends TestCase
{
    // ─── CSV Parser ───────────────────────────────────────────────────────────

    #[Test]
    public function csv_parser_implements_interface(): void
    {
        $parser = new CsvImportParser;
        self::assertInstanceOf(ImportParserInterface::class, $parser);
    }

    #[Test]
    public function csv_parser_yields_rows_from_file(): void
    {
        $csv = "order_number,order_date,channel_code\nORD-001,2026-01-10,online\nORD-002,2026-01-11,retail\n";
        $file = $this->writeTempFile($csv, 'csv');

        $parser = new CsvImportParser;
        $rows = iterator_to_array($parser->parse($file), false);

        self::assertCount(2, $rows);
        self::assertSame('ORD-001', $rows[0]['order_number']);
        self::assertSame('2026-01-10', $rows[0]['order_date']);
        self::assertSame('online', $rows[0]['channel_code']);
        self::assertSame('ORD-002', $rows[1]['order_number']);

        unlink($file);
    }

    #[Test]
    public function csv_parser_handles_empty_file(): void
    {
        $csv = "order_number,order_date\n";
        $file = $this->writeTempFile($csv, 'csv');

        $parser = new CsvImportParser;
        $rows = iterator_to_array($parser->parse($file), false);

        self::assertCount(0, $rows);

        unlink($file);
    }

    #[Test]
    public function csv_parser_trims_whitespace_in_values(): void
    {
        $csv = "order_number,sku\n ORD-001 , SKU-X \n";
        $file = $this->writeTempFile($csv, 'csv');

        $parser = new CsvImportParser;
        $rows = iterator_to_array($parser->parse($file), false);

        self::assertSame('ORD-001', $rows[0]['order_number']);
        self::assertSame('SKU-X', $rows[0]['sku']);

        unlink($file);
    }

    // ─── JSON Parser ──────────────────────────────────────────────────────────

    #[Test]
    public function json_parser_implements_interface(): void
    {
        $parser = new JsonImportParser;
        self::assertInstanceOf(ImportParserInterface::class, $parser);
    }

    #[Test]
    public function json_parser_yields_rows_from_json_lines_file(): void
    {
        $jsonl = '{"order_number":"ORD-001","order_date":"2026-01-10"}'."\n"
               .'{"order_number":"ORD-002","order_date":"2026-01-11"}'."\n";
        $file = $this->writeTempFile($jsonl, 'jsonl');

        $parser = new JsonImportParser;
        $rows = iterator_to_array($parser->parse($file), false);

        self::assertCount(2, $rows);
        self::assertSame('ORD-001', $rows[0]['order_number']);
        self::assertSame('ORD-002', $rows[1]['order_number']);

        unlink($file);
    }

    #[Test]
    public function json_parser_yields_rows_from_json_array_file(): void
    {
        $json = json_encode([
            ['order_number' => 'ORD-001', 'order_date' => '2026-01-10'],
            ['order_number' => 'ORD-002', 'order_date' => '2026-01-11'],
        ]);
        $file = $this->writeTempFile($json, 'json');

        $parser = new JsonImportParser;
        $rows = iterator_to_array($parser->parse($file), false);

        self::assertCount(2, $rows);
        self::assertSame('ORD-001', $rows[0]['order_number']);

        unlink($file);
    }

    #[Test]
    public function json_parser_handles_empty_lines(): void
    {
        $jsonl = '{"order_number":"ORD-001"}'."\n\n".'{"order_number":"ORD-002"}'."\n";
        $file = $this->writeTempFile($jsonl, 'jsonl');

        $parser = new JsonImportParser;
        $rows = iterator_to_array($parser->parse($file), false);

        self::assertCount(2, $rows);

        unlink($file);
    }

    // ─── SalesRowValidator ────────────────────────────────────────────────────

    #[Test]
    public function sales_validator_implements_interface(): void
    {
        self::assertInstanceOf(RowValidatorInterface::class, new SalesRowValidator);
    }

    #[Test]
    public function sales_validator_passes_valid_row(): void
    {
        $validator = new SalesRowValidator;
        $errors = $validator->validate($this->validSalesRow(), 1);
        self::assertEmpty($errors);
    }

    #[Test]
    public function sales_validator_fails_when_order_number_missing(): void
    {
        $row = $this->validSalesRow();
        $row['order_number'] = '';
        $errors = (new SalesRowValidator)->validate($row, 2);
        self::assertNotEmpty($errors);
        self::assertSame('order_number', $errors[0]->field);
    }

    #[Test]
    public function sales_validator_fails_on_invalid_date(): void
    {
        $row = $this->validSalesRow();
        $row['order_date'] = 'not-a-date';
        $errors = (new SalesRowValidator)->validate($row, 3);
        self::assertNotEmpty($errors);
        self::assertSame('order_date', $errors[0]->field);
    }

    #[Test]
    public function sales_validator_fails_when_quantity_zero_or_negative(): void
    {
        $row = $this->validSalesRow();
        $row['quantity'] = '0';
        $errors = (new SalesRowValidator)->validate($row, 4);
        self::assertNotEmpty($errors);
        self::assertSame('quantity', $errors[0]->field);
    }

    #[Test]
    public function sales_validator_fails_when_unit_price_negative(): void
    {
        $row = $this->validSalesRow();
        $row['unit_price'] = '-1';
        $errors = (new SalesRowValidator)->validate($row, 5);
        self::assertNotEmpty($errors);
        self::assertSame('unit_price', $errors[0]->field);
    }

    #[Test]
    public function sales_validator_fails_when_unit_cost_negative(): void
    {
        $row = $this->validSalesRow();
        $row['unit_cost'] = '-0.01';
        $errors = (new SalesRowValidator)->validate($row, 6);
        self::assertNotEmpty($errors);
        self::assertSame('unit_cost', $errors[0]->field);
    }

    #[Test]
    public function sales_validator_accumulates_multiple_errors(): void
    {
        $row = [
            'order_number' => '',
            'order_date' => 'bad',
            'channel_code' => '',
            'region_code' => '',
            'warehouse_code' => '',
            'sku' => '',
            'quantity' => '0',
            'unit_price' => '-1',
            'unit_cost' => '-1',
            'order_status' => '',
        ];
        $errors = (new SalesRowValidator)->validate($row, 7);
        self::assertCount(10, $errors);
    }

    // ─── InventoryRowValidator ────────────────────────────────────────────────

    #[Test]
    public function inventory_validator_implements_interface(): void
    {
        self::assertInstanceOf(RowValidatorInterface::class, new InventoryRowValidator);
    }

    #[Test]
    public function inventory_validator_passes_valid_row(): void
    {
        $errors = (new InventoryRowValidator)->validate($this->validInventoryRow(), 1);
        self::assertEmpty($errors);
    }

    #[Test]
    public function inventory_validator_fails_when_snapshot_date_invalid(): void
    {
        $row = $this->validInventoryRow();
        $row['snapshot_date'] = '2026/01/10';
        $errors = (new InventoryRowValidator)->validate($row, 2);
        self::assertNotEmpty($errors);
        self::assertSame('snapshot_date', $errors[0]->field);
    }

    #[Test]
    public function inventory_validator_fails_when_quantity_on_hand_negative(): void
    {
        $row = $this->validInventoryRow();
        $row['quantity_on_hand'] = '-1';
        $errors = (new InventoryRowValidator)->validate($row, 3);
        self::assertNotEmpty($errors);
        self::assertSame('quantity_on_hand', $errors[0]->field);
    }

    #[Test]
    public function inventory_validator_fails_when_required_field_empty(): void
    {
        $row = $this->validInventoryRow();
        $row['sku'] = '   ';
        $errors = (new InventoryRowValidator)->validate($row, 4);
        self::assertNotEmpty($errors);
        self::assertSame('sku', $errors[0]->field);
    }

    #[Test]
    public function inventory_validator_accumulates_all_errors(): void
    {
        $row = [
            'snapshot_date' => 'bad',
            'warehouse_code' => '',
            'sku' => '',
            'quantity_on_hand' => '-1',
            'quantity_reserved' => '-1',
            'safety_stock' => '-1',
            'reorder_point' => '-1',
            'unit_cost' => '-1',
        ];
        $errors = (new InventoryRowValidator)->validate($row, 5);
        self::assertCount(8, $errors);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function writeTempFile(string $content, string $ext): string
    {
        $file = sys_get_temp_dir().'/test_import_'.uniqid().'.'.$ext;
        file_put_contents($file, $content);

        return $file;
    }

    /** @return array<string, string> */
    private function validSalesRow(): array
    {
        return [
            'order_number' => 'ORD-001',
            'order_date' => '2026-01-10',
            'channel_code' => 'online',
            'region_code' => 'RU-MSK',
            'warehouse_code' => 'WH-01',
            'sku' => 'SKU-XYZ',
            'quantity' => '5',
            'unit_price' => '100.00',
            'unit_cost' => '60.00',
            'order_status' => 'confirmed',
        ];
    }

    /** @return array<string, string> */
    private function validInventoryRow(): array
    {
        return [
            'snapshot_date' => '2026-01-10',
            'warehouse_code' => 'WH-01',
            'sku' => 'SKU-XYZ',
            'quantity_on_hand' => '100',
            'quantity_reserved' => '10',
            'safety_stock' => '5',
            'reorder_point' => '20',
            'unit_cost' => '60.00',
        ];
    }
}
