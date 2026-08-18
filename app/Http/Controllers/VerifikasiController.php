<?php

namespace App\Http\Controllers;

use App\Http\Requests\MulaiVerifikasiRequest;
use App\Http\Requests\CekOtpRequest;
use App\Services\VerifikasiService;

class VerifikasiController extends Controller
{
    protected VerifikasiService $verifikasiService;

    public function __construct(VerifikasiService $verifikasiService)
    {
        $this->verifikasiService = $verifikasiService;
    }

    public function mulai(MulaiVerifikasiRequest $request)
    {
        $nik = $request->input('nik');
        $no_wa = $request->input('no_wa');

        $result = $this->verifikasiService->mulai($nik, $no_wa);

        $response = ['status' => $result['status']];
        
        if (isset($result['data'])) {
            $response = array_merge($response, $result['data']);
        }

        return response()->json($response, $result['code']);
    }

    public function cek(CekOtpRequest $request)
    {
        $sesiId = $request->input('sesi_id');
        $otp = $request->input('otp');

        $result = $this->verifikasiService->cekOtp($sesiId, $otp);

        $response = ['status' => $result['status']];

        if (isset($result['data'])) {
            $response = array_merge($response, $result['data']);
        }

        return response()->json($response, $result['code']);
    }
}
