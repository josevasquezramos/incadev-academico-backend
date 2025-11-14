<?php

namespace App\Http\Controllers\Api;

use App\Exports\EnrollmentsExport;
use App\Exports\AttendancesExport;
use App\Exports\GradesExport;
use App\Http\Controllers\Controller;
use IncadevUns\CoreDomain\Models\Group;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    private function authorizeGroupAccess(Request $request, Group $group)
    {
        $user = $request->user();
        $isAdmin = $user->hasRole('admin');
        $isTeacherOfGroup = $group->teachers()->where('user_id', $user->id)->exists();

        if (!$isAdmin && !$isTeacherOfGroup) {
            abort(Response::HTTP_FORBIDDEN, 'No tienes permisos para acceder a los datos de este grupo.');
        }
    }

    public function exportEnrollments(Request $request, Group $group)
    {
        $this->authorizeGroupAccess($request, $group);

        $fileName = 'matriculas_' . $group->name . '_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new EnrollmentsExport($group->id),
            $fileName
        );
    }

    public function exportAttendances(Request $request, Group $group)
    {
        $this->authorizeGroupAccess($request, $group);

        $fileName = 'asistencias_' . $group->name . '_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new AttendancesExport($group->id),
            $fileName
        );
    }

    public function exportGrades(Request $request, Group $group)
    {
        $this->authorizeGroupAccess($request, $group);

        $fileName = 'notas_' . $group->name . '_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new GradesExport($group->id),
            $fileName
        );
    }
}