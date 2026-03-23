<?php declare(strict_types=1);

namespace Coco\SourceWatcherApi\Pipeline;

/**
 * List of available pipeline steps (extractors, transformers, loaders) for the board.
 * Keep in sync with source-watcher-core when adding new steps.
 */
final class StepsConfig
{
    public const TYPE_EXTRACTOR = 'extractor';
    public const TYPE_EXECUTION_EXTRACTOR = 'execution-extractor';
    public const TYPE_TRANSFORMER = 'transformer';
    public const TYPE_LOADER = 'loader';

    public static function getSteps(): array
    {
        return [
            [
                'id' => 'extractor-1',
                'type' => self::TYPE_EXTRACTOR,
                'name' => 'Csv',
                'object' => 'CsvExtractor',
                'description' => 'Extract rows from a CSV file using delimiter, enclosure, and optional column subset.'
            ],
            [
                'id' => 'extractor-2',
                'type' => self::TYPE_EXTRACTOR,
                'name' => 'Database',
                'object' => 'DatabaseExtractor',
                'description' => 'Extract rows from a database table using a configured connection.'
            ],
            [
                'id' => 'extractor-3',
                'type' => self::TYPE_EXECUTION_EXTRACTOR,
                'name' => 'Find Missing',
                'object' => 'FindMissingFromSequenceExtractor',
                'description' => 'Given a numeric sequence, find which values are missing (execution-time extractor).'
            ],
            [
                'id' => 'extractor-4',
                'type' => self::TYPE_EXTRACTOR,
                'name' => 'Json',
                'object' => 'JsonExtractor',
                'description' => 'Extract data from JSON input, optionally selecting specific fields.'
            ],
            [
                'id' => 'extractor-5',
                'type' => self::TYPE_EXTRACTOR,
                'name' => 'Txt',
                'object' => 'TxtExtractor',
                'description' => 'Extract lines from a plain text file as rows.'
            ],
            [
                'id' => 'extractor-6',
                'type' => self::TYPE_EXTRACTOR,
                'name' => 'Tesseract OCR',
                'object' => 'TesseractOcrExtractor',
                'description' => 'Extract text from an image file (PNG, JPEG, TIFF, etc.) using Tesseract OCR. Each text line becomes one row.'
            ],
            [
                'id' => 'extractor-7',
                'type' => self::TYPE_EXTRACTOR,
                'name' => 'PDF',
                'object' => 'PdfExtractor',
                'description' => 'Extract text from any PDF (digital, scanned, or mixed). Uses pdftotext for text-layer pages and Tesseract OCR for image-only pages automatically.'
            ],
            [
                'id' => 'transformer-1',
                'type' => self::TYPE_TRANSFORMER,
                'name' => 'Convert Case',
                'object' => 'ConvertCaseTransformer',
                'description' => 'Change the case of column NAMES (upper, lower, title) without touching the values.'
            ],
            [
                'id' => 'transformer-2',
                'type' => self::TYPE_TRANSFORMER,
                'name' => 'Guess Gender',
                'object' => 'GuessGenderTransformer',
                'description' => 'Add a gender column by guessing gender from a first-name field.'
            ],
            [
                'id' => 'transformer-3',
                'type' => self::TYPE_TRANSFORMER,
                'name' => 'Java',
                'object' => 'JavaTransformer',
                'description' => 'Call custom Java code to transform each row using configured arguments.'
            ],
            [
                'id' => 'transformer-4',
                'type' => self::TYPE_TRANSFORMER,
                'name' => 'Rename Columns',
                'object' => 'RenameColumnsTransformer',
                'description' => 'Rename columns according to a mapping of old_name -> new_name.'
            ],
            [
                'id' => 'loader-1',
                'type' => self::TYPE_LOADER,
                'name' => 'Database',
                'object' => 'DatabaseLoader',
                'description' => 'Load rows into a database table using a configured connector (MySQL, PostgreSQL, SQLite).'
            ],
        ];
    }
}
