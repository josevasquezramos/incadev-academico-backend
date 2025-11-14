<?php

namespace App\Exports;

use IncadevUns\CoreDomain\Models\Enrollment;
use IncadevUns\CoreDomain\Models\ClassSession;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class AttendancesExport implements FromArray, WithTitle, WithEvents
{
    protected $groupId;
    protected $classSessions;
    protected $moduleRanges = [];

    public function __construct($groupId)
    {
        $this->groupId = $groupId;
        $this->classSessions = ClassSession::where('group_id', $groupId)
            ->with('module')
            ->orderBy('module_id')
            ->orderBy('start_time')
            ->get();
    }

    public function array(): array
    {
        $data = [];
        
        // Primera fila: Módulos (agrupados)
        $moduleHeaders = ['', '', ''];
        $currentModule = null;
        $moduleStart = 4;
        $classCount = 0;

        foreach ($this->classSessions as $index => $session) {
            if ($currentModule !== $session->module_id) {
                if ($currentModule !== null) {
                    $this->moduleRanges[] = [
                        'start' => $moduleStart,
                        'end' => $moduleStart + $classCount - 1,
                        'module' => $this->classSessions[$index - 1]->module
                    ];
                    $moduleStart = $moduleStart + $classCount;
                }
                $currentModule = $session->module_id;
                $classCount = 1;
            } else {
                $classCount++;
            }
            $moduleHeaders[] = '';
        }

        // Guardar el último módulo
        if ($currentModule !== null) {
            $this->moduleRanges[] = [
                'start' => $moduleStart,
                'end' => $moduleStart + $classCount - 1,
                'module' => $this->classSessions->last()->module
            ];
        }

        $moduleHeaders[] = '';

        // Segunda fila: Títulos de las sesiones
        $classHeaders = ['DNI', 'APELLIDOS Y NOMBRES', 'CORREO'];
        
        foreach ($this->classSessions as $session) {
            // CAMBIO: Usar el título de la sesión en lugar de un contador
            $classHeaders[] = $session->title ?? 'Clase'; 
        }

        $classHeaders[] = '% ASISTENCIA';

        $data[] = $moduleHeaders;
        $data[] = $classHeaders;

        // Datos de alumnos - ORDENADO POR APELLIDOS Y NOMBRES
        $enrollments = Enrollment::where('group_id', $this->groupId)
            ->with(['user', 'attendances', 'result'])
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->orderBy('users.fullname') // ORDENAR POR FULLNAME ASCENDENTE
            ->select('enrollments.*') // Seleccionar solo las columnas de enrollment
            ->get();

        foreach ($enrollments as $enrollment) {
            $row = [
                $enrollment->user->dni ?? 'N/A',
                // CAMBIO: Estandarizar a fullname
                $enrollment->user->fullname ?? 'N/A', 
                $enrollment->user->email ?? 'N/A',
            ];

            foreach ($this->classSessions as $session) {
                $attendance = $enrollment->attendances
                    ->where('class_session_id', $session->id)
                    ->first();
                
                $row[] = $attendance 
                    ? $this->translateAttendanceStatus($attendance->status->value)
                    : '-';
            }

            // CAMBIO: Guardar como número (round) en lugar de string (number_format)
            $row[] = $enrollment->result 
                ? round($enrollment->result->attendance_percentage, 2)
                : '-';

            $data[] = $row;
        }

        return $data;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Establecer altura de filas
                $sheet->getRowDimension(1)->setRowHeight(40);
                $sheet->getRowDimension(2)->setRowHeight(120);
                
                // 2. Fusionar DATOS DEL ALUMNO
                $sheet->mergeCells('A1:C1');
                $sheet->setCellValue('A1', 'DATOS DEL ALUMNO');
                
                // 3. Fusionar celdas de módulos
                foreach ($this->moduleRanges as $range) {
                    $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($range['start']);
                    $endCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($range['end']);
                    
                    $sheet->mergeCells("{$startCol}1:{$endCol}1");
                    $sheet->setCellValue("{$startCol}1", $range['module']->title);
                }
                
                // 4. Fusionar % ASISTENCIA
                $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4 + count($this->classSessions));
                $sheet->mergeCells("{$lastCol}1:{$lastCol}2");
                $sheet->setCellValue("{$lastCol}1", '% ASISTENCIA');
                
                // 5. Configurar anchos de columnas
                $sheet->getColumnDimension('A')->setAutoSize(true);
                $sheet->getColumnDimension('B')->setAutoSize(true);
                $sheet->getColumnDimension('C')->setAutoSize(true);
                
                // Columnas de sesiones
                for ($i = 4; $i <= 3 + count($this->classSessions); $i++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                    $sheet->getColumnDimension($colLetter)->setWidth(14);
                }
                // Columna de % Asistencia
                $sheet->getColumnDimension($lastCol)->setWidth(18);

                $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4 + count($this->classSessions));
                
                // 6. Aplicar estilos a la fila 1 (Títulos)
                $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4472C4'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);
                
                // 7. Aplicar estilos a la fila 2 (Subtítulos)
                $sheet->getStyle("A2:{$lastColLetter}2")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '5B9BD5'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);
                
                // 8. Aplicar rotación de texto a las sesiones
                for ($i = 4; $i <= 3 + count($this->classSessions); $i++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                    $sheet->getStyle("{$colLetter}2")->getAlignment()->setTextRotation(90);
                }
                
                // 9. Aplicar bordes a toda la tabla
                $totalRows = count($this->array());
                $sheet->getStyle("A1:{$lastColLetter}{$totalRows}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // 10. Formato para la columna % ASISTENCIA
                $percentageRange = "{$lastColLetter}3:{$lastColLetter}{$totalRows}";
                
                // Aplicar formato de número para que muestre 70.00%
                $sheet->getStyle($percentageRange)->getNumberFormat()
                    ->setFormatCode('0.00"%"');

                // 11. Centrar celdas de asistencia (P, A, T, J, -)
                $startAttendColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4);
                $endAttendColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + count($this->classSessions));
                $attendanceCellsRange = "{$startAttendColLetter}3:{$endAttendColLetter}{$totalRows}";
                
                $sheet->getStyle($attendanceCellsRange)->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // --- INICIO Formato Condicional % ASISTENCIA ---
                
                // Regla 1: Desaprobado (< 70) en Rojo
                $conditional1 = new Conditional();
                $conditional1->setConditionType(Conditional::CONDITION_CELLIS);
                $conditional1->setOperatorType(Conditional::OPERATOR_LESSTHAN);
                $conditional1->addCondition(70);
                $conditional1->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setARGB('F8696B'); // Rojo

                // Regla 2: Aprobado (>= 70) en Verde
                $conditional2 = new Conditional();
                $conditional2->setConditionType(Conditional::CONDITION_CELLIS);
                $conditional2->setOperatorType(Conditional::OPERATOR_GREATERTHANOREQUAL);
                $conditional2->addCondition(70);
                $conditional2->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setARGB('63BE7B'); // Verde

                // Aplicar los estilos condicionales
                $sheet->getStyle($percentageRange)->setConditionalStyles([$conditional1, $conditional2]);
                
                // --- FIN Formato Condicional ---
            },
        ];
    }

    public function title(): string
    {
        return 'Asistencias';
    }

    private function translateAttendanceStatus($status): string
    {
        return match($status) {
            'present' => 'P',
            'absent' => 'A',
            'late' => 'T',
            'excused' => 'J',
            default => '-',
        };
    }
}