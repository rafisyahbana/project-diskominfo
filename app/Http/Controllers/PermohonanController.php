<?php

namespace App\Http\Controllers;

use App\Http\Requests\AjukanSuratRequest;
use Illuminate\Http\Request;
use App\Services\PermohonanService;

class PermohonanController extends Controller
{
    protected PermohonanService $permohonanService;

    public function __construct(PermohonanService $permohonanService)
    {
        $this->permohonanService = $permohonanService;
    }

    public function ajukan(AjukanSuratRequest $request)
    {
        $sesiId = $request->input('sesi_id');
        $jenisSurat = $request->input('jenis_surat');
        $dataForm = $request->input('data_form');

        $result = $this->permohonanService->ajukan($sesiId, $jenisSurat, $dataForm);

        $response = ['status' => $result['status']];
        if (isset($result['data'])) {
            $response = array_merge($response, $result['data']);
        }

        return response()->json($response, $result['code']);
    }

    public function show(Request $request, string $id_permohonan)
    {
        $sesiId = $request->query('sesi_id');

        $result = $this->permohonanService->show($sesiId, $id_permohonan);

        $response = ['status' => $result['status']];
        if (isset($result['data']) && $result['code'] == 200) {
            $response = $result['data']; // In show, if success, we just return the data directly
        }

        return response()->json($response, $result['code']);
    }

    public function index(Request $request)
    {
        $sesiId = $request->query('sesi_id');

        $result = $this->permohonanService->index($sesiId);

        $response = ['status' => $result['status']];
        if (isset($result['data']) && $result['code'] == 200) {
            $response = $result['data']; // In index, if success, data contains 'data' array
        }

        return response()->json($response, $result['code']);
    }
}
