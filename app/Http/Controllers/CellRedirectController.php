<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowCellRedirectRequest;
use App\Models\Cell;
use Illuminate\Contracts\View\View;

class CellRedirectController extends Controller
{
    public function __invoke(ShowCellRedirectRequest $request): View
    {
        $rowLetter = $request->input('row_letter');
        $cellNumber = $request->integer('cell_number');
        $flatNumber = $request->integer('flat_number');

        return view('cell-redirect', [
            'label' => Cell::slotLabel($rowLetter, $cellNumber, $flatNumber),
            'appLink' => "warehouseapp://cell?row={$rowLetter}&cell={$cellNumber}&flat={$flatNumber}",
        ]);
    }
}
