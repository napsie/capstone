<?php

function normalizeImportHeader(string $value): string {
    $value = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value));
    return trim(preg_replace('/[^a-z0-9]+/', '_', $value), '_');
}

function parseCsvRecords(string $path): array {
    $handle = fopen($path, 'rb');
    if (!$handle) throw new RuntimeException('Unable to read the CSV file.');
    $first = fgets($handle);
    if ($first === false) { fclose($handle); return []; }
    $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    rewind($handle);
    $rows = [];
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) $rows[] = $row;
    fclose($handle);
    return $rows;
}

function excelCellText(SimpleXMLElement $cell, array $sharedStrings): string {
    $type = (string)$cell['t'];
    if ($type === 'inlineStr') return trim((string)$cell->is->t);
    $value = (string)$cell->v;
    return $type === 's' ? trim((string)($sharedStrings[(int)$value] ?? '')) : trim($value);
}

function parseXlsxRecords(string $path): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('Excel import requires the PHP ZIP extension.');
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('Unable to open the Excel workbook.');
    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $xml = simplexml_load_string($sharedXml);
        if ($xml) foreach ($xml->si as $item) {
            $parts = [];
            if (isset($item->t)) $parts[] = (string)$item->t;
            foreach ($item->r as $run) $parts[] = (string)$run->t;
            $sharedStrings[] = implode('', $parts);
        }
    }
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) throw new RuntimeException('The first worksheet could not be read.');
    $sheet = simplexml_load_string($sheetXml);
    if (!$sheet) throw new RuntimeException('The Excel worksheet is invalid.');
    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $values = [];
        foreach ($row->c as $cell) {
            preg_match('/^[A-Z]+/', (string)$cell['r'], $match);
            $letters = $match[0] ?? 'A';
            $index = 0;
            foreach (str_split($letters) as $letter) $index = ($index * 26) + (ord($letter) - 64);
            $values[$index - 1] = excelCellText($cell, $sharedStrings);
        }
        if ($values) { ksort($values); $rows[] = $values; }
    }
    return $rows;
}

function importRowsToAssociative(array $rows): array {
    if (!$rows) return [];
    $headers = array_map(fn($value) => normalizeImportHeader((string)$value), array_shift($rows));
    $result = [];
    foreach ($rows as $index => $values) {
        $record = [];
        foreach ($headers as $column => $header) if ($header !== '') $record[$header] = trim((string)($values[$column] ?? ''));
        if (count(array_filter($record, fn($value) => $value !== '')) > 0) {
            $record['_row'] = $index + 2;
            $result[] = $record;
        }
    }
    return $result;
}

function normalizeImportDate(string $value): ?string {
    $value = trim($value);
    if ($value === '') return null;
    if (is_numeric($value)) return gmdate('Y-m-d', ((int)$value - 25569) * 86400);
    foreach (['!Y-m-d', '!m/d/Y', '!m/d/y', '!d/m/Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        if ($date) return $date->format('Y-m-d');
    }
    return null;
}

function generateImportToken(PDO $conn): string {
    do {
        $token = 'PRX-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $conn->prepare('SELECT 1 FROM applications WHERE id_number = ? LIMIT 1');
        $stmt->execute([$token]);
    } while ($stmt->fetchColumn());
    return $token;
}
