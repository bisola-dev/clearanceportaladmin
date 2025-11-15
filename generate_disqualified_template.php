<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Get the remark type from URL parameter
$remarkType = isset($_GET['remark']) ? $_GET['remark'] : 'general';

// Define sheet names and titles based on remark type
$sheetConfig = [
    'no_payment' => [
        'title' => 'List of Disqualified Students',
        'sheet_name' => 'No Payment'
    ],
    'incomplete_payment' => [
        'title' => 'List of Disqualified Students',
        'sheet_name' => 'Incomplete Payment'
    ],
    'general' => [
        'title' => 'List of Disqualified Students',
        'sheet_name' => 'Disqualified Students'
    ]
];

$config = isset($sheetConfig[$remarkType]) ? $sheetConfig[$remarkType] : $sheetConfig['general'];

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set sheet name
$sheet->setTitle($config['sheet_name']);

// Set document properties
$spreadsheet->getProperties()
    ->setCreator('Yaba College of Technology')
    ->setLastModifiedBy('Clearance System')
    ->setTitle($config['title'] . ' Template')
    ->setSubject($config['title'])
    ->setDescription('Template for uploading ' . strtolower($config['title']) . ' with matric numbers');

// Set headers
$sheet->setCellValue('A1', $config['title']);
$sheet->mergeCells('A1:B1');

// Style the main header
$headerStyle = [
    'font' => [
        'bold' => true,
        'size' => 14,
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => [
            'rgb' => 'FFD700',
        ],
    ],
];
$sheet->getStyle('A1:B1')->applyFromArray($headerStyle);

// Set column header
$sheet->setCellValue('A2', 'Matric Number');

// Style column header
$columnHeaderStyle = [
    'font' => [
        'bold' => true,
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => [
            'rgb' => '006400',
        ],
    ],
    'font' => [
        'color' => [
            'rgb' => 'FFFFFF',
        ],
        'bold' => true,
    ],
];
$sheet->getStyle('A2')->applyFromArray($columnHeaderStyle);

// Add just a few sample rows (5 blank rows for matric numbers)
for ($i = 3; $i <= 7; $i++) {
    $sheet->setCellValue('A' . $i, '');
}

// Add sample data for demonstration
$sheet->setCellValue('A3', 'MATRIC001');
$sheet->setCellValue('A4', 'MATRIC002');
$sheet->setCellValue('A5', 'MATRIC003');

// Set column width
$sheet->getColumnDimension('A')->setWidth(20);

// Add borders to the data area
$styleArray = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ],
    ],
];
$sheet->getStyle('A1:A7')->applyFromArray($styleArray);

// Add instructions
$sheet->setCellValue('C2', 'Instructions:');
$sheet->setCellValue('C3', '1. Enter matric numbers in column A starting from row 3');
$sheet->setCellValue('C4', '2. Each matric number should be in a separate row');
$sheet->setCellValue('C5', '3. Add more rows as needed by copying and pasting');
$sheet->setCellValue('C6', '4. Do not change the header "Matric Number"');
$sheet->setCellValue('C7', '5. Save the file and upload it to the disqualified students page');

$sheet->getStyle('C2:C7')->getFont()->setBold(true);
$sheet->getStyle('C2')->getFont()->setSize(12);

// Set headers for download
$filename = 'disqualified_students_' . $remarkType . '_template.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Content-Transfer-Encoding: binary');
header('Pragma: no-cache');
header('Expires: 0');

// Create Excel file
ob_clean();
flush();
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>