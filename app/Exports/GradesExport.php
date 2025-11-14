<?php

namespace App\Exports;

use IncadevUns\CoreDomain\Models\Enrollment;
use IncadevUns\CoreDomain\Models\Exam;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Color;

class GradesExport implements FromArray, WithTitle, WithEvents
{
    protected $groupId;
    protected $exams;
    protected $moduleRanges = [];

    public function __construct($groupId)
    {
        $this->groupId = $groupId;
        $this->exams = Exam::where('group_id', $groupId)
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
        $examCount = 0;

        foreach ($this->exams as $index => $exam) {
            if ($currentModule !== $exam->module_id) {
                if ($currentModule !== null) {
                    $this->moduleRanges[] = [
                        'start' => $moduleStart,
                        'end' => $moduleStart + $examCount - 1,
                        'module' => $this->exams[$index - 1]->module
                    ];
                    $moduleStart = $moduleStart + $examCount;
                }
                $currentModule = $exam->module_id;
                $examCount = 1;
            } else {
                $examCount++;
            }
            $moduleHeaders[] = '';
        }

        // Guardar el último módulo
        if ($currentModule !== null) {
            $this->moduleRanges[] = [
                'start' => $moduleStart,
                'end' => $moduleStart + $examCount - 1,
                'module' => $this->exams->last()->module
            ];
        }

        $moduleHeaders[] = '';

        // Segunda fila: Títulos de exámenes
        $examHeaders = ['DNI', 'APELLIDOS Y NOMBRES', 'CORREO'];
        
        foreach ($this->exams as $exam) {
            $examHeaders[] = $exam->title;
        }

        $examHeaders[] = 'NOTA FINAL';

        $data[] = $moduleHeaders;
        $data[] = $examHeaders;

        // Datos de alumnos - ORDENADO POR APELLIDOS Y NOMBRES
        $enrollments = Enrollment::where('group_id', $this->groupId)
            ->with(['user', 'grades', 'result'])
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->orderBy('users.fullname') // ORDENAR POR FULLNAME ASCENDENTE
            ->select('enrollments.*') // Seleccionar solo las columnas de enrollment
            ->get();

        foreach ($enrollments as $enrollment) {
            $row = [
                $enrollment->user->dni ?? 'N/A',
                $enrollment->user->fullname ?? 'N/A',
                $enrollment->user->email ?? 'N/A',
            ];

            foreach ($this->exams as $exam) {
                $grade = $enrollment->grades
                    ->where('exam_id', $exam->id)
                    ->first();
                
                $row[] = $grade ? round($grade->grade, 2) : '-'; // CAMBIADO: number_format por round
            }

            $row[] = $enrollment->result 
                ? round($enrollment->result->final_grade, 2) // CAMBIADO: number_format por round
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
                
                // Establecer altura de filas
                $sheet->getRowDimension(1)->setRowHeight(40);
                $sheet->getRowDimension(2)->setRowHeight(120);
                
                // Fusionar DATOS DEL ALUMNO
                $sheet->mergeCells('A1:C1');
                $sheet->setCellValue('A1', 'DATOS DEL ALUMNO');
                
                // Fusionar celdas de módulos
                foreach ($this->moduleRanges as $range) {
                    $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($range['start']);
                    $endCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($range['end']);
                    
                    $sheet->mergeCells("{$startCol}1:{$endCol}1");
                    $sheet->setCellValue("{$startCol}1", $range['module']->title);
                }
                
                // Fusionar NOTA FINAL
                $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4 + count($this->exams));
                $sheet->mergeCells("{$lastCol}1:{$lastCol}2");
                $sheet->setCellValue("{$lastCol}1", 'NOTA FINAL');
                
                // Configurar anchos de columnas
                // Columnas A, B, C con auto-size
                $sheet->getColumnDimension('A')->setAutoSize(true);
                $sheet->getColumnDimension('B')->setAutoSize(true);
                $sheet->getColumnDimension('C')->setAutoSize(true);
                
                // Columnas de exámenes y nota final con ancho fijo (en caracteres, aproximadamente 100 píxeles = 14 caracteres)
                for ($i = 4; $i <= 4 + count($this->exams); $i++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                    $sheet->getColumnDimension($colLetter)->setWidth(14);
                }
                
                $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4 + count($this->exams));
                
                // Aplicar estilos a la fila 1
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
                
                // Aplicar estilos a la fila 2 con texto girado
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
                        'vertical' => Alignment::VERTICAL_CENTER, // CAMBIADO DE VERTICAL_BOTTOM
                        'wrapText' => true,
                    ],
                ]);
                
                // Aplicar rotación de texto solo a las columnas de exámenes (D en adelante, excepto la última)
                for ($i = 4; $i <= 3 + count($this->exams); $i++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                    $sheet->getStyle("{$colLetter}2")->getAlignment()->setTextRotation(90);
                }
                
                // Aplicar bordes a toda la tabla
                $totalRows = count($this->array());
                $sheet->getStyle("A1:{$lastColLetter}{$totalRows}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // --- INICIO Formato Condicional para NOTA FINAL (Simulado con 3 colores) ---

                // Definir el rango de celdas para las notas finales (desde la fila 3 hasta el final)
                $notaFinalRange = "{$lastColLetter}3:{$lastColLetter}{$totalRows}";
                
                // Estos valores (10.5, 13.5) son ejemplos para una escala de 0-20. 
                // Ajústalos según sea necesario.
                
                // Regla 1: Notas Bajas (p.ej., < 10.5) en Rojo
                $conditional1 = new Conditional();
                $conditional1->setConditionType(Conditional::CONDITION_CELLIS);
                $conditional1->setOperatorType(Conditional::OPERATOR_LESSTHAN);
                $conditional1->addCondition(10.5);
                $conditional1->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setARGB('F8696B'); // Rojo

                // Regla 2: Notas Medias (p.ej., entre 10.5 y 13.5) en Amarillo
                $conditional2 = new Conditional();
                $conditional2->setConditionType(Conditional::CONDITION_CELLIS);
                $conditional2->setOperatorType(Conditional::OPERATOR_BETWEEN);
                $conditional2->addCondition(10.5);
                $conditional2->addCondition(13.5);
                $conditional2->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setARGB('FFEB9C'); // Amarillo

                // Regla 3: Notas Altas (p.ej., > 13.5) en Verde
                $conditional3 = new Conditional();
                $conditional3->setConditionType(Conditional::CONDITION_CELLIS);
                $conditional3->setOperatorType(Conditional::OPERATOR_GREATERTHAN);
                $conditional3->addCondition(13.5);
                $conditional3->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setARGB('63BE7B'); // Verde

                // 3. Aplicar los estilos condicionales al rango de notas
                $sheet->getStyle($notaFinalRange)->setConditionalStyles([$conditional1, $conditional2, $conditional3]);
                
                // --- FIN Formato Condicional ---
            },
        ];
    }

    public function title(): string
    {
        return 'Notas';
    }
}