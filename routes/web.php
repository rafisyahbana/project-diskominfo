<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return redirect('/dashboard/login');
});

Route::middleware('auth:petugas')->prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard.permohonan.index');
    });

    Route::get('/permohonan', [DashboardController::class, 'index'])->name('permohonan.index');
    Route::get('/permohonan/{id}', [DashboardController::class, 'show'])->name('permohonan.show');
    Route::post('/permohonan/{id}/approve', [DashboardController::class, 'approve'])->name('permohonan.approve');
    Route::post('/permohonan/{id}/reject', [DashboardController::class, 'reject'])->name('permohonan.reject');
    Route::post('/permohonan/{id}/terbitkan', [DashboardController::class, 'terbitkan'])->name('permohonan.terbitkan');
    Route::get('/dokumen/{id}/preview', [DashboardController::class, 'previewDokumen'])->name('dokumen.preview');
});

// Route publik dengan signed URL (untuk warga, tidak perlu login petugas)
Route::get('/surat/{permohonan}/unduh', [DashboardController::class, 'unduhSurat'])
    ->middleware('signed')
    ->name('surat.unduh');

require __DIR__.'/auth.php';
