<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class BiometricDedicatedExport implements FromView, ShouldAutoSize
{
    protected $pdfData;
    protected $viewName;

    public function __construct(array $pdfData, string $viewName)
    {
        $this->pdfData = $pdfData;
        $this->viewName = $viewName;
    }

    public function view(): View
    {
        return view($this->viewName, $this->pdfData);
    }
}
