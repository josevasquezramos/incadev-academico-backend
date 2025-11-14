<?php

namespace App\Exports;

use IncadevUns\CoreDomain\Models\Enrollment;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EnrollmentsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $groupId;

    public function __construct($groupId)
    {
        $this->groupId = $groupId;
    }

    public function query()
    {
        return Enrollment::query()
            ->where('group_id', $this->groupId)
            ->with('user')
            ->orderBy('created_at', 'desc'); // MANTENER EL ORDEN ORIGINAL
    }

    public function headings(): array
    {
        return [
            'DNI',
            'Apellidos y Nombres',
            'Correo',
            'Estado Académico',
            'Estado de Pago',
            'Fecha de Matrícula',
        ];
    }

    public function map($enrollment): array
    {
        return [
            $enrollment->user->dni ?? 'N/A',
            $enrollment->user->fullname ?? 'N/A',
            $enrollment->user->email ?? 'N/A',
            $this->translateAcademicStatus($enrollment->academic_status->value),
            $this->translatePaymentStatus($enrollment->payment_status->value),
            $enrollment->created_at->format('d/m/Y H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Obtener el número total de filas (datos + cabecera)
        $totalRows = $this->query()->count() + 1;
        
        // Aplicar bordes a toda la tabla
        $sheet->getStyle("A1:F{$totalRows}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Ajustar el ancho de las columnas automáticamente
        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'], // Azul
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],
            // Estilo para las filas de datos
            'A2:F' . $totalRows => [
                'alignment' => [
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Matrículas';
    }

    private function translatePaymentStatus($status): string
    {
        return match($status) {
            'pending' => 'Pendiente',
            'paid' => 'Pagado',
            'partially_paid' => 'Parcialmente Pagado',
            'refunded' => 'Reembolsado',
            'cancelled' => 'Cancelado',
            'overdue' => 'Vencido',
            default => $status,
        };
    }

    private function translateAcademicStatus($status): string
    {
        return match($status) {
            'pending' => 'Pendiente',
            'active' => 'Activo',
            'completed' => 'Completado',
            'failed' => 'Reprobado',
            'dropped' => 'Retirado',
            default => $status,
        };
    }
}